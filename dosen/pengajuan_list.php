<?php
// /KP/dosen/pengajuan_list.php

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

$nip_dosen_login = $_SESSION['user_id']; // NIP dosen yang login

// Sertakan file koneksi database
require_once '../config/db_connect.php';

$list_pengajuan_kp = [];
$error_db = '';

// 2. AMBIL DATA PENGAJUAN KP YANG PERLU DITINJAU OLEH DOSEN INI
if ($conn && ($conn instanceof mysqli)) {
    // Status yang relevan untuk dosen tinjau/verifikasi
    // Sesuaikan array status ini dengan alur kerja sistem Anda
    $relevan_statuses = ['diajukan_mahasiswa', 'diverifikasi_dospem'];
    $status_placeholders = implode(',', array_fill(0, count($relevan_statuses), '?')); // Membuat ?,?,? untuk IN clause

    $sql = "SELECT
                pk.id_pengajuan,
                m.nim,
                m.nama AS nama_mahasiswa,
                pk.judul_kp,
                pr.nama_perusahaan,
                pk.tanggal_pengajuan,
                pk.status_pengajuan
            FROM pengajuan_kp pk
            JOIN mahasiswa m ON pk.nim = m.nim
            LEFT JOIN perusahaan pr ON pk.id_perusahaan = pr.id_perusahaan
            WHERE pk.nip_dosen_pembimbing_kp = ? 
              AND pk.status_pengajuan IN ($status_placeholders)
            ORDER BY pk.tanggal_pengajuan ASC, pk.id_pengajuan ASC"; // Tampilkan yang paling lama dulu

    $stmt = $conn->prepare($sql);

    if ($stmt) {
        // Bind NIP dosen, lalu bind semua status yang relevan
        // Tipe data untuk NIP adalah 's' (string), tipe untuk status juga 's'
        $types = 's' . str_repeat('s', count($relevan_statuses));
        $bind_params = array_merge([$nip_dosen_login], $relevan_statuses);

        // Perlu memanggil bind_param dengan referensi
        $ref_params = [];
        foreach ($bind_params as $key => $value) {
            $ref_params[$key] = &$bind_params[$key]; // Buat referensi
        }
        array_unshift($ref_params, $types); // Tambahkan tipe data di awal array untuk call_user_func_array

        call_user_func_array([$stmt, 'bind_param'], $ref_params);

        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $list_pengajuan_kp[] = $row;
            }
        }
        $stmt->close();
    } else {
        $error_db = "Gagal menyiapkan query: " . (($conn->error) ? htmlspecialchars($conn->error) : "Kesalahan tidak diketahui.");
    }
    // Koneksi akan ditutup di footer
} else {
    $error_db = "Koneksi database gagal atau tidak valid.";
}

// Set judul halaman dan sertakan header
$page_title = "Daftar Pengajuan KP untuk Diverifikasi";
require_once '../includes/header.php';
?>

