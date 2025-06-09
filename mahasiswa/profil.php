<?php
// /KP/mahasiswa/profil.php (Enhanced Version)

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
$success_message = '';
$error_message = '';

// Handle AJAX update request
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['ajax_update'])) {
    header('Content-Type: application/json');
    
    $new_email = trim($_POST['email']);
    $new_no_hp = trim($_POST['no_hp']);

    if (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Format email tidak valid.']);
        exit();
    }

    if ($conn) {
        $sql_check_email = "SELECT nim FROM mahasiswa WHERE email = ? AND nim != ?";
        $stmt_check_email = $conn->prepare($sql_check_email);
        $stmt_check_email->bind_param("ss", $new_email, $nim_mahasiswa);
        $stmt_check_email->execute();
        $stmt_check_email->store_result();

        if ($stmt_check_email->num_rows > 0) {
            echo json_encode(['success' => false, 'message' => 'Email tersebut sudah digunakan oleh mahasiswa lain.']);
        } else {
            $sql_update = "UPDATE mahasiswa SET email = ?, no_hp = ? WHERE nim = ?";
            $stmt_update = $conn->prepare($sql_update);
            $stmt_update->bind_param("sss", $new_email, $new_no_hp, $nim_mahasiswa);
            if ($stmt_update->execute()) {
                echo json_encode(['success' => true, 'message' => 'Profil berhasil diperbarui!']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Gagal memperbarui profil.']);
            }
            $stmt_update->close();
        }
        $stmt_check_email->close();
    } else {
        echo json_encode(['success' => false, 'message' => 'Gagal terhubung ke database.']);
    }
    exit();
}

// Get profile data
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
    <div class="profile-container">
        
        <?php if (!empty($error_db)): ?>
            <div class="alert alert-error fade-in">
                <i class="icon-alert-circle"></i>
                <span><?php echo htmlspecialchars($error_db); ?></span>
            </div>
        <?php endif; ?>

        <?php if (is_array($mahasiswa_data)): ?>
            <!-- Profile Header Card -->
            <div class="profile-header-card fade-in">
                <div class="profile-background"></div>
                <div class="profile-content">
                    <div class="profile-avatar-section">
                        <div class="profile-avatar">
                            <span><?php echo strtoupper(substr($mahasiswa_data['nama'], 0, 1)); ?></span>
                            <div class="avatar-ring"></div>
                        </div>
                        <div class="profile-status">
                            <span class="status-badge status-<?php echo strtolower(htmlspecialchars($mahasiswa_data['status_akun'])); ?>">
                                <i class="status-icon"></i>
                                <?php echo ucfirst(str_replace('_', ' ', htmlspecialchars($mahasiswa_data['status_akun']))); ?>
                            </span>
                        </div>
                    </div>
                    <div class="profile-info">
                        <h1 class="profile-name"><?php echo htmlspecialchars($mahasiswa_data['nama']); ?></h1>
                        <p class="profile-subtitle"><?php echo htmlspecialchars($mahasiswa_data['prodi']); ?> • Angkatan <?php echo htmlspecialchars($mahasiswa_data['angkatan']); ?></p>
                        <p class="profile-nim">NIM: <?php echo htmlspecialchars($mahasiswa_data['nim']); ?></p>
                    </div>
                    <div class="profile-actions">
                        <button class="btn btn-primary btn-edit" onclick="openEditModal()">
                            <i class="icon-edit"></i>
                            <span>Edit Profil</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Stats Cards -->
            <div class="stats-grid">
                <div class="stat-card fade-in" style="animation-delay: 0.1s">
                    <div class="stat-icon">
                        <i class="icon-graduation-cap"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo htmlspecialchars(number_format($mahasiswa_data['ipk'] ?? 0, 2)); ?></h3>
                        <p>IPK</p>
                    </div>
                </div>
                <div class="stat-card fade-in" style="animation-delay: 0.2s">
                    <div class="stat-icon">
                        <i class="icon-book"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo htmlspecialchars($mahasiswa_data['sks_lulus'] ?? '0'); ?></h3>
                        <p>SKS Lulus</p>
                    </div>
                </div>
                <div class="stat-card fade-in" style="animation-delay: 0.3s">
                    <div class="stat-icon">
                        <i class="icon-calendar"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo htmlspecialchars($mahasiswa_data['angkatan']); ?></h3>
                        <p>Angkatan</p>
                    </div>
                </div>
            </div>

            <!-- Details Cards -->
            <div class="details-grid">
                <div class="detail-card fade-in" style="animation-delay: 0.4s">
                    <div class="card-header">
                        <h3><i class="icon-user"></i>Informasi Pribadi</h3>
                    </div>
                    <div class="card-body">
                        <div class="detail-row">
                            <span class="detail-label">Nama Lengkap</span>
                            <span class="detail-value"><?php echo htmlspecialchars($mahasiswa_data['nama']); ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Email</span>
                            <span class="detail-value"><?php echo htmlspecialchars($mahasiswa_data['email']); ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Nomor HP</span>
                            <span class="detail-value"><?php echo htmlspecialchars($mahasiswa_data['no_hp'] ?: 'Belum diisi'); ?></span>
                        </div>
                    </div>
                </div>

                <div class="detail-card fade-in" style="animation-delay: 0.5s">
                    <div class="card-header">
                        <h3><i class="icon-academic-cap"></i>Informasi Akademik</h3>
                    </div>
                    <div class="card-body">
                        <div class="detail-row">
                            <span class="detail-label">Program Studi</span>
                            <span class="detail-value"><?php echo htmlspecialchars($mahasiswa_data['prodi']); ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">NIM</span>
                            <span class="detail-value"><?php echo htmlspecialchars($mahasiswa_data['nim']); ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Terdaftar Sejak</span>
                            <span class="detail-value"><?php echo date("d F Y", strtotime($mahasiswa_data['created_at'])); ?></span>
                        </div>
                    </div>
                </div>
            </div>
        <?php elseif(empty($error_db)): ?>
            <div class="loading-state">
                <div class="loading-spinner"></div>
                <p>Memuat data profil...</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Edit Profile Modal -->
<div id="editModal" class="modal-overlay">
    <div class="modal-container">
        <div class="modal-header">
            <h2><i class="icon-edit"></i>Edit Profil</h2>
            <button class="modal-close" onclick="closeEditModal()">&times;</button>
        </div>
        <div class="modal-body">
            <div id="modalMessage" class="modal-message" style="display: none;"></div>
            <form id="editForm">
                <div class="form-section">
                    <h4><i class="icon-contact"></i>Informasi Kontak</h4>
                    <p class="form-description">Anda dapat mengubah informasi kontak di bawah ini</p>
                    
                    <div class="form-group">
                        <label for="modal_email">Email *</label>
                        <input type="email" id="modal_email" name="email" value="<?php echo htmlspecialchars($mahasiswa_data['email'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="modal_no_hp">Nomor HP</label>
                        <input type="text" id="modal_no_hp" name="no_hp" value="<?php echo htmlspecialchars($mahasiswa_data['no_hp'] ?? ''); ?>">
                    </div>
                </div>
                
                <div class="form-section readonly-section">
                    <h4><i class="icon-lock"></i>Data Akademik (Tidak dapat diubah)</h4>
                    <div class="readonly-grid">
                        <div class="readonly-item">
                            <label>NIM</label>
                            <span><?php echo htmlspecialchars($mahasiswa_data['nim'] ?? ''); ?></span>
                        </div>
                        <div class="readonly-item">
                            <label>Nama</label>
                            <span><?php echo htmlspecialchars($mahasiswa_data['nama'] ?? ''); ?></span>
                        </div>
                        <div class="readonly-item">
                            <label>Program Studi</label>
                            <span><?php echo htmlspecialchars($mahasiswa_data['prodi'] ?? ''); ?></span>
                        </div>
                        <div class="readonly-item">
                            <label>Angkatan</label>
                            <span><?php echo htmlspecialchars($mahasiswa_data['angkatan'] ?? ''); ?></span>
                        </div>
                    </div>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeEditModal()">Batal</button>
            <button type="button" class="btn btn-primary btn-loading" onclick="saveProfile()">
                <span class="btn-text">Simpan Perubahan</span>
                <span class="btn-spinner" style="display: none;"></span>
            </button>
        </div>
    </div>
</div>

<style>
/* Icons */
.icon-edit::before { content: "✏️"; margin-right: 8px; }
.icon-user::before { content: "👤"; margin-right: 8px; }
.icon-academic-cap::before { content: "🎓"; margin-right: 8px; }
.icon-graduation-cap::before { content: "🎓"; }
.icon-book::before { content: "📚"; }
.icon-calendar::before { content: "📅"; }
.icon-contact::before { content: "📧"; margin-right: 8px; }
.icon-lock::before { content: "🔒"; margin-right: 8px; }
.icon-alert-circle::before { content: "⚠️"; margin-right: 8px; }

/* Variables */
:root {
    --primary-color: #3b82f6;
    --primary-dark: #2563eb;
    --success-color: #10b981;
    --warning-color: #f59e0b;
    --error-color: #ef4444;
    --secondary-color: #6b7280;
    --dark-color: #1f2937;
    --border-color: #e5e7eb;
    --background-color: #f8fafc;
    --card-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
    --border-radius: 12px;
}

/* Main Layout */
.main-content-full {
    padding: 2rem;
    background: var(--background-color);
    min-height: 100vh;
}

.profile-container {
    max-width: 1200px;
    margin: 0 auto;
}

/* Animations */
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}

