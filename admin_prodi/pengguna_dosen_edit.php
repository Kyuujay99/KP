<?php
// /KP/admin_prodi/pengguna_dosen_edit.php (Versi Disempurnakan)

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once '../includes/auth_check.php';
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin_prodi') {
    header("Location: /KP/index.php?error=unauthorized_admin");
    exit();
}

require_once '../config/db_connect.php';

$nip_to_edit = null;
$dosen_data = null;
$error_message = '';
$success_message = '';

if (isset($_GET['nip'])) {
    $nip_to_edit = trim($_GET['nip']);
    if (empty($nip_to_edit)) {
        $error_message = "NIP dosen tidak boleh kosong.";
        $nip_to_edit = null;
    }
} else {
    $error_message = "NIP dosen tidak valid atau tidak ditemukan.";
}

function getDosenData($conn_db, $nip, &$out_error_message) {
    $data = null;
    $sql = "SELECT nip, nama_dosen, email, status_akun FROM dosen_pembimbing WHERE nip = ? LIMIT 1";
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
    }
    return $data;
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_edit_dosen']) && !empty($nip_to_edit)) {
    if ($conn) {
        $nama_dosen_input = trim($_POST['nama_dosen']);
        $email_input = trim($_POST['email']);
        $status_akun_input = $_POST['status_akun'];
        $password_baru_input = $_POST['password_baru'];

        if (empty($nama_dosen_input) || empty($email_input) || empty($status_akun_input)) {
            $error_message = "Field dengan tanda (*) wajib diisi.";
        } elseif (!filter_var($email_input, FILTER_VALIDATE_EMAIL)) {
            $error_message = "Format email tidak valid.";
        } else {
            $stmt_check_email = $conn->prepare("SELECT nip FROM dosen_pembimbing WHERE email = ? AND nip != ?");
            $stmt_check_email->bind_param("ss", $email_input, $nip_to_edit);
            $stmt_check_email->execute();
            $stmt_check_email->store_result();
            if ($stmt_check_email->num_rows > 0) {
                $error_message = "Email '" . htmlspecialchars($email_input) . "' sudah digunakan oleh akun lain.";
            }
            $stmt_check_email->close();

            if (empty($error_message)) {
                $sql_update = "UPDATE dosen_pembimbing SET nama_dosen = ?, email = ?, status_akun = ?";
                $types = "sss";
                $params = [$nama_dosen_input, $email_input, $status_akun_input];

                if (!empty($password_baru_input)) {
                    if (strlen($password_baru_input) < 6) {
                        $error_message = "Password baru minimal harus 6 karakter.";
                    } else {
                        // Di dunia nyata, gunakan password_hash()
                        // $hashed_password = password_hash($password_baru_input, PASSWORD_DEFAULT);
                        $sql_update .= ", password = ?";
                        $types .= "s";
                        $params[] = $password_baru_input;
                    }
                }

                if (empty($error_message)) {
                    $sql_update .= " WHERE nip = ?";
                    $types .= "s";
                    $params[] = $nip_to_edit;
                    
                    $stmt_update = $conn->prepare($sql_update);
                    $stmt_update->bind_param($types, ...$params);
                    if ($stmt_update->execute()) {
                        if ($stmt_update->affected_rows > 0) {
                            $success_message = "Data dosen berhasil diperbarui.";
                        } else {
                            $success_message = "Tidak ada perubahan data yang dilakukan.";
                        }
                    } else {
                        $error_message = "Gagal memperbarui data: " . $stmt_update->error;
                    }
                    $stmt_update->close();
                }
            }
        }
    }
}

if (!empty($nip_to_edit) && $conn) {
    $dosen_data = getDosenData($conn, $nip_to_edit, $error_message);
}

$opsi_status_akun_dosen = ['active' => 'Active', 'inactive' => 'Inactive'];
$page_title = "Edit Akun Dosen";
if ($dosen_data && !empty($dosen_data['nama_dosen'])) {
    $page_title = "Edit: " . htmlspecialchars($dosen_data['nama_dosen']);
}
require_once '../includes/header.php';
?>

