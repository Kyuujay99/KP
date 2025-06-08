<?php
// /KP/perusahaan/dashboard.php (Versi Modern)

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// 1. OTENTIKASI DAN OTORISASI
require_once '../includes/auth_check.php';
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'perusahaan') {
    header("Location: /KP/index.php?error=unauthorized_perusahaan");
    exit();
}

$id_perusahaan_login = $_SESSION['user_id'];
$nama_perusahaan_login = $_SESSION['user_nama'];
require_once '../config/db_connect.php';

// Inisialisasi variabel statistik
$stats = [
    'pengajuan_masuk' => 0,
    'mahasiswa_aktif' => 0,
    'perlu_penilaian' => 0,
];

if ($conn) {
    // Hitung jumlah pengajuan baru yang menunggu konfirmasi perusahaan
    $stmt_pengajuan = $conn->prepare("SELECT COUNT(id_pengajuan) AS total FROM pengajuan_kp WHERE id_perusahaan = ? AND status_pengajuan = 'menunggu_konfirmasi_perusahaan'");
    if($stmt_pengajuan) {
        $stmt_pengajuan->bind_param("i", $id_perusahaan_login);
        $stmt_pengajuan->execute();
        $result = $stmt_pengajuan->get_result()->fetch_assoc();
        $stats['pengajuan_masuk'] = $result['total'] ?? 0;
        $stmt_pengajuan->close();
    }

    // Hitung jumlah mahasiswa yang sedang aktif KP di perusahaan
    $stmt_aktif = $conn->prepare("SELECT COUNT(id_pengajuan) AS total FROM pengajuan_kp WHERE id_perusahaan = ? AND status_pengajuan = 'kp_berjalan'");
     if($stmt_aktif) {
        $stmt_aktif->bind_param("i", $id_perusahaan_login);
        $stmt_aktif->execute();
        $result = $stmt_aktif->get_result()->fetch_assoc();
        $stats['mahasiswa_aktif'] = $result['total'] ?? 0;
        $stmt_aktif->close();
    }

    // Hitung jumlah mahasiswa yang telah selesai KP tapi belum dinilai oleh perusahaan
    $stmt_nilai = $conn->prepare("SELECT COUNT(pk.id_pengajuan) AS total FROM pengajuan_kp pk LEFT JOIN nilai_kp nk ON pk.id_pengajuan = nk.id_pengajuan WHERE pk.id_perusahaan = ? AND pk.status_pengajuan IN ('selesai_pelaksanaan', 'laporan_disetujui', 'selesai_dinilai') AND nk.nilai_pembimbing_lapangan IS NULL");
    if($stmt_nilai) {
        $stmt_nilai->bind_param("i", $id_perusahaan_login);
        $stmt_nilai->execute();
        $result = $stmt_nilai->get_result()->fetch_assoc();
        $stats['perlu_penilaian'] = $result['total'] ?? 0;
        $stmt_nilai->close();
    }
}

$page_title = "Dashboard Perusahaan";
require_once '../includes/header.php';
?>

