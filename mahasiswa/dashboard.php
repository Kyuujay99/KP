<?php
// /KP/mahasiswa/dashboard.php (Versi Modern)

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Otentikasi dan Otorisasi
require_once '../includes/auth_check.php';
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'mahasiswa') {
    session_unset();
    session_destroy();
    header("Location: /KP/index.php?error=unauthorized");
    exit();
}

$nim_mahasiswa = $_SESSION['user_id'];
$nama_mahasiswa = $_SESSION['user_nama'];

// Koneksi ke DB untuk mengambil data
require_once '../config/db_connect.php';

// Inisialisasi data untuk ditampilkan di dashboard
$kp_data = [
    'id_pengajuan' => null,
    'judul_kp' => 'Belum ada pengajuan KP',
    'status_raw' => 'belum_mengajukan',
    'status_text' => 'Belum Ada Pengajuan',
    'nama_dosen' => 'Belum Ditentukan',
    'nama_perusahaan' => 'Belum Ditentukan',
    'logbook_count' => 0
];
$status_is_urgent = false;

if ($conn && ($conn instanceof mysqli)) {
    // 1. Ambil data pengajuan KP terakhir beserta nama dospem dan perusahaan
    $sql_kp = "SELECT 
                    pk.id_pengajuan, pk.judul_kp, pk.status_pengajuan,
                    dp.nama_dosen, p.nama_perusahaan
                FROM pengajuan_kp pk
                LEFT JOIN dosen_pembimbing dp ON pk.nip_dosen_pembimbing_kp = dp.nip
                LEFT JOIN perusahaan p ON pk.id_perusahaan = p.id_perusahaan
                WHERE pk.nim = ?
                ORDER BY pk.tanggal_pengajuan DESC, pk.id_pengajuan DESC
                LIMIT 1";
                
    $stmt_kp = $conn->prepare($sql_kp);
    if ($stmt_kp) {
        $stmt_kp->bind_param("s", $nim_mahasiswa);
        $stmt_kp->execute();
        $result_kp = $stmt_kp->get_result();
        
        if ($result_kp->num_rows > 0) {
            $row_kp = $result_kp->fetch_assoc();
            $kp_data['id_pengajuan'] = $row_kp['id_pengajuan'];
            $kp_data['judul_kp'] = htmlspecialchars($row_kp['judul_kp']);
            $kp_data['status_raw'] = $row_kp['status_pengajuan'];
            $kp_data['status_text'] = ucfirst(str_replace('_', ' ', $row_kp['status_pengajuan']));
            $kp_data['nama_dosen'] = $row_kp['nama_dosen'] ? htmlspecialchars($row_kp['nama_dosen']) : 'Belum Ditentukan';
            $kp_data['nama_perusahaan'] = $row_kp['nama_perusahaan'] ? htmlspecialchars($row_kp['nama_perusahaan']) : 'Belum Ditentukan';

            // Tentukan apakah status memerlukan perhatian khusus
            $urgent_statuses = ['ditolak_dospem', 'ditolak_perusahaan', 'ditolak_admin_prodi', 'perlu_revisi'];
            if (in_array($kp_data['status_raw'], $urgent_statuses)) {
                $status_is_urgent = true;
            }

            // 2. Hitung jumlah logbook jika ada pengajuan
            $sql_logbook = "SELECT COUNT(id_logbook) AS total FROM logbook WHERE id_pengajuan = ?";
            $stmt_logbook = $conn->prepare($sql_logbook);
            if ($stmt_logbook) {
                $stmt_logbook->bind_param("i", $kp_data['id_pengajuan']);
                $stmt_logbook->execute();
                $result_logbook = $stmt_logbook->get_result();
                if($row_logbook = $result_logbook->fetch_assoc()) {
                    $kp_data['logbook_count'] = $row_logbook['total'];
                }
                $stmt_logbook->close();
            }
        }
        $stmt_kp->close();
    }
}

$page_title = "Dashboard Mahasiswa";
require_once '../includes/header.php'; // Memuat header, navbar, dan CSS
?>

