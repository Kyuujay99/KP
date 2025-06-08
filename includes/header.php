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

// Icon mapping untuk menu items
$menu_icons = [
    'Dashboard' => '🏠', 'Profil Saya' => '👤', 'Profil Perusahaan' => '🏢',
    'Pengajuan KP' => '📝', 'Pengajuan Masuk' => '📨', 'Monitoring Pengajuan' => '📊',
    'Verifikasi Pengajuan' => '✅', 'Bimbingan' => '👨‍🏫', 'Mahasiswa Bimbingan' => '👥',
    'Mahasiswa KP' => '🎓', 'Logbook' => '📖', 'Dokumen' => '📄',
    'Verifikasi Dokumen' => '📋', 'Jadwal Seminar' => '📅', 'Input Nilai' => '💯',
    'Input Penilaian' => '⭐', 'Kelola Mahasiswa' => '👨‍🎓', 'Kelola Dosen' => '👨‍🏫',
    'Kelola Perusahaan' => '🏭', 'Manajemen Surat' => '📜', 'Laporan KP' => '📈'
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?> - SIM KP</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --secondary-gradient: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            --success-gradient: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            --dark-gradient: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
            --glass-bg: rgba(255, 255, 255, 0.25);
            --glass-border: rgba(255, 255, 255, 0.18);
            --text-primary: #2c3e50;
            --text-secondary: #7f8c8d;
            --shadow-light: 0 8px 32px rgba(0, 0, 0, 0.1);
            --shadow-medium: 0 12px 40px rgba(0, 0, 0, 0.15);
            --border-radius: 16px;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        * { box-sizing: border-box; }
        body, h1, h2, h3, h4, h5, h6, p, ul, ol { margin: 0; padding: 0; }
        
        body { 
            font-family: 'Poppins', sans-serif; 
            line-height: 1.6; 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 50%, #667eea 100%);
            background-size: 400% 400%;
            animation: gradientShift 15s ease infinite;
            color: var(--text-primary); 
            display: flex; 
            flex-direction: column; 
            min-height: 100vh; 
        }

        @keyframes gradientShift {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        /* Modern Glassmorphism Navbar */
        .navbar { 
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid var(--glass-border);
            padding: 1rem 2rem; 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            box-shadow: var(--shadow-medium);
            position: sticky; 
            top: 0; 
            z-index: 1000;
            transition: var(--transition);
        }

        .navbar::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 1px;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.4), transparent);
        }

        /* Animated Brand Logo */
        .navbar-brand { 
            font-size: 1.5rem; 
            background: linear-gradient(135deg, #fff 0%, #f8f9fa 100%);
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
            text-decoration: none; 
            font-weight: 800; 
            letter-spacing: -0.5px;
            position: relative;
            transition: var(--transition);
        }

        .navbar-brand::after {
            content: '';
            position: absolute;
            bottom: -4px;
            left: 0;
            width: 0;
            height: 2px;
            background: var(--primary-gradient);
            border-radius: 2px;
            transition: width 0.4s ease;
        }

        .navbar-brand:hover::after {
            width: 100%;
        }

        .navbar-brand:hover {
            transform: translateY(-1px);
            filter: drop-shadow(0 4px 8px rgba(0,0,0,0.2));
        }

        .navbar-nav { 
            list-style: none; 
            display: flex; 
            align-items: center; 
            gap: 1rem; 
        }

        /* Enhanced Login Button */
        .btn-login {
            background: var(--primary-gradient);
            color: white;
            padding: 12px 24px;
            border-radius: 50px;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.9rem;
            transition: var(--transition);
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
            position: relative;
            overflow: hidden;
        }

        .btn-login::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }

        .btn-login:hover::before {
            left: 100%;
        }

        .btn-login:hover {
            transform: translateY(-2px) scale(1.05);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.6);
        }

        /* Advanced Dropdown Styling */
        .dropdown { 
            position: relative; 
            display: inline-block; 
        }

        .dropdown-toggle { 
            background: var(--glass-bg);
            backdrop-filter: blur(10px);
            color: white;
            padding: 10px 16px; 
            font-size: 0.95em; 
            font-weight: 500; 
            border: 1px solid var(--glass-border);
            cursor: pointer; 
            display: flex; 
            align-items: center; 
            gap: 12px; 
            border-radius: 50px; 
            transition: var(--transition);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        .dropdown-toggle:hover, .dropdown-toggle.active { 
            background: rgba(255, 255, 255, 0.35);
            border-color: rgba(255, 255, 255, 0.3);
            transform: translateY(-1px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.15);
        }

        .user-avatar-initial { 
            width: 36px; 
            height: 36px; 
            border-radius: 50%; 
            background: var(--primary-gradient);
            color: white; 
            display: inline-flex; 
            align-items: center; 
            justify-content: center; 
            font-weight: 700;
            font-size: 0.9rem;
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
            transition: var(--transition);
        }

        .dropdown-toggle:hover .user-avatar-initial {
            transform: scale(1.1) rotate(5deg);
            box-shadow: 0 6px 16px rgba(0,0,0,0.3);
        }

        .user-name-nav { 
            font-weight: 600; 
            color: white;
            text-shadow: 0 1px 2px rgba(0,0,0,0.1);
        }

        .user-role-badge {
            background: var(--secondary-gradient);
            color: white;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.7rem;
            font-weight: 500;
            text-transform: capitalize;
        }

        .dropdown-toggle::after { 
            content: '⌄'; 
            font-size: 1.2em; 
            transition: transform 0.3s ease; 
            color: rgba(255,255,255,0.8);
        }

        .dropdown-toggle.active::after { 
            transform: rotate(180deg); 
        }

        /* Premium Dropdown Menu */
        .dropdown-menu {
            display: none;
            position: absolute; 
            right: 0; 
            top: calc(100% + 12px);
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            min-width: 280px;
            border: 1px solid var(--glass-border);
            box-shadow: var(--shadow-medium);
            z-index: 1001;
            border-radius: var(--border-radius); 
            padding: 8px 0;
            animation: dropdownSlide 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            overflow: hidden;
        }

        @keyframes dropdownSlide {
            from { 
                opacity: 0; 
                transform: translateY(-10px) scale(0.95); 
            }
            to { 
                opacity: 1; 
                transform: translateY(0) scale(1); 
            }
        }

        .dropdown-menu.show { 
            display: block; 
        }

        .dropdown-menu::before {
            content: '';
            position: absolute;
            top: -6px;
            right: 20px;
            width: 12px;
            height: 12px;
            background: var(--glass-bg);
            border: 1px solid var(--glass-border);
            border-bottom: none;
            border-right: none;
            transform: rotate(45deg);
            backdrop-filter: blur(20px);
        }

        .dropdown-menu a { 
            color: white;
            padding: 14px 20px; 
            text-decoration: none; 
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 0.9em; 
            font-weight: 500;
            transition: var(--transition);
            position: relative;
            overflow: hidden;
        }

        .dropdown-menu a::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            height: 100%;
            width: 0;
            background: var(--primary-gradient);
            transition: width 0.3s ease;
            z-index: -1;
        }

        .dropdown-menu a:hover::before {
            width: 100%;
        }

        .dropdown-menu a:hover { 
            color: white;
            transform: translateX(4px);
            background: transparent;
        }

        .dropdown-menu .menu-icon {
            font-size: 1.1em;
            width: 20px;
            text-align: center;
        }

        .dropdown-divider { 
            height: 1px; 
            margin: 8px 16px; 
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
        }

        .dropdown-menu a.logout-link { 
            color: #ff6b6b;
            font-weight: 600; 
        }

        .dropdown-menu a.logout-link::before {
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a24 100%);
        }

        .dropdown-menu a.logout-link:hover { 
            color: white;
        }

        /* Notification Badge */
        .notification-badge {
            position: absolute;
            top: -4px;
            right: -4px;
            background: var(--secondary-gradient);
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.7rem;
            font-weight: 600;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }

        /* Mobile Responsive */
        @media (max-width: 768px) {
            .navbar {
                padding: 1rem;
            }
            
            .navbar-brand {
                font-size: 1.2rem;
            }
            
            .dropdown-menu {
                right: -10px;
                min-width: 250px;
            }
            
            .user-name-nav {
                display: none;
            }
        }

        /* Scrollbar Styling */
        .dropdown-menu::-webkit-scrollbar {
            width: 6px;
        }

        .dropdown-menu::-webkit-scrollbar-track {
            background: rgba(255,255,255,0.1);
            border-radius: 3px;
        }

        .dropdown-menu::-webkit-scrollbar-thumb {
            background: rgba(255,255,255,0.3);
            border-radius: 3px;
        }

        .dropdown-menu::-webkit-scrollbar-thumb:hover {
            background: rgba(255,255,255,0.5);
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <a href="<?php echo $base_url; ?>/index.php" class="navbar-brand">
            <i class="fas fa-graduation-cap" style="margin-right: 8px;"></i>
            SIM KERJA PRAKTEK
        </a>
        <ul class="navbar-nav">
            <?php if ($is_logged_in): ?>
                <li class="nav-item">
                    <div class="dropdown">
                        <button class="dropdown-toggle" id="dropdown-button">
                            <div style="position: relative;">
                                <span class="user-avatar-initial"><?php echo strtoupper(substr($user_nama, 0, 1)); ?></span>
                            </div>
                            <div>
                                <span class="user-name-nav"><?php echo htmlspecialchars(explode(' ', $user_nama)[0]); ?></span>
                                <div class="user-role-badge"><?php echo $user_role_display; ?></div>
                            </div>
                        </button>
                        <div class="dropdown-menu" id="dropdown-menu">
                            <?php if (isset($menu_items[$user_role_key])): ?>
                                <?php foreach ($menu_items[$user_role_key] as $title => $link): ?>
                                    <a href="<?php echo $base_url . $link; ?>">
                                        <span class="menu-icon"><?php echo isset($menu_icons[$title]) ? $menu_icons[$title] : '📋'; ?></span>
                                        <?php echo $title; ?>
                                    </a>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            <div class="dropdown-divider"></div>
                            <a href="<?php echo $base_url; ?>/logout.php" class="logout-link">
                                <span class="menu-icon">🚪</span>
                                Logout
                            </a>
                        </div>
                    </div>
                </li>
            <?php else: ?>
                <li class="nav-item">
                    <a href="<?php echo $base_url; ?>/index.php" class="btn-login">
                        <i class="fas fa-sign-in-alt" style="margin-right: 6px;"></i>
                        Login
                    </a>
                </li>
            <?php endif; ?>
        </ul>
    </nav>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const dropdownButton = document.getElementById('dropdown-button');
        const dropdownMenu = document.getElementById('dropdown-menu');

        if (dropdownButton && dropdownMenu) {
            // Enhanced click handler with smooth animations
            dropdownButton.addEventListener('click', function(event) {
                event.stopPropagation();
                
                const isOpen = dropdownMenu.classList.contains('show');
                
                if (!isOpen) {
                    dropdownMenu.classList.add('show');
                    dropdownButton.classList.add('active');
                    
                    // Add stagger animation to menu items
                    const menuItems = dropdownMenu.querySelectorAll('a');
                    menuItems.forEach((item, index) => {
                        item.style.opacity = '0';
                        item.style.transform = 'translateX(-10px)';
                        setTimeout(() => {
                            item.style.transition = 'all 0.3s ease';
                            item.style.opacity = '1';
                            item.style.transform = 'translateX(0)';
                        }, index * 50);
                    });
                } else {
                    dropdownMenu.classList.remove('show');
                    dropdownButton.classList.remove('active');
                }
            });

            // Enhanced outside click handler
            window.addEventListener('click', function(event) {
                if (!dropdownButton.contains(event.target) && !dropdownMenu.contains(event.target)) {
                    if (dropdownMenu.classList.contains('show')) {
                        dropdownMenu.classList.remove('show');
                        dropdownButton.classList.remove('active');
                    }
                }
            });

            // Keyboard navigation
            dropdownButton.addEventListener('keydown', function(event) {
                if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    dropdownButton.click();
                }
            });
        }

        // Navbar scroll effect
        let lastScrollTop = 0;
        const navbar = document.querySelector('.navbar');
        
        window.addEventListener('scroll', function() {
            const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
            
            if (scrollTop > lastScrollTop && scrollTop > 100) {
                // Scrolling down
                navbar.style.transform = 'translateY(-100%)';
                navbar.style.opacity = '0.95';
            } else {
                // Scrolling up
                navbar.style.transform = 'translateY(0)';
                navbar.style.opacity = '1';
            }
            
            // Add blur effect when scrolled
            if (scrollTop > 50) {
                navbar.style.backdropFilter = 'blur(25px)';
                navbar.style.background = 'rgba(255, 255, 255, 0.15)';
            } else {
                navbar.style.backdropFilter = 'blur(20px)';
                navbar.style.background = 'rgba(255, 255, 255, 0.25)';
            }
            
            lastScrollTop = scrollTop;
        });
    });
    </script>
</body>
</html>