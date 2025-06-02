<?php
// /KP/admin_prodi/pengguna_dosen_edit.php

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
$nip_to_edit = null;
$dosen_data = null;
$error_message = '';
$success_message = '';

// 2. VALIDASI DAN AMBIL NIP DARI URL
if (isset($_GET['nip'])) {
    // Tidak perlu filter_var untuk NIP karena bisa mengandung huruf/karakter, cukup sanitasi dasar.
    $nip_to_edit = trim($_GET['nip']);
    if (empty($nip_to_edit)) {
        $error_message = "NIP dosen tidak boleh kosong.";
        $nip_to_edit = null; // Invalidate jika kosong setelah trim
    }
} else {
    $error_message = "NIP dosen tidak valid atau tidak ditemukan untuk diedit.";
}

// Sertakan file koneksi database
require_once '../config/db_connect.php';

// 3. FUNGSI UNTUK MENGAMBIL DATA DOSEN
function getDosenData($conn_db, $nip, &$out_error_message) {
    $data = null;
    $sql = "SELECT nip, password, nama_dosen, email, status_akun FROM dosen_pembimbing WHERE nip = ? LIMIT 1";
    $stmt = $conn_db->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("s", $nip);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows === 1) {
            $data = $result->fetch_assoc();
        } else {
            $out_error_message = "Data dosen dengan NIP " . htmlspecialchars($nip) . " tidak ditemukan.";
        }
        $stmt->close();
    } else {
        $out_error_message = "Gagal menyiapkan query untuk mengambil data dosen: " . (($conn_db->error) ? htmlspecialchars($conn_db->error) : "Kesalahan DB tidak diketahui.");
    }
    return $data;
}

