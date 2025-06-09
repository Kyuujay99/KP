<?php
// /KP/dosen/seminar_jadwal_list.php (Versi dengan Fitur Pencarian & Perbaikan Duplikasi)

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

$list_seminar_kp = [];
$error_db = '';

// 2. AMBIL DATA SEMINAR KP YANG TERKAIT DENGAN DOSEN
if ($conn) {
    $sql = "SELECT
                sk.id_seminar, sk.id_pengajuan, pk.judul_kp, pk.nip_dosen_pembimbing_kp,
                m.nim AS nim_mahasiswa, m.nama AS nama_mahasiswa,
                sk.tanggal_seminar, sk.tempat_seminar,
                dp1.nama_dosen AS nama_penguji1, sk.nip_dosen_penguji1,
                dp2.nama_dosen AS nama_penguji2, sk.nip_dosen_penguji2,
                sk.status_pelaksanaan_seminar
            FROM seminar_kp sk
            JOIN (
                SELECT id_pengajuan, MAX(id_seminar) as max_id_seminar
                FROM seminar_kp
                GROUP BY id_pengajuan
            ) as s_max ON sk.id_seminar = s_max.max_id_seminar
            JOIN pengajuan_kp pk ON sk.id_pengajuan = pk.id_pengajuan
            JOIN mahasiswa m ON pk.nim = m.nim
            LEFT JOIN dosen_pembimbing dp1 ON sk.nip_dosen_penguji1 = dp1.nip
            LEFT JOIN dosen_pembimbing dp2 ON sk.nip_dosen_penguji2 = dp2.nip
            WHERE pk.nip_dosen_pembimbing_kp = ?
               OR sk.nip_dosen_penguji1 = ?
               OR sk.nip_dosen_penguji2 = ?
            ORDER BY sk.tanggal_seminar DESC, sk.created_at DESC";

    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("sss", $nip_dosen_login, $nip_dosen_login, $nip_dosen_login);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                if ($row['nip_dosen_pembimbing_kp'] == $nip_dosen_login) {
                    $row['peran_dosen'] = 'Pembimbing';
                } elseif ($row['nip_dosen_penguji1'] == $nip_dosen_login) {
                    $row['peran_dosen'] = 'Penguji 1';
                } elseif ($row['nip_dosen_penguji2'] == $nip_dosen_login) {
                    $row['peran_dosen'] = 'Penguji 2';
                } else {
                    $row['peran_dosen'] = 'Terkait';
                }
                $list_seminar_kp[] = $row;
            }
        }
        $stmt->close();
    } else {
        $error_db = "Gagal menyiapkan query: " . $conn->error;
    }
} else {
    $error_db = "Koneksi database gagal.";
}

$page_title = "Jadwal & Daftar Seminar KP";
require_once '../includes/header.php';
?>

