<?php
// /KP/admin_prodi/laporan_kp_view.php (Versi Final dan Lengkap)

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once '../includes/auth_check.php';
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin_prodi') {
    header("Location: /KP/index.php?error=unauthorized_admin");
    exit();
}
require_once '../config/db_connect.php';

$laporan_data = [];
$statistik_perusahaan = [];
$available_angkatan = [];
$available_prodi = [];
$error_db = '';

$filter_tahun_angkatan = isset($_GET['tahun_angkatan']) ? (int)$_GET['tahun_angkatan'] : '';
$filter_status_kp = isset($_GET['status_kp']) ? trim($_GET['status_kp']) : '';
$filter_prodi = isset($_GET['prodi']) ? trim($_GET['prodi']) : '';

$available_status_kp = [
    'kp_berjalan' => 'KP Sedang Berjalan', 'selesai_pelaksanaan' => 'Selesai Pelaksanaan',
    'laporan_disetujui' => 'Laporan Disetujui', 'selesai_dinilai' => 'Selesai Dinilai',
];

if ($conn) {
    // Ambil data untuk filter
    $result_angkatan = $conn->query("SELECT DISTINCT angkatan FROM mahasiswa WHERE angkatan IS NOT NULL ORDER BY angkatan DESC");
    if ($result_angkatan) while ($row = $result_angkatan->fetch_assoc()) $available_angkatan[] = $row['angkatan'];
    
    $result_prodi = $conn->query("SELECT DISTINCT prodi FROM mahasiswa WHERE prodi IS NOT NULL AND prodi != '' ORDER BY prodi ASC");
    if ($result_prodi) while ($row = $result_prodi->fetch_assoc()) $available_prodi[] = $row['prodi'];

    // Query untuk statistik perusahaan
    $sql_stats = "SELECT p.nama_perusahaan, COUNT(pk.id_pengajuan) AS jumlah_mahasiswa FROM perusahaan p JOIN pengajuan_kp pk ON p.id_perusahaan = pk.id_perusahaan GROUP BY p.id_perusahaan ORDER BY jumlah_mahasiswa DESC LIMIT 10";
    $result_stats = $conn->query($sql_stats);
    if ($result_stats) while($row_stat = $result_stats->fetch_assoc()) $statistik_perusahaan[] = $row_stat;

    // Query utama untuk laporan detail
    $sql_laporan = "SELECT pk.id_pengajuan, m.nim, m.nama AS nama_mahasiswa, m.prodi AS prodi_mahasiswa, m.angkatan AS angkatan_mahasiswa, pk.judul_kp, pr.nama_perusahaan, dp.nama_dosen AS nama_dosen_pembimbing, pk.tanggal_mulai_rencana, pk.tanggal_selesai_rencana, pk.status_pengajuan FROM pengajuan_kp pk JOIN mahasiswa m ON pk.nim = m.nim LEFT JOIN perusahaan pr ON pk.id_perusahaan = pr.id_perusahaan LEFT JOIN dosen_pembimbing dp ON pk.nip_dosen_pembimbing_kp = dp.nip WHERE 1=1";
    
    $params = [];
    $types = "";
    if (!empty($filter_tahun_angkatan)) { $sql_laporan .= " AND m.angkatan = ?"; $params[] = $filter_tahun_angkatan; $types .= "i"; }
    if (!empty($filter_status_kp)) { $sql_laporan .= " AND pk.status_pengajuan = ?"; $params[] = $filter_status_kp; $types .= "s"; }
    if (!empty($filter_prodi)) { $sql_laporan .= " AND m.prodi = ?"; $params[] = $filter_prodi; $types .= "s"; }
    $sql_laporan .= " ORDER BY m.angkatan DESC, m.prodi ASC, m.nama ASC";

    $stmt_laporan = $conn->prepare($sql_laporan);
    if ($stmt_laporan) {
        if (!empty($params)) $stmt_laporan->bind_param($types, ...$params);
        $stmt_laporan->execute();
        $result_laporan = $stmt_laporan->get_result();
        while ($row_lap = $result_laporan->fetch_assoc()) $laporan_data[] = $row_lap;
        $stmt_laporan->close();
    }
} else {
    $error_db = "Koneksi database gagal.";
}

$page_title = "Laporan & Statistik Kerja Praktek";
require_once '../includes/header.php';
?>

