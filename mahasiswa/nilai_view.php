<?php
// /KP/mahasiswa/nilai_view.php (Versi Disempurnakan)

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
$nilai_kp_data_list = [];

// Sertakan file koneksi database
require_once '../config/db_connect.php';

// 2. AMBIL DATA NILAI KP
if ($conn && ($conn instanceof mysqli)) {
    $sql = "SELECT
                pk.id_pengajuan, pk.judul_kp, pk.status_pengajuan,
                nk.id_nilai, nk.nilai_pembimbing_lapangan, nk.catatan_pembimbing_lapangan,
                nk.nilai_dosen_pembimbing, nk.catatan_dosen_pembimbing,
                nk.nilai_penguji1_seminar, nk.catatan_penguji1_seminar,
                nk.nilai_penguji2_seminar, nk.catatan_penguji2_seminar,
                nk.nilai_akhir_angka, nk.nilai_akhir_huruf, nk.is_final,
                nk.tanggal_input_nilai
            FROM pengajuan_kp pk
            LEFT JOIN nilai_kp nk ON pk.id_pengajuan = nk.id_pengajuan
            WHERE pk.nim = ?
            ORDER BY pk.id_pengajuan DESC";

    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("s", $nim_mahasiswa);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $nilai_kp_data_list[] = $row;
            }
        }
        $stmt->close();
    } else {
        $error_message = "Gagal mengambil data nilai KP: " . htmlspecialchars($conn->error);
    }
} else {
    $error_message = "Koneksi database gagal.";
}

$page_title = "Rincian Nilai Kerja Praktek Anda";
require_once '../includes/header.php';
?>

<div class="kp-nilai-view-container">

    <div class="list-hero-section">
        <div class="list-hero-content">
            <div class="list-hero-icon">
                <svg viewBox="0 0 24 24"><path d="M12 20V10"/><path d="M18 20V4"/><path d="M6 20v-4"/></svg>
            </div>
            <h1><?php echo htmlspecialchars($page_title); ?></h1>
            <p>Rincian perolehan nilai KP Anda. Nilai akhir akan muncul setelah semua komponen dinilai dan difinalisasi oleh Admin Prodi.</p>
        </div>
    </div>

    <div class="list-wrapper">
        <?php if (!empty($error_message)): ?>
            <div class="message error"><p><?php echo $error_message; ?></p></div>
        <?php endif; ?>

        <?php if (empty($nilai_kp_data_list) && empty($error_message)): ?>
            <div class="message info">
                <h4>Belum Ada Data Nilai</h4>
                <p>Belum ada data nilai yang dapat ditampilkan untuk pengajuan Kerja Praktek Anda.</p>
            </div>
        <?php else: ?>
            <?php foreach ($nilai_kp_data_list as $data_kp): ?>
                <div class="nilai-card animate-on-scroll">
                    <div class="card-header">
                        <h3><?php echo htmlspecialchars($data_kp['judul_kp']); ?></h3>
                        <span class="status-badge status-<?php echo strtolower(str_replace([' ', '_'], '-', $data_kp['status_pengajuan'])); ?>">
                            Status KP: <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $data_kp['status_pengajuan']))); ?>
                        </span>
                    </div>
                    <div class="card-body">
                        <?php if ($data_kp['id_nilai'] === null): ?>
                            <div class="message info" style="text-align:left;">
                                <p><em>Belum ada rincian nilai yang dimasukkan untuk KP ini. Silakan tunggu proses penilaian dari semua pihak terkait.</em></p>
                            </div>
                        <?php else: ?>
                            <div class="nilai-grid">
                                <div class="nilai-komponen-section">
                                    <h4>Komponen Penilaian</h4>
                                    <dl>
                                        <dt>Pembimbing Lapangan</dt>
                                        <dd><?php echo $data_kp['nilai_pembimbing_lapangan'] !== null ? htmlspecialchars(number_format($data_kp['nilai_pembimbing_lapangan'], 2)) : '<em>Belum Ada</em>'; ?></dd>

                                        <dt>Dosen Pembimbing</dt>
                                        <dd><?php echo $data_kp['nilai_dosen_pembimbing'] !== null ? htmlspecialchars(number_format($data_kp['nilai_dosen_pembimbing'], 2)) : '<em>Belum Ada</em>'; ?></dd>
                                       
                                        <dt>Penguji 1 Seminar</dt>
                                        <dd><?php echo $data_kp['nilai_penguji1_seminar'] !== null ? htmlspecialchars(number_format($data_kp['nilai_penguji1_seminar'], 2)) : '<em>Belum Ada</em>'; ?></dd>
                                        
                                        <dt>Penguji 2 Seminar</dt>
                                        <dd><?php echo $data_kp['nilai_penguji2_seminar'] !== null ? htmlspecialchars(number_format($data_kp['nilai_penguji2_seminar'], 2)) : '<em>Belum Ada</em>'; ?></dd>
                                    </dl>
                                </div>
                                <div class="nilai-akhir-section">
                                    <h4>Nilai Akhir</h4>
                                    <div class="nilai-huruf-box">
                                        <span>Nilai Huruf</span>
                                        <div class="nilai-display"><?php echo ($data_kp['nilai_akhir_huruf'] !== null) ? htmlspecialchars(strtoupper($data_kp['nilai_akhir_huruf'])) : '-'; ?></div>
                                    </div>
                                    <div class="nilai-angka-box">
                                        <span>Nilai Angka</span>
                                        <div class="nilai-display"><?php echo ($data_kp['nilai_akhir_angka'] !== null) ? htmlspecialchars(number_format($data_kp['nilai_akhir_angka'], 2)) : '-'; ?></div>
                                    </div>
                                    <div class="status-final <?php echo ($data_kp['id_nilai'] !== null && $data_kp['is_final'] == 1) ? 'final' : 'sementara'; ?>">
                                        <?php echo ($data_kp['id_nilai'] !== null && $data_kp['is_final'] == 1) ? '✔️ Nilai Final' : '⏳ Nilai Sementara'; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<style>
