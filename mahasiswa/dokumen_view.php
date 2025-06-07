<?php
// /KP/mahasiswa/dokumen_view.php

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
$nama_mahasiswa = $_SESSION['user_nama'];

// Sertakan file koneksi database
require_once '../config/db_connect.php';

$list_dokumen = [];
$error_db = '';

// 2. AMBIL SEMUA DATA DOKUMEN MILIK MAHASISWA YANG LOGIN
if ($conn && ($conn instanceof mysqli)) {
    // Query untuk mengambil semua dokumen yang diunggah oleh mahasiswa ini
    // di-JOIN dengan pengajuan_kp untuk mendapatkan konteks judul KP
    $sql = "SELECT
                dk.id_dokumen,
                dk.id_pengajuan,
                dk.nama_dokumen,
                dk.jenis_dokumen,
                dk.file_path,
                dk.tanggal_upload,
                dk.status_verifikasi_dokumen,
                dk.catatan_verifikator,
                pk.judul_kp
            FROM dokumen_kp dk
            JOIN pengajuan_kp pk ON dk.id_pengajuan = pk.id_pengajuan
            WHERE pk.nim = ? 
            /* Jika hanya ingin menampilkan dokumen yang diupload oleh mahasiswa: AND dk.tipe_uploader = 'mahasiswa' */
            ORDER BY dk.tanggal_upload DESC";

    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("s", $nim_mahasiswa);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $list_dokumen[] = $row;
            }
        }
        $stmt->close();
    } else {
        $error_db = "Gagal menyiapkan query untuk mengambil data dokumen: " . (($conn->error) ? htmlspecialchars($conn->error) : "Kesalahan tidak diketahui.");
    }
} else {
    $error_db = "Koneksi database gagal atau tidak valid.";
}

// Set judul halaman dan sertakan header
$page_title = "Dokumen Saya";
require_once '../includes/header.php';
?>

<div class="page-layout-wrapper">

    <?php require_once '../includes/sidebar_mahasiswa.php'; ?>

    <main class="main-content-area">
        <div class="list-container dokumen-view-mahasiswa">
            <h1><?php echo htmlspecialchars($page_title); ?></h1>
            <p>Halaman ini menampilkan riwayat semua dokumen yang telah Anda unggah ke sistem untuk berbagai keperluan pengajuan Kerja Praktek.</p>
            <hr>

            <?php if (!empty($error_db)): ?>
                <div class="message error">
                    <p><?php echo $error_db; ?></p>
                </div>
            <?php endif; ?>

            <?php if (empty($list_dokumen) && empty($error_db)): ?>
                <div class="message info">
                    <p>Anda belum pernah mengunggah dokumen apapun.</p>
                    <p>Anda dapat mengunggah dokumen melalui halaman <a href="/KP/mahasiswa/pengajuan_kp_form.php">Pengajuan KP Baru</a> atau melalui halaman detail pengajuan KP Anda.</p>
                </div>
            <?php elseif (!empty($list_dokumen)): ?>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>No.</th>
                                <th>Nama Dokumen</th>
                                <th>Jenis</th>
                                <th>Terkait KP</th>
                                <th>Tgl. Upload</th>
                                <th>Status Verifikasi</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $counter = 1; ?>
                            <?php foreach ($list_dokumen as $dokumen): ?>
                                <tr>
                                    <td><?php echo $counter++; ?></td>
                                    <td><?php echo htmlspecialchars($dokumen['nama_dokumen']); ?></td>
                                    <td><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $dokumen['jenis_dokumen']))); ?></td>
                                    <td>
                                        <a href="/KP/mahasiswa/pengajuan_kp_detail.php?id=<?php echo $dokumen['id_pengajuan']; ?>" title="Lihat Detail KP">
                                            <?php echo htmlspecialchars($dokumen['judul_kp']); ?>
                                        </a>
                                    </td>
                                    <td><?php echo date("d M Y H:i", strtotime($dokumen['tanggal_upload'])); ?></td>
                                    <td>
                                        <span class="status-dokumen status-dokumen-<?php echo strtolower(str_replace([' ', '_'], '-', $dokumen['status_verifikasi_dokumen'])); ?>" 
                                              title="<?php echo htmlspecialchars($dokumen['catatan_verifikator'] ?? 'Belum ada catatan'); ?>">
                                            <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $dokumen['status_verifikasi_dokumen']))); ?>
                                        </span>
                                        <?php if (!empty($dokumen['catatan_verifikator'])): ?>
                                            <i class="icon-info" title="<?php echo htmlspecialchars($dokumen['catatan_verifikator']); ?>"></i>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($dokumen['file_path'])): ?>
                                            <a href="/KP/<?php echo htmlspecialchars($dokumen['file_path']); ?>" class="btn btn-primary btn-sm" target="_blank">
                                                Unduh
                                            </a>
                                        <?php else: ?>
                                            <button class="btn btn-secondary btn-sm" disabled>Tidak Ada File</button>
                                        <?php endif; ?>
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
    /* Asumsikan CSS umum sudah ada dari header, sidebar, tabel, message, btn */
    .dokumen-view-mahasiswa h1 { margin-top: 0; margin-bottom: 10px; }
    .dokumen-view-mahasiswa hr { margin-bottom: 20px; }
    .dokumen-view-mahasiswa p { margin-bottom: 15px; }
    .icon-info::before { content: "ℹ️"; cursor: help; margin-left: 5px; }

    .data-table td a {
        text-decoration: none;
        color: #007bff;
    }
    .data-table td a:hover {
        text-decoration: underline;
    }
    
    /* Styling untuk status verifikasi dokumen (pastikan konsisten atau global) */
    .status-dokumen {
        padding: 3px 8px;
        border-radius: 12px;
        font-size: 0.8em;
        font-weight: bold;
        color: #212529;
        white-space: nowrap;
    }
    .status-dokumen-pending { background-color: #ffc107; } /* Kuning */
    .status-dokumen-disetujui { background-color: #28a745; color: #fff; } /* Hijau */
    .status-dokumen-revisi-diperlukan { background-color: #fd7e14; color: #fff; } /* Orange */
    .status-dokumen-ditolak { background-color: #dc3545; color: #fff; } /* Merah */
</style>

<?php
require_once '../includes/footer.php';

if (isset($conn) && ($conn instanceof mysqli)) {
    $conn->close();
}
?>