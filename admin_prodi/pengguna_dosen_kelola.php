<?php
// /KP/admin_prodi/pengguna_dosen_kelola.php

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

$list_dosen = [];
$error_message = '';
$success_message = '';

// 2. PROSES AKSI (UBAH STATUS AKUN DOSEN) JIKA ADA PARAMETER GET
if (isset($_GET['action']) && isset($_GET['nip'])) {
    $action = $_GET['action'];
    $nip_aksi = $_GET['nip'];
    $new_status = '';

    if ($action === 'activate_dosen') {
        $new_status = 'active';
    } elseif ($action === 'deactivate_dosen') {
        $new_status = 'inactive';
    }

    // Validasi new_status berdasarkan ENUM di tabel dosen_pembimbing
    $allowed_statuses_dosen = ['active', 'inactive'];
    if (!empty($new_status) && in_array($new_status, $allowed_statuses_dosen) && $conn && ($conn instanceof mysqli)) {
        // Pastikan NIP ada sebelum update
        $sql_check_nip = "SELECT nip FROM dosen_pembimbing WHERE nip = ?";
        $stmt_check_nip = $conn->prepare($sql_check_nip);
        $stmt_check_nip->bind_param("s", $nip_aksi);
        $stmt_check_nip->execute();
        $result_check_nip = $stmt_check_nip->get_result();

        if ($result_check_nip->num_rows === 1) {
            $sql_update_status = "UPDATE dosen_pembimbing SET status_akun = ? WHERE nip = ?";
            $stmt_update_status = $conn->prepare($sql_update_status);
            if ($stmt_update_status) {
                $stmt_update_status->bind_param("ss", $new_status, $nip_aksi);
                if ($stmt_update_status->execute()) {
                    if ($stmt_update_status->affected_rows > 0) {
                        $success_message = "Status akun untuk Dosen NIP " . htmlspecialchars($nip_aksi) . " berhasil diubah menjadi " . ucfirst($new_status) . ".";
                    } else {
                        $error_message = "Tidak ada perubahan status, mungkin status sudah " . ucfirst($new_status) . ".";
                    }
                } else {
                    $error_message = "Gagal mengubah status akun dosen: " . htmlspecialchars($stmt_update_status->error);
                }
                $stmt_update_status->close();
            } else {
                $error_message = "Gagal menyiapkan statement update status dosen: " . htmlspecialchars($conn->error);
            }
        } else {
            $error_message = "Dosen dengan NIP " . htmlspecialchars($nip_aksi) . " tidak ditemukan.";
        }
        $stmt_check_nip->close();
    } elseif (empty($new_status)) {
        $error_message = "Tindakan tidak valid untuk status dosen.";
    } elseif (!in_array($new_status, $allowed_statuses_dosen) && !empty($new_status)) {
        $error_message = "Status tujuan tidak valid untuk dosen.";
    }
     else {
        $error_message = "Koneksi database gagal atau tidak valid.";
    }
}


// 3. AMBIL SEMUA DATA DOSEN DARI DATABASE
if ($conn && ($conn instanceof mysqli)) {
    $sql_dosen = "SELECT nip, nama_dosen, email, status_akun, created_at 
                  FROM dosen_pembimbing 
                  ORDER BY nama_dosen ASC";
    $result_dosen = $conn->query($sql_dosen);
    if ($result_dosen) {
        while ($row = $result_dosen->fetch_assoc()) {
            $list_dosen[] = $row;
        }
        $result_dosen->free();
    } else {
        $error_message .= (empty($error_message)?"":"<br>") . "Gagal mengambil data dosen: " . htmlspecialchars($conn->error);
    }
    // Koneksi akan ditutup di footer
} else {
     $error_message .= (empty($error_message)?"":"<br>") . "Koneksi database gagal atau tidak valid (saat ambil list dosen).";
}

// Set judul halaman dan sertakan header
$page_title = "Kelola Akun Dosen Pembimbing";
require_once '../includes/header.php';
?>

