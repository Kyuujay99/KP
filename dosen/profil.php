<?php
// /KP/dosen/profil.php (Enhanced Version with Edit Modal)

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
require_once '../config/db_connect.php';

$dosen_data = null;
$error_db = '';
$jumlah_bimbingan = 0;

// Handle AJAX update request
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['ajax_update'])) {
    header('Content-Type: application/json');
    
    $nama_dosen = trim($_POST['nama_dosen']);
    $email = trim($_POST['email']);
    $password_baru = $_POST['password_baru'];

    if (empty($nama_dosen) || empty($email)) {
        echo json_encode(['success' => false, 'message' => 'Nama dan Email wajib diisi.']);
        exit();
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Format email tidak valid.']);
        exit();
    }

    if ($conn) {
        // Cek duplikasi email
        $stmt_check = $conn->prepare("SELECT nip FROM dosen_pembimbing WHERE email = ? AND nip != ?");
        $stmt_check->bind_param("ss", $email, $nip_dosen_login);
        $stmt_check->execute();
        if ($stmt_check->get_result()->num_rows > 0) {
            echo json_encode(['success' => false, 'message' => 'Email ini sudah digunakan oleh dosen lain.']);
            $stmt_check->close();
            exit();
        }
        $stmt_check->close();

        // Bangun query update
        $sql_update = "UPDATE dosen_pembimbing SET nama_dosen = ?, email = ?";
        $params = [$nama_dosen, $email];
        $types = "ss";

        if (!empty($password_baru)) {
            if (strlen($password_baru) < 6) {
                echo json_encode(['success' => false, 'message' => 'Password baru minimal harus 6 karakter.']);
                exit();
            }
            $sql_update .= ", password = ?";
            // Menggunakan plain text sesuai permintaan proyek tugas
            $params[] = $password_baru;
            $types .= "s";
        }

        $sql_update .= " WHERE nip = ?";
        $params[] = $nip_dosen_login;
        $types .= "s";
        
        $stmt_update = $conn->prepare($sql_update);
        $stmt_update->bind_param($types, ...$params);

        if ($stmt_update->execute()) {
            $_SESSION['user_nama'] = $nama_dosen; // Update nama di session
            echo json_encode(['success' => true, 'message' => 'Profil berhasil diperbarui!']);
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
    $stmt_profil = $conn->prepare("SELECT nip, nama_dosen, email, status_akun, created_at FROM dosen_pembimbing WHERE nip = ? LIMIT 1");
    if ($stmt_profil) {
        $stmt_profil->bind_param("s", $nip_dosen_login);
        $stmt_profil->execute();
        $result = $stmt_profil->get_result();
        if ($result->num_rows === 1) {
            $dosen_data = $result->fetch_assoc();
            // Ambil data statistik bimbingan
            $stmt_bimbingan = $conn->prepare("SELECT COUNT(id_pengajuan) as total FROM pengajuan_kp WHERE nip_dosen_pembimbing_kp = ? AND status_pengajuan = 'kp_berjalan'");
            $stmt_bimbingan->bind_param("s", $nip_dosen_login);
            $stmt_bimbingan->execute();
            $jumlah_bimbingan = $stmt_bimbingan->get_result()->fetch_assoc()['total'] ?? 0;
            $stmt_bimbingan->close();
        } else {
            $error_db = "Data profil tidak ditemukan.";
        }
        $stmt_profil->close();
    } else {
        $error_db = "Gagal menyiapkan query: " . $conn->error;
    }
} else {
    $error_db = "Gagal terhubung ke database.";
}

$page_title = "Profil Dosen";
if (is_array($dosen_data) && !empty($dosen_data['nama_dosen'])) {
    $page_title = "Profil: " . htmlspecialchars($dosen_data['nama_dosen']);
}
require_once '../includes/header.php';
?>

<div class="main-content-full">
    <div class="profile-container">
        
        <div id="pageMessageContainer"></div>

        <?php if (!empty($error_db)): ?>
            <div class="alert alert-error fade-in"><span><?php echo htmlspecialchars($error_db); ?></span></div>
        <?php endif; ?>

        <?php if (is_array($dosen_data)): ?>
            <div class="profile-header-card fade-in">
                <div class="profile-background"></div>
                <div class="profile-content">
                    <div class="profile-avatar-section">
                        <div class="profile-avatar">
                            <span><?php echo strtoupper(substr($dosen_data['nama_dosen'], 0, 1)); ?></span>
                        </div>
                        <div class="profile-status">
                            <span class="status-badge status-<?php echo strtolower(htmlspecialchars($dosen_data['status_akun'])); ?>">
                                <i class="status-icon"></i>
                                <?php echo ucfirst(str_replace('_', ' ', htmlspecialchars($dosen_data['status_akun']))); ?>
                            </span>
                        </div>
                    </div>
                    <div class="profile-info">
                        <h1 class="profile-name"><?php echo htmlspecialchars($dosen_data['nama_dosen']); ?></h1>
                        <p class="profile-subtitle">Dosen Pembimbing / Penguji</p>
                        <p class="profile-nim">NIP: <?php echo htmlspecialchars($dosen_data['nip']); ?></p>
                    </div>
                    <div class="profile-actions">
                        <button class="btn btn-primary btn-edit" onclick="openEditModal()">
                            <i class="icon-edit"></i>
                            <span>Edit Profil</span>
                        </button>
                    </div>
                </div>
            </div>

            <div class="stats-grid">
                <div class="stat-card fade-in" style="animation-delay: 0.1s">
                    <div class="stat-icon"><i class="icon-academic-cap"></i></div>
                    <div class="stat-info">
                        <h3><?php echo $jumlah_bimbingan; ?></h3>
                        <p>Mahasiswa Bimbingan Aktif</p>
                    </div>
                </div>
            </div>

            <div class="details-grid">
                <div class="detail-card fade-in" style="animation-delay: 0.4s">
                    <div class="card-header"><h3><i class="icon-user"></i>Informasi Kontak & Akun</h3></div>
                    <div class="card-body">
                        <div class="detail-row">
                            <span class="detail-label">Email (Login)</span>
                            <span class="detail-value"><?php echo htmlspecialchars($dosen_data['email']); ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Akun Dibuat</span>
                            <span class="detail-value"><?php echo date("d F Y", strtotime($dosen_data['created_at'])); ?></span>
                        </div>
                    </div>
                </div>
            </div>
        <?php elseif(empty($error_db)): ?>
            <div class="loading-state"><div class="loading-spinner"></div><p>Memuat data profil...</p></div>
        <?php endif; ?>
    </div>
</div>

<div id="editModal" class="modal-overlay">
    <div class="modal-container">
        <div class="modal-header">
            <h2><i class="icon-edit"></i>Edit Profil Dosen</h2>
            <button class="modal-close" onclick="closeEditModal()">&times;</button>
        </div>
        <div class="modal-body">
            <div id="modalMessage" class="modal-message" style="display: none;"></div>
            <form id="editForm">
                <div class="form-section">
                    <h4><i class="icon-contact"></i>Informasi Dasar</h4>
                    <div class="form-group">
                        <label for="modal_nama_dosen">Nama Lengkap & Gelar *</label>
                        <input type="text" id="modal_nama_dosen" name="nama_dosen" value="<?php echo htmlspecialchars($dosen_data['nama_dosen'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="modal_email">Email *</label>
                        <input type="email" id="modal_email" name="email" value="<?php echo htmlspecialchars($dosen_data['email'] ?? ''); ?>" required>
                    </div>
                </div>
                <div class="form-section">
                    <h4><i class="icon-lock"></i>Ubah Password (Opsional)</h4>
                    <div class="form-group">
                        <label for="modal_password_baru">Password Baru</label>
                        <input type="password" id="modal_password_baru" name="password_baru" placeholder="Kosongkan jika tidak diubah">
                    </div>
                </div>
                <div class="form-group">
                    <label for="modal_nip">NIP</label>
                    <input type="text" id="modal_nip" name="nip" value="<?php echo htmlspecialchars($dosen_data['nip'] ?? ''); ?>" readonly>
                    <small>NIP tidak dapat diubah.</small>
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
.icon-academic-cap::before { content: "🎓"; }
.icon-contact::before { content: "📧"; margin-right: 8px; }
.icon-lock::before { content: "🔒"; margin-right: 8px; }

/* Variabel Warna - Mode Gelap */
:root {
    --primary-color: #3b82f6;
    --primary-dark: #2563eb;
    --success-color: #10b981;
    --warning-color: #f59e0b;
    --error-color: #ef4444;
    --secondary-color: #94a3b8; /* Teks sekunder terang */
    --dark-color: #e2e8f0;      /* Teks utama terang */
    --border-color: #334155;    /* Border lebih gelap */
    --background-color: #0f172a;/* Latar belakang utama (Sangat Gelap) */
    --card-bg-color: #1e293b;   /* Latar belakang kartu (Biru Gelap) */
    --card-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.3), 0 2px 4px -1px rgba(0, 0, 0, 0.2);
    --border-radius: 12px;
}

/* Layout Utama */
.main-content-full {
    padding: 2rem;
    background-color: var(--background-color);
    min-height: 100vh;
}
.profile-container {
    max-width: 1200px;
    margin: 0 auto;
}

/* Animasi */
@keyframes fadeIn { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
.fade-in { animation: fadeIn 0.6s ease-out forwards; }

/* Kartu Header Profil */
.profile-header-card {
    background: var(--card-bg-color);
    border-radius: var(--border-radius);
    box-shadow: var(--card-shadow);
    overflow: hidden;
    position: relative;
    margin-bottom: 2rem;
    border: 1px solid var(--border-color);
}
.profile-background {
    height: 120px;
    background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
    position: relative;
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
    border: 4px solid var(--card-bg-color);
    box-shadow: var(--card-shadow);
}
.profile-status { margin-top: 1rem; }
.status-badge { display: inline-flex; align-items: center; gap: 6px; padding: 6px 12px; border-radius: 20px; font-size: 0.85rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; }
.status-icon { width: 6px; height: 6px; border-radius: 50%; display: inline-block; }
.status-active { background: rgba(16, 185, 129, 0.1); color: var(--success-color); }
.status-active .status-icon { background: var(--success-color); animation: pulse 2s infinite; }
.status-inactive { background: rgba(107, 114, 128, 0.1); color: var(--secondary-color); }
.status-inactive .status-icon { background: var(--secondary-color); }
@keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.5; } }
.profile-info { flex: 1; padding-top: 1rem; }
.profile-name { font-size: 2rem; font-weight: 700; color: var(--dark-color); margin: 0 0 0.5rem 0; }
.profile-subtitle { font-size: 1.1rem; color: var(--secondary-color); margin: 0 0 0.25rem 0; font-weight: 500; }
.profile-nim { font-size: 0.95rem; color: var(--secondary-color); margin: 0; font-family: monospace; background: var(--background-color); padding: 4px 8px; border-radius: 6px; display: inline-block; }
.profile-actions { padding-top: 1rem; }

