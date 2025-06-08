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
    $status_perlu_tindakan = "'penentuan_dospem_kp', 'menunggu_konfirmasi_perusahaan'";
    $result = $conn->query("SELECT COUNT(*) AS total FROM pengajuan_kp WHERE status_pengajuan IN ($status_perlu_tindakan)");
    if ($result) $stats['pengajuan_perlu_tindakan'] = $result->fetch_assoc()['total'];
}

$page_title = "Dashboard Admin Prodi";
require_once '../includes/header.php';
?>

<div class="dashboard-modern">
    <!-- Hero Section with Animated Background -->
    <div class="hero-section">
        <div class="hero-background">
            <div class="floating-shapes">
                <div class="shape shape-1"></div>
                <div class="shape shape-2"></div>
                <div class="shape shape-3"></div>
                <div class="shape shape-4"></div>
                <div class="shape shape-5"></div>
            </div>
        </div>
        <div class="hero-content">
            <div class="hero-avatar">
                <div class="avatar-circle">
                    <span class="avatar-text"><?php echo strtoupper(substr($nama_admin_login, 0, 2)); ?></span>
                </div>
                <div class="status-indicator"></div>
            </div>
            <h1 class="hero-title">
                Selamat Datang, <span class="highlight"><?php echo htmlspecialchars($nama_admin_login); ?></span>
            </h1>
            <p class="hero-subtitle">Dashboard Admin Program Studi - Kelola Kerja Praktek dengan Efisien</p>
            <div class="current-time" id="currentTime"></div>
        </div>
    </div>

    <!-- Analytics Cards -->
    <div class="analytics-section">
        <div class="section-header">
            <h2 class="section-title">Ringkasan Analytics</h2>
            <div class="section-line"></div>
        </div>
        
        <div class="analytics-grid">
            <div class="analytics-card card-students">
                <div class="card-header">
                    <div class="card-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M22 10v6M2 10l10-5 10 5-10 5z"/>
                            <path d="M6 12v5c3 3 9 3 12 0v-5"/>
                        </svg>
                    </div>
                    <div class="card-trend positive">
                        <span class="trend-icon">↗</span>
                        <span class="trend-text">+12%</span>
                    </div>
                </div>
                <div class="card-content">
                    <div class="card-number" data-count="<?php echo $stats['mahasiswa_aktif']; ?>">0</div>
                    <div class="card-label">Mahasiswa Aktif</div>
                    <div class="card-progress">
                        <div class="progress-bar" style="--progress: 85%"></div>
                    </div>
                </div>
            </div>

            <div class="analytics-card card-lecturers">
                <div class="card-header">
                    <div class="card-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                            <circle cx="12" cy="7" r="4"/>
                        </svg>
                    </div>
                    <div class="card-trend positive">
                        <span class="trend-icon">↗</span>
                        <span class="trend-text">+8%</span>
                    </div>
                </div>
                <div class="card-content">
                    <div class="card-number" data-count="<?php echo $stats['dosen_aktif']; ?>">0</div>
                    <div class="card-label">Dosen Pembimbing</div>
                    <div class="card-progress">
                        <div class="progress-bar" style="--progress: 72%"></div>
                    </div>
                </div>
            </div>

            <div class="analytics-card card-companies">
                <div class="card-header">
                    <div class="card-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M3 21h18"/>
                            <path d="M5 21V7l8-4v18"/>
                            <path d="M19 21V11l-6-4"/>
                        </svg>
                    </div>
                    <div class="card-trend positive">
                        <span class="trend-icon">↗</span>
                        <span class="trend-text">+15%</span>
                    </div>
                </div>
                <div class="card-content">
                    <div class="card-number" data-count="<?php echo $stats['perusahaan_mitra']; ?>">0</div>
                    <div class="card-label">Perusahaan Mitra</div>
                    <div class="card-progress">
                        <div class="progress-bar" style="--progress: 90%"></div>
                    </div>
                </div>
            </div>

            <div class="analytics-card card-pending <?php echo $stats['pengajuan_perlu_tindakan'] > 0 ? 'alert-card' : ''; ?>">
                <div class="card-header">
                    <div class="card-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"/>
                            <path d="M12 6v6l4 2"/>
                        </svg>
                    </div>
                    <?php if ($stats['pengajuan_perlu_tindakan'] > 0): ?>
                        <div class="card-trend negative">
                            <span class="trend-icon">⚠</span>
                            <span class="trend-text">Urgent</span>
                        </div>
                    <?php else: ?>
                        <div class="card-trend neutral">
                            <span class="trend-icon">✓</span>
                            <span class="trend-text">Clear</span>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="card-content">
                    <div class="card-number" data-count="<?php echo $stats['pengajuan_perlu_tindakan']; ?>">0</div>
                    <div class="card-label">Perlu Tindakan</div>
                    <?php if ($stats['pengajuan_perlu_tindakan'] > 0): ?>
                        <a href="pengajuan_kp_monitoring.php" class="action-button pulse">
                            <span>Lihat Detail</span>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M5 12h14M12 5l7 7-7 7"/>
                            </svg>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions Menu -->
    <div class="actions-section">
        <div class="section-header">
            <h2 class="section-title">Menu Manajemen</h2>
            <div class="section-line"></div>
        </div>

        <div class="actions-grid">
            <a href="pengajuan_kp_monitoring.php" class="action-card" data-category="monitoring">
                <div class="action-background"></div>
                <div class="action-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M3 3v18h18"/>
                        <path d="M18.7 8l-5.1 5.2-2.8-2.7L7 14.3"/>
                    </svg>
                </div>
                <div class="action-content">
                    <h3>Monitoring Pengajuan</h3>
                    <p>Pantau status dan progress pengajuan KP mahasiswa secara real-time</p>
                    <div class="action-badge">Real-time</div>
                </div>
                <div class="action-arrow">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M5 12h14M12 5l7 7-7 7"/>
                    </svg>
                </div>
            </a>

            <a href="pengguna_mahasiswa_kelola.php" class="action-card" data-category="users">
                <div class="action-background"></div>
                <div class="action-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/>
                        <circle cx="9" cy="7" r="4"/>
                        <path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>
                    </svg>
                </div>
                <div class="action-content">
                    <h3>Kelola Mahasiswa</h3>
                    <p>Manajemen akun dan data mahasiswa program KP</p>
                    <div class="action-badge">User Management</div>
                </div>
                <div class="action-arrow">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M5 12h14M12 5l7 7-7 7"/>
                    </svg>
                </div>
            </a>

            <a href="pengguna_dosen_kelola.php" class="action-card" data-category="users">
                <div class="action-background"></div>
                <div class="action-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M22 10v6M2 10l10-5 10 5-10 5z"/>
                        <path d="M6 12v5c3 3 9 3 12 0v-5"/>
                    </svg>
                </div>
                <div class="action-content">
                    <h3>Kelola Dosen</h3>
                    <p>Manajemen dosen pembimbing dan assignment KP</p>
                    <div class="action-badge">Academic</div>
                </div>
                <div class="action-arrow">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M5 12h14M12 5l7 7-7 7"/>
                    </svg>
                </div>
            </a>

            <a href="perusahaan_kelola.php" class="action-card" data-category="partners">
                <div class="action-background"></div>
                <div class="action-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M3 21h18"/>
                        <path d="M5 21V7l8-4v18"/>
                        <path d="M19 21V11l-6-4"/>
                    </svg>
                </div>
                <div class="action-content">
                    <h3>Kelola Perusahaan</h3>
                    <p>Verifikasi dan manajemen perusahaan mitra program KP</p>
                    <div class="action-badge">Partnership</div>
                </div>
                <div class="action-arrow">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M5 12h14M12 5l7 7-7 7"/>
                    </svg>
                </div>
            </a>

            <a href="dokumen_verifikasi_list.php" class="action-card" data-category="documents">
                <div class="action-background"></div>
                <div class="action-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                        <polyline points="14,2 14,8 20,8"/>
                        <line x1="16" y1="13" x2="8" y2="13"/>
                        <line x1="16" y1="17" x2="8" y2="17"/>
                        <polyline points="10,9 9,9 8,9"/>
                    </svg>
                </div>
                <div class="action-content">
                    <h3>Verifikasi Dokumen</h3>
                    <p>Review dan approval dokumen yang disubmit mahasiswa</p>
                    <div class="action-badge">Verification</div>
                </div>
                <div class="action-arrow">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M5 12h14M12 5l7 7-7 7"/>
                    </svg>
                </div>
            </a>

            <a href="surat_generate_list.php" class="action-card" data-category="documents">
                <div class="action-background"></div>
                <div class="action-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
                        <polyline points="22,6 12,13 2,6"/>
                    </svg>
                </div>
                <div class="action-content">
                    <h3>Generate Surat</h3>
                    <p>Buat surat pengantar dan surat tugas untuk program KP</p>
                    <div class="action-badge">Auto-Generate</div>
                </div>
                <div class="action-arrow">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M5 12h14M12 5l7 7-7 7"/>
                    </svg>
                </div>
            </a>

            <a href="laporan_kp_view.php" class="action-card" data-category="reports">
                <div class="action-background"></div>
                <div class="action-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M12 20V10"/>
                        <path d="M18 20V4"/>
                        <path d="M6 20v-4"/>
                    </svg>
                </div>
                <div class="action-content">
                    <h3>Laporan & Statistik</h3>
                    <p>Dashboard analytics dan rekapitulasi data program KP</p>
                    <div class="action-badge">Analytics</div>
                </div>
                <div class="action-arrow">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M5 12h14M12 5l7 7-7 7"/>
                    </svg>
                </div>
            </a>
        </div>
    </div>
