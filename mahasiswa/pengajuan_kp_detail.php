<?php
// /KP/mahasiswa/pengajuan_kp_detail.php

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
$id_pengajuan_url = null;
$pengajuan_detail = null;
$dokumen_terkait = [];
$error_message = '';

// 2. VALIDASI DAN AMBIL ID PENGAJUAN DARI URL (GET PARAMETER)
if (isset($_GET['id']) && filter_var($_GET['id'], FILTER_VALIDATE_INT)) {
    $id_pengajuan_url = (int)$_GET['id'];
} else {
    $error_message = "ID Pengajuan tidak valid atau tidak ditemukan.";
    // Jika ID tidak valid, tidak perlu lanjut ke query database
}

// Sertakan file koneksi database
require_once '../config/db_connect.php';

// 3. AMBIL DATA DETAIL PENGAJUAN KP DARI DATABASE
if ($id_pengajuan_url && empty($error_message) && $conn && ($conn instanceof mysqli)) {
    // Query untuk mengambil detail pengajuan KP spesifik milik mahasiswa yang login
    $sql_detail = "SELECT
                       pk.id_pengajuan, pk.judul_kp, pk.deskripsi_kp,
                       p.nama_perusahaan, pk.id_perusahaan,
                       pk.tanggal_pengajuan, pk.tanggal_mulai_rencana, pk.tanggal_selesai_rencana,
                       pk.status_pengajuan, dpk.nama_dosen AS nama_dosen_pembimbing,
                       pk.catatan_admin, pk.catatan_dosen,
                       pk.surat_pengantar_path, pk.surat_balasan_perusahaan_path
                   FROM pengajuan_kp pk
                   LEFT JOIN perusahaan p ON pk.id_perusahaan = p.id_perusahaan
                   LEFT JOIN dosen_pembimbing dpk ON pk.nip_dosen_pembimbing_kp = dpk.nip
                   WHERE pk.id_pengajuan = ? AND pk.nim = ?";

    $stmt_detail = $conn->prepare($sql_detail);
    if ($stmt_detail) {
        $stmt_detail->bind_param("is", $id_pengajuan_url, $nim_mahasiswa);
        $stmt_detail->execute();
        $result_detail = $stmt_detail->get_result();
        if ($result_detail->num_rows === 1) {
            $pengajuan_detail = $result_detail->fetch_assoc();

            // Ambil juga dokumen terkait pengajuan ini dari tabel dokumen_kp
            $sql_dokumen = "SELECT id_dokumen, nama_dokumen, jenis_dokumen, file_path, tanggal_upload, status_verifikasi_dokumen, catatan_verifikator
                            FROM dokumen_kp
                            WHERE id_pengajuan = ?
                            ORDER BY tanggal_upload DESC";
            $stmt_dokumen = $conn->prepare($sql_dokumen);
            if ($stmt_dokumen) {
                $stmt_dokumen->bind_param("i", $id_pengajuan_url);
                $stmt_dokumen->execute();
                $result_dokumen = $stmt_dokumen->get_result();
                while ($row_dokumen = $result_dokumen->fetch_assoc()) {
                    $dokumen_terkait[] = $row_dokumen;
                }
                $stmt_dokumen->close();
            } else {
                $error_message .= " Gagal mengambil daftar dokumen: " . (($conn->error) ? htmlspecialchars($conn->error) : "Kesalahan tidak diketahui.");
            }

        } else {
            $error_message = "Detail pengajuan KP tidak ditemukan atau Anda tidak memiliki akses.";
        }
        $stmt_detail->close();
    } else {
        $error_message = "Gagal menyiapkan query detail: " . (($conn->error) ? htmlspecialchars($conn->error) : "Kesalahan tidak diketahui.");
    }
} elseif (!$id_pengajuan_url && empty($error_message)) {
    // Ini terjadi jika $_GET['id'] tidak ada dari awal
    $error_message = "ID Pengajuan tidak disediakan di URL.";
}


