<?php
// /KP/perusahaan/konfirmasi_pengajuan_form.php

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// 1. OTENTIKASI DAN OTORISASI
require_once '../includes/auth_check.php';
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'perusahaan') {
    session_unset();
    session_destroy();
    header("Location: /KP/index.php?error=unauthorized_perusahaan");
    exit();
}

$id_perusahaan_login = $_SESSION['user_id'];
$nama_perusahaan_login = $_SESSION['user_nama'];
$id_pengajuan_url = null;

$pengajuan_detail = null;
$dokumen_mahasiswa = [];
$error_message = '';
$success_message = '';
$error_message_initial_load = '';

// 2. VALIDASI PARAMETER URL
if (isset($_GET['id_pengajuan']) && filter_var($_GET['id_pengajuan'], FILTER_VALIDATE_INT) && (int)$_GET['id_pengajuan'] > 0) {
    $id_pengajuan_url = (int)$_GET['id_pengajuan'];
} else {
    $error_message_initial_load = "ID Pengajuan tidak valid atau tidak ditemukan dalam URL.";
}

// Sertakan file koneksi database
require_once '../config/db_connect.php';

// 3. FUNGSI UNTUK MENGAMBIL DATA DETAIL PENGAJUAN DAN DOKUMEN MAHASISWA
function getPengajuanDetailsForCompany($conn_db, $pengajuan_id, $id_perusahaan, &$out_error_message) {
    $data = ['pengajuan' => null, 'dokumen' => []];
    if (!$conn_db || !($conn_db instanceof mysqli) || $conn_db->connect_error) {
        $out_error_message = "Koneksi database tidak valid."; return $data;
    }
    if ($pengajuan_id === null || $pengajuan_id <= 0) {
        $out_error_message = "ID Pengajuan tidak valid untuk mengambil data."; return $data;
    }

    $sql_pengajuan = "SELECT pk.id_pengajuan, pk.judul_kp, pk.deskripsi_kp, pk.status_pengajuan,
                             pk.tanggal_mulai_rencana, pk.tanggal_selesai_rencana, pk.tanggal_pengajuan AS tanggal_diajukan_kampus,
                             m.nim, m.nama AS nama_mahasiswa, m.prodi, m.angkatan, m.email AS email_mahasiswa, m.no_hp AS no_hp_mahasiswa
                      FROM pengajuan_kp pk
                      JOIN mahasiswa m ON pk.nim = m.nim
                      WHERE pk.id_pengajuan = ? AND pk.id_perusahaan = ? AND pk.status_pengajuan = 'menunggu_konfirmasi_perusahaan'";
    $stmt_pengajuan = $conn_db->prepare($sql_pengajuan);
    if ($stmt_pengajuan) {
        $stmt_pengajuan->bind_param("ii", $pengajuan_id, $id_perusahaan);
        $stmt_pengajuan->execute();
        $result_pengajuan = $stmt_pengajuan->get_result();
        if ($result_pengajuan->num_rows === 1) {
            $data['pengajuan'] = $result_pengajuan->fetch_assoc();

            // Ambil dokumen yang diunggah mahasiswa untuk pengajuan ini (misal proposal)
            $sql_dokumen = "SELECT nama_dokumen, jenis_dokumen, file_path, tanggal_upload 
                            FROM dokumen_kp 
                            WHERE id_pengajuan = ? AND tipe_uploader = 'mahasiswa' 
                            ORDER BY tanggal_upload DESC";
            $stmt_dokumen = $conn_db->prepare($sql_dokumen);
            if ($stmt_dokumen) {
                $stmt_dokumen->bind_param("i", $pengajuan_id);
                $stmt_dokumen->execute();
                $result_dokumen = $stmt_dokumen->get_result();
                while ($row_doc = $result_dokumen->fetch_assoc()) {
                    $data['dokumen'][] = $row_doc;
                }
                $stmt_dokumen->close();
            } else {
                $out_error_message .= " Gagal mengambil dokumen mahasiswa.";
            }
        } else {
            if(empty($out_error_message)) $out_error_message = "Pengajuan KP tidak ditemukan, tidak menunggu konfirmasi Anda, atau bukan untuk perusahaan Anda.";
        }
        $stmt_pengajuan->close();
    } else {
        $out_error_message = "Gagal menyiapkan query info pengajuan: " . htmlspecialchars($conn_db->error);
    }
    return $data;
}

