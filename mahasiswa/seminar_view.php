<?php
// /KP/mahasiswa/seminar_view.php (Versi Modern & Ditingkatkan)

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
$seminar_data_by_kp = []; // Array untuk menyimpan data seminar dikelompokkan per KP

// Sertakan file koneksi database
require_once '../config/db_connect.php';

// 2. AMBIL DATA SEMINAR KP DARI DATABASE UNTUK MAHASISWA YANG LOGIN
if ($conn && ($conn instanceof mysqli)) {
    $sql = "SELECT
                sk.id_seminar, sk.id_pengajuan, pk.judul_kp,
                sk.tanggal_pengajuan_seminar, sk.status_kelayakan_seminar,
                sk.catatan_kelayakan, sk.tanggal_seminar, sk.tempat_seminar,
                dp1.nama_dosen AS nama_penguji1, dp2.nama_dosen AS nama_penguji2,
                sk.status_pelaksanaan_seminar, sk.catatan_hasil_seminar
            FROM seminar_kp sk
            JOIN pengajuan_kp pk ON sk.id_pengajuan = pk.id_pengajuan
            LEFT JOIN dosen_pembimbing dp1 ON sk.nip_dosen_penguji1 = dp1.nip
            LEFT JOIN dosen_pembimbing dp2 ON sk.nip_dosen_penguji2 = dp2.nip
            WHERE pk.nim = ?
            ORDER BY pk.id_pengajuan DESC, sk.tanggal_seminar DESC, sk.created_at DESC";

    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("s", $nim_mahasiswa);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $seminar_data_by_kp[$row['id_pengajuan']]['judul_kp'] = $row['judul_kp'];
                $seminar_data_by_kp[$row['id_pengajuan']]['seminars'][] = $row;
            }
        }
        $stmt->close();
    } else {
        $error_message = "Gagal menyiapkan query untuk mengambil data seminar: " . htmlspecialchars($conn->error);
    }
} else {
    $error_message = "Koneksi database gagal.";
}

// Set judul halaman dan sertakan header
$page_title = "Informasi Seminar Kerja Praktek";
require_once '../includes/header.php';
?>

