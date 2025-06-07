<?php
// /KP/dosen/bimbingan_kelola.php (Versi Diperbaiki & Dipercantik)

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
$id_pengajuan_url = null;
$pengajuan_info = null;
$riwayat_bimbingan = [];
$error_message = '';
$success_message = '';

// 2. VALIDASI DAN AMBIL ID PENGAJUAN DARI URL
if (isset($_GET['id_pengajuan']) && filter_var($_GET['id_pengajuan'], FILTER_VALIDATE_INT)) {
    $id_pengajuan_url = (int)$_GET['id_pengajuan'];
} else {
    $error_message = "ID Pengajuan tidak valid atau tidak ditemukan.";
}

require_once '../config/db_connect.php';

// 3. FUNGSI UNTUK MENGAMBIL DATA
function getBimbinganData($conn_db, $id_pengajuan, $nip_dosen, &$out_error_message) {
    $data = ['pengajuan' => null, 'riwayat' => []];
    $sql_pengajuan = "SELECT pk.id_pengajuan, pk.judul_kp, pk.status_pengajuan, 
                             m.nim, m.nama AS nama_mahasiswa, m.prodi
                      FROM pengajuan_kp pk
                      JOIN mahasiswa m ON pk.nim = m.nim
                      WHERE pk.id_pengajuan = ? AND pk.nip_dosen_pembimbing_kp = ?";
    $stmt_pengajuan = $conn_db->prepare($sql_pengajuan);
    if ($stmt_pengajuan) {
        $stmt_pengajuan->bind_param("is", $id_pengajuan, $nip_dosen);
        $stmt_pengajuan->execute();
        $result_pengajuan = $stmt_pengajuan->get_result();
        if ($result_pengajuan->num_rows === 1) {
            $data['pengajuan'] = $result_pengajuan->fetch_assoc();
            $sql_riwayat = "SELECT id_bimbingan, tanggal_bimbingan, topik_bimbingan, catatan_mahasiswa, catatan_dosen, file_lampiran_mahasiswa, file_lampiran_dosen, status_bimbingan FROM bimbingan_kp WHERE id_pengajuan = ? ORDER BY tanggal_bimbingan DESC";
            $stmt_riwayat = $conn_db->prepare($sql_riwayat);
            if ($stmt_riwayat) {
                $stmt_riwayat->bind_param("i", $id_pengajuan);
                $stmt_riwayat->execute();
                $result_riwayat = $stmt_riwayat->get_result();
                while ($row_riwayat = $result_riwayat->fetch_assoc()) {
                    $data['riwayat'][] = $row_riwayat;
                }
                $stmt_riwayat->close();
            }
        } else {
            $out_error_message = "Pengajuan KP tidak ditemukan atau Anda bukan pembimbingnya.";
        }
        $stmt_pengajuan->close();
    }
    return $data;
}

// 4. PROSES PENAMBAHAN SESI BIMBINGAN BARU
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_bimbingan']) && $id_pengajuan_url !== null && empty($error_message)) {
    $tanggal_bimbingan_input = $_POST['tanggal_bimbingan'];
    $topik_bimbingan_input = trim($_POST['topik_bimbingan']);
    $catatan_dosen_input = trim($_POST['catatan_dosen']);
    
    if (empty($tanggal_bimbingan_input) || empty($topik_bimbingan_input)) {
        $error_message = "Tanggal dan Topik Bimbingan wajib diisi.";
    } else {
        try {
            $tanggal_bimbingan_db = (new DateTime($tanggal_bimbingan_input))->format('Y-m-d H:i:s');
            if ($conn) {
                $sql_insert = "INSERT INTO bimbingan_kp (id_pengajuan, nip_pembimbing, tanggal_bimbingan, topik_bimbingan, catatan_dosen, status_bimbingan) VALUES (?, ?, ?, ?, ?, 'selesai')";
                $stmt_insert = $conn->prepare($sql_insert);
                $stmt_insert->bind_param("issss", $id_pengajuan_url, $nip_dosen_login, $tanggal_bimbingan_db, $topik_bimbingan_input, $catatan_dosen_input);
                if ($stmt_insert->execute()) {
                    $success_message = "Sesi bimbingan baru berhasil ditambahkan!";
                    $_POST = [];
                } else {
                    $error_message = "Gagal menyimpan sesi bimbingan: " . $stmt_insert->error;
                }
                $stmt_insert->close();
            }
        } catch (Exception $e) {
            $error_message = "Format tanggal bimbingan tidak valid.";
        }
    }
}

