<?php
// /KP/mahasiswa/pengajuan_kp_detail.php (Versi Final & Ditingkatkan)

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
$id_pengajuan_url = null;
$pengajuan_detail = null;
$dokumen_terkait = [];
$error_message = '';

// 2. VALIDASI DAN AMBIL ID PENGAJUAN DARI URL
if (isset($_GET['id']) && filter_var($_GET['id'], FILTER_VALIDATE_INT)) {
    $id_pengajuan_url = (int)$_GET['id'];
} else {
    $error_message = "ID Pengajuan tidak valid atau tidak ditemukan.";
}

// Sertakan file koneksi database
require_once '../config/db_connect.php';

// 3. AMBIL DATA DETAIL PENGAJUAN KP
if ($id_pengajuan_url && empty($error_message) && $conn) {
    // Query untuk mengambil detail pengajuan KP spesifik milik mahasiswa yang login
    $sql_detail = "SELECT
                       pk.id_pengajuan, pk.judul_kp, pk.deskripsi_kp,
                       p.nama_perusahaan,
                       pk.tanggal_pengajuan, pk.tanggal_mulai_rencana, pk.tanggal_selesai_rencana,
                       pk.status_pengajuan, dpk.nama_dosen AS nama_dosen_pembimbing,
                       pk.catatan_admin, pk.catatan_dosen
                   FROM pengajuan_kp pk
                   LEFT JOIN perusahaan p ON pk.id_perusahaan = p.id_perusahaan
                   LEFT JOIN dosen_pembimbing dpk ON pk.nip_dosen_pembimbing_kp = dpk.nip
                   WHERE pk.id_pengajuan = ? AND pk.nim = ?";

    $stmt_detail = $conn->prepare($sql_detail);
    if ($stmt_detail) {
        $stmt_detail->bind_param("is", $id_pengajuan_url, $nim_mahasiswa);
        $stmt_detail->execute();
        $result_detail = $stmt_detail->get_result();
        if ($result_detail->num_rows === 1) {
            $pengajuan_detail = $result_detail->fetch_assoc();

            // Ambil juga dokumen terkait pengajuan ini
            $sql_dokumen = "SELECT id_dokumen, nama_dokumen, jenis_dokumen, file_path, tanggal_upload, status_verifikasi_dokumen, catatan_verifikator
                            FROM dokumen_kp
                            WHERE id_pengajuan = ?
                            ORDER BY tanggal_upload DESC";
            $stmt_dokumen = $conn->prepare($sql_dokumen);
            if ($stmt_dokumen) {
                $stmt_dokumen->bind_param("i", $id_pengajuan_url);
                $stmt_dokumen->execute();
                $result_dokumen = $stmt_dokumen->get_result();
                $dokumen_terkait = $result_dokumen->fetch_all(MYSQLI_ASSOC);
                $stmt_dokumen->close();
            } else {
                $error_message .= " Gagal mengambil daftar dokumen.";
            }
        } else {
            $error_message = "Detail pengajuan KP tidak ditemukan atau Anda tidak memiliki akses.";
        }
        $stmt_detail->close();
    } else {
        $error_message = "Gagal menyiapkan query detail pengajuan.";
    }
}

// Set judul halaman dan sertakan header
$page_title = "Detail Pengajuan Kerja Praktek";
if ($pengajuan_detail) {
    $page_title = "Detail: " . htmlspecialchars($pengajuan_detail['judul_kp']);
}
require_once '../includes/header.php';
?>

