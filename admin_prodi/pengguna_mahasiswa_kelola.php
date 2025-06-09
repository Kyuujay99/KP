<?php
// /KP/admin_prodi/pengguna_mahasiswa_kelola.php (Versi Disempurnakan)

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once '../includes/auth_check.php';
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin_prodi') {
    header("Location: /KP/index.php?error=unauthorized_admin");
    exit();
}
require_once '../config/db_connect.php';

$list_mahasiswa = [];
$error_message = '';
$success_message = '';
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';

// Logika untuk memproses perubahan status
if (isset($_GET['action']) && isset($_GET['nim'])) {
    if ($conn) {
        $action = $_GET['action'];
        $nim_aksi = $_GET['nim'];
        $new_status = '';
        if ($action === 'activate') $new_status = 'active';
        elseif ($action === 'suspend') $new_status = 'suspended';
        elseif ($action === 'reactivate') $new_status = 'active';
        
        if (!empty($new_status)) {
            $stmt_update_status = $conn->prepare("UPDATE mahasiswa SET status_akun = ? WHERE nim = ?");
            if ($stmt_update_status) {
                $stmt_update_status->bind_param("ss", $new_status, $nim_aksi);
                if ($stmt_update_status->execute() && $stmt_update_status->affected_rows > 0) {
                    $success_message = "Status akun untuk NIM " . htmlspecialchars($nim_aksi) . " berhasil diubah.";
                } else {
                    $error_message = "Gagal mengubah status atau status sudah sama.";
                }
                $stmt_update_status->close();
            }
        }
    }
}

// Logika untuk mengambil daftar mahasiswa dengan pencarian
if ($conn) {
    $sql_mahasiswa = "SELECT nim, nama, email, no_hp, prodi, angkatan, status_akun FROM mahasiswa";
    $params = [];
    $types = "";

    if (!empty($search_query)) {
        $sql_mahasiswa .= " WHERE (nama LIKE ? OR nim LIKE ?)";
        $search_term = "%" . $search_query . "%";
        $params[] = $search_term;
        $params[] = $search_term;
        $types .= "ss";
    }

    $sql_mahasiswa .= " ORDER BY CASE WHEN status_akun = 'pending_verification' THEN 1 WHEN status_akun = 'active' THEN 2 ELSE 3 END, nama ASC";

    $stmt_mahasiswa = $conn->prepare($sql_mahasiswa);
    if($stmt_mahasiswa) {
        if(!empty($params)){
            $stmt_mahasiswa->bind_param($types, ...$params);
        }
        $stmt_mahasiswa->execute();
        $result_mahasiswa = $stmt_mahasiswa->get_result();
        $list_mahasiswa = $result_mahasiswa->fetch_all(MYSQLI_ASSOC);
        $stmt_mahasiswa->close();
    } else {
        $error_message = "Gagal mengambil data mahasiswa: " . $conn->error;
    }
}

$page_title = "Kelola Akun Mahasiswa";
require_once '../includes/header.php';
?>