// Set judul halaman dan sertakan header
$page_title = "Detail Pengajuan Kerja Praktek";
if ($pengajuan_detail && !empty($pengajuan_detail['judul_kp'])) {
    $page_title = "Detail: " . htmlspecialchars($pengajuan_detail['judul_kp']);
}
require_once '../includes/header.php';
?>

<div class="page-layout-wrapper">

    <?php require_once '../includes/sidebar_mahasiswa.php'; ?>

    <main class="main-content-area">
        <div class="detail-pengajuan-container">
            <h1><?php echo htmlspecialchars($page_title); ?></h1>
            <a href="/KP/mahasiswa/pengajuan_kp_view.php" class="btn btn-light btn-sm mb-3">&laquo; Kembali ke Daftar Pengajuan</a>
            <hr>

            <?php if (!empty($error_message)): ?>
                <div class="message error">
                    <p><?php echo $error_message; ?></p>
                </div>
            <?php elseif ($pengajuan_detail): ?>
                <div class="info-section">
                    <h3>Informasi Umum Pengajuan</h3>
                    <dl>
                        <dt>ID Pengajuan:</dt>
                        <dd><?php echo $pengajuan_detail['id_pengajuan']; ?></dd>

                        <dt>Judul KP:</dt>
                        <dd><?php echo htmlspecialchars($pengajuan_detail['judul_kp']); ?></dd>

                        <dt>Status Pengajuan:</dt>
                        <dd>
                            <span class="status-badge status-<?php echo strtolower(str_replace(' ', '-', str_replace('_', '-', $pengajuan_detail['status_pengajuan']))); ?>">
                                <?php echo ucfirst(str_replace('_', ' ', htmlspecialchars($pengajuan_detail['status_pengajuan']))); ?>
                            </span>
                        </dd>

                        <dt>Tanggal Pengajuan:</dt>
                        <dd><?php echo date("d F Y", strtotime($pengajuan_detail['tanggal_pengajuan'])); ?></dd>
                    </dl>
                </div>

                <div class="info-section">
                    <h3>Detail Perusahaan & Pelaksanaan</h3>
                    <dl>
                        <dt>Perusahaan Tujuan:</dt>
                        <dd><?php echo $pengajuan_detail['nama_perusahaan'] ? htmlspecialchars($pengajuan_detail['nama_perusahaan']) : '<em>Belum ditentukan / Diajukan manual</em>'; ?></dd>

                        <dt>Deskripsi KP:</dt>
                        <dd><?php echo nl2br(htmlspecialchars($pengajuan_detail['deskripsi_kp'])); ?></dd>

                        <dt>Rencana Tanggal Mulai:</dt>
                        <dd><?php echo date("d F Y", strtotime($pengajuan_detail['tanggal_mulai_rencana'])); ?></dd>

                        <dt>Rencana Tanggal Selesai:</dt>
                        <dd><?php echo date("d F Y", strtotime($pengajuan_detail['tanggal_selesai_rencana'])); ?></dd>
                    </dl>
                </div>

                <div class="info-section">
                    <h3>Pembimbing & Catatan</h3>
                    <dl>
                        <dt>Dosen Pembimbing KP:</dt>
                        <dd><?php echo $pengajuan_detail['nama_dosen_pembimbing'] ? htmlspecialchars($pengajuan_detail['nama_dosen_pembimbing']) : '<em>Belum ditentukan</em>'; ?></dd>
                    </dl>
                    <?php if(!empty($pengajuan_detail['catatan_dosen'])): ?>
                    <div class="catatan catatan-dosen">
                        <strong>Catatan dari Dosen:</strong>
                        <p><?php echo nl2br(htmlspecialchars($pengajuan_detail['catatan_dosen'])); ?></p>
                    </div>
                    <?php endif; ?>
                    <?php if(!empty($pengajuan_detail['catatan_admin'])): ?>
                    <div class="catatan catatan-admin">
                        <strong>Catatan dari Admin Prodi:</strong>
                        <p><?php echo nl2br(htmlspecialchars($pengajuan_detail['catatan_admin'])); ?></p>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="info-section">
                    <h3>Dokumen Terkait</h3>
                    <?php if (!empty($dokumen_terkait)): ?>
                        <ul class="dokumen-list">
                            <?php foreach ($dokumen_terkait as $dokumen): ?>
                                <li>
                                    <strong><?php echo htmlspecialchars($dokumen['nama_dokumen']); ?></strong>
                                    (Jenis: <?php echo ucfirst(str_replace('_', ' ', htmlspecialchars($dokumen['jenis_dokumen']))); ?>)
                                    <br>
                                    <small>Diupload: <?php echo date("d M Y, H:i", strtotime($dokumen['tanggal_upload'])); ?> |
                                    Status: <span class="status-dokumen-<?php echo strtolower(str_replace(' ', '-', str_replace('_', '-', $dokumen['status_verifikasi_dokumen']))); ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', htmlspecialchars($dokumen['status_verifikasi_dokumen']))); ?>
                                    </span>
                                    </small>
                                    <?php if (!empty($dokumen['catatan_verifikator'])): ?>
                                        <div class="catatan catatan-verifikator">
                                            <small>Catatan Verifikator: <?php echo nl2br(htmlspecialchars($dokumen['catatan_verifikator'])); ?></small>
                                        </div>
                                    <?php endif; ?>
                                    <?php if (!empty($dokumen['file_path'])): ?>
                                        <br>
                                        <a href="/KP/<?php echo htmlspecialchars($dokumen['file_path']); ?>" target="_blank" class="btn btn-outline-primary btn-sm">Unduh/Lihat Dokumen</a>
                                    <?php else: ?>
                                        <small><em> (File tidak tersedia)</em></small>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p>Belum ada dokumen yang diunggah untuk pengajuan ini.</p>
                    <?php endif; ?>
                    <p style="margin-top:15px;"><a href="/KP/mahasiswa/dokumen_upload.php?id_pengajuan=<?php echo $id_pengajuan_url; ?>" class="btn btn-success btn-sm">Upload Dokumen Baru</a></p>
                </div>

                <?php else: ?>
                <div class="message info">
                    <p>Detail pengajuan tidak dapat ditampilkan atau ID tidak valid.</p>
                </div>
            <?php endif; ?>
        </div>
    </main>