@keyframes slideIn {
    from { opacity: 0; transform: scale(0.95); }
    to { opacity: 1; transform: scale(1); }
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

.fade-in {
    animation: fadeIn 0.6s ease-out forwards;
}

/* Profile Header Card */
.profile-header-card {
    background: white;
    border-radius: var(--border-radius);
    box-shadow: var(--card-shadow);
    overflow: hidden;
    position: relative;
    margin-bottom: 2rem;
}

.profile-background {
    height: 120px;
    background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
    position: relative;
}

.profile-background::after {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grid" width="10" height="10" patternUnits="userSpaceOnUse"><path d="M 10 0 L 0 0 0 10" fill="none" stroke="white" stroke-width="0.5" opacity="0.1"/></pattern></defs><rect width="100" height="100" fill="url(%23grid)"/></svg>');
}

.profile-content {
    padding: 0 2rem 2rem;
    display: flex;
    align-items: flex-start;
    gap: 2rem;
    position: relative;
}

.profile-avatar-section {
    display: flex;
    flex-direction: column;
    align-items: center;
    margin-top: -50px;
}

.profile-avatar {
    width: 100px;
    height: 100px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2.5rem;
    font-weight: 700;
    color: white;
    position: relative;
    border: 4px solid white;
    box-shadow: var(--card-shadow);
}

.avatar-ring {
    position: absolute;
    inset: -8px;
    border-radius: 50%;
    background: linear-gradient(45deg, var(--primary-color), var(--success-color));
    z-index: -1;
    opacity: 0.2;
}

.profile-status {
    margin-top: 1rem;
}

.profile-info {
    flex: 1;
    padding-top: 1rem;
}

.profile-name {
    font-size: 2rem;
    font-weight: 700;
    color: var(--dark-color);
    margin: 0 0 0.5rem 0;
}

.profile-subtitle {
    font-size: 1.1rem;
    color: var(--secondary-color);
    margin: 0 0 0.25rem 0;
    font-weight: 500;
}

.profile-nim {
    font-size: 0.95rem;
    color: var(--secondary-color);
    margin: 0;
    font-family: monospace;
    background: var(--background-color);
    padding: 4px 8px;
    border-radius: 6px;
    display: inline-block;
}

.profile-actions {
    padding-top: 1rem;
}

/* Status Badge */
.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 0.85rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.status-icon {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    display: inline-block;
}

.status-active {
    background: rgba(16, 185, 129, 0.1);
    color: var(--success-color);
}

.status-active .status-icon {
    background: var(--success-color);
    animation: pulse 2s infinite;
}

.status-pending_verification {
    background: rgba(245, 158, 11, 0.1);
    color: var(--warning-color);
}

.status-pending_verification .status-icon {
    background: var(--warning-color);
}

.status-suspended {
    background: rgba(239, 68, 68, 0.1);
    color: var(--error-color);
}

.status-suspended .status-icon {
    background: var(--error-color);
}

@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.5; }
}

