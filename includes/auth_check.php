<?php
// /KP/includes/auth_check.php (Versi Sederhana - Pastikan sudah dibuat)

// Pastikan session dimulai. Jika sudah dimulai di halaman pemanggil, ini tidak akan memulai ulang.
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Jika tidak ada 'user_id' dalam session (artinya belum login),
// Arahkan pengguna kembali ke halaman login utama.
if (!isset($_SESSION['user_id'])) {
    // Menggunakan path absolut ke halaman login dari root web server.
    // Ganti '/KP/index.php' jika path proyekmu berbeda.
    header("Location: /KP/index.php");
    exit(); // Hentikan eksekusi script lebih lanjut.
}
?>