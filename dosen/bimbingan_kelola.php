<?php
// /KP/dosen/bimbingan_kelola.php

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
$pengajuan_info = null; // Info pengajuan KP dan mahasiswa
$riwayat_bimbingan = [];
$error_message = '';
$success_message = '';

// 2. VALIDASI DAN AMBIL ID PENGAJUAN DARI URL
if (isset($_GET['id_pengajuan']) && filter_var($_GET['id_pengajuan'], FILTER_VALIDATE_INT)) {
    $id_pengajuan_url = (int)$_GET['id_pengajuan'];
} else {
    $error_message = "ID Pengajuan tidak valid atau tidak ditemukan.";
}

// Sertakan file koneksi database
require_once '../config/db_connect.php';

// 3. FUNGSI UNTUK MENGAMBIL DATA (INFO PENGAJUAN & RIWAYAT BIMBINGAN)
function getBimbinganData($conn_db, $id_pengajuan, $nip_dosen, &$out_error_message) {
    $data = ['pengajuan' => null, 'riwayat' => []];

    // Ambil info pengajuan dan mahasiswa, pastikan dosen ini adalah pembimbingnya
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

            // Ambil riwayat bimbingan untuk pengajuan ini
            $sql_riwayat = "SELECT id_bimbingan, tanggal_bimbingan, topik_bimbingan, 
                                   catatan_mahasiswa, catatan_dosen, 
                                   file_lampiran_mahasiswa, file_lampiran_dosen, status_bimbingan
                            FROM bimbingan_kp
                            WHERE id_pengajuan = ?
                            ORDER BY tanggal_bimbingan DESC";
            $stmt_riwayat = $conn_db->prepare($sql_riwayat);
            if ($stmt_riwayat) {
                $stmt_riwayat->bind_param("i", $id_pengajuan);
                $stmt_riwayat->execute();
                $result_riwayat = $stmt_riwayat->get_result();
                while ($row_riwayat = $result_riwayat->fetch_assoc()) {
                    $data['riwayat'][] = $row_riwayat;
                }
                $stmt_riwayat->close();
            } else {
                $out_error_message .= (empty($out_error_message)?"":"<br>") . "Gagal mengambil riwayat bimbingan.";
            }
        } else {
            $out_error_message = "Pengajuan KP tidak ditemukan atau Anda bukan pembimbing untuk pengajuan ini.";
        }
        $stmt_pengajuan->close();
    } else {
        $out_error_message = "Gagal menyiapkan query info pengajuan: " . (($conn_db->error) ? htmlspecialchars($conn_db->error) : "Kesalahan DB.");
    }
    return $data;
}

// 4. PROSES PENAMBAHAN SESI BIMBINGAN BARU JIKA FORM DISUBMIT
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_bimbingan']) && $id_pengajuan_url !== null && empty($error_message)) {
    $tanggal_bimbingan_input = $_POST['tanggal_bimbingan']; // Format YYYY-MM-DDTHH:MM dari datetime-local
    $topik_bimbingan_input = trim($_POST['topik_bimbingan']);
    $catatan_dosen_input = trim($_POST['catatan_dosen']);
    // Untuk file upload dosen, akan ditambahkan jika diperlukan. Untuk sekarang, fokus teks.
    // $file_lampiran_dosen_input = null; 

    // Validasi dasar
    if (empty($tanggal_bimbingan_input) || empty($topik_bimbingan_input)) {
        $error_message = "Tanggal Bimbingan dan Topik Bimbingan wajib diisi.";
    } else {
        // Konversi format datetime-local ke format DATETIME MySQL (YYYY-MM-DD HH:MM:SS)
        try {
            $datetime_obj = new DateTime($tanggal_bimbingan_input);
            $tanggal_bimbingan_db = $datetime_obj->format('Y-m-d H:i:s');
        } catch (Exception $e) {
            $error_message = "Format tanggal bimbingan tidak valid.";
            $tanggal_bimbingan_db = null; // Set null jika error
        }

        if ($tanggal_bimbingan_db && empty($error_message)) {
            if ($conn && ($conn instanceof mysqli)) {
                // Status bimbingan default saat dosen menambahkan
                $status_bimbingan_default = 'selesai'; // atau 'direview_dosen'

                $sql_insert = "INSERT INTO bimbingan_kp (id_pengajuan, nip_pembimbing, tanggal_bimbingan, topik_bimbingan, catatan_dosen, status_bimbingan)
                               VALUES (?, ?, ?, ?, ?, ?)";
                $stmt_insert = $conn->prepare($sql_insert);
                if ($stmt_insert) {
                    $stmt_insert->bind_param("isssss",
                        $id_pengajuan_url,
                        $nip_dosen_login,
                        $tanggal_bimbingan_db,
                        $topik_bimbingan_input,
                        $catatan_dosen_input,
                        $status_bimbingan_default
                    );
                    if ($stmt_insert->execute()) {
                        $success_message = "Sesi bimbingan baru berhasil ditambahkan!";
                        // Kosongkan form atau redirect untuk mencegah resubmit F5
                        $_POST = []; // Bersihkan POST
                    } else {
                        $error_message = "Gagal menyimpan sesi bimbingan: " . htmlspecialchars($stmt_insert->error);
                    }
                    $stmt_insert->close();
                } else {
                    $error_message = "Gagal menyiapkan statement insert bimbingan: " . htmlspecialchars($conn->error);
                }
            } else {
                $error_message = "Koneksi database hilang saat akan menyimpan bimbingan.";
            }
        }
    }
}


