# TautPinang

TautPinang adalah aplikasi web untuk membuat dan mengelola **halaman kumpulan
tautan** (link-in-bio). Setiap halaman punya alamat sendiri, misalnya
`https://domain-anda/bps-tanjungpinang`, berisi logo, judul, deskripsi, daftar
tombol tautan, dan QR code. Tampilannya bisa diatur tanpa menulis kode.

Dibangun dengan Laravel 12, Livewire 3, Jetstream, dan Tailwind CSS.

## Daftar isi

- [Fitur](#fitur)
- [Peran pengguna](#peran-pengguna)
- [Halaman aplikasi](#halaman-aplikasi)
- [Teknologi](#teknologi)
- [Menjalankan aplikasi](#menjalankan-aplikasi)
- [Konfigurasi](#konfigurasi)
- [Pengujian](#pengujian)
- [Keamanan](#keamanan)

## Fitur

### Pembuat tautan (Buat Tautan)

- **Live preview**: tampilan halaman diperbarui seketika saat form diubah.
- **Identitas halaman**: judul, deskripsi, dan *slug* unik (huruf, angka, `-`, `_`)
  yang menjadi alamat publik.
- **Logo**: pilih dari URL atau unggah gambar (klik atau *drag & drop*, maks. 2 MB).
- **Daftar tautan**:
  - tambah dan hapus tautan, atur ulang urutan dengan *drag & drop*;
  - aktifkan/nonaktifkan tiap tautan tanpa menghapusnya;
  - pilih **icon Font Awesome** lewat icon picker;
  - warna latar dan teks khusus per tautan.
- **Gaya tombol global**: warna latar, teks, dan border; ketebalan, jenis, dan
  radius border; efek *hover* (gradien, warna, border, dan *glow*). Warna
  mendukung transparansi (RGBA).
- **QR code**: tampilkan QR code halaman, atur posisi, warna, warna border,
  latar, dan tingkat transparansinya.
- **Footer**: dua baris teks footer.
- **Editor gaya JSON** untuk mengatur seluruh gaya sekaligus, serta tombol
  isi data contoh, reset gaya, dan reset form.
- **Unduh HTML**: ekspor halaman sebagai file `.html` statis.

### Halaman publik

- Bisa diakses tanpa login di `/{slug}`.
- Meta tag Open Graph untuk pratinjau saat dibagikan ke media sosial; gambar
  pratinjaunya memakai QR code halaman.
- Tautan nonaktif tidak ditampilkan. Halaman nonaktif atau slug yang tidak ada
  menampilkan halaman 404 khusus.

### Dashboard

Ringkasan milik pengguna: jumlah halaman tautan, jumlah yang aktif, total
tombol tautan, dan lima halaman terbaru.

### Kelola tautan

- Cari, filter (status aktif, jenis logo: unggahan/URL), dan urutkan.
- Lihat detail, edit, **duplikat**, serta aktifkan/nonaktifkan halaman.
- **Aksi massal**: aktifkan, nonaktifkan, atau hapus beberapa halaman sekaligus.
- **Impor dan ekspor** data halaman dalam format JSON (impor maks. 1 MB).
- **Riwayat terhapus** (*soft delete*): pulihkan halaman, atau hapus permanen,
  satu per satu maupun massal.
- Hanya pembuat halaman yang dapat mengubah isinya.

### Akun dan autentikasi

- Registrasi dan login dengan email/password.
- **Login dengan Google** (OAuth). Akun Google dihubungkan ke akun yang
  emailnya sama.
- Autentikasi dua faktor (2FA), ubah profil dan password, keluar dari sesi
  browser lain, serta hapus akun (fitur Jetstream).
- Akun yang dinonaktifkan tidak dapat login, termasuk lewat Google.

### Antarmuka

- **Mode gelap** di halaman login, dashboard, buat/edit tautan, kelola tautan,
  dan manajemen user.
- Halaman Syarat & Ketentuan serta Kebijakan Privasi.

## Peran pengguna

| Kemampuan | User | Admin |
| --- | :---: | :---: |
| Membuat, mengedit, dan menghapus halaman milik sendiri | ✅ | ✅ |
| Melihat dan mengaktifkan/menonaktifkan halaman semua pengguna | — | ✅ |
| Manajemen user: tambah, edit, ubah role, reset password | — | ✅ |
| Mengaktifkan/menonaktifkan akun user | — | ✅ |

Halaman tautan milik akun yang dinonaktifkan (`inactive` atau `disabled`) tidak
dapat diakses publik dan menampilkan 404, sampai akunnya diaktifkan kembali.
Admin tidak dapat menonaktifkan admin lain.

## Halaman aplikasi

| URL | Keterangan | Akses |
| --- | --- | --- |
| `/login`, `/register` | Masuk dan daftar | Publik |
| `/auth/google` | Login dengan Google | Publik |
| `/dashboard` | Ringkasan | Login |
| `/buat-tautan` | Membuat halaman tautan | Login |
| `/kelola-tautan` | Daftar dan pengelolaan halaman | Login |
| `/edit-tautan/{id}` | Mengedit halaman milik sendiri | Login |
| `/user/profile` | Profil, password, 2FA, sesi | Login |
| `/user-management` | Manajemen user | Admin |
| `/{slug}` | Halaman tautan publik | Publik |

## Teknologi

- **Backend**: PHP 8.3.37+, Laravel 12, Livewire 3, Jetstream 5 (Fortify,
  Sanctum), Laravel Socialite (Google)
- **Frontend**: Tailwind CSS, Alpine.js, Vite, Font Awesome
- **Database**: MySQL 8.4 (produksi/Docker), SQLite (pengujian)
- **Runtime**: Docker image `serversideup/php` (NGINX + PHP-FPM)

## Menjalankan aplikasi

### Dengan Docker (disarankan)

Stack NGINX/PHP-FPM dan MySQL dengan volume persisten untuk database dan
unggahan.

```sh
cp .env.docker.example .env.docker   # lalu isi APP_KEY, DB_PASSWORD, DB_ROOT_PASSWORD, Google OAuth
docker compose --env-file .env.docker build --pull
docker compose --env-file .env.docker up -d --wait
docker compose --env-file .env.docker exec app php artisan migrate --force
```

Aplikasi berjalan di `http://localhost:8080`. Panduan lengkap, termasuk cara
memperbarui tanpa kehilangan data, ada di [docs/docker.md](docs/docker.md).

### Tanpa Docker

Butuh PHP **8.3.37 atau lebih baru di cabang 8.3**, Composer, Node.js, dan MySQL.

```sh
composer install
npm ci
cp .env.example .env          # atur koneksi database
php artisan key:generate
php artisan migrate
php artisan storage:link      # agar logo unggahan bisa diakses
npm run build                 # atau `npm run dev` saat pengembangan
php artisan serve
```

### Membuat admin pertama

Pengguna baru selalu ber-role `user`. Daftar lewat `/register`, lalu jadikan
akun itu admin:

```sh
php artisan tinker --execute="App\Models\User::where('email', 'email@anda')->update(['role' => 'admin']);"
```

Di Docker, awali perintah tersebut dengan
`docker compose --env-file .env.docker exec app`.

## Konfigurasi

Variabel penting di `.env` / `.env.docker`:

| Variabel | Keterangan |
| --- | --- |
| `APP_URL` | URL publik aplikasi; dipakai untuk tautan dan QR code |
| `APP_ENV`, `APP_DEBUG` | Gunakan `production` dan `false` di server |
| `DB_*` | Koneksi MySQL |
| `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET` | Kredensial OAuth dari Google Cloud Console |
| `GOOGLE_REDIRECT_URI` | `APP_URL` + `/auth/google/callback` |

## Pengujian

```sh
php vendor/bin/phpunit
```

Atau di Docker, dengan runtime yang sama seperti produksi:

```sh
docker build --pull --target testing -t tautpinang:tests .
docker run --rm --entrypoint php tautpinang:tests vendor/bin/phpunit --colors=never --fail-on-warning
```

Setiap push dicek oleh workflow GitHub Actions
[PHP runtime security](.github/workflows/php-runtime.yml): audit Composer, suite
PHPUnit, build image produksi, verifikasi versi PHP CLI/FPM, migrasi MySQL, dan
smoke test login lewat NGINX/PHP-FPM. Hasil terakhir (26 November 2026): 44 test,
**37 lulus, 7 dilewati, 0 gagal**; audit Composer **0 advisori**.

## Keamanan

### 26 November 2026 — PHP 8.3.37

PHP **8.3.37** (rilis keamanan 19 November 2026) menambal 15 CVE, termasuk
request smuggling PHP-FPM (CVE-2026-129289), use-after-free epoll PHP-FPM
(CVE-2026-129291), serta RCE melalui session (CVE-2026-126714, CVE-2026-115560)
dan cache timezone (CVE-2026-129496). Minimum runtime kini `~8.3.37`.

Pembaruan ini juga:

- menaikkan Laravel ke 12.74.0 dan Symfony ke 7.4.24, serta menutup 20
  advisori Composer baru;
- memperbaiki stored XSS pada icon tautan di halaman publik.

### 23 Oktober 2026 — PHP 8.3.36

Menindaklanjuti **CVE-2026-109618** dan **CVE-2026-109619** (RCE tanpa
autentikasi, juga pada PHP-FPM) dari
[advisori resmi PHP 21 Oktober 2026](https://www.php.net/archive/2026.php#2026-10-21-5),
serta memperbarui seluruh dependensi Composer.

### Catatan deployment

Dockerfile mengunci image CLI dan FPM-NGINX ke versi PHP yang sudah ditambal
beserta digest-nya, dan Composer menolak runtime yang lebih lama.
**Push kode tidak memperbarui server: image harus dibangun ulang dan semua
container/worker lama harus diganti.**

Panduan rebuild, verifikasi PHP-FPM, dan daftar CVE lengkap ada di
[docs/php-security-update.md](docs/php-security-update.md).
