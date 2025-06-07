<?php
// /KP/dosen/profil_edit.php

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

$nip_dosen_login = $_SESSION['user_id'];
$dosen_data = null;
$error_message = '';
$success_message = '';

require_once '../config/db_connect.php';

// 2. PROSES UPDATE DATA JIKA FORM DISUBMIT
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_edit_dosen'])) {
    if (!$conn || !($conn instanceof mysqli) || $conn->connect_error) {
        $error_message = "Koneksi database tidak tersedia.";
    } else {
        $nama_dosen_input = trim($_POST['nama_dosen']);
        $email_input = trim($_POST['email']);
        $password_baru_input = $_POST['password_baru'];
        $password_konfirmasi_input = $_POST['confirm_password_baru'];

        if (empty($nama_dosen_input) || empty($email_input)) {
            $error_message = "Nama Lengkap dan Email wajib diisi.";
        } elseif (!filter_var($email_input, FILTER_VALIDATE_EMAIL)) {
            $error_message = "Format email tidak valid.";
        }

        if (empty($error_message) && !empty($password_baru_input)) {
            if (strlen($password_baru_input) < 6) {
                $error_message = "Password baru minimal harus 6 karakter.";
            } elseif ($password_baru_input !== $password_konfirmasi_input) {
                $error_message = "Password baru dan konfirmasi password tidak cocok.";
            }
        }

        if (empty($error_message)) {
            // Cek duplikasi email jika email diubah
            $current_email_stmt = $conn->prepare("SELECT email FROM dosen_pembimbing WHERE nip = ?");
            if ($current_email_stmt) {
                $current_email_stmt->bind_param("s", $nip_dosen_login);
                $current_email_stmt->execute();
                $current_email_result = $current_email_stmt->get_result();
                if ($current_email_result->num_rows === 1) {
                    $current_email = $current_email_result->fetch_assoc()['email'];
                    if ($current_email !== $email_input) {
                        $check_email_stmt = $conn->prepare("SELECT nip FROM dosen_pembimbing WHERE email = ?");
                        if ($check_email_stmt) {
                            $check_email_stmt->bind_param("s", $email_input);
                            $check_email_stmt->execute();
                            $check_email_stmt->store_result();
                            if ($check_email_stmt->num_rows > 0) {
                                $error_message = "Email tersebut sudah digunakan oleh dosen lain.";
                            }
                            $check_email_stmt->close();
                        }
                    }
                }
                $current_email_stmt->close();
            }

            if(empty($error_message)) {
                $fields_to_update = "nama_dosen = ?, email = ?";
                $types = "ss";
                $params = [$nama_dosen_input, $email_input];

                if (!empty($password_baru_input)) {
                    $fields_to_update .= ", password = ?";
                    $types .= "s";
                    $params[] = $password_baru_input; // Ingat, ini plain text
                }

                $sql_update = "UPDATE dosen_pembimbing SET $fields_to_update WHERE nip = ?";
                $types .= "s";
                $params[] = $nip_dosen_login;

                $stmt_update = $conn->prepare($sql_update);
                if ($stmt_update) {
                    $ref_params = [];
                    foreach ($params as $key => $value) { $ref_params[$key] = &$params[$key]; }
                    array_unshift($ref_params, $types);
                    call_user_func_array([$stmt_update, 'bind_param'], $ref_params);

                    if ($stmt_update->execute()) {
                        if ($stmt_update->affected_rows > 0) {
                            $success_message = "Profil berhasil diperbarui.";
                            $_SESSION['user_nama'] = $nama_dosen_input;
                        } else {
                            $success_message = "Tidak ada perubahan data yang dilakukan.";
                        }
                    } else {
                        $error_message = "Gagal memperbarui profil: " . htmlspecialchars($stmt_update->error);
                    }
                    $stmt_update->close();
                } else {
                    $error_message = "Gagal menyiapkan statement update: " . htmlspecialchars($conn->error);
                }
            }
        }
    }
}

