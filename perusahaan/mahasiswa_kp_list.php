<?php
// /KP/perusahaan/mahasiswa_kp_list.php (Versi Modern & Terisolasi)

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
// Default filter adalah 'kp_berjalan' jika tidak ada parameter GET
$filter_status = isset($_GET['status_kp']) ? $_GET['status_kp'] : 'kp_berjalan'; 

if ($conn) {
    // Query dasar untuk mengambil data mahasiswa yang relevan
    $sql = "SELECT m.nim, m.nama AS nama_mahasiswa, m.prodi AS prodi_mahasiswa, m.angkatan,
                   pk.id_pengajuan, pk.judul_kp, pk.tanggal_mulai_rencana, pk.tanggal_selesai_rencana, pk.status_pengajuan,
                   nk.nilai_pembimbing_lapangan
            FROM pengajuan_kp pk
            JOIN mahasiswa m ON pk.nim = m.nim
            LEFT JOIN nilai_kp nk ON pk.id_pengajuan = nk.id_pengajuan
            WHERE pk.id_perusahaan = ?";

    $params = [$id_perusahaan_login];
    $types = "i";

    // Menerapkan filter status
    if (!empty($filter_status) && $filter_status !== 'semua') {
        $sql .= " AND pk.status_pengajuan = ?";
        $params[] = $filter_status;
        $types .= "s";
    } else if ($filter_status === 'semua') {
        // Jika 'semua', tampilkan semua status yang relevan untuk perusahaan
         $sql .= " AND pk.status_pengajuan IN ('diterima_perusahaan', 'kp_berjalan', 'selesai_pelaksanaan', 'selesai_dinilai')";
    }
    
    $sql .= " ORDER BY pk.tanggal_mulai_rencana DESC, m.nama ASC";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param($types, ...$params);
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

// Opsi untuk filter bar
$opsi_filter = [
    'kp_berjalan' => 'KP Sedang Berjalan',
    'diterima_perusahaan' => 'Diterima (Belum Mulai)',
    'selesai_pelaksanaan' => 'Selesai (Menunggu Nilai)',
    'semua' => 'Tampilkan Semua Relevan'
];

$page_title = "Mahasiswa KP di " . htmlspecialchars($nama_perusahaan_login);
require_once '../includes/header.php';
?>

<div class="kp-list-modern-container">

    <div class="list-hero-section">
        <div class="list-hero-content">
            <div class="list-hero-icon">
                <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
            </div>
            <h1><?php echo htmlspecialchars($page_title); ?></h1>
            <p>Daftar mahasiswa yang sedang atau akan melaksanakan Kerja Praktek di perusahaan Anda.</p>
        </div>
    </div>

    <div class="list-wrapper">
        <div class="filter-bar">
            <?php foreach ($opsi_filter as $value => $text): ?>
                <a href="?status_kp=<?php echo $value; ?>" class="filter-item <?php echo ($filter_status == $value) ? 'active' : ''; ?>">
                    <?php echo htmlspecialchars($text); ?>
                </a>
            <?php endforeach; ?>
        </div>
        
        <?php if (!empty($error_db)): ?>
            <div class="message error"><p><?php echo $error_db; ?></p></div>
        <?php endif; ?>

        <?php if (empty($list_mahasiswa) && empty($error_db)): ?>
            <div class="message info">
                <h4>Tidak Ada Data</h4>
                <p>Tidak ada mahasiswa yang cocok dengan filter status "<?php echo htmlspecialchars($opsi_filter[$filter_status] ?? $filter_status); ?>".</p>
            </div>
        <?php else: ?>
            <div class="mhs-grid">
                <?php foreach ($list_mahasiswa as $mhs): ?>
                    <div class="mhs-card animate-on-scroll">
                        <div class="mhs-card-header">
                            <div class="user-profile">
                                <div class="user-avatar"><?php echo strtoupper(substr($mhs['nama_mahasiswa'], 0, 1)); ?></div>
                                <div class="user-info">
                                    <h4><?php echo htmlspecialchars($mhs['nama_mahasiswa']); ?></h4>
                                    <span><?php echo htmlspecialchars($mhs['nim']); ?></span>
                                </div>
                            </div>
                            <span class="status-badge status-<?php echo strtolower(htmlspecialchars($mhs['status_pengajuan'])); ?>">
                                <?php echo ucfirst(str_replace('_', ' ', htmlspecialchars($mhs['status_pengajuan']))); ?>
                            </span>
                        </div>
                        <div class="mhs-card-body">
                            <p class="judul-kp"><?php echo htmlspecialchars($mhs['judul_kp']); ?></p>
                            <div class="detail-grid">
                                <div class="detail-item">
                                    <span>Program Studi</span>
                                    <strong><?php echo htmlspecialchars($mhs['prodi_mahasiswa']); ?> '<?php echo substr(htmlspecialchars($mhs['angkatan']), -2); ?></strong>
                                </div>
                                <div class="detail-item">
                                    <span>Periode KP</span>
                                    <strong><?php echo date("d M Y", strtotime($mhs['tanggal_mulai_rencana'])); ?> - <?php echo date("d M Y", strtotime($mhs['tanggal_selesai_rencana'])); ?></strong>
                                </div>
                            </div>
                        </div>
                        <div class="mhs-card-footer">
                             <?php if (in_array($mhs['status_pengajuan'], ['kp_berjalan', 'selesai_pelaksanaan', 'selesai_dinilai'])): ?>
                                <a href="penilaian_lapangan_input.php?id_pengajuan=<?php echo $mhs['id_pengajuan']; ?>" class="btn-aksi <?php echo ($mhs['nilai_pembimbing_lapangan'] !== null) ? 'edit' : 'input'; ?>">
                                     <?php if ($mhs['nilai_pembimbing_lapangan'] !== null): ?>
                                        <svg viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
                                        Edit Nilai (<?php echo htmlspecialchars($mhs['nilai_pembimbing_lapangan']); ?>)
                                     <?php else: ?>
                                        <svg viewBox="0 0 24 24"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>
                                        Input Nilai
                                    <?php endif; ?>
                                </a>
                            <?php else: ?>
                                <span class="btn-aksi disabled">Penilaian Belum Aktif</span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
/* GAYA BARU YANG TERISOLASI DALAM KONTENER */
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
    max-width: 1400px;
    margin: 0 auto;
    padding: 2rem;
}
.kp-list-modern-container svg {
    stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; fill: none; stroke: currentColor;
}

