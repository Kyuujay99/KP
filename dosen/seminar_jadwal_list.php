<?php
// /KP/dosen/seminar_jadwal_list.php (Versi Diperbaiki & Dipercantik)

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

$list_seminar_kp = [];
$error_db = '';

// 2. AMBIL DATA SEMINAR KP YANG TERKAIT DENGAN DOSEN
if ($conn && ($conn instanceof mysqli)) {
    // Kueri untuk mengambil seminar di mana dosen ini adalah pembimbing ATAU penguji
    $sql = "SELECT
                sk.id_seminar,
                sk.id_pengajuan,
                pk.judul_kp,
                pk.nip_dosen_pembimbing_kp,
                m.nim AS nim_mahasiswa,
                m.nama AS nama_mahasiswa,
                sk.tanggal_seminar,
                sk.tempat_seminar,
                dp1.nama_dosen AS nama_penguji1,
                sk.nip_dosen_penguji1,
                dp2.nama_dosen AS nama_penguji2,
                sk.nip_dosen_penguji2,
                sk.status_pelaksanaan_seminar
            FROM seminar_kp sk
            JOIN pengajuan_kp pk ON sk.id_pengajuan = pk.id_pengajuan
            JOIN mahasiswa m ON pk.nim = m.nim
            LEFT JOIN dosen_pembimbing dp1 ON sk.nip_dosen_penguji1 = dp1.nip
            LEFT JOIN dosen_pembimbing dp2 ON sk.nip_dosen_penguji2 = dp2.nip
            WHERE pk.nip_dosen_pembimbing_kp = ?  /* Dosen sebagai pembimbing */
               OR sk.nip_dosen_penguji1 = ?       /* Dosen sebagai penguji 1 */
               OR sk.nip_dosen_penguji2 = ?       /* Dosen sebagai penguji 2 */
            GROUP BY sk.id_seminar /* Menghindari duplikasi jika dosen adalah pembimbing sekaligus penguji */
            ORDER BY sk.tanggal_seminar DESC, sk.created_at DESC";

    $stmt = $conn->prepare($sql);
    if ($stmt) {
        // Bind parameter dengan cara yang lebih sederhana dan aman
        $stmt->bind_param("sss", $nip_dosen_login, $nip_dosen_login, $nip_dosen_login);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                // Tentukan peran dosen untuk seminar ini
                if ($row['nip_dosen_pembimbing_kp'] == $nip_dosen_login) {
                    $row['peran_dosen'] = 'Pembimbing';
                } elseif ($row['nip_dosen_penguji1'] == $nip_dosen_login) {
                    $row['peran_dosen'] = 'Penguji 1';
                } elseif ($row['nip_dosen_penguji2'] == $nip_dosen_login) {
                    $row['peran_dosen'] = 'Penguji 2';
                } else {
                    $row['peran_dosen'] = 'Terkait';
                }
                $list_seminar_kp[] = $row;
            }
        }
        $stmt->close();
    } else {
        $error_db = "Gagal menyiapkan query untuk mengambil data seminar: " . $conn->error;
    }
} else {
    $error_db = "Koneksi database gagal atau tidak valid.";
}

// Set judul halaman
$page_title = "Jadwal & Daftar Seminar Kerja Praktek";
require_once '../includes/header.php';
?>

