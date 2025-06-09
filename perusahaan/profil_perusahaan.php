<?php
// /KP/perusahaan/profil_perusahaan.php (Enhanced Version with Edit Modal)

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
require_once '../config/db_connect.php';

$perusahaan_data = null;
$error_db = '';
$jumlah_mahasiswa_aktif = 0;
$jumlah_mahasiswa_total = 0;

// Handle AJAX update request
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['ajax_update'])) {
    header('Content-Type: application/json');
    
    $nama_perusahaan = trim($_POST['nama_perusahaan']);
    $alamat = trim($_POST['alamat']);
    $bidang = trim($_POST['bidang']);
    $kontak_nama = trim($_POST['kontak_person_nama']);
    $kontak_email = trim($_POST['kontak_person_email']);
    $kontak_no_hp = trim($_POST['kontak_person_no_hp']);
    $password_baru = $_POST['password_baru'];

    if (empty($nama_perusahaan)) {
        echo json_encode(['success' => false, 'message' => 'Nama Perusahaan wajib diisi.']);
        exit();
    }
    if (!empty($kontak_email) && !filter_var($kontak_email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Format Email Kontak Person tidak valid.']);
        exit();
    }

    if ($conn) {
        $sql_parts = "nama_perusahaan = ?, alamat = ?, bidang = ?, kontak_person_nama = ?, kontak_person_email = ?, kontak_person_no_hp = ?";
        $params = [$nama_perusahaan, $alamat, $bidang, $kontak_nama, $kontak_email, $kontak_no_hp];
        $types = "ssssss";

        if (!empty($password_baru)) {
            if (strlen($password_baru) < 6) {
                echo json_encode(['success' => false, 'message' => 'Password baru minimal harus 6 karakter.']);
                exit();
            }
            $sql_parts .= ", password_perusahaan = ?";
            // Sesuai sistem yang ada, password disimpan sebagai plain text
            $params[] = $password_baru;
            $types .= "s";
        }

        $sql_update = "UPDATE perusahaan SET $sql_parts WHERE id_perusahaan = ?";
        $params[] = $id_perusahaan_login;
        $types .= "i";
        
        $stmt_update = $conn->prepare($sql_update);
        $stmt_update->bind_param($types, ...$params);

        if ($stmt_update->execute()) {
            $_SESSION['user_nama'] = $nama_perusahaan; // Update nama di session
            echo json_encode(['success' => true, 'message' => 'Profil perusahaan berhasil diperbarui!']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Gagal memperbarui profil.']);
        }
        $stmt_update->close();
    } else {
        echo json_encode(['success' => false, 'message' => 'Gagal terhubung ke database.']);
    }
    exit();
}


// Get profile and stats data
if ($conn) {
    // Get profile data
    $stmt_profil = $conn->prepare("SELECT * FROM perusahaan WHERE id_perusahaan = ? LIMIT 1");
    if ($stmt_profil) {
        $stmt_profil->bind_param("i", $id_perusahaan_login);
        $stmt_profil->execute();
        $result_profil = $stmt_profil->get_result();
        if ($result_profil->num_rows === 1) {
            $perusahaan_data = $result_profil->fetch_assoc();

            // Get stats: Mahasiswa Aktif
            $stmt_aktif = $conn->prepare("SELECT COUNT(id_pengajuan) as total FROM pengajuan_kp WHERE id_perusahaan = ? AND status_pengajuan = 'kp_berjalan'");
            $stmt_aktif->bind_param("i", $id_perusahaan_login);
            $stmt_aktif->execute();
            $jumlah_mahasiswa_aktif = $stmt_aktif->get_result()->fetch_assoc()['total'] ?? 0;
            $stmt_aktif->close();

            // Get stats: Total Mahasiswa Pernah Diterima
            $status_pernah_kp = ['kp_berjalan', 'selesai_pelaksanaan', 'laporan_disetujui', 'selesai_dinilai', 'diterima_perusahaan'];
            $placeholders = implode(',', array_fill(0, count($status_pernah_kp), '?'));
            $sql_total = "SELECT COUNT(id_pengajuan) as total FROM pengajuan_kp WHERE id_perusahaan = ? AND status_pengajuan IN ($placeholders)";
            $stmt_total = $conn->prepare($sql_total);
            $types = 'i' . str_repeat('s', count($status_pernah_kp));
            $stmt_total->bind_param($types, $id_perusahaan_login, ...$status_pernah_kp);
            $stmt_total->execute();
            $jumlah_mahasiswa_total = $stmt_total->get_result()->fetch_assoc()['total'] ?? 0;
            $stmt_total->close();

        } else {
            $error_db = "Data perusahaan Anda tidak dapat ditemukan.";
        }
        $stmt_profil->close();
    } else {
        $error_db = "Gagal menyiapkan query.";
    }
} else {
    $error_db = "Gagal terhubung ke database.";
}

$page_title = "Profil Perusahaan";
if ($perusahaan_data) {
    $page_title = htmlspecialchars($perusahaan_data['nama_perusahaan']);
}
require_once '../includes/header.php';
?>

<div class="main-content-full">
    <div class="profile-container">
        
        <div id="pageMessageContainer"></div>

        <?php if (!empty($error_db)): ?>
            <div class="alert alert-error fade-in"><span><?php echo htmlspecialchars($error_db); ?></span></div>
        <?php endif; ?>

        <?php if (is_array($perusahaan_data)): ?>
            <!-- Profile Header Card -->
            <div class="profile-header-card fade-in">
                <div class="profile-background"></div>
                <div class="profile-content">
                    <div class="profile-avatar-section">
                        <div class="profile-avatar">
                            <span><?php echo strtoupper(substr($perusahaan_data['nama_perusahaan'], 0, 1)); ?></span>
                        </div>
                        <div class="profile-status">
                             <span class="status-badge status-<?php echo strtolower(htmlspecialchars($perusahaan_data['status_akun'])); ?>">
                                 <i class="status-icon"></i>
                                 <?php echo ucfirst(str_replace('_', ' ', htmlspecialchars($perusahaan_data['status_akun']))); ?>
                             </span>
                        </div>
                    </div>
                    <div class="profile-info">
                        <h1 class="profile-name"><?php echo htmlspecialchars($perusahaan_data['nama_perusahaan']); ?></h1>
                        <p class="profile-subtitle">Mitra Kerja Praktek</p>
                        <p class="profile-nim">ID Perusahaan: <?php echo htmlspecialchars($perusahaan_data['id_perusahaan']); ?></p>
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
                    <div class="stat-icon"><i class="icon-user-group"></i></div>
                    <div class="stat-info">
                        <h3><?php echo $jumlah_mahasiswa_aktif; ?></h3>
                        <p>Mahasiswa KP Aktif</p>
                    </div>
                </div>
                <div class="stat-card fade-in" style="animation-delay: 0.2s">
                    <div class="stat-icon"><i class="icon-check-circle"></i></div>
                    <div class="stat-info">
                        <h3><?php echo $jumlah_mahasiswa_total; ?></h3>
                        <p>Total Mahasiswa Diterima</p>
                    </div>
                </div>
            </div>
            
            <!-- Details Cards -->
            <div class="details-grid">
                <div class="detail-card fade-in" style="animation-delay: 0.4s">
                    <div class="card-header"><h3><i class="icon-office"></i>Informasi Perusahaan</h3></div>
                    <div class="card-body">
                        <div class="detail-row"><span class="detail-label">Email Login</span><span class="detail-value"><?php echo htmlspecialchars($perusahaan_data['email_perusahaan']); ?></span></div>
                        <div class="detail-row"><span class="detail-label">Bidang Usaha</span><span class="detail-value"><?php echo htmlspecialchars($perusahaan_data['bidang'] ?: '-'); ?></span></div>
                        <div class="detail-row"><span class="detail-label">Alamat</span><span class="detail-value"><?php echo nl2br(htmlspecialchars($perusahaan_data['alamat'] ?: '-')); ?></span></div>
                    </div>
                </div>
                <div class="detail-card fade-in" style="animation-delay: 0.5s">
                    <div class="card-header"><h3><i class="icon-contact"></i>Informasi Kontak Person (PIC)</h3></div>
                    <div class="card-body">
                        <div class="detail-row"><span class="detail-label">Nama Kontak</span><span class="detail-value"><?php echo htmlspecialchars($perusahaan_data['kontak_person_nama'] ?: '-'); ?></span></div>
                        <div class="detail-row"><span class="detail-label">Email Kontak</span><span class="detail-value"><?php echo htmlspecialchars($perusahaan_data['kontak_person_email'] ?: '-'); ?></span></div>
                        <div class="detail-row"><span class="detail-label">Nomor HP Kontak</span><span class="detail-value"><?php echo htmlspecialchars($perusahaan_data['kontak_person_no_hp'] ?: '-'); ?></span></div>
                    </div>
                </div>
            </div>
        <?php elseif(empty($error_db)): ?>
            <div class="loading-state"><div class="loading-spinner"></div><p>Memuat data profil...</p></div>
        <?php endif; ?>
    </div>
</div>

<!-- Edit Modal -->
<div id="editModal" class="modal-overlay">
    <div class="modal-container">
        <div class="modal-header">
            <h2><i class="icon-edit"></i>Edit Profil Perusahaan</h2>
            <button class="modal-close" onclick="closeEditModal()">&times;</button>
        </div>
        <div class="modal-body">
            <div id="modalMessage" class="modal-message" style="display: none;"></div>
            <form id="editForm">
                <div class="form-section">
                    <h4><i class="icon-office"></i>Informasi Utama</h4>
                    <div class="form-group">
                        <label for="modal_nama_perusahaan">Nama Perusahaan *</label>
                        <input type="text" id="modal_nama_perusahaan" name="nama_perusahaan" value="<?php echo htmlspecialchars($perusahaan_data['nama_perusahaan'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="modal_alamat">Alamat</label>
                        <textarea id="modal_alamat" name="alamat" rows="3"><?php echo htmlspecialchars($perusahaan_data['alamat'] ?? ''); ?></textarea>
                    </div>
                    <div class="form-group">
                        <label for="modal_bidang">Bidang Usaha</label>
                        <input type="text" id="modal_bidang" name="bidang" value="<?php echo htmlspecialchars($perusahaan_data['bidang'] ?? ''); ?>">
                    </div>
                </div>
                <div class="form-section">
                    <h4><i class="icon-contact"></i>Informasi Kontak Person</h4>
                    <div class="form-group">
                        <label for="modal_kontak_nama">Nama Kontak</label>
                        <input type="text" id="modal_kontak_nama" name="kontak_person_nama" value="<?php echo htmlspecialchars($perusahaan_data['kontak_person_nama'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label for="modal_kontak_email">Email Kontak</label>
                        <input type="email" id="modal_kontak_email" name="kontak_person_email" value="<?php echo htmlspecialchars($perusahaan_data['kontak_person_email'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label for="modal_kontak_hp">Nomor HP Kontak</label>
                        <input type="text" id="modal_kontak_hp" name="kontak_person_no_hp" value="<?php echo htmlspecialchars($perusahaan_data['kontak_person_no_hp'] ?? ''); ?>">
                    </div>
                </div>
                 <div class="form-section">
                    <h4><i class="icon-lock"></i>Ubah Password (Opsional)</h4>
                    <div class="form-group">
                        <label for="modal_password_baru">Password Baru</label>
                        <input type="password" id="modal_password_baru" name="password_baru" placeholder="Kosongkan jika tidak diubah">
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
/* CSS Konsisten untuk Profil (Mahasiswa, Dosen, Perusahaan) */
.icon-edit::before { content: "✏️"; margin-right: 8px; }
.icon-office::before { content: "🏢"; margin-right: 8px; }
.icon-contact::before { content: "📞"; margin-right: 8px; }
.icon-lock::before { content: "🔒"; margin-right: 8px; }
.icon-user-group::before { content: "👥"; }
.icon-check-circle::before { content: "✔️"; }
:root {
    --primary-color: #3b82f6;
    --primary-dark: #2563eb;
    --success-color: #10b981;
    --warning-color: #f59e0b;
    --error-color: #ef4444;
    --secondary-color: #94a3b8;
    --dark-color: #e2e8f0;
    --border-color: #334155;
    --background-color: #0f172a;
    --card-bg-color: #1e293b;
    --card-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.3), 0 2px 4px -1px rgba(0, 0, 0, 0.2);
    --border-radius: 12px;
}
.main-content-full { padding: 2rem; background-color: var(--background-color); min-height: 100vh; }
.profile-container { max-width: 1200px; margin: 0 auto; }
@keyframes fadeIn { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
@keyframes slideIn { from { opacity: 0; transform: scale(0.95); } to { opacity: 1; transform: scale(1); } }
@keyframes spin { to { transform: rotate(360deg); } }
.fade-in { animation: fadeIn 0.6s ease-out forwards; }
.profile-header-card { background: var(--card-bg-color); border-radius: var(--border-radius); box-shadow: var(--card-shadow); overflow: hidden; position: relative; margin-bottom: 2rem; border: 1px solid var(--border-color); }
.profile-background { height: 120px; background: linear-gradient(135deg, var(--primary-color), var(--primary-dark)); position: relative; }
.profile-content { padding: 0 2rem 2rem; display: flex; align-items: flex-start; gap: 2rem; position: relative; }
.profile-avatar-section { display: flex; flex-direction: column; align-items: center; margin-top: -50px; }
.profile-avatar { width: 100px; height: 100px; border-radius: 50%; background: linear-gradient(135deg, var(--primary-color), var(--primary-dark)); display: flex; align-items: center; justify-content: center; font-size: 2.5rem; font-weight: 700; color: white; position: relative; border: 4px solid var(--card-bg-color); box-shadow: var(--card-shadow); }
.profile-status { margin-top: 1rem; }
.status-badge { display: inline-flex; align-items: center; gap: 6px; padding: 6px 12px; border-radius: 20px; font-size: 0.85rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; }
.status-icon { width: 6px; height: 6px; border-radius: 50%; display: inline-block; }
.status-active { background: rgba(16, 185, 129, 0.1); color: var(--success-color); }
.status-active .status-icon { background: var(--success-color); animation: pulse 2s infinite; }
.status-pending_approval, .status-pending { background: rgba(245, 158, 11, 0.1); color: var(--warning-color); }
.status-pending_approval .status-icon, .status-pending .status-icon { background: var(--warning-color); }
.status-inactive, .status-ditolak { background: rgba(107, 114, 128, 0.1); color: var(--secondary-color); }
.status-inactive .status-icon, .status-ditolak .status-icon { background: var(--secondary-color); }
@keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.5; } }
.profile-info { flex: 1; padding-top: 1rem; }
.profile-name { font-size: 2rem; font-weight: 700; color: var(--dark-color); margin: 0 0 0.5rem 0; }
.profile-subtitle { font-size: 1.1rem; color: var(--secondary-color); margin: 0 0 0.25rem 0; font-weight: 500; }
.profile-nim { font-size: 0.95rem; color: var(--secondary-color); margin: 0; font-family: monospace; background: var(--background-color); padding: 4px 8px; border-radius: 6px; display: inline-block; }
.profile-actions { padding-top: 1rem; }
.stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem; margin-bottom: 2rem; }
.stat-card { background: var(--card-bg-color); border: 1px solid var(--border-color); padding: 1.5rem; border-radius: var(--border-radius); box-shadow: var(--card-shadow); display: flex; align-items: center; gap: 1rem; transition: transform 0.2s ease; }
.stat-card:hover { transform: translateY(-2px); }
.stat-icon { width: 48px; height: 48px; border-radius: 12px; background: linear-gradient(135deg, var(--primary-color), var(--primary-dark)); display: flex; align-items: center; justify-content: center; font-size: 1.5rem; color: white; flex-shrink: 0; }
.stat-info h3 { font-size: 1.8rem; font-weight: 700; color: var(--dark-color); margin: 0 0 0.25rem 0; }
.stat-info p { font-size: 0.9rem; color: var(--secondary-color); margin: 0; font-weight: 500; }
.details-grid { display: grid; grid-template-columns: 1fr; gap: 2rem; }
.detail-card { background: var(--card-bg-color); border: 1px solid var(--border-color); border-radius: var(--border-radius); box-shadow: var(--card-shadow); overflow: hidden; }
.card-header { padding: 1.5rem; background: var(--background-color); border-bottom: 1px solid var(--border-color); }
.card-header h3 { font-size: 1.2rem; font-weight: 600; color: var(--dark-color); margin: 0; display: flex; align-items: center; gap: 0.5rem; }
.card-body { padding: 1.5rem; }
.detail-row { display: flex; justify-content: space-between; align-items: flex-start; gap: 1rem; padding: 1rem 0; border-bottom: 1px solid var(--border-color); }
.detail-row:last-child { border-bottom: none; }
.detail-label { font-weight: 500; color: var(--secondary-color); font-size: 0.9rem; flex-shrink: 0; }
.detail-value { font-weight: 600; color: var(--dark-color); text-align: right; word-break: break-word; }
.btn { display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.75rem 1.5rem; border-radius: 8px; font-weight: 600; text-decoration: none; border: none; cursor: pointer; transition: all 0.2s ease; font-size: 0.9rem; }
.btn-primary { background: var(--primary-color); color: white; }
.btn-primary:hover { background: var(--primary-dark); transform: translateY(-1px); }
.btn-secondary { background: #334155; color: #f1f5f9; }
.btn-secondary:hover { background: #475569; }
.modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.7); backdrop-filter: blur(8px); z-index: 1000; animation: fadeIn 0.3s ease-out; }
.modal-overlay.active { display: flex; align-items: center; justify-content: center; padding: 2rem; }
.modal-container { background: var(--card-bg-color); color: var(--dark-color); border: 1px solid var(--border-color); border-radius: var(--border-radius); box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.3); width: 100%; max-width: 600px; max-height: 90vh; overflow: hidden; animation: slideIn 0.3s ease-out; display: flex; flex-direction: column;}
.modal-header, .modal-footer { background: var(--background-color); border-color: var(--border-color); padding: 1.5rem; display: flex; align-items: center; }
.modal-header { justify-content: space-between; border-bottom: 1px solid var(--border-color); }
.modal-footer { justify-content: flex-end; gap: 1rem; border-top: 1px solid var(--border-color); }
.modal-header h2 { color: var(--dark-color); margin: 0; font-size: 1.4rem; display: flex; align-items: center; gap: 0.5rem; }
.modal-close { background: none; border: none; font-size: 1.5rem; color: var(--secondary-color); cursor: pointer; line-height: 1; padding: 0.5rem; border-radius: 50%; transition: all 0.2s ease; }
.modal-close:hover { color: var(--dark-color); background-color: var(--border-color); }
.modal-body { padding: 2rem; overflow-y: auto; }
.form-section { margin-bottom: 2.5rem; }
.form-section:last-of-type { margin-bottom: 0; }
.form-section h4 { font-size: 1.1rem; font-weight: 600; color: var(--dark-color); margin: 0 0 1rem 0; display: flex; align-items: center; gap: 0.5rem;}
.form-group { margin-bottom: 1.5rem; }
.form-group:last-of-type { margin-bottom: 0; }
.form-group label { display: block; font-weight: 500; color: var(--dark-color); margin-bottom: 0.5rem; font-size: 0.9rem; }
.form-group input, .form-group textarea { width: 100%; padding: 0.75rem 1rem; border-radius: 8px; font-size: 1rem; transition: all 0.2s ease; background: var(--background-color); color: var(--dark-color); border: 2px solid var(--border-color); }
.form-group input::placeholder, .form-group textarea::placeholder { color: var(--secondary-color); opacity: 0.7; }
.form-group input:focus, .form-group textarea:focus { outline: none; border-color: var(--primary-color); }
.btn-loading { position: relative; }
.btn-loading.loading .btn-text { opacity: 0; }
.btn-loading .btn-spinner { display: none; }
.btn-loading.loading .btn-spinner { display: block; position: absolute; left: 50%; top: 50%; transform: translate(-50%, -50%); width: 16px; height: 16px; border: 2px solid rgba(255,255,255,0.3); border-top-color: white; border-radius: 50%; animation: spin 1s linear infinite; }
.modal-message { padding: 1rem; border-radius: 6px; margin-bottom: 1.5rem; font-weight: 500; display: none; }
.modal-message.success { background: rgba(16, 185, 129, 0.2); color: #6ee7b7; border: 1px solid rgba(16, 185, 129, 0.4); }
.modal-message.error { background: rgba(239, 68, 68, 0.2); color: #fca5a5; border: 1px solid rgba(239, 68, 68, 0.4); }
.alert-error { background: rgba(239, 68, 68, 0.2); color: #fca5a5; padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem; }
.loading-state { color: var(--secondary-color); text-align: center; padding: 4rem; }
.loading-spinner { width: 40px; height: 40px; border: 3px solid var(--border-color); border-top: 3px solid var(--primary-color); border-radius: 50%; animation: spin 1s linear infinite; margin: 0 auto 1rem; }
@media (max-width: 768px) { .main-content-full { padding: 1rem; } .profile-content { flex-direction: column; text-align: center; gap: 1rem; } .profile-info { padding-top: 0; } .profile-name { font-size: 1.5rem; } .stats-grid, .details-grid { grid-template-columns: 1fr; gap: 1rem; } .modal-overlay.active { padding: 1rem; } }
</style>

<script>
function openEditModal() {
    document.getElementById('editModal').classList.add('active');
    document.body.style.overflow = 'hidden';
}
function closeEditModal() {
    document.getElementById('editModal').classList.remove('active');
    document.body.style.overflow = '';
}
function saveProfile() {
    const saveBtn = document.querySelector('.btn-loading');
    const modalMessage = document.getElementById('modalMessage');
    const formData = new FormData();
    formData.append('ajax_update', '1');
    formData.append('nama_perusahaan', document.getElementById('modal_nama_perusahaan').value);
    formData.append('alamat', document.getElementById('modal_alamat').value);
    formData.append('bidang', document.getElementById('modal_bidang').value);
    formData.append('kontak_person_nama', document.getElementById('modal_kontak_nama').value);
    formData.append('kontak_person_email', document.getElementById('modal_kontak_email').value);
    formData.append('kontak_person_no_hp', document.getElementById('modal_kontak_hp').value);
    formData.append('password_baru', document.getElementById('modal_password_baru').value);

    saveBtn.classList.add('loading');
    saveBtn.disabled = true;
    modalMessage.style.display = 'none';

    fetch(window.location.href, {
        method: 'POST',
        body: formData,
        headers: {'X-Requested-With': 'XMLHttpRequest'}
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showModalMessage(data.message, 'success');
            setTimeout(() => location.reload(), 1500);
        } else {
            showModalMessage(data.message, 'error');
        }
    })
    .catch(error => showModalMessage('Terjadi kesalahan. Coba lagi.', 'error'))
    .finally(() => {
        saveBtn.classList.remove('loading');
        saveBtn.disabled = false;
    });
}
function showModalMessage(message, type) {
    const modalMessage = document.getElementById('modalMessage');
    modalMessage.textContent = message;
    modalMessage.className = `modal-message ${type}`;
    modalMessage.style.display = 'block';
}
document.addEventListener('click', e => { if (e.target.id === 'editModal') closeEditModal(); });
document.addEventListener('keydown', e => { if (e.key === 'Escape' && document.getElementById('editModal').classList.contains('active')) closeEditModal(); });
</script>

<?php
require_once '../includes/footer.php';
?>
