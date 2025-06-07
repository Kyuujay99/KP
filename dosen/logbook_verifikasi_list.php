<?php
// /KP/dosen/logbook_verifikasi_list.php

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

$list_logbook_verifikasi = [];
$error_db = '';
$filter_status_logbook = isset($_GET['status_logbook']) ? $_GET['status_logbook'] : 'pending'; // Default filter 'pending'

// 2. AMBIL DATA LOGBOOK YANG PERLU DITINJAU OLEH DOSEN INI
if ($conn && ($conn instanceof mysqli)) {
    $sql = "SELECT
                l.id_logbook,
                l.id_pengajuan,
                l.tanggal_kegiatan,
                SUBSTRING(l.uraian_kegiatan, 1, 100) AS uraian_singkat, /* Ambil cuplikan uraian */
                l.status_verifikasi_logbook,
                l.created_at AS tanggal_submit_logbook,
                m.nim AS nim_mahasiswa,
                m.nama AS nama_mahasiswa,
                pk.judul_kp
            FROM logbook l
            JOIN pengajuan_kp pk ON l.id_pengajuan = pk.id_pengajuan
            JOIN mahasiswa m ON pk.nim = m.nim
            WHERE pk.nip_dosen_pembimbing_kp = ?";

    $params = [$nip_dosen_login];
    $types = "s";

    if (!empty($filter_status_logbook) && $filter_status_logbook !== 'semua') {
        $sql .= " AND l.status_verifikasi_logbook = ?";
        $params[] = $filter_status_logbook;
        $types .= "s";
    }

    $sql .= " ORDER BY l.tanggal_kegiatan ASC, l.created_at ASC"; // Proses logbook terlama dulu

    $stmt = $conn->prepare($sql);

    if ($stmt) {
        $stmt->bind_param($types, ...$params); // Spread operator untuk bind_param dinamis
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $list_logbook_verifikasi[] = $row;
            }
        }
        $stmt->close();
    } else {
        $error_db = "Gagal menyiapkan query untuk mengambil data logbook: " . (($conn->error) ? htmlspecialchars($conn->error) : "Kesalahan tidak diketahui.");
    }
} else {
    $error_db = "Koneksi database gagal atau tidak valid.";
}

// Daftar status verifikasi logbook untuk filter dropdown (sesuai ENUM)
$opsi_filter_status_logbook = [
    'pending' => 'Pending (Menunggu Verifikasi)',
    'disetujui' => 'Disetujui',
    'revisi_minor' => 'Revisi Minor',
    'revisi_mayor' => 'Revisi Mayor',
    'semua' => 'Tampilkan Semua Status Logbook'
];

// Set judul halaman dan sertakan header
$page_title = "Verifikasi Logbook Mahasiswa Bimbingan";
if(!empty($filter_status_logbook) && $filter_status_logbook !== 'semua') {
    $page_title = "Logbook Status: " . (isset($opsi_filter_status_logbook[$filter_status_logbook]) ? $opsi_filter_status_logbook[$filter_status_logbook] : ucfirst($filter_status_logbook));
} elseif ($filter_status_logbook === 'semua') {
    $page_title = "Semua Logbook Mahasiswa Bimbingan";
}
require_once '../includes/header.php';
?>

