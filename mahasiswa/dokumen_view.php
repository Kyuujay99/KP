<?php
// /KP/mahasiswa/dokumen_view.php (Versi Modern & Terisolasi)

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once '../includes/auth_check.php';
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'mahasiswa') {
    header("Location: /KP/index.php?error=unauthorized");
    exit();
}

$nim_mahasiswa = $_SESSION['user_id'];
require_once '../config/db_connect.php';

$list_dokumen = [];
$error_db = '';

if ($conn) {
    $sql = "SELECT
                dk.id_dokumen, dk.id_pengajuan, dk.nama_dokumen, dk.jenis_dokumen,
                dk.file_path, dk.tanggal_upload, dk.status_verifikasi_dokumen,
                dk.catatan_verifikator, pk.judul_kp
            FROM dokumen_kp dk
            JOIN pengajuan_kp pk ON dk.id_pengajuan = pk.id_pengajuan
            WHERE pk.nim = ? 
            ORDER BY dk.tanggal_upload DESC";

    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("s", $nim_mahasiswa);
        $stmt->execute();
        $result = $stmt->get_result();
        $list_dokumen = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    } else {
        $error_db = "Gagal mengambil data dokumen: " . $conn->error;
    }
} else {
    $error_db = "Koneksi database gagal.";
}

$page_title = "Dokumen Saya";
require_once '../includes/header.php';
?>

<div class="kp-dokumen-modern-container">

    <div class="list-hero-section">
        <div class="list-hero-content">
            <div class="list-hero-icon">
                <svg viewBox="0 0 24 24"><path d="M13 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"></path><polyline points="13 2 13 9 20 9"></polyline></svg>
            </div>
            <h1><?php echo htmlspecialchars($page_title); ?></h1>
            <p>Riwayat semua dokumen yang telah Anda unggah ke sistem untuk berbagai keperluan Kerja Praktek.</p>
        </div>
    </div>

    <div class="list-wrapper">
        <?php if (!empty($error_db)): ?>
            <div class="message error"><p><?php echo $error_db; ?></p></div>
        <?php endif; ?>

        <?php if (empty($list_dokumen) && empty($error_db)): ?>
            <div class="message info">
                <h4>Belum Ada Dokumen</h4>
                <p>Anda dapat mengunggah dokumen melalui halaman detail pengajuan KP Anda.</p>
            </div>
        <?php else: ?>
            <div class="dokumen-grid">
                <?php foreach ($list_dokumen as $doc): ?>
                    <div class="dokumen-card animate-on-scroll">
                        <div class="dokumen-icon-header">
                            <?php
                                $file_ext = strtolower(pathinfo($doc['file_path'], PATHINFO_EXTENSION));
                                $icon_svg = '<svg viewBox="0 0 24 24"><path d="M13 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"></path><polyline points="13 2 13 9 20 9"></polyline></svg>'; // default
                                $icon_class = 'file-other';
                                if (in_array($file_ext, ['pdf'])) {
                                    $icon_svg = '<svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><path d="M10 15.5v-5a2 2 0 0 1 2-2h2"></path><path d="M10 12.5a2 2 0 0 1 2-2h1a2 2 0 0 1 2 2v1a2 2 0 0 1-2 2h-1a2 2 0 0 1-2-2v-1z"></path></svg>';
                                    $icon_class = 'file-pdf';
                                } elseif (in_array($file_ext, ['doc', 'docx'])) {
                                    $icon_svg = '<svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><path d="M12 18v-6"></path><path d="M12 18l-2-2"></path><path d="M12 18l2-2"></path><path d="M12 18H8"></path><path d="M12 18h4"></path></svg>';
                                    $icon_class = 'file-doc';
                                } elseif (in_array($file_ext, ['jpg', 'jpeg', 'png', 'gif'])) {
                                    $icon_svg = '<svg viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><circle cx="8.5" cy="8.5" r="1.5"></circle><polyline points="21 15 16 10 5 21"></polyline></svg>';
                                    $icon_class = 'file-img';
                                }
                            ?>
                            <div class="dokumen-icon <?php echo $icon_class; ?>"><?php echo $icon_svg; ?></div>
                        </div>
                        <div class="dokumen-body">
                            <h4 title="<?php echo htmlspecialchars($doc['nama_dokumen']); ?>"><?php echo htmlspecialchars($doc['nama_dokumen']); ?></h4>
                            <p class="jenis-dokumen"><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $doc['jenis_dokumen']))); ?></p>
                            <div class="info-terkait">
                                <strong>Terkait KP:</strong>
                                <a href="pengajuan_kp_detail.php?id=<?php echo $doc['id_pengajuan']; ?>" title="Lihat Detail KP Terkait">
                                    <?php echo htmlspecialchars($doc['judul_kp']); ?>
                                </a>
                            </div>
                        </div>
                        <div class="dokumen-footer">
                            <div class="status-verifikasi">
                                <span class="status-badge status-dokumen-<?php echo strtolower(htmlspecialchars($doc['status_verifikasi_dokumen'])); ?>">
                                    <?php echo ucfirst(str_replace('_', ' ', htmlspecialchars($doc['status_verifikasi_dokumen']))); ?>
                                </span>
                                <?php if (!empty($doc['catatan_verifikator'])): ?>
                                    <i class="info-icon" title="<?php echo htmlspecialchars($doc['catatan_verifikator']); ?>">
                                        <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>
                                    </i>
                                <?php endif; ?>
                            </div>
                            <?php if (!empty($doc['file_path'])): ?>
                                <a href="/KP/<?php echo htmlspecialchars(str_replace('../', '', $doc['file_path'])); ?>" class="btn-aksi" target="_blank">
                                    Lihat File
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
body {
    background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
    background-attachment: fixed;
}

