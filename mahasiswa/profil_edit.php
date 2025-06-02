<?php
// /KP/mahasiswa/profil_edit.php

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

// Sertakan file koneksi database
require_once '../config/db_connect.php';

$mahasiswa_data = null;
$error_message = '';
$success_message = '';

// 2. AMBIL DATA PROFIL SAAT INI UNTUK DITAMPILKAN DI FORM
if ($conn && ($conn instanceof mysqli)) {
    $sql_select = "SELECT nama, email, no_hp, prodi, angkatan FROM mahasiswa WHERE nim = ? LIMIT 1";
    $stmt_select = $conn->prepare($sql_select);
    if ($stmt_select) {
        $stmt_select->bind_param("s", $nim_mahasiswa);
        $stmt_select->execute();
        $result = $stmt_select->get_result();
        if ($result->num_rows === 1) {
            $mahasiswa_data = $result->fetch_assoc();
        } else {
            $error_message = "Data profil tidak ditemukan.";
        }
        $stmt_select->close();
    } else {
        $error_message = "Gagal mengambil data profil: " . (($conn->error) ? htmlspecialchars($conn->error) : "Kesalahan tidak diketahui.");
    }
} else {
    $error_message = "Koneksi database gagal atau tidak valid.";
}


// 3. PROSES UPDATE DATA JIKA FORM DISUBMIT (METHOD POST)
if ($_SERVER["REQUEST_METHOD"] == "POST" && $mahasiswa_data) { // Pastikan $mahasiswa_data ada sebelum proses update
    // Ambil data dari form (hanya field yang boleh diubah)
    // Lakukan validasi dan sanitasi input di sini sebelum update ke database!
    // Untuk contoh ini, kita asumsikan input sederhana.
    $new_email = $_POST['email'];
    $new_no_hp = $_POST['no_hp'];
    // Prodi dan Angkatan mungkin tidak seharusnya diubah oleh mahasiswa, tergantung kebijakan.
    // Jika boleh diubah, tambahkan di sini. Untuk contoh, kita fokus email dan no_hp.

    // Validasi sederhana (contoh: email harus valid)
    if (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Format email tidak valid.";
    } else {
        // Jika koneksi masih ada dan valid
        if ($conn && ($conn instanceof mysqli)) {
            // Cek apakah email baru sudah digunakan oleh mahasiswa lain (kecuali dirinya sendiri)
            $sql_check_email = "SELECT nim FROM mahasiswa WHERE email = ? AND nim != ?";
            $stmt_check_email = $conn->prepare($sql_check_email);
            if($stmt_check_email) {
                $stmt_check_email->bind_param("ss", $new_email, $nim_mahasiswa);
                $stmt_check_email->execute();
                $stmt_check_email->store_result();

                if ($stmt_check_email->num_rows > 0) {
                    $error_message = "Email tersebut sudah digunakan oleh mahasiswa lain.";
                } else {
                    // Lanjutkan dengan proses update
                    $sql_update = "UPDATE mahasiswa SET email = ?, no_hp = ? WHERE nim = ?";
                    $stmt_update = $conn->prepare($sql_update);
                    if ($stmt_update) {
                        $stmt_update->bind_param("sss", $new_email, $new_no_hp, $nim_mahasiswa);
                        if ($stmt_update->execute()) {
                            $success_message = "Profil berhasil diperbarui!";
                            // Update data di $mahasiswa_data agar form menampilkan data terbaru
                            $mahasiswa_data['email'] = $new_email;
                            $mahasiswa_data['no_hp'] = $new_no_hp;
                            // Perbarui juga data di session jika email atau nama diubah (nama tidak diubah di sini)
                            $_SESSION['user_nama'] = $mahasiswa_data['nama']; // Jika nama diubah, update ini
                                                                            // Jika email dipakai untuk login/identifikasi lain, mungkin perlu diupdate juga
                        } else {
                            $error_message = "Gagal memperbarui profil: " . (($stmt_update->error) ? htmlspecialchars($stmt_update->error) : "Kesalahan tidak diketahui.");
                        }
                        $stmt_update->close();
                    } else {
                        $error_message = "Gagal menyiapkan statement update: " . (($conn->error) ? htmlspecialchars($conn->error) : "Kesalahan tidak diketahui.");
                    }
                }
                $stmt_check_email->close();
            } else {
                 $error_message = "Gagal menyiapkan statement pengecekan email: " . (($conn->error) ? htmlspecialchars($conn->error) : "Kesalahan tidak diketahui.");
            }
        } else {
            $error_message = "Koneksi database hilang saat akan update.";
        }
    }
}


// Set judul halaman dan sertakan header
$page_title = "Edit Profil Saya";
require_once '../includes/header.php';
?>

