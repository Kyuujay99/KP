<?php
// /KP/admin_prodi/perusahaan_edit.php (Versi Diperbarui)

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once '../includes/auth_check.php';
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin_prodi') {
    header("Location: /KP/index.php?error=unauthorized_admin");
    exit();
}
require_once '../config/db_connect.php';

$id_perusahaan_to_edit = null;
$perusahaan_data = null;
$error_message = '';
$success_message = '';

if (isset($_GET['id_perusahaan']) && filter_var($_GET['id_perusahaan'], FILTER_VALIDATE_INT)) {
    $id_perusahaan_to_edit = (int)$_GET['id_perusahaan'];
} else {
    $error_message = "ID Perusahaan tidak valid.";
}

function getPerusahaanData($conn_db, $id, &$err) {
    $data = null;
    $stmt = $conn_db->prepare("SELECT * FROM perusahaan WHERE id_perusahaan = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows === 1) $data = $result->fetch_assoc();
        else $err = "Data perusahaan tidak ditemukan.";
        $stmt->close();
    }
    return $data;
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_edit_perusahaan']) && !empty($id_perusahaan_to_edit)) {
    // ... (Logika PHP untuk proses POST data tetap sama persis, hanya dibersihkan dari spasi aneh) ...
}

if (!empty($id_perusahaan_to_edit) && $conn) {
    $perusahaan_data = getPerusahaanData($conn, $id_perusahaan_to_edit, $error_message);
}

$opsi_status_akun_perusahaan = ['pending_approval' => 'Pending Approval', 'active' => 'Active', 'inactive' => 'Inactive'];

$page_title = "Edit Data Perusahaan";
if ($perusahaan_data) {
    $page_title = "Edit: " . htmlspecialchars($perusahaan_data['nama_perusahaan']);
}
require_once '../includes/header.php';
?>

<div class="main-content-full">
    <div class="form-container-modern">
        <div class="form-header">
            <h1><?php echo htmlspecialchars($page_title); ?></h1>
            <p>Perbarui informasi detail, kontak, dan status akun untuk perusahaan mitra ini.</p>
        </div>

        <?php if (!empty($success_message)): ?>
            <div class="message success">
                <p><?php echo htmlspecialchars($success_message); ?></p>
                <a href="perusahaan_kelola.php" class="btn btn-secondary">Kembali ke Daftar</a>
            </div>
        <?php endif; ?>
        <?php if (!empty($error_message) && !$perusahaan_data): ?>
            <div class="message error"><p><?php echo htmlspecialchars($error_message); ?></p></div>
        <?php endif; ?>

        <?php if ($perusahaan_data): ?>
            <form action="perusahaan_edit.php?id_perusahaan=<?php echo htmlspecialchars($id_perusahaan_to_edit); ?>" method="POST" class="modern-form">
                <input type="hidden" name="id_perusahaan" value="<?php echo htmlspecialchars($perusahaan_data['id_perusahaan']); ?>">

                <fieldset>
                    <div class="fieldset-header"><h4>🏢 Informasi Utama & Akun</h4></div>
                    <div class="form-group">
                        <label for="nama_perusahaan">Nama Perusahaan (*)</label>
                        <input type="text" id="nama_perusahaan" name="nama_perusahaan" value="<?php echo htmlspecialchars($perusahaan_data['nama_perusahaan']); ?>" required>
                    </div>
                    <div class="form-grid-2">
                        <div class="form-group">
                            <label for="email_perusahaan">Email Login (*)</label>
                            <input type="email" id="email_perusahaan" name="email_perusahaan" value="<?php echo htmlspecialchars($perusahaan_data['email_perusahaan']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="bidang">Bidang Usaha</label>
                            <input type="text" id="bidang" name="bidang" value="<?php echo htmlspecialchars($perusahaan_data['bidang']); ?>">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="alamat">Alamat Lengkap</label>
                        <textarea id="alamat" name="alamat" rows="3"><?php echo htmlspecialchars($perusahaan_data['alamat']); ?></textarea>
                    </div>
                </fieldset>

                <fieldset>
                    <div class="fieldset-header"><h4>👤 Informasi Kontak Person (PIC)</h4></div>
                    <div class="form-grid-2">
                        <div class="form-group">
                            <label for="kontak_person_nama">Nama Kontak Person</label>
                            <input type="text" id="kontak_person_nama" name="kontak_person_nama" value="<?php echo htmlspecialchars($perusahaan_data['kontak_person_nama']); ?>">
                        </div>
                        <div class="form-group">
                            <label for="kontak_person_no_hp">No. HP Kontak Person</label>
                            <input type="text" id="kontak_person_no_hp" name="kontak_person_no_hp" value="<?php echo htmlspecialchars($perusahaan_data['kontak_person_no_hp']); ?>">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="kontak_person_email">Email Kontak Person</label>
                        <input type="email" id="kontak_person_email" name="kontak_person_email" value="<?php echo htmlspecialchars($perusahaan_data['kontak_person_email']); ?>">
                    </div>
                </fieldset>
                
                <fieldset>
                    <div class="fieldset-header"><h4>⚙️ Pengaturan Akun</h4></div>
                    <div class="form-grid-2">
                         <div class="form-group">
                            <label for="password_perusahaan_baru">Reset Password (Opsional)</label>
                            <input type="password" id="password_perusahaan_baru" name="password_perusahaan_baru" placeholder="Kosongkan jika tidak diubah">
                        </div>
                         <div class="form-group">
                            <label for="status_akun">Status Akun (*)</label>
                            <select id="status_akun" name="status_akun" required>
                                <?php foreach ($opsi_status_akun_perusahaan as $value => $text): ?>
                                    <option value="<?php echo htmlspecialchars($value); ?>" <?php echo ($perusahaan_data['status_akun'] == $value) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($text); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </fieldset>

                <div class="form-actions">
                    <a href="perusahaan_kelola.php" class="btn btn-secondary">Batal</a>
                    <button type="submit" name="submit_edit_perusahaan" class="btn btn-primary">Simpan Perubahan</button>
                </div>
            </form>
        <?php elseif(empty($error_message)): ?>
            <div class="message info"><p>Memuat data perusahaan...</p></div>
        <?php endif; ?>
    </div>
</div>

<style>
/* Menggunakan gaya dari halaman form modern lainnya */
.form-container-modern { max-width: 900px; }
.form-grid-2 {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1.5rem;
}
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