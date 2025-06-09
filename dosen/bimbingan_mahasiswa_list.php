<?php
// /KP/dosen/bimbingan_mahasiswa_list.php (Versi Diperbaiki & Dipercantik)

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

$list_mahasiswa_bimbingan = [];
$error_db = '';

// 2. AMBIL DATA MAHASISWA BIMBINGAN YANG KP-NYA AKTIF
if ($conn) {
    $status_kp_aktif = ['kp_berjalan', 'selesai_pelaksanaan', 'disetujui_dospem', 'diterima_perusahaan'];
    $status_placeholders = implode(',', array_fill(0, count($status_kp_aktif), '?'));

    $sql = "SELECT
                m.nim, m.nama AS nama_mahasiswa,
                pk.id_pengajuan, pk.judul_kp, pk.status_pengajuan,
                (SELECT COUNT(*) FROM bimbingan_kp bk WHERE bk.id_pengajuan = pk.id_pengajuan) AS jumlah_sesi_bimbingan,
                (SELECT MAX(bk_last.tanggal_bimbingan) FROM bimbingan_kp bk_last WHERE bk_last.id_pengajuan = pk.id_pengajuan) AS bimbingan_terakhir
            FROM pengajuan_kp pk
            JOIN mahasiswa m ON pk.nim = m.nim
            WHERE pk.nip_dosen_pembimbing_kp = ? 
              AND pk.status_pengajuan IN ($status_placeholders)
            ORDER BY m.nama ASC, pk.id_pengajuan DESC";

    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $types = 's' . str_repeat('s', count($status_kp_aktif));
        $params = array_merge([$nip_dosen_login], $status_kp_aktif);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $list_mahasiswa_bimbingan[] = $row;
        }
        $stmt->close();
    } else {
        $error_db = "Gagal menyiapkan query: " . $conn->error;
    }
} else {
    $error_db = "Koneksi database gagal.";
}

$page_title = "Daftar Mahasiswa Bimbingan KP";
require_once '../includes/header.php';
?>

<div class="main-content-dark">
    <div class="list-container">
        <div class="list-header">
            <h1><i class="icon-users"></i><?php echo htmlspecialchars($page_title); ?></h1>
            <p>Berikut adalah daftar mahasiswa yang sedang atau akan melaksanakan Kerja Praktek di bawah bimbingan Anda.</p>
        </div>

        <div class="search-container">
            <input type="text" id="searchInput" onkeyup="searchCards()" placeholder="Cari mahasiswa berdasarkan nama, NIM, atau judul...">
            <i class="icon-search"></i>
        </div>

        <?php if (!empty($error_db)): ?>
            <div class="message error"><p><?php echo $error_db; ?></p></div>
        <?php endif; ?>

        <?php if (empty($list_mahasiswa_bimbingan) && empty($error_db)): ?>
            <div class="message info">
                <p>Saat ini tidak ada mahasiswa aktif yang menjadi bimbingan Anda.</p>
            </div>
        <?php else: ?>
            <div class="card-grid" id="bimbinganCardContainer">
                <?php foreach ($list_mahasiswa_bimbingan as $mhs_kp): ?>
                    <div class="bimbingan-card">
                        <div class="card-header">
                            <div class="mahasiswa-info">
                                <div class="mahasiswa-avatar"><?php echo strtoupper(substr($mhs_kp['nama_mahasiswa'], 0, 1)); ?></div>
                                <div>
                                    <h4 class="mahasiswa-nama"><?php echo htmlspecialchars($mhs_kp['nama_mahasiswa']); ?></h4>
                                    <p class="mahasiswa-nim"><?php echo htmlspecialchars($mhs_kp['nim']); ?></p>
                                </div>
                            </div>
                            <span class="status-badge status-<?php echo strtolower(str_replace([' ', '_'], '-', $mhs_kp['status_pengajuan'])); ?>">
                                <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $mhs_kp['status_pengajuan']))); ?>
                            </span>
                        </div>
                        <div class="card-body">
                            <p class="judul-kp-title">Judul KP:</p>
                            <p class="judul-kp-text"><?php echo htmlspecialchars($mhs_kp['judul_kp']); ?></p>
                            <div class="bimbingan-stats">
                                <div>
                                    <span>Total Bimbingan</span>
                                    <strong><?php echo $mhs_kp['jumlah_sesi_bimbingan']; ?> Sesi</strong>
                                </div>
                                <div>
                                    <span>Bimbingan Terakhir</span>
                                    <strong><?php echo $mhs_kp['bimbingan_terakhir'] ? date("d M Y", strtotime($mhs_kp['bimbingan_terakhir'])) : 'Belum Ada'; ?></strong>
                                </div>
                            </div>
                        </div>
                        <div class="card-footer">
                            <a href="bimbingan_kelola.php?id_pengajuan=<?php echo $mhs_kp['id_pengajuan']; ?>" class="btn btn-primary">
                                <i class="icon-manage"></i> Kelola Bimbingan
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <div id="noResultsMessage" class="message info" style="display: none;">
                <p>Mahasiswa tidak ditemukan.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
/* Ikon */
.icon-users::before { content: "👥"; margin-right: 12px; }
.icon-search::before { content: "🔍"; }
.icon-manage::before { content: "✍️"; margin-right: 8px; }

