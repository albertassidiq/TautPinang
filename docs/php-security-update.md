# Tindak lanjut php2shell

Tanggal perubahan: 23 Oktober 2026.

## Ruang lingkup

Advisori PHP tanggal 21 Oktober 2026 menyatakan bahwa CVE-2026-109618 dan
CVE-2026-109619 dapat menyebabkan RCE tanpa autentikasi. PHP-FPM juga terdampak.
Perubahan aplikasi, autentikasi, atau konfigurasi saja tidak menambal runtime.

Versi pertama yang diperbaiki menurut advisori: PHP 8.2.35, 8.3.36, 8.4.27,
dan 8.5.12. Project ini tetap memakai cabang 8.3 yang sebelumnya digunakan
Dockerfile, dengan minimum **8.3.36**, untuk membatasi perubahan kompatibilitas.
Upgrade cabang PHP atau framework berikutnya memerlukan pengujian tersendiri.

## Perubahan repository

- Image CLI dan FPM-NGINX dikunci ke PHP 8.3.36 dengan digest multi-architecture.
- Composer menolak PHP di bawah 8.3.36 serta cabang selain 8.3. Pemeriksaan
  platform dijalankan terhadap runtime build dan produksi yang sebenarnya.
- `session.upload_progress.enabled=0` dimuat melalui PHP INI sebelum parsing
  request sebagai pertahanan tambahan, bukan pengganti upgrade.
- `.dockerignore` mengecualikan `.env`, kredensial Composer, dependensi lokal,
  dan cache agar tidak ikut masuk ke image hasil build.
- Instalasi Composer menunda script Artisan sampai source aplikasi tersedia;
  aset frontend dibangun dari lockfile dengan `npm ci`.

## Deployment berbasis container

1. Catat versi image dan PHP yang sedang berjalan serta siapkan backup database
   dan file unggahan. Simpan konfigurasi dan volume persisten yang sudah ada.
2. Ambil commit perbaikan, lalu bangun image baru dengan base image segar:

   ```sh
   docker build --pull --no-cache -t tautpinang:php-8.3.36 .
   docker run --rm --entrypoint php tautpinang:php-8.3.36 -v
   docker run --rm --entrypoint php-fpm tautpinang:php-8.3.36 -v
   docker run --rm --entrypoint composer tautpinang:php-8.3.36 check-platform-reqs --no-dev
   ```

3. Deploy image baru melalui platform yang biasa dipakai, dengan environment,
   port, jaringan, database, dan volume unggahan yang sama. Gunakan
   `APP_ENV=production` dan `APP_DEBUG=false`. Jangan jalankan migrasi reset.
4. Ganti seluruh container PHP-FPM dan restart/recreate semua queue worker,
   scheduler, serta instance/replica lain yang memakai runtime lama.
5. Periksa **container yang benar-benar melayani trafik**, bukan hanya image baru:

   ```sh
   docker exec <container-aplikasi> php -v
   docker exec <container-aplikasi> php-fpm -v
   docker exec <container-aplikasi> composer check-platform-reqs --no-dev
   docker exec <container-aplikasi> php-fpm -i
   ```

   CLI dan FPM harus menunjukkan PHP 8.3.36 atau patch 8.3 lebih baru. Pada
   output FPM, pastikan `session.upload_progress.enabled` bernilai `Off`.
   Periksa juga konfigurasi pool atau mount yang dapat menimpa PHP INI.
6. Uji login, halaman tautan publik, unggah logo, pembuatan dan pengubahan
   tautan, serta queue. Periksa health check dan log error sebelum menyatakan
   deployment selesai. Jangan rollback ke image PHP yang rentan.

Jika menggunakan Docker Compose untuk membangun Dockerfile ini, jalankan
`docker compose build --pull --no-cache <service-aplikasi>` lalu
`docker compose up -d --force-recreate <service-aplikasi>`, dan lakukan langkah
yang sama pada service worker terkait. Sesuaikan nama service di server.

Untuk pembaruan PHP berikutnya, ubah kedua tag dan digest Dockerfile bersama-sama,
perbarui batas Composer bila diperlukan, lalu ulangi build dan verifikasi.

## Deployment VM atau hosting tanpa container

Pilih PHP 8.3.36 atau patch 8.3 lebih baru dari paket vendor resmi. Perbarui
binary yang digunakan web server (Apache/mod_php atau PHP-FPM), CLI, dan worker.
Pastikan virtual host menunjuk ke runtime baru, lalu restart seluruh proses
terkait. `php -v` di terminal saja tidak membuktikan versi web server.

