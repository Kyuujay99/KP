<?php
// /KP/dosen/profil.php

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

// Inisialisasi variabel
$nip_dosen_login = $_SESSION['user_id'];
$dosen_data = null;
$error_message = '';

// Sertakan file koneksi database
require_once '../config/db_connect.php';

// 2. AMBIL DATA PROFIL DOSEN DARI DATABASE
if ($conn && ($conn instanceof mysqli) && !$conn->connect_error) {
    $sql = "SELECT nip, nama_dosen, email, status_akun, created_at FROM dosen_pembimbing WHERE nip = ? LIMIT 1";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("s", $nip_dosen_login);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows === 1) {
            $dosen_data = $result->fetch_assoc();
        } else {
            $error_message = "Data profil dosen Anda tidak dapat ditemukan.";
        }
        $stmt->close();
    } else {
        $error_message = "Gagal menyiapkan query untuk mengambil data profil dosen.";
    }
} else {
    $error_message = "Koneksi database gagal.";
}

// Set judul halaman dan sertakan header
$page_title = "Profil Dosen Saya";
// Pemeriksaan aman sebelum mengakses elemen array
if (is_array($dosen_data) && !empty($dosen_data['nama_dosen'])) {
    $page_title = "Profil: " . htmlspecialchars($dosen_data['nama_dosen']);
}
require_once '../includes/header.php';
?>

<div class="page-layout-wrapper">
    <?php require_once '../includes/sidebar_dosen.php'; ?>

    <main class="main-content-area">
        <div class="profile-container">
            <h1><?php echo htmlspecialchars($page_title); ?></h1>
            <p>Informasi detail mengenai data diri dan akun Anda sebagai dosen pembimbing.</p>
            <hr>

            <?php if (!empty($error_message)): ?>
                <div class="message error"><p><?php echo htmlspecialchars($error_message); ?></p></div>
            <?php endif; ?>

            <?php if (is_array($dosen_data)): // Pengecekan aman ?>
                <div class="profile-actions-header">
                    <a href="/KP/dosen/profil_edit.php" class="btn btn-primary"><i class="icon-pencil"></i> Edit Profil & Password</a>
                </div>
                <div class="profile-details">
                    <h3>Informasi Pribadi & Akun</h3>
                    <dl>
                        <dt>NIP:</dt><dd><?php echo htmlspecialchars($dosen_data['nip']); ?></dd>
                        <dt>Nama Lengkap:</dt><dd><strong><?php echo htmlspecialchars($dosen_data['nama_dosen']); ?></strong></dd>
                        <dt>Email (Login):</dt><dd><?php echo htmlspecialchars($dosen_data['email']); ?></dd>
                        <dt>Status Akun:</dt>
                        <dd>
                            <span class="status-akun status-dosen-<?php echo strtolower(htmlspecialchars($dosen_data['status_akun'])); ?>">
                                <?php echo ucfirst(htmlspecialchars($dosen_data['status_akun'])); ?>
                            </span>
                        </dd>
                        <dt>Tanggal Akun Dibuat:</dt><dd><?php echo date("d F Y", strtotime($dosen_data['created_at'])); ?></dd>
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
    .profile-details h3 { font-size: 1.2em; color: #007bff; margin-top: 20px; margin-bottom: 15px; padding-bottom: 5px; border-bottom: 1px solid #eee; }
    .profile-details dl { margin-bottom: 15px; overflow: hidden; }
    .profile-details dt { font-weight: bold; color: #555; float: left; width: 200px; clear: left; margin-bottom: 8px; }
    .profile-details dd { margin-left: 210px; margin-bottom: 8px; color: #333; }
    .status-akun { padding: 3px 8px; border-radius: 12px; font-size: 0.8em; font-weight: bold; color: #fff; }
    .status-dosen-active { background-color: #28a745; }
    .status-dosen-inactive { background-color: #6c757d; }
</style>

<?php
require_once '../includes/footer.php';
if (isset($conn) && ($conn instanceof mysqli)) {
    $conn->close();
}
?>