<div class="page-layout-wrapper">
    <main class="main-content-area">
        <div class="list-container logbook-verifikasi-dosen-list">
            <h1><?php echo htmlspecialchars($page_title); ?></h1>
            <p>Halaman ini menampilkan daftar entri logbook dari mahasiswa bimbingan Anda. Anda dapat memfilter berdasarkan status verifikasi.</p>
            <hr>

            <form action="/KP/dosen/logbook_verifikasi_list.php" method="GET" class="filter-form">
                <div class="form-group">
                    <label for="status_logbook">Filter Status Logbook:</label>
                    <select name="status_logbook" id="status_logbook" onchange="this.form.submit()">
                        <?php foreach ($opsi_filter_status_logbook as $value => $text): ?>
                            <option value="<?php echo $value; ?>" <?php echo ($filter_status_logbook == $value) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($text); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php if(!empty($filter_status_logbook) && $filter_status_logbook !== 'pending'): ?>
                    <a href="/KP/dosen/logbook_verifikasi_list.php" class="btn btn-secondary btn-sm">Reset ke Pending</a>
                <?php endif; ?>
            </form>
            <hr class="filter-hr">

            <?php if (!empty($error_db)): ?>
                <div class="message error">
                    <p><?php echo $error_db; ?></p>
                </div>
            <?php endif; ?>

            <?php if (empty($list_logbook_verifikasi) && empty($error_db)): ?>
                <div class="message info">
                    <p>Tidak ada entri logbook yang ditemukan<?php echo (!empty($filter_status_logbook) && $filter_status_logbook !== 'semua') ? " dengan status '" . htmlspecialchars($opsi_filter_status_logbook[$filter_status_logbook]) . "'" : " yang menunggu verifikasi dari Anda"; ?>.</p>
                </div>
            <?php elseif (!empty($list_logbook_verifikasi)): ?>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>No.</th>
                                <th>Tgl. Kegiatan</th>
                                <th>Mahasiswa (NIM)</th>
                                <th>Judul KP</th>
                                <th>Uraian Singkat</th>
                                <th>Status Logbook</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $counter = 1; ?>
                            <?php foreach ($list_logbook_verifikasi as $logbook): ?>
                                <tr>
                                    <td><?php echo $counter++; ?></td>
                                    <td><?php echo date("d M Y", strtotime($logbook['tanggal_kegiatan'])); ?></td>
                                    <td><?php echo htmlspecialchars($logbook['nama_mahasiswa']); ?> (<?php echo htmlspecialchars($logbook['nim_mahasiswa']); ?>)</td>
                                    <td><small><?php echo htmlspecialchars($logbook['judul_kp']); ?></small></td>
                                    <td><?php echo htmlspecialchars($logbook['uraian_singkat']) . (strlen($logbook['uraian_singkat']) >= 100 ? '...' : ''); ?></td>
                                    <td>
                                        <span class="status-logbook status-logbook-<?php echo strtolower(str_replace([' ', '_'], '-', $logbook['status_verifikasi_logbook'])); ?>">
                                            <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $logbook['status_verifikasi_logbook']))); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="/KP/dosen/logbook_verifikasi_form.php?id_logbook=<?php echo $logbook['id_logbook']; ?>" class="btn btn-primary btn-sm">
                                            Verifikasi/Detail
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

        </div>
    </main>

</div>

<style>
    /* Asumsikan CSS umum dari header, sidebar, tabel, message, btn, filter-form sudah ada */
    .logbook-verifikasi-dosen-list h1 { margin-top: 0; margin-bottom: 10px; }
    .logbook-verifikasi-dosen-list hr { margin-bottom: 20px; }
    .logbook-verifikasi-dosen-list p { margin-bottom: 15px; }
    .filter-hr { margin-top:0; margin-bottom:25px; }

    .data-table td small { font-size: 0.9em; color: #555; }

    /* Styling untuk status verifikasi logbook (ENUM: 'pending','disetujui','revisi_minor','revisi_mayor') */
    /* Pastikan ini konsisten dengan yang ada di mahasiswa/logbook_view.php atau global */
    .status-logbook {
        padding: 3px 8px;
        border-radius: 12px;
        font-size: 0.8em;
        font-weight: bold;
        color: #212529;
        white-space: nowrap;
    }
    .status-logbook-pending { background-color: #ffc107; }
    .status-logbook-disetujui { background-color: #28a745; color: #fff; }
    .status-logbook-revisi-minor { background-color: #fd7e14; color: #fff; }
    .status-logbook-revisi-mayor { background-color: #dc3545; color: #fff; }
</style>

<?php
require_once '../includes/footer.php';

if (isset($conn) && ($conn instanceof mysqli)) {
    $conn->close();
}
?>