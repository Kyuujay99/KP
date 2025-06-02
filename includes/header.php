<?php
// /KP/includes/header.php

// Pastikan session dimulai. Jika sudah dimulai di halaman pemanggil, ini tidak akan memulai ulang.
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Variabel untuk judul halaman default. Bisa di-override oleh halaman yang memanggil.
if (!isset($page_title)) {
    $page_title = "SIM Kerja Praktek";
}

// Variabel untuk menentukan path dasar (berguna jika file CSS/JS eksternal nanti)
// Untuk sekarang, kita asumsikan /KP/ adalah root proyek.
$base_url = "/KP"; // Sesuaikan jika nama folder proyekmu berbeda

// Cek apakah pengguna sudah login untuk menampilkan nama dan tombol logout
$is_logged_in = isset($_SESSION['user_id']);
$user_nama = $is_logged_in ? htmlspecialchars($_SESSION['user_nama']) : 'Tamu';
$user_role = $is_logged_in ? htmlspecialchars(ucfirst($_SESSION['user_role'])) : '';

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?> - SIM KP</title>

    <style>
        /* Reset CSS Sederhana & Global Styles */
        body, h1, h2, h3, h4, h5, h6, p, ul, ol, li, figure, figcaption, blockquote, dl, dd, form, fieldset, legend, input, textarea, button, select, table, th, td {
            margin: 0;
            padding: 0;
            box-sizing: border-box; /* Perhitungan box model yang lebih intuitif */
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            background-color: #f0f2f5; /* Warna latar yang sedikit lebih lembut */
            color: #333;
            display: flex;
            flex-direction: column;
            min-height: 100vh; /* Memastikan footer selalu di bawah jika konten pendek */
        }

        /* Styling untuk Header/Navbar */
        .navbar {
            background-color: #343a40; /* Warna gelap untuk navbar */
            color: #fff;
            padding: 0.8rem 2rem; /* Padding atas/bawah dan kiri/kanan */
            display: flex;
            justify-content: space-between; /* Brand di kiri, item lain di kanan */
            align-items: center; /* Vertikal align item di tengah */
            box-shadow: 0 2px 4px rgba(0,0,0,0.1); /* Shadow halus di bawah navbar */
        }

        .navbar-brand {
            font-size: 1.6rem; /* Ukuran font brand */
            color: #fff;
            text-decoration: none;
            font-weight: bold;
        }
        .navbar-brand:hover {
            color: #f8f9fa;
        }

        .navbar-nav {
            list-style: none; /* Hilangkan bullet points dari list */
            display: flex; /* Susun item navigasi secara horizontal */
            align-items: center;
        }

        .nav-item {
            margin-left: 1rem; /* Jarak antar item navigasi */
        }

        .nav-link {
            color: #adb5bd; /* Warna link navigasi (abu-abu muda) */
            text-decoration: none;
            padding: 0.5rem 0.75rem;
            border-radius: 4px;
            transition: color 0.15s ease-in-out, background-color 0.15s ease-in-out;
        }
        .nav-link:hover,
        .nav-link.active { /* Kelas 'active' bisa ditambahkan via PHP jika link sesuai halaman saat ini */
            color: #fff;
            background-color: #495057; /* Warna latar saat hover atau aktif */
        }

        .user-info {
            color: #ced4da; /* Warna untuk info pengguna */
            font-size: 0.9rem;
            margin-right: 0.5rem; /* Jarak ke tombol logout */
        }
        .user-info strong {
            color: #f8f9fa; /* Nama pengguna lebih terang */
        }

        .btn { /* Tombol umum, bisa dipakai di banyak tempat */
            display: inline-block;
            padding: 0.375rem 0.75rem;
            font-size: 1rem;
            font-weight: 400;
            line-height: 1.5;
            text-align: center;
            text-decoration: none;
            vertical-align: middle;
            cursor: pointer;
            user-select: none; /* Agar teks tombol tidak bisa dipilih */
            border: 1px solid transparent;
            border-radius: 0.25rem;
            transition: color .15s ease-in-out,background-color .15s ease-in-out,border-color .15s ease-in-out,box-shadow .15s ease-in-out;
        }
        .btn-logout {
            color: #fff;
            background-color: #dc3545; /* Warna merah untuk logout */
            border-color: #dc3545;
        }
        .btn-logout:hover {
            background-color: #c82333;
            border-color: #bd2130;
        }
        .btn-login {
            color: #fff;
            background-color: #007bff; /* Warna biru untuk login */
            border-color: #007bff;
        }
        .btn-login:hover {
            background-color: #0069d9;
            border-color: #0062cc;
        }


        /* Styling untuk Kontainer Utama Konten */
        .main-container {
            width: 100%;
            max-width: 1200px; /* Lebar maksimum konten */
            margin: 20px auto; /* Posisi tengah dan jarak atas/bawah */
            padding: 20px;
            background-color: transparent; /* Latar transparan, karena body sudah punya warna */
            flex-grow: 1; /* Membuat kontainer tumbuh mengisi sisa ruang (penting untuk footer di bawah) */
            box-sizing: border-box;
        }

        /* Umum untuk form dan tabel yang mungkin dipakai di banyak halaman */
        /* Tombol dari contoh sebelumnya */
        .btn-info { color: #fff; background-color: #17a2b8; border-color: #17a2b8; }
        .btn-info:hover { background-color: #138496; border-color: #117a8b; }
        .btn-sm { padding: 0.25rem 0.5rem; font-size: 0.875rem; line-height: 1.5; border-radius: 0.2rem; }

        hr { border: 0; height: 1px; background-color: #eee; margin: 20px 0; }
    </style>
    </head>
<body>

    <nav class="navbar">
        <a href="<?php echo $base_url; ?>/index.php" class="navbar-brand">SIM KP</a>
        <ul class="navbar-nav">
            <?php if ($is_logged_in): ?>
                <li class="nav-item user-info">
                    Halo, <strong><?php echo $user_nama; ?></strong> (<?php echo $user_role; ?>)
                </li>
                <?php
                // Navigasi dashboard berdasarkan peran
                if ($_SESSION['user_role'] == 'mahasiswa') {
                    echo '<li class="nav-item"><a href="' . $base_url . '/mahasiswa/dashboard.php" class="nav-link">Dashboard</a></li>';
                } elseif ($_SESSION['user_role'] == 'dosen') {
                    echo '<li class="nav-item"><a href="' . $base_url . '/dosen/dashboard.php" class="nav-link">Dashboard</a></li>';
                } elseif ($_SESSION['user_role'] == 'admin_prodi') {
                    echo '<li class="nav-item"><a href="' . $base_url . '/admin_prodi/dashboard.php" class="nav-link">Dashboard</a></li>';
                } elseif ($_SESSION['user_role'] == 'perusahaan') {
                    echo '<li class="nav-item"><a href="' . $base_url . '/perusahaan/dashboard.php" class="nav-link">Dashboard</a></li>';
                }
                // Tambahkan link lain yang umum untuk semua user yang login, misal Profil
                // Contoh: echo '<li class="nav-item"><a href="' . $base_url . '/' . $_SESSION['user_role'] . '/profil.php" class="nav-link">Profil Saya</a></li>';
                ?>
                <li class="nav-item">
                    <a href="<?php echo $base_url; ?>/logout.php" class="nav-link btn btn-logout">Logout</a>
                </li>
            <?php else: ?>
                <li class="nav-item">
                    <a href="<?php echo $base_url; ?>/index.php" class="nav-link">Login</a>
                </li>
                <li class="nav-item">
                    <a href="<?php echo $base_url; ?>/register.php" class="nav-link">Register</a>
                </li>
            <?php endif; ?>
        </ul>
    </nav>

    <div class="main-container">
        ```

**Penjelasan Detail `includes/header.php`:**

1.  **`session_start()`**: Penting untuk mengakses variabel `$_SESSION`.
2.  **`$page_title`**: Variabel ini memungkinkan setiap halaman yang memanggil `header.php` untuk menentukan judulnya sendiri. Jika tidak diset, judul default akan digunakan.
    * Cara penggunaan di halaman pemanggil (misalnya di `mahasiswa/dashboard.php` sebelum `require_once '../includes/header.php';`):
        ```php
        <?php
        $page_title = "Dashboard Mahasiswa"; // Set judul spesifik
        require_once '../includes/header.php';
        ?>
        ```
3.  **`$base_url`**: Berguna untuk membuat path URL yang konsisten ke aset atau halaman lain.
4.  **Pengecekan Login (`$is_logged_in`, `$user_nama`, `$user_role`)**: Mengambil data sesi untuk personalisasi navbar.
5.  **Struktur HTML Dasar**: `<!DOCTYPE html>`, `<html>`, `<head>`, dan awal `<body>`.
6.  **Meta Tags**: `charset` dan `viewport` adalah standar penting.
7.  **CSS Disematkan**:
    * **Reset Sederhana**: Menghilangkan margin/padding default browser agar tampilan lebih konsisten.
    * **Global Styles**: Styling dasar untuk `body`.
    * **Navbar Styles**: Pengaturan tampilan untuk bar navigasi, brand, link, dan info pengguna.
    * **Main Container Styles**: Mengatur layout dasar untuk area konten utama.
8.  **Navbar Dinamis**:
    * Logo/Brand "SIM KP" mengarah ke `index.php`.
    * Jika pengguna **sudah login** (`$is_logged_in` bernilai `true`):
        * Menampilkan pesan "Halo, [Nama Pengguna] ([Peran])".
        * Menampilkan link "Dashboard" yang mengarah ke dashboard sesuai peran pengguna.
        * Menampilkan tombol/link "Logout" yang mengarah ke `logout.php`.
    * Jika pengguna **belum login**:
        * Menampilkan link "Login" dan "Register".
9.  **`<div class="main-container">`**: Ini adalah `div` pembungkus yang akan berisi konten unik dari setiap halaman (misalnya, konten dari `mahasiswa/dashboard.php`). `flex-grow: 1;` pada `body` dan `main-container` membantu footer menempel di bawah jika kontennya pendek.

**2. Kode Detail untuk `includes/footer.php`**

File ini akan sangat sederhana, hanya berisi penutup tag dan mungkin copyright.

```php
<?php
// /KP/includes/footer.php

$current_year = date("Y"); // Mendapatkan tahun saat ini secara dinamis
?>

        </div> <footer style="background-color: #343a40; color: #adb5bd; text-align: center; padding: 1.5rem 1rem; margin-top: auto; /* Mendorong footer ke bawah */ font-size: 0.9em; box-shadow: 0 -2px 4px rgba(0,0,0,0.1);">
        <p>&copy; <?php echo $current_year; ?> Sistem Informasi Manajemen Kerja Praktek. All Rights Reserved.</p>
        </footer>

    </body>
</html>