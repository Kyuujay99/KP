<?php
// /KP/mahasiswa/pengajuan_kp_form.php (Versi Final dengan CSS Terisolasi)

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

require_once '../config/db_connect.php';

$error_message = '';
$success_message = '';
$list_perusahaan = [];
$mahasiswa_info = null;

// --- DEFINISI PRASYARAT KP ---
define('MIN_SKS', 90);
define('MIN_IPK', 2.75);

// AMBIL DATA MAHASISWA DAN PERUSAHAAN
if ($conn && ($conn instanceof mysqli)) {
    // Ambil data mahasiswa
    $stmt_mhs = $conn->prepare("SELECT ipk, sks_lulus FROM mahasiswa WHERE nim = ?");
    if ($stmt_mhs) {
        $stmt_mhs->bind_param("s", $nim_mahasiswa);
        $stmt_mhs->execute();
        $result_mhs = $stmt_mhs->get_result();
        $mahasiswa_info = ($result_mhs->num_rows === 1) ? $result_mhs->fetch_assoc() : null;
        $stmt_mhs->close();
    }

    // Ambil daftar perusahaan
    $result_perusahaan = $conn->query("SELECT id_perusahaan, nama_perusahaan FROM perusahaan WHERE status_akun = 'active' ORDER BY nama_perusahaan ASC");
    if ($result_perusahaan) {
        $list_perusahaan = $result_perusahaan->fetch_all(MYSQLI_ASSOC);
    }
} else {
    $error_message = "Koneksi database gagal atau tidak valid.";
}

// Tentukan status kelayakan
$sks_ok = $mahasiswa_info && $mahasiswa_info['sks_lulus'] >= MIN_SKS;
$ipk_ok = $mahasiswa_info && $mahasiswa_info['ipk'] >= MIN_IPK;
$memenuhi_syarat = $sks_ok && $ipk_ok;

