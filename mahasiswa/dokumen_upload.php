<?php
// /KP/mahasiswa/dokumen_upload.php (Versi Final & Ditingkatkan)

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// 1. OTENTIKASI DAN OTORISASI
require_once '../includes/auth_check.php';
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'mahasiswa') {
    session_unset();
    session_destroy();
    header("Location: /KP/index.php?error=unauthorized");
    exit();
}

$nim_mahasiswa = $_SESSION['user_id'];
$id_pengajuan_url = null;
$judul_kp_konteks = "Pengajuan KP Tidak Ditemukan";
$error_message = '';
$success_message = '';

require_once '../config/db_connect.php';

// 2. VALIDASI ID PENGAJUAN DAN KEPEMILIKAN
if (isset($_GET['id_pengajuan']) && filter_var($_GET['id_pengajuan'], FILTER_VALIDATE_INT)) {
    $id_pengajuan_url = (int)$_GET['id_pengajuan'];

    if ($conn) {
        $sql_check_owner = "SELECT judul_kp FROM pengajuan_kp WHERE id_pengajuan = ? AND nim = ?";
        $stmt_check_owner = $conn->prepare($sql_check_owner);
        if ($stmt_check_owner) {
            $stmt_check_owner->bind_param("is", $id_pengajuan_url, $nim_mahasiswa);
            $stmt_check_owner->execute();
            $result_owner = $stmt_check_owner->get_result();
            if ($result_owner->num_rows === 1) {
                $judul_kp_konteks = $result_owner->fetch_assoc()['judul_kp'];
            } else {
                $error_message = "Pengajuan KP tidak ditemukan atau Anda tidak memiliki izin untuk mengaksesnya.";
                $id_pengajuan_url = null;
            }
            $stmt_check_owner->close();
        } else {
            $error_message = "Gagal memverifikasi kepemilikan pengajuan.";
            $id_pengajuan_url = null;
        }
    } else {
        $error_message = "Koneksi database gagal.";
        $id_pengajuan_url = null;
    }
} else {
    $error_message = "ID Pengajuan tidak valid atau tidak disertakan.";
}


// 3. PROSES UPLOAD DOKUMEN SAAT FORM DI-SUBMIT
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_dokumen']) && $id_pengajuan_url !== null) {
    $nama_dokumen_input = trim($_POST['nama_dokumen']);
    $jenis_dokumen_input = $_POST['jenis_dokumen'];
    $deskripsi_dokumen_input = trim($_POST['deskripsi_dokumen']);

    if (empty($nama_dokumen_input) || empty($jenis_dokumen_input)) {
        $error_message = "Nama dan Jenis Dokumen wajib diisi.";
    } elseif (!isset($_FILES["file_dokumen"]) || $_FILES["file_dokumen"]["error"] !== UPLOAD_ERR_OK) {
        $error_message = "Anda belum memilih file atau terjadi kesalahan saat mengunggah.";
    } else {
        $target_dir = "../uploads/dokumen_kp/";
        if (!is_dir($target_dir)) { mkdir($target_dir, 0755, true); }
        
        $original_filename = basename($_FILES["file_dokumen"]["name"]);
        $file_extension = strtolower(pathinfo($original_filename, PATHINFO_EXTENSION));
        $cleaned_nama_dok = preg_replace('/[^a-zA-Z0-9_-]/', '_', substr($nama_dokumen_input, 0, 50));
        $unique_filename = $id_pengajuan_url . "_" . $nim_mahasiswa . "_" . $jenis_dokumen_input . "_" . time() . "." . $file_extension;
        $target_file = $target_dir . $unique_filename;
        
        $allowed_types = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png', 'zip', 'rar'];
        if (!in_array($file_extension, $allowed_types)) {
            $error_message = "Format file tidak diizinkan. Gunakan PDF, DOC(X), JPG, PNG, ZIP, atau RAR.";
        } elseif ($_FILES["file_dokumen"]["size"] > 10000000) { // 10MB
            $error_message = "Ukuran file terlalu besar (maksimal 10MB).";
        } else {
            if (move_uploaded_file($_FILES["file_dokumen"]["tmp_name"], $target_file)) {
                $file_path_db = str_replace('../', '', $target_file);

                $sql_insert_doc = "INSERT INTO dokumen_kp (id_pengajuan, uploader_id, tipe_uploader, nama_dokumen, jenis_dokumen, file_path, deskripsi, status_verifikasi_dokumen) VALUES (?, ?, 'mahasiswa', ?, ?, ?, ?, 'pending')";
                $stmt_insert_doc = $conn->prepare($sql_insert_doc);
                if ($stmt_insert_doc) {
                    $stmt_insert_doc->bind_param("isssss", $id_pengajuan_url, $nim_mahasiswa, $nama_dokumen_input, $jenis_dokumen_input, $file_path_db, $deskripsi_dokumen_input);
                    if ($stmt_insert_doc->execute()) {
                        $success_message = "Dokumen '" . htmlspecialchars($nama_dokumen_input) . "' berhasil diunggah! Anda akan dialihkan kembali.";
                        header("refresh:3;url=/KP/mahasiswa/pengajuan_kp_detail.php?id=" . $id_pengajuan_url);
                    } else {
                        $error_message = "Gagal menyimpan informasi dokumen ke database.";
                        unlink($target_file); // Hapus file jika insert gagal
                    }
                    $stmt_insert_doc->close();
                }
            } else {
                $error_message = "Gagal memindahkan file yang diunggah.";
            }
        }
    }
}

