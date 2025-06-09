<?php
// /KP/admin_prodi/perusahaan_kelola.php (Versi Final dengan Modal)

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once '../includes/auth_check.php';
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin_prodi') {
    header("Location: /KP/index.php?error=unauthorized_admin");
    exit();
}

require_once '../config/db_connect.php';

$list_perusahaan = [];
$error_message = '';
$success_message = '';
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';

// Logika untuk memproses perubahan status (tetap sama)
if (isset($_GET['action']) && isset($_GET['id_perusahaan'])) {
    if ($conn) {
        $action = $_GET['action'];
        $id_perusahaan_aksi = filter_var($_GET['id_perusahaan'], FILTER_VALIDATE_INT);
        $new_status = '';

        if ($id_perusahaan_aksi) {
            if ($action === 'approve') $new_status = 'active';
            elseif ($action === 'deactivate') $new_status = 'inactive';
            elseif ($action === 'reactivate') $new_status = 'active';

            if (!empty($new_status)) {
                $stmt = $conn->prepare("UPDATE perusahaan SET status_akun = ? WHERE id_perusahaan = ?");
                if ($stmt) {
                    $stmt->bind_param("si", $new_status, $id_perusahaan_aksi);
                    if ($stmt->execute() && $stmt->affected_rows > 0) {
                        $success_message = "Status akun perusahaan berhasil diubah.";
                    } else {
                        $error_message = "Gagal mengubah status atau status sudah sama.";
                    }
                    $stmt->close();
                }
            }
        }
    }
}

// Logika untuk mengambil daftar perusahaan dengan pencarian server-side (tetap sama)
if ($conn) {
    $sql_perusahaan = "SELECT id_perusahaan, nama_perusahaan, email_perusahaan, bidang, kontak_person_nama, kontak_person_no_hp, status_akun FROM perusahaan";
    $params = [];
    $types = "";
    if (!empty($search_query)) {
        $sql_perusahaan .= " WHERE (nama_perusahaan LIKE ? OR bidang LIKE ?)";
        $search_term = "%" . $search_query . "%";
        $params[] = $search_term;
        $params[] = $search_term;
        $types .= "ss";
    }
    $sql_perusahaan .= " ORDER BY CASE WHEN status_akun = 'pending_approval' THEN 1 WHEN status_akun = 'active' THEN 2 ELSE 3 END, nama_perusahaan ASC";
    $stmt_perusahaan = $conn->prepare($sql_perusahaan);
    if ($stmt_perusahaan) {
        if (!empty($params)) {
            $stmt_perusahaan->bind_param($types, ...$params);
        }
        $stmt_perusahaan->execute();
        $list_perusahaan = $stmt_perusahaan->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt_perusahaan->close();
    } else {
        $error_message = "Gagal mengambil data perusahaan: " . $conn->error;
    }
}

$page_title = "Kelola Data Perusahaan Mitra";
require_once '../includes/header.php';
?>

