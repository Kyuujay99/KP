<?php
// /KP/admin_prodi/perusahaan_edit.php (Versi Final & Ditingkatkan)

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once '../includes/auth_check.php';
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin_prodi') {
    header("Location: /KP/index.php?error=unauthorized_admin");
    exit();
}
require_once '../config/db_connect.php';

$id_perusahaan_to_edit = null;
$perusahaan_data = null;
$error_message = '';
$success_message = '';

if (isset($_GET['id_perusahaan']) && filter_var($_GET['id_perusahaan'], FILTER_VALIDATE_INT)) {
    $id_perusahaan_to_edit = (int)$_GET['id_perusahaan'];
} else {
    $error_message = "ID Perusahaan tidak valid atau tidak ditemukan.";
}

function getPerusahaanData($conn_db, $id, &$err) {
    if (!$conn_db || !$id) {
        $err = "Koneksi atau ID tidak valid.";
        return null;
    }
    $data = null;
    $stmt = $conn_db->prepare("SELECT * FROM perusahaan WHERE id_perusahaan = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows === 1) {
            $data = $result->fetch_assoc();
        } else {
            $err = "Data perusahaan dengan ID tersebut tidak ditemukan.";
        }
        $stmt->close();
    } else {
        $err = "Gagal menyiapkan query untuk mengambil data perusahaan.";
    }
    return $data;
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_edit_perusahaan']) && !empty($id_perusahaan_to_edit)) {
    if ($conn) {
        $id_perusahaan_form = (int)$_POST['id_perusahaan'];
        if ($id_perusahaan_form !== $id_perusahaan_to_edit) {
            $error_message = "Kesalahan data: ID Perusahaan tidak cocok.";
        } else {
            // Sanitasi dan validasi input
            $nama_perusahaan = trim($_POST['nama_perusahaan']);
            $email_perusahaan = trim($_POST['email_perusahaan']);
            $alamat = trim($_POST['alamat']);
            $bidang = trim($_POST['bidang']);
            $kontak_nama = trim($_POST['kontak_person_nama']);
            $kontak_email = trim($_POST['kontak_person_email']);
            $kontak_no_hp = trim($_POST['kontak_person_no_hp']);
            $status_akun = $_POST['status_akun'];
            $password_baru = $_POST['password_perusahaan_baru'];

            if (empty($nama_perusahaan) || empty($email_perusahaan) || empty($status_akun)) {
                $error_message = "Nama Perusahaan, Email Login, dan Status Akun wajib diisi.";
            } elseif (!filter_var($email_perusahaan, FILTER_VALIDATE_EMAIL)) {
                $error_message = "Format Email Login tidak valid.";
            } elseif (!empty($kontak_email) && !filter_var($kontak_email, FILTER_VALIDATE_EMAIL)) {
                $error_message = "Format Email Kontak Person tidak valid.";
            }

            if (empty($error_message)) {
                $sql_check_email = $conn->prepare("SELECT id_perusahaan FROM perusahaan WHERE email_perusahaan = ? AND id_perusahaan != ?");
                $sql_check_email->bind_param("si", $email_perusahaan, $id_perusahaan_to_edit);
                $sql_check_email->execute();
                if ($sql_check_email->get_result()->num_rows > 0) {
                    $error_message = "Email login '$email_perusahaan' sudah digunakan oleh perusahaan lain.";
                }
                $sql_check_email->close();
            }

            if (empty($error_message)) {
                // Bangun query update secara dinamis
                $sql_update = "UPDATE perusahaan SET nama_perusahaan=?, email_perusahaan=?, alamat=?, bidang=?, kontak_person_nama=?, kontak_person_email=?, kontak_person_no_hp=?, status_akun=?";
                $params = [$nama_perusahaan, $email_perusahaan, $alamat, $bidang, $kontak_nama, $kontak_email, $kontak_no_hp, $status_akun];
                $types = "ssssssss";

                if (!empty($password_baru)) {
                    if (strlen($password_baru) < 6) {
                        $error_message = "Password baru minimal harus 6 karakter.";
                    } else {
                        $sql_update .= ", password_perusahaan = ?";
                        $params[] = $password_baru; // Simpan password sebagai plain text sesuai permintaan
                        $types .= "s";
                    }
                }

                if (empty($error_message)) {
                    $sql_update .= " WHERE id_perusahaan = ?";
                    $params[] = $id_perusahaan_to_edit;
                    $types .= "i";

                    $stmt_update = $conn->prepare($sql_update);
                    $stmt_update->bind_param($types, ...$params);

                    if ($stmt_update->execute()) {
                        if ($stmt_update->affected_rows > 0) {
                            $success_message = "Data perusahaan berhasil diperbarui.";
                        } else {
                            $success_message = "Tidak ada perubahan data yang disimpan.";
                        }
                    } else {
                        $error_message = "Gagal memperbarui data: " . $stmt_update->error;
                    }
                    $stmt_update->close();
                }
            }
        }
    } else {
        $error_message = "Koneksi ke database gagal.";
    }
}