// Selalu ambil data terbaru
if ($id_pengajuan_url && empty($error_message_on_load)) {
    $fetched_data = getBimbinganData($conn, $id_pengajuan_url, $nip_dosen_login, $error_message);
    $pengajuan_info = $fetched_data['pengajuan'];
    $riwayat_bimbingan = $fetched_data['riwayat'];
}

$page_title = "Kelola Bimbingan KP";
if ($pengajuan_info) {
    $page_title = "Bimbingan: " . htmlspecialchars($pengajuan_info['nama_mahasiswa']);
}
require_once '../includes/header.php';
?>

<div class="main-content-full">
    <div class="form-container-modern">
        <div class="form-header">
            <h1><?php echo htmlspecialchars($page_title); ?></h1>
            <a href="bimbingan_mahasiswa_list.php" class="btn btn-secondary">&laquo; Kembali ke Daftar Mahasiswa</a>
        </div>

        <?php if ($pengajuan_info): ?>
            <div class="info-section">
                <p><strong>Mahasiswa:</strong> <?php echo htmlspecialchars($pengajuan_info['nama_mahasiswa'] . ' (' . $pengajuan_info['nim'] . ')'); ?></p>
                <p><strong>Judul KP:</strong> <?php echo htmlspecialchars($pengajuan_info['judul_kp']); ?></p>
            </div>

            <?php if (!empty($success_message)): ?>
                <div class="message success"><p><?php echo htmlspecialchars($success_message); ?></p></div>
            <?php endif; ?>
            <?php if (!empty($error_message)): ?>
                <div class="message error"><p><?php echo htmlspecialchars($error_message); ?></p></div>
            <?php endif; ?>

            <form action="bimbingan_kelola.php?id_pengajuan=<?php echo $id_pengajuan_url; ?>" method="POST" class="modern-form">
                <fieldset>
                    <div class="fieldset-header">
                        <span class="fieldset-number">➕</span>
                        <h4>Catat Sesi Bimbingan Baru</h4>
                    </div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="tanggal_bimbingan">Tanggal & Waktu Bimbingan (*)</label>
                            <input type="datetime-local" id="tanggal_bimbingan" name="tanggal_bimbingan" value="<?php echo date('Y-m-d\TH:i'); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="topik_bimbingan">Topik Bimbingan (*)</label>
                            <input type="text" id="topik_bimbingan" name="topik_bimbingan" required placeholder="Contoh: Review Bab 1">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="catatan_dosen">Catatan / Arahan untuk Mahasiswa</label>
                        <textarea id="catatan_dosen" name="catatan_dosen" rows="5" placeholder="Tuliskan feedback, arahan, atau poin revisi..."></textarea>
                    </div>
                </fieldset>
                <div class="form-actions">
                    <button type="submit" name="submit_bimbingan" class="btn btn-primary btn-submit">Simpan Catatan</button>
                </div>
            </form>

            <div class="history-section">
                <div class="fieldset-header">
                    <span class="fieldset-number">📖</span>
                    <h4>Riwayat Bimbingan</h4>
                </div>
                <?php if (empty($riwayat_bimbingan)): ?>
                    <div class="message info"><p>Belum ada riwayat bimbingan untuk mahasiswa ini.</p></div>
                <?php else: ?>
                    <ul class="bimbingan-history-list">
                        <?php foreach ($riwayat_bimbingan as $sesi): ?>
                            <li class="bimbingan-item">
                                <div class="bimbingan-header">
                                    <div class="header-info">
                                        <span class="bimbingan-date"><?php echo date("d F Y, H:i", strtotime($sesi['tanggal_bimbingan'])); ?></span>
                                        <h5 class="bimbingan-topic"><?php echo htmlspecialchars($sesi['topik_bimbingan']); ?></h5>
                                    </div>
                                    <span class="status-bimbingan status-bimbingan-<?php echo strtolower(str_replace('_', '-', $sesi['status_bimbingan'])); ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', htmlspecialchars($sesi['status_bimbingan']))); ?>
                                    </span>
                                </div>
                                <?php if (!empty($sesi['catatan_mahasiswa'])): ?>
                                    <div class="catatan catatan-mahasiswa">
                                        <strong>Catatan Mahasiswa:</strong>
                                        <p><?php echo nl2br(htmlspecialchars($sesi['catatan_mahasiswa'])); ?></p>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($sesi['catatan_dosen'])): ?>
                                    <div class="catatan catatan-dosen">
                                        <strong>Catatan Anda:</strong>
                                        <p><?php echo nl2br(htmlspecialchars($sesi['catatan_dosen'])); ?></p>
                                    </div>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>

        <?php elseif(empty($error_message)): ?>
            <div class="message info"><p>Memuat data bimbingan...</p></div>
        <?php endif; ?>
    </div>
