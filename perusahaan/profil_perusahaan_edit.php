<?php
// /KP/perusahaan/profil_perusahaan_edit.php (Versi Modern & Terisolasi)

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// 1. OTENTIKASI DAN OTORISASI
require_once '../includes/auth_check.php';
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'perusahaan') {
    header("Location: /KP/index.php?error=unauthorized_perusahaan");
    exit();
}

$id_perusahaan_login = $_SESSION['user_id'];
$perusahaan_data = null;
$error_message = '';
$success_message = '';

require_once '../config/db_connect.php';

// 2. PROSES UPDATE DATA (Logika PHP Anda sudah baik, hanya sedikit penyesuaian)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_edit_perusahaan'])) {
    if (!$conn) {
        $error_message = "Koneksi database tidak tersedia.";
    } else {
        // Ambil dan bersihkan data
        $nama_perusahaan_input = trim($_POST['nama_perusahaan']);
        $alamat_input = trim($_POST['alamat']);
        $bidang_input = trim($_POST['bidang']);
        $kontak_nama_input = trim($_POST['kontak_person_nama']);
        $kontak_email_input = trim($_POST['kontak_person_email']);
        $kontak_no_hp_input = trim($_POST['kontak_person_no_hp']);
        $password_baru_input = $_POST['password_perusahaan_baru'];

        // Validasi
        if (empty($nama_perusahaan_input)) {
            $error_message = "Nama Perusahaan wajib diisi.";
        } elseif (!empty($kontak_email_input) && !filter_var($kontak_email_input, FILTER_VALIDATE_EMAIL)) {
            $error_message = "Format Email Kontak Person tidak valid.";
        }

        if (empty($error_message)) {
            $sql_parts = [];
            $params = [];
            $types = "";

            // Bangun query secara dinamis
            $sql_parts[] = "nama_perusahaan = ?"; $params[] = $nama_perusahaan_input; $types .= "s";
            $sql_parts[] = "alamat = ?"; $params[] = $alamat_input; $types .= "s";
            $sql_parts[] = "bidang = ?"; $params[] = $bidang_input; $types .= "s";
            $sql_parts[] = "kontak_person_nama = ?"; $params[] = $kontak_nama_input; $types .= "s";
            $sql_parts[] = "kontak_person_email = ?"; $params[] = $kontak_email_input; $types .= "s";
            $sql_parts[] = "kontak_person_no_hp = ?"; $params[] = $kontak_no_hp_input; $types .= "s";

            if (!empty($password_baru_input)) {
                if (strlen($password_baru_input) < 6) {
                    $error_message = "Password baru minimal harus 6 karakter.";
                } else {
                    $sql_parts[] = "password_perusahaan = ?";
                    $params[] = password_hash($password_baru_input, PASSWORD_DEFAULT);
                    $types .= "s";
                }
            }
            
            if (empty($error_message)) {
                $params[] = $id_perusahaan_login;
                $types .= "i";
                
                $sql_update = "UPDATE perusahaan SET " . implode(', ', $sql_parts) . " WHERE id_perusahaan = ?";
                $stmt_update = $conn->prepare($sql_update);
                
                if ($stmt_update) {
                    $stmt_update->bind_param($types, ...$params);
                    if ($stmt_update->execute()) {
                        if ($stmt_update->affected_rows > 0) {
                            $success_message = "Profil perusahaan berhasil diperbarui.";
                            $_SESSION['user_nama'] = $nama_perusahaan_input; // Update nama di session
                        } else {
                            $success_message = "Tidak ada perubahan data yang dilakukan.";
                        }
                    } else {
                        $error_message = "Gagal memperbarui profil: " . htmlspecialchars($stmt_update->error);
                    }
                    $stmt_update->close();
                } else {
                    $error_message = "Gagal menyiapkan statement update: " . htmlspecialchars($conn->error);
                }
            }
        }
    }
}

// 3. SELALU AMBIL DATA TERBARU UNTUK DITAMPILKAN DI FORM
if ($conn) {
    $stmt_get = $conn->prepare("SELECT * FROM perusahaan WHERE id_perusahaan = ?");
    if ($stmt_get) {
        $stmt_get->bind_param("i", $id_perusahaan_login);
        $stmt_get->execute();
        $result_get = $stmt_get->get_result();
        $perusahaan_data = ($result_get->num_rows === 1) ? $result_get->fetch_assoc() : null;
        $stmt_get->close();
    } else {
        if(empty($error_message)) $error_message = "Gagal memuat data perusahaan.";
    }
} else {
    if(empty($error_message)) $error_message = "Koneksi database tidak tersedia.";
}

$page_title = "Edit Profil Perusahaan";
require_once '../includes/header.php';
?>

