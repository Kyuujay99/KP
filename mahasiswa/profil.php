<?php
// /KP/mahasiswa/profil.php (Versi Diperbarui)

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// 1. OTENTIKASI DAN OTORISASI
require_once '../includes/auth_check.php';
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'mahasiswa') {
    session_unset();
    session_destroy();
    header("Location: /KP/index.php?error=unauthorized");
    exit();
}

$nim_mahasiswa = $_SESSION['user_id'];
require_once '../config/db_connect.php';

$mahasiswa_data = null;
$error_db = '';

if ($conn) {
    $sql = "SELECT nim, nama, email, no_hp, prodi, angkatan, status_akun, created_at, ipk, sks_lulus FROM mahasiswa WHERE nim = ? LIMIT 1";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("s", $nim_mahasiswa);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows === 1) {
            $mahasiswa_data = $result->fetch_assoc();
        } else {
            $error_db = "Data profil tidak ditemukan.";
        }
        $stmt->close();
    } else {
        $error_db = "Gagal menyiapkan query: " . $conn->error;
    }
} else {
    $error_db = "Gagal terhubung ke database.";
}

$page_title = "Profil Mahasiswa";
if (is_array($mahasiswa_data) && !empty($mahasiswa_data['nama'])) {
    $page_title = "Profil: " . htmlspecialchars($mahasiswa_data['nama']);
}
require_once '../includes/header.php';
?>

<div class="main-content-full">
    <div class="profile-page-container">
        
        <?php if (!empty($error_db)): ?>
            <div class="message error"><p><?php echo htmlspecialchars($error_db); ?></p></div>
        <?php endif; ?>

        <?php if (is_array($mahasiswa_data)): ?>
            <div class="profile-card">
                <div class="profile-card-header">
                    <div class="profile-avatar">
                        <?php echo strtoupper(substr($mahasiswa_data['nama'], 0, 1)); ?>
                    </div>
                    <div class="profile-name-role">
                        <h2><?php echo htmlspecialchars($mahasiswa_data['nama']); ?></h2>
                        <p>Mahasiswa Program Studi <?php echo htmlspecialchars($mahasiswa_data['prodi']); ?></p>
                    </div>
                    <a href="/KP/mahasiswa/profil_edit.php" class="btn btn-primary-outline">
                        <i class="icon-pencil"></i> Edit Profil
                    </a>
                </div>
                <div class="profile-card-body">
                    <div class="details-section">
                        <h3><i class="icon-detail"></i>Informasi Pribadi & Kontak</h3>
                        <div class="details-grid">
                            <div class="detail-item"><span class="detail-label">NIM</span><span class="detail-value"><?php echo htmlspecialchars($mahasiswa_data['nim']); ?></span></div>
                            <div class="detail-item"><span class="detail-label">Email</span><span class="detail-value"><?php echo htmlspecialchars($mahasiswa_data['email']); ?></span></div>
                            <div class="detail-item"><span class="detail-label">Nomor HP</span><span class="detail-value"><?php echo htmlspecialchars($mahasiswa_data['no_hp'] ?: '-'); ?></span></div>
                        </div>
                    </div>
                    <div class="details-section">
                        <h3><i class="icon-detail"></i>Informasi Akademik</h3>
                        <div class="details-grid">
                            <div class="detail-item"><span class="detail-label">Program Studi</span><span class="detail-value"><?php echo htmlspecialchars($mahasiswa_data['prodi']); ?></span></div>
                            <div class="detail-item"><span class="detail-label">Angkatan</span><span class="detail-value"><?php echo htmlspecialchars($mahasiswa_data['angkatan']); ?></span></div>
                            <div class="detail-item"><span class="detail-label">SKS Lulus</span><span class="detail-value"><?php echo htmlspecialchars($mahasiswa_data['sks_lulus'] ?? '0'); ?></span></div>
                            <div class="detail-item"><span class="detail-label">IPK</span><span class="detail-value"><?php echo htmlspecialchars(number_format($mahasiswa_data['ipk'] ?? 0, 2)); ?></span></div>
                        </div>
                    </div>
                     <div class="details-section">
                        <h3><i class="icon-detail"></i>Informasi Akun</h3>
                        <div class="details-grid">
                            <div class="detail-item">
                                <span class="detail-label">Status Akun</span>
                                <span class="detail-value">
                                    <span class="status-badge status-<?php echo strtolower(htmlspecialchars($mahasiswa_data['status_akun'])); ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', htmlspecialchars($mahasiswa_data['status_akun']))); ?>
                                    </span>
                                </span>
                            </div>
                            <div class="detail-item"><span class="detail-label">Tanggal Terdaftar</span><span class="detail-value"><?php echo date("d F Y", strtotime($mahasiswa_data['created_at'])); ?></span></div>
                        </div>
                    </div>
                </div>
            </div>
        <?php elseif(empty($error_db)): ?>
            <div class="message info"><p>Memuat data profil...</p></div>
        <?php endif; ?>
        
    </div>
</div>

<style>
    .icon-pencil::before { content: "✏️ "; }
    .icon-detail::before { content: "🔹"; margin-right: 8px; }

    .main-content-full { padding: 2rem; }
    .profile-page-container { max-width: 900px; margin: auto; }
    .profile-card {
        background-color: #fff;
        border-radius: var(--border-radius);
        box-shadow: var(--card-shadow);
        overflow: hidden;
    }
    .profile-card-header {
        background: linear-gradient(135deg, #f8f9fa, #e9ecef);
        padding: 2rem;
        display: flex;
        align-items: center;
        gap: 1.5rem;
    }
    .profile-avatar {
        width: 80px; height: 80px;
        border-radius: 50%;
        background-color: var(--primary-color); color: white;
        display: flex; align-items: center; justify-content: center;
        font-size: 2.5em; font-weight: 600; flex-shrink: 0;
    }
    .profile-name-role { flex-grow: 1; }
    .profile-name-role h2 { margin: 0; font-size: 1.8em; color: var(--dark-color); }
    .profile-name-role p { margin: 0; color: var(--secondary-color); font-weight: 500; }
    .btn.btn-primary-outline {
        color: var(--primary-color); background-color: transparent; border: 2px solid var(--primary-color);
        font-weight: bold; padding: 8px 18px; border-radius: 8px; text-decoration: none;
        transition: all 0.3s ease;
    }
    .btn.btn-primary-outline:hover { background-color: var(--primary-color); color: #fff; }

    .profile-card-body { padding: 1rem 2rem 2rem; }
    .details-section { margin-top: 2rem; }
    .details-section h3 {
        font-size: 1.3em; color: var(--dark-color); margin-bottom: 1.5rem; padding-bottom: 0.5rem;
        border-bottom: 1px solid var(--border-color); display: flex; align-items: center; gap: 10px;
    }
    .details-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 1rem 2rem;
    }
    .detail-item {
        display: flex;
        flex-direction: column;
        gap: 0.25rem;
    }
    .detail-label { font-size: 0.9em; color: var(--secondary-color); }
    .detail-value { font-size: 1.1em; font-weight: 500; color: var(--dark-color); }

    .status-badge {
        padding: 5px 12px; border-radius: 20px; font-size: 0.9em; font-weight: 600;
        color: #fff; display: inline-block;
    }
    .status-pending_verification { background-color: #ffc107; color: #212529;}
    .status-active { background-color: #28a745; }
    .status-suspended { background-color: #dc3545; }
</style>

<?php
require_once '../includes/footer.php';
if (isset($conn) && $conn) {
    $conn->close();
}
?>