.page-hero {
    padding: 3rem 2rem 6rem;
    color: white;
    text-align: center;
}

.page-hero h1 {
    font-size: 2.5rem;
    font-weight: 700;
}

.page-content-wrapper {
    max-width: 1400px;
    margin: 0 auto;
    padding: 2rem;
    margin-top: -5rem; /* Kunci utama untuk menarik konten ke atas */
    position: relative;
    z-index: 10;
}
/* Sedikit penyesuaian agar tidak ada container ganda */
.kp-dokumen-modern-container {
    padding: 0; /* Hapus padding dari container asli */
}
.list-wrapper {
    background-color: #ffffff;
    padding: 2.5rem;
    border-radius: 16px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.1);
}
/* GAYA BARU YANG TERISOLASI DALAM KONTENER */
.kp-dokumen-modern-container {
    --primary-color: #667eea;
    --success-color: #34d399; --warning-color: #fbbf24;
    --danger-color: #f87171; --info-color: #60a5fa;
    --text-primary: #1f2937; --text-secondary: #6b7280;
    --bg-light: #f9fafb; --border-color: #e5e7eb;
    --card-shadow: 0 4px 6px -1px rgba(0,0,0,0.1), 0 2px 4px -1px rgba(0,0,0,0.06);
    --border-radius: 12px;
    font-family: 'Inter', sans-serif; color: var(--text-primary);
    max-width: 1400px; margin: 0 auto; padding: 2rem;
}
.kp-dokumen-modern-container svg {
    stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; fill: none; stroke: currentColor;
}
.kp-dokumen-modern-container .list-hero-section {
    padding: 3rem 2rem; background: linear-gradient(135deg, #1f2937 0%, #4b5563 100%);
    border-radius: var(--border-radius); margin-bottom: 2rem; color: white; text-align: center;
}
.kp-dokumen-modern-container .list-hero-content { max-width: 700px; margin: 0 auto; }
.kp-dokumen-modern-container .list-hero-icon {
    width: 60px; height: 60px; background: rgba(255,255,255,.1);
    border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1.5rem;
}
.kp-dokumen-modern-container .list-hero-icon svg { width: 28px; height: 28px; stroke: white; }
.kp-dokumen-modern-container .list-hero-section h1 { font-size: 2.5rem; font-weight: 700; margin-bottom: .5rem; }
.kp-dokumen-modern-container .list-hero-section p { font-size: 1.1rem; opacity: .9; font-weight: 300; }
.kp-dokumen-modern-container .list-wrapper { background-color: #fff; padding: 2rem; border-radius: var(--border-radius); box-shadow: var(--card-shadow); }
.kp-dokumen-modern-container .message { padding: 1.5rem; border-radius: var(--border-radius); text-align: center; }
.kp-dokumen-modern-container .message.info { background-color: #eff6ff; color: #1e40af; }
.kp-dokumen-modern-container .message h4 { margin-top: 0; font-size: 1.2rem; }

/* Grid Kartu Dokumen */
.kp-dokumen-modern-container .dokumen-grid {
    display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 1.5rem;
}
.kp-dokumen-modern-container .dokumen-card {
    background: #fff; border-radius: var(--border-radius);
    border: 1px solid var(--border-color); box-shadow: var(--card-shadow);
    display: flex; flex-direction: column; transition: all 0.3s ease;
}
.kp-dokumen-modern-container .dokumen-card:hover { transform: translateY(-5px); box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1), 0 4px 6px -2px rgba(0,0,0,0.05); }
.dokumen-icon-header { padding: 1.5rem; text-align: center; }
.dokumen-icon {
    width: 60px; height: 60px; margin: 0 auto;
    display: flex; align-items: center; justify-content: center;
    border-radius: 12px; color: #fff;
}
.dokumen-icon svg { width: 32px; height: 32px; }
.file-pdf { background-color: #ef4444; }
.file-doc { background-color: #3b82f6; }
.file-img { background-color: #10b981; }
.file-other { background-color: #6b7280; }

.dokumen-body { padding: 0 1.5rem 1rem; text-align: center; flex-grow: 1; }
.dokumen-body h4 { font-size: 1.1rem; margin: 0 0 .25rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.dokumen-body .jenis-dokumen { font-size: .9rem; color: var(--text-secondary); margin-bottom: 1rem; }
.info-terkait {
    font-size: .9rem; background: var(--bg-light); padding: .75rem; border-radius: 8px;
}
.info-terkait strong { color: var(--text-primary); }
.info-terkait a {
    display: block; color: var(--primary-color); font-weight: 500;
    text-decoration: none; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.info-terkait a:hover { text-decoration: underline; }

.dokumen-footer {
    display: flex; justify-content: space-between; align-items: center;
    padding: 1rem 1.5rem; background-color: var(--bg-light);
    border-top: 1px solid var(--border-color);
    border-bottom-left-radius: var(--border-radius); border-bottom-right-radius: var(--border-radius);
}
.status-verifikasi { display: flex; align-items: center; gap: .5rem; }
.status-badge { padding: .25rem .75rem; border-radius: 999px; font-size: .8rem; font-weight: 600; }
.status-dokumen-pending { background-color: var(--warning-color); color: var(--text-primary); }
.status-dokumen-disetujui { background-color: var(--success-color); color: #fff; }
.status-dokumen-revisi-diperlukan { background-color: #fb923c; color: #fff; }
.status-dokumen-ditolak { background-color: var(--danger-color); color: #fff; }
.info-icon { cursor: help; color: var(--text-secondary); }
.info-icon svg { width: 16px; height: 16px; }

.btn-aksi {
    padding: .5rem 1rem; border-radius: 8px; text-decoration: none;
    font-weight: 600; background-color: var(--primary-color); color: #fff;
    border: 1px solid var(--primary-color); transition: all .2s ease;
}
.btn-aksi:hover { background-color: #4338ca; border-color: #4338ca; }

/* Animasi */
.kp-dokumen-modern-container .animate-on-scroll {
    opacity: 0; transform: translateY(20px); transition: opacity .5s ease-out,transform .5s ease-out;
}
.kp-dokumen-modern-container .animate-on-scroll.is-visible { opacity: 1; transform: translateY(0); }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const container = document.querySelector('.kp-dokumen-modern-container');
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
if (isset($conn)) { $conn->close(); }
?>