<div class="dashboard-modern">
    <div class="hero-section">
        <div class="hero-background">
            <div class="floating-shapes">
                <div class="shape shape-1"></div><div class="shape shape-2"></div><div class="shape shape-3"></div><div class="shape shape-4"></div><div class="shape shape-5"></div>
            </div>
        </div>
        <div class="hero-content">
            <div class="hero-avatar">
                <div class="avatar-circle">
                    <span class="avatar-text"><?php echo strtoupper(substr($nama_mahasiswa, 0, 2)); ?></span>
                </div>
                <div class="status-indicator"></div>
            </div>
            <h1 class="hero-title">
                Selamat Datang, <span class="highlight"><?php echo htmlspecialchars(explode(' ', $nama_mahasiswa)[0]); ?>!</span>
            </h1>
            <p class="hero-subtitle">Pusat Kendali untuk Semua Aktivitas Kerja Praktek Anda</p>
                <div class="current-time" id="currentTime"></div>
        </div>
    </div>

    <div class="analytics-section">
        <div class="section-header">
            <h2 class="section-title">Informasi Kerja Praktek</h2>
            <div class="section-line"></div>
        </div>
        
        <div class="analytics-grid">
            <div class="analytics-card card-pending <?php echo $status_is_urgent ? 'alert-card' : ''; ?>">
                <div class="card-header">
                    <div class="card-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                    <?php if ($status_is_urgent): ?>
                        <div class="card-trend negative"><span class="trend-icon">⚠</span><span class="trend-text">Perhatian</span></div>
                    <?php else: ?>
                        <div class="card-trend positive"><span class="trend-icon">✓</span><span class="trend-text">Terkini</span></div>
                    <?php endif; ?>
                </div>
                <div class="card-content">
                    <div class="card-label" style="font-size: 1.2rem; font-weight: bold; margin-bottom: 0.5rem;">Status Pengajuan</div>
                    <div class="card-number" style="font-size: 1.5rem; -webkit-text-fill-color: initial; background: none; margin-bottom: 1rem; line-height: 1.3;"><?php echo $kp_data['status_text']; ?></div>
                    <?php if ($kp_data['id_pengajuan']): ?>
                        <a href="pengajuan_kp_detail.php?id=<?php echo $kp_data['id_pengajuan']; ?>" class="action-button"><span>Lihat Detail</span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M12 5l7 7-7 7"/></svg></a>
                    <?php endif; ?>
                </div>
            </div>

            <div class="analytics-card card-lecturers">
                <div class="card-header">
                    <div class="card-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></div>
                </div>
                <div class="card-content">
                    <div class="card-label" style="font-size: 1.2rem; font-weight: bold; margin-bottom: 0.5rem;">Dosen Pembimbing</div>
                    <div class="card-number" style="font-size: 1.5rem; -webkit-text-fill-color: initial; background: none; margin-bottom: 1rem; line-height: 1.3;"><?php echo $kp_data['nama_dosen']; ?></div>
                </div>
            </div>

            <div class="analytics-card card-companies">
                <div class="card-header">
                    <div class="card-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 21h18"/><path d="M5 21V7l8-4v18"/><path d="M19 21V11l-6-4"/></svg></div>
                </div>
                <div class="card-content">
                    <div class="card-label" style="font-size: 1.2rem; font-weight: bold; margin-bottom: 0.5rem;">Perusahaan KP</div>
                    <div class="card-number" style="font-size: 1.5rem; -webkit-text-fill-color: initial; background: none; margin-bottom: 1rem; line-height: 1.3;"><?php echo $kp_data['nama_perusahaan']; ?></div>
                </div>
            </div>

            <div class="analytics-card card-students">
                 <div class="card-header">
                    <div class="card-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg></div>
                </div>
                <div class="card-content">
                    <div class="card-label" style="font-size: 1.2rem; font-weight: bold; margin-bottom: 0.5rem;">Logbook Terisi</div>
                    <div class="card-number" data-count="<?php echo $kp_data['logbook_count']; ?>">0</div>
                     <div class="card-progress"><div class="progress-bar" style="--progress: <?php echo ($kp_data['logbook_count'] > 0) ? '100%' : '0%'; ?>"></div></div>
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
            <a href="pengajuan_kp_form.php" class="action-card" data-category="documents">
                <div class="action-background"></div>
                <div class="action-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><path d="M14 2v6h6"/><path d="M12 18v-6"/><path d="M9 15h6"/></svg></div>
                <div class="action-content"><h3>Pengajuan KP Baru</h3><p>Isi formulir untuk memulai proses Kerja Praktek Anda.</p><div class="action-badge">Mulai Disini</div></div>
                <div class="action-arrow"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M12 5l7 7-7 7"/></svg></div>
            </a>
            <a href="pengajuan_kp_view.php" class="action-card" data-category="monitoring">
                <div class="action-background"></div>
                <div class="action-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 13v6a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2h6"/><path d="M15 3h6v6"/><path d="M10 14L21 3"/></svg></div>
                <div class="action-content"><h3>Riwayat Pengajuan</h3><p>Pantau status semua pengajuan Kerja Praktek Anda.</p><div class="action-badge">Monitoring</div></div>
                <div class="action-arrow"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M12 5l7 7-7 7"/></svg></div>
            </a>
            <a href="bimbingan_view.php" class="action-card" data-category="users">
                <div class="action-background"></div>
                <div class="action-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/></svg></div>
                <div class="action-content"><h3>Info Bimbingan</h3><p>Lihat jadwal dan catatan bimbingan dari dosen.</p><div class="action-badge">Akademik</div></div>
                <div class="action-arrow"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M12 5l7 7-7 7"/></svg></div>
            </a>
            <a href="logbook_view.php" class="action-card" data-category="reports">
                <div class="action-background"></div>
                <div class="action-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 3h6a4 4 0 014 4v14a3 3 0 00-3-3H2z"/><path d="M22 3h-6a4 4 0 00-4 4v14a3 3 0 013-3h7z"/></svg></div>
                <div class="action-content"><h3>Riwayat Logbook</h3><p>Tinjau semua catatan logbook yang telah Anda isi.</p><div class="action-badge">Laporan</div></div>
                <div class="action-arrow"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M12 5l7 7-7 7"/></svg></div>
            </a>
            <a href="dokumen_view.php" class="action-card" data-category="documents">
                <div class="action-background"></div>
                <div class="action-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M13 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V9z"/><path d="M13 2v7h7"/></svg></div>
                <div class="action-content"><h3>Dokumen Saya</h3><p>Akses semua dokumen yang telah Anda unggah.</p><div class="action-badge">Upload</div></div>
                <div class="action-arrow"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M12 5l7 7-7 7"/></svg></div>
            </a>
            <a href="profil.php" class="action-card" data-category="users">
                <div class="action-background"></div>
                <div class="action-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></div>
                <div class="action-content"><h3>Profil Saya</h3><p>Lihat dan perbarui data pribadi Anda.</p><div class="action-badge">Akun</div></div>
                <div class="action-arrow"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M12 5l7 7-7 7"/></svg></div>
            </a>
        </div>
    </div>
