<?php
// /KP/admin_prodi/pengguna_mahasiswa_edit.php

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
$nim_to_edit = null;
$mahasiswa_data = null;
$error_message = '';
$success_message = '';

// 2. VALIDASI DAN AMBIL NIM DARI URL
if (isset($_GET['nim'])) {
    $nim_to_edit = $_GET['nim'];
} else {
    $error_message = "NIM mahasiswa tidak valid atau tidak ditemukan untuk diedit.";
    // Jika NIM tidak ada, tidak perlu lanjut
}

// Sertakan file koneksi database
require_once '../config/db_connect.php';

// 3. FUNGSI UNTUK MENGAMBIL DATA MAHASISWA
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
    } else {
        $out_error_message = "Gagal menyiapkan query untuk mengambil data mahasiswa: " . (($conn_db->error) ? htmlspecialchars($conn_db->error) : "Kesalahan DB tidak diketahui.");
    }
    return $data;
}

// 4. PROSES UPDATE DATA JIKA FORM DISUBMIT (METHOD POST)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_edit_mahasiswa']) && !empty($nim_to_edit)) {
    $nim_form = $_POST['nim']; // NIM dari hidden field, harus sama dengan $nim_to_edit

    if ($nim_form !== $nim_to_edit) {
        $error_message = "Kesalahan: NIM tidak cocok dengan data yang akan diedit.";
    } else {
        $nama_input = trim($_POST['nama']);
        $email_input = trim($_POST['email']);
        $no_hp_input = trim($_POST['no_hp']);
        $prodi_input = trim($_POST['prodi']);
        $angkatan_input = (int)$_POST['angkatan'];
        $status_akun_input = $_POST['status_akun'];
        $password_baru_input = $_POST['password_baru']; // Bisa kosong

        // Validasi dasar
        if (empty($nama_input) || empty($email_input) || empty($prodi_input) || empty($angkatan_input) || empty($status_akun_input)) {
            $error_message = "Nama, Email, Prodi, Angkatan, dan Status Akun wajib diisi.";
        } elseif (!filter_var($email_input, FILTER_VALIDATE_EMAIL)) {
            $error_message = "Format email tidak valid.";
        } elseif ($angkatan_input < 1990 || $angkatan_input > (int)date("Y") + 1) {
            $error_message = "Tahun angkatan tidak valid.";
        }
        // Validasi status akun (sesuai ENUM)
        $allowed_statuses_akun = ['pending_verification', 'active', 'suspended'];
        if (!in_array($status_akun_input, $allowed_statuses_akun)) {
            $error_message = "Status akun yang dipilih tidak valid.";
        }


        if (empty($error_message)) {
            if ($conn && ($conn instanceof mysqli)) {
                // Cek apakah email baru sudah digunakan oleh mahasiswa lain (kecuali dirinya sendiri)
                $sql_check_email = "SELECT nim FROM mahasiswa WHERE email = ? AND nim != ?";
                $stmt_check_email = $conn->prepare($sql_check_email);
                if ($stmt_check_email) {
                    $stmt_check_email->bind_param("ss", $email_input, $nim_to_edit);
                    $stmt_check_email->execute();
                    $stmt_check_email->store_result();

                    if ($stmt_check_email->num_rows > 0) {
                        $error_message = "Email '" . htmlspecialchars($email_input) . "' sudah digunakan oleh mahasiswa lain.";
                    }
                    $stmt_check_email->close();
                } else {
                    $error_message = "Gagal memeriksa duplikasi email: " . htmlspecialchars($conn->error);
                }


                if (empty($error_message)) { // Lanjutkan jika tidak ada error duplikasi email
                    $fields_to_update = "nama = ?, email = ?, no_hp = ?, prodi = ?, angkatan = ?, status_akun = ?";
                    $types = "ssssis";
                    $params = [$nama_input, $email_input, $no_hp_input, $prodi_input, $angkatan_input, $status_akun_input];

                    // Jika password baru diisi, tambahkan ke query update
                    if (!empty($password_baru_input)) {
                        // Validasi panjang password baru jika perlu (misal minimal 6 karakter)
                        if (strlen($password_baru_input) < 6) {
                             $error_message = "Password baru minimal harus 6 karakter.";
                        } else {
                            $fields_to_update .= ", password = ?";
                            $types .= "s";
                            $params[] = $password_baru_input; // Password plain text
                        }
                    }

                    if (empty($error_message)) { // Lanjutkan jika tidak ada error password
                        $sql_update = "UPDATE mahasiswa SET $fields_to_update WHERE nim = ?";
                        $types .= "s";
                        $params[] = $nim_to_edit;

                        $stmt_update = $conn->prepare($sql_update);
                        if ($stmt_update) {
                            // Perlu memanggil bind_param dengan referensi
                            $ref_params = [];
                            foreach ($params as $key => $value) {
                                $ref_params[$key] = &$params[$key];
                            }
                            array_unshift($ref_params, $types);

                            call_user_func_array([$stmt_update, 'bind_param'], $ref_params);

                            if ($stmt_update->execute()) {
                                if ($stmt_update->affected_rows > 0) {
                                    $success_message = "Data mahasiswa NIM " . htmlspecialchars($nim_to_edit) . " berhasil diperbarui.";
                                    // Pertimbangkan redirect ke halaman list atau refresh data di halaman ini
                                    // header("Location: /KP/admin_prodi/pengguna_mahasiswa_kelola.php?update_success=1");
                                    // exit();
                                } else {
                                    $success_message = "Tidak ada perubahan data yang dilakukan (mungkin data masih sama).";
                                }
                            } else {
                                $error_message = "Gagal memperbarui data mahasiswa: " . htmlspecialchars($stmt_update->error);
                            }
                            $stmt_update->close();
                        } else {
                            $error_message = "Gagal menyiapkan statement update: " . htmlspecialchars($conn->error);
                        }
                    }
                }
            } else {
                $error_message = "Koneksi database gagal atau tidak valid saat update.";
            }
        }
    }
}

