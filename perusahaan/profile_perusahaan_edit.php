<?php
// /KP/perusahaan/profil_perusahaan_edit.php

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

$id_perusahaan_login = $_SESSION['user_id'];
$perusahaan_data = null;
$error_message = '';
$success_message = '';

require_once '../config/db_connect.php';

// 2. PROSES UPDATE DATA JIKA FORM DISUBMIT
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_edit_perusahaan'])) {
    if (!$conn || !($conn instanceof mysqli) || $conn->connect_error) {
        $error_message = "Koneksi database tidak tersedia.";
    } else {
        $nama_perusahaan_input = trim($_POST['nama_perusahaan']);
        $alamat_input = trim($_POST['alamat']);
        $bidang_input = trim($_POST['bidang']);
        $kontak_nama_input = trim($_POST['kontak_person_nama']);
        $kontak_email_input = trim($_POST['kontak_person_email']);
        $kontak_no_hp_input = trim($_POST['kontak_person_no_hp']);
        $password_baru_input = $_POST['password_perusahaan_baru'];

        if (empty($nama_perusahaan_input)) {
            $error_message = "Nama Perusahaan wajib diisi.";
        } elseif (!empty($kontak_email_input) && !filter_var($kontak_email_input, FILTER_VALIDATE_EMAIL)) {
            $error_message = "Format Email Kontak Person tidak valid.";
        }

        if (empty($error_message)) {
            $fields_to_update = "nama_perusahaan = ?, alamat = ?, bidang = ?, kontak_person_nama = ?, kontak_person_email = ?, kontak_person_no_hp = ?";
            $types = "ssssss";
            $params = [$nama_perusahaan_input, $alamat_input, $bidang_input, $kontak_nama_input, $kontak_email_input, $kontak_no_hp_input];

            if (!empty($password_baru_input)) {
                if (strlen($password_baru_input) < 6) {
                    $error_message = "Password baru minimal harus 6 karakter.";
                } else {
                    $fields_to_update .= ", password_perusahaan = ?";
                    $types .= "s";
                    $params[] = $password_baru_input;
                }
            }
            
            if (empty($error_message)) {
                $sql_update = "UPDATE perusahaan SET $fields_to_update WHERE id_perusahaan = ?";
                $types .= "i";
                $params[] = $id_perusahaan_login;

                $stmt_update = $conn->prepare($sql_update);
                if ($stmt_update) {
                    $ref_params = [];
                    foreach ($params as $key => $value) { $ref_params[$key] = &$params[$key]; }
                    array_unshift($ref_params, $types);
                    call_user_func_array([$stmt_update, 'bind_param'], $ref_params);

                    if ($stmt_update->execute()) {
                        if ($stmt_update->affected_rows > 0) {
                            $success_message = "Profil perusahaan berhasil diperbarui.";
                            $_SESSION['user_nama'] = $nama_perusahaan_input;
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
if ($conn && ($conn instanceof mysqli)) {
    $sql_get = "SELECT * FROM perusahaan WHERE id_perusahaan = ?";
    $stmt_get = $conn->prepare($sql_get);
    if ($stmt_get) {
        $stmt_get->bind_param("i", $id_perusahaan_login);
        $stmt_get->execute();
        $result_get = $stmt_get->get_result();
        if ($result_get->num_rows === 1) {
            $perusahaan_data = $result_get->fetch_assoc();
        } else {
            if(empty($error_message)) $error_message = "Gagal memuat data profil perusahaan Anda.";
        }
        $stmt_get->close();
    } else {
        if(empty($error_message)) $error_message = "Gagal memuat data perusahaan.";
    }
} else {
    if(empty($error_message)) $error_message = "Koneksi database tidak tersedia.";
}

$page_title = "Edit Profil Perusahaan";
require_once '../includes/header.php';
?>

<div class="page-layout-wrapper">
    <?php require_once '../includes/sidebar_perusahaan.php'; ?>
    <main class="main-content-area">
        <div class="form-container edit-perusahaan-profil">
            <h1><?php echo htmlspecialchars($page_title); ?></h1>
            <a href="/KP/perusahaan/profil_perusahaan.php" class="btn btn-light btn-sm mb-3">&laquo; Kembali ke Profil</a>
            <hr>

            <?php if (!empty($success_message)): ?><div class="message success"><p><?php echo htmlspecialchars($success_message); ?></p></div><?php endif; ?>
            <?php if (!empty($error_message)): ?><div class="message error"><p><?php echo htmlspecialchars($error_message); ?></p></div><?php endif; ?>

            <?php if (is_array($perusahaan_data)): ?>
                <form action="/KP/perusahaan/profil_perusahaan_edit.php" method="POST">
                    <fieldset>
                        <legend>Informasi Perusahaan</legend>
                        <div class="form-group">
                            <label for="nama_perusahaan">Nama Perusahaan (*):</label>
                            <input type="text" id="nama_perusahaan" name="nama_perusahaan" value="<?php echo htmlspecialchars($perusahaan_data['nama_perusahaan'] ?? ''); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="email_perusahaan">Email Perusahaan (Login):</label>
                            <input type="email" id="email_perusahaan" name="email_perusahaan_view" value="<?php echo htmlspecialchars($perusahaan_data['email_perusahaan'] ?? ''); ?>" readonly class="readonly-input">
                            <small>Email login tidak dapat diubah dari halaman ini.</small>
                        </div>
                        <div class="form-group">
                            <label for="alamat">Alamat Lengkap:</label>
                            <textarea id="alamat" name="alamat" rows="3"><?php echo htmlspecialchars($perusahaan_data['alamat'] ?? ''); ?></textarea>
                        </div>
                        <div class="form-group">
                            <label for="bidang">Bidang Usaha:</label>
                            <input type="text" id="bidang" name="bidang" value="<?php echo htmlspecialchars($perusahaan_data['bidang'] ?? ''); ?>">
                        </div>
                    </fieldset>
                    <fieldset>
                        <legend>Informasi Kontak Person</legend>
                        <div class="form-group">
                            <label for="kontak_person_nama">Nama Kontak Person:</label>
                            <input type="text" id="kontak_person_nama" name="kontak_person_nama" value="<?php echo htmlspecialchars($perusahaan_data['kontak_person_nama'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label for="kontak_person_email">Email Kontak Person:</label>
                            <input type="email" id="kontak_person_email" name="kontak_person_email" value="<?php echo htmlspecialchars($perusahaan_data['kontak_person_email'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label for="kontak_person_no_hp">No. HP Kontak Person:</label>
                            <input type="text" id="kontak_person_no_hp" name="kontak_person_no_hp" value="<?php echo htmlspecialchars($perusahaan_data['kontak_person_no_hp'] ?? ''); ?>">
                        </div>
                    </fieldset>
                    <fieldset>
                        <legend>Ubah Password</legend>
                        <div class="form-group">
                            <label for="password_perusahaan_baru">Password Baru:</label>
                            <input type="password" id="password_perusahaan_baru" name="password_perusahaan_baru" minlength="6">
                            <small>Kosongkan jika tidak ingin mengubah password.</small>
                        </div>
                    </fieldset>
                    <div class="form-actions">
                        <button type="submit" name="submit_edit_perusahaan" class="btn btn-primary">Simpan Perubahan</button>
                    </div>
                </form>
            <?php elseif (empty($error_message)): ?>
                <div class="message info"><p>Memuat data...</p></div>
            <?php endif; ?>
        </div>
    </main>
</div>

<style>
    /* ... Salin CSS dari profil_perusahaan.php dan tambahkan styling form jika perlu ... */
    .edit-perusahaan-profil h1 { margin-top: 0; }
    .readonly-input { background-color: #e9ecef; cursor: not-allowed; }
    .form-group { margin-bottom: 1rem; }
    .form-group label { display: block; font-weight: bold; margin-bottom: .5rem; }
    .form-group small { display: block; font-size: 0.85em; color: #6c757d; margin-top: 4px; }
    .form-group textarea, .form-control { width: 100%; padding: .375rem .75rem; font-size: 1rem; line-height: 1.5; border: 1px solid #ced4da; border-radius: .25rem; }
    .form-actions { margin-top: 1.5rem; }
</style>

<?php
require_once '../includes/footer.php';
if (isset($conn) && ($conn instanceof mysqli)) {
    $conn->close();
}
?>