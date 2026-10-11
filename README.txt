INVENTORY MANAGEMENT SYSTEM - MINI PROJECT 3
Teknologi: PHP 8+, MySQL/MariaDB, Bootstrap 5, XAMPP

CARA MENJALANKAN:
1. Install dan buka XAMPP.
2. Aktifkan Apache dan MySQL.
3. Ekstrak folder inventory_management_system ke:
   C:\xampp\htdocs\inventory_management_system
4. Buka http://localhost/phpmyadmin
5. Pilih menu Import, pilih file database.sql dari folder proyek, lalu klik Import/Go.
6. Buka http://localhost/inventory_management_system/setup.php
7. Buat akun admin. Password minimal 8 karakter.
8. Login melalui http://localhost/inventory_management_system/
9. Dari menu Pengguna, admin dapat membuat akun staff.
10. Setelah admin dibuat, hapus setup.php dari folder proyek.

AKUN:
Akun admin dibuat sendiri lewat setup.php. Tidak ada password default yang ditanamkan.

HAK AKSES:
- Admin: kategori, produk, supplier, pengguna, transaksi, histori, dashboard.
- Staff: dashboard, transaksi, histori.

FITUR:
- Password di-hash dengan password_hash() dan diverifikasi password_verify().
- PDO prepared statements untuk query berbasis input.
- Output HTML di-escape menggunakan htmlspecialchars.
- Token CSRF pada formulir POST.
- Relasi produk-supplier menggunakan tabel penghubung product_suppliers.
- Transaksi memakai database transaction dan row lock agar stok tidak menjadi minus.
- Pencarian, pagination, sorting produk, dashboard dan indikator stok menipis.

CATATAN:
- Pastikan ekstensi PDO MySQL aktif pada PHP XAMPP.
- Jika MySQL Anda memakai password, ubah variabel $pass di config.php.
- Jika ingin menghapus produk yang sudah memiliki histori transaksi, hapus histori terkait terlebih dahulu atau pertahankan data untuk audit.
- Ini merupakan versi mini project pembelajaran; untuk produksi perlu tambahan hardening, audit log, dan pengujian lebih menyeluruh.