if ($id_perusahaan_to_edit && $conn) {
    $perusahaan_data = getPerusahaanData($conn, $id_perusahaan_to_edit, $error_message);
}

$opsi_status_akun_perusahaan = ['pending_approval' => 'Pending Approval', 'active' => 'Active', 'inactive' => 'Inactive'];
$page_title = "Edit Data Perusahaan";
if ($perusahaan_data) {
    $page_title = "Edit: " . htmlspecialchars($perusahaan_data['nama_perusahaan']);
}
require_once '../includes/header.php';
?>

<div class="kp-edit-form-container">
    <div class="form-hero-section">
        <div class="form-hero-content">
            <div class="form-hero-icon"><svg viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg></div>
            <h1><?php echo htmlspecialchars($page_title); ?></h1>
            <p>Perbarui informasi detail, kontak, dan status akun untuk perusahaan mitra ini.</p>
        </div>
    </div>

    <div class="form-wrapper">
        <a href="perusahaan_kelola.php" class="back-link">&larr; Kembali ke Daftar Perusahaan</a>

        <?php if (!empty($success_message)): ?>
            <div class="message success"><h4>Berhasil!</h4><p><?php echo htmlspecialchars($success_message); ?></p></div>
        <?php endif; ?>
        <?php if (!empty($error_message) && !$perusahaan_data): ?>
            <div class="message error"><p><?php echo htmlspecialchars($error_message); ?></p></div>
        <?php endif; ?>

        <?php if ($perusahaan_data): ?>
            <form action="perusahaan_edit.php?id_perusahaan=<?php echo $id_perusahaan_to_edit; ?>" method="POST" class="modern-form">
                <input type="hidden" name="id_perusahaan" value="<?php echo htmlspecialchars($perusahaan_data['id_perusahaan']); ?>">

                <div class="form-step">
                    <div class="form-step-header">
                        <div class="form-step-icon"><svg viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg></div>
                        <h3>Informasi Utama & Akun</h3>
                    </div>
                    <div class="form-group"><label for="nama_perusahaan">Nama Perusahaan (*)</label><input type="text" id="nama_perusahaan" name="nama_perusahaan" value="<?php echo htmlspecialchars($perusahaan_data['nama_perusahaan']); ?>" required></div>
                    <div class="form-grid"><div class="form-group"><label for="email_perusahaan">Email Login (*)</label><input type="email" id="email_perusahaan" name="email_perusahaan" value="<?php echo htmlspecialchars($perusahaan_data['email_perusahaan']); ?>" required></div><div class="form-group"><label for="bidang">Bidang Usaha</label><input type="text" id="bidang" name="bidang" value="<?php echo htmlspecialchars($perusahaan_data['bidang']); ?>"></div></div>
                    <div class="form-group"><label for="alamat">Alamat Lengkap</label><textarea id="alamat" name="alamat" rows="3"><?php echo htmlspecialchars($perusahaan_data['alamat']); ?></textarea></div>
                </div>

                <div class="form-step">
                    <div class="form-step-header"><div class="form-step-icon"><svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg></div><h3>Informasi Kontak Person (PIC)</h3></div>
                    <div class="form-grid"><div class="form-group"><label for="kontak_person_nama">Nama Kontak Person</label><input type="text" id="kontak_person_nama" name="kontak_person_nama" value="<?php echo htmlspecialchars($perusahaan_data['kontak_person_nama']); ?>"></div><div class="form-group"><label for="kontak_person_no_hp">No. HP Kontak Person</label><input type="text" id="kontak_person_no_hp" name="kontak_person_no_hp" value="<?php echo htmlspecialchars($perusahaan_data['kontak_person_no_hp']); ?>"></div></div>
                    <div class="form-group"><label for="kontak_person_email">Email Kontak Person</label><input type="email" id="kontak_person_email" name="kontak_person_email" value="<?php echo htmlspecialchars($perusahaan_data['kontak_person_email']); ?>"></div>
                </div>

                <div class="form-step">
                    <div class="form-step-header"><div class="form-step-icon"><svg viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg></div><h3>Pengaturan Akun</h3></div>
                    <div class="form-grid">
                        <div class="form-group"><label for="password_perusahaan_baru">Reset Password (Opsional)</label><input type="password" id="password_perusahaan_baru" name="password_perusahaan_baru" placeholder="Kosongkan jika tidak diubah" minlength="6"></div>
                        <div class="form-group"><label for="status_akun">Status Akun (*)</label><select id="status_akun" name="status_akun" required><?php foreach ($opsi_status_akun_perusahaan as $value => $text): ?><option value="<?php echo htmlspecialchars($value); ?>" <?php echo ($perusahaan_data['status_akun'] == $value) ? 'selected' : ''; ?>><?php echo htmlspecialchars($text); ?></option><?php endforeach; ?></select></div>
                    </div>
                </div>

                <div class="form-actions"><button type="submit" name="submit_edit_perusahaan" class="btn-submit"><svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"></polyline></svg> Simpan Perubahan</button></div>
            </form>
        <?php elseif(empty($error_message)): ?>
            <div class="message info"><p>Memuat data perusahaan...</p></div>
        <?php endif; ?>
    </div>
