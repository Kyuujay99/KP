<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once '../includes/auth_check.php';

if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'mahasiswa') {
    session_unset();
    session_destroy();
    header("Location: /KP/index.php?error=unauthorized");
    exit();
}

$nim_mahasiswa = $_SESSION['user_id'];
$nama_mahasiswa = $_SESSION['user_nama'];

// Set judul halaman spesifik untuk header.php
$page_title = "Dashboard Mahasiswa";
require_once '../includes/header.php'; // Header sekarang akan memuat $page_title

require_once '../config/db_connect.php';

$status_kp_terakhir = "Belum ada pengajuan KP.";
$judul_kp_terakhir = "";
$id_pengajuan_terakhir = null;

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
            $status_kp_terakhir = ucfirst(str_replace('_', ' ', $row_kp['status_pengajuan']));
            $judul_kp_terakhir = htmlspecialchars($row_kp['judul_kp']);
            $id_pengajuan_terakhir = $row_kp['id_pengajuan'];
        }
        $stmt_cek_kp->close();
    }
}
?>

<div class="dashboard-container">
    <h2>Selamat Datang di Dashboard Mahasiswa, <?php echo htmlspecialchars($nama_mahasiswa); ?>!</h2>
    <p>NIM: <?php echo htmlspecialchars($nim_mahasiswa); ?></p>
    <hr>

    <div class="dashboard-section">
        <h3>Status Kerja Praktek Anda</h3>
        <?php if (!empty($judul_kp_terakhir)): ?>
            <p><strong>Judul KP Terakhir:</strong> <?php echo $judul_kp_terakhir; ?></p>
            <p><strong>Status:</strong> <span class="status-<?php echo strtolower(str_replace(' ', '-', $status_kp_terakhir)); ?>"><?php echo htmlspecialchars($status_kp_terakhir); ?></span></p>
            <?php if ($id_pengajuan_terakhir): ?>
                <a href="/KP/mahasiswa/pengajuan_kp_view.php?id=<?php echo $id_pengajuan_terakhir; ?>" class="btn btn-info btn-sm">Lihat Detail Pengajuan</a>
            <?php endif; ?>
        <?php else: ?>
            <p><?php echo htmlspecialchars($status_kp_terakhir); ?></p>
        <?php endif; ?>
    </div>
    <hr>

    <h3>Menu Navigasi Mahasiswa</h3>
    <div class="navigation-grid">
        <a href="/KP/mahasiswa/pengajuan_kp_form.php" class="nav-item">
            <h4>Pengajuan KP Baru</h4>
            <p>Isi formulir untuk mengajukan Kerja Praktek baru.</p>
        </a>
        <a href="/KP/mahasiswa/pengajuan_kp_view.php" class="nav-item">
            <h4>Lihat Pengajuan KP Saya</h4>
            <p>Pantau status semua pengajuan Kerja Praktek Anda.</p>
        </a>
        <a href="/KP/mahasiswa/logbook_form.php" class="nav-item">
            <h4>Isi Logbook Harian</h4>
            <p>Catat kegiatan harian pelaksanaan KP Anda.</p>
        </a>
        <a href="/KP/mahasiswa/logbook_view.php" class="nav-item">
            <h4>Lihat Riwayat Logbook</h4>
            <p>Tinjau semua catatan logbook yang telah Anda isi.</p>
        </a>
        <a href="/KP/mahasiswa/bimbingan_view.php" class="nav-item">
            <h4>Informasi Bimbingan</h4>
            <p>Lihat jadwal dan catatan bimbingan dari dosen.</p>
        </a>
        <a href="/KP/mahasiswa/dokumen_upload.php" class="nav-item">
            <h4>Upload Dokumen</h4>
            <p>Unggah dokumen pendukung KP (proposal, laporan, dll).</p>
        </a>
         <a href="/KP/mahasiswa/dokumen_view.php" class="nav-item">
            <h4>Lihat Dokumen Saya</h4>
            <p>Akses semua dokumen yang telah Anda unggah.</p>
        </a>
        <a href="/KP/mahasiswa/seminar_view.php" class="nav-item">
            <h4>Informasi Seminar</h4>
            <p>Lihat detail jadwal dan persiapan seminar KP.</p>
        </a>
        <a href="/KP/mahasiswa/nilai_view.php" class="nav-item">
            <h4>Lihat Nilai KP</h4>
            <p>Cek nilai akhir Kerja Praktek Anda.</p>
        </a>
        <a href="/KP/mahasiswa/profil.php" class="nav-item">
            <h4>Profil Saya</h4>
            <p>Lihat dan perbarui data pribadi Anda.</p>
        </a>
    </div>
</div>

<style>
    .dashboard-container {
        padding: 20px;
        background-color: #fff;
        border-radius: 8px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    }
    .dashboard-container h2 {
        color: #333;
        margin-top: 0;
    }
    .dashboard-container h3 {
        color: #555;
        border-bottom: 1px solid #eee;
        padding-bottom: 10px;
        margin-top: 30px;
    }
    .dashboard-section p {
        font-size: 1.1em;
        color: #444;
    }
    .status-draft { color: gray; font-weight: bold; }
    .status-diajukan-mahasiswa { color: orange; font-weight: bold; }
    .status-kp-berjalan { color: green; font-weight: bold; }
    .status-selesai-pelaksanaan { color: blue; font-weight: bold; }
    .status-ditolak-dospem, .status-ditolak-perusahaan { color: red; font-weight: bold; }
    /* Tambahkan style lain untuk status lainnya */

    .navigation-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); /* Responsif grid */
        gap: 20px; /* Jarak antar item */
        margin-top: 20px;
    }
    .nav-item {
        display: block;
        background-color: #f9f9f9;
        padding: 20px;
        border-radius: 8px;
        text-decoration: none;
        color: #333;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    .nav-item:hover {
        transform: translateY(-5px);
        box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        background-color: #e9ecef;
    }
    .nav-item h4 {
        margin-top: 0;
        margin-bottom: 10px;
        color: #007bff;
    }
    .nav-item p {
        font-size: 0.9em;
        color: #555;
        line-height: 1.4;
    }
    /* Tombol dari header.php akan digunakan jika konsisten */
    .btn { /* Pastikan ini sinkron dengan style di header.php atau definisikan ulang */
        display: inline-block;
        padding: 8px 15px;
        font-size: 14px;
        font-weight: bold;
        text-align: center;
        text-decoration: none;
        border-radius: 4px;
        cursor: pointer;
        transition: background-color 0.2s ease;
    }
    .btn-info {
        color: #fff;
        background-color: #17a2b8;
        border: 1px solid #17a2b8;
    }
    .btn-info:hover {
        background-color: #138496;
    }
    .btn-sm {
        padding: 5px 10px;
        font-size: 12px;
    }
    hr {
        border: 0;
        height: 1px;
        background-color: #eee;
        margin: 25px 0;
    }
</style>

<?php
// 5. SERTAKAN FILE FOOTER
// Footer biasanya berisi bagian bawah halaman HTML, hak cipta, dll.
require_once '../includes/footer.php';

// Tutup koneksi database jika dibuka di halaman ini dan tidak ditutup di footer
if (isset($conn) && $conn) {
    $conn->close();
}
?>