// Selalu ambil data terbaru untuk ditampilkan (atau jika ada error sebelumnya)
if ($id_pengajuan_url && empty($error_message_initial_load) && $conn && ($conn instanceof mysqli)) {
    $fetched_data = getBimbinganData($conn, $id_pengajuan_url, $nip_dosen_login, $error_message);
    $pengajuan_info = $fetched_data['pengajuan'];
    $riwayat_bimbingan = $fetched_data['riwayat'];
     if (!$pengajuan_info && empty($error_message)) { // Jika fungsi return null tapi tidak set error
        $error_message = "Gagal memuat data pengajuan atau Anda tidak berhak.";
    }
}


// Set judul halaman
$page_title = "Kelola Bimbingan KP";
if ($pengajuan_info && !empty($pengajuan_info['judul_kp'])) {
    $page_title = "Bimbingan: " . htmlspecialchars($pengajuan_info['judul_kp']);
}
require_once '../includes/header.php';
?>

<div class="page-layout-wrapper">

    <?php require_once '../includes/sidebar_dosen.php'; ?>

    <main class="main-content-area">
        <div class="form-container kelola-bimbingan-container">
            <h1><?php echo htmlspecialchars($page_title); ?></h1>
            <a href="/KP/dosen/bimbingan_mahasiswa_list.php" class="btn btn-light btn-sm mb-3">&laquo; Kembali ke Daftar Mahasiswa Bimbingan</a>
            <hr>

            <?php if (!empty($success_message)): ?>
                <div class="message success"><p><?php echo htmlspecialchars($success_message); ?></p></div>
            <?php endif; ?>
            <?php if (!empty($error_message)): ?>
                <div class="message error"><p><?php echo htmlspecialchars($error_message); ?></p></div>
            <?php endif; ?>

            <?php if ($pengajuan_info): ?>
                <div class="info-section card mb-4">
                    <div class="card-header"><h3>Informasi Kerja Praktek & Mahasiswa</h3></div>
                    <div class="card-body">
                        <dl>
                            <dt>Judul KP:</dt><dd><strong><?php echo htmlspecialchars($pengajuan_info['judul_kp']); ?></strong></dd>
                            <dt>Mahasiswa:</dt><dd><?php echo htmlspecialchars($pengajuan_info['nama_mahasiswa']); ?> (<?php echo htmlspecialchars($pengajuan_info['nim']); ?>)</dd>
                            <dt>Prodi:</dt><dd><?php echo htmlspecialchars($pengajuan_info['prodi']); ?></dd>
                            <dt>Status KP:</dt>
                            <dd><span class="status-badge status-<?php echo strtolower(str_replace([' ', '_'], '-', $pengajuan_info['status_pengajuan'])); ?>">
                                <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $pengajuan_info['status_pengajuan']))); ?></span>
                            </dd>
                        </dl>
                    </div>
                </div>

                <div class="action-form card mb-4">
                    <div class="card-header"><h3><i class="icon-plus"></i> Tambah Sesi Bimbingan Baru</h3></div>
                    <div class="card-body">
                        <form action="/KP/dosen/bimbingan_kelola.php?id_pengajuan=<?php echo $id_pengajuan_url; ?>" method="POST">
                            <div class="form-group">
                                <label for="tanggal_bimbingan">Tanggal & Waktu Bimbingan (*):</label>
                                <input type="datetime-local" id="tanggal_bimbingan" name="tanggal_bimbingan" class="form-control"
                                       value="<?php echo isset($_POST['tanggal_bimbingan_input_val']) ? htmlspecialchars($_POST['tanggal_bimbingan_input_val']) : date('Y-m-d\TH:i'); ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="topik_bimbingan">Topik Bimbingan (*):</label>
                                <input type="text" id="topik_bimbingan" name="topik_bimbingan" class="form-control" value="<?php echo isset($_POST['topik_bimbingan']) ? htmlspecialchars($_POST['topik_bimbingan']) : ''; ?>" required placeholder="Contoh: Review Bab 1, Diskusi Progres Coding">
                            </div>
                            <div class="form-group">
                                <label for="catatan_dosen">Catatan dari Dosen untuk Mahasiswa:</label>
                                <textarea id="catatan_dosen" name="catatan_dosen" class="form-control" rows="5" placeholder="Tuliskan arahan, feedback, atau catatan penting lainnya..."><?php echo isset($_POST['catatan_dosen']) ? htmlspecialchars($_POST['catatan_dosen']) : ''; ?></textarea>
                            </div>
                            <div class="form-actions">
                                <button type="submit" name="submit_bimbingan" class="btn btn-primary">Simpan Sesi Bimbingan</button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="info-section card">
                    <div class="card-header"><h3>Riwayat Sesi Bimbingan</h3></div>
                    <div class="card-body">
                        <?php if (empty($riwayat_bimbingan)): ?>
                            <p>Belum ada riwayat sesi bimbingan untuk pengajuan KP ini.</p>
                        <?php else: ?>
                            <ul class="bimbingan-history-list">
                                <?php foreach ($riwayat_bimbingan as $sesi): ?>
                                    <li class="bimbingan-item">
                                        <div class="bimbingan-header">
                                            <strong><?php echo date("d F Y, H:i", strtotime($sesi['tanggal_bimbingan'])); ?></strong>
                                            <span class="status-bimbingan status-bimbingan-<?php echo strtolower(str_replace('_', '-', $sesi['status_bimbingan'])); ?>">
                                                <?php echo ucfirst(str_replace('_', ' ', htmlspecialchars($sesi['status_bimbingan']))); ?>
                                            </span>
                                        </div>
                                        <p><strong>Topik:</strong> <?php echo htmlspecialchars($sesi['topik_bimbingan']); ?></p>
                                        <?php if (!empty($sesi['catatan_mahasiswa'])): ?>
                                            <div class="catatan catatan-mahasiswa">
                                                <strong>Catatan Mahasiswa:</strong>
                                                <p><?php echo nl2br(htmlspecialchars($sesi['catatan_mahasiswa'])); ?></p>
                                                <?php if ($sesi['file_lampiran_mahasiswa']): ?>
                                                    <small><a href="/KP/<?php echo htmlspecialchars($sesi['file_lampiran_mahasiswa']); ?>" target="_blank">Lihat Lampiran Mahasiswa</a></small>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                        <?php if (!empty($sesi['catatan_dosen'])): ?>
                                            <div class="catatan catatan-dosen">
                                                <strong>Catatan Dosen:</strong>
                                                <p><?php echo nl2br(htmlspecialchars($sesi['catatan_dosen'])); ?></p>
                                                 <?php if ($sesi['file_lampiran_dosen']): ?>
                                                    <small><a href="/KP/<?php echo htmlspecialchars($sesi['file_lampiran_dosen']); ?>" target="_blank">Lihat Lampiran Dosen</a></small>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                        </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                </div>

            <?php elseif(empty($error_message)): ?>
                <div class="message info"><p>Memuat data bimbingan...</p></div>
            <?php endif; ?>
        </div>
    </main>