// ===================================================================
// == PROSES SUBMIT FORM ==
// ===================================================================
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_pengajuan'])) {
    
    // 1. Validasi Prasyarat di Sisi Server
    if (!$memenuhi_syarat) {
        $error_message = "Anda tidak memenuhi syarat untuk mengajukan KP. Pengajuan dibatalkan.";
    } else {
        // 2. Ambil dan bersihkan data dari form
        $judul_kp = trim($_POST['judul_kp']);
        $deskripsi_kp = trim($_POST['deskripsi_kp']);
        $id_perusahaan_form = $_POST['id_perusahaan'];
        $nama_perusahaan_baru = trim($_POST['nama_perusahaan_baru']);
        $tanggal_mulai = $_POST['tanggal_mulai_rencana'];
        $tanggal_selesai = $_POST['tanggal_selesai_rencana'];

        // 3. Validasi Input Dasar
        if (empty($judul_kp) || empty($deskripsi_kp) || empty($tanggal_mulai) || empty($tanggal_selesai)) {
            $error_message = "Semua field yang ditandai (*) wajib diisi.";
        } elseif (empty($id_perusahaan_form) || ($id_perusahaan_form === 'BARU' && empty($nama_perusahaan_baru))) {
            $error_message = "Informasi perusahaan wajib diisi.";
        } else {
            
            $conn->begin_transaction();
            try {
                $id_perusahaan_final = null;

                // 4. Logika Penentuan ID Perusahaan
                if ($id_perusahaan_form === 'BARU') {
                    // Buat perusahaan baru
                    $email_placeholder = "new." . time() . "@placeholder.com";
                    $password_placeholder = "default_pass_123"; // Plain text sesuai sistem
                    $stmt_new_comp = $conn->prepare("INSERT INTO perusahaan (email_perusahaan, password_perusahaan, nama_perusahaan, status_akun) VALUES (?, ?, ?, 'pending_approval')");
                    $stmt_new_comp->bind_param("sss", $email_placeholder, $password_placeholder, $nama_perusahaan_baru);
                    if ($stmt_new_comp->execute()) {
                        $id_perusahaan_final = $conn->insert_id;
                    } else {
                        throw new Exception("Gagal membuat data perusahaan baru.");
                    }
                    $stmt_new_comp->close();
                } else {
                    $id_perusahaan_final = (int)$id_perusahaan_form;
                }

                // 5. Insert Data Pengajuan ke tabel `pengajuan_kp`
                $tanggal_pengajuan = date('Y-m-d');
                $status_pengajuan = 'diajukan_mahasiswa';
                $stmt_pengajuan = $conn->prepare("INSERT INTO pengajuan_kp (nim, id_perusahaan, judul_kp, deskripsi_kp, tanggal_pengajuan, tanggal_mulai_rencana, tanggal_selesai_rencana, status_pengajuan) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt_pengajuan->bind_param("sissssss", $nim_mahasiswa, $id_perusahaan_final, $judul_kp, $deskripsi_kp, $tanggal_pengajuan, $tanggal_mulai, $tanggal_selesai, $status_pengajuan);
                
                if (!$stmt_pengajuan->execute()) {
                    throw new Exception("Gagal menyimpan data pengajuan KP.");
                }
                $id_pengajuan_baru = $conn->insert_id;
                $stmt_pengajuan->close();

                // 6. Penanganan Upload File Proposal (jika ada)
                if (isset($_FILES['proposal_kp']) && $_FILES['proposal_kp']['error'] == UPLOAD_ERR_OK) {
                    $upload_dir = '../uploads/proposals/';
                    if (!is_dir($upload_dir)) { mkdir($upload_dir, 0755, true); }
                    
                    $file_tmp_path = $_FILES['proposal_kp']['tmp_name'];
                    $file_name = $_FILES['proposal_kp']['name'];
                    $file_size = $_FILES['proposal_kp']['size'];
                    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                    $allowed_ext = ['pdf', 'doc', 'docx'];

                    if (in_array($file_ext, $allowed_ext) && $file_size <= 5000000) { // Maks 5MB
                        $new_file_name = "proposal_" . $nim_mahasiswa . "_" . time() . "." . $file_ext;
                        $dest_path = $upload_dir . $new_file_name;

                        if (move_uploaded_file($file_tmp_path, $dest_path)) {
                            // Insert ke tabel dokumen_kp
                            $tipe_uploader = 'mahasiswa';
                            $nama_dokumen = "Proposal KP - " . $judul_kp;
                            $jenis_dokumen = 'proposal_kp';
                            $db_path = str_replace('../', '', $dest_path);
                            
                            $stmt_doc = $conn->prepare("INSERT INTO dokumen_kp (id_pengajuan, uploader_id, tipe_uploader, nama_dokumen, jenis_dokumen, file_path) VALUES (?, ?, ?, ?, ?, ?)");
                            $stmt_doc->bind_param("isssss", $id_pengajuan_baru, $nim_mahasiswa, $tipe_uploader, $nama_dokumen, $jenis_dokumen, $db_path);
                            
                            if (!$stmt_doc->execute()) {
                                throw new Exception("Gagal menyimpan data dokumen proposal.");
                            }
                            $stmt_doc->close();
                        } else {
                            throw new Exception("Gagal memindahkan file yang di-upload.");
                        }
                    } else {
                        throw new Exception("File tidak valid. Pastikan format (PDF/DOC/DOCX) dan ukuran (Maks 5MB) sesuai.");
                    }
                }

                // Jika semua berhasil
                $conn->commit();
                $success_message = "Pengajuan KP Anda telah berhasil dikirim! Anda akan dialihkan sebentar lagi.";
                // Alihkan ke halaman detail setelah beberapa detik
                header("refresh:3;url=pengajuan_kp_detail.php?id=" . $id_pengajuan_baru);

            } catch (Exception $e) {
                $conn->rollback();
                $error_message = $e->getMessage();
            }
        }
    }
}
// ===================================================================
// == AKHIR PROSES SUBMIT FORM ==
// ===================================================================


