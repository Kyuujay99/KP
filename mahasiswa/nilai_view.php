<?php
// /KP/mahasiswa/nilai_view.php

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
$nilai_kp_data_list = []; // Array untuk menyimpan data nilai untuk setiap KP

// Sertakan file koneksi database
require_once '../config/db_connect.php';

// 2. AMBIL DATA NILAI KP DARI DATABASE UNTUK MAHASISWA YANG LOGIN
if ($conn && ($conn instanceof mysqli)) {
    // Query untuk mengambil data pengajuan KP dan semua field nilai terkaitnya (jika ada)
    $sql = "SELECT
                pk.id_pengajuan,
                pk.judul_kp,
                pk.status_pengajuan, /* Untuk konteks, apakah KP sudah selesai */
                nk.id_nilai,
                nk.nilai_pembimbing_lapangan,
                nk.catatan_pembimbing_lapangan,
                nk.nilai_dosen_pembimbing,
                nk.catatan_dosen_pembimbing,
                nk.nilai_penguji1_seminar,
                nk.catatan_penguji1_seminar,
                nk.nilai_penguji2_seminar,
                nk.catatan_penguji2_seminar,
                nk.nilai_akhir_angka,
                nk.nilai_akhir_huruf,
                nk.is_final,
                nk.tanggal_input_nilai
            FROM pengajuan_kp pk
            LEFT JOIN nilai_kp nk ON pk.id_pengajuan = nk.id_pengajuan
            WHERE pk.nim = ?
            ORDER BY pk.id_pengajuan DESC"; // Tampilkan KP terbaru dulu

    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("s", $nim_mahasiswa);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $nilai_kp_data_list[] = $row;
            }
        }
        $stmt->close();
    } else {
        $error_message = "Gagal menyiapkan query untuk mengambil data nilai KP: " . (($conn->error) ? htmlspecialchars($conn->error) : "Kesalahan tidak diketahui.");
    }
} else {
    $error_message = "Koneksi database gagal atau tidak valid.";
}

// Set judul halaman dan sertakan header
$page_title = "Rincian Nilai Kerja Praktek Anda";
require_once '../includes/header.php';
?>