<!-- KONTENER BARU UNTUK TAMPILAN MODERN -->
<div class="kp-detail-container">
    <div class="detail-hero-section">
        <div class="detail-hero-content">
            <div class="detail-hero-icon">
                <svg viewBox="0 0 24 24"><path d="M13 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"></path><polyline points="13 2 13 9 20 9"></polyline></svg>
            </div>
            <h1><?php echo htmlspecialchars($page_title); ?></h1>
            <p>Rincian lengkap pengajuan Kerja Praktek Anda, termasuk status, catatan, dan dokumen terkait.</p>
        </div>
    </div>
    
    <div class="detail-wrapper">
        <a href="pengajuan_kp_view.php" class="back-link">&larr; Kembali ke Riwayat Pengajuan</a>
        
        <?php if (!empty($error_message)): ?>
            <div class="message error animate-on-scroll"><p><?php echo $error_message; ?></p></div>
        <?php elseif ($pengajuan_detail): ?>
            <div class="detail-main-grid">
                <!-- Kolom Kiri: Detail Utama -->
                <div class="detail-content">
                    <div class="info-card animate-on-scroll">
                        <div class="info-card-header">
                            <svg viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-6h2v6zm0-8h-2V7h2v2z"></path></svg>
                            <h3>Informasi Pengajuan</h3>
                        </div>
                        <div class="info-card-body">
                            <div class="info-item"><span>Status Saat Ini</span><strong class="status-badge status-<?php echo strtolower(str_replace('_', '-', $pengajuan_detail['status_pengajuan'])); ?>"><?php echo ucfirst(str_replace('_', ' ', htmlspecialchars($pengajuan_detail['status_pengajuan']))); ?></strong></div>
                            <div class="info-item"><span>Perusahaan Tujuan</span><strong><?php echo $pengajuan_detail['nama_perusahaan'] ? htmlspecialchars($pengajuan_detail['nama_perusahaan']) : '<em>Diajukan Manual</em>'; ?></strong></div>
                            <div class="info-item"><span>Periode Rencana</span><strong><?php echo date("d M Y", strtotime($pengajuan_detail['tanggal_mulai_rencana'])); ?> - <?php echo date("d M Y", strtotime($pengajuan_detail['tanggal_selesai_rencana'])); ?></strong></div>
                            <div class="info-item wide"><span>Deskripsi</span><p><?php echo nl2br(htmlspecialchars($pengajuan_detail['deskripsi_kp'])); ?></p></div>
                        </div>
                    </div>

                    <div class="info-card animate-on-scroll">
                        <div class="info-card-header">
                            <svg viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>
                            <h3>Catatan & Pembimbing</h3>
                        </div>
                        <div class="info-card-body">
                            <div class="info-item"><span>Dosen Pembimbing</span><strong><?php echo $pengajuan_detail['nama_dosen_pembimbing'] ? htmlspecialchars($pengajuan_detail['nama_dosen_pembimbing']) : '<em>Belum Ditentukan</em>'; ?></strong></div>
                            <?php if(!empty($pengajuan_detail['catatan_dosen'])): ?>
                                <div class="catatan catatan-dosen"><strong>Catatan Dosen:</strong><p><?php echo nl2br(htmlspecialchars($pengajuan_detail['catatan_dosen'])); ?></p></div>
                            <?php endif; ?>
                            <?php if(!empty($pengajuan_detail['catatan_admin'])): ?>
                                <div class="catatan catatan-admin"><strong>Catatan Admin:</strong><p><?php echo nl2br(htmlspecialchars($pengajuan_detail['catatan_admin'])); ?></p></div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Kolom Kanan: Dokumen Terkait -->
                <div class="detail-sidebar">
                    <div class="info-card animate-on-scroll">
                        <div class="info-card-header">
                            <svg viewBox="0 0 24 24"><path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"></path></svg>
                            <h3>Dokumen Terkait</h3>
                        </div>
                        <div class="info-card-body">
                            <?php if (!empty($dokumen_terkait)): ?>
                                <ul class="dokumen-list">
                                    <?php foreach ($dokumen_terkait as $dokumen): ?>
                                        <li>
                                            <div class="dok-icon"><svg viewBox="0 0 24 24"><path d="M13 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"></path><polyline points="13 2 13 9 20 9"></polyline></svg></div>
                                            <div class="dok-info">
                                                <strong><?php echo htmlspecialchars($dokumen['nama_dokumen']); ?></strong>
                                                <span>Diupload: <?php echo date("d M Y", strtotime($dokumen['tanggal_upload'])); ?></span>
                                                <span class="status-dokumen status-dokumen-<?php echo strtolower(htmlspecialchars($dokumen['status_verifikasi_dokumen'])); ?>">
                                                    <?php echo ucfirst(str_replace('_', ' ', htmlspecialchars($dokumen['status_verifikasi_dokumen']))); ?>
                                                </span>
                                            </div>
                                            <a href="/KP/<?php echo htmlspecialchars(ltrim($dokumen['file_path'], '.')); ?>" target="_blank" class="btn-unduh" title="Lihat/Unduh Dokumen">
                                                <svg viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
                                            </a>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php else: ?>
                                <p class="text-muted">Belum ada dokumen yang diunggah.</p>
                            <?php endif; ?>
                        </div>
                        <div class="card-footer-action">
                            <a href="dokumen_upload.php?id_pengajuan=<?php echo $id_pengajuan_url; ?>" class="btn-upload">
                                <svg viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="17 8 12 3 7 8"></polyline><line x1="12" y1="3" x2="12" y2="15"></line></svg>
                                Upload Dokumen Baru
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
/* GAYA BARU YANG TERISOLASI DALAM KONTENER */
.kp-detail-container { --primary-gradient:linear-gradient(135deg,#667eea 0%,#764ba2 100%); --text-primary:#1f2937; --text-secondary:#6b7280; --bg-light:#f9fafb; --border-color:#e5e7eb; --card-shadow:0 10px 30px rgba(0,0,0,.07); --border-radius:16px; font-family:Inter,sans-serif; color:var(--text-primary); max-width:1400px; margin:0 auto; padding:2rem 1rem }
.kp-detail-container svg { stroke-width:2; stroke-linecap:round; stroke-linejoin:round; fill:none; stroke:currentColor }
.kp-detail-container .detail-hero-section { padding:3rem 2rem; background:var(--primary-gradient); border-radius:var(--border-radius); margin-bottom:2rem; color:#fff; text-align:center }
.kp-detail-container .detail-hero-content { max-width:700px; margin:0 auto }
.kp-detail-container .detail-hero-icon { width:60px; height:60px; background:rgba(255,255,255,.1); border-radius:50%; display:flex; align-items:center; justify-content:center; margin:0 auto 1.5rem }
.kp-detail-container .detail-hero-icon svg { width:28px; height:28px; stroke:#fff }
.kp-detail-container .detail-hero-section h1 { font-size:2.5rem; font-weight:700; margin-bottom:.5rem }
.kp-detail-container .detail-hero-section p { font-size:1.1rem; opacity:.9; font-weight:300 }
.kp-detail-container .detail-wrapper { background-color:#fff; padding:2.5rem; border-radius:var(--border-radius); box-shadow:var(--card-shadow) }
.kp-detail-container .back-link { text-decoration:none; color:var(--text-secondary); font-weight:500; display:inline-block; margin-bottom:2rem; transition:color .2s ease }
.kp-detail-container .back-link:hover { color:var(--text-primary) }
.kp-detail-container .message.error { background-color:#fee2e2; color:#991b1b; padding:1.5rem; border-radius:var(--border-radius) }

.detail-main-grid { display:grid; grid-template-columns:2fr 1fr; gap:2rem }
.info-card { background:var(--bg-light); border:1px solid var(--border-color); border-radius:var(--border-radius); overflow:hidden; margin-bottom:2rem }
.info-card-header { display:flex; align-items:center; gap:12px; padding:1rem 1.5rem; border-bottom:1px solid var(--border-color) }
.info-card-header svg { width:20px; height:20px; color:#667eea }
.info-card-header h3 { margin:0; font-size:1.2rem; font-weight:600 }
.info-card-body { padding:1.5rem }
.info-item { padding:.75rem 0; border-bottom:1px dashed var(--border-color) }
.info-item:last-child { border-bottom:none }
.info-item span { display:block; font-size:.9rem; color:var(--text-secondary); margin-bottom:.25rem }
.info-item strong { font-weight:600; line-height:1.4 }
.info-item p { margin:0; line-height:1.6 }
.info-item.wide { grid-column:1/-1 }

.status-badge { padding:.25rem .75rem; border-radius:999px; font-size:.8rem; font-weight:600; text-transform:capitalize; color:#fff; }
.status-draft { background-color: #e5e7eb; color: #4b5563; } .status-diajukan-mahasiswa { background-color: #fef3c7; color: #92400e; } .status-diverifikasi-dospem { background-color: #fed7aa; color: #9a3412; } .status-disetujui-dospem { background-color: #a7f3d0; color: #065f46; } .status-ditolak-dospem, .status-ditolak-perusahaan { background-color: #fecaca; color: #991b1b; } .status-menunggu-konfirmasi-perusahaan { background-color: #c7d2fe; color: #4338ca; } .status-diterima-perusahaan, .status-selesai-pelaksanaan { background-color: #bbf7d0; color: #15803d; } .status-kp-berjalan { background-color: #93c5fd; color: #1e40af; } .status-laporan-disetujui { background-color: #fbcfe8; color: #9d2667; } .status-selesai-dinilai { background-color: #374151; color: #f9fafb; } .status-dibatalkan { background-color: #d1d5db; color: #4b5563; }

.catatan { padding:1rem; margin-top:1rem; border-radius:8px; border-left:4px solid }
.catatan.catatan-dosen { background-color:#e0f2fe; border-color:#38bdf8 }
.catatan.catatan-admin { background-color:#fef3c7; border-color:#facc15 }
.catatan strong { display:block; margin-bottom:.5rem; font-weight:600 }
.catatan p { margin:0; line-height:1.6; font-size:.9rem }

.dokumen-list { list-style:none; padding:0; margin:0 }
.dokumen-list li { display:flex; align-items:center; gap:1rem; padding:.75rem; border-radius:8px; transition:background-color .2s ease }
.dokumen-list li:hover { background-color:#f3f4f6 }
.dok-icon { width:40px; height:40px; flex-shrink:0; background-color:#e0e7ff; color:#4338ca; border-radius:50%; display:flex; align-items:center; justify-content:center }
.dok-icon svg { width:20px; height:20px }
.dok-info { flex-grow:1 }
.dok-info strong { display:block; font-weight:500 }
.dok-info span { font-size:.85rem; color:var(--text-secondary) }
.status-dokumen { font-weight:600 }
.status-dokumen-pending { color:#f59e0b } .status-dokumen-disetujui { color:#16a34a } .status-dokumen-revisi-diperlukan { color:#f97316 } .status-dokumen-ditolak { color:#ef4444 }
.btn-unduh { color:var(--text-secondary); padding: .5rem; border-radius: 50%; display: flex; align-items: center; justify-content: center; transition: all .2s ease; }
.btn-unduh:hover { color:var(--text-primary); background-color: #e5e7eb; }
.btn-unduh svg { width:20px; height:20px; }
.card-footer-action { padding:1rem 1.5rem; background-color:#f3f4f6; border-top:1px solid var(--border-color); }
.btn-upload { display:flex; align-items:center; justify-content:center; gap:.5rem; width:100%; text-decoration:none; padding:.75rem; border-radius:8px; font-weight:600; background:var(--primary-color); color:#fff; transition:all .2s ease }
.btn-upload:hover { background:#4338ca }

.animate-on-scroll { opacity:0; transform:translateY(30px); transition:opacity .6s ease-out,transform .6s ease-out }
.animate-on-scroll.is-visible { opacity:1; transform:translateY(0) }

@media (max-width: 992px) { .detail-main-grid { grid-template-columns: 1fr; } }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const container = document.querySelector('.kp-detail-container');
    if (!container) return;
    const animatedElements = container.querySelectorAll('.animate-on-scroll');
    const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry, index) => {
            if (entry.isIntersecting) {
                entry.target.style.transitionDelay = `${index * 100}ms`;
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