$page_title = "Formulir Pengajuan Kerja Praktek";
require_once '../includes/header.php';
?>

<div class="kp-form-modern-container">

    <div class="form-hero-section">
        <div class="form-hero-content">
            <div class="form-hero-icon">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
            </div>
            <h1><?php echo htmlspecialchars($page_title); ?></h1>
            <p>Lengkapi semua informasi di bawah ini untuk memulai proses pengajuan Kerja Praktek Anda.</p>
        </div>
    </div>

    <div class="form-wrapper">
        <div class="prasyarat-box animate-on-scroll">
            <div class="prasyarat-header">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
                <h4>Status Prasyarat Akademik</h4>
            </div>
            <div class="prasyarat-body">
                <div class="prasyarat-item">
                    <div class="prasyarat-info">
                        <span class="prasyarat-label">SKS Lulus (Minimum: <?php echo MIN_SKS; ?>)</span>
                        <span class="prasyarat-value <?php echo $sks_ok ? 'ok' : 'not-ok'; ?>">
                            <?php echo $mahasiswa_info ? htmlspecialchars($mahasiswa_info['sks_lulus']) : 'N/A'; ?>
                        </span>
                    </div>
                    <div class="prasyarat-progress">
                        <div class="progress-bar <?php echo $sks_ok ? 'ok' : 'not-ok'; ?>" style="width: <?php echo $mahasiswa_info ? min(100, ($mahasiswa_info['sks_lulus'] / MIN_SKS) * 100) : 0; ?>%;"></div>
                    </div>
                </div>
                <div class="prasyarat-item">
                     <div class="prasyarat-info">
                        <span class="prasyarat-label">IPK (Minimum: <?php echo MIN_IPK; ?>)</span>
                        <span class="prasyarat-value <?php echo $ipk_ok ? 'ok' : 'not-ok'; ?>">
                            <?php echo $mahasiswa_info ? htmlspecialchars(number_format($mahasiswa_info['ipk'], 2)) : 'N/A'; ?>
                        </span>
                    </div>
                    <div class="prasyarat-progress">
                        <div class="progress-bar <?php echo $ipk_ok ? 'ok' : 'not-ok'; ?>" style="width: <?php echo $mahasiswa_info ? min(100, ($mahasiswa_info['ipk'] / MIN_IPK) * 100) : 0; ?>%;"></div>
                    </div>
                </div>
            </div>
            <?php if (!$memenuhi_syarat && $mahasiswa_info): ?>
                <div class="prasyarat-footer not-ok">
                    Anda belum memenuhi syarat untuk mengajukan KP. Formulir dinonaktifkan.
                </div>
            <?php elseif($memenuhi_syarat): ?>
                 <div class="prasyarat-footer ok">
                    Selamat, Anda telah memenuhi syarat dan dapat mengisi formulir di bawah ini.
                </div>
            <?php endif; ?>
        </div>

        <?php if (!empty($success_message)): ?>
            <div class="message success animate-on-scroll">
                <h4>Pengajuan Berhasil!</h4>
                <p><?php echo htmlspecialchars($success_message); ?></p>
                <a href="/KP/mahasiswa/pengajuan_kp_view.php" class="btn">Lihat Status Pengajuan</a>
            </div>
        <?php endif; ?>
        <?php if (!empty($error_message)): ?>
            <div class="message error animate-on-scroll">
                <p><?php echo htmlspecialchars($error_message); ?></p>
            </div>
        <?php endif; ?>

        <?php if (!$success_message): ?>
        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST" enctype="multipart/form-data" class="modern-form">
            <fieldset <?php if(!$memenuhi_syarat) echo 'disabled'; ?>>
                
                <div class="form-step animate-on-scroll">
                    <div class="form-step-header">
                        <div class="form-step-icon"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M13 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"></path><polyline points="13 2 13 9 20 9"></polyline></svg></div>
                        <h3>Informasi Proposal KP</h3>
                    </div>
                    <div class="form-group">
                        <label for="judul_kp">Judul Kerja Praktek (*)</label>
                        <input type="text" id="judul_kp" name="judul_kp" value="<?php echo isset($_POST['judul_kp']) ? htmlspecialchars($_POST['judul_kp']) : ''; ?>" required placeholder="Contoh: Pengembangan Sistem Informasi A Berbasis Web">
                    </div>
                    <div class="form-group">
                        <label for="deskripsi_kp">Deskripsi Singkat Rencana Kegiatan (*)</label>
                        <textarea id="deskripsi_kp" name="deskripsi_kp" rows="5" required placeholder="Jelaskan secara singkat apa yang akan Anda lakukan selama KP..."><?php echo isset($_POST['deskripsi_kp']) ? htmlspecialchars($_POST['deskripsi_kp']) : ''; ?></textarea>
                    </div>
                </div>

                <div class="form-step animate-on-scroll">
                    <div class="form-step-header">
                        <div class="form-step-icon"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg></div>
                        <h3>Informasi Perusahaan Tujuan</h3>
                    </div>
                    <div class="form-group">
                        <label for="id_perusahaan">Pilih Perusahaan (jika sudah terdaftar)</label>
                        <select id="id_perusahaan" name="id_perusahaan" onchange="toggleNamaPerusahaanBaru(this.value)">
                            <option value="">-- Pilih dari daftar perusahaan mitra --</option>
                            <?php foreach ($list_perusahaan as $perusahaan): ?>
                                <option value="<?php echo $perusahaan['id_perusahaan']; ?>" <?php echo (isset($_POST['id_perusahaan']) && $_POST['id_perusahaan'] == $perusahaan['id_perusahaan']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($perusahaan['nama_perusahaan']); ?>
                                </option>
                            <?php endforeach; ?>
                            <option value="BARU">-- [LAINNYA] Input perusahaan baru --</option>
                        </select>
                    </div>
                    <div class="form-group" id="input_nama_perusahaan_baru" style="display:none;">
                        <label for="nama_perusahaan_baru">Nama Perusahaan Baru (*)</label>
                        <input type="text" id="nama_perusahaan_baru" name="nama_perusahaan_baru" value="<?php echo isset($_POST['nama_perusahaan_baru']) ? htmlspecialchars($_POST['nama_perusahaan_baru']) : ''; ?>" placeholder="Ketik nama perusahaan tujuan Anda">
                        <small>Isi kolom ini jika perusahaan Anda belum terdaftar. Data akan diverifikasi oleh Admin.</small>
                    </div>
                </div>

                <div class="form-step animate-on-scroll">
                    <div class="form-step-header">
                        <div class="form-step-icon"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg></div>
                        <h3>Rencana Pelaksanaan</h3>
                    </div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="tanggal_mulai_rencana">Tanggal Mulai (*)</label>
                            <input type="date" id="tanggal_mulai_rencana" name="tanggal_mulai_rencana" value="<?php echo isset($_POST['tanggal_mulai_rencana']) ? htmlspecialchars($_POST['tanggal_mulai_rencana']) : ''; ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="tanggal_selesai_rencana">Tanggal Selesai (*)</label>
                            <input type="date" id="tanggal_selesai_rencana" name="tanggal_selesai_rencana" value="<?php echo isset($_POST['tanggal_selesai_rencana']) ? htmlspecialchars($_POST['tanggal_selesai_rencana']) : ''; ?>" required>
                        </div>
                    </div>
                </div>

                <div class="form-step animate-on-scroll">
                    <div class="form-step-header">
                         <div class="form-step-icon"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"></path></svg></div>
                        <h3>Dokumen Pendukung</h3>
                    </div>
                    <div class="form-group">
                        <label for="proposal_kp">Upload File Proposal KP (Opsional)</label>
                        <div class="file-drop-area">
                            <span class="file-drop-message">Seret & lepas file di sini, atau klik untuk memilih file</span>
                            <input type="file" id="proposal_kp" name="proposal_kp" class="file-input" accept=".pdf,.doc,.docx">
                        </div>
                        <small>Anda bisa mengunggah proposal nanti. (Format: PDF/DOC/DOCX, Maks: 5MB)</small>
                    </div>
                </div>

            </fieldset>
            
            <div class="form-actions animate-on-scroll">
                <button type="submit" name="submit_pengajuan" class="btn-submit" <?php if(!$memenuhi_syarat) echo 'disabled'; ?>>
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="22" y1="2" x2="11" y2="13"></line><polygon points="22 2 15 22 11 13 2 9 22 2"></polygon></svg>
                    Kirim Pengajuan
                </button>
            </div>
        </form>
        <?php endif; ?>
    </div>

</div> <script>
// JavaScript untuk toggle input perusahaan baru
function toggleNamaPerusahaanBaru(selectedValue) {
    const inputDiv = document.getElementById('input_nama_perusahaan_baru');
    const inputField = document.getElementById('nama_perusahaan_baru');
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
    const container = document.querySelector('.kp-form-modern-container');
    if (!container) return; // Hentikan jika kontainer tidak ada

    // Jalankan toggle saat halaman dimuat, untuk menangani data POST
    const select = container.querySelector('#id_perusahaan');
    if (select) {
        toggleNamaPerusahaanBaru(select.value);
    }
    
    // JavaScript untuk custom file input
    const fileInput = container.querySelector('.file-input');
    const dropArea = container.querySelector('.file-drop-area');
    const dropMessage = container.querySelector('.file-drop-message');

    if (fileInput && dropArea && dropMessage) {
        fileInput.addEventListener('change', () => {
            if (fileInput.files.length > 0) {
                dropMessage.textContent = fileInput.files[0].name;
                dropArea.classList.add('has-file');
            }
        });
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            dropArea.addEventListener(eventName, e => { e.preventDefault(); e.stopPropagation(); }, false);
        });
        ['dragenter', 'dragover'].forEach(eventName => {
            dropArea.addEventListener(eventName, () => dropArea.classList.add('is-active'), false);
        });
        ['dragleave', 'drop'].forEach(eventName => {
            dropArea.addEventListener(eventName, () => dropArea.classList.remove('is-active'), false);
        });
        dropArea.addEventListener('drop', e => {
            fileInput.files = e.dataTransfer.files;
            if (fileInput.files.length > 0) {
                dropMessage.textContent = fileInput.files[0].name;
                dropArea.classList.add('has-file');
            }
        }, false);
    }

    // JavaScript untuk animasi saat scroll
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

