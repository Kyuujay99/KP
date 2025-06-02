<?php
// /KP/includes/sidebar_mahasiswa.php

// Pastikan session dimulai jika belum (opsional, biasanya sudah di halaman pemanggil)
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// $base_url bisa diambil dari session jika diset di header, atau didefinisikan ulang
if (!isset($base_url)) {
    $base_url = "/KP"; // Sesuaikan jika nama folder proyekmu berbeda
}

// Anda bisa menambahkan logika di sini untuk menandai link yang aktif
// berdasarkan halaman saat ini, tapi untuk kesederhanaan kita lewatkan dulu.
// $current_page = basename($_SERVER['PHP_SELF']);
?>

<aside class="sidebar-mahasiswa">
    <h3>Menu Mahasiswa</h3>
    <ul>
        <li>
            <a href="<?php echo $base_url; ?>/mahasiswa/dashboard.php">
                Dashboard Utama
            </a>
        </li>
        <li>
            <a href="<?php echo $base_url; ?>/mahasiswa/profil.php">
                Profil Saya
            </a>
        </li>
        <li>
            <a href="<?php echo $base_url; ?>/mahasiswa/pengajuan_kp_form.php">
                Pengajuan KP Baru
            </a>
        </li>
        <li>
            <a href="<?php echo $base_url; ?>/mahasiswa/pengajuan_kp_view.php">
                Lihat Pengajuan KP
            </a>
        </li>
        <li>
            <a href="<?php echo $base_url; ?>/mahasiswa/bimbingan_view.php">
                Informasi Bimbingan
            </a>
        </li>
        <li>
            <a href="<?php echo $base_url; ?>/mahasiswa/logbook_form.php">
                Isi Logbook Harian
            </a>
        </li>
        <li>
            <a href="<?php echo $base_url; ?>/mahasiswa/logbook_view.php">
                Riwayat Logbook
            </a>
        </li>
        <li>
            <a href="<?php echo $base_url; ?>/mahasiswa/dokumen_upload.php">
                Upload Dokumen
            </a>
        </li>
        <li>
            <a href="<?php echo $base_url; ?>/mahasiswa/dokumen_view.php">
                Dokumen Saya
            </a>
        </li>
        <li>
            <a href="<?php echo $base_url; ?>/mahasiswa/seminar_view.php">
                Informasi Seminar
            </a>
        </li>
        <li>
            <a href="<?php echo $base_url; ?>/mahasiswa/nilai_view.php">
                Lihat Nilai KP
            </a>
        </li>
    </ul>
</aside>