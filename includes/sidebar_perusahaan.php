<?php
// /KP/includes/sidebar_perusahaan.php

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (!isset($base_url)) {
    $base_url = "/KP"; // Sesuaikan jika nama folder proyekmu berbeda
}

// $current_page = basename($_SERVER['PHP_SELF']); // Untuk menandai link aktif
?>

<aside class="sidebar-perusahaan">
    <h3>Menu Perusahaan</h3>
    <ul>
        <li>
            <a href="<?php echo $base_url; ?>/perusahaan/dashboard.php">
                Dashboard Perusahaan
            </a>
        </li>
        <li>
            <a href="<?php echo $base_url; ?>/perusahaan/profil_perusahaan.php">
                Profil Perusahaan Saya
            </a>
        </li>
        <li>
            <a href="<?php echo $base_url; ?>/perusahaan/mahasiswa_kp_list.php">
                Mahasiswa KP di Perusahaan
            </a>
        </li>
        <li>
            <a href="<?php echo $base_url; ?>/perusahaan/penilaian_lapangan_list.php">
                Input Penilaian Lapangan
            </a>
        </li>
        </ul>
</aside>

<style>
    /* Styling untuk sidebar perusahaan, bisa diadaptasi atau digeneralisasi */
    .sidebar-perusahaan {
        flex: 0 0 250px; /* Lebar sidebar */
        background-color: #f8f9fa;
        padding: 20px;
        border-radius: 8px;
        box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        height: fit-content;
    }
    .sidebar-perusahaan h3 {
        font-size: 1.3em;
        color: #343a40;
        margin-top: 0;
        margin-bottom: 15px;
        padding-bottom: 10px;
        border-bottom: 1px solid #e0e0e0;
    }
    .sidebar-perusahaan ul {
        list-style: none;
        padding: 0;
        margin: 0;
    }
    .sidebar-perusahaan ul li a {
        display: block;
        padding: 10px 15px;
        text-decoration: none;
        color: #495057;
        border-radius: 5px;
        margin-bottom: 5px;
        transition: background-color 0.2s ease, color 0.2s ease;
    }
    .sidebar-perusahaan ul li a:hover,
    .sidebar-perusahaan ul li a.active { /* Kelas .active ditambahkan via PHP */
        background-color: #17a2b8; /* Warna tema Perusahaan (misal: info/cyan) */
        color: #fff;
    }
</style>