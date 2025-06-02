<?php
// /KP/admin_prodi/perusahaan_kelola.php

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

$list_perusahaan = [];
$error_message = '';
$success_message = '';

// 2. PROSES AKSI (UBAH STATUS AKUN PERUSAHAAN) JIKA ADA PARAMETER GET
if (isset($_GET['action']) && isset($_GET['id_perusahaan'])) {
    $action = $_GET['action'];
    $id_perusahaan_aksi = filter_var($_GET['id_perusahaan'], FILTER_VALIDATE_INT);
    $new_status = '';

    if ($id_perusahaan_aksi === false) {
        $error_message = "ID Perusahaan tidak valid.";
    } else {
        if ($action === 'approve_perusahaan') {
            $new_status = 'active';
        } elseif ($action === 'deactivate_perusahaan') {
            $new_status = 'inactive';
        } elseif ($action === 'reactivate_perusahaan') { // Jika ingin mengaktifkan kembali dari inactive
            $new_status = 'active';
        }

        // Validasi new_status berdasarkan ENUM di tabel perusahaan
        $allowed_statuses_perusahaan = ['pending_approval', 'active', 'inactive'];
        if (!empty($new_status) && in_array($new_status, $allowed_statuses_perusahaan) && $conn && ($conn instanceof mysqli)) {
            // Pastikan ID Perusahaan ada sebelum update
            $sql_check_id = "SELECT id_perusahaan FROM perusahaan WHERE id_perusahaan = ?";
            $stmt_check_id = $conn->prepare($sql_check_id);
            $stmt_check_id->bind_param("i", $id_perusahaan_aksi);
            $stmt_check_id->execute();
            $result_check_id = $stmt_check_id->get_result();

            if ($result_check_id->num_rows === 1) {
                $sql_update_status = "UPDATE perusahaan SET status_akun = ? WHERE id_perusahaan = ?";
                $stmt_update_status = $conn->prepare($sql_update_status);
                if ($stmt_update_status) {
                    $stmt_update_status->bind_param("si", $new_status, $id_perusahaan_aksi);
                    if ($stmt_update_status->execute()) {
                        if ($stmt_update_status->affected_rows > 0) {
                            $success_message = "Status akun untuk Perusahaan ID " . htmlspecialchars($id_perusahaan_aksi) . " berhasil diubah menjadi " . ucfirst($new_status) . ".";
                        } else {
                            $error_message = "Tidak ada perubahan status, mungkin status sudah " . ucfirst($new_status) . ".";
                        }
                    } else {
                        $error_message = "Gagal mengubah status akun perusahaan: " . htmlspecialchars($stmt_update_status->error);
                    }
                    $stmt_update_status->close();
                } else {
                    $error_message = "Gagal menyiapkan statement update status perusahaan: " . htmlspecialchars($conn->error);
                }
            } else {
                $error_message = "Perusahaan dengan ID " . htmlspecialchars($id_perusahaan_aksi) . " tidak ditemukan.";
            }
            $stmt_check_id->close();
        } elseif (empty($new_status) && $id_perusahaan_aksi) { // ID valid tapi action tidak menghasilkan new_status
             $error_message = "Tindakan tidak valid untuk status perusahaan.";
        } elseif (!empty($new_status) && !in_array($new_status, $allowed_statuses_perusahaan) && $id_perusahaan_aksi) {
            $error_message = "Status tujuan tidak valid untuk perusahaan.";
        } 
        elseif ($id_perusahaan_aksi && (!$conn || !($conn instanceof mysqli))) {
            $error_message = "Koneksi database gagal atau tidak valid.";
        }
    }
}


// 3. AMBIL SEMUA DATA PERUSAHAAN DARI DATABASE
if ($conn && ($conn instanceof mysqli)) {
    $sql_perusahaan = "SELECT id_perusahaan, nama_perusahaan, email_perusahaan, bidang, alamat, 
                              kontak_person_nama, kontak_person_email, kontak_person_no_hp, status_akun, created_at 
                       FROM perusahaan 
                       ORDER BY nama_perusahaan ASC";
    $result_perusahaan_db = $conn->query($sql_perusahaan); // Mengganti nama variabel result
    if ($result_perusahaan_db) {
        while ($row = $result_perusahaan_db->fetch_assoc()) {
            $list_perusahaan[] = $row;
        }
        $result_perusahaan_db->free();
    } else {
        $error_message .= (empty($error_message)?"":"<br>") . "Gagal mengambil data perusahaan: " . htmlspecialchars($conn->error);
    }
} else {
     $error_message .= (empty($error_message)?"":"<br>") . "Koneksi database gagal atau tidak valid (saat ambil list perusahaan).";
}

// Set judul halaman dan sertakan header
$page_title = "Kelola Data Perusahaan Mitra";
require_once '../includes/header.php';
?>