// Selalu ambil data terbaru untuk ditampilkan di form (atau jika ada error sebelumnya, $mahasiswa_data akan null)
if (!empty($nim_to_edit) && $conn && ($conn instanceof mysqli)) {
    // Jika ada pesan sukses, berarti data baru saja diupdate, jadi kita ambil data terbaru
    // Jika ada pesan error dari POST, data lama ($mahasiswa_data yang sudah ada) mungkin masih relevan untuk ditampilkan di form
    // agar pengguna tidak kehilangan input mereka. Namun, untuk menjaga konsistensi, kita selalu fetch ulang.
    $mahasiswa_data = getMahasiswaData($conn, $nim_to_edit, $error_message);
    if (!$mahasiswa_data && empty($error_message)) { // Jika fungsi return null tapi tidak set error eksplisit
        $error_message = "Gagal memuat data mahasiswa untuk NIM " . htmlspecialchars($nim_to_edit) . ".";
    }
}


// Daftar status akun (sesuai ENUM di tabel mahasiswa)
$opsi_status_akun = [
    'pending_verification' => 'Pending Verification',
    'active' => 'Active',
    'suspended' => 'Suspended'
];

// Set judul halaman
$page_title = "Edit Akun Mahasiswa";
if ($mahasiswa_data && !empty($mahasiswa_data['nama'])) {
    $page_title = "Edit: " . htmlspecialchars($mahasiswa_data['nama']) . " (" . htmlspecialchars($mahasiswa_data['nim']) . ")";
} elseif (!empty($nim_to_edit) && empty($mahasiswa_data) && empty($error_message)) {
    // Kasus jika NIM ada di URL tapi data belum ter-load (misal, error DB sebelum fetch)
    $page_title = "Edit Mahasiswa NIM: " . htmlspecialchars($nim_to_edit);
}
require_once '../includes/header.php';
?>