<div class="kp-list-modern-container">

    <div class="list-hero-section">
        <div class="list-hero-content">
            <div class="list-hero-icon">
                <svg viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>
            </div>
            <h1><?php echo htmlspecialchars($page_title); ?></h1>
            <p>Lihat, tambah, edit, dan kelola status semua akun mahasiswa yang terdaftar dalam sistem.</p>
        </div>
    </div>
    
    <div class="list-wrapper">
        <div class="list-header-controls">
            <a href="pengguna_mahasiswa_tambah.php" class="btn btn-primary">➕ Tambah Mahasiswa Baru</a>
            <form action="pengguna_mahasiswa_kelola.php" method="GET" class="search-form">
                <input type="text" name="search" id="userSearchInput" placeholder="Cari nama atau NIM..." value="<?php echo htmlspecialchars($search_query); ?>">
                <button type="submit">Cari</button>
            </form>
        </div>

        <?php if (!empty($success_message)): ?>
            <div class="message success"><p><?php echo htmlspecialchars($success_message); ?></p></div>
        <?php endif; ?>
        <?php if (!empty($error_message)): ?>
            <div class="message error"><p><?php echo htmlspecialchars($error_message); ?></p></div>
        <?php endif; ?>

        <?php if (empty($list_mahasiswa) && empty($error_message)): ?>
            <div class="message info">
                <h4>Data Kosong</h4>
                <p>Belum ada data mahasiswa yang terdaftar di sistem. Silakan tambahkan mahasiswa baru.</p>
            </div>
        <?php else: ?>
            <div class="user-card-grid" id="userCardGrid">
                <?php foreach ($list_mahasiswa as $mhs): ?>
                    <div class="user-card animate-on-scroll">
                        <div class="card-header-status status-<?php echo strtolower(htmlspecialchars($mhs['status_akun'])); ?>">
                            <?php echo ucfirst(str_replace('_', ' ', htmlspecialchars($mhs['status_akun']))); ?>
                        </div>
                        <div class="card-main-info">
                            <div class="user-avatar"><?php echo strtoupper(substr($mhs['nama'], 0, 1)); ?></div>
                            <h4 class="user-name"><?php echo htmlspecialchars($mhs['nama']); ?></h4>
                            <p class="user-id"><?php echo htmlspecialchars($mhs['nim']); ?></p>
                            <p class="user-extra-info"><?php echo htmlspecialchars($mhs['prodi'] ?: '-'); ?> - Angkatan <?php echo htmlspecialchars($mhs['angkatan'] ?: '-'); ?></p>
                        </div>
                        <div class="card-contact-info">
                            <span><i class="icon">📧</i> <?php echo htmlspecialchars($mhs['email']); ?></span>
                            <span><i class="icon">📞</i> <?php echo htmlspecialchars($mhs['no_hp'] ?: 'Tidak ada No. HP'); ?></span>
                        </div>
                        <div class="card-actions">
                            <a href="pengguna_mahasiswa_edit.php?nim=<?php echo htmlspecialchars($mhs['nim']); ?>" class="btn btn-secondary" title="Edit">✏️ Edit Profil</a>
                            <button class="btn btn-primary btn-modal-trigger"
                                    data-nim="<?php echo htmlspecialchars($mhs['nim']); ?>"
                                    data-nama="<?php echo htmlspecialchars($mhs['nama']); ?>"
                                    data-status="<?php echo $mhs['status_akun']; ?>">
                                ⚙️ Ubah Status
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal Structure -->
<div id="statusModal" class="modal-overlay" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h4 id="modalTitle">Ubah Status untuk [Nama Mahasiswa]</h4>
            <button id="modalCloseBtn" class="modal-close-btn">&times;</button>
        </div>
        <div class="modal-body" id="modalBody">
            <!-- Dynamic action links here -->
        </div>
    </div>
</div>

