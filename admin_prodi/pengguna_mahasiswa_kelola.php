<?php
// /KP/admin_prodi/pengguna_mahasiswa_kelola.php

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

$list_mahasiswa = [];
$error_message = '';
$success_message = '';

// 2. PROSES AKSI (UBAH STATUS AKUN) JIKA ADA PARAMETER GET
if (isset($_GET['action']) && isset($_GET['nim'])) {
    $action = $_GET['action'];
    $nim_aksi = $_GET['nim'];
    $new_status = '';

    if ($action === 'activate') {
        $new_status = 'active';
    } elseif ($action === 'suspend') {
        $new_status = 'suspended';
    } elseif ($action === 'reactivate') { // Jika ingin mengaktifkan kembali dari suspended
        $new_status = 'active';
    }

    if (!empty($new_status) && $conn && ($conn instanceof mysqli)) {
        // Pastikan NIM ada sebelum update
        $sql_check_nim = "SELECT nim FROM mahasiswa WHERE nim = ?";
        $stmt_check_nim = $conn->prepare($sql_check_nim);
        $stmt_check_nim->bind_param("s", $nim_aksi);
        $stmt_check_nim->execute();
        $result_check_nim = $stmt_check_nim->get_result();

        if ($result_check_nim->num_rows === 1) {
            $sql_update_status = "UPDATE mahasiswa SET status_akun = ? WHERE nim = ?";
            $stmt_update_status = $conn->prepare($sql_update_status);
            if ($stmt_update_status) {
                $stmt_update_status->bind_param("ss", $new_status, $nim_aksi);
                if ($stmt_update_status->execute()) {
                    if ($stmt_update_status->affected_rows > 0) {
                        $success_message = "Status akun untuk NIM " . htmlspecialchars($nim_aksi) . " berhasil diubah menjadi " . ucfirst($new_status) . ".";
                    } else {
                        $error_message = "Tidak ada perubahan status, mungkin status sudah " . ucfirst($new_status) . ".";
                    }
                } else {
                    $error_message = "Gagal mengubah status akun: " . htmlspecialchars($stmt_update_status->error);
                }
                $stmt_update_status->close();
            } else {
                $error_message = "Gagal menyiapkan statement update status: " . htmlspecialchars($conn->error);
            }
        } else {
            $error_message = "Mahasiswa dengan NIM " . htmlspecialchars($nim_aksi) . " tidak ditemukan.";
        }
        $stmt_check_nim->close();
    } elseif (empty($new_status)) {
        $error_message = "Tindakan tidak valid.";
    } else {
        $error_message = "Koneksi database gagal atau tidak valid.";
    }
}


// 3. AMBIL SEMUA DATA MAHASISWA DARI DATABASE
if ($conn && ($conn instanceof mysqli)) {
    $sql_mahasiswa = "SELECT nim, nama, email, no_hp, prodi, angkatan, status_akun 
                      FROM mahasiswa 
                      ORDER BY angkatan DESC, prodi ASC, nama ASC";
    $result_mahasiswa = $conn->query($sql_mahasiswa);
    if ($result_mahasiswa) {
        while ($row = $result_mahasiswa->fetch_assoc()) {
            $list_mahasiswa[] = $row;
        }
        $result_mahasiswa->free();
    } else {
        $error_message .= (empty($error_message)?"":"<br>") . "Gagal mengambil data mahasiswa: " . htmlspecialchars($conn->error);
    }
    // Koneksi akan ditutup di footer
} else {
     $error_message .= (empty($error_message)?"":"<br>") . "Koneksi database gagal atau tidak valid (saat ambil list).";
}

// Set judul halaman dan sertakan header
$page_title = "Kelola Akun Mahasiswa";
require_once '../includes/header.php';
?>

