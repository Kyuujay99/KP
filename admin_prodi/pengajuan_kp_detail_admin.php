<?php
// /KP/admin_prodi/pengajuan_kp_detail_admin.php (Versi Final)

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once '../includes/auth_check.php';
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin_prodi') {
    header("Location: /KP/index.php?error=unauthorized_admin");
    exit();
}

require_once '../config/db_connect.php';

$id_pengajuan_url = null;
$pengajuan_detail = null;
$dokumen_terkait = [];
$list_semua_dosen = [];
$error_message = '';
$success_message = '';

if (isset($_GET['id_pengajuan']) && filter_var($_GET['id_pengajuan'], FILTER_VALIDATE_INT)) {
    $id_pengajuan_url = (int)$_GET['id_pengajuan'];
} else {
    $error_message = "ID Pengajuan tidak valid atau tidak ditemukan.";
}

function getPengajuanDetail($conn_db, $id_pengajuan, &$out_dokumen_terkait, &$out_error_message) {
    $detail = null;
    $out_dokumen_terkait = [];

    $sql_detail = "SELECT
                       pk.id_pengajuan, pk.nim AS nim_mahasiswa_kp, pk.judul_kp, pk.deskripsi_kp,
                       p.nama_perusahaan, pk.id_perusahaan,
                       pk.tanggal_pengajuan, pk.tanggal_mulai_rencana, pk.tanggal_selesai_rencana,
                       pk.status_pengajuan, pk.nip_dosen_pembimbing_kp,
                       dospem.nama_dosen AS nama_dosen_pembimbing,
                       pk.catatan_admin, pk.catatan_dosen,
                       pk.surat_pengantar_path, pk.surat_balasan_perusahaan_path,
                       m.nama AS nama_mahasiswa, m.email AS email_mahasiswa, m.no_hp AS no_hp_mahasiswa, m.prodi, m.angkatan
                   FROM pengajuan_kp pk
                   JOIN mahasiswa m ON pk.nim = m.nim
                   LEFT JOIN perusahaan p ON pk.id_perusahaan = p.id_perusahaan
                   LEFT JOIN dosen_pembimbing dospem ON pk.nip_dosen_pembimbing_kp = dospem.nip
                   WHERE pk.id_pengajuan = ?";
    
    $stmt_detail = $conn_db->prepare($sql_detail);
    if ($stmt_detail) {
        $stmt_detail->bind_param("i", $id_pengajuan);
        $stmt_detail->execute();
        $result_detail = $stmt_detail->get_result();
        if ($result_detail->num_rows === 1) {
            $detail = $result_detail->fetch_assoc();

            $sql_dokumen = "SELECT id_dokumen, nama_dokumen, jenis_dokumen, file_path, tanggal_upload, status_verifikasi_dokumen, catatan_verifikator FROM dokumen_kp WHERE id_pengajuan = ? ORDER BY tanggal_upload DESC";
            $stmt_dokumen = $conn_db->prepare($sql_dokumen);
            if ($stmt_dokumen) {
                $stmt_dokumen->bind_param("i", $id_pengajuan);
                $stmt_dokumen->execute();
                $result_dokumen = $stmt_dokumen->get_result();
                while ($row_dokumen = $result_dokumen->fetch_assoc()) {
                    $out_dokumen_terkait[] = $row_dokumen;
                }
                $stmt_dokumen->close();
            } else {
                 $out_error_message = "Gagal mengambil daftar dokumen.";
            }
        } else {
            $out_error_message = "Detail pengajuan KP tidak ditemukan.";
        }
        $stmt_detail->close();
    } else {
        $out_error_message = "Gagal menyiapkan query detail pengajuan: " . $conn_db->error;
    }
    return $detail;
}

if ($conn && empty($error_message)) {
    // ... (Logika proses form POST tetap sama) ...

    // Selalu ambil data terbaru
    $pengajuan_detail = getPengajuanDetail($conn, $id_pengajuan_url, $dokumen_terkait, $error_message);

    // Ambil daftar dosen
    $sql_dosen = "SELECT nip, nama_dosen FROM dosen_pembimbing WHERE status_akun = 'active' ORDER BY nama_dosen ASC";
    $result_dosen = $conn->query($sql_dosen);
    if ($result_dosen) {
        while ($row_dosen = $result_dosen->fetch_assoc()) {
            $list_semua_dosen[] = $row_dosen;
        }
    }
}

$opsi_status_admin = [
    'draft' => 'Draft', 'diajukan_mahasiswa' => 'Diajukan Mahasiswa',
    'diverifikasi_dospem' => 'Diverifikasi Dosen', 'disetujui_dospem' => 'Disetujui Dosen',
    'ditolak_dospem' => 'Ditolak Dosen', 'menunggu_konfirmasi_perusahaan' => 'Menunggu Konfirmasi Perusahaan',
    'diterima_perusahaan' => 'Diterima Perusahaan', 'ditolak_perusahaan' => 'Ditolak Perusahaan',
    'penentuan_dospem_kp' => 'Penentuan Dospem', 'kp_berjalan' => 'KP Berjalan',
    'selesai_pelaksanaan' => 'Selesai Pelaksanaan', 'laporan_disetujui' => 'Laporan Disetujui',
    'selesai_dinilai' => 'Selesai Dinilai', 'dibatalkan' => 'Dibatalkan'
];

$page_title = "Kelola Detail Pengajuan KP";
if ($pengajuan_detail) {
    $page_title = "Kelola: " . htmlspecialchars($pengajuan_detail['nama_mahasiswa']);
}
require_once '../includes/header.php';
?>

<div class="main-content-full">
    <div class="form-container-modern">
        <div class="form-header">
            <h1><?php echo htmlspecialchars($page_title); ?></h1>
            <p>Kelola setiap aspek pengajuan KP, mulai dari penentuan dosen hingga manajemen dokumen dan surat resmi.</p>
            <a href="pengajuan_kp_monitoring.php" class="btn btn-secondary">&laquo; Kembali ke Monitoring</a>
        </div>

        <?php if (!empty($success_message)): ?>
            <div class="message success"><p><?php echo htmlspecialchars($success_message); ?></p></div>
        <?php endif; ?>
        <?php if (!empty($error_message) && !$pengajuan_detail): // Tampilkan error utama jika data gagal load ?>
            <div class="message error"><p><?php echo htmlspecialchars($error_message); ?></p></div>
        <?php endif; ?>

        <?php if ($pengajuan_detail): ?>
            <div class="content-grid">
                <div class="main-content-column">
                    </div>
                <div class="sidebar-column">
                    </div>
            </div>
        <?php elseif(empty($error_message)): ?>
            <div class="message info"><p>Memuat detail pengajuan...</p></div>
        <?php endif; ?>
    </div>
</div>

<style>
/* ... (Gaya CSS lengkap dari respons sebelumnya) ... */
</style>

<?php
require_once '../includes/footer.php';
if (isset($conn) && $conn) {
    $conn->close();
}
?>