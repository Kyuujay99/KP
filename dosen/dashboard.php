<?php
// /KP/dosen/dashboard.php

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// 1. OTENTIKASI DAN OTORISASI
require_once '../includes/auth_check.php';
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'dosen') {
    session_unset();
    session_destroy();
    header("Location: /KP/index.php?error=unauthorized_dosen");
    exit();
}

$nip_dosen_login = $_SESSION['user_id'];
$nama_dosen_login = $_SESSION['user_nama'];

// Sertakan file koneksi database
require_once '../config/db_connect.php';

// Inisialisasi variabel untuk data summary
$jumlah_mahasiswa_bimbingan = 0;
$jumlah_perlu_verifikasi = 0;
$jumlah_perlu_nilai_dospem = 0;
$jumlah_perlu_nilai_seminar = 0;

if ($conn && ($conn instanceof mysqli)) {
    // Hitung jumlah mahasiswa bimbingan aktif
    $sql_bimbingan = "SELECT COUNT(DISTINCT pk.nim) AS total FROM pengajuan_kp pk WHERE pk.nip_dosen_pembimbing_kp = ? AND pk.status_pengajuan IN ('kp_berjalan', 'selesai_pelaksanaan', 'laporan_disetujui')";
    $stmt_bimbingan = $conn->prepare($sql_bimbingan);
    if ($stmt_bimbingan) {
        $stmt_bimbingan->bind_param("s", $nip_dosen_login);
        $stmt_bimbingan->execute();
        $result = $stmt_bimbingan->get_result();
        if ($row = $result->fetch_assoc()) {
            $jumlah_mahasiswa_bimbingan = $row['total'];
        }
        $stmt_bimbingan->close();
    }

    // Hitung jumlah pengajuan KP yang perlu diverifikasi
    $sql_verifikasi = "SELECT COUNT(pk.id_pengajuan) AS total FROM pengajuan_kp pk WHERE pk.nip_dosen_pembimbing_kp = ? AND pk.status_pengajuan = 'diajukan_mahasiswa'";
    $stmt_verifikasi = $conn->prepare($sql_verifikasi);
    if ($stmt_verifikasi) {
        $stmt_verifikasi->bind_param("s", $nip_dosen_login);
        $stmt_verifikasi->execute();
        $result = $stmt_verifikasi->get_result();
        if ($row = $result->fetch_assoc()) {
            $jumlah_perlu_verifikasi = $row['total'];
        }
        $stmt_verifikasi->close();
    }
    
    // Hitung jumlah mahasiswa yang perlu dinilai sebagai pembimbing
    $sql_nilai_dospem = "SELECT COUNT(pk.id_pengajuan) AS total FROM pengajuan_kp pk LEFT JOIN nilai_kp nk ON pk.id_pengajuan = nk.id_pengajuan WHERE pk.nip_dosen_pembimbing_kp = ? AND pk.status_pengajuan IN ('selesai_pelaksanaan', 'laporan_disetujui', 'selesai_dinilai') AND (nk.id_nilai IS NULL OR nk.nilai_dosen_pembimbing IS NULL)";
    $stmt_nilai_dospem = $conn->prepare($sql_nilai_dospem);
     if ($stmt_nilai_dospem) {
        $stmt_nilai_dospem->bind_param("s", $nip_dosen_login);
        $stmt_nilai_dospem->execute();
        $result = $stmt_nilai_dospem->get_result();
        if ($row = $result->fetch_assoc()) {
            $jumlah_perlu_nilai_dospem = $row['total'];
        }
        $stmt_nilai_dospem->close();
    }

    // Hitung jumlah seminar yang perlu dinilai sebagai penguji
    $sql_nilai_seminar = "SELECT COUNT(sk.id_seminar) AS total FROM seminar_kp sk LEFT JOIN nilai_kp nk ON sk.id_pengajuan = nk.id_pengajuan WHERE (sk.nip_dosen_penguji1 = ? AND (nk.id_nilai IS NULL OR nk.nilai_penguji1_seminar IS NULL)) OR (sk.nip_dosen_penguji2 = ? AND (nk.id_nilai IS NULL OR nk.nilai_penguji2_seminar IS NULL)) AND sk.status_pelaksanaan_seminar = 'selesai'";
    $stmt_nilai_seminar = $conn->prepare($sql_nilai_seminar);
    if($stmt_nilai_seminar) {
        $stmt_nilai_seminar->bind_param("ss", $nip_dosen_login, $nip_dosen_login);
        $stmt_nilai_seminar->execute();
        $result = $stmt_nilai_seminar->get_result();
        if ($row = $result->fetch_assoc()) {
            $jumlah_perlu_nilai_seminar = $row['total'];
        }
        $stmt_nilai_seminar->close();
    }
}


