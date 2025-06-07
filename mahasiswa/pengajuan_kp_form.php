<?php
// /KP/mahasiswa/pengajuan_kp_form.php (Versi Diperbarui)

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

// Sertakan file koneksi database
require_once '../config/db_connect.php';

$error_message = '';
$success_message = '';
$list_perusahaan = [];
$mahasiswa_info = null;

// --- DEFINISI PRASYARAT KP ---
define('MIN_SKS', 90);
define('MIN_IPK', 2.75);
// -----------------------------

// AMBIL DATA MAHASISWA (IPK & SKS) DAN DATA PERUSAHAAN
if ($conn && ($conn instanceof mysqli)) {
    // Ambil data mahasiswa
    $sql_mhs = "SELECT ipk, sks_lulus FROM mahasiswa WHERE nim = ?";
    $stmt_mhs = $conn->prepare($sql_mhs);
    if ($stmt_mhs) {
        $stmt_mhs->bind_param("s", $nim_mahasiswa);
        $stmt_mhs->execute();
        $result_mhs = $stmt_mhs->get_result();
        if ($result_mhs->num_rows === 1) {
            $mahasiswa_info = $result_mhs->fetch_assoc();
        }
        $stmt_mhs->close();
    }

    // Ambil daftar perusahaan
    $sql_perusahaan = "SELECT id_perusahaan, nama_perusahaan FROM perusahaan WHERE status_akun = 'active' ORDER BY nama_perusahaan ASC";
    $result_perusahaan = $conn->query($sql_perusahaan);
    if ($result_perusahaan && $result_perusahaan->num_rows > 0) {
        while ($row = $result_perusahaan->fetch_assoc()) {
            $list_perusahaan[] = $row;
        }
    }
} else {
    $error_message = "Koneksi database gagal atau tidak valid.";
}

// PROSES SUBMIT FORM
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_pengajuan'])) {
    // ... (Logika PHP untuk validasi dan proses form tetap sama persis seperti kode Anda sebelumnya)
    // ... saya tidak akan menuliskannya lagi di sini untuk keringkasan, tapi pastikan logika tersebut ada ...
}

$page_title = "Formulir Pengajuan Kerja Praktek";
require_once '../includes/header.php';
?>

