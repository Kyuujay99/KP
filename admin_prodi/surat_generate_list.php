<?php
// /KP/admin_prodi/surat_generate_list.php

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

// Sertakan file koneksi database
require_once '../config/db_connect.php';

$list_pengajuan_siap_surat = [];
$error_db = '';

// 2. AMBIL DATA PENGAJUAN KP YANG SIAP DIBUATKAN SURAT PENGANTAR
if ($conn && ($conn instanceof mysqli)) {
    // Logika: Ambil pengajuan yang statusnya sudah disetujui dosen pembimbing
    // dan idealnya belum memiliki path surat pengantar.
    // Alur ini bisa disesuaikan, misalnya statusnya harus 'diterima_perusahaan' dulu baru bisa dibuat surat.
    // Untuk contoh ini, kita ambil yang sudah 'disetujui_dospem' atau 'diterima_perusahaan'.
    $status_siap_surat = ['disetujui_dospem', 'diterima_perusahaan', 'kp_berjalan'];
    $status_placeholders = implode(',', array_fill(0, count($status_siap_surat), '?'));

    $sql = "SELECT
                pk.id_pengajuan,
                m.nim,
                m.nama AS nama_mahasiswa,
                pk.judul_kp,
                pr.nama_perusahaan,
                pk.surat_pengantar_path
            FROM pengajuan_kp pk
            JOIN mahasiswa m ON pk.nim = m.nim
            LEFT JOIN perusahaan pr ON pk.id_perusahaan = pr.id_perusahaan
            WHERE pk.status_pengajuan IN ($status_placeholders)
            ORDER BY pk.updated_at ASC"; // Tampilkan yang paling lama menunggu

    $stmt = $conn->prepare($sql);
    if ($stmt) {
        // Spread operator (...) untuk bind_param dinamis
        $stmt->bind_param(str_repeat('s', count($status_siap_surat)), ...$status_siap_surat);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $list_pengajuan_siap_surat[] = $row;
            }
        }
        $stmt->close();
    } else {
        $error_db = "Gagal menyiapkan query: " . (($conn->error) ? htmlspecialchars($conn->error) : "Kesalahan tidak diketahui.");
    }
} else {
    $error_db = "Koneksi database gagal atau tidak valid.";
}

// Set judul halaman dan sertakan header
$page_title = "Manajemen & Generate Surat";
require_once '../includes/header.php';
?>

<div class="page-layout-wrapper">

    <?php require_once '../includes/sidebar_admin_prodi.php'; ?>

    <main class="main-content-area">
        <div class="list-container surat-generate-list">
            <h1><?php echo htmlspecialchars($page_title); ?></h1>
            <p>Halaman ini menampilkan daftar pengajuan KP yang siap untuk dibuatkan surat resmi, seperti Surat Pengantar.</p>
            <hr>

            <?php if (!empty($error_db)): ?>
                <div class="message error">
                    <p><?php echo $error_db; ?></p>
                </div>
            <?php endif; ?>

            <div class="card">
                <div class="card-header">
                    <h3><i class="icon-mail"></i> Daftar Surat Pengantar KP</h3>
                </div>
                <div class="card-body">
                    <?php if (empty($list_pengajuan_siap_surat) && empty($error_db)): ?>
                        <div class="message info">
                            <p>Saat ini tidak ada pengajuan KP yang memerlukan pembuatan Surat Pengantar (dengan status 'Disetujui Dospem' atau 'Diterima Perusahaan').</p>
                        </div>
                    <?php elseif (!empty($list_pengajuan_siap_surat)): ?>
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>No.</th>
                                        <th>NIM</th>
                                        <th>Nama Mahasiswa</th>
                                        <th>Judul KP</th>
                                        <th>Perusahaan Tujuan</th>
                                        <th>Status Surat</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php $counter = 1; ?>
                                    <?php foreach ($list_pengajuan_siap_surat as $pengajuan): ?>
                                        <tr>
                                            <td><?php echo $counter++; ?></td>
                                            <td><?php echo htmlspecialchars($pengajuan['nim']); ?></td>
                                            <td><?php echo htmlspecialchars($pengajuan['nama_mahasiswa']); ?></td>
                                            <td><?php echo htmlspecialchars($pengajuan['judul_kp']); ?></td>
                                            <td><?php echo htmlspecialchars($pengajuan['nama_perusahaan'] ?? 'N/A'); ?></td>
                                            <td>
                                                <?php if (empty($pengajuan['surat_pengantar_path'])): ?>
                                                    <span class="status-surat status-belum-dibuat">Belum Dibuat</span>
                                                <?php else: ?>
                                                    <span class="status-surat status-sudah-dibuat">Sudah Dibuat</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <a href="/KP/admin_prodi/surat_generate.php?tipe=pengantar&id_pengajuan=<?php echo $pengajuan['id_pengajuan']; ?>" class="btn btn-warning btn-sm" target="_blank" title="Generate atau lihat kembali surat pengantar">
                                                    <i class="icon-print"></i> Generate/View
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
            
            </div>
    </main>
</div>

<style>
    /* Asumsikan CSS umum dari header, sidebar, tabel, message, btn, card sudah ada */
    .surat-generate-list h1 { margin-top: 0; margin-bottom: 10px; }
    .surat-generate-list hr { margin-bottom: 20px; }
    .surat-generate-list p { margin-bottom: 15px; }
    .icon-mail::before { content: "✉️ "; }
    .icon-print::before { content: "🖨️ "; }

    .card .card-header h3 {
        margin: 0;
        font-size: 1.2em;
    }

    .status-surat {
        padding: 3px 8px;
        border-radius: 12px;
        font-size: 0.8em;
        font-weight: bold;
        color: #fff;
        white-space: nowrap;
    }
    .status-belum-dibuat { background-color: #ffc107; color: #212529; } /* Kuning */
    .status-sudah-dibuat { background-color: #28a745; } /* Hijau */
</style>

<?php
require_once '../includes/footer.php';

if (isset($conn) && ($conn instanceof mysqli)) {
    $conn->close();
}
?>