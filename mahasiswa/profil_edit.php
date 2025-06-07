<?php
// /KP/mahasiswa/profil_edit.php (Versi Diperbarui)

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// 1. OTENTIKASI DAN OTORISASI
require_once '../includes/auth_check.php';
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'mahasiswa') {
    session_unset();
    session_destroy();
    header("Location: /KP/index.php?error=unauthorized");
    exit();
}

$nim_mahasiswa = $_SESSION['user_id'];
require_once '../config/db_connect.php';

$mahasiswa_data = null;
$error_message = '';
$success_message = '';

// 3. PROSES UPDATE DATA JIKA FORM DISUBMIT
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $new_email = trim($_POST['email']);
    $new_no_hp = trim($_POST['no_hp']);

    if (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Format email tidak valid.";
    } else {
        if ($conn) {
            $sql_check_email = "SELECT nim FROM mahasiswa WHERE email = ? AND nim != ?";
            $stmt_check_email = $conn->prepare($sql_check_email);
            $stmt_check_email->bind_param("ss", $new_email, $nim_mahasiswa);
            $stmt_check_email->execute();
            $stmt_check_email->store_result();

            if ($stmt_check_email->num_rows > 0) {
                $error_message = "Email tersebut sudah digunakan oleh mahasiswa lain.";
            } else {
                $sql_update = "UPDATE mahasiswa SET email = ?, no_hp = ? WHERE nim = ?";
                $stmt_update = $conn->prepare($sql_update);
                $stmt_update->bind_param("sss", $new_email, $new_no_hp, $nim_mahasiswa);
                if ($stmt_update->execute()) {
                    $success_message = "Profil berhasil diperbarui!";
                } else {
                    $error_message = "Gagal memperbarui profil: " . $stmt_update->error;
                }
                $stmt_update->close();
            }
            $stmt_check_email->close();
        }
    }
}

// 2. AMBIL DATA PROFIL TERBARU UNTUK DITAMPILKAN DI FORM
if ($conn) {
    $sql_select = "SELECT nama, email, no_hp, prodi, angkatan FROM mahasiswa WHERE nim = ? LIMIT 1";
    $stmt_select = $conn->prepare($sql_select);
    $stmt_select->bind_param("s", $nim_mahasiswa);
    $stmt_select->execute();
    $result = $stmt_select->get_result();
    if ($result->num_rows === 1) {
        $mahasiswa_data = $result->fetch_assoc();
    } else {
        if(empty($error_message)) $error_message = "Data profil tidak ditemukan.";
    }
    $stmt_select->close();
}

$page_title = "Edit Profil Mahasiswa";
require_once '../includes/header.php';
?>

<div class="main-content-full">
    <div class="form-container-modern">
        <div class="form-header">
            <h1><?php echo htmlspecialchars($page_title); ?></h1>
            <p>Anda hanya dapat mengubah informasi kontak. Untuk data akademik, silakan hubungi admin.</p>
        </div>

        <?php if (!empty($success_message)): ?>
            <div class="message success"><p><?php echo htmlspecialchars($success_message); ?></p></div>
        <?php endif; ?>
        <?php if (!empty($error_message)): ?>
            <div class="message error"><p><?php echo htmlspecialchars($error_message); ?></p></div>
        <?php endif; ?>

        <?php if (is_array($mahasiswa_data)): ?>
            <form action="profil_edit.php" method="POST" class="modern-form">
                <fieldset>
                    <div class="fieldset-header">
                        <h4>Kontak yang Dapat Dihubungi</h4>
                    </div>
                    <div class="form-grid">
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
                
                <fieldset disabled>
                    <div class="fieldset-header">
                        <h4>Data Akademik (Tidak Dapat Diubah)</h4>
                    </div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label>NIM</label>
                            <input type="text" value="<?php echo htmlspecialchars($nim_mahasiswa); ?>" class="readonly-input">
                        </div>
                        <div class="form-group">
                            <label>Nama Lengkap</label>
                            <input type="text" value="<?php echo htmlspecialchars($mahasiswa_data['nama']); ?>" class="readonly-input">
                        </div>
                        <div class="form-group">
                            <label>Program Studi</label>
                            <input type="text" value="<?php echo htmlspecialchars($mahasiswa_data['prodi']); ?>" class="readonly-input">
                        </div>
                        <div class="form-group">
                            <label>Angkatan</label>
                            <input type="text" value="<?php echo htmlspecialchars($mahasiswa_data['angkatan']); ?>" class="readonly-input">
                        </div>
                    </div>
                </fieldset>

                <div class="form-actions">
                    <a href="profil.php" class="btn btn-secondary">Batal</a>
                    <button type="submit" class="btn btn-primary btn-submit">Simpan Perubahan</button>
                </div>
            </form>
        <?php elseif(empty($error_message)): ?>
            <div class="message info"><p>Memuat data...</p></div>
        <?php endif; ?>
    </div>
</div>

<style>
    .form-container-modern { max-width: 900px; margin: 20px auto; background: #fff; padding: 2.5rem; border-radius: var(--border-radius); box-shadow: var(--card-shadow); }
    .form-header { text-align: center; margin-bottom: 2rem; }
    .form-header h1 { color: var(--primary-color); }
    .modern-form fieldset { border: none; padding: 0; margin-bottom: 1rem; }
    fieldset[disabled] { opacity: 0.6; }
    .fieldset-header { margin-bottom: 1.5rem; padding-bottom: 10px; border-bottom: 1px solid var(--border-color); }
    .fieldset-header h4 { margin: 0; font-size: 1.3em; color: var(--dark-color); }
    .form-group { margin-bottom: 1.5rem; }
    .form-group label { display: block; font-weight: 500; margin-bottom: 0.5rem; }
    .form-group input { width: 100%; padding: 12px; border: 1px solid var(--border-color); border-radius: 8px; transition: all 0.3s ease; }
    .form-group input:focus { border-color: var(--primary-color); box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.2); outline: none; }
    .readonly-input { background-color: #e9ecef; cursor: not-allowed; }
    .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem; }
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