</div>

<style>
    /* Asumsikan CSS umum dari header dan sidebar sudah berlaku */
    .detail-pengajuan-container h1 { margin-top: 0; margin-bottom: 5px; font-size: 1.8em; }
    .detail-pengajuan-container hr { margin-top:15px; margin-bottom: 25px; }
    .btn.mb-3 { margin-bottom: 1rem !important; }
    .btn-light { color: #212529; background-color: #f8f9fa; border-color: #f8f9fa; }
    .btn-light:hover { color: #212529; background-color: #e2e6ea; border-color: #dae0e5; }


    .info-section {
        margin-bottom: 30px;
        padding-bottom: 20px;
        border-bottom: 1px solid #eee;
    }
    .info-section:last-child {
        border-bottom: none;
        padding-bottom: 0;
    }
    .info-section h3 {
        font-size: 1.3em;
        color: #007bff;
        margin-top: 0;
        margin-bottom: 15px;
    }
    .info-section dl { margin-bottom: 0; }
    .info-section dt {
        font-weight: bold;
        color: #555;
        float: left;
        width: 200px; /* Lebar label bisa disesuaikan */
        clear: left;
        margin-bottom: 10px;
    }
    .info-section dd {
        margin-left: 210px; /* Sesuaikan dengan lebar dt + sedikit spasi */
        margin-bottom: 10px;
        color: #333;
        line-height: 1.5;
    }

    /* Catatan styling (sudah ada di view_pengajuan, pastikan konsisten) */
    .catatan { background-color: #f1f3f5; border-left: 4px solid; padding: 10px 15px; margin-top: 10px; border-radius: 4px; font-size: 0.9em; }
    .catatan-dosen { border-left-color: #17a2b8; }
    .catatan-admin { border-left-color: #ffc107; }
    .catatan-verifikator { border-left-color: #6f42c1; background-color: #f4f0f7; font-size:0.9em; margin-top:5px; }
    .catatan strong { display: block; margin-bottom: 5px; color: #343a40; }
    .catatan p, .catatan small { margin-bottom: 0; color: #495057; }

    /* Badge status (sudah ada di view_pengajuan, pastikan konsisten) */
    .status-badge { padding: 5px 10px; border-radius: 15px; font-size: 0.8em; font-weight: bold; color: #fff; text-transform: capitalize; }
    .status-draft { background-color: #6c757d; }
    .status-diajukan-mahasiswa { background-color: #ffc107; color:#212529; }
    /* Tambahkan semua kelas status lainnya dari pengajuan_kp_view.php */
    .status-kp-berjalan { background-color: #0d6efd; } /* dst... */
    .status-diverifikasi-dospem { background-color: #fd7e14; /* Orange */ }
    .status-disetujui-dospem { background-color: #20c997; /* Teal */ }
    .status-ditolak-dospem { background-color: #dc3545; /* Merah */ }
    .status-menunggu-konfirmasi-perusahaan { background-color: #6610f2; /* Indigo */ }
    .status-diterima-perusahaan { background-color: #198754; /* Hijau tua */ }
    .status-ditolak-perusahaan { background-color: #dc3545; /* Merah */ }
    .status-penentuan-dospem-kp { background-color: #0dcaf0; color:#212529; /* Cyan */ }
    .status-selesai-pelaksanaan { background-color: #28a745; /* Hijau */ }
    .status-laporan-disetujui { background-color: #d63384; /* Pink */ }
    .status-selesai-dinilai { background-color: #1f2023; /* Dark */ }
    .status-dibatalkan { background-color: #adb5bd; color:#212529; /* Abu-abu muda */ }

    /* Status dokumen */
    .status-dokumen-pending { color: orange; font-weight: bold; }
    .status-dokumen-disetujui { color: green; font-weight: bold; }
    .status-dokumen-revisi-diperlukan { color: #fd7e14; font-weight: bold; }
    .status-dokumen-ditolak { color: red; font-weight: bold; }


    .dokumen-list {
        list-style: none;
        padding: 0;
    }
    .dokumen-list li {
        background-color: #f9f9f9;
        padding: 15px;
        border: 1px solid #eee;
        border-radius: 5px;
        margin-bottom: 10px;
    }
    .dokumen-list li strong {
        font-size: 1.05em;
        color: #333;
    }
    .dokumen-list li small {
        display: block;
        color: #666;
        margin-top: 3px;
        margin-bottom: 8px;
    }
    .btn-outline-primary {
        color: #007bff;
        border-color: #007bff;
    }
    .btn-outline-primary:hover {
        color: #fff;
        background-color: #007bff;
        border-color: #007bff;
    }
    .btn-success { color: #fff; background-color: #28a745; border-color: #28a745; }
    .btn-success:hover { background-color: #218838; border-color: #1e7e34; }

    /* Message styling (jika belum global di header.php) */
    .message { padding: 10px 15px; margin-bottom: 20px; border-radius: 5px; border: 1px solid transparent; }
    .message.info { background-color: #d1ecf1; color: #0c5460; border-color: #bee5eb; }
    .message.error { background-color: #f8d7da; color: #721c24; border-color: #f5c6cb; }

</style>

<?php
require_once '../includes/footer.php';

if (isset($conn) && ($conn instanceof mysqli)) {
    $conn->close();
}
?>