</div>

<style>
/* GAYA BARU YANG TERISOLASI DALAM KONTENER */
.kp-edit-form-container {
    --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    --text-primary: #1f2937; --text-secondary: #6b7280;
    --bg-light: #f9fafb; --border-color: #e5e7eb;
    --card-shadow: 0 10px 30px rgba(0,0,0,0.07);
    --border-radius: 16px;
    font-family: 'Inter', sans-serif;
    color: var(--text-primary);
    max-width: 900px; margin: 0 auto; padding: 2rem 1rem;
}
.kp-edit-form-container svg { stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; fill: none; stroke: currentColor; }
.kp-edit-form-container .form-hero-section {
    padding: 3rem 2rem; background: var(--primary-gradient);
    border-radius: var(--border-radius); margin-bottom: 2rem;
    color: white; text-align: center;
}
.kp-edit-form-container .form-hero-content { max-width: 600px; margin: 0 auto; }
.kp-edit-form-container .form-hero-icon {
    width: 60px; height: 60px; background: rgba(255,255,255,0.1);
    border-radius: 50%; display: flex; align-items: center; justify-content: center;
    margin: 0 auto 1.5rem;
}
.kp-edit-form-container .form-hero-icon svg { width: 28px; height: 28px; stroke: white; }
.kp-edit-form-container .form-hero-section h1 { font-size: 2.5rem; font-weight: 700; margin-bottom: 0.5rem; }
.kp-edit-form-container .form-hero-section p { font-size: 1.1rem; opacity: 0.9; font-weight: 300; }
.kp-edit-form-container .form-wrapper {
    background-color: #ffffff; padding: 2.5rem;
    border-radius: var(--border-radius); box-shadow: var(--card-shadow);
}
.kp-edit-form-container .back-link {
    text-decoration: none; color: var(--text-secondary); font-weight: 500;
    display: inline-block; margin-bottom: 2rem; transition: color 0.2s ease;
}
.kp-edit-form-container .back-link:hover { color: var(--text-primary); }
.kp-edit-form-container .message {
    padding: 1rem 1.5rem; margin-bottom: 2rem;
    border-radius: 12px; border: 1px solid transparent;
    font-size: 1em; text-align: center;
}
.kp-edit-form-container .message h4 { margin-top: 0; }
.kp-edit-form-container .message.success { background-color: #d1e7dd; color: #0f5132; border-color: #badbcc; }
.kp-edit-form-container .message.error { background-color: #f8d7da; color: #842029; border-color: #f5c2c7; }
.kp-edit-form-container .modern-form .form-step {
    margin-bottom: 2.5rem; border: 1px solid #f0f0f0;
    border-radius: 12px; padding: 1.5rem; background-color: #fff;
    box-shadow: 0 4px 15px rgba(0,0,0,0.03);
}
.kp-edit-form-container .form-step-header { display: flex; align-items: center; gap: 1rem; margin-bottom: 1.5rem; }
.kp-edit-form-container .form-step-icon {
    width: 40px; height: 40px; flex-shrink: 0;
    background: var(--bg-light); border-radius: 50%; display: flex;
    align-items: center; justify-content: center; color: #667eea;
}
.kp-edit-form-container .form-step-icon svg { width: 20px; height: 20px; stroke: currentColor; }
.kp-edit-form-container .form-step-header h3 { margin: 0; font-weight: 600; }
.kp-edit-form-container .form-group { margin-bottom: 1.5rem; }
.kp-edit-form-container .form-group:last-child { margin-bottom: 0; }
.kp-edit-form-container .form-group label { display: block; font-weight: 500; margin-bottom: 0.5rem; font-size: 0.95rem; }
.kp-edit-form-container .form-group input, .kp-edit-form-container .form-group textarea, .kp-edit-form-container .form-group select {
    width: 100%; padding: 12px 15px; border: 1px solid var(--border-color);
    border-radius: 8px; font-size: 1em; font-family: 'Inter', sans-serif;
    transition: all 0.2s ease; background-color: var(--bg-light);
}
.kp-edit-form-container .form-group input:focus, .kp-edit-form-container .form-group textarea:focus, .kp-edit-form-container .form-group select:focus {
    border-color: #667eea; background-color: #fff;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.2); outline: none;
}
.kp-edit-form-container .form-grid {
    display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem;
}
.kp-edit-form-container .form-actions { margin-top: 2rem; text-align: right; }
.kp-edit-form-container .btn-submit {
    background: var(--primary-gradient); color: white; padding: 14px 30px;
    font-size: 1.1em; font-weight: 600; border: none;
    border-radius: 10px; display: inline-flex; align-items: center;
    gap: 10px; cursor: pointer; transition: all 0.3s ease;
    box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
}
.kp-edit-form-container .btn-submit:hover:not([disabled]) {
    transform: translateY(-3px); box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const container = document.querySelector('.kp-edit-form-container');
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