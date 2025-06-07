<?php
// /KP/dosen/logbook_verifikasi_form.php

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
$id_logbook_url = null;
$logbook_detail = null; // Akan berisi detail logbook dan info terkait
$error_message = '';
$success_message = '';

// 2. VALIDASI DAN AMBIL ID LOGBOOK DARI URL
if (isset($_GET['id_logbook']) && filter_var($_GET['id_logbook'], FILTER_VALIDATE_INT)) {
    $id_logbook_url = (int)$_GET['id_logbook'];
} else {
    $error_message = "ID Logbook tidak valid atau tidak ditemukan.";
}

// Sertakan file koneksi database
require_once '../config/db_connect.php';

// 3. FUNGSI UNTUK MENGAMBIL DETAIL LOGBOOK DAN KONTEKSNYA
function getLogbookDetailForDosen($conn_db, $logbook_id, $nip_dosen, &$out_error_message) {
    $data = null;
    $sql = "SELECT
                l.id_logbook, l.id_pengajuan, l.tanggal_kegiatan, l.jam_mulai, l.jam_selesai,
                l.uraian_kegiatan, l.status_verifikasi_logbook, l.catatan_pembimbing_logbook,
                pk.judul_kp, pk.nim AS nim_mahasiswa_kp,
                m.nama AS nama_mahasiswa
            FROM logbook l
            JOIN pengajuan_kp pk ON l.id_pengajuan = pk.id_pengajuan
            JOIN mahasiswa m ON pk.nim = m.nim
            WHERE l.id_logbook = ? AND pk.nip_dosen_pembimbing_kp = ?";
    $stmt = $conn_db->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("is", $logbook_id, $nip_dosen);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows === 1) {
            $data = $result->fetch_assoc();
        } else {
            $out_error_message = "Detail logbook tidak ditemukan atau Anda tidak berhak mengakses logbook ini.";
        }
        $stmt->close();
    } else {
        $out_error_message = "Gagal menyiapkan query untuk mengambil detail logbook: " . (($conn_db->error) ? htmlspecialchars($conn_db->error) : "Kesalahan DB tidak diketahui.");
    }
    return $data;
}

// 4. PROSES UPDATE STATUS VERIFIKASI LOGBOOK JIKA FORM DISUBMIT
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_verifikasi_logbook']) && $id_logbook_url !== null && empty($error_message)) {
    $new_status_verifikasi = $_POST['status_verifikasi_logbook'];
    $catatan_pembimbing_input = trim($_POST['catatan_pembimbing_logbook']);
    $id_logbook_form = (int)$_POST['id_logbook'];

    if ($id_logbook_form !== $id_logbook_url) {
        $error_message = "Kesalahan: ID Logbook tidak cocok.";
    }
    // Validasi status baru (sesuai ENUM di DB)
    $allowed_statuses_logbook = ['pending', 'disetujui', 'revisi_minor', 'revisi_mayor'];
    if (empty($new_status_verifikasi) || !in_array($new_status_verifikasi, $allowed_statuses_logbook)) {
        $error_message = "Status verifikasi logbook yang dipilih tidak valid.";
    } elseif (($new_status_verifikasi === 'revisi_minor' || $new_status_verifikasi === 'revisi_mayor') && empty($catatan_pembimbing_input)) {
        $error_message = "Catatan pembimbing wajib diisi jika status adalah 'Revisi Minor' atau 'Revisi Mayor'.";
    }


    if (empty($error_message)) {
        if ($conn && ($conn instanceof mysqli)) {
            // Query update juga harus memastikan nip_dosen_pembimbing_kp cocok untuk keamanan tambahan
            $sql_update_logbook = "UPDATE logbook l
                                   JOIN pengajuan_kp pk ON l.id_pengajuan = pk.id_pengajuan
                                   SET l.status_verifikasi_logbook = ?, l.catatan_pembimbing_logbook = ?
                                   WHERE l.id_logbook = ? AND pk.nip_dosen_pembimbing_kp = ?";
            $stmt_update_logbook = $conn->prepare($sql_update_logbook);
            if ($stmt_update_logbook) {
                $stmt_update_logbook->bind_param("ssis", $new_status_verifikasi, $catatan_pembimbing_input, $id_logbook_form, $nip_dosen_login);
                if ($stmt_update_logbook->execute()) {
                    if ($stmt_update_logbook->affected_rows > 0) {
                        $success_message = "Status verifikasi logbook berhasil diperbarui!";
                    } else {
                        // Mungkin tidak ada perubahan jika status dan catatan sama, atau NIP tidak cocok (meskipun sudah dicek saat load)
                        $success_message = "Tidak ada perubahan pada status atau catatan logbook (mungkin data masih sama atau otorisasi gagal saat update).";
                    }
                } else {
                    $error_message = "Gagal memperbarui status logbook: " . (($stmt_update_logbook->error) ? htmlspecialchars($stmt_update_logbook->error) : "Kesalahan tidak diketahui.");
                }
                $stmt_update_logbook->close();
            } else {
                $error_message = "Gagal menyiapkan statement update logbook: " . (($conn->error) ? htmlspecialchars($conn->error) : "Kesalahan tidak diketahui.");
            }
        } else {
            $error_message = "Koneksi database hilang saat akan update status logbook.";
        }
    }
}