<div class="main-content-dark">
    <div class="list-container">
        <div class="list-header">
            <h1><i class="icon-calendar"></i><?php echo htmlspecialchars($page_title); ?></h1>
            <p>Daftar seminar Kerja Praktek yang terkait dengan Anda, baik sebagai dosen pembimbing maupun sebagai dosen penguji.</p>
        </div>

        <!-- FITUR PENCARIAN BARU -->
        <div class="search-container">
            <input type="text" id="searchInput" onkeyup="searchTable()" placeholder="Cari berdasarkan nama, NIM, atau judul...">
            <i class="icon-search"></i>
        </div>

        <?php if (!empty($error_db)): ?>
            <div class="message error"><p><?php echo $error_db; ?></p></div>
        <?php endif; ?>

        <?php if (empty($list_seminar_kp) && empty($error_db)): ?>
            <div class="message info">
                <p>Saat ini tidak ada data seminar KP yang terkait dengan Anda.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="data-table" id="seminarTable">
                    <thead>
                        <tr>
                            <th>Jadwal & Tempat</th>
                            <th>Mahasiswa</th>
                            <th>Judul KP</th>
                            <th>Peran Anda</th>
                            <th>Tim Penguji</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($list_seminar_kp as $seminar): ?>
                            <tr>
                                <td>
                                    <?php if ($seminar['tanggal_seminar']): ?>
                                        <div class="jadwal-hari"><?php echo date("d M Y", strtotime($seminar['tanggal_seminar'])); ?></div>
                                        <div class="jadwal-jam"><i class="icon-time"></i><?php echo date("H:i", strtotime($seminar['tanggal_seminar'])); ?> WIB</div>
                                        <div class="jadwal-tempat"><i class="icon-location"></i><?php echo htmlspecialchars($seminar['tempat_seminar']); ?></div>
                                    <?php else: ?>
                                        <span class="text-muted"><em>Belum Dijadwalkan</em></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="mahasiswa-nama"><?php echo htmlspecialchars($seminar['nama_mahasiswa']); ?></div>
                                    <div class="mahasiswa-nim"><?php echo htmlspecialchars($seminar['nim_mahasiswa']); ?></div>
                                </td>
                                <td class="judul-kp-cell"><?php echo htmlspecialchars($seminar['judul_kp']); ?></td>
                                <td>
                                    <span class="peran-badge peran-<?php echo strtolower(str_replace(' ', '-', $seminar['peran_dosen'])); ?>">
                                        <?php echo htmlspecialchars($seminar['peran_dosen']); ?>
                                    </span>
                                </td>
                                <td class="penguji-cell">
                                    <p><strong>P1:</strong> <?php echo htmlspecialchars($seminar['nama_penguji1'] ?: '-'); ?></p>
                                    <p><strong>P2:</strong> <?php echo htmlspecialchars($seminar['nama_penguji2'] ?: '-'); ?></p>
                                </td>
                                <td>
                                    <span class="status-badge status-<?php echo strtolower(str_replace('_', '-', $seminar['status_pelaksanaan_seminar'])); ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', htmlspecialchars($seminar['status_pelaksanaan_seminar']))); ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="seminar_kelola_detail.php?id_seminar=<?php echo $seminar['id_seminar']; ?>&id_pengajuan=<?php echo $seminar['id_pengajuan']; ?>" class="btn btn-primary btn-sm">
                                        <i class="icon-detail"></i> Detail
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
             <div id="noResultsMessage" class="message info" style="display: none;">
                <p>Data tidak ditemukan.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
/* Ikon */
.icon-calendar::before { content: "🗓️"; margin-right: 12px; }
.icon-time::before { content: "🕒"; margin-right: 6px; }
.icon-location::before { content: "📍"; margin-right: 6px; }
.icon-detail::before { content: "👁️"; margin-right: 6px; }
.icon-search::before { content: "🔍"; }

/* Variabel Warna Mode Gelap */
:root {
    --primary-color: #3b82f6; --primary-hover: #2563eb; --success-color: #10b981;
    --info-color: #0ea5e9; --warning-color: #f59e0b; --danger-color: #ef4444;
    --secondary-color: #94a3b8; --text-color: #e2e8f0; --border-color: #334155;
    --bg-dark: #0f172a; --bg-card: #1e293b; --bg-hover: #334155;
    --card-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.3), 0 2px 4px -1px rgba(0, 0, 0, 0.2);
    --border-radius: 12px;
}

/* Layout Utama */
.main-content-dark { background-color: var(--bg-dark); padding: 2rem; color: var(--text-color); }
.list-container { max-width: 1600px; margin: 0 auto; padding: 2rem; background-color: var(--bg-card); border-radius: var(--border-radius); box-shadow: var(--card-shadow); border: 1px solid var(--border-color); }
.list-header { margin-bottom: 1.5rem; padding-bottom: 1.5rem; border-bottom: 1px solid var(--border-color); }
.list-header h1 { font-size: 2em; margin: 0 0 0.5rem 0; display: flex; align-items: center; }
.list-header p { font-size: 1.1em; color: var(--secondary-color); margin: 0; }

/* Fitur Pencarian */
.search-container {
    position: relative;
    margin-bottom: 2rem;
}
.search-container #searchInput {
    width: 100%;
    padding: 12px 20px 12px 45px;
    background-color: var(--bg-dark);
    border: 2px solid var(--border-color);
    border-radius: 8px;
    color: var(--text-color);
    font-size: 1rem;
    transition: all 0.2s ease-in-out;
}
.search-container #searchInput:focus {
    outline: none;
    border-color: var(--primary-color);
    box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.2);
}
.search-container .icon-search {
    position: absolute;
    left: 15px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--secondary-color);
    font-size: 1.2rem;
}