</div>

<style>
    /* Asumsikan CSS umum dari header, sidebar, .card, .form-group, .message, .btn, status-badge sudah ada */
    .kelola-bimbingan-container h1 { margin-top: 0; margin-bottom: 5px; }
    .kelola-bimbingan-container hr { margin-top:15px; margin-bottom: 20px; }
    .btn.mb-3 { margin-bottom: 1rem !important; }
    .icon-plus::before { content: "+ "; font-weight: bold; }

    .info-section.card .card-header h3 { font-size: 1.2em; color: #007bff; }
    .info-section.card .card-body dl dt { width: 150px; }
    .info-section.card .card-body dl dd { margin-left: 160px; }

    .action-form.card .card-header h3 { font-size: 1.2em; color: #28a745; }
    .form-control { /* Kelas umum untuk input dan textarea jika belum ada */
        width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box;
    }

    .bimbingan-history-list { list-style: none; padding: 0; }
    .bimbingan-item {
        background-color: #f9f9f9;
        border: 1px solid #eee;
        padding: 15px;
        margin-bottom: 15px;
        border-radius: 5px;
    }
    .bimbingan-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 10px;
        padding-bottom: 8px;
        border-bottom: 1px dotted #ddd;
    }
    .bimbingan-header strong { font-size: 1.1em; color: #333; }
    .bimbingan-item p { margin-bottom: 8px; line-height: 1.5; }
    .bimbingan-item p strong { color: #555; }

    .catatan { padding: 10px; margin-top: 8px; border-radius: 4px; font-size: 0.9em; border-left: 3px solid; }
    .catatan strong { display: block; margin-bottom: 3px; }
    .catatan p { margin-bottom: 0; }
    .catatan small a { color: #007bff; text-decoration:none; }
    .catatan small a:hover { text-decoration:underline; }

    .catatan-mahasiswa { border-left-color: #6f42c1; background-color: #f4f0f7; } /* Ungu muda */
    .catatan-dosen { border-left-color: #17a2b8; background-color: #e8f7fa; } /* Biru muda/info */

    /* Status Bimbingan: ENUM('diajukan_mahasiswa','direview_dosen','selesai') */
    .status-bimbingan { padding: 3px 8px; border-radius: 10px; font-size: 0.75em; font-weight: bold; color: #fff; }
    .status-bimbingan-diajukan-mahasiswa { background-color: #ffc107; color: #212529; } /* Kuning */
    .status-bimbingan-direview-dosen { background-color: #fd7e14; } /* Orange */
    .status-bimbingan-selesai { background-color: #28a745; } /* Hijau */
</style>

<?php
require_once '../includes/footer.php';

if (isset($conn) && ($conn instanceof mysqli)) {
    $conn->close();
}
?>