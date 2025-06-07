<?php
// /KP/dosen/bimbingan_mahasiswa_list.php (Versi Diperbaiki & Dipercantik)

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// 1. OTENTIKASI DAN OTORISASI
require_once '../includes/auth_check.php';
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'dosen') {
    session_unset();
    session_destroy();
    header("Location: /KP/index.php?error=unauthorized_dosen");
    exit();
}

$nip_dosen_login = $_SESSION['user_id'];

// Sertakan file koneksi database
require_once '../config/db_connect.php';

$list_mahasiswa_bimbingan = [];
$error_db = '';

// 2. AMBIL DATA MAHASISWA BIMBINGAN YANG KP-NYA AKTIF
if ($conn && ($conn instanceof mysqli)) {
    // Status KP yang dianggap aktif dan memerlukan bimbingan.
    $status_kp_aktif = ['kp_berjalan', 'selesai_pelaksanaan', 'disetujui_dospem', 'diterima_perusahaan'];
    $status_placeholders = implode(',', array_fill(0, count($status_kp_aktif), '?'));

    $sql = "SELECT
                m.nim,
                m.nama AS nama_mahasiswa,
                pk.id_pengajuan,
                pk.judul_kp,
                pk.status_pengajuan,
                (SELECT COUNT(*) FROM bimbingan_kp bk WHERE bk.id_pengajuan = pk.id_pengajuan) AS jumlah_sesi_bimbingan,
                (SELECT MAX(bk_last.tanggal_bimbingan) FROM bimbingan_kp bk_last WHERE bk_last.id_pengajuan = pk.id_pengajuan) AS bimbingan_terakhir
            FROM pengajuan_kp pk
            JOIN mahasiswa m ON pk.nim = m.nim
            WHERE pk.nip_dosen_pembimbing_kp = ? 
              AND pk.status_pengajuan IN ($status_placeholders)
            ORDER BY m.nama ASC, pk.id_pengajuan DESC";

    $stmt = $conn->prepare($sql);

    if ($stmt) {
        $types = 's' . str_repeat('s', count($status_kp_aktif));
        $params = array_merge([$nip_dosen_login], $status_kp_aktif);
        $stmt->bind_param($types, ...$params); // Menggunakan spread operator lebih modern dan bersih
        
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $list_mahasiswa_bimbingan[] = $row;
            }
        }
        $stmt->close();
    } else {
        $error_db = "Gagal menyiapkan query: " . $conn->error;
    }
} else {
    $error_db = "Koneksi database gagal.";
}

// Set judul halaman dan sertakan header
$page_title = "Daftar Mahasiswa Bimbingan KP";
require_once '../includes/header.php';
?>

