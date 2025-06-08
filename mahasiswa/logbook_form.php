<?php
// /KP/mahasiswa/logbook_form.php (Versi Modern & Terisolasi)

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// 1. OTENTIKASI DAN OTORISASI
require_once '../includes/auth_check.php';
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'mahasiswa') {
    header("Location: /KP/index.php?error=unauthorized");
    exit();
}

$nim_mahasiswa = $_SESSION['user_id'];
$error_message = '';
$success_message = '';
$active_kp_list = [];
$selected_id_pengajuan = null;

require_once '../config/db_connect.php';

// 2. AMBIL DATA KP AKTIF (Logika PHP Anda sudah baik dan dipertahankan)
if ($conn) {
    $sql_active_kp = "SELECT id_pengajuan, judul_kp FROM pengajuan_kp WHERE nim = ? AND status_pengajuan = 'kp_berjalan' ORDER BY tanggal_mulai_rencana DESC";
    $stmt_active_kp = $conn->prepare($sql_active_kp);
    if ($stmt_active_kp) {
        $stmt_active_kp->bind_param("s", $nim_mahasiswa);
        $stmt_active_kp->execute();
        $result_active_kp = $stmt_active_kp->get_result();
        $active_kp_list = $result_active_kp->fetch_all(MYSQLI_ASSOC);
        $stmt_active_kp->close();

        if (count($active_kp_list) === 1) {
            $selected_id_pengajuan = $active_kp_list[0]['id_pengajuan'];
        }
    } else {
        $error_message = "Gagal mengambil data KP aktif: " . $conn->error;
    }
} else {
    $error_message = "Koneksi database gagal.";
}

// 3. PROSES PENYIMPANAN LOGBOOK (Logika PHP Anda sudah baik dan dipertahankan)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_logbook'])) {
    $id_pengajuan_logbook = isset($_POST['id_pengajuan']) ? (int)$_POST['id_pengajuan'] : null;
    $tanggal_kegiatan = $_POST['tanggal_kegiatan'];
    $jam_mulai = !empty($_POST['jam_mulai']) ? $_POST['jam_mulai'] : null;
    $jam_selesai = !empty($_POST['jam_selesai']) ? $_POST['jam_selesai'] : null;
    $uraian_kegiatan = trim($_POST['uraian_kegiatan']);

    if (empty($id_pengajuan_logbook)) {
        $error_message = "Kerja Praktek aktif tidak dipilih atau tidak ditemukan.";
    } elseif (empty($tanggal_kegiatan) || empty($uraian_kegiatan)) {
        $error_message = "Tanggal dan Uraian Kegiatan wajib diisi.";
    } elseif ($jam_mulai && $jam_selesai && strtotime($jam_selesai) <= strtotime($jam_mulai)) {
        $error_message = "Jam selesai harus setelah jam mulai.";
    } else {
        $sql_verify_kp = "SELECT id_pengajuan FROM pengajuan_kp WHERE id_pengajuan = ? AND nim = ? AND status_pengajuan = 'kp_berjalan'";
        $stmt_verify = $conn->prepare($sql_verify_kp);
        $stmt_verify->bind_param("is", $id_pengajuan_logbook, $nim_mahasiswa);
        $stmt_verify->execute();
        $stmt_verify->store_result();
        
        if ($stmt_verify->num_rows === 1) {
            $sql_insert = "INSERT INTO logbook (id_pengajuan, tanggal_kegiatan, jam_mulai, jam_selesai, uraian_kegiatan, status_verifikasi_logbook) VALUES (?, ?, ?, ?, ?, 'pending')";
            $stmt_insert = $conn->prepare($sql_insert);
            $stmt_insert->bind_param("issss", $id_pengajuan_logbook, $tanggal_kegiatan, $jam_mulai, $jam_selesai, $uraian_kegiatan);
            if ($stmt_insert->execute()) {
                $success_message = "Catatan logbook berhasil disimpan!";
                $_POST['uraian_kegiatan'] = ''; // Kosongkan field uraian setelah sukses
            } else {
                $error_message = "Gagal menyimpan logbook: " . $stmt_insert->error;
            }
            $stmt_insert->close();
        } else {
            $error_message = "Pengajuan KP yang dipilih tidak valid atau tidak aktif.";
        }
        $stmt_verify->close();
    }
}

$page_title = "Isi Logbook Kegiatan KP";
require_once '../includes/header.php';
?>

