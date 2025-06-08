<?php
// /KP/perusahaan/konfirmasi_pengajuan_form.php (Versi Modern & Terisolasi)

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

require_once '../config/db_connect.php';

// 3. FUNGSI UNTUK MENGAMBIL DATA (Tidak diubah, sudah baik)
function getPengajuanDetailsForCompany($conn_db, $pengajuan_id, $id_perusahaan, &$out_error_message) {
    $data = ['pengajuan' => null, 'dokumen' => []];
    if (!$conn_db || !($conn_db instanceof mysqli)) { $out_error_message = "Koneksi DB tidak valid."; return $data; }

    $sql_pengajuan = "SELECT pk.id_pengajuan, pk.judul_kp, pk.deskripsi_kp, pk.status_pengajuan,
                             pk.tanggal_mulai_rencana, pk.tanggal_selesai_rencana, pk.created_at AS tanggal_diajukan_mahasiswa,
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
            $sql_dokumen = "SELECT nama_dokumen, jenis_dokumen, file_path, tanggal_upload FROM dokumen_kp WHERE id_pengajuan = ? AND tipe_uploader = 'mahasiswa' ORDER BY tanggal_upload DESC";
            $stmt_dokumen = $conn_db->prepare($sql_dokumen);
            if ($stmt_dokumen) {
                $stmt_dokumen->bind_param("i", $pengajuan_id);
                $stmt_dokumen->execute();
                $result_dokumen = $stmt_dokumen->get_result();
                while ($row_doc = $result_dokumen->fetch_assoc()) { $data['dokumen'][] = $row_doc; }
                $stmt_dokumen->close();
            }
        } else {
             $out_error_message = "Pengajuan KP tidak ditemukan, tidak menunggu konfirmasi Anda, atau bukan untuk perusahaan Anda.";
        }
        $stmt_pengajuan->close();
    }
    return $data;
}