// Set judul halaman dan sertakan header
$page_title = "Dashboard Dosen";
require_once '../includes/header.php';
?>

<div class="main-content-full">
    
    <div class="dashboard-header">
        <div class="welcome-text">
            <h1>Dashboard Dosen</h1>
            <p>Selamat datang kembali, <?php echo htmlspecialchars($nama_dosen_login); ?>.</p>
        </div>
    </div>

    <div class="summary-grid">
        <div class="summary-item">
            <div class="summary-icon icon-bimbingan">👨‍🏫</div>
            <div class="summary-text">
                <span class="summary-value"><?php echo $jumlah_mahasiswa_bimbingan; ?></span>
                <span class="summary-label">Mahasiswa Bimbingan Aktif</span>
            </div>
        </div>
        <div class="summary-item">
            <div class="summary-icon icon-verifikasi">🔍</div>
            <div class="summary-text">
                <span class="summary-value"><?php echo $jumlah_perlu_verifikasi; ?></span>
                <span class="summary-label">Pengajuan Perlu Verifikasi</span>
            </div>
            <?php if ($jumlah_perlu_verifikasi > 0): ?>
                <a href="pengajuan_list.php" class="summary-link">Lihat</a>
            <?php endif; ?>
        </div>
        <div class="summary-item">
            <div class="summary-icon icon-nilai">📈</div>
            <div class="summary-text">
                <span class="summary-value"><?php echo $jumlah_perlu_nilai_dospem; ?></span>
                <span class="summary-label">Perlu Penilaian Pembimbing</span>
            </div>
             <?php if ($jumlah_perlu_nilai_dospem > 0): ?>
                <a href="nilai_input_list.php" class="summary-link">Input Nilai</a>
            <?php endif; ?>
        </div>
         <div class="summary-item">
            <div class="summary-icon icon-seminar">🎙️</div>
            <div class="summary-text">
                <span class="summary-value"><?php echo $jumlah_perlu_nilai_seminar; ?></span>
                <span class="summary-label">Perlu Penilaian Seminar</span>
            </div>
             <?php if ($jumlah_perlu_nilai_seminar > 0): ?>
                <a href="seminar_jadwal_list.php" class="summary-link">Beri Nilai</a>
            <?php endif; ?>
        </div>
    </div>

    <div class="navigation-header">
        <h3>Menu Utama Dosen</h3>
        <div class="line"></div>
    </div>
    
    <div class="navigation-grid">
        <a href="pengajuan_list.php" class="nav-item">
            <div class="nav-icon">🔍</div>
            <h4>Verifikasi Pengajuan KP</h4>
            <p>Setujui atau tolak pengajuan KP dari mahasiswa bimbingan.</p>
        </a>
        <a href="bimbingan_mahasiswa_list.php" class="nav-item">
            <div class="nav-icon">👨‍🏫</div>
            <h4>Mahasiswa Bimbingan</h4>
            <p>Lihat daftar mahasiswa, kelola bimbingan, dan periksa logbook.</p>
        </a>
        <a href="nilai_input_list.php" class="nav-item">
            <div class="nav-icon">📈</div>
            <h4>Input & Kelola Nilai</h4>
            <p>Berikan penilaian akhir sebagai dosen pembimbing.</p>
        </a>
        <a href="seminar_jadwal_list.php" class="nav-item">
            <div class="nav-icon">🎙️</div>
            <h4>Jadwal & Penilaian Seminar</h4>
            <p>Lihat jadwal seminar dan berikan nilai sebagai penguji.</p>
        </a>
        <a href="profil.php" class="nav-item">
            <div class="nav-icon">👤</div>
            <h4>Profil Saya</h4>
            <p>Lihat dan perbarui data pribadi dan informasi akun Anda.</p>
        </a>
    </div>

