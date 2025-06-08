<?php
// /KP/index.php (Versi Epik dengan Glassmorphism)

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// 1. PENGECEKAN APAKAH PENGGUNA SUDAH LOGIN
// Jika pengguna sudah memiliki session (sudah login),
// maka langsung arahkan (redirect) ke dashboard sesuai perannya.
if (isset($_SESSION['user_id']) && isset($_SESSION['user_role'])) {
    if ($_SESSION['user_role'] == 'mahasiswa') {
        header("Location: /KP/mahasiswa/dashboard.php"); // Path lengkap ke dashboard mahasiswa
        exit(); // Pastikan keluar dari script setelah redirect
    } elseif ($_SESSION['user_role'] == 'dosen') {
        header("Location: /KP/dosen/dashboard.php"); // Path lengkap ke dashboard dosen
        exit();
    } elseif ($_SESSION['user_role'] == 'admin_prodi') {
        header("Location: /KP/admin_prodi/dashboard.php"); // Path lengkap ke dashboard admin
        exit();
    } elseif ($_SESSION['user_role'] == 'perusahaan') {
        header("Location: /KP/perusahaan/dashboard.php"); // Path lengkap ke dashboard perusahaan
        exit();
    }
    // Tambahkan peran lain jika ada
}

// Variabel untuk menyimpan pesan error jika login gagal
$error_message = '';