<div class="main-content-full">
    <div class="list-container">
        <div class="list-header">
            <h1><?php echo htmlspecialchars($page_title); ?></h1>
            <p>Daftar seminar Kerja Praktek yang terkait dengan Anda, baik sebagai dosen pembimbing maupun sebagai dosen penguji.</p>
        </div>

        <?php if (!empty($error_db)): ?>
            <div class="message error"><p><?php echo $error_db; ?></p></div>
        <?php endif; ?>

        <?php if (empty($list_seminar_kp) && empty($error_db)): ?>
            <div class="message info">
                <p>Saat ini tidak ada data seminar KP yang terkait dengan Anda.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Jadwal & Tempat</th>
                            <th>Mahasiswa</th>
                            <th>Judul KP</th>
                            <th>Peran Anda</th>
                            <th>Tim Penguji</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($list_seminar_kp as $seminar): ?>
                            <tr>
                                <td>
                                    <?php if ($seminar['tanggal_seminar']): ?>
                                        <div class="jadwal-hari"><?php echo date("d M Y", strtotime($seminar['tanggal_seminar'])); ?></div>
                                        <div class="jadwal-jam"><?php echo date("H:i", strtotime($seminar['tanggal_seminar'])); ?> WIB</div>
                                        <div class="jadwal-tempat"><?php echo htmlspecialchars($seminar['tempat_seminar']); ?></div>
                                    <?php else: ?>
                                        <em>Belum Dijadwalkan</em>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="mahasiswa-nama"><?php echo htmlspecialchars($seminar['nama_mahasiswa']); ?></div>
                                    <div class="mahasiswa-nim"><?php echo htmlspecialchars($seminar['nim_mahasiswa']); ?></div>
                                </td>
                                <td class="judul-kp-cell"><?php echo htmlspecialchars($seminar['judul_kp']); ?></td>
                                <td>
                                    <span class="peran-badge peran-<?php echo strtolower(str_replace(' ', '-', $seminar['peran_dosen'])); ?>">
                                        <?php echo htmlspecialchars($seminar['peran_dosen']); ?>
                                    </span>
                                </td>
                                <td class="penguji-cell">
                                    <strong>P1:</strong> <?php echo htmlspecialchars($seminar['nama_penguji1'] ?: '-'); ?><br>
                                    <strong>P2:</strong> <?php echo htmlspecialchars($seminar['nama_penguji2'] ?: '-'); ?>
                                </td>
                                <td>
                                    <span class="status-badge status-<?php echo strtolower(str_replace('_', '-', $seminar['status_pelaksanaan_seminar'])); ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', htmlspecialchars($seminar['status_pelaksanaan_seminar']))); ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="seminar_kelola_detail.php?id_seminar=<?php echo $seminar['id_seminar']; ?>&id_pengajuan=<?php echo $seminar['id_pengajuan']; ?>" class="btn btn-primary btn-sm">
                                        Detail / Nilai
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
    .list-container {
        max-width: 1400px;
        margin: 20px auto;
        padding: 2rem;
        background-color: #fff;
        border-radius: var(--border-radius);
        box-shadow: var(--card-shadow);
    }
    .list-header {
        margin-bottom: 1.5rem;
        padding-bottom: 1rem;
        border-bottom: 1px solid var(--border-color);
    }
    .table-responsive { overflow-x: auto; }
    .data-table { width: 100%; border-collapse: collapse; }
    .data-table th, .data-table td {
        padding: 12px 15px;
        border-bottom: 1px solid var(--border-color);
        text-align: left;
        vertical-align: top;
    }
    .data-table th {
        background-color: #f8f9fa;
        font-weight: 600;
        color: #495057;
    }
    .data-table tbody tr:hover { background-color: #f1f7ff; }

    /* Styling Kolom Spesifik */
    .jadwal-hari { font-weight: 600; font-size: 1.1em; color: var(--dark-color); }
    .jadwal-jam { color: var(--primary-color); }
    .jadwal-tempat { font-size: 0.9em; color: var(--secondary-color); }
    .mahasiswa-nama { font-weight: 600; }
    .mahasiswa-nim { font-size: 0.9em; color: #6c757d; }
    .judul-kp-cell { min-width: 300px; white-space: normal; }
    .penguji-cell { font-size: 0.9em; white-space: nowrap; }

    /* Badge untuk Peran dan Status */
    .peran-badge, .status-badge {
        padding: 5px 10px;
        border-radius: 20px;
        font-size: 0.8em;
        font-weight: 600;
        color: #fff;
        text-transform: capitalize;
    }
    .peran-pembimbing { background-color: #28a745; }
    .peran-penguji-1, .peran-penguji-2 { background-color: #007bff; }
    
    .status-dijadwalkan { background-color: #17a2b8; }
    .status-selesai { background-color: #0d6efd; }
    .status-dibatalkan { background-color: #6c757d; }
    .status-ditunda { background-color: #fd7e14; }

    .message { padding: 1rem; border-radius: 8px; margin-top: 1rem; }
    .message.info { background-color: #e9f5ff; color: #0056b3; }
    .btn-primary { background-color: var(--primary-color); color: white; border: none; }
    .btn-primary:hover { background-color: var(--primary-hover); }
</style>

<?php
require_once '../includes/footer.php';
if (isset($conn) && $conn) {
    $conn->close();
}
?>