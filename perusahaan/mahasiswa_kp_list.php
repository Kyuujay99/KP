<?php
// /KP/perusahaan/mahasiswa_kp_list.php (Versi Diperbarui)

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once '../includes/auth_check.php';
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'perusahaan') {
    header("Location: /KP/index.php?error=unauthorized_perusahaan");
    exit();
}

$id_perusahaan_login = $_SESSION['user_id'];
$nama_perusahaan_login = $_SESSION['user_nama'];
require_once '../config/db_connect.php';

$list_mahasiswa = [];
$error_db = '';
$filter_status = isset($_GET['status_kp']) ? $_GET['status_kp'] : 'kp_berjalan';

if ($conn) {
    $sql = "SELECT m.nim, m.nama AS nama_mahasiswa, m.prodi AS prodi_mahasiswa, m.angkatan,
                   pk.id_pengajuan, pk.judul_kp, pk.tanggal_mulai_rencana, pk.tanggal_selesai_rencana, pk.status_pengajuan,
                   nk.nilai_pembimbing_lapangan
            FROM pengajuan_kp pk
            JOIN mahasiswa m ON pk.nim = m.nim
            LEFT JOIN nilai_kp nk ON pk.id_pengajuan = nk.id_pengajuan
            WHERE pk.id_perusahaan = ?";

    $params = [$id_perusahaan_login];
    $types = "i";

    if (!empty($filter_status) && $filter_status !== 'semua') {
        $sql .= " AND pk.status_pengajuan = ?";
        $params[] = $filter_status;
        $types .= "s";
    } else if (empty($filter_status)) {
        // Default jika tidak ada filter, tampilkan yang relevan
        $sql .= " AND pk.status_pengajuan IN ('diterima_perusahaan', 'kp_berjalan', 'selesai_pelaksanaan')";
    }
    
    $sql .= " ORDER BY pk.tanggal_mulai_rencana DESC, m.nama ASC";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $list_mahasiswa[] = $row;
        }
        $stmt->close();
    } else {
        $error_db = "Gagal menyiapkan query: " . $conn->error;
    }
}

$opsi_filter = [
    'kp_berjalan' => 'KP Sedang Berjalan',
    'selesai_pelaksanaan' => 'Selesai (Menunggu Nilai)',
    'diterima_perusahaan' => 'Diterima (Belum Mulai)',
    'semua' => 'Tampilkan Semua'
];

$page_title = "Mahasiswa KP di " . htmlspecialchars($nama_perusahaan_login);
require_once '../includes/header.php';
?>

<div class="main-content-full">
    <div class="list-container">
        <div class="list-header">
            <h1><?php echo htmlspecialchars($page_title); ?></h1>
            <p>Daftar mahasiswa yang sedang atau akan melaksanakan Kerja Praktek di perusahaan Anda.</p>
        </div>
        
        <form action="mahasiswa_kp_list.php" method="GET" class="filter-form">
            <div class="form-group">
                <label for="status_kp">Filter Status:</label>
                <select name="status_kp" id="status_kp" onchange="this.form.submit()">
                    <?php foreach ($opsi_filter as $value => $text): ?>
                        <option value="<?php echo $value; ?>" <?php echo ($filter_status == $value) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($text); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>

        <?php if (!empty($error_db)): ?>
            <div class="message error"><p><?php echo $error_db; ?></p></div>
        <?php endif; ?>

        <?php if (empty($list_mahasiswa) && empty($error_db)): ?>
            <div class="message info">
                <h4>Tidak Ada Data</h4>
                <p>Tidak ada mahasiswa yang cocok dengan filter yang Anda pilih.</p>
            </div>
        <?php else: ?>
            <div class="user-card-grid">
                <?php foreach ($list_mahasiswa as $mhs): ?>
                    <div class="user-card">
                        <div class="card-header-status status-<?php echo strtolower(htmlspecialchars($mhs['status_pengajuan'])); ?>">
                            <?php echo ucfirst(str_replace('_', ' ', htmlspecialchars($mhs['status_pengajuan']))); ?>
                        </div>
                        <div class="card-main-info">
                            <div class="user-avatar"><?php echo strtoupper(substr($mhs['nama_mahasiswa'], 0, 1)); ?></div>
                            <h4 class="user-name"><?php echo htmlspecialchars($mhs['nama_mahasiswa']); ?></h4>
                            <p class="user-id"><?php echo htmlspecialchars($mhs['nim']); ?></p>
                            <p class="user-extra-info"><?php echo htmlspecialchars($mhs['prodi_mahasiswa']); ?> - Angkatan <?php echo htmlspecialchars($mhs['angkatan']); ?></p>
                        </div>
                        <div class="card-body-info">
                             <p class="judul-kp-text"><?php echo htmlspecialchars($mhs['judul_kp']); ?></p>
                             <div class="periode-info">
                                <span>Periode:</span>
                                <strong><?php echo date("d M Y", strtotime($mhs['tanggal_mulai_rencana'])); ?> - <?php echo date("d M Y", strtotime($mhs['tanggal_selesai_rencana'])); ?></strong>
                             </div>
                        </div>
                        <div class="card-actions">
                             <?php if (in_array($mhs['status_pengajuan'], ['kp_berjalan', 'selesai_pelaksanaan'])): ?>
                                <a href="penilaian_lapangan_input.php?id_pengajuan=<?php echo $mhs['id_pengajuan']; ?>" class="btn btn-primary">
                                    <?php echo ($mhs['nilai_pembimbing_lapangan'] !== null) ? '📝 Edit Nilai' : '⭐ Input Nilai'; ?>
                                </a>
                            <?php else: ?>
                                <button class="btn btn-secondary" disabled>Penilaian Belum Aktif</button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
    /* Menggunakan gaya dari halaman kelola pengguna admin dan disesuaikan */
    .user-card-grid {
        grid-template-columns: repeat(auto-fit, minmax(340px, 1fr));
    }
    .user-card .card-body-info {
        padding: 1.5rem;
        border-bottom: 1px solid var(--border-color);
        flex-grow: 1;
    }
    .judul-kp-text { font-weight: 600; color: var(--dark-color); margin-bottom: 1rem; }
    .periode-info { background-color: #f8f9fa; padding: 10px; border-radius: 8px; text-align: center; }
    .periode-info span { display: block; font-size: 0.9em; color: var(--secondary-color); }
    .periode-info strong { color: var(--dark-color); }
    .card-actions .btn { width: 100%; border-radius: 0; padding: 1rem; }
    .btn-success { background-color: #28a745; color: white; border:none; }
    .status-diterima-perusahaan { background-color: #20c997; }
    .status-kp-berjalan { background-color: #0d6efd; }
    .status-selesai-pelaksanaan { background-color: #6c757d; }
</style>

<?php
require_once '../includes/footer.php';
if (isset($conn)) { $conn->close(); }
?>