<div class="dashboard-modern">
    <div class="hero-section">
        <div class="hero-background">
            <div class="floating-shapes">
                <div class="shape shape-1"></div><div class="shape shape-2"></div><div class="shape shape-3"></div>
            </div>
        </div>
        <div class="hero-content">
            <div class="hero-avatar">
                <div class="avatar-circle">
                    <span class="avatar-text"><?php echo strtoupper(substr($nama_perusahaan_login, 0, 2)); ?></span>
                </div>
                <div class="status-indicator"></div>
            </div>
            <h1 class="hero-title">
                Selamat Datang, <span class="highlight"><?php echo htmlspecialchars($nama_perusahaan_login); ?></span>
            </h1>
            <p class="hero-subtitle">Portal Mitra Kerja Praktek Universitas Teknologi Maju</p>
            <div class="current-time" id="currentTime"></div>
        </div>
    </div>

    <div class="analytics-section">
        <div class="section-header">
            <h2 class="section-title">Ringkasan Aktivitas</h2>
            <div class="section-line"></div>
        </div>
        
        <div class="analytics-grid">
            <div class="analytics-card card-pending <?php echo $stats['pengajuan_masuk'] > 0 ? 'alert-card' : ''; ?>">
                <div class="card-header">
                    <div class="card-icon"><svg viewBox="0 0 24 24"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg></div>
                    <?php if ($stats['pengajuan_masuk'] > 0): ?>
                        <div class="card-trend negative"><span class="trend-icon">⚠</span><span class="trend-text">Urgent</span></div>
                    <?php else: ?>
                        <div class="card-trend neutral"><span class="trend-icon">✓</span><span class="trend-text">Clear</span></div>
                    <?php endif; ?>
                </div>
                <div class="card-content">
                    <div class="card-number" data-count="<?php echo $stats['pengajuan_masuk']; ?>">0</div>
                    <div class="card-label">Pengajuan Baru Masuk</div>
                    <?php if ($stats['pengajuan_masuk'] > 0): ?>
                        <a href="pengajuan_kp_masuk.php" class="action-button pulse"><span>Lihat Pengajuan</span><svg viewBox="0 0 24 24"><path d="M5 12h14M12 5l7 7-7 7"/></svg></a>
                    <?php endif; ?>
                </div>
            </div>

            <div class="analytics-card card-students">
                <div class="card-header">
                    <div class="card-icon"><svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg></div>
                </div>
                <div class="card-content">
                    <div class="card-number" data-count="<?php echo $stats['mahasiswa_aktif']; ?>">0</div>
                    <div class="card-label">Mahasiswa KP Aktif</div>
                    <a href="mahasiswa_kp_list.php" class="action-button" style="background:var(--success-gradient); margin-top:1rem;"><span>Lihat Daftar</span><svg viewBox="0 0 24 24"><path d="M5 12h14M12 5l7 7-7 7"/></svg></a>
                </div>
            </div>

            <div class="analytics-card card-pending <?php echo $stats['perlu_penilaian'] > 0 ? 'alert-card' : ''; ?>">
                <div class="card-header">
                    <div class="card-icon"><svg viewBox="0 0 24 24"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg></div>
                    <?php if ($stats['perlu_penilaian'] > 0): ?>
                        <div class="card-trend negative"><span class="trend-icon">⚠</span><span class="trend-text">Urgent</span></div>
                    <?php else: ?>
                        <div class="card-trend neutral"><span class="trend-icon">✓</span><span class="trend-text">Clear</span></div>
                    <?php endif; ?>
                </div>
                <div class="card-content">
                    <div class="card-number" data-count="<?php echo $stats['perlu_penilaian']; ?>">0</div>
                    <div class="card-label">Mahasiswa Perlu Dinilai</div>
                     <?php if ($stats['perlu_penilaian'] > 0): ?>
                        <a href="penilaian_lapangan_list.php" class="action-button pulse"><span>Beri Penilaian</span><svg viewBox="0 0 24 24"><path d="M5 12h14M12 5l7 7-7 7"/></svg></a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="actions-section">
        <div class="section-header">
            <h2 class="section-title">Menu Navigasi</h2>
            <div class="section-line"></div>
        </div>

        <div class="actions-grid">
             <a href="pengajuan_kp_masuk.php" class="action-card" data-category="monitoring">
                <div class="action-background"></div>
                <div class="action-icon"><svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="12" y1="18" x2="12" y2="12"></line><line x1="9" y1="15" x2="15" y2="15"></line></svg></div>
                <div class="action-content"><h3>Konfirmasi Pengajuan</h3><p>Tinjau dan berikan keputusan untuk lamaran KP yang masuk.</p><div class="action-badge">Keputusan</div></div>
                <div class="action-arrow"><svg viewBox="0 0 24 24"><path d="M5 12h14M12 5l7 7-7 7"/></svg></div>
            </a>
            <a href="mahasiswa_kp_list.php" class="action-card" data-category="users">
                <div class="action-background"></div>
                <div class="action-icon"><svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg></div>
                <div class="action-content"><h3>Daftar Mahasiswa KP</h3><p>Pantau semua mahasiswa yang sedang KP di perusahaan Anda.</p><div class="action-badge">Monitoring</div></div>
                <div class="action-arrow"><svg viewBox="0 0 24 24"><path d="M5 12h14M12 5l7 7-7 7"/></svg></div>
            </a>
            <a href="penilaian_lapangan_list.php" class="action-card" data-category="reports">
                <div class="action-background"></div>
                <div class="action-icon"><svg viewBox="0 0 24 24"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg></div>
                <div class="action-content"><h3>Input Penilaian Lapangan</h3><p>Berikan evaluasi kinerja untuk mahasiswa yang telah selesai KP.</p><div class="action-badge">Evaluasi</div></div>
                <div class="action-arrow"><svg viewBox="0 0 24 24"><path d="M5 12h14M12 5l7 7-7 7"/></svg></div>
            </a>
            <a href="profil_perusahaan.php" class="action-card" data-category="partners">
                <div class="action-background"></div>
                <div class="action-icon"><svg viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg></div>
                <div class="action-content"><h3>Profil Perusahaan</h3><p>Perbarui informasi detail dan kontak perusahaan Anda.</p><div class="action-badge">Manajemen Akun</div></div>
                <div class="action-arrow"><svg viewBox="0 0 24 24"><path d="M5 12h14M12 5l7 7-7 7"/></svg></div>
            </a>
        </div>
    </div>