<div class="page-layout-wrapper">

    <?php require_once '../includes/sidebar_admin_prodi.php'; ?>

    <main class="main-content-area">
        <div class="list-container kelola-perusahaan-list">
            <h1><?php echo htmlspecialchars($page_title); ?></h1>
            <p>Halaman ini menampilkan daftar semua perusahaan mitra yang terdaftar. Anda dapat menyetujui perusahaan baru dan mengelola statusnya.</p>
            <a href="/KP/admin_prodi/perusahaan_tambah.php" class="btn btn-success mb-3"><i class="icon-plus"></i> Tambah Perusahaan Baru</a>
            <hr>

            <?php if (!empty($success_message)): ?>
                <div class="message success"><p><?php echo $success_message; ?></p></div>
            <?php endif; ?>
            <?php if (!empty($error_message)): ?>
                <div class="message error"><p><?php echo $error_message; ?></p></div>
            <?php endif; ?>

            <?php if (empty($list_perusahaan) && empty($error_message)): ?>
                <div class="message info">
                    <p>Belum ada data perusahaan mitra yang terdaftar di sistem.</p>
                </div>
            <?php elseif (!empty($list_perusahaan)): ?>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>No.</th>
                                <th>ID</th>
                                <th>Nama Perusahaan</th>
                                <th>Email</th>
                                <th>Bidang</th>
                                <th>Kontak Person</th>
                                <th>Status Akun</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $counter = 1; ?>
                            <?php foreach ($list_perusahaan as $perusahaan): ?>
                                <tr>
                                    <td><?php echo $counter++; ?></td>
                                    <td><?php echo $perusahaan['id_perusahaan']; ?></td>
                                    <td><?php echo htmlspecialchars($perusahaan['nama_perusahaan']); ?></td>
                                    <td><?php echo htmlspecialchars($perusahaan['email_perusahaan']); ?></td>
                                    <td><?php echo htmlspecialchars($perusahaan['bidang'] ?: '-'); ?></td>
                                    <td>
                                        <?php echo htmlspecialchars($perusahaan['kontak_person_nama'] ?: '-'); ?>
                                        <?php if($perusahaan['kontak_person_no_hp']): ?>
                                            <br><small>(<?php echo htmlspecialchars($perusahaan['kontak_person_no_hp']); ?>)</small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="status-akun status-perusahaan-<?php echo strtolower(str_replace('_', '-', htmlspecialchars($perusahaan['status_akun']))); ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', htmlspecialchars($perusahaan['status_akun']))); ?>
                                        </span>
                                    </td>
                                    <td class="actions-cell">
                                        <a href="/KP/admin_prodi/perusahaan_edit.php?id_perusahaan=<?php echo $perusahaan['id_perusahaan']; ?>" class="btn btn-info btn-sm" title="Edit Data Perusahaan">Edit</a>
                                        <?php if ($perusahaan['status_akun'] === 'pending_approval'): ?>
                                            <a href="/KP/admin_prodi/perusahaan_kelola.php?action=approve_perusahaan&id_perusahaan=<?php echo $perusahaan['id_perusahaan']; ?>" class="btn btn-success btn-sm" onclick="return confirm('Anda yakin ingin menyetujui perusahaan ini?');" title="Setujui Perusahaan">Setujui</a>
                                        <?php elseif ($perusahaan['status_akun'] === 'active'): ?>
                                            <a href="/KP/admin_prodi/perusahaan_kelola.php?action=deactivate_perusahaan&id_perusahaan=<?php echo $perusahaan['id_perusahaan']; ?>" class="btn btn-warning btn-sm" onclick="return confirm('Anda yakin ingin menonaktifkan perusahaan ini? Perusahaan nonaktif tidak akan bisa dipilih mahasiswa.');" title="Nonaktifkan Perusahaan">Nonaktifkan</a>
                                        <?php elseif ($perusahaan['status_akun'] === 'inactive'): ?>
                                            <a href="/KP/admin_prodi/perusahaan_kelola.php?action=reactivate_perusahaan&id_perusahaan=<?php echo $perusahaan['id_perusahaan']; ?>" class="btn btn-success btn-sm" onclick="return confirm('Anda yakin ingin mengaktifkan kembali perusahaan ini?');" title="Aktifkan Kembali Perusahaan">Re-Aktifkan</a>
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
    .kelola-perusahaan-list h1 { margin-top: 0; margin-bottom: 10px; }
    .kelola-perusahaan-list hr { margin-bottom: 20px; }
    .kelola-perusahaan-list p { margin-bottom: 15px; }
    .btn.mb-3 { margin-bottom: 1rem !important; }
    .icon-plus::before { content: "+ "; font-weight: bold; }

    .data-table td.actions-cell .btn {
        margin-right: 5px;
        margin-bottom: 5px;
    }
    .data-table td small { font-size: 0.85em; color: #555; display: block; }


    /* Styling untuk status akun perusahaan (ENUM: 'pending_approval','active','inactive') */
    .status-akun { /* Class umum */
        padding: 3px 8px;
        border-radius: 12px;
        font-size: 0.8em;
        font-weight: bold;
        color: #fff;
        white-space: nowrap;
    }
    .status-perusahaan-pending-approval { background-color: #ffc107; color: #212529; } /* Kuning */
    .status-perusahaan-active { background-color: #28a745; } /* Hijau */
    .status-perusahaan-inactive { background-color: #6c757d; } /* Abu-abu */

    /* Pastikan warna tombol dari CSS global sudah ada */
</style>

<?php
require_once '../includes/footer.php';

if (isset($conn) && ($conn instanceof mysqli)) {
    $conn->close();
}
?>