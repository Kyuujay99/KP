<?php
// /KP/perusahaan/penilaian_lapangan_list.php (Versi Modern & Terisolasi)

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// 1. OTENTIKASI DAN OTORISASI
require_once '../includes/auth_check.php';
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'perusahaan') {
    header("Location: /KP/index.php?error=unauthorized_perusahaan");
    exit();
}

$id_perusahaan_login = $_SESSION['user_id'];
$nama_perusahaan_login = $_SESSION['user_nama'];

require_once '../config/db_connect.php';

$list_mahasiswa_penilaian = [];
$error_db = '';

// 2. AMBIL DATA MAHASISWA YANG PERLU PENILAIAN (Logika PHP Anda sudah baik dan dipertahankan)
if ($conn && ($conn instanceof mysqli)) {
    $sql = "SELECT m.nim, m.nama AS nama_mahasiswa, pk.id_pengajuan, pk.judul_kp, pk.status_pengajuan,
                   pk.tanggal_mulai_rencana, pk.tanggal_selesai_rencana
            FROM pengajuan_kp pk
            JOIN mahasiswa m ON pk.nim = m.nim
            WHERE pk.id_perusahaan = ? 
              AND pk.status_pengajuan IN ('kp_berjalan', 'selesai_pelaksanaan', 'selesai_dinilai')
              AND NOT EXISTS (
                  SELECT 1 FROM nilai_kp nk 
                  WHERE nk.id_pengajuan = pk.id_pengajuan 
                  AND nk.nilai_pembimbing_lapangan IS NOT NULL
              )
            ORDER BY pk.tanggal_selesai_rencana ASC, m.nama ASC";

    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("i", $id_perusahaan_login);
        $stmt->execute();
        $result = $stmt->get_result();
        $list_mahasiswa_penilaian = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    } else {
        $error_db = "Gagal menyiapkan query: " . htmlspecialchars($conn->error);
    }
} else {
    $error_db = "Koneksi database gagal.";
}

$page_title = "Input Penilaian Lapangan";
require_once '../includes/header.php';
?>

