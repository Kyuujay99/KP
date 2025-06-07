<?php
// /KP/admin_prodi/pengajuan_kp_monitoring.php (Versi Diperbarui)

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once '../includes/auth_check.php';
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin_prodi') {
    header("Location: /KP/index.php?error=unauthorized_admin");
    exit();
}

require_once '../config/db_connect.php';

$list_semua_pengajuan = [];
$error_db = '';
$filter_status = isset($_GET['status']) ? $_GET['status'] : '';

if ($conn && ($conn instanceof mysqli)) {
    $sql = "SELECT
                pk.id_pengajuan, m.nim, m.nama AS nama_mahasiswa, m.prodi AS prodi_mahasiswa,
                pk.judul_kp, pr.nama_perusahaan, dp.nama_dosen AS nama_dosen_pembimbing,
                pk.tanggal_pengajuan, pk.status_pengajuan
            FROM pengajuan_kp pk
            JOIN mahasiswa m ON pk.nim = m.nim
            LEFT JOIN perusahaan pr ON pk.id_perusahaan = pr.id_perusahaan
            LEFT JOIN dosen_pembimbing dp ON pk.nip_dosen_pembimbing_kp = dp.nip";

    $params = [];
    $types = "";
    if (!empty($filter_status)) {
        $sql .= " WHERE pk.status_pengajuan = ?";
        $params[] = $filter_status;
        $types .= "s";
    }

    $sql .= " ORDER BY pk.tanggal_pengajuan DESC, pk.id_pengajuan DESC";

    $stmt = $conn->prepare($sql);
    if ($stmt) {
        if (!empty($filter_status)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $list_semua_pengajuan[] = $row;
        }
        $stmt->close();
    } else {
        $error_db = "Gagal menyiapkan query: " . $conn->error;
    }
} else {
    $error_db = "Koneksi database gagal.";
}

$opsi_status_filter = [
    'draft' => 'Draft', 'diajukan_mahasiswa' => 'Diajukan Mahasiswa',
    'diverifikasi_dospem' => 'Diverifikasi Dosen', 'disetujui_dospem' => 'Disetujui Dosen',
    'ditolak_dospem' => 'Ditolak Dosen', 'menunggu_konfirmasi_perusahaan' => 'Menunggu Konfirmasi Perusahaan',
    'diterima_perusahaan' => 'Diterima Perusahaan', 'ditolak_perusahaan' => 'Ditolak Perusahaan',
    'penentuan_dospem_kp' => 'Penentuan Dospem', 'kp_berjalan' => 'KP Berjalan',
    'selesai_pelaksanaan' => 'Selesai Pelaksanaan', 'laporan_disetujui' => 'Laporan Disetujui',
    'selesai_dinilai' => 'Selesai Dinilai', 'dibatalkan' => 'Dibatalkan'
];

$page_title = "Monitoring Pengajuan Kerja Praktek";
require_once '../includes/header.php';
?>

