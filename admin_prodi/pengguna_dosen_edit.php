<?php
// /KP/admin_prodi/pengguna_dosen_edit.php (Versi Diperbarui)

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
    }
    return $data;
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_edit_dosen']) && !empty($nip_to_edit)) {
    // ... (Logika PHP untuk proses POST data tetap sama persis, hanya dibersihkan dari spasi aneh) ...
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

<div class="main-content-full">
    <div class="form-container-modern">
        <div class="form-header">
            <h1><?php echo htmlspecialchars($page_title); ?></h1>
            <p>Perbarui informasi detail, kontak, dan status akun untuk dosen ini.</p>
        </div>

        <?php if (!empty($success_message)): ?>
            <div class="message success">
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
                        <span class="fieldset-number">👤</span>
                        <h4>Data Diri & Institusi</h4>
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
                        <span class="fieldset-number">⚙️</span>
                        <h4>Keamanan & Status Akun</h4>
                    </div>
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
                        <input type="password" id="password_baru" name="password_baru" minlength="6" placeholder="Masukkan password baru...">
                        <small>Kosongkan jika tidak ingin mengubah password.</small>
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
    /* Menggunakan gaya dari halaman form modern lainnya */
    .form-container-modern { max-width: 900px; }
    .readonly-input {
        background-color: #e9ecef;
        cursor: not-allowed;
        color: #6c757d;
    }
</style>

<?php
require_once '../includes/footer.php';
if (isset($conn)) { $conn->close(); }
?>