<div class="kp-edit-profil-container">

    <div class="form-hero-section">
        <div class="form-hero-content">
            <div class="form-hero-icon"><svg viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg></div>
            <h1><?php echo htmlspecialchars($page_title); ?></h1>
            <p>Perbarui informasi detail perusahaan, kontak, dan keamanan akun Anda.</p>
        </div>
    </div>

    <div class="form-wrapper">
        <a href="profil_perusahaan.php" class="back-link">&larr; Kembali ke Halaman Profil</a>
        
        <?php if (!empty($success_message)): ?><div class="message success"><h4>Berhasil!</h4><p><?php echo htmlspecialchars($success_message); ?></p></div><?php endif; ?>
        <?php if (!empty($error_message)): ?><div class="message error"><p><?php echo htmlspecialchars($error_message); ?></p></div><?php endif; ?>

        <?php if (is_array($perusahaan_data)): ?>
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST" class="modern-form">
                
                <div class="form-step animate-on-scroll">
                    <div class="form-step-header"><div class="form-step-icon"><svg viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg></div><h3>Informasi Perusahaan</h3></div>
                    <div class="form-group"><label for="nama_perusahaan">Nama Perusahaan (*)</label><input type="text" id="nama_perusahaan" name="nama_perusahaan" value="<?php echo htmlspecialchars($perusahaan_data['nama_perusahaan'] ?? ''); ?>" required></div>
                    <div class="form-group"><label for="email_perusahaan">Email Perusahaan (Login)</label><input type="email" id="email_perusahaan" value="<?php echo htmlspecialchars($perusahaan_data['email_perusahaan'] ?? ''); ?>" readonly><small>Email login tidak dapat diubah.</small></div>
                    <div class="form-group"><label for="alamat">Alamat Lengkap</label><textarea id="alamat" name="alamat" rows="3"><?php echo htmlspecialchars($perusahaan_data['alamat'] ?? ''); ?></textarea></div>
                    <div class="form-group"><label for="bidang">Bidang Usaha</label><input type="text" id="bidang" name="bidang" value="<?php echo htmlspecialchars($perusahaan_data['bidang'] ?? ''); ?>"></div>
                </div>

                <div class="form-step animate-on-scroll">
                    <div class="form-step-header"><div class="form-step-icon"><svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg></div><h3>Informasi Kontak Person</h3></div>
                    <div class="form-grid">
                        <div class="form-group"><label for="kontak_person_nama">Nama Kontak Person</label><input type="text" id="kontak_person_nama" name="kontak_person_nama" value="<?php echo htmlspecialchars($perusahaan_data['kontak_person_nama'] ?? ''); ?>"></div>
                        <div class="form-group"><label for="kontak_person_no_hp">No. HP Kontak Person</label><input type="text" id="kontak_person_no_hp" name="kontak_person_no_hp" value="<?php echo htmlspecialchars($perusahaan_data['kontak_person_no_hp'] ?? ''); ?>"></div>
                    </div>
                    <div class="form-group"><label for="kontak_person_email">Email Kontak Person</label><input type="email" id="kontak_person_email" name="kontak_person_email" value="<?php echo htmlspecialchars($perusahaan_data['kontak_person_email'] ?? ''); ?>"></div>
                </div>
                
                <div class="form-step animate-on-scroll">
                    <div class="form-step-header"><div class="form-step-icon"><svg viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg></div><h3>Keamanan Akun</h3></div>
                    <div class="form-group">
                        <label for="password_perusahaan_baru">Password Baru</label>
                        <div class="password-wrapper">
                            <input type="password" id="password_perusahaan_baru" name="password_perusahaan_baru" minlength="6">
                            <button type="button" class="password-toggle">
                                <svg class="icon-eye" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                                <svg class="icon-eye-off" style="display:none;" viewBox="0 0 24 24"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line></svg>
                            </button>
                        </div>
                        <small>Kosongkan jika tidak ingin mengubah password.</small>
                    </div>
                </div>

                <div class="form-actions"><button type="submit" name="submit_edit_perusahaan" class="btn-submit"><svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"></polyline></svg>Simpan Perubahan</button></div>
            </form>
        <?php endif; ?>
    </div>
</div>

