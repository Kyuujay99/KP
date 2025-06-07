<?php
// /KP/logout.php

// Selalu mulai session untuk dapat mengakses dan menghapusnya.
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// 1. Hapus semua variabel session.
$_SESSION = array();

// 2. Hancurkan session.
// Ini akan menghapus semua informasi yang terkait dengan session saat ini.
if (session_destroy()) {
    // 3. Arahkan (redirect) pengguna kembali ke halaman login (index.php) setelah berhasil logout.
    // Tambahkan parameter ?status=logout_success agar bisa menampilkan pesan jika diperlukan.
    header("Location: /KP/index.php?status=logout_success");
    exit(); // Pastikan tidak ada kode lain yang dieksekusi setelah redirect.
} else {
    // Jika karena alasan tertentu session tidak bisa dihancurkan,
    // tetap arahkan ke halaman login, mungkin dengan pesan error.
    header("Location: /KP/index.php?error=logout_failed");
    exit();
}
?>