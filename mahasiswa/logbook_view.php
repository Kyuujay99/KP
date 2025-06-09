<?php
// /KP/mahasiswa/logbook_view.php (Versi Disempurnakan)

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
$error_message = '';
$logbook_entries_by_kp = []; // Array untuk menyimpan logbook dikelompokkan per KP

// Sertakan file koneksi database
require_once '../config/db_connect.php';

// 2. AMBIL DATA LOGBOOK DARI DATABASE UNTUK MAHASISWA YANG LOGIN
if ($conn && ($conn instanceof mysqli)) {
    // Query untuk mengambil data logbook, di-join dengan tabel pengajuan_kp untuk judul KP
    $sql = "SELECT
                l.id_logbook,
                l.id_pengajuan,
                pk.judul_kp, 
                l.tanggal_kegiatan,
                l.jam_mulai,
                l.jam_selesai,
                l.uraian_kegiatan,
                l.status_verifikasi_logbook,
                l.catatan_pembimbing_logbook
            FROM logbook l
            JOIN pengajuan_kp pk ON l.id_pengajuan = pk.id_pengajuan
            WHERE pk.nim = ?
            ORDER BY pk.id_pengajuan DESC, l.tanggal_kegiatan DESC, l.created_at DESC";

    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("s", $nim_mahasiswa);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                // Kelompokkan berdasarkan id_pengajuan
                $logbook_entries_by_kp[$row['id_pengajuan']]['judul_kp'] = $row['judul_kp'];
                $logbook_entries_by_kp[$row['id_pengajuan']]['entries'][] = $row;
            }
        }
        $stmt->close();
    } else {
        $error_message = "Gagal mengambil data logbook: " . htmlspecialchars($conn->error);
    }
} else {
    $error_message = "Koneksi database gagal.";
}

$page_title = "Riwayat Logbook Kegiatan KP";
require_once '../includes/header.php';
?>