<style>
/* GAYA BARU YANG TERISOLASI DALAM KONTENER */

.kp-form-modern-container {
    --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    --success-color: #28a745;
    --danger-color: #dc3545;
    --text-primary: #2d3748;
    --text-secondary: #718096;
    --bg-light: #f8f9fa;
    --border-color: #dee2e6;
    --card-shadow: 0 10px 30px rgba(0,0,0,0.07);
    --border-radius: 16px;
    font-family: 'Inter', sans-serif;
    color: var(--text-primary);
    max-width: 900px;
    margin: 0 auto;
    padding: 2rem 1rem;
}
.kp-form-modern-container svg {
    stroke-width: 2;
    stroke-linecap: round;
    stroke-linejoin: round;
    fill:none;
}

/* Form Hero Section */
.kp-form-modern-container .form-hero-section {
    padding: 3rem 2rem;
    background: var(--primary-gradient);
    border-radius: var(--border-radius);
    margin-bottom: 2rem;
    color: white;
    text-align: center;
}
.kp-form-modern-container .form-hero-content { max-width: 600px; margin: 0 auto; }
.kp-form-modern-container .form-hero-icon {
    width: 60px; height: 60px; background: rgba(255,255,255,0.1);
    border-radius: 50%; display: flex; align-items: center; justify-content: center;
    margin: 0 auto 1.5rem;
}
.kp-form-modern-container .form-hero-icon svg { width: 28px; height: 28px; stroke: white; }
.kp-form-modern-container .form-hero-section h1 { font-size: 2.5rem; font-weight: 700; margin-bottom: 0.5rem; }
.kp-form-modern-container .form-hero-section p { font-size: 1.1rem; opacity: 0.9; font-weight: 300; }