// 4. PROSES KONFIRMASI JIKA FORM DISUBMIT
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_konfirmasi'])) {
    if ($id_pengajuan_url === null || !empty($error_message_initial_load)) {
        $error_message = "Tidak dapat memproses: ID Pengajuan awal tidak valid. " . $error_message_initial_load;
    } elseif (!$conn || !($conn instanceof mysqli) || $conn->connect_error) {
        $error_message = "Koneksi database tidak tersedia.";
    } else {
        $id_pengajuan_form = (int)$_POST['id_pengajuan'];
        $tindakan_konfirmasi = $_POST['tindakan_konfirmasi']; // 'terima' atau 'tolak'
        // Catatan dari perusahaan mungkin bisa disimpan di field 'catatan_admin' atau perlu field baru.
        // Untuk saat ini, kita hanya ubah status dan upload surat balasan.
        // $catatan_perusahaan = trim($_POST['catatan_perusahaan']);

        $new_status_kp = '';
        if ($tindakan_konfirmasi === 'terima') {
            $new_status_kp = 'diterima_perusahaan';
        } elseif ($tindakan_konfirmasi === 'tolak') {
            $new_status_kp = 'ditolak_perusahaan';
        } else {
            $error_message = "Tindakan konfirmasi tidak valid.";
        }

        if ($id_pengajuan_form !== $id_pengajuan_url) {
            $error_message = "Kesalahan: ID Pengajuan pada form tidak cocok.";
        }
        
        $surat_balasan_path_db = null; // Path untuk disimpan ke DB
        $upload_surat_balasan_ok = 1;

        // Penanganan File Upload Surat Balasan (jika ada)
        if (empty($error_message) && isset($_FILES["surat_balasan_perusahaan"]) && $_FILES["surat_balasan_perusahaan"]["error"] == 0) {
            $target_dir_surat = "../uploads/dokumen_kp/"; // Simpan bersama dokumen lain
            $file_surat_ext = strtolower(pathinfo($_FILES["surat_balasan_perusahaan"]["name"], PATHINFO_EXTENSION));
            $unique_surat_filename = "surat_balasan_" . $id_pengajuan_form . "_" . $id_perusahaan_login . "_" . time() . "." . $file_surat_ext;
            $target_file_surat = $target_dir_surat . $unique_surat_filename;

            $allowed_surat_types = ['pdf', 'doc', 'docx', 'jpg', 'png'];
            if (!in_array($file_surat_ext, $allowed_surat_types)) {
                $error_message = "Format file surat balasan tidak diizinkan (hanya PDF, DOCX, JPG, PNG).";
                $upload_surat_balasan_ok = 0;
            }
            if ($upload_surat_balasan_ok && $_FILES["surat_balasan_perusahaan"]["size"] > 5000000) { // Max 5MB
                $error_message = "Ukuran file surat balasan terlalu besar (maks 5MB).";
                $upload_surat_balasan_ok = 0;
            }

            if ($upload_surat_balasan_ok) {
                if (move_uploaded_file($_FILES["surat_balasan_perusahaan"]["tmp_name"], $target_file_surat)) {
                    $surat_balasan_path_db = "uploads/dokumen_kp/" . $unique_surat_filename;
                } else {
                    $error_message = "Gagal mengupload file surat balasan.";
                    $upload_surat_balasan_ok = 0;
                }
            }
        } elseif (isset($_FILES["surat_balasan_perusahaan"]) && $_FILES["surat_balasan_perusahaan"]["error"] != UPLOAD_ERR_NO_FILE) {
             $error_message = "Terjadi error pada file surat balasan (Code: ".$_FILES["surat_balasan_perusahaan"]["error"].").";
             $upload_surat_balasan_ok = 0;
        }
        // Jika tindakan 'terima', surat balasan mungkin lebih diharapkan
        if ($tindakan_konfirmasi === 'terima' && $surat_balasan_path_db === null && $upload_surat_balasan_ok && !(isset($_FILES["surat_balasan_perusahaan"]) && $_FILES["surat_balasan_perusahaan"]["error"] == UPLOAD_ERR_NO_FILE)) {
            // $error_message = "Surat balasan resmi perusahaan sebaiknya diunggah jika menerima pengajuan.";
            // Untuk sekarang kita buat opsional saja.
        }


        if (empty($error_message) && $upload_surat_balasan_ok) {
            $conn->begin_transaction();
            try {
                // Update status_pengajuan
                $sql_update_status = "UPDATE pengajuan_kp SET status_pengajuan = ? WHERE id_pengajuan = ? AND id_perusahaan = ? AND status_pengajuan = 'menunggu_konfirmasi_perusahaan'";
                $stmt_update_status = $conn->prepare($sql_update_status);
                if (!$stmt_update_status) throw new Exception("Prepare update status KP gagal: " . $conn->error);
                $stmt_update_status->bind_param("sii", $new_status_kp, $id_pengajuan_url, $id_perusahaan_login);
                if (!$stmt_update_status->execute()) throw new Exception("Eksekusi update status KP gagal: " . $stmt_update_status->error);
                $affected_rows_status = $stmt_update_status->affected_rows;
                $stmt_update_status->close();

                if ($affected_rows_status == 0 && empty($error_message)) { // Gagal update status, mungkin status sudah berubah atau tidak berhak
                     throw new Exception("Tidak dapat mengubah status pengajuan. Mungkin status sudah berubah atau Anda tidak memiliki otorisasi.");
                }

                // Jika ada surat balasan yang diupload, simpan sebagai dokumen_kp
                if ($surat_balasan_path_db !== null) {
                    $nama_surat_dok = "Surat Balasan dari " . $nama_perusahaan_login;
                    $jenis_surat_dok = "surat_balasan_perusahaan";
                    $sql_insert_surat = "INSERT INTO dokumen_kp (id_pengajuan, uploader_id, tipe_uploader, nama_dokumen, jenis_dokumen, file_path, status_verifikasi_dokumen) 
                                         VALUES (?, ?, 'perusahaan', ?, ?, ?, 'disetujui')"; // Langsung disetujui
                    $stmt_insert_surat = $conn->prepare($sql_insert_surat);
                    if (!$stmt_insert_surat) throw new Exception("Prepare insert surat balasan gagal: ". $conn->error);
                    // uploader_id untuk perusahaan bisa berupa id_perusahaan atau email, perlu konsistensi. Di sini kita pakai id_perusahaan.
                    $uploader_id_perusahaan_str = (string)$id_perusahaan_login; 
                    $stmt_insert_surat->bind_param("issss", $id_pengajuan_url, $uploader_id_perusahaan_str, $nama_surat_dok, $jenis_surat_dok, $surat_balasan_path_db);
                    if (!$stmt_insert_surat->execute()) throw new Exception("Eksekusi insert surat balasan gagal: ". $stmt_insert_surat->error);
                    $stmt_insert_surat->close();
                }

                $conn->commit();
                $success_message = "Konfirmasi pengajuan KP telah berhasil disimpan. Status diubah menjadi: " . ucfirst(str_replace('_', ' ', $new_status_kp)) . ".";
                // Redirect atau refresh data
                 // header("Location: /KP/perusahaan/konfirmasi_pengajuan_list.php?confirm_success=1");
                 // exit();

            } catch (Exception $e) {
                $conn->rollback();
                $error_message = "Gagal memproses konfirmasi: " . htmlspecialchars($e->getMessage());
                // Jika file terlanjur diupload tapi DB gagal, hapus file
                if ($surat_balasan_path_db && file_exists("../".$surat_balasan_path_db)) { // Path relatif dari file ini ke root KP lalu ke uploads
                    unlink("../".$surat_balasan_path_db);
                }
            }
        }
    }
}


