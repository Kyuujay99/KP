<?php
// /KP/perusahaan/pengajuan_kp_masuk.php

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// 1. OTENTIKASI DAN OTORISASI
require_once '../includes/auth_check.php';
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'perusahaan') {
    header("Location: /KP/index.php?error=unauthorized_perusahaan");
    exit();
}

$id_perusahaan_login = $_SESSION['user_id'];
$nama_perusahaan_login = $_SESSION['user_nama'];

require_once '../config/db_connect.php';

$list_pengajuan_masuk = [];
$error_db = '';

// 2. AMBIL DATA PENGAJUAN YANG MENUNGGU KONFIRMASI DARI PERUSAHAAN INI
if ($conn && ($conn instanceof mysqli)) {
    $sql = "SELECT
                pk.id_pengajuan,
                m.nim,
                m.nama AS nama_mahasiswa,
                m.prodi,
                pk.judul_kp,
                pk.tanggal_pengajuan AS tanggal_diajukan_kampus
            FROM pengajuan_kp pk
            JOIN mahasiswa m ON pk.nim = m.nim
            WHERE pk.id_perusahaan = ?
              AND pk.status_pengajuan = 'menunggu_konfirmasi_perusahaan'
            ORDER BY pk.tanggal_pengajuan ASC";

    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("i", $id_perusahaan_login);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $list_pengajuan_masuk[] = $row;
        }
        $stmt->close();
    } else {
        $error_db = "Gagal menyiapkan query: " . $conn->error;
    }
} else {
    $error_db = "Koneksi database gagal.";
}

$page_title = "Pengajuan KP Masuk";
require_once '../includes/header.php';
?>

<div class="main-content-full">
    <div class="list-container">
        <h1><?php echo htmlspecialchars($page_title); ?></h1>
        <p>Berikut adalah daftar pengajuan Kerja Praktek dari mahasiswa yang menunggu konfirmasi (penerimaan/penolakan) dari pihak Anda.</p>
        <hr>

        <?php if (!empty($error_db)): ?>
            <div class="message error"><p><?php echo $error_db; ?></p></div>
        <?php endif; ?>

        <?php if (empty($list_pengajuan_masuk) && empty($error_db)): ?>
            <div class="message info">
                <p>Saat ini tidak ada pengajuan KP yang menunggu konfirmasi dari Anda.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>No.</th>
                            <th>Tgl. Diajukan</th>
                            <th>Mahasiswa</th>
                            <th>Program Studi</th>
                            <th>Judul/Topik KP</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $counter = 1; ?>
                        <?php foreach ($list_pengajuan_masuk as $pengajuan): ?>
                            <tr>
                                <td><?php echo $counter++; ?></td>
                                <td><?php echo date("d M Y", strtotime($pengajuan['tanggal_diajukan_kampus'])); ?></td>
                                <td><?php echo htmlspecialchars($pengajuan['nama_mahasiswa']); ?> (<?php echo htmlspecialchars($pengajuan['nim']); ?>)</td>
                                <td><?php echo htmlspecialchars($pengajuan['prodi']); ?></td>
                                <td><?php echo htmlspecialchars($pengajuan['judul_kp']); ?></td>
                                <td>
                                    <a href="pengajuan_kp_konfirmasi.php?id_pengajuan=<?php echo $pengajuan['id_pengajuan']; ?>" class="btn btn-primary btn-sm">
                                        Lihat & Konfirmasi
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

<?php
require_once '../includes/footer.php';
if (isset($conn) && $conn) {
    $conn->close();
}
?>

<style>
/* ... CSS untuk tabel dan layout ... */
.list-container { max-width: 1200px; margin: 20px auto; padding: 2rem; background-color: #fff; border-radius: var(--border-radius); box-shadow: var(--card-shadow); }
.table-responsive { overflow-x: auto; }
.data-table { width: 100%; border-collapse: collapse; }
.data-table th, .data-table td { padding: 12px 15px; border-bottom: 1px solid var(--border-color); text-align: left; }
.data-table th { background-color: #f8f9fa; font-weight: 600; }
.data-table tbody tr:hover { background-color: #f1f7ff; }
.btn-primary { background-color: var(--primary-color); color: white; border: none; }
.btn-primary:hover { background-color: var(--primary-hover); }
.message { padding: 1rem; border-radius: 8px; margin-bottom: 1rem; }
.message.info { background-color: #e9f5ff; color: #0056b3; }
.message.error { background-color: #f8d7da; color: #721c24; }
</style>