$enum_jenis_dokumen = [
    'ktm' => 'KTM (Kartu Tanda Mahasiswa)', 'khs' => 'KHS (Kartu Hasil Studi)',
    'proposal_kp' => 'Proposal KP', 'surat_pengantar_kp' => 'Surat Pengantar KP',
    'surat_balasan_perusahaan' => 'Surat Balasan Perusahaan', 'laporan_kemajuan' => 'Laporan Kemajuan',
    'draft_laporan_akhir' => 'Draft Laporan Akhir', 'laporan_akhir_final' => 'Laporan Akhir Final',
    'lembar_pengesahan' => 'Lembar Pengesahan', 'sertifikat_kp' => 'Sertifikat KP',
    'form_penilaian_perusahaan' => 'Form Penilaian Perusahaan', 'form_penilaian_dosen' => 'Form Penilaian Dosen',
    'lainnya' => 'Lainnya'
];

$page_title = "Upload Dokumen Kerja Praktek";
require_once '../includes/header.php';
?>

<!-- KONTENER BARU UNTUK TAMPILAN MODERN -->
<div class="kp-form-container">
    <div class="form-hero-section">
        <div class="form-hero-content">
            <div class="form-hero-icon"><svg viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="17 8 12 3 7 8"></polyline><line x1="12" y1="3" x2="12" y2="15"></line></svg></div>
            <h1><?php echo htmlspecialchars($page_title); ?></h1>
            <?php if($id_pengajuan_url): ?>
                <p>Lengkapi dokumen pendukung untuk pengajuan KP: "<?php echo htmlspecialchars($judul_kp_konteks); ?>"</p>
            <?php else: ?>
                <p>Formulir untuk mengunggah dokumen pendukung Kerja Praktek.</p>
            <?php endif; ?>
        </div>
    </div>

    <div class="form-wrapper">
        <a href="pengajuan_kp_detail.php?id=<?php echo $id_pengajuan_url; ?>" class="back-link">&larr; Kembali ke Detail Pengajuan</a>

        <?php if (!empty($success_message)): ?>
            <div class="message success animate-on-scroll"><h4>Berhasil!</h4><p><?php echo htmlspecialchars($success_message); ?></p></div>
        <?php endif; ?>
        <?php if (!empty($error_message)): ?>
            <div class="message error animate-on-scroll"><p><?php echo htmlspecialchars($error_message); ?></p></div>
        <?php endif; ?>

        <?php if ($id_pengajuan_url !== null && !$success_message): ?>
        <form action="dokumen_upload.php?id_pengajuan=<?php echo $id_pengajuan_url; ?>" method="POST" enctype="multipart/form-data" class="modern-form animate-on-scroll">
            <input type="hidden" name="id_pengajuan" value="<?php echo $id_pengajuan_url; ?>">

            <div class="form-step">
                <div class="form-step-header">
                    <div class="form-step-icon"><svg viewBox="0 0 24 24"><path d="M13 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"></path><polyline points="13 2 13 9 20 9"></polyline></svg></div>
                    <h3>Detail Dokumen</h3>
                </div>
                <div class="form-group"><label for="nama_dokumen">Nama/Judul Dokumen (*)</label><input type="text" id="nama_dokumen" name="nama_dokumen" value="<?php echo isset($_POST['nama_dokumen']) ? htmlspecialchars($_POST['nama_dokumen']) : ''; ?>" required placeholder="Contoh: Proposal KP Final, Scan KHS Semester 5"></div>
                <div class="form-group"><label for="jenis_dokumen">Jenis Dokumen (*)</label><select id="jenis_dokumen" name="jenis_dokumen" required><option value="">-- Pilih Jenis --</option><?php foreach ($enum_jenis_dokumen as $value => $text): ?><option value="<?php echo $value; ?>" <?php echo (isset($_POST['jenis_dokumen']) && $_POST['jenis_dokumen'] == $value) ? 'selected' : ''; ?>><?php echo htmlspecialchars($text); ?></option><?php endforeach; ?></select></div>
                <div class="form-group"><label for="deskripsi_dokumen">Deskripsi Tambahan (Opsional)</label><textarea id="deskripsi_dokumen" name="deskripsi_dokumen" rows="4" placeholder="Jelaskan isi atau tujuan dokumen ini..."><?php echo isset($_POST['deskripsi_dokumen']) ? htmlspecialchars($_POST['deskripsi_dokumen']) : ''; ?></textarea></div>
            </div>

            <div class="form-step">
                <div class="form-step-header">
                    <div class="form-step-icon"><svg viewBox="0 0 24 24"><path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"></path></svg></div>
                    <h3>File Upload</h3>
                </div>
                <div class="form-group">
                    <label for="file_dokumen">Pilih File (*)</label>
                    <div class="file-drop-area">
                        <span class="file-drop-message">Seret & lepas file di sini, atau klik untuk memilih</span>
                        <input type="file" id="file_dokumen" name="file_dokumen" class="file-input" required accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.zip,.rar">
                    </div>
                    <small>Format: PDF, DOC(X), JPG, PNG, ZIP, RAR. Maks: 10MB.</small>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" name="submit_dokumen" class="btn-submit"><svg viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="17 8 12 3 7 8"></polyline><line x1="12" y1="3" x2="12" y2="15"></line></svg>Upload Dokumen</button>
            </div>
        </form>
        <?php endif; ?>
    </div>
