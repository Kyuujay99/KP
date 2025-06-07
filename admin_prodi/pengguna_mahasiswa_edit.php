<?php
// /KP/admin_prodi/pengguna_mahasiswa_edit.php (Versi Final dan Lengkap)

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once '../includes/auth_check.php';
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin_prodi') {
    header("Location: /KP/index.php?error=unauthorized_admin");
    exit();
}

require_once '../config/db_connect.php';

$nim_to_edit = null;
$mahasiswa_data = null;
$error_message = '';
$success_message = '';

if (isset($_GET['nim'])) {
    $nim_to_edit = $_GET['nim'];
} else {
    $error_message = "NIM mahasiswa tidak valid atau tidak ditemukan.";
}

function getMahasiswaData($conn_db, $nim, &$out_error_message) {
    $data = null;
    $sql = "SELECT nim, password, nama, email, no_hp, prodi, angkatan, status_akun FROM mahasiswa WHERE nim = ? LIMIT 1";
    $stmt = $conn_db->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("s", $nim);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows === 1) {
            $data = $result->fetch_assoc();
        } else {
            $out_error_message = "Data mahasiswa dengan NIM " . htmlspecialchars($nim) . " tidak ditemukan.";
        }
        $stmt->close();
    }
    return $data;
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_edit_mahasiswa']) && !empty($nim_to_edit)) {
    if ($conn) {
        $nim_form = $_POST['nim'];
        if ($nim_form !== $nim_to_edit) {
            $error_message = "Kesalahan: NIM tidak cocok.";
        } else {
            $nama_input = trim($_POST['nama']);
            $email_input = trim($_POST['email']);
            $no_hp_input = trim($_POST['no_hp']);
            $prodi_input = trim($_POST['prodi']);
            $angkatan_input = (int)$_POST['angkatan'];
            $status_akun_input = $_POST['status_akun'];
            $password_baru_input = $_POST['password_baru'];

            if (empty($nama_input) || empty($email_input) || empty($prodi_input) || empty($angkatan_input) || empty($status_akun_input)) {
                $error_message = "Field dengan tanda (*) wajib diisi.";
            } elseif (!filter_var($email_input, FILTER_VALIDATE_EMAIL)) {
                $error_message = "Format email tidak valid.";
            } else {
                $sql_check_email = $conn->prepare("SELECT nim FROM mahasiswa WHERE email = ? AND nim != ?");
                $sql_check_email->bind_param("ss", $email_input, $nim_to_edit);
                $sql_check_email->execute();
                $sql_check_email->store_result();
                if ($sql_check_email->num_rows > 0) {
                    $error_message = "Email '" . htmlspecialchars($email_input) . "' sudah digunakan oleh mahasiswa lain.";
                }
                $sql_check_email->close();

                if (empty($error_message)) {
                    $fields_to_update = "nama = ?, email = ?, no_hp = ?, prodi = ?, angkatan = ?, status_akun = ?";
                    $types = "ssssis";
                    $params = [$nama_input, $email_input, $no_hp_input, $prodi_input, $angkatan_input, $status_akun_input];

                    if (!empty($password_baru_input)) {
                        if (strlen($password_baru_input) < 6) {
                            $error_message = "Password baru minimal harus 6 karakter.";
                        } else {
                            $fields_to_update .= ", password = ?";
                            $types .= "s";
                            $params[] = $password_baru_input;
                        }
                    }

                    if (empty($error_message)) {
                        $sql_update = "UPDATE mahasiswa SET $fields_to_update WHERE nim = ?";
                        $types .= "s";
                        $params[] = $nim_to_edit;
                        $stmt_update = $conn->prepare($sql_update);
                        $stmt_update->bind_param($types, ...$params);
                        if ($stmt_update->execute()) {
                            if ($stmt_update->affected_rows > 0) {
                                $success_message = "Data mahasiswa berhasil diperbarui.";
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
    } else {
        $error_message = "Koneksi database gagal.";
    }
}

if (!empty($nim_to_edit) && $conn) {
    $mahasiswa_data = getMahasiswaData($conn, $nim_to_edit, $error_message);
}

$opsi_status_akun = [
    'pending_verification' => 'Pending Verification', 'active' => 'Active', 'suspended' => 'Suspended'
];
$opsi_prodi = [
    'Teknik Informatika' => 'Teknik Informatika', 'Sistem Informasi' => 'Sistem Informasi', 'Teknik Elektro' => 'Teknik Elektro', 'Teknik Mesin' => 'Teknik Mesin', 'Teknik Industri' => 'Teknik Industri',
];

$page_title = "Edit Akun Mahasiswa";
if ($mahasiswa_data && !empty($mahasiswa_data['nama'])) {
    $page_title = "Edit: " . htmlspecialchars($mahasiswa_data['nama']);
}
require_once '../includes/header.php';
?>

<div class="main-content-full">
    <div class="form-container-modern">
        <div class="form-header">
            <h1><?php echo htmlspecialchars($page_title); ?></h1>
            <p>Perbarui informasi detail, kontak, dan status akun untuk mahasiswa ini.</p>
        </div>

        <?php if (!empty($success_message)): ?>
            <div class="message success">
                <p><?php echo htmlspecialchars($success_message); ?></p>
                <a href="pengguna_mahasiswa_kelola.php" class="btn btn-secondary">Kembali ke Daftar</a>
            </div>
        <?php endif; ?>
        <?php if (!empty($error_message) && !$mahasiswa_data): ?>
            <div class="message error"><p><?php echo htmlspecialchars($error_message); ?></p></div>
        <?php endif; ?>

        <?php if ($mahasiswa_data): ?>
            <form action="pengguna_mahasiswa_edit.php?nim=<?php echo htmlspecialchars($nim_to_edit); ?>" method="POST" class="modern-form">
                <input type="hidden" name="nim" value="<?php echo htmlspecialchars($mahasiswa_data['nim']); ?>">

                <fieldset>
                    <div class="fieldset-header">
                        <span class="fieldset-number">👤</span>
                        <h4>Data Diri & Kontak</h4>
                    </div>
                    <div class="form-grid-2">
                        <div class="form-group">
                            <label for="view_nim">NIM</label>
                            <input type="text" id="view_nim" value="<?php echo htmlspecialchars($mahasiswa_data['nim']); ?>" readonly class="readonly-input">
                        </div>
                        <div class="form-group">
                            <label for="nama">Nama Lengkap (*)</label>
                            <input type="text" id="nama" name="nama" value="<?php echo htmlspecialchars($mahasiswa_data['nama']); ?>" required>
                        </div>
                    </div>
                    <div class="form-grid-2">
                        <div class="form-group">
                            <label for="email">Email (*)</label>
                            <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($mahasiswa_data['email']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="no_hp">Nomor HP</label>
                            <input type="text" id="no_hp" name="no_hp" value="<?php echo htmlspecialchars($mahasiswa_data['no_hp']); ?>">
                        </div>
                    </div>
                </fieldset>

                <fieldset>
                    <div class="fieldset-header">
                        <span class="fieldset-number">🎓</span>
                        <h4>Data Akademik</h4>
                    </div>
                    <div class="form-grid-2">
                        <div class="form-group">
                            <label for="prodi">Program Studi (*)</label>
                            <select id="prodi" name="prodi" required>
                                <?php foreach($opsi_prodi as $value => $text): ?>
                                    <option value="<?php echo htmlspecialchars($value); ?>" <?php echo ($mahasiswa_data['prodi'] == $value) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($text); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="angkatan">Angkatan (*)</label>
                            <input type="number" id="angkatan" name="angkatan" value="<?php echo htmlspecialchars($mahasiswa_data['angkatan']); ?>" min="2000" max="<?php echo date('Y')+1; ?>" required>
                        </div>
                    </div>
                </fieldset>

                <fieldset>
                    <div class="fieldset-header">
                        <span class="fieldset-number">⚙️</span>
                        <h4>Pengaturan Akun</h4>
                    </div>
                    <div class="form-grid-2">
                        <div class="form-group">
                            <label for="status_akun">Status Akun (*)</label>
                            <select id="status_akun" name="status_akun" required>
                                <?php foreach ($opsi_status_akun as $value => $text): ?>
                                    <option value="<?php echo $value; ?>" <?php echo ($mahasiswa_data['status_akun'] == $value) ? 'selected' : ''; ?>>
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
                    </div>
                </fieldset>

                <div class="form-actions">
                    <a href="pengguna_mahasiswa_kelola.php" class="btn btn-secondary">Batal</a>
                    <button type="submit" name="submit_edit_mahasiswa" class="btn btn-primary">Simpan Perubahan</button>
                </div>
            </form>
        <?php elseif(empty($error_message)): ?>
            <div class="message info"><p>Memuat data mahasiswa...</p></div>
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