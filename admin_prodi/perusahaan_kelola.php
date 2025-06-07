<?php
// /KP/admin_prodi/perusahaan_kelola.php (Versi Baru Disesuaikan)

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

// Logika untuk memproses perubahan status
if (isset($_GET['action']) && isset($_GET['id_perusahaan'])) {
    if ($conn) {
        $action = $_GET['action'];
        $id_perusahaan_aksi = filter_var($_GET['id_perusahaan'], FILTER_VALIDATE_INT);
        $new_status = '';
        if ($id_perusahaan_aksi) {
            if ($action === 'approve_perusahaan') $new_status = 'active';
            elseif ($action === 'deactivate_perusahaan') $new_status = 'inactive';
            elseif ($action === 'reactivate_perusahaan') $new_status = 'active';

            if (!empty($new_status)) {
                $stmt = $conn->prepare("UPDATE perusahaan SET status_akun = ? WHERE id_perusahaan = ?");
                if ($stmt) {
                    $stmt->bind_param("si", $new_status, $id_perusahaan_aksi);
                    if ($stmt->execute()) {
                        if ($stmt->affected_rows > 0) {
                            $success_message = "Status akun perusahaan berhasil diubah menjadi '" . ucfirst($new_status) . "'.";
                        } else {
                            $error_message = "Tidak ada perubahan status, mungkin status sudah sama.";
                        }
                    } else {
                        $error_message = "Gagal mengubah status: " . $stmt->error;
                    }
                    $stmt->close();
                } else {
                    $error_message = "Gagal menyiapkan statement: " . $conn->error;
                }
            } else {
                $error_message = "Tindakan tidak valid.";
            }
        } else {
            $error_message = "ID Perusahaan tidak valid.";
        }
    } else {
        $error_message = "Koneksi database gagal.";
    }
}

// Logika untuk mengambil daftar perusahaan
if ($conn) {
    $sql_perusahaan = "SELECT id_perusahaan, nama_perusahaan, email_perusahaan, bidang, kontak_person_nama, kontak_person_no_hp, status_akun FROM perusahaan ORDER BY CASE WHEN status_akun = 'pending_approval' THEN 1 WHEN status_akun = 'active' THEN 2 ELSE 3 END, nama_perusahaan ASC";
    $result_perusahaan_db = $conn->query($sql_perusahaan);
    if ($result_perusahaan_db) {
        while ($row = $result_perusahaan_db->fetch_assoc()) {
            $list_perusahaan[] = $row;
        }
    } else {
        $error_message = "Gagal mengambil data perusahaan: " . $conn->error;
    }
} else {
    if(empty($error_message)) $error_message = "Koneksi database gagal.";
}

$page_title = "Kelola Data Perusahaan Mitra";
require_once '../includes/header.php';
?>