<div class="kp-list-modern-container">
    <div class="list-hero-section">
        <div class="list-hero-content">
            <div class="list-hero-icon"><svg viewBox="0 0 24 24"><path d="M3 21h18"/><path d="M5 21V7l8-4v18"/><path d="M19 21V11l-6-4"/></svg></div>
            <h1><?php echo htmlspecialchars($page_title); ?></h1>
            <p>Verifikasi, kelola, dan lihat semua perusahaan mitra yang terdaftar dalam sistem.</p>
        </div>
    </div>

    <div class="list-wrapper">
        <div class="list-header-controls">
            <a href="perusahaan_tambah.php" class="btn btn-primary">➕ Tambah Perusahaan Baru</a>
            <form action="perusahaan_kelola.php" method="GET" class="search-form">
                <input type="text" name="search" id="userSearchInput" placeholder="Cari nama atau bidang..." value="<?php echo htmlspecialchars($search_query); ?>">
                <button type="submit">Cari</button>
            </form>
        </div>

        <?php if (!empty($success_message)): ?><div class="message success"><p><?php echo htmlspecialchars($success_message); ?></p></div><?php endif; ?>
        <?php if (!empty($error_message)): ?><div class="message error"><p><?php echo htmlspecialchars($error_message); ?></p></div><?php endif; ?>

        <?php if (empty($list_perusahaan) && empty($error_message)): ?>
            <div class="message info"><h4>Data Kosong</h4><p>Belum ada data perusahaan mitra yang terdaftar.</p></div>
        <?php else: ?>
            <div class="user-card-grid" id="userCardGrid">
                <?php foreach ($list_perusahaan as $p): ?>
                    <div class="user-card animate-on-scroll">
                        <div class="card-header-status status-perusahaan-<?php echo strtolower(htmlspecialchars($p['status_akun'])); ?>"><?php echo ucfirst(str_replace('_', ' ', htmlspecialchars($p['status_akun']))); ?></div>
                        <div class="card-main-info">
                            <div class="user-avatar company-avatar">🏢</div>
                            <h4 class="user-name"><?php echo htmlspecialchars($p['nama_perusahaan']); ?></h4>
                            <p class="user-id company-bidang"><?php echo htmlspecialchars($p['bidang'] ?: 'Bidang belum diatur'); ?></p>
                        </div>
                        <div class="card-contact-info">
                            <span><i class="icon">📧</i> <?php echo htmlspecialchars($p['email_perusahaan']); ?></span>
                            <span class="company-pic"><i class="icon">👤</i> PIC: <?php echo htmlspecialchars($p['kontak_person_nama'] ?: 'N/A'); ?> (<?php echo htmlspecialchars($p['kontak_person_no_hp'] ?: '-'); ?>)</span>
                        </div>
                        <div class="card-actions">
                            <a href="perusahaan_edit.php?id_perusahaan=<?php echo $p['id_perusahaan']; ?>" class="btn btn-secondary" title="Edit">✏️ Edit Detail</a>
                            <button class="btn btn-primary btn-modal-trigger" 
                                    data-id="<?php echo $p['id_perusahaan']; ?>" 
                                    data-nama="<?php echo htmlspecialchars($p['nama_perusahaan']); ?>"
                                    data-status="<?php echo $p['status_akun']; ?>">
                                ⚙️ Ubah Status
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<div id="statusModal" class="modal-overlay" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h4 id="modalTitle">Ubah Status untuk [Nama Perusahaan]</h4>
            <button id="modalCloseBtn" class="modal-close-btn">&times;</button>
        </div>
        <div class="modal-body" id="modalBody">
            </div>
    </div>
</div>

