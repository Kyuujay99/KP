<?php
// /KP/admin_prodi/perusahaan_edit.php

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// 1. OTENTIKASI DAN OTORISASI
require_once '../includes/auth_check.php';
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin_prodi') {
    session_unset();
    session_destroy();
    header("Location: /KP/index.php?error=unauthorized_admin");
    exit();
}

$admin_identifier = $_SESSION['user_id'];
$id_perusahaan_to_edit = null;
$perusahaan_data = null;
$error_message = '';
$success_message = '';

// 2. VALIDASI DAN AMBIL ID PERUSAHAAN DARI URL
if (isset($_GET['id_perusahaan']) && filter_var($_GET['id_perusahaan'], FILTER_VALIDATE_INT)) {
    $id_perusahaan_to_edit = (int)$_GET['id_perusahaan'];
} else {
    $error_message = "ID Perusahaan tidak valid atau tidak ditemukan untuk diedit.";
}

// Sertakan file koneksi database
require_once '../config/db_connect.php';

// 3. FUNGSI UNTUK MENGAMBIL DATA PERUSAHAAN
function getPerusahaanData($conn_db, $id_perusahaan, &$out_error_message) {
    $data = null;
    $sql = "SELECT id_perusahaan, email_perusahaan, password_perusahaan, nama_perusahaan, alamat, 
                   bidang, kontak_person_nama, kontak_person_email, kontak_person_no_hp, status_akun 
            FROM perusahaan WHERE id_perusahaan = ? LIMIT 1";
    $stmt = $conn_db->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("i", $id_perusahaan);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows === 1) {
            $data = $result->fetch_assoc();
        } else {
            $out_error_message = "Data perusahaan dengan ID " . htmlspecialchars($id_perusahaan) . " tidak ditemukan.";
        }
        $stmt->close();
    } else {
        $out_error_message = "Gagal menyiapkan query untuk mengambil data perusahaan: " . (($conn_db->error) ? htmlspecialchars($conn_db->error) : "Kesalahan DB tidak diketahui.");
    }
    return $data;
}

// 4. PROSES UPDATE DATA JIKA FORM DISUBMIT (METHOD POST)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_edit_perusahaan']) && !empty($id_perusahaan_to_edit)) {
    $id_perusahaan_form = (int)$_POST['id_perusahaan'];

    if ($id_perusahaan_form !== $id_perusahaan_to_edit) {
        $error_message = "Kesalahan: ID Perusahaan tidak cocok dengan data yang akan diedit.";
    } else {
        $nama_perusahaan_input = trim($_POST['nama_perusahaan']);
        $email_perusahaan_input = trim($_POST['email_perusahaan']);
        $alamat_input = trim($_POST['alamat']);
        $bidang_input = trim($_POST['bidang']);
        $kontak_nama_input = trim($_POST['kontak_person_nama']);
        $kontak_email_input = trim($_POST['kontak_person_email']);
        $kontak_no_hp_input = trim($_POST['kontak_person_no_hp']);
        $status_akun_input = $_POST['status_akun'];
        $password_baru_input = $_POST['password_perusahaan_baru']; // Bisa kosong

        // Validasi dasar
        if (empty($nama_perusahaan_input) || empty($email_perusahaan_input) || empty($status_akun_input)) {
            $error_message = "Nama Perusahaan, Email Perusahaan, dan Status Akun wajib diisi.";
        } elseif (!filter_var($email_perusahaan_input, FILTER_VALIDATE_EMAIL)) {
            $error_message = "Format Email Perusahaan tidak valid.";
        } elseif (!empty($kontak_email_input) && !filter_var($kontak_email_input, FILTER_VALIDATE_EMAIL)) {
            $error_message = "Format Email Kontak Person tidak valid.";
        }
        
        // Validasi status akun (sesuai ENUM di tabel perusahaan)
        $allowed_statuses_perusahaan = ['pending_approval', 'active', 'inactive'];
        if (!in_array($status_akun_input, $allowed_statuses_perusahaan)) {
            $error_message = "Status akun yang dipilih tidak valid.";
        }

        if (empty($error_message)) {
            if ($conn && ($conn instanceof mysqli)) {
                // Cek apakah email perusahaan baru sudah digunakan oleh perusahaan lain (kecuali dirinya sendiri)
                $sql_check_email = "SELECT id_perusahaan FROM perusahaan WHERE email_perusahaan = ? AND id_perusahaan != ?";
                $stmt_check_email = $conn->prepare($sql_check_email);
                if($stmt_check_email){
                    $stmt_check_email->bind_param("si", $email_perusahaan_input, $id_perusahaan_to_edit);
                    $stmt_check_email->execute();
                    $stmt_check_email->store_result();
                    if ($stmt_check_email->num_rows > 0) {
                        $error_message = "Email Perusahaan '" . htmlspecialchars($email_perusahaan_input) . "' sudah digunakan oleh perusahaan lain.";
                    }
                    $stmt_check_email->close();
                } else {
                    $error_message = "Gagal memeriksa duplikasi email perusahaan: ". htmlspecialchars($conn->error);
                }
                

                if (empty($error_message)) { // Lanjutkan jika tidak ada error duplikasi email
                    $fields_to_update = "nama_perusahaan = ?, email_perusahaan = ?, alamat = ?, bidang = ?, kontak_person_nama = ?, kontak_person_email = ?, kontak_person_no_hp = ?, status_akun = ?";
                    $types = "ssssssss";
                    $params = [
                        $nama_perusahaan_input, $email_perusahaan_input, $alamat_input, $bidang_input,
                        $kontak_nama_input, $kontak_email_input, $kontak_no_hp_input, $status_akun_input
                    ];

                    if (!empty($password_baru_input)) {
                        if (strlen($password_baru_input) < 6) { // Contoh validasi panjang password
                             $error_message = "Password baru minimal harus 6 karakter.";
                        } else {
                            $fields_to_update .= ", password_perusahaan = ?";
                            $types .= "s";
                            $params[] = $password_baru_input; // Password plain text
                        }
                    }

                    if (empty($error_message)) { // Lanjutkan jika tidak ada error password
                        $sql_update = "UPDATE perusahaan SET $fields_to_update WHERE id_perusahaan = ?";
                        $types .= "i";
                        $params[] = $id_perusahaan_to_edit;

                        $stmt_update = $conn->prepare($sql_update);
                        if ($stmt_update) {
                            $ref_params = [];
                            foreach ($params as $key => $value) {
                                $ref_params[$key] = &$params[$key];
                            }
                            array_unshift($ref_params, $types);
                            call_user_func_array([$stmt_update, 'bind_param'], $ref_params);

                            if ($stmt_update->execute()) {
                                if ($stmt_update->affected_rows > 0) {
                                    $success_message = "Data perusahaan ID " . htmlspecialchars($id_perusahaan_to_edit) . " berhasil diperbarui.";
                                } else {
                                    $success_message = "Tidak ada perubahan data yang dilakukan (mungkin data masih sama).";
                                }
                            } else {
                                $error_message = "Gagal memperbarui data perusahaan: " . htmlspecialchars($stmt_update->error);
                            }
                            $stmt_update->close();
                        } else {
                            $error_message = "Gagal menyiapkan statement update perusahaan: " . htmlspecialchars($conn->error);
                        }
                    }
                }
            } else {
                $error_message = "Koneksi database gagal atau tidak valid saat update.";
            }
        }
    }
}

