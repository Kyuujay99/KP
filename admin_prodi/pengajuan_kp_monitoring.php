<?php
// /KP/admin_prodi/pengajuan_kp_monitoring.php (Versi Refactored)

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
$filter_status = isset($_GET['status']) ? trim($_GET['status']) : '';
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';


if ($conn && ($conn instanceof mysqli)) {
    $sql = "SELECT
                pk.id_pengajuan, m.nim, m.nama AS nama_mahasiswa, m.prodi AS prodi_mahasiswa,
                pk.judul_kp, pr.nama_perusahaan, dp.nama_dosen AS nama_dosen_pembimbing,
                pk.tanggal_pengajuan, pk.status_pengajuan
            FROM pengajuan_kp pk
            JOIN mahasiswa m ON pk.nim = m.nim
            LEFT JOIN perusahaan pr ON pk.id_perusahaan = pr.id_perusahaan
            LEFT JOIN dosen_pembimbing dp ON pk.nip_dosen_pembimbing_kp = dp.nip";

    $where_clauses = [];
    $params = [];
    $types = "";

    if (!empty($filter_status)) {
        $where_clauses[] = "pk.status_pengajuan = ?";
        $params[] = $filter_status;
        $types .= "s";
    }

    if (!empty($search_query)) {
        $where_clauses[] = "(m.nama LIKE ? OR m.nim LIKE ? OR pk.judul_kp LIKE ? OR pr.nama_perusahaan LIKE ?)";
        $search_term = "%" . $search_query . "%";
        $params[] = $search_term;
        $params[] = $search_term;
        $params[] = $search_term;
        $params[] = $search_term;
        $types .= "ssss";
    }

    if (!empty($where_clauses)) {
        $sql .= " WHERE " . implode(" AND ", $where_clauses);
    }

    $sql .= " ORDER BY pk.tanggal_pengajuan DESC, pk.id_pengajuan DESC";

    $stmt = $conn->prepare($sql);
    if ($stmt) {
        if (!empty($params)) {
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

<div class="kp-list-modern-container">

    <div class="list-hero-section">
        <div class="list-hero-content">
            <div class="list-hero-icon">
                <svg viewBox="0 0 24 24"><path d="M18 13v6a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2h6"/><path d="M15 3h6v6"/><path d="M10 14L21 3"/></svg>
            </div>
            <h1><?php echo htmlspecialchars($page_title); ?></h1>
            <p>Pantau semua pengajuan Kerja Praktek dari mahasiswa secara terpusat. Gunakan filter untuk menyaring data.</p>
        </div>
    </div>

    <div class="list-wrapper">
        <div class="filter-controls">
            <form action="pengajuan_kp_monitoring.php" method="GET" class="filter-form">
                <div class="search-wrapper">
                    <input type="text" name="search" id="searchInput" placeholder="Cari Mhs, Judul, Perusahaan..." value="<?php echo htmlspecialchars($search_query); ?>">
                    <button type="submit" class="search-btn">🔍</button>
                </div>
                <div class="filter-wrapper">
                    <label for="status">Filter Status:</label>
                    <select name="status" id="status" onchange="this.form.submit()">
                        <option value="">-- Semua Status --</option>
                        <?php foreach ($opsi_status_filter as $value => $text): ?>
                            <option value="<?php echo $value; ?>" <?php echo ($filter_status == $value) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($text); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <a href="pengajuan_kp_monitoring.php" class="btn-reset">Reset</a>
            </form>
        </div>

        <?php if (!empty($error_db)): ?>
            <div class="message error"><p><?php echo $error_db; ?></p></div>
        <?php endif; ?>

        <?php if (empty($list_semua_pengajuan) && empty($error_db)): ?>
            <div class="message info">
                <h4>Data Tidak Ditemukan</h4>
                <p>Tidak ada pengajuan KP yang cocok dengan kriteria pencarian atau filter Anda.</p>
            </div>
        <?php else: ?>
            <div class="card-grid">
                <?php foreach ($list_semua_pengajuan as $p): ?>
                    <div class="pengajuan-card animate-on-scroll">
                        <div class="card-header">
                             <div class="user-profile">
                                <div class="user-avatar"><?php echo strtoupper(substr($p['nama_mahasiswa'], 0, 1)); ?></div>
                                <div class="user-info">
                                    <h4><?php echo htmlspecialchars($p['nama_mahasiswa']); ?></h4>
                                    <span><?php echo htmlspecialchars($p['nim']); ?></span>
                                </div>
                            </div>
                            <span class="status-badge status-<?php echo strtolower(str_replace('_', '-', $p['status_pengajuan'])); ?>">
                                <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $p['status_pengajuan']))); ?>
                            </span>
                        </div>
                        <div class="card-body">
                             <p class="judul-kp"><?php echo htmlspecialchars($p['judul_kp']); ?></p>
                            <div class="detail-grid">
                                <div class="detail-item">
                                    <span>Perusahaan</span>
                                    <strong><?php echo htmlspecialchars($p['nama_perusahaan'] ?: 'N/A'); ?></strong>
                                </div>
                                <div class="detail-item">
                                    <span>Dosen Pembimbing</span>
                                    <strong><?php echo htmlspecialchars($p['nama_dosen_pembimbing'] ?: '<em>Belum Ditentukan</em>'); ?></strong>
                                </div>
                            </div>
                        </div>
                        <div class="card-footer">
                            <span class="tanggal-info">Diajukan: <?php echo date("d M Y", strtotime($p['tanggal_pengajuan'])); ?></span>
                            <a href="pengajuan_kp_detail_admin.php?id_pengajuan=<?php echo $p['id_pengajuan']; ?>" class="btn-aksi">
                                Kelola
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
/* Modern List Layout Styles */
.kp-list-modern-container {
    --primary-color: #667eea;
    --success-color: #34d399;
    --warning-color: #fbbf24;
    --info-color: #60a5fa;
    --danger-color: #f87171;
    --text-primary: #1f2937;
    --text-secondary: #6b7280;
    --bg-light: #f9fafb;
    --border-color: #e5e7eb;
    --card-shadow: 0 4px 6px -1px rgba(0,0,0,0.1), 0 2px 4px -1px rgba(0,0,0,0.06);
    --border-radius: 12px;
    font-family: 'Inter', sans-serif;
    color: var(--text-primary);
    max-width: 1600px;
    margin: 0 auto;
    padding: 2rem;
}
.list-hero-section {
    padding: 3rem 2rem; background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
    border-radius: var(--border-radius); margin-bottom: 2rem; color: white; text-align: center;
}
.list-hero-content { max-width: 700px; margin: 0 auto; }
.list-hero-icon {
    width: 60px; height: 60px; background: rgba(255,255,255,0.1);
    border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1.5rem;
}
.list-hero-icon svg { width: 28px; height: 28px; stroke: white; fill: none; stroke-width: 2; }
.list-hero-section h1 { font-size: 2.5rem; font-weight: 700; margin-bottom: 0.5rem; }
.list-hero-section p { font-size: 1.1rem; opacity: 0.9; font-weight: 300; }
.list-wrapper { background-color: #fff; padding: 2rem; border-radius: var(--border-radius); box-shadow: var(--card-shadow); }
.filter-controls {
    display: flex;
    flex-wrap: wrap;
    gap: 1rem;
    margin-bottom: 2rem;
    padding-bottom: 1.5rem;
    border-bottom: 1px solid var(--border-color);
}
.filter-form { display:contents; }
.search-wrapper { flex-grow: 1; position: relative; }
#searchInput { width: 100%; padding: 0.75rem 1rem; border-radius: 8px; border: 1px solid var(--border-color); font-size: 1rem; }
.search-btn { display: none; }
.filter-wrapper { display: flex; align-items: center; gap: 0.5rem; }
select { padding: 0.75rem; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-light); }
.btn-reset { padding: 0.75rem 1rem; border-radius: 8px; text-decoration: none; font-weight: 500; background: var(--border-color); color: var(--text-primary); }

