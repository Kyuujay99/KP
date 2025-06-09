<?php
// /KP/admin_prodi/pengguna_mahasiswa_tambah.php (Versi Disempurnakan)

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
                // Di dunia nyata, gunakan password_hash()
                // $hashed_password = password_hash($password_input, PASSWORD_DEFAULT);
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

<div class="form-container-modern">
    <div class="form-hero-section">
        <div class="form-hero-content">
            <div class="form-hero-icon">
                <svg viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>
            </div>
            <h1><?php echo htmlspecialchars($page_title); ?></h1>
            <p>Isi formulir di bawah ini untuk menambahkan akun mahasiswa baru ke dalam sistem.</p>
        </div>
    </div>

    <div class="form-wrapper">
        <?php if (!empty($success_message)): ?>
            <div class="message success">
                <h4>Berhasil!</h4>
                <p><?php echo htmlspecialchars($success_message); ?></p>
                <a href="pengguna_mahasiswa_kelola.php" class="btn btn-secondary">Kembali ke Daftar Mahasiswa</a>
            </div>
        <?php endif; ?>
        <?php if (!empty($error_message)): ?>
            <div class="message error"><p><?php echo htmlspecialchars($error_message); ?></p></div>
        <?php endif; ?>

        <?php if (!$success_message): ?>
        <form action="pengguna_mahasiswa_tambah.php" method="POST" class="modern-form">
            <fieldset>
                <div class="fieldset-header">
                    <h4><i class="icon">👤</i>Data Identitas & Login</h4>
                </div>
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
                <div class="fieldset-header">
                    <h4><i class="icon">🎓</i>Data Akademik & Status</h4>
                </div>
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
/* Modern Form Styles */
:root {
    --primary-color: #667eea;
    --text-primary: #1f2937;
    --text-secondary: #6b7280;
    --bg-light: #f9fafb;
    --border-color: #e5e7eb;
    --card-shadow: 0 10px 30px rgba(0,0,0,0.07);
    --border-radius: 16px;
}
.form-container-modern { max-width: 900px; margin: 0 auto; padding: 2rem 1rem; }
.form-container-modern svg { stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; fill: none; stroke: currentColor; }
.form-hero-section {
    padding: 3rem 2rem; background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%);
    border-radius: var(--border-radius); margin-bottom: 2rem; color: white; text-align: center;
}
.form-hero-content { max-width: 600px; margin: 0 auto; }
.form-hero-icon { width: 60px; height: 60px; background: rgba(255,255,255,0.1); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1.5rem; }
.form-hero-icon svg { width: 28px; height: 28px; stroke: white; }
.form-hero-section h1 { font-size: 2.5rem; font-weight: 700; margin-bottom: 0.5rem; }
.form-hero-section p { font-size: 1.1rem; opacity: 0.9; font-weight: 300; }
.form-wrapper { background-color: #ffffff; padding: 2.5rem; border-radius: var(--border-radius); box-shadow: var(--card-shadow); }

.modern-form fieldset { border: none; padding: 0; margin-bottom: 2.5rem; }
.fieldset-header { display: flex; align-items: center; gap: 0.75rem; padding-bottom: 1rem; margin-bottom: 1.5rem; border-bottom: 1px solid var(--border-color); }
.fieldset-header h4 { margin: 0; font-size: 1.25rem; }
.fieldset-header .icon { font-style: normal; color: var(--primary-color); }

.form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem; }
.form-group { margin-bottom: 1.5rem; }
.form-group label { display: block; font-weight: 500; margin-bottom: 0.5rem; font-size: 0.95rem; }
.form-group input, .form-group select {
    width: 100%; padding: 12px 15px; border: 1px solid var(--border-color);
    border-radius: 8px; font-size: 1em; font-family: 'Inter', sans-serif;
    transition: all 0.2s ease; background-color: var(--bg-light);
}
.form-group input:focus, .form-group select:focus {
    border-color: var(--primary-color); background-color: #fff;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.2); outline: none;
}
.form-group small { font-size: 0.85em; color: var(--text-secondary); margin-top: 5px; }

.form-actions { display: flex; justify-content: flex-end; gap: 1rem; padding-top: 1.5rem; border-top: 1px solid var(--border-color); }
.btn-secondary { background-color: var(--bg-light); color: var(--text-secondary); border: 1px solid var(--border-color); }
.btn-primary { background-color: var(--primary-color); color: white; }
</style>

<?php
require_once '../includes/footer.php';
if (isset($conn)) { $conn->close(); }
?>
