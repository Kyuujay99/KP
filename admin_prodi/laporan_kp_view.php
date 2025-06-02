<?php
// /KP/admin_prodi/laporan_kp_view.php

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

$laporan_data = [];
$error_db = '';

// Ambil parameter filter dari URL (GET)
$filter_tahun_angkatan = isset($_GET['tahun_angkatan']) && is_numeric($_GET['tahun_angkatan']) ? (int)$_GET['tahun_angkatan'] : '';
$filter_status_kp = isset($_GET['status_kp']) ? trim($_GET['status_kp']) : '';
$filter_prodi = isset($_GET['prodi']) ? trim($_GET['prodi']) : '';

$available_angkatan = [];
$available_prodi = [];
$available_status_kp = [ // Sesuaikan dengan ENUM atau kebutuhan laporan
    'kp_berjalan' => 'KP Sedang Berjalan',
    'selesai_pelaksanaan' => 'Selesai Pelaksanaan',
    'laporan_disetujui' => 'Laporan Disetujui',
    'selesai_dinilai' => 'Selesai Dinilai',
    'dibatalkan' => 'Dibatalkan',
    // Tambahkan status lain yang relevan untuk laporan
];


// 2. AMBIL DATA UNTUK FILTER DAN LAPORAN
if ($conn && ($conn instanceof mysqli)) {
    // Ambil tahun angkatan unik dari tabel mahasiswa untuk filter
    $sql_angkatan = "SELECT DISTINCT angkatan FROM mahasiswa ORDER BY angkatan DESC";
    $result_angkatan = $conn->query($sql_angkatan);
    if ($result_angkatan) {
        while ($row = $result_angkatan->fetch_assoc()) {
            if (!empty($row['angkatan'])) $available_angkatan[] = $row['angkatan'];
        }
        $result_angkatan->free();
    }

    // Ambil prodi unik dari tabel mahasiswa untuk filter
    $sql_prodi = "SELECT DISTINCT prodi FROM mahasiswa WHERE prodi IS NOT NULL AND prodi != '' ORDER BY prodi ASC";
    $result_prodi = $conn->query($sql_prodi);
    if ($result_prodi) {
        while ($row = $result_prodi->fetch_assoc()) {
            $available_prodi[] = $row['prodi'];
        }
        $result_prodi->free();
    }

    // Query utama untuk mengambil data laporan KP
    $sql_laporan = "SELECT
                        pk.id_pengajuan,
                        m.nim,
                        m.nama AS nama_mahasiswa,
                        m.prodi AS prodi_mahasiswa,
                        m.angkatan AS angkatan_mahasiswa,
                        pk.judul_kp,
                        pr.nama_perusahaan,
                        dp.nama_dosen AS nama_dosen_pembimbing,
                        pk.tanggal_mulai_rencana,
                        pk.tanggal_selesai_rencana,
                        pk.status_pengajuan
                    FROM pengajuan_kp pk
                    JOIN mahasiswa m ON pk.nim = m.nim
                    LEFT JOIN perusahaan pr ON pk.id_perusahaan = pr.id_perusahaan
                    LEFT JOIN dosen_pembimbing dp ON pk.nip_dosen_pembimbing_kp = dp.nip
                    WHERE 1=1"; // Klausul WHERE awal

    $params = [];
    $types = "";

    if (!empty($filter_tahun_angkatan)) {
        $sql_laporan .= " AND m.angkatan = ?";
        $params[] = $filter_tahun_angkatan;
        $types .= "i";
    }
    if (!empty($filter_status_kp)) {
        $sql_laporan .= " AND pk.status_pengajuan = ?";
        $params[] = $filter_status_kp;
        $types .= "s";
    }
    if (!empty($filter_prodi)) {
        $sql_laporan .= " AND m.prodi = ?";
        $params[] = $filter_prodi;
        $types .= "s";
    }

    $sql_laporan .= " ORDER BY m.angkatan DESC, m.prodi ASC, m.nama ASC, pk.id_pengajuan DESC";

    $stmt_laporan = $conn->prepare($sql_laporan);

    if ($stmt_laporan) {
        if (!empty($params)) {
            $stmt_laporan->bind_param($types, ...$params);
        }
        $stmt_laporan->execute();
        $result_laporan = $stmt_laporan->get_result();
        if ($result_laporan->num_rows > 0) {
            while ($row_lap = $result_laporan->fetch_assoc()) {
                $laporan_data[] = $row_lap;
            }
        }
        $stmt_laporan->close();
    } else {
        $error_db = "Gagal menyiapkan query laporan: " . (($conn->error) ? htmlspecialchars($conn->error) : "Kesalahan tidak diketahui.");
    }
} else {
    $error_db = "Koneksi database gagal atau tidak valid.";
}


