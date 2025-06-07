<?php
// /KP/mahasiswa/dashboard.php (Versi Diperbarui)

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

// Set judul halaman
$page_title = "Dashboard Mahasiswa";
require_once '../includes/header.php'; // Memuat header, navbar, dan CSS

// Koneksi ke DB untuk mengambil data
require_once '../config/db_connect.php';

$status_kp_terakhir = "Belum ada pengajuan KP.";
$judul_kp_terakhir = "";
$id_pengajuan_terakhir = null;
$status_kp_raw = ""; // Untuk class CSS

if ($conn) {
    $sql_cek_kp = "SELECT id_pengajuan, judul_kp, status_pengajuan
                   FROM pengajuan_kp
                   WHERE nim = ?
                   ORDER BY tanggal_pengajuan DESC, id_pengajuan DESC
                   LIMIT 1";
    $stmt_cek_kp = $conn->prepare($sql_cek_kp);
    if ($stmt_cek_kp) {
        $stmt_cek_kp->bind_param("s", $nim_mahasiswa);
        $stmt_cek_kp->execute();
        $result_kp = $stmt_cek_kp->get_result();
        if ($result_kp->num_rows > 0) {
            $row_kp = $result_kp->fetch_assoc();
            $status_kp_raw = $row_kp['status_pengajuan'];
            $status_kp_terakhir = ucfirst(str_replace('_', ' ', $row_kp['status_pengajuan']));
            $judul_kp_terakhir = htmlspecialchars($row_kp['judul_kp']);
            $id_pengajuan_terakhir = $row_kp['id_pengajuan'];
        }
        $stmt_cek_kp->close();
    }
}
?>

<div class="main-content-full">
    
    <div class="dashboard-header">
        <div class="welcome-text">
            <h1>Selamat Datang, <?php echo htmlspecialchars(explode(' ', $nama_mahasiswa)[0]); ?>!</h1>
            <p>Ini adalah pusat kendali untuk semua aktivitas Kerja Praktek Anda.</p>
        </div>
        <div class="nim-badge">
            NIM: <?php echo htmlspecialchars($nim_mahasiswa); ?>
        </div>
    </div>

    <div class="dashboard-section-status card">
        <h3><i class="icon-status"></i>Status Kerja Praktek Anda</h3>
        <div class="status-content">
            <?php if (!empty($judul_kp_terakhir)): ?>
                <div class="status-info">
                    <span class="info-label">Judul KP Terakhir:</span>
                    <span class="info-value"><?php echo $judul_kp_terakhir; ?></span>
                </div>
                <div class="status-info">
                    <span class="info-label">Status Saat Ini:</span>
                    <span class="info-value">
                        <span class="status-badge status-<?php echo strtolower(str_replace('_', '-', $status_kp_raw)); ?>">
                            <?php echo htmlspecialchars($status_kp_terakhir); ?>
                        </span>
                    </span>
                </div>
                <?php if ($id_pengajuan_terakhir): ?>
                    <div class="status-action">
                        <a href="/KP/mahasiswa/pengajuan_kp_detail.php?id=<?php echo $id_pengajuan_terakhir; ?>" class="btn btn-primary-outline">
                            Lihat Detail Pengajuan
                        </a>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <p class="no-data"><?php echo htmlspecialchars($status_kp_terakhir); ?>. Silakan ajukan KP baru melalui menu di bawah.</p>
            <?php endif; ?>
        </div>
    </div>

    <div class="navigation-header">
        <h3>Menu Navigasi</h3>
        <div class="line"></div>
    </div>
    
    <div class="navigation-grid">
        <a href="/KP/mahasiswa/pengajuan_kp_form.php" class="nav-item">
            <div class="nav-icon"><i class="icon-form"></i></div>
            <h4>Pengajuan KP Baru</h4>
            <p>Isi formulir untuk memulai proses Kerja Praktek Anda.</p>
        </a>
        <a href="/KP/mahasiswa/pengajuan_kp_view.php" class="nav-item">
            <div class="nav-icon"><i class="icon-history"></i></div>
            <h4>Riwayat Pengajuan</h4>
            <p>Pantau status semua pengajuan Kerja Praktek Anda.</p>
        </a>
        <a href="/KP/mahasiswa/bimbingan_view.php" class="nav-item">
            <div class="nav-icon"><i class="icon-bimbingan"></i></div>
            <h4>Info Bimbingan</h4>
            <p>Lihat jadwal dan catatan bimbingan dari dosen.</p>
        </a>
        <a href="/KP/mahasiswa/logbook_view.php" class="nav-item">
            <div class="nav-icon"><i class="icon-logbook"></i></div>
            <h4>Riwayat Logbook</h4>
            <p>Tinjau semua catatan logbook yang telah Anda isi.</p>
        </a>
        <a href="/KP/mahasiswa/dokumen_view.php" class="nav-item">
            <div class="nav-icon"><i class="icon-dokumen"></i></div>
            <h4>Dokumen Saya</h4>
            <p>Akses semua dokumen yang telah Anda unggah.</p>
        </a>
        <a href="/KP/mahasiswa/profil.php" class="nav-item">
            <div class="nav-icon"><i class="icon-profil"></i></div>
            <h4>Profil Saya</h4>
            <p>Lihat dan perbarui data pribadi Anda.</p>
        </a>
    </div>

</div>

