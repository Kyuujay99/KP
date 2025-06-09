<?php
// /KP/admin_prodi/pengguna_dosen_kelola.php (Versi Disempurnakan)

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once '../includes/auth_check.php';
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin_prodi') {
    header("Location: /KP/index.php?error=unauthorized_admin");
    exit();
}
require_once '../config/db_connect.php';

$list_dosen = [];
$error_message = '';
$success_message = '';
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';

// Logika untuk memproses perubahan status
if (isset($_GET['action']) && isset($_GET['nip'])) {
    if ($conn) {
        $action = $_GET['action'];
        $nip_aksi = $_GET['nip'];
        $new_status = '';

        if (!empty($nip_aksi)) {
            if ($action === 'activate') $new_status = 'active';
            elseif ($action === 'deactivate') $new_status = 'inactive';

            if (!empty($new_status)) {
                $stmt = $conn->prepare("UPDATE dosen_pembimbing SET status_akun = ? WHERE nip = ?");
                if ($stmt) {
                    $stmt->bind_param("ss", $new_status, $nip_aksi);
                    if ($stmt->execute() && $stmt->affected_rows > 0) {
                        $success_message = "Status akun untuk Dosen NIP " . htmlspecialchars($nip_aksi) . " berhasil diubah.";
                    } else {
                        $error_message = "Gagal mengubah status atau status sudah sama.";
                    }
                    $stmt->close();
                }
            }
        }
    }
}

// Logika untuk mengambil daftar dosen dengan pencarian server-side
if ($conn) {
    $sql_dosen = "SELECT nip, nama_dosen, email, status_akun FROM dosen_pembimbing";
    $params = [];
    $types = "";

    if (!empty($search_query)) {
        $sql_dosen .= " WHERE (nama_dosen LIKE ? OR nip LIKE ?)";
        $search_term = "%" . $search_query . "%";
        $params[] = $search_term;
        $params[] = $search_term;
        $types .= "ss";
    }

    $sql_dosen .= " ORDER BY nama_dosen ASC";
    
    $stmt_dosen = $conn->prepare($sql_dosen);
    if ($stmt_dosen) {
        if (!empty($params)) {
            $stmt_dosen->bind_param($types, ...$params);
        }
        $stmt_dosen->execute();
        $result_dosen_db = $stmt_dosen->get_result();
        while ($row = $result_dosen_db->fetch_assoc()) {
            $list_dosen[] = $row;
        }
        $stmt_dosen->close();
    } else {
        $error_message = "Gagal mengambil data dosen: " . $conn->error;
    }
}

$page_title = "Kelola Akun Dosen";
require_once '../includes/header.php';
?>

