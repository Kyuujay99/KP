<?php
// /KP/mahasiswa/pengajuan_kp_view.php

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

// Sertakan file koneksi database
require_once '../config/db_connect.php';

$list_pengajuan = []; // Array untuk menyimpan daftar pengajuan KP
$error_db = '';

// 2. AMBIL DATA PENGAJUAN KP DARI DATABASE UNTUK MAHASISWA YANG LOGIN
if ($conn && ($conn instanceof mysqli)) {
    // Query untuk mengambil data pengajuan KP, di-join dengan tabel perusahaan untuk mendapatkan nama perusahaan
    // Jika id_perusahaan di pengajuan_kp NULL, nama_perusahaan akan NULL juga (karena LEFT JOIN)
    $sql = "SELECT 
                pk.id_pengajuan, 
                pk.judul_kp, 
                pk.deskripsi_kp, 
                p.nama_perusahaan, 
                pk.tanggal_pengajuan, 
                pk.tanggal_mulai_rencana, 
                pk.tanggal_selesai_rencana, 
                pk.status_pengajuan,
                pk.catatan_admin,
                pk.catatan_dosen
            FROM pengajuan_kp pk
            LEFT JOIN perusahaan p ON pk.id_perusahaan = p.id_perusahaan
            WHERE pk.nim = ?
            ORDER BY pk.tanggal_pengajuan DESC, pk.id_pengajuan DESC";

    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("s", $nim_mahasiswa);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $list_pengajuan[] = $row;
            }
        }
        // Jika num_rows = 0, $list_pengajuan akan tetap kosong, yang akan ditangani di HTML
        $stmt->close();
    } else {
        $error_db = "Gagal menyiapkan query untuk mengambil data pengajuan: " . (($conn->error) ? htmlspecialchars($conn->error) : "Kesalahan tidak diketahui.");
    }
    // Koneksi akan ditutup di footer atau di akhir script
} else {
    $error_db = "Koneksi database gagal atau tidak valid.";
}

// Set judul halaman dan sertakan header
$page_title = "Riwayat Pengajuan Kerja Praktek Saya";
require_once '../includes/header.php';
?>

<div class="page-layout-wrapper">

    <?php require_once '../includes/sidebar_mahasiswa.php'; ?>

    <main class="main-content-area">
        <div class="view-pengajuan-container">
            <h1><?php echo htmlspecialchars($page_title); ?></h1>
            <p>Berikut adalah daftar semua pengajuan Kerja Praktek yang telah Anda buat beserta statusnya.</p>
            <hr>

            <?php if (!empty($error_db)): ?>
                <div class="message error">
                    <p><?php echo $error_db; ?></p>
                </div>
            <?php endif; ?>

            <?php if (empty($list_pengajuan) && empty($error_db)): ?>
                <div class="message info">
                    <p>Anda belum memiliki riwayat pengajuan Kerja Praktek.</p>
                    <p><a href="/KP/mahasiswa/pengajuan_kp_form.php" class="btn btn-primary">Ajukan KP Sekarang</a></p>
                </div>
            <?php elseif (!empty($list_pengajuan)): ?>
                <div class="pengajuan-list">
                    <?php foreach ($list_pengajuan as $pengajuan): ?>
                        <div class="pengajuan-item card">
                            <div class="card-header">
                                <h3><?php echo htmlspecialchars($pengajuan['judul_kp']); ?></h3>
                                <span class="status-badge status-<?php echo strtolower(str_replace(' ', '-', str_replace('_', '-', $pengajuan['status_pengajuan']))); ?>">
                                    <?php echo ucfirst(str_replace('_', ' ', htmlspecialchars($pengajuan['status_pengajuan']))); ?>
                                </span>
                            </div>
                            <div class="card-body">
                                <p>
                                    <strong>Perusahaan:</strong> 
                                    <?php echo $pengajuan['nama_perusahaan'] ? htmlspecialchars($pengajuan['nama_perusahaan']) : '<em>Belum ditentukan / Perusahaan diajukan manual</em>'; ?>
                                </p>
                                <p><strong>Tanggal Pengajuan:</strong> <?php echo date("d F Y", strtotime($pengajuan['tanggal_pengajuan'])); ?></p>
                                <p><strong>Rencana Periode:</strong> <?php echo date("d M Y", strtotime($pengajuan['tanggal_mulai_rencana'])); ?> s/d <?php echo date("d M Y", strtotime($pengajuan['tanggal_selesai_rencana'])); ?></p>
                                
                                <?php if(!empty($pengajuan['catatan_dosen'])): ?>
                                <div class="catatan catatan-dosen">
                                    <strong>Catatan Dosen:</strong>
                                    <p><?php echo nl2br(htmlspecialchars($pengajuan['catatan_dosen'])); ?></p>
                                </div>
                                <?php endif; ?>

                                <?php if(!empty($pengajuan['catatan_admin'])): ?>
                                <div class="catatan catatan-admin">
                                    <strong>Catatan Admin:</strong>
                                    <p><?php echo nl2br(htmlspecialchars($pengajuan['catatan_admin'])); ?></p>
                                </div>
                                <?php endif; ?>
                            </div>
                            <div class="card-footer">
                                <small>ID Pengajuan: <?php echo $pengajuan['id_pengajuan']; ?></small>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

        </div>
    </main>

