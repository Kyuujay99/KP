<?php
// /KP/config/db_connect.php

// 1. Definisikan konstanta atau variabel konfigurasi
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', ''); // Kosongkan jika tidak ada password
define('DB_NAME', 'kerjapraktek');

// 2. Buat koneksi database
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// 3. Periksa koneksi
if ($conn->connect_error) {
    // Hentikan eksekusi dan tampilkan pesan error jika koneksi gagal.
    // Untuk lingkungan produksi, sebaiknya error ini dicatat di log, bukan ditampilkan ke pengguna.
    die("Koneksi Database Gagal: " . $conn->connect_error);
}

// 4. Set karakter set (opsional tapi direkomendasikan)
$conn->set_charset("utf8mb4");

// Variabel $conn sekarang berisi objek koneksi yang siap digunakan oleh file lain.
?>