:root {
    --primary-color: #667eea; --text-primary: #1f2937; --text-secondary: #6b7280;
    --bg-light: #f9fafb; --border-color: #e5e7eb; --card-shadow: 0 4px 6px -1px rgba(0,0,0,0.1), 0 2px 4px -1px rgba(0,0,0,0.06);
    --border-radius: 12px;
}
.kp-nilai-view-container {
    font-family: 'Inter', sans-serif; color: var(--text-primary);
    max-width: 1200px; margin: 0 auto; padding: 2rem;
}
.list-hero-section {
    padding: 3rem 2rem; background: linear-gradient(135deg, #10b981 0%, #34d399 100%);
    border-radius: var(--border-radius); margin-bottom: 2rem; color: white; text-align: center;
}
.list-hero-content { max-width: 700px; margin: 0 auto; }
.list-hero-icon { width: 60px; height: 60px; background: rgba(255,255,255,0.1); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1.5rem; }
.list-hero-icon svg { width: 28px; height: 28px; stroke: white; stroke-width: 2; fill: none; }
.list-hero-section h1 { font-size: 2.5rem; font-weight: 700; margin-bottom: 0.5rem; }
.list-hero-section p { font-size: 1.1rem; opacity: 0.9; font-weight: 300; }
.list-wrapper { background-color: #fff; padding: 2.5rem; border-radius: var(--border-radius); box-shadow: var(--card-shadow); }

.nilai-card { border: 1px solid var(--border-color); border-radius: var(--border-radius); margin-bottom: 2rem; overflow: hidden; }
.nilai-card .card-header { padding: 1.25rem 1.5rem; background-color: var(--bg-light); border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; }
.nilai-card .card-header h3 { margin: 0; font-size: 1.25rem; }
.nilai-card .card-body { padding: 1.5rem; }

.nilai-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; align-items: start; }
@media (max-width: 768px) { .nilai-grid { grid-template-columns: 1fr; } }
.nilai-komponen-section h4, .nilai-akhir-section h4 { font-size: 1.1rem; font-weight: 600; color: var(--text-primary); margin-bottom: 1rem; padding-bottom: 0.5rem; border-bottom: 1px solid var(--border-color); }
.nilai-komponen-section dl { margin: 0; }
.nilai-komponen-section dt { font-weight: 500; color: var(--text-secondary); float: left; width: 60%; clear: left; padding: 0.5rem 0; }
.nilai-komponen-section dd { font-weight: 600; text-align: right; padding: 0.5rem 0; }

.nilai-akhir-section { background-color: var(--bg-light); padding: 1.5rem; border-radius: 10px; }
.nilai-huruf-box { text-align: center; margin-bottom: 1.5rem; }
.nilai-huruf-box span { font-size: 1rem; color: var(--text-secondary); }
.nilai-huruf-box .nilai-display { font-size: 4rem; font-weight: 800; color: var(--primary-color); line-height: 1; }
.nilai-angka-box { text-align: center; }
.nilai-angka-box span { font-size: 1rem; color: var(--text-secondary); }
.nilai-angka-box .nilai-display { font-size: 1.5rem; font-weight: 700; }

.status-final { margin-top: 1.5rem; text-align: center; font-weight: 600; padding: 0.5rem; border-radius: 8px; }
.status-final.final { background-color: #d1fae5; color: #065f46; }
.status-final.sementara { background-color: #fef3c7; color: #92400e; }

.animate-on-scroll { opacity: 0; transform: translateY(20px); transition: opacity .5s ease-out, transform .5s ease-out; }
.animate-on-scroll.is-visible { opacity: 1; transform: translateY(0); }
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