<div class="kp-logbook-view-container">

    <div class="list-hero-section">
        <div class="list-hero-content">
            <div class="list-hero-icon">
                <svg viewBox="0 0 24 24"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"></path><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"></path></svg>
            </div>
            <h1><?php echo htmlspecialchars($page_title); ?></h1>
            <p>Berikut adalah catatan riwayat semua sesi bimbingan Anda dengan Dosen Pembimbing.</p>
            <a href="/KP/mahasiswa/logbook_form.php" class="btn btn-primary mt-4">➕ Isi Logbook Baru</a>
        </div>
    </div>

    <div class="list-wrapper">
        <?php if (!empty($error_message)): ?>
            <div class="message error"><p><?php echo $error_message; ?></p></div>
        <?php endif; ?>

        <?php if (empty($logbook_entries_by_kp) && empty($error_message)): ?>
            <div class="message info">
                <h4>Belum Ada Riwayat Logbook</h4>
                <p>Anda belum memiliki catatan logbook. Silakan isi kegiatan harian Anda melalui tombol "Isi Logbook Baru".</p>
            </div>
        <?php else: ?>
            <?php foreach ($logbook_entries_by_kp as $id_kp => $data_kp): ?>
                <div class="timeline-group">
                    <div class="timeline-group-header">
                        <h2>Logbook untuk KP: <?php echo htmlspecialchars($data_kp['judul_kp']); ?></h2>
                    </div>
                    <div class="timeline">
                        <?php if (empty($data_kp['entries'])): ?>
                            <p class="message info" style="text-align:left;">Belum ada entri logbook untuk KP ini.</p>
                        <?php else: ?>
                            <?php foreach ($data_kp['entries'] as $entry): ?>
                                <div class="timeline-item animate-on-scroll">
                                    <div class="timeline-point"></div>
                                    <div class="timeline-content">
                                        <div class="logbook-header">
                                            <div class="logbook-date-time">
                                                <strong class="tanggal"><?php echo date("d F Y", strtotime($entry['tanggal_kegiatan'])); ?></strong>
                                                <span class="waktu">
                                                    <?php 
                                                    if ($entry['jam_mulai']) {
                                                        echo date("H:i", strtotime($entry['jam_mulai']));
                                                        if($entry['jam_selesai']) echo " - " . date("H:i", strtotime($entry['jam_selesai']));
                                                    } else {
                                                        echo "Waktu tidak dicatat";
                                                    }
                                                    ?>
                                                </span>
                                            </div>
                                            <span class="status-badge status-logbook-<?php echo strtolower(htmlspecialchars($entry['status_verifikasi_logbook'])); ?>">
                                                <?php echo ucfirst(str_replace('_', ' ', htmlspecialchars($entry['status_verifikasi_logbook']))); ?>
                                            </span>
                                        </div>
                                        <div class="logbook-body">
                                            <p class="uraian"><?php echo nl2br(htmlspecialchars($entry['uraian_kegiatan'])); ?></p>
                                            <?php if (!empty($entry['catatan_pembimbing_logbook'])): ?>
                                                <div class="catatan-dosen">
                                                    <strong>Catatan Dosen Pembimbing:</strong>
                                                    <p><?php echo nl2br(htmlspecialchars($entry['catatan_pembimbing_logbook'])); ?></p>
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
/* Modern Logbook View Styles */
:root {
    --primary-color: #667eea; --text-primary: #1f2937; --text-secondary: #6b7280;
    --bg-light: #f9fafb; --border-color: #e5e7eb; --card-shadow: 0 4px 6px -1px rgba(0,0,0,0.1), 0 2px 4px -1px rgba(0,0,0,0.06);
    --border-radius: 12px;
}
.kp-logbook-view-container {
    font-family: 'Inter', sans-serif; color: var(--text-primary);
    max-width: 1000px; margin: 0 auto; padding: 2rem;
}
.kp-logbook-view-container svg { stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; fill: none; stroke: currentColor; }
.list-hero-section {
    padding: 3rem 2rem; background: linear-gradient(135deg, #06b6d4 0%, #3b82f6 100%);
    border-radius: var(--border-radius); margin-bottom: 2rem; color: white; text-align: center;
}
.list-hero-content { max-width: 700px; margin: 0 auto; }
.list-hero-icon { width: 60px; height: 60px; background: rgba(255,255,255,0.1); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1.5rem; }
.list-hero-icon svg { width: 28px; height: 28px; stroke: white; }
.list-hero-section h1 { font-size: 2.5rem; font-weight: 700; margin-bottom: 0.5rem; }
.list-hero-section p { font-size: 1.1rem; opacity: 0.9; font-weight: 300; }
.list-wrapper { background-color: #fff; padding: 2.5rem; border-radius: var(--border-radius); box-shadow: var(--card-shadow); }
.btn.mt-4 { margin-top: 1.5rem; }

/* Timeline Styles */
.timeline-group { margin-bottom: 3rem; }
.timeline-group-header { margin-bottom: 2rem; padding-bottom: 1rem; border-bottom: 2px solid var(--border-color); }
.timeline-group-header h2 { font-size: 1.5rem; color: var(--text-primary); }
.timeline { position: relative; padding-left: 2rem; }
.timeline::before {
    content: ''; position: absolute; left: 10px; top: 10px; bottom: 10px;
    width: 3px; background-color: var(--border-color); border-radius: 2px;
}
.timeline-item { position: relative; margin-bottom: 2rem; }
.timeline-point {
    position: absolute; left: -8px; top: 12px;
    width: 18px; height: 18px; border-radius: 50%;
    background-color: var(--primary-color); border: 4px solid #fff;
    box-shadow: 0 0 0 3px var(--border-color);
}
.timeline-content {
    background-color: #fff; border: 1px solid var(--border-color);
    border-radius: var(--border-radius); box-shadow: var(--card-shadow);
    overflow: hidden;
}
.logbook-header { display: flex; justify-content: space-between; align-items: center; padding: 1rem 1.5rem; background-color: var(--bg-light); border-bottom: 1px solid var(--border-color); }
.logbook-date-time .tanggal { font-weight: 600; color: var(--text-primary); font-size: 1.1em; }
.logbook-date-time .waktu { display: block; font-size: 0.9em; color: var(--text-secondary); }
.logbook-body { padding: 1.5rem; }
.logbook-body .uraian { margin-top: 0; line-height: 1.6; white-space: pre-wrap; }
.catatan-dosen {
    margin-top: 1.5rem; padding: 1rem; border-radius: 8px;
    background-color: #eef2ff; border-left: 4px solid #6366f1;
}
.catatan-dosen strong { display: block; margin-bottom: 0.5rem; font-weight: 600; color: #4338ca; }
.catatan-dosen p { margin: 0; line-height: 1.6; font-style: italic; }

.status-badge { padding: 0.25rem 0.75rem; border-radius: 999px; font-size: 0.8rem; font-weight: 600; }
.status-logbook-pending { background-color: #fef3c7; color: #92400e; }
.status-logbook-disetujui { background-color: #d1fae5; color: #065f46; }
.status-logbook-revisi-minor { background-color: #ffedd5; color: #9a3412; }
.status-logbook-revisi-mayor { background-color: #fee2e2; color: #991b1b; }

.animate-on-scroll { opacity: 0; transform: translateX(20px); transition: opacity .5s ease-out, transform .5s ease-out; }
.animate-on-scroll.is-visible { opacity: 1; transform: translateX(0); }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const animatedElements = document.querySelectorAll('.animate-on-scroll');
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
if (isset($conn)) {
    $conn->close();
}
?>