</div>

<style>
/* Modern Dashboard Styles */
:root {
    --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    --secondary-gradient: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
    --success-gradient: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
    --warning-gradient: linear-gradient(135deg, #ffecd2 0%, #fcb69f 100%);
    --danger-gradient: linear-gradient(135deg, #ff9a9e 0%, #fecfef 100%);
    --dark-gradient: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
    
    --card-shadow: 0 10px 30px rgba(0,0,0,0.1);
    --card-shadow-hover: 0 20px 40px rgba(0,0,0,0.15);
    --border-radius: 16px;
    --border-radius-large: 24px;
    
    --text-primary: #2d3748;
    --text-secondary: #718096;
    --text-muted: #a0aec0;
}

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
    min-height: 100vh;
    line-height: 1.6;
    color: var(--text-primary);
}

/* Dashboard Container */
.dashboard-modern {
    min-height: 100vh;
    padding: 0;
}

/* Hero Section */
.hero-section {
    position: relative;
    background: var(--primary-gradient);
    padding: 4rem 2rem 3rem;
    overflow: hidden;
    margin-bottom: 3rem;
}

.hero-background {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    overflow: hidden;
}

.floating-shapes {
    position: absolute;
    width: 100%;
    height: 100%;
}

.shape {
    position: absolute;
    border-radius: 50%;
    background: rgba(255,255,255,0.1);
    animation: float 6s ease-in-out infinite;
}

.shape-1 { width: 80px; height: 80px; top: 10%; left: 10%; animation-delay: 0s; }
.shape-2 { width: 120px; height: 120px; top: 20%; right: 10%; animation-delay: 1s; }
.shape-3 { width: 60px; height: 60px; bottom: 30%; left: 20%; animation-delay: 2s; }
.shape-4 { width: 100px; height: 100px; bottom: 10%; right: 30%; animation-delay: 3s; }
.shape-5 { width: 140px; height: 140px; top: 50%; left: 50%; animation-delay: 4s; transform: translate(-50%, -50%); }

@keyframes float {
    0%, 100% { transform: translateY(0px) rotate(0deg); }
    50% { transform: translateY(-20px) rotate(180deg); }
}

.hero-content {
    position: relative;
    z-index: 10;
    text-align: center;
    max-width: 800px;
    margin: 0 auto;
    color: white;
    animation: fadeInUp 1s ease-out;
}

@keyframes fadeInUp {
    from { opacity: 0; transform: translateY(30px); }
    to { opacity: 1; transform: translateY(0); }
}

.hero-avatar {
    position: relative;
    display: inline-block;
    margin-bottom: 2rem;
}

.avatar-circle {
    width: 100px;
    height: 100px;
    border-radius: 50%;
    background: rgba(255,255,255,0.2);
    backdrop-filter: blur(10px);
    border: 3px solid rgba(255,255,255,0.3);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
    font-weight: bold;
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0% { box-shadow: 0 0 0 0 rgba(255,255,255,0.4); }
    70% { box-shadow: 0 0 0 20px rgba(255,255,255,0); }
    100% { box-shadow: 0 0 0 0 rgba(255,255,255,0); }
}

.status-indicator {
    position: absolute;
    bottom: 8px;
    right: 8px;
    width: 20px;
    height: 20px;
    background: #10b981;
    border-radius: 50%;
    border: 3px solid white;
    animation: blink 2s infinite;
}

@keyframes blink {
    0%, 50% { opacity: 1; }
    51%, 100% { opacity: 0.3; }
}

.hero-title {
    font-size: 3rem;
    font-weight: 700;
    margin-bottom: 1rem;
    line-height: 1.2;
}

.highlight {
    background: linear-gradient(45deg, #ffd700, #ffed4e);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.hero-subtitle {
    font-size: 1.3rem;
    opacity: 0.9;
    margin-bottom: 2rem;
    font-weight: 300;
}

.current-time {
    display: inline-block;
    padding: 0.5rem 1.5rem;
    background: rgba(255,255,255,0.1);
    border-radius: 30px;
    font-size: 1rem;
    font-weight: 500;
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255,255,255,0.2);
}

/* Analytics Section */
.analytics-section, .actions-section {
    padding: 0 2rem 3rem;
    max-width: 1400px;
    margin: 0 auto;
}

.section-header {
    text-align: center;
    margin-bottom: 3rem;
}

.section-title {
    font-size: 2.5rem;
    font-weight: 700;
    color: var(--text-primary);
    margin-bottom: 1rem;
    position: relative;
    display: inline-block;
}

.section-line {
    width: 80px;
    height: 4px;
    background: var(--primary-gradient);
    margin: 0 auto;
    border-radius: 2px;
    animation: expandWidth 1s ease-out 0.5s both;
}

@keyframes expandWidth {
    from { width: 0; }
    to { width: 80px; }
}

/* Analytics Grid */
.analytics-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 2rem;
    margin-bottom: 4rem;
}