// Set judul halaman dan sertakan header
$page_title = "Laporan Kerja Praktek Mahasiswa";
require_once '../includes/header.php';
?>

<div class="page-layout-wrapper">

    <?php require_once '../includes/sidebar_admin_prodi.php'; ?>

    <main class="main-content-area">
        <div class="list-container laporan-kp-container">
            <h1><?php echo htmlspecialchars($page_title); ?></h1>
            <p>Gunakan filter di bawah untuk menampilkan data mahasiswa yang melaksanakan Kerja Praktek.</p>
            <hr>

            <form action="/KP/admin_prodi/laporan_kp_view.php" method="GET" class="filter-form card mb-4">
                <div class="card-body">
                    <div class="filter-grid">
                        <div class="form-group">
                            <label for="tahun_angkatan">Tahun Angkatan:</label>
                            <select name="tahun_angkatan" id="tahun_angkatan" class="form-control">
                                <option value="">-- Semua Angkatan --</option>
                                <?php foreach ($available_angkatan as $angkatan): ?>
                                    <option value="<?php echo $angkatan; ?>" <?php echo ($filter_tahun_angkatan == $angkatan) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($angkatan); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="prodi">Program Studi:</label>
                            <select name="prodi" id="prodi" class="form-control">
                                <option value="">-- Semua Prodi --</option>
                                <?php foreach ($available_prodi as $prodi_item): ?>
                                    <option value="<?php echo htmlspecialchars($prodi_item); ?>" <?php echo ($filter_prodi == $prodi_item) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($prodi_item); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="status_kp">Status KP:</label>
                            <select name="status_kp" id="status_kp" class="form-control">
                                <option value="">-- Semua Status --</option>
                                <?php foreach ($available_status_kp as $value => $text): ?>
                                    <option value="<?php echo $value; ?>" <?php echo ($filter_status_kp == $value) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($text); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="filter-actions">
                        <button type="submit" class="btn btn-primary btn-sm">Terapkan Filter</button>
                        <a href="/KP/admin_prodi/laporan_kp_view.php" class="btn btn-secondary btn-sm">Reset Filter</a>
                    </div>
                </div>
            </form>
            

            <?php if (!empty($error_db)): ?>
                <div class="message error">
                    <p><?php echo $error_db; ?></p>
                </div>
            <?php endif; ?>

            <?php if (empty($laporan_data) && empty($error_db)): ?>
                <div class="message info">
                    <p>Tidak ada data laporan Kerja Praktek yang cocok dengan filter yang Anda pilih.</p>
                </div>
            <?php elseif (!empty($laporan_data)): ?>
                <div class="table-summary">
                    Total ditemukan: <strong><?php echo count($laporan_data); ?></strong> data pengajuan KP.
                    <?php if (isset($_GET['tahun_angkatan']) || isset($_GET['status_kp']) || isset($_GET['prodi'])): // Cek apakah ada filter aktif ?>
                    <button onclick="window.print()" class="btn btn-success btn-sm float-right no-print" style="margin-left:10px;"><i class="icon-print"></i> Cetak Laporan Ini</button>
                    <?php endif; ?>
                </div>
                <div class="table-responsive">
                    <table class="data-table report-table">
                        <thead>
                            <tr>
                                <th>No.</th>
                                <th>NIM</th>
                                <th>Nama Mahasiswa</th>
                                <th>Angkatan</th>
                                <th>Prodi</th>
                                <th>Judul KP</th>
                                <th>Perusahaan</th>
                                <th>Dosen Pembimbing</th>
                                <th>Periode Rencana</th>
                                <th>Status KP</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $counter = 1; ?>
                            <?php foreach ($laporan_data as $data): ?>
                                <tr>
                                    <td><?php echo $counter++; ?></td>
                                    <td><?php echo htmlspecialchars($data['nim']); ?></td>
                                    <td><?php echo htmlspecialchars($data['nama_mahasiswa']); ?></td>
                                    <td><?php echo htmlspecialchars($data['angkatan_mahasiswa']); ?></td>
                                    <td><?php echo htmlspecialchars($data['prodi_mahasiswa']); ?></td>
                                    <td><?php echo htmlspecialchars($data['judul_kp']); ?></td>
                                    <td><?php echo $data['nama_perusahaan'] ? htmlspecialchars($data['nama_perusahaan']) : '<em>-</em>'; ?></td>
                                    <td><?php echo $data['nama_dosen_pembimbing'] ? htmlspecialchars($data['nama_dosen_pembimbing']) : '<em>-</em>'; ?></td>
                                    <td>
                                        <?php echo date("d M Y", strtotime($data['tanggal_mulai_rencana'])); ?> - 
                                        <?php echo date("d M Y", strtotime($data['tanggal_selesai_rencana'])); ?>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?php echo strtolower(str_replace([' ', '_'], '-', $data['status_pengajuan'])); ?>">
                                            <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $data['status_pengajuan']))); ?>
                                        </span>
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
    /* Asumsikan CSS umum sudah ada dari header, sidebar, tabel, message, btn, status-badge, card */
    .laporan-kp-container h1 { margin-top: 0; margin-bottom: 10px; }
    .laporan-kp-container hr { margin-bottom: 20px; }
    .laporan-kp-container p { margin-bottom: 15px; }
    .icon-print::before { content: "🖨️ "; } /* Ikon sederhana untuk print */

    .filter-form.card {
        background-color: #f8f9fa; /* Latar sedikit beda untuk form filter */
    }
    .filter-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 15px;
        margin-bottom: 15px;
    }
    .filter-form .form-group {
        margin-bottom: 0; /* Hapus margin bawah dari .form-group di dalam filter */
    }
    .filter-form .form-group label {
        font-weight: bold;
        color: #555;
        margin-bottom: 5px; /* Sedikit jarak ke select */
        display: block;
    }
    .filter-form .form-control { /* Kelas umum untuk select/input di form */
        width: 100%;
        padding: 8px 10px;
        border: 1px solid #ccc;
        border-radius: 4px;
    }
    .filter-actions {
        margin-top: 10px;
        text-align: right; /* Tombol filter di kanan */
    }
    .filter-actions .btn {
        margin-left: 10px;
    }
    .table-summary {
        margin-bottom: 15px;
        font-weight: bold;
        padding: 10px;
        background-color: #e9ecef;
        border-radius: 4px;
    }
    .float-right { float: right; }

    .report-table th, .report-table td {
        font-size: 0.85em; /* Ukuran font lebih kecil untuk tabel laporan yang padat */
        padding: 8px;
    }
    
    @media print {
        /* Sembunyikan elemen yang tidak perlu dicetak */
        .navbar, .sidebar-admin-prodi, .filter-form, .btn-light, .print-button-container, .no-print, footer, .page-layout-wrapper > main > .list-container > a.btn.mb-3, .laporan-kp-container > p:first-of-type, .laporan-kp-container > hr:first-of-type, .filter-actions > a.btn-secondary  {
            display: none !important;
        }
        .page-layout-wrapper, .main-content-area, .laporan-kp-container, body, html {
            margin: 0 !important;
            padding: 0 !important;
            box-shadow: none !important;
            border: none !important;
            width: auto !important; /* Atau 100% */
            background-color: #fff !important;
        }
        .laporan-kp-container h1 {
            font-size: 16pt;
            text-align: center;
            margin-bottom: 20px;
        }
        .table-summary {
            font-size: 10pt;
            background-color: transparent !important;
            padding: 5px 0;
            text-align:left;
        }
        .data-table {
            font-size: 9pt; /* Perkecil lagi untuk print */
            width: 100% !important;
            margin-top: 5px;
        }
        .data-table th, .data-table td {
            padding: 4px 6px;
            border: 1px solid #999; /* Border lebih jelas untuk print */
        }
         .data-table th {
            background-color: #eee !important; /* Warna lebih terang untuk print */
            color: #000 !important;
        }
        .status-badge { /* Atur agar status badge terbaca saat print */
            color: #000 !important; /* Teks hitam */
            background-color: transparent !important; /* Hilangkan background berwarna */
            border: 1px solid #ccc;
            padding: 2px 4px;
        }
    }
</style>

<?php
require_once '../includes/footer.php';

if (isset($conn) && ($conn instanceof mysqli)) {
    $conn->close();
}
?>