<div class="kp-list-modern-container">
    <div class="list-hero-section">
        <div class="list-hero-content">
            <div class="list-hero-icon">
                <svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
            </div>
            <h1><?php echo htmlspecialchars($page_title); ?></h1>
            <p>Lihat, tambah, edit, dan kelola status semua akun dosen pembimbing dan penguji.</p>
        </div>
    </div>

    <div class="list-wrapper">
        <div class="list-header-controls">
            <a href="pengguna_dosen_tambah.php" class="btn btn-primary">➕ Tambah Dosen Baru</a>
            <form action="pengguna_dosen_kelola.php" method="GET" class="search-form">
                <input type="text" name="search" id="userSearchInput" placeholder="Cari nama atau NIP..." value="<?php echo htmlspecialchars($search_query); ?>">
                <button type="submit">Cari</button>
            </form>
        </div>

        <?php if (!empty($success_message)): ?>
            <div class="message success"><p><?php echo htmlspecialchars($success_message); ?></p></div>
        <?php endif; ?>
        <?php if (!empty($error_message)): ?>
            <div class="message error"><p><?php echo htmlspecialchars($error_message); ?></p></div>
        <?php endif; ?>

        <?php if (empty($list_dosen) && empty($error_message)): ?>
            <div class="message info">
                <h4>Data Kosong</h4>
                <p>Belum ada data dosen yang terdaftar. Silakan tambahkan dosen baru.</p>
            </div>
        <?php else: ?>
            <div class="user-card-grid" id="userCardGrid">
                <?php foreach ($list_dosen as $dosen): ?>
                    <div class="user-card animate-on-scroll">
                        <div class="card-header-status status-dosen-<?php echo strtolower(htmlspecialchars($dosen['status_akun'])); ?>">
                            <?php echo ucfirst(htmlspecialchars($dosen['status_akun'])); ?>
                        </div>
                        <div class="card-main-info">
                            <div class="user-avatar dosen-avatar">🧑‍🏫</div>
                            <h4 class="user-name"><?php echo htmlspecialchars($dosen['nama_dosen']); ?></h4>
                            <p class="user-id">NIP: <?php echo htmlspecialchars($dosen['nip']); ?></p>
                        </div>
                        <div class="card-contact-info">
                            <span><i class="icon">📧</i> <?php echo htmlspecialchars($dosen['email']); ?></span>
                        </div>
                        <div class="card-actions">
                            <a href="pengguna_dosen_edit.php?nip=<?php echo htmlspecialchars($dosen['nip']); ?>" class="btn btn-secondary" title="Edit">✏️ Edit Profil</a>
                            <button class="btn btn-primary btn-modal-trigger" 
                                    data-nip="<?php echo htmlspecialchars($dosen['nip']); ?>"
                                    data-nama="<?php echo htmlspecialchars($dosen['nama_dosen']); ?>"
                                    data-status="<?php echo $dosen['status_akun']; ?>">
                                ⚙️ Ubah Status
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal Structure (Hidden by default) -->
<div id="statusModal" class="modal-overlay" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h4 id="modalTitle">Ubah Status untuk [Nama Dosen]</h4>
            <button id="modalCloseBtn" class="modal-close-btn">&times;</button>
        </div>
        <div class="modal-body" id="modalBody">
            <!-- Action links will be dynamically inserted here by JavaScript -->
        </div>
    </div>
</div>

<style>
/* Modern List & Card Styles */
.kp-list-modern-container {
    --primary-color: #667eea; --text-primary: #1f2937; --text-secondary: #6b7280;
    --bg-light: #f9fafb; --border-color: #e5e7eb; --card-shadow: 0 4px 6px -1px rgba(0,0,0,0.05), 0 2px 4px -1px rgba(0,0,0,0.04);
    --border-radius: 12px; font-family: 'Inter', sans-serif; color: var(--text-primary);
    max-width: 1600px; margin: 0 auto; padding: 2rem;
}
.list-hero-section {
    padding: 3rem 2rem; background: linear-gradient(135deg, #3a6186 0%, #89253e 100%);
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
.user-avatar { width: 80px; height: 80px; border-radius: 50%; color: white; display: inline-flex; align-items: center; justify-content: center; font-size: 2.5em; font-weight: 600; margin-bottom: 1rem; }
.dosen-avatar { background-color: #3b82f6; }
.user-name { margin: 0; font-size: 1.3em; font-weight: 600; color: var(--text-primary); }
.user-id { margin: 0.25rem 0 0 0; color: var(--text-secondary); font-family: monospace; font-size: 0.9rem; }
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
.status-dosen-active { background-color: #d1fae5; color: #065f46; border-color: #bbf7d0;}
.status-dosen-inactive { background-color: #e5e7eb; color: #4b5563; border-color: #d1d5db;}

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
    
    // Function to open the modal
    document.querySelectorAll('.btn-modal-trigger').forEach(button => {
        button.addEventListener('click', function(event) {
            event.stopPropagation();
            const nip = this.dataset.nip;
            const nama = this.dataset.nama;
            const status = this.dataset.status;

            modalTitle.textContent = `Ubah Status: ${nama}`;
            
            let actionsHtml = '';
            if (status === 'inactive') {
                actionsHtml += `<a href="?action=activate&nip=${nip}" onclick="return confirm('Anda yakin ingin mengaktifkan akun dosen ini?');">✔️ Aktifkan Akun</a>`;
            }
            if (status === 'active') {
                actionsHtml += `<a href="?action=deactivate&nip=${nip}" onclick="return confirm('Anda yakin ingin menonaktifkan akun dosen ini?');">🚫 Non-aktifkan Akun</a>`;
            }
            
            modalBody.innerHTML = actionsHtml;
            modal.style.display = 'flex';
            setTimeout(() => modal.classList.add('show'), 10);
        });
    });

    // Function to close the modal
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
