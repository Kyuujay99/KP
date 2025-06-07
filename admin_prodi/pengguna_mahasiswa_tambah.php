<?php
// /KP/admin_prodi/pengguna_mahasiswa_tambah.php (Versi Final)

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

// Inisialisasi variabel untuk menyimpan nilai input jika ada error validasi
$input_nim = '';
$input_nama = '';
$input_email = '';
$input_no_hp = '';
$input_prodi = '';
$input_angkatan = '';
$input_status_akun = 'pending_verification';

// Logika proses form saat disubmit
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_tambah_mahasiswa'])) {
    $input_nim = trim($_POST['nim']);
    $input_nama = trim($_POST['nama']);
    $input_email = trim($_POST['email']);
    $password_input = $_POST['password'];
    $confirm_password_input = $_POST['confirm_password'];
    $input_no_hp = trim($_POST['no_hp']);
    $input_prodi = trim($_POST['prodi']);
    $input_angkatan = !empty($_POST['angkatan']) ? (int)$_POST['angkatan'] : null;
    $input_status_akun = $_POST['status_akun'];

    if (empty($input_nim) || empty($input_nama) || empty($input_email) || empty($password_input) || empty($input_prodi) || empty($input_angkatan) || empty($input_status_akun)) {
        $error_message = "Semua field yang ditandai (*) wajib diisi.";
    } elseif ($password_input !== $confirm_password_input) {
        $error_message = "Password dan Konfirmasi Password tidak cocok.";
    } elseif (strlen($password_input) < 6) {
        $error_message = "Password minimal harus 6 karakter.";
    } elseif (!filter_var($input_email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Format email tidak valid.";
    } else {
        if ($conn) {
            $stmt_check = $conn->prepare("SELECT nim FROM mahasiswa WHERE nim = ? OR email = ?");
            $stmt_check->bind_param("ss", $input_nim, $input_email);
            $stmt_check->execute();
            $stmt_check->store_result();

            if ($stmt_check->num_rows > 0) {
                $error_message = "NIM atau Email sudah terdaftar.";
            } else {
                $sql_insert = "INSERT INTO mahasiswa (nim, password, nama, email, no_hp, prodi, angkatan, status_akun) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt_insert = $conn->prepare($sql_insert);
                $stmt_insert->bind_param("ssssssis", $input_nim, $password_input, $input_nama, $input_email, $input_no_hp, $input_prodi, $input_angkatan, $input_status_akun);
                if ($stmt_insert->execute()) {
                    $success_message = "Mahasiswa baru '" . htmlspecialchars($input_nama) . "' berhasil ditambahkan.";
                    // Kosongkan variabel agar form bersih
                    $input_nim = $input_nama = $input_email = $input_no_hp = $input_prodi = $input_angkatan = '';
                    $input_status_akun = 'pending_verification';
                } else {
                    $error_message = "Gagal menambahkan mahasiswa baru: " . $stmt_insert->error;
                }
                $stmt_insert->close();
            }
            $stmt_check->close();
        } else {
            $error_message = "Koneksi database gagal.";
        }
    }
}

$opsi_status_akun = [
    'pending_verification' => 'Pending Verification',
    'active' => 'Active',
    'suspended' => 'Suspended'
];
$opsi_prodi = [
    'Teknik Informatika' => 'Teknik Informatika', 'Sistem Informasi' => 'Sistem Informasi',
    'Teknik Elektro' => 'Teknik Elektro', 'Teknik Mesin' => 'Teknik Mesin', 'Teknik Industri' => 'Teknik Industri',
];

$page_title = "Tambah Akun Mahasiswa Baru";
require_once '../includes/header.php';
?>

<div class="main-content-full">
    <div class="form-container-modern">
        <div class="form-header">
            <h1><?php echo htmlspecialchars($page_title); ?></h1>
            <p>Isi formulir di bawah ini untuk menambahkan akun mahasiswa baru ke dalam sistem.</p>
        </div>

        <?php if (!empty($success_message)): ?>
            <div class="message success">
                <p><?php echo htmlspecialchars($success_message); ?></p>
                <a href="pengguna_mahasiswa_kelola.php" class="btn btn-secondary">Kembali ke Daftar Mahasiswa</a>
            </div>
        <?php endif; ?>
        <?php if (!empty($error_message)): ?>
            <div class="message error"><p><?php echo htmlspecialchars($error_message); ?></p></div>
        <?php endif; ?>

        <?php if (!$success_message): // Sembunyikan form jika sukses ?>
        <form action="pengguna_mahasiswa_tambah.php" method="POST" class="modern-form">
            <fieldset>
                <div class="fieldset-header"><h4>Data Identitas & Login</h4></div>
                <div class="form-grid">
                    <div class="form-group">
                        <label for="nim">NIM (*)</label>
                        <input type="text" id="nim" name="nim" value="<?php echo htmlspecialchars($input_nim); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="nama">Nama Lengkap (*)</label>
                        <input type="text" id="nama" name="nama" value="<?php echo htmlspecialchars($input_nama); ?>" required>
                    </div>
                </div>
                <div class="form-group">
                    <label for="email">Email (*)</label>
                    <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($input_email); ?>" required>
                </div>
                <div class="form-grid">
                    <div class="form-group">
                        <label for="password">Password (*)</label>
                        <input type="password" id="password" name="password" required minlength="6">
                        <small>Minimal 6 karakter.</small>
                    </div>
                    <div class="form-group">
                        <label for="confirm_password">Konfirmasi Password (*)</label>
                        <input type="password" id="confirm_password" name="confirm_password" required minlength="6">
                    </div>
                </div>
                <div class="form-group">
                    <label for="no_hp">Nomor HP</label>
                    <input type="text" id="no_hp" name="no_hp" value="<?php echo htmlspecialchars($input_no_hp); ?>">
                </div>
            </fieldset>

            <fieldset>
                <div class="fieldset-header"><h4>Data Akademik & Status Akun</h4></div>
                <div class="form-grid">
                    <div class="form-group">
                        <label for="prodi">Program Studi (*)</label>
                        <select id="prodi" name="prodi" required>
                            <option value="">-- Pilih Prodi --</option>
                            <?php foreach ($opsi_prodi as $value => $text): ?>
                                <option value="<?php echo htmlspecialchars($value); ?>" <?php echo ($input_prodi == $value) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($text); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="angkatan">Angkatan (*)</label>
                        <input type="number" id="angkatan" name="angkatan" value="<?php echo htmlspecialchars($input_angkatan); ?>" min="2000" max="<?php echo date('Y') + 1; ?>" required>
                    </div>
                </div>
                <div class="form-group">
                    <label for="status_akun">Status Akun Awal (*)</label>
                    <select id="status_akun" name="status_akun" required>
                        <?php foreach ($opsi_status_akun as $value => $text): ?>
                            <option value="<?php echo $value; ?>" <?php echo ($input_status_akun == $value) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($text); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </fieldset>

            <div class="form-actions">
                <a href="pengguna_mahasiswa_kelola.php" class="btn btn-secondary">Batal</a>
                <button type="submit" name="submit_tambah_mahasiswa" class="btn btn-primary">Tambah Mahasiswa</button>
            </div>
        </form>
        <?php endif; ?>
    </div>
</div>

<style>
    /* Menggunakan gaya dari halaman form modern lainnya */
    .form-container-modern { max-width: 900px; }
</style>

<?php
require_once '../includes/footer.php';
if (isset($conn)) { $conn->close(); }
?>