</div>

<style>
    /* Menggunakan CSS dari file form sebelumnya dan menambahkan beberapa kustomisasi */
    .form-container-modern { max-width: 900px; margin: 20px auto; }
    .info-section {
        background-color: #e9f5ff;
        border-left: 5px solid var(--primary-color);
        padding: 1rem 1.5rem;
        margin-bottom: 2rem;
        border-radius: 8px;
    }
    .info-section p { margin: 0.5rem 0; }
    .history-section { margin-top: 3rem; }
    .bimbingan-history-list { list-style: none; padding: 0; }
    .bimbingan-item {
        background-color: #fff;
        border: 1px solid var(--border-color);
        padding: 1.5rem;
        margin-bottom: 1rem;
        border-radius: 10px;
        box-shadow: 0 2px 5px rgba(0,0,0,0.05);
    }
    .bimbingan-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 1rem;
        padding-bottom: 1rem;
        border-bottom: 1px dashed #ddd;
    }
    .bimbingan-date {
        font-size: 0.9em;
        color: var(--secondary-color);
        font-weight: 500;
    }
    .bimbingan-topic {
        margin: 5px 0 0 0;
        font-size: 1.2em;
        font-weight: 600;
        color: var(--dark-color);
    }
    
    .catatan {
        padding: 1rem;
        margin-top: 1rem;
        border-radius: 8px;
        font-size: 0.95em;
        border-left: 4px solid;
    }
    .catatan strong { display: block; margin-bottom: 0.5rem; font-weight: 600; }
    .catatan p { margin: 0; line-height: 1.6; }
    .catatan-mahasiswa { border-color: #6f42c1; background-color: #f4f0f7; }
    .catatan-dosen { border-color: #17a2b8; background-color: #e8f7fa; }

    /* Status Bimbingan */
    .status-bimbingan { padding: 5px 12px; border-radius: 20px; font-size: 0.8em; font-weight: bold; color: #fff; }
    .status-bimbingan-diajukan-mahasiswa { background-color: #ffc107; color: #212529; }
    .status-bimbingan-direview-dosen { background-color: #fd7e14; }
    .status-bimbingan-selesai { background-color: #28a745; }
</style>

<?php
require_once '../includes/footer.php';
if (isset($conn) && $conn) {
    $conn->close();
}
?>