/* Stats Grid */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.stat-card {
    background: white;
    padding: 1.5rem;
    border-radius: var(--border-radius);
    box-shadow: var(--card-shadow);
    display: flex;
    align-items: center;
    gap: 1rem;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px -5px rgba(0, 0, 0, 0.1);
}

.stat-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    color: white;
}

.stat-info h3 {
    font-size: 1.8rem;
    font-weight: 700;
    color: var(--dark-color);
    margin: 0 0 0.25rem 0;
}

.stat-info p {
    font-size: 0.9rem;
    color: var(--secondary-color);
    margin: 0;
    font-weight: 500;
}

/* Details Grid */
.details-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
    gap: 2rem;
}

.detail-card {
    background: white;
    border-radius: var(--border-radius);
    box-shadow: var(--card-shadow);
    overflow: hidden;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.detail-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px -5px rgba(0, 0, 0, 0.1);
}

.card-header {
    padding: 1.5rem;
    background: var(--background-color);
    border-bottom: 1px solid var(--border-color);
}

.card-header h3 {
    font-size: 1.2rem;
    font-weight: 600;
    color: var(--dark-color);
    margin: 0;
    display: flex;
    align-items: center;
}

.card-body {
    padding: 1.5rem;
}

.detail-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1rem 0;
    border-bottom: 1px solid var(--border-color);
}

