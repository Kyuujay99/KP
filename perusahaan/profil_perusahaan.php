<?php
// /KP/perusahaan/profil_perusahaan.php

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

// Inisialisasi variabel
$id_perusahaan_login = $_SESSION['user_id'];
$perusahaan_data = null;
$error_message = '';

// Sertakan file koneksi database
require_once '../config/db_connect.php';

// 2. AMBIL DATA PROFIL PERUSAHAAN DARI DATABASE
if ($conn && ($conn instanceof mysqli) && !$conn->connect_error) {
    $sql = "SELECT * FROM perusahaan WHERE id_perusahaan = ? LIMIT 1";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("i", $id_perusahaan_login);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows === 1) {
            $perusahaan_data = $result->fetch_assoc();
        } else {
            $error_message = "Data perusahaan Anda tidak dapat ditemukan.";
        }
        $stmt->close();
    } else {
        $error_message = "Gagal menyiapkan query untuk mengambil data profil perusahaan.";
    }
} else {
    $error_message = "Koneksi database gagal.";
}

// Set judul halaman dan sertakan header
$page_title = "Profil Perusahaan Saya";
// Periksa dengan aman apakah $perusahaan_data adalah array dan memiliki kunci yang diharapkan
if (is_array($perusahaan_data) && isset($perusahaan_data['nama_perusahaan'])) {
    $page_title = "Profil: " . htmlspecialchars($perusahaan_data['nama_perusahaan']);
}
require_once '../includes/header.php';
?>

<div class="page-layout-wrapper">
    <?php require_once '../includes/sidebar_perusahaan.php'; ?>

    <main class="main-content-area">
        <div class="profile-container">
            <h1><?php echo htmlspecialchars($page_title); ?></h1>
            <p>Informasi detail mengenai perusahaan dan akun Anda yang terdaftar di sistem.</p>
            <hr>

            <?php if (!empty($error_message)): ?>
                <div class="message error"><p><?php echo htmlspecialchars($error_message); ?></p></div>
            <?php endif; ?>

            <?php if (is_array($perusahaan_data)): // Pengecekan aman ?>
                <div class="profile-actions-header">
                    <a href="/KP/perusahaan/profil_perusahaan_edit.php" class="btn btn-primary"><i class="icon-pencil"></i> Edit Profil & Kontak</a>
                </div>
                <div class="profile-details">
                    <h3>Informasi Perusahaan</h3>
                    <dl>
                        <dt>ID Perusahaan:</dt><dd><?php echo htmlspecialchars($perusahaan_data['id_perusahaan']); ?></dd>
                        <dt>Nama Perusahaan:</dt><dd><strong><?php echo htmlspecialchars($perusahaan_data['nama_perusahaan']); ?></strong></dd>
                        <dt>Email (Login):</dt><dd><?php echo htmlspecialchars($perusahaan_data['email_perusahaan']); ?></dd>
                        <dt>Bidang Usaha:</dt><dd><?php echo htmlspecialchars($perusahaan_data['bidang'] ?? '-'); ?></dd>
                        <dt>Alamat:</dt><dd><?php echo nl2br(htmlspecialchars($perusahaan_data['alamat'] ?? '-')); ?></dd>
                    </dl>

                    <h3>Informasi Kontak Person (PIC)</h3>
                    <dl>
                        <dt>Nama Kontak Person:</dt><dd><?php echo htmlspecialchars($perusahaan_data['kontak_person_nama'] ?? '-'); ?></dd>
                        <dt>Email Kontak Person:</dt><dd><?php echo htmlspecialchars($perusahaan_data['kontak_person_email'] ?? '-'); ?></dd>
                        <dt>No. HP Kontak Person:</dt><dd><?php echo htmlspecialchars($perusahaan_data['kontak_person_no_hp'] ?? '-'); ?></dd>
                    </dl>

                    <h3>Informasi Akun</h3>
                    <dl>
                        <dt>Status Akun:</dt>
                        <dd>
                            <span class="status-akun status-perusahaan-<?php echo strtolower(htmlspecialchars($perusahaan_data['status_akun'])); ?>">
                                <?php echo ucfirst(str_replace('_', ' ', htmlspecialchars($perusahaan_data['status_akun']))); ?>
                            </span>
                        </dd>
                        <dt>Tanggal Terdaftar:</dt><dd><?php echo date("d F Y", strtotime($perusahaan_data['created_at'])); ?></dd>
                    </dl>
                </div>
            <?php elseif(empty($error_message)): ?>
                <div class="message info"><p>Memuat data profil...</p></div>
            <?php endif; ?>
        </div>
    </main>
</div>

<style>
    .profile-container h1 { margin-top: 0; margin-bottom: 10px; }
    .profile-container p { color: #555; }
    .profile-container hr { margin-bottom: 25px; }
    .profile-actions-header { margin-bottom: 20px; }
    .icon-pencil::before { content: "✏️ "; }
    .profile-details h3 { font-size: 1.2em; color: #17a2b8; margin-top: 20px; margin-bottom: 15px; padding-bottom: 5px; border-bottom: 1px solid #eee; }
    .profile-details dl { margin-bottom: 15px; overflow: hidden; }
    .profile-details dt { font-weight: bold; color: #555; float: left; width: 220px; clear: left; margin-bottom: 8px; }
    .profile-details dd { margin-left: 230px; margin-bottom: 8px; color: #333; white-space: pre-wrap; }
    .status-akun { padding: 3px 8px; border-radius: 12px; font-size: 0.8em; font-weight: bold; color: #fff; }
    .status-perusahaan-pending-approval { background-color: #ffc107; color: #212529; }
    .status-perusahaan-active { background-color: #28a745; }
    .status-perusahaan-inactive { background-color: #6c757d; }
</style>

<?php
require_once '../includes/footer.php';
if (isset($conn) && ($conn instanceof mysqli)) {
    $conn->close();
}
?>