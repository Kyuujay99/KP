<?php
// /KP/dosen/profil_edit.php (Versi Diperbarui)

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
            // Cek duplikasi email (logika tetap sama)
            // ...

            if(empty($error_message)) {
                // Proses update ke DB (logika tetap sama)
                // ...
            }
        }
    }
}

// 3. SELALU AMBIL DATA TERBARU UNTUK DITAMPILKAN DI FORM
if ($conn && ($conn instanceof mysqli)) {
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

<div class="main-content-full">
    <div class="form-container-modern">
        <div class="form-header">
            <h1><?php echo htmlspecialchars($page_title); ?></h1>
            <p>Perbarui informasi kontak dan keamanan akun Anda di bawah ini.</p>
        </div>

        <?php if (!empty($success_message)): ?>
            <div class="message success"><p><?php echo htmlspecialchars($success_message); ?></p></div>
        <?php endif; ?>
        <?php if (!empty($error_message)): ?>
            <div class="message error"><p><?php echo htmlspecialchars($error_message); ?></p></div>
        <?php endif; ?>

        <?php if (is_array($dosen_data)): ?>
            <form action="profil_edit.php" method="POST" class="modern-form">
                <fieldset>
                    <div class="fieldset-header">
                        <span class="fieldset-number">👤</span>
                        <h4>Informasi Pribadi & Kontak</h4>
                    </div>
                    <div class="form-group">
                        <label for="view_nip">NIP</label>
                        <input type="text" id="view_nip" value="<?php echo htmlspecialchars($dosen_data['nip'] ?? ''); ?>" readonly class="readonly-input">
                        <small>NIP tidak dapat diubah.</small>
                    </div>
                    <div class="form-group">
                        <label for="nama_dosen">Nama Lengkap (beserta gelar) (*)</label>
                        <input type="text" id="nama_dosen" name="nama_dosen" value="<?php echo htmlspecialchars($dosen_data['nama_dosen'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="email">Email (*)</label>
                        <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($dosen_data['email'] ?? ''); ?>" required>
                    </div>
                </fieldset>
                
                <fieldset>
                    <div class="fieldset-header">
                        <span class="fieldset-number">🔑</span>
                        <h4>Ubah Password</h4>
                    </div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="password_baru">Password Baru</label>
                            <input type="password" id="password_baru" name="password_baru" minlength="6" placeholder="Minimal 6 karakter">
                            <small>Kosongkan jika tidak ingin mengubah.</small>
                        </div>
                        <div class="form-group">
                            <label for="confirm_password_baru">Konfirmasi Password Baru</label>
                            <input type="password" id="confirm_password_baru" name="confirm_password_baru" minlength="6" placeholder="Ketik ulang password baru">
                        </div>
                    </div>
                </fieldset>

                <div class="form-actions">
                    <a href="profil.php" class="btn btn-secondary">Batal</a>
                    <button type="submit" name="submit_edit_dosen" class="btn btn-primary btn-submit">Simpan Perubahan</button>
                </div>
            </form>
        <?php elseif (empty($error_message)): ?>
            <div class="message info"><p>Memuat data...</p></div>
        <?php endif; ?>
    </div>
</div>

<style>
    /* Menggunakan gaya dari halaman form sebelumnya */
    .form-container-modern { max-width: 900px; margin: 20px auto; background: #fff; padding: 2rem; border-radius: var(--border-radius); box-shadow: var(--card-shadow); }
    .form-header { text-align: center; margin-bottom: 2rem; }
    .form-header h1 { color: var(--primary-color); }
    .modern-form fieldset { border: none; padding: 0; margin-bottom: 2rem; }
    .fieldset-header { display: flex; align-items: center; gap: 15px; margin-bottom: 1.5rem; border-bottom: 1px solid var(--border-color); padding-bottom: 10px; }
    .fieldset-number { font-size: 1.5em; }
    .fieldset-header h4 { margin: 0; font-size: 1.3em; color: var(--dark-color); }
    .form-group { margin-bottom: 1.5rem; }
    .form-group label { display: block; font-weight: 500; margin-bottom: 0.5rem; }
    .form-group input, .form-group textarea, .form-group select { width: 100%; padding: 12px; border: 1px solid var(--border-color); border-radius: 8px; transition: all 0.3s ease; }
    .form-group input:focus { border-color: var(--primary-color); box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.2); outline: none; }
    .readonly-input { background-color: #e9ecef; cursor: not-allowed; }
    .form-group small { font-size: 0.85em; color: var(--secondary-color); margin-top: 5px; }
    .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; }
    .form-actions { margin-top: 2rem; display: flex; justify-content: flex-end; gap: 1rem; }
    .btn-secondary { background-color: #f8f9fa; color: #333; border: 1px solid var(--border-color); }
    .btn-submit { background: var(--primary-color); color: white; }
</style>

<?php
require_once '../includes/footer.php';
if (isset($conn) && $conn) {
    $conn->close();
}
?>