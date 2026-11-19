# Shift & Sesi Kas: Buka, Tutup, dan Rekonsiliasi

Halaman ini menjelaskan alur kasir dengan shift: siapa bekerja kapan, berapa uang awal di laci, dan bagaimana selisih kas dihitung.

> **Penting:** *shift* hanya definisi jam kerja. Uang laci selalu terikat ke **sesi kas**, yaitu catatan nyata dari saat kas dibuka sampai ditutup.

## 1) Tiga hal yang berbeda

| Istilah | Arti | Di mana diatur |
|---|---|---|
| Shift | Definisi jam, mis. Pagi 08:00–16:00, Sore 16:00–22:00 | Shift & Jadwal → Master Shift |
| Jadwal shift | Siapa yang bertugas pada tanggal dan shift tertentu | Shift & Jadwal → Jadwal Shift |
| Sesi kas | Catatan kas nyata: kas awal, transaksi, kas seharusnya, kas fisik, selisih | Shift & Jadwal → Sesi Kas |

## 2) Siapa boleh apa

| Aksi | Permission |
|---|---|
| Lihat halaman & jadwal shift | `shifts.view` |
| Kelola master shift dan jadwal | `shifts.manage` |
| Buka sesi kas | `shifts.sessions.open` |
| Tutup sesi kas sendiri | `shifts.sessions.close` |
| Tutup sesi kas kasir lain | `shifts.sessions.close.any` |
| Lihat laporan shift | `shifts.reports.view` |

Default role: owner, admin, dan manager mendapat semua; kasir mendapat lihat shift, buka sesi, dan tutup sesi sendiri.

## 3) Buka sesi kas (awal shift)

1. Hitung uang fisik di laci (modal awal).
2. Buka **Shift & Jadwal → Sesi Kas**, pilih shift (opsional), lalu isi **Kas Awal** persis sesuai uang fisik.
3. Simpan. Satu kasir hanya boleh punya **satu sesi terbuka**; tutup dulu sesi sebelumnya.

> **Tips:** jika toko mewajibkan sesi kas sebelum transaksi, aktifkan `shift_required_for_pos`. Tanpa sesi terbuka, POS akan menolak checkout.

## 4) Saat operasional

- Semua transaksi tunai dan non-tunai otomatis tertaut ke sesi kas yang sedang terbuka.
- Transaksi yang dibuat sebelum sesi dibuka (atau dari self-order) tidak punya sesi; angka ini tidak masuk rekonsiliasi.
- Refund tunai mengurangi kas seharusnya.

## 5) Tutup sesi kas (akhir shift)

1. Buka **Sesi Kas**, klik **Tutup** pada sesi yang aktif.
2. Sistem menampilkan **Kas Seharusnya** = kas awal + penjualan tunai − refund tunai.
3. Hitung uang fisik, isi **Kas Fisik** apa adanya (jangan “dibulatkan” agar cocok).
4. Isi catatan jika ada selisih, lalu simpan.

Sistem menyimpan tiga angka saat penutupan:

- **Kas Seharusnya** (expected),
- **Kas Fisik** (closing),
- **Selisih** (variance) = kas fisik − kas seharusnya.

## 6) Membaca selisih

- **0** → kas cocok.
- **Minus** → uang fisik kurang. Cek: kembalian salah, transaksi tunai tidak dicatat, refund tanpa catatan, uang terpakai.
- **Plus** → uang fisik lebih. Cek: transaksi tunai tidak diinput, kas awal salah hitung, uang lama tertinggal di laci.

> **Catatan:** untuk sesi yang sudah ditutup, halaman Sesi Kas dan Laporan Shift menampilkan **angka tersimpan** saat penutupan, bukan hitungan ulang. Jadi angka historis tidak berubah walau ada refund/koreksi transaksi setelahnya. Sesi yang masih terbuka dihitung live.

## 7) Laporan shift

**Shift & Jadwal → Laporan Shift** merangkum per periode:

- jumlah sesi dan sesi yang masih terbuka,
- jumlah transaksi, omzet, dan porsi tunai,
- total kas seharusnya, kas fisik tercatat, dan total selisih.

Filter tersedia: rentang tanggal (hari ini / 7 hari / 30 hari / kustom), karyawan, dan shift. Total di kartu ringkasan selalu sama dengan penjumlahan angka pada baris yang ditampilkan.

## 8) Praktik aman

- Satu kasir satu akun; hindari akun bersama agar atribusi sesi jelas.
- Selalu tutup sesi sebelum pulang, jangan biarkan sesi terbuka berhari-hari.
- Tulis alasan pada catatan bila selisih di luar toleransi (mis. di atas Rp5.000).
- Jadwalkan shift di muka agar kasir tahu jam kerjanya; sistem menolak jadwal yang bentrok untuk orang yang sama.