<style>
/* GAYA BARU YANG TERISOLASI DALAM KONTENER */
.kp-edit-profil-container{--primary-gradient:linear-gradient(135deg,#667eea 0%,#764ba2 100%);--text-primary:#1f2937;--text-secondary:#6b7280;--bg-light:#f9fafb;--border-color:#e5e7eb;--card-shadow:0 10px 30px rgba(0,0,0,.07);--border-radius:16px;font-family:Inter,sans-serif;color:var(--text-primary);max-width:900px;margin:0 auto;padding:2rem 1rem}.kp-edit-profil-container svg{stroke-width:2;stroke-linecap:round;stroke-linejoin:round;fill:none;stroke:currentColor}.kp-edit-profil-container .form-hero-section{padding:3rem 2rem;background:var(--primary-gradient);border-radius:var(--border-radius);margin-bottom:2rem;color:#fff;text-align:center}.kp-edit-profil-container .form-hero-content{max-width:600px;margin:0 auto}.kp-edit-profil-container .form-hero-icon{width:60px;height:60px;background:rgba(255,255,255,.1);border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 1.5rem}.kp-edit-profil-container .form-hero-icon svg{width:28px;height:28px;stroke:#fff}.kp-edit-profil-container .form-hero-section h1{font-size:2.5rem;font-weight:700;margin-bottom:.5rem}.kp-edit-profil-container .form-hero-section p{font-size:1.1rem;opacity:.9;font-weight:300}.kp-edit-profil-container .form-wrapper{background-color:#fff;padding:2.5rem;border-radius:var(--border-radius);box-shadow:var(--card-shadow)}.kp-edit-profil-container .back-link{text-decoration:none;color:var(--text-secondary);font-weight:500;display:inline-block;margin-bottom:2rem;transition:color .2s ease}.kp-edit-profil-container .back-link:hover{color:var(--text-primary)}.kp-edit-profil-container .message{padding:1rem 1.5rem;margin-bottom:2rem;border-radius:12px;border:1px solid transparent;font-size:1em;text-align:center}.kp-edit-profil-container .message h4{margin-top:0}.kp-edit-profil-container .message.success{background-color:#d1e7dd;color:#0f5132;border-color:#badbcc}.kp-edit-profil-container .message.error{background-color:#f8d7da;color:#842029;border-color:#f5c2c7}.kp-edit-profil-container .modern-form .form-step{margin-bottom:2.5rem;border:1px solid #f0f0f0;border-radius:12px;padding:1.5rem;background-color:#fff;box-shadow:0 4px 15px rgba(0,0,0,.03)}.kp-edit-profil-container .form-step-header{display:flex;align-items:center;gap:1rem;margin-bottom:1.5rem}.kp-edit-profil-container .form-step-icon{width:40px;height:40px;flex-shrink:0;background:var(--bg-light);border-radius:50%;display:flex;align-items:center;justify-content:center;color:#667eea}.kp-edit-profil-container .form-step-icon svg{width:20px;height:20px;stroke:currentColor}.kp-edit-profil-container .form-step-header h3{margin:0;font-weight:600}.kp-edit-profil-container .form-group{margin-bottom:1.5rem}.kp-edit-profil-container .form-group:last-child{margin-bottom:0}.kp-edit-profil-container .form-group label{display:block;font-weight:500;margin-bottom:.5rem;font-size:.95rem}.kp-edit-profil-container .form-group input,.kp-edit-profil-container .form-group textarea{width:100%;padding:12px 15px;border:1px solid var(--border-color);border-radius:8px;font-size:1em;font-family:Inter,sans-serif;transition:all .2s ease;background-color:var(--bg-light)}.kp-edit-profil-container .form-group input:focus,.kp-edit-profil-container .form-group textarea:focus{border-color:#667eea;background-color:#fff;box-shadow:0 0 0 3px rgba(102,126,234,.2);outline:none}.kp-edit-profil-container .form-group input[readonly]{background-color:#e9ecef;cursor:not-allowed}.kp-edit-profil-container .form-group small{display:block;font-size:.85em;color:var(--text-secondary);margin-top:8px}.kp-edit-profil-container .form-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:1.5rem}.kp-edit-profil-container .password-wrapper{position:relative;display:flex;align-items:center}.kp-edit-profil-container .password-wrapper input{padding-right:40px}.kp-edit-profil-container .password-toggle{position:absolute;right:1px;top:1px;bottom:1px;width:40px;background:0 0;border:none;cursor:pointer;color:var(--text-secondary)}.kp-edit-profil-container .form-actions{margin-top:2rem;text-align:right}.kp-edit-profil-container .btn-submit{background:var(--primary-gradient);color:#fff;padding:14px 30px;font-size:1.1em;font-weight:600;border:none;border-radius:10px;display:inline-flex;align-items:center;gap:10px;cursor:pointer;transition:all .3s ease;box-shadow:0 4px 15px rgba(102,126,234,.3)}.kp-edit-profil-container .btn-submit:hover:not([disabled]){transform:translateY(-3px);box-shadow:0 8px 25px rgba(102,126,234,.4)}.kp-edit-profil-container .animate-on-scroll{opacity:0;transform:translateY(30px);transition:opacity .6s ease-out,transform .6s ease-out}.kp-edit-profil-container .animate-on-scroll.is-visible{opacity:1;transform:translateY(0)}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const container = document.querySelector('.kp-edit-profil-container');
    if (!container) return;

    // Show/hide password
    const toggleButton = container.querySelector('.password-toggle');
    if (toggleButton) {
        toggleButton.addEventListener('click', function() {
            const passwordInput = container.querySelector('#password_perusahaan_baru');
            const iconEye = this.querySelector('.icon-eye');
            const iconEyeOff = this.querySelector('.icon-eye-off');
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                iconEye.style.display = 'none';
                iconEyeOff.style.display = 'block';
            } else {
                passwordInput.type = 'password';
                iconEye.style.display = 'block';
                iconEyeOff.style.display = 'none';
            }
        });
    }

    // Animasi saat scroll
    const animatedElements = container.querySelectorAll('.animate-on-scroll');
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('is-visible');
                observer.unobserve(entry.target);
            }
        });
    }, { threshold: 0.1 });
    animatedElements.forEach(el => observer.observe(el));
});
</script>

<?php
require_once '../includes/footer.php';
if (isset($conn) && $conn instanceof mysqli) {
    $conn->close();
}
?>