<?php
// /KP/mahasiswa/profil.php

// Mulai session (atau lanjutkan session yang ada).
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// 1. OTENTIKASI DAN OTORISASI
// Sertakan file untuk memeriksa apakah pengguna sudah login.
require_once '../includes/auth_check.php';

// Pastikan peran pengguna adalah 'mahasiswa'.
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'mahasiswa') {
    session_unset();
    session_destroy();
    header("Location: /KP/index.php?error=unauthorized");
    exit();
}

// 2. PENGAMBILAN DATA PENGGUNA (DARI SESSION DAN DATABASE)
$nim_mahasiswa = $_SESSION['user_id']; // NIM dari session

// Sertakan file koneksi database untuk mengambil detail profil
require_once '../config/db_connect.php';

$mahasiswa_data = null; // Variabel untuk menyimpan data profil mahasiswa
$error_db = ''; // Variabel untuk menyimpan pesan error database

if ($conn) {
    $sql = "SELECT nim, nama, email, no_hp, prodi, angkatan, status_akun, created_at 
            FROM mahasiswa 
            WHERE nim = ? 
            LIMIT 1";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("s", $nim_mahasiswa);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows === 1) {
            $mahasiswa_data = $result->fetch_assoc();
        } else {
            // Seharusnya tidak terjadi jika user_id di session valid
            $error_db = "Data profil tidak ditemukan.";
        }
        $stmt->close();
    } else {
        $error_db = "Gagal menyiapkan query: " . $conn->error;
    }
    // Jangan tutup koneksi $conn di sini jika header/footer mungkin menggunakannya atau ada query lain.
    // $conn->close(); // Sebaiknya ditutup di akhir script atau di footer.
} else {
    $error_db = "Gagal terhubung ke database.";
}

// 3. SET JUDUL HALAMAN DAN SERTAKAN FILE HEADER
$page_title = "Profil Saya";
require_once '../includes/header.php'; // Memuat header.php
?>

