<?php
// /KP/mahasiswa/bimbingan_view.php (Versi Modern & Terisolasi)

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// 1. OTENTIKASI DAN OTORISASI
require_once '../includes/auth_check.php';
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'mahasiswa') {
    header("Location: /KP/index.php?error=unauthorized");
    exit();
}

$nim_mahasiswa = $_SESSION['user_id'];
$error_message = '';
$bimbingan_entries_by_kp = []; 

require_once '../config/db_connect.php';

// 2. AMBIL DATA BIMBINGAN (Logika PHP Anda sudah baik dan dipertahankan)
if ($conn) {
    $sql = "SELECT
                bk.id_bimbingan, bk.id_pengajuan, pk.judul_kp, 
                dp.nama_dosen AS nama_pembimbing, bk.tanggal_bimbingan,
                bk.topik_bimbingan, bk.catatan_mahasiswa, bk.catatan_dosen,
                bk.file_lampiran_mahasiswa, bk.file_lampiran_dosen, bk.status_bimbingan
            FROM bimbingan_kp bk
            JOIN pengajuan_kp pk ON bk.id_pengajuan = pk.id_pengajuan
            LEFT JOIN dosen_pembimbing dp ON bk.nip_pembimbing = dp.nip
            WHERE pk.nim = ?
            ORDER BY pk.id_pengajuan DESC, bk.tanggal_bimbingan DESC";

    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("s", $nim_mahasiswa);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $bimbingan_entries_by_kp[$row['id_pengajuan']]['judul_kp'] = $row['judul_kp'];
            $bimbingan_entries_by_kp[$row['id_pengajuan']]['entries'][] = $row;
        }
        $stmt->close();
    } else {
        $error_message = "Gagal menyiapkan query: " . htmlspecialchars($conn->error);
    }
} else {
    $error_message = "Koneksi database gagal.";
}

$page_title = "Riwayat Bimbingan Kerja Praktek";
require_once '../includes/header.php';
?>

<div class="kp-bimbingan-modern-container">

    <div class="list-hero-section">
        <div class="list-hero-content">
            <div class="list-hero-icon">
                <svg viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>
            </div>
            <h1><?php echo htmlspecialchars($page_title); ?></h1>
            <p>Berikut adalah catatan riwayat semua sesi bimbingan Anda dengan Dosen Pembimbing.</p>
        </div>
    </div>

    <div class="list-wrapper">
        <?php if (!empty($error_message)): ?>
            <div class="message error"><p><?php echo $error_message; ?></p></div>
        <?php endif; ?>

        <?php if (empty($bimbingan_entries_by_kp) && empty($error_message)): ?>
            <div class="message info">
                <h4>Belum Ada Riwayat</h4>
                <p>Anda belum memiliki riwayat sesi bimbingan. Sesi bimbingan akan muncul di sini setelah dicatat oleh Dosen Pembimbing Anda.</p>
            </div>
        <?php else: ?>
            <?php foreach ($bimbingan_entries_by_kp as $id_kp => $data_kp): ?>
                <div class="timeline-group">
                    <div class="timeline-group-header">
                        <h2>Untuk KP: <?php echo htmlspecialchars($data_kp['judul_kp']); ?></h2>
                    </div>
                    <div class="timeline">
                        <?php if (empty($data_kp['entries'])): ?>
                            <p>Belum ada entri bimbingan untuk KP ini.</p>
                        <?php else: ?>
                            <?php foreach ($data_kp['entries'] as $sesi): ?>
                                <div class="timeline-item animate-on-scroll">
                                    <div class="timeline-point"></div>
                                    <div class="timeline-content">
                                        <div class="bimbingan-header">
                                            <span class="tanggal"><?php echo date("d F Y, H:i", strtotime($sesi['tanggal_bimbingan'])); ?></span>
                                            <span class="status-badge status-<?php echo strtolower(htmlspecialchars($sesi['status_bimbingan'])); ?>">
                                                <?php echo ucfirst(str_replace('_', ' ', htmlspecialchars($sesi['status_bimbingan']))); ?>
                                            </span>
                                        </div>
                                        <div class="bimbingan-body">
                                            <h4><?php echo htmlspecialchars($sesi['topik_bimbingan'] ?: 'Diskusi Umum'); ?></h4>
                                            <p class="dosen-info">Pembimbing: <strong><?php echo htmlspecialchars($sesi['nama_pembimbing'] ?: 'N/A'); ?></strong></p>
                                            
                                            <?php if (!empty($sesi['catatan_dosen'])): ?>
                                                <div class="catatan dosen">
                                                    <strong>Catatan Dosen:</strong>
                                                    <p><?php echo nl2br(htmlspecialchars($sesi['catatan_dosen'])); ?></p>
                                                    <?php if ($sesi['file_lampiran_dosen']): ?>
                                                        <a href="/KP/<?php echo htmlspecialchars(str_replace('../', '', $sesi['file_lampiran_dosen'])); ?>" target="_blank" class="btn-lampiran">Lihat Lampiran Dosen</a>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <?php if (!empty($sesi['catatan_mahasiswa'])): ?>
                                                <div class="catatan mahasiswa">
                                                    <strong>Catatan Anda:</strong>
                                                    <p><?php echo nl2br(htmlspecialchars($sesi['catatan_mahasiswa'])); ?></p>
                                                    <?php if ($sesi['file_lampiran_mahasiswa']): ?>
                                                        <a href="/KP/<?php echo htmlspecialchars(str_replace('../', '', $sesi['file_lampiran_mahasiswa'])); ?>" target="_blank" class="btn-lampiran">Lihat Lampiran Anda</a>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<style>