<div class="page-layout-wrapper">
    <main class="main-content-area">
        <div class="list-container nilai-view-container-mahasiswa">
            <h1><?php echo htmlspecialchars($page_title); ?></h1>
            <p>Berikut adalah rincian perolehan nilai Kerja Praktek Anda. Jika ada komponen nilai yang masih kosong atau status nilai 'Sementara', berarti proses penilaian masih berlangsung atau belum difinalisasi oleh Admin Prodi.</p>
            <hr>

            <?php if (!empty($error_message)): ?>
                <div class="message error">
                    <p><?php echo $error_message; ?></p>
                </div>
            <?php endif; ?>

            <?php if (empty($nilai_kp_data_list) && empty($error_message)): ?>
                <div class="message info">
                    <p>Anda belum memiliki data pengajuan Kerja Praktek, atau belum ada nilai yang dimasukkan untuk KP Anda.</p>
                </div>
            <?php elseif (!empty($nilai_kp_data_list)): ?>
                <?php foreach ($nilai_kp_data_list as $data_kp): ?>
                    <div class="kp-nilai-group card mb-4">
                        <div class="card-header">
                            <h3>Nilai untuk KP: <?php echo htmlspecialchars($data_kp['judul_kp']); ?></h3>
                            <small>ID Pengajuan: <?php echo $data_kp['id_pengajuan']; ?> | Status KP:
                                <span class="status-badge status-<?php echo strtolower(str_replace([' ', '_'], '-', $data_kp['status_pengajuan'])); ?>">
                                    <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $data_kp['status_pengajuan']))); ?>
                                </span>
                            </small>
                        </div>
                        <div class="card-body">
                            <?php if ($data_kp['id_nilai'] === null): // Cek apakah ada record nilai_kp terkait ?>
                                <p class="text-muted"><em>Belum ada rincian nilai yang dimasukkan untuk KP ini. Silakan tunggu proses penilaian.</em></p>
                            <?php else: ?>
                                <div class="nilai-section">
                                    <h4>Komponen Penilaian:</h4>
                                    <dl class="nilai-komponen">
                                        <dt>Nilai Pembimbing Lapangan:</dt>
                                        <dd><?php echo $data_kp['nilai_pembimbing_lapangan'] !== null ? htmlspecialchars(number_format($data_kp['nilai_pembimbing_lapangan'], 2)) : '<em>Belum Ada</em>'; ?></dd>
                                        <?php if (!empty($data_kp['catatan_pembimbing_lapangan'])): ?>
                                            <dd class="catatan-komponen"><em>Catatan:</em> <?php echo nl2br(htmlspecialchars($data_kp['catatan_pembimbing_lapangan'])); ?></dd>
                                        <?php endif; ?>

                                        <dt>Nilai Dosen Pembimbing:</dt>
                                        <dd><?php echo $data_kp['nilai_dosen_pembimbing'] !== null ? htmlspecialchars(number_format($data_kp['nilai_dosen_pembimbing'], 2)) : '<em>Belum Ada</em>'; ?></dd>
                                        <?php if (!empty($data_kp['catatan_dosen_pembimbing'])): ?>
                                            <dd class="catatan-komponen"><em>Catatan:</em> <?php echo nl2br(htmlspecialchars($data_kp['catatan_dosen_pembimbing'])); ?></dd>
                                        <?php endif; ?>

                                        <dt>Nilai Penguji 1 Seminar:</dt>
                                        <dd><?php echo $data_kp['nilai_penguji1_seminar'] !== null ? htmlspecialchars(number_format($data_kp['nilai_penguji1_seminar'], 2)) : '<em>Belum Ada</em>'; ?></dd>
                                        <?php if (!empty($data_kp['catatan_penguji1_seminar'])): ?>
                                            <dd class="catatan-komponen"><em>Catatan:</em> <?php echo nl2br(htmlspecialchars($data_kp['catatan_penguji1_seminar'])); ?></dd>
                                        <?php endif; ?>

                                        <dt>Nilai Penguji 2 Seminar:</dt>
                                        <dd><?php echo $data_kp['nilai_penguji2_seminar'] !== null ? htmlspecialchars(number_format($data_kp['nilai_penguji2_seminar'], 2)) : '<em>Belum Ada</em>'; ?></dd>
                                        <?php if (!empty($data_kp['catatan_penguji2_seminar'])): ?>
                                            <dd class="catatan-komponen"><em>Catatan:</em> <?php echo nl2br(htmlspecialchars($data_kp['catatan_penguji2_seminar'])); ?></dd>
                                        <?php endif; ?>
                                    </dl>
                                </div>
                                <hr class="nilai-separator">
                                <div class="nilai-akhir-section">
                                    <h4>Rekapitulasi Nilai Akhir</h4>
                                    <dl>
                                        <dt>Nilai Akhir (Angka):</dt>
                                        <dd class="nilai-angka-akhir"><strong><?php echo ($data_kp['nilai_akhir_angka'] !== null) ? htmlspecialchars(number_format($data_kp['nilai_akhir_angka'], 2)) : '<em>Belum Dihitung</em>'; ?></strong></dd>

                                        <dt>Nilai Akhir (Huruf):</dt>
                                        <dd class="nilai-huruf-akhir"><strong><?php echo ($data_kp['nilai_akhir_huruf'] !== null) ? htmlspecialchars(strtoupper($data_kp['nilai_akhir_huruf'])) : '<em>-</em>'; ?></strong></dd>

                                        <dt>Status Finalisasi Nilai:</dt>
                                        <dd class="<?php echo ($data_kp['id_nilai'] !== null && $data_kp['is_final'] == 1) ? 'text-success-final' : 'text-warning-sementara'; ?>">
                                            <?php echo ($data_kp['id_nilai'] !== null && $data_kp['is_final'] == 1) ? 'Nilai Final' : 'Nilai Sementara / Belum Difinalisasi'; ?>
                                        </dd>
                                        <?php if ($data_kp['tanggal_input_nilai'] && $data_kp['id_nilai'] !== null): ?>
                                        <dt>Update Nilai Terakhir:</dt>
                                        <dd><small><?php echo date("d M Y H:i", strtotime($data_kp['tanggal_input_nilai'])); ?></small></dd>
                                        <?php endif; ?>
                                    </dl>
                                </div>
                            <?php endif; // Penutup if $data_kp['id_nilai'] === null ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

        </div>
    </main>

