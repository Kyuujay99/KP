<?php
// /KP/register.php (Versi Final dengan UI yang Disesuaikan Penuh)

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once 'config/db_connect.php'; 
$registration_message = '';
$registration_success = false;

// Variabel untuk menyimpan input jika terjadi error
$input_values = [
    'role' => '', 'email' => '', 'nim' => '', 'nama_mahasiswa' => '', 'prodi' => '', 'angkatan' => '', 'no_hp_mahasiswa' => '',
    'nip' => '', 'nama_dosen' => '', 'nama_perusahaan' => '', 'alamat_perusahaan' => '', 'bidang_perusahaan' => ''
];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Simpan semua input untuk repopulate form jika ada error
    foreach ($input_values as $key => &$value) {
        if (isset($_POST[$key])) {
            $value = $_POST[$key];
        }
    }

    $role = $_POST['role'];
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    if (empty($role) || empty($email) || empty($password)) {
        $registration_message = "Harap lengkapi semua field yang ditandai (*).";
    } elseif ($password !== $confirm_password) {
        $registration_message = "Duh, password sama konfirmasinya nggak cocok, ayang.";
    } else {
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
                $registration_message = "NIM atau Email ini udah ada yang pake, bub.";
            } else {
                $stmt = $conn->prepare("INSERT INTO mahasiswa (nim, password, nama, email, no_hp, prodi, angkatan, status_akun) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending_verification')");
                $stmt->bind_param("ssssssi", $nim, $password, $nama, $email, $no_hp, $prodi, $angkatan);
                if ($stmt->execute()) {
                    $registration_success = true;
                    $registration_message = "Hore! Registrasi Mahasiswa berhasil! Tinggal nunggu diverifikasi admin yaa.";
                } else {
                    $registration_message = "Yahh, registrasi gagal: " . $stmt->error;
                }
                $stmt->close();
            }
            $stmt_check->close();

        } elseif ($role == "dosen") {
            // Logika untuk dosen
             $nip = $_POST['nip'];
            $nama_dosen = $_POST['nama_dosen'];
            $stmt_check_dosen = $conn->prepare("SELECT nip FROM dosen_pembimbing WHERE nip = ? OR email = ?");
            $stmt_check_dosen->bind_param("ss", $nip, $email);
            $stmt_check_dosen->execute();
            $stmt_check_dosen->store_result();

            if ($stmt_check_dosen->num_rows > 0) {
                $registration_message = "NIP atau Email ini udah dipake sama dosen lain.";
            } else {
                $stmt = $conn->prepare("INSERT INTO dosen_pembimbing (nip, password, nama_dosen, email, status_akun) VALUES (?, ?, ?, ?, 'active')");
                $stmt->bind_param("ssss", $nip, $password, $nama_dosen, $email);
                if ($stmt->execute()) {
                    $registration_success = true;
                    $registration_message = "Registrasi Dosen berhasil! Akunnya langsung aktif, lho.";
                } else {
                    $registration_message = "Registrasi gagal: " . $stmt->error;
                }
                $stmt->close();
            }
            $stmt_check_dosen->close();

        } elseif ($role == "perusahaan") {
            // Logika untuk perusahaan
            $nama_perusahaan = $_POST['nama_perusahaan'];
            $alamat = $_POST['alamat_perusahaan'];
            $bidang = $_POST['bidang_perusahaan'];
            $stmt_check_perusahaan = $conn->prepare("SELECT id_perusahaan FROM perusahaan WHERE email_perusahaan = ?");
            $stmt_check_perusahaan->bind_param("s", $email);
            $stmt_check_perusahaan->execute();
            $stmt_check_perusahaan->store_result();

            if ($stmt_check_perusahaan->num_rows > 0) {
                $registration_message = "Email ini udah dipake sama perusahaan lain, bub.";
            } else {
                $stmt = $conn->prepare("INSERT INTO perusahaan (email_perusahaan, password_perusahaan, nama_perusahaan, alamat, bidang, status_akun) VALUES (?, ?, ?, ?, ?, 'pending_approval')");
                $stmt->bind_param("sssss", $email, $password, $nama_perusahaan, $alamat, $bidang);
                if ($stmt->execute()) {
                    $registration_success = true;
                    $registration_message = "Registrasi Perusahaan berhasil! Tinggal tunggu approval dari admin yaa.";
                } else {
                    $registration_message = "Registrasi gagal: " . $stmt->error;
                }
                $stmt->close();
            }
            $stmt_check_perusahaan->close();
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
    <title>Registrasi - SIM Kerja Praktek</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root { --primary-color: #6a11cb; --secondary-color: #2575fc; --dark-color: #1a1a2e; --light-color: #f4f7f9; --text-color: #5a5a5a; --border-radius: 15px; }
        body { font-family: 'Poppins', sans-serif; margin: 0; min-height: 100vh; overflow-y: auto; display: flex; justify-content: center; align-items: center; padding: 2rem 1rem; background: linear-gradient(-45deg, #c3cfe2, #2c3e50, #2d3748, #0f172a); background-size: 400% 400%; animation: gradientBG 18s ease infinite; }
        @keyframes gradientBG { 0% { background-position: 0% 50%; } 50% { background-position: 100% 50%; } 100% { background-position: 0% 50%; } }
        .background-shapes { position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: 1; pointer-events:none; overflow: hidden;}
        .shape { position: absolute; list-style: none; display: block; background: rgba(255, 255, 255, 0.15); animation: moveShape 20s linear infinite; bottom: -150px; }
        .shape.s1 { left: 10%; width: 80px; height: 80px; animation-delay: 0s; } .shape.s2 { left: 20%; width: 30px; height: 30px; animation-delay: 2s; animation-duration: 17s; } .shape.s3 { left: 25%; width: 100px; height: 100px; animation-delay: 4s; } .shape.s4 { left: 40%; width: 60px; height: 60px; animation-delay: 0s; animation-duration: 22s; } .shape.s5 { left: 65%; width: 20px; height: 20px; animation-delay: 0s; } .shape.s6 { left: 75%; width: 110px; height: 110px; animation-delay: 3s; } .shape.s7 { left: 90%; width: 150px; height: 150px; animation-delay: 7s; }
        @keyframes moveShape { 0% { transform: translateY(0) rotate(0deg); opacity: 1; border-radius: 20%; } 100% { transform: translateY(-120vh) rotate(720deg); opacity: 0; border-radius: 50%; } }
        .glass-card { position: relative; z-index: 2; width: 100%; max-width: 480px; padding: 2.5rem; background: rgba(255, 255, 255, 0.25); backdrop-filter: blur(15px); -webkit-backdrop-filter: blur(15px); border: 1px solid rgba(255, 255, 255, 0.3); border-radius: 20px; box-shadow: 0 15px 35px rgba(0, 0, 0, 0.15); animation: fadeInCard 1s ease-out; transition: transform 0.2s ease; }
        @keyframes fadeInCard { from { opacity: 0; transform: translateY(30px); } to { opacity: 1; transform: translateY(0); } }
        .form-header h2 { text-align: center; font-size: 2rem; font-weight: 700; color: #fff; text-shadow: 0 2px 5px rgba(0,0,0,0.2); margin: 0 0 2rem 0; }
        .message { padding: 1rem; margin-bottom: 1.5rem; border-radius: 8px; text-align: center; font-size: 0.9rem; animation: fadeInCard 0.5s; }
        .message.success { background-color: rgba(40, 167, 69, 0.8); color: #fff; }
        .message.error { background-color: rgba(220, 53, 69, 0.8); color: #fff; }
        .input-group { position: relative; margin-bottom: 1.5rem; }
        .input-group label { position: absolute; left: 15px; top: 14px; font-size: 1rem; color: rgba(255,255,255,0.6); pointer-events: none; transition: all 0.3s ease; z-index: 1; }
        .input-group input, .input-group select, .input-group textarea { width: 100%; padding: 14px 15px; background: rgba(255, 255, 255, 0.1); border: 1px solid rgba(255, 255, 255, 0.3); border-radius: 8px; font-size: 1rem; color: #fff; transition: all 0.3s ease; box-sizing: border-box; }
        .input-group input::placeholder, .input-group textarea::placeholder { color: transparent; }
        .input-group input:focus, .input-group input:not(:placeholder-shown), .input-group select:focus, .input-group select:valid, .input-group textarea:focus, .input-group textarea:not(:placeholder-shown) { outline: none; background: rgba(255,255,255,0.2); border-color: rgba(255,255,255,0.8); }
        .input-group input:focus + label, .input-group input:not(:placeholder-shown) + label, .input-group select:focus + label, .input-group select:valid + label, .input-group textarea:focus + label, .input-group textarea:not(:placeholder-shown) + label { top: -10px; left: 10px; font-size: 0.8em; background: linear-gradient(135deg, #6a11cb, #2575fc); padding: 2px 8px; color: #fff; border-radius: 4px; box-shadow: 0 2px 8px rgba(0,0,0,0.2); }
        .custom-select select { appearance: none; -webkit-appearance: none; -moz-appearance: none; cursor: pointer; }
        .custom-select select option { background: #2c2c54; color: #fff; padding: 12px 16px; }
        .custom-select::after { content: ''; position: absolute; top: 50%; right: 15px; transform: translateY(-50%) rotate(45deg); width: 8px; height: 8px; border-right: 2px solid rgba(255,255,255,0.7); border-bottom: 2px solid rgba(255,255,255,0.7); pointer-events: none; transition: all 0.3s ease; }
        .custom-select:hover::after { border-color: #fff; }
        .custom-select select:focus ~ .custom-select::after { transform: translateY(-25%) rotate(225deg); }
        .btn-submit { width: 100%; padding: 15px; background-size: 200% auto; background-image: linear-gradient(to right, #2575fc 0%, #6a11cb 51%, #2575fc 100%); color: white; border: none; border-radius: 8px; cursor: pointer; font-size: 1.1rem; font-weight: 600; transition: all 0.5s ease; box-shadow: 0 5px 15px rgba(0,0,0,0.2); text-transform: uppercase; letter-spacing: 1px; }
        .btn-submit:hover { background-position: right center; transform: translateY(-2px); box-shadow: 0 8px 25px rgba(0,0,0,0.3); }
        .login-link-container { text-align: center; margin-top: 1.5rem; color: #fff; }
        .login-link { color: #fff; font-weight: 600; text-decoration: none; transition: all 0.3s ease; }
        .login-link:hover { color: #ffd700; text-shadow: 0 0 10px rgba(255, 215, 0, 0.5); }
        .form-section { display: none; animation: fadeInSection 0.5s; margin-top: 1.5rem; padding-top: 1.5rem; border-top: 1px dashed rgba(255,255,255,0.2); }
        .form-section.active { display: block; }
        @keyframes fadeInSection { from { opacity: 0; } to { opacity: 1; } }
    </style>
</head>
<body>
    <ul class="background-shapes">
        <li class="shape s1"></li><li class="shape s2"></li><li class="shape s3"></li><li class="shape s4"></li><li class="shape s5"></li><li class="shape s6"></li><li class="shape s7"></li>
    </ul>

    <div class="glass-card" id="register-card">
        <div class="form-header">
            <h2>Registrasi Akun</h2>
        </div>

        <?php if (!empty($registration_message)): ?>
            <div class="message <?php echo $registration_success ? 'success' : 'error'; ?>">
                <?php echo htmlspecialchars($registration_message); ?>
            </div>
        <?php endif; ?>

        <?php if (!$registration_success): ?>
        <form action="/KP/register.php" method="POST" id="registrationForm">
            <div class="input-group custom-select">
                <select id="role" name="role" required onchange="toggleRoleFields()">
                    <option value="" disabled <?php echo empty($input_values['role']) ? 'selected' : ''; ?>></option>
                    <option value="mahasiswa" <?php echo ($input_values['role'] == 'mahasiswa') ? 'selected' : ''; ?>>👨‍🎓 Mahasiswa</option>
                    <option value="dosen" <?php echo ($input_values['role'] == 'dosen') ? 'selected' : ''; ?>>👨‍🏫 Dosen</option>
                    <option value="perusahaan" <?php echo ($input_values['role'] == 'perusahaan') ? 'selected' : ''; ?>>🏢 Perusahaan</option>
                </select>
                 <label for="role">Daftar sebagai (*)</label>
            </div>

            <!-- Bagian yang muncul setelah role dipilih -->
            <div id="auth_fields" class="form-section">
                <div class="input-group"><input type="email" id="email" name="email" value="<?php echo htmlspecialchars($input_values['email']); ?>" placeholder=" "><label for="email">Email (*)</label></div>
                <div class="input-group"><input type="password" id="password" name="password" minlength="6" placeholder=" "><label for="password">Password (*)</label></div>
                <div class="input-group"><input type="password" id="confirm_password" name="confirm_password" minlength="6" placeholder=" "><label for="confirm_password">Konfirmasi Password (*)</label></div>
            </div>

            <!-- Form Mahasiswa -->
            <div id="mahasiswa_fields" class="form-section">
                <div class="input-group"><input type="text" id="nim" name="nim" value="<?php echo htmlspecialchars($input_values['nim']); ?>" placeholder=" "><label for="nim">NIM (*)</label></div>
                <div class="input-group"><input type="text" id="nama_mahasiswa" name="nama_mahasiswa" value="<?php echo htmlspecialchars($input_values['nama_mahasiswa']); ?>" placeholder=" "><label for="nama_mahasiswa">Nama Lengkap (*)</label></div>
                <div class="input-group custom-select"><select id="prodi" name="prodi"><option value="" disabled selected></option><option value="Teknik Informatika" <?php echo ($input_values['prodi'] == 'Teknik Informatika') ? 'selected' : ''; ?>>Teknik Informatika</option><option value="Sistem Informasi" <?php echo ($input_values['prodi'] == 'Sistem Informasi') ? 'selected' : ''; ?>>Sistem Informasi</option><option value="Teknik Elektro" <?php echo ($input_values['prodi'] == 'Teknik Elektro') ? 'selected' : ''; ?>>Teknik Elektro</option><option value="Teknik Mesin" <?php echo ($input_values['prodi'] == 'Teknik Mesin') ? 'selected' : ''; ?>>Teknik Mesin</option></select><label for="prodi">Program Studi (*)</label></div>
                <div class="input-group"><input type="number" id="angkatan" name="angkatan" min="2000" max="<?php echo date('Y'); ?>" value="<?php echo htmlspecialchars($input_values['angkatan']); ?>" placeholder=" "><label for="angkatan">Angkatan (*)</label></div>
                <div class="input-group"><input type="text" id="no_hp_mahasiswa" name="no_hp_mahasiswa" value="<?php echo htmlspecialchars($input_values['no_hp_mahasiswa']); ?>" placeholder=" "><label for="no_hp_mahasiswa">No. HP (Opsional)</label></div>
            </div>

            <!-- Form Dosen -->
            <div id="dosen_fields" class="form-section">
                <div class="input-group"><input type="text" id="nip" name="nip" value="<?php echo htmlspecialchars($input_values['nip']); ?>" placeholder=" "><label for="nip">NIP (*)</label></div>
                <div class="input-group"><input type="text" id="nama_dosen" name="nama_dosen" value="<?php echo htmlspecialchars($input_values['nama_dosen']); ?>" placeholder=" "><label for="nama_dosen">Nama Lengkap & Gelar (*)</label></div>
            </div>

            <!-- Form Perusahaan -->
            <div id="perusahaan_fields" class="form-section">
                <div class="input-group"><input type="text" id="nama_perusahaan" name="nama_perusahaan" value="<?php echo htmlspecialchars($input_values['nama_perusahaan']); ?>" placeholder=" "><label for="nama_perusahaan">Nama Perusahaan (*)</label></div>
                <div class="input-group"><textarea id="alamat_perusahaan" name="alamat_perusahaan" rows="2" placeholder=" "><?php echo htmlspecialchars($input_values['alamat_perusahaan']); ?></textarea><label for="alamat_perusahaan">Alamat (Opsional)</label></div>
                <div class="input-group"><input type="text" id="bidang_perusahaan" name="bidang_perusahaan" value="<?php echo htmlspecialchars($input_values['bidang_perusahaan']); ?>" placeholder=" "><label for="bidang_perusahaan">Bidang Usaha (Opsional)</label></div>
            </div>

            <button type="submit" name="submit_tambah_perusahaan" class="btn-submit">Daftar</button>
        </form>
        <?php endif; ?>

        <div class="login-link-container">
            Sudah punya akun? <a href="/KP/index.php" class="login-link">Login di sini</a>
        </div>
    </div>
    <script>
        const card = document.getElementById('register-card');
        if (card) {
            document.body.addEventListener('mousemove', e => {
                const rect = card.getBoundingClientRect();
                const x = e.clientX - (rect.left + rect.width / 2);
                const y = e.clientY - (rect.top + rect.height / 2);
                card.style.transform = `perspective(1500px) rotateX(${-y / 40}deg) rotateY(${x / 40}deg)`;
            });
            document.body.addEventListener('mouseleave', () => {
                card.style.transform = 'perspective(1500px) rotateX(0) rotateY(0)';
            });
        }

        function toggleRoleFields() {
            const role = document.getElementById('role').value;
            const allSections = document.querySelectorAll('.form-section');
            const authFields = document.getElementById('auth_fields');
            
            allSections.forEach(section => {
                section.classList.remove('active');
                section.querySelectorAll('input, select, textarea').forEach(input => input.required = false);
            });

            if (role) {
                authFields.classList.add('active');
                authFields.querySelectorAll('input').forEach(input => input.required = true);
                
                const sectionToShow = document.getElementById(role + '_fields');
                if (sectionToShow) {
                    sectionToShow.classList.add('active');
                    sectionToShow.querySelectorAll('input, select, textarea').forEach(input => {
                        const label = document.querySelector(`label[for="${input.id}"]`);
                        if (label && label.textContent.includes('(*)')) {
                            input.required = true;
                        }
                    });
                }
            }
        }
        document.addEventListener('DOMContentLoaded', toggleRoleFields);
    </script>
</body>
</html>
