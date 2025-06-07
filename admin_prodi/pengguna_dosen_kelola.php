<?php
// /KP/admin_prodi/pengguna_dosen_kelola.php (Versi Diperbarui)

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

if (isset($_GET['action']) && isset($_GET['nip'])) {
    $action = $_GET['action'];
    $nip_aksi = $_GET['nip'];
    $new_status = '';

    if ($action === 'activate_dosen') {
        $new_status = 'active';
    } elseif ($action === 'deactivate_dosen') {
        $new_status = 'inactive';
    }

    if (!empty($new_status) && $conn) {
        $stmt_update = $conn->prepare("UPDATE dosen_pembimbing SET status_akun = ? WHERE nip = ?");
        if ($stmt_update) {
            $stmt_update->bind_param("ss", $new_status, $nip_aksi);
            if ($stmt_update->execute() && $stmt_update->affected_rows > 0) {
                $success_message = "Status akun untuk Dosen NIP " . htmlspecialchars($nip_aksi) . " berhasil diubah.";
            }
        }
    }
}

if ($conn) {
    $result_dosen = $conn->query("SELECT nip, nama_dosen, email, status_akun FROM dosen_pembimbing ORDER BY nama_dosen ASC");
    if ($result_dosen) {
        while ($row = $result_dosen->fetch_assoc()) {
            $list_dosen[] = $row;
        }
    } else {
        $error_message = "Gagal mengambil data dosen: " . $conn->error;
    }
} else {
    $error_message = "Koneksi database gagal.";
}

$page_title = "Kelola Akun Dosen";
require_once '../includes/header.php';
?>

<div class="main-content-full">
    <div class="list-container">
        <div class="list-header">
            <h1><?php echo htmlspecialchars($page_title); ?></h1>
            <p>Lihat, tambah, edit, dan kelola status semua akun dosen pembimbing dan penguji.</p>
            <a href="pengguna_dosen_tambah.php" class="btn btn-primary">➕ Tambah Dosen Baru</a>
        </div>

        <div class="filter-search-container">
            <div class="search-wrapper">
                <input type="text" id="userSearchInput" placeholder="Cari dosen berdasarkan nama atau NIP...">
                <span class="search-icon">🔍</span>
            </div>
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
                    <div class="user-card">
                        <div class="card-header-status status-dosen-<?php echo strtolower(htmlspecialchars($dosen['status_akun'])); ?>">
                            <?php echo ucfirst(htmlspecialchars($dosen['status_akun'])); ?>
                        </div>
                        <div class="card-main-info">
                            <div class="user-avatar"><?php echo strtoupper(substr($dosen['nama_dosen'], 0, 1)); ?></div>
                            <h4 class="user-name"><?php echo htmlspecialchars($dosen['nama_dosen']); ?></h4>
                            <p class="user-id">NIP: <?php echo htmlspecialchars($dosen['nip']); ?></p>
                        </div>
                        <div class="card-contact-info">
                            <span>📧 <?php echo htmlspecialchars($dosen['email']); ?></span>
                        </div>
                        <div class="card-actions">
                            <a href="pengguna_dosen_edit.php?nip=<?php echo htmlspecialchars($dosen['nip']); ?>" class="btn btn-secondary" title="Edit">Edit Profil</a>
                            <div class="dropdown">
                                <button class="btn btn-primary dropdown-toggle" title="Ubah Status">Ubah Status</button>
                                <div class="dropdown-menu">
                                    <?php if ($dosen['status_akun'] === 'inactive'): ?>
                                        <a href="?action=activate_dosen&nip=<?php echo htmlspecialchars($dosen['nip']); ?>" onclick="return confirm('Aktifkan akun ini?');">Aktifkan</a>
                                    <?php elseif ($dosen['status_akun'] === 'active'): ?>
                                        <a href="?action=deactivate_dosen&nip=<?php echo htmlspecialchars($dosen['nip']); ?>" onclick="return confirm('Non-aktifkan akun ini?');">Non-aktifkan</a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <div id="noResultsMessage" class="message info" style="display: none;">
                <h4>Pencarian Tidak Ditemukan</h4>
                <p>Tidak ada dosen yang cocok dengan kata kunci pencarian Anda.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
