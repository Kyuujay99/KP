<?php
// /KP/mahasiswa/bimbingan_view.php

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
$bimbingan_entries_by_kp = []; // Array untuk menyimpan bimbingan dikelompokkan per KP

// Sertakan file koneksi database
require_once '../config/db_connect.php';

// 2. AMBIL DATA BIMBINGAN DARI DATABASE UNTUK MAHASISWA YANG LOGIN
if ($conn && ($conn instanceof mysqli)) {
    // Query untuk mengambil data bimbingan, di-join dengan pengajuan_kp untuk judul KP
    // dan dosen_pembimbing untuk nama dosen (walaupun mahasiswa tahu pembimbingnya, ini untuk kelengkapan)
    $sql = "SELECT
                bk.id_bimbingan,
                bk.id_pengajuan,
                pk.judul_kp, 
                dp.nama_dosen AS nama_pembimbing,
                bk.tanggal_bimbingan,
                bk.topik_bimbingan,
                bk.catatan_mahasiswa, /* Ditampilkan jika mahasiswa juga bisa input catatan di masa depan */
                bk.catatan_dosen,
                bk.file_lampiran_mahasiswa, /* Ditampilkan jika mahasiswa juga bisa upload lampiran */
                bk.file_lampiran_dosen,
                bk.status_bimbingan,
                bk.created_at AS tanggal_catat_bimbingan
            FROM bimbingan_kp bk
            JOIN pengajuan_kp pk ON bk.id_pengajuan = pk.id_pengajuan
            LEFT JOIN dosen_pembimbing dp ON bk.nip_pembimbing = dp.nip /* NIP Pembimbing di tabel bimbingan_kp */
            WHERE pk.nim = ?
            ORDER BY pk.id_pengajuan DESC, bk.tanggal_bimbingan DESC";

    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("s", $nim_mahasiswa);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                // Kelompokkan berdasarkan id_pengajuan
                $bimbingan_entries_by_kp[$row['id_pengajuan']]['judul_kp'] = $row['judul_kp'];
                $bimbingan_entries_by_kp[$row['id_pengajuan']]['entries'][] = $row;
            }
        }
        $stmt->close();
    } else {
        $error_message = "Gagal menyiapkan query untuk mengambil data bimbingan: " . (($conn->error) ? htmlspecialchars($conn->error) : "Kesalahan tidak diketahui.");
    }
} else {
    $error_message = "Koneksi database gagal atau tidak valid.";
}

// Set judul halaman dan sertakan header
$page_title = "Riwayat Bimbingan Kerja Praktek";
require_once '../includes/header.php';
?>

<div class="page-layout-wrapper">
    <main class="main-content-area">
        <div class="list-container bimbingan-view-container">
            <h1><?php echo htmlspecialchars($page_title); ?></h1>
            <p>Berikut adalah riwayat semua sesi bimbingan Kerja Praktek Anda dengan Dosen Pembimbing.</p>
            <hr>

            <?php if (!empty($error_message)): ?>
                <div class="message error">
                    <p><?php echo $error_message; ?></p>
                </div>
            <?php endif; ?>

            <?php if (empty($bimbingan_entries_by_kp) && empty($error_message)): ?>
                <div class="message info">
                    <p>Anda belum memiliki riwayat sesi bimbingan.</p>
                    <p>Pastikan Dosen Pembimbing Anda telah mencatat sesi bimbingan yang telah dilakukan.</p>
                </div>
            <?php elseif (!empty($bimbingan_entries_by_kp)): ?>
                <?php foreach ($bimbingan_entries_by_kp as $id_kp => $data_kp_bimbingan): ?>
                    <div class="kp-bimbingan-group card mb-4">
                        <div class="card-header">
                            <h3>Bimbingan untuk KP: <?php echo htmlspecialchars($data_kp_bimbingan['judul_kp']); ?> (ID Pengajuan: <?php echo $id_kp; ?>)</h3>
                        </div>
                        <div class="card-body">
                            <?php if (empty($data_kp_bimbingan['entries'])): ?>
                                <p>Belum ada entri bimbingan untuk KP ini.</p>
                            <?php else: ?>
                                <ul class="bimbingan-history-list">
                                    <?php foreach ($data_kp_bimbingan['entries'] as $sesi): ?>
                                        <li class="bimbingan-item">
                                            <div class="bimbingan-header">
                                                <strong><?php echo date("d F Y, H:i", strtotime($sesi['tanggal_bimbingan'])); ?></strong>
                                                <span class="status-bimbingan status-bimbingan-<?php echo strtolower(str_replace('_', '-', $sesi['status_bimbingan'])); ?>">
                                                    <?php echo ucfirst(str_replace('_', ' ', htmlspecialchars($sesi['status_bimbingan']))); ?>
                                                </span>
                                            </div>
                                            <p><strong>Dosen Pembimbing:</strong> <?php echo htmlspecialchars($sesi['nama_pembimbing'] ?: 'N/A'); ?></p>
                                            <p><strong>Topik:</strong> <?php echo htmlspecialchars($sesi['topik_bimbingan'] ?: 'Tidak ada topik spesifik'); ?></p>
                                            
                                            <?php if (!empty($sesi['catatan_mahasiswa'])): ?>
                                                <div class="catatan catatan-mahasiswa">
                                                    <strong>Catatan Anda:</strong>
                                                    <p><?php echo nl2br(htmlspecialchars($sesi['catatan_mahasiswa'])); ?></p>
                                                    <?php if ($sesi['file_lampiran_mahasiswa']): ?>
                                                        <small><a href="/KP/<?php echo htmlspecialchars($sesi['file_lampiran_mahasiswa']); ?>" target="_blank">Lihat Lampiran Anda</a></small>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endif; ?>

                                            <?php if (!empty($sesi['catatan_dosen'])): ?>
                                                <div class="catatan catatan-dosen">
                                                    <strong>Catatan Dosen Pembimbing:</strong>
                                                    <p><?php echo nl2br(htmlspecialchars($sesi['catatan_dosen'])); ?></p>
                                                    <?php if ($sesi['file_lampiran_dosen']): ?>
                                                        <small><a href="/KP/<?php echo htmlspecialchars($sesi['file_lampiran_dosen']); ?>" target="_blank">Lihat Lampiran Dosen</a></small>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <?php if (empty($sesi['catatan_mahasiswa']) && empty($sesi['catatan_dosen']) && empty($sesi['topik_bimbingan'])): ?>
                                                <p><em>Tidak ada detail catatan untuk sesi bimbingan ini.</em></p>
                                            <?php endif; ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

        </div>
    </main>

