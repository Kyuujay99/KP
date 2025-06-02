<?php
// /KP/admin_prodi/perusahaan_tambah.php

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
$input_nama_perusahaan = '';
$input_email_perusahaan = '';
$input_alamat = '';
$input_bidang = '';
$input_kontak_nama = '';
$input_kontak_email = '';
$input_kontak_no_hp = '';
$input_status_akun = 'pending_approval'; // Default status untuk perusahaan baru

// Sertakan file koneksi database
require_once '../config/db_connect.php';

// 2. PROSES PENAMBAHAN PERUSAHAAN JIKA FORM DISUBMIT (METHOD POST)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_tambah_perusahaan'])) {
    // Ambil dan sanitasi data dari form
    $input_nama_perusahaan = trim($_POST['nama_perusahaan']);
    $input_email_perusahaan = trim($_POST['email_perusahaan']);
    $password_input = $_POST['password_perusahaan'];
    $confirm_password_input = $_POST['confirm_password_perusahaan'];
    $input_alamat = trim($_POST['alamat']);
    $input_bidang = trim($_POST['bidang']);
    $input_kontak_nama = trim($_POST['kontak_person_nama']);
    $input_kontak_email = trim($_POST['kontak_person_email']);
    $input_kontak_no_hp = trim($_POST['kontak_person_no_hp']);
    $input_status_akun = $_POST['status_akun'];

    // Validasi dasar
    if (empty($input_nama_perusahaan) || empty($input_email_perusahaan) || empty($password_input) || empty($input_status_akun)) {
        $error_message = "Nama Perusahaan, Email Perusahaan (untuk login), Password, dan Status Akun wajib diisi.";
    } elseif ($password_input !== $confirm_password_input) {
        $error_message = "Password dan Konfirmasi Password tidak cocok.";
    } elseif (strlen($password_input) < 6) {
        $error_message = "Password minimal harus 6 karakter.";
    } elseif (!filter_var($input_email_perusahaan, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Format Email Perusahaan tidak valid.";
    } elseif (!empty($input_kontak_email) && !filter_var($input_kontak_email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Format Email Kontak Person tidak valid.";
    } else {
        // Validasi status akun (sesuai ENUM di tabel perusahaan)
        $allowed_statuses_perusahaan = ['pending_approval', 'active', 'inactive'];
        if (!in_array($input_status_akun, $allowed_statuses_perusahaan)) {
            $error_message = "Status akun yang dipilih tidak valid.";
        } else {
            // Jika validasi dasar lolos, cek keunikan email perusahaan di database
            if ($conn && ($conn instanceof mysqli)) {
                $sql_check_email = "SELECT id_perusahaan FROM perusahaan WHERE email_perusahaan = ?";
                $stmt_check_email = $conn->prepare($sql_check_email);
                $stmt_check_email->bind_param("s", $input_email_perusahaan);
                $stmt_check_email->execute();
                $stmt_check_email->store_result();
                if ($stmt_check_email->num_rows > 0) {
                    $error_message = "Email Perusahaan " . htmlspecialchars($input_email_perusahaan) . " sudah terdaftar. Gunakan email lain.";
                }
                $stmt_check_email->close();

                // Jika tidak ada error duplikasi, lakukan INSERT
                if (empty($error_message)) {
                    $sql_insert = "INSERT INTO perusahaan (email_perusahaan, password_perusahaan, nama_perusahaan, alamat, bidang, 
                                       kontak_person_nama, kontak_person_email, kontak_person_no_hp, status_akun) 
                                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    $stmt_insert = $conn->prepare($sql_insert);
                    if ($stmt_insert) {
                        $stmt_insert->bind_param("sssssssss",
                            $input_email_perusahaan,
                            $password_input, // Simpan password plain text
                            $input_nama_perusahaan,
                            $input_alamat,
                            $input_bidang,
                            $input_kontak_nama,
                            $input_kontak_email,
                            $input_kontak_no_hp,
                            $input_status_akun
                        );

                        if ($stmt_insert->execute()) {
                            $success_message = "Perusahaan baru '" . htmlspecialchars($input_nama_perusahaan) . "' berhasil ditambahkan.";
                            // Kosongkan variabel input setelah berhasil
                            $input_nama_perusahaan = $input_email_perusahaan = $input_alamat = $input_bidang = '';
                            $input_kontak_nama = $input_kontak_email = $input_kontak_no_hp = '';
                            $input_status_akun = 'pending_approval';
                        } else {
                            $error_message = "Gagal menambahkan perusahaan baru: " . htmlspecialchars($stmt_insert->error);
                        }
                        $stmt_insert->close();
                    } else {
                        $error_message = "Gagal menyiapkan statement insert perusahaan: " . htmlspecialchars($conn->error);
                    }
                }
            } else {
                $error_message = "Koneksi database gagal atau tidak valid.";
            }
        }
    }
}


// Daftar status akun untuk dropdown (sesuai ENUM di tabel perusahaan)
$opsi_status_akun_perusahaan = [
    'pending_approval' => 'Pending Approval',
    'active' => 'Active',
    'inactive' => 'Inactive'
];

// Set judul halaman dan sertakan header
$page_title = "Tambah Data Perusahaan Mitra Baru";
require_once '../includes/header.php';
?>