// Selalu ambil data terbaru untuk ditampilkan
$display_error_message = $error_message_initial_load;
if (empty($display_error_message) && !empty($error_message)) {
    $display_error_message = $error_message;
}

if ($id_pengajuan_url !== null && empty($error_message_initial_load)) {
    if ($conn && ($conn instanceof mysqli) && !$conn->connect_error) {
        $fetch_error_temp = '';
        $fetched_data = getPengajuanDetailsForCompany($conn, $id_pengajuan_url, $id_perusahaan_login, $fetch_error_temp);
        
        if (is_array($fetched_data) && isset($fetched_data['pengajuan']) && $fetched_data['pengajuan'] !== null) {
            $pengajuan_detail = $fetched_data['pengajuan'];
            $dokumen_mahasiswa = $fetched_data['dokumen'];
            if (empty($display_error_message) && !empty($fetch_error_temp) && !$pengajuan_detail ) {
                $display_error_message = $fetch_error_temp;
            }
        } elseif (empty($display_error_message)) {
            $display_error_message = !empty($fetch_error_temp) ? $fetch_error_temp : "Data pengajuan KP tidak dapat dimuat untuk perusahaan Anda atau statusnya tidak lagi 'menunggu konfirmasi'.";
        }
    } elseif (empty($display_error_message)) {
        $display_error_message = "Koneksi database tidak tersedia untuk memuat data.";
    }
}