/* Menggunakan gaya dari halaman kelola mahasiswa */
.status-dosen-active { background-color: #28a745; }
.status-dosen-inactive { background-color: #6c757d; }
    .user-card-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
        gap: 1.5rem;
    }
    .user-card {
        background-color: #fff;
        border-radius: var(--border-radius);
        box-shadow: var(--card-shadow);
        display: flex;
        flex-direction: column;
        overflow: hidden;
        position: relative;
        transition: transform 0.3s ease, box-shadow 0.3s ease;
    }
    .user-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 30px rgba(0,0,0,0.1);
    }

    .card-header-status {
        position: absolute;
        top: 15px;
        right: 15px;
        padding: 5px 12px;
        border-radius: 20px;
        font-size: 0.8em;
        font-weight: 600;
        color: #fff;
    }
    .status-pending_verification { background-color: #ffc107; color: #212529;}
    .status-active { background-color: #28a745; }
    .status-suspended { background-color: #dc3545; }

    .card-main-info {
        padding: 2rem 1.5rem 1.5rem;
        text-align: center;
        border-bottom: 1px solid var(--border-color);
    }
    .user-avatar {
        width: 80px;
        height: 80px;
        border-radius: 50%;
        background-color: var(--primary-color);
        color: white;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 2.5em;
        font-weight: 600;
        margin-bottom: 1rem;
        border: 4px solid #fff;
        box-shadow: 0 4px 10px rgba(0,0,0,0.1);
    }
    .user-name {
        margin: 0;
        font-size: 1.3em;
        font-weight: 600;
        color: var(--dark-color);
    }
    .user-id {
        margin: 0;
        color: var(--secondary-color);
        font-family: monospace;
    }
    .user-extra-info {
        margin-top: 0.5rem;
        font-size: 0.9em;
        color: var(--secondary-color);
    }
    
    .card-contact-info {
        padding: 1rem 1.5rem;
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
        font-size: 0.9em;
    }
    .card-contact-info span {
        color: #495057;
        word-break: break-all;
    }
    
    .card-actions {
        margin-top: auto; /* Mendorong ke bawah */
        display: grid;
        grid-template-columns: 1fr 1fr;
        background-color: #f8f9fa;
        border-top: 1px solid var(--border-color);
    }
    .card-actions .btn {
        padding: 1rem;
        border-radius: 0;
        text-align: center;
        text-decoration: none;
        font-weight: 600;
        transition: background-color 0.3s ease;
    }
    .btn.btn-secondary {
        border-right: 1px solid var(--border-color);
        color: #495057;
    }
    .btn.btn-secondary:hover { background-color: #e2e6ea; }
    .btn.btn-primary {
        background-color: transparent;
        color: var(--primary-color);
    }
    .btn.btn-primary:hover {
        background-color: var(--primary-color);
        color: #fff;
    }

    /* Dropdown untuk Aksi Status */
    .dropdown { position: relative; }
    .dropdown-toggle::after { content: ' ▼'; font-size: 0.7em; }
    .dropdown-menu {
        display: none;
        position: absolute;
        bottom: 100%; /* Muncul di atas tombol */
        right: 0;
        background-color: white;
        min-width: 160px;
        box-shadow: 0 8px 16px rgba(0,0,0,0.2);
        z-index: 1;
        border-radius: 8px;
        overflow: hidden;
    }
    .dropdown-menu a {
        color: black;
        padding: 12px 16px;
        text-decoration: none;
        display: block;
    }
    .dropdown-menu a:hover { background-color: #f1f1f1; }
    .dropdown:hover .dropdown-menu { display: block; }
    
    /* BARU: CSS untuk Kotak Pencarian */
    .filter-search-container {
        margin-bottom: 2rem;
    }
    .search-wrapper {
        position: relative;
        max-width: 500px;
    }
    #userSearchInput {
        width: 100%;
        padding: 12px 20px 12px 45px; /* Padding kiri untuk ikon */
        border: 1px solid var(--border-color);
        border-radius: 50px; /* Bentuk pil */
        font-size: 1em;
        transition: all 0.3s ease;
    }
    #userSearchInput:focus {
        border-color: var(--primary-color);
        box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.2);
        outline: none;
    }
    .search-icon {
        position: absolute;
        left: 15px;
        top: 50%;
        transform: translateY(-50%);
        font-size: 1.2em;
        color: var(--secondary-color);
    }</style>

<script>
// Menggunakan skrip pencarian yang sama dengan halaman kelola mahasiswa
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('userSearchInput');
    const userCardGrid = document.getElementById('userCardGrid');
    const noResultsMessage = document.getElementById('noResultsMessage');
    
    if (searchInput && userCardGrid) {
        const userCards = userCardGrid.querySelectorAll('.user-card');
        searchInput.addEventListener('keyup', function() {
            const searchTerm = searchInput.value.toLowerCase();
            let visibleCards = 0;
            userCards.forEach(function(card) {
                const userName = card.querySelector('.user-name').textContent.toLowerCase();
                const userId = card.querySelector('.user-id').textContent.toLowerCase();
                if (userName.includes(searchTerm) || userId.includes(searchTerm)) {
                    card.style.display = 'flex';
                    visibleCards++;
                } else {
                    card.style.display = 'none';
                }
            });
            if (noResultsMessage) {
                noResultsMessage.style.display = (visibleCards === 0) ? 'block' : 'none';
            }
        });
    }
});
</script>

<?php
require_once '../includes/footer.php';
if (isset($conn)) { $conn->close(); }
?>