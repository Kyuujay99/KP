<?php
// /KP/index.php (Versi Final dengan UI yang Ditingkatkan)

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// 1. PENGECEKAN APAKAH PENGGUNA SUDAH LOGIN
if (isset($_SESSION['user_id']) && isset($_SESSION['user_role'])) {
    $role_dashboard_map = [
        'mahasiswa' => '/KP/mahasiswa/dashboard.php',
        'dosen' => '/KP/dosen/dashboard.php',
        'admin_prodi' => '/KP/admin_prodi/dashboard.php',
        'perusahaan' => '/KP/perusahaan/dashboard.php'
    ];
    if (array_key_exists($_SESSION['user_role'], $role_dashboard_map)) {
        header("Location: " . $role_dashboard_map[$_SESSION['user_role']]);
        exit();
    }
}

$error_message = '';

// 2. PROSES LOGIN KETIKA FORM DISUBMIT
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    require_once 'config/db_connect.php';

    $username_or_email = $_POST['username_or_email'];
    $password_input = $_POST['password_input'];
    $role = $_POST['role'];

    $table_map = [
        "mahasiswa" => ['table' => "mahasiswa", 'id_col' => "nim", 'name_col' => "nama", 'pass_col' => "password", 'id_field' => "nim", 'status_col' => 'status_akun'],
        "dosen" => ['table' => "dosen_pembimbing", 'id_col' => "nip", 'name_col' => "nama_dosen", 'pass_col' => "password", 'id_field' => "nip", 'status_col' => 'status_akun'],
        "admin_prodi" => ['table' => "admin_prodi", 'id_col' => "id_admin", 'name_col' => "nama_admin", 'pass_col' => "password", 'id_field' => "username"],
        "perusahaan" => ['table' => "perusahaan", 'id_col' => "id_perusahaan", 'name_col' => "nama_perusahaan", 'pass_col' => "password_perusahaan", 'id_field' => "email_perusahaan", 'status_col' => 'status_akun']
    ];

    if (array_key_exists($role, $table_map)) {
        $config = $table_map[$role];
        $id_field = $config['id_field'];
        $email_field = ($role === 'admin_prodi') ? 'email_admin' : 'email';
        
        $sql = "SELECT * FROM {$config['table']} WHERE ($id_field = ? OR $email_field = ?) LIMIT 1";
        if ($role === 'perusahaan') {
             $sql = "SELECT * FROM {$config['table']} WHERE {$config['id_field']} = ? LIMIT 1";
        }

        $stmt = $conn->prepare($sql);
        if ($stmt) {
            if ($role === 'perusahaan') {
                $stmt->bind_param("s", $username_or_email);
            } else {
                $stmt->bind_param("ss", $username_or_email, $username_or_email);
            }
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows == 1) {
                $user = $result->fetch_assoc();

                // Cek status akun sebelum verifikasi password
                if (isset($config['status_col']) && $user[$config['status_col']] === 'suspended') {
                    $error_message = "Akun Anda telah ditangguhkan. Silakan hubungi admin.";
                } elseif (isset($config['status_col']) && $user[$config['status_col']] === 'inactive') {
                    $error_message = "Akun Anda tidak aktif. Silakan hubungi admin.";
                } elseif ($password_input === $user[$config['pass_col']]) { // Password cocok (plain text)
                    $_SESSION['user_id'] = $user[$config['id_col']];
                    $_SESSION['user_nama'] = $user[$config['name_col']];
                    $_SESSION['user_role'] = $role;

                    header("Location: " . $table_map[$role]['redirect_path'] ?? "/KP/index.php");
                    exit();
                } else {
                    $error_message = "Password yang Anda masukkan salah.";
                }
            } else {
                $error_message = "Kombinasi User ID dan Password tidak ditemukan atau akun tidak aktif.";
            }
            $stmt->close();
        }
    } else {
        $error_message = "Peran yang dipilih tidak valid.";
    }
    if (isset($conn)) { $conn->close(); }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - SIM Kerja Praktek</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #6a11cb; --secondary-color: #2575fc;
            --dark-color: #1a1a2e; --light-color: #f4f7f9;
            --text-color: #5a5a5a; --border-radius: 15px;
        }
        body {
            font-family: 'Poppins', sans-serif; margin: 0;
            height: 100vh; overflow: hidden;
            display: flex; justify-content: center; align-items: center;
            background: linear-gradient(-45deg, #6a11cb, #2575fc, #ec008c, #fc6767);
            background-size: 400% 400%;
            animation: gradientBG 18s ease infinite;
        }
        
        .background-shapes { position: absolute; top: 0; left: 0; width: 100%; height: 100%; z-index: 1; pointer-events: none; }
        .shape { position: absolute; list-style: none; display: block; background: rgba(255, 255, 255, 0.15); animation: moveShape 20s linear infinite; bottom: -150px; }
        .shape.s1 { left: 10%; width: 80px; height: 80px; animation-delay: 0s; } .shape.s2 { left: 20%; width: 30px; height: 30px; animation-delay: 2s; animation-duration: 17s; } .shape.s3 { left: 25%; width: 100px; height: 100px; animation-delay: 4s; } .shape.s4 { left: 40%; width: 60px; height: 60px; animation-delay: 0s; animation-duration: 22s; } .shape.s5 { left: 65%; width: 20px; height: 20px; animation-delay: 0s; } .shape.s6 { left: 75%; width: 110px; height: 110px; animation-delay: 3s; } .shape.s7 { left: 90%; width: 150px; height: 150px; animation-delay: 7s; }
        @keyframes moveShape { 0% { transform: translateY(0) rotate(0deg); opacity: 1; border-radius: 20%; } 100% { transform: translateY(-120vh) rotate(720deg); opacity: 0; border-radius: 50%; } }
        @keyframes gradientBG { 0% { background-position: 0% 50%; } 50% { background-position: 100% 50%; } 100% { background-position: 0% 50%; } }
        @keyframes fadeInCard { 0% { opacity: 0; transform: translateY(30px); } 100% { opacity: 1; transform: translateY(0); } }

        .glass-card { position: relative; z-index: 2; width: 100%; max-width: 420px; padding: 3rem; background: rgba(255, 255, 255, 0.25); backdrop-filter: blur(15px); -webkit-backdrop-filter: blur(15px); border: 1px solid rgba(255, 255, 255, 0.3); border-radius: 20px; box-shadow: 0 15px 35px rgba(0, 0, 0, 0.15); animation: fadeInCard 1s ease-out; transition: transform 0.2s ease; }
        .form-header h1 { font-size: 2.2rem; font-weight: 700; color: #fff; text-align: center; text-shadow: 0 2px 5px rgba(0,0,0,0.2); margin-bottom: 0.5rem; }
        .form-header p { text-align: center; color: rgba(255,255,255,0.8); margin: 0 0 2rem 0; font-size: 0.95rem; }
        .error-message { background: rgba(255, 59, 48, 0.2); border: 1px solid rgba(255, 59, 48, 0.4); color: #fff; padding: 12px 16px; border-radius: 8px; margin-bottom: 1.5rem; font-size: 0.9rem; backdrop-filter: blur(10px); animation: shake 0.5s ease-in-out; }
        @keyframes shake { 0%, 100% { transform: translateX(0); } 25% { transform: translateX(-5px); } 75% { transform: translateX(5px); } }
         .input-group { position: relative; margin-bottom: 1.5rem; }
        .input-group label { position: absolute; left: 15px; top: 14px; font-size: 1rem; color: rgba(255,255,255,0.6); pointer-events: none; transition: all 0.3s ease; z-index: 1; }
        .input-group input, .input-group select, .input-group textarea { width: 100%; padding: 14px 15px; background: rgba(255, 255, 255, 0.1); border: 1px solid rgba(255, 255, 255, 0.3); border-radius: 8px; font-size: 1rem; color: #fff; transition: all 0.3s ease; box-sizing: border-box; }
        .input-group input::placeholder, .input-group textarea::placeholder { color: transparent; }
        .input-group input:focus, .input-group input:not(:placeholder-shown), .input-group select:focus, .input-group select:valid, .input-group textarea:focus, .input-group textarea:not(:placeholder-shown) { outline: none; background: rgba(255,255,255,0.2); border-color: rgba(255,255,255,0.8); }
        .input-group input:focus + label, .input-group input:not(:placeholder-shown) + label, .input-group select:focus + label, .input-group select:valid + label, .input-group textarea:focus + label, .input-group textarea:not(:placeholder-shown) + label { top: -10px; left: 10px; font-size: 0.8em; background: linear-gradient(135deg, #6a11cb, #2575fc); padding: 2px 8px; color: #fff; border-radius: 4px; box-shadow: 0 2px 8px rgba(0,0,0,0.2); }
        /* Custom Dropdown Styles */
        .custom-select select { appearance: none; -webkit-appearance: none; -moz-appearance: none; cursor: pointer; padding-right: 45px; }
        .custom-select select option { background: #2c2c54; color: #fff; padding: 12px 16px; }
        .custom-select::after { content: ''; position: absolute; top: 50%; right: 15px; transform: translateY(-50%) rotate(45deg); width: 8px; height: 8px; border-right: 2px solid rgba(255,255,255,0.7); border-bottom: 2px solid rgba(255,255,255,0.7); pointer-events: none; transition: all 0.3s ease; }
        .custom-select:hover::after { border-color: #fff; }
        .custom-select select:focus ~ .custom-select::after { transform: translateY(-25%) rotate(225deg); }
        
        .btn-submit { width: 100%; padding: 15px; background-size: 200% auto; background-image: linear-gradient(to right, #2575fc 0%, #6a11cb 51%, #2575fc 100%); color: white; border: none; border-radius: 8px; cursor: pointer; font-size: 1.1rem; font-weight: 600; transition: all 0.5s ease; box-shadow: 0 5px 15px rgba(0,0,0,0.2); text-transform: uppercase; letter-spacing: 1px; }
        .btn-submit:hover { background-position: right center; transform: translateY(-2px); box-shadow: 0 8px 25px rgba(0,0,0,0.3); }
        .btn-submit:active { transform: translateY(0); box-shadow: 0 5px 15px rgba(0,0,0,0.2); }
        
        .register-link-container { text-align: center; margin-top: 1.5rem; color: rgba(255,255,255,0.8); }
        .register-link { color: #fff; font-weight: 600; text-decoration: none; transition: all 0.3s ease; }
        .register-link:hover { color: #ffd700; text-shadow: 0 0 10px rgba(255, 215, 0, 0.5); }
        @media (max-width: 480px) { .glass-card { margin: 20px; padding: 2rem; } .form-header h1 { font-size: 1.8rem; } }
    </style>
</head>
<body>
    <ul class="background-shapes">
        <li class="shape s1"></li><li class="shape s2"></li><li class="shape s3"></li><li class="shape s4"></li><li class="shape s5"></li><li class="shape s6"></li><li class="shape s7"></li>
    </ul>

    <div class="glass-card" id="login-card">
        <div class="form-header">
            <h1>SIM-KP</h1>
            <p>Sistem Informasi Manajemen Kerja Praktek</p>
        </div>
        
        <?php if (!empty($error_message)): ?>
            <div class="error-message"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>

        <form action="/KP/index.php" method="POST">
            <div class="input-group">
                <input type="text" id="username_or_email" name="username_or_email" placeholder=" " required>
                <label for="username_or_email">NIM / NIP / Email</label>
            </div>
            
            <div class="input-group">
                <input type="password" id="password_input" name="password_input" placeholder=" " required>
                <label for="password_input">Password</label>
            </div>
            
            <div class="input-group custom-select">
                <select id="role" name="role" required>
                    <option value="" disabled selected></option>
                    <option value="mahasiswa">👨‍🎓 Mahasiswa</option>
                    <option value="dosen">👨‍🏫 Dosen Pembimbing</option>
                    <option value="admin_prodi">👨‍💼 Admin Program Studi</option>
                    <option value="perusahaan">🏢 Perusahaan</option>
                </select>
                <label for="role">Login Sebagai</label>
            </div>
            
            <button type="submit" class="btn-submit">LOGIN</button>
        </form>

        <div class="register-link-container">
            Belum punya akun? <a href="/KP/register.php" class="register-link">Daftar di sini</a>
        </div>
    </div>

    <script>
        const card = document.getElementById('login-card');
        if (card) {
            document.body.addEventListener('mousemove', e => {
                const rect = card.getBoundingClientRect();
                const centerX = rect.left + rect.width / 2;
                const centerY = rect.top + rect.height / 2;
                const rotateX = -(e.clientY - centerY) / 40;
                const rotateY = (e.clientX - centerX) / 40;
                card.style.transform = `perspective(1500px) rotateX(${rotateX}deg) rotateY(${rotateY}deg) scale(1.02)`;
            });
            document.body.addEventListener('mouseleave', () => {
                card.style.transform = 'perspective(1500px) rotateX(0) rotateY(0) scale(1)';
            });
        }
    </script>
</body>
</html>