// Selalu ambil data terbaru untuk ditampilkan di form
if (!empty($id_perusahaan_to_edit) && $conn && ($conn instanceof mysqli) && empty($error_message_initial_load)) {
    $perusahaan_data = getPerusahaanData($conn, $id_perusahaan_to_edit, $error_message);
    if (!$perusahaan_data && empty($error_message)) {
        $error_message = "Gagal memuat data perusahaan untuk ID " . htmlspecialchars($id_perusahaan_to_edit) . ".";
    }
}

// Daftar status akun (sesuai ENUM di tabel perusahaan)
$opsi_status_akun_perusahaan = [
    'pending_approval' => 'Pending Approval',
    'active' => 'Active',
    'inactive' => 'Inactive'
];

// Set judul halaman
$page_title = "Edit Data Perusahaan";
if ($perusahaan_data && !empty($perusahaan_data['nama_perusahaan'])) {
    $page_title = "Edit: " . htmlspecialchars($perusahaan_data['nama_perusahaan']);
} elseif (!empty($id_perusahaan_to_edit) && empty($perusahaan_data) && empty($error_message)) {
    $page_title = "Edit Perusahaan ID: " . htmlspecialchars($id_perusahaan_to_edit);
}
require_once '../includes/header.php';
?>

<div class="page-layout-wrapper">

    <?php require_once '../includes/sidebar_admin_prodi.php'; ?>

    <main class="main-content-area">
        <div class="form-container edit-perusahaan-form">
            <h1><?php echo htmlspecialchars($page_title); ?></h1>
            <a href="/KP/admin_prodi/perusahaan_kelola.php" class="btn btn-light btn-sm mb-3">&laquo; Kembali ke Daftar Perusahaan</a>
            <hr>

            <?php if (!empty($success_message)): ?>
                <div class="message success"><p><?php echo htmlspecialchars($success_message); ?></p></div>
            <?php endif; ?>
            <?php if (!empty($error_message)): ?>
                <div class="message error"><p><?php echo htmlspecialchars($error_message); ?></p></div>
            <?php endif; ?>

            <?php if ($perusahaan_data): // Hanya tampilkan form jika data perusahaan berhasil diambil ?>
            <form action="/KP/admin_prodi/perusahaan_edit.php?id_perusahaan=<?php echo htmlspecialchars($id_perusahaan_to_edit); ?>" method="POST">
                <input type="hidden" name="id_perusahaan" value="<?php echo htmlspecialchars($perusahaan_data['id_perusahaan']); ?>">

                <fieldset>
                    <legend>Informasi Utama Perusahaan</legend>
                    <div class="form-group">
                        <label for="view_id_perusahaan">ID Perusahaan:</label>
                        <input type="text" id="view_id_perusahaan" value="<?php echo htmlspecialchars($perusahaan_data['id_perusahaan']); ?>" readonly class="readonly-input">
                    </div>
                    <div class="form-group">
                        <label for="nama_perusahaan">Nama Perusahaan (*):</label>
                        <input type="text" id="nama_perusahaan" name="nama_perusahaan" value="<?php echo htmlspecialchars($perusahaan_data['nama_perusahaan']); ?>" required maxlength="100">
                    </div>
                    <div class="form-group">
                        <label for="email_perusahaan">Email Perusahaan (untuk Login) (*):</label>
                        <input type="email" id="email_perusahaan" name="email_perusahaan" value="<?php echo htmlspecialchars($perusahaan_data['email_perusahaan']); ?>" required maxlength="100">
                    </div>
                    <div class="form-group">
                        <label for="alamat">Alamat Lengkap:</label>
                        <textarea id="alamat" name="alamat" rows="3"><?php echo htmlspecialchars($perusahaan_data['alamat']); ?></textarea>
                    </div>
                    <div class="form-group">
                        <label for="bidang">Bidang Usaha:</label>
                        <input type="text" id="bidang" name="bidang" value="<?php echo htmlspecialchars($perusahaan_data['bidang']); ?>" maxlength="100">
                    </div>
                </fieldset>

                <fieldset>
                    <legend>Informasi Kontak Person</legend>
                    <div class="form-group">
                        <label for="kontak_person_nama">Nama Kontak Person:</label>
                        <input type="text" id="kontak_person_nama" name="kontak_person_nama" value="<?php echo htmlspecialchars($perusahaan_data['kontak_person_nama']); ?>" maxlength="100">
                    </div>
                    <div class="form-group">
                        <label for="kontak_person_email">Email Kontak Person:</label>
                        <input type="email" id="kontak_person_email" name="kontak_person_email" value="<?php echo htmlspecialchars($perusahaan_data['kontak_person_email']); ?>" maxlength="100">
                    </div>
                     <div class="form-group">
                        <label for="kontak_person_no_hp">No. HP Kontak Person:</label>
                        <input type="text" id="kontak_person_no_hp" name="kontak_person_no_hp" value="<?php echo htmlspecialchars($perusahaan_data['kontak_person_no_hp']); ?>" maxlength="20">
                    </div>
                </fieldset>
                
                <fieldset>
                    <legend>Pengaturan Akun Perusahaan</legend>
                    <div class="form-group">
                        <label for="password_sekarang">Password Saat Ini:</label>
                        <input type="text" id="password_sekarang" value="<?php echo htmlspecialchars($perusahaan_data['password_perusahaan']); ?>" readonly class="readonly-input" title="Password perusahaan saat ini (plain text).">
                        <small>Password ditampilkan apa adanya (plain text).</small>
                    </div>
                    <div class="form-group">
                        <label for="password_perusahaan_baru">Password Baru Akun Perusahaan:</label>
                        <input type="password" id="password_perusahaan_baru" name="password_perusahaan_baru">
                        <small>Kosongkan jika tidak ingin mengubah password. Minimal 6 karakter jika diisi.</small>
                    </div>
                    <div class="form-group">
                        <label for="status_akun">Status Akun (*):</label>
                        <select id="status_akun" name="status_akun" required>
                            <?php foreach ($opsi_status_akun_perusahaan as $value => $text): ?>
                                <option value="<?php echo $value; ?>" <?php echo ($perusahaan_data['status_akun'] == $value) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($text); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </fieldset>

                <div class="form-actions">
                    <button type="submit" name="submit_edit_perusahaan" class="btn btn-primary">Simpan Perubahan</button>
                    <a href="/KP/admin_prodi/perusahaan_kelola.php" class="btn btn-secondary">Batal</a>
                </div>
            </form>
            <?php elseif(empty($error_message) && !empty($id_perusahaan_to_edit)): ?>
                <div class="message info"><p>Memuat data perusahaan...</p></div>
            <?php endif; ?>

        </div>
    </main>
</div>

<style>
    /* Asumsikan CSS umum dari header, sidebar, form-container, fieldset, legend, form-group, message, btn sudah ada */
    .edit-perusahaan-form h1 { margin-top: 0; margin-bottom: 5px; }
    .edit-perusahaan-form hr { margin-top:15px; margin-bottom: 20px; }
    .btn.mb-3 { margin-bottom: 1rem !important; }

    .readonly-input { background-color: #e9ecef; cursor: not-allowed; }
    .form-group small { display: block; font-size: 0.85em; color: #6c757d; margin-top: 4px; }
    .form-group textarea { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; font-size: 1em; }
</style>

<?php
require_once '../includes/footer.php';

if (isset($conn) && ($conn instanceof mysqli)) {
    $conn->close();
}
?>