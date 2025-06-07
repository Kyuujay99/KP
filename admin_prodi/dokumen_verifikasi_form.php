<?php
// /KP/admin_prodi/dokumen_verifikasi_form.php (Versi Final dan Lengkap)

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once '../includes/auth_check.php';
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin_prodi') {
    header("Location: /KP/index.php?error=unauthorized_admin");
    exit();
}
require_once '../config/db_connect.php';

$id_dokumen_url = null;
$id_pengajuan_url = null;
$dokumen_detail = null;
$pengajuan_konteks = null;
$error_message = '';
$success_message = '';

if (isset($_GET['id_dokumen']) && filter_var($_GET['id_dokumen'], FILTER_VALIDATE_INT)) {
    $id_dokumen_url = (int)$_GET['id_dokumen'];
} else {
    $error_message = "ID Dokumen tidak valid.";
}
if (isset($_GET['id_pengajuan']) && filter_var($_GET['id_pengajuan'], FILTER_VALIDATE_INT)) {
    $id_pengajuan_url = (int)$_GET['id_pengajuan'];
} else {
    if (empty($error_message)) $error_message = "ID Pengajuan tidak disertakan.";
}

function getDocumentAndPengajuanContext($conn_db, $doc_id, $pengajuan_id, &$out_error_message) {
    $data = ['dokumen' => null, 'pengajuan' => null];
    $sql_doc = "SELECT dk.*, m.nim, m.nama AS nama_mahasiswa, pk.judul_kp FROM dokumen_kp dk JOIN pengajuan_kp pk ON dk.id_pengajuan = pk.id_pengajuan JOIN mahasiswa m ON pk.nim = m.nim WHERE dk.id_dokumen = ? AND dk.id_pengajuan = ?";
    $stmt_doc = $conn_db->prepare($sql_doc);
    if ($stmt_doc) {
        $stmt_doc->bind_param("ii", $doc_id, $pengajuan_id);
        $stmt_doc->execute();
        $result_doc = $stmt_doc->get_result();
        if ($result_doc->num_rows === 1) {
            $doc_data = $result_doc->fetch_assoc();
            $data['dokumen'] = $doc_data;
            $data['pengajuan'] = ['nim' => $doc_data['nim'], 'nama_mahasiswa' => $doc_data['nama_mahasiswa'], 'judul_kp' => $doc_data['judul_kp']];
        } else {
            if(empty($out_error_message)) $out_error_message = "Dokumen tidak ditemukan atau tidak sesuai.";
        }
        $stmt_doc->close();
    }
    return $data;
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_verifikasi_dokumen']) && empty($error_message)) {
    if ($conn) {
        $new_status = $_POST['status_verifikasi_dokumen'];
        $catatan_input = trim($_POST['catatan_verifikator']);
        $id_dokumen_form = (int)$_POST['id_dokumen'];
        $allowed_statuses = ['pending', 'disetujui', 'revisi_diperlukan', 'ditolak'];

        if ($id_dokumen_form !== $id_dokumen_url) {
            $error_message = "Kesalahan: ID Dokumen tidak cocok.";
        } elseif (!in_array($new_status, $allowed_statuses)) {
            $error_message = "Status verifikasi tidak valid.";
        } else {
            $stmt_update = $conn->prepare("UPDATE dokumen_kp SET status_verifikasi_dokumen = ?, catatan_verifikator = ? WHERE id_dokumen = ?");
            if ($stmt_update) {
                $stmt_update->bind_param("ssi", $new_status, $catatan_input, $id_dokumen_form);
                if ($stmt_update->execute()) {
                    if ($stmt_update->affected_rows > 0) {
                        $success_message = "Status verifikasi dokumen berhasil diperbarui!";
                    } else {
                        $success_message = "Tidak ada perubahan data yang dilakukan.";
                    }
                } else {
                    $error_message = "Gagal memperbarui status: " . $stmt_update->error;
                }
                $stmt_update->close();
            }
        }
    }
}

if (empty($error_message) && $conn) {
    $fetched_data = getDocumentAndPengajuanContext($conn, $id_dokumen_url, $id_pengajuan_url, $error_message);
    if ($fetched_data['dokumen']) {
        $dokumen_detail = $fetched_data['dokumen'];
        $pengajuan_konteks = $fetched_data['pengajuan'];
    }
}