// Selalu ambil data terbaru untuk ditampilkan (atau jika ada error sebelumnya, $logbook_detail akan null)
if ($id_logbook_url && empty($error_message_initial_load) && $conn && ($conn instanceof mysqli)) {
    $logbook_detail = getLogbookDetailForDosen($conn, $id_logbook_url, $nip_dosen_login, $error_message);
    if (!$logbook_detail && empty($error_message)) { // Jika fungsi return null tapi tidak set error eksplisit
        $error_message = "Gagal memuat detail logbook.";
    }
}

// Daftar status verifikasi logbook (sesuai ENUM)
$opsi_status_verifikasi_logbook = [
    'pending' => 'Pending (Menunggu Verifikasi)',
    'disetujui' => 'Disetujui',
    'revisi_minor' => 'Revisi Minor (Perlu Perbaikan Kecil)',
    'revisi_mayor' => 'Revisi Mayor (Perlu Perbaikan Besar)'
];

// Set judul halaman
$page_title = "Verifikasi Detail Logbook";
if ($logbook_detail && !empty($logbook_detail['nama_mahasiswa'])) {
    $page_title = "Verifikasi Logbook: " . htmlspecialchars($logbook_detail['nama_mahasiswa']) . " (" . date("d M Y", strtotime($logbook_detail['tanggal_kegiatan'])) . ")";
}
require_once '../includes/header.php';
?>