/* Variabel Warna Mode Gelap */
:root {
    --primary-color: #3b82f6; --primary-hover: #2563eb; --secondary-color: #94a3b8;
    --text-color: #e2e8f0; --border-color: #334155; --bg-dark: #0f172a;
    --bg-card: #1e293b; --bg-hover: #334155; --card-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.3);
    --border-radius: 12px;
}

/* Layout Utama */
.main-content-dark { background-color: var(--bg-dark); padding: 2rem; color: var(--text-color); min-height: 100vh;}
.list-container { max-width: 1600px; margin: 0 auto; }
.list-header { text-align: center; margin-bottom: 2rem; }
.list-header h1 { font-size: 2.2em; margin-bottom: 0.5rem; }
.list-header p { font-size: 1.1em; color: var(--secondary-color); max-width: 800px; margin: auto; }

/* Pencarian */
.search-container { position: relative; margin-bottom: 2rem; }
.search-container #searchInput { width: 100%; padding: 12px 20px 12px 45px; background-color: var(--bg-card); border: 2px solid var(--border-color); border-radius: 8px; color: var(--text-color); font-size: 1rem; transition: all 0.2s; }
.search-container #searchInput:focus { outline: none; border-color: var(--primary-color); }
.search-container .icon-search { position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: var(--secondary-color); }

/* Grid Kartu */
.card-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(350px, 1fr)); gap: 1.5rem; }
.bimbingan-card { background-color: var(--bg-card); border-radius: var(--border-radius); box-shadow: var(--card-shadow); display: flex; flex-direction: column; transition: transform 0.2s, box-shadow 0.2s; border: 1px solid var(--border-color); }
.bimbingan-card:hover { transform: translateY(-5px); box-shadow: 0 8px 25px rgba(0,0,0,0.2); }
.bimbingan-card .card-header { display: flex; justify-content: space-between; align-items: flex-start; padding: 1.5rem; border-bottom: 1px solid var(--border-color); }
.mahasiswa-info { display: flex; align-items: center; gap: 15px; }
.mahasiswa-avatar { width: 50px; height: 50px; border-radius: 50%; background-color: var(--primary-color); color: white; display: inline-flex; align-items: center; justify-content: center; font-size: 1.5em; font-weight: 600; flex-shrink: 0; }
.mahasiswa-nama { margin: 0; font-size: 1.2em; font-weight: 600; color: var(--text-color); }
.mahasiswa-nim { margin: 0; color: var(--secondary-color); }
.bimbingan-card .card-body { padding: 1.5rem; flex-grow: 1; }
.judul-kp-title { font-size: 0.9em; color: var(--secondary-color); margin-bottom: 0.25rem; }
.judul-kp-text { font-weight: 500; color: var(--text-color); margin-top: 0; margin-bottom: 1.5rem; min-height: 4.5em; }
.bimbingan-stats { display: flex; justify-content: space-between; background-color: var(--bg-dark); padding: 1rem; border-radius: 8px; }
.bimbingan-stats > div { text-align: center; }
.bimbingan-stats span { display: block; font-size: 0.85em; color: var(--secondary-color); }
.bimbingan-stats strong { display: block; font-size: 1.2em; color: var(--text-color); font-weight: 600; }
.bimbingan-card .card-footer { padding: 1rem; background-color: var(--bg-dark); border-top: 1px solid var(--border-color); }
.btn-primary { width: 100%; justify-content: center; }

/* Badge & Pesan */
.status-badge { padding: 5px 12px; border-radius: 20px; font-size: 0.8em; font-weight: bold; }
.status-kp-berjalan { background-color: rgba(59, 130, 246, 0.2); color: #93c5fd; }
.status-disetujui-dospem, .status-diterima-perusahaan, .status-selesai-pelaksanaan { background-color: rgba(16, 185, 129, 0.2); color: #6ee7b7; }
.message { padding: 1.5rem; border-radius: 8px; margin-top: 1rem; text-align: center; background-color: var(--bg-card); border: 1px solid var(--border-color); }
</style>

<script>
function searchCards() {
    let input = document.getElementById('searchInput');
    let filter = input.value.toUpperCase();
    let cardContainer = document.getElementById('bimbinganCardContainer');
    let cards = cardContainer.getElementsByClassName('bimbingan-card');
    let noResultsMessage = document.getElementById("noResultsMessage");
    let resultsFound = false;

    for (let i = 0; i < cards.length; i++) {
        let title = cards[i].querySelector('.judul-kp-text');
        let name = cards[i].querySelector('.mahasiswa-nama');
        let nim = cards[i].querySelector('.mahasiswa-nim');
        if (title && name && nim) {
            let textValue = (name.textContent || name.innerText) + " " + (nim.textContent || nim.innerText) + " " + (title.textContent || title.innerText);
            if (textValue.toUpperCase().indexOf(filter) > -1) {
                cards[i].style.display = "";
                resultsFound = true;
            } else {
                cards[i].style.display = "none";
            }
        }
    }
    noResultsMessage.style.display = resultsFound ? "none" : "block";
}
</script>

<?php
require_once '../includes/footer.php';
if (isset($conn)) { $conn->close(); }
?>