// 4. PROSES KONFIRMASI JIKA FORM DISUBMIT (Logika tidak diubah, hanya ditambahkan path upload yang benar)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_konfirmasi'])) {
    if ($id_pengajuan_url === null || !empty($error_message_initial_load)) {
        $error_message = "Tidak dapat memproses: ID Pengajuan awal tidak valid.";
    } elseif (!$conn) {
        $error_message = "Koneksi database tidak tersedia.";
    } else {
        $id_pengajuan_form = (int)$_POST['id_pengajuan'];
        $tindakan_konfirmasi = $_POST['tindakan_konfirmasi'];
        $new_status_kp = ($tindakan_konfirmasi === 'terima') ? 'diterima_perusahaan' : (($tindakan_konfirmasi === 'tolak') ? 'ditolak_perusahaan' : '');

        if (empty($new_status_kp) || $id_pengajuan_form !== $id_pengajuan_url) {
            $error_message = "Kesalahan data: Tindakan tidak valid atau ID pengajuan tidak cocok.";
        } else {
            $surat_balasan_path_db = null;
            $upload_error = false;
            
            if (isset($_FILES["surat_balasan_perusahaan"]) && $_FILES["surat_balasan_perusahaan"]["error"] == UPLOAD_ERR_OK) {
                $upload_dir = "../uploads/surat_balasan/";
                if (!is_dir($upload_dir)) { mkdir($upload_dir, 0755, true); }
                
                $file_info = pathinfo($_FILES["surat_balasan_perusahaan"]["name"]);
                $file_ext = strtolower($file_info['extension']);
                $unique_filename = "surat_balasan_" . $id_pengajuan_form . "_" . time() . "." . $file_ext;
                $target_file = $upload_dir . $unique_filename;
                $allowed_types = ['pdf', 'doc', 'docx', 'jpg', 'png', 'jpeg'];

                if (!in_array($file_ext, $allowed_types) || $_FILES["surat_balasan_perusahaan"]["size"] > 5000000) {
                    $error_message = "File tidak valid (Format: PDF/DOCX/JPG/PNG, Maks: 5MB).";
                    $upload_error = true;
                } elseif (!move_uploaded_file($_FILES["surat_balasan_perusahaan"]["tmp_name"], $target_file)) {
                    $error_message = "Gagal mengupload file surat balasan.";
                    $upload_error = true;
                } else {
                    $surat_balasan_path_db = $target_file; 
                }
            }

            if (!$upload_error) {
                $conn->begin_transaction();
                try {
                    $sql_update = "UPDATE pengajuan_kp SET status_pengajuan = ?, surat_balasan_perusahaan_path = ? WHERE id_pengajuan = ? AND id_perusahaan = ? AND status_pengajuan = 'menunggu_konfirmasi_perusahaan'";
                    $stmt_update = $conn->prepare($sql_update);
                    if(!$stmt_update) throw new Exception("Prepare statement gagal.");
                    
                    $stmt_update->bind_param("ssii", $new_status_kp, $surat_balasan_path_db, $id_pengajuan_url, $id_perusahaan_login);
                    $stmt_update->execute();

                    if ($stmt_update->affected_rows === 0) {
                        throw new Exception("Tidak dapat mengubah status. Mungkin status sudah diubah sebelumnya.");
                    }
                    $stmt_update->close();
                    
                    if ($surat_balasan_path_db !== null) {
                         $nama_dok = "Surat Balasan dari " . $nama_perusahaan_login;
                         $jenis_dok = "surat_balasan_perusahaan";
                         $stmt_doc = $conn->prepare("INSERT INTO dokumen_kp (id_pengajuan, uploader_id, tipe_uploader, nama_dokumen, jenis_dokumen, file_path, status_verifikasi_dokumen) VALUES (?, ?, 'perusahaan', ?, ?, ?, 'disetujui')");
                         if(!$stmt_doc) throw new Exception("Prepare statement dokumen gagal.");
                         $uploader_id_str = (string)$id_perusahaan_login;
                         $stmt_doc->bind_param("issss", $id_pengajuan_url, $uploader_id_str, $nama_dok, $jenis_dok, $surat_balasan_path_db);
                         $stmt_doc->execute();
                         $stmt_doc->close();
                    }
                    
                    $conn->commit();
                    $success_message = "Konfirmasi pengajuan KP telah berhasil disimpan. Halaman akan dimuat ulang.";
                    header("refresh:3;url=" . $_SERVER['REQUEST_URI']);
                } catch (Exception $e) {
                    $conn->rollback();
                    $error_message = "Gagal memproses: " . $e->getMessage();
                    if ($surat_balasan_path_db && file_exists($surat_balasan_path_db)) {
                        unlink($surat_balasan_path_db);
                    }
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
    if ($conn) {
        $fetch_error_temp = '';
        $fetched_data = getPengajuanDetailsForCompany($conn, $id_pengajuan_url, $id_perusahaan_login, $fetch_error_temp);
        if ($fetched_data['pengajuan']) {
            $pengajuan_detail = $fetched_data['pengajuan'];
            $dokumen_mahasiswa = $fetched_data['dokumen'];
        } elseif (empty($display_error_message)) {
            $display_error_message = $fetch_error_temp;
        }
    } else {
         $display_error_message = "Koneksi database tidak tersedia untuk memuat data.";
    }
}

$page_title = "Konfirmasi Pengajuan Kerja Praktek";
require_once '../includes/header.php';
?>
<div class="kp-konfirmasi-modern-container">

    <div class="form-hero-section">
        <div class="form-hero-content">
            <div class="form-hero-icon">
                <svg viewBox="0 0 24 24"><path d="M14 9V5a3 3 0 0 0-3-3l-4 9v11h11.28a2 2 0 0 0 2-1.7l1.38-9a2 2 0 0 0-2-2.3zM7 22H4a2 2 0 0 1-2-2v-7a2 2 0 0 1 2-2h3"></path></svg>
            </div>
            <h1><?php echo htmlspecialchars($page_title); ?></h1>
            <p>Tinjau detail pengajuan dari mahasiswa dan berikan keputusan penerimaan.</p>
        </div>
    </div>

    <div class="form-wrapper">
        <a href="pengajuan_kp_masuk.php" class="back-link">&larr; Kembali ke Daftar Pengajuan</a>
        
        <?php if (!empty($success_message)): ?>
            <div class="message success animate-on-scroll"><h4>Berhasil!</h4><p><?php echo htmlspecialchars($success_message); ?></p></div>
        <?php endif; ?>
        <?php if (!empty($display_error_message)): ?>
            <div class="message error animate-on-scroll"><p><?php echo htmlspecialchars($display_error_message); ?></p></div>
        <?php endif; ?>
        
        <?php if ($pengajuan_detail): ?>
            <div class="info-block animate-on-scroll">
                <div class="info-header"><h3><svg viewBox="0 0 24 24"><path d="M13 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"></path><polyline points="13 2 13 9 20 9"></polyline></svg> Rencana Kerja Praktek</h3></div>
                <div class="info-content">
                    <h4><?php echo htmlspecialchars($pengajuan_detail['judul_kp']); ?></h4>
                    <p><?php echo nl2br(htmlspecialchars($pengajuan_detail['deskripsi_kp'])); ?></p>
                    <div class="info-grid">
                        <div class="info-item"><span>Periode Rencana</span><strong><?php echo date("d M Y", strtotime($pengajuan_detail['tanggal_mulai_rencana'])); ?> - <?php echo date("d M Y", strtotime($pengajuan_detail['tanggal_selesai_rencana'])); ?></strong></div>
                        <div class="info-item"><span>Diajukan Oleh Kampus</span><strong><?php echo date("d M Y", strtotime($pengajuan_detail['tanggal_diajukan_mahasiswa'])); ?></strong></div>
                    </div>
                </div>
            </div>

            <div class="info-block animate-on-scroll">
                <div class="info-header"><h3><svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg> Profil Mahasiswa</h3></div>
                 <div class="info-content">
                     <div class="info-grid">
                        <div class="info-item"><span>Nama Mahasiswa</span><strong><?php echo htmlspecialchars($pengajuan_detail['nama_mahasiswa']); ?></strong></div>
                        <div class="info-item"><span>NIM</span><strong><?php echo htmlspecialchars($pengajuan_detail['nim']); ?></strong></div>
                        <div class="info-item"><span>Program Studi</span><strong><?php echo htmlspecialchars($pengajuan_detail['prodi']); ?></strong></div>
                        <div class="info-item"><span>Angkatan</span><strong><?php echo htmlspecialchars($pengajuan_detail['angkatan']); ?></strong></div>
                        <div class="info-item"><span>Email</span><a href="mailto:<?php echo htmlspecialchars($pengajuan_detail['email_mahasiswa']); ?>"><?php echo htmlspecialchars($pengajuan_detail['email_mahasiswa']); ?></a></div>
                        <div class="info-item"><span>No. HP</span><a href="tel:<?php echo htmlspecialchars($pengajuan_detail['no_hp_mahasiswa']); ?>"><?php echo htmlspecialchars($pengajuan_detail['no_hp_mahasiswa'] ?: '-'); ?></a></div>
                    </div>
                 </div>
            </div>

            <div class="info-block animate-on-scroll">
                <div class="info-header"><h3><svg viewBox="0 0 24 24"><path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"></path></svg> Dokumen Pendukung</h3></div>
                 <div class="info-content">
                    <?php if (!empty($dokumen_mahasiswa)): ?>
                        <ul class="dokumen-list">
                            <?php foreach ($dokumen_mahasiswa as $doc): ?>
                                <li>
                                    <div class="dok-info">
                                        <strong><?php echo htmlspecialchars($doc['nama_dokumen']); ?></strong>
                                        <span>Jenis: <?php echo ucfirst(str_replace('_', ' ', htmlspecialchars($doc['jenis_dokumen']))); ?></span>
                                    </div>
                                    <?php if(!empty($doc['file_path'])): ?>
                                        <a href="/KP/<?php echo htmlspecialchars(str_replace('../', '', $doc['file_path'])); ?>" target="_blank" class="btn-unduh">Unduh</a>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p><em>Mahasiswa tidak menyertakan dokumen pendukung.</em></p>
                    <?php endif; ?>
                 </div>
            </div>

            <?php if ($pengajuan_detail['status_pengajuan'] === 'menunggu_konfirmasi_perusahaan' && empty($success_message)): ?>
            <form action="<?php echo htmlspecialchars($_SERVER['REQUEST_URI']); ?>" method="POST" enctype="multipart/form-data" class="modern-form animate-on-scroll">
                <div class="form-step">
                    <div class="form-step-header">
                        <div class="form-step-icon"><svg viewBox="0 0 24 24"><path d="M20 12.18V5.82a2 2 0 0 0-1.09-1.83l-6-3.6a2 2 0 0 0-1.82 0l-6 3.6A2 2 0 0 0 4 5.82v6.36a2 2 0 0 0 1.09 1.83l6 3.6a2 2 0 0 0 1.82 0l6-3.6A2 2 0 0 0 20 12.18z"></path><polyline points="12 22 12 12 4 7"></polyline><polyline points="20 7 12 12"></polyline></svg></div>
                        <h3>Berikan Keputusan</h3>
                    </div>
                    <input type="hidden" name="id_pengajuan" value="<?php echo $id_pengajuan_url; ?>">
                    <div class="form-group">
                        <label>Tindakan Konfirmasi (*)</label>
                        <div class="radio-options">
                            <label class="radio-card">
                                <input type="radio" name="tindakan_konfirmasi" value="terima" required>
                                <div class="radio-content">
                                    <svg class="terima" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"></polyline></svg>
                                    <span>Terima Pengajuan</span>
                                </div>
                            </label>
                            <label class="radio-card">
                                <input type="radio" name="tindakan_konfirmasi" value="tolak" required>
                                <div class="radio-content">
                                    <svg class="tolak" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                                    <span>Tolak Pengajuan</span>
                                </div>
                            </label>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="surat_balasan_perusahaan">Upload Surat Balasan Resmi (Opsional)</label>
                        <div class="file-drop-area">
                            <span class="file-drop-message">Seret & lepas file, atau klik untuk memilih</span>
                            <input type="file" id="surat_balasan_perusahaan" name="surat_balasan_perusahaan" class="file-input" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                        </div>
                        <small>Sangat disarankan jika menerima. Format: PDF/DOCX/JPG/PNG, Maks: 5MB</small>
                    </div>
                    <div class="form-actions">
                        <button type="submit" name="submit_konfirmasi" class="btn-submit">
                           <svg viewBox="0 0 24 24"><line x1="22" y1="2" x2="11" y2="13"></line><polygon points="22 2 15 22 11 13 2 9 22 2"></polygon></svg>
                            Kirim Konfirmasi
                        </button>
                    </div>
                </div>
            </form>
            <?php endif; ?>
        <?php endif; ?>

    </div>
</div>

<style>
.kp-konfirmasi-modern-container{--primary-gradient:linear-gradient(135deg,#667eea 0%,#764ba2 100%);--success-color:#28a745;--danger-color:#dc3545;--text-primary:#2d3748;--text-secondary:#718096;--bg-light:#f8f9fa;--border-color:#dee2e6;--card-shadow:0 10px 30px rgba(0,0,0,.07);--border-radius:16px;font-family:Inter,sans-serif;color:var(--text-primary);max-width:900px;margin:0 auto;padding:2rem 1rem}.kp-konfirmasi-modern-container svg{stroke-width:2;stroke-linecap:round;stroke-linejoin:round;fill:none;stroke:currentColor}.kp-konfirmasi-modern-container .form-hero-section{padding:3rem 2rem;background:var(--primary-gradient);border-radius:var(--border-radius);margin-bottom:2rem;color:#fff;text-align:center}.kp-konfirmasi-modern-container .form-hero-content{max-width:600px;margin:0 auto}.kp-konfirmasi-modern-container .form-hero-icon{width:60px;height:60px;background:rgba(255,255,255,.1);border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 1.5rem}.kp-konfirmasi-modern-container .form-hero-icon svg{width:28px;height:28px;stroke:#fff}.kp-konfirmasi-modern-container .form-hero-section h1{font-size:2.5rem;font-weight:700;margin-bottom:.5rem}.kp-konfirmasi-modern-container .form-hero-section p{font-size:1.1rem;opacity:.9;font-weight:300}.kp-konfirmasi-modern-container .form-wrapper{background-color:#fff;padding:2.5rem;border-radius:var(--border-radius);box-shadow:var(--card-shadow)}.kp-konfirmasi-modern-container .back-link{text-decoration:none;color:var(--text-secondary);font-weight:500;display:inline-block;margin-bottom:2rem;transition:color .2s ease}.kp-konfirmasi-modern-container .back-link:hover{color:var(--text-primary)}.kp-konfirmasi-modern-container .message{padding:1rem 1.5rem;margin-bottom:2rem;border-radius:12px;border:1px solid transparent;font-size:1em;text-align:center}.kp-konfirmasi-modern-container .message h4{margin-top:0}.kp-konfirmasi-modern-container .message.success{background-color:#d1e7dd;color:#0f5132;border-color:#badbcc}.kp-konfirmasi-modern-container .message.error{background-color:#f8d7da;color:#842029;border-color:#f5c2c7}.kp-konfirmasi-modern-container .info-block{border:1px solid var(--border-color);border-radius:12px;margin-bottom:1.5rem}.kp-konfirmasi-modern-container .info-header{display:flex;align-items:center;gap:12px;padding:1rem 1.5rem;border-bottom:1px solid var(--border-color)}.kp-konfirmasi-modern-container .info-header svg{width:20px;height:20px;color:#667eea}.kp-konfirmasi-modern-container .info-header h3{margin:0;font-size:1.2rem;font-weight:600}.kp-konfirmasi-modern-container .info-content{padding:1.5rem}.kp-konfirmasi-modern-container .info-content h4{margin-top:0;margin-bottom:.5rem;font-size:1.3rem}.kp-konfirmasi-modern-container .info-content p{margin-top:0;line-height:1.6;color:var(--text-secondary)}.kp-konfirmasi-modern-container .info-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:1rem}.kp-konfirmasi-modern-container .info-item span{display:block;font-size:.9rem;color:var(--text-secondary);margin-bottom:.25rem}.kp-konfirmasi-modern-container .info-item strong,.kp-konfirmasi-modern-container .info-item a{font-weight:600;text-decoration:none;color:var(--text-primary)}.kp-konfirmasi-modern-container .info-item a:hover{text-decoration:underline}.kp-konfirmasi-modern-container .dokumen-list{list-style:none;padding:0;margin:0}.kp-konfirmasi-modern-container .dokumen-list li{display:flex;justify-content:space-between;align-items:center;padding:1rem;border-radius:8px;transition:background-color .2s ease}.kp-konfirmasi-modern-container .dokumen-list li:not(:last-child){border-bottom:1px solid var(--border-color)}.kp-konfirmasi-modern-container .dokumen-list li:hover{background-color:var(--bg-light)}.kp-konfirmasi-modern-container .dokumen-list .dok-info strong{display:block;font-weight:600}.kp-konfirmasi-modern-container .dokumen-list .dok-info span{font-size:.85rem;color:var(--text-secondary)}.kp-konfirmasi-modern-container .btn-unduh{padding:.5rem 1rem;background:var(--bg-light);border:1px solid var(--border-color);border-radius:8px;text-decoration:none;color:var(--text-primary);font-weight:500;transition:all .2s ease}.kp-konfirmasi-modern-container .btn-unduh:hover{background-color:#667eea;color:#fff;border-color:#667eea}.kp-konfirmasi-modern-container .modern-form .form-step{margin-bottom:0;border:none;box-shadow:none;padding:0}.kp-konfirmasi-modern-container .modern-form .form-step-header{margin-top:1.5rem;border-top:1px solid var(--border-color);padding-top:1.5rem}.kp-konfirmasi-modern-container .form-group label{font-weight:600;margin-bottom:.75rem}.kp-konfirmasi-modern-container .radio-options{display:grid;grid-template-columns:1fr 1fr;gap:1rem}.kp-konfirmasi-modern-container .radio-card{display:block;border:2px solid var(--border-color);border-radius:12px;padding:1.5rem;cursor:pointer;transition:all .2s ease-in-out;text-align:center}.kp-konfirmasi-modern-container .radio-card input{display:none}.kp-konfirmasi-modern-container .radio-card .radio-content svg{width:36px;height:36px;margin-bottom:.5rem;transition:transform .2s ease}.kp-konfirmasi-modern-container .radio-card .radio-content .terima{color:var(--success-color)}.kp-konfirmasi-modern-container .radio-card .radio-content .tolak{color:var(--danger-color)}.kp-konfirmasi-modern-container .radio-card .radio-content span{font-size:1.1rem;font-weight:600;color:var(--text-secondary);transition:color .2s ease}.kp-konfirmasi-modern-container .radio-card:hover{border-color:#a3bffa}.kp-konfirmasi-modern-container .radio-card input:checked+.radio-content{transform:scale(1.05)}.kp-konfirmasi-modern-container .radio-card input:checked+.radio-content svg{transform:scale(1.2)}.kp-konfirmasi-modern-container .radio-card input:checked+.radio-content span{color:var(--text-primary)}.kp-konfirmasi-modern-container .radio-card input:checked~.radio-content .terima{color:var(--success-color)}.kp-konfirmasi-modern-container .radio-card input:checked~.radio-content .tolak{color:var(--danger-color)}.kp-konfirmasi-modern-container .radio-card.terima input:checked~.radio-content{color:var(--success-color)}.kp-konfirmasi-modern-container .file-drop-area{position:relative;padding:2rem;border:2px dashed var(--border-color);border-radius:12px;text-align:center;transition:all .2s ease;cursor:pointer}.kp-konfirmasi-modern-container .file-drop-area.is-active{border-color:#667eea;background-color:rgba(102,126,234,.05)}.kp-konfirmasi-modern-container .file-drop-area.has-file{border-color:var(--success-color);background-color:rgba(40,167,69,.05)}.kp-konfirmasi-modern-container .file-drop-message{color:var(--text-secondary);font-weight:500}.kp-konfirmasi-modern-container .file-input{position:absolute;left:0;top:0;height:100%;width:100%;cursor:pointer;opacity:0}.kp-konfirmasi-modern-container .form-actions{margin-top:2rem;text-align:right}.kp-konfirmasi-modern-container .btn-submit{background:var(--primary-gradient);color:#fff;padding:14px 30px;font-size:1.1em;font-weight:600;border:none;border-radius:10px;display:inline-flex;align-items:center;gap:10px;cursor:pointer;transition:all .3s ease;box-shadow:0 4px 15px rgba(102,126,234,.3)}.kp-konfirmasi-modern-container .btn-submit:hover:not([disabled]){transform:translateY(-3px);box-shadow:0 8px 25px rgba(102,126,234,.4)}.kp-konfirmasi-modern-container .animate-on-scroll{opacity:0;transform:translateY(30px);transition:opacity .6s ease-out,transform .6s ease-out}.kp-konfirmasi-modern-container .animate-on-scroll.is-visible{opacity:1;transform:translateY(0)}
</style>

<?php
require_once '../includes/footer.php';
if (isset($conn) && ($conn instanceof mysqli)) {
    $conn->close();
}
?>