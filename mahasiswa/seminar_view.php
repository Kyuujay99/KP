<?php
// /KP/mahasiswa/seminar_view.php

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
$seminar_data_by_kp = []; // Array untuk menyimpan data seminar dikelompokkan per KP

// Sertakan file koneksi database
require_once '../config/db_connect.php';

// 2. AMBIL DATA SEMINAR KP DARI DATABASE UNTUK MAHASISWA YANG LOGIN
if ($conn && ($conn instanceof mysqli)) {
    $sql = "SELECT
                sk.id_seminar,
                sk.id_pengajuan,
                pk.judul_kp,
                sk.tanggal_pengajuan_seminar,
                sk.status_kelayakan_seminar,
                sk.catatan_kelayakan,
                sk.tanggal_seminar,
                sk.tempat_seminar,
                dp1.nama_dosen AS nama_penguji1,
                dp2.nama_dosen AS nama_penguji2,
                sk.status_pelaksanaan_seminar,
                sk.catatan_hasil_seminar,
                sk.created_at AS tanggal_data_seminar_dibuat
            FROM seminar_kp sk
            JOIN pengajuan_kp pk ON sk.id_pengajuan = pk.id_pengajuan
            LEFT JOIN dosen_pembimbing dp1 ON sk.nip_dosen_penguji1 = dp1.nip
            LEFT JOIN dosen_pembimbing dp2 ON sk.nip_dosen_penguji2 = dp2.nip
            WHERE pk.nim = ?
            ORDER BY pk.id_pengajuan DESC, sk.tanggal_seminar DESC, sk.created_at DESC";

    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("s", $nim_mahasiswa);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                // Kelompokkan berdasarkan id_pengajuan jika seorang mahasiswa bisa memiliki seminar untuk >1 KP
                // atau jika satu KP bisa memiliki >1 entri seminar (misal seminar ulang)
                $seminar_data_by_kp[$row['id_pengajuan']]['judul_kp'] = $row['judul_kp'];
                $seminar_data_by_kp[$row['id_pengajuan']]['seminars'][] = $row;
            }
        }
        $stmt->close();
    } else {
        $error_message = "Gagal menyiapkan query untuk mengambil data seminar: " . (($conn->error) ? htmlspecialchars($conn->error) : "Kesalahan tidak diketahui.");
    }
} else {
    $error_message = "Koneksi database gagal atau tidak valid.";
}

// Set judul halaman dan sertakan header
$page_title = "Informasi Seminar Kerja Praktek";
require_once '../includes/header.php';
?>