.detail-row:last-child {
    border-bottom: none;
}

.detail-label {
    font-weight: 500;
    color: var(--secondary-color);
    font-size: 0.9rem;
}

.detail-value {
    font-weight: 600;
    color: var(--dark-color);
    text-align: right;
}

/* Buttons */
.btn {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.75rem 1.5rem;
    border-radius: 8px;
    font-weight: 600;
    text-decoration: none;
    border: none;
    cursor: pointer;
    transition: all 0.2s ease;
    font-size: 0.9rem;
}

.btn-primary {
    background: var(--primary-color);
    color: white;
}

.btn-primary:hover {
    background: var(--primary-dark);
    transform: translateY(-1px);
}

.btn-secondary {
    background: var(--background-color);
    color: var(--secondary-color);
    border: 1px solid var(--border-color);
}

.btn-secondary:hover {
    background: white;
    color: var(--dark-color);
}

.btn-edit {
    position: relative;
    overflow: hidden;
}

.btn-edit::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
    transition: left 0.5s;
}

.btn-edit:hover::before {
    left: 100%;
}

/* Modal */
.modal-overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    backdrop-filter: blur(4px);
    z-index: 1000;
    animation: fadeIn 0.3s ease-out;
}

.modal-overlay.active {
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 2rem;
}

.modal-container {
    background: white;
    border-radius: var(--border-radius);
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
    width: 100%;
    max-width: 600px;
    max-height: 90vh;
    overflow: hidden;
    animation: slideIn 0.3s ease-out;
}

.modal-header {
    padding: 1.5rem;
    border-bottom: 1px solid var(--border-color);
    display: flex;
    align-items: center;
    justify-content: space-between;
    background: var(--background-color);
}

.modal-header h2 {
    margin: 0;
    font-size: 1.4rem;
    color: var(--dark-color);
    display: flex;
    align-items: center;
}

.modal-close {
    background: none;
    border: none;
    font-size: 1.5rem;
    color: var(--secondary-color);
    cursor: pointer;
    padding: 0.5rem;
    border-radius: 50%;
    transition: all 0.2s ease;
}

.modal-close:hover {
    background: var(--border-color);
    color: var(--dark-color);
}

.modal-body {
    padding: 2rem;
    max-height: 60vh;
    overflow-y: auto;
}

.modal-footer {
    padding: 1.5rem;
    border-top: 1px solid var(--border-color);
    display: flex;
    justify-content: flex-end;
    gap: 1rem;
    background: var(--background-color);
}

/* Form Styles */
.form-section {
    margin-bottom: 2rem;
}

.form-section h4 {
    font-size: 1.1rem;
    font-weight: 600;
    color: var(--dark-color);
    margin: 0 0 0.5rem 0;
    display: flex;
    align-items: center;
}

.form-description {
    color: var(--secondary-color);
    font-size: 0.9rem;
    margin: 0 0 1.5rem 0;
}

.form-group {
    margin-bottom: 1.5rem;
}

.form-group label {
    display: block;
    font-weight: 500;
    color: var(--dark-color);
    margin-bottom: 0.5rem;
    font-size: 0.9rem;
}

.form-group input {
    width: 100%;
    padding: 0.75rem 1rem;
    border: 2px solid var(--border-color);
    border-radius: 8px;
    font-size: 1rem;
    transition: all 0.2s ease;
    background: white;
}

.form-group input:focus {
    outline: none;
    border-color: var(--primary-color);
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.readonly-section {
    background: var(--background-color);
    padding: 1.5rem;
    border-radius: 8px;
    border: 1px solid var(--border-color);
}

.readonly-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
}