// Set judul halaman
$page_title = "Konfirmasi Pengajuan Kerja Praktek";
if ($pengajuan_detail && isset($pengajuan_detail['judul_kp'])) {
    $page_title = "Konfirmasi: " . htmlspecialchars($pengajuan_detail['judul_kp']);
} elseif ($id_pengajuan_url !== null) {
     $page_title = "Konfirmasi Pengajuan KP (ID: ".htmlspecialchars($id_pengajuan_url).")";
}
require_once '../includes/header.php';
?>

<div class="page-layout-wrapper">
    <?php require_once '../includes/sidebar_perusahaan.php'; ?>

    <main class="main-content-area">
        <div class="form-container konfirmasi-kp-form">
            <h1><?php echo htmlspecialchars($page_title); ?></h1>
            <a href="/KP/perusahaan/konfirmasi_pengajuan_list.php" class="btn btn-light btn-sm mb-3">&laquo; Kembali ke Daftar Konfirmasi</a>
            <hr>

            <?php if (!empty($success_message)): ?>
                <div class="message success"><p><?php echo htmlspecialchars($success_message); ?></p></div>
            <?php endif; ?>
            <?php if (!empty($display_error_message)): ?>
                <div class="message error"><p><?php echo htmlspecialchars($display_error_message); ?></p></div>
            <?php endif; ?>

            <?php if ($pengajuan_detail): ?>
                <div class="info-section card mb-4">
                    <div class="card-header"><h3>Detail Pengajuan dari Mahasiswa</h3></div>
                    <div class="card-body">
                        <dl>
                            <dt>Nama Mahasiswa:</dt><dd><?php echo htmlspecialchars($pengajuan_detail['nama_mahasiswa']); ?></dd>
                            <dt>NIM:</dt><dd><?php echo htmlspecialchars($pengajuan_detail['nim']); ?></dd>
                            <dt>Program Studi:</dt><dd><?php echo htmlspecialchars($pengajuan_detail['prodi']); ?></dd>
                            <dt>Angkatan:</dt><dd><?php echo htmlspecialchars($pengajuan_detail['angkatan']); ?></dd>
                            <dt>Email Mahasiswa:</dt><dd><a href="mailto:<?php echo htmlspecialchars($pengajuan_detail['email_mahasiswa']); ?>"><?php echo htmlspecialchars($pengajuan_detail['email_mahasiswa']); ?></a></dd>
                            <dt>No. HP Mahasiswa:</dt><dd><?php echo htmlspecialchars($pengajuan_detail['no_hp_mahasiswa'] ?: '-'); ?></dd>
                            <hr style="margin: 10px 0;">
                            <dt>Judul/Topik Rencana KP:</dt><dd><strong><?php echo htmlspecialchars($pengajuan_detail['judul_kp']); ?></strong></dd>
                            <dt>Deskripsi Rencana KP:</dt><dd><?php echo nl2br(htmlspecialchars($pengajuan_detail['deskripsi_kp'])); ?></dd>
                            <dt>Periode Rencana:</dt><dd><?php echo date("d M Y", strtotime($pengajuan_detail['tanggal_mulai_rencana'])); ?> s/d <?php echo date("d M Y", strtotime($pengajuan_detail['tanggal_selesai_rencana'])); ?></dd>
                            <dt>Diajukan ke Kampus:</dt><dd><?php echo date("d M Y", strtotime($pengajuan_detail['tanggal_diajukan_kampus'])); ?></dd>
                        </dl>
                    </div>
                </div>

                <div class="info-section card mb-4">
                    <div class="card-header"><h3>Dokumen Pendukung dari Mahasiswa</h3></div>
                    <div class="card-body">
                        <?php if (!empty($dokumen_mahasiswa)): ?>
                            <ul class="dokumen-list">
                                <?php foreach ($dokumen_mahasiswa as $doc): ?>
                                    <li>
                                        <strong><?php echo htmlspecialchars($doc['nama_dokumen']); ?></strong>
                                        (Jenis: <?php echo ucfirst(str_replace('_', ' ', htmlspecialchars($doc['jenis_dokumen']))); ?>)
                                        <?php if(!empty($doc['file_path'])): ?>
                                            <br><a href="/KP/<?php echo htmlspecialchars($doc['file_path']); ?>" target="_blank" class="btn btn-outline-primary btn-sm">Unduh/Lihat Dokumen</a>
                                        <?php endif; ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <p><em>Mahasiswa tidak menyertakan dokumen pendukung untuk pengajuan ini.</em></p>
                        <?php endif; ?>
                    </div>
                </div>

                <?php // Tampilkan form konfirmasi hanya jika statusnya masih 'menunggu_konfirmasi_perusahaan' dan belum ada pesan sukses dari POST
                if ($pengajuan_detail['status_pengajuan'] === 'menunggu_konfirmasi_perusahaan' && empty($success_message)): ?>
                <form action="/KP/perusahaan/konfirmasi_pengajuan_form.php?id_pengajuan=<?php echo $id_pengajuan_url; ?>" method="POST" enctype="multipart/form-data" class="action-form card">
                    <div class="card-header"><h3><i class="icon-check"></i> Berikan Konfirmasi Penerimaan</h3></div>
                    <div class="card-body">
                        <input type="hidden" name="id_pengajuan" value="<?php echo $id_pengajuan_url; ?>">
                        <div class="form-group">
                            <label>Tindakan Konfirmasi (*):</label>
                            <div>
                                <input type="radio" id="tindakan_terima" name="tindakan_konfirmasi" value="terima" required <?php echo (isset($_POST['tindakan_konfirmasi']) && $_POST['tindakan_konfirmasi'] == 'terima') ? 'checked' : ''; ?>>
                                <label for="tindakan_terima" style="font-weight:normal; margin-right:15px;">Terima Pengajuan KP</label>
                                <br>
                                <input type="radio" id="tindakan_tolak" name="tindakan_konfirmasi" value="tolak" required <?php echo (isset($_POST['tindakan_konfirmasi']) && $_POST['tindakan_konfirmasi'] == 'tolak') ? 'checked' : ''; ?>>
                                <label for="tindakan_tolak" style="font-weight:normal;">Tolak Pengajuan KP</label>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="surat_balasan_perusahaan">Upload Surat Balasan Resmi (Opsional, PDF/DOCX/JPG/PNG, maks. 5MB):</label>
                            <input type="file" id="surat_balasan_perusahaan" name="surat_balasan_perusahaan" class="form-control-file" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                            <small>Surat balasan resmi dari perusahaan jika ada (misal: surat penerimaan atau penolakan).</small>
                        </div>
                        <div class="form-actions">
                            <button type="submit" name="submit_konfirmasi" class="btn btn-success">Kirim Konfirmasi</button>
                        </div>
                    </div>
                </form>
                <?php elseif (!empty($success_message)): ?>
                    <?php else: ?>
                    <div class="message info"><p>Pengajuan KP ini sudah tidak dalam status menunggu konfirmasi dari perusahaan Anda. Status saat ini: <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $pengajuan_detail['status_pengajuan']))); ?>.</p></div>
                <?php endif; ?>


            <?php elseif(empty($display_error_message)): ?>
                <div class="message info"><p>Memuat detail pengajuan...</p></div>
            <?php endif; ?>
        </div>
    </main>