<div class="page-layout-wrapper">
    <main class="main-content-area">
        <div class="list-container seminar-view-container">
            <h1><?php echo htmlspecialchars($page_title); ?></h1>
            <p>Halaman ini menampilkan informasi terkait pelaksanaan seminar Kerja Praktek Anda.</p>
            <hr>

            <?php if (!empty($error_message)): ?>
                <div class="message error">
                    <p><?php echo $error_message; ?></p>
                </div>
            <?php endif; ?>

            <?php if (empty($seminar_data_by_kp) && empty($error_message)): ?>
                <div class="message info">
                    <p>Belum ada informasi seminar Kerja Praktek untuk Anda.</p>
                    <p>Jika Anda sudah menyelesaikan laporan KP dan memenuhi syarat, silakan hubungi Koordinator KP atau Dosen Pembimbing Anda untuk informasi penjadwalan seminar.</p>
                </div>
            <?php elseif (!empty($seminar_data_by_kp)): ?>
                <?php foreach ($seminar_data_by_kp as $id_kp => $data_seminar_group): ?>
                    <div class="kp-seminar-group card mb-4">
                        <div class="card-header">
                            <h3>Seminar untuk KP: <?php echo htmlspecialchars($data_seminar_group['judul_kp']); ?> (ID Pengajuan: <?php echo $id_kp; ?>)</h3>
                        </div>
                        <div class="card-body">
                            <?php if (empty($data_seminar_group['seminars'])): ?>
                                <p>Belum ada data seminar untuk KP ini.</p>
                            <?php else: ?>
                                <?php foreach ($data_seminar_group['seminars'] as $seminar): ?>
                                    <div class="seminar-item">
                                        <h4>Detail Seminar (ID Seminar: <?php echo $seminar['id_seminar']; ?>)</h4>
                                        <dl>
                                            <dt>Tanggal Pengajuan Seminar oleh Mhs:</dt>
                                            <dd><?php echo $seminar['tanggal_pengajuan_seminar'] ? date("d M Y", strtotime($seminar['tanggal_pengajuan_seminar'])) : '<em>Belum diajukan oleh mahasiswa</em>'; ?></dd>

                                            <dt>Status Kelayakan Seminar:</dt>
                                            <dd><span class="status-seminar status-kelayakan-<?php echo strtolower(str_replace('_', '-', $seminar['status_kelayakan_seminar'])); ?>"><?php echo ucfirst(str_replace('_', ' ', htmlspecialchars($seminar['status_kelayakan_seminar']))); ?></span></dd>
                                            
                                            <?php if(!empty($seminar['catatan_kelayakan'])): ?>
                                            <dt>Catatan Kelayakan:</dt>
                                            <dd class="catatan"><?php echo nl2br(htmlspecialchars($seminar['catatan_kelayakan'])); ?></dd>
                                            <?php endif; ?>

                                            <dt>Jadwal Seminar:</dt>
                                            <dd>
                                                <?php if ($seminar['tanggal_seminar']): ?>
                                                    <strong><?php echo date("d F Y, H:i", strtotime($seminar['tanggal_seminar'])); ?></strong>
                                                    <?php echo $seminar['tempat_seminar'] ? ' di ' . htmlspecialchars($seminar['tempat_seminar']) : ''; ?>
                                                <?php else: ?>
                                                    <em>Belum dijadwalkan</em>
                                                <?php endif; ?>
                                            </dd>

                                            <dt>Dosen Penguji 1:</dt>
                                            <dd><?php echo $seminar['nama_penguji1'] ? htmlspecialchars($seminar['nama_penguji1']) : '<em>Belum ditentukan</em>'; ?></dd>
                                            
                                            <dt>Dosen Penguji 2:</dt>
                                            <dd><?php echo $seminar['nama_penguji2'] ? htmlspecialchars($seminar['nama_penguji2']) : '<em>Belum ditentukan</em>'; ?></dd>

                                            <dt>Status Pelaksanaan Seminar:</dt>
                                            <dd><span class="status-seminar status-pelaksanaan-<?php echo strtolower(str_replace('_', '-', $seminar['status_pelaksanaan_seminar'])); ?>"><?php echo ucfirst(str_replace('_', ' ', htmlspecialchars($seminar['status_pelaksanaan_seminar']))); ?></span></dd>

                                            <?php if(!empty($seminar['catatan_hasil_seminar'])): ?>
                                            <dt>Catatan Hasil Seminar:</dt>
                                            <dd class="catatan"><?php echo nl2br(htmlspecialchars($seminar['catatan_hasil_seminar'])); ?></dd>
                                            <?php endif; ?>
                                            
                                            <dt>Data Dibuat/Diupdate:</dt>
                                            <dd><small><?php echo date("d M Y H:i", strtotime($seminar['tanggal_data_seminar_dibuat'])); ?></small></dd>
                                        </dl>
                                        <?php if (next($data_seminar_group['seminars'])): // Cek apakah ada item berikutnya ?>
                                            <hr class="item-separator">
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
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
    .seminar-view-container h1 { margin-top: 0; margin-bottom: 10px; }
    .seminar-view-container hr { margin-bottom: 20px; }
    .seminar-view-container p { margin-bottom: 15px; }
    .btn.mb-3 { margin-bottom: 1rem !important; }
    .icon-plus::before { content: "+ "; font-weight: bold; }


    .kp-seminar-group.card { margin-bottom: 30px; }
    .kp-seminar-group .card-header h3 { margin: 0; font-size: 1.4em; color: #0056b3; }

    .seminar-item {
        padding: 15px 0;
    }
    .seminar-item:last-child .item-separator {
        display: none; /* Sembunyikan separator untuk item terakhir */
    }
    .seminar-item h4 {
        font-size: 1.2em;
        color: #333;
        margin-top: 0;
        margin-bottom: 15px;
    }
    .seminar-item dl { margin-bottom: 0; }
    .seminar-item dt {
        font-weight: bold;
        color: #555;
        float: left;
        width: 220px; /* Lebar label */
        clear: left;
        margin-bottom: 8px;
        padding-right: 10px;
    }
    .seminar-item dd {
        margin-left: 230px; /* Sesuai lebar dt + spasi */
        margin-bottom: 8px;
        color: #333;
        line-height: 1.5;
    }
    .seminar-item dd.catatan {
        margin-left: 0; /* Catatan bisa full width di bawah dt/dd sebelumnya */
        background-color: #f9f9f9;
        padding: 8px;
        border: 1px solid #eee;
        border-radius: 4px;
        white-space: pre-wrap;
        margin-top: -5px; /* Tarik sedikit ke atas */
        margin-bottom: 10px;
    }
    .item-separator {
        border: 0;
        height: 1px;
        background-color: #e0e0e0;
        margin: 20px 0;
    }

    /* Styling untuk status seminar */
    .status-seminar {
        padding: 3px 8px;
        border-radius: 12px;
        font-size: 0.8em;
        font-weight: bold;
        color: #fff; /* Default putih, bisa di-override */
        white-space: nowrap;
    }
    /* Status Kelayakan: 'pending_verifikasi','layak_seminar','belum_layak' */
    .status-kelayakan-pending-verifikasi { background-color: #ffc107; color: #212529; } /* Kuning */
    .status-kelayakan-layak-seminar { background-color: #28a745; } /* Hijau */
    .status-kelayakan-belum-layak { background-color: #dc3545; } /* Merah */

    /* Status Pelaksanaan: 'dijadwalkan','selesai','dibatalkan','ditunda' */
    .status-pelaksanaan-dijadwalkan { background-color: #0dcaf0; color: #212529;} /* Cyan */
    .status-pelaksanaan-selesai { background-color: #0d6efd; } /* Biru */
    .status-pelaksanaan-dibatalkan { background-color: #6c757d; } /* Abu-abu */
    .status-pelaksanaan-ditunda { background-color: #fd7e14; } /* Orange */

</style>

<?php
require_once '../includes/footer.php';

if (isset($conn) && ($conn instanceof mysqli)) {
    $conn->close();
}
?>