<div class="page-layout-wrapper">

    <?php require_once '../includes/sidebar_dosen.php'; ?>

    <main class="main-content-area">
        <div class="list-container pengajuan-kp-dosen-list">
            <h1><?php echo htmlspecialchars($page_title); ?></h1>
            <p>Berikut adalah daftar pengajuan Kerja Praktek mahasiswa yang memerlukan tinjauan atau verifikasi dari Anda.</p>
            <hr>

            <?php if (!empty($error_db)): ?>
                <div class="message error">
                    <p><?php echo $error_db; ?></p>
                </div>
            <?php endif; ?>

            <?php if (empty($list_pengajuan_kp) && empty($error_db)): ?>
                <div class="message info">
                    <p>Saat ini tidak ada pengajuan Kerja Praktek yang memerlukan tindakan dari Anda.</p>
                </div>
            <?php elseif (!empty($list_pengajuan_kp)): ?>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>No.</th>
                                <th>NIM</th>
                                <th>Nama Mahasiswa</th>
                                <th>Judul KP</th>
                                <th>Perusahaan</th>
                                <th>Tgl. Diajukan</th>
                                <th>Status</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $counter = 1; ?>
                            <?php foreach ($list_pengajuan_kp as $pengajuan): ?>
                                <tr>
                                    <td><?php echo $counter++; ?></td>
                                    <td><?php echo htmlspecialchars($pengajuan['nim']); ?></td>
                                    <td><?php echo htmlspecialchars($pengajuan['nama_mahasiswa']); ?></td>
                                    <td><?php echo htmlspecialchars($pengajuan['judul_kp']); ?></td>
                                    <td><?php echo $pengajuan['nama_perusahaan'] ? htmlspecialchars($pengajuan['nama_perusahaan']) : '<em>Belum Ada / Diajukan Manual</em>'; ?></td>
                                    <td><?php echo date("d M Y", strtotime($pengajuan['tanggal_pengajuan'])); ?></td>
                                    <td>
                                        <span class="status-badge status-<?php echo strtolower(str_replace(' ', '-', str_replace('_', '-', $pengajuan['status_pengajuan']))); ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', htmlspecialchars($pengajuan['status_pengajuan']))); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="/KP/dosen/pengajuan_verifikasi_detail.php?id_pengajuan=<?php echo $pengajuan['id_pengajuan']; ?>" class="btn btn-primary btn-sm">
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
    /* Asumsikan CSS untuk .page-layout-wrapper, .sidebar-dosen, .main-content-area sudah ada */
    .list-container h1 { margin-top: 0; margin-bottom: 10px; font-size: 1.8em; }
    .list-container hr { margin-bottom: 25px; }
    .list-container p { margin-bottom: 20px; font-size:0.95em; color:#555; }

    .table-responsive {
        overflow-x: auto; /* Agar tabel bisa di-scroll horizontal di layar kecil */
    }
    .data-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 20px;
        font-size: 0.9em;
    }
    .data-table th, .data-table td {
        border: 1px solid #ddd;
        padding: 10px 12px; /* Padding lebih nyaman */
        text-align: left;
        vertical-align: top; /* Jika ada konten multi-baris */
    }
    .data-table th {
        background-color: #f2f2f2; /* Latar header tabel */
        font-weight: bold;
        color: #333;
    }
    .data-table tbody tr:nth-child(even) {
        background-color: #f9f9f9; /* Zebra striping */
    }
    .data-table tbody tr:hover {
        background-color: #f1f1f1; /* Efek hover */
    }
    .data-table .btn-sm {
        padding: 5px 8px; /* Ukuran tombol aksi lebih kecil */
        font-size: 0.85em;
    }

    /* Styling untuk badge status (sama seperti di halaman mahasiswa, pastikan konsisten atau global) */
    .status-badge {
        padding: 5px 10px; border-radius: 15px; font-size: 0.8em;
        font-weight: bold; color: #fff; text-transform: capitalize;
    }
    .status-draft { background-color: #6c757d; }
    .status-diajukan-mahasiswa { background-color: #ffc107; color:#212529; }
    .status-diverifikasi-dospem { background-color: #fd7e14; }
    .status-disetujui-dospem { background-color: #20c997; }
    .status-ditolak-dospem { background-color: #dc3545; }
    /* Tambahkan semua kelas status lainnya yang relevan */
    .status-kp-berjalan { background-color: #0d6efd; }


    /* Styling untuk message (sudah ada di contoh sebelumnya atau di header.php) */
    .message { padding: 10px 15px; margin-bottom: 20px; border-radius: 5px; border: 1px solid transparent; }
    .message.info { background-color: #d1ecf1; color: #0c5460; border-color: #bee5eb; }
    .message.error { background-color: #f8d7da; color: #721c24; border-color: #f5c6cb; }
    /* Tombol (pastikan konsisten dengan global CSS) */
    .btn-primary { color: #fff; background-color: #007bff; border-color: #007bff; }
    .btn-primary:hover { background-color: #0069d9; border-color: #0062cc; }
</style>

<?php
require_once '../includes/footer.php';

if (isset($conn) && ($conn instanceof mysqli)) {
    $conn->close();
}
?>