.analytics-card {
    background: white;
    border-radius: var(--border-radius-large);
    padding: 2rem;
    box-shadow: var(--card-shadow);
    transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    position: relative;
    overflow: hidden;
    animation: slideInUp 0.6s ease-out;
    animation-fill-mode: both;
}

.analytics-card:nth-child(1) { animation-delay: 0.1s; }
.analytics-card:nth-child(2) { animation-delay: 0.2s; }
.analytics-card:nth-child(3) { animation-delay: 0.3s; }
.analytics-card:nth-child(4) { animation-delay: 0.4s; }

@keyframes slideInUp {
    from { opacity: 0; transform: translateY(50px); }
    to { opacity: 1; transform: translateY(0); }
}

.analytics-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: var(--primary-gradient);
    transform: scaleX(0);
    transition: transform 0.3s ease;
}

.analytics-card:hover::before {
    transform: scaleX(1);
}

.analytics-card:hover {
    transform: translateY(-10px);
    box-shadow: var(--card-shadow-hover);
}

.card-students::before { background: var(--success-gradient); }
.card-lecturers::before { background: var(--primary-gradient); }
.card-companies::before { background: var(--secondary-gradient); }
.card-pending::before { background: var(--warning-gradient); }

.alert-card::before { background: var(--danger-gradient); }
.alert-card {
    border: 2px solid #ff6b6b;
    animation: alertPulse 2s infinite;
}