<div class="page-layout-wrapper">

    <?php require_once '../includes/sidebar_admin_prodi.php'; ?>

    <main class="main-content-area">
        <div class="list-container kelola-dosen-list">
            <h1><?php echo htmlspecialchars($page_title); ?></h1>
            <p>Halaman ini menampilkan daftar semua akun dosen pembimbing yang terdaftar. Anda dapat mengelola status akun mereka.</p>
            <a href="/KP/admin_prodi/pengguna_dosen_tambah.php" class="btn btn-success mb-3"><i class="icon-plus"></i> Tambah Dosen Baru</a>
            <hr>

            <?php if (!empty($success_message)): ?>
                <div class="message success"><p><?php echo $success_message; ?></p></div>
            <?php endif; ?>
            <?php if (!empty($error_message)): ?>
                <div class="message error"><p><?php echo $error_message; ?></p></div>
            <?php endif; ?>

            <?php if (empty($list_dosen) && empty($error_message)): ?>
                <div class="message info">
                    <p>Belum ada data dosen pembimbing yang terdaftar di sistem.</p>
                </div>
            <?php elseif (!empty($list_dosen)): ?>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>No.</th>
                                <th>NIP</th>
                                <th>Nama Dosen</th>
                                <th>Email</th>
                                <th>Tgl. Dibuat</th>
                                <th>Status Akun</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $counter = 1; ?>
                            <?php foreach ($list_dosen as $dosen): ?>
                                <tr>
                                    <td><?php echo $counter++; ?></td>
                                    <td><?php echo htmlspecialchars($dosen['nip']); ?></td>
                                    <td><?php echo htmlspecialchars($dosen['nama_dosen']); ?></td>
                                    <td><?php echo htmlspecialchars($dosen['email']); ?></td>
                                    <td><?php echo date("d M Y", strtotime($dosen['created_at'])); ?></td>
                                    <td>
                                        <span class="status-akun status-dosen-<?php echo strtolower(htmlspecialchars($dosen['status_akun'])); ?>">
                                            <?php echo ucfirst(htmlspecialchars($dosen['status_akun'])); ?>
                                        </span>
                                    </td>
                                    <td class="actions-cell">
                                        <a href="/KP/admin_prodi/pengguna_dosen_edit.php?nip=<?php echo htmlspecialchars($dosen['nip']); ?>" class="btn btn-info btn-sm" title="Edit Profil Dosen">Edit</a>
                                        <?php if ($dosen['status_akun'] === 'inactive'): ?>
                                            <a href="/KP/admin_prodi/pengguna_dosen_kelola.php?action=activate_dosen&nip=<?php echo htmlspecialchars($dosen['nip']); ?>" class="btn btn-success btn-sm" onclick="return confirm('Anda yakin ingin mengaktifkan akun dosen ini?');" title="Aktifkan Akun Dosen">Aktifkan</a>
                                        <?php elseif ($dosen['status_akun'] === 'active'): ?>
                                            <a href="/KP/admin_prodi/pengguna_dosen_kelola.php?action=deactivate_dosen&nip=<?php echo htmlspecialchars($dosen['nip']); ?>" class="btn btn-warning btn-sm" onclick="return confirm('Anda yakin ingin menonaktifkan akun dosen ini? Dosen dengan akun nonaktif tidak akan bisa login.');" title="Nonaktifkan Akun Dosen">Nonaktifkan</a>
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
    .kelola-dosen-list h1 { margin-top: 0; margin-bottom: 10px; }
    .kelola-dosen-list hr { margin-bottom: 20px; }
    .kelola-dosen-list p { margin-bottom: 15px; }
    .btn.mb-3 { margin-bottom: 1rem !important; }
    .icon-plus::before { content: "+ "; font-weight: bold; }


    .data-table td.actions-cell .btn {
        margin-right: 5px;
        margin-bottom: 5px;
    }

    /* Styling untuk status akun dosen (ENUM: 'active','inactive') */
    .status-akun { /* Class umum jika ada status lain nanti */
        padding: 3px 8px;
        border-radius: 12px;
        font-size: 0.8em;
        font-weight: bold;
        color: #fff;
        white-space: nowrap;
    }
    .status-dosen-active { background-color: #28a745; } /* Hijau */
    .status-dosen-inactive { background-color: #6c757d; } /* Abu-abu */

    /* Pastikan warna tombol dari CSS global sudah ada */
    .btn-success { /* ... sudah ada ... */ }
    .btn-warning { /* ... sudah ada ... */ }
    .btn-danger { /* ... sudah ada ... */ }
    .btn-info { /* ... sudah ada ... */ }
</style>

<?php
require_once '../includes/footer.php';

if (isset($conn) && ($conn instanceof mysqli)) {
    $conn->close();
}
?>