/* Hero Section */
.kp-list-modern-container .list-hero-section { /* ... sama seperti .form-hero-section ... */ }
/* ... (Gaya Hero disamakan dengan halaman form sebelumnya) ... */
.kp-list-modern-container .list-hero-section {
    padding: 3rem 2rem; background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
    border-radius: var(--border-radius); margin-bottom: 2rem; color: white; text-align: center;
}
.kp-list-modern-container .list-hero-content { max-width: 700px; margin: 0 auto; }
.kp-list-modern-container .list-hero-icon {
    width: 60px; height: 60px; background: rgba(255,255,255,0.1);
    border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1.5rem;
}
.kp-list-modern-container .list-hero-icon svg { width: 28px; height: 28px; stroke: white; }
.kp-list-modern-container .list-hero-section h1 { font-size: 2.5rem; font-weight: 700; margin-bottom: 0.5rem; }
.kp-list-modern-container .list-hero-section p { font-size: 1.1rem; opacity: 0.9; font-weight: 300; }

/* Wrapper & Filter */
.kp-list-modern-container .list-wrapper { background-color: #fff; padding: 2rem; border-radius: var(--border-radius); box-shadow: var(--card-shadow); }
.kp-list-modern-container .filter-bar {
    display: flex; flex-wrap: wrap; gap: 0.75rem;
    margin-bottom: 2rem; padding-bottom: 1.5rem; border-bottom: 1px solid var(--border-color);
}
.kp-list-modern-container .filter-item {
    text-decoration: none; padding: 0.5rem 1rem; border-radius: 8px;
    font-weight: 500; color: var(--text-secondary); background-color: var(--bg-light);
    border: 1px solid var(--border-color); transition: all 0.2s ease;
}
.kp-list-modern-container .filter-item:hover { background-color: #e5e7eb; color: var(--text-primary); }
.kp-list-modern-container .filter-item.active {
    background-color: var(--primary-color); color: white; border-color: var(--primary-color);
    box-shadow: 0 4px 14px rgba(102, 126, 234, 0.3);
}

/* Notifikasi */
.kp-list-modern-container .message { padding: 1.5rem; border-radius: var(--border-radius); text-align: center; }
.kp-list-modern-container .message.info { background-color: #eff6ff; color: #1e40af; }
.kp-list-modern-container .message h4 { margin-top: 0; font-size: 1.2rem; }

/* Grid Kartu Mahasiswa */
.kp-list-modern-container .mhs-grid {
    display: grid; grid-template-columns: repeat(auto-fit, minmax(350px, 1fr)); gap: 1.5rem;
}
.kp-list-modern-container .mhs-card {
    background: #fff; border-radius: var(--border-radius);
    border: 1px solid var(--border-color); box-shadow: var(--card-shadow);
    display: flex; flex-direction: column; transition: all 0.3s ease;
}
.kp-list-modern-container .mhs-card:hover { transform: translateY(-5px); box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1), 0 4px 6px -2px rgba(0,0,0,0.05); }

/* Header Kartu */
.kp-list-modern-container .mhs-card-header { display: flex; justify-content: space-between; align-items: center; padding: 1rem 1.5rem; border-bottom: 1px solid var(--border-color); }
.kp-list-modern-container .user-profile { display: flex; align-items: center; gap: 1rem; }
.kp-list-modern-container .user-avatar { width: 40px; height: 40px; border-radius: 50%; background-color: var(--primary-color); color: white; display: flex; align-items: center; justify-content: center; font-weight: 600; font-size: 1.2rem; }
.kp-list-modern-container .user-info h4 { margin: 0; font-size: 1.1rem; }
.kp-list-modern-container .user-info span { font-size: 0.9rem; color: var(--text-secondary); }
.kp-list-modern-container .status-badge { padding: 0.25rem 0.75rem; border-radius: 999px; font-size: 0.8rem; font-weight: 500; color: #fff; }
.status-diterima-perusahaan { background-color: var(--info-color); }
.status-kp_berjalan { background-color: var(--success-color); }
.status-selesai_pelaksanaan { background-color: var(--warning-color); color: var(--text-primary);}
.status-selesai_dinilai { background-color: var(--text-secondary); }

/* Body Kartu */
.kp-list-modern-container .mhs-card-body { padding: 1.5rem; flex-grow: 1; }
.kp-list-modern-container .judul-kp { font-weight: 600; font-size: 1.1rem; margin-top: 0; margin-bottom: 1.5rem; line-height: 1.4; }
.kp-list-modern-container .detail-grid { display: grid; gap: 1rem; }
.kp-list-modern-container .detail-item span { display: block; font-size: 0.85rem; color: var(--text-secondary); margin-bottom: 2px; }
.kp-list-modern-container .detail-item strong { font-weight: 500; }

/* Footer Kartu */
.kp-list-modern-container .mhs-card-footer { padding: 1rem 1.5rem; background: var(--bg-light); border-top: 1px solid var(--border-color); border-bottom-left-radius: var(--border-radius); border-bottom-right-radius: var(--border-radius); }
.kp-list-modern-container .btn-aksi {
    display: flex; align-items: center; justify-content: center; gap: 0.5rem;
    width: 100%; padding: 0.75rem; border-radius: 8px; text-decoration: none;
    font-weight: 600; transition: all 0.2s ease;
}
.kp-list-modern-container .btn-aksi svg { width: 16px; height: 16px; }
.kp-list-modern-container .btn-aksi.input { background: var(--primary-color); color: white; }
.kp-list-modern-container .btn-aksi.input:hover { background: #4338ca; }
.kp-list-modern-container .btn-aksi.edit { background: var(--bg-light); color: var(--text-primary); border: 1px solid var(--border-color); }
.kp-list-modern-container .btn-aksi.edit:hover { background-color: #e5e7eb; }
.kp-list-modern-container .btn-aksi.disabled { background: var(--border-color); color: var(--text-secondary); cursor: not-allowed; }

/* Animasi */
.kp-list-modern-container .animate-on-scroll {
    opacity: 0; transform: translateY(20px); transition: opacity 0.5s ease-out, transform 0.5s ease-out;
}
.kp-list-modern-container .animate-on-scroll.is-visible { opacity: 1; transform: translateY(0); }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const container = document.querySelector('.kp-list-modern-container');
    if (!container) return;

    // Animasi saat scroll
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
if (isset($conn)) { $conn->close(); }
?>