<div class="main-content-full">
    <div class="list-container">
        <div class="list-header">
            <h1><?php echo htmlspecialchars($page_title); ?></h1>
            <p>Lihat statistik dan hasilkan laporan detail mengenai pelaksanaan Kerja Praktek mahasiswa.</p>
        </div>

        <div class="report-grid">
            <div class="stat-card">
                <div class="card-header"><h4>Statistik Perusahaan Teratas</h4></div>
                <div class="card-body">
                    <?php if(!empty($statistik_perusahaan)): ?>
                        <ol class="stat-list">
                            <?php foreach($statistik_perusahaan as $stat): ?>
                            <li>
                                <span class="stat-name"><?php echo htmlspecialchars($stat['nama_perusahaan']); ?></span>
                                <span class="stat-value"><?php echo htmlspecialchars($stat['jumlah_mahasiswa']); ?> Mhs</span>
                            </li>
                            <?php endforeach; ?>
                        </ol>
                    <?php else: ?>
                        <p><em>Belum ada data statistik.</em></p>
                    <?php endif; ?>
                </div>
            </div>
            <div class="filter-card">
                <div class="card-header"><h4>Filter Laporan Detail</h4></div>
                <div class="card-body">
                    <form action="laporan_kp_view.php" method="GET" class="filter-form-grid">
                        <div class="form-group">
                            <label for="tahun_angkatan">Angkatan</label>
                            <select name="tahun_angkatan" id="tahun_angkatan"><option value="">Semua</option><?php foreach ($available_angkatan as $a) echo "<option value='$a' ".($filter_tahun_angkatan==$a ? 'selected' : '').">$a</option>"; ?></select>
                        </div>
                        <div class="form-group">
                            <label for="prodi">Program Studi</label>
                            <select name="prodi" id="prodi"><option value="">Semua</option><?php foreach ($available_prodi as $p) echo "<option value='$p' ".($filter_prodi==$p ? 'selected' : '').">$p</option>"; ?></select>
                        </div>
                        <div class="form-group">
                            <label for="status_kp">Status KP</label>
                            <select name="status_kp" id="status_kp"><option value="">Semua</option><?php foreach ($available_status_kp as $v => $t) echo "<option value='$v' ".($filter_status_kp==$v ? 'selected' : '').">$t</option>"; ?></select>
                        </div>
                        <div class="form-actions">
                            <a href="laporan_kp_view.php" class="btn btn-secondary">Reset</a>
                            <button type="submit" class="btn btn-primary">Terapkan Filter</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="detail-report-section">
            <div class="list-header" style="text-align: left; border-top: 1px solid var(--border-color); padding-top: 2rem; margin-top: 2rem;">
                <h2>Laporan Detail Pengajuan KP</h2>
                <p>Total ditemukan: <strong><?php echo count($laporan_data); ?></strong> data.</p>
            </div>
             <?php if (!empty($laporan_data)): ?>
                <div class="table-responsive">
                    </div>
             <?php else: ?>
                <div class="message info"><p>Tidak ada data laporan yang cocok dengan filter yang Anda pilih.</p></div>
             <?php endif; ?>
        </div>
    </div>
</div>

<style>
/* CSS untuk Laporan & Statistik */
.list-container { max-width: 1600px; }
.report-grid { display: grid; grid-template-columns: 1fr 2fr; gap: 2rem; align-items: flex-start; margin-bottom: 2rem; }
.stat-card, .filter-card { background-color: #fff; border-radius: var(--border-radius); box-shadow: var(--card-shadow); }
.card-header { padding: 1rem 1.5rem; border-bottom: 1px solid var(--border-color); }
.card-header h4 { margin: 0; font-size: 1.1em; }
.card-body { padding: 1.5rem; }

/* Statistik */
.stat-list { list-style: none; padding: 0; margin: 0; }
.stat-list li { display: flex; justify-content: space-between; align-items: center; padding: 10px 5px; border-bottom: 1px dashed #eee; transition: background-color 0.2s ease; }
.stat-list li:hover { background-color: #f8f9fa; }
.stat-list li:last-child { border-bottom: none; }
.stat-name { font-weight: 500; }
.stat-value { font-weight: 600; color: var(--primary-color); background-color: #e9f5ff; padding: 3px 8px; border-radius: 20px; font-size: 0.9em; }

/* Filter */
.filter-form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 1rem; align-items: flex-end; }
.form-group label { display: block; font-weight: 500; margin-bottom: 0.5rem; }
.form-group select { width: 100%; padding: 10px; border: 1px solid var(--border-color); border-radius: 8px; }
.form-actions { display: flex; gap: 10px; }
.form-actions .btn { width: 100%; padding: 10px; }
</style>

<?php
require_once '../includes/footer.php';
if (isset($conn)) { $conn->close(); }
?>