</div>

<style>
/* CSS lengkap dari dashboard admin/dosen ditempel di sini */
svg{fill:none;stroke:currentColor;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}:root{--primary-gradient:linear-gradient(135deg,#667eea 0%,#764ba2 100%);--secondary-gradient:linear-gradient(135deg,#f093fb 0%,#f5576c 100%);--success-gradient:linear-gradient(135deg,#4facfe 0%,#00f2fe 100%);--warning-gradient:linear-gradient(135deg,#ffecd2 0%,#fcb69f 100%);--danger-gradient:linear-gradient(135deg,#ff9a9e 0%,#fecfef 100%);--dark-gradient:linear-gradient(135deg,#2c3e50 0%,#34495e 100%);--card-shadow:0 10px 30px rgba(0,0,0,.1);--card-shadow-hover:0 20px 40px rgba(0,0,0,.15);--border-radius:16px;--border-radius-large:24px;--text-primary:#2d3748;--text-secondary:#718096;--text-muted:#a0aec0}*{margin:0;padding:0;box-sizing:border-box}body{font-family:Inter,-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;background:linear-gradient(135deg,#f5f7fa 0%,#c3cfe2 100%);min-height:100vh;line-height:1.6;color:var(--text-primary)}.dashboard-modern{min-height:100vh;padding:0}.hero-section{position:relative;background:var(--primary-gradient);padding:4rem 2rem 3rem;overflow:hidden;margin-bottom:3rem}.hero-background{position:absolute;top:0;left:0;right:0;bottom:0;overflow:hidden}.floating-shapes{position:absolute;width:100%;height:100%}.shape{position:absolute;border-radius:50%;background:rgba(255,255,255,.1);animation:float 6s ease-in-out infinite}.shape-1{width:80px;height:80px;top:10%;left:10%;animation-delay:0s}.shape-2{width:120px;height:120px;top:20%;right:10%;animation-delay:1s}.shape-3{width:60px;height:60px;bottom:30%;left:20%;animation-delay:2s}.shape-4{width:100px;height:100px;bottom:10%;right:30%;animation-delay:3s}.shape-5{width:140px;height:140px;top:50%;left:50%;animation-delay:4s;transform:translate(-50%,-50%)}@keyframes float{0%,100%{transform:translateY(0) rotate(0)}50%{transform:translateY(-20px) rotate(180deg)}}.hero-content{position:relative;z-index:10;text-align:center;max-width:800px;margin:0 auto;color:#fff;animation:fadeInUp 1s ease-out}@keyframes fadeInUp{0%{opacity:0;transform:translateY(30px)}to{opacity:1;transform:translateY(0)}}.hero-avatar{position:relative;display:inline-block;margin-bottom:2rem}.avatar-circle{width:100px;height:100px;border-radius:50%;background:rgba(255,255,255,.2);backdrop-filter:blur(10px);border:3px solid rgba(255,255,255,.3);display:flex;align-items:center;justify-content:center;font-size:2rem;font-weight:700;animation:pulse 2s infinite}@keyframes pulse{0%{box-shadow:0 0 0 0 rgba(255,255,255,.4)}70%{box-shadow:0 0 0 20px transparent}100%{box-shadow:0 0 0 0 transparent}}.status-indicator{position:absolute;bottom:8px;right:8px;width:20px;height:20px;background:#10b981;border-radius:50%;border:3px solid #fff;animation:blink 2s infinite}@keyframes blink{0%,50%{opacity:1}51%,to{opacity:.3}}.hero-title{font-size:3rem;font-weight:700;margin-bottom:1rem;line-height:1.2}.highlight{background:linear-gradient(45deg,#ffd700,#ffed4e);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text}.hero-subtitle{font-size:1.3rem;opacity:.9;margin-bottom:2rem;font-weight:300}.current-time{display:inline-block;padding:.5rem 1.5rem;background:rgba(255,255,255,.1);border-radius:30px;font-size:1rem;font-weight:500;backdrop-filter:blur(10px);border:1px solid rgba(255,255,255,.2)}.analytics-section,.actions-section{padding:0 2rem 3rem;max-width:1400px;margin:0 auto}.section-header{text-align:center;margin-bottom:3rem}.section-title{font-size:2.5rem;font-weight:700;color:var(--text-primary);margin-bottom:1rem;position:relative;display:inline-block}.section-line{width:80px;height:4px;background:var(--primary-gradient);margin:0 auto;border-radius:2px;animation:expandWidth 1s ease-out .5s both}@keyframes expandWidth{0%{width:0}to{width:80px}}.analytics-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:2rem;margin-bottom:4rem}.analytics-card{background:#fff;border-radius:var(--border-radius-large);padding:2rem;box-shadow:var(--card-shadow);transition:all .4s cubic-bezier(.175,.885,.32,1.275);position:relative;overflow:hidden;animation:slideInUp .6s ease-out;animation-fill-mode:both}.analytics-card:nth-child(1){animation-delay:.1s}.analytics-card:nth-child(2){animation-delay:.2s}.analytics-card:nth-child(3){animation-delay:.3s}.analytics-card:nth-child(4){animation-delay:.4s}@keyframes slideInUp{0%{opacity:0;transform:translateY(50px)}to{opacity:1;transform:translateY(0)}}.analytics-card:before{content:'';position:absolute;top:0;left:0;right:0;height:4px;background:var(--primary-gradient);transform:scaleX(0);transition:transform .3s ease}.analytics-card:hover:before{transform:scaleX(1)}.analytics-card:hover{transform:translateY(-10px);box-shadow:var(--card-shadow-hover)}.card-students:before{background:var(--success-gradient)}.card-lecturers:before{background:var(--primary-gradient)}.card-companies:before{background:var(--secondary-gradient)}.card-pending:before{background:var(--warning-gradient)}.alert-card:before{background:var(--danger-gradient)}.alert-card{border:2px solid #ff6b6b;animation:alertPulse 2s infinite}@keyframes alertPulse{0%,to{box-shadow:var(--card-shadow)}50%{box-shadow:0 10px 30px rgba(255,107,107,.3)}}.card-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:1.5rem}.card-icon{width:60px;height:60px;border-radius:16px;display:flex;align-items:center;justify-content:center;color:#fff;margin-right:auto}.card-students .card-icon{background:var(--success-gradient)}.card-lecturers .card-icon{background:var(--primary-gradient)}.card-companies .card-icon{background:var(--secondary-gradient)}.card-pending .card-icon{background:var(--warning-gradient)}.card-icon svg{width:28px;height:28px}.card-trend{display:flex;align-items:center;gap:.5rem;padding:.3rem .8rem;border-radius:20px;font-size:.85rem;font-weight:600}.card-trend.positive{background:rgba(16,185,129,.1);color:#10b981}.card-trend.negative{background:rgba(239,68,68,.1);color:#ef4444;animation:blink 1.5s infinite}.card-trend.neutral{background:rgba(107,114,128,.1);color:#6b7280}.card-content{text-align:left}.card-number{font-size:3rem;font-weight:800;color:var(--text-primary);margin-bottom:.5rem;line-height:1;background:linear-gradient(45deg,#667eea,#764ba2);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text}.card-label{font-size:1rem;color:var(--text-secondary);font-weight:500;margin-bottom:1rem}.card-progress{width:100%;height:6px;background:#f1f5f9;border-radius:10px;overflow:hidden;position:relative}.progress-bar{height:100%;background:var(--primary-gradient);border-radius:10px;width:var(--progress);transition:width 2s ease-out .5s;position:relative}.card-students .progress-bar{background:var(--success-gradient)}.card-lecturers .progress-bar{background:var(--primary-gradient)}.card-companies .progress-bar{background:var(--secondary-gradient)}.card-pending .progress-bar{background:var(--warning-gradient)}.progress-bar:after{content:'';position:absolute;top:0;left:0;right:0;bottom:0;background:linear-gradient(90deg,transparent,rgba(255,255,255,.4),transparent);animation:shine 2s infinite}@keyframes shine{0%{transform:translateX(-100%)}to{transform:translateX(100%)}}.action-button{display:inline-flex;align-items:center;gap:.5rem;padding:.75rem 1.5rem;background:var(--danger-gradient);color:#fff;text-decoration:none;border-radius:25px;font-weight:600;font-size:.9rem;transition:all .3s ease;margin-top:1rem;box-shadow:0 4px 15px rgba(255,107,107,.3)}.action-button:hover{transform:translateY(-2px);box-shadow:0 8px 25px rgba(255,107,107,.4);text-decoration:none;color:#fff}.action-button svg{width:16px;height:16px}.action-button.pulse{animation:pulseButton 2s infinite}@keyframes pulseButton{0%,to{transform:scale(1)}50%{transform:scale(1.05)}}.actions-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(350px,1fr));gap:2rem}.action-card{background:#fff;border-radius:var(--border-radius-large);padding:2rem;box-shadow:var(--card-shadow);transition:all .4s cubic-bezier(.175,.885,.32,1.275);position:relative;overflow:hidden;text-decoration:none;color:inherit;display:block;animation:slideInUp .6s ease-out;animation-fill-mode:both}.action-card:nth-child(1){animation-delay:.1s}.action-card:nth-child(2){animation-delay:.15s}.action-card:nth-child(3){animation-delay:.2s}.action-card:nth-child(4){animation-delay:.25s}.action-card:nth-child(5){animation-delay:.3s}.action-card:nth-child(6){animation-delay:.35s}.action-card:nth-child(7){animation-delay:.4s}.action-card:before{content:'';position:absolute;top:0;left:0;right:0;height:4px;background:var(--primary-gradient);transform:scaleX(0);transition:transform .3s ease}.action-card[data-category=monitoring]:before{background:var(--success-gradient)}.action-card[data-category=users]:before{background:var(--primary-gradient)}.action-card[data-category=partners]:before{background:var(--secondary-gradient)}.action-card[data-category=documents]:before{background:var(--warning-gradient)}.action-card[data-category=reports]:before{background:var(--dark-gradient)}.action-card:hover:before{transform:scaleX(1)}.action-card:hover{transform:translateY(-8px);box-shadow:var(--card-shadow-hover);text-decoration:none;color:inherit}.action-card:hover .action-background{opacity:1;transform:scale(1)}.action-card:hover .action-icon{transform:rotate(5deg) scale(1.1)}.action-card:hover .action-arrow{transform:translateX(5px)}.action-background{position:absolute;top:-50%;right:-50%;width:100px;height:100px;background:var(--primary-gradient);border-radius:50%;opacity:.05;transform:scale(0);transition:all .6s ease}.action-card[data-category=monitoring] .action-background{background:var(--success-gradient)}.action-card[data-category=users] .action-background{background:var(--primary-gradient)}.action-card[data-category=partners] .action-background{background:var(--secondary-gradient)}.action-card[data-category=documents] .action-background{background:var(--warning-gradient)}.action-card[data-category=reports] .action-background{background:var(--dark-gradient)}.action-icon{width:60px;height:60px;border-radius:16px;display:flex;align-items:center;justify-content:center;color:#fff;margin-bottom:1.5rem;transition:all .3s ease;position:relative;z-index:10}.action-card[data-category=monitoring] .action-icon{background:var(--success-gradient)}.action-card[data-category=users] .action-icon{background:var(--primary-gradient)}.action-card[data-category=partners] .action-icon{background:var(--secondary-gradient)}.action-card[data-category=documents] .action-icon{background:var(--warning-gradient)}.action-card[data-category=reports] .action-icon{background:var(--dark-gradient)}.action-icon svg{width:28px;height:28px}.action-content{position:relative;z-index:10}.action-content h3{font-size:1.4rem;font-weight:700;color:var(--text-primary);margin-bottom:.5rem;line-height:1.3}.action-content p{color:var(--text-secondary);font-size:.95rem;line-height:1.5;margin-bottom:1rem}.action-badge{display:inline-block;padding:.3rem .8rem;background:rgba(102,126,234,.1);color:#667eea;border-radius:20px;font-size:.8rem;font-weight:600;text-transform:uppercase;letter-spacing:.5px}.action-card[data-category=monitoring] .action-badge{background:rgba(79,172,254,.1);color:#4facfe}.action-card[data-category=users] .action-badge{background:rgba(102,126,234,.1);color:#667eea}.action-card[data-category=partners] .action-badge{background:rgba(240,147,251,.1);color:#f093fb}.action-card[data-category=documents] .action-badge{background:rgba(255,236,210,.5);color:#e67e22}.action-card[data-category=reports] .action-badge{background:rgba(44,62,80,.1);color:#2c3e50}.action-arrow{position:absolute;top:2rem;right:2rem;width:30px;height:30px;color:var(--text-muted);transition:all .3s ease}.action-arrow svg{width:100%;height:100%}@keyframes countUp{0%{opacity:0;transform:translateY(20px)}to{opacity:1;transform:translateY(0)}}.card-number{animation:countUp 1s ease-out .8s both}@media (max-width:1200px){.analytics-grid{grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:1.5rem}.actions-grid{grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:1.5rem}}@media (max-width:768px){.hero-section{padding:3rem 1rem 2rem;margin-bottom:2rem}.hero-title{font-size:2.2rem}.hero-subtitle{font-size:1.1rem}.section-title{font-size:2rem}.analytics-section,.actions-section{padding:0 1rem 2rem}.analytics-grid,.actions-grid{grid-template-columns:1fr;gap:1rem}.analytics-card,.action-card{padding:1.5rem}.avatar-circle{width:80px;height:80px;font-size:1.5rem}.card-number{font-size:2.5rem}}@media (max-width:480px){.hero-title{font-size:1.8rem}.hero-subtitle{font-size:1rem}.section-title{font-size:1.6rem}.analytics-card,.action-card{padding:1rem}.card-number{font-size:2rem}.action-content h3{font-size:1.2rem}}@media (prefers-color-scheme:dark){:root{--text-primary:#e2e8f0;--text-secondary:#a0aec0;--text-muted:#718096}body{background:linear-gradient(135deg,#1a202c 0%,#2d3748 100%)}.analytics-card,.action-card{background:#2d3748;color:var(--text-primary)}.card-progress{background:#4a5568}}@media (prefers-reduced-motion:reduce){*{animation-duration:.01ms!important;animation-iteration-count:1!important;transition-duration:.01ms!important}.floating-shapes{display:none}}@media print{.hero-section{background:none!important;color:#000!important}.analytics-card,.action-card{box-shadow:none!important;border:1px solid #ccc!important}.floating-shapes{display:none!important}}
</style>
<script>
document.addEventListener('DOMContentLoaded',function(){function e(){const e=(new Date).toLocaleString("id-ID",{weekday:"long",year:"numeric",month:"long",day:"numeric",hour:"2-digit",minute:"2-digit",second:"2-digit"}),t=document.getElementById("currentTime");t&&(t.textContent=e)}e(),setInterval(e,1e3);const t=document.querySelectorAll(".card-number[data-count]");function n(e){const t=parseInt(e.getAttribute("data-count")),n=t/125||1;let o=0;const a=setInterval(()=>{o+=n,o>=t&&(o=t,clearInterval(a)),e.textContent=Math.floor(o).toLocaleString("id-ID")},16)}const o=new IntersectionObserver((e,t)=>{e.forEach(e=>{e.isIntersecting&&!e.target.classList.contains("animated")&&(e.target.classList.add("animated"),n(e.target),t.unobserve(e.target))})},{threshold:.5});t.forEach(e=>o.observe(e)),window.addEventListener("scroll",()=>{const e=window.pageYOffset;document.querySelectorAll(".shape").forEach((t,n)=>{const o=.5+.1*n,a=e*o;t.style.transform=`translateY(${a}px)`})});const a=document.querySelectorAll(".analytics-card, .action-card");a.forEach(e=>{e.addEventListener("mouseenter",function(){this.style.transform="translateY(-10px) scale(1.02)"}),e.addEventListener("mouseleave",function(){this.style.transform="translateY(0) scale(1)"})}),setTimeout(()=>{document.querySelectorAll(".progress-bar").forEach(e=>{const t=e.style.getPropertyValue("--progress");e.style.setProperty("--progress","0%"),setTimeout(()=>{e.style.setProperty("--progress",t)},100)})},1e3);const c=document.querySelectorAll(".alert-card");if(c.length>0){const e=document.querySelector(".urgent-notification");e||setTimeout(()=>{c.forEach(e=>{const t=document.createElement("div");t.className="urgent-notification",t.innerHTML="⚠️ Anda memiliki item yang perlu perhatian!",t.style.cssText="position:fixed;top:20px;right:20px;background:linear-gradient(135deg, #ff9a9e 0%, #fecfef 100%);color:white;padding:1rem 1.5rem;border-radius:10px;box-shadow:0 10px 30px rgba(255,154,158,0.3);z-index:1000;animation:slideInRight .5s ease-out",document.body.appendChild(t),setTimeout(()=>{t.style.animation="slideOutRight .5s ease-in forwards",setTimeout(()=>t.remove(),500)},4e3)})},2e3)}});const r=document.createElement("style");r.textContent="\n    @keyframes slideInRight {\n        from { transform: translateX(100%); opacity: 0; }\n        to { transform: translateX(0); opacity: 1; }\n    }\n    \n    @keyframes slideOutRight {\n        from { transform: translateX(0); opacity: 1; }\n        to { transform: translateX(100%); opacity: 0; }\n    }\n",document.head.appendChild(r);
</script>

<?php
require_once '../includes/footer.php';
if (isset($conn) && $conn instanceof mysqli) {
    $conn->close();
}
?>