// 3. SELALU AMBIL DATA TERBARU UNTUK DITAMPILKAN DI FORM
if (empty($error_message_from_post) && $conn && ($conn instanceof mysqli)) {
    $sql_get = "SELECT nip, nama_dosen, email FROM dosen_pembimbing WHERE nip = ?";
    $stmt_get = $conn->prepare($sql_get);
    if ($stmt_get) {
        $stmt_get->bind_param("s", $nip_dosen_login);
        $stmt_get->execute();
        $result_get = $stmt_get->get_result();
        if ($result_get->num_rows === 1) {
            $dosen_data = $result_get->fetch_assoc();
        } else {
            if(empty($error_message)) $error_message = "Gagal memuat data profil Anda.";
        }
        $stmt_get->close();
    }
}

$page_title = "Edit Profil Dosen";
require_once '../includes/header.php';
?>

<div class="page-layout-wrapper">
    <?php require_once '../includes/sidebar_dosen.php'; ?>
    <main class="main-content-area">
        <div class="form-container edit-dosen-profil">
            <h1><?php echo htmlspecialchars($page_title); ?></h1>
            <a href="/KP/dosen/profil.php" class="btn btn-light btn-sm mb-3">&laquo; Kembali ke Profil</a>
            <hr>

            <?php if (!empty($success_message)): ?><div class="message success"><p><?php echo htmlspecialchars($success_message); ?></p></div><?php endif; ?>
            <?php if (!empty($error_message)): ?><div class="message error"><p><?php echo htmlspecialchars($error_message); ?></p></div><?php endif; ?>

            <?php if (is_array($dosen_data)): ?>
                <form action="/KP/dosen/profil_edit.php" method="POST">
                    <fieldset>
                        <legend>Informasi Pribadi & Kontak</legend>
                        <div class="form-group">
                            <label for="view_nip">NIP:</label>
                            <input type="text" id="view_nip" value="<?php echo htmlspecialchars($dosen_data['nip'] ?? ''); ?>" readonly class="readonly-input">
                        </div>
                        <div class="form-group">
                            <label for="nama_dosen">Nama Lengkap (beserta gelar) (*):</label>
                            <input type="text" id="nama_dosen" name="nama_dosen" class="form-control" value="<?php echo htmlspecialchars($dosen_data['nama_dosen'] ?? ''); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="email">Email (*):</label>
                            <input type="email" id="email" name="email" class="form-control" value="<?php echo htmlspecialchars($dosen_data['email'] ?? ''); ?>" required>
                        </div>
                    </fieldset>
                    <fieldset>
                        <legend>Ubah Password</legend>
                        <div class="form-group">
                            <label for="password_baru">Password Baru:</label>
                            <input type="password" id="password_baru" name="password_baru" class="form-control" minlength="6">
                            <small>Kosongkan jika tidak ingin mengubah password.</small>
                        </div>
                        <div class="form-group">
                            <label for="confirm_password_baru">Konfirmasi Password Baru:</label>
                            <input type="password" id="confirm_password_baru" name="confirm_password_baru" class="form-control" minlength="6">
                        </div>
                    </fieldset>
                    <div class="form-actions">
                        <button type="submit" name="submit_edit_dosen" class="btn btn-primary">Simpan Perubahan</button>
                    </div>
                </form>
            <?php elseif (empty($error_message)): ?>
                <div class="message info"><p>Memuat data...</p></div>
            <?php endif; ?>
        </div>
    </main>
</div>

<style>
    .edit-dosen-profil h1 { margin-top: 0; }
    .readonly-input { background-color: #e9ecef; cursor: not-allowed; }
    .form-group { margin-bottom: 1rem; }
    .form-group label { display: block; font-weight: bold; margin-bottom: .5rem; }
    .form-control { width: 100%; padding: .375rem .75rem; font-size: 1rem; line-height: 1.5; border: 1px solid #ced4da; border-radius: .25rem; }
    .form-actions { margin-top: 1.5rem; }
    .form-group small { display: block; font-size: 0.85em; color: #6c757d; margin-top: 4px; }
</style>

<?php
require_once '../includes/footer.php';
if (isset($conn) && ($conn instanceof mysqli)) {
    $conn->close();
}
?>