<div class="main-content-full">
    <div class="list-container">
        <div class="list-header">
            <h1><?php echo htmlspecialchars($page_title); ?></h1>
            <p>Berikut adalah daftar mahasiswa yang sedang atau akan melaksanakan Kerja Praktek di bawah bimbingan Anda.</p>
        </div>

        <?php if (!empty($error_db)): ?>
            <div class="message error"><p><?php echo $error_db; ?></p></div>
        <?php endif; ?>

        <?php if (empty($list_mahasiswa_bimbingan) && empty($error_db)): ?>
            <div class="message info">
                <p>Saat ini tidak ada mahasiswa aktif yang menjadi bimbingan Anda untuk Kerja Praktek.</p>
            </div>
        <?php elseif (!empty($list_mahasiswa_bimbingan)): ?>
            <div class="card-grid">
                <?php foreach ($list_mahasiswa_bimbingan as $mhs_kp): ?>
                    <div class="bimbingan-card">
                        <div class="card-header">
                            <div class="mahasiswa-info">
                                <div class="mahasiswa-avatar"><?php echo strtoupper(substr($mhs_kp['nama_mahasiswa'], 0, 1)); ?></div>
                                <div>
                                    <h4 class="mahasiswa-nama"><?php echo htmlspecialchars($mhs_kp['nama_mahasiswa']); ?></h4>
                                    <p class="mahasiswa-nim"><?php echo htmlspecialchars($mhs_kp['nim']); ?></p>
                                </div>
                            </div>
                            <span class="status-badge status-<?php echo strtolower(str_replace([' ', '_'], '-', $mhs_kp['status_pengajuan'])); ?>">
                                <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $mhs_kp['status_pengajuan']))); ?>
                            </span>
                        </div>
                        <div class="card-body">
                            <p class="judul-kp-title">Judul KP:</p>
                            <p class="judul-kp-text"><?php echo htmlspecialchars($mhs_kp['judul_kp']); ?></p>
                            <div class="bimbingan-stats">
                                <div>
                                    <span>Total Bimbingan</span>
                                    <strong><?php echo $mhs_kp['jumlah_sesi_bimbingan']; ?> Sesi</strong>
                                </div>
                                <div>
                                    <span>Bimbingan Terakhir</span>
                                    <strong><?php echo $mhs_kp['bimbingan_terakhir'] ? date("d M Y", strtotime($mhs_kp['bimbingan_terakhir'])) : 'Belum Ada'; ?></strong>
                                </div>
                            </div>
                        </div>
                        <div class="card-footer">
                            <a href="bimbingan_kelola.php?id_pengajuan=<?php echo $mhs_kp['id_pengajuan']; ?>" class="btn btn-primary">
                                Kelola Bimbingan & Logbook
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
    .list-container {
        max-width: 1400px;
        margin: 20px auto;
        padding: 2rem;
    }
    .list-header {
        margin-bottom: 2rem;
        text-align: center;
    }
    .list-header h1 {
        font-weight: 700;
        color: var(--dark-color);
    }
    .list-header p {
        font-size: 1.1em;
        color: var(--secondary-color);
    }

    .card-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
        gap: 1.5rem;
    }

    .bimbingan-card {
        background-color: #fff;
        border-radius: var(--border-radius);
        box-shadow: var(--card-shadow);
        display: flex;
        flex-direction: column;
        transition: transform 0.3s ease, box-shadow 0.3s ease;
    }
    .bimbingan-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 25px rgba(0,0,0,0.1);
    }
    
    .bimbingan-card .card-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        padding: 1.5rem;
        border-bottom: 1px solid var(--border-color);
    }
    .mahasiswa-info { display: flex; align-items: center; gap: 15px; }
    .mahasiswa-avatar {
        width: 50px;
        height: 50px;
        border-radius: 50%;
        background-color: var(--primary-color);
        color: white;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5em;
        font-weight: 600;
    }
    .mahasiswa-nama { margin: 0; font-size: 1.2em; font-weight: 600; color: var(--dark-color); }
    .mahasiswa-nim { margin: 0; color: var(--secondary-color); }

    .bimbingan-card .card-body {
        padding: 1.5rem;
        flex-grow: 1;
    }
    .judul-kp-title {
        font-size: 0.9em;
        color: var(--secondary-color);
        margin-bottom: 0.25rem;
    }
    .judul-kp-text {
        font-weight: 500;
        color: var(--dark-color);
        margin-bottom: 1.5rem;
        min-height: 60px; /* Jaga tinggi agar card sejajar */
    }

    .bimbingan-stats {
        display: flex;
        justify-content: space-between;
        background-color: #f8f9fa;
        padding: 1rem;
        border-radius: 8px;
    }
    .bimbingan-stats > div { text-align: center; }
    .bimbingan-stats span {
        display: block;
        font-size: 0.85em;
        color: var(--secondary-color);
    }
    .bimbingan-stats strong {
        display: block;
        font-size: 1.2em;
        color: var(--dark-color);
        font-weight: 600;
    }

    .bimbingan-card .card-footer {
        padding: 1.5rem;
        background-color: #f8f9fa;
        text-align: center;
        border-top: 1px solid var(--border-color);
    }
    
    /* Tombol dan Badge */
    .btn-primary {
        background-color: var(--primary-color);
        color: white;
        border: none;
        width: 100%;
        padding: 12px;
        font-weight: 600;
    }
    .btn-primary:hover { background-color: var(--primary-hover); }

    .status-badge {
        padding: 5px 10px;
        border-radius: 20px;
        font-size: 0.8em;
        font-weight: 600;
        color: #fff;
    }
    .status-kp-berjalan { background-color: #0d6efd; }
    .status-disetujui-dospem { background-color: #198754; }
    .status-diterima-perusahaan { background-color: #20c997; }
    .status-selesai-pelaksanaan { background-color: #28a745; }
</style>

<?php
require_once '../includes/footer.php';
if (isset($conn) && $conn) {
    $conn->close();
}
?>