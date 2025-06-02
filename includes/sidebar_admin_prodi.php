<?php
// /KP/includes/sidebar_admin_prodi.php

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (!isset($base_url)) {
    $base_url = "/KP"; // Sesuaikan jika perlu
}

// $current_page = basename($_SERVER['PHP_SELF']); // Untuk menandai link aktif
?>

<aside class="sidebar-admin-prodi">
    <h3>Menu Admin Prodi</h3>
    <ul>
        <li>
            <a href="<?php echo $base_url; ?>/admin_prodi/dashboard.php">
                Dashboard Admin
            </a>
        </li>
        <li>
            <a href="<?php echo $base_url; ?>/admin_prodi/pengajuan_kp_monitoring.php">
                Monitoring Pengajuan KP
            </a>
        </li>
        <li>
            <a href="<?php echo $base_url; ?>/admin_prodi/pengguna_mahasiswa_kelola.php">
                Kelola Akun Mahasiswa
            </a>
        </li>
        <li>
            <a href="<?php echo $base_url; ?>/admin_prodi/pengguna_dosen_kelola.php">
                Kelola Akun Dosen
            </a>
        </li>
        <li>
            <a href="<?php echo $base_url; ?>/admin_prodi/perusahaan_kelola.php">
                Kelola Data Perusahaan
            </a>
        </li>
        <li>
            <a href="<?php echo $base_url; ?>/admin_prodi/dokumen_verifikasi_list.php">
                Verifikasi Dokumen KP
            </a>
        </li>
        <li>
            <a href="<?php echo $base_url; ?>/admin_prodi/surat_generate_list.php">
                Manajemen & Generate Surat
            </a>
        </li>
        <li>
            <a href="<?php echo $base_url; ?>/admin_prodi/laporan_kp_view.php">
                Laporan & Statistik KP
            </a>
        </li>
        </ul>
</aside>

<style>
    /* Styling untuk sidebar admin prodi, bisa diadaptasi dari sidebar lain */
    /* atau dibuat lebih generik di header.php */
    .sidebar-admin-prodi {
        flex: 0 0 260px; /* Lebar sidebar bisa sedikit berbeda */
        background-color: #f8f9fa;
        padding: 20px;
        border-radius: 8px;
        box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        height: fit-content; /* Tinggi menyesuaikan konten */
    }
    .sidebar-admin-prodi h3 {
        font-size: 1.3em;
        color: #343a40;
        margin-top: 0;
        margin-bottom: 15px;
        padding-bottom: 10px;
        border-bottom: 1px solid #e0e0e0;
    }
    .sidebar-admin-prodi ul {
        list-style: none;
        padding: 0;
        margin: 0;
    }
    .sidebar-admin-prodi ul li a {
        display: block;
        padding: 10px 15px;
        text-decoration: none;
        color: #495057;
        border-radius: 5px;
        margin-bottom: 5px;
        transition: background-color 0.2s ease, color 0.2s ease;
    }
    .sidebar-admin-prodi ul li a:hover,
    .sidebar-admin-prodi ul li a.active { /* Kelas .active ditambahkan via PHP */
        background-color: #28a745; /* Warna tema Admin Prodi (misal: hijau) */
        color: #fff;
    }
</style>