<div class="page-layout-wrapper">

    <?php require_once '../includes/sidebar_mahasiswa.php'; ?>

    <main class="main-content-area">
        <div class="edit-profile-container">
            <h1><?php echo htmlspecialchars($page_title); ?></h1>
            <hr>

            <?php if (!empty($success_message)): ?>
                <div class="message success">
                    <p><?php echo htmlspecialchars($success_message); ?></p>
                </div>
            <?php endif; ?>
            <?php if (!empty($error_message)): ?>
                <div class="message error">
                    <p><?php echo htmlspecialchars($error_message); ?></p>
                </div>
            <?php endif; ?>

            <?php if ($mahasiswa_data): // Hanya tampilkan form jika data awal berhasil diambil ?>
            <form action="/KP/mahasiswa/profil_edit.php" method="POST" class="profile-form">
                <fieldset>
                    <legend>Informasi Pribadi & Kontak</legend>
                    <div class="form-group">
                        <label for="nim">NIM:</label>
                        <input type="text" id="nim" name="nim" value="<?php echo htmlspecialchars($nim_mahasiswa); ?>" readonly class="readonly-input">
                        <small>NIM tidak dapat diubah.</small>
                    </div>

                    <div class="form-group">
                        <label for="nama">Nama Lengkap:</label>
                        <input type="text" id="nama" name="nama" value="<?php echo htmlspecialchars($mahasiswa_data['nama']); ?>" readonly class="readonly-input">
                        <small>Nama tidak dapat diubah melalui halaman ini.</small>
                    </div>

                    <div class="form-group">
                        <label for="email">Email:</label>
                        <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($mahasiswa_data['email']); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="no_hp">Nomor HP:</label>
                        <input type="text" id="no_hp" name="no_hp" value="<?php echo htmlspecialchars($mahasiswa_data['no_hp']); ?>">
                    </div>
                </fieldset>

                <fieldset>
                    <legend>Informasi Akademik</legend>
                    <div class="form-group">
                        <label for="prodi">Program Studi:</label>
                        <input type="text" id="prodi" name="prodi" value="<?php echo htmlspecialchars($mahasiswa_data['prodi']); ?>" readonly class="readonly-input">
                        <small>Program Studi tidak dapat diubah.</small>
                    </div>

                    <div class="form-group">
                        <label for="angkatan">Angkatan:</label>
                        <input type="text" id="angkatan" name="angkatan" value="<?php echo htmlspecialchars($mahasiswa_data['angkatan']); ?>" readonly class="readonly-input">
                        <small>Angkatan tidak dapat diubah.</small>
                    </div>
                </fieldset>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
                    <a href="/KP/mahasiswa/profil.php" class="btn btn-secondary">Batal</a>
                </div>
            </form>
            <?php elseif(empty($error_message)): // Jika $mahasiswa_data null tapi tidak ada $error_message eksplisit ?>
                <div class="message info"><p>Memuat data profil...</p></div>
            <?php endif; ?>

        </div>
    </main>

</div>

<style>
    /* Asumsikan CSS untuk .page-layout-wrapper, .sidebar-mahasiswa, .main-content-area sudah ada */

    .edit-profile-container {
        /* background-color: #fff; padding: 20px; border-radius: 8px; */ /* Dari .main-content-area */
    }
    .edit-profile-container h1 { margin-top: 0; margin-bottom: 10px; font-size: 1.8em; }
    .edit-profile-container hr { margin-bottom: 25px; }

    .profile-form fieldset {
        border: 1px solid #ddd;
        padding: 20px;
        margin-bottom: 25px;
        border-radius: 5px;
    }
    .profile-form legend {
        font-weight: bold;
        color: #007bff;
        padding: 0 10px;
        font-size: 1.1em;
    }
    .form-group {
        margin-bottom: 18px;
    }
    .form-group label {
        display: block;
        font-weight: bold;
        margin-bottom: 6px;
        color: #555;
    }
    .form-group input[type="text"],
    .form-group input[type="email"],
    .form-group input[type="number"] { /* (Angkatan jadi text readonly) */
        width: 100%;
        padding: 10px 12px;
        border: 1px solid #ccc;
        border-radius: 4px;
        box-sizing: border-box;
        font-size: 1em;
    }
    .form-group input[type="text"]:focus,
    .form-group input[type="email"]:focus {
        border-color: #007bff;
        box-shadow: 0 0 0 0.2rem rgba(0,123,255,.25);
        outline: none;
    }
    .form-group small {
        display: block;
        font-size: 0.85em;
        color: #777;
        margin-top: 4px;
    }
    .readonly-input {
        background-color: #e9ecef; /* Warna abu-abu untuk input readonly */
        cursor: not-allowed;
    }

    .form-actions {
        margin-top: 20px;
        text-align: left;
    }
    .form-actions .btn {
        margin-right: 10px;
    }
    .btn-secondary {
        color: #333;
        background-color: #f8f9fa;
        border: 1px solid #ccc;
    }
    .btn-secondary:hover {
        background-color: #e2e6ea;
    }

    /* Message styling (sudah ada di profil.php, pastikan konsisten atau global di header) */
    .message { padding: 10px 15px; margin-bottom: 20px; border-radius: 5px; border: 1px solid transparent; }
    .message.success { background-color: #d4edda; color: #155724; border-color: #c3e6cb; }
    .message.error { background-color: #f8d7da; color: #721c24; border-color: #f5c6cb; }
    .message.info { background-color: #d1ecf1; color: #0c5460; border-color: #bee5eb; }
</style>

<?php
require_once '../includes/footer.php';

if (isset($conn) && ($conn instanceof mysqli)) {
    $conn->close();
}
?>