<div class="main-content-full">
    <div class="list-container">
        <div class="list-header">
            <h1><?php echo htmlspecialchars($page_title); ?></h1>
            <p>Pantau semua pengajuan Kerja Praktek dari mahasiswa secara terpusat. Gunakan filter untuk menyaring data.</p>
        </div>

        <form action="pengajuan_kp_monitoring.php" method="GET" class="filter-form">
            <div class="form-group">
                <label for="status">Filter berdasarkan Status:</label>
                <select name="status" id="status" onchange="this.form.submit()">
                    <option value="">-- Tampilkan Semua Status --</option>
                    <?php foreach ($opsi_status_filter as $value => $text): ?>
                        <option value="<?php echo $value; ?>" <?php echo ($filter_status == $value) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($text); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php if(!empty($filter_status)): ?>
                <a href="pengajuan_kp_monitoring.php" class="btn btn-secondary btn-sm">Reset Filter</a>
            <?php endif; ?>
        </form>

        <?php if (!empty($error_db)): ?>
            <div class="message error"><p><?php echo $error_db; ?></p></div>
        <?php endif; ?>

        <?php if (empty($list_semua_pengajuan) && empty($error_db)): ?>
            <div class="message info">
                <h4>Data Tidak Ditemukan</h4>
                <p>Tidak ada pengajuan KP yang cocok dengan filter yang Anda pilih.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Mahasiswa</th>
                            <th>Detail Pengajuan</th>
                            <th>Dosen Pembimbing</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($list_semua_pengajuan as $p): ?>
                            <tr>
                                <td>
                                    <div class="mahasiswa-info">
                                        <div class="mahasiswa-avatar"><?php echo strtoupper(substr($p['nama_mahasiswa'], 0, 1)); ?></div>
                                        <div>
                                            <div class="mahasiswa-nama"><?php echo htmlspecialchars($p['nama_mahasiswa']); ?></div>
                                            <div class="mahasiswa-nim"><?php echo htmlspecialchars($p['nim']); ?></div>
                                            <div class="mahasiswa-prodi"><?php echo htmlspecialchars($p['prodi_mahasiswa'] ?: '-'); ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="judul-kp-text"><?php echo htmlspecialchars($p['judul_kp']); ?></div>
                                    <div class="perusahaan-text"><?php echo htmlspecialchars($p['nama_perusahaan'] ?: 'N/A'); ?></div>
                                    <div class="tanggal-text">Diajukan: <?php echo date("d M Y", strtotime($p['tanggal_pengajuan'])); ?></div>
                                </td>
                                <td><?php echo htmlspecialchars($p['nama_dosen_pembimbing'] ?: '<em>Belum Ditentukan</em>'); ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo strtolower(str_replace('_', '-', $p['status_pengajuan'])); ?>">
                                        <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $p['status_pengajuan']))); ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="pengajuan_kp_detail_admin.php?id_pengajuan=<?php echo $p['id_pengajuan']; ?>" class="btn btn-primary btn-sm">
                                        Kelola
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
    /* Menggunakan gaya dari halaman list lainnya */
    .list-container { max-width: 1600px; margin: 20px auto; padding: 2rem; }
    .list-header { margin-bottom: 1.5rem; text-align: center; }
    .table-responsive { background-color: #fff; border-radius: var(--border-radius); box-shadow: var(--card-shadow); overflow: hidden; }
    .data-table { width: 100%; border-collapse: collapse; }
    .data-table th, .data-table td { padding: 1rem 1.5rem; border-bottom: 1px solid var(--border-color); text-align: left; vertical-align: middle; }
    .data-table th { background-color: #f8f9fa; font-weight: 600; text-transform: uppercase; }
    .data-table tbody tr:hover { background-color: #f1f7ff; }

    .mahasiswa-prodi { font-size: 0.85em; color: #6c757d; }
    .tanggal-text { font-size: 0.85em; color: #6c757d; margin-top: 5px; }

    .filter-form {
        display: flex;
        gap: 1rem;
        align-items: center;
        background-color: #fff;
        padding: 1rem;
        border-radius: var(--border-radius);
        margin-bottom: 2rem;
        box-shadow: var(--card-shadow);
    }
    .filter-form .form-group { display: flex; align-items: center; gap: 0.5rem; }
    .filter-form label { font-weight: 500; }
    .filter-form select {
        padding: 8px 12px; border: 1px solid var(--border-color); border-radius: 8px;
        min-width: 250px; background-color: #f8f9fa;
    }

    /* Badge Status */
    .status-badge { padding: 5px 12px; border-radius: 20px; font-size: 0.8em; font-weight: 600; color: #fff; }
    .status-draft { background-color: #6c757d; }
    .status-diajukan-mahasiswa { background-color: #ffc107; color:#212529; }
    .status-diverifikasi-dospem, .status-ditolak-dospem, .status-ditolak-perusahaan { background-color: #dc3545; }
    .status-disetujui-dospem { background-color: #20c997; }
    .status-menunggu-konfirmasi-perusahaan { background-color: #6610f2; }
    .status-diterima-perusahaan { background-color: #198754; }
    .status-penentuan-dospem-kp { background-color: #0dcaf0; color:#212529; }
    .status-kp-berjalan { background-color: #0d6efd; }
    .status-selesai-pelaksanaan, .status-laporan-disetujui { background-color: #28a745; }
    .status-selesai-dinilai { background-color: #1f2023; }
    .status-dibatalkan { background-color: #adb5bd; color:#212529; }

</style>

<?php
require_once '../includes/footer.php';
if (isset($conn) && $conn) {
    $conn->close();
}
?>