.readonly-item {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.readonly-item label {
    font-size: 0.8rem;
    color: var(--secondary-color);
    font-weight: 500;
}

.readonly-item span {
    font-weight: 600;
    color: var(--dark-color);
}

/* Loading States */
.loading-state {
    text-align: center;
    padding: 4rem 2rem;
}

.loading-spinner {
    width: 40px;
    height: 40px;
    border: 3px solid var(--border-color);
    border-top: 3px solid var(--primary-color);
    border-radius: 50%;
    animation: spin 1s linear infinite;
    margin: 0 auto 1rem;
}

.btn-loading.loading .btn-text {
    opacity: 0;
}

.btn-loading.loading .btn-spinner {
    display: inline-block !important;
    width: 16px;
    height: 16px;
    border: 2px solid rgba(255,255,255,0.3);
    border-top: 2px solid white;
    border-radius: 50%;
    animation: spin 1s linear infinite;
}
.alert {
    padding: 1rem 1.5rem;
    border-radius: 8px;
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    font-weight: 500;
}

.alert-error {
    background: rgba(239, 68, 68, 0.1);
    color: var(--error-color);
    border: 1px solid rgba(239, 68, 68, 0.2);
}

.alert-success {
    background: rgba(16, 185, 129, 0.1);
    color: var(--success-color);
    border: 1px solid rgba(16, 185, 129, 0.2);
}

.alert-warning {
    background: rgba(245, 158, 11, 0.1);
    color: var(--warning-color);
    border: 1px solid rgba(245, 158, 11, 0.2);
}

.alert-info {
    background: rgba(59, 130, 246, 0.1);
    color: var(--primary-color);
    border: 1px solid rgba(59, 130, 246, 0.2);
}

/* Modal Message */
.modal-message {
    padding: 1rem;
    border-radius: 6px;
    margin-bottom: 1.5rem;
    font-weight: 500;
}

.modal-message.success {
    background: rgba(16, 185, 129, 0.1);
    color: var(--success-color);
    border: 1px solid rgba(16, 185, 129, 0.2);
}

.modal-message.error {
    background: rgba(239, 68, 68, 0.1);
    color: var(--error-color);
    border: 1px solid rgba(239, 68, 68, 0.2);
}

/* Responsive Design */
@media (max-width: 768px) {
    .main-content-full {
        padding: 1rem;
    }
    
    .profile-content {
        flex-direction: column;
        text-align: center;
        gap: 1rem;
    }
    
    .profile-info {
        padding-top: 0;
    }
    
    .profile-name {
        font-size: 1.5rem;
    }
    
    .stats-grid {
        grid-template-columns: 1fr;
        gap: 1rem;
    }
    
    .details-grid {
        grid-template-columns: 1fr;
        gap: 1.5rem;
    }
    
    .detail-card {
        min-width: auto;
    }
    
    .detail-row {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.5rem;
    }
    
    .detail-value {
        text-align: left;
    }
    
    .readonly-grid {
        grid-template-columns: 1fr;
    }
    
    .modal-container {
        margin: 1rem;
        max-width: calc(100% - 2rem);
    }
    
    .modal-body {
        padding: 1.5rem;
    }
    
    .modal-footer {
        flex-direction: column-reverse;
        gap: 0.75rem;
    }
    
    .btn {
        width: 100%;
        justify-content: center;
    }
}

@media (max-width: 480px) {
    .profile-avatar {
        width: 80px;
        height: 80px;
        font-size: 2rem;
    }
    
    .profile-avatar-section {
        margin-top: -40px;
    }
    
    .profile-background {
        height: 100px;
    }
    
    .stat-card {
        padding: 1rem;
    }
    
    .stat-icon {
        width: 40px;
        height: 40px;
        font-size: 1.2rem;
    }
    
    .stat-info h3 {
        font-size: 1.5rem;
    }
}

/* Dark mode support (optional) */
@media (prefers-color-scheme: dark) {
    :root {
        --background-color: #0f172a;
        --dark-color: #f8fafc;
        --secondary-color: #94a3b8;
        --border-color: #334155;
        --card-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.3), 0 2px 4px -1px rgba(0, 0, 0, 0.2);
    }
    
    .profile-header-card,
    .stat-card,
    .detail-card,
    .modal-container {
        background: #1e293b;
    }
    
    .card-header,
    .modal-header,
    .modal-footer,
    .readonly-section {
        background: #0f172a;
    }
    
    .form-group input {
        background: #1e293b;
        color: #f8fafc;
    }
    
    .profile-nim {
        background: #0f172a;
    }
}

