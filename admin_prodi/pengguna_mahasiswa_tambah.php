<?php
// /KP/admin_prodi/pengguna_mahasiswa_tambah.php

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
$error_message = '';
$success_message = '';

// Inisialisasi variabel untuk menyimpan nilai input jika ada error validasi
$input_nim = '';
$input_nama = '';
$input_email = '';
$input_no_hp = '';
$input_prodi = '';
$input_angkatan = '';
$input_status_akun = 'pending_verification'; // Default status

// Sertakan file koneksi database
require_once '../config/db_connect.php';

// 2. PROSES PENAMBAHAN MAHASISWA JIKA FORM DISUBMIT (METHOD POST)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_tambah_mahasiswa'])) {
    // Ambil dan sanitasi (minimal trim) data dari form
    $input_nim = trim($_POST['nim']);
    $input_nama = trim($_POST['nama']);
    $input_email = trim($_POST['email']);
    $password_input = $_POST['password']; // Password plain text
    $confirm_password_input = $_POST['confirm_password'];
    $input_no_hp = trim($_POST['no_hp']);
    $input_prodi = trim($_POST['prodi']);
    $input_angkatan = !empty($_POST['angkatan']) ? (int)$_POST['angkatan'] : null;
    $input_status_akun = $_POST['status_akun'];

    // Validasi dasar
    if (empty($input_nim) || empty($input_nama) || empty($input_email) || empty($password_input) || empty($input_prodi) || empty($input_angkatan) || empty($input_status_akun)) {
        $error_message = "Semua field yang ditandai (*) wajib diisi.";
    } elseif ($password_input !== $confirm_password_input) {
        $error_message = "Password dan Konfirmasi Password tidak cocok.";
    } elseif (strlen($password_input) < 6) {
        $error_message = "Password minimal harus 6 karakter.";
    } elseif (!filter_var($input_email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Format email tidak valid.";
    } elseif ($input_angkatan !== null && ($input_angkatan < 1990 || $input_angkatan > (int)date("Y") + 4)) { // +4 untuk mahasiswa baru
        $error_message = "Tahun angkatan tidak valid.";
    } else {
        // Validasi status akun (sesuai ENUM)
        $allowed_statuses_akun = ['pending_verification', 'active', 'suspended'];
        if (!in_array($input_status_akun, $allowed_statuses_akun)) {
            $error_message = "Status akun yang dipilih tidak valid.";
        } else {
            // Jika validasi dasar lolos, cek keunikan NIM dan Email di database
            if ($conn && ($conn instanceof mysqli)) {
                // Cek NIM
                $sql_check_nim = "SELECT nim FROM mahasiswa WHERE nim = ?";
                $stmt_check_nim = $conn->prepare($sql_check_nim);
                $stmt_check_nim->bind_param("s", $input_nim);
                $stmt_check_nim->execute();
                $stmt_check_nim->store_result();
                if ($stmt_check_nim->num_rows > 0) {
                    $error_message = "NIM " . htmlspecialchars($input_nim) . " sudah terdaftar. Gunakan NIM lain.";
                }
                $stmt_check_nim->close();

                // Cek Email (jika NIM belum ada error)
                if (empty($error_message)) {
                    $sql_check_email = "SELECT email FROM mahasiswa WHERE email = ?";
                    $stmt_check_email = $conn->prepare($sql_check_email);
                    $stmt_check_email->bind_param("s", $input_email);
                    $stmt_check_email->execute();
                    $stmt_check_email->store_result();
                    if ($stmt_check_email->num_rows > 0) {
                        $error_message = "Email " . htmlspecialchars($input_email) . " sudah terdaftar. Gunakan email lain.";
                    }
                    $stmt_check_email->close();
                }

                // Jika tidak ada error duplikasi, lakukan INSERT
                if (empty($error_message)) {
                    $sql_insert = "INSERT INTO mahasiswa (nim, password, nama, email, no_hp, prodi, angkatan, status_akun) 
                                   VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                    $stmt_insert = $conn->prepare($sql_insert);
                    if ($stmt_insert) {
                        $stmt_insert->bind_param("ssssssis",
                            $input_nim,
                            $password_input, // Simpan password plain text
                            $input_nama,
                            $input_email,
                            $input_no_hp,
                            $input_prodi,
                            $input_angkatan,
                            $input_status_akun
                        );

                        if ($stmt_insert->execute()) {
                            $success_message = "Mahasiswa baru dengan NIM " . htmlspecialchars($input_nim) . " berhasil ditambahkan.";
                            // Kosongkan variabel input setelah berhasil agar form bersih
                            $input_nim = $input_nama = $input_email = $input_no_hp = $input_prodi = $input_angkatan = '';
                            $input_status_akun = 'pending_verification';
                            // Pertimbangkan redirect ke halaman list mahasiswa
                            // header("Location: /KP/admin_prodi/pengguna_mahasiswa_kelola.php?add_success=1");
                            // exit();
                        } else {
                            $error_message = "Gagal menambahkan mahasiswa baru: " . htmlspecialchars($stmt_insert->error);
                        }
                        $stmt_insert->close();
                    } else {
                        $error_message = "Gagal menyiapkan statement insert: " . htmlspecialchars($conn->error);
                    }
                }
            } else {
                $error_message = "Koneksi database gagal atau tidak valid.";
            }
        }
    }
}