// 4. PROSES UPDATE DATA JIKA FORM DISUBMIT (METHOD POST)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_edit_dosen']) && !empty($nip_to_edit)) {
    $nip_form = $_POST['nip']; // NIP dari hidden field

    if ($nip_form !== $nip_to_edit) {
        $error_message = "Kesalahan: NIP tidak cocok dengan data yang akan diedit.";
    } else {
        $nama_dosen_input = trim($_POST['nama_dosen']);
        $email_input = trim($_POST['email']);
        $status_akun_input = $_POST['status_akun'];
        $password_baru_input = $_POST['password_baru']; // Bisa kosong

        // Validasi dasar
        if (empty($nama_dosen_input) || empty($email_input) || empty($status_akun_input)) {
            $error_message = "Nama Dosen, Email, dan Status Akun wajib diisi.";
        } elseif (!filter_var($email_input, FILTER_VALIDATE_EMAIL)) {
            $error_message = "Format email tidak valid.";
        }
        // Validasi status akun (sesuai ENUM di tabel dosen_pembimbing)
        $allowed_statuses_dosen = ['active', 'inactive'];
        if (!in_array($status_akun_input, $allowed_statuses_dosen)) {
            $error_message = "Status akun yang dipilih tidak valid.";
        }

        if (empty($error_message)) {
            if ($conn && ($conn instanceof mysqli)) {
                // Cek apakah email baru sudah digunakan oleh dosen lain (kecuali dirinya sendiri)
                $sql_check_email = "SELECT nip FROM dosen_pembimbing WHERE email = ? AND nip != ?";
                $stmt_check_email = $conn->prepare($sql_check_email);
                if($stmt_check_email) {
                    $stmt_check_email->bind_param("ss", $email_input, $nip_to_edit);
                    $stmt_check_email->execute();
                    $stmt_check_email->store_result();

                    if ($stmt_check_email->num_rows > 0) {
                        $error_message = "Email '" . htmlspecialchars($email_input) . "' sudah digunakan oleh dosen lain.";
                    }
                    $stmt_check_email->close();
                } else {
                    $error_message = "Gagal memeriksa duplikasi email: ". htmlspecialchars($conn->error);
                }


                if (empty($error_message)) { // Lanjutkan jika tidak ada error duplikasi email
                    $fields_to_update = "nama_dosen = ?, email = ?, status_akun = ?";
                    $types = "sss";
                    $params = [$nama_dosen_input, $email_input, $status_akun_input];

                    if (!empty($password_baru_input)) {
                        if (strlen($password_baru_input) < 6) { // Contoh validasi panjang password
                             $error_message = "Password baru minimal harus 6 karakter.";
                        } else {
                            $fields_to_update .= ", password = ?";
                            $types .= "s";
                            $params[] = $password_baru_input; // Password plain text
                        }
                    }
                    
                    if (empty($error_message)) { // Lanjutkan jika tidak ada error password
                        $sql_update = "UPDATE dosen_pembimbing SET $fields_to_update WHERE nip = ?";
                        $types .= "s";
                        $params[] = $nip_to_edit;

                        $stmt_update = $conn->prepare($sql_update);
                        if ($stmt_update) {
                            $ref_params = [];
                            foreach ($params as $key => $value) {
                                $ref_params[$key] = &$params[$key];
                            }
                            array_unshift($ref_params, $types);
                            call_user_func_array([$stmt_update, 'bind_param'], $ref_params);

                            if ($stmt_update->execute()) {
                                if ($stmt_update->affected_rows > 0) {
                                    $success_message = "Data dosen NIP " . htmlspecialchars($nip_to_edit) . " berhasil diperbarui.";
                                    // Untuk update session nama jika admin edit profilnya sendiri (tidak relevan di sini)
                                    // if ($_SESSION['user_id'] == $nip_to_edit) { $_SESSION['user_nama'] = $nama_dosen_input; }
                                } else {
                                    $success_message = "Tidak ada perubahan data yang dilakukan (mungkin data masih sama).";
                                }
                            } else {
                                $error_message = "Gagal memperbarui data dosen: " . htmlspecialchars($stmt_update->error);
                            }
                            $stmt_update->close();
                        } else {
                            $error_message = "Gagal menyiapkan statement update dosen: " . htmlspecialchars($conn->error);
                        }
                    }
                }
            } else {
                $error_message = "Koneksi database gagal atau tidak valid saat update.";
            }
        }
    }
}

// Selalu ambil data terbaru untuk ditampilkan di form
if (!empty($nip_to_edit) && $conn && ($conn instanceof mysqli) && empty($error_message_initial_load) /* Hindari fetch jika NIP awal sudah error */) {
    $dosen_data = getDosenData($conn, $nip_to_edit, $error_message); // $error_message akan di-pass by reference
    if (!$dosen_data && empty($error_message)) { // Jika fungsi return null tapi tidak set error
        $error_message = "Gagal memuat data dosen untuk NIP " . htmlspecialchars($nip_to_edit) . ".";
    }
}


// Daftar status akun (sesuai ENUM di tabel dosen_pembimbing)
$opsi_status_akun_dosen = [
    'active' => 'Active',
    'inactive' => 'Inactive'
];

// Set judul halaman
$page_title = "Edit Akun Dosen";
if ($dosen_data && !empty($dosen_data['nama_dosen'])) {
    $page_title = "Edit: " . htmlspecialchars($dosen_data['nama_dosen']) . " (" . htmlspecialchars($dosen_data['nip']) . ")";
} elseif (!empty($nip_to_edit) && empty($dosen_data) && empty($error_message)) {
    $page_title = "Edit Dosen NIP: " . htmlspecialchars($nip_to_edit);
}
require_once '../includes/header.php';
?>

