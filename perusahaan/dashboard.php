<?php
// /KP/perusahaan/dashboard.php (Versi Profesional)

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once '../includes/auth_check.php';
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'perusahaan') {
    header("Location: /KP/index.php?error=unauthorized_perusahaan");
    exit();
}

$id_perusahaan_login = $_SESSION['user_id'];
$nama_perusahaan_login = $_SESSION['user_nama'];
require_once '../config/db_connect.php';

// ... (Logika PHP untuk mengambil data statistik tetap sama persis) ...

$page_title = "Dashboard Perusahaan";
require_once '../includes/header.php';
?>

<div class="main-content-full">
    <div class="perusahaan-dashboard-container">
        <div class="dashboard-header">
            <div class="header-icon">🏢</div>
            <div class="welcome-text">
                <h1><?php echo htmlspecialchars($nama_perusahaan_login); ?></h1>
                <p>Selamat datang di Portal Mitra Kerja Praktek Universitas Teknologi Maju.</p>
            </div>
        </div>

        <div class="summary-grid">
            <div class="summary-card">
                <div class="summary-icon icon-pengajuan">📩</div>
                <div class="summary-text">
                    <span class="summary-value"><?php echo $stats['pengajuan_masuk']; ?></span>
                    <span class="summary-label">Pengajuan Baru</span>
                </div>
                <a href="pengajuan_kp_masuk.php" class="summary-action">Lihat & Konfirmasi</a>
            </div>
            <div class="summary-card">
                <div class="summary-icon icon-mahasiswa">🎓</div>
                <div class="summary-text">
                    <span class="summary-value"><?php echo $stats['mahasiswa_aktif']; ?></span>
                    <span class="summary-label">Mahasiswa Aktif</span>
                </div>
                <a href="mahasiswa_kp_list.php" class="summary-action">Lihat Daftar</a>
            </div>
            <div class="summary-card alert">
                <div class="summary-icon icon-penilaian">⭐</div>
                <div class="summary-text">
                    <span class="summary-value"><?php echo $stats['perlu_penilaian']; ?></span>
                    <span class="summary-label">Perlu Dinilai</span>
                </div>
                <a href="penilaian_lapangan_list.php" class="summary-action">Beri Penilaian</a>
            </div>
        </div>

        <div class="navigation-header">
            <h3>Menu Navigasi</h3>
            <div class="line"></div>
        </div>
        
        <div class="navigation-grid">
            <a href="pengajuan_kp_masuk.php" class="nav-card">
                <div class="nav-card-icon">📩</div>
                <div class="nav-card-text">
                    <h4>Konfirmasi Pengajuan</h4>
                    <p>Tinjau dan berikan keputusan untuk lamaran KP yang masuk.</p>
                </div>
                <div class="nav-card-arrow">→</div>
            </a>
            <a href="mahasiswa_kp_list.php" class="nav-card">
                <div class="nav-card-icon">👥</div>
                <h4>Daftar Mahasiswa KP</h4>
                <p>Pantau semua mahasiswa yang sedang KP di perusahaan Anda.</p>
                </div>
                <div class="nav-card-arrow">→</div>
            </a>
            <a href="penilaian_lapangan_list.php" class="nav-card">
                <div class="nav-card-icon">⭐</div>
                <div class="nav-card-text">
                    <h4>Input Penilaian</h4>
                    <p>Berikan evaluasi kinerja untuk mahasiswa yang selesai KP.</p>
                </div>
                <div class="nav-card-arrow">→</div>
            </a>
            <a href="profil_perusahaan.php" class="nav-card">
                <div class="nav-card-icon">🏢</div>
                <div class="nav-card-text">
                    <h4>Profil Perusahaan</h4>
                    <p>Perbarui informasi detail dan kontak perusahaan Anda.</p>
                </div>
                <div class="nav-card-arrow">→</div>
            </a>
        </div>
    </div>
</div>

<style>
    .perusahaan-dashboard-container { max-width: 1200px; margin: 2rem auto; padding: 2rem; }
    
    .dashboard-header {
        display: flex;
        align-items: center;
        gap: 1.5rem;
        padding: 2rem;
        background-color: var(--dark-color);
        color: white;
        border-radius: var(--border-radius);
        margin-bottom: 2.5rem;
        animation: slideDown 0.5s ease-out;
    }
    .header-icon { font-size: 3.5em; line-height: 1; opacity: 0.8; }
    .welcome-text h1 { font-size: 2em; margin: 0; }
    .welcome-text p { margin: 5px 0 0; opacity: 0.8; }

    .summary-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.5rem; margin-bottom: 3rem; }
    .summary-card {
        background: #fff;
        padding: 1.5rem;
        border-radius: var(--border-radius);
        box-shadow: var(--card-shadow);
        display: flex;
        flex-direction: column;
        border-bottom: 4px solid var(--primary-color);
        transition: transform 0.3s ease, box-shadow 0.3s ease;
        animation: popIn 0.5s ease-out forwards;
        opacity: 0;
    }
    .summary-card:hover { transform: translateY(-5px); box-shadow: 0 10px 30px rgba(0,0,0,0.1); }
    .summary-card.alert { border-bottom-color: #ffc107; }
    <?php for ($i = 1; $i <= 3; $i++): ?>
    .summary-card:nth-child(<?php echo $i; ?>) { animation-delay: <?php echo $i * 0.1; ?>s; }
    <?php endfor; ?>

    .summary-icon { font-size: 2.5em; margin-bottom: 1rem; }
    .summary-text { flex-grow: 1; }
    .summary-value { font-size: 3em; font-weight: 700; line-height: 1; color: var(--dark-color); }
    .summary-label { color: var(--secondary-color); font-weight: 500; }
    .summary-action { display: block; margin-top: 1.5rem; text-align: right; text-decoration: none; color: var(--primary-color); font-weight: 600; }
    .summary-action:hover { text-decoration: underline; }

    .navigation-header { text-align: center; margin: 3rem 0 2rem; }
    .navigation-header h3 { font-size: 1.8em; }
    .navigation-header .line { width: 80px; height: 4px; background-color: var(--primary-color); margin: 10px auto 0; border-radius: 2px; }

    .navigation-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.5rem; }
    .nav-card {
        background-color: #fff;
        padding: 1.5rem;
        border-radius: var(--border-radius);
        text-decoration: none;
        box-shadow: var(--card-shadow);
        display: flex;
        align-items: center;
        gap: 1.5rem;
        transition: all 0.3s ease;
    }
    .nav-card:hover { transform: scale(1.03); box-shadow: 0 10px 30px rgba(0,0,0,0.12); }
    .nav-card-icon { font-size: 2.2em; padding: 15px; border-radius: 12px; background-color: #f0f2f5; }
    .nav-card-text h4 { margin: 0 0 5px 0; color: var(--dark-color); }
    .nav-card-text p { margin: 0; color: var(--secondary-color); font-size: 0.9em; }
    .nav-card-arrow { margin-left: auto; font-size: 1.5em; color: var(--border-color); transition: color 0.3s ease, transform 0.3s ease; }
    .nav-card:hover .nav-card-arrow { color: var(--primary-color); transform: translateX(5px); }
    
    @keyframes slideDown { from { opacity: 0; transform: translateY(-20px); } to { opacity: 1; transform: translateY(0); } }
    @keyframes popIn { from { opacity: 0; transform: scale(0.95); } to { opacity: 1; transform: scale(1); } }
</style>

<?php
require_once '../includes/footer.php';
if (isset($conn)) { $conn->close(); }
?>