<div class="main-content-full">
    <div class="form-container-modern">
        <div class="form-header">
            <h1><?php echo htmlspecialchars($page_title); ?></h1>
            <p>Lengkapi semua informasi di bawah ini untuk memulai proses pengajuan Kerja Praktek Anda.</p>
        </div>

        <div class="info-prasyarat card">
            <div class="card-header">
                <h4><i class="icon-check"></i>Status Prasyarat Akademik Anda</h4>
            </div>
            <div class="card-body">
                <?php if($mahasiswa_info): ?>
                    <div class="prasyarat-grid">
                        <div class="prasyarat-item <?php echo ($mahasiswa_info['sks_lulus'] >= MIN_SKS) ? 'ok' : 'not-ok'; ?>">
                            <span class="prasyarat-label">SKS Lulus</span>
                            <span class="prasyarat-value"><?php echo htmlspecialchars($mahasiswa_info['sks_lulus']); ?> / <?php echo MIN_SKS; ?></span>
                            <span class="prasyarat-status-icon"><?php echo ($mahasiswa_info['sks_lulus'] >= MIN_SKS) ? '✔' : '✖'; ?></span>
                        </div>
                        <div class="prasyarat-item <?php echo ($mahasiswa_info['ipk'] >= MIN_IPK) ? 'ok' : 'not-ok'; ?>">
                            <span class="prasyarat-label">IPK</span>
                            <span class="prasyarat-value"><?php echo htmlspecialchars(number_format($mahasiswa_info['ipk'], 2)); ?> / <?php echo MIN_IPK; ?></span>
                            <span class="prasyarat-status-icon"><?php echo ($mahasiswa_info['ipk'] >= MIN_IPK) ? '✔' : '✖'; ?></span>
                        </div>
                    </div>
                <?php else: ?>
                    <p><em>Tidak dapat memuat data akademik Anda. Silakan hubungi admin.</em></p>
                <?php endif; ?>
            </div>
        </div>

        <?php if (!empty($success_message)): ?>
            <div class="message success">
                <h4>Pengajuan Berhasil!</h4>
                <p><?php echo htmlspecialchars($success_message); ?></p>
                <a href="/KP/mahasiswa/pengajuan_kp_view.php" class="btn btn-primary">Lihat Status Pengajuan</a>
            </div>
        <?php endif; ?>
        <?php if (!empty($error_message)): ?>
            <div class="message error">
                <p><?php echo htmlspecialchars($error_message); ?></p>
            </div>
        <?php endif; ?>

        <?php if (!$success_message): ?>
            <?php 
            $memenuhi_syarat = ($mahasiswa_info && $mahasiswa_info['sks_lulus'] >= MIN_SKS && $mahasiswa_info['ipk'] >= MIN_IPK);
            if (!$mahasiswa_info) $memenuhi_syarat = true; // Anggap memenuhi jika data tidak terload
            ?>
            <form action="/KP/mahasiswa/pengajuan_kp_form.php" method="POST" enctype="multipart/form-data" class="modern-form">
                <fieldset <?php if(!$memenuhi_syarat) echo 'disabled'; ?>>
                    
                    <div class="fieldset-header">
                        <span class="fieldset-number">1</span>
                        <h4>Informasi Proposal Kerja Praktek</h4>
                    </div>
                    <div class="form-group">
                        <label for="judul_kp">Judul Kerja Praktek (*)</label>
                        <input type="text" id="judul_kp" name="judul_kp" value="<?php echo isset($_POST['judul_kp']) ? htmlspecialchars($_POST['judul_kp']) : ''; ?>" required placeholder="Contoh: Pengembangan Sistem Informasi A Berbasis Web">
                    </div>
                    <div class="form-group">
                        <label for="deskripsi_kp">Deskripsi Singkat Rencana Kegiatan (*)</label>
                        <textarea id="deskripsi_kp" name="deskripsi_kp" rows="5" required placeholder="Jelaskan secara singkat apa yang akan Anda lakukan selama KP..."><?php echo isset($_POST['deskripsi_kp']) ? htmlspecialchars($_POST['deskripsi_kp']) : ''; ?></textarea>
                    </div>

                    <div class="fieldset-header">
                        <span class="fieldset-number">2</span>
                        <h4>Informasi Perusahaan Tujuan</h4>
                    </div>
                    <div class="form-group">
                        <label for="id_perusahaan">Pilih Perusahaan (jika sudah terdaftar) (*)</label>
                        <select id="id_perusahaan" name="id_perusahaan" onchange="toggleNamaPerusahaanBaru(this.value)">
                            <option value="">-- Pilih dari daftar perusahaan mitra --</option>
                            <?php foreach ($list_perusahaan as $perusahaan): ?>
                                <option value="<?php echo $perusahaan['id_perusahaan']; ?>" <?php echo (isset($_POST['id_perusahaan']) && $_POST['id_perusahaan'] == $perusahaan['id_perusahaan']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($perusahaan['nama_perusahaan']); ?>
                                </option>
                            <?php endforeach; ?>
                             <option value="BARU">-- [LAINNYA] Input nama perusahaan secara manual --</option>
                        </select>
                    </div>
                    <div class="form-group" id="input_nama_perusahaan_baru" style="display:none;">
                        <label for="nama_perusahaan_baru">Nama Perusahaan Baru (*)</label>
                        <input type="text" id="nama_perusahaan_baru" name="nama_perusahaan_baru" value="<?php echo isset($_POST['nama_perusahaan_baru']) ? htmlspecialchars($_POST['nama_perusahaan_baru']) : ''; ?>" placeholder="Ketik nama perusahaan tujuan Anda">
                        <small>Isi kolom ini jika perusahaan Anda belum terdaftar di sistem. Data akan diverifikasi oleh Admin.</small>
                    </div>

                    <div class="fieldset-header">
                        <span class="fieldset-number">3</span>
                        <h4>Rencana Pelaksanaan</h4>
                    </div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="tanggal_mulai_rencana">Tanggal Mulai Rencana (*)</label>
                            <input type="date" id="tanggal_mulai_rencana" name="tanggal_mulai_rencana" value="<?php echo isset($_POST['tanggal_mulai_rencana']) ? htmlspecialchars($_POST['tanggal_mulai_rencana']) : ''; ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="tanggal_selesai_rencana">Tanggal Selesai Rencana (*)</label>
                            <input type="date" id="tanggal_selesai_rencana" name="tanggal_selesai_rencana" value="<?php echo isset($_POST['tanggal_selesai_rencana']) ? htmlspecialchars($_POST['tanggal_selesai_rencana']) : ''; ?>" required>
                        </div>
                    </div>

                    <div class="fieldset-header">
                        <span class="fieldset-number">4</span>
                        <h4>Dokumen Pendukung</h4>
                    </div>
                    <div class="form-group">
                        <label for="proposal_kp">Upload File Proposal KP (Opsional)</label>
                        <input type="file" id="proposal_kp" name="proposal_kp" class="form-control-file" accept=".pdf,.doc,.docx">
                        <small>Anda bisa mengunggah proposal nanti setelah pengajuan dibuat. (PDF/DOCX, maks. 5MB)</small>
                    </div>

                </fieldset>
                
                <div class="form-actions">
                    <button type="submit" name="submit_pengajuan" class="btn btn-primary btn-submit" <?php if(!$memenuhi_syarat) echo 'disabled'; ?>>
                        <i class="icon-submit"></i> Kirim Pengajuan
                    </button>
                    <?php if(!$memenuhi_syarat): ?>
                        <div class="message warning"><p>Anda belum dapat mengajukan KP karena prasyarat akademik belum terpenuhi.</p></div>
                    <?php endif; ?>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>

<script>
// JavaScript sederhana untuk menampilkan/menyembunyikan input nama perusahaan baru
function toggleNamaPerusahaanBaru(selectedValue) {
    var inputDiv = document.getElementById('input_nama_perusahaan_baru');
    var inputField = document.getElementById('nama_perusahaan_baru');
    if (selectedValue === 'BARU') {
        inputDiv.style.display = 'block';
        inputField.required = true;
    } else {
        inputDiv.style.display = 'none';
        inputField.required = false;
        inputField.value = '';
    }
}
document.addEventListener('DOMContentLoaded', function() {
    var select = document.getElementById('id_perusahaan');
    if (select) {
        toggleNamaPerusahaanBaru(select.value);
    }
});
</script>

<style>
    /* Ikon Sederhana */
    .icon-check::before { content: "📋 "; }
    .icon-submit::before { content: "🚀 "; }

    /* Layout Utama */
    .main-content-full {
        width: 100%;
        max-width: 900px;
        margin: 20px auto;
        padding: 20px;
    }
    .form-container-modern {
        background-color: #fff;
        padding: 2rem;
        border-radius: var(--border-radius);
        box-shadow: var(--card-shadow);
        animation: fadeIn 0.5s ease-in-out;
    }
    .form-header {
        text-align: center;
        margin-bottom: 2rem;
        border-bottom: 1px solid var(--border-color);
        padding-bottom: 1.5rem;
    }
    .form-header h1 {
        color: var(--primary-color);
        font-weight: 600;
    }
    .form-header p {
        color: var(--secondary-color);
        font-size: 1.1em;
    }

    /* Kartu Info Prasyarat */
    .info-prasyarat.card {
        background-color: #f8f9fa;
        border: 1px solid #e9ecef;
        border-radius: 10px;
        margin-bottom: 2rem;
    }
    .info-prasyarat .card-header {
        background: none;
        border: none;
        padding: 1rem 1.5rem 0.5rem;
    }
    .info-prasyarat .card-header h4 {
        color: #343a40;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .info-prasyarat .card-body {
        padding: 0 1.5rem 1rem;
    }
    .prasyarat-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 1rem;
    }
    .prasyarat-item {
        background-color: #fff;
        padding: 1rem;
        border-radius: 8px;
        border: 1px solid var(--border-color);
        text-align: center;
        transition: all 0.3s ease;
    }
    .prasyarat-item.ok { border-left: 5px solid #28a745; }
    .prasyarat-item.not-ok { border-left: 5px solid #dc3545; }
    .prasyarat-item .prasyarat-label {
        display: block;
        font-size: 0.9em;
        color: var(--secondary-color);
        margin-bottom: 0.5rem;
    }
    .prasyarat-item .prasyarat-value {
        display: block;
        font-size: 1.5em;
        font-weight: 600;
        color: var(--dark-color);
    }
    .prasyarat-item .prasyarat-status-icon {
        font-size: 1.2em;
        font-weight: bold;
    }
    .prasyarat-item.ok .prasyarat-status-icon { color: #28a745; }
    .prasyarat-item.not-ok .prasyarat-status-icon { color: #dc3545; }

    /* Form Modern */
    .modern-form fieldset {
        border: none;
        padding: 0;
        margin: 0;
    }
    .fieldset-header {
        display: flex;
        align-items: center;
        gap: 15px;
        margin-top: 2rem;
        margin-bottom: 1.5rem;
        border-bottom: 2px solid #e9ecef;
        padding-bottom: 10px;
    }
    .fieldset-number {
        background-color: var(--primary-color);
        color: white;
        width: 30px;
        height: 30px;
        border-radius: 50%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
    }
    .fieldset-header h4 {
        margin: 0;
        font-size: 1.3em;
        color: var(--dark-color);
    }

    .form-group { margin-bottom: 1.5rem; }
    .form-group label {
        display: block;
        font-weight: 500;
        margin-bottom: 0.5rem;
        color: #495057;
    }
    .form-group input, .form-group textarea, .form-group select {
        width: 100%;
        padding: 12px 15px;
        border: 1px solid var(--border-color);
        border-radius: 8px;
        font-size: 1em;
        font-family: 'Poppins', sans-serif;
        transition: border-color 0.3s ease, box-shadow 0.3s ease;
    }
    .form-group input:focus, .form-group textarea:focus, .form-group select:focus {
        border-color: var(--primary-color);
        box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.2);
        outline: none;
    }
    .form-group .form-control-file {
        border: 1px dashed var(--border-color);
        padding: 1rem;
        text-align: center;
        background-color: #f8f9fa;
        cursor: pointer;
    }
    .form-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 1.5rem;
    }
    .form-group small {
        display: block;
        font-size: 0.85em;
        color: var(--secondary-color);
        margin-top: 8px;
    }
    fieldset[disabled] { opacity: 0.5; cursor: not-allowed; }

    /* Notifikasi */
    .message {
        padding: 1rem 1.5rem;
        margin-bottom: 1.5rem;
        border-radius: 8px;
        border: 1px solid transparent;
        font-size: 1em;
    }
    .message.success { background-color: #d1e7dd; color: #0f5132; border-color: #badbcc; }
    .message.error { background-color: #f8d7da; color: #842029; border-color: #f5c2c7; }
    .message.warning { background-color: #fff3cd; color: #664d03; border-color: #ffecb5; }
    
    /* Tombol Aksi */
    .form-actions {
        margin-top: 2.5rem;
        text-align: right;
    }
    .btn-submit {
        background: linear-gradient(135deg, var(--primary-color), var(--primary-hover));
        color: white;
        padding: 12px 25px;
        font-size: 1.1em;
        font-weight: 600;
        border: none;
        display: inline-flex;
        align-items: center;
        gap: 10px;
    }
    .btn-submit:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(0, 123, 255, 0.3);
    }
    .btn-submit[disabled] {
        background: #ced4da;
        cursor: not-allowed;
    }

    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }
</style>

<?php
require_once '../includes/footer.php';
if(isset($conn) && $conn instanceof mysqli) {
    $conn->close();
}
?>