.card-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(380px, 1fr)); gap: 1.5rem; }
.pengajuan-card { background: #fff; border-radius: var(--border-radius); border: 1px solid var(--border-color); box-shadow: var(--card-shadow); display: flex; flex-direction: column; transition: all 0.3s ease; }
.pengajuan-card:hover { transform: translateY(-5px); box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); }
.card-header { display: flex; justify-content: space-between; align-items: center; padding: 1rem 1.5rem; border-bottom: 1px solid var(--border-color); }
.user-profile { display: flex; align-items: center; gap: 1rem; }
.user-avatar { width: 40px; height: 40px; border-radius: 50%; background-color: var(--primary-color); color: white; display: flex; align-items: center; justify-content: center; font-weight: 600; flex-shrink: 0; }
.user-info h4 { margin: 0; font-size: 1.1rem; color: var(--text-primary); }
.user-info span { font-size: 0.9rem; color: var(--text-secondary); }
.card-body { padding: 1.5rem; flex-grow: 1; }
.judul-kp { font-weight: 600; font-size: 1.1rem; margin-top: 0; margin-bottom: 1.5rem; line-height: 1.4; color: var(--text-primary); }
.detail-grid { display: grid; gap: 1rem; }
.detail-item span { display: block; font-size: 0.85rem; color: var(--text-secondary); margin-bottom: 2px; }
.detail-item strong { font-weight: 500; color: var(--text-primary); }

.card-footer { display: flex; justify-content: space-between; align-items: center; padding: 1rem 1.5rem; background: var(--bg-light); border-top: 1px solid var(--border-color); }
.tanggal-info { font-size: 0.85rem; color: var(--text-secondary); }
.btn-aksi { display: flex; align-items: center; justify-content: center; gap: 0.5rem; padding: 0.6rem 1.2rem; border-radius: 8px; text-decoration: none; font-weight: 600; transition: all 0.2s ease; background: var(--primary-color); color: white; border: none; }
.btn-aksi:hover { background: #4338ca; }
.animate-on-scroll { opacity: 0; transform: translateY(20px); transition: opacity 0.5s ease-out, transform 0.5s ease-out; }
.animate-on-scroll.is-visible { opacity: 1; transform: translateY(0); }

/* Status Badges */
.status-badge { padding: 5px 12px; border-radius: 20px; font-size: 0.8em; font-weight: 600; color: #fff; text-transform: capitalize; }
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

<script>
document.addEventListener('DOMContentLoaded', function() {
    const container = document.querySelector('.kp-list-modern-container');
    if (!container) return;
    const animatedElements = container.querySelectorAll('.animate-on-scroll');
    const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry, index) => {
            if (entry.isIntersecting) {
                entry.target.style.transitionDelay = `${index * 50}ms`;
                entry.target.classList.add('is-visible');
                observer.unobserve(entry.target);
            }
        });
    }, { threshold: 0.1 });
    animatedElements.forEach(el => observer.observe(el));
});
</script>

<?php
require_once '../includes/footer.php';
if (isset($conn) && $conn) {
    $conn->close();
}
?>