<?php
// /KP/perusahaan/pengajuan_kp_masuk.php (Versi Modern & Terisolasi)

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

$list_pengajuan_masuk = [];
$error_db = '';

// 2. AMBIL DATA PENGAJUAN (Logika PHP Anda sudah baik dan dipertahankan)
if ($conn && ($conn instanceof mysqli)) {
    $sql = "SELECT pk.id_pengajuan, m.nim, m.nama AS nama_mahasiswa, m.prodi, pk.judul_kp, pk.created_at AS tanggal_diajukan
            FROM pengajuan_kp pk
            JOIN mahasiswa m ON pk.nim = m.nim
            WHERE pk.id_perusahaan = ? AND pk.status_pengajuan = 'menunggu_konfirmasi_perusahaan'
            ORDER BY pk.created_at ASC";

    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("i", $id_perusahaan_login);
        $stmt->execute();
        $result = $stmt->get_result();
        $list_pengajuan_masuk = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    } else {
        $error_db = "Gagal menyiapkan query: " . $conn->error;
    }
} else {
    $error_db = "Koneksi database gagal.";
}

$page_title = "Pengajuan KP Masuk";
require_once '../includes/header.php';
?>

<div class="kp-list-modern-container">

    <div class="list-hero-section">
        <div class="list-hero-content">
            <div class="list-hero-icon">
                <svg viewBox="0 0 24 24"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg>
            </div>
            <h1><?php echo htmlspecialchars($page_title); ?></h1>
            <p>Daftar pengajuan KP dari mahasiswa yang menunggu konfirmasi dari pihak Anda.</p>
        </div>
    </div>

    <div class="list-wrapper">
        <?php if (!empty($error_db)): ?>
            <div class="message error"><p><?php echo $error_db; ?></p></div>
        <?php endif; ?>

        <?php if (empty($list_pengajuan_masuk) && empty($error_db)): ?>
            <div class="message info">
                <h4>Tidak Ada Pengajuan Baru</h4>
                <p>Saat ini tidak ada pengajuan Kerja Praktek yang menunggu konfirmasi dari Anda. Terima kasih.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="modern-table">
                    <thead>
                        <tr>
                            <th>No.</th>
                            <th>Tgl. Diajukan</th>
                            <th>Mahasiswa</th>
                            <th>Program Studi</th>
                            <th>Judul/Topik Rencana KP</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($list_pengajuan_masuk as $index => $pengajuan): ?>
                            <tr class="animate-on-scroll">
                                <td><?php echo $index + 1; ?></td>
                                <td><?php echo date("d M Y", strtotime($pengajuan['tanggal_diajukan'])); ?></td>
                                <td>
                                    <div class="user-cell">
                                        <div class="user-avatar"><?php echo strtoupper(substr($pengajuan['nama_mahasiswa'], 0, 1)); ?></div>
                                        <div class="user-info">
                                            <strong><?php echo htmlspecialchars($pengajuan['nama_mahasiswa']); ?></strong>
                                            <span><?php echo htmlspecialchars($pengajuan['nim']); ?></span>
                                        </div>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($pengajuan['prodi']); ?></td>
                                <td class="judul-kp"><?php echo htmlspecialchars($pengajuan['judul_kp']); ?></td>
                                <td>
                                    <a href="pengajuan_kp_konfirmasi.php?id_pengajuan=<?php echo $pengajuan['id_pengajuan']; ?>" class="btn-aksi">
                                        <svg viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                                        Lihat & Konfirmasi
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
    --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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
.kp-list-modern-container .list-hero-section {
    padding: 3rem 2rem; background: var(--primary-gradient);
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

/* Wrapper & Notifikasi */
.kp-list-modern-container .list-wrapper { background-color: #fff; padding: 2rem; border-radius: var(--border-radius); box-shadow: var(--card-shadow); }
.kp-list-modern-container .message { padding: 1.5rem; border-radius: var(--border-radius); text-align: center; }
.kp-list-modern-container .message.info { background-color: #eff6ff; color: #1e40af; }
.kp-list-modern-container .message.error { background-color: #fee2e2; color: #991b1b; }
.kp-list-modern-container .message h4 { margin-top: 0; font-size: 1.2rem; }

/* Tabel Modern */
.kp-list-modern-container .table-responsive { overflow-x: auto; }
.kp-list-modern-container .modern-table { width: 100%; border-collapse: separate; border-spacing: 0 0.5rem; }
.kp-list-modern-container .modern-table thead th {
    padding: 1rem 1.5rem; text-align: left;
    font-weight: 600; font-size: 0.85rem;
    color: var(--text-secondary); text-transform: uppercase;
    letter-spacing: 0.05em; border-bottom: 2px solid var(--border-color);
}
.kp-list-modern-container .modern-table tbody tr {
    transition: background-color 0.2s ease, box-shadow 0.2s ease, transform 0.2s ease;
}
.kp-list-modern-container .modern-table tbody tr:hover {
    background-color: var(--bg-light);
    transform: translateY(-2px);
    box-shadow: 0 1px 2px 0 rgba(0,0,0,0.05);
}
.kp-list-modern-container .modern-table tbody td {
    padding: 1.25rem 1.5rem;
    border-bottom: 1px solid var(--border-color);
    vertical-align: middle;
}
.kp-list-modern-container .user-cell { display: flex; align-items: center; gap: 1rem; }
.kp-list-modern-container .user-avatar { width: 40px; height: 40px; border-radius: 50%; background-color: var(--primary-color); color: white; display: flex; align-items: center; justify-content: center; font-weight: 600; flex-shrink: 0; }
.kp-list-modern-container .user-info strong { display: block; font-weight: 600; }
.kp-list-modern-container .user-info span { font-size: 0.9rem; color: var(--text-secondary); }
.kp-list-modern-container .judul-kp { max-width: 350px; white-space: normal; }

/* Tombol Aksi */
.kp-list-modern-container .btn-aksi {
    display: inline-flex; align-items: center; gap: 0.5rem;
    padding: 0.5rem 1rem; border-radius: 8px; text-decoration: none;
    font-weight: 600; background-color: var(--primary-color); color: white;
    border: 1px solid var(--primary-color);
    transition: all 0.2s ease;
}
.kp-list-modern-container .btn-aksi:hover {
    background-color: #4338ca; border-color: #4338ca;
    transform: translateY(-1px);
    box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
}
.kp-list-modern-container .btn-aksi svg { width: 16px; height: 16px; }

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
    const animatedElements = container.querySelectorAll('.animate-on-scroll');
    const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry, index) => {
            if (entry.isIntersecting) {
                entry.target.style.transitionDelay = `${index * 70}ms`; // Staggered delay
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