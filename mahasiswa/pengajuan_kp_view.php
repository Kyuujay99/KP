<?php
// /KP/mahasiswa/pengajuan_kp_view.php (Versi Modern & Ditingkatkan)

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// 1. OTENTIKASI DAN OTORISASI
require_once '../includes/auth_check.php';
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'mahasiswa') {
    session_unset();
    session_destroy();
    header("Location: /KP/index.php?error=unauthorized");
    exit();
}

$nim_mahasiswa = $_SESSION['user_id'];

// Sertakan file koneksi database
require_once '../config/db_connect.php';

$list_pengajuan = []; // Array untuk menyimpan daftar pengajuan KP
$error_db = '';

// 2. AMBIL DATA PENGAJUAN KP DARI DATABASE UNTUK MAHASISWA YANG LOGIN
if ($conn && ($conn instanceof mysqli)) {
    // Query untuk mengambil data pengajuan KP, di-join dengan tabel lain untuk mendapatkan detail
    $sql = "SELECT 
                pk.id_pengajuan, 
                pk.judul_kp, 
                pk.deskripsi_kp, 
                p.nama_perusahaan, 
                pk.tanggal_pengajuan, 
                pk.tanggal_mulai_rencana, 
                pk.tanggal_selesai_rencana, 
                pk.status_pengajuan,
                pk.catatan_admin,
                pk.catatan_dosen
            FROM pengajuan_kp pk
            LEFT JOIN perusahaan p ON pk.id_perusahaan = p.id_perusahaan
            WHERE pk.nim = ?
            ORDER BY pk.tanggal_pengajuan DESC, pk.id_pengajuan DESC";

    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("s", $nim_mahasiswa);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $list_pengajuan[] = $row;
            }
        }
        $stmt->close();
    } else {
        $error_db = "Gagal menyiapkan query untuk mengambil data pengajuan: " . htmlspecialchars($conn->error);
    }
} else {
    $error_db = "Koneksi database gagal atau tidak valid.";
}

// Set judul halaman dan sertakan header
$page_title = "Riwayat Pengajuan Kerja Praktek";
require_once '../includes/header.php';
?>

<!-- KONTENER BARU UNTUK TAMPILAN MODERN -->
<div class="kp-view-container">

    <div class="view-hero-section">
        <div class="view-hero-content">
            <div class="view-hero-icon">
                <svg viewBox="0 0 24 24"><path d="M13 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"></path><polyline points="13 2 13 9 20 9"></polyline></svg>
            </div>
            <h1><?php echo htmlspecialchars($page_title); ?></h1>
            <p>Daftar semua pengajuan Kerja Praktek yang telah Anda buat, beserta status dan catatan terbaru dari dosen atau admin.</p>
            <a href="/KP/mahasiswa/pengajuan_kp_form.php" class="btn-hero-action">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                Buat Pengajuan Baru
            </a>
        </div>
    </div>

    <div class="view-wrapper">
        <?php if (!empty($error_db)): ?>
            <div class="message error"><p><?php echo $error_db; ?></p></div>
        <?php endif; ?>

        <?php if (empty($list_pengajuan) && empty($error_db)): ?>
            <div class="message info animate-on-scroll">
                <h4>Anda Belum Memiliki Pengajuan</h4>
                <p>Saatnya memulai perjalanan Kerja Praktek Anda. Klik tombol di atas untuk membuat pengajuan baru.</p>
            </div>
        <?php else: ?>
            <div class="pengajuan-grid">
                <?php foreach ($list_pengajuan as $pengajuan): ?>
                    <div class="pengajuan-card animate-on-scroll">
                        <div class="card-header">
                            <h3 title="<?php echo htmlspecialchars($pengajuan['judul_kp']); ?>"><?php echo htmlspecialchars($pengajuan['judul_kp']); ?></h3>
                            <span class="status-badge status-<?php echo strtolower(str_replace('_', '-', $pengajuan['status_pengajuan'])); ?>">
                                <?php echo ucfirst(str_replace('_', ' ', htmlspecialchars($pengajuan['status_pengajuan']))); ?>
                            </span>
                        </div>
                        <div class="card-body">
                            <div class="detail-item">
                                <span>Perusahaan Tujuan</span>
                                <strong><?php echo $pengajuan['nama_perusahaan'] ? htmlspecialchars($pengajuan['nama_perusahaan']) : '<em>Diajukan Manual</em>'; ?></strong>
                            </div>
                            <div class="detail-item">
                                <span>Rencana Periode</span>
                                <strong><?php echo date("d M Y", strtotime($pengajuan['tanggal_mulai_rencana'])); ?> - <?php echo date("d M Y", strtotime($pengajuan['tanggal_selesai_rencana'])); ?></strong>
                            </div>
                            
                            <?php if(!empty($pengajuan['catatan_dosen']) || !empty($pengajuan['catatan_admin'])): ?>
                            <div class="catatan-section">
                                <?php if(!empty($pengajuan['catatan_dosen'])): ?>
                                <div class="catatan catatan-dosen">
                                    <strong>Catatan Dosen:</strong>
                                    <p><?php echo nl2br(htmlspecialchars($pengajuan['catatan_dosen'])); ?></p>
                                </div>
                                <?php endif; ?>
                                <?php if(!empty($pengajuan['catatan_admin'])): ?>
                                <div class="catatan catatan-admin">
                                    <strong>Catatan Admin:</strong>
                                    <p><?php echo nl2br(htmlspecialchars($pengajuan['catatan_admin'])); ?></p>
                                </div>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        <div class="card-footer">
                            <span class="footer-info">Diajukan: <?php echo date("d F Y", strtotime($pengajuan['tanggal_pengajuan'])); ?></span>
                            <a href="pengajuan_kp_detail.php?id=<?php echo $pengajuan['id_pengajuan']; ?>" class="btn-detail">Lihat Detail</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
