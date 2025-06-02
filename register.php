<?php
// /KP/register.php
session_start();
include 'config/db_connect.php'; // Pastikan path ini benar
$registration_message = '';
$registration_success = false;

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $role = $_POST['role'];
    $email = $_POST['email'];
    $password = $_POST['password']; // Password akan disimpan apa adanya
    $confirm_password = $_POST['confirm_password'];

    if ($password !== $confirm_password) {
        $registration_message = "Password dan konfirmasi password tidak cocok.";
    } else {
        // Proses registrasi berdasarkan peran
        if ($role == "mahasiswa") {
            $nim = $_POST['nim'];
            $nama = $_POST['nama_mahasiswa'];
            $prodi = $_POST['prodi'];
            $angkatan = $_POST['angkatan'];
            $no_hp = $_POST['no_hp_mahasiswa'];

            $stmt_check = $conn->prepare("SELECT nim FROM mahasiswa WHERE nim = ? OR email = ?");
            $stmt_check->bind_param("ss", $nim, $email);
            $stmt_check->execute();
            $stmt_check->store_result();

            if ($stmt_check->num_rows > 0) {
                $registration_message = "NIM atau Email sudah terdaftar.";
            } else {
                $stmt = $conn->prepare("INSERT INTO mahasiswa (nim, password, nama, email, no_hp, prodi, angkatan, status_akun) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending_verification')");
                $stmt->bind_param("ssssssi", $nim, $password, $nama, $email, $no_hp, $prodi, $angkatan);
                if ($stmt->execute()) {
                    $registration_success = true;
                    $registration_message = "Registrasi Mahasiswa berhasil! Akun Anda menunggu verifikasi admin.";
                } else {
                    $registration_message = "Registrasi gagal: " . $stmt->error;
                }
                $stmt->close();
            }
            $stmt_check->close();

        } elseif ($role == "dosen") {
            $nip = $_POST['nip'];
            $nama_dosen = $_POST['nama_dosen'];

            $stmt_check_dosen = $conn->prepare("SELECT nip FROM dosen_pembimbing WHERE nip = ? OR email = ?");
            $stmt_check_dosen->bind_param("ss", $nip, $email);
            $stmt_check_dosen->execute();
            $stmt_check_dosen->store_result();

            if ($stmt_check_dosen->num_rows > 0) {
                $registration_message = "NIP atau Email sudah terdaftar untuk dosen.";
            } else {
                $stmt = $conn->prepare("INSERT INTO dosen_pembimbing (nip, password, nama_dosen, email, status_akun) VALUES (?, ?, ?, ?, 'active')");
                $stmt->bind_param("ssss", $nip, $password, $nama_dosen, $email);
                if ($stmt->execute()) {
                    $registration_success = true;
                    $registration_message = "Registrasi Dosen berhasil!";
                } else {
                    $registration_message = "Registrasi gagal: " . $stmt->error;
                }
                $stmt->close();
            }
            $stmt_check_dosen->close();

        } elseif ($role == "perusahaan") {
            $nama_perusahaan = $_POST['nama_perusahaan'];
            $alamat = $_POST['alamat_perusahaan'];
            $bidang = $_POST['bidang_perusahaan'];

            $stmt_check_perusahaan = $conn->prepare("SELECT id_perusahaan FROM perusahaan WHERE email_perusahaan = ?");
            $stmt_check_perusahaan->bind_param("s", $email);
            $stmt_check_perusahaan->execute();
            $stmt_check_perusahaan->store_result();

            if ($stmt_check_perusahaan->num_rows > 0) {
                $registration_message = "Email sudah terdaftar untuk perusahaan.";
            } else {
                $stmt = $conn->prepare("INSERT INTO perusahaan (email_perusahaan, password_perusahaan, nama_perusahaan, alamat, bidang, status_akun) VALUES (?, ?, ?, ?, ?, 'pending_approval')");
                $stmt->bind_param("sssss", $email, $password, $nama_perusahaan, $alamat, $bidang);
                if ($stmt->execute()) {
                    $registration_success = true;
                    $registration_message = "Registrasi Perusahaan berhasil! Akun Anda menunggu approval admin.";
                } else {
                    $registration_message = "Registrasi gagal: " . $stmt->error;
                }
                $stmt->close();
            }
            $stmt_check_perusahaan->close();
        } else {
            $registration_message = "Peran tidak valid untuk registrasi.";
        }
    }
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrasi - Sistem Informasi Kerja Praktek</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f4f4f4; display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; padding: 20px 0; }
        .register-container { background-color: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 0 15px rgba(0,0,0,0.1); width: 400px; }
        .register-container h2 { text-align: center; color: #333; margin-bottom: 20px; }
        .register-container label { display: block; margin-bottom: 8px; color: #555; font-weight: bold; }
        .register-container input[type="text"],
        .register-container input[type="email"],
        .register-container input[type="password"],
        .register-container input[type="number"],
        .register-container textarea,
        .register-container select { width: 100%; padding: 10px; margin-bottom: 15px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box; }
        .register-container button { width: 100%; padding: 12px; background-color: #28a745; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; font-weight: bold; }
        .register-container button:hover { background-color: #218838; }
        .message { padding: 10px; margin-bottom: 15px; border-radius: 4px; text-align: center; font-size: 0.9em; }
        .message.success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb;}
        .message.error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb;}
        .login-link { display: block; text-align: center; margin-top: 20px; color: #007bff; text-decoration: none; font-size: 0.9em; }
        .login-link:hover { text-decoration: underline; }
        .form-section { border-top: 1px dashed #eee; padding-top: 15px; margin-top:15px; display: none; }
        .form-section.active { display: block; }
        .form-section h3 { font-size: 1.1em; color: #333; margin-bottom:10px; }
    </style>
</head>
<body>
    <div class="register-container">
        <h2>Registrasi Akun Baru</h2>

        <?php if (!empty($registration_message)): ?>
            <div class="message <?php echo $registration_success ? 'success' : 'error'; ?>">
                <?php echo htmlspecialchars($registration_message); ?>
            </div>
        <?php endif; ?>

        <?php if (!$registration_success): ?>
        <form action="/KP/register.php" method="POST" id="registrationForm">
            <div>
                <label for="role">Daftar sebagai:</label>
                <select id="role" name="role" required onchange="toggleRoleFields()">
                    <option value="">-- Pilih Peran --</option>
                    <option value="mahasiswa">Mahasiswa</option>
                    <option value="dosen">Dosen</option>
                    <option value="perusahaan">Perusahaan</option>
                </select>
            </div>

            <div style="border-top: 1px dashed #eee; padding-top: 15px; margin-top:15px;">
                <label for="email">Email:</label>
                <input type="email" id="email" name="email" required>
            </div>
            <div>
                <label for="password">Password:</label>
                <input type="password" id="password" name="password" required>
            </div>
            <div>
                <label for="confirm_password">Konfirmasi Password:</label>
                <input type="password" id="confirm_password" name="confirm_password" required>
            </div>

            <div id="mahasiswa_fields" class="form-section">
                <h3>Detail Mahasiswa</h3>
                <label for="nim">NIM:</label>
                <input type="text" id="nim" name="nim">
                <label for="nama_mahasiswa">Nama Lengkap (Mahasiswa):</label>
                <input type="text" id="nama_mahasiswa" name="nama_mahasiswa">
                <label for="prodi">Program Studi:</label>
                <select id="prodi" name="prodi">
                    <option value="">-- Pilih Prodi --</option>
                    <option value="Teknik Informatika">Teknik Informatika</option>
                    <option value="Sistem Informasi">Sistem Informasi</option>
                    <option value="Teknik Elektro">Teknik Elektro</option>
                    <option value="Teknik Mesin">Teknik Mesin</option>
                    </select>
                <label for="angkatan">Angkatan (Tahun):</label>
                <input type="number" id="angkatan" name="angkatan" min="2000" max="<?php echo date('Y'); ?>">
                <label for="no_hp_mahasiswa">No. HP (Mahasiswa):</label>
                <input type="text" id="no_hp_mahasiswa" name="no_hp_mahasiswa" placeholder="Contoh: 08123456789">
            </div>

            <div id="dosen_fields" class="form-section">
                <h3>Detail Dosen</h3>
                <label for="nip">NIP:</label>
                <input type="text" id="nip" name="nip">
                <label for="nama_dosen">Nama Lengkap (Dosen):</label>
                <input type="text" id="nama_dosen" name="nama_dosen">
            </div>

            <div id="perusahaan_fields" class="form-section">
                <h3>Detail Perusahaan</h3>
                <label for="nama_perusahaan">Nama Perusahaan:</label>
                <input type="text" id="nama_perusahaan" name="nama_perusahaan">
                <label for="alamat_perusahaan">Alamat Perusahaan:</label>
                <textarea id="alamat_perusahaan" name="alamat_perusahaan" rows="3"></textarea>
                <label for="bidang_perusahaan">Bidang Perusahaan:</label>
                <input type="text" id="bidang_perusahaan" name="bidang_perusahaan">
            </div>

            <button type="submit">Daftar</button>
        </form>
        <?php endif; ?>

        <a href="/KP/index.php" class="login-link">Sudah punya akun? Login di sini</a>
    </div>

    <script>
        function toggleRoleFields() {
            const role = document.getElementById('role').value;
            const allSections = document.querySelectorAll('.form-section');
            const emailField = document.getElementById('email');
            const passwordField = document.getElementById('password');
            const confirmPasswordField = document.getElementById('confirm_password');

            // Sembunyikan semua section spesifik peran
            allSections.forEach(section => section.classList.remove('active'));
            // Set semua input di section spesifik peran menjadi tidak required
            document.querySelectorAll('.form-section input, .form-section select, .form-section textarea').forEach(input => input.required = false);

            // Bagian umum selalu required jika peran dipilih
            if (role) {
                emailField.required = true;
                passwordField.required = true;
                confirmPasswordField.required = true;
            } else {
                emailField.required = false;
                passwordField.required = false;
                confirmPasswordField.required = false;
            }


            if (role === 'mahasiswa') {
                const mahasiswaSection = document.getElementById('mahasiswa_fields');
                mahasiswaSection.classList.add('active');
                // Set required fields untuk mahasiswa
                mahasiswaSection.querySelector('#nim').required = true;
                mahasiswaSection.querySelector('#nama_mahasiswa').required = true;
                mahasiswaSection.querySelector('#prodi').required = true;
                mahasiswaSection.querySelector('#angkatan').required = true;
            } else if (role === 'dosen') {
                const dosenSection = document.getElementById('dosen_fields');
                dosenSection.classList.add('active');
                // Set required fields untuk dosen
                dosenSection.querySelector('#nip').required = true;
                dosenSection.querySelector('#nama_dosen').required = true;
            } else if (role === 'perusahaan') {
                const perusahaanSection = document.getElementById('perusahaan_fields');
                perusahaanSection.classList.add('active');
                // Set required fields untuk perusahaan
                perusahaanSection.querySelector('#nama_perusahaan').required = true;
            }
        }
        // Panggil saat load untuk set kondisi awal (terutama jika ada error dan form di-repopulate)
        document.addEventListener('DOMContentLoaded', toggleRoleFields);
    </script>
</body>
</html>