<div class="page-layout-wrapper">
    <main class="main-content-area">
        <div class="form-container verifikasi-logbook-form">
            <h1><?php echo htmlspecialchars($page_title); ?></h1>
            <a href="/KP/dosen/logbook_verifikasi_list.php" class="btn btn-light btn-sm mb-3">&laquo; Kembali ke Daftar Logbook</a>
            <hr>

            <?php if (!empty($success_message)): ?>
                <div class="message success"><p><?php echo htmlspecialchars($success_message); ?></p></div>
            <?php endif; ?>
            <?php if (!empty($error_message)): ?>
                <div class="message error"><p><?php echo htmlspecialchars($error_message); ?></p></div>
            <?php endif; ?>

            <?php if ($logbook_detail): ?>
                <div class="info-section card mb-4">
                    <div class="card-header"><h3>Detail Entri Logbook</h3></div>
                    <div class="card-body">
                        <dl>
                            <dt>Mahasiswa:</dt><dd><?php echo htmlspecialchars($logbook_detail['nama_mahasiswa']); ?> (<?php echo htmlspecialchars($logbook_detail['nim_mahasiswa_kp']); ?>)</dd>
                            <dt>Judul KP:</dt><dd><?php echo htmlspecialchars($logbook_detail['judul_kp']); ?></dd>
                            <dt>ID Pengajuan KP:</dt><dd><?php echo $logbook_detail['id_pengajuan']; ?></dd>
                            <hr style="margin: 10px 0;">
                            <dt>Tanggal Kegiatan:</dt><dd><strong><?php echo date("d F Y", strtotime($logbook_detail['tanggal_kegiatan'])); ?></strong></dd>
                            <dt>Waktu Kegiatan:</dt>
                            <dd>
                                <?php 
                                if ($logbook_detail['jam_mulai'] && $logbook_detail['jam_selesai']) {
                                    echo date("H:i", strtotime($logbook_detail['jam_mulai'])) . " - " . date("H:i", strtotime($logbook_detail['jam_selesai']));
                                } elseif ($logbook_detail['jam_mulai']) {
                                    echo "Mulai: " . date("H:i", strtotime($logbook_detail['jam_mulai']));
                                } else {
                                    echo "<em>Tidak dicatat</em>";
                                }
                                ?>
                            </dd>
                            <dt>Uraian Kegiatan:</dt>
                            <dd class="uraian-kegiatan-display"><?php echo nl2br(htmlspecialchars($logbook_detail['uraian_kegiatan'])); ?></dd>
                        </dl>
                    </div>
                </div>

                <form action="/KP/dosen/logbook_verifikasi_form.php?id_logbook=<?php echo $id_logbook_url; ?>" method="POST" class="action-form card">
                    <div class="card-header"><h3>Formulir Verifikasi Logbook</h3></div>
                    <div class="card-body">
                        <input type="hidden" name="id_logbook" value="<?php echo $id_logbook_url; ?>">

                        <div class="form-group">
                            <label for="status_verifikasi_logbook_current">Status Verifikasi Saat Ini:</label>
                            <input type="text" id="status_verifikasi_logbook_current" value="<?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $logbook_detail['status_verifikasi_logbook']))); ?>" readonly class="readonly-input">
                        </div>

                        <div class="form-group">
                            <label for="status_verifikasi_logbook">Ubah Status Verifikasi Menjadi (*):</label>
                            <select id="status_verifikasi_logbook" name="status_verifikasi_logbook" required>
                                <option value="">-- Pilih Status Baru --</option>
                                <?php foreach ($opsi_status_verifikasi_logbook as $value => $text): ?>
                                    <option value="<?php echo $value; ?>" <?php echo ($logbook_detail['status_verifikasi_logbook'] == $value) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($text); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="catatan_pembimbing_logbook">Catatan Pembimbing:</label>
                            <textarea id="catatan_pembimbing_logbook" name="catatan_pembimbing_logbook" rows="5" placeholder="Berikan feedback atau catatan untuk mahasiswa..."><?php echo htmlspecialchars($logbook_detail['catatan_pembimbing_logbook']); ?></textarea>
                            <small>Catatan ini akan menggantikan catatan sebelumnya (jika ada). Wajib diisi jika status 'Revisi Minor' atau 'Revisi Mayor'.</small>
                        </div>

                        <div class="form-actions">
                            <button type="submit" name="submit_verifikasi_logbook" class="btn btn-success">Simpan Verifikasi</button>
                        </div>
                    </div>
                </form>

            <?php elseif(empty($error_message)): ?>
                <div class="message info"><p>Memuat detail logbook...</p></div>
            <?php endif; ?>

        </div>
    </main>
</div>

<style>
    /* Asumsikan CSS umum dari header, sidebar, .card, .form-group, .message, .btn sudah ada */
    .verifikasi-logbook-form h1 { margin-top: 0; margin-bottom: 5px; }
    .verifikasi-logbook-form hr { margin-top:15px; margin-bottom: 20px; }
    .btn.mb-3 { margin-bottom: 1rem !important; }

    .info-section.card .card-header h3 { font-size: 1.2em; color: #007bff; }
    .info-section.card .card-body dl dt { width: 160px; } /* Sesuaikan */
    .info-section.card .card-body dl dd { margin-left: 170px; /* Sesuaikan */ }
    .uraian-kegiatan-display {
        white-space: pre-wrap; /* Agar format teks uraian terjaga */
        background-color: #f9f9f9;
        padding: 10px;
        border: 1px solid #eee;
        border-radius: 4px;
        min-height: 100px;
    }

    .action-form.card .card-header h3 { font-size: 1.2em; color: #28a745; }
    .readonly-input { background-color: #e9ecef; cursor: not-allowed; }
    .form-group small { display: block; font-size: 0.85em; color: #6c757d; margin-top: 4px; }
</style>

<?php
require_once '../includes/footer.php';

if (isset($conn) && ($conn instanceof mysqli)) {
    $conn->close();
}
?>