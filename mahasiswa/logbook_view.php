<?php
// /KP/mahasiswa/logbook_view.php

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
$logbook_entries_by_kp = []; // Array untuk menyimpan logbook dikelompokkan per KP

// Sertakan file koneksi database
require_once '../config/db_connect.php';

// 2. AMBIL DATA LOGBOOK DARI DATABASE UNTUK MAHASISWA YANG LOGIN
if ($conn && ($conn instanceof mysqli)) {
    // Query untuk mengambil data logbook, di-join dengan tabel pengajuan_kp untuk judul KP
    $sql = "SELECT
                l.id_logbook,
                l.id_pengajuan,
                pk.judul_kp, 
                l.tanggal_kegiatan,
                l.jam_mulai,
                l.jam_selesai,
                l.uraian_kegiatan,
                l.status_verifikasi_logbook,
                l.catatan_pembimbing_logbook,
                l.created_at AS tanggal_submit_logbook
            FROM logbook l
            JOIN pengajuan_kp pk ON l.id_pengajuan = pk.id_pengajuan
            WHERE pk.nim = ?
            ORDER BY pk.id_pengajuan DESC, l.tanggal_kegiatan DESC, l.jam_mulai DESC"; // Urutkan agar KP terbaru di atas, lalu logbook terbaru di atas per KP

    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("s", $nim_mahasiswa);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                // Kelompokkan berdasarkan id_pengajuan
                $logbook_entries_by_kp[$row['id_pengajuan']]['judul_kp'] = $row['judul_kp'];
                $logbook_entries_by_kp[$row['id_pengajuan']]['entries'][] = $row;
            }
        }
        // Jika num_rows = 0, $logbook_entries_by_kp akan tetap kosong
        $stmt->close();
    } else {
        $error_message = "Gagal menyiapkan query untuk mengambil data logbook: " . (($conn->error) ? htmlspecialchars($conn->error) : "Kesalahan tidak diketahui.");
    }
} else {
    $error_message = "Koneksi database gagal atau tidak valid.";
}

// Set judul halaman dan sertakan header
$page_title = "Riwayat Logbook Kegiatan Kerja Praktek";
require_once '../includes/header.php';
?>