<div class="kp-list-modern-container">

    <div class="list-hero-section">
        <div class="list-hero-content">
            <div class="list-hero-icon">
                <svg viewBox="0 0 24 24"><path d="M12 20h9"></path><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"></path></svg>
            </div>
            <h1><?php echo htmlspecialchars($page_title); ?></h1>
            <p>Daftar mahasiswa yang memerlukan penilaian kinerja dari Anda sebagai Pembimbing Lapangan.</p>
        </div>
    </div>

    <div class="list-wrapper">
        <?php if (!empty($error_db)): ?>
            <div class="message error"><p><?php echo $error_db; ?></p></div>
        <?php endif; ?>

        <?php if (empty($list_mahasiswa_penilaian) && empty($error_db)): ?>
            <div class="message info">
                <h4>Semua Sudah Dinilai</h4>
                <p>Saat ini tidak ada mahasiswa yang memerlukan penilaian lapangan dari Anda. Terima kasih atas kerja sama Anda.</p>
                <a href="/KP/perusahaan/mahasiswa_kp_list.php?status_kp=semua" class="btn-info">Lihat Daftar Semua Mahasiswa KP</a>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="modern-table">
                    <thead>
                        <tr>
                            <th>No.</th>
                            <th>Mahasiswa</th>
                            <th>Judul KP</th>
                            <th>Periode Pelaksanaan</th>
                            <th>Status KP</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($list_mahasiswa_penilaian as $index => $mhs): ?>
                            <tr class="animate-on-scroll">
                                <td><?php echo $index + 1; ?></td>
                                <td>
                                    <div class="user-cell">
                                        <div class="user-avatar"><?php echo strtoupper(substr($mhs['nama_mahasiswa'], 0, 1)); ?></div>
                                        <div class="user-info">
                                            <strong><?php echo htmlspecialchars($mhs['nama_mahasiswa']); ?></strong>
                                            <span><?php echo htmlspecialchars($mhs['nim']); ?></span>
                                        </div>
                                    </div>
                                </td>
                                <td class="judul-kp"><?php echo htmlspecialchars($mhs['judul_kp']); ?></td>
                                <td><?php echo date("d M Y", strtotime($mhs['tanggal_mulai_rencana'])); ?> - <?php echo date("d M Y", strtotime($mhs['tanggal_selesai_rencana'])); ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo strtolower(htmlspecialchars($mhs['status_pengajuan'])); ?>">
                                        <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $mhs['status_pengajuan']))); ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="penilaian_lapangan_input.php?id_pengajuan=<?php echo $mhs['id_pengajuan']; ?>" class="btn-aksi">
                                        <svg viewBox="0 0 24 24"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>
                                        Beri Nilai
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
/* GAYA BARU YANG TERISOLASI DALAM KONTENER */
.kp-list-modern-container {
    --primary-color: #667eea;
    --primary-gradient: linear-gradient(135deg,#667eea 0%,#764ba2 100%);
    --success-color: #34d399; --warning-color: #fbbf24;
    --text-primary: #1f2937; --text-secondary: #6b7280;
    --bg-light: #f9fafb; --border-color: #e5e7eb;
    --card-shadow: 0 4px 6px -1px rgba(0,0,0,0.1), 0 2px 4px -1px rgba(0,0,0,0.06);
    --border-radius: 12px;
    font-family: 'Inter', sans-serif; color: var(--text-primary);
    max-width: 1400px; margin: 0 auto; padding: 2rem;
}
.kp-list-modern-container svg {
    stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; fill: none; stroke: currentColor;
}
.kp-list-modern-container .list-hero-section {
    padding: 3rem 2rem; background: var(--primary-gradient);
    border-radius: var(--border-radius); margin-bottom: 2rem; color: white; text-align: center;
}
.kp-list-modern-container .list-hero-content { max-width: 700px; margin: 0 auto; }
.kp-list-modern-container .list-hero-icon {
    width: 60px; height: 60px; background: rgba(255,255,255,.1);
    border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1.5rem;
}
.kp-list-modern-container .list-hero-icon svg { width: 28px; height: 28px; stroke: white; }
.kp-list-modern-container .list-hero-section h1 { font-size: 2.5rem; font-weight: 700; margin-bottom: 0.5rem; }
.kp-list-modern-container .list-hero-section p { font-size: 1.1rem; opacity: 0.9; font-weight: 300; }
.kp-list-modern-container .list-wrapper { background-color: #fff; padding: 2rem; border-radius: var(--border-radius); box-shadow: var(--card-shadow); }
.kp-list-modern-container .message { padding: 1.5rem; border-radius: var(--border-radius); text-align: center; }
.kp-list-modern-container .message.info { background-color: #eff6ff; color: #1e40af; }
.kp-list-modern-container .message.error { background-color: #fee2e2; color: #991b1b; }
.kp-list-modern-container .message h4 { margin-top: 0; font-size: 1.2rem; }
.kp-list-modern-container .btn-info {
    display: inline-block; margin-top: 1rem; padding: .6rem 1.2rem;
    background-color: #3b82f6; color: white; font-weight: 500;
    text-decoration: none; border-radius: 8px; transition: background-color .2s;
}
.kp-list-modern-container .btn-info:hover { background-color: #2563eb; }

/* Tabel Modern */
.kp-list-modern-container .table-responsive { overflow-x: auto; }
.kp-list-modern-container .modern-table { width: 100%; border-collapse: separate; border-spacing: 0 0.5rem; }
.kp-list-modern-container .modern-table thead th {
    padding: 1rem 1.5rem; text-align: left; font-weight: 600; font-size: 0.85rem;
    color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.05em;
    border-bottom: 2px solid var(--border-color);
}
.kp-list-modern-container .modern-table tbody tr { transition: background-color 0.2s ease, transform 0.2s ease; }
.kp-list-modern-container .modern-table tbody tr:hover { background-color: var(--bg-light); transform: translateY(-2px); }
.kp-list-modern-container .modern-table tbody td { padding: 1.25rem 1.5rem; border-bottom: 1px solid var(--border-color); vertical-align: middle; }
.kp-list-modern-container .user-cell { display: flex; align-items: center; gap: 1rem; }
.kp-list-modern-container .user-avatar { width: 40px; height: 40px; border-radius: 50%; background-color: var(--primary-color); color: white; display: flex; align-items: center; justify-content: center; font-weight: 600; flex-shrink: 0; }
.kp-list-modern-container .user-info strong { display: block; font-weight: 600; }
.kp-list-modern-container .user-info span { font-size: 0.9rem; color: var(--text-secondary); }
.kp-list-modern-container .judul-kp { max-width: 300px; white-space: normal; }
.kp-list-modern-container .status-badge { padding: 0.25rem 0.75rem; border-radius: 999px; font-size: .8rem; font-weight: 500; color: #fff; }
.status-kp-berjalan { background-color: var(--success-color); }
.status-selesai-pelaksanaan { background-color: var(--warning-color); color: var(--text-primary); }
.status-selesai-dinilai { background-color: var(--text-secondary); }

.kp-list-modern-container .btn-aksi {
    display: inline-flex; align-items: center; gap: .5rem; padding: .5rem 1rem;
    border-radius: 8px; text-decoration: none; font-weight: 600;
    background-color: #fff; color: var(--primary-color);
    border: 1px solid var(--primary-color); transition: all .2s ease;
}
.kp-list-modern-container .btn-aksi:hover { background-color: var(--primary-color); color: #fff; transform: translateY(-1px); box-shadow: 0 4px 6px -1px rgba(0,0,0,.1); }
.kp-list-modern-container .btn-aksi svg { width: 16px; height: 16px; }

.kp-list-modern-container .animate-on-scroll {
    opacity: 0; transform: translateY(20px); transition: opacity .5s ease-out,transform .5s ease-out;
}
.kp-list-modern-container .animate-on-scroll.is-visible { opacity: 1; transform: translateY(0); }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const container = document.querySelector('.kp-list-modern-container');
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
if (isset($conn) && $conn instanceof mysqli) {
    $conn->close();
}
?>