</div>

<style>
:root { --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%); --secondary-gradient: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); --success-gradient: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); --warning-gradient: linear-gradient(135deg, #ffecd2 0%, #fcb69f 100%); --danger-gradient: linear-gradient(135deg, #ff9a9e 0%, #fecfef 100%); --dark-gradient: linear-gradient(135deg, #2c3e50 0%, #34495e 100%); --card-shadow: 0 10px 30px rgba(0,0,0,0.1); --card-shadow-hover: 0 20px 40px rgba(0,0,0,0.15); --border-radius: 16px; --border-radius-large: 24px; --text-primary: #2d3748; --text-secondary: #718096; --text-muted: #a0aec0; } * { margin: 0; padding: 0; box-sizing: border-box; } body { font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%); min-height: 100vh; line-height: 1.6; color: var(--text-primary); } .dashboard-modern { min-height: 100vh; padding: 0; } .hero-section { position: relative; background: var(--primary-gradient); padding: 4rem 2rem 3rem; overflow: hidden; margin-bottom: 3rem; } .hero-background { position: absolute; top: 0; left: 0; right: 0; bottom: 0; overflow: hidden; } .floating-shapes { position: absolute; width: 100%; height: 100%; } .shape { position: absolute; border-radius: 50%; background: rgba(255,255,255,0.1); animation: float 6s ease-in-out infinite; } .shape-1 { width: 80px; height: 80px; top: 10%; left: 10%; animation-delay: 0s; } .shape-2 { width: 120px; height: 120px; top: 20%; right: 10%; animation-delay: 1s; } .shape-3 { width: 60px; height: 60px; bottom: 30%; left: 20%; animation-delay: 2s; } .shape-4 { width: 100px; height: 100px; bottom: 10%; right: 30%; animation-delay: 3s; } .shape-5 { width: 140px; height: 140px; top: 50%; left: 50%; animation-delay: 4s; transform: translate(-50%, -50%); } @keyframes float { 0%, 100% { transform: translateY(0px) rotate(0deg); } 50% { transform: translateY(-20px) rotate(180deg); } } .hero-content { position: relative; z-index: 10; text-align: center; max-width: 800px; margin: 0 auto; color: white; animation: fadeInUp 1s ease-out; } @keyframes fadeInUp { from { opacity: 0; transform: translateY(30px); } to { opacity: 1; transform: translateY(0); } } .hero-avatar { position: relative; display: inline-block; margin-bottom: 2rem; } .avatar-circle { width: 100px; height: 100px; border-radius: 50%; background: rgba(255,255,255,0.2); backdrop-filter: blur(10px); border: 3px solid rgba(255,255,255,0.3); display: flex; align-items: center; justify-content: center; font-size: 2rem; font-weight: bold; animation: pulse 2s infinite; } @keyframes pulse { 0% { box-shadow: 0 0 0 0 rgba(255,255,255,0.4); } 70% { box-shadow: 0 0 0 20px rgba(255,255,255,0); } 100% { box-shadow: 0 0 0 0 rgba(255,255,255,0); } } .status-indicator { position: absolute; bottom: 8px; right: 8px; width: 20px; height: 20px; background: #10b981; border-radius: 50%; border: 3px solid white; animation: blink 2s infinite; } @keyframes blink { 0%, 50% { opacity: 1; } 51%, 100% { opacity: 0.3; } } .hero-title { font-size: 3rem; font-weight: 700; margin-bottom: 1rem; line-height: 1.2; } .highlight { background: linear-gradient(45deg, #ffd700, #ffed4e); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; } .hero-subtitle { font-size: 1.3rem; opacity: 0.9; margin-bottom: 2rem; font-weight: 300; } .current-time { display: inline-block; padding: 0.5rem 1.5rem; background: rgba(255,255,255,0.1); border-radius: 30px; font-size: 1rem; font-weight: 500; backdrop-filter: blur(10px); border: 1px solid rgba(255,255,255,0.2); } .analytics-section, .actions-section { padding: 0 2rem 3rem; max-width: 1400px; margin: 0 auto; } .section-header { text-align: center; margin-bottom: 3rem; } .section-title { font-size: 2.5rem; font-weight: 700; color: var(--text-primary); margin-bottom: 1rem; position: relative; display: inline-block; } .section-line { width: 80px; height: 4px; background: var(--primary-gradient); margin: 0 auto; border-radius: 2px; animation: expandWidth 1s ease-out 0.5s both; } @keyframes expandWidth { from { width: 0; } to { width: 80px; } } .analytics-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 2rem; margin-bottom: 4rem; } .analytics-card { background: white; border-radius: var(--border-radius-large); padding: 2rem; box-shadow: var(--card-shadow); transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275); position: relative; overflow: hidden; animation: slideInUp 0.6s ease-out; animation-fill-mode: both; } .analytics-card:nth-child(1) { animation-delay: 0.1s; } .analytics-card:nth-child(2) { animation-delay: 0.2s; } .analytics-card:nth-child(3) { animation-delay: 0.3s; } .analytics-card:nth-child(4) { animation-delay: 0.4s; } @keyframes slideInUp { from { opacity: 0; transform: translateY(50px); } to { opacity: 1; transform: translateY(0); } } .analytics-card::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px; background: var(--primary-gradient); transform: scaleX(0); transition: transform 0.3s ease; } .analytics-card:hover::before { transform: scaleX(1); } .analytics-card:hover { transform: translateY(-10px); box-shadow: var(--card-shadow-hover); } .card-students::before { background: var(--success-gradient); } .card-lecturers::before { background: var(--primary-gradient); } .card-companies::before { background: var(--secondary-gradient); } .card-pending::before { background: var(--warning-gradient); } .alert-card::before { background: var(--danger-gradient); } .alert-card { border: 2px solid #ff6b6b; animation: alertPulse 2s infinite; } @keyframes alertPulse { 0%, 100% { box-shadow: var(--card-shadow); } 50% { box-shadow: 0 10px 30px rgba(255, 107, 107, 0.3); } } .card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; } .card-icon { width: 60px; height: 60px; border-radius: 16px; display: flex; align-items: center; justify-content: center; color: white; margin-right: auto; } .card-students .card-icon { background: var(--success-gradient); } .card-lecturers .card-icon { background: var(--primary-gradient); } .card-companies .card-icon { background: var(--secondary-gradient); } .card-pending .card-icon { background: var(--warning-gradient); } .card-icon svg { width: 28px; height: 28px; } .card-trend { display: flex; align-items: center; gap: 0.5rem; padding: 0.3rem 0.8rem; border-radius: 20px; font-size: 0.85rem; font-weight: 600; } .card-trend.positive { background: rgba(16, 185, 129, 0.1); color: #10b981; } .card-trend.negative { background: rgba(239, 68, 68, 0.1); color: #ef4444; animation: blink 1.5s infinite; } .card-trend.neutral { background: rgba(107, 114, 128, 0.1); color: #6b7280; } .card-content { text-align: left; } .card-number { font-size: 3rem; font-weight: 800; color: var(--text-primary); margin-bottom: 0.5rem; line-height: 1; background: linear-gradient(45deg, #667eea, #764ba2); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; } .card-label { font-size: 1rem; color: var(--text-secondary); font-weight: 500; margin-bottom: 1rem; } .card-progress { width: 100%; height: 6px; background: #f1f5f9; border-radius: 10px; overflow: hidden; position: relative; } .progress-bar { height: 100%; background: var(--primary-gradient); border-radius: 10px; width: var(--progress); transition: width 2s ease-out 0.5s; position: relative; } .card-students .progress-bar { background: var(--success-gradient); } .card-lecturers .progress-bar { background: var(--primary-gradient); } .card-companies .progress-bar { background: var(--secondary-gradient); } .card-pending .progress-bar { background: var(--warning-gradient); } .progress-bar::after { content: ''; position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: linear-gradient(90deg, transparent, rgba(255,255,255,0.4), transparent); animation: shine 2s infinite; } @keyframes shine { 0% { transform: translateX(-100%); } 100% { transform: translateX(100%); } } .action-button { display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.75rem 1.5rem; background: var(--danger-gradient); color: white; text-decoration: none; border-radius: 25px; font-weight: 600; font-size: 0.9rem; transition: all 0.3s ease; margin-top: 1rem; box-shadow: 0 4px 15px rgba(255, 107, 107, 0.3); } .action-button:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(255, 107, 107, 0.4); text-decoration: none; color: white; } .action-button svg { width: 16px; height: 16px; } .action-button.pulse { animation: pulseButton 2s infinite; } @keyframes pulseButton { 0%, 100% { transform: scale(1); } 50% { transform: scale(1.05); } } .actions-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(350px, 1fr)); gap: 2rem; } .action-card { background: white; border-radius: var(--border-radius-large); padding: 2rem; box-shadow: var(--card-shadow); transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275); position: relative; overflow: hidden; text-decoration: none; color: inherit; display: block; animation: slideInUp 0.6s ease-out; animation-fill-mode: both; } .action-card:nth-child(1) { animation-delay: 0.1s; } .action-card:nth-child(2) { animation-delay: 0.15s; } .action-card:nth-child(3) { animation-delay: 0.2s; } .action-card:nth-child(4) { animation-delay: 0.25s; } .action-card:nth-child(5) { animation-delay: 0.3s; } .action-card:nth-child(6) { animation-delay: 0.35s; } .action-card:nth-child(7) { animation-delay: 0.4s; } .action-card::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px; background: var(--primary-gradient); transform: scaleX(0); transition: transform 0.3s ease; } .action-card[data-category="monitoring"]::before { background: var(--success-gradient); } .action-card[data-category="users"]::before { background: var(--primary-gradient); } .action-card[data-category="partners"]::before { background: var(--secondary-gradient); } .action-card[data-category="documents"]::before { background: var(--warning-gradient); } .action-card[data-category="reports"]::before { background: var(--dark-gradient); } .action-card:hover::before { transform: scaleX(1); } .action-card:hover { transform: translateY(-8px); box-shadow: var(--card-shadow-hover); text-decoration: none; color: inherit; } .action-card:hover .action-background { opacity: 1; transform: scale(1); } .action-card:hover .action-icon { transform: rotate(5deg) scale(1.1); } .action-card:hover .action-arrow { transform: translateX(5px); } .action-background { position: absolute; top: -50%; right: -50%; width: 100px; height: 100px; background: var(--primary-gradient); border-radius: 50%; opacity: 0.05; transform: scale(0); transition: all 0.6s ease; } .action-card[data-category="monitoring"] .action-background { background: var(--success-gradient); } .action-card[data-category="users"] .action-background { background: var(--primary-gradient); } .action-card[data-category="partners"] .action-background { background: var(--secondary-gradient); } .action-card[data-category="documents"] .action-background { background: var(--warning-gradient); } .action-card[data-category="reports"] .action-background { background: var(--dark-gradient); } .action-icon { width: 60px; height: 60px; border-radius: 16px; display: flex; align-items: center; justify-content: center; color: white; margin-bottom: 1.5rem; transition: all 0.3s ease; position: relative; z-index: 10; } .action-card[data-category="monitoring"] .action-icon { background: var(--success-gradient); } .action-card[data-category="users"] .action-icon { background: var(--primary-gradient); } .action-card[data-category="partners"] .action-icon { background: var(--secondary-gradient); } .action-card[data-category="documents"] .action-icon { background: var(--warning-gradient); } .action-card[data-category="reports"] .action-icon { background: var(--dark-gradient); } .action-icon svg { width: 28px; height: 28px; } .action-content { position: relative; z-index: 10; } .action-content h3 { font-size: 1.4rem; font-weight: 700; color: var(--text-primary); margin-bottom: 0.5rem; line-height: 1.3; } .action-content p { color: var(--text-secondary); font-size: 0.95rem; line-height: 1.5; margin-bottom: 1rem; } .action-badge { display: inline-block; padding: 0.3rem 0.8rem; background: rgba(102, 126, 234, 0.1); color: #667eea; border-radius: 20px; font-size: 0.8rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; } .action-card[data-category="monitoring"] .action-badge { background: rgba(79, 172, 254, 0.1); color: #4facfe; } .action-card[data-category="users"] .action-badge { background: rgba(102, 126, 234, 0.1); color: #667eea; } .action-card[data-category="partners"] .action-badge { background: rgba(240, 147, 251, 0.1); color: #f093fb; } .action-card[data-category="documents"] .action-badge { background: rgba(255, 236, 210, 0.5); color: #e67e22; } .action-card[data-category="reports"] .action-badge { background: rgba(44, 62, 80, 0.1); color: #2c3e50; } .action-arrow { position: absolute; top: 2rem; right: 2rem; width: 30px; height: 30px; color: var(--text-muted); transition: all 0.3s ease; } .action-arrow svg { width: 100%; height: 100%; } @keyframes countUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } } .card-number { animation: countUp 1s ease-out 0.8s both; } @media (max-width: 1200px) { .analytics-grid { grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.5rem; } .actions-grid { grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 1.5rem; } } @media (max-width: 768px) { .hero-section { padding: 3rem 1rem 2rem; margin-bottom: 2rem; } .hero-title { font-size: 2.2rem; } .hero-subtitle { font-size: 1.1rem; } .section-title { font-size: 2rem; } .analytics-section, .actions-section { padding: 0 1rem 2rem; } .analytics-grid { grid-template-columns: 1fr; gap: 1rem; } .actions-grid { grid-template-columns: 1fr; gap: 1rem; } .analytics-card, .action-card { padding: 1.5rem; } .avatar-circle { width: 80px; height: 80px; font-size: 1.5rem; } .card-number { font-size: 2.5rem; } } @media (max-width: 480px) { .hero-title { font-size: 1.8rem; } .hero-subtitle { font-size: 1rem; } .section-title { font-size: 1.6rem; } .analytics-card, .action-card { padding: 1rem; } .card-number { font-size: 2rem; } .action-content h3 { font-size: 1.2rem; } } @media (prefers-color-scheme: dark) { :root { --text-primary: #e2e8f0; --text-secondary: #a0aec0; --text-muted: #718096; } body { background: linear-gradient(135deg, #1a202c 0%, #2d3748 100%); } .analytics-card, .action-card { background: #2d3748; color: var(--text-primary); } .card-progress { background: #4a5568; } } @media (prefers-reduced-motion: reduce) { * { animation-duration: 0.01ms !important; animation-iteration-count: 1 !important; transition-duration: 0.01ms !important; } .floating-shapes { display: none; } } @media print { .hero-section { background: none !important; color: black !important; } .analytics-card, .action-card { box-shadow: none !important; border: 1px solid #ccc !important; } .floating-shapes { display: none !important; } }
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
if (isset($conn) && $conn) {
    $conn->close();
}
?>