</div>

<style>
    /* Asumsikan CSS umum dari header, sidebar, tabel, message, btn, card, status-badge sudah ada */
    .bimbingan-view-container h1 { margin-top: 0; margin-bottom: 10px; }
    .bimbingan-view-container hr { margin-bottom: 20px; }
    .bimbingan-view-container p { margin-bottom: 15px; }
    .btn.mb-3 { margin-bottom: 1rem !important; }

    .kp-bimbingan-group.card { margin-bottom: 30px; }
    .kp-bimbingan-group .card-header h3 { margin: 0; font-size: 1.4em; color: #0056b3; }

    .bimbingan-history-list { list-style: none; padding: 0; }
    .bimbingan-item {
        background-color: #ffffff; /* Warna dasar item */
        border: 1px solid #e0e0e0;
        padding: 15px 20px;
        margin-bottom: 15px;
        border-radius: 5px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.04);
    }
    .bimbingan-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 12px;
        padding-bottom: 10px;
        border-bottom: 1px dashed #ccc;
    }
    .bimbingan-header strong { font-size: 1.15em; color: #333; }
    .bimbingan-item p { margin-bottom: 8px; line-height: 1.6; font-size: 0.95em; }
    .bimbingan-item p strong { color: #495057; }

    .catatan { padding: 10px 12px; margin-top: 10px; border-radius: 4px; font-size: 0.9em; border-left: 4px solid; }
    .catatan strong { display: block; margin-bottom: 5px; font-size: 0.95em; }
    .catatan p { margin-bottom: 0; line-height: 1.5; }
    .catatan small a { color: #007bff; text-decoration:none; font-weight: bold; }
    .catatan small a:hover { text-decoration:underline; }

    .catatan-mahasiswa { border-left-color: #6f42c1; background-color: #f8f0ff; } /* Ungu sangat muda */
    .catatan-dosen { border-left-color: #17a2b8; background-color: #e8f7fa; } /* Biru info muda */

    /* Status Bimbingan: ENUM('diajukan_mahasiswa','direview_dosen','selesai') */
    /* Pastikan konsisten dengan yang ada di dosen/bimbingan_kelola.php atau global */
    .status-bimbingan { padding: 3px 8px; border-radius: 10px; font-size: 0.75em; font-weight: bold; color: #fff; white-space: nowrap;}
    .status-bimbingan-diajukan-mahasiswa { background-color: #ffc107; color: #212529; }
    .status-bimbingan-direview-dosen { background-color: #fd7e14; }
    .status-bimbingan-selesai { background-color: #28a745; }
</style>

<?php
require_once '../includes/footer.php';

if (isset($conn) && ($conn instanceof mysqli)) {
    $conn->close();
}
?>