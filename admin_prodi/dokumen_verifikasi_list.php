<?php
// /KP/admin_prodi/dokumen_verifikasi_list.php (Versi Final dan Lengkap)

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once '../includes/auth_check.php';
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin_prodi') {
    header("Location: /KP/index.php?error=unauthorized_admin");
    exit();
}
require_once '../config/db_connect.php';

$list_dokumen = [];
$error_db = '';
$filter_status_dokumen = isset($_GET['status_dokumen']) ? $_GET['status_dokumen'] : 'pending';

if ($conn) {
    $sql = "SELECT
                dk.id_dokumen, dk.id_pengajuan, dk.nama_dokumen, dk.jenis_dokumen, dk.tanggal_upload,
                dk.status_verifikasi_dokumen, pk.judul_kp, m.nim AS nim_mahasiswa, m.nama AS nama_mahasiswa
            FROM dokumen_kp dk
            JOIN pengajuan_kp pk ON dk.id_pengajuan = pk.id_pengajuan
            JOIN mahasiswa m ON pk.nim = m.nim";

    $params = [];
    $types = "";
    if (!empty($filter_status_dokumen) && $filter_status_dokumen !== 'semua') {
        $sql .= " WHERE dk.status_verifikasi_dokumen = ?";
        $params[] = $filter_status_dokumen;
        $types .= "s";
    }
    $sql .= " ORDER BY dk.tanggal_upload ASC";

    $stmt = $conn->prepare($sql);
    if ($stmt) {
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $list_dokumen[] = $row;
        }
        $stmt->close();
    } else {
        $error_db = "Gagal menyiapkan query: " . $conn->error;
    }
}

$opsi_filter_status_dokumen = [
    'pending' => 'Pending', 'disetujui' => 'Disetujui',
    'revisi_diperlukan' => 'Revisi Diperlukan', 'ditolak' => 'Ditolak',
    'semua' => 'Tampilkan Semua'
];
$page_title = "Verifikasi Dokumen KP";
require_once '../includes/header.php';
?>

<div class="main-content-full">
    <div class="list-container">
        <div class="list-header">
            <h1><?php echo htmlspecialchars($page_title); ?></h1>
            <p>Periksa dan berikan status verifikasi untuk semua dokumen yang diunggah oleh mahasiswa.</p>
        </div>

        <form action="dokumen_verifikasi_list.php" method="GET" class="filter-form">
            <div class="form-group">
                <label for="status_dokumen">Filter Status:</label>
                <select name="status_dokumen" id="status_dokumen" onchange="this.form.submit()">
                    <?php foreach ($opsi_filter_status_dokumen as $value => $text): ?>
                        <option value="<?php echo $value; ?>" <?php echo ($filter_status_dokumen == $value) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($text); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php if(!empty($filter_status_dokumen) && $filter_status_dokumen !== 'pending'): ?>
                <a href="dokumen_verifikasi_list.php" class="btn btn-secondary btn-sm">Tampilkan Pending Saja</a>
            <?php endif; ?>
        </form>

        <?php if (!empty($error_db)): ?>
            <div class="message error"><p><?php echo $error_db; ?></p></div>
        <?php endif; ?>

        <?php if (empty($list_dokumen) && empty($error_db)): ?>
            <div class="message info">
                <h4>Tidak Ada Dokumen</h4>
                <p>Tidak ada dokumen yang cocok dengan filter status yang dipilih.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Mahasiswa</th>
                            <th>Detail Dokumen</th>
                            <th>Terkait KP</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($list_dokumen as $doc): ?>
                            <tr>
                                <td>
                                    <div class="mahasiswa-info">
                                        <div class="mahasiswa-avatar"><?php echo strtoupper(substr($doc['nama_mahasiswa'], 0, 1)); ?></div>
                                        <div>
                                            <div class="mahasiswa-nama"><?php echo htmlspecialchars($doc['nama_mahasiswa']); ?></div>
                                            <div class="mahasiswa-nim"><?php echo htmlspecialchars($doc['nim_mahasiswa']); ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="dokumen-nama"><?php echo htmlspecialchars($doc['nama_dokumen']); ?></div>
                                    <div class="dokumen-jenis"><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $doc['jenis_dokumen']))); ?></div>
                                    <div class="tanggal-text">Diupload: <?php echo date("d M Y, H:i", strtotime($doc['tanggal_upload'])); ?></div>
                                </td>
                                <td class="judul-kp-cell">
                                    <a href="pengajuan_kp_detail_admin.php?id_pengajuan=<?php echo $doc['id_pengajuan']; ?>" title="Lihat Detail KP Terkait">
                                        <?php echo htmlspecialchars($doc['judul_kp']); ?>
                                    </a>
                                </td>
                                <td>
                                    <span class="status-badge status-dokumen-<?php echo strtolower(htmlspecialchars($doc['status_verifikasi_dokumen'])); ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', htmlspecialchars($doc['status_verifikasi_dokumen']))); ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="dokumen_verifikasi_form.php?id_dokumen=<?php echo $doc['id_dokumen']; ?>&id_pengajuan=<?php echo $doc['id_pengajuan']; ?>" class="btn btn-primary btn-sm">
                                        Verifikasi
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
.list-container { max-width: 1400px; margin: 20px auto; padding: 2rem; }
.list-header { margin-bottom: 1.5rem; text-align: center; }
.table-responsive { background-color: #fff; border-radius: var(--border-radius); box-shadow: var(--card-shadow); overflow: hidden; }
.data-table { width: 100%; border-collapse: collapse; }
.data-table th, .data-table td { padding: 1rem 1.5rem; border-bottom: 1px solid var(--border-color); text-align: left; vertical-align: middle; }
.data-table th { background-color: #f8f9fa; font-weight: 600; font-size: 0.9em; text-transform: uppercase; letter-spacing: 0.5px; }
.data-table tbody tr:hover { background-color: #f1f7ff; }
.judul-kp-cell a { text-decoration: none; color: var(--primary-color); font-weight: 500; }
.judul-kp-cell a:hover { text-decoration: underline; }

.mahasiswa-info { display: flex; align-items: center; gap: 15px; }
.mahasiswa-avatar { width: 45px; height: 45px; border-radius: 50%; background-color: var(--primary-color); color: white; display: inline-flex; align-items: center; justify-content: center; font-size: 1.2em; font-weight: 600; flex-shrink: 0; }
.mahasiswa-nama { font-weight: 600; }
.mahasiswa-nim { font-size: 0.9em; color: #6c757d; }
.dokumen-nama { font-weight: 600; }
.dokumen-jenis { font-size: 0.9em; color: #6c757d; }
.tanggal-text { font-size: 0.85em; color: #6c757d; margin-top: 5px; }
.filter-form { display: flex; gap: 1rem; align-items: center; background-color: #fff; padding: 1rem; border-radius: var(--border-radius); margin-bottom: 2rem; box-shadow: var(--card-shadow); }

.status-badge { padding: 5px 12px; border-radius: 20px; font-size: 0.8em; font-weight: 600; }
.status-dokumen-pending { background-color: #ffc107; color: #212529; }
.status-dokumen-disetujui { background-color: #28a745; color: #fff; }
.status-dokumen-revisi_diperlukan { background-color: #fd7e14; color: #fff; }
.status-dokumen-ditolak { background-color: #dc3545; color: #fff; }
</style>

<?php
require_once '../includes/footer.php';
if (isset($conn)) { $conn->close(); }
?>