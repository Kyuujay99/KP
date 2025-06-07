<?php
// /KP/admin_prodi/dashboard.php

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// 1. OTENTIKASI DAN OTORISASI
require_once '../includes/auth_check.php';
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin_prodi') {
    session_unset();
    session_destroy();
    header("Location: /KP/index.php?error=unauthorized_admin");
    exit();
}

$id_admin_login = $_SESSION['user_id'];
$nama_admin_login = $_SESSION['user_nama'];

require_once '../config/db_connect.php';

// Inisialisasi variabel untuk data summary
$stats = [
    'mahasiswa_aktif' => 0,
    'dosen_aktif' => 0,
    'perusahaan_mitra' => 0,
    'pengajuan_perlu_tindakan' => 0
];

if ($conn && ($conn instanceof mysqli)) {
    // Hitung jumlah mahasiswa aktif
    $result = $conn->query("SELECT COUNT(*) AS total FROM mahasiswa WHERE status_akun = 'active'");
    if ($result) $stats['mahasiswa_aktif'] = $result->fetch_assoc()['total'];

    // Hitung jumlah dosen aktif
    $result = $conn->query("SELECT COUNT(*) AS total FROM dosen_pembimbing WHERE status_akun = 'active'");
    if ($result) $stats['dosen_aktif'] = $result->fetch_assoc()['total'];

    // Hitung jumlah perusahaan mitra aktif
    $result = $conn->query("SELECT COUNT(*) AS total FROM perusahaan WHERE status_akun = 'active'");
    if ($result) $stats['perusahaan_mitra'] = $result->fetch_assoc()['total'];
    
    // Hitung jumlah pengajuan yang memerlukan tindakan admin
    // (Contoh: penentuan dospem, verifikasi dokumen, dll. Status ini bisa disesuaikan)
    $status_perlu_tindakan = "'penentuan_dospem_kp', 'menunggu_konfirmasi_perusahaan'";
    $result = $conn->query("SELECT COUNT(*) AS total FROM pengajuan_kp WHERE status_pengajuan IN ($status_perlu_tindakan)");
    if ($result) $stats['pengajuan_perlu_tindakan'] = $result->fetch_assoc()['total'];
}

$page_title = "Dashboard Admin Prodi";
require_once '../includes/header.php';
?>

<div class="main-content-full">
    <div class="admin-dashboard-container">
        <div class="dashboard-header">
            <div class="welcome-text">
                <h1>Dashboard Admin Prodi</h1>
                <p>Selamat datang, <?php echo htmlspecialchars($nama_admin_login); ?>. Kelola semua aspek program Kerja Praktek dari sini.</p>
            </div>
        </div>

        <div class="summary-grid">
            <div class="summary-item">
                <div class="summary-icon icon-mahasiswa">🎓</div>
                <div class="summary-text">
                    <span class="summary-value"><?php echo $stats['mahasiswa_aktif']; ?></span>
                    <span class="summary-label">Mahasiswa Aktif</span>
                </div>
            </div>
            <div class="summary-item">
                <div class="summary-icon icon-dosen">👨‍🏫</div>
                <div class="summary-text">
                    <span class="summary-value"><?php echo $stats['dosen_aktif']; ?></span>
                    <span class="summary-label">Dosen Aktif</span>
                </div>
            </div>
            <div class="summary-item">
                <div class="summary-icon icon-perusahaan">🏢</div>
                <div class="summary-text">
                    <span class="summary-value"><?php echo $stats['perusahaan_mitra']; ?></span>
                    <span class="summary-label">Perusahaan Mitra</span>
                </div>
            </div>
            <div class="summary-item alert">
                <div class="summary-icon icon-alert">⚠️</div>
                <div class="summary-text">
                    <span class="summary-value"><?php echo $stats['pengajuan_perlu_tindakan']; ?></span>
                    <span class="summary-label">Pengajuan Perlu Tindakan</span>
                </div>
                <?php if ($stats['pengajuan_perlu_tindakan'] > 0): ?>
                    <a href="pengajuan_kp_monitoring.php" class="summary-link">Lihat</a>
                <?php endif; ?>
            </div>
        </div>

        <div class="navigation-header">
            <h3>Menu Manajemen Utama</h3>
            <div class="line"></div>
        </div>
        
        <div class="navigation-grid">
            <a href="pengajuan_kp_monitoring.php" class="nav-item">
                <div class="nav-icon">📊</div>
                <h4>Monitoring Pengajuan KP</h4>
                <p>Pantau dan kelola semua status pengajuan KP mahasiswa.</p>
            </a>
            <a href="pengguna_mahasiswa_kelola.php" class="nav-item">
                <div class="nav-icon">👥</div>
                <h4>Kelola Akun Mahasiswa</h4>
                <p>Tambah, edit, dan kelola status akun mahasiswa.</p>
            </a>
            <a href="pengguna_dosen_kelola.php" class="nav-item">
                <div class="nav-icon">👨‍🏫</div>
                <h4>Kelola Akun Dosen</h4>
                <p>Tambah, edit, dan kelola status akun dosen pembimbing.</p>
            </a>
            <a href="perusahaan_kelola.php" class="nav-item">
                <div class="nav-icon">🏢</div>
                <h4>Kelola Data Perusahaan</h4>
                <p>Verifikasi dan kelola daftar perusahaan mitra KP.</p>
            </a>
            <a href="dokumen_verifikasi_list.php" class="nav-item">
                <div class="nav-icon">📎</div>
                <h4>Verifikasi Dokumen</h4>
                <p>Periksa dan verifikasi semua dokumen yang diunggah.</p>
            </a>
             <a href="surat_generate_list.php" class="nav-item">
                <div class="nav-icon">✉️</div>
                <h4>Manajemen Surat</h4>
                <p>Buat surat pengantar dan surat tugas untuk KP.</p>
            </a>
            <a href="laporan_kp_view.php" class="nav-item">
                <div class="nav-icon">📈</div>
                <h4>Laporan & Statistik</h4>
                <p>Lihat rekapitulasi dan statistik pelaksanaan program KP.</p>
            </a>
        </div>
    </div>
