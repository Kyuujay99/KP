<?php
// /KP/includes/sidebar_dosen.php

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (!isset($base_url)) {
    $base_url = "/KP"; // Sesuaikan jika perlu
}
?>

<aside class="sidebar-dosen">
    <h3>Menu Dosen</h3>
    <ul>
        <li>
            <a href="<?php echo $base_url; ?>/dosen/dashboard.php">
                Dashboard Dosen
            </a>
        </li>
        <li>
            <a href="<?php echo $base_url; ?>/dosen/profil.php">
                Profil Saya
            </a>
        </li>
        <li>
            <a href="<?php echo $base_url; ?>/dosen/pengajuan_list.php">
                Verifikasi Pengajuan KP
            </a>
        </li>
        <li>
            <a href="<?php echo $base_url; ?>/dosen/bimbingan_mahasiswa_list.php">
                Mahasiswa Bimbingan
            </a>
        </li>
        <li>
            <a href="<?php echo $base_url; ?>/dosen/logbook_verifikasi_list.php">
                Verifikasi Logbook
            </a>
        </li>
        <li>
            <a href="<?php echo $base_url; ?>/dosen/nilai_input_list.php">
                Input & Kelola Nilai
            </a>
        </li>
        <li>
            <a href="<?php echo $base_url; ?>/dosen/seminar_jadwal_list.php">
                Jadwal & Penilaian Seminar
            </a>
        </li>
        </ul>
</aside>

<style>
    /* Anda bisa meng-copy styling dari .sidebar-mahasiswa dan mengganti nama kelasnya, */
    /* atau membuat kelas CSS yang lebih generik untuk sidebar di header.php */
    .sidebar-dosen {
        flex: 0 0 240px;
        background-color: #f8f9fa;
        padding: 20px;
        border-radius: 8px;
        box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        height: fit-content;
    }
    .sidebar-dosen h3 {
        font-size: 1.3em;
        color: #343a40;
        margin-top: 0;
        margin-bottom: 15px;
        padding-bottom: 10px;
        border-bottom: 1px solid #e0e0e0;
    }
    .sidebar-dosen ul {
        list-style: none;
        padding: 0;
        margin: 0;
    }
    .sidebar-dosen ul li a {
        display: block;
        padding: 10px 15px;
        text-decoration: none;
        color: #495057;
        border-radius: 5px;
        margin-bottom: 5px;
        transition: background-color 0.2s ease, color 0.2s ease;
    }
    .sidebar-dosen ul li a:hover,
    .sidebar-dosen ul li a.active { /* Kelas .active bisa ditambahkan dengan PHP */
        background-color: #007bff; /* Warna tema dosen bisa berbeda */
        color: #fff;
    }
</style>