<div class="main-content-full">
    <div class="list-container">
        <div class="list-header">
            <h1><?php echo htmlspecialchars($page_title); ?></h1>
            <p>Verifikasi, kelola, dan lihat semua perusahaan mitra yang terdaftar dalam sistem.</p>
            <a href="perusahaan_tambah.php" class="btn btn-primary">➕ Tambah Perusahaan Baru</a>
        </div>

        <div class="filter-search-container">
            <div class="search-wrapper">
                <input type="text" id="userSearchInput" placeholder="Cari perusahaan berdasarkan nama atau bidang...">
                <span class="search-icon">🔍</span>
            </div>
        </div>

        <?php if (!empty($success_message)): ?>
            <div class="message success"><p><?php echo htmlspecialchars($success_message); ?></p></div>
        <?php endif; ?>
        <?php if (!empty($error_message)): ?>
            <div class="message error"><p><?php echo htmlspecialchars($error_message); ?></p></div>
        <?php endif; ?>

        <?php if (empty($list_perusahaan) && empty($error_message)): ?>
            <div class="message info">
                <h4>Data Kosong</h4>
                <p>Belum ada data perusahaan mitra yang terdaftar.</p>
            </div>
        <?php else: ?>
            <div class="user-card-grid" id="userCardGrid">
                <?php foreach ($list_perusahaan as $p): ?>
                    <div class="user-card">
                        <div class="card-header-status status-perusahaan-<?php echo strtolower(htmlspecialchars($p['status_akun'])); ?>">
                            <?php echo ucfirst(str_replace('_', ' ', htmlspecialchars($p['status_akun']))); ?>
                        </div>
                        <div class="card-main-info">
                            <div class="user-avatar company-avatar">🏢</div>
                            <h4 class="user-name"><?php echo htmlspecialchars($p['nama_perusahaan']); ?></h4>
                            <p class="user-id company-bidang"><?php echo htmlspecialchars($p['bidang'] ?: 'Bidang belum diatur'); ?></p>
                        </div>
                        <div class="card-contact-info">
                            <span>📧 <?php echo htmlspecialchars($p['email_perusahaan']); ?></span>
                            <span class="company-pic">👤 PIC: <?php echo htmlspecialchars($p['kontak_person_nama'] ?: 'N/A'); ?> (<?php echo htmlspecialchars($p['kontak_person_no_hp'] ?: '-'); ?>)</span>
                        </div>
                        <div class="card-actions">
                            <a href="perusahaan_edit.php?id_perusahaan=<?php echo $p['id_perusahaan']; ?>" class="btn btn-secondary" title="Edit">Edit Detail</a>
                            <div class="dropdown">
                                <button class="btn btn-primary dropdown-toggle" title="Ubah Status">Ubah Status</button>
                                <div class="dropdown-menu">
                                    <?php if ($p['status_akun'] === 'pending_approval'): ?>
                                        <a href="?action=approve_perusahaan&id_perusahaan=<?php echo $p['id_perusahaan']; ?>" onclick="return confirm('Anda yakin ingin menyetujui perusahaan ini?');">Setujui</a>
                                    <?php endif; ?>
                                    <?php if ($p['status_akun'] === 'active'): ?>
                                        <a href="?action=deactivate_perusahaan&id_perusahaan=<?php echo $p['id_perusahaan']; ?>" onclick="return confirm('Anda yakin ingin menonaktifkan perusahaan ini?');">Non-aktifkan</a>
                                    <?php endif; ?>
                                    <?php if ($p['status_akun'] === 'inactive'): ?>
                                        <a href="?action=reactivate_perusahaan&id_perusahaan=<?php echo $p['id_perusahaan']; ?>" onclick="return confirm('Anda yakin ingin mengaktifkan kembali?');">Re-Aktifkan</a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <div id="noResultsMessage" class="message info" style="display: none;">
                <h4>Pencarian Tidak Ditemukan</h4>
                <p>Tidak ada perusahaan yang cocok dengan kata kunci pencarian Anda.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
    /* Menggunakan gaya dari halaman kelola mahasiswa, hanya kustomisasi kecil */
    .company-avatar { background-color: #6c757d; }
    .company-bidang { color: var(--primary-color); font-weight: 500; font-family: 'Poppins', sans-serif; }
    .company-pic { color: #495057; }
    
    /* Status Perusahaan */
    .status-perusahaan-pending-approval { background-color: #ffc107; color: #212529; }
    .status-perusahaan-active { background-color: #28a745; color: #fff; }
    .status-perusahaan-inactive { background-color: #6c757d; color: #fff; }

    /* Salin semua CSS dari file pengguna_mahasiswa_kelola.php di sini */
    .user-card-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 1.5rem; }
    .user-card { background-color: #fff; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.06); display: flex; flex-direction: column; overflow: hidden; position: relative; transition: all 0.3s ease; }
    .user-card:hover { transform: translateY(-5px); box-shadow: 0 10px 30px rgba(0,0,0,0.1); }
    .card-header-status { position: absolute; top: 15px; right: 15px; padding: 5px 12px; border-radius: 20px; font-size: 0.8em; font-weight: 600; color: #fff; }
    .card-main-info { padding: 2rem 1.5rem 1.5rem; text-align: center; border-bottom: 1px solid #dee2e6; }
    .user-avatar { width: 80px; height: 80px; border-radius: 50%; color: white; display: inline-flex; align-items: center; justify-content: center; font-size: 2.5em; font-weight: 600; margin-bottom: 1rem; border: 4px solid #fff; box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
    .user-name { margin: 0; font-size: 1.3em; font-weight: 600; color: #343a40; }
    .user-id { margin: 0; color: #6c757d; font-family: monospace; }
    .card-contact-info { padding: 1rem 1.5rem; display: flex; flex-direction: column; gap: 0.5rem; font-size: 0.9em; }
    .card-contact-info span { color: #495057; word-break: break-all; }
    .card-actions { margin-top: auto; display: grid; grid-template-columns: 1fr 1fr; background-color: #f8f9fa; border-top: 1px solid #dee2e6; }
    .card-actions .btn { padding: 1rem; border-radius: 0; text-align: center; text-decoration: none; font-weight: 600; transition: background-color 0.3s ease; }
    .btn.btn-secondary { border-right: 1px solid #dee2e6; color: #495057; }
    .btn.btn-secondary:hover { background-color: #e2e6ea; }
    .btn.btn-primary { background-color: transparent; color: #007bff; }
    .btn.btn-primary:hover { background-color: #007bff; color: #fff; }
    .dropdown { position: relative; }
    .dropdown-toggle::after { content: ' ▼'; font-size: 0.7em; }
    .dropdown-menu { display: none; position: absolute; bottom: 100%; right: 0; background-color: white; min-width: 160px; box-shadow: 0 8px 16px rgba(0,0,0,0.2); z-index: 1; border-radius: 8px; overflow: hidden; }
    .dropdown-menu a { color: black; padding: 12px 16px; text-decoration: none; display: block; }
    .dropdown-menu a:hover { background-color: #f1f1f1; }
    .dropdown:hover .dropdown-menu { display: block; }
    .filter-search-container { margin-bottom: 2rem; }
    .search-wrapper { position: relative; max-width: 500px; }
    #userSearchInput { width: 100%; padding: 12px 20px 12px 45px; border: 1px solid #dee2e6; border-radius: 50px; font-size: 1em; transition: all 0.3s ease; }
    #userSearchInput:focus { border-color: #007bff; box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.2); outline: none; }
    .search-icon { position: absolute; left: 15px; top: 50%; transform: translateY(-50%); font-size: 1.2em; color: #6c757d; }
</style>

<script>
// Menggunakan skrip pencarian yang sama persis
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
                const companyName = card.querySelector('.user-name').textContent.toLowerCase();
                const companyBidang = card.querySelector('.company-bidang').textContent.toLowerCase();
                if (companyName.includes(searchTerm) || companyBidang.includes(searchTerm)) {
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