<?php
// /KP/index.php
// Mulai session di paling atas halaman. Session digunakan untuk menyimpan informasi login pengguna.
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
        $conn->close(); // Tutup koneksi database
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
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f0f2f5; /* Warna latar belakang sedikit berbeda */
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh; /* Tinggi penuh viewport */
            margin: 0;
        }
        .login-wrapper {
            display: flex; /* Untuk layout jika ingin ada gambar/info di samping form */
            justify-content: center;
            align-items: center;
            width: 100%;
        }
        .login-container {
            background-color: #ffffff;
            padding: 35px 30px; /* Padding sedikit lebih besar */
            border-radius: 10px; /* Border radius lebih besar */
            box-shadow: 0 4px 12px rgba(0,0,0,0.15); /* Shadow lebih jelas */
            width: 100%;
            max-width: 380px; /* Lebar maksimum form */
            box-sizing: border-box;
        }
        .login-container h1 {
            text-align: center;
            color: #1c1e21; /* Warna judul lebih gelap */
            font-size: 24px; /* Ukuran font judul */
            margin-top: 0;
            margin-bottom: 25px; /* Jarak bawah judul */
            font-weight: 600;
        }
        .login-container label {
            display: block;
            margin-bottom: 8px;
            color: #4b4f56; /* Warna label */
            font-size: 14px; /* Ukuran font label */
            font-weight: 600;
        }
        .login-container input[type="text"],
        .login-container input[type="password"],
        .login-container select {
            width: 100%;
            padding: 12px 15px; /* Padding input lebih besar */
            margin-bottom: 18px; /* Jarak bawah input */
            border: 1px solid #ccd0d5; /* Warna border input */
            border-radius: 6px; /* Border radius input */
            box-sizing: border-box;
            font-size: 16px; /* Ukuran font input */
            transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
        }
        .login-container input[type="text"]:focus,
        .login-container input[type="password"]:focus,
        .login-container select:focus {
            border-color: #007bff; /* Warna border saat fokus */
            outline: 0;
            box-shadow: 0 0 0 0.2rem rgba(0,123,255,.25); /* Efek shadow saat fokus */
        }
        .login-container button[type="submit"] {
            width: 100%;
            padding: 12px;
            background-color: #007bff; /* Warna tombol utama (biru) */
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 16px;
            font-weight: bold;
            transition: background-color 0.15s ease-in-out;
        }
        .login-container button[type="submit"]:hover {
            background-color: #0056b3; /* Warna tombol saat hover */
        }
        .error-message {
            color: #dc3545; /* Warna merah untuk error */
            background-color: #f8d7da; /* Latar belakang error */
            border: 1px solid #f5c6cb; /* Border error */
            padding: 10px 15px;
            margin-bottom: 18px;
            border-radius: 6px;
            text-align: center;
            font-size: 14px;
        }
        .register-link-container {
            text-align: center;
            margin-top: 20px; /* Jarak atas link register */
            padding-top: 20px;
            border-top: 1px solid #dadde1; /* Garis pemisah */
        }
        .register-link {
            color: #007bff;
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
        }
        .register-link:hover {
            text-decoration: underline;
        }
        .input-group {
            margin-bottom: 18px;
        }
    </style>
</head>
<body>
    <div class="login-wrapper">
        <div class="login-container">
            <h1>SIM Kerja Praktek</h1> <?php if (!empty($error_message)): ?>
                <div class="error-message">
                    <?php echo htmlspecialchars($error_message); // htmlspecialchars untuk keamanan dasar ?>
                </div>
            <?php endif; ?>

            <form action="/KP/index.php" method="POST">
                <div class="input-group">
                    <label for="username_or_email">NIM/NIP/Username/Email:</label>
                    <input type="text" id="username_or_email" name="username_or_email" value="<?php echo isset($_POST['username_or_email']) ? htmlspecialchars($_POST['username_or_email']) : ''; ?>" required>
                </div>
                <div class="input-group">
                    <label for="password_input">Password:</label>
                    <input type="password" id="password_input" name="password_input" required>
                </div>
                <div class="input-group">
                    <label for="role">Login sebagai:</label>
                    <select id="role" name="role" required>
                        <option value="">-- Pilih Peran --</option>
                        <option value="mahasiswa" <?php echo (isset($_POST['role']) && $_POST['role'] == 'mahasiswa') ? 'selected' : ''; ?>>Mahasiswa</option>
                        <option value="dosen" <?php echo (isset($_POST['role']) && $_POST['role'] == 'dosen') ? 'selected' : ''; ?>>Dosen</option>
                        <option value="admin_prodi" <?php echo (isset($_POST['role']) && $_POST['role'] == 'admin_prodi') ? 'selected' : ''; ?>>Admin Prodi</option>
                        <option value="perusahaan" <?php echo (isset($_POST['role']) && $_POST['role'] == 'perusahaan') ? 'selected' : ''; ?>>Perusahaan</option>
                    </select>
                </div>
                <button type="submit">Login</button>
            </form>

            <div class="register-link-container">
                <a href="/KP/register.php" class="register-link">Belum punya akun? Daftar di sini</a>
            </div>
        </div>
    </div>
</body>
</html>