<div class="page-layout-wrapper">

    <?php require_once '../includes/sidebar_admin_prodi.php'; ?>

    <main class="main-content-area">
        <div class="form-container tambah-perusahaan-form">
            <h1><?php echo htmlspecialchars($page_title); ?></h1>
            <p>Isi formulir di bawah ini untuk menambahkan data perusahaan mitra baru ke dalam sistem.</p>
            <a href="/KP/admin_prodi/perusahaan_kelola.php" class="btn btn-light btn-sm mb-3">&laquo; Kembali ke Daftar Perusahaan</a>
            <hr>

            <?php if (!empty($success_message)): ?>
                <div class="message success"><p><?php echo htmlspecialchars($success_message); ?></p></div>
            <?php endif; ?>
            <?php if (!empty($error_message)): ?>
                <div class="message error"><p><?php echo htmlspecialchars($error_message); ?></p></div>
            <?php endif; ?>

            <form action="/KP/admin_prodi/perusahaan_tambah.php" method="POST">
                <fieldset>
                    <legend>Informasi Utama & Akun Perusahaan</legend>
                    <div class="form-group">
                        <label for="nama_perusahaan">Nama Perusahaan (*):</label>
                        <input type="text" id="nama_perusahaan" name="nama_perusahaan" value="<?php echo htmlspecialchars($input_nama_perusahaan); ?>" required maxlength="100">
                    </div>
                    <div class="form-group">
                        <label for="email_perusahaan">Email Perusahaan (untuk Login) (*):</label>
                        <input type="email" id="email_perusahaan" name="email_perusahaan" value="<?php echo htmlspecialchars($input_email_perusahaan); ?>" required maxlength="100">
                    </div>
                    <div class="form-group">
                        <label for="password_perusahaan">Password Akun Perusahaan (*):</label>
                        <input type="password" id="password_perusahaan" name="password_perusahaan" required minlength="6">
                        <small>Minimal 6 karakter. Akan disimpan sebagai plain text.</small>
                    </div>
                    <div class="form-group">
                        <label for="confirm_password_perusahaan">Konfirmasi Password Akun (*):</label>
                        <input type="password" id="confirm_password_perusahaan" name="confirm_password_perusahaan" required minlength="6">
                    </div>
                    <div class="form-group">
                        <label for="alamat">Alamat Lengkap Perusahaan:</label>
                        <textarea id="alamat" name="alamat" rows="3"><?php echo htmlspecialchars($input_alamat); ?></textarea>
                    </div>
                    <div class="form-group">
                        <label for="bidang">Bidang Usaha Perusahaan:</label>
                        <input type="text" id="bidang" name="bidang" value="<?php echo htmlspecialchars($input_bidang); ?>" maxlength="100">
                    </div>
                </fieldset>

                <fieldset>
                    <legend>Informasi Kontak Person (PIC) di Perusahaan</legend>
                    <div class="form-group">
                        <label for="kontak_person_nama">Nama Kontak Person:</label>
                        <input type="text" id="kontak_person_nama" name="kontak_person_nama" value="<?php echo htmlspecialchars($input_kontak_nama); ?>" maxlength="100">
                    </div>
                    <div class="form-group">
                        <label for="kontak_person_email">Email Kontak Person:</label>
                        <input type="email" id="kontak_person_email" name="kontak_person_email" value="<?php echo htmlspecialchars($input_kontak_email); ?>" maxlength="100">
                    </div>
                     <div class="form-group">
                        <label for="kontak_person_no_hp">No. HP Kontak Person:</label>
                        <input type="text" id="kontak_person_no_hp" name="kontak_person_no_hp" value="<?php echo htmlspecialchars($input_kontak_no_hp); ?>" maxlength="20">
                    </div>
                </fieldset>
                
                <fieldset>
                    <legend>Status Awal Akun Perusahaan</legend>
                    <div class="form-group">
                        <label for="status_akun">Status Akun Awal (*):</label>
                        <select id="status_akun" name="status_akun" required>
                            <?php foreach ($opsi_status_akun_perusahaan as $value_status => $text_status): ?>
                                <option value="<?php echo $value_status; ?>" <?php echo ($input_status_akun == $value_status) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($text_status); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small>Pilih 'Pending Approval' jika perlu verifikasi sebelum ditampilkan ke mahasiswa, atau 'Active' jika langsung aktif.</small>
                    </div>
                </fieldset>

                <div class="form-actions">
                    <button type="submit" name="submit_tambah_perusahaan" class="btn btn-primary">Tambah Perusahaan</button>
                    <button type="reset" class="btn btn-secondary">Reset Form</button>
                </div>
            </form>

        </div>
    </main>
</div>

<style>
    /* Asumsikan CSS umum dari header, sidebar, form-container, fieldset, legend, form-group, message, btn sudah ada */
    .tambah-perusahaan-form h1 { margin-top: 0; margin-bottom: 5px; }
    .tambah-perusahaan-form hr { margin-top:15px; margin-bottom: 20px; }
    .btn.mb-3 { margin-bottom: 1rem !important; }

    .form-group small { display: block; font-size: 0.85em; color: #6c757d; margin-top: 4px; }
    .form-group textarea { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; font-size: 1em; }
</style>

<?php
require_once '../includes/footer.php';

if (isset($conn) && ($conn instanceof mysqli)) {
    $conn->close();
}
?>