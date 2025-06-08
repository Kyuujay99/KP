<?php
// /KP/perusahaan/profil_perusahaan.php (Versi Modern & Terisolasi)

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// 1. OTENTIKASI DAN OTORISASI
require_once '../includes/auth_check.php';
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'perusahaan') {
    session_unset();
    session_destroy();
    header("Location: /KP/index.php?error=unauthorized_perusahaan");
    exit();
}

$id_perusahaan_login = $_SESSION['user_id'];
$perusahaan_data = null;
$error_message = '';

require_once '../config/db_connect.php';

// 2. AMBIL DATA PROFIL PERUSAHAAN (Logika PHP Anda sudah baik dan dipertahankan)
if ($conn && ($conn instanceof mysqli) && !$conn->connect_error) {
    $sql = "SELECT * FROM perusahaan WHERE id_perusahaan = ? LIMIT 1";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("i", $id_perusahaan_login);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows === 1) {
            $perusahaan_data = $result->fetch_assoc();
        } else {
            $error_message = "Data perusahaan Anda tidak dapat ditemukan.";
        }
        $stmt->close();
    } else {
        $error_message = "Gagal menyiapkan query untuk mengambil data profil.";
    }
} else {
    $error_message = "Koneksi database gagal.";
}

$page_title = "Profil Perusahaan";
if ($perusahaan_data) {
    $page_title = htmlspecialchars($perusahaan_data['nama_perusahaan']);
}
require_once '../includes/header.php';
?>

<!-- KONTENER PEMBUNGKUS UTAMA UNTUK MENGISOLASI TAMPILAN -->
<div class="kp-profile-modern-container">

    <!-- Hero Header -->
    <div class="profile-hero-section">
        <div class="profile-hero-content">
            <div class="profile-hero-icon">
                <svg viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>
            </div>
            <h1><?php echo $page_title; ?></h1>
            <p>Informasi detail mengenai perusahaan dan akun Anda yang terdaftar di sistem.</p>
        </div>
    </div>

    <div class="profile-wrapper">
         <?php if (!empty($error_message)): ?>
            <div class="message error"><p><?php echo htmlspecialchars($error_message); ?></p></div>
        <?php endif; ?>

        <?php if ($perusahaan_data): ?>
            <div class="profile-header animate-on-scroll">
                <h2>Detail Profil</h2>
                <a href="/KP/perusahaan/profil_perusahaan_edit.php" class="btn-edit-profile">
                    <svg viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path></svg>
                    Edit Profil
                </a>
            </div>

            <div class="profile-grid">
                <!-- Informasi Perusahaan -->
                <div class="info-card animate-on-scroll">
                    <div class="info-card-header">
                        <div class="info-card-icon"><svg viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg></div>
                        <h3>Informasi Perusahaan</h3>
                    </div>
                    <div class="info-card-body">
                        <div class="info-item"><span>ID Perusahaan</span><strong><?php echo htmlspecialchars($perusahaan_data['id_perusahaan']); ?></strong></div>
                        <div class="info-item"><span>Nama Perusahaan</span><strong><?php echo htmlspecialchars($perusahaan_data['nama_perusahaan']); ?></strong></div>
                        <div class="info-item"><span>Email Login</span><strong><?php echo htmlspecialchars($perusahaan_data['email_perusahaan']); ?></strong></div>
                        <div class="info-item"><span>Bidang Usaha</span><strong><?php echo htmlspecialchars($perusahaan_data['bidang'] ?? '-'); ?></strong></div>
                        <div class="info-item wide"><span>Alamat</span><strong><?php echo nl2br(htmlspecialchars($perusahaan_data['alamat'] ?? '-')); ?></strong></div>
                    </div>
                </div>

                <!-- Informasi Kontak Person -->
                <div class="info-card animate-on-scroll">
                    <div class="info-card-header">
                        <div class="info-card-icon"><svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg></div>
                        <h3>Kontak Person (PIC)</h3>
                    </div>
                    <div class="info-card-body">
                        <div class="info-item"><span>Nama Kontak</span><strong><?php echo htmlspecialchars($perusahaan_data['kontak_person_nama'] ?? '-'); ?></strong></div>
                        <div class="info-item"><span>Email Kontak</span><strong><?php echo htmlspecialchars($perusahaan_data['kontak_person_email'] ?? '-'); ?></strong></div>
                        <div class="info-item"><span>No. HP Kontak</span><strong><?php echo htmlspecialchars($perusahaan_data['kontak_person_no_hp'] ?? '-'); ?></strong></div>
                    </div>
                </div>

                <!-- Informasi Akun -->
                 <div class="info-card animate-on-scroll">
                    <div class="info-card-header">
                         <div class="info-card-icon"><svg viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><line x1="19" y1="8" x2="19" y2="14"></line><line x1="22" y1="11" x2="16" y2="11"></line></svg></div>
                        <h3>Informasi Akun</h3>
                    </div>
                    <div class="info-card-body">
                         <div class="info-item">
                             <span>Status Akun</span>
                             <strong>
                                 <span class="status-badge status-<?php echo strtolower(htmlspecialchars($perusahaan_data['status_akun'])); ?>">
                                     <?php echo ucfirst(str_replace('_', ' ', htmlspecialchars($perusahaan_data['status_akun']))); ?>
                                 </span>
                             </strong>
                         </div>
                         <div class="info-item">
                             <span>Tanggal Terdaftar</span>
                             <strong><?php echo date("d F Y", strtotime($perusahaan_data['created_at'])); ?></strong>
                         </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