<!-- KONTENER UTAMA UNTUK TAMPILAN MODERN -->
<div class="kp-view-container">

    <div class="view-hero-section">
        <div class="view-hero-content">
            <div class="view-hero-icon">
                <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
            </div>
            <h1><?php echo htmlspecialchars($page_title); ?></h1>
            <p>Halaman ini menampilkan informasi terkait jadwal, status, dan hasil seminar Kerja Praktek Anda.</p>
        </div>
    </div>

    <div class="view-wrapper">
        <?php if (!empty($error_message)): ?>
            <div class="message error animate-on-scroll"><p><?php echo $error_message; ?></p></div>
        <?php endif; ?>

        <?php if (empty($seminar_data_by_kp) && empty($error_message)): ?>
            <div class="message info animate-on-scroll">
                <h4>Belum Ada Informasi Seminar</h4>
                <p>Saat ini belum ada data seminar untuk pengajuan KP Anda. Jika Anda sudah memenuhi syarat, silakan hubungi Dosen Pembimbing atau Admin Prodi untuk penjadwalan.</p>
            </div>
        <?php else: ?>
            <?php foreach ($seminar_data_by_kp as $id_kp => $data_seminar_group): ?>
                <div class="seminar-group-card animate-on-scroll">
                    <div class="group-header">
                        <h3>Seminar untuk KP: "<?php echo htmlspecialchars($data_seminar_group['judul_kp']); ?>"</h3>
                    </div>
                    <div class="group-body">
                        <?php if (empty($data_seminar_group['seminars'])): ?>
                            <p class="text-muted">Belum ada data seminar untuk pengajuan KP ini.</p>
                        <?php else: ?>
                            <?php foreach ($data_seminar_group['seminars'] as $seminar): ?>
                                <div class="seminar-item">
                                    <div class="seminar-timeline">
                                        <div class="timeline-icon">
                                            <svg viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>
                                        </div>
                                        <div class="timeline-line"></div>
                                    </div>
                                    <div class="seminar-details">
                                        <div class="detail-header">
                                            <h4>
                                                <?php echo $seminar['tanggal_seminar'] ? 'Jadwal Seminar' : 'Status Pengajuan Seminar'; ?>
                                            </h4>
                                            <span class="status-badge status-pelaksanaan-<?php echo strtolower(htmlspecialchars($seminar['status_pelaksanaan_seminar'])); ?>">
                                                <?php echo ucfirst(str_replace('_', ' ', htmlspecialchars($seminar['status_pelaksanaan_seminar']))); ?>
                                            </span>
                                        </div>
                                        
                                        <?php if ($seminar['tanggal_seminar']): ?>
                                            <div class="jadwal-info">
                                                <div class="info-item">
                                                    <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                                                    <span><?php echo date("l, d F Y", strtotime($seminar['tanggal_seminar'])); ?></span>
                                                </div>
                                                <div class="info-item">
                                                    <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                                                    <span><?php echo date("H:i", strtotime($seminar['tanggal_seminar'])); ?> WIB</span>
                                                </div>
                                                <div class="info-item">
                                                    <svg viewBox="0 0 24 24"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>
                                                    <span><?php echo htmlspecialchars($seminar['tempat_seminar']); ?></span>
                                                </div>
                                            </div>
                                        <?php endif; ?>

                                        <div class="penguji-info">
                                            <p><strong>Dosen Penguji 1:</strong> <?php echo htmlspecialchars($seminar['nama_penguji1'] ?: 'Belum ditentukan'); ?></p>
                                            <p><strong>Dosen Penguji 2:</strong> <?php echo htmlspecialchars($seminar['nama_penguji2'] ?: 'Belum ditentukan'); ?></p>
                                        </div>

                                        <div class="status-kelayakan">
                                            <strong>Status Kelayakan:</strong> 
                                            <span class="status-badge status-kelayakan-<?php echo strtolower(htmlspecialchars($seminar['status_kelayakan_seminar'])); ?>">
                                                <?php echo ucfirst(str_replace('_', ' ', htmlspecialchars($seminar['status_kelayakan_seminar']))); ?>
                                            </span>
                                        </div>

                                        <?php if(!empty($seminar['catatan_kelayakan'])): ?>
                                        <div class="catatan"><strong>Catatan Kelayakan:</strong><p><?php echo nl2br(htmlspecialchars($seminar['catatan_kelayakan'])); ?></p></div>
                                        <?php endif; ?>
                                        <?php if(!empty($seminar['catatan_hasil_seminar'])): ?>
                                        <div class="catatan"><strong>Catatan Hasil Seminar:</strong><p><?php echo nl2br(htmlspecialchars($seminar['catatan_hasil_seminar'])); ?></p></div>
                                        <?php endif; ?>
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
.kp-view-container { --primary-gradient:linear-gradient(135deg,#06b6d4 0%,#3b82f6 100%); --text-primary:#1f2937; --text-secondary:#6b7280; --bg-light:#f9fafb; --border-color:#e5e7eb; --card-shadow:0 10px 30px rgba(0,0,0,.07); --border-radius:16px; font-family:Inter,sans-serif; color:var(--text-primary); max-width:1200px; margin:0 auto; padding:2rem }
.kp-view-container svg { stroke-width:2; stroke-linecap:round; stroke-linejoin:round; fill:none; stroke:currentColor }
.kp-view-container .view-hero-section { padding:3rem 2rem; background:var(--primary-gradient); border-radius:var(--border-radius); margin-bottom:2.5rem; color:#fff; text-align:center }
.kp-view-container .view-hero-content { max-width:700px; margin:0 auto }
.kp-view-container .view-hero-icon { width:60px; height:60px; background:rgba(255,255,255,.1); border-radius:50%; display:flex; align-items:center; justify-content:center; margin:0 auto 1.5rem }
.kp-view-container .view-hero-icon svg { width:28px; height:28px; stroke:#fff }
.kp-view-container .view-hero-section h1 { font-size:2.5rem; font-weight:700; margin-bottom:.5rem }
.kp-view-container .view-hero-section p { font-size:1.1rem; opacity:.9; font-weight:300 }
.kp-view-container .view-wrapper { background-color:#fff; padding:2.5rem; border-radius:var(--border-radius); box-shadow:var(--card-shadow) }
.kp-view-container .message { padding:1.5rem; border-radius:var(--border-radius); text-align:center }
.kp-view-container .message.info { background-color:#eff6ff; color:#1e40af }
.kp-view-container .message h4 { margin-top:0; font-size:1.2rem }

.seminar-group-card { border:1px solid var(--border-color); border-radius:var(--border-radius); margin-bottom:2rem; overflow:hidden; }
.group-header { padding:1.5rem; background-color:var(--bg-light); border-bottom:1px solid var(--border-color) }
.group-header h3 { margin:0; font-size:1.3rem }
.group-body { padding:1.5rem }
.seminar-item { display:flex; gap:1.5rem }
.seminar-timeline { display:flex; flex-direction:column; align-items:center }
.timeline-icon { width:40px; height:40px; background-color:#e0e7ff; color:#4338ca; border-radius:50%; display:flex; align-items:center; justify-content:center; flex-shrink:0 }
.timeline-icon svg { width:20px; height:20px }
.timeline-line { flex-grow:1; width:2px; background-color:var(--border-color) }
.seminar-details { flex-grow:1 }
.detail-header { display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:1rem }
.detail-header h4 { margin:0; font-size:1.2rem }
.status-badge { padding:.25rem .75rem; border-radius:999px; font-size:.8rem; font-weight:600; text-transform:capitalize; color:#fff }
.status-kelayakan-pending-verifikasi { background-color:#fef3c7; color:#92400e }
.status-kelayakan-layak-seminar { background-color:#dcfce7; color:#166534 }
.status-kelayakan-belum-layak { background-color:#fee2e2; color:#991b1b }
.status-pelaksanaan-dijadwalkan { background-color:#cffafe; color:#155e75 }
.status-pelaksanaan-selesai { background-color:#bfdbfe; color:#1e40af }
.status-pelaksanaan-dibatalkan { background-color:#e5e7eb; color:#4b5563 }
.status-pelaksanaan-ditunda { background-color:#ffedd5; color:#9a3412 }
.jadwal-info { display:grid; grid-template-columns:repeat(auto-fit,minmax(200px,1fr)); gap:.5rem 1.5rem; background-color:var(--bg-light); padding:1rem; border-radius:8px; margin-bottom:1rem }
.info-item { display:flex; align-items:center; gap:.5rem; color:var(--text-secondary) }
.info-item svg { width:16px; height:16px }
.info-item span { font-weight:500 }
.penguji-info { margin-bottom:1rem }
.penguji-info p { margin:.25rem 0 }
.status-kelayakan { margin-bottom:1rem; display:flex; align-items:center; gap:.5rem }
.catatan { padding:1rem; margin-top:1rem; border-radius:8px; border-left:4px solid; background-color:#f3f4f6; border-color:#d1d5db }
.catatan strong { display:block; margin-bottom:.5rem; font-weight:600 }
.catatan p { margin:0; line-height:1.6; font-size:.9rem }
.text-muted { color:var(--text-secondary) }
.animate-on-scroll { opacity:0; transform:translateY(30px); transition:opacity .6s ease-out,transform .6s ease-out }
.animate-on-scroll.is-visible { opacity:1; transform:translateY(0) }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const container = document.querySelector('.kp-view-container');
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
if (isset($conn) && ($conn instanceof mysqli)) {
    $conn->close();
}
?>