/* Kartu Statistik */
.stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem; margin-bottom: 2rem; }
.stat-card { background: var(--card-bg-color); border: 1px solid var(--border-color); padding: 1.5rem; border-radius: var(--border-radius); box-shadow: var(--card-shadow); display: flex; align-items: center; gap: 1rem; transition: transform 0.2s ease; }
.stat-card:hover { transform: translateY(-2px); }
.stat-icon { width: 48px; height: 48px; border-radius: 12px; background: linear-gradient(135deg, var(--primary-color), var(--primary-dark)); display: flex; align-items: center; justify-content: center; font-size: 1.5rem; color: white; }
.stat-info h3 { font-size: 1.8rem; font-weight: 700; color: var(--dark-color); margin: 0 0 0.25rem 0; }
.stat-info p { font-size: 0.9rem; color: var(--secondary-color); margin: 0; font-weight: 500; }

/* Kartu Detail */
.details-grid { display: grid; grid-template-columns: 1fr; gap: 2rem; }
.detail-card { background: var(--card-bg-color); border: 1px solid var(--border-color); border-radius: var(--border-radius); box-shadow: var(--card-shadow); overflow: hidden; }
.card-header { padding: 1.5rem; background: var(--background-color); border-bottom: 1px solid var(--border-color); }
.card-header h3 { font-size: 1.2rem; font-weight: 600; color: var(--dark-color); margin: 0; display: flex; align-items: center; }
.card-body { padding: 1.5rem; }
.detail-row { display: flex; justify-content: space-between; align-items: center; padding: 1rem 0; border-bottom: 1px solid var(--border-color); }
.detail-row:last-child { border-bottom: none; }
.detail-label { font-weight: 500; color: var(--secondary-color); font-size: 0.9rem; }
.detail-value { font-weight: 600; color: var(--dark-color); text-align: right; }