</div>

<style>
    /* Menggunakan style dari dashboard dosen dan disesuaikan */
    .main-content-full { padding: 2rem; }
    .admin-dashboard-container { max-width: 1400px; margin: auto; }
    
    .dashboard-header {
        margin-bottom: 2rem;
        padding: 2rem;
        background: linear-gradient(135deg, #28a745, #218838);
        color: white;
        border-radius: var(--border-radius);
    }
    .dashboard-header h1 { font-size: 2.2em; }
    .dashboard-header p { font-size: 1.2em; opacity: 0.9; margin-top: 5px; }

    .summary-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
        gap: 1.5rem;
        margin-bottom: 3rem;
    }
    .summary-item {
        background-color: #fff;
        padding: 1.5rem;
        border-radius: var(--border-radius);
        box-shadow: var(--card-shadow);
        display: flex;
        align-items: center;
        gap: 1.5rem;
        position: relative;
    }
    .summary-icon {
        font-size: 2.5rem;
        padding: 1rem;
        border-radius: 50%;
        line-height: 1;
    }
    .icon-mahasiswa { background-color: rgba(0, 123, 255, 0.1); color: #007BFF; }
    .icon-dosen { background-color: rgba(23, 162, 184, 0.1); color: #17a2b8; }
    .icon-perusahaan { background-color: rgba(108, 117, 125, 0.1); color: #6c757d; }
    .icon-alert { background-color: rgba(255, 193, 7, 0.1); color: #ffc107; }
    .summary-item.alert { border-left: 5px solid #ffc107; }

    .summary-text { display: flex; flex-direction: column; }
    .summary-value { font-size: 2.5em; font-weight: 700; color: var(--dark-color); line-height: 1; }
    .summary-label { font-size: 1em; color: var(--secondary-color); }
    .summary-link {
        position: absolute; top: 10px; right: 10px; font-size: 0.8em; font-weight: 600; text-decoration: none;
        color: var(--primary-color); background-color: rgba(0, 123, 255, 0.1); padding: 5px 10px; border-radius: 20px;
    }

    /* Header Navigasi */
    .navigation-header { text-align: center; margin-bottom: 2rem; }
    .navigation-header h3 { display: inline-block; font-size: 1.8em; color: #343a40; position: relative; padding-bottom: 10px; }
    .navigation-header h3::after {
        content: ''; position: absolute; bottom: 0; left: 50%; transform: translateX(-50%);
        width: 60px; height: 3px; background-color: #28a745;
    }

    /* Grid Navigasi */
    .navigation-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 1.5rem;
    }
    .nav-item {
        display: flex; flex-direction: column; text-align: left; background-color: #fff;
        padding: 1.5rem; border-radius: var(--border-radius); text-decoration: none; color: #333;
        box-shadow: var(--card-shadow); transition: all 0.3s ease; border-left: 5px solid transparent;
    }
    .nav-item:hover { transform: translateY(-5px); box-shadow: 0 8px 25px rgba(0,0,0,0.1); border-left-color: #28a745; }
    .nav-icon { font-size: 2em; margin-bottom: 1rem; color: #28a745; }
    .nav-item h4 { margin: 0 0 8px 0; color: #218838; font-size: 1.2em; }
    .nav-item p { font-size: 0.9em; color: var(--secondary-color); line-height: 1.5; margin: 0; }
</style>

<?php
require_once '../includes/footer.php';
if (isset($conn) && $conn) {
    $conn->close();
}
?>