<div class="form-container-modern">
    <div class="form-hero-section">
        <div class="form-hero-content">
            <div class="form-hero-icon">
                <svg viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
            </div>
            <h1><?php echo htmlspecialchars($page_title); ?></h1>
            <p>Perbarui informasi detail, kontak, dan status akun untuk dosen ini.</p>
        </div>
    </div>
    
    <div class="form-wrapper">
        <?php if (!empty($success_message)): ?>
            <div class="message success">
                <h4>Berhasil!</h4>
                <p><?php echo htmlspecialchars($success_message); ?></p>
                <a href="pengguna_dosen_kelola.php" class="btn btn-secondary">Kembali ke Daftar Dosen</a>
            </div>
        <?php endif; ?>
        <?php if (!empty($error_message) && !$dosen_data): ?>
            <div class="message error"><p><?php echo htmlspecialchars($error_message); ?></p></div>
        <?php endif; ?>

        <?php if ($dosen_data): ?>
            <form action="pengguna_dosen_edit.php?nip=<?php echo htmlspecialchars($nip_to_edit); ?>" method="POST" class="modern-form">
                <input type="hidden" name="nip" value="<?php echo htmlspecialchars($dosen_data['nip']); ?>">

                <fieldset>
                    <div class="fieldset-header">
                        <h4><i class="icon">👤</i>Data Diri & Institusi</h4>
                    </div>
                    <div class="form-group">
                        <label for="view_nip">NIP</label>
                        <input type="text" id="view_nip" value="<?php echo htmlspecialchars($dosen_data['nip']); ?>" readonly class="readonly-input">
                    </div>
                    <div class="form-group">
                        <label for="nama_dosen">Nama Lengkap & Gelar (*)</label>
                        <input type="text" id="nama_dosen" name="nama_dosen" value="<?php echo htmlspecialchars($dosen_data['nama_dosen']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="email">Email Institusi (*)</label>
                        <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($dosen_data['email']); ?>" required>
                    </div>
                </fieldset>

                <fieldset>
                    <div class="fieldset-header">
                        <h4><i class="icon">⚙️</i>Keamanan & Status Akun</h4>
                    </div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="status_akun">Status Akun (*)</label>
                            <select id="status_akun" name="status_akun" required>
                                <?php foreach ($opsi_status_akun_dosen as $value => $text): ?>
                                    <option value="<?php echo htmlspecialchars($value); ?>" <?php echo ($dosen_data['status_akun'] == $value) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($text); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="password_baru">Reset Password (Opsional)</label>
                            <input type="password" id="password_baru" name="password_baru" minlength="6" placeholder="Kosongkan jika tidak diubah">
                        </div>
                    </div>
                </fieldset>

                <div class="form-actions">
                    <a href="pengguna_dosen_kelola.php" class="btn btn-secondary">Batal</a>
                    <button type="submit" name="submit_edit_dosen" class="btn btn-primary">Simpan Perubahan</button>
                </div>
            </form>
        <?php elseif(empty($error_message)): ?>
            <div class="message info"><p>Memuat data dosen...</p></div>
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
    padding: 3rem 2rem; background: linear-gradient(135deg, #4b6cb7 0%, #182848 100%);
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
.readonly-input { background-color: #e9ecef; cursor: not-allowed; color: var(--text-secondary); }

.form-actions { display: flex; justify-content: flex-end; gap: 1rem; padding-top: 1.5rem; border-top: 1px solid var(--border-color); }
.btn-secondary { background-color: var(--bg-light); color: var(--text-secondary); border: 1px solid var(--border-color); }
.btn-primary { background-color: var(--primary-color); color: white; }
</style>

<?php
require_once '../includes/footer.php';
if (isset($conn)) { $conn->close(); }
?>