<style>
    /* Ikon sederhana menggunakan emoji untuk modernitas */
    .icon-status::before { content: "📊"; }
    .icon-form::before { content: "📝"; }
    .icon-history::before { content: "📜"; }
    .icon-bimbingan::before { content: "👨‍🏫"; }
    .icon-logbook::before { content: "📓"; }
    .icon-dokumen::before { content: "📎"; }
    .icon-profil::before { content: "👤"; }

    /* Layout Utama Tanpa Sidebar */
    .main-content-full {
        width: 100%;
        max-width: 1200px;
        margin: 20px auto;
        padding: 20px;
    }

    /* Header Dashboard */
    .dashboard-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 2rem;
        padding: 1.5rem;
        background: linear-gradient(135deg, #007BFF, #0056b3);
        color: white;
        border-radius: 12px;
        animation: fadeIn 0.5s ease-in-out;
    }
    .dashboard-header h1 {
        margin: 0;
        font-size: 2em;
        font-weight: 600;
    }
    .dashboard-header p {
        margin: 5px 0 0;
        opacity: 0.9;
        font-size: 1.1em;
    }
    .nim-badge {
        background-color: rgba(255, 255, 255, 0.2);
        padding: 8px 15px;
        border-radius: 20px;
        font-weight: 500;
    }

    /* Section Status KP */
    .dashboard-section-status.card {
        background-color: #fff;
        padding: 1.5rem;
        border-radius: 12px;
        margin-bottom: 2rem;
        box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        animation: slideUp 0.6s ease-out;
    }
    .dashboard-section-status h3 {
        margin-top: 0;
        margin-bottom: 1.5rem;
        font-size: 1.5em;
        color: #343a40;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .status-info {
        display: flex;
        align-items: baseline;
        gap: 10px;
        margin-bottom: 1rem;
        font-size: 1.1em;
    }
    .status-info .info-label {
        font-weight: 600;
        color: #495057;
        width: 150px;
    }
    .status-info .info-value {
        color: #212529;
    }
    .status-action {
        margin-top: 1.5rem;
    }
    p.no-data {
        color: #6c757d;
        font-style: italic;
    }

    /* Badge Status */
    .status-badge {
        padding: 6px 12px;
        border-radius: 20px;
        font-size: 0.85em;
        font-weight: 600;
        color: #fff;
        text-transform: capitalize;
    }
    .status-draft { background-color: #6c757d; }
    .status-diajukan-mahasiswa { background-color: #ffc107; color:#212529; }
    .status-penentuan-dospem-kp { background-color: #17a2b8; }
    .status-kp-berjalan { background-color: #0d6efd; }
    .status-selesai-pelaksanaan { background-color: #28a745; }
    .status-ditolak-dospem { background-color: #dc3545; }
    
    /* Tombol */
    .btn.btn-primary-outline {
        color: #007bff;
        background-color: transparent;
        border: 2px solid #007bff;
        font-weight: bold;
        padding: 8px 18px;
        border-radius: 8px;
        text-decoration: none;
        transition: all 0.3s ease;
    }
    .btn.btn-primary-outline:hover {
        background-color: #007bff;
        color: #fff;
    }
    
    /* Header Navigasi */
    .navigation-header {
        text-align: center;
        margin-bottom: 2rem;
    }
    .navigation-header h3 {
        display: inline-block;
        font-size: 1.8em;
        color: #343a40;
        margin: 0;
        position: relative;
        padding-bottom: 10px;
    }
    .navigation-header h3::after {
        content: '';
        position: absolute;
        bottom: 0;
        left: 50%;
        transform: translateX(-50%);
        width: 60px;
        height: 3px;
        background-color: #007bff;
    }

    /* Grid Navigasi */
    .navigation-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 1.5rem;
    }
    .nav-item {
        display: flex;
        flex-direction: column;
        align-items: center;
        text-align: center;
        background-color: #fff;
        padding: 2rem 1.5rem;
        border-radius: 12px;
        text-decoration: none;
        color: #333;
        box-shadow: 0 4px 15px rgba(0,0,0,0.06);
        transition: transform 0.3s ease, box-shadow 0.3s ease;
        border-bottom: 4px solid transparent;
        animation: popIn 0.5s ease-out forwards;
        opacity: 0; /* Mulai dari transparan untuk animasi */
    }
    /* Animasi staggered untuk item grid */
    <?php for ($i = 1; $i <= 6; $i++): ?>
    .nav-item:nth-child(<?php echo $i; ?>) {
        animation-delay: <?php echo $i * 0.1; ?>s;
    }
    <?php endfor; ?>

    .nav-item:hover {
        transform: translateY(-8px);
        box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        border-bottom-color: #007bff;
    }
    .nav-icon {
        font-size: 2.5em;
        margin-bottom: 1rem;
        line-height: 1;
    }
    .nav-item h4 {
        margin: 0 0 10px 0;
        color: #0056b3;
        font-size: 1.2em;
    }
    .nav-item p {
        font-size: 0.9em;
        color: #6c757d;
        line-height: 1.5;
        margin: 0;
    }

    /* Animasi */
    @keyframes fadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
    }
    @keyframes slideUp {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }
    @keyframes popIn {
        from { opacity: 0; transform: scale(0.9); }
        to { opacity: 1; transform: scale(1); }
    }

</style>

<?php
// Memanggil footer
require_once '../includes/footer.php';

// Menutup koneksi database
if (isset($conn) && $conn) {
    $conn->close();
}
?>