</div>

<style>
    /* Asumsikan CSS untuk .page-layout-wrapper, .sidebar-mahasiswa, .main-content-area sudah ada */
    .view-pengajuan-container h1 { margin-top: 0; margin-bottom: 10px; font-size: 1.8em; }
    .view-pengajuan-container hr { margin-bottom: 25px; }
    .view-pengajuan-container p { margin-bottom: 20px; font-size:0.95em; color:#555; }

    .pengajuan-list {
        display: grid;
        gap: 20px; /* Jarak antar item pengajuan */
    }

    .pengajuan-item.card {
        background-color: #fff;
        border: 1px solid #e0e0e0;
        border-radius: 8px;
        box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        display: flex;
        flex-direction: column; /* Agar footer bisa di bawah */
    }
    .pengajuan-item .card-header {
        background-color: #f8f9fa;
        padding: 15px 20px;
        border-bottom: 1px solid #e0e0e0;
        display: flex;
        justify-content: space-between;
        align-items: center;
        border-top-left-radius: 8px;
        border-top-right-radius: 8px;
    }
    .pengajuan-item .card-header h3 {
        margin: 0;
        font-size: 1.3em;
        color: #007bff;
    }
    .pengajuan-item .card-body {
        padding: 20px;
        flex-grow: 1; /* Membuat body card mengisi ruang */
    }
    .pengajuan-item .card-body p {
        margin-bottom: 10px;
        color: #333;
        font-size: 0.95em;
        line-height: 1.5;
    }
    .pengajuan-item .card-body p strong {
        color: #495057;
    }
    .pengajuan-item .card-footer {
        background-color: #f8f9fa;
        padding: 10px 20px;
        border-top: 1px solid #e0e0e0;
        text-align: right;
        font-size: 0.85em;
        color: #6c757d;
        border-bottom-left-radius: 8px;
        border-bottom-right-radius: 8px;
    }
    .pengajuan-item .card-footer .btn {
        margin-left: 10px;
    }

    .catatan {
        background-color: #f1f3f5;
        border-left: 4px solid;
        padding: 10px 15px;
        margin-top: 15px;
        border-radius: 4px;
        font-size: 0.9em;
    }
    .catatan-dosen { border-left-color: #17a2b8; /* Info */ }
    .catatan-admin { border-left-color: #ffc107; /* Warning */ }
    .catatan strong { display: block; margin-bottom: 5px; color: #343a40; }
    .catatan p { margin-bottom: 0; color: #495057; }


    /* Styling untuk badge status */
    .status-badge {
        padding: 5px 10px;
        border-radius: 15px; /* Lebih bulat */
        font-size: 0.8em;
        font-weight: bold;
        color: #fff;
        text-transform: capitalize; /* Huruf pertama besar */
    }
    .status-draft { background-color: #6c757d; /* Abu-abu */ }
    .status-diajukan-mahasiswa { background-color: #ffc107; color:#212529; /* Kuning */ }
    .status-diverifikasi-dospem { background-color: #fd7e14; /* Orange */ }
    .status-disetujui-dospem { background-color: #20c997; /* Teal */ }
    .status-ditolak-dospem { background-color: #dc3545; /* Merah */ }
    .status-menunggu-konfirmasi-perusahaan { background-color: #6610f2; /* Indigo */ }
    .status-diterima-perusahaan { background-color: #198754; /* Hijau tua */ }
    .status-ditolak-perusahaan { background-color: #dc3545; /* Merah */ }
    .status-penentuan-dospem-kp { background-color: #0dcaf0; color:#212529; /* Cyan */ }
    .status-kp-berjalan { background-color: #0d6efd; /* Biru primer */ }
    .status-selesai-pelaksanaan { background-color: #28a745; /* Hijau */ }
    .status-laporan-disetujui { background-color: #d63384; /* Pink */ }
    .status-selesai-dinilai { background-color: #1f2023; /* Dark */ }
    .status-dibatalkan { background-color: #adb5bd; color:#212529; /* Abu-abu muda */ }


    /* Styling untuk message dan tombol (diasumsikan sudah ada di CSS global/header) */
    .message { padding: 10px 15px; margin-bottom: 20px; border-radius: 5px; border: 1px solid transparent; }
    .message.info { background-color: #d1ecf1; color: #0c5460; border-color: #bee5eb; }
    .message.error { background-color: #f8d7da; color: #721c24; border-color: #f5c6cb; }
    .btn-primary { color: #fff; background-color: #007bff; border-color: #007bff; }
    .btn-primary:hover { background-color: #0069d9; border-color: #0062cc; }
    .btn-info { color: #fff; background-color: #17a2b8; border-color: #17a2b8; }
    .btn-info:hover { background-color: #138496; border-color: #117a8b; }
    .btn-sm { padding: .25rem .5rem; font-size: .875rem; line-height: 1.5; border-radius: .2rem; }

</style>

<?php
require_once '../includes/footer.php';

if (isset($conn) && ($conn instanceof mysqli)) {
    $conn->close();
}
?>