<?php
// /KP/admin_prodi/dokumen_verifikasi_list.php

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// 1. OTENTIKASI DAN OTORISASI
require_once '../includes/auth_check.php';
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin_prodi') {
    session_unset();
    session_destroy();
    header("Location: /KP/index.php?error=unauthorized_admin");
    exit();
}

$admin_identifier = $_SESSION['user_id'];

// Sertakan file koneksi database
require_once '../config/db_connect.php';

$list_dokumen_pending = [];
$error_db = '';
$filter_status_dokumen = isset($_GET['status_dokumen']) ? $_GET['status_dokumen'] : 'pending'; // Default filter 'pending'

// 2. AMBIL DATA DOKUMEN YANG MEMERLUKAN VERIFIKASI DARI DATABASE
if ($conn && ($conn instanceof mysqli)) {
    $sql = "SELECT
                dk.id_dokumen, dk.id_pengajuan,
                dk.nama_dokumen, dk.jenis_dokumen, dk.tanggal_upload,
                dk.status_verifikasi_dokumen,
                pk.judul_kp,
                m.nim AS nim_mahasiswa,
                m.nama AS nama_mahasiswa
            FROM dokumen_kp dk
            JOIN pengajuan_kp pk ON dk.id_pengajuan = pk.id_pengajuan
            JOIN mahasiswa m ON pk.nim = m.nim";

    $params = [];
    $types = "";
    if (!empty($filter_status_dokumen) && $filter_status_dokumen !== 'semua') {
        $sql .= " WHERE dk.status_verifikasi_dokumen = ?";
        $params[] = $filter_status_dokumen;
        $types .= "s";
    }

    $sql .= " ORDER BY dk.tanggal_upload ASC"; // Proses dokumen terlama dulu

    $stmt = $conn->prepare($sql);

    if ($stmt) {
        if (!empty($filter_status_dokumen) && $filter_status_dokumen !== 'semua') {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $list_dokumen_pending[] = $row;
            }
        }
        $stmt->close();
    } else {
        $error_db = "Gagal menyiapkan query untuk mengambil data dokumen: " . (($conn->error) ? htmlspecialchars($conn->error) : "Kesalahan tidak diketahui.");
    }
} else {
    $error_db = "Koneksi database gagal atau tidak valid.";
}

// Daftar status verifikasi dokumen untuk filter dropdown (sesuai ENUM)
$opsi_filter_status_dokumen = [
    'pending' => 'Pending (Menunggu Verifikasi)',
    'disetujui' => 'Disetujui',
    'revisi_diperlukan' => 'Revisi Diperlukan',
    'ditolak' => 'Ditolak',
    'semua' => 'Tampilkan Semua Status Dokumen'
];


// Set judul halaman dan sertakan header
$page_title = "Daftar Dokumen KP Menunggu Verifikasi";
if(!empty($filter_status_dokumen) && $filter_status_dokumen !== 'semua') {
    $page_title = "Dokumen KP Status: " . (isset($opsi_filter_status_dokumen[$filter_status_dokumen]) ? $opsi_filter_status_dokumen[$filter_status_dokumen] : ucfirst($filter_status_dokumen));
} elseif ($filter_status_dokumen === 'semua') {
    $page_title = "Semua Dokumen KP Terunggah";
}

require_once '../includes/header.php';
?>