<div class="page-layout-wrapper">
    <main class="main-content-area">
        <div class="list-container logbook-view-container">
            <h1><?php echo htmlspecialchars($page_title); ?></h1>
            <p>Berikut adalah riwayat semua catatan logbook kegiatan Kerja Praktek yang telah Anda submit.</p>
            <a href="/KP/mahasiswa/logbook_form.php" class="btn btn-primary mb-3"><i class="icon-plus"></i> Isi Logbook Baru</a>
            <hr>

            <?php if (!empty($error_message)): ?>
                <div class="message error">
                    <p><?php echo $error_message; ?></p>
                </div>
            <?php endif; ?>

            <?php if (empty($logbook_entries_by_kp) && empty($error_message)): ?>
                <div class="message info">
                    <p>Anda belum memiliki riwayat catatan logbook.</p>
                    <p>Silakan isi logbook kegiatan Anda melalui form yang tersedia.</p>
                </div>
            <?php elseif (!empty($logbook_entries_by_kp)): ?>
                <?php foreach ($logbook_entries_by_kp as $id_kp => $data_kp): ?>
                    <div class="kp-logbook-group card mb-4">
                        <div class="card-header">
                            <h3>Logbook untuk KP: <?php echo htmlspecialchars($data_kp['judul_kp']); ?> (ID Pengajuan: <?php echo $id_kp; ?>)</h3>
                        </div>
                        <div class="card-body">
                            <?php if (empty($data_kp['entries'])): ?>
                                <p>Belum ada entri logbook untuk KP ini.</p>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="data-table logbook-table">
                                        <thead>
                                            <tr>
                                                <th>No.</th>
                                                <th>Tanggal Kegiatan</th>
                                                <th>Waktu</th>
                                                <th>Uraian Kegiatan</th>
                                                <th>Status Verifikasi</th>
                                                <th>Catatan Pembimbing</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php $entry_counter = 1; ?>
                                            <?php foreach ($data_kp['entries'] as $entry): ?>
                                                <tr>
                                                    <td><?php echo $entry_counter++; ?></td>
                                                    <td><?php echo date("d M Y", strtotime($entry['tanggal_kegiatan'])); ?></td>
                                                    <td>
                                                        <?php 
                                                        if ($entry['jam_mulai'] && $entry['jam_selesai']) {
                                                            echo date("H:i", strtotime($entry['jam_mulai'])) . " - " . date("H:i", strtotime($entry['jam_selesai']));
                                                        } elseif ($entry['jam_mulai']) {
                                                            echo date("H:i", strtotime($entry['jam_mulai'])) . " - (Selesai tidak dicatat)";
                                                        } else {
                                                            echo "-";
                                                        }
                                                        ?>
                                                    </td>
                                                    <td class="uraian-kegiatan-cell"><?php echo nl2br(htmlspecialchars($entry['uraian_kegiatan'])); ?></td>
                                                    <td>
                                                        <span class="status-logbook status-logbook-<?php echo strtolower(str_replace([' ', '_'], '-', $entry['status_verifikasi_logbook'])); ?>">
                                                            <?php echo ucfirst(str_replace('_', ' ', htmlspecialchars($entry['status_verifikasi_logbook']))); ?>
                                                        </span>
                                                    </td>
                                                    <td class="catatan-pembimbing-cell">
                                                        <?php echo $entry['catatan_pembimbing_logbook'] ? nl2br(htmlspecialchars($entry['catatan_pembimbing_logbook'])) : '<em>Belum ada catatan</em>'; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

        </div>
    </main>

</div>

<style>
    /* Asumsikan CSS umum dari header, sidebar, tabel, message, btn, card sudah ada */
    .logbook-view-container h1 { margin-top: 0; margin-bottom: 10px; }
    .logbook-view-container hr { margin-bottom: 20px; }
    .logbook-view-container p { margin-bottom: 15px; }
    .btn.mb-3 { margin-bottom: 1rem !important; } /* Untuk tombol Isi Logbook Baru */
    .icon-plus::before { content: "+ "; font-weight: bold; }

    .kp-logbook-group.card {
        margin-bottom: 30px; /* Jarak antar grup KP */
    }
    .kp-logbook-group .card-header h3 {
        margin: 0;
        font-size: 1.4em;
        color: #0056b3; /* Warna berbeda untuk judul KP */
    }
    .logbook-table td.uraian-kegiatan-cell {
        white-space: pre-wrap; /* Agar spasi dan baris baru di uraian tetap tampil */
        min-width: 250px; /* Agar kolom uraian tidak terlalu sempit */
    }
    .logbook-table td.catatan-pembimbing-cell {
        white-space: pre-wrap;
        font-style: italic;
        color: #555;
        min-width: 200px;
    }

    /* Styling untuk status verifikasi logbook (ENUM: 'pending','disetujui','revisi_minor','revisi_mayor') */
    .status-logbook {
        padding: 3px 8px;
        border-radius: 12px;
        font-size: 0.8em;
        font-weight: bold;
        color: #212529; /* Default warna teks */
        white-space: nowrap;
    }
    .status-logbook-pending { background-color: #ffc107; /* Kuning */ }
    .status-logbook-disetujui { background-color: #28a745; color: #fff; /* Hijau */ }
    .status-logbook-revisi-minor { background-color: #fd7e14; color: #fff; /* Orange */ }
    .status-logbook-revisi-mayor { background-color: #dc3545; color: #fff; /* Merah */ }
</style>

<?php
require_once '../includes/footer.php';

if (isset($conn) && ($conn instanceof mysqli)) {
    $conn->close();
}
?>