@keyframes alertPulse {
    0%, 100% { box-shadow: var(--card-shadow); }
    50% { box-shadow: 0 10px 30px rgba(255, 107, 107, 0.3); }
}

.card-header {
    display: flex;
    justify-content: between;
    align-items: center;
    margin-bottom: 1.5rem;
}

.card-icon {
    width: 60px;
    height: 60px;
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    margin-right: auto;
}

.card-students .card-icon { background: var(--success-gradient); }
.card-lecturers .card-icon { background: var(--primary-gradient); }
.card-companies .card-icon { background: var(--secondary-gradient); }
.card-pending .card-icon { background: var(--warning-gradient); }

.card-icon svg {
    width: 28px;
    height: 28px;
}

.card-trend {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.3rem 0.8rem;
    border-radius: 20px;
    font-size: 0.85rem;
    font-weight: 600;
}

.card-trend.positive {
    background: rgba(16, 185, 129, 0.1);
    color: #10b981;
}

.card-trend.negative {
    background: rgba(239, 68, 68, 0.1);
    color: #ef4444;
    animation: blink 1.5s infinite;
}

.card-trend.neutral {
    background: rgba(107, 114, 128, 0.1);
    color: #6b7280;
}

.card-content {
    text-align: left;
}

.card-number {
    font-size: 3rem;
    font-weight: 800;
    color: var(--text-primary);
    margin-bottom: 0.5rem;
    line-height: 1;
    background: linear-gradient(45deg, #667eea, #764ba2);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.card-label {
    font-size: 1rem;
    color: var(--text-secondary);
    font-weight: 500;
    margin-bottom: 1rem;
}

.card-progress {
    width: 100%;
    height: 6px;
    background: #f1f5f9;
    border-radius: 10px;
    overflow: hidden;
    position: relative;
}

.progress-bar {
    height: 100%;
    background: var(--primary-gradient);
    border-radius: 10px;
    width: var(--progress);
    transition: width 2s ease-out 0.5s;
    position: relative;
}

.card-students .progress-bar { background: var(--success-gradient); }
.card-lecturers .progress-bar { background: var(--primary-gradient); }
.card-companies .progress-bar { background: var(--secondary-gradient); }
.card-pending .progress-bar { background: var(--warning-gradient); }

.progress-bar::after {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.4), transparent);
    animation: shine 2s infinite;
}