/* Tombol */
.btn { display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.75rem 1.5rem; border-radius: 8px; font-weight: 600; text-decoration: none; border: none; cursor: pointer; transition: all 0.2s ease; font-size: 0.9rem; }
.btn-primary { background: var(--primary-color); color: white; }
.btn-primary:hover { background: var(--primary-dark); transform: translateY(-1px); }
.btn-secondary { background: #334155; color: #f1f5f9; }
.btn-secondary:hover { background: #475569; }

/* Latar belakang gelap saat modal aktif */
.modal-overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.7); /* Lebih gelap */
    backdrop-filter: blur(8px);      /* Efek blur */
    z-index: 1000;
    animation: fadeIn 0.3s ease-out;
}

.modal-overlay.active {
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 2rem;
}

/* Kontainer utama modal */
.modal-container {
    background: var(--card-bg-color); /* Warna kartu gelap */
    color: var(--dark-color);         /* Warna teks terang */
    border: 1px solid var(--border-color);
    border-radius: var(--border-radius);
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.3);
    width: 100%;
    max-width: 600px;
    max-height: 90vh;
    overflow: hidden;
    animation: slideIn 0.3s ease-out;
    display: flex;
    flex-direction: column;
}

@keyframes slideIn {
    from { opacity: 0; transform: scale(0.95); }
    to { opacity: 1; transform: scale(1); }
}

