PANDUAN DEPLOY PRODUKSI - SATU by Poolapack
===========================================

Dokumen ini untuk menaikkan aplikasi ke server produksi (VPS/shared hosting).
Untuk setup lokal, lihat README.md.


A. PRASYARAT SERVER
-------------------
1) PHP 8.3 (minimal 8.2) + ekstensi: pdo_mysql, openssl, mbstring, tokenizer,
   ctype, json, xml, curl, fileinfo, gd, bcmath
   PENTING: pastikan CA bundle tersedia agar HTTPS dari PHP/Guzzle bisa diverifikasi
   (dipakai Pusher & Midtrans). Set di php.ini:
     curl.cainfo = "/path/ke/cacert.pem"
     openssl.cafile = "/path/ke/cacert.pem"
   Unduh bundle dari https://curl.se/ca/cacert.pem. Gejala bila belum diset:
   "cURL error 60: SSL certificate ... unable to get local issuer certificate".

   Verifikasi cepat di server (skrip yang sama dipakai launcher lokal):

     php scripts/check-ssl.php

   Output bila sehat:
     [SSL] CA bundle OK (/path/cacert.pem); HTTPS ke api-ap1.pusher.com OK (HTTP 200).

   Exit code: 0 = OK, 1 = CA ada tapi koneksi HTTPS gagal (cek DNS/firewall/egress),
              2 = CA bundle belum diset atau file tidak ada.
   Skrip membaca PUSHER_HOST / PUSHER_APP_CLUSTER dari .env dan tidak mencetak kredensial.
   Di Debian/Ubuntu biasanya cukup: apt install ca-certificates, lalu set
     openssl.cafile = "/etc/ssl/certs/ca-certificates.crt"
     curl.cainfo   = "/etc/ssl/certs/ca-certificates.crt"
2) Composer 2.x
3) MySQL 8 / MariaDB 10.4+
4) Web server (Nginx/Apache/LiteSpeed) dengan document root ke folder `public/`
5) Node.js 20+ hanya jika build aset dilakukan di server
6) HTTPS aktif (wajib untuk Midtrans dan cookie secure)


   Catatan data wilayah: tabel `regions` (provinsi/kabupaten/kecamatan) diisi otomatis
   saat `php artisan migrate` dari `database/data/regions.csv` (sumber Kemendagri).
   Untuk menyegarkan referensi setelah file CSV diperbarui:

     php artisan regions:import

   Tabel ini terpisah dari `member_regions` (poligon wilayah sales) supaya form
   pendaftaran member tidak bergantung pada wilayah yang sudah diimport peta.


B. FILE YANG TIDAK IKUT DI-COMMIT
---------------------------------
- `.env`               -> dibuat di server dari `.env.production.example`
- `vendor/`            -> hasil `composer install`
- `node_modules/`      -> hanya untuk build
- `public/build/`      -> hasil `npm run build` (harus ikut ter-upload)
- `public/hot`         -> JANGAN pernah ada di produksi (penanda Vite dev)
- `storage/*`          -> runtime (log, cache, sesi, file upload)


C. LANGKAH DEPLOY
-----------------
1) Upload/clone kode ke server, lalu masuk ke foldernya.

2) Siapkan environment:
   cp .env.production.example .env
   - Isi APP_KEY:            php artisan key:generate
   - Isi DB_* sesuai database produksi
   - Isi APP_URL=https://domain-anda.com   (PENTING: dipakai untuk link/QR self order)
     Aplikasi otomatis memaksa skema https untuk URL bila APP_URL diawali https.
   - SESSION_SECURE_COOKIE=true
   - APP_DEBUG=false, APP_ENV=production
   - Isi PUSHER_* dan MIDTRANS_* bila dipakai

3) Install dependency tanpa paket dev:
   composer install --no-dev --optimize-autoloader

4) Migrasi database:
   php artisan migrate --force
   php artisan db:seed --class=Database\\Seeders\\RolePermissionSeeder --force

5) Link storage (logo toko, QR, foto produk):
   php artisan storage:link

6) Build aset frontend (di server, atau build di lokal lalu upload folder public/build):
   npm ci
   npm run build
   Hapus sisa dev server bila ada:  rm -f public/hot

7) Optimasi:
   php artisan optimize
   php artisan queue:restart

8) Pastikan permission:
   chmod -R ug+rwX storage bootstrap/cache
   (user web server harus bisa menulis ke keduanya)

9) Buka aplikasi:
   - Landing: https://domain-anda.com
   - Admin:   https://domain-anda.com/admin (atau /signin bila ADMIN_DOMAIN diisi)
   - Setup owner pertama (jika belum ada user): /admin/setup
   - Setelah membuat owner, jalankan lagi seeder role/permission.