</div>

<style>
    /* Asumsikan CSS umum sudah ada dari header, sidebar, .card, .form-group, .message, .btn, status-badge sudah ada */
    .konfirmasi-kp-form h1 { margin-top: 0; margin-bottom: 5px; }
    .konfirmasi-kp-form hr { margin-top:15px; margin-bottom: 20px; }
    .btn.mb-3 { margin-bottom: 1rem !important; }
    .icon-check::before { content: "✔️ "; }

    .info-section.card .card-header h3 { font-size: 1.2em; color: #007bff; }
    .info-section.card .card-body dl dt { width: 200px; float:left; font-weight:bold; margin-bottom:0.5rem; padding-right: 10px; box-sizing: border-box;}
    .info-section.card .card-body dl dd { margin-left: 200px; margin-bottom:0.5rem; }

    .dokumen-list { list-style: none; padding: 0; }
    .dokumen-list li { background-color: #f9f9f9; padding: 10px; border: 1px solid #eee; border-radius: 4px; margin-bottom: 8px; }
    .dokumen-list li strong { font-size: 1em; }
    .dokumen-list li small { display: block; color: #666; margin-top: 3px; }
    .btn-outline-primary { color: #007bff; border-color: #007bff; }
    .btn-outline-primary:hover { color: #fff; background-color: #007bff; border-color: #007bff; }
    .form-control-file { display: block; width: 100%; }


    .action-form.card .card-header h3 { font-size: 1.2em; color: #28a745; }
</style>

<?php
require_once '../includes/footer.php';

if (isset($conn) && ($conn instanceof mysqli) && !$conn->connect_error) {
    $conn->close();
}
?>