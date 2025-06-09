<?php
// /KP/admin_prodi/perusahaan_tambah.php (Versi Final & Ditingkatkan)

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once '../includes/auth_check.php';
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin_prodi') {
    header("Location: /KP/index.php?error=unauthorized_admin");
    exit();
}
require_once '../config/db_connect.php';

$error_message = '';
$success_message = '';
// Variabel untuk mempertahankan input jika ada error
$input_values = [
    'nama_perusahaan' => '', 'email_perusahaan' => '', 'alamat' => '',
    'bidang' => '', 'kontak_person_nama' => '', 'kontak_person_email' => '',
    'kontak_person_no_hp' => '', 'status_akun' => 'pending_approval'
];

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_tambah_perusahaan'])) {
    // Simpan semua input ke dalam array untuk repopulate form jika error
    foreach ($input_values as $key => $value) {
        if (isset($_POST[$key])) {
            $input_values[$key] = $_POST[$key];
        }
    }

    // Ambil dan sanitasi data dari form
    $nama_perusahaan = trim($_POST['nama_perusahaan']);
    $email_perusahaan = trim($_POST['email_perusahaan']);
    $alamat = trim($_POST['alamat']);
    $bidang = trim($_POST['bidang']);
    $kontak_nama = trim($_POST['kontak_person_nama']);
    $kontak_email = trim($_POST['kontak_person_email']);
    $kontak_no_hp = trim($_POST['kontak_person_no_hp']);
    $status_akun = $_POST['status_akun'];
    $password = $_POST['password_perusahaan'];
    $confirm_password = $_POST['confirm_password_perusahaan'];

    // Validasi data
    if (empty($nama_perusahaan) || empty($email_perusahaan) || empty($password) || empty($confirm_password)) {
        $error_message = "Nama Perusahaan, Email Login, dan Password wajib diisi.";
    } elseif ($password !== $confirm_password) {
        $error_message = "Password dan Konfirmasi Password tidak cocok.";
    } elseif (strlen($password) < 6) {
        $error_message = "Password minimal harus 6 karakter.";
    } elseif (!filter_var($email_perusahaan, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Format Email Login Perusahaan tidak valid.";
    } elseif (!empty($kontak_email) && !filter_var($kontak_email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Format Email Kontak Person tidak valid.";
    }

    if (empty($error_message)) {
        if ($conn) {
            // Cek apakah email sudah ada
            $stmt_check = $conn->prepare("SELECT id_perusahaan FROM perusahaan WHERE email_perusahaan = ?");
            $stmt_check->bind_param("s", $email_perusahaan);
            $stmt_check->execute();
            $stmt_check->store_result();

            if ($stmt_check->num_rows > 0) {
                $error_message = "Email '" . htmlspecialchars($email_perusahaan) . "' sudah terdaftar untuk perusahaan lain.";
            } else {
                // Gunakan prepared statement untuk INSERT
                $sql_insert = "INSERT INTO perusahaan (nama_perusahaan, email_perusahaan, password_perusahaan, alamat, bidang, kontak_person_nama, kontak_person_email, kontak_person_no_hp, status_akun) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt_insert = $conn->prepare($sql_insert);
                // SIMPAN PASSWORD SEBAGAI PLAIN TEXT SESUAI KEBUTUHAN SISTEM.
                // UNTUK PRODUKSI, SEBAIKNYA GUNAKAN HASHING: password_hash($password, PASSWORD_DEFAULT)
                $stmt_insert->bind_param("sssssssss", $nama_perusahaan, $email_perusahaan, $password, $alamat, $bidang, $kontak_nama, $kontak_email, $kontak_no_hp, $status_akun);
                
                if ($stmt_insert->execute()) {
                    $success_message = "Perusahaan '" . htmlspecialchars($nama_perusahaan) . "' berhasil ditambahkan.";
                    // Kosongkan form setelah sukses
                    foreach ($input_values as &$value) {
                        $value = '';
                    }
                    $input_values['status_akun'] = 'pending_approval';
                } else {
                    $error_message = "Gagal menambahkan perusahaan: " . $stmt_insert->error;
                }
                $stmt_insert->close();
            }
            $stmt_check->close();
        } else {
            $error_message = "Koneksi database gagal.";
        }
    }
}

$opsi_status_akun_perusahaan = [
    'pending_approval' => 'Pending Approval',
    'active' => 'Active',
    'inactive' => 'Inactive'
];

$page_title = "Tambah Data Perusahaan Mitra";
require_once '../includes/header.php';
?>

<!-- KONTENER BARU UNTUK TAMPILAN MODERN -->
<div class="kp-form-container">
    <div class="form-hero-section">
        <div class="form-hero-content">
            <div class="form-hero-icon">
                <svg viewBox="0 0 24 24"><path d="M3 21h18M5 21V7l8-4v18M19 21V11l-6-4"></path></svg>
            </div>
            <h1><?php echo htmlspecialchars($page_title); ?></h1>
            <p>Isi formulir untuk menambahkan data perusahaan mitra baru ke dalam sistem.</p>
        </div>
    </div>

    <div class="form-wrapper">
        <a href="perusahaan_kelola.php" class="back-link">&larr; Kembali ke Daftar Perusahaan</a>
        
        <?php if (!empty($success_message)): ?>
            <div class="message success"><h4>Berhasil!</h4><p><?php echo htmlspecialchars($success_message); ?></p></div>
        <?php endif; ?>
        <?php if (!empty($error_message)): ?>
            <div class="message error"><p><?php echo htmlspecialchars($error_message); ?></p></div>
        <?php endif; ?>

        <?php if (!$success_message): ?>
            <form action="perusahaan_tambah.php" method="POST" class="modern-form">
                <div class="form-step">
                    <div class="form-step-header">
                        <div class="form-step-icon"><svg viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg></div>
                        <h3>Informasi Utama & Akun Perusahaan</h3>
                    </div>
                    <div class="form-group"><label for="nama_perusahaan">Nama Perusahaan (*)</label><input type="text" id="nama_perusahaan" name="nama_perusahaan" value="<?php echo htmlspecialchars($input_values['nama_perusahaan']); ?>" required></div>
                    <div class="form-grid"><div class="form-group"><label for="email_perusahaan">Email Login (*)</label><input type="email" id="email_perusahaan" name="email_perusahaan" value="<?php echo htmlspecialchars($input_values['email_perusahaan']); ?>" required></div><div class="form-group"><label for="bidang">Bidang Usaha</label><input type="text" id="bidang" name="bidang" value="<?php echo htmlspecialchars($input_values['bidang']); ?>"></div></div>
                    <div class="form-group"><label for="alamat">Alamat Lengkap</label><textarea id="alamat" name="alamat" rows="3"><?php echo htmlspecialchars($input_values['alamat']); ?></textarea></div>
                    <div class="form-grid"><div class="form-group"><label for="password_perusahaan">Password Akun (*)</label><input type="password" id="password_perusahaan" name="password_perusahaan" required minlength="6"></div><div class="form-group"><label for="confirm_password_perusahaan">Konfirmasi Password (*)</label><input type="password" id="confirm_password_perusahaan" name="confirm_password_perusahaan" required minlength="6"></div></div>
                </div>

                <div class="form-step">
                    <div class="form-step-header"><div class="form-step-icon"><svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg></div><h3>Informasi Kontak Person (PIC)</h3></div>
                    <div class="form-grid"><div class="form-group"><label for="kontak_person_nama">Nama Kontak Person</label><input type="text" id="kontak_person_nama" name="kontak_person_nama" value="<?php echo htmlspecialchars($input_values['kontak_person_nama']); ?>"></div><div class="form-group"><label for="kontak_person_no_hp">No. HP Kontak Person</label><input type="text" id="kontak_person_no_hp" name="kontak_person_no_hp" value="<?php echo htmlspecialchars($input_values['kontak_person_no_hp']); ?>"></div></div>
                    <div class="form-group"><label for="kontak_person_email">Email Kontak Person</label><input type="email" id="kontak_person_email" name="kontak_person_email" value="<?php echo htmlspecialchars($input_values['kontak_person_email']); ?>"></div>
                </div>
                
                <div class="form-step">
                    <div class="form-step-header"><div class="form-step-icon"><svg viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-6h2v6zm0-8h-2V7h2v2z"></path></svg></div><h3>Status Akun</h3></div>
                    <div class="form-group"><label for="status_akun">Status Akun Awal (*)</label><select id="status_akun" name="status_akun" required><?php foreach ($opsi_status_akun_perusahaan as $value => $text): ?><option value="<?php echo $value; ?>" <?php echo ($input_values['status_akun'] == $value) ? 'selected' : ''; ?>><?php echo htmlspecialchars($text); ?></option><?php endforeach; ?></select><small>Pilih 'Pending Approval' jika akun perlu diverifikasi setelah dibuat, atau 'Active' jika langsung dapat digunakan.</small></div>
                </div>

                <div class="form-actions"><button type="submit" name="submit_tambah_perusahaan" class="btn-submit"><svg viewBox="0 0 24 24"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path><polyline points="17 21 17 13 7 13 7 21"></polyline><polyline points="7 3 7 8 15 8"></polyline></svg> Tambah Perusahaan</button></div>
            </form>
        <?php endif; ?>
    </div>
</div>

<style>
/* GAYA BARU YANG TERISOLASI DALAM KONTENER */
.kp-form-container {
    --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    --text-primary: #1f2937; --text-secondary: #6b7280;
    --bg-light: #f9fafb; --border-color: #e5e7eb;
    --card-shadow: 0 10px 30px rgba(0,0,0,0.07);
    --border-radius: 16px;
    font-family: 'Inter', sans-serif;
    color: var(--text-primary);
    max-width: 900px; margin: 0 auto; padding: 2rem 1rem;
}
.kp-form-container svg { stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; fill: none; stroke: currentColor; }
.kp-form-container .form-hero-section {
    padding: 3rem 2rem; background: var(--primary-gradient);
    border-radius: var(--border-radius); margin-bottom: 2rem;
    color: white; text-align: center;
}
.kp-form-container .form-hero-content { max-width: 600px; margin: 0 auto; }
.kp-form-container .form-hero-icon {
    width: 60px; height: 60px; background: rgba(255,255,255,0.1);
    border-radius: 50%; display: flex; align-items: center; justify-content: center;
    margin: 0 auto 1.5rem;
}
.kp-form-container .form-hero-icon svg { width: 28px; height: 28px; stroke: white; }
.kp-form-container .form-hero-section h1 { font-size: 2.5rem; font-weight: 700; margin-bottom: 0.5rem; }
.kp-form-container .form-hero-section p { font-size: 1.1rem; opacity: 0.9; font-weight: 300; }
.kp-form-container .form-wrapper {
    background-color: #ffffff; padding: 2.5rem;
    border-radius: var(--border-radius); box-shadow: var(--card-shadow);
}
.kp-form-container .back-link {
    text-decoration: none; color: var(--text-secondary); font-weight: 500;
    display: inline-block; margin-bottom: 2rem; transition: color 0.2s ease;
}
.kp-form-container .back-link:hover { color: var(--text-primary); }
.kp-form-container .message {
    padding: 1rem 1.5rem; margin-bottom: 2rem;
    border-radius: 12px; border: 1px solid transparent;
    font-size: 1em; text-align: center;
}
.kp-form-container .message h4 { margin-top: 0; }
.kp-form-container .message.success { background-color: #d1e7dd; color: #0f5132; border-color: #badbcc; }
.kp-form-container .message.error { background-color: #f8d7da; color: #842029; border-color: #f5c2c7; }
.kp-form-container .modern-form .form-step {
    margin-bottom: 2.5rem; border: 1px solid #f0f0f0;
    border-radius: 12px; padding: 1.5rem; background-color: #fff;
    box-shadow: 0 4px 15px rgba(0,0,0,0.03);
}
.kp-form-container .form-step-header { display: flex; align-items: center; gap: 1rem; margin-bottom: 1.5rem; }
.kp-form-container .form-step-icon {
    width: 40px; height: 40px; flex-shrink: 0;
    background: var(--bg-light); border-radius: 50%; display: flex;
    align-items: center; justify-content: center; color: #667eea;
}
.kp-form-container .form-step-icon svg { width: 20px; height: 20px; stroke: currentColor; }
.kp-form-container .form-step-header h3 { margin: 0; font-weight: 600; }
.kp-form-container .form-group { margin-bottom: 1.5rem; }
.kp-form-container .form-group:last-child { margin-bottom: 0; }
.kp-form-container .form-group label { display: block; font-weight: 500; margin-bottom: 0.5rem; font-size: 0.95rem; }
.kp-form-container .form-group input, .kp-form-container .form-group textarea, .kp-form-container .form-group select {
    width: 100%; padding: 12px 15px; border: 1px solid var(--border-color);
    border-radius: 8px; font-size: 1em; font-family: 'Inter', sans-serif;
    transition: all 0.2s ease; background-color: var(--bg-light);
}
.kp-form-container .form-group input:focus, .kp-form-container .form-group textarea:focus, .kp-form-container .form-group select:focus {
    border-color: #667eea; background-color: #fff;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.2); outline: none;
}
.kp-form-container .form-group small { display: block; font-size: 0.85em; color: var(--text-secondary); margin-top: 8px; }
.kp-form-container .form-grid {
    display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem;
}
.kp-form-container .form-actions { margin-top: 2rem; text-align: right; }
.kp-form-container .btn-submit {
    background: var(--primary-gradient); color: white; padding: 14px 30px;
    font-size: 1.1em; font-weight: 600; border: none;
    border-radius: 10px; display: inline-flex; align-items: center;
    gap: 10px; cursor: pointer; transition: all 0.3s ease;
    box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
}
.kp-form-container .btn-submit:hover:not([disabled]) {
    transform: translateY(-3px); box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const container = document.querySelector('.kp-form-container');
    if (!container) return;

    // Animasi saat scroll
    const animatedElements = container.querySelectorAll('.form-step, .message');
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.style.opacity = '1';
                entry.target.style.transform = 'translateY(0)';
                observer.unobserve(entry.target);
            }
        });
    }, { threshold: 0.1 });

    animatedElements.forEach(el => {
        el.style.opacity = '0';
        el.style.transform = 'translateY(20px)';
        el.style.transition = 'opacity 0.6s ease-out, transform 0.6s ease-out';
        observer.observe(el);
    });
});
</script>

<?php
require_once '../includes/footer.php';
if (isset($conn)) { $conn->close(); }
?>