.kp-form-modern-container .form-wrapper {
    background-color: #ffffff; padding: 2.5rem;
    border-radius: var(--border-radius); box-shadow: var(--card-shadow);
}

/* Prasyarat Box */
.kp-form-modern-container .prasyarat-box {
    background: var(--bg-light); border: 1px solid var(--border-color);
    border-radius: 12px; margin-bottom: 2rem; overflow: hidden;
}
.kp-form-modern-container .prasyarat-header {
    display: flex; align-items: center; gap: 12px;
    padding: 1rem 1.5rem; border-bottom: 1px solid var(--border-color);
}
.kp-form-modern-container .prasyarat-header svg { width: 20px; height: 20px; color: var(--text-primary); }
.kp-form-modern-container .prasyarat-header h4 { margin: 0; font-size: 1.1rem; font-weight: 600; }
.kp-form-modern-container .prasyarat-body {
    display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1rem; padding: 1.5rem;
}
.kp-form-modern-container .prasyarat-item .prasyarat-info {
    display: flex; justify-content: space-between; align-items: baseline; margin-bottom: 0.5rem;
}
.kp-form-modern-container .prasyarat-label { font-size: 0.9rem; color: var(--text-secondary); }
.kp-form-modern-container .prasyarat-value { font-size: 1.1rem; font-weight: 600; }
.kp-form-modern-container .prasyarat-value.ok { color: var(--success-color); }
.kp-form-modern-container .prasyarat-value.not-ok { color: var(--danger-color); }
.kp-form-modern-container .prasyarat-progress {
    width: 100%; height: 8px; background-color: #e9ecef;
    border-radius: 10px; overflow: hidden;
}
.kp-form-modern-container .progress-bar { height: 100%; border-radius: 10px; transition: width 0.5s ease-in-out; }
.kp-form-modern-container .progress-bar.ok { background-color: var(--success-color); }
.kp-form-modern-container .progress-bar.not-ok { background-color: var(--danger-color); }
.kp-form-modern-container .prasyarat-footer {
    padding: 0.75rem 1.5rem; text-align: center;
    font-weight: 500; font-size: 0.9rem;
}
.kp-form-modern-container .prasyarat-footer.ok { background-color: #d1e7dd; color: #0f5132; }
.kp-form-modern-container .prasyarat-footer.not-ok { background-color: #f8d7da; color: #842029; }

/* Modern Form */
.kp-form-modern-container .modern-form fieldset { border: none; padding: 0; margin: 0; }
.kp-form-modern-container .modern-form fieldset[disabled] { opacity: 0.5; pointer-events: none; }
.kp-form-modern-container .form-step {
    margin-bottom: 2.5rem; border: 1px solid #f0f0f0;
    border-radius: 12px; padding: 1.5rem; background-color: #fff;
    box-shadow: 0 4px 15px rgba(0,0,0,0.03);
}
.kp-form-modern-container .form-step-header { display: flex; align-items: center; gap: 1rem; margin-bottom: 1.5rem; }
.kp-form-modern-container .form-step-icon {
    width: 40px; height: 40px; flex-shrink: 0;
    background: var(--bg-light); border-radius: 50%; display: flex;
    align-items: center; justify-content: center; color: #667eea;
}
.kp-form-modern-container .form-step-icon svg { width: 20px; height: 20px; stroke: currentColor; }
.kp-form-modern-container .form-step-header h3 { margin: 0; font-weight: 600; }
.kp-form-modern-container .form-group { margin-bottom: 1.5rem; }
.kp-form-modern-container .form-group:last-child { margin-bottom: 0; }
.kp-form-modern-container .form-group label { display: block; font-weight: 500; margin-bottom: 0.5rem; font-size: 0.95rem; }
.kp-form-modern-container .form-group input, 
.kp-form-modern-container .form-group textarea, 
.kp-form-modern-container .form-group select {
    width: 100%; padding: 12px 15px; border: 1px solid var(--border-color);
    border-radius: 8px; font-size: 1em; font-family: 'Inter', sans-serif;
    transition: all 0.2s ease; background-color: var(--bg-light);
}
.kp-form-modern-container .form-group input:focus, 
.kp-form-modern-container .form-group textarea:focus, 
.kp-form-modern-container .form-group select:focus {
    border-color: #667eea; background-color: #fff;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.2); outline: none;
}
.kp-form-modern-container .form-group small { display: block; font-size: 0.85em; color: var(--text-secondary); margin-top: 8px; }
.kp-form-modern-container .form-grid {
    display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem;
}

/* Custom File Input */
.kp-form-modern-container .file-drop-area {
    position: relative; padding: 2rem; border: 2px dashed var(--border-color);
    border-radius: 12px; text-align: center; transition: all 0.2s ease; cursor: pointer;
}
.kp-form-modern-container .file-drop-area.is-active {
    border-color: #667eea; background-color: rgba(102, 126, 234, 0.05);
}
.kp-form-modern-container .file-drop-area.has-file {
    border-color: var(--success-color); background-color: rgba(40, 167, 69, 0.05);
}
.kp-form-modern-container .file-drop-message { color: var(--text-secondary); font-weight: 500; }
.kp-form-modern-container .file-input {
    position: absolute; left: 0; top: 0; height: 100%; width: 100%; cursor: pointer; opacity: 0;
}

/* Notifikasi */
.kp-form-modern-container .message {
    padding: 1rem 1.5rem; margin-bottom: 2rem;
    border-radius: 12px; border: 1px solid transparent;
    font-size: 1em; text-align: center;
}
.kp-form-modern-container .message h4 { margin-top: 0; }
.kp-form-modern-container .message.success { background-color: #d1e7dd; color: #0f5132; border-color: #badbcc; }
.kp-form-modern-container .message.error { background-color: #f8d7da; color: #842029; border-color: #f5c2c7; }
.kp-form-modern-container .message .btn {
    margin-top: 1rem; display: inline-block; padding: 8px 16px;
    background-color: #0f5132; color: white; text-decoration: none;
    border-radius: 8px; font-weight: 500;
}

/* Tombol Submit */
.kp-form-modern-container .form-actions { margin-top: 2rem; text-align: right; }
.kp-form-modern-container .btn-submit {
    background: var(--primary-gradient); color: white; padding: 14px 30px;
    font-size: 1.1em; font-weight: 600; border: none;
    border-radius: 10px; display: inline-flex; align-items: center;
    gap: 10px; cursor: pointer; transition: all 0.3s ease;
    box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
}
.kp-form-modern-container .btn-submit:hover:not([disabled]) {
    transform: translateY(-3px); box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
}
.kp-form-modern-container .btn-submit[disabled] {
    background: #ced4da; cursor: not-allowed; box-shadow: none; transform: none;
}

/* Animasi on Scroll */
.kp-form-modern-container .animate-on-scroll {
    opacity: 0; transform: translateY(30px);
    transition: opacity 0.6s ease-out, transform 0.6s ease-out;
}
.kp-form-modern-container .animate-on-scroll.is-visible {
    opacity: 1; transform: translateY(0);
}
</style>

<?php
require_once '../includes/footer.php';
if(isset($conn) && $conn instanceof mysqli) {
    $conn->close();
}
?>