/* GAYA BARU YANG TERISOLASI DALAM KONTENER */
.kp-view-container {
    --primary-color: #667eea;
    --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    --text-primary: #1f2937; --text-secondary: #6b7280;
    --bg-light: #f9fafb; --border-color: #e5e7eb;
    --card-shadow: 0 4px 6px -1px rgba(0,0,0,0.1), 0 2px 4px -1px rgba(0,0,0,0.06);
    --border-radius: 16px;
    font-family: 'Inter', sans-serif;
    color: var(--text-primary);
    max-width: 1400px; margin: 0 auto; padding: 2rem;
}
.kp-view-container svg { stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; fill: none; stroke: currentColor; }
.kp-view-container .view-hero-section {
    padding: 3rem 2rem; background: var(--primary-gradient);
    border-radius: var(--border-radius); margin-bottom: 2rem;
    color: white; text-align: center;
}
.kp-view-container .view-hero-content { max-width: 700px; margin: 0 auto; }
.kp-view-container .view-hero-icon {
    width: 60px; height: 60px; background: rgba(255,255,255,0.1);
    border-radius: 50%; display: flex; align-items: center; justify-content: center;
    margin: 0 auto 1.5rem;
}
.kp-view-container .view-hero-icon svg { width: 28px; height: 28px; stroke: white; }
.kp-view-container .view-hero-section h1 { font-size: 2.5rem; font-weight: 700; margin-bottom: 0.5rem; }
.kp-view-container .view-hero-section p { font-size: 1.1rem; opacity: 0.9; font-weight: 300; margin-bottom: 1.5rem; }
.kp-view-container .btn-hero-action {
    display: inline-flex; align-items: center; gap: 0.5rem;
    padding: 0.75rem 1.5rem; background: #fff; color: var(--primary-color);
    text-decoration: none; font-weight: 600; border-radius: 50px;
    transition: all 0.3s ease;
}
.kp-view-container .btn-hero-action:hover {
    background: #f0f2ff; transform: translateY(-2px);
    box-shadow: 0 4px 20px rgba(0,0,0,0.1);
}
.kp-view-container .view-wrapper { background-color: #fff; padding: 2.5rem; border-radius: var(--border-radius); box-shadow: var(--card-shadow); }
.kp-view-container .message { padding: 1.5rem; border-radius: var(--border-radius); text-align: center; }
.kp-view-container .message.info { background-color: #eff6ff; color: #1e40af; }
.kp-view-container .message h4 { margin-top: 0; font-size: 1.2rem; }

.pengajuan-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(350px, 1fr)); gap: 1.5rem; }
.pengajuan-card {
    background: #fff; border-radius: var(--border-radius);
    border: 1px solid var(--border-color); box-shadow: var(--card-shadow);
    display: flex; flex-direction: column; transition: all 0.3s ease;
}
.pengajuan-card:hover { transform: translateY(-5px); box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1), 0 4px 6px -2px rgba(0,0,0,0.05); }
.pengajuan-card .card-header { padding: 1rem 1.5rem; border-bottom: 1px solid var(--border-color); }
.pengajuan-card .card-header h3 { font-size: 1.2rem; margin: 0 0 0.5rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.status-badge { padding: .25rem .75rem; border-radius: 999px; font-size: .8rem; font-weight: 600; text-transform: capitalize; }
.status-draft { background-color: #e5e7eb; color: #4b5563; }
.status-diajukan-mahasiswa { background-color: #fef3c7; color: #92400e; }
.status-diverifikasi-dospem { background-color: #fed7aa; color: #9a3412; }
.status-disetujui-dospem { background-color: #a7f3d0; color: #065f46; }
.status-ditolak-dospem, .status-ditolak-perusahaan { background-color: #fecaca; color: #991b1b; }
.status-menunggu-konfirmasi-perusahaan { background-color: #c7d2fe; color: #4338ca; }
.status-diterima-perusahaan, .status-selesai-pelaksanaan { background-color: #bbf7d0; color: #15803d; }
.status-kp-berjalan { background-color: #93c5fd; color: #1e40af; }
.status-laporan-disetujui { background-color: #fbcfe8; color: #9d2667; }
.status-selesai-dinilai { background-color: #374151; color: #f9fafb; }
.status-dibatalkan { background-color: #d1d5db; color: #4b5563; }

.pengajuan-card .card-body { padding: 1.5rem; flex-grow: 1; }
.pengajuan-card .detail-item { margin-bottom: 1rem; }
.pengajuan-card .detail-item span { display: block; font-size: .85rem; color: var(--text-secondary); margin-bottom: 2px; }
.pengajuan-card .detail-item strong { font-weight: 500; }
.catatan-section { margin-top: 1.5rem; padding-top: 1rem; border-top: 1px dashed var(--border-color); }
.catatan { padding: 1rem; margin-top: 1rem; border-radius: 8px; border-left: 4px solid; }
.catatan.catatan-dosen { background-color: #e0f2fe; border-color: #38bdf8; }
.catatan.catatan-admin { background-color: #fef3c7; border-color: #facc15; }
.catatan strong { display: block; margin-bottom: .5rem; font-weight: 600; }
.catatan p { margin: 0; line-height: 1.6; font-size: 0.9rem; }

.pengajuan-card .card-footer {
    display: flex; justify-content: space-between; align-items: center;
    padding: 1rem 1.5rem; background: var(--bg-light);
    border-top: 1px solid var(--border-color); border-bottom-left-radius: var(--border-radius); border-bottom-right-radius: var(--border-radius);
}
.pengajuan-card .footer-info { font-size: .85rem; color: var(--text-secondary); }
.pengajuan-card .btn-detail {
    text-decoration: none; padding: .5rem 1rem; border-radius: 8px;
    font-weight: 600; background: var(--primary-color); color: #fff;
    transition: all 0.2s ease;
}
.pengajuan-card .btn-detail:hover { background: #4338ca; }

.animate-on-scroll {
    opacity: 0; transform: translateY(20px);
    transition: opacity .5s ease-out, transform .5s ease-out;
}
.animate-on-scroll.is-visible { opacity: 1; transform: translateY(0); }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const container = document.querySelector('.kp-view-container');
    if (!container) return;
    const animatedElements = container.querySelectorAll('.animate-on-scroll');
    const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry, index) => {
            if (entry.isIntersecting) {
                entry.target.style.transitionDelay = `${index * 70}ms`;
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
if (isset($conn) && ($conn instanceof mysqli)) {
    $conn->close();
}
?>
