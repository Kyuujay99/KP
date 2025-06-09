<?php
// /KP/admin_prodi/surat_generate_list.php (Versi Disempurnakan)

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once '../includes/auth_check.php';
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin_prodi') {
    header("Location: /KP/index.php?error=unauthorized_admin");
    exit();
}
require_once '../config/db_connect.php';

$list_pengajuan_siap_surat = [];
$error_db = '';

if ($conn) {
    $status_siap_surat = ['disetujui_dospem', 'diterima_perusahaan', 'kp_berjalan'];
    $status_placeholders = implode(',', array_fill(0, count($status_siap_surat), '?'));
    $sql = "SELECT pk.id_pengajuan, m.nim, m.nama AS nama_mahasiswa, pk.judul_kp, pr.nama_perusahaan, pk.surat_pengantar_path
            FROM pengajuan_kp pk
            JOIN mahasiswa m ON pk.nim = m.nim
            LEFT JOIN perusahaan pr ON pk.id_perusahaan = pr.id_perusahaan
            WHERE pk.status_pengajuan IN ($status_placeholders)
            ORDER BY pk.updated_at ASC";

    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param(str_repeat('s', count($status_siap_surat)), ...$status_siap_surat);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $list_pengajuan_siap_surat[] = $row;
        }
        $stmt->close();
    } else {
        $error_db = "Gagal menyiapkan query: " . $conn->error;
    }
} else {
    $error_db = "Koneksi database gagal.";
}

$page_title = "Manajemen & Generate Surat";
require_once '../includes/header.php';
?>

<div class="kp-list-modern-container">

    <div class="list-hero-section">
        <div class="list-hero-content">
            <div class="list-hero-icon">
                <svg viewBox="0 0 24 24"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg>
            </div>
            <h1><?php echo htmlspecialchars($page_title); ?></h1>
            <p>Daftar pengajuan KP yang siap untuk dibuatkan surat resmi, seperti Surat Pengantar atau Surat Tugas.</p>
        </div>
    </div>

    <div class="list-wrapper">
        <?php if (!empty($error_db)): ?>
            <div class="message error"><p><?php echo $error_db; ?></p></div>
        <?php endif; ?>

        <?php if (empty($list_pengajuan_siap_surat) && empty($error_db)): ?>
            <div class="message info">
                <h4>Tidak Ada Antrian Surat</h4>
                <p>Saat ini tidak ada pengajuan KP yang memerlukan pembuatan surat resmi.</p>
            </div>
        <?php else: ?>
            <div class="card-grid">
                <?php foreach ($list_pengajuan_siap_surat as $pengajuan): ?>
                    <div class="surat-card animate-on-scroll">
                        <div class="card-header">
                            <div class="user-profile">
                                <div class="user-avatar"><?php echo strtoupper(substr($pengajuan['nama_mahasiswa'], 0, 1)); ?></div>
                                <div class="user-info">
                                    <h4><?php echo htmlspecialchars($pengajuan['nama_mahasiswa']); ?></h4>
                                    <span><?php echo htmlspecialchars($pengajuan['nim']); ?></span>
                                </div>
                            </div>
                        </div>
                        <div class="card-body">
                            <p class="judul-kp"><?php echo htmlspecialchars($pengajuan['judul_kp']); ?></p>
                            <div class="detail-item">
                                <span>Perusahaan Tujuan</span>
                                <strong><?php echo htmlspecialchars($pengajuan['nama_perusahaan'] ?? 'N/A'); ?></strong>
                            </div>
                            <div class="detail-item">
                                <span>Status Surat</span>
                                <?php if (empty($pengajuan['surat_pengantar_path'])): ?>
                                    <strong class="status-label-belum">Belum Dibuat</strong>
                                <?php else: ?>
                                    <strong class="status-label-sudah">Sudah Dibuat</strong>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="card-footer">
                             <a href="surat_generate.php?tipe=pengantar&id_pengajuan=<?php echo $pengajuan['id_pengajuan']; ?>" class="btn-aksi" target="_blank">
                                ✉️ Surat Pengantar
                            </a>
                             <a href="surat_generate.php?tipe=tugas&id_pengajuan=<?php echo $pengajuan['id_pengajuan']; ?>" class="btn-aksi" target="_blank">
                                📝 Surat Tugas
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
.list-hero-section {
    padding: 3rem 2rem;
    background: linear-gradient(135deg, #06b6d4 0%, #3b82f6 100%);
    border-radius: var(--border-radius);
    margin-bottom: 2rem;
    color: white;
    text-align: center;
}
.list-hero-content { max-width: 700px; margin: 0 auto; }
.list-hero-icon {
    width: 60px; height: 60px;
    background: rgba(255,255,255,0.1);
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    margin: 0 auto 1.5rem;
}
.list-hero-icon svg { width: 28px; height: 28px; stroke: white; fill:none; stroke-width: 2; }
.list-hero-section h1 { font-size: 2.5rem; font-weight: 700; margin-bottom: 0.5rem; }
.list-hero-section p { font-size: 1.1rem; opacity: 0.9; font-weight: 300; }

.list-wrapper { background-color: #fff; padding: 2rem; border-radius: var(--border-radius); box-shadow: var(--card-shadow); }
.card-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(350px, 1fr)); gap: 1.5rem; }

.surat-card {
    background: #fff;
    border-radius: var(--border-radius);
    border: 1px solid var(--border-color);
    box-shadow: var(--card-shadow);
    display: flex;
    flex-direction: column;
    transition: all 0.3s ease;
}
.surat-card:hover { transform: translateY(-5px); box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); }

.surat-card .card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1.25rem 1.5rem;
    border-bottom: 1px solid var(--border-color);
}
.user-profile { display: flex; align-items: center; gap: 1rem; }
.user-avatar { width: 40px; height: 40px; border-radius: 50%; background-color: var(--primary-color); color: white; display: flex; align-items: center; justify-content: center; font-weight: 600; font-size: 1.2rem; }
.user-info h4 { margin: 0; font-size: 1.1rem; color: var(--text-primary); }
.user-info span { font-size: 0.9rem; color: var(--text-secondary); }

.surat-card .card-body { padding: 1.5rem; flex-grow: 1; }
.judul-kp { font-weight: 600; font-size: 1.1rem; margin-bottom: 1.5rem; line-height: 1.4; }
.detail-item { padding: 0.75rem 0; }
.detail-item:not(:last-child) { border-bottom: 1px dashed var(--border-color); }
.detail-item span { display: block; font-size: 0.85rem; color: var(--text-secondary); margin-bottom: 2px; }
.detail-item strong { font-weight: 500; font-size: 1rem; }
.status-label-belum { color: #d97706; }
.status-label-sudah { color: #16a34a; }


.surat-card .card-footer {
    padding: 1rem 1.5rem;
    background: var(--bg-light);
    border-top: 1px solid var(--border-color);
    display: flex;
    gap: 1rem;
}
.btn-aksi {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    padding: 0.75rem;
    border-radius: 8px;
    text-decoration: none;
    font-weight: 600;
    border: 1px solid var(--border-color);
    background-color: #fff;
    color: var(--text-primary);
    transition: all 0.2s ease;
}
.btn-aksi:hover { background-color: var(--primary-color); color: white; border-color: var(--primary-color); transform: translateY(-2px); }

/* Animasi */
.animate-on-scroll { opacity: 0; transform: translateY(20px); transition: opacity 0.5s ease-out, transform 0.5s ease-out; }
.animate-on-scroll.is-visible { opacity: 1; transform: translateY(0); }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const animatedElements = document.querySelectorAll('.animate-on-scroll');
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