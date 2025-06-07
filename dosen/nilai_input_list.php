<?php
// /KP/dosen/nilai_input_list.php (Versi Diperbarui)

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once '../includes/auth_check.php';
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'dosen') {
    header("Location: /KP/index.php?error=unauthorized_dosen");
    exit();
}

$nip_dosen_login = $_SESSION['user_id'];
require_once '../config/db_connect.php';

$list_kp_untuk_dinilai = [];
$error_db = '';

if ($conn && ($conn instanceof mysqli)) {
    $status_siap_dinilai = ['selesai_pelaksanaan', 'laporan_disetujui', 'selesai_dinilai'];
    $status_placeholders = implode(',', array_fill(0, count($status_siap_dinilai), '?'));

    $sql = "SELECT
                pk.id_pengajuan, m.nim, m.nama AS nama_mahasiswa, pk.judul_kp, pk.status_pengajuan,
                nk.nilai_dosen_pembimbing
            FROM pengajuan_kp pk
            JOIN mahasiswa m ON pk.nim = m.nim
            LEFT JOIN nilai_kp nk ON pk.id_pengajuan = nk.id_pengajuan
            WHERE pk.nip_dosen_pembimbing_kp = ? AND pk.status_pengajuan IN ($status_placeholders)
            ORDER BY nk.nilai_dosen_pembimbing ASC, pk.status_pengajuan ASC, m.nama ASC";

    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $types = 's' . str_repeat('s', count($status_siap_dinilai));
        $params = array_merge([$nip_dosen_login], $status_siap_dinilai);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $list_kp_untuk_dinilai[] = $row;
        }
        $stmt->close();
    } else {
        $error_db = "Gagal menyiapkan query: " . $conn->error;
    }
} else {
    $error_db = "Koneksi database gagal.";
}

$page_title = "Penilaian KP oleh Pembimbing";
require_once '../includes/header.php';
?>

<div class="main-content-full">
    <div class="list-container">
        <div class="list-header">
            <h1><?php echo htmlspecialchars($page_title); ?></h1>
            <p>Daftar mahasiswa bimbingan Anda yang telah menyelesaikan KP dan siap untuk dinilai.</p>
        </div>

        <?php if (!empty($error_db)): ?>
            <div class="message error"><p><?php echo $error_db; ?></p></div>
        <?php endif; ?>

        <?php if (empty($list_kp_untuk_dinilai) && empty($error_db)): ?>
            <div class="message info">
                <h4>Tidak Ada Data</h4>
                <p>Saat ini tidak ada mahasiswa yang memerlukan penilaian dari Anda.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Mahasiswa</th>
                            <th>Judul KP</th>
                            <th>Status KP</th>
                            <th>Nilai Anda</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($list_kp_untuk_dinilai as $kp): ?>
                            <tr>
                                <td>
                                    <div class="mahasiswa-info">
                                        <div class="mahasiswa-avatar"><?php echo strtoupper(substr($kp['nama_mahasiswa'], 0, 1)); ?></div>
                                        <div>
                                            <div class="mahasiswa-nama"><?php echo htmlspecialchars($kp['nama_mahasiswa']); ?></div>
                                            <div class="mahasiswa-nim"><?php echo htmlspecialchars($kp['nim']); ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="judul-kp-cell"><?php echo htmlspecialchars($kp['judul_kp']); ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo strtolower(str_replace('_', '-', $kp['status_pengajuan'])); ?>">
                                        <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $kp['status_pengajuan']))); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($kp['nilai_dosen_pembimbing'] !== null): ?>
                                        <span class="nilai-badge nilai-sudah"><?php echo htmlspecialchars(number_format($kp['nilai_dosen_pembimbing'], 2)); ?></span>
                                    <?php else: ?>
                                        <span class="nilai-badge nilai-kosong">Belum Diinput</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="nilai_input_form.php?id_pengajuan=<?php echo $kp['id_pengajuan']; ?>" class="btn btn-primary btn-sm">
                                        <?php echo ($kp['nilai_dosen_pembimbing'] !== null) ? 'Edit Nilai' : 'Input Nilai'; ?>
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
    .list-container { max-width: 1400px; margin: 20px auto; padding: 2rem; }
    .list-header { margin-bottom: 1.5rem; text-align: center; }
    .table-responsive { background-color: #fff; border-radius: var(--border-radius); box-shadow: var(--card-shadow); overflow: hidden; }
    .data-table { width: 100%; border-collapse: collapse; }
    .data-table th, .data-table td { padding: 1rem 1.5rem; border-bottom: 1px solid var(--border-color); text-align: left; vertical-align: middle; }
    .data-table th { background-color: #f8f9fa; font-weight: 600; }
    .data-table tbody tr:hover { background-color: #f1f7ff; }

    .mahasiswa-info { display: flex; align-items: center; gap: 15px; }
    .mahasiswa-avatar { width: 45px; height: 45px; border-radius: 50%; background-color: var(--primary-color); color: white; display: inline-flex; align-items: center; justify-content: center; font-size: 1.2em; font-weight: 600; flex-shrink: 0; }
    .mahasiswa-nama { font-weight: 600; }
    .mahasiswa-nim { font-size: 0.9em; color: #6c757d; }
    .judul-kp-cell { min-width: 300px; }
    
    .status-badge, .nilai-badge { padding: 5px 12px; border-radius: 20px; font-size: 0.8em; font-weight: 600; }
    .status-selesai-pelaksanaan { background-color: #28a745; color: #fff; }
    .status-laporan-disetujui { background-color: #d63384; color: #fff; }
    .status-selesai-dinilai { background-color: #1f2023; color: #fff; }
    .nilai-sudah { background-color: #28a745; color: #fff; }
    .nilai-kosong { background-color: #ffc107; color: #212529; }
</style>

<?php
require_once '../includes/footer.php';
if (isset($conn) && $conn) { $conn->close(); }
?>