<div class="kp-logbook-form-container">

    <div class="form-hero-section">
        <div class="form-hero-content">
            <div class="form-hero-icon">
                <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line></svg>
            </div>
            <h1><?php echo htmlspecialchars($page_title); ?></h1>
            <p>Catat kegiatan harian atau mingguan Anda selama pelaksanaan Kerja Praktek di sini.</p>
        </div>
    </div>

    <div class="form-wrapper">
        <a href="logbook_view.php" class="back-link">&larr; Lihat Riwayat Logbook</a>

        <?php if (!empty($success_message)): ?>
            <div class="message success"><h4>Berhasil!</h4><p><?php echo htmlspecialchars($success_message); ?></p></div>
        <?php endif; ?>
        <?php if (!empty($error_message)): ?>
            <div class="message error"><p><?php echo htmlspecialchars($error_message); ?></p></div>
        <?php endif; ?>

        <?php if (empty($active_kp_list) && empty($error_message)): ?>
            <div class="message warning">
                <h4>Tidak Ada KP Aktif</h4>
                <p>Anda hanya dapat mengisi logbook jika status KP Anda 'Sedang Berjalan'.</p>
                <a href="/KP/mahasiswa/pengajuan_kp_view.php" class="btn-info">Lihat Status Pengajuan KP</a>
            </div>
        <?php else: ?>
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST" class="modern-form animate-on-scroll">
                <div class="form-step">
                    <?php if (count($active_kp_list) > 1): ?>
                        <div class="form-group">
                            <label for="id_pengajuan">Pilih Pengajuan KP (*)</label>
                            <select id="id_pengajuan" name="id_pengajuan" required>
                                <option value="">-- Pilih KP Aktif --</option>
                                <?php foreach ($active_kp_list as $kp): ?>
                                    <option value="<?php echo $kp['id_pengajuan']; ?>" <?php echo (isset($_POST['id_pengajuan']) && $_POST['id_pengajuan'] == $kp['id_pengajuan']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($kp['judul_kp']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php else: ?>
                        <input type="hidden" name="id_pengajuan" value="<?php echo $selected_id_pengajuan; ?>">
                        <div class="info-kp-aktif">
                            <span>Mengisi logbook untuk KP:</span>
                            <strong><?php echo htmlspecialchars($active_kp_list[0]['judul_kp']); ?></strong>
                        </div>
                    <?php endif; ?>

                    <div class="form-group">
                        <label for="tanggal_kegiatan">Tanggal Kegiatan (*)</label>
                        <input type="date" id="tanggal_kegiatan" name="tanggal_kegiatan" value="<?php echo isset($_POST['tanggal_kegiatan']) ? htmlspecialchars($_POST['tanggal_kegiatan']) : date('Y-m-d'); ?>" required>
                    </div>

                    <div class="form-grid">
                        <div class="form-group">
                            <label for="jam_mulai">Jam Mulai (Opsional)</label>
                            <input type="time" id="jam_mulai" name="jam_mulai" value="<?php echo isset($_POST['jam_mulai']) ? htmlspecialchars($_POST['jam_mulai']) : ''; ?>">
                        </div>
                        <div class="form-group">
                            <label for="jam_selesai">Jam Selesai (Opsional)</label>
                            <input type="time" id="jam_selesai" name="jam_selesai" value="<?php echo isset($_POST['jam_selesai']) ? htmlspecialchars($_POST['jam_selesai']) : ''; ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="uraian_kegiatan">Uraian Kegiatan (*)</label>
                        <textarea id="uraian_kegiatan" name="uraian_kegiatan" rows="8" required placeholder="Jelaskan secara detail kegiatan yang Anda lakukan pada tanggal tersebut..."><?php echo isset($_POST['uraian_kegiatan']) ? htmlspecialchars($_POST['uraian_kegiatan']) : ''; ?></textarea>
                    </div>

                    <div class="form-actions">
                        <button type="reset" class="btn-secondary">Reset Form</button>
                        <button type="submit" name="submit_logbook" class="btn-submit">
                            <svg viewBox="0 0 24 24"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path><polyline points="17 21 17 13 7 13 7 21"></polyline><polyline points="7 3 7 8 15 8"></polyline></svg>
                            Simpan Catatan
                        </button>
                    </div>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>

<style>
/* GAYA BARU YANG TERISOLASI DALAM KONTENER */
.kp-logbook-form-container{--primary-gradient:linear-gradient(135deg,#667eea 0%,#764ba2 100%);--text-primary:#1f2937;--text-secondary:#6b7280;--bg-light:#f9fafb;--border-color:#e5e7eb;--card-shadow:0 10px 30px rgba(0,0,0,.07);--border-radius:16px;font-family:Inter,sans-serif;color:var(--text-primary);max-width:900px;margin:0 auto;padding:2rem 1rem}.kp-logbook-form-container svg{stroke-width:2;stroke-linecap:round;stroke-linejoin:round;fill:none;stroke:currentColor}.kp-logbook-form-container .form-hero-section{padding:3rem 2rem;background:var(--primary-gradient);border-radius:var(--border-radius);margin-bottom:2rem;color:#fff;text-align:center}.kp-logbook-form-container .form-hero-content{max-width:600px;margin:0 auto}.kp-logbook-form-container .form-hero-icon{width:60px;height:60px;background:rgba(255,255,255,.1);border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 1.5rem}.kp-logbook-form-container .form-hero-icon svg{width:28px;height:28px;stroke:#fff}.kp-logbook-form-container .form-hero-section h1{font-size:2.5rem;font-weight:700;margin-bottom:.5rem}.kp-logbook-form-container .form-hero-section p{font-size:1.1rem;opacity:.9;font-weight:300}.kp-logbook-form-container .form-wrapper{background-color:#fff;padding:2.5rem;border-radius:var(--border-radius);box-shadow:var(--card-shadow)}.kp-logbook-form-container .back-link{text-decoration:none;color:var(--text-secondary);font-weight:500;display:inline-block;margin-bottom:2rem;transition:color .2s ease}.kp-logbook-form-container .back-link:hover{color:var(--text-primary)}.kp-logbook-form-container .message{padding:1.5rem;border-radius:var(--border-radius);text-align:center;margin-bottom:2rem}.kp-logbook-form-container .message.info{background-color:#eff6ff;color:#1e40af}.kp-logbook-form-container .message.success{background-color:#dcfce7;color:#166534}.kp-logbook-form-container .message.error{background-color:#fee2e2;color:#991b1b}.kp-logbook-form-container .message.warning{background-color:#fefce8;color:#854d0e}.kp-logbook-form-container .message h4{margin-top:0;font-size:1.2rem}.kp-logbook-form-container .btn-info{display:inline-block;margin-top:1rem;padding:.6rem 1.2rem;background-color:#3b82f6;color:#fff;font-weight:500;text-decoration:none;border-radius:8px;transition:background-color .2s}.kp-logbook-form-container .btn-info:hover{background-color:#2563eb}.kp-logbook-form-container .info-kp-aktif{padding:1rem 1.5rem;background-color:var(--bg-light);border:1px solid var(--border-color);border-radius:12px;margin-bottom:2rem;text-align:center}.kp-logbook-form-container .info-kp-aktif span{display:block;color:var(--text-secondary);font-size:.9rem}.kp-logbook-form-container .info-kp-aktif strong{font-size:1.1rem}.kp-logbook-form-container .modern-form .form-step{padding:0}.kp-logbook-form-container .form-group{margin-bottom:1.5rem}.kp-logbook-form-container .form-group label{display:block;font-weight:500;margin-bottom:.5rem;font-size:.95rem}.kp-logbook-form-container .form-group input,.kp-logbook-form-container .form-group select,.kp-logbook-form-container .form-group textarea{width:100%;padding:12px 15px;border:1px solid var(--border-color);border-radius:8px;font-size:1em;font-family:Inter,sans-serif;transition:all .2s ease;background-color:var(--bg-light)}.kp-logbook-form-container .form-group input:focus,.kp-logbook-form-container .form-group select:focus,.kp-logbook-form-container .form-group textarea:focus{border-color:#667eea;background-color:#fff;box-shadow:0 0 0 3px rgba(102,126,234,.2);outline:none}.kp-logbook-form-container .form-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:1.5rem}.kp-logbook-form-container .form-actions{margin-top:2rem;padding-top:1.5rem;border-top:1px solid var(--border-color);display:flex;justify-content:flex-end;gap:1rem}.kp-logbook-form-container .btn-submit{background:var(--primary-gradient);color:#fff;padding:12px 24px;font-size:1rem;font-weight:600;border:none;border-radius:8px;display:inline-flex;align-items:center;gap:10px;cursor:pointer;transition:all .3s ease;box-shadow:0 4px 15px rgba(102,126,234,.3)}.kp-logbook-form-container .btn-submit:hover:not([disabled]){transform:translateY(-2px);box-shadow:0 7px 20px rgba(102,126,234,.4)}.kp-logbook-form-container .btn-submit svg{width:18px;height:18px}.kp-logbook-form-container .btn-secondary{background:var(--bg-light);color:var(--text-secondary);border:1px solid var(--border-color);padding:12px 24px;font-size:1rem;font-weight:600;border-radius:8px;cursor:pointer;transition:all .2s ease}.kp-logbook-form-container .btn-secondary:hover{background-color:var(--border-color);color:var(--text-primary)}.kp-logbook-form-container .animate-on-scroll{opacity:0;transform:translateY(30px);transition:opacity .6s ease-out,transform .6s ease-out}.kp-logbook-form-container .animate-on-scroll.is-visible{opacity:1;transform:translateY(0)}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const container = document.querySelector('.kp-logbook-form-container');
    if (!container) return;
    const animatedElements = container.querySelectorAll('.animate-on-scroll');
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('is-visible');
                observer.unobserve(entry.target);
            }
        });
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