@keyframes shine {
    0% { transform: translateX(-100%); }
    100% { transform: translateX(100%); }
}

.action-button {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.75rem 1.5rem;
    background: var(--danger-gradient);
    color: white;
    text-decoration: none;
    border-radius: 25px;
    font-weight: 600;
    font-size: 0.9rem;
    transition: all 0.3s ease;
    margin-top: 1rem;
    box-shadow: 0 4px 15px rgba(255, 107, 107, 0.3);
}

.action-button:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(255, 107, 107, 0.4);
    text-decoration: none;
    color: white;
}

.action-button svg {
    width: 16px;
    height: 16px;
}

.action-button.pulse {
    animation: pulseButton 2s infinite;
}

@keyframes pulseButton {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.05); }
}

/* Actions Grid */
.actions-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
    gap: 2rem;
}

.action-card {
    background: white;
    border-radius: var(--border-radius-large);
    padding: 2rem;
    box-shadow: var(--card-shadow);
    transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    position: relative;
    overflow: hidden;
    text-decoration: none;
    color: inherit;
    display: block;
    animation: slideInUp 0.6s ease-out;
    animation-fill-mode: both;
}

.action-card:nth-child(1) { animation-delay: 0.1s; }
.action-card:nth-child(2) { animation-delay: 0.15s; }
.action-card:nth-child(3) { animation-delay: 0.2s; }
.action-card:nth-child(4) { animation-delay: 0.25s; }
.action-card:nth-child(5) { animation-delay: 0.3s; }
.action-card:nth-child(6) { animation-delay: 0.35s; }
.action-card:nth-child(7) { animation-delay: 0.4s; }

.action-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: var(--primary-gradient);
    transform: scaleX(0);
    transition: transform 0.3s ease;
}

.action-card[data-category="monitoring"]::before { background: var(--success-gradient); }
.action-card[data-category="users"]::before { background: var(--primary-gradient); }
.action-card[data-category="partners"]::before { background: var(--secondary-gradient); }
.action-card[data-category="documents"]::before { background: var(--warning-gradient); }
.action-card[data-category="reports"]::before { background: var(--dark-gradient); }

.action-card:hover::before {
    transform: scaleX(1);
}

.action-card:hover {
    transform: translateY(-8px);
    box-shadow: var(--card-shadow-hover);
    text-decoration: none;
    color: inherit;
}

.action-card:hover .action-background {
    opacity: 1;
    transform: scale(1);
}

.action-card:hover .action-icon {
    transform: rotate(5deg) scale(1.1);
}

.action-card:hover .action-arrow {
    transform: translateX(5px);
}

.action-background {
    position: absolute;
    top: -50%;
    right: -50%;
    width: 100px;
    height: 100px;
    background: var(--primary-gradient);
    border-radius: 50%;
    opacity: 0.05;
    transform: scale(0);
    transition: all 0.6s ease;
}

.action-card[data-category="monitoring"] .action-background { background: var(--success-gradient); }
.action-card[data-category="users"] .action-background { background: var(--primary-gradient); }
.action-card[data-category="partners"] .action-background { background: var(--secondary-gradient); }
.action-card[data-category="documents"] .action-background { background: var(--warning-gradient); }
.action-card[data-category="reports"] .action-background { background: var(--dark-gradient); }