<style>
/* Modern List Layout & Card Styles */
:root {
    --primary-color: #667eea; --text-primary: #1f2937; --text-secondary: #6b7280;
    --bg-light: #f9fafb; --border-color: #e5e7eb; --card-shadow: 0 4px 6px -1px rgba(0,0,0,0.05), 0 2px 4px -1px rgba(0,0,0,0.04);
    --border-radius: 12px;
}
.kp-list-modern-container {
    font-family: 'Inter', sans-serif; color: var(--text-primary);
    max-width: 1600px; margin: 0 auto; padding: 2rem;
}
.list-hero-section {
    padding: 3rem 2rem; background: linear-gradient(135deg, #4b6cb7 0%, #182848 100%);
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
.company-avatar { background-color: var(--text-secondary); }
.user-name { margin: 0; font-size: 1.3em; font-weight: 600; color: var(--text-primary); }
.user-id.company-bidang { margin: 0.25rem 0 0 0; color: var(--primary-color); font-weight: 500; font-size: 1rem; }
.card-contact-info { padding: 1rem 1.5rem; display: flex; flex-direction: column; gap: 0.75rem; font-size: 0.9em; flex-grow: 1;}
.card-contact-info span { color: var(--text-secondary); word-break: break-all; display:flex; align-items:center; gap: 0.5rem; }
.card-contact-info .icon { font-style: normal; }
.card-actions { margin-top: auto; display: grid; grid-template-columns: 1fr 1fr; background-color: var(--bg-light); border-top: 1px solid var(--border-color); }
.card-actions .btn { padding: 1rem; border: none; background: transparent; text-align: center; text-decoration: none; font-weight: 600; transition: background-color 0.3s ease; cursor: pointer; font-size: 0.9rem; }
.btn.btn-secondary { border-right: 1px solid var(--border-color); color: var(--text-primary); }
.btn.btn-secondary:hover { background-color: #e9ecef; }
.btn.btn-primary { color: var(--primary-color); }
.btn.btn-primary:hover { background-color: var(--primary-color); color: #fff; }
.status-perusahaan-pending-approval { background-color: #fef3c7; color: #92400e; border-color: #fde68a; }
.status-perusahaan-active { background-color: #d1fae5; color: #065f46; border-color: #bbf7d0;}
.status-perusahaan-inactive { background-color: #e5e7eb; color: #4b5563; border-color: #d1d5db;}
.animate-on-scroll { opacity: 0; transform: translateY(20px); transition: opacity 0.5s ease-out, transform 0.5s ease-out; }
.animate-on-scroll.is-visible { opacity: 1; transform: translateY(0); }

/* Modal Styles */
.modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); display: flex; justify-content: center; align-items: center; z-index: 1000; transition: opacity 0.3s ease; }
.modal-content { background: #fff; padding: 2rem; border-radius: var(--border-radius); width: 90%; max-width: 500px; box-shadow: 0 10px 30px rgba(0,0,0,0.2); transform: scale(0.95); transition: transform 0.3s ease; }
.modal-overlay.show .modal-content { transform: scale(1); }
.modal-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border-color); padding-bottom: 1rem; margin-bottom: 1rem; }
.modal-header h4 { margin: 0; font-size: 1.25rem; }
.modal-close-btn { background: none; border: none; font-size: 1.8rem; cursor: pointer; color: var(--text-secondary); }
.modal-body a { display: block; padding: 1rem; margin: 0.5rem 0; border-radius: 8px; text-decoration: none; color: var(--text-primary); background: var(--bg-light); transition: all 0.2s; font-weight: 500; }
.modal-body a:hover { background: var(--primary-color); color: #fff; transform: translateX(5px); }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('statusModal');
    const modalTitle = document.getElementById('modalTitle');
    const modalBody = document.getElementById('modalBody');
    const modalCloseBtn = document.getElementById('modalCloseBtn');
    
    // Function to open the modal
    document.querySelectorAll('.btn-modal-trigger').forEach(button => {
        button.addEventListener('click', function() {
            const id = this.dataset.id;
            const nama = this.dataset.nama;
            const status = this.dataset.status;

            modalTitle.textContent = `Ubah Status untuk: ${nama}`;
            
            let actionsHtml = '';
            if (status === 'pending_approval') {
                actionsHtml += `<a href="?action=approve&id_perusahaan=${id}" onclick="return confirm('Anda yakin ingin menyetujui perusahaan ini?');">✔️ Setujui</a>`;
            }
            if (status === 'active') {
                actionsHtml += `<a href="?action=deactivate&id_perusahaan=${id}" onclick="return confirm('Anda yakin ingin menonaktifkan perusahaan ini?');">🚫 Non-aktifkan</a>`;
            }
            if (status === 'inactive') {
                actionsHtml += `<a href="?action=reactivate&id_perusahaan=${id}" onclick="return confirm('Anda yakin ingin mengaktifkan kembali?');">♻️ Re-Aktifkan</a>`;
            }
            
            modalBody.innerHTML = actionsHtml;
            modal.style.display = 'flex';
            setTimeout(() => modal.classList.add('show'), 10); // For transition
        });
    });

    // Function to close the modal
    function closeModal() {
        modal.classList.remove('show');
        setTimeout(() => modal.style.display = 'none', 300); // Wait for transition
    }

    modalCloseBtn.addEventListener('click', closeModal);
    modal.addEventListener('click', function(event) {
        if (event.target === modal) {
            closeModal();
        }
    });

    // Animasi saat scroll
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