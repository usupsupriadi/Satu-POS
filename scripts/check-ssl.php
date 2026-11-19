<?php

/**
 * Pemeriksa awal untuk launcher lokal.
 *
 * Memastikan PHP punya CA bundle (curl.cainfo / openssl.cafile) dan bisa
 * melakukan verifikasi HTTPS ke host Pusher. Exit code:
 *   0 = OK
 *   1 = CA terpasang tetapi koneksi HTTPS gagal
 *   2 = CA bundle belum diset / file tidak ada
 */

$caFile = (string) (ini_get('curl.cainfo') ?: ini_get('openssl.cafile'));

if ($caFile === '') {
    fwrite(STDERR, "[SSL] CA bundle belum diset (curl.cainfo / openssl.cafile kosong di php.ini).\n");
    exit(2);
}

if (! is_file($caFile)) {
    fwrite(STDERR, "[SSL] File CA bundle tidak ditemukan: {$caFile}\n");
    exit(2);
}

$cluster = 'ap1';
$host = '';

$envPath = dirname(__DIR__).'/.env';
if (is_file($envPath)) {
    $env = (string) file_get_contents($envPath);

    if (preg_match('/^PUSHER_APP_CLUSTER=([^\r\n#]+)/m', $env, $m)) {
        $value = trim($m[1], " \t\"'");
        if ($value !== '') {
            $cluster = $value;
        }
    }

    if (preg_match('/^PUSHER_HOST=([^\r\n#]+)/m', $env, $m)) {
        $host = trim($m[1], " \t\"'");
    }
}

if ($host === '') {
    $host = 'api-'.$cluster.'.pusher.com';
}

$ch = curl_init('https://'.$host.'/');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 10,
    CURLOPT_CONNECTTIMEOUT => 8,
]);
curl_exec($ch);
$error = (string) curl_error($ch);
$code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($error !== '') {
    fwrite(STDERR, "[SSL] Gagal verifikasi HTTPS ke {$host}: {$error}\n");
    exit(1);
}

echo "[SSL] CA bundle OK ({$caFile}); HTTPS ke {$host} OK (HTTP {$code}).\n";
exit(0);
