<?php
// /KP/perusahaan/pengajuan_kp_konfirmasi.php

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// 1. OTENTIKASI & OTORISASI
require_once '../includes/auth_check.php';
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'perusahaan') {
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

require_once '../config/db_connect.php';

// 2. VALIDASI & AMBIL DATA PENGAJUAN
if (isset($_GET['id_pengajuan']) && filter_var($_GET['id_pengajuan'], FILTER_VALIDATE_INT)) {
    $id_pengajuan_url = (int)$_GET['id_pengajuan'];
    // ... (Fungsi getPengajuanDetailsForCompany dari file lama bisa ditaruh di sini) ...
} else {
    $error_message = "ID Pengajuan tidak valid.";
}

// 3. PROSES FORM KONFIRMASI
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_konfirmasi']) && $id_pengajuan_url) {
    // ... (Logika PHP untuk memproses form konfirmasi dari file lama bisa ditaruh di sini) ...
    // Update status ke 'diterima_perusahaan' atau 'ditolak_perusahaan'
}

// Selalu ambil data terbaru untuk ditampilkan
if ($id_pengajuan_url && empty($error_message)) {
    // ... (Logika PHP untuk mengambil data detail pengajuan dan dokumennya bisa ditaruh di sini) ...
}

$page_title = "Konfirmasi Pengajuan KP";
require_once '../includes/header.php';
?>

<div class="main-content-full">
    <div class="form-container-modern">
        <div class="form-header">
            <h1><?php echo htmlspecialchars($page_title); ?></h1>
            <a href="pengajuan_kp_masuk.php" class="btn btn-secondary">&laquo; Kembali ke Daftar Pengajuan</a>
        </div>

        <?php if (!empty($success_message)): ?>
            <div class="message success"><p><?php echo htmlspecialchars($success_message); ?></p></div>
        <?php endif; ?>
        <?php if (!empty($error_message)): ?>
            <div class="message error"><p><?php echo htmlspecialchars($error_message); ?></p></div>
        <?php endif; ?>
        
        <?php if ($pengajuan_detail): ?>
            <div class="info-section">
                <h3>Detail Pengajuan dari Mahasiswa</h3>
                <dl>
                    <dt>Nama Mahasiswa:</dt><dd><?php echo htmlspecialchars($pengajuan_detail['nama_mahasiswa']); ?></dd>
                    </dl>
            </div>

            <div class="info-section">
                <h3>Dokumen Pendukung dari Mahasiswa</h3>
                </div>

            <?php if ($pengajuan_detail['status_pengajuan'] === 'menunggu_konfirmasi_perusahaan'): ?>
            <form action="pengajuan_kp_konfirmasi.php?id_pengajuan=<?php echo $id_pengajuan_url; ?>" method="POST" enctype="multipart/form-data" class="modern-form">
                <fieldset>
                    <div class="fieldset-header">
                        <span class="fieldset-number">!</span>
                        <h4>Tindakan Konfirmasi</h4>
                    </div>
                    <div class="form-group">
                        <label>Berikan jawaban Anda untuk pengajuan ini (*):</label>
                        <div class="radio-group">
                            <label><input type="radio" name="tindakan_konfirmasi" value="terima" required> Terima Pengajuan</label>
                            <label><input type="radio" name="tindakan_konfirmasi" value="tolak" required> Tolak Pengajuan</label>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="surat_balasan_perusahaan">Upload Surat Balasan Resmi (Opsional)</label>
                        <input type="file" id="surat_balasan_perusahaan" name="surat_balasan_perusahaan" class="form-control-file">
                        <small>Sangat disarankan untuk mengunggah surat balasan resmi (PDF, JPG, PNG).</small>
                    </div>
                </fieldset>
                <div class="form-actions">
                    <button type="submit" name="submit_konfirmasi" class="btn btn-primary btn-submit">Kirim Konfirmasi</button>
                </div>
            </form>
            <?php endif; ?>

        <?php elseif(empty($error_message)): ?>
            <p>Memuat data pengajuan...</p>
        <?php endif; ?>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>

<style>
/* ... CSS dari halaman form mahasiswa bisa diadaptasi di sini ... */
.form-container-modern { max-width: 900px; margin: 20px auto; }
.info-section { background-color: #f8f9fa; padding: 1.5rem; border-radius: 12px; margin-bottom: 2rem; }
.info-section h3 { margin-top: 0; }
.info-section dl { display: grid; grid-template-columns: 200px 1fr; gap: 10px; }
.info-section dt { font-weight: 600; color: #495057; }
.radio-group { display: flex; gap: 2rem; margin-top: 0.5rem; }
.radio-group label { display: flex; align-items: center; gap: 0.5rem; font-weight: 500; }
</style>