<div class="page-layout-wrapper">

    <?php require_once '../includes/sidebar_admin_prodi.php'; ?>

    <main class="main-content-area">
        <div class="form-container edit-dosen-form">
            <h1><?php echo htmlspecialchars($page_title); ?></h1>
            <a href="/KP/admin_prodi/pengguna_dosen_kelola.php" class="btn btn-light btn-sm mb-3">&laquo; Kembali ke Daftar Dosen</a>
            <hr>

            <?php if (!empty($success_message)): ?>
                <div class="message success"><p><?php echo htmlspecialchars($success_message); ?></p></div>
            <?php endif; ?>
            <?php if (!empty($error_message)): ?>
                <div class="message error"><p><?php echo htmlspecialchars($error_message); ?></p></div>
            <?php endif; ?>

            <?php if ($dosen_data): // Hanya tampilkan form jika data dosen berhasil diambil ?>
            <form action="/KP/admin_prodi/pengguna_dosen_edit.php?nip=<?php echo htmlspecialchars($nip_to_edit); ?>" method="POST">
                <input type="hidden" name="nip" value="<?php echo htmlspecialchars($dosen_data['nip']); ?>">

                <fieldset>
                    <legend>Data Dosen</legend>
                    <div class="form-group">
                        <label for="view_nip">NIP:</label>
                        <input type="text" id="view_nip" value="<?php echo htmlspecialchars($dosen_data['nip']); ?>" readonly class="readonly-input">
                    </div>
                    <div class="form-group">
                        <label for="nama_dosen">Nama Lengkap Dosen (*):</label>
                        <input type="text" id="nama_dosen" name="nama_dosen" value="<?php echo htmlspecialchars($dosen_data['nama_dosen']); ?>" required maxlength="100">
                    </div>
                    <div class="form-group">
                        <label for="email">Email (*):</label>
                        <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($dosen_data['email']); ?>" required maxlength="100">
                    </div>
                </fieldset>

                <fieldset>
                    <legend>Pengaturan Akun</legend>
                    <div class="form-group">
                        <label for="password_sekarang">Password Saat Ini:</label>
                        <input type="text" id="password_sekarang" value="<?php echo htmlspecialchars($dosen_data['password']); ?>" readonly class="readonly-input" title="Password saat ini (plain text).">
                        <small>Password ditampilkan apa adanya (plain text).</small>
                    </div>
                    <div class="form-group">
                        <label for="password_baru">Password Baru:</label>
                        <input type="password" id="password_baru" name="password_baru">
                        <small>Kosongkan jika tidak ingin mengubah password. Minimal 6 karakter jika diisi.</small>
                    </div>
                    <div class="form-group">
                        <label for="status_akun">Status Akun (*):</label>
                        <select id="status_akun" name="status_akun" required>
                            <?php foreach ($opsi_status_akun_dosen as $value => $text): ?>
                                <option value="<?php echo $value; ?>" <?php echo ($dosen_data['status_akun'] == $value) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($text); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </fieldset>

                <div class="form-actions">
                    <button type="submit" name="submit_edit_dosen" class="btn btn-primary">Simpan Perubahan</button>
                    <a href="/KP/admin_prodi/pengguna_dosen_kelola.php" class="btn btn-secondary">Batal</a>
                </div>
            </form>
            <?php elseif(empty($error_message) && !empty($nip_to_edit)): ?>
                <div class="message info"><p>Memuat data dosen...</p></div>
            <?php endif; ?>

        </div>
    </main>
</div>

<style>
    /* Asumsikan CSS umum dari header, sidebar, form-container, fieldset, legend, form-group, message, btn sudah ada */
    .edit-dosen-form h1 { margin-top: 0; margin-bottom: 5px; }
    .edit-dosen-form hr { margin-top:15px; margin-bottom: 20px; }
    .btn.mb-3 { margin-bottom: 1rem !important; }

    .readonly-input { background-color: #e9ecef; cursor: not-allowed; }
    .form-group small { display: block; font-size: 0.85em; color: #6c757d; margin-top: 4px; }
</style>

<?php
require_once '../includes/footer.php';

if (isset($conn) && ($conn instanceof mysqli)) {
    $conn->close();
}
?>