$opsi_status_verifikasi_dokumen = ['pending' => 'Pending', 'disetujui' => 'Disetujui', 'revisi_diperlukan' => 'Revisi Diperlukan', 'ditolak' => 'Ditolak'];
$page_title = "Verifikasi Dokumen";
if ($dokumen_detail) {
    $page_title = "Verifikasi: " . htmlspecialchars($dokumen_detail['nama_dokumen']);
}
require_once '../includes/header.php';
?>

<div class="main-content-full">
    <div class="form-container-modern">
        <div class="form-header">
            <h1><?php echo htmlspecialchars($page_title); ?></h1>
            <p>Periksa detail dokumen yang diunggah dan berikan status verifikasi.</p>
        </div>

        <?php if (!empty($success_message)): ?>
            <div class="message success">
                <p><?php echo htmlspecialchars($success_message); ?></p>
                <a href="pengajuan_kp_detail_admin.php?id_pengajuan=<?php echo $id_pengajuan_url; ?>" class="btn btn-secondary">Kembali ke Detail Pengajuan</a>
            </div>
        <?php endif; ?>
        <?php if (!empty($error_message) && !$dokumen_detail): ?>
            <div class="message error"><p><?php echo htmlspecialchars($error_message); ?></p></div>
        <?php endif; ?>

        <?php if ($dokumen_detail && $pengajuan_konteks): ?>
            <div class="content-grid-verif">
                <div class="document-details-card">
                    <div class="card-header"><h4>📄 Detail Dokumen</h4></div>
                    <div class="card-body">
                        <div class="detail-item"><span class="detail-label">Nama Dokumen</span><span class="detail-value main-detail"><?php echo htmlspecialchars($dokumen_detail['nama_dokumen']); ?></span></div>
                        <div class="detail-item"><span class="detail-label">Jenis</span><span class="detail-value"><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $dokumen_detail['jenis_dokumen']))); ?></span></div>
                        <div class="detail-item"><span class="detail-label">Tanggal Upload</span><span class="detail-value"><?php echo date("d F Y, H:i", strtotime($dokumen_detail['tanggal_upload'])); ?></span></div>
                        <?php if(!empty($dokumen_detail['deskripsi'])): ?>
                        <div class="detail-item"><span class="detail-label">Deskripsi Mahasiswa</span><span class="detail-value description-text"><?php echo nl2br(htmlspecialchars($dokumen_detail['deskripsi'])); ?></span></div>
                        <?php endif; ?>
                    </div>
                    <div class="card-footer">
                        <?php if(!empty($dokumen_detail['file_path'])): ?>
                            <a href="/KP/<?php echo htmlspecialchars($dokumen_detail['file_path']); ?>" target="_blank" class="btn btn-primary">Unduh & Lihat File</a>
                        <?php else: ?>
                            <button class="btn btn-secondary" disabled>File Tidak Tersedia</button>
                        <?php endif; ?>
                    </div>
                    <div class="context-info">
                        <p><strong>Terkait Pengajuan:</strong><br>
                        <a href="pengajuan_kp_detail_admin.php?id_pengajuan=<?php echo $id_pengajuan_url; ?>"><?php echo htmlspecialchars($pengajuan_konteks['judul_kp']); ?></a><br>
                        <small>Oleh: <?php echo htmlspecialchars($pengajuan_konteks['nama_mahasiswa']); ?> (<?php echo htmlspecialchars($pengajuan_konteks['nim']); ?>)</small></p>
                    </div>
                </div>

                <div class="verification-form-card">
                     <form action="dokumen_verifikasi_form.php?id_dokumen=<?php echo $id_dokumen_url; ?>&id_pengajuan=<?php echo $id_pengajuan_url; ?>" method="POST" class="modern-form">
                        <input type="hidden" name="id_dokumen" value="<?php echo $id_dokumen_url; ?>">
                        <fieldset>
                            <div class="fieldset-header"><h4>✔️ Formulir Verifikasi</h4></div>
                             <div class="form-group">
                                <label>Status Saat Ini:</label>
                                <span class="status-badge status-<?php echo strtolower(htmlspecialchars($dokumen_detail['status_verifikasi_dokumen'])); ?>">
                                    <?php echo ucfirst(str_replace('_', ' ', htmlspecialchars($dokumen_detail['status_verifikasi_dokumen']))); ?>
                                </span>
                            </div>
                            <div class="form-group">
                                <label for="status_verifikasi_dokumen">Ubah Status Menjadi (*)</label>
                                <div class="status-radio-group">
                                    <?php foreach ($opsi_status_verifikasi_dokumen as $value => $text): ?>
                                    <label class="radio-label">
                                        <input type="radio" name="status_verifikasi_dokumen" value="<?php echo htmlspecialchars($value); ?>" <?php echo ($dokumen_detail['status_verifikasi_dokumen'] == $value) ? 'checked' : ''; ?> required>
                                        <span class="radio-text"><?php echo htmlspecialchars($text); ?></span>
                                    </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="catatan_verifikator">Catatan Verifikator</label>
                                <textarea id="catatan_verifikator" name="catatan_verifikator" rows="6" placeholder="Berikan catatan jika dokumen ditolak atau memerlukan revisi..."><?php echo htmlspecialchars($dokumen_detail['catatan_verifikator']); ?></textarea>
                                <small>Catatan ini akan dapat dilihat oleh mahasiswa.</small>
                            </div>
                        </fieldset>
                        <div class="form-actions">
                             <a href="dokumen_verifikasi_list.php" class="btn btn-secondary">Kembali ke Daftar</a>
                            <button type="submit" name="submit_verifikasi_dokumen" class="btn btn-success">Simpan Verifikasi</button>
                        </div>
                    </form>
                </div>
            </div>
        <?php elseif(empty($error_message)): ?>
            <div class="message info"><p>Memuat detail dokumen...</p></div>
        <?php endif; ?>
    </div>
