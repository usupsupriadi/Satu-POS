<?php

namespace App\Services\Backup;

use Illuminate\Support\Facades\File;
use PDO;
use RuntimeException;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use Throwable;

class DatabaseBackupService
{
    private ?string $resolvedDumpBinary = null;

    private ?string $resolvedClientBinary = null;

    public function __construct(private ?string $connection = null) {}

    private function connectionName(): string
    {
        return $this->connection ?: (string) config('database.default');
    }

    /**
     * @return array<string, mixed>
     */
    private function connectionConfig(): array
    {
        $name = $this->connectionName();
        $config = config('database.connections.'.$name);

        if (! is_array($config)) {
            throw new RuntimeException("Koneksi database [{$name}] tidak ditemukan.");
        }

        if (! in_array($config['driver'] ?? null, ['mysql', 'mariadb'], true)) {
            throw new RuntimeException(
                'Backup hanya mendukung MySQL/MariaDB. Driver koneksi ['.$name.']: '.($config['driver'] ?? '-')
            );
        }

        return $config;
    }

    private function databaseName(): string
    {
        $database = (string) ($this->connectionConfig()['database'] ?? '');

        if ($database === '') {
            throw new RuntimeException('Nama database belum diisi pada konfigurasi koneksi.');
        }

        return $database;
    }