/* GAYA BARU YANG TERISOLASI DALAM KONTENER */
.kp-profile-modern-container {
    --primary-color: #667eea;
    --primary-gradient: linear-gradient(135deg,#667eea 0%,#764ba2 100%);
    --text-primary: #1f2937;
    --text-secondary: #6b7280;
    --bg-light: #f9fafb;
    --border-color: #e5e7eb;
    --card-shadow: 0 4px 6px -1px rgba(0,0,0,0.1), 0 2px 4px -1px rgba(0,0,0,0.06);
    --border-radius: 12px;
    font-family: 'Inter', sans-serif;
    color: var(--text-primary);
    max-width: 1200px;
    margin: 0 auto;
    padding: 2rem;
}
.kp-profile-modern-container svg {
    stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; fill: none; stroke: currentColor;
}
.kp-profile-modern-container .profile-hero-section {
    padding: 3rem 2rem;
    background: var(--primary-gradient);
    border-radius: var(--border-radius);
    margin-bottom: 2rem;
    color: white;
    text-align: center;
}
.kp-profile-modern-container .profile-hero-content { max-width: 700px; margin: 0 auto; }
.kp-profile-modern-container .profile-hero-icon {
    width: 60px; height: 60px;
    background: rgba(255,255,255,0.1);
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    margin: 0 auto 1.5rem;
}
.kp-profile-modern-container .profile-hero-icon svg { width: 28px; height: 28px; stroke: white; }
.kp-profile-modern-container .profile-hero-section h1 { font-size: 2.5rem; font-weight: 700; margin-bottom: 0.5rem; }
.kp-profile-modern-container .profile-hero-section p { font-size: 1.1rem; opacity: 0.9; font-weight: 300; }
.kp-profile-modern-container .profile-wrapper {
    background-color: #fff;
    padding: 2rem;
    border-radius: var(--border-radius);
    box-shadow: var(--card-shadow);
}
.kp-profile-modern-container .message { padding: 1.5rem; border-radius: var(--border-radius); text-align: center; }
.kp-profile-modern-container .message.error { background-color: #fee2e2; color: #991b1b; }

.kp-profile-modern-container .profile-header {
    display: flex; justify-content: space-between; align-items: center;
    padding-bottom: 1.5rem; margin-bottom: 2rem; border-bottom: 1px solid var(--border-color);
}
.kp-profile-modern-container .profile-header h2 { margin: 0; font-size: 1.8rem; }
.kp-profile-modern-container .btn-edit-profile {
    display: inline-flex; align-items: center; gap: 0.5rem;
    padding: 0.75rem 1.25rem; border-radius: 8px; text-decoration: none;
    font-weight: 600; background-color: var(--primary-color); color: white;
    border: 1px solid var(--primary-color); transition: all 0.2s ease;
}
.kp-profile-modern-container .btn-edit-profile:hover {
    background-color: #4338ca; border-color: #4338ca;
    transform: translateY(-2px); box-shadow: 0 4px 20px rgba(102, 126, 234, 0.4);
}
.kp-profile-modern-container .btn-edit-profile svg { width: 16px; height: 16px; }

.kp-profile-modern-container .profile-grid { display: grid; gap: 2rem; }
.kp-profile-modern-container .info-card {
    background-color: var(--bg-light);
    border: 1px solid var(--border-color);
    border-radius: var(--border-radius);
    overflow: hidden;
}
.kp-profile-modern-container .info-card-header {
    display: flex; align-items: center; gap: 1rem;
    padding: 1rem 1.5rem; border-bottom: 1px solid var(--border-color);
}
.kp-profile-modern-container .info-card-icon {
    width: 40px; height: 40px; display: flex; align-items: center; justify-content: center;
    background-color: #e0e7ff; color: #4338ca; border-radius: 50%;
}
.kp-profile-modern-container .info-card-icon svg { width: 20px; height: 20px; }
.kp-profile-modern-container .info-card-header h3 { margin: 0; font-size: 1.2rem; }
.kp-profile-modern-container .info-card-body {
    display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 1.5rem; padding: 1.5rem;
}
.kp-profile-modern-container .info-item span {
    display: block; font-size: 0.9rem;
    color: var(--text-secondary); margin-bottom: 0.25rem;
}
.kp-profile-modern-container .info-item strong { font-weight: 600; line-height: 1.4; }
.kp-profile-modern-container .info-item.wide { grid-column: 1 / -1; }

.kp-profile-modern-container .status-badge {
    padding: 0.25rem 0.75rem; border-radius: 999px;
    font-size: 0.85rem; font-weight: 500; color: #fff;
    display: inline-block;
}
.status-pending_approval { background-color: #fbbf24; color: var(--text-primary); }
.status-active { background-color: #34d399; }
.status-inactive { background-color: var(--text-secondary); }

.kp-profile-modern-container .animate-on-scroll {
    opacity: 0; transform: translateY(20px);
    transition: opacity 0.5s ease-out, transform 0.5s ease-out;
}
.kp-profile-modern-container .animate-on-scroll.is-visible { opacity: 1; transform: translateY(0); }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const container = document.querySelector('.kp-profile-modern-container');
    if (!container) return;
    const animatedElements = container.querySelectorAll('.animate-on-scroll');
    const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry, index) => {
            if (entry.isIntersecting) {
                entry.target.style.transitionDelay = `${index * 100}ms`;
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
if (isset($conn) && ($conn instanceof mysqli)) {
    $conn->close();
}
?>