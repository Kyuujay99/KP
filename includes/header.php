<?php
// /KP/includes/header.php (Versi Final dengan Dropdown Klik)

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
if (!isset($page_title)) {
    $page_title = "SIM Kerja Praktek";
}
$base_url = "/KP";
$is_logged_in = isset($_SESSION['user_id']);
$user_nama = $is_logged_in ? htmlspecialchars($_SESSION['user_nama']) : 'Tamu';
$user_role_key = $is_logged_in ? $_SESSION['user_role'] : '';
$user_role_display = $is_logged_in ? htmlspecialchars(ucfirst(str_replace('_', ' ', $_SESSION['user_role']))) : '';

// Definisikan item menu untuk setiap peran
$menu_items = [
    'mahasiswa' => [ 'Dashboard' => '/mahasiswa/dashboard.php', 'Profil Saya' => '/mahasiswa/profil.php', 'Pengajuan KP' => '/mahasiswa/pengajuan_kp_view.php', 'Bimbingan' => '/mahasiswa/bimbingan_view.php', 'Logbook' => '/mahasiswa/logbook_view.php', 'Dokumen' => '/mahasiswa/dokumen_view.php' ],
    'dosen' => [ 'Dashboard' => '/dosen/dashboard.php', 'Profil Saya' => '/dosen/profil.php', 'Verifikasi Pengajuan' => '/dosen/pengajuan_list.php', 'Mahasiswa Bimbingan' => '/dosen/bimbingan_mahasiswa_list.php', 'Jadwal Seminar' => '/dosen/seminar_jadwal_list.php', 'Input Nilai' => '/dosen/nilai_input_list.php' ],
    'admin_prodi' => [ 'Dashboard' => '/admin_prodi/dashboard.php', 'Monitoring Pengajuan' => '/admin_prodi/pengajuan_kp_monitoring.php', 'Kelola Mahasiswa' => '/admin_prodi/pengguna_mahasiswa_kelola.php', 'Kelola Dosen' => '/admin_prodi/pengguna_dosen_kelola.php', 'Kelola Perusahaan' => '/admin_prodi/perusahaan_kelola.php', 'Manajemen Surat' => '/admin_prodi/surat_generate_list.php', 'Verifikasi Dokumen' => '/admin_prodi/dokumen_verifikasi_list.php', 'Laporan KP' => '/admin_prodi/laporan_kp_view.php' ],
    'perusahaan' => [ 'Dashboard' => '/perusahaan/dashboard.php', 'Profil Perusahaan' => '/perusahaan/profil_perusahaan.php', 'Pengajuan Masuk' => '/perusahaan/pengajuan_kp_masuk.php', 'Mahasiswa KP' => '/perusahaan/mahasiswa_kp_list.php', 'Input Penilaian' => '/perusahaan/penilaian_lapangan_list.php' ]
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?> - SIM KP</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #007BFF; --primary-hover: #0056b3; --secondary-color: #6c757d;
            --dark-color: #343a40; --light-color: #f8f9fa; --background-color: #f4f7f9;
            --text-color: #333; --border-color: #dee2e6; --card-shadow: 0 4px 15px rgba(0,0,0,0.06);
            --border-radius: 12px;
        }
        * { box-sizing: border-box; }
        body, h1, h2, h3, h4, h5, h6, p, ul, ol { margin: 0; padding: 0; }
        body { font-family: 'Poppins', sans-serif; line-height: 1.6; background-color: var(--background-color); color: var(--text-color); display: flex; flex-direction: column; min-height: 100vh; }
        .navbar { background-color: #fff; padding: 0.8rem 2rem; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 2px 10px rgba(0,0,0,0.05); position: sticky; top: 0; z-index: 1000; }
        .navbar-brand { font-size: 1.6rem; color: var(--primary-color); text-decoration: none; font-weight: 700; }
        .navbar-nav { list-style: none; display: flex; align-items: center; gap: 0.5rem; }

        /* --- PERUBAHAN CSS UNTUK DROPDOWN --- */
        .dropdown { position: relative; display: inline-block; }
        .dropdown-toggle { background-color: transparent; color: var(--dark-color); padding: 8px 12px; font-size: 0.95em; font-weight: 500; border: 2px solid transparent; cursor: pointer; display: flex; align-items: center; gap: 8px; border-radius: 50px; transition: all 0.3s ease; }
        .dropdown-toggle:hover, .dropdown-toggle.active { background-color: #e9f5ff; border-color: #cfe8ff; }
        .dropdown-toggle .user-avatar-initial { width: 32px; height: 32px; border-radius: 50%; background-color: var(--primary-color); color: white; display: inline-flex; align-items: center; justify-content: center; font-weight: 600; }
        .dropdown-toggle .user-name-nav { font-weight: 600; }
        .dropdown-toggle::after { content: '▼'; font-size: 0.7em; margin-left: 5px; transition: transform 0.3s ease; }
        .dropdown-toggle.active::after { transform: rotate(180deg); }

        .dropdown-menu {
            display: none; /* Defaultnya tersembunyi */
            position: absolute; right: 0; top: calc(100% + 10px);
            background-color: white; min-width: 260px;
            box-shadow: 0 8px 30px rgba(0,0,0,0.1); z-index: 1001;
            border-radius: var(--border-radius); padding: 0.5rem 0;
            animation: fadeIn 0.2s ease-out;
        }
        /* Kelas .show akan ditambahkan oleh JavaScript untuk menampilkan menu */
        .dropdown-menu.show { display: block; }
        .dropdown-menu a { color: #333; padding: 12px 20px; text-decoration: none; display: block; font-size: 0.9em; transition: background-color 0.2s ease, color 0.2s ease; }
        .dropdown-menu a:hover { background-color: var(--primary-color); color: white; }
        .dropdown-divider { height: 1px; margin: 0.5rem 0; background-color: var(--border-color); }
        .dropdown-menu a.logout-link { color: #dc3545; font-weight: 600; }
        .dropdown-menu a.logout-link:hover { background-color: #dc3545; color: white; }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <a href="<?php echo $base_url; ?>/index.php" class="navbar-brand">SISTEM INFORMASI MANAJEMEN - KERJA PRAKTEK</a>
        <ul class="navbar-nav">
            <?php if ($is_logged_in): ?>
                <li class="nav-item">
                    <div class="dropdown">
                        <button class="dropdown-toggle" id="dropdown-button">
                            <span class="user-avatar-initial"><?php echo strtoupper(substr($user_nama, 0, 1)); ?></span>
                            <span class="user-name-nav"><?php echo htmlspecialchars(explode(' ', $user_nama)[0]); ?></span>
                        </button>
                        <div class="dropdown-menu" id="dropdown-menu">
                            <?php if (isset($menu_items[$user_role_key])): ?>
                                <?php foreach ($menu_items[$user_role_key] as $title => $link): ?>
                                    <a href="<?php echo $base_url . $link; ?>"><?php echo $title; ?></a>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            <div class="dropdown-divider"></div>
                            <a href="<?php echo $base_url; ?>/logout.php" class="logout-link">Logout</a>
                        </div>
                    </div>
                </li>
            <?php else: ?>
                <li class="nav-item">
                    <a href="<?php echo $base_url; ?>/index.php" class="btn">Login</a>
                </li>
            <?php endif; ?>
        </ul>
    </nav>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const dropdownButton = document.getElementById('dropdown-button');
        const dropdownMenu = document.getElementById('dropdown-menu');

        if (dropdownButton && dropdownMenu) {
            // Tampilkan/sembunyikan menu saat tombol di-klik
            dropdownButton.addEventListener('click', function(event) {
                event.stopPropagation(); // Mencegah event klik menyebar ke window
                dropdownMenu.classList.toggle('show');
                dropdownButton.classList.toggle('active');
            });

            // Sembunyikan menu saat mengklik di luar area menu
            window.addEventListener('click', function(event) {
                if (!dropdownButton.contains(event.target) && !dropdownMenu.contains(event.target)) {
                    if (dropdownMenu.classList.contains('show')) {
                        dropdownMenu.classList.remove('show');
                        dropdownButton.classList.remove('active');
                    }
                }
            });
        }
    });
    </script>