</div>

<style>
/* GAYA BARU YANG TERISOLASI DALAM KONTENER */
.kp-form-container {
    --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    --text-primary: #1f2937; --text-secondary: #6b7280;
    --bg-light: #f9fafb; --border-color: #e5e7eb;
    --card-shadow: 0 10px 30px rgba(0,0,0,0.07);
    --border-radius: 16px;
    font-family: 'Inter', sans-serif;
    color: var(--text-primary);
    max-width: 900px; margin: 0 auto; padding: 2rem 1rem;
}
.kp-form-container svg { stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; fill: none; stroke: currentColor; }
.kp-form-container .form-hero-section {
    padding: 3rem 2rem; background: var(--primary-gradient);
    border-radius: var(--border-radius); margin-bottom: 2rem;
    color: white; text-align: center;
}
.kp-form-container .form-hero-content { max-width: 600px; margin: 0 auto; }
.kp-form-container .form-hero-icon {
    width: 60px; height: 60px; background: rgba(255,255,255,0.1);
    border-radius: 50%; display: flex; align-items: center; justify-content: center;
    margin: 0 auto 1.5rem;
}
.kp-form-container .form-hero-icon svg { width: 28px; height: 28px; stroke: white; }
.kp-form-container .form-hero-section h1 { font-size: 2.5rem; font-weight: 700; margin-bottom: 0.5rem; }
.kp-form-container .form-hero-section p { font-size: 1.1rem; opacity: 0.9; font-weight: 300; }
.kp-form-container .form-wrapper {
    background-color: #ffffff; padding: 2.5rem;
    border-radius: var(--border-radius); box-shadow: var(--card-shadow);
}
.kp-form-container .back-link {
    text-decoration: none; color: var(--text-secondary); font-weight: 500;
    display: inline-block; margin-bottom: 2rem; transition: color 0.2s ease;
}
.kp-form-container .back-link:hover { color: var(--text-primary); }
.kp-form-container .message {
    padding: 1rem 1.5rem; margin-bottom: 2rem;
    border-radius: 12px; border: 1px solid transparent;
    font-size: 1em; text-align: center;
}
.kp-form-container .message h4 { margin-top: 0; }
.kp-form-container .message.success { background-color: #d1e7dd; color: #0f5132; border-color: #badbcc; }
.kp-form-container .message.error { background-color: #f8d7da; color: #842029; border-color: #f5c2c7; }
.kp-form-container .modern-form .form-step {
    margin-bottom: 2.5rem; border: 1px solid #f0f0f0;
    border-radius: 12px; padding: 1.5rem; background-color: #fff;
    box-shadow: 0 4px 15px rgba(0,0,0,0.03);
}
.kp-form-container .form-step:last-of-type { margin-bottom: 0; }
.kp-form-container .form-step-header { display: flex; align-items: center; gap: 1rem; margin-bottom: 1.5rem; }
.kp-form-container .form-step-icon {
    width: 40px; height: 40px; flex-shrink: 0;
    background: var(--bg-light); border-radius: 50%; display: flex;
    align-items: center; justify-content: center; color: #667eea;
}
.kp-form-container .form-step-icon svg { width: 20px; height: 20px; stroke: currentColor; }
.kp-form-container .form-step-header h3 { margin: 0; font-weight: 600; }
.kp-form-container .form-group { margin-bottom: 1.5rem; }
.kp-form-container .form-group:last-child { margin-bottom: 0; }
.kp-form-container .form-group label { display: block; font-weight: 500; margin-bottom: 0.5rem; font-size: 0.95rem; }
.kp-form-container .form-group input, 
.kp-form-container .form-group textarea, 
.kp-form-container .form-group select {
    width: 100%; padding: 12px 15px; border: 1px solid var(--border-color);
    border-radius: 8px; font-size: 1em; font-family: 'Inter', sans-serif;
    transition: all 0.2s ease; background-color: var(--bg-light);
}
.kp-form-container .form-group input:focus, 
.kp-form-container .form-group textarea:focus, 
.kp-form-container .form-group select:focus {
    border-color: #667eea; background-color: #fff;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.2); outline: none;
}
.kp-form-container .form-group small { display: block; font-size: 0.85em; color: var(--text-secondary); margin-top: 8px; }

/* Custom File Input */
.kp-form-container .file-drop-area {
    position: relative; padding: 2rem; border: 2px dashed var(--border-color);
    border-radius: 12px; text-align: center; transition: all 0.2s ease; cursor: pointer;
}
.kp-form-container .file-drop-area.is-active {
    border-color: #667eea; background-color: rgba(102, 126, 234, 0.05);
}
.kp-form-container .file-drop-area.has-file {
    border-color: #28a745; background-color: rgba(40, 167, 69, 0.05);
}
.kp-form-container .file-drop-message { color: var(--text-secondary); font-weight: 500; }
.kp-form-container .file-input {
    position: absolute; left: 0; top: 0; height: 100%; width: 100%; cursor: pointer; opacity: 0;
}

.kp-form-container .form-actions { margin-top: 2rem; text-align: right; }
.kp-form-container .btn-submit {
    background: var(--primary-gradient); color: white; padding: 14px 30px;
    font-size: 1.1em; font-weight: 600; border: none;
    border-radius: 10px; display: inline-flex; align-items: center;
    gap: 10px; cursor: pointer; transition: all 0.3s ease;
    box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
}
.kp-form-container .btn-submit:hover:not([disabled]) {
    transform: translateY(-3px); box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
}

.kp-form-container .animate-on-scroll {
    opacity: 0; transform: translateY(30px);
    transition: opacity 0.6s ease-out, transform 0.6s ease-out;
}
.kp-form-container .animate-on-scroll.is-visible {
    opacity: 1; transform: translateY(0);
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const container = document.querySelector('.kp-form-container');
    if (!container) return;
    
    // Custom file input logic
    const fileInput = container.querySelector('.file-input');
    const dropArea = container.querySelector('.file-drop-area');
    const dropMessage = container.querySelector('.file-drop-message');

    if (fileInput && dropArea && dropMessage) {
        fileInput.addEventListener('change', () => { if (fileInput.files.length > 0) { dropMessage.textContent = fileInput.files[0].name; dropArea.classList.add('has-file'); } });
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(e => dropArea.addEventListener(e, t => { t.preventDefault(); t.stopPropagation(); }, false));
        ['dragenter', 'dragover'].forEach(e => dropArea.addEventListener(e, () => dropArea.classList.add('is-active'), false));
        ['dragleave', 'drop'].forEach(e => dropArea.addEventListener(e, () => dropArea.classList.remove('is-active'), false));
        dropArea.addEventListener('drop', e => { fileInput.files = e.dataTransfer.files; if (fileInput.files.length > 0) { dropMessage.textContent = fileInput.files[0].name; dropArea.classList.add('has-file'); } }, false);
    }

    // Scroll animation
    const animatedElements = container.querySelectorAll('.animate-on-scroll');
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => { if (entry.isIntersecting) { entry.target.classList.add('is-visible'); observer.unobserve(entry.target); } });
    }, { threshold: 0.1 });
    animatedElements.forEach(el => observer.observe(el));
});
</script>

<?php
require_once '../includes/footer.php';
if (isset($conn)) {
    $conn->close();
}
?>
