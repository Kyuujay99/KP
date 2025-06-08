<?php
// /KP/register.php (Versi Final, Identik dengan Login)

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once 'config/db_connect.php'; 
$registration_message = '';
$registration_success = false;

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $role = $_POST['role'];
    $email = $_POST['email'];
    $password = $_POST['password'];
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
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary-color: #6a11cb; --secondary-color: #2575fc;
            --dark-color: #1a1a2e; --light-color: #f4f7f9;
            --text-color: #5a5a5a; --border-radius: 20px;
        }
        /* Latar Belakang & Partikel Animasi */
        body {
            font-family: 'Poppins', sans-serif; margin: 0;
            min-height: 100vh; overflow-x: hidden;
            display: flex; justify-content: center; align-items: center;
            padding: 2rem 1rem;
            background: linear-gradient(-45deg, #6a11cb, #2575fc, #ec008c, #fc6767);
            background-size: 400% 400%;
            animation: gradientBG 18s ease infinite;
        }
        @keyframes gradientBG {
            0% { background-position: 0% 50%; } 50% { background-position: 100% 50%; } 100% { background-position: 0% 50%; }
        }

        /* Container utama (sebelumnya .glass-card) */
        .register-container {
            width: 100%; max-width: 500px;
            padding: 2.5rem;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(25px); -webkit-backdrop-filter: blur(25px);
            border: 1.5px solid rgba(255, 255, 255, 0.2);
            border-radius: var(--border-radius);
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.2);
            animation: fadeInCard 1s ease-out;
            color: #fff;
            transition: transform 0.1s ease-out;
        }
        @keyframes fadeInCard { from { opacity: 0; transform: scale(0.9); } to { opacity: 1; transform: scale(1); } }
        
        /* Header formulir */
        .register-container h2 {
            text-align: center; font-size: 2rem; font-weight: 700;
            color: #fff; text-shadow: 0 2px 5px rgba(0,0,0,0.2);
            margin: 0 0 2rem 0;
        }

        /* Styling untuk semua elemen form */
        .register-container label {
            display: block; margin-bottom: 8px;
            color: rgba(255, 255, 255, 0.8); font-weight: 500; font-size: 0.9em;
        }
        .register-container input[type="text"],
        .register-container input[type="email"],
        .register-container input[type="password"],
        .register-container input[type="number"],
        .register-container textarea,
        .register-container select {
            width: 100%; padding: 12px 15px; margin-bottom: 1rem;
            background: rgba(255,255,255,0.15);
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 8px; font-size: 1rem; color: #fff;
            transition: all 0.3s ease;
        }
        .register-container select option { color: #000; background-color: #fff; }
        .register-container input::placeholder, .register-container textarea::placeholder { color: rgba(255,255,255,0.5); }
        .register-container input:focus,
        .register-container select:focus,
        .register-container textarea:focus {
            outline: none; background: rgba(255,255,255,0.25); border-color: rgba(255,255,255,0.8);
        }

        /* Tombol Submit */
        .register-container button[type="submit"] {
            width: 100%; padding: 15px; margin-top: 1rem;
            background-size: 200% auto;
            background-image: linear-gradient(to right, #2575fc 0%, #6a11cb 51%, #2575fc 100%);
            color: white; border: none; border-radius: 8px; cursor: pointer;
            font-size: 1.1rem; font-weight: 600; transition: all 0.5s ease;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        .register-container button[type="submit"]:hover {
            background-position: right center;
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.3);
        }

        /* Pesan notifikasi */
        .message { padding: 1rem; margin-bottom: 1.5rem; border-radius: 8px; text-align: center; }
        .message.success { background-color: rgba(40, 167, 69, 0.8); color: #fff; }
        .message.error { background-color: rgba(220, 53, 69, 0.8); color: #fff; }
        
        /* Link ke halaman login */
        .login-link { display: block; text-align: center; margin-top: 1.5rem; color: rgba(255,255,255,0.8); text-decoration: none; }
        .login-link:hover { text-decoration: underline; color: #fff; }

        /* Form section yang dinamis */
        .form-section {
            display: none;
            animation: fadeInSection 0.5s;
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid rgba(255,255,255,0.2);
        }
        .form-section.active { display: block; }
        .form-section h3 {
            font-size: 1.1em; color: #fff;
            margin: 0 0 1rem 0; text-align: center; opacity: 0.9;
        }
        @keyframes fadeInSection { from { opacity: 0; } to { opacity: 1; } }

        /* Auth fields section - untuk email, password, confirm password */
        #auth_fields {
            display: none;
            animation: fadeInSection 0.5s;
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 1px dashed rgba(255,255,255,0.2);
        }
        #auth_fields.active { display: block; }

    </style>
</head>
<body>
    <div class="register-container" id="register-card">
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

            <div id="auth_fields" class="form-section">
                <label for="email">Email:</label>
                <input type="email" id="email" name="email" required>
                <label for="password">Password:</label>
                <input type="password" id="password" name="password" required>
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
        // Skrip untuk 3D tilt mengikuti kursor
        const card = document.getElementById('register-card');
        if (card) {
            document.body.addEventListener('mousemove', e => {
                const rect = card.getBoundingClientRect();
                const x = e.clientX - (rect.left + rect.width / 2);
                const y = e.clientY - (rect.top + rect.height / 2);
                const rotateX = -y / 40;
                const rotateY = x / 40;
                card.style.transform = `perspective(1500px) rotateX(${rotateX}deg) rotateY(${rotateY}deg)`;
            });
            document.body.addEventListener('mouseleave', () => {
                card.style.transform = 'perspective(1500px) rotateX(0) rotateY(0)';
            });
        }

        // Skrip untuk form dinamis
        function toggleRoleFields() {
            const role = document.getElementById('role').value;
            const allSections = document.querySelectorAll('.form-section');
            const authFields = document.getElementById('auth_fields');
            
            // Reset semua required fields
            document.querySelectorAll('.form-section input, .form-section select, .form-section textarea').forEach(input => input.required = false);
            
            // Sembunyikan semua sections
            allSections.forEach(section => section.classList.remove('active'));

            if (role) {
                // Tampilkan auth fields (email, password, confirm password)
                authFields.classList.add('active');
                authFields.querySelectorAll('input').forEach(input => input.required = true);
                
                // Tampilkan section yang sesuai dengan role
                const sectionToShow = document.getElementById(role + '_fields');
                if (sectionToShow) {
                    sectionToShow.classList.add('active');
                    sectionToShow.querySelectorAll('input, select, textarea').forEach(input => {
                        // Hanya set required jika labelnya mengandung (*)
                        const label = document.querySelector(`label[for="${input.id}"]`);
                        if (label && label.textContent.includes('(*)')) {
                            input.required = true;
                        }
                    });
                }
            } else {
                // Sembunyikan auth fields jika belum pilih role
                authFields.classList.remove('active');
            }
        }
        
        document.addEventListener('DOMContentLoaded', toggleRoleFields);
    </script>
</body>
</html>