<div class="page-layout-wrapper">

    <?php require_once '../includes/sidebar_admin_prodi.php'; ?>

    <main class="main-content-area">
        <div class="form-container edit-mahasiswa-form">
            <h1><?php echo htmlspecialchars($page_title); ?></h1>
            <a href="/KP/admin_prodi/pengguna_mahasiswa_kelola.php" class="btn btn-light btn-sm mb-3">&laquo; Kembali ke Daftar Mahasiswa</a>
            <hr>

            <?php if (!empty($success_message)): ?>
                <div class="message success"><p><?php echo htmlspecialchars($success_message); ?></p></div>
            <?php endif; ?>
            <?php if (!empty($error_message)): ?>
                <div class="message error"><p><?php echo htmlspecialchars($error_message); ?></p></div>
            <?php endif; ?>

            <?php if ($mahasiswa_data): // Hanya tampilkan form jika data mahasiswa berhasil diambil ?>
            <form action="/KP/admin_prodi/pengguna_mahasiswa_edit.php?nim=<?php echo htmlspecialchars($nim_to_edit); ?>" method="POST">
                <input type="hidden" name="nim" value="<?php echo htmlspecialchars($mahasiswa_data['nim']); ?>">

                <fieldset>
                    <legend>Data Identitas Mahasiswa</legend>
                    <div class="form-group">
                        <label for="view_nim">NIM:</label>
                        <input type="text" id="view_nim" value="<?php echo htmlspecialchars($mahasiswa_data['nim']); ?>" readonly class="readonly-input">
                    </div>
                    <div class="form-group">
                        <label for="nama">Nama Lengkap (*):</label>
                        <input type="text" id="nama" name="nama" value="<?php echo htmlspecialchars($mahasiswa_data['nama']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="email">Email (*):</label>
                        <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($mahasiswa_data['email']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="no_hp">Nomor HP:</label>
                        <input type="text" id="no_hp" name="no_hp" value="<?php echo htmlspecialchars($mahasiswa_data['no_hp']); ?>">
                    </div>
                </fieldset>

                <fieldset>
                    <legend>Data Akademik</legend>
                    <div class="form-group">
                        <label for="prodi">Program Studi (*):</label>
                        <input type="text" id="prodi" name="prodi" value="<?php echo htmlspecialchars($mahasiswa_data['prodi']); ?>" required>
                         </div>
                    <div class="form-group">
                        <label for="angkatan">Angkatan (Tahun) (*):</label>
                        <input type="number" id="angkatan" name="angkatan" value="<?php echo htmlspecialchars($mahasiswa_data['angkatan']); ?>" min="1990" max="<?php echo date('Y')+1; ?>" required>
                    </div>
                </fieldset>

                <fieldset>
                    <legend>Pengaturan Akun</legend>
                     <div class="form-group">
                        <label for="password_sekarang">Password Saat Ini:</label>
                        <input type="text" id="password_sekarang" value="<?php echo htmlspecialchars($mahasiswa_data['password']); ?>" readonly class="readonly-input" title="Ini adalah password saat ini (plain text).">
                        <small>Password ditampilkan apa adanya (plain text) karena sistem tidak menggunakan hashing.</small>
                    </div>
                    <div class="form-group">
                        <label for="password_baru">Password Baru:</label>
                        <input type="password" id="password_baru" name="password_baru">
                        <small>Kosongkan jika tidak ingin mengubah password. Minimal 6 karakter jika diisi.</small>
                    </div>
                    <div class="form-group">
                        <label for="status_akun">Status Akun (*):</label>
                        <select id="status_akun" name="status_akun" required>
                            <?php foreach ($opsi_status_akun as $value => $text): ?>
                                <option value="<?php echo $value; ?>" <?php echo ($mahasiswa_data['status_akun'] == $value) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($text); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </fieldset>

                <div class="form-actions">
                    <button type="submit" name="submit_edit_mahasiswa" class="btn btn-primary">Simpan Perubahan</button>
                    <a href="/KP/admin_prodi/pengguna_mahasiswa_kelola.php" class="btn btn-secondary">Batal</a>
                </div>
            </form>
            <?php elseif(empty($error_message)): // Jika data mahasiswa belum terload tapi tidak ada error spesifik ?>
                <div class="message info"><p>Memuat data mahasiswa...</p></div>
            <?php endif; ?>

        </div>
    </main>
</div>

<style>
    /* Asumsikan CSS umum dari header, sidebar, form-container, fieldset, legend, form-group, message, btn sudah ada */
    .edit-mahasiswa-form h1 { margin-top: 0; margin-bottom: 5px; }
    .edit-mahasiswa-form hr { margin-top:15px; margin-bottom: 20px; }
    .btn.mb-3 { margin-bottom: 1rem !important; } /* Untuk tombol kembali */

    .readonly-input { background-color: #e9ecef; cursor: not-allowed; }
</style>

<?php
require_once '../includes/footer.php';

if (isset($conn) && ($conn instanceof mysqli)) {
    $conn->close();
}
?>