<?php
// /KP/admin_prodi/pengguna_dosen_tambah.php (Versi Diperbarui)

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once '../includes/auth_check.php';
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin_prodi') {
    header("Location: /KP/index.php?error=unauthorized_admin");
    exit();
}

require_once '../config/db_connect.php';

$error_message = '';
$success_message = '';
$input_nip = '';
$input_nama_dosen = '';
$input_email_dosen = '';
$input_status_akun_dosen = 'active';

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_tambah_dosen'])) {
    $input_nip = trim($_POST['nip']);
    $input_nama_dosen = trim($_POST['nama_dosen']);
    $input_email_dosen = trim($_POST['email']);
    $password_input = $_POST['password'];
    $confirm_password_input = $_POST['confirm_password'];
    $input_status_akun_dosen = $_POST['status_akun'];

    if (empty($input_nip) || empty($input_nama_dosen) || empty($input_email_dosen) || empty($password_input)) {
        $error_message = "Semua field yang ditandai (*) wajib diisi.";
    } elseif ($password_input !== $confirm_password_input) {
        $error_message = "Password dan Konfirmasi Password tidak cocok.";
    } elseif (strlen($password_input) < 6) {
        $error_message = "Password minimal harus 6 karakter.";
    } elseif (!filter_var($input_email_dosen, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Format email tidak valid.";
    } else {
        if ($conn) {
            $stmt_check = $conn->prepare("SELECT nip FROM dosen_pembimbing WHERE nip = ? OR email = ?");
            $stmt_check->bind_param("ss", $input_nip, $input_email_dosen);
            $stmt_check->execute();
            $stmt_check->store_result();

            if ($stmt_check->num_rows > 0) {
                $error_message = "NIP atau Email sudah terdaftar.";
            } else {
                $stmt_insert = $conn->prepare("INSERT INTO dosen_pembimbing (nip, password, nama_dosen, email, status_akun) VALUES (?, ?, ?, ?, ?)");
                $stmt_insert->bind_param("sssss", $input_nip, $password_input, $input_nama_dosen, $input_email_dosen, $input_status_akun_dosen);
                if ($stmt_insert->execute()) {
                    $success_message = "Dosen baru '" . htmlspecialchars($input_nama_dosen) . "' berhasil ditambahkan.";
                    $input_nip = $input_nama_dosen = $input_email_dosen = '';
                    $input_status_akun_dosen = 'active';
                } else {
                    $error_message = "Gagal menambahkan dosen baru: " . $stmt_insert->error;
                }
                $stmt_insert->close();
            }
            $stmt_check->close();
        } else {
            $error_message = "Koneksi database gagal.";
        }
    }
}

$opsi_status_akun_dosen = ['active' => 'Active', 'inactive' => 'Inactive'];
$page_title = "Tambah Akun Dosen Baru";
require_once '../includes/header.php';
?>

<div class="main-content-full">
    <div class="form-container-modern">
        <div class="form-header">
            <h1><?php echo htmlspecialchars($page_title); ?></h1>
            <p>Isi formulir untuk menambahkan akun dosen pembimbing atau penguji baru ke dalam sistem.</p>
        </div>

        <?php if (!empty($success_message)): ?>
            <div class="message success">
                <p><?php echo htmlspecialchars($success_message); ?></p>
                <a href="pengguna_dosen_kelola.php" class="btn btn-secondary">Kembali ke Daftar Dosen</a>
            </div>
        <?php endif; ?>
        <?php if (!empty($error_message)): ?>
            <div class="message error"><p><?php echo htmlspecialchars($error_message); ?></p></div>
        <?php endif; ?>

        <?php if (!$success_message): ?>
        <form action="pengguna_dosen_tambah.php" method="POST" class="modern-form">
            <fieldset>
                <div class="fieldset-header">
                    <span class="fieldset-number">👤</span>
                    <h4>Data Diri & Institusi</h4>
                </div>
                <div class="form-grid-2">
                    <div class="form-group">
                        <label for="nip">NIP (*)</label>
                        <input type="text" id="nip" name="nip" value="<?php echo htmlspecialchars($input_nip); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="nama_dosen">Nama Lengkap & Gelar (*)</label>
                        <input type="text" id="nama_dosen" name="nama_dosen" value="<?php echo htmlspecialchars($input_nama_dosen); ?>" required>
                    </div>
                </div>
                <div class="form-group">
                    <label for="email">Email Institusi (*)</label>
                    <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($input_email_dosen); ?>" required>
                </div>
            </fieldset>

            <fieldset>
                <div class="fieldset-header">
                    <span class="fieldset-number">🔑</span>
                    <h4>Keamanan & Status Akun</h4>
                </div>
                <div class="form-grid-2">
                    <div class="form-group">
                        <label for="password">Password Awal (*)</label>
                        <input type="password" id="password" name="password" required minlength="6" placeholder="Minimal 6 karakter">
                    </div>
                    <div class="form-group">
                        <label for="confirm_password">Konfirmasi Password (*)</label>
                        <input type="password" id="confirm_password" name="confirm_password" required minlength="6">
                    </div>
                </div>
                <div class="form-group">
                    <label for="status_akun">Status Akun Awal (*)</label>
                    <select id="status_akun" name="status_akun" required>
                        <?php foreach ($opsi_status_akun_dosen as $value => $text): ?>
                            <option value="<?php echo htmlspecialchars($value); ?>" <?php echo ($input_status_akun_dosen == $value) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($text); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </fieldset>

            <div class="form-actions">
                <a href="pengguna_dosen_kelola.php" class="btn btn-secondary">Batal</a>
                <button type="submit" name="submit_tambah_dosen" class="btn btn-primary">Tambah Dosen</button>
            </div>
        </form>
        <?php endif; ?>
    </div>
</div>

<style>
/* Menggunakan gaya dari halaman form modern mahasiswa */
.form-container-modern { max-width: 900px; }
.form-grid-2 {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1.5rem;
}
/* ... (salin sisa CSS dari file pengguna_mahasiswa_edit.php) ... */
</style>

<?php
require_once '../includes/footer.php';
if (isset($conn)) { $conn->close(); }
?>