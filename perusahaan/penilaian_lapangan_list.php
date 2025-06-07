<?php
// /KP/perusahaan/penilaian_lapangan_list.php

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// 1. OTENTIKASI DAN OTORISASI
require_once '../includes/auth_check.php';
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'perusahaan') {
    session_unset();
    session_destroy();
    header("Location: /KP/index.php?error=unauthorized_perusahaan");
    exit();
}

$id_perusahaan_login = $_SESSION['user_id'];
$nama_perusahaan_login = $_SESSION['user_nama'];

// Sertakan file koneksi database
require_once '../config/db_connect.php';

$list_mahasiswa_penilaian = [];
$error_db = '';

// 2. AMBIL DATA MAHASISWA YANG PERLU PENILAIAN DARI PEMBIMBING LAPANGAN
if ($conn && ($conn instanceof mysqli)) {
    // Logika: Ambil mahasiswa yang status KP-nya 'kp_berjalan' atau 'selesai_pelaksanaan'
    // DAN belum memiliki entri 'nilai_pembimbing_lapangan' di tabel nilai_kp.
    $sql = "SELECT
                m.nim,
                m.nama AS nama_mahasiswa,
                pk.id_pengajuan,
                pk.judul_kp,
                pk.status_pengajuan,
                pk.tanggal_mulai_rencana,
                pk.tanggal_selesai_rencana
            FROM pengajuan_kp pk
            JOIN mahasiswa m ON pk.nim = m.nim
            WHERE pk.id_perusahaan = ? 
              AND pk.status_pengajuan IN ('kp_berjalan', 'selesai_pelaksanaan')
              AND NOT EXISTS (
                  SELECT 1 
                  FROM nilai_kp nk 
                  WHERE nk.id_pengajuan = pk.id_pengajuan 
                  AND nk.nilai_pembimbing_lapangan IS NOT NULL
              )
            ORDER BY pk.tanggal_selesai_rencana ASC, m.nama ASC";

    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("i", $id_perusahaan_login);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $list_mahasiswa_penilaian[] = $row;
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
$page_title = "Input Penilaian Lapangan";
require_once '../includes/header.php';
?>

<div class="page-layout-wrapper">

    <?php require_once '../includes/sidebar_perusahaan.php'; ?>

    <main class="main-content-area">
        <div class="list-container penilaian-lapangan-list">
            <h1><?php echo htmlspecialchars($page_title); ?></h1>
            <p>Halaman ini berisi daftar mahasiswa Kerja Praktek yang telah menyelesaikan atau sedang dalam tahap akhir KP dan memerlukan penilaian dari Anda sebagai Pembimbing Lapangan.</p>
            <hr>

            <?php if (!empty($error_db)): ?>
                <div class="message error">
                    <p><?php echo $error_db; ?></p>
                </div>
            <?php endif; ?>

            <?php if (empty($list_mahasiswa_penilaian) && empty($error_db)): ?>
                <div class="message info">
                    <p>Saat ini tidak ada mahasiswa yang memerlukan penilaian lapangan dari Anda. Semua mahasiswa yang relevan telah dinilai.</p>
                    <p><a href="/KP/perusahaan/mahasiswa_kp_list.php">Lihat daftar semua mahasiswa KP di sini</a>.</p>
                </div>
            <?php elseif (!empty($list_mahasiswa_penilaian)): ?>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>No.</th>
                                <th>NIM</th>
                                <th>Nama Mahasiswa</th>
                                <th>Judul KP</th>
                                <th>Periode Pelaksanaan</th>
                                <th>Status KP</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $counter = 1; ?>
                            <?php foreach ($list_mahasiswa_penilaian as $mhs): ?>
                                <tr>
                                    <td><?php echo $counter++; ?></td>
                                    <td><?php echo htmlspecialchars($mhs['nim']); ?></td>
                                    <td><?php echo htmlspecialchars($mhs['nama_mahasiswa']); ?></td>
                                    <td><?php echo htmlspecialchars($mhs['judul_kp']); ?></td>
                                    <td>
                                        <?php echo date("d M Y", strtotime($mhs['tanggal_mulai_rencana'])); ?>
                                        - 
                                        <?php echo date("d M Y", strtotime($mhs['tanggal_selesai_rencana'])); ?>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?php echo strtolower(str_replace([' ', '_'], '-', $mhs['status_pengajuan'])); ?>">
                                            <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $mhs['status_pengajuan']))); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="/KP/perusahaan/penilaian_lapangan_form.php?id_pengajuan=<?php echo $mhs['id_pengajuan']; ?>" class="btn btn-success btn-sm">
                                            <i class="icon-pencil"></i> Input Nilai
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
    /* Asumsikan CSS umum sudah ada dari header, sidebar, tabel, message, btn, status-badge, dll. */
    /* Namun, sesuai permintaan, saya sertakan kembali CSS yang relevan. */
    .penilaian-lapangan-list h1 { margin-top: 0; margin-bottom: 10px; }
    .penilaian-lapangan-list hr { margin-bottom: 20px; }
    .penilaian-lapangan-list p { margin-bottom: 15px; }
    .icon-pencil::before { content: "📝 "; }

    .table-responsive {
        overflow-x: auto;
    }
    .data-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 20px;
    }
    .data-table th, .data-table td {
        border: 1px solid #ddd;
        padding: 10px 12px;
        text-align: left;
        vertical-align: middle;
    }
    .data-table th {
        background-color: #f2f2f2;
        font-weight: bold;
    }
    .data-table tbody tr:nth-child(even) {
        background-color: #f9f9f9;
    }
    .data-table tbody tr:hover {
        background-color: #f1f1f1;
    }

    .status-badge {
        padding: 5px 10px;
        border-radius: 15px;
        font-size: 0.8em;
        font-weight: bold;
        color: #fff;
        text-transform: capitalize;
        white-space: nowrap;
    }
    .status-kp-berjalan { background-color: #0d6efd; }
    .status-selesai-pelaksanaan { background-color: #28a745; }

    .message { padding: 10px 15px; margin-bottom: 20px; border-radius: 5px; border: 1px solid transparent; }
    .message.info { background-color: #d1ecf1; color: #0c5460; border-color: #bee5eb; }
    .message.error { background-color: #f8d7da; color: #721c24; border-color: #f5c6cb; }
    .message.info a { color: #0c5460; font-weight: bold; }
</style>

<?php
require_once '../includes/footer.php';

if (isset($conn) && ($conn instanceof mysqli)) {
    $conn->close();
}
?>