</div>
<style>
    /* Menggunakan gaya dari halaman form modern lainnya */
    .form-container-modern { max-width: 1200px; }
    .content-grid-verif { display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; align-items: flex-start; }
    .document-details-card, .verification-form-card { background-color: #fff; border-radius: var(--border-radius); box-shadow: var(--card-shadow); overflow: hidden; animation: slideUp 0.5s ease-out; }
    .document-details-card .card-header, .verification-form-card .fieldset-header { padding: 1.5rem; background-color: #f8f9fa; border-bottom: 1px solid var(--border-color); }
    .document-details-card .card-header h4, .verification-form-card .fieldset-header h4 { margin: 0; font-size: 1.2em; }
    .document-details-card .card-body { padding: 1.5rem; }
    .detail-item { margin-bottom: 1.2rem; }
    .detail-label { display: block; font-size: 0.9em; color: var(--secondary-color); margin-bottom: 0.25rem; }
    .detail-value { font-size: 1.1em; font-weight: 500; }
    .detail-value.main-detail { font-weight: 600; font-size: 1.3em; color: var(--primary-color); }
    .description-text { white-space: pre-wrap; line-height: 1.7; }
    .document-details-card .card-footer { padding: 1.5rem; background-color: #f8f9fa; border-top: 1px solid var(--border-color); }
    .document-details-card .btn { width: 100%; padding: 12px; font-weight: 600; }
    .context-info { padding: 1.5rem; background-color: #e9f5ff; }
    .context-info p { margin: 0; line-height: 1.6; }
    .context-info a { color: var(--primary-color); text-decoration: none; font-weight: 500; }
    .context-info a:hover { text-decoration: underline; }
    
    .verification-form-card .modern-form { padding: 1.5rem; }
    .status-radio-group { display: flex; flex-wrap: wrap; gap: 1rem; }
    .radio-label { display: flex; align-items: center; padding: 10px 15px; border: 1px solid var(--border-color); border-radius: 8px; cursor: pointer; transition: all 0.3s ease; }
    .radio-label:hover { background-color: #f8f9fa; }
    input[type="radio"] { margin-right: 10px; accent-color: var(--primary-color); }
    input[type="radio"]:checked + .radio-text { font-weight: 600; color: var(--primary-color); }
    input[type="radio"]:checked ~ .radio-label { border-color: var(--primary-color); background-color: #e9f5ff; }

    @media (max-width: 992px) { .content-grid-verif { grid-template-columns: 1fr; } }
</style>

<?php
require_once '../includes/footer.php';
if (isset($conn)) { $conn->close(); }
?>