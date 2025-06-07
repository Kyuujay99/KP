<?php
// /KP/perusahaan/penilaian_lapangan_input.php

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
$id_pengajuan_url = null;
$pengajuan_info = null;
$nilai_lapangan_existing = null;
$error_message = '';
$success_message = '';

require_once '../config/db_connect.php';

// 2. VALIDASI & AMBIL DATA
if (isset($_GET['id_pengajuan']) && filter_var($_GET['id_pengajuan'], FILTER_VALIDATE_INT)) {
    $id_pengajuan_url = (int)$_GET['id_pengajuan'];
    // ... Logika untuk mengambil info pengajuan dan nilai yang sudah ada ...
} else {
    $error_message = "ID Pengajuan tidak valid.";
}

// 3. PROSES FORM PENILAIAN
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_penilaian']) && $id_pengajuan_url) {
    $nilai_input = $_POST['nilai_pembimbing_lapangan'];
    $catatan_input = trim($_POST['catatan_pembimbing_lapangan']);

    // Validasi
    if (!is_numeric($nilai_input) || $nilai_input < 0 || $nilai_input > 100) {
        $error_message = "Nilai harus berupa angka antara 0 dan 100.";
    } else {
        // Cek apakah record nilai sudah ada
        $sql_cek = "SELECT id_nilai FROM nilai_kp WHERE id_pengajuan = ?";
        // ... (Logika INSERT atau UPDATE ke tabel nilai_kp) ...
    }
}

$page_title = "Input Penilaian Lapangan";
require_once '../includes/header.php';
?>

<div class="main-content-full">
    <div class="form-container-modern">
        <div class="form-header">
            <h1><?php echo htmlspecialchars($page_title); ?></h1>
            <a href="penilaian_lapangan_list.php" class="btn btn-secondary">&laquo; Kembali ke Daftar Penilaian</a>
        </div>

        <?php if (!empty($success_message)): ?>
            <div class="message success"><p><?php echo htmlspecialchars($success_message); ?></p></div>
        <?php endif; ?>
        <?php if (!empty($error_message)): ?>
            <div class="message error"><p><?php echo htmlspecialchars($error_message); ?></p></div>
        <?php endif; ?>

        <?php if ($pengajuan_info): ?>
            <div class="info-section">
                <h3>Penilaian untuk Mahasiswa</h3>
                <dl>
                    <dt>Nama Mahasiswa:</dt><dd><?php echo htmlspecialchars($pengajuan_info['nama_mahasiswa']); ?></dd>
                    <dt>NIM:</dt><dd><?php echo htmlspecialchars($pengajuan_info['nim']); ?></dd>
                    <dt>Judul KP:</dt><dd><?php echo htmlspecialchars($pengajuan_info['judul_kp']); ?></dd>
                </dl>
            </div>
            
            <form action="penilaian_lapangan_input.php?id_pengajuan=<?php echo $id_pengajuan_url; ?>" method="POST" class="modern-form">
                <fieldset>
                    <div class="fieldset-header">
                        <span class="fieldset-number">⭐</span>
                        <h4>Formulir Penilaian</h4>
                    </div>
                    <div class="form-group">
                        <label for="nilai_pembimbing_lapangan">Nilai Kinerja Keseluruhan (0-100) (*)</label>
                        <input type="number" id="nilai_pembimbing_lapangan" name="nilai_pembimbing_lapangan" step="0.01" min="0" max="100" class="form-control" value="<?php echo htmlspecialchars($nilai_lapangan_existing['nilai'] ?? ''); ?>" required>
                        <small>Aspek penilaian mencakup: kedisiplinan, inisiatif, kemampuan kerja sama, dan pencapaian target.</small>
                    </div>
                     <div class="form-group">
                        <label for="catatan_pembimbing_lapangan">Catatan/Feedback untuk Mahasiswa (Opsional)</label>
                        <textarea id="catatan_pembimbing_lapangan" name="catatan_pembimbing_lapangan" rows="6" placeholder="Berikan feedback konstruktif mengenai kinerja mahasiswa selama KP..."><?php echo htmlspecialchars($nilai_lapangan_existing['catatan'] ?? ''); ?></textarea>
                    </div>
                </fieldset>
                <div class="form-actions">
                    <button type="submit" name="submit_penilaian" class="btn btn-primary btn-submit">Simpan Penilaian</button>
                </div>
            </form>
        <?php endif; ?>

    </div>
</div>

<?php require_once '../includes/footer.php'; ?>