<div class="page-layout-wrapper">

    <?php require_once '../includes/sidebar_mahasiswa.php'; // Memanggil sidebar mahasiswa ?>

    <main class="main-content-area">
        <div class="profile-container">
            <h1><?php echo htmlspecialchars($page_title); ?></h1>
            <hr>

            <?php if (!empty($error_db)): ?>
                <div class="message error">
                    <p><?php echo htmlspecialchars($error_db); ?></p>
                </div>
            <?php elseif ($mahasiswa_data): ?>
                <div class="profile-details">
                    <h3>Informasi Pribadi</h3>
                    <dl>
                        <dt>NIM:</dt>
                        <dd><?php echo htmlspecialchars($mahasiswa_data['nim']); ?></dd>

                        <dt>Nama Lengkap:</dt>
                        <dd><?php echo htmlspecialchars($mahasiswa_data['nama']); ?></dd>

                        <dt>Email:</dt>
                        <dd><?php echo htmlspecialchars($mahasiswa_data['email']); ?></dd>

                        <dt>Nomor HP:</dt>
                        <dd><?php echo $mahasiswa_data['no_hp'] ? htmlspecialchars($mahasiswa_data['no_hp']) : '-'; ?></dd>
                    </dl>

                    <h3>Informasi Akademik</h3>
                    <dl>
                        <dt>Program Studi:</dt>
                        <dd><?php echo $mahasiswa_data['prodi'] ? htmlspecialchars($mahasiswa_data['prodi']) : '-'; ?></dd>

                        <dt>Angkatan:</dt>
                        <dd><?php echo $mahasiswa_data['angkatan'] ? htmlspecialchars($mahasiswa_data['angkatan']) : '-'; ?></dd>
                    </dl>

                    <h3>Informasi Akun</h3>
                    <dl>
                        <dt>Status Akun:</dt>
                        <dd class="status-akun-<?php echo strtolower(htmlspecialchars($mahasiswa_data['status_akun'])); ?>">
                            <?php echo ucfirst(str_replace('_', ' ', htmlspecialchars($mahasiswa_data['status_akun']))); ?>
                        </dd>

                        <dt>Tanggal Terdaftar:</dt>
                        <dd><?php echo date("d F Y, H:i", strtotime($mahasiswa_data['created_at'])); ?></dd>
                    </dl>
                </div>
                <div class="profile-actions">
                    <p><em>Fitur edit profil akan tersedia nanti.</em></p>
                </div>
            <?php else: ?>
                <div class="message info">
                    <p>Tidak ada data profil yang dapat ditampilkan.</p>
                </div>
            <?php endif; ?>

        </div> </main> </div> <style>
    /* CSS untuk layout sidebar dan konten utama sudah ada di dashboard.php atau header.php jika diglobalisasi */
    /* Mari asumsikan .page-layout-wrapper, .sidebar-mahasiswa, .main-content-area sudah di-style */

    .profile-container {
        background-color: #fff; /* Jika .main-content-area belum punya background */
        padding: 20px; /* Jika .main-content-area belum punya padding */
        border-radius: 8px; /* Jika .main-content-area belum punya border-radius */
        /* box-shadow: 0 2px 10px rgba(0,0,0,0.07); */ /* Jika .main-content-area belum punya shadow */
    }

    .profile-container h1 {
        color: #333;
        margin-top: 0;
        margin-bottom: 10px;
        font-size: 1.8em; /* Sedikit lebih besar dari h2 dashboard */
    }
    .profile-container hr {
        margin-bottom: 25px;
    }

    .profile-details h3 {
        font-size: 1.2em;
        color: #007bff; /* Biru untuk sub-judul */
        margin-top: 20px;
        margin-bottom: 10px;
        padding-bottom: 5px;
        border-bottom: 1px solid #eee;
    }
    .profile-details dl {
        margin-bottom: 15px;
    }
    .profile-details dt { /* Definition Term (Label) */
        font-weight: bold;
        color: #555;
        float: left; /* Membuat label di kiri */
        width: 150px; /* Lebar tetap untuk label */
        clear: left; /* Pastikan setiap dt memulai baris baru di kiri */
        margin-bottom: 8px;
    }
    .profile-details dd { /* Definition Description (Value) */
        margin-left: 160px; /* Memberi ruang setelah dt */
        margin-bottom: 8px;
        color: #333;
    }

    /* Styling untuk status akun */
    .status-akun-active { color: green; font-weight: bold; }
    .status-akun-pending_verification { color: orange; font-weight: bold; }
    .status-akun-suspended { color: red; font-weight: bold; }

    .profile-actions {
        margin-top: 30px;
        padding-top: 20px;
        border-top: 1px solid #eee;
    }
    .profile-actions p em {
        font-size: 0.9em;
        color: #777;
    }
    
    /* Message styling (jika belum global di header.php) */
    .message { padding: 10px 15px; margin-bottom: 20px; border-radius: 5px; border: 1px solid transparent; }
    .message.error { background-color: #f8d7da; color: #721c24; border-color: #f5c6cb; }
    .message.info { background-color: #d1ecf1; color: #0c5460; border-color: #bee5eb; }

    /* Pastikan .btn dan .btn-primary sudah ada di CSS global (header.php) */
    .btn {
        display: inline-block;
        font-weight: 400;
        color: #212529;
        text-align: center;
        vertical-align: middle;
        cursor: pointer;
        user-select: none;
        background-color: transparent;
        border: 1px solid transparent;
        padding: .375rem .75rem;
        font-size: 1rem;
        line-height: 1.5;
        border-radius: .25rem;
        text-decoration: none;
    }
    .btn-primary {
        color: #fff;
        background-color: #007bff;
        border-color: #007bff;
    }
    .btn-primary:hover {
        color: #fff;
        background-color: #0069d9;
        border-color: #0062cc;
    }

</style>

<?php
// 4. SERTAKAN FILE FOOTER
require_once '../includes/footer.php'; // Memuat footer.php

// Tutup koneksi database jika dibuka di halaman ini
if (isset($conn) && $conn) {
    $conn->close();
}
?>