Jalankan `composer install --no-dev --prefer-dist --optimize-autoloader` dan
`composer check-platform-reqs --no-dev` dengan PHP yang baru. Terapkan
`session.upload_progress.enabled=0` pada konfigurasi PHP server sebelum request
diproses, lalu ulangi pengujian aplikasi. Jangan gunakan `--ignore-platform-reqs`
atau `config.platform.php` untuk menyamarkan runtime yang masih rentan.

## Catatan inventaris dan pelaporan

Status runtime server produksi tidak dapat ditentukan dari source repository.
Isi data berikut setelah deployment dan pemeriksaan server selesai:

| Data | Hasil pemeriksaan |
| --- | --- |
| Nama aplikasi / URL / aset | TautPinang / isi sesuai server |
| Runtime dan versi sebelum update | Belum diverifikasi di server |
| Versi CLI dan web SAPI setelah update | Belum diverifikasi di server |
| Commit dan image digest yang dideploy | Isi dari hasil deployment |
| Tanggal, waktu, serta PIC deployment | Isi setelah deployment |
| Semua worker dan replica sudah diganti | Belum diverifikasi di server |
| Hasil uji aplikasi dan pemeriksaan log | Isi setelah pengujian |

Gunakan template BPS-CSIRT yang disebut dalam pemberitahuan untuk pelaporan.
Perubahan repository ini tidak mengirim laporan atau menyatakan server sudah
ditambal.

## Hasil verifikasi repository

Perbaikan runtime pada commit `abebeb43972b091450ec9349a94bc277170e16d0`:

- [GitHub Actions: lulus](https://github.com/albertassidiq/TautPinang/actions/runs/39932202443).
  Image produksi berhasil dibangun dari Dockerfile; versi CLI dan FPM,
  persyaratan platform Composer, PHP INI, serta akses halaman login melalui
  NGINX/PHP-FPM berhasil diperiksa.
- `npm ci` dan `npm run build`: lulus. Build masih mengeluarkan warning lama
  terkait data Browserslist dan konfigurasi `darkMode` Tailwind.
- `composer check-platform-reqs --no-dev`: lulus di PHP 8.3.36; PHP lokal
  8.2.12 ditolak oleh batas versi baru. Uji batas menerima 8.3.36 dan 8.3.37,
  serta menolak versi rentan yang diuji.
- `composer validate --strict --no-check-publish`: manifest dan lockfile
  sinkron; pemeriksaan strict masih memberi warning untuk constraint
  `laravel/socialite: *` yang sudah ada.
- Suite PHPUnit dijalankan dengan PHP 8.3.36, SQLite in-memory, dan kunci
  aplikasi khusus pengujian: **32 test, 22 lulus, 3 gagal, 7 dilewati**.

Test yang masih gagal:

| Test | Hasil |
| --- | --- |
| `Tests\Feature\ExampleTest::test_the_application_returns_a_successful_response` | Mengharapkan HTTP 200, mendapat redirect 302 |
| `Tests\Feature\PasswordConfirmationTest::test_confirm_password_screen_can_be_rendered` | Mengharapkan HTTP 200, mendapat redirect 302 |
| `Tests\Feature\PasswordConfirmationTest::test_password_is_not_confirmed_with_invalid_password` | Session tidak memiliki key `errors` yang diharapkan |

Perbaikan runtime ini tidak mengubah kode aplikasi maupun file test. Hasil
di atas tidak berarti seluruh fungsi aplikasi atau server produksi sudah
terverifikasi; pengujian deployment pada server tetap harus dilakukan.

## Temuan dependensi lain

Audit `composer audit --locked` pada 23 Oktober 2026 menemukan **53 advisori
pada 16 paket**, termasuk advisori dengan severity tinggi pada Laravel,
Guzzle, CommonMark, phpseclib, dan Symfony MIME. Temuan ini berbeda dari dua CVE
runtime PHP di atas. Perubahan ini mempertahankan versi paket pada lockfile;
audit dependensi belum bersih dan pembaruan framework/dependensi perlu
ditindaklanjuti dengan pengujian kompatibilitas aplikasi.

Jalankan kembali `composer audit --locked` untuk daftar dan status terbaru.

## Referensi

- [Advisori resmi PHP](https://www.php.net/archive/2026.php#2026-10-21-5)
- [CVE-2026-109618 / GHSA-w8hw-w9fx-j79w](https://github.com/php/php-src/security/advisories/GHSA-w8hw-w9fx-j79w)
- [CVE-2026-109619 / GHSA-6wmf-4rv5-p3rp](https://github.com/php/php-src/security/advisories/GHSA-6wmf-4rv5-p3rp)
- [Image serversideup/php](https://serversideup.net/open-source/docker-php/docs/getting-started/choosing-an-image)