<div class="page-layout-wrapper">

    <?php require_once '../includes/sidebar_admin_prodi.php'; ?>

    <main class="main-content-area">
        <div class="list-container dokumen-verifikasi-list">
            <h1><?php echo htmlspecialchars($page_title); ?></h1>
            <p>Halaman ini menampilkan daftar dokumen yang diunggah oleh mahasiswa terkait pengajuan Kerja Praktek mereka. Anda dapat memfilter berdasarkan status verifikasi dokumen.</p>
            <hr>

            <form action="/KP/admin_prodi/dokumen_verifikasi_list.php" method="GET" class="filter-form">
                <div class="form-group">
                    <label for="status_dokumen">Filter berdasarkan Status Dokumen:</label>
                    <select name="status_dokumen" id="status_dokumen" onchange="this.form.submit()">
                        <?php foreach ($opsi_filter_status_dokumen as $value => $text): ?>
                            <option value="<?php echo $value; ?>" <?php echo ($filter_status_dokumen == $value) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($text); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php if(!empty($filter_status_dokumen) && $filter_status_dokumen !== 'pending'): // Tombol reset jika filter bukan default ?>
                    <a href="/KP/admin_prodi/dokumen_verifikasi_list.php" class="btn btn-secondary btn-sm">Reset ke Pending</a>
                <?php endif; ?>
            </form>
            <hr class="filter-hr">


            <?php if (!empty($error_db)): ?>
                <div class="message error">
                    <p><?php echo $error_db; ?></p>
                </div>
            <?php endif; ?>

            <?php if (empty($list_dokumen_pending) && empty($error_db)): ?>
                <div class="message info">
                    <p>Tidak ada dokumen yang ditemukan<?php echo (!empty($filter_status_dokumen) && $filter_status_dokumen !== 'semua') ? " dengan status '" . htmlspecialchars($opsi_filter_status_dokumen[$filter_status_dokumen]) . "'" : " yang menunggu verifikasi"; ?>.</p>
                </div>
            <?php elseif (!empty($list_dokumen_pending)): ?>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>No.</th>
                                <th>Tgl. Upload</th>
                                <th>Nama Dokumen</th>
                                <th>Jenis Dokumen</th>
                                <th>Mahasiswa (NIM)</th>
                                <th>Judul KP Terkait</th>
                                <th>Status Dokumen</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $counter = 1; ?>
                            <?php foreach ($list_dokumen_pending as $dokumen): ?>
                                <tr>
                                    <td><?php echo $counter++; ?></td>
                                    <td><?php echo date("d M Y H:i", strtotime($dokumen['tanggal_upload'])); ?></td>
                                    <td><?php echo htmlspecialchars($dokumen['nama_dokumen']); ?></td>
                                    <td><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $dokumen['jenis_dokumen']))); ?></td>
                                    <td><?php echo htmlspecialchars($dokumen['nama_mahasiswa']); ?> (<?php echo htmlspecialchars($dokumen['nim_mahasiswa']); ?>)</td>
                                    <td><small><?php echo htmlspecialchars($dokumen['judul_kp']); ?></small></td>
                                    <td>
                                        <span class="status-dokumen status-dokumen-<?php echo strtolower(str_replace([' ', '_'], '-', $dokumen['status_verifikasi_dokumen'])); ?>">
                                            <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $dokumen['status_verifikasi_dokumen']))); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="/KP/admin_prodi/dokumen_verifikasi_form.php?id_dokumen=<?php echo $dokumen['id_dokumen']; ?>&id_pengajuan=<?php echo $dokumen['id_pengajuan']; ?>" class="btn btn-primary btn-sm">
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
    /* Asumsikan CSS umum sudah ada dari header, sidebar, tabel, message, btn, filter-form */
    .dokumen-verifikasi-list h1 { margin-top: 0; margin-bottom: 10px; }
    .dokumen-verifikasi-list hr { margin-bottom: 20px; }
    .dokumen-verifikasi-list p { margin-bottom: 15px; }
    .filter-hr { margin-top:0; margin-bottom:25px; }


    /* Styling untuk status dokumen (pastikan konsisten atau global) */
    .status-dokumen {
        padding: 3px 8px;
        border-radius: 12px;
        font-size: 0.8em;
        font-weight: bold;
        color: #212529; /* Default warna teks untuk status */
        white-space: nowrap;
    }
    .status-dokumen-pending { background-color: #ffc107; /* Kuning */ }
    .status-dokumen-disetujui { background-color: #28a745; color: #fff; /* Hijau */ }
    .status-dokumen-revisi-diperlukan { background-color: #fd7e14; color: #fff; /* Orange */ }
    .status-dokumen-ditolak { background-color: #dc3545; color: #fff; /* Merah */ }
    .data-table td small { font-size: 0.9em; color: #555; }

</style>

<?php
require_once '../includes/footer.php';

if (isset($conn) && ($conn instanceof mysqli)) {
    $conn->close();
}
?>