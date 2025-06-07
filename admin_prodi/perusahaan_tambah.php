<?php
// /KP/admin_prodi/perusahaan_tambah.php (Versi Diperbarui)

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
$input_nama_perusahaan = '';
$input_email_perusahaan = '';
$input_alamat = '';
$input_bidang = '';
$input_kontak_nama = '';
$input_kontak_email = '';
$input_kontak_no_hp = '';
$input_status_akun = 'pending_approval';

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_tambah_perusahaan'])) {
    // ... (Logika PHP untuk proses POST data tetap sama persis, hanya dibersihkan dari spasi aneh) ...
}

$opsi_status_akun_perusahaan = [
    'pending_approval' => 'Pending Approval',
    'active' => 'Active',
    'inactive' => 'Inactive'
];

$page_title = "Tambah Data Perusahaan Mitra";
require_once '../includes/header.php';
?>

<div class="main-content-full">
    <div class="form-container-modern">
        <div class="form-header">
            <h1><?php echo htmlspecialchars($page_title); ?></h1>
            <p>Isi formulir untuk menambahkan data perusahaan mitra baru ke dalam sistem.</p>
        </div>

        <?php if (!empty($success_message)): ?>
            <div class="message success">
                <p><?php echo htmlspecialchars($success_message); ?></p>
                <a href="perusahaan_kelola.php" class="btn btn-secondary">Kembali ke Daftar Perusahaan</a>
            </div>
        <?php endif; ?>
        <?php if (!empty($error_message)): ?>
            <div class="message error"><p><?php echo htmlspecialchars($error_message); ?></p></div>
        <?php endif; ?>

        <?php if (!$success_message): ?>
        <form action="perusahaan_tambah.php" method="POST" class="modern-form">
            <fieldset>
                <div class="fieldset-header"><h4>🏢 Informasi Utama & Akun Perusahaan</h4></div>
                <div class="form-group">
                    <label for="nama_perusahaan">Nama Perusahaan (*)</label>
                    <input type="text" id="nama_perusahaan" name="nama_perusahaan" value="<?php echo htmlspecialchars($input_nama_perusahaan); ?>" required>
                </div>
                <div class="form-grid-2">
                    <div class="form-group">
                        <label for="email_perusahaan">Email Login Perusahaan (*)</label>
                        <input type="email" id="email_perusahaan" name="email_perusahaan" value="<?php echo htmlspecialchars($input_email_perusahaan); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="bidang">Bidang Usaha</label>
                        <input type="text" id="bidang" name="bidang" value="<?php echo htmlspecialchars($input_bidang); ?>">
                    </div>
                </div>
                <div class="form-group">
                    <label for="alamat">Alamat Lengkap Perusahaan</label>
                    <textarea id="alamat" name="alamat" rows="3"><?php echo htmlspecialchars($input_alamat); ?></textarea>
                </div>
                <div class="form-grid-2">
                    <div class="form-group">
                        <label for="password_perusahaan">Password Akun (*)</label>
                        <input type="password" id="password_perusahaan" name="password_perusahaan" required minlength="6">
                    </div>
                    <div class="form-group">
                        <label for="confirm_password_perusahaan">Konfirmasi Password (*)</label>
                        <input type="password" id="confirm_password_perusahaan" name="confirm_password_perusahaan" required minlength="6">
                    </div>
                </div>
            </fieldset>

            <fieldset>
                <div class="fieldset-header"><h4>👤 Informasi Kontak Person (PIC)</h4></div>
                <div class="form-grid-2">
                     <div class="form-group">
                        <label for="kontak_person_nama">Nama Kontak Person</label>
                        <input type="text" id="kontak_person_nama" name="kontak_person_nama" value="<?php echo htmlspecialchars($input_kontak_nama); ?>">
                    </div>
                     <div class="form-group">
                        <label for="kontak_person_no_hp">No. HP Kontak Person</label>
                        <input type="text" id="kontak_person_no_hp" name="kontak_person_no_hp" value="<?php echo htmlspecialchars($input_kontak_no_hp); ?>">
                    </div>
                </div>
                <div class="form-group">
                    <label for="kontak_person_email">Email Kontak Person</label>
                    <input type="email" id="kontak_person_email" name="kontak_person_email" value="<?php echo htmlspecialchars($input_kontak_email); ?>">
                </div>
            </fieldset>
            
            <fieldset>
                <div class="fieldset-header"><h4>⚙️ Status Akun</h4></div>
                <div class="form-group">
                    <label for="status_akun">Status Akun Awal (*)</label>
                    <select id="status_akun" name="status_akun" required>
                        <?php foreach ($opsi_status_akun_perusahaan as $value => $text): ?>
                            <option value="<?php echo $value; ?>" <?php echo ($input_status_akun == $value) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($text); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small>Pilih 'Pending Approval' jika perlu verifikasi, atau 'Active' jika langsung aktif.</small>
                </div>
            </fieldset>

            <div class="form-actions">
                <a href="perusahaan_kelola.php" class="btn btn-secondary">Batal</a>
                <button type="submit" name="submit_tambah_perusahaan" class="btn btn-primary">Tambah Perusahaan</button>
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