// 2. PROSES LOGIN KETIKA FORM DISUBMIT (METHOD POST)
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Sertakan file koneksi database. Pastikan path-nya benar.
    // File ini berisi variabel $conn untuk koneksi ke MySQL.
    require_once 'config/db_connect.php';

    // Ambil data dari form login yang dikirim melalui metode POST
    $username_or_email = $_POST['username_or_email'];
    $password_input = $_POST['password_input']; // Password yang dimasukkan pengguna
    $role = $_POST['role']; // Peran yang dipilih pengguna (mahasiswa, dosen, dll.)

    $sql = ""; // Variabel untuk menyimpan query SQL
    $user_id_column = ""; // Nama kolom untuk ID unik pengguna (NIM, NIP, username)
    $nama_column = ""; // Nama kolom untuk nama pengguna
    $password_column = ""; // Nama kolom untuk password di database
    $table_name = ""; // Nama tabel database
    $redirect_path = ""; // Path tujuan setelah login berhasil

    // Tentukan query dan kolom berdasarkan peran yang dipilih
    switch ($role) {
        case "mahasiswa":
            $table_name = "mahasiswa";
            $user_id_column = "nim"; // Mahasiswa login dengan NIM atau Email
            $nama_column = "nama";
            $password_column = "password";
            // Cek juga status akun mahasiswa, misalnya hanya yang 'active' atau 'pending_verification' yang boleh coba login
            $sql = "SELECT $user_id_column, $nama_column, $password_column, status_akun FROM $table_name WHERE (nim = ? OR email = ?) LIMIT 1";
            $redirect_path = "/KP/mahasiswa/dashboard.php";
            break;
        case "dosen":
            $table_name = "dosen_pembimbing";
            $user_id_column = "nip"; // Dosen login dengan NIP atau Email
            $nama_column = "nama_dosen";
            $password_column = "password";
            // Cek status akun dosen, misalnya hanya yang 'active'
            $sql = "SELECT $user_id_column, $nama_column, $password_column, status_akun FROM $table_name WHERE (nip = ? OR email = ?) AND status_akun = 'active' LIMIT 1";
            $redirect_path = "/KP/dosen/dashboard.php";
            break;
        case "admin_prodi":
            $table_name = "admin_prodi";
            $user_id_column = "username"; // Admin login dengan username atau Email
            $nama_column = "nama_admin";
            $password_column = "password";
            $sql = "SELECT id_admin, $user_id_column, $nama_column, $password_column FROM $table_name WHERE (username = ? OR email_admin = ?) LIMIT 1";
            // Untuk admin, mungkin user_id_column adalah id_admin jika username bisa berubah. Kita pakai username untuk session user_id agar konsisten dengan input.
            // Jika ingin pakai id_admin sebagai $_SESSION['user_id'], sesuaikan.
            $_SESSION['user_id_col_db'] = 'id_admin'; // Kolom aktual di DB untuk ID jika beda
            $redirect_path = "/KP/admin_prodi/dashboard.php";
            break;
        case "perusahaan":
            $table_name = "perusahaan";
            $user_id_column = "email_perusahaan"; // Perusahaan login dengan email
            $nama_column = "nama_perusahaan";
            $password_column = "password_perusahaan";
            // Cek status akun perusahaan, misalnya hanya yang 'active'
            $sql = "SELECT id_perusahaan, $user_id_column, $nama_column, $password_column, status_akun FROM $table_name WHERE $user_id_column = ? AND status_akun = 'active' LIMIT 1";
            $_SESSION['user_id_col_db'] = 'id_perusahaan'; // Kolom aktual di DB untuk ID
            $redirect_path = "/KP/perusahaan/dashboard.php";
            break;
        default:
            $error_message = "Peran tidak valid.";
            break;
    }

    if (!empty($sql) && $conn) { // Pastikan $sql tidak kosong dan koneksi $conn ada
        // Gunakan prepared statement untuk keamanan dasar dari SQL Injection
        $stmt = $conn->prepare($sql);

        if ($stmt) {
            if ($role == "perusahaan") { // Perusahaan hanya login dengan email_perusahaan (satu parameter)
                 $stmt->bind_param("s", $username_or_email);
            } else { // Mahasiswa, Dosen, Admin Prodi bisa login dengan id/username atau email (dua parameter untuk klausa WHERE)
                 $stmt->bind_param("ss", $username_or_email, $username_or_email);
            }
            $stmt->execute();
            $result = $stmt->get_result(); // Ambil hasil query

            if ($result->num_rows == 1) { // Jika pengguna ditemukan (satu baris hasil)
                $user = $result->fetch_assoc(); // Ambil data pengguna sebagai array asosiatif

                // PENTING: Perbandingan password secara langsung (plain text)
                // Ini dilakukan sesuai permintaan untuk kesederhanaan, BUKAN praktik yang aman.
                if ($password_input === $user[$password_column]) {
                    // Jika password cocok

                    // Untuk mahasiswa, cek apakah status_akun 'suspended'
                    if ($role == 'mahasiswa' && $user['status_akun'] == 'suspended') {
                        $error_message = "Akun mahasiswa Anda telah ditangguhkan. Hubungi admin.";
                    } else {
                        // Simpan informasi pengguna ke dalam session
                        // Jika user_id_col_db diset (untuk admin/perusahaan), gunakan itu untuk mengambil ID dari DB
                        // Jika tidak, gunakan user_id_column yang juga merupakan input login (NIM/NIP/Username)
                        if (isset($_SESSION['user_id_col_db'])) {
                             $_SESSION['user_id'] = $user[$_SESSION['user_id_col_db']];
                             unset($_SESSION['user_id_col_db']); // Hapus setelah dipakai
                        } else {
                             $_SESSION['user_id'] = $user[$user_id_column];
                        }
                        $_SESSION['user_identifier_login'] = $username_or_email; // Simpan identifier yg dipakai login
                        $_SESSION['user_nama'] = $user[$nama_column];
                        $_SESSION['user_role'] = $role;

                        // Arahkan ke dashboard yang sesuai
                        header("Location: " . $redirect_path);
                        exit();
                    }
                } else {
                    // Jika password tidak cocok
                    $error_message = "Password yang Anda masukkan salah.";
                }
            } else {
                // Jika pengguna tidak ditemukan dengan username/email tersebut atau status tidak sesuai
                if ($role == "dosen" || $role == "perusahaan") {
                    $error_message = "Kombinasi NIP/Email dan Password salah, atau akun belum aktif.";
                } else {
                    $error_message = "Kombinasi NIM/Username/Email dan Password salah.";
                }
            }
            $stmt->close(); // Tutup statement
        } else {
            // Jika prepared statement gagal
            $error_message = "Terjadi kesalahan dalam sistem. Silakan coba lagi. (Error: Statement)";
            // error_log("Statement preparation failed: " . $conn->error); // Catat error untuk admin
        }
        // Pastikan $conn ada dan merupakan objek mysqli sebelum ditutup
        if (isset($conn) && $conn instanceof mysqli) {
            $conn->close(); // Tutup koneksi database
        }
    } elseif (!$conn && !empty($sql)) {
        $error_message = "Tidak dapat terhubung ke database.";
    }
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
            --primary-color: #6a11cb; /* Warna Ungu */
            --secondary-color: #2575fc; /* Warna Biru */
            --dark-color: #1a1a2e;
            --light-color: #f4f7f9;
            --text-color: #5a5a5a;
            --border-radius: 15px;
        }
        body {
            font-family: 'Poppins', sans-serif;
            margin: 0;
            height: 100vh;
            overflow: hidden;
            display: flex;
            justify-content: center;
            align-items: center;
            /* PERBAIKAN: Latar belakang lebih berwarna dan beranimasi */
            background: linear-gradient(-45deg, #6a11cb, #2575fc, #ec008c, #fc6767);
            background-size: 400% 400%;
            animation: gradientBG 18s ease infinite;
        }
        
        /* Animasi Latar Belakang & Partikel */
        .background-shapes { position: absolute; top: 0; left: 0; width: 100%; height: 100%; z-index: 1; }
        .shape {
            position: absolute; list-style: none; display: block;
            background: rgba(255, 255, 255, 0.1);
            animation: moveShape 25s linear infinite;
            bottom: -200px;
        }
        /* --- ANIMASI LATAR BELAKANG --- */
        .background-shapes { position: absolute; top: 0; left: 0; width: 100%; height: 100%; z-index: 1; }
        .shape {
            position: absolute;
            list-style: none;
            display: block;
            background: rgba(255, 255, 255, 0.15);
            animation: moveShape 20s linear infinite;
            bottom: -150px; /* Mulai dari bawah layar */
        }
        .shape.s1 { left: 10%; width: 80px; height: 80px; animation-delay: 0s; }
        .shape.s2 { left: 20%; width: 30px; height: 30px; animation-delay: 2s; animation-duration: 17s; }
        .shape.s3 { left: 25%; width: 100px; height: 100px; animation-delay: 4s; }
        .shape.s4 { left: 40%; width: 60px; height: 60px; animation-delay: 0s; animation-duration: 22s; }
        .shape.s5 { left: 65%; width: 20px; height: 20px; animation-delay: 0s; }
        .shape.s6 { left: 75%; width: 110px; height: 110px; animation-delay: 3s; }
        .shape.s7 { left: 90%; width: 150px; height: 150px; animation-delay: 7s; }

        @keyframes moveShape {
            0% { transform: translateY(0) rotate(0deg); opacity: 1; border-radius: 20%; }
            100% { transform: translateY(-120vh) rotate(720deg); opacity: 0; border-radius: 50%; }
        }
        @keyframes gradientBG {
            0% { background-position: 0% 50%; } 50% { background-position: 100% 50%; } 100% { background-position: 0% 50%; }
        }

        /* --- KARTU KACA (GLASSMORPHISM) --- */
        .glass-card {
            position: relative;
            z-index: 2;
            width: 100%;
            max-width: 420px;
            padding: 3rem;
            background: rgba(255, 255, 255, 0.25);
            backdrop-filter: blur(15px);
            -webkit-backdrop-filter: blur(15px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.15);
            animation: fadeInCard 1s ease-out;
            transition: transform 0.2s ease;
        }
        
        
        /* Konten Formulir */
        .form-header h1 { font-size: 2.2rem; font-weight: 700; color: #fff; text-align: center; text-shadow: 0 2px 5px rgba(0,0,0,0.2); }
        .form-header p { text-align: center; color: rgba(255,255,255,0.8); margin: -10px 0 2rem 0; }
        
        .input-group { position: relative; margin-bottom: 1.5rem; }
        .input-group label {
            position: absolute; left: 15px; top: 14px; font-size: 1rem;
            color: rgba(255,255,255,0.6); pointer-events: none; transition: all 0.3s ease;
        }
        .input-group input, .input-group select {
            width: 100%; padding: 14px; background: transparent;
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 8px; font-size: 1rem; color: #fff;
        }
        .input-group select { appearance: none; -webkit-appearance: none; }
        .input-group select option { color: #000; }
        
        /* PERBAIKAN: CSS untuk floating label yang lebih baik */
        .input-group input:focus, .input-group select:focus { outline: none; background: rgba(255,255,255,0.2); border-color: rgba(255,255,255,0.8); }
        .input-group input:focus + label, .input-group input:not(:placeholder-shown) + label,
        .input-group select:focus + label, .input-group select:valid + label {
            top: -10px; left: 10px; font-size: 0.8em;
            background: #393e60; padding: 0 5px; color: #fff;
        }

        /* PERBAIKAN: Tombol Login lebih menarik */
        .btn-submit {
            width: 100%; padding: 15px; background-size: 200% auto;
            background-image: linear-gradient(to right, #2575fc 0%, #6a11cb 51%, #2575fc 100%);
            color: white; border: none; border-radius: 8px; cursor: pointer;
            font-size: 1.1rem; font-weight: 600; transition: all 0.5s ease;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        .btn-submit:hover {
            background-position: right center; /* Menggerakkan gradien */
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.3);
        }
        
        .register-link-container { text-align: center; margin-top: 1.5rem; color: rgba(255,255,255,0.8); }
        .register-link { color: #fff; font-weight: 600; }
    </style>
</head>
<body>
    <ul class="background-shapes">
        <?php for ($i = 1; $i <= 7; $i++) echo "<li class='shape s$i'></li>"; ?>
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
            <div class="input-group">
                <select id="role" name="role" required>
                    <option value="" disabled selected></option>
                    <option value="mahasiswa">Mahasiswa</option>
                    <option value="dosen">Dosen</option>
                    <option value="admin_prodi">Admin Prodi</option>
                    <option value="perusahaan">Perusahaan</option>
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
            const cardRect = card.getBoundingClientRect();
            const cardCenterX = cardRect.left + cardRect.width / 2;
            const cardCenterY = cardRect.top + cardRect.height / 2;

            document.body.addEventListener('mousemove', e => {
                const rotateX = -(e.clientY - cardCenterY) / 30; // Pembagi lebih besar untuk efek lebih halus
                const rotateY = (e.clientX - cardCenterX) / 30;
                card.style.transform = `perspective(1500px) rotateX(${rotateX}deg) rotateY(${rotateY}deg) scale(1.05)`;
            });

            document.body.addEventListener('mouseleave', () => {
                card.style.transform = 'perspective(1500px) rotateX(0) rotateY(0) scale(1)';
            });
        }
    </script>
</body>
</html>