</div>

<style>
    /* Asumsikan CSS umum dari header, sidebar, card, message, btn, status-badge sudah ada */
    .nilai-view-container-mahasiswa h1 { margin-top: 0; margin-bottom: 10px; }
    .nilai-view-container-mahasiswa hr { margin-bottom: 20px; }
    .nilai-view-container-mahasiswa p { margin-bottom: 15px; line-height:1.6; }

    .kp-nilai-group.card { margin-bottom: 30px; }
    .kp-nilai-group .card-header { display: flex; justify-content: space-between; align-items: center; background-color: #e9ecef; }
    .kp-nilai-group .card-header h3 { margin: 0; font-size: 1.3em; color: #0056b3; }
    .kp-nilai-group .card-header small { font-size: 0.85em; color: #495057; }

    .nilai-section h4, .nilai-akhir-section h4 {
        font-size: 1.15em;
        color: #007bff;
        margin-top: 0;
        margin-bottom: 15px;
        padding-bottom: 5px;
        border-bottom: 1px solid #eee;
    }

    .nilai-komponen dt {
        font-weight: bold;
        color: #555;
        width: 220px; /* Lebar label */
        padding-right: 10px;
        margin-bottom: 8px;
        display: inline-block; /* Agar bisa berdampingan dengan dd jika dd pendek */
        vertical-align: top;
    }
    .nilai-komponen dd {
        display: inline; /* Coba inline, atau block dengan margin-left */
        margin-left: 5px;
        margin-bottom: 8px;
        color: #333;
    }
    .nilai-komponen dd.catatan-komponen {
        display: block; /* Catatan selalu di baris baru */
        margin-left: 0; /* Reset margin-left untuk catatan */
        margin-top: 3px;
        margin-bottom: 12px;
        padding: 8px 10px;
        background-color: #f8f9fa;
        border: 1px dashed #ced4da;
        border-radius: 4px;
        font-size: 0.9em;
        white-space: pre-wrap;
        color: #495057;
    }
    .nilai-komponen dd.catatan-komponen em { font-weight: normal; }


    .nilai-separator { margin: 25px 0; border-style: dashed; border-color: #ced4da; }

    .nilai-akhir-section dl dt {
        width: 220px; /* Samakan dengan komponen */
        float: left; clear: left;
        font-weight: bold; margin-bottom: 10px;
    }
    .nilai-akhir-section dl dd {
        margin-left: 230px; margin-bottom: 10px; font-size: 1.1em;
    }
    .nilai-akhir-section dl dd.nilai-angka-akhir strong { font-size: 1.2em; color: #28a745; }
    .nilai-akhir-section dl dd.nilai-huruf-akhir strong { font-size: 1.2em; color: #0d6efd; }
    
    .text-success-final { color: green; font-weight: bold; }
    .text-warning-sementara { color: #ffc107; font-weight: bold; }
    .text-muted { color: #6c757d !important; }

    /* Pastikan styling status-badge dari file lain terpakai atau definisikan di sini */
    /* Contoh: .status-selesai-dinilai { background-color: #1f2023; color: #fff; } */
</style>

<?php
require_once '../includes/footer.php';

if (isset($conn) && ($conn instanceof mysqli)) {
    $conn->close();
}
?>