.action-icon {
    width: 60px;
    height: 60px;
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    margin-bottom: 1.5rem;
    transition: all 0.3s ease;
    position: relative;
    z-index: 10;
}

.action-card[data-category="monitoring"] .action-icon { background: var(--success-gradient); }
.action-card[data-category="users"] .action-icon { background: var(--primary-gradient); }
.action-card[data-category="partners"] .action-icon { background: var(--secondary-gradient); }
.action-card[data-category="documents"] .action-icon { background: var(--warning-gradient); }
.action-card[data-category="reports"] .action-icon { background: var(--dark-gradient); }

.action-icon svg {
    width: 28px;
    height: 28px;
}

.action-content {
    position: relative;
    z-index: 10;
}

.action-content h3 {
    font-size: 1.4rem;
    font-weight: 700;
    color: var(--text-primary);
    margin-bottom: 0.5rem;
    line-height: 1.3;
}

.action-content p {
    color: var(--text-secondary);
    font-size: 0.95rem;
    line-height: 1.5;
    margin-bottom: 1rem;
}

.action-badge {
    display: inline-block;
    padding: 0.3rem 0.8rem;
    background: rgba(102, 126, 234, 0.1);
    color: #667eea;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.action-card[data-category="monitoring"] .action-badge { 
    background: rgba(79, 172, 254, 0.1); 
    color: #4facfe; 
}
.action-card[data-category="users"] .action-badge { 
    background: rgba(102, 126, 234, 0.1); 
    color: #667eea; 
}
.action-card[data-category="partners"] .action-badge { 
    background: rgba(240, 147, 251, 0.1); 
    color: #f093fb; 
}
.action-card[data-category="documents"] .action-badge { 
    background: rgba(255, 236, 210, 0.5); 
    color: #e67e22; 
}
.action-card[data-category="reports"] .action-badge { 
    background: rgba(44, 62, 80, 0.1); 
    color: #2c3e50; 
}

.action-arrow {
    position: absolute;
    top: 2rem;
    right: 2rem;
    width: 30px;
    height: 30px;
    color: var(--text-muted);
    transition: all 0.3s ease;
}

.action-arrow svg {
    width: 100%;
    height: 100%;
}

/* Number Animation */
@keyframes countUp {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}

.card-number {
    animation: countUp 1s ease-out 0.8s both;
}

/* Responsive Design */
@media (max-width: 1200px) {
    .analytics-grid {
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 1.5rem;
    }
    
    .actions-grid {
        grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
        gap: 1.5rem;
    }
}

@media (max-width: 768px) {
    .hero-section {
        padding: 3rem 1rem 2rem;
        margin-bottom: 2rem;
    }
    
    .hero-title {
        font-size: 2.2rem;
    }
    
    .hero-subtitle {
        font-size: 1.1rem;
    }
    
    .section-title {
        font-size: 2rem;
    }
    
    .analytics-section, .actions-section {
        padding: 0 1rem 2rem;
    }
    
    .analytics-grid {
        grid-template-columns: 1fr;
        gap: 1rem;
    }
    
    .actions-grid {
        grid-template-columns: 1fr;
        gap: 1rem;
    }
    
    .analytics-card, .action-card {
        padding: 1.5rem;
    }
    
    .avatar-circle {
        width: 80px;
        height: 80px;
        font-size: 1.5rem;
    }
    
    .card-number {
        font-size: 2.5rem;
    }
}

@media (max-width: 480px) {
    .hero-title {
        font-size: 1.8rem;
    }
    
    .hero-subtitle {
        font-size: 1rem;
    }
    
    .section-title {
        font-size: 1.6rem;
    }
    
    .analytics-card, .action-card {
        padding: 1rem;
    }
    
    .card-number {
        font-size: 2rem;
    }
    
    .action-content h3 {
        font-size: 1.2rem;
    }
}

