<?php
// /KP/dosen/profil.php (Versi Diperbarui)

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// 1. OTENTIKASI DAN OTORISASI
require_once '../includes/auth_check.php';
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'dosen') {
    session_unset();
    session_destroy();
    header("Location: /KP/index.php?error=unauthorized_dosen");
    exit();
}

$nip_dosen_login = $_SESSION['user_id'];
$dosen_data = null;
$error_message = '';

require_once '../config/db_connect.php';

// 2. AMBIL DATA PROFIL DOSEN
if ($conn && ($conn instanceof mysqli) && !$conn->connect_error) {
    $sql = "SELECT nip, nama_dosen, email, status_akun, created_at FROM dosen_pembimbing WHERE nip = ? LIMIT 1";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("s", $nip_dosen_login);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows === 1) {
            $dosen_data = $result->fetch_assoc();
        } else {
            $error_message = "Data profil dosen Anda tidak dapat ditemukan.";
        }
        $stmt->close();
    } else {
        $error_message = "Gagal menyiapkan query untuk mengambil data profil dosen.";
    }
} else {
    $error_message = "Koneksi database gagal.";
}

$page_title = "Profil Dosen";
if (is_array($dosen_data) && !empty($dosen_data['nama_dosen'])) {
    $page_title = "Profil: " . htmlspecialchars($dosen_data['nama_dosen']);
}
require_once '../includes/header.php';
?>

<div class="main-content-full">
    <div class="profile-page-container">
        
        <?php if (!empty($error_message)): ?>
            <div class="message error"><p><?php echo htmlspecialchars($error_message); ?></p></div>
        <?php endif; ?>

        <?php if (is_array($dosen_data)): ?>
            <div class="profile-card">
                <div class="profile-card-header">
                    <div class="profile-avatar">
                        <?php echo strtoupper(substr($dosen_data['nama_dosen'], 0, 1)); ?>
                    </div>
                    <div class="profile-name-role">
                        <h2><?php echo htmlspecialchars($dosen_data['nama_dosen']); ?></h2>
                        <p>Dosen Pembimbing / Penguji</p>
                    </div>
                    <a href="/KP/dosen/profil_edit.php" class="btn btn-primary-outline">
                        <i class="icon-pencil"></i> Edit Profil
                    </a>
                </div>
                <div class="profile-card-body">
                    <h3>Detail Informasi</h3>
                    <div class="details-grid">
                        <div class="detail-item">
                            <span class="detail-label"><i class="icon-detail"></i>NIP</span>
                            <span class="detail-value"><?php echo htmlspecialchars($dosen_data['nip']); ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label"><i class="icon-detail"></i>Email (Login)</span>
                            <span class="detail-value"><?php echo htmlspecialchars($dosen_data['email']); ?></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label"><i class="icon-detail"></i>Status Akun</span>
                            <span class="detail-value">
                                <span class="status-badge status-dosen-<?php echo strtolower(htmlspecialchars($dosen_data['status_akun'])); ?>">
                                    <?php echo ucfirst(htmlspecialchars($dosen_data['status_akun'])); ?>
                                </span>
                            </span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label"><i class="icon-detail"></i>Akun Dibuat</span>
                            <span class="detail-value"><?php echo date("d F Y", strtotime($dosen_data['created_at'])); ?></span>
                        </div>
                    </div>
                </div>
            </div>
        <?php elseif(empty($error_message)): ?>
            <div class="message info"><p>Memuat data profil...</p></div>
        <?php endif; ?>
        
    </div>
</div>

<style>
    .icon-pencil::before { content: "✏️ "; }
    .icon-detail::before { content: "🔹"; margin-right: 8px; color: var(--primary-color); }

    .main-content-full { padding: 2rem; }
    .profile-page-container {
        max-width: 900px;
        margin: auto;
    }
    .profile-card {
        background-color: #fff;
        border-radius: var(--border-radius);
        box-shadow: var(--card-shadow);
        overflow: hidden;
        animation: fadeIn 0.5s ease-in-out;
    }
    .profile-card-header {
        background: linear-gradient(135deg, #f8f9fa, #e9ecef);
        padding: 2rem;
        display: flex;
        align-items: center;
        gap: 1.5rem;
        border-bottom: 1px solid var(--border-color);
    }
    .profile-avatar {
        width: 80px;
        height: 80px;
        border-radius: 50%;
        background-color: var(--primary-color);
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 2.5em;
        font-weight: 600;
        flex-shrink: 0;
    }
    .profile-name-role {
        flex-grow: 1;
    }
    .profile-name-role h2 {
        margin: 0;
        font-size: 1.8em;
        color: var(--dark-color);
    }
    .profile-name-role p {
        margin: 0;
        color: var(--secondary-color);
        font-weight: 500;
    }
    .btn.btn-primary-outline {
        color: var(--primary-color);
        background-color: transparent;
        border: 2px solid var(--primary-color);
        font-weight: bold;
        padding: 8px 18px;
        border-radius: 8px;
        text-decoration: none;
        transition: all 0.3s ease;
    }
    .btn.btn-primary-outline:hover {
        background-color: var(--primary-color);
        color: #fff;
    }

    .profile-card-body {
        padding: 2rem;
    }
    .profile-card-body h3 {
        font-size: 1.4em;
        color: var(--dark-color);
        margin-bottom: 1.5rem;
        padding-bottom: 0.5rem;
        border-bottom: 1px solid var(--border-color);
    }
    .details-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 1.5rem;
    }
    .detail-item {
        background-color: #f8f9fa;
        padding: 1rem;
        border-radius: 8px;
    }
    .detail-label {
        display: block;
        font-size: 0.9em;
        color: var(--secondary-color);
        margin-bottom: 0.5rem;
        display: flex;
        align-items: center;
    }
    .detail-value {
        font-size: 1.1em;
        font-weight: 500;
        color: var(--dark-color);
    }
    .status-badge {
        padding: 5px 10px;
        border-radius: 20px;
        font-size: 0.9em;
        font-weight: 600;
        color: #fff;
    }
    .status-dosen-active { background-color: #28a745; }
    .status-dosen-inactive { background-color: #6c757d; }
</style>

<?php
require_once '../includes/footer.php';
if (isset($conn) && $conn) {
    $conn->close();
}
?>