/* Header, Body, Footer Modal */
.modal-header, .modal-footer {
    background: var(--background-color); /* Background paling gelap */
    border-color: var(--border-color);
    padding: 1.5rem;
    display: flex;
    align-items: center;
}

.modal-header {
    justify-content: space-between;
    border-bottom: 1px solid var(--border-color);
}

.modal-footer {
    justify-content: flex-end;
    gap: 1rem;
    border-top: 1px solid var(--border-color);
}

.modal-header h2 {
    color: var(--dark-color);
    margin: 0;
    font-size: 1.4rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.modal-close {
    background: none;
    border: none;
    font-size: 1.5rem;
    color: var(--secondary-color);
    cursor: pointer;
    line-height: 1;
    padding: 0.5rem;
    border-radius: 50%;
    transition: all 0.2s ease;
}

.modal-close:hover {
    color: var(--dark-color);
    background-color: var(--border-color);
}

.modal-body {
    padding: 2rem;
    overflow-y: auto;
}

/* Form di dalam Modal */
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
    color: white;
    font-size: 0.9rem;
    margin: 0 0 1.5rem 0;
}

.form-group {
    margin-bottom: 1.5rem;
}
.form-group:last-of-type {
    margin-bottom: 0;
}
.form-group label {
    display: block;
    font-weight: 500;
    color: var(white);
    margin-bottom: 0.5rem;
    font-size: 0.9rem;
}
.form-group small {
    font-size: 0.85em;
    color: var(--secondary-color);
    margin-top: 5px;
    display: block;
}

/* INI BAGIAN PENTINGNYA, BUB */
.form-group input {
    width: 100%;
    padding: 0.75rem 1rem;
    border-radius: 8px;
    font-size: 1rem;
    transition: all 0.2s ease;
    background: var(--background-color); /* <-- Warnanya disamain kayak background utama yg gelap banget */
    color: var(--dark-color);
    border: 2px solid var(--border-color);
}
.form-group input:focus {
    outline: none;
    border-color: var(--primary-color);
}

/* Ini buat NIP yang readonly jadi keliatan beda */
.form-group input[readonly] {
    background-color: #0b1221; /* Sedikit beda biar keliatan disable */
    color: var(--secondary-color);
    cursor: not-allowed;
    opacity: 0.8;
}

/* Styling untuk pesan error/sukses di dalam modal */
.modal-message {
    padding: 1rem;
    border-radius: 6px;
    margin-bottom: 1.5rem;
    font-weight: 500;
    display: none; /* Disembunyikan dulu */
}
.modal-message.success {
    background: rgba(16, 185, 129, 0.2);
    color: #6ee7b7;
    border: 1px solid rgba(16, 185, 129, 0.4);
}
.modal-message.error {
    background: rgba(239, 68, 68, 0.2);
    color: #fca5a5;
    border: 1px solid rgba(239, 68, 68, 0.4);
}

/* Tombol Loading di Modal */
.btn-loading {
    position: relative;
}
.btn-loading .btn-text {
    transition: opacity 0.2s;
}
.btn-loading.loading .btn-text {
    opacity: 0;
}
.btn-loading .btn-spinner {
    display: none;
}
.btn-loading.loading .btn-spinner {
    display: block;
    position: absolute;
    left: 50%;
    top: 50%;
    transform: translate(-50%, -50%);
    width: 16px;
    height: 16px;
    border: 2px solid rgba(255,255,255,0.3);
    border-top-color: white;
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

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
    formData.append('nama_dosen', document.getElementById('modal_nama_dosen').value);
    formData.append('email', document.getElementById('modal_email').value);
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
            setTimeout(() => {
                location.reload(); // Reload halaman untuk melihat perubahan
            }, 1500);
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

document.addEventListener('click', e => {
    if (e.target.id === 'editModal') closeEditModal();
});
document.addEventListener('keydown', e => {
    if (e.key === 'Escape' && document.getElementById('editModal').classList.contains('active')) {
        closeEditModal();
    }
});
</script>

<?php
require_once '../includes/footer.php';
?>