/* GAYA BARU YANG TERISOLASI DALAM KONTENER */
.kp-bimbingan-modern-container {
    --primary-color: #667eea;
    --text-primary: #1f2937; --text-secondary: #6b7280;
    --bg-light: #f9fafb; --border-color: #e5e7eb;
    --card-shadow: 0 4px 6px -1px rgba(0,0,0,0.1), 0 2px 4px -1px rgba(0,0,0,0.06);
    --border-radius: 12px;
    font-family: 'Inter', sans-serif; color: var(--text-primary);
    max-width: 1000px; margin: 0 auto; padding: 2rem;
}
.kp-bimbingan-modern-container svg {
    stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; fill: none; stroke: currentColor;
}
.kp-bimbingan-modern-container .list-hero-section {
    padding: 3rem 2rem; background: linear-gradient(135deg, #06b6d4 0%, #3b82f6 100%);
    border-radius: var(--border-radius); margin-bottom: 2rem; color: white; text-align: center;
}
.kp-bimbingan-modern-container .list-hero-content { max-width: 700px; margin: 0 auto; }
.kp-bimbingan-modern-container .list-hero-icon {
    width: 60px; height: 60px; background: rgba(255,255,255,.1);
    border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1.5rem;
}
.kp-bimbingan-modern-container .list-hero-icon svg { width: 28px; height: 28px; stroke: white; }
.kp-bimbingan-modern-container .list-hero-section h1 { font-size: 2.5rem; font-weight: 700; margin-bottom: .5rem; }
.kp-bimbingan-modern-container .list-hero-section p { font-size: 1.1rem; opacity: .9; font-weight: 300; }
.kp-bimbingan-modern-container .list-wrapper { background-color: #fff; padding: 2.5rem; border-radius: var(--border-radius); box-shadow: var(--card-shadow); }
.kp-bimbingan-modern-container .message { padding: 1.5rem; border-radius: var(--border-radius); text-align: center; }
.kp-bimbingan-modern-container .message.info { background-color: #eff6ff; color: #1e40af; }
.kp-bimbingan-modern-container .message h4 { margin-top: 0; font-size: 1.2rem; }

/* Timeline Styles */
.kp-bimbingan-modern-container .timeline-group { margin-bottom: 3rem; }
.kp-bimbingan-modern-container .timeline-group-header {
    margin-bottom: 2rem; padding-bottom: 1rem;
    border-bottom: 2px solid var(--border-color);
}
.kp-bimbingan-modern-container .timeline-group-header h2 { font-size: 1.5rem; color: var(--text-primary); }
.kp-bimbingan-modern-container .timeline { position: relative; padding-left: 2rem; }
.kp-bimbingan-modern-container .timeline::before {
    content: ''; position: absolute; left: 10px; top: 10px; bottom: 10px;
    width: 3px; background-color: var(--border-color); border-radius: 2px;
}
.kp-bimbingan-modern-container .timeline-item { position: relative; margin-bottom: 2rem; }
.kp-bimbingan-modern-container .timeline-point {
    position: absolute; left: -8px; top: 12px;
    width: 18px; height: 18px; border-radius: 50%;
    background-color: var(--primary-color); border: 4px solid #fff;
    box-shadow: 0 0 0 3px var(--border-color);
}
.kp-bimbingan-modern-container .timeline-content {
    background-color: #fff; border: 1px solid var(--border-color);
    border-radius: var(--border-radius); box-shadow: var(--card-shadow);
    overflow: hidden;
}
.kp-bimbingan-modern-container .bimbingan-header {
    display: flex; justify-content: space-between; align-items: center;
    padding: 1rem 1.5rem; background-color: var(--bg-light);
    border-bottom: 1px solid var(--border-color);
}
.kp-bimbingan-modern-container .bimbingan-header .tanggal { font-weight: 600; color: var(--text-primary); }
.kp-bimbingan-modern-container .bimbingan-body { padding: 1.5rem; }
.kp-bimbingan-modern-container .bimbingan-body h4 { margin-top: 0; margin-bottom: 0.5rem; font-size: 1.2rem; }
.kp-bimbingan-modern-container .dosen-info { color: var(--text-secondary); margin-bottom: 1.5rem; }
.kp-bimbingan-modern-container .catatan {
    padding: 1rem; margin-top: 1rem; border-radius: 8px; border-left: 4px solid;
}
.kp-bimbingan-modern-container .catatan.dosen { background-color: #e0f2fe; border-color: #38bdf8; }
.kp-bimbingan-modern-container .catatan.mahasiswa { background-color: #f3e8ff; border-color: #c084fc; }
.kp-bimbingan-modern-container .catatan strong { display: block; margin-bottom: .5rem; font-weight: 600; }
.kp-bimbingan-modern-container .catatan p { margin: 0; line-height: 1.6; }
.kp-bimbingan-modern-container .btn-lampiran {
    display: inline-block; margin-top: .75rem; text-decoration: none;
    font-weight: 500; color: var(--primary-color);
}
.kp-bimbingan-modern-container .btn-lampiran:hover { text-decoration: underline; }
.kp-bimbingan-modern-container .status-badge {
    padding: 0.25rem .75rem; border-radius: 999px; font-size: .8rem; font-weight: 500; color: #fff;
}
.status-diajukan_mahasiswa { background-color: #fbbf24; color: var(--text-primary); }
.status-direview_dosen { background-color: #fb923c; }
.status-selesai { background-color: #34d399; }

.kp-bimbingan-modern-container .animate-on-scroll {
    opacity: 0; transform: translateX(20px); transition: opacity .5s ease-out, transform .5s ease-out;
}
.kp-bimbingan-modern-container .animate-on-scroll.is-visible { opacity: 1; transform: translateX(0); }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const container = document.querySelector('.kp-bimbingan-modern-container');
    if (!container) return;
    const animatedElements = container.querySelectorAll('.animate-on-scroll');
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
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
if (isset($conn) && $conn instanceof mysqli) {
    $conn->close();
}
?>