<style>
/* Modern List Layout & Card Styles */
.kp-list-modern-container {
    --primary-color: #667eea; --text-primary: #1f2937; --text-secondary: #6b7280;
    --bg-light: #f9fafb; --border-color: #e5e7eb; --card-shadow: 0 4px 6px -1px rgba(0,0,0,0.05), 0 2px 4px -1px rgba(0,0,0,0.04);
    --border-radius: 12px; font-family: 'Inter', sans-serif; color: var(--text-primary);
    max-width: 1600px; margin: 0 auto; padding: 2rem;
}
.list-hero-section {
    padding: 3rem 2rem; background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 100%);
    border-radius: var(--border-radius); margin-bottom: 2rem; color: white; text-align: center;
}
.list-hero-content { max-width: 700px; margin: 0 auto; }
.list-hero-icon { width: 60px; height: 60px; background: rgba(255,255,255,0.1); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1.5rem; }
.list-hero-icon svg { width: 32px; height: 32px; stroke: white; fill: none; stroke-width: 2; }
.list-hero-section h1 { font-size: 2.5rem; font-weight: 700; margin-bottom: 0.5rem; }
.list-hero-section p { font-size: 1.1rem; opacity: 0.9; font-weight: 300; }
.list-wrapper { background-color: #fff; padding: 2rem; border-radius: var(--border-radius); box-shadow: var(--card-shadow); }

.list-header-controls { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; margin-bottom: 2rem; }
.search-form { display: flex; gap: 0.5rem; }
#userSearchInput { padding: 0.75rem 1rem; border: 1px solid var(--border-color); border-radius: 8px; width: 300px; }
.search-form button { padding: 0.75rem 1rem; border: none; background-color: var(--primary-color); color: white; border-radius: 8px; cursor: pointer; }

.user-card-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(340px, 1fr)); gap: 1.5rem; }
.user-card { background-color: #fff; border-radius: var(--border-radius); box-shadow: var(--card-shadow); display: flex; flex-direction: column; overflow: hidden; position: relative; transition: all 0.3s ease; border: 1px solid var(--border-color); }
.user-card:hover { transform: translateY(-5px); box-shadow: 0 10px 15px -3px rgba(0,0,0,0.07); }
.card-header-status { position: absolute; top: 15px; right: 15px; padding: 5px 12px; border-radius: 20px; font-size: 0.8em; font-weight: 600; border: 1px solid; }
.card-main-info { padding: 2rem 1.5rem 1.5rem; text-align: center; border-bottom: 1px solid var(--border-color); }
.user-avatar { width: 80px; height: 80px; border-radius: 50%; color: white; display: inline-flex; align-items: center; justify-content: center; font-size: 2.5em; font-weight: 600; margin-bottom: 1rem; background-color: var(--primary-color);}
.user-name { margin: 0; font-size: 1.3em; font-weight: 600; color: var(--text-primary); }
.user-id { margin: 0.25rem 0; color: var(--text-secondary); font-family: monospace; font-size: 0.9rem; }
.user-extra-info { margin-top: 0.5rem; font-size: 0.9em; color: var(--text-secondary); }
.card-contact-info { padding: 1rem 1.5rem; display: flex; flex-direction: column; gap: 0.75rem; font-size: 0.9em; flex-grow: 1;}
.card-contact-info span { color: var(--text-secondary); word-break: break-all; display:flex; align-items:center; gap: 0.5rem; }
.card-contact-info .icon { font-style: normal; }
.card-actions { margin-top: auto; display: grid; grid-template-columns: 1fr 1fr; background-color: var(--bg-light); border-top: 1px solid var(--border-color); }
.card-actions .btn { padding: 1rem; border: none; background: transparent; text-align: center; text-decoration: none; font-weight: 600; transition: background-color 0.3s ease; cursor: pointer; font-size: 0.9rem; }
.btn.btn-secondary { border-right: 1px solid var(--border-color); color: var(--text-primary); }
.btn.btn-secondary:hover { background-color: #e9ecef; }
.btn.btn-primary { color: var(--primary-color); }
.btn.btn-primary:hover { background-color: var(--primary-color); color: #fff; }

/* Status Styles */
.status-pending_verification { background-color: #fef3c7; color: #92400e; border-color: #fde68a; }
.status-active { background-color: #d1fae5; color: #065f46; border-color: #bbf7d0;}
.status-suspended { background-color: #fee2e2; color: #991b1c; border-color: #fecaca;}

/* Modal Styles */
.modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); display: flex; justify-content: center; align-items: center; z-index: 1000; opacity: 0; pointer-events: none; transition: opacity 0.3s ease; }
.modal-overlay.show { opacity: 1; pointer-events: auto; }
.modal-content { background: #fff; padding: 2rem; border-radius: var(--border-radius); width: 90%; max-width: 500px; box-shadow: 0 10px 30px rgba(0,0,0,0.2); transform: scale(0.95); transition: transform 0.3s ease; }
.modal-overlay.show .modal-content { transform: scale(1); }
.modal-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border-color); padding-bottom: 1rem; margin-bottom: 1rem; }
.modal-header h4 { margin: 0; font-size: 1.25rem; }
.modal-close-btn { background: none; border: none; font-size: 1.8rem; cursor: pointer; color: var(--text-secondary); }
.modal-body a { display: block; padding: 1rem; margin: 0.5rem 0; border-radius: 8px; text-decoration: none; color: var(--text-primary); background: var(--bg-light); transition: all 0.2s; font-weight: 500; }
.modal-body a:hover { background: var(--primary-color); color: #fff; transform: translateX(5px); }
.animate-on-scroll { opacity: 0; transform: translateY(20px); transition: opacity 0.5s ease-out, transform 0.5s ease-out; }
.animate-on-scroll.is-visible { opacity: 1; transform: translateY(0); }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('statusModal');
    if (!modal) return;

    const modalTitle = document.getElementById('modalTitle');
    const modalBody = document.getElementById('modalBody');
    const modalCloseBtn = document.getElementById('modalCloseBtn');
    
    document.querySelectorAll('.btn-modal-trigger').forEach(button => {
        button.addEventListener('click', function(event) {
            event.stopPropagation();
            const nim = this.dataset.nim;
            const nama = this.dataset.nama;
            const status = this.dataset.status;

            modalTitle.textContent = `Ubah Status: ${nama}`;
            
            let actionsHtml = '';
            if (status === 'pending_verification') {
                actionsHtml += `<a href="?action=activate&nim=${nim}" onclick="return confirm('Aktifkan akun ini?');">✔️ Aktifkan Akun</a>`;
            }
            if (status === 'active') {
                actionsHtml += `<a href="?action=suspend&nim=${nim}" onclick="return confirm('Tangguhkan akun ini?');">🚫 Tangguhkan Akun</a>`;
            }
            if (status === 'suspended') {
                actionsHtml += `<a href="?action=reactivate&nim=${nim}" onclick="return confirm('Aktifkan kembali akun ini?');">♻️ Aktifkan Kembali</a>`;
            }
            
            modalBody.innerHTML = actionsHtml;
            modal.style.display = 'flex';
            setTimeout(() => modal.classList.add('show'), 10);
        });
    });

    function closeModal() {
        modal.classList.remove('show');
        setTimeout(() => modal.style.display = 'none', 300);
    }

    modalCloseBtn.addEventListener('click', closeModal);
    modal.addEventListener('click', function(event) {
        if (event.target === modal) {
            closeModal();
        }
    });

    // Animate on scroll
    const animatedElements = document.querySelectorAll('.animate-on-scroll');
    const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry, index) => {
            if (entry.isIntersecting) {
                entry.target.style.transitionDelay = `${index * 50}ms`;
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
if (isset($conn)) { $conn->close(); }
?>
