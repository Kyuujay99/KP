<?php
// /KP/admin_prodi/pengguna_dosen_tambah.php

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
$input_nip = '';
$input_nama_dosen = '';
$input_email_dosen = '';
$input_status_akun_dosen = 'active'; // Default status

// Sertakan file koneksi database
require_once '../config/db_connect.php';

// 2. PROSES PENAMBAHAN DOSEN JIKA FORM DISUBMIT (METHOD POST)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_tambah_dosen'])) {
    // Ambil dan sanitasi (minimal trim) data dari form
    $input_nip = trim($_POST['nip']);
    $input_nama_dosen = trim($_POST['nama_dosen']);
    $input_email_dosen = trim($_POST['email']);
    $password_input = $_POST['password']; // Password plain text
    $confirm_password_input = $_POST['confirm_password'];
    $input_status_akun_dosen = $_POST['status_akun'];

    // Validasi dasar
    if (empty($input_nip) || empty($input_nama_dosen) || empty($input_email_dosen) || empty($password_input) || empty($input_status_akun_dosen)) {
        $error_message = "Semua field yang ditandai (*) wajib diisi.";
    } elseif ($password_input !== $confirm_password_input) {
        $error_message = "Password dan Konfirmasi Password tidak cocok.";
    } elseif (strlen($password_input) < 6) {
        $error_message = "Password minimal harus 6 karakter.";
    } elseif (!filter_var($input_email_dosen, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Format email tidak valid.";
    } else {
        // Validasi status akun (sesuai ENUM di tabel dosen_pembimbing)
        $allowed_statuses_dosen = ['active', 'inactive'];
        if (!in_array($input_status_akun_dosen, $allowed_statuses_dosen)) {
            $error_message = "Status akun yang dipilih tidak valid.";
        } else {
            // Jika validasi dasar lolos, cek keunikan NIP dan Email di database
            if ($conn && ($conn instanceof mysqli)) {
                // Cek NIP
                $sql_check_nip = "SELECT nip FROM dosen_pembimbing WHERE nip = ?";
                $stmt_check_nip = $conn->prepare($sql_check_nip);
                $stmt_check_nip->bind_param("s", $input_nip);
                $stmt_check_nip->execute();
                $stmt_check_nip->store_result();
                if ($stmt_check_nip->num_rows > 0) {
                    $error_message = "NIP " . htmlspecialchars($input_nip) . " sudah terdaftar. Gunakan NIP lain.";
                }
                $stmt_check_nip->close();

                // Cek Email (jika NIP belum ada error)
                if (empty($error_message)) {
                    $sql_check_email = "SELECT email FROM dosen_pembimbing WHERE email = ?";
                    $stmt_check_email = $conn->prepare($sql_check_email);
                    $stmt_check_email->bind_param("s", $input_email_dosen);
                    $stmt_check_email->execute();
                    $stmt_check_email->store_result();
                    if ($stmt_check_email->num_rows > 0) {
                        $error_message = "Email " . htmlspecialchars($input_email_dosen) . " sudah terdaftar. Gunakan email lain.";
                    }
                    $stmt_check_email->close();
                }

                // Jika tidak ada error duplikasi, lakukan INSERT
                if (empty($error_message)) {
                    $sql_insert = "INSERT INTO dosen_pembimbing (nip, password, nama_dosen, email, status_akun) 
                                   VALUES (?, ?, ?, ?, ?)";
                    $stmt_insert = $conn->prepare($sql_insert);
                    if ($stmt_insert) {
                        $stmt_insert->bind_param("sssss",
                            $input_nip,
                            $password_input, // Simpan password plain text
                            $input_nama_dosen,
                            $input_email_dosen,
                            $input_status_akun_dosen
                        );

                        if ($stmt_insert->execute()) {
                            $success_message = "Dosen baru dengan NIP " . htmlspecialchars($input_nip) . " berhasil ditambahkan.";
                            // Kosongkan variabel input setelah berhasil
                            $input_nip = $input_nama_dosen = $input_email_dosen = '';
                            $input_status_akun_dosen = 'active';
                            // Pertimbangkan redirect ke halaman list dosen
                            // header("Location: /KP/admin_prodi/pengguna_dosen_kelola.php?add_success=1");
                            // exit();
                        } else {
                            $error_message = "Gagal menambahkan dosen baru: " . htmlspecialchars($stmt_insert->error);
                        }
                        $stmt_insert->close();
                    } else {
                        $error_message = "Gagal menyiapkan statement insert dosen: " . htmlspecialchars($conn->error);
                    }
                }
            } else {
                $error_message = "Koneksi database gagal atau tidak valid.";
            }
        }
    }
}


