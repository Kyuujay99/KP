<?php
// /KP/mahasiswa/dokumen_view.php (Versi Diperbarui)

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once '../includes/auth_check.php';
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'mahasiswa') {
    header("Location: /KP/index.php?error=unauthorized");
    exit();
}

$nim_mahasiswa = $_SESSION['user_id'];
require_once '../config/db_connect.php';

$list_dokumen = [];
$error_db = '';

if ($conn) {
    $sql = "SELECT
                dk.id_dokumen, dk.id_pengajuan, dk.nama_dokumen, dk.jenis_dokumen,
                dk.file_path, dk.tanggal_upload, dk.status_verifikasi_dokumen,
                dk.catatan_verifikator, pk.judul_kp
            FROM dokumen_kp dk
            JOIN pengajuan_kp pk ON dk.id_pengajuan = pk.id_pengajuan
            WHERE pk.nim = ? 
            ORDER BY dk.tanggal_upload DESC";

    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("s", $nim_mahasiswa);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $list_dokumen[] = $row;
        }
        $stmt->close();
    } else {
        $error_db = "Gagal mengambil data dokumen: " . $conn->error;
    }
} else {
    $error_db = "Koneksi database gagal.";
}

$page_title = "Dokumen Saya";
require_once '../includes/header.php';
?>

<div class="main-content-full">
    <div class="list-container">
        <div class="list-header">
            <h1><?php echo htmlspecialchars($page_title); ?></h1>
            <p>Riwayat semua dokumen yang telah Anda unggah ke sistem untuk berbagai keperluan Kerja Praktek.</p>
        </div>

        <?php if (!empty($error_db)): ?>
            <div class="message error"><p><?php echo $error_db; ?></p></div>
        <?php endif; ?>

        <?php if (empty($list_dokumen) && empty($error_db)): ?>
            <div class="message info">
                <h4>Belum Ada Dokumen</h4>
                <p>Anda dapat mengunggah dokumen melalui halaman detail pengajuan KP Anda.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Detail Dokumen</th>
                            <th>Terkait KP</th>
                            <th>Status Verifikasi</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($list_dokumen as $doc): ?>
                            <tr>
                                <td>
                                    <div class="dokumen-nama"><?php echo htmlspecialchars($doc['nama_dokumen']); ?></div>
                                    <div class="dokumen-jenis"><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $doc['jenis_dokumen']))); ?></div>
                                    <div class="tanggal-text">Diupload: <?php echo date("d M Y, H:i", strtotime($doc['tanggal_upload'])); ?></div>
                                </td>
                                <td class="judul-kp-cell">
                                    <a href="pengajuan_kp_detail.php?id=<?php echo $doc['id_pengajuan']; ?>" title="Lihat Detail KP Terkait">
                                        <?php echo htmlspecialchars($doc['judul_kp']); ?>
                                    </a>
                                </td>
                                <td>
                                    <span class="status-badge status-dokumen-<?php echo strtolower(htmlspecialchars($doc['status_verifikasi_dokumen'])); ?>" 
                                          title="<?php echo htmlspecialchars($doc['catatan_verifikator'] ?: 'Belum ada catatan'); ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', htmlspecialchars($doc['status_verifikasi_dokumen']))); ?>
                                    </span>
                                    <?php if (!empty($doc['catatan_verifikator'])): ?>
                                        <i class="icon-info" title="<?php echo htmlspecialchars($doc['catatan_verifikator']); ?>"></i>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($doc['file_path'])): ?>
                                        <a href="/KP/<?php echo htmlspecialchars($doc['file_path']); ?>" class="btn btn-primary btn-sm" target="_blank">
                                            Lihat File
                                        </a>
                                    <?php else: ?>
                                        <button class="btn btn-secondary btn-sm" disabled>File Hilang</button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
    /* Menggunakan gaya dari halaman list lainnya dan disesuaikan */
    .icon-info::before { content: " ⓘ"; cursor: help; color: var(--primary-color); font-weight: bold; }
    .status-badge {
        padding: 5px 12px; border-radius: 20px; font-size: 0.8em; font-weight: 600;
    }
    .status-dokumen-pending { background-color: #ffc107; color: #212529; }
    .status-dokumen-disetujui { background-color: #28a745; color: #fff; }
    .status-dokumen-revisi-diperlukan { background-color: #fd7e14; color: #fff; }
    .status-dokumen-ditolak { background-color: #dc3545; color: #fff; }
</style>

<?php
require_once '../includes/footer.php';
if (isset($conn)) { $conn->close(); }
?>