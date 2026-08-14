# KasirFlow (Laravel PWA)

**KasirFlow** — aplikasi kasir berbasis web dengan dukungan **PWA**, **mode offline**, **printer Bluetooth/USB**, **barcode scanner**, **grafik ROI**, dan **API sinkron** (paket berbayar).

## Fitur

- Kasir POS cepat (cari produk / scan barcode)
- Mode offline via IndexedDB + localStorage + Service Worker
- Install PWA dari menu Pengaturan
- Printer thermal ESC/POS 58mm & 80mm (Bluetooth BLE, USB Serial, WebUSB) termasuk Rongta RPP02N
- Barcode scanner keyboard-wedge
- Langganan paket Gratis & Berbayar + **validasi transfer BSI otomatis via email**
- Sinkronisasi transaksi offline → server saat online
- Laporan penjualan & HPP, laporan stok (nilai, menipis, expired)
- Monitor produk expired / hampir expired
- Stock opname (hitung fisik → sesuaikan stok sistem)
- Pembelian / restock produk dari supplier
- Cetak label harga (pricetag)
- Piutang & hutang + laporan laba/rugi
- Dashboard grafik ROI (Return on Investment)
- **API REST v1** (berbayar): produk, penjualan, pembelian, piutang, hutang, stock opname, laporan

## Instalasi (XAMPP)

1. Pastikan project ada di `C:\xampp\htdocs\poskasir`
2. Install dependency & build assets:

```bash
composer install
npm install
npm run build
php artisan migrate --seed
php artisan storage:link
```

3. Akses:
   - `http://localhost/poskasir/public`
   - atau jalankan `php artisan serve` lalu buka `http://127.0.0.1:8000`

## Akun demo

- Toko: `admin@poskasir.test` / `password`
- Developer: `developer@poskasir.test` / `password`

## API Sinkron (Paket Berbayar)

1. Upgrade ke paket Berbayar
2. Buka **Pengaturan → API Sinkron → Buat token API**
3. Gunakan header `Authorization: Bearer {token}`

| Endpoint | Deskripsi |
|----------|-----------|
| `GET /api/v1/sync/pull?since=` | Tarik semua data toko |
| `POST /api/v1/sync/push` | Kirim transaksi offline |
| `GET /api/v1/products` | Daftar produk |
| `GET /api/v1/transactions` | Penjualan |
| `GET /api/v1/purchases` | Pembelian |
| `GET /api/v1/receivables` | Piutang |
| `GET /api/v1/payables` | Hutang |
| `GET /api/v1/stock-opnames` | Stock opname |
| `GET /api/v1/reports/sales` | Laporan penjualan |
| `GET /api/v1/reports/stock` | Laporan stok |
| `GET /api/v1/reports/profit-loss` | Laba rugi |
| `GET /api/v1/reports/roi?days=7` | Grafik ROI |

## Panel developer

Login sebagai developer untuk:
- Monitoring akun berlangganan / gratis / tidak aktif
- Menentukan harga & paket langganan
- Mengatur mode **Online / Offline** dan Google reCAPTCHA

Mode **Offline** (default lokal): captcha tidak ditampilkan.  
Mode **Online** + captcha aktif: reCAPTCHA muncul di login, daftar, dan berlangganan berbayar (otomatis disembunyikan jika perangkat offline).

## Alur offline

1. Login online terlebih dahulu
2. Buka **Pengaturan → Install / Aktifkan Offline**
3. Klik **Install ke perangkat** (PWA)
4. Transaksi saat offline tersimpan lokal, otomatis sync saat online

## Printer

- **USB RPP02N (Windows)**: colok kabel USB ke PC XAMPP, buka **Pengaturan**, mode USB = **Windows RAW**, pilih nama printer, lalu **Tes cetak**
- **Bluetooth**: Chrome Android/Desktop → **Hubungkan Bluetooth** di Kasir
- **USB Serial/COM**: hanya jika Windows membuat port COM (jarang untuk RPP02N)
- Kertas **58mm** (RPP02N / portable) atau **80mm** diatur di Pengaturan
- RPP02N portable: matikan potong kertas otomatis

RPP02N lewat USB adalah printer class Windows, bukan port Serial. Browser tidak bisa menulis langsung; aplikasi mengirim ESC/POS RAW ke spooler Windows.

## Catatan database

Default memakai **SQLite** (`database/database.sqlite`). Untuk MySQL XAMPP, ubah `.env`:

```
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=poskasir
DB_USERNAME=root
DB_PASSWORD=
```

Lalu buat database `poskasir` di phpMyAdmin dan jalankan `php artisan migrate --seed`.

## Validasi langganan otomatis (email BSI)

Transfer ke rekening BSI divalidasi otomatis dari notifikasi email `NotifikasiKredit` dari `BSICenter@bankbsi.co.id`.

### Alur pembayaran

1. Pemilik toko klik **Berlangganan** → pilih **Transfer bank**
2. Sistem menampilkan **kode unit** (100–999) dan nominal transfer = harga paket + kode unit
3. Setelah transfer, BSI mengirim email ke inbox Gmail yang dikonfigurasi
4. Command `subscription:verify-payments` (setiap menit via scheduler) atau tombol **Cek sekarang** memvalidasi nominal
5. Langganan aktif otomatis; nomor transaksi BSI disimpan agar tidak diproses dua kali

### Setup Gmail IMAP

1. Aktifkan **2-Step Verification** di akun Google
2. Buat **App Password** untuk Mail → salin ke `.env`:

```
SUBSCRIPTION_IMAP_USERNAME=amzhadigitalnusantara@gmail.com
SUBSCRIPTION_IMAP_PASSWORD=xxxx xxxx xxxx xxxx
SUBSCRIPTION_BANK_ACCOUNT=1234567890
```

3. Aktifkan ekstensi **php_imap** di XAMPP (`php.ini`: hapus `;` di depan `extension=imap`, restart Apache)
4. Jalankan scheduler (production) atau cek manual:

```bash
php artisan subscription:verify-payments
```

Untuk development lokal, jalankan scheduler:

```bash
php artisan schedule:work
```