/* Print styles */
@media print {
    .main-content-full {
        background: white;
        padding: 0;
    }
    
    .profile-actions,
    .btn-edit {
        display: none;
    }
    
    .profile-header-card,
    .stat-card,
    .detail-card {
        box-shadow: none;
        border: 1px solid #e5e7eb;
    }
    
    .fade-in {
        animation: none;
    }
}

/* Accessibility improvements */
@media (prefers-reduced-motion: reduce) {
    * {
        animation-duration: 0.01ms !important;
        animation-iteration-count: 1 !important;
        transition-duration: 0.01ms !important;
    }
    
    .fade-in {
        animation: none;
    }
}

/* Focus styles for better accessibility */
.btn:focus,
.form-group input:focus,
.modal-close:focus {
    outline: 2px solid var(--primary-color);
    outline-offset: 2px;
}
</style>

<script>
// Modal Functions
function openEditModal() {
    const modal = document.getElementById('editModal');
    const modalMessage = document.getElementById('modalMessage');
    
    // Reset form and hide message
    modalMessage.style.display = 'none';
    modalMessage.className = 'modal-message';
    
    // Show modal with animation
    modal.classList.add('active');
    
    // Focus on first input
    setTimeout(() => {
        const firstInput = modal.querySelector('input:not([readonly])');
        if (firstInput) {
            firstInput.focus();
        }
    }, 300);
    
    // Prevent body scroll
    document.body.style.overflow = 'hidden';
}

function closeEditModal() {
    const modal = document.getElementById('editModal');
    const modalMessage = document.getElementById('modalMessage');
    
    // Hide message
    modalMessage.style.display = 'none';
    
    // Hide modal
    modal.classList.remove('active');
    
    // Restore body scroll
    document.body.style.overflow = '';
    
    // Reset button state
    const saveBtn = document.querySelector('.btn-loading');
    saveBtn.classList.remove('loading');
    saveBtn.disabled = false;
}

// Save Profile Function
function saveProfile() {
    const form = document.getElementById('editForm');
    const saveBtn = document.querySelector('.btn-loading');
    const modalMessage = document.getElementById('modalMessage');
    
    // Get form data
    const formData = new FormData();
    formData.append('ajax_update', '1');
    formData.append('email', document.getElementById('modal_email').value.trim());
    formData.append('no_hp', document.getElementById('modal_no_hp').value.trim());
    
    // Validate email
    const email = formData.get('email');
    if (!email || !isValidEmail(email)) {
        showModalMessage('Format email tidak valid.', 'error');
        return;
    }
    
    // Set loading state
    saveBtn.classList.add('loading');
    saveBtn.disabled = true;
    
    // Hide previous messages
    modalMessage.style.display = 'none';
    
    // Send AJAX request
    fetch(window.location.href, {
        method: 'POST',
        body: formData,
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Network response was not ok');
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            showModalMessage(data.message, 'success');
            
            // Update page data
            updatePageData();
            
            // Close modal after delay
            setTimeout(() => {
                closeEditModal();
                
                // Show page-level success message
                showPageMessage(data.message, 'success');
            }, 1500);
        } else {
            showModalMessage(data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showModalMessage('Terjadi kesalahan saat menyimpan data. Silakan coba lagi.', 'error');
    })
    .finally(() => {
        // Reset button state
        saveBtn.classList.remove('loading');
        saveBtn.disabled = false;
    });
}

// Helper function to validate email
function isValidEmail(email) {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
}

// Show modal message
function showModalMessage(message, type) {
    const modalMessage = document.getElementById('modalMessage');
    modalMessage.textContent = message;
    modalMessage.className = `modal-message ${type}`;
    modalMessage.style.display = 'block';
    
    // Scroll to message
    modalMessage.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

// Show page-level message
function showPageMessage(message, type) {
    // Remove existing alerts
    const existingAlerts = document.querySelectorAll('.alert');
    existingAlerts.forEach(alert => alert.remove());
    
    // Create new alert
    const alert = document.createElement('div');
    alert.className = `alert alert-${type} fade-in`;
    alert.innerHTML = `
        <i class="icon-${type === 'success' ? 'check' : 'alert'}-circle"></i>
        <span>${message}</span>
    `;
    
    // Insert at top of profile container
    const profileContainer = document.querySelector('.profile-container');
    profileContainer.insertBefore(alert, profileContainer.firstChild);
    
    // Auto-hide after 5 seconds
    setTimeout(() => {
        alert.style.opacity = '0';
        alert.style.transform = 'translateY(-20px)';
        setTimeout(() => alert.remove(), 300);
    }, 5000);
}

// Update page data after successful save
function updatePageData() {
    const newEmail = document.getElementById('modal_email').value.trim();
    const newNoHp = document.getElementById('modal_no_hp').value.trim();
    
    // Update email in detail cards
    const emailValue = document.querySelector('.detail-row:has(.detail-label:contains("Email")) .detail-value');
    if (emailValue) {
        emailValue.textContent = newEmail;
    }
    
    // Update phone number in detail cards
    const phoneValue = document.querySelector('.detail-row:has(.detail-label:contains("Nomor HP")) .detail-value');
    if (phoneValue) {
        phoneValue.textContent = newNoHp || 'Belum diisi';
    }
}

// Close modal when clicking outside
document.addEventListener('click', function(event) {
    const modal = document.getElementById('editModal');
    const modalContainer = document.querySelector('.modal-container');
    
    if (event.target === modal) {
        closeEditModal();
    }
});

// Close modal with Escape key
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        const modal = document.getElementById('editModal');
        if (modal.classList.contains('active')) {
            closeEditModal();
        }
    }
});

