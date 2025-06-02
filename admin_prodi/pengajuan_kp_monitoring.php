<?php
// /KP/admin_prodi/pengajuan_kp_monitoring.php

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

$list_semua_pengajuan = [];
$error_db = '';
$filter_status = isset($_GET['status']) ? $_GET['status'] : ''; // Ambil filter status dari URL

// 2. AMBIL SEMUA DATA PENGAJUAN KP DARI DATABASE
if ($conn && ($conn instanceof mysqli)) {
    $sql = "SELECT
                pk.id_pengajuan,
                m.nim,
                m.nama AS nama_mahasiswa,
                m.prodi AS prodi_mahasiswa,
                pk.judul_kp,
                pr.nama_perusahaan,
                dp.nama_dosen AS nama_dosen_pembimbing,
                pk.tanggal_pengajuan,
                pk.status_pengajuan
            FROM pengajuan_kp pk
            JOIN mahasiswa m ON pk.nim = m.nim
            LEFT JOIN perusahaan pr ON pk.id_perusahaan = pr.id_perusahaan
            LEFT JOIN dosen_pembimbing dp ON pk.nip_dosen_pembimbing_kp = dp.nip";

    // Tambahkan filter status jika ada
    $params = [];
    $types = "";
    if (!empty($filter_status)) {
        $sql .= " WHERE pk.status_pengajuan = ?";
        $params[] = $filter_status;
        $types .= "s";
    }

    $sql .= " ORDER BY pk.tanggal_pengajuan DESC, pk.id_pengajuan DESC";

    $stmt = $conn->prepare($sql);

    if ($stmt) {
        if (!empty($filter_status)) {
            $stmt->bind_param($types, ...$params); // Spread operator untuk bind_param dinamis
        }
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $list_semua_pengajuan[] = $row;
            }
        }
        $stmt->close();
    } else {
        $error_db = "Gagal menyiapkan query: " . (($conn->error) ? htmlspecialchars($conn->error) : "Kesalahan tidak diketahui.");
    }
} else {
    $error_db = "Koneksi database gagal atau tidak valid.";
}

// Daftar status untuk filter dropdown (sesuai ENUM di tabel pengajuan_kp)
$opsi_status_filter = [
    'draft' => 'Draft',
    'diajukan_mahasiswa' => 'Diajukan Mahasiswa',
    'diverifikasi_dospem' => 'Diverifikasi Dosen Pembimbing (awal)',
    'disetujui_dospem' => 'Disetujui Dosen Pembimbing',
    'ditolak_dospem' => 'Ditolak Dosen Pembimbing',
    'menunggu_konfirmasi_perusahaan' => 'Menunggu Konfirmasi Perusahaan',
    'diterima_perusahaan' => 'Diterima Perusahaan',
    'ditolak_perusahaan' => 'Ditolak Perusahaan',
    'penentuan_dospem_kp' => 'Penentuan Dosen Pembimbing KP (oleh Admin)',
    'kp_berjalan' => 'KP Berjalan',
    'selesai_pelaksanaan' => 'Selesai Pelaksanaan',
    'laporan_disetujui' => 'Laporan Disetujui',
    'selesai_dinilai' => 'Selesai Dinilai',
    'dibatalkan' => 'Dibatalkan'
];


// Set judul halaman dan sertakan header
$page_title = "Monitoring Semua Pengajuan Kerja Praktek";
if(!empty($filter_status)) {
    $page_title .= " (Status: " . (isset($opsi_status_filter[$filter_status]) ? $opsi_status_filter[$filter_status] : $filter_status) . ")";
}
require_once '../includes/header.php';
?>