<div class="page-layout-wrapper">

    <?php require_once '../includes/sidebar_admin_prodi.php'; ?>

    <main class="main-content-area">
        <div class="list-container kelola-mahasiswa-list">
            <h1><?php echo htmlspecialchars($page_title); ?></h1>
            <p>Halaman ini menampilkan daftar semua akun mahasiswa yang terdaftar. Anda dapat mengelola status akun mereka.</p>
            <a href="/KP/admin_prodi/pengguna_mahasiswa_tambah.php" class="btn btn-success mb-3"><i class="icon-plus"></i> Tambah Mahasiswa Baru</a>
            <hr>

            <?php if (!empty($success_message)): ?>
                <div class="message success"><p><?php echo $success_message; ?></p></div>
            <?php endif; ?>
            <?php if (!empty($error_message)): ?>
                <div class="message error"><p><?php echo $error_message; ?></p></div>
            <?php endif; ?>

            <?php if (empty($list_mahasiswa) && empty($error_message)): ?>
                <div class="message info">
                    <p>Belum ada data mahasiswa yang terdaftar di sistem.</p>
                </div>
            <?php elseif (!empty($list_mahasiswa)): ?>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>No.</th>
                                <th>NIM</th>
                                <th>Nama Lengkap</th>
                                <th>Email</th>
                                <th>No. HP</th>
                                <th>Prodi</th>
                                <th>Angkatan</th>
                                <th>Status Akun</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $counter = 1; ?>
                            <?php foreach ($list_mahasiswa as $mahasiswa): ?>
                                <tr>
                                    <td><?php echo $counter++; ?></td>
                                    <td><?php echo htmlspecialchars($mahasiswa['nim']); ?></td>
                                    <td><?php echo htmlspecialchars($mahasiswa['nama']); ?></td>
                                    <td><?php echo htmlspecialchars($mahasiswa['email']); ?></td>
                                    <td><?php echo htmlspecialchars($mahasiswa['no_hp'] ?: '-'); ?></td>
                                    <td><?php echo htmlspecialchars($mahasiswa['prodi'] ?: '-'); ?></td>
                                    <td><?php echo htmlspecialchars($mahasiswa['angkatan'] ?: '-'); ?></td>
                                    <td>
                                        <span class="status-akun status-<?php echo strtolower(htmlspecialchars($mahasiswa['status_akun'])); ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', htmlspecialchars($mahasiswa['status_akun']))); ?>
                                        </span>
                                    </td>
                                    <td class="actions-cell">
                                        <a href="/KP/admin_prodi/pengguna_mahasiswa_edit.php?nim=<?php echo htmlspecialchars($mahasiswa['nim']); ?>" class="btn btn-info btn-sm" title="Edit Profil Mahasiswa">Edit</a>
                                        <?php if ($mahasiswa['status_akun'] === 'pending_verification'): ?>
                                            <a href="/KP/admin_prodi/pengguna_mahasiswa_kelola.php?action=activate&nim=<?php echo htmlspecialchars($mahasiswa['nim']); ?>" class="btn btn-success btn-sm" onclick="return confirm('Anda yakin ingin mengaktifkan akun ini?');" title="Aktifkan Akun">Aktifkan</a>
                                        <?php elseif ($mahasiswa['status_akun'] === 'active'): ?>
                                            <a href="/KP/admin_prodi/pengguna_mahasiswa_kelola.php?action=suspend&nim=<?php echo htmlspecialchars($mahasiswa['nim']); ?>" class="btn btn-warning btn-sm" onclick="return confirm('Anda yakin ingin menangguhkan akun ini? Akun yang ditangguhkan tidak akan bisa login.');" title="Tangguhkan Akun">Suspend</a>
                                        <?php elseif ($mahasiswa['status_akun'] === 'suspended'): ?>
                                            <a href="/KP/admin_prodi/pengguna_mahasiswa_kelola.php?action=reactivate&nim=<?php echo htmlspecialchars($mahasiswa['nim']); ?>" class="btn btn-success btn-sm" onclick="return confirm('Anda yakin ingin mengaktifkan kembali akun ini?');" title="Aktifkan Kembali Akun">Re-Aktifkan</a>
                                        <?php endif; ?>
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
    /* Asumsikan CSS umum sudah ada dari header, sidebar, tabel, message, btn */
    .kelola-mahasiswa-list h1 { margin-top: 0; margin-bottom: 10px; }
    .kelola-mahasiswa-list hr { margin-bottom: 20px; }
    .kelola-mahasiswa-list p { margin-bottom: 15px; }
    .btn.mb-3 { margin-bottom: 1rem !important; } /* Untuk tombol Tambah */
    .icon-plus::before { content: "+ "; font-weight: bold; } /* Ikon sederhana untuk tambah */


    .data-table td.actions-cell .btn {
        margin-right: 5px;
        margin-bottom: 5px; /* Agar tombol tidak terlalu rapat jika ada banyak */
    }

    /* Styling untuk status akun (mirip status_badge sebelumnya) */
    .status-akun {
        padding: 3px 8px;
        border-radius: 12px;
        font-size: 0.8em;
        font-weight: bold;
        color: #fff;
        white-space: nowrap;
    }
    .status-pending_verification { background-color: #ffc107; color: #212529; } /* Kuning */
    .status-active { background-color: #28a745; } /* Hijau */
    .status-suspended { background-color: #dc3545; } /* Merah */

    /* Pastikan warna tombol dari CSS global sudah ada */
    .btn-success { color: #fff; background-color: #28a745; border-color: #28a745; }
    .btn-success:hover { background-color: #218838; border-color: #1e7e34; }
    .btn-warning { color: #212529; background-color: #ffc107; border-color: #ffc107; }
    .btn-warning:hover { color: #212529; background-color: #e0a800; border-color: #d39e00; }
    .btn-danger { color: #fff; background-color: #dc3545; border-color: #dc3545; }
    .btn-danger:hover { background-color: #c82333; border-color: #bd2130; }
    .btn-info { /* ... sudah ada ... */ }

</style>

<?php
require_once '../includes/footer.php';

if (isset($conn) && ($conn instanceof mysqli)) {
    $conn->close();
}
?>