D. QUEUE & SCHEDULER
--------------------
1) Queue worker (QUEUE_CONNECTION=database) - pakai Supervisor:

   [program:satu-pos-worker]
   command=php /path/to/project/artisan queue:work --sleep=3 --tries=3 --max-time=3600
   directory=/path/to/project
   autostart=true
   autorestart=true
   user=www-data
   numprocs=1

2) Scheduler (cron tiap menit):

   * * * * * cd /path/to/project && php artisan schedule:run >> /dev/null 2>&1

   Dipakai untuk job terjadwal seperti `vouchers:alert` dan
   `self-order:expire-pending` (membatalkan pesanan tunai yang tidak diambil kasir).


E. SETELAH DOMAIN BERUBAH
-------------------------
Link dan gambar QR meja disimpan saat QR dibuat, jadi TIDAK ikut berubah
otomatis ketika APP_URL diganti. Setelah mengganti domain:

   php artisan qr:regenerate

Lalu cetak ulang QR meja dari menu Meja (download SVG baru).
Opsi satu meja saja: php artisan qr:regenerate --table=1

Link QR sekarang DITANDATANGANI dan punya masa berlaku (parameter `e` dan `s`),
sehingga foto QR lama tidak bisa dipakai selamanya. Pengaturan di `.env`:

   SELF_ORDER_QR_TTL_DAYS=365            # masa berlaku QR (hari)
   SELF_ORDER_TABLE_SESSION_MINUTES=60   # batas waktu sesi setelah scan QR
   SELF_ORDER_PENDING_EXPIRY_MINUTES=20  # auto-batal pesanan tunai pending
   SELF_ORDER_ALLOW_LEGACY_QR=false      # true hanya saat transisi cetak ulang

Catatan rilis fitur ini:
- QR yang sudah dicetak sebelum pembaruan TIDAK bertanda tangan. Setelah
  `php artisan qr:regenerate`, cetak ulang QR fisik. Bila belum sempat,
  set `SELF_ORDER_ALLOW_LEGACY_QR=true` sementara lalu kembalikan ke false.
- Mengganti `APP_KEY` akan membatalkan semua tanda tangan QR (harus regenerate).
- Sesi meja dibatasi waktu: pelanggan yang sudah pulang tidak bisa memesan lagi
  setelah `SELF_ORDER_TABLE_SESSION_MINUTES` habis; harus scan ulang di meja.
- Pesanan self-order tunai yang tidak diambil kasir dalam
  `SELF_ORDER_PENDING_EXPIRY_MINUTES` menjadi `expired` dan hilang dari daftar
  pending POS; poin yang dipakai dikembalikan dan kuota voucher dilepas.


F. MIDTRANS
-----------
- Set Payment Notification URL ke:
  https://domain-anda.com/order/payment/webhook
- Endpoint ini dikecualikan dari CSRF dan dibatasi rate limit.
- Gunakan MIDTRANS_IS_PRODUCTION=true dan kunci produksi saat live.


G. CHECKLIST SETELAH DEPLOY
---------------------------
[ ] APP_ENV=production, APP_DEBUG=false
[ ] APP_URL memakai https dan domain final
[ ] APP_KEY terisi, QR_SECRET konsisten
[ ] SESSION_SECURE_COOKIE=true, HTTPS aktif
[ ] public/hot tidak ada
[ ] `php artisan migrate` sudah dijalankan (membuat tabel `regions` + kolom alamat member)
[ ] `php artisan tinker --execute="echo \App\Models\Region::count();"` menampilkan 7285
[ ] php artisan qr:regenerate sudah dijalankan
[ ] Login admin berhasil, role/permission tersinkron
[ ] Self order dari scan QR meja berhasil (incognito)
[ ] Pembayaran Midtrans (sandbox/production) berhasil + webhook masuk
[ ] php scripts/check-ssl.php menghasilkan exit 0 (CA bundle & HTTPS Pusher OK)
[ ] Email verifikasi member terkirim
[ ] Queue worker & scheduler berjalan (termasuk `self-order:expire-pending` tiap menit)
[ ] QR meja bertanda tangan (`?e=...&s=...`) dan sudah dicetak ulang
[ ] storage:link aktif (logo/QR/foto tampil)
[ ] PWA admin bisa di-install dan ikon baru tampil


H. UPDATE VERSI
---------------
   php artisan down
   git pull            (atau upload kode baru)
   composer install --no-dev --optimize-autoloader
   php artisan migrate --force
   npm ci && npm run build
   rm -f public/hot
   php artisan optimize
   php artisan queue:restart
   php artisan up
