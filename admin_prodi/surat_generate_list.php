<?php
// /KP/admin_prodi/surat_generate_list.php (Versi Diperbarui)

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once '../includes/auth_check.php';
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin_prodi') {
    header("Location: /KP/index.php?error=unauthorized_admin");
    exit();
}
require_once '../config/db_connect.php';

$list_pengajuan_siap_surat = [];
$error_db = '';

if ($conn) {
    $status_siap_surat = ['disetujui_dospem', 'diterima_perusahaan', 'kp_berjalan'];
    $status_placeholders = implode(',', array_fill(0, count($status_siap_surat), '?'));
    $sql = "SELECT pk.id_pengajuan, m.nim, m.nama AS nama_mahasiswa, pk.judul_kp, pr.nama_perusahaan, pk.surat_pengantar_path
            FROM pengajuan_kp pk
            JOIN mahasiswa m ON pk.nim = m.nim
            LEFT JOIN perusahaan pr ON pk.id_perusahaan = pr.id_perusahaan
            WHERE pk.status_pengajuan IN ($status_placeholders)
            ORDER BY pk.updated_at ASC";

    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param(str_repeat('s', count($status_siap_surat)), ...$status_siap_surat);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $list_pengajuan_siap_surat[] = $row;
        }
        $stmt->close();
    } else {
        $error_db = "Gagal menyiapkan query: " . $conn->error;
    }
} else {
    $error_db = "Koneksi database gagal.";
}

$page_title = "Manajemen & Generate Surat";
require_once '../includes/header.php';
?>

<div class="main-content-full">
    <div class="list-container">
        <div class="list-header">
            <h1><?php echo htmlspecialchars($page_title); ?></h1>
            <p>Daftar pengajuan KP yang siap untuk dibuatkan surat resmi, seperti Surat Pengantar atau Surat Tugas.</p>
        </div>

        <?php if (!empty($error_db)): ?>
            <div class="message error"><p><?php echo $error_db; ?></p></div>
        <?php endif; ?>

        <?php if (empty($list_pengajuan_siap_surat) && empty($error_db)): ?>
            <div class="message info">
                <h4>Tidak Ada Antrian Surat</h4>
                <p>Saat ini tidak ada pengajuan KP yang memerlukan pembuatan surat resmi.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Mahasiswa</th>
                            <th>Detail Pengajuan</th>
                            <th>Status Surat</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($list_pengajuan_siap_surat as $pengajuan): ?>
                            <tr>
                                <td>
                                    <div class="mahasiswa-info">
                                        <div class="mahasiswa-avatar"><?php echo strtoupper(substr($pengajuan['nama_mahasiswa'], 0, 1)); ?></div>
                                        <div>
                                            <div class="mahasiswa-nama"><?php echo htmlspecialchars($pengajuan['nama_mahasiswa']); ?></div>
                                            <div class="mahasiswa-nim"><?php echo htmlspecialchars($pengajuan['nim']); ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="judul-kp-text"><?php echo htmlspecialchars($pengajuan['judul_kp']); ?></div>
                                    <div class="perusahaan-text">🏢 <?php echo htmlspecialchars($pengajuan['nama_perusahaan'] ?? 'N/A'); ?></div>
                                </td>
                                <td>
                                    <?php if (empty($pengajuan['surat_pengantar_path'])): ?>
                                        <span class="status-badge status-belum-dibuat">Belum Dibuat</span>
                                    <?php else: ?>
                                        <span class="status-badge status-sudah-dibuat">Sudah Dibuat</span>
                                    <?php endif; ?>
                                </td>
                                <td class="actions-cell">
                                    <a href="surat_generate.php?tipe=pengantar&id_pengajuan=<?php echo $pengajuan['id_pengajuan']; ?>" class="btn btn-primary btn-sm" target="_blank">
                                        ✉️ Surat Pengantar
                                    </a>
                                     <a href="surat_generate.php?tipe=tugas&id_pengajuan=<?php echo $pengajuan['id_pengajuan']; ?>" class="btn btn-secondary btn-sm" target="_blank">
                                        📝 Surat Tugas
                                    </a>
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
    .table-responsive { background-color: #fff; border-radius: var(--border-radius); box-shadow: var(--card-shadow); overflow: hidden; }
    .data-table { width: 100%; border-collapse: collapse; }
    .data-table th, .data-table td { padding: 1rem 1.5rem; border-bottom: 1px solid var(--border-color); text-align: left; vertical-align: middle; }
    .data-table th { background-color: #f8f9fa; font-weight: 600; text-transform: uppercase; }
    .data-table tbody tr:hover { background-color: #f1f7ff; }

    .mahasiswa-info { display: flex; align-items: center; gap: 15px; }
    .mahasiswa-avatar { width: 45px; height: 45px; border-radius: 50%; background-color: var(--primary-color); color: white; display: inline-flex; align-items: center; justify-content: center; font-size: 1.2em; font-weight: 600; flex-shrink: 0; }
    .mahasiswa-nama { font-weight: 600; }
    .mahasiswa-nim { font-size: 0.9em; color: #6c757d; }
    
    .judul-kp-text { font-weight: 500; color: var(--dark-color); }
    .perusahaan-text { font-size: 0.9em; color: #6c757d; }
    .actions-cell { display: flex; gap: 10px; }

    .status-badge { padding: 5px 12px; border-radius: 20px; font-size: 0.8em; font-weight: 600; }
    .status-belum-dibuat { background-color: #ffc107; color: #212529; }
    .status-sudah-dibuat { background-color: #28a745; color: #fff; }
</style>

<?php
require_once '../includes/footer.php';
if (isset($conn)) { $conn->close(); }
?>