/* Dark mode support */
@media (prefers-color-scheme: dark) {
    :root {
        --text-primary: #e2e8f0;
        --text-secondary: #a0aec0;
        --text-muted: #718096;
    }
    
    body {
        background: linear-gradient(135deg, #1a202c 0%, #2d3748 100%);
    }
    
    .analytics-card, .action-card {
        background: #2d3748;
        color: var(--text-primary);
    }
    
    .card-progress {
        background: #4a5568;
    }
}

/* Accessibility */
@media (prefers-reduced-motion: reduce) {
    * {
        animation-duration: 0.01ms !important;
        animation-iteration-count: 1 !important;
        transition-duration: 0.01ms !important;
    }
    
    .floating-shapes {
        display: none;
    }
}

/* Print styles */
@media print {
    .hero-section {
        background: none !important;
        color: black !important;
    }
    
    .analytics-card, .action-card {
        box-shadow: none !important;
        border: 1px solid #ccc !important;
    }
    
    .floating-shapes {
        display: none !important;
    }
}
</style>

<script>
// JavaScript untuk animasi dan interaktivitas
document.addEventListener('DOMContentLoaded', function() {
    // Update waktu real-time
    function updateTime() {
        const now = new Date();
        const timeString = now.toLocaleString('id-ID', {
            weekday: 'long',
            year: 'numeric',
            month: 'long',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit'
        });
        
        const timeElement = document.getElementById('currentTime');
        if (timeElement) {
            timeElement.textContent = timeString;
        }
    }
    
    // Update setiap detik
    updateTime();
    setInterval(updateTime, 1000);
    
    // Animasi counter untuk angka
    function animateCounter(element) {
        const target = parseInt(element.getAttribute('data-count'));
        const duration = 2000;
        const step = target / (duration / 16);
        let current = 0;
        
        const timer = setInterval(() => {
            current += step;
            if (current >= target) {
                current = target;
                clearInterval(timer);
            }
            element.textContent = Math.floor(current).toLocaleString('id-ID');
        }, 16);
    }
    
    // Trigger animasi counter dengan Intersection Observer
    const counterElements = document.querySelectorAll('.card-number[data-count]');
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting && !entry.target.classList.contains('animated')) {
                entry.target.classList.add('animated');
                animateCounter(entry.target);
            }
        });
    }, { threshold: 0.5 });
    
    counterElements.forEach(el => observer.observe(el));
    
    // Parallax effect untuk floating shapes
    window.addEventListener('scroll', () => {
        const scrolled = window.pageYOffset;
        const shapes = document.querySelectorAll('.shape');
        
        shapes.forEach((shape, index) => {
            const speed = 0.5 + (index * 0.1);
            const yPos = scrolled * speed;
            shape.style.transform = `translateY(${yPos}px)`;
        });
    });
    
    // Smooth hover effects
    const cards = document.querySelectorAll('.analytics-card, .action-card');
    cards.forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-10px) scale(1.02)';
        });
        
        card.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0) scale(1)';
        });
    });
    
    // Progress bar animation
    setTimeout(() => {
        const progressBars = document.querySelectorAll('.progress-bar');
        progressBars.forEach(bar => {
            const progress = bar.style.getPropertyValue('--progress');
            bar.style.setProperty('--progress', '0%');
            setTimeout(() => {
                bar.style.setProperty('--progress', progress);
            }, 100);
        });
    }, 1000);
    
    // Notification untuk urgent items
    const urgentCards = document.querySelectorAll('.alert-card');
    if (urgentCards.length > 0) {
        setTimeout(() => {
            urgentCards.forEach(card => {
                const notification = document.createElement('div');
                notification.className = 'urgent-notification';
                notification.innerHTML = '⚠️ Perhatian diperlukan!';
                notification.style.cssText = `
                    position: fixed;
                    top: 20px;
                    right: 20px;
                    background: linear-gradient(135deg, #ff9a9e 0%, #fecfef 100%);
                    color: white;
                    padding: 1rem 1.5rem;
                    border-radius: 10px;
                    box-shadow: 0 10px 30px rgba(255,154,158,0.3);
                    z-index: 1000;
                    animation: slideInRight 0.5s ease-out;
                `;
                
                document.body.appendChild(notification);
                
                setTimeout(() => {
                    notification.style.animation = 'slideOutRight 0.5s ease-in forwards';
                    setTimeout(() => notification.remove(), 500);
                }, 3000);
            });
        }, 2000);
    }
});

// CSS untuk notifikasi
const notificationStyles = document.createElement('style');
notificationStyles.textContent = `
    @keyframes slideInRight {
        from { transform: translateX(100%); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
    }
    
    @keyframes slideOutRight {
        from { transform: translateX(0); opacity: 1; }
        to { transform: translateX(100%); opacity: 0; }
    }
`;
document.head.appendChild(notificationStyles);
</script>

<?php
require_once '../includes/footer.php';
?>