<div class="page-layout-wrapper">

    <?php require_once '../includes/sidebar_admin_prodi.php'; ?>

    <main class="main-content-area">
        <div class="list-container monitoring-kp-list">
            <h1><?php echo htmlspecialchars($page_title); ?></h1>
            <p>Halaman ini menampilkan semua pengajuan Kerja Praktek dari mahasiswa. Gunakan filter untuk menyaring berdasarkan status.</p>
            <hr>

            <form action="/KP/admin_prodi/pengajuan_kp_monitoring.php" method="GET" class="filter-form">
                <div class="form-group">
                    <label for="status">Filter berdasarkan Status:</label>
                    <select name="status" id="status" onchange="this.form.submit()">
                        <option value="">-- Semua Status --</option>
                        <?php foreach ($opsi_status_filter as $value => $text): ?>
                            <option value="<?php echo $value; ?>" <?php echo ($filter_status == $value) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($text); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php if(!empty($filter_status)): ?>
                    <a href="/KP/admin_prodi/pengajuan_kp_monitoring.php" class="btn btn-secondary btn-sm">Hapus Filter</a>
                <?php endif; ?>
            </form>
            <hr class="filter-hr">


            <?php if (!empty($error_db)): ?>
                <div class="message error">
                    <p><?php echo $error_db; ?></p>
                </div>
            <?php endif; ?>

            <?php if (empty($list_semua_pengajuan) && empty($error_db)): ?>
                <div class="message info">
                    <p>Tidak ada data pengajuan Kerja Praktek yang ditemukan<?php echo !empty($filter_status) ? " dengan status yang dipilih" : ""; ?>.</p>
                </div>
            <?php elseif (!empty($list_semua_pengajuan)): ?>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>NIM</th>
                                <th>Nama Mahasiswa</th>
                                <th>Prodi</th>
                                <th>Judul KP</th>
                                <th>Perusahaan</th>
                                <th>Dosen Pembimbing</th>
                                <th>Tgl. Diajukan</th>
                                <th>Status</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($list_semua_pengajuan as $pengajuan): ?>
                                <tr>
                                    <td><?php echo $pengajuan['id_pengajuan']; ?></td>
                                    <td><?php echo htmlspecialchars($pengajuan['nim']); ?></td>
                                    <td><?php echo htmlspecialchars($pengajuan['nama_mahasiswa']); ?></td>
                                    <td><?php echo htmlspecialchars($pengajuan['prodi_mahasiswa'] ?: '-'); ?></td>
                                    <td><?php echo htmlspecialchars($pengajuan['judul_kp']); ?></td>
                                    <td><?php echo $pengajuan['nama_perusahaan'] ? htmlspecialchars($pengajuan['nama_perusahaan']) : '<em>N/A</em>'; ?></td>
                                    <td><?php echo $pengajuan['nama_dosen_pembimbing'] ? htmlspecialchars($pengajuan['nama_dosen_pembimbing']) : '<em>Belum Ada</em>'; ?></td>
                                    <td><?php echo date("d M Y", strtotime($pengajuan['tanggal_pengajuan'])); ?></td>
                                    <td>
                                        <span class="status-badge status-<?php echo strtolower(str_replace([' ', '_'], '-', $pengajuan['status_pengajuan'])); ?>">
                                            <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $pengajuan['status_pengajuan']))); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="/KP/admin_prodi/pengajuan_kp_detail_admin.php?id_pengajuan=<?php echo $pengajuan['id_pengajuan']; ?>" class="btn btn-info btn-sm">
                                            Detail/Kelola
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
    /* Asumsikan CSS untuk layout, tabel, status-badge, message, btn sudah ada dari halaman sebelumnya atau global */
    .list-container h1 { margin-top: 0; margin-bottom: 10px; }
    .list-container hr { margin-bottom: 20px; }
    .list-container p { margin-bottom: 15px; }

    .filter-form {
        background-color: #f9f9f9;
        padding: 15px;
        border-radius: 5px;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 15px; /* Jarak antar elemen form */
    }
    .filter-form .form-group {
        margin-bottom: 0; /* Hapus margin bawah default dari .form-group jika ada */
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .filter-form label {
        font-weight: bold;
        color: #555;
        margin-bottom: 0; /* Hapus margin bawah default dari label */
    }
    .filter-form select {
        padding: 8px 10px;
        border: 1px solid #ccc;
        border-radius: 4px;
        min-width: 200px; /* Agar select tidak terlalu kecil */
    }
    .filter-hr { /* Garis pemisah setelah filter */
        margin-top:0;
        margin-bottom:25px;
    }
    .btn-secondary { /* Untuk tombol Hapus Filter */
        color: #fff;
        background-color: #6c757d;
        border-color: #6c757d;
    }
    .btn-secondary:hover {
        background-color: #5a6268;
        border-color: #545b62;
    }

    /* Styling untuk badge status (pastikan konsisten atau global di header.php) */
    .status-badge {
        padding: 5px 10px; border-radius: 15px; font-size: 0.8em;
        font-weight: bold; color: #fff; text-transform: capitalize; white-space: nowrap;
    }
    /* Contoh beberapa status, lengkapi dari halaman sebelumnya */
    .status-draft { background-color: #6c757d; }
    .status-diajukan-mahasiswa { background-color: #ffc107; color:#212529; }
    .status-diverifikasi-dospem { background-color: #fd7e14; }
    .status-disetujui-dospem { background-color: #20c997; }
    .status-ditolak-dospem { background-color: #dc3545; }
    .status-menunggu-konfirmasi-perusahaan { background-color: #6610f2; }
    .status-diterima-perusahaan { background-color: #198754; }
    .status-ditolak-perusahaan { background-color: #dc3545; }
    .status-penentuan-dospem-kp { background-color: #0dcaf0; color:#212529; }
    .status-kp-berjalan { background-color: #0d6efd; }
    .status-selesai-pelaksanaan { background-color: #28a745; }
    .status-laporan-disetujui { background-color: #d63384; }
    .status-selesai-dinilai { background-color: #1f2023; }
    .status-dibatalkan { background-color: #adb5bd; color:#212529; }

</style>

<?php
require_once '../includes/footer.php';

if (isset($conn) && ($conn instanceof mysqli)) {
    $conn->close();
}
?>