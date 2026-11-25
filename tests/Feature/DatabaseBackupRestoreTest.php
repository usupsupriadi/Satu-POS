<?php

use App\Services\Backup\DatabaseBackupService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;

it('mencadangkan dan memulihkan database ke database kosong', function () {
    $config = config('database.connections.mysql');
    $source = 'satu_pos_backup_test_src';
    $target = 'satu_pos_backup_test_dst';
    $fresh = 'satu_pos_backup_test_fresh';
    $directory = storage_path('app/backups/testing-'.Str::random(8));
    $pruneDirectory = null;

    $connect = function (?string $database = null) use ($config): PDO {
        $dsn = sprintf('mysql:host=%s;port=%s', $config['host'] ?? '127.0.0.1', $config['port'] ?? 3306);

        if ($database !== null) {
            $dsn .= ';dbname='.$database;
        }

        return new PDO($dsn, (string) $config['username'], (string) $config['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
    };

    try {
        $server = $connect();
        $service = new DatabaseBackupService('mysql');
        $service->dumpBinary();
        $service->clientBinary();
    } catch (Throwable $exception) {
        $this->markTestSkipped('MySQL lokal atau binary mysqldump/mysql tidak tersedia: '.$exception->getMessage());
    }

    try {
        foreach ([$source, $target, $fresh] as $database) {
            $server->exec("DROP DATABASE IF EXISTS `{$database}`");
        }

        $server->exec("CREATE DATABASE `{$source}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $server->exec("CREATE DATABASE `{$target}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

        $sourceDb = $connect($source);
        $sourceDb->exec('CREATE TABLE backup_probe (id INT PRIMARY KEY, label VARCHAR(50) NOT NULL)');
        $sourceDb->exec("INSERT INTO backup_probe VALUES (1, 'satu'), (2, 'dua'), (3, 'tiga')");
        $sourceDb->exec('CREATE VIEW backup_probe_view AS SELECT id FROM backup_probe');

        expect(Artisan::call('backup:database', [
            '--connection' => 'mysql',
            '--database' => $source,
            '--path' => $directory,
            '--keep' => 0,
        ]))->toBe(0);

        $archives = glob($directory.'/*.sql.gz') ?: [];
        expect($archives)->toHaveCount(1);

        $meta = json_decode((string) file_get_contents($archives[0].'.json'), true);
        expect($meta['sha256'])->toBe(hash_file('sha256', $archives[0]))
            ->and($meta['tables'])->toBe(1)
            ->and($meta['bytes'])->toBeGreaterThan(0);

        expect(Artisan::call('backup:restore', [
            'file' => $archives[0],
            '--connection' => 'mysql',
            '--database' => $target,
        ]))->toBe(1);

        expect(Artisan::call('backup:restore', [
            'file' => $archives[0],
            '--connection' => 'mysql',
            '--database' => $target,
            '--force' => true,
        ]))->toBe(0);

        expect((int) $connect($target)->query('SELECT COUNT(*) FROM backup_probe')->fetchColumn())->toBe(3)
            ->and((int) $connect($target)->query('SELECT COUNT(*) FROM backup_probe_view')->fetchColumn())->toBe(3);

        // Restore kedua harus menimpa data yang sudah ada, bukan menumpuk.
        $targetDb = $connect($target);
        $targetDb->exec('DELETE FROM backup_probe WHERE id = 1');
        $targetDb->exec("INSERT INTO backup_probe VALUES (99, 'sisa')");

        expect(Artisan::call('backup:restore', [
            'file' => $archives[0],
            '--connection' => 'mysql',
            '--database' => $target,
            '--force' => true,
        ]))->toBe(0);

        expect((int) $connect($target)->query('SELECT COUNT(*) FROM backup_probe')->fetchColumn())->toBe(3);

        // Database tujuan yang belum ada harus dibuat otomatis.
        expect(Artisan::call('backup:restore', [
            'file' => $archives[0],
            '--connection' => 'mysql',
            '--database' => $fresh,
            '--force' => true,
        ]))->toBe(0);

        expect((int) $connect($fresh)->query('SELECT COUNT(*) FROM backup_probe')->fetchColumn())->toBe(3);

        // Retensi: hanya arsip terbaru yang disisakan beserta sidecar-nya.
        $pruneDirectory = storage_path('app/backups/prune-'.Str::random(8));

        mkdir($pruneDirectory, 0750, true);

        foreach (['20260101-000001', '20260101-000002'] as $stamp) {
            file_put_contents("{$pruneDirectory}/backup-probe-{$stamp}.sql.gz", 'x');
            file_put_contents("{$pruneDirectory}/backup-probe-{$stamp}.sql.gz.json", '{}');
        }

        expect($service->prune(1, $pruneDirectory))->toBe(2)
            ->and(glob($pruneDirectory.'/*'))->toHaveCount(2)
            ->and(glob($pruneDirectory.'/*.json'))->toHaveCount(1);
    } finally {
        foreach ([$source, $target, $fresh] as $database) {
            try {
                $server->exec("DROP DATABASE IF EXISTS `{$database}`");
            } catch (Throwable) {
                // Abaikan: database mungkin belum sempat dibuat.
            }
        }

        foreach (glob($directory.'/*') ?: [] as $file) {
            @unlink($file);
        }

        @rmdir($directory);

        if ($pruneDirectory !== null) {
            foreach (glob($pruneDirectory.'/*') ?: [] as $file) {
                @unlink($file);
            }

            @rmdir($pruneDirectory);
        }
    }
});