/* Tabel */
.table-responsive { overflow-x: auto; }
.data-table { width: 100%; border-collapse: separate; border-spacing: 0; }
.data-table th, .data-table td { padding: 1rem 1.25rem; text-align: left; vertical-align: top; border-bottom: 1px solid var(--border-color); }
.data-table th { background-color: var(--bg-dark); font-weight: 600; color: var(--secondary-color); text-transform: uppercase; font-size: 0.85em; letter-spacing: 0.5px; }
.data-table thead tr th:first-child { border-top-left-radius: 8px; }
.data-table thead tr th:last-child { border-top-right-radius: 8px; }
.data-table tbody tr { transition: background-color 0.2s ease-in-out; }
.data-table tbody tr:hover { background-color: var(--bg-hover); }
.data-table tbody tr:last-child td { border-bottom: none; }
.data-table tbody tr:last-child td:first-child { border-bottom-left-radius: 8px; }
.data-table tbody tr:last-child td:last-child { border-bottom-right-radius: 8px; }

/* Styling Kolom Spesifik */
.jadwal-hari { font-weight: 600; font-size: 1.1em; color: var(--text-color); }
.jadwal-jam, .jadwal-tempat { color: var(--secondary-color); display: flex; align-items: center; }
.mahasiswa-nama { font-weight: 600; color: var(--text-color); }
.mahasiswa-nim { font-size: 0.9em; color: var(--secondary-color); }
.judul-kp-cell { min-width: 300px; max-width: 450px; white-space: normal; }
.penguji-cell p { margin: 0 0 0.5rem 0; }
.penguji-cell p:last-child { margin-bottom: 0; }
.text-muted { color: var(--secondary-color); }

/* Badge */
.peran-badge, .status-badge { padding: 6px 12px; border-radius: 20px; font-size: 0.85em; font-weight: 600; text-transform: capitalize; display: inline-block; }
.peran-pembimbing { background: rgba(16, 185, 129, 0.2); color: #6ee7b7; }
.peran-penguji-1, .peran-penguji-2 { background: rgba(59, 130, 246, 0.2); color: #93c5fd; }
.status-dijadwalkan { background: rgba(14, 165, 233, 0.2); color: #7dd3fc; }
.status-selesai { background: rgba(59, 130, 246, 0.2); color: #93c5fd; }
.status-dibatalkan { background: rgba(148, 163, 184, 0.2); color: #94a3b8; }
.status-ditunda { background: rgba(245, 158, 11, 0.2); color: #fcd34d; }

/* Tombol dan Pesan */
.message { padding: 1rem 1.5rem; border-radius: 8px; margin-top: 1rem; text-align: center; }
.message.info { background-color: rgba(59, 130, 246, 0.1); color: #93c5fd; }
.message.error { background-color: rgba(239, 68, 68, 0.1); color: #fca5a5; }
.btn.btn-primary { background-color: var(--primary-color); color: white; border: none; padding: 8px 16px; border-radius: 8px; text-decoration: none; font-weight: 600; transition: background-color 0.2s ease; display: inline-flex; align-items: center; }
.btn.btn-primary:hover { background-color: var(--primary-hover); }
</style>

<script>
// Fungsi untuk mencari data di dalam tabel
function searchTable() {
    // 1. Deklarasi variabel
    let input, filter, table, tr, td, i, txtValue;
    input = document.getElementById("searchInput");
    filter = input.value.toUpperCase();
    table = document.getElementById("seminarTable");
    tr = table.getElementsByTagName("tr");
    let noResultsMessage = document.getElementById("noResultsMessage");
    let resultsFound = false;

    // 2. Loop melalui semua baris tabel, dan sembunyikan yang tidak cocok
    for (i = 1; i < tr.length; i++) { // Mulai dari 1 untuk skip header
        // Mengambil semua sel dalam satu baris
        let cells = tr[i].getElementsByTagName("td");
        let rowText = '';
        
        // Menggabungkan teks dari sel mahasiswa (indeks 1) dan judul (indeks 2)
        if (cells[1]) {
            rowText += cells[1].textContent || cells[1].innerText;
        }
        if (cells[2]) {
            rowText += ' ' + (cells[2].textContent || cells[2].innerText);
        }

        if (rowText.toUpperCase().indexOf(filter) > -1) {
            tr[i].style.display = "";
            resultsFound = true;
        } else {
            tr[i].style.display = "none";
        }
    }

    // Tampilkan pesan "tidak ditemukan" jika tidak ada hasil
    if (resultsFound) {
        noResultsMessage.style.display = "none";
    } else {
        noResultsMessage.style.display = "block";
    }
}
</script>

<?php
require_once '../includes/footer.php';
if (isset($conn)) {
    $conn->close();
}
?>