// Form submission with Enter key
document.addEventListener('keydown', function(event) {
    if (event.key === 'Enter' && event.target.closest('#editForm')) {
        event.preventDefault();
        saveProfile();
    }
});

// Auto-resize phone number input
document.getElementById('modal_no_hp').addEventListener('input', function(e) {
    // Remove non-numeric characters except + and -
    let value = e.target.value.replace(/[^\d+\-\s]/g, '');
    
    // Format Indonesian phone number
    if (value.startsWith('08')) {
        value = '+62' + value.substring(1);
    }
    
    e.target.value = value;
});

// Add loading animation to page elements
document.addEventListener('DOMContentLoaded', function() {
    // Add stagger animation to cards
    const cards = document.querySelectorAll('.fade-in');
    cards.forEach((card, index) => {
        card.style.animationDelay = `${index * 0.1}s`;
    });
    
    // Add hover effects to interactive elements
    const interactiveElements = document.querySelectorAll('.stat-card, .detail-card, .btn');
    interactiveElements.forEach(element => {
        element.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-2px)';
        });
        
        element.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0)';
        });
    });
    
    // Initialize tooltips for status badge
    const statusBadge = document.querySelector('.status-badge');
    if (statusBadge) {
        statusBadge.title = getStatusDescription(statusBadge.textContent.trim().toLowerCase());
    }
});

// Get status description for tooltip
function getStatusDescription(status) {
    const descriptions = {
        'active': 'Akun aktif dan dapat mengakses semua fitur',
        'pending verification': 'Akun menunggu verifikasi dari admin',
        'suspended': 'Akun dinonaktifkan sementara'
    };
    return descriptions[status] || 'Status tidak diketahui';
}

// Add visual feedback for form validation
document.getElementById('modal_email').addEventListener('blur', function() {
    const email = this.value.trim();
    if (email && !isValidEmail(email)) {
        this.style.borderColor = 'var(--error-color)';
        this.style.boxShadow = '0 0 0 3px rgba(239, 68, 68, 0.1)';
    } else {
        this.style.borderColor = 'var(--border-color)';
        this.style.boxShadow = 'none';
    }
});

// Real-time email validation
document.getElementById('modal_email').addEventListener('input', function() {
    const email = this.value.trim();
    if (email && isValidEmail(email)) {
        this.style.borderColor = 'var(--success-color)';
        this.style.boxShadow = '0 0 0 3px rgba(16, 185, 129, 0.1)';
    } else if (email) {
        this.style.borderColor = 'var(--error-color)';
        this.style.boxShadow = '0 0 0 3px rgba(239, 68, 68, 0.1)';
    } else {
        this.style.borderColor = 'var(--border-color)';
        this.style.boxShadow = 'none';
    }
});

// Console log for debugging (remove in production)
console.log('Profile page loaded successfully');
</script>

<?php
// Include footer
require_once '../includes/footer.php';
?>