</div>

<style>
    /* Layout Utama */
    .main-content-full {
        width: 100%;
        max-width: 1200px;
        margin: 20px auto;
        padding: 20px;
        animation: fadeIn 0.5s ease-in-out;
    }

    /* Header Dashboard */
    .dashboard-header {
        margin-bottom: 2rem;
        padding-bottom: 1rem;
        border-bottom: 1px solid var(--border-color);
    }
    .dashboard-header h1 {
        margin: 0;
        font-size: 2.2em;
        font-weight: 700;
        color: var(--dark-color);
    }
    .dashboard-header p {
        margin: 5px 0 0;
        color: var(--secondary-color);
        font-size: 1.2em;
    }

    /* Summary Grid */
    .summary-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
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
        gap: 1rem;
        position: relative;
        overflow: hidden;
        transition: transform 0.3s ease, box-shadow 0.3s ease;
    }
    .summary-item:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 25px rgba(0,0,0,0.1);
    }
    .summary-icon {
        font-size: 2.5rem;
        padding: 1rem;
        border-radius: 50%;
        line-height: 1;
    }
    .icon-bimbingan { background-color: rgba(0, 123, 255, 0.1); color: #007BFF; }
    .icon-verifikasi { background-color: rgba(255, 193, 7, 0.1); color: #ffc107; }
    .icon-nilai { background-color: rgba(40, 167, 69, 0.1); color: #28a745; }
    .icon-seminar { background-color: rgba(108, 117, 125, 0.1); color: #6c757d; }

    .summary-text {
        display: flex;
        flex-direction: column;
    }
    .summary-value {
        font-size: 2.2em;
        font-weight: 700;
        color: var(--dark-color);
        line-height: 1;
    }
    .summary-label {
        font-size: 0.9em;
        color: var(--secondary-color);
    }
    .summary-link {
        position: absolute;
        top: 10px;
        right: 10px;
        font-size: 0.8em;
        font-weight: 600;
        text-decoration: none;
        color: var(--primary-color);
        background-color: rgba(0, 123, 255, 0.1);
        padding: 5px 10px;
        border-radius: 20px;
        transition: all 0.3s ease;
    }
    .summary-link:hover {
        background-color: var(--primary-color);
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
        background-color: var(--primary-color);
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
        text-align: left;
        background-color: #fff;
        padding: 1.5rem;
        border-radius: var(--border-radius);
        text-decoration: none;
        color: #333;
        box-shadow: var(--card-shadow);
        transition: transform 0.3s ease, box-shadow 0.3s ease;
        border-left: 5px solid transparent;
    }
    .nav-item:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        border-left-color: var(--primary-color);
    }
    .nav-icon {
        font-size: 2em;
        margin-bottom: 1rem;
        color: var(--primary-color);
        line-height: 1;
        width: 50px;
        height: 50px;
        background-color: rgba(0, 123, 255, 0.1);
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 12px;
    }
    .nav-item h4 {
        margin: 0 0 8px 0;
        color: #0056b3;
        font-size: 1.2em;
    }
    .nav-item p {
        font-size: 0.9em;
        color: var(--secondary-color);
        line-height: 1.5;
        margin: 0;
    }

    /* Animasi */
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }
</style>

<?php
require_once '../includes/footer.php';

if (isset($conn) && ($conn instanceof mysqli)) {
    $conn->close();
}
?>