// Daftar status akun untuk dropdown (sesuai ENUM di tabel dosen_pembimbing)
$opsi_status_akun_dosen = [
    'active' => 'Active',
    'inactive' => 'Inactive'
];

// Set judul halaman dan sertakan header
$page_title = "Tambah Akun Dosen Baru";
require_once '../includes/header.php';
?>

<div class="page-layout-wrapper">

    <?php require_once '../includes/sidebar_admin_prodi.php'; ?>

    <main class="main-content-area">
        <div class="form-container tambah-dosen-form">
            <h1><?php echo htmlspecialchars($page_title); ?></h1>
            <p>Isi formulir di bawah ini untuk menambahkan akun dosen pembimbing baru ke dalam sistem.</p>
            <a href="/KP/admin_prodi/pengguna_dosen_kelola.php" class="btn btn-light btn-sm mb-3">&laquo; Kembali ke Daftar Dosen</a>
            <hr>

            <?php if (!empty($success_message)): ?>
                <div class="message success"><p><?php echo htmlspecialchars($success_message); ?></p></div>
            <?php endif; ?>
            <?php if (!empty($error_message)): ?>
                <div class="message error"><p><?php echo htmlspecialchars($error_message); ?></p></div>
            <?php endif; ?>

            <form action="/KP/admin_prodi/pengguna_dosen_tambah.php" method="POST">
                <fieldset>
                    <legend>Data Identitas & Login Dosen</legend>
                    <div class="form-group">
                        <label for="nip">NIP (*):</label>
                        <input type="text" id="nip" name="nip" value="<?php echo htmlspecialchars($input_nip); ?>" required maxlength="20">
                    </div>
                    <div class="form-group">
                        <label for="nama_dosen">Nama Lengkap Dosen (beserta gelar) (*):</label>
                        <input type="text" id="nama_dosen" name="nama_dosen" value="<?php echo htmlspecialchars($input_nama_dosen); ?>" required maxlength="100">
                    </div>
                    <div class="form-group">
                        <label for="email">Email Institusi (*):</label>
                        <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($input_email_dosen); ?>" required maxlength="100">
                    </div>
                    <div class="form-group">
                        <label for="password">Password Awal (*):</label>
                        <input type="password" id="password" name="password" required minlength="6">
                        <small>Minimal 6 karakter. Akan disimpan sebagai plain text. Dosen dapat mengubahnya nanti.</small>
                    </div>
                    <div class="form-group">
                        <label for="confirm_password">Konfirmasi Password Awal (*):</label>
                        <input type="password" id="confirm_password" name="confirm_password" required minlength="6">
                    </div>
                </fieldset>

                <fieldset>
                    <legend>Status Akun</legend>
                    <div class="form-group">
                        <label for="status_akun">Status Akun Awal (*):</label>
                        <select id="status_akun" name="status_akun" required>
                            <?php foreach ($opsi_status_akun_dosen as $value_status => $text_status): ?>
                                <option value="<?php echo $value_status; ?>" <?php echo ($input_status_akun_dosen == $value_status) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($text_status); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </fieldset>

                <div class="form-actions">
                    <button type="submit" name="submit_tambah_dosen" class="btn btn-primary">Tambah Dosen</button>
                    <button type="reset" class="btn btn-secondary">Reset Form</button>
                </div>
            </form>

        </div>
    </main>
</div>

<style>
    /* Asumsikan CSS umum dari header, sidebar, form-container, fieldset, legend, form-group, message, btn sudah ada */
    .tambah-dosen-form h1 { margin-top: 0; margin-bottom: 5px; }
    .tambah-dosen-form hr { margin-top:15px; margin-bottom: 20px; }
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