// Daftar status akun untuk dropdown
$opsi_status_akun = [
    'pending_verification' => 'Pending Verification',
    'active' => 'Active',
    'suspended' => 'Suspended'
];
// Daftar prodi contoh untuk dropdown (bisa juga diambil dari tabel prodi jika ada)
$opsi_prodi = [
    'Teknik Informatika' => 'Teknik Informatika',
    'Sistem Informasi' => 'Sistem Informasi',
    'Teknik Elektro' => 'Teknik Elektro',
    'Teknik Mesin' => 'Teknik Mesin',
    'Teknik Industri' => 'Teknik Industri',
    // Tambahkan prodi lain
];


// Set judul halaman dan sertakan header
$page_title = "Tambah Akun Mahasiswa Baru";
require_once '../includes/header.php';
?>

<div class="page-layout-wrapper">

    <?php require_once '../includes/sidebar_admin_prodi.php'; ?>

    <main class="main-content-area">
        <div class="form-container tambah-mahasiswa-form">
            <h1><?php echo htmlspecialchars($page_title); ?></h1>
            <p>Isi formulir di bawah ini untuk menambahkan akun mahasiswa baru ke dalam sistem.</p>
            <a href="/KP/admin_prodi/pengguna_mahasiswa_kelola.php" class="btn btn-light btn-sm mb-3">&laquo; Kembali ke Daftar Mahasiswa</a>
            <hr>

            <?php if (!empty($success_message)): ?>
                <div class="message success"><p><?php echo htmlspecialchars($success_message); ?></p></div>
            <?php endif; ?>
            <?php if (!empty($error_message)): ?>
                <div class="message error"><p><?php echo htmlspecialchars($error_message); ?></p></div>
            <?php endif; ?>

            <form action="/KP/admin_prodi/pengguna_mahasiswa_tambah.php" method="POST">
                <fieldset>
                    <legend>Data Identitas & Login Mahasiswa</legend>
                    <div class="form-group">
                        <label for="nim">NIM (*):</label>
                        <input type="text" id="nim" name="nim" value="<?php echo htmlspecialchars($input_nim); ?>" required maxlength="15">
                    </div>
                    <div class="form-group">
                        <label for="nama">Nama Lengkap (*):</label>
                        <input type="text" id="nama" name="nama" value="<?php echo htmlspecialchars($input_nama); ?>" required maxlength="100">
                    </div>
                    <div class="form-group">
                        <label for="email">Email (*):</label>
                        <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($input_email); ?>" required maxlength="100">
                    </div>
                    <div class="form-group">
                        <label for="password">Password (*):</label>
                        <input type="password" id="password" name="password" required minlength="6">
                        <small>Minimal 6 karakter. Akan disimpan sebagai plain text.</small>
                    </div>
                    <div class="form-group">
                        <label for="confirm_password">Konfirmasi Password (*):</label>
                        <input type="password" id="confirm_password" name="confirm_password" required minlength="6">
                    </div>
                    <div class="form-group">
                        <label for="no_hp">Nomor HP:</label>
                        <input type="text" id="no_hp" name="no_hp" value="<?php echo htmlspecialchars($input_no_hp); ?>" maxlength="20">
                    </div>
                </fieldset>

                <fieldset>
                    <legend>Data Akademik & Status Akun</legend>
                    <div class="form-group">
                        <label for="prodi">Program Studi (*):</label>
                        <select id="prodi" name="prodi" required>
                            <option value="">-- Pilih Program Studi --</option>
                            <?php foreach($opsi_prodi as $value_prodi => $text_prodi): ?>
                                <option value="<?php echo htmlspecialchars($value_prodi); ?>" <?php echo ($input_prodi == $value_prodi) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($text_prodi); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="angkatan">Angkatan (Tahun) (*):</label>
                        <input type="number" id="angkatan" name="angkatan" value="<?php echo htmlspecialchars($input_angkatan); ?>" min="1990" max="<?php echo date('Y') + 4; ?>" required placeholder="Contoh: <?php echo date('Y'); ?>">
                    </div>
                    <div class="form-group">
                        <label for="status_akun">Status Akun Awal (*):</label>
                        <select id="status_akun" name="status_akun" required>
                            <?php foreach ($opsi_status_akun as $value_status => $text_status): ?>
                                <option value="<?php echo $value_status; ?>" <?php echo ($input_status_akun == $value_status) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($text_status); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </fieldset>

                <div class="form-actions">
                    <button type="submit" name="submit_tambah_mahasiswa" class="btn btn-primary">Tambah Mahasiswa</button>
                    <button type="reset" class="btn btn-secondary">Reset Form</button>
                </div>
            </form>

        </div>
    </main>
</div>

<style>
    /* Asumsikan CSS umum dari header, sidebar, form-container, fieldset, legend, form-group, message, btn sudah ada */
    .tambah-mahasiswa-form h1 { margin-top: 0; margin-bottom: 5px; }
    .tambah-mahasiswa-form hr { margin-top:15px; margin-bottom: 20px; }
    .btn.mb-3 { margin-bottom: 1rem !important; } /* Untuk tombol kembali */

    .form-group small {
        display: block;
        font-size: 0.85em;
        color: #6c757d;
        margin-top: 4px;
    }
</style>

<?php
require_once '../includes/footer.php';

if (isset($conn) && ($conn instanceof mysqli)) {
    $conn->close();
}
?>