    private function directory(?string $path = null): string
    {
        $directory = $path ?: (string) config('backup.path', storage_path('app/backups/database'));

        File::ensureDirectoryExists($directory, 0750);

        return rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $directory), DIRECTORY_SEPARATOR);
    }

    /**
     * Membuat arsip dump database.
     *
     * @return array{database: string, path: string, bytes: int, sha256: string, tables: int, created_at: string}
     */
    public function dump(?string $database = null, ?string $directory = null): array
    {
        $database = $database ?: $this->databaseName();
        $directory = $this->directory($directory);

        $stamp = now()->format('Ymd-His');
        $slug = preg_replace('/[^A-Za-z0-9._-]/', '-', $database) ?: 'database';

        $rawPath = $directory.DIRECTORY_SEPARATOR.'backup-'.$slug.'-'.$stamp.'.sql';
        $archivePath = $rawPath.'.gz';

        $this->deleteIfExists($rawPath);
        $this->deleteIfExists($archivePath);
        $this->deleteIfExists($archivePath.'.json');

        $tables = $this->tableCount($this->pdo(), $database);
        $optionFile = $this->writeOptionFile();

        try {
            $process = $this->process([
                $this->dumpBinary(),
                '--defaults-extra-file='.$optionFile,
                '--single-transaction',
                '--quick',
                '--skip-lock-tables',
                '--routines',
                '--triggers',
                '--events',
                '--hex-blob',
                '--no-tablespaces',
                '--default-character-set=utf8mb4',
                '--result-file='.$rawPath,
                $database,
            ]);

            $process->run();

            if (! $process->isSuccessful()) {
                throw new RuntimeException('Dump database gagal: '.$this->errorSummary($process));
            }

            if (! is_file($rawPath) || filesize($rawPath) === 0) {
                throw new RuntimeException('Dump database tidak menghasilkan berkas: '.$rawPath);
            }

            $this->assertDumpComplete($rawPath);
        } catch (Throwable $exception) {
            $this->deleteIfExists($rawPath);
            $this->deleteIfExists($archivePath);

            throw $exception;
        } finally {
            $this->deleteIfExists($optionFile);
        }

        $this->gzipFile($rawPath, $archivePath);
        $this->deleteIfExists($rawPath);

        $bytes = (int) filesize($archivePath);
        $sha256 = (string) hash_file('sha256', $archivePath);

        $meta = [
            'database' => $database,
            'path' => $archivePath,
            'bytes' => $bytes,
            'sha256' => $sha256,
            'tables' => $tables,
            'created_at' => now()->toIso8601String(),
        ];

        File::put(
            $archivePath.'.json',
            json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL
        );

        return $meta;
    }

    /**
     * Memulihkan arsip ke database tujuan. Database dibuat bila belum ada.
     *
     * @return array{database: string, path: string, tables: int, migrations: int, created_database: bool}
     */
    public function restore(string $archive, ?string $database = null, bool $force = false): array
    {
        $archive = $this->resolveArchive($archive);
        $database = $database ?: $this->databaseName();

        if (! $force) {
            throw new RuntimeException(
                'Restore menimpa isi database. Jalankan ulang dengan opsi --force bila sudah yakin.'
            );
        }

        $meta = $this->readMetadata($archive);

        $server = $this->pdo();
        $created = false;

        if (! $this->databaseExists($server, $database)) {
            $server->exec(sprintf(
                'CREATE DATABASE `%s` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci',
                str_replace('`', '``', $database)
            ));
            $created = true;
        }

        $this->dropAllTables($server, $database);

        $rawPath = $this->decompressForRestore($archive);
        $optionFile = $this->writeOptionFile();

        try {
            $handle = fopen($rawPath, 'rb');

            if ($handle === false) {
                throw new RuntimeException('Tidak bisa membuka berkas restore: '.$rawPath);
            }

            $process = $this->process([
                $this->clientBinary(),
                '--defaults-extra-file='.$optionFile,
                '--default-character-set=utf8mb4',
                '--database='.$database,
            ]);

            $process->setInput($handle);
            $process->run();

            fclose($handle);

            if (! $process->isSuccessful()) {
                throw new RuntimeException('Restore database gagal: '.$this->errorSummary($process));
            }
        } finally {
            $this->deleteIfExists($optionFile);

            if ($rawPath !== $archive) {
                $this->deleteIfExists($rawPath);
            }
        }

        $target = $this->pdo($database);
        $tables = $this->tableCount($target, $database);
        $migrations = $this->countRows($target, 'migrations');

        $expected = isset($meta['tables']) ? (int) $meta['tables'] : null;

        if ($expected !== null && $expected !== $tables) {
            throw new RuntimeException(
                "Restore tidak lengkap: arsip berisi {$expected} tabel, hasil restore {$tables} tabel."
            );
        }

        return [
            'database' => $database,
            'path' => $archive,
            'tables' => $tables,
            'migrations' => $migrations,
            'created_database' => $created,
        ];
    }

    /**
     * Menghapus arsip lama sehingga hanya $keep arsip terbaru yang tersisa.
     *
     * @return int Jumlah berkas lama yang dihapus.
     */
    public function prune(int $keep, ?string $directory = null): int
    {
        if ($keep <= 0) {
            return 0;
        }

        $deleted = 0;

        foreach (array_slice($this->archives($directory), $keep) as $archive) {
            foreach ([$archive, $archive.'.json'] as $file) {
                if (is_file($file)) {
                    @unlink($file);
                    $deleted++;
                }
            }
        }

        return $deleted;
    }

    /**
     * @return list<string>
     */
    private function archives(?string $directory = null): array
    {
        $files = array_values(array_filter(
            glob($this->directory($directory).DIRECTORY_SEPARATOR.'*.sql.gz') ?: [],
            'is_file'
        ));

        usort($files, fn (string $a, string $b): int => (filemtime($b) <=> filemtime($a)) ?: strcmp($b, $a));

        return $files;
    }

    public function latestArchive(?string $directory = null): string
    {
        $archives = $this->archives($directory);

        if ($archives === []) {
            throw new RuntimeException('Belum ada arsip backup di '.$this->directory($directory));
        }

        return $archives[0];
    }

    private function resolveArchive(string $file): string
    {
        if (is_file($file)) {
            return $file;
        }

        $candidate = $this->directory().DIRECTORY_SEPARATOR.$file;

        if (is_file($candidate)) {
            return $candidate;
        }

        throw new RuntimeException('Arsip backup tidak ditemukan: '.$file);
    }

    public function dumpBinary(): string
    {
        return $this->resolvedDumpBinary ??= $this->locateBinary(
            'dump',
            ['mariadb-dump', 'mysqldump']
        );
    }

    public function clientBinary(): string
    {
        return $this->resolvedClientBinary ??= $this->locateBinary(
            'client',
            ['mariadb', 'mysql']
        );
    }

    /**
     * @param  list<string>  $names
     */
    private function locateBinary(string $kind, array $names): string
    {
        $override = config('backup.binaries.'.$kind);

        if (is_string($override) && $override !== '') {
            if (is_file($override)) {
                return $override;
            }

            throw new RuntimeException("Binary backup [{$kind}] tidak ditemukan di ".$override);
        }

        $finder = new ExecutableFinder;

        foreach ($names as $name) {
            $found = $finder->find($name);

            if (is_string($found) && is_file($found)) {
                return $found;
            }
        }

        foreach ($this->binaryFallbacks($names) as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        throw new RuntimeException(sprintf(
            'Utilitas %s tidak ditemukan. Pasang paket klien MySQL/MariaDB atau set %s.',
            implode('/', $names),
            $kind === 'dump' ? 'DB_BACKUP_DUMP_BINARY' : 'DB_BACKUP_CLIENT_BINARY'
        ));
    }

    /**
     * @param  list<string>  $names
     * @return list<string>
     */
    private function binaryFallbacks(array $names): array
    {
        $roots = [];
        $extension = PHP_OS_FAMILY === 'Windows' ? '.exe' : '';

        if (PHP_OS_FAMILY === 'Windows') {
            foreach (['MariaDB*', 'MySQL*'] as $pattern) {
                $roots = array_merge($roots, glob('C:\\Program Files\\'.$pattern.'\\bin') ?: []);
                $roots = array_merge($roots, glob('C:\\Program Files (x86)\\'.$pattern.'\\bin') ?: []);
            }

            $roots = array_merge(
                $roots,
                glob('C:\\xampp\\mysql\\bin') ?: [],
                glob('C:\\laragon\\bin\\mysql\\*\\bin') ?: [],
                glob('C:\\laragon\\bin\\mariadb\\*\\bin') ?: []
            );
        } else {
            $roots = ['/usr/bin', '/usr/local/bin', '/usr/local/mysql/bin', '/usr/sbin'];
        }

        $candidates = [];

        foreach ($roots as $root) {
            foreach ($names as $name) {
                $candidates[] = rtrim($root, '\\/').DIRECTORY_SEPARATOR.$name.$extension;
            }
        }

        return $candidates;
    }

    /**
     * @param  list<string>  $command
     */
    private function process(array $command): Process
    {
        return new Process(
            $command,
            base_path(),
            null,
            null,
            (float) config('backup.timeout', 1800)
        );
    }

    private function errorSummary(Process $process): string
    {
        $error = trim($process->getErrorOutput());

        if ($error === '') {
            $error = trim($process->getOutput());
        }

        $error = preg_replace('/password[^\r\n]*/i', 'password=***', $error) ?? $error;

        return $error !== '' ? $error : 'exit code '.$process->getExitCode();
    }

    private function writeOptionFile(): string
    {
        $config = $this->connectionConfig();

        $lines = [
            '[client]',
            'host='.$this->optionValue((string) ($config['host'] ?? '127.0.0.1')),
            'port='.(string) ($config['port'] ?? '3306'),
            'user='.$this->optionValue((string) ($config['username'] ?? '')),
        ];

        if ((string) ($config['password'] ?? '') !== '') {
            $lines[] = 'password='.$this->optionValue((string) $config['password']);
        }

        if ((string) ($config['unix_socket'] ?? '') !== '') {
            $lines[] = 'socket='.$this->optionValue((string) $config['unix_socket']);
        }

        $path = tempnam(sys_get_temp_dir(), 'satu-pos-db-');

        if ($path === false) {
            throw new RuntimeException('Gagal membuat berkas kredensial sementara.');
        }

        File::put($path, implode(PHP_EOL, $lines).PHP_EOL);
        @chmod($path, 0600);

        return $path;
    }

    private function optionValue(string $value): string
    {
        return '"'.str_replace(['\\', '"'], ['\\\\', '\\"'], $value).'"';
    }

    private function pdo(?string $database = null): PDO
    {
        $config = $this->connectionConfig();
        $socket = (string) ($config['unix_socket'] ?? '');

        $dsn = $socket !== ''
            ? 'mysql:unix_socket='.$socket
            : sprintf('mysql:host=%s;port=%s', $config['host'] ?? '127.0.0.1', $config['port'] ?? '3306');

        if ($database !== null) {
            $dsn .= ';dbname='.$database;
        }

        return new PDO($dsn, (string) ($config['username'] ?? ''), (string) ($config['password'] ?? ''), [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 10,
        ]);
    }

    private function databaseExists(PDO $server, string $database): bool
    {
        $statement = $server->prepare('SELECT 1 FROM information_schema.schemata WHERE schema_name = ?');
        $statement->execute([$database]);

        return $statement->fetchColumn() !== false;
    }

    private function tableCount(PDO $pdo, string $database): int
    {
        $statement = $pdo->prepare(
            "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = ? AND table_type = 'BASE TABLE'"
        );
        $statement->execute([$database]);

        return (int) $statement->fetchColumn();
    }

    private function dropAllTables(PDO $server, string $database): void
    {
        $views = array_map(
            'strval',
            $server->query(
                'SELECT table_name FROM information_schema.views WHERE table_schema = '.$server->quote($database)
            )->fetchAll(PDO::FETCH_COLUMN)
        );

        $tables = array_map(
            'strval',
            $server->query(
                'SELECT table_name FROM information_schema.tables WHERE table_schema = '.$server->quote($database)
                ." AND table_type = 'BASE TABLE'"
            )->fetchAll(PDO::FETCH_COLUMN)
        );

        if ($views === [] && $tables === []) {
            return;
        }

        $server->exec('SET FOREIGN_KEY_CHECKS=0');

        $schema = '`'.str_replace('`', '``', $database).'`';

        try {
            foreach ($views as $view) {
                $server->exec('DROP VIEW IF EXISTS '.$schema.'.`'.str_replace('`', '``', $view).'`');
            }

            foreach ($tables as $table) {
                $server->exec('DROP TABLE IF EXISTS '.$schema.'.`'.str_replace('`', '``', $table).'`');
            }
        } finally {
            $server->exec('SET FOREIGN_KEY_CHECKS=1');
        }
    }

    private function countRows(PDO $pdo, string $table): int
    {
        try {
            return (int) $pdo->query('SELECT COUNT(*) FROM `'.$table.'`')->fetchColumn();
        } catch (Throwable) {
            return 0;
        }
    }

    private function assertDumpComplete(string $path): void
    {
        $handle = fopen($path, 'rb');

        if ($handle === false) {
            throw new RuntimeException('Tidak bisa membaca hasil dump: '.$path);
        }

        $tail = '';

        try {
            $size = (int) filesize($path);
            $offset = max(0, $size - 4096);
            fseek($handle, $offset);
            $tail = (string) fread($handle, 4096);
        } finally {
            fclose($handle);
        }

        if (! str_contains($tail, 'Dump completed')) {
            throw new RuntimeException('Hasil dump terpotong (penanda "Dump completed" tidak ditemukan).');
        }
    }

    private function gzipFile(string $source, string $target): void
    {
        $input = fopen($source, 'rb');
        $output = gzopen($target, 'wb9');

        if ($input === false || $output === false) {
            throw new RuntimeException('Gagal mengompresi dump database.');
        }

        try {
            while (! feof($input)) {
                $chunk = fread($input, 1024 * 1024);

                if ($chunk === false) {
                    throw new RuntimeException('Gagal membaca hasil dump saat kompresi.');
                }

                gzwrite($output, $chunk);
            }
        } finally {
            fclose($input);
            gzclose($output);
        }
    }

    private function decompressForRestore(string $archive): string
    {
        if (! str_ends_with(strtolower($archive), '.gz')) {
            return $archive;
        }

        $directory = $this->directory();

        $target = $directory.DIRECTORY_SEPARATOR.'.restore-'.bin2hex(random_bytes(6)).'.sql';
        $input = gzopen($archive, 'rb');

        if ($input === false) {
            throw new RuntimeException('Arsip gzip tidak bisa dibuka: '.$archive);
        }

        $output = fopen($target, 'wb');

        if ($output === false) {
            gzclose($input);
            throw new RuntimeException('Tidak bisa menyiapkan berkas restore sementara.');
        }

        try {
            while (! gzeof($input)) {
                $chunk = gzread($input, 1024 * 1024);

                if ($chunk === false) {
                    throw new RuntimeException('Arsip gzip rusak: '.$archive);
                }

                fwrite($output, $chunk);
            }
        } catch (Throwable $exception) {
            fclose($output);
            gzclose($input);
            $this->deleteIfExists($target);

            throw $exception;
        }

        fclose($output);
        gzclose($input);

        return $target;
    }

    /**
     * Membaca sidecar arsip sekaligus memverifikasi checksum bila tersedia.
     *
     * @return array<string, mixed>|null
     */
    private function readMetadata(string $archive): ?array
    {
        if (! is_readable($archive)) {
            throw new RuntimeException('Arsip backup tidak bisa dibaca: '.$archive);
        }

        $metaPath = $archive.'.json';

        if (! is_file($metaPath)) {
            return null;
        }

        $meta = json_decode((string) file_get_contents($metaPath), true);

        if (! is_array($meta)) {
            return null;
        }

        if (isset($meta['sha256']) && ! hash_equals((string) $meta['sha256'], (string) hash_file('sha256', $archive))) {
            throw new RuntimeException('Checksum arsip tidak cocok; arsip mungkin rusak atau berubah.');
        }

        return $meta;
    }

    private function deleteIfExists(string $path): void
    {
        if (is_file($path)) {
            @unlink($path);
        }
    }
}
