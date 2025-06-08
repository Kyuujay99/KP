<?php
// /KP/perusahaan/penilaian_lapangan_input.php (Versi Modern & Terisolasi)

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

// 2. VALIDASI & AMBIL DATA AWAL
if (isset($_GET['id_pengajuan']) && filter_var($_GET['id_pengajuan'], FILTER_VALIDATE_INT)) {
    $id_pengajuan_url = (int)$_GET['id_pengajuan'];
    
    if ($conn) {
        // Ambil info pengajuan dan mahasiswa, pastikan milik perusahaan yang login
        $sql_info = "SELECT m.nama AS nama_mahasiswa, m.nim, pk.judul_kp
                     FROM pengajuan_kp pk
                     JOIN mahasiswa m ON pk.nim = m.nim
                     WHERE pk.id_pengajuan = ? AND pk.id_perusahaan = ? 
                     AND pk.status_pengajuan IN ('kp_berjalan', 'selesai_pelaksanaan', 'laporan_disetujui', 'selesai_dinilai')";
        $stmt_info = $conn->prepare($sql_info);
        $stmt_info->bind_param("ii", $id_pengajuan_url, $id_perusahaan_login);
        $stmt_info->execute();
        $result_info = $stmt_info->get_result();
        if ($result_info->num_rows > 0) {
            $pengajuan_info = $result_info->fetch_assoc();
            
            // Cek apakah nilai sudah ada
            $stmt_nilai = $conn->prepare("SELECT nilai_pembimbing_lapangan, catatan_pembimbing_lapangan FROM nilai_kp WHERE id_pengajuan = ?");
            $stmt_nilai->bind_param("i", $id_pengajuan_url);
            $stmt_nilai->execute();
            $result_nilai = $stmt_nilai->get_result();
            if ($result_nilai->num_rows > 0) {
                $nilai_lapangan_existing = $result_nilai->fetch_assoc();
            }
            $stmt_nilai->close();
        } else {
            $error_message = "Pengajuan tidak ditemukan atau Anda tidak berhak menilai mahasiswa ini.";
        }
        $stmt_info->close();
    } else {
        $error_message = "Koneksi database gagal.";
    }
} else {
    $error_message = "ID Pengajuan tidak valid.";
}

// 3. PROSES FORM PENILAIAN SAAT SUBMIT
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_penilaian']) && $id_pengajuan_url && empty($error_message)) {
    $nilai_input = $_POST['nilai_pembimbing_lapangan'];
    $catatan_input = trim($_POST['catatan_pembimbing_lapangan']);

    if (!is_numeric($nilai_input) || $nilai_input < 0 || $nilai_input > 100) {
        $error_message = "Nilai harus berupa angka antara 0 dan 100.";
    } else {
        if ($nilai_lapangan_existing) {
            // Jika nilai sudah ada, UPDATE
            $sql = "UPDATE nilai_kp SET nilai_pembimbing_lapangan = ?, catatan_pembimbing_lapangan = ? WHERE id_pengajuan = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("dsi", $nilai_input, $catatan_input, $id_pengajuan_url);
        } else {
            // Jika nilai belum ada, INSERT
            $sql = "INSERT INTO nilai_kp (id_pengajuan, nilai_pembimbing_lapangan, catatan_pembimbing_lapangan) VALUES (?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ids", $id_pengajuan_url, $nilai_input, $catatan_input);
        }

        if ($stmt->execute()) {
            $success_message = "Penilaian telah berhasil disimpan!";
            // Refresh data nilai yang ada untuk ditampilkan di form
            $nilai_lapangan_existing = ['nilai_pembimbing_lapangan' => $nilai_input, 'catatan_pembimbing_lapangan' => $catatan_input];
        } else {
            $error_message = "Gagal menyimpan penilaian ke database: " . $stmt->error;
        }
        $stmt->close();
    }
}


$page_title = "Input Penilaian Lapangan";
require_once '../includes/header.php';
?>

<div class="kp-penilaian-modern-container">
    <div class="form-hero-section">
        <div class="form-hero-content">
            <div class="form-hero-icon"><svg viewBox="0 0 24 24"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg></div>
            <h1><?php echo htmlspecialchars($page_title); ?></h1>
            <p>Berikan evaluasi kinerja mahasiswa selama melaksanakan Kerja Praktek di perusahaan Anda.</p>
        </div>
    </div>
    <div class="form-wrapper">
        <a href="mahasiswa_kp_list.php" class="back-link">&larr; Kembali ke Daftar Mahasiswa</a>
        <?php if (!empty($success_message)): ?>
            <div class="message success"><h4>Berhasil!</h4><p><?php echo htmlspecialchars($success_message); ?></p></div>
        <?php endif; ?>
        <?php if (!empty($error_message)): ?>
            <div class="message error"><p><?php echo htmlspecialchars($error_message); ?></p></div>
        <?php endif; ?>
        <?php if ($pengajuan_info): ?>
            <div class="info-mahasiswa-block animate-on-scroll">
                <div class="user-profile">
                    <div class="user-avatar"><?php echo strtoupper(substr($pengajuan_info['nama_mahasiswa'], 0, 1)); ?></div>
                    <div class="user-info">
                        <h4><?php echo htmlspecialchars($pengajuan_info['nama_mahasiswa']); ?></h4>
                        <span>NIM: <?php echo htmlspecialchars($pengajuan_info['nim']); ?></span>
                    </div>
                </div>
                <div class="kp-info">
                    <p><strong>Judul KP:</strong> <?php echo htmlspecialchars($pengajuan_info['judul_kp']); ?></p>
                </div>
            </div>
            <form action="penilaian_lapangan_input.php?id_pengajuan=<?php echo $id_pengajuan_url; ?>" method="POST" class="modern-form animate-on-scroll">
                <div class="form-step">
                    <div class="form-step-header">
                        <div class="form-step-icon"><svg viewBox="0 0 24 24"><path d="M12 20h9"></path><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"></path></svg></div>
                        <h3>Formulir Penilaian Kinerja</h3>
                    </div>
                    <div class="form-group">
                        <label for="nilai_pembimbing_lapangan">Nilai Kinerja Keseluruhan (0-100)</label>
                        <div class="nilai-slider-group">
                            <input type="range" id="nilai_slider" min="0" max="100" step="1" value="<?php echo htmlspecialchars($nilai_lapangan_existing['nilai_pembimbing_lapangan'] ?? 75); ?>">
                            <input type="number" id="nilai_pembimbing_lapangan" name="nilai_pembimbing_lapangan" step="0.01" min="0" max="100" class="nilai-input-box" value="<?php echo htmlspecialchars($nilai_lapangan_existing['nilai_pembimbing_lapangan'] ?? 75.00); ?>" required>
                        </div>
                        <small>Aspek penilaian mencakup: kedisiplinan, inisiatif, kemampuan kerja sama, dan pencapaian target.</small>
                    </div>
                    <div class="form-group">
                        <label for="catatan_pembimbing_lapangan">Catatan/Feedback untuk Mahasiswa (Opsional)</label>
                        <textarea id="catatan_pembimbing_lapangan" name="catatan_pembimbing_lapangan" rows="6" placeholder="Berikan feedback konstruktif mengenai kinerja mahasiswa..."><?php echo htmlspecialchars($nilai_lapangan_existing['catatan_pembimbing_lapangan'] ?? ''); ?></textarea>
                    </div>
                    <div class="form-actions">
                        <button type="submit" name="submit_penilaian" class="btn-submit">
                            <svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"></polyline></svg>
                            <?php echo $nilai_lapangan_existing ? 'Simpan Perubahan' : 'Simpan Penilaian'; ?>
                        </button>
                    </div>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>

<style>
/* GAYA BARU YANG TERISOLASI DALAM KONTENER */
.kp-penilaian-modern-container {
    --primary-gradient: linear-gradient(135deg,#667eea 0%,#764ba2 100%);
    --success-color: #28a745; --danger-color: #dc3545;
    --text-primary: #1f2937; --text-secondary: #6b7280;
    --bg-light: #f9fafb; --border-color: #e5e7eb;
    --card-shadow: 0 10px 30px rgba(0,0,0,.07); --border-radius: 16px;
    font-family: Inter,sans-serif; color: var(--text-primary);
    max-width: 900px; margin: 0 auto; padding: 2rem 1rem;
}
.kp-penilaian-modern-container svg {
    stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; fill: none; stroke: currentColor;
}
.kp-penilaian-modern-container .form-hero-section {
    padding: 3rem 2rem; background: var(--primary-gradient); border-radius: var(--border-radius);
    margin-bottom: 2rem; color: #fff; text-align: center;
}
.kp-penilaian-modern-container .form-hero-content { max-width: 600px; margin: 0 auto; }
.kp-penilaian-modern-container .form-hero-icon {
    width: 60px; height: 60px; background: rgba(255,255,255,.1);
    border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1.5rem;
}
.kp-penilaian-modern-container .form-hero-icon svg { width: 28px; height: 28px; stroke: #fff; }
.kp-penilaian-modern-container .form-hero-section h1 { font-size: 2.5rem; font-weight: 700; margin-bottom: .5rem; }
.kp-penilaian-modern-container .form-hero-section p { font-size: 1.1rem; opacity: .9; font-weight: 300; }
.kp-penilaian-modern-container .form-wrapper {
    background-color: #fff; padding: 2.5rem; border-radius: var(--border-radius); box-shadow: var(--card-shadow);
}
.kp-penilaian-modern-container .back-link {
    text-decoration: none; color: var(--text-secondary); font-weight: 500;
    display: inline-block; margin-bottom: 2rem; transition: color .2s ease;
}
.kp-penilaian-modern-container .back-link:hover { color: var(--text-primary); }
.kp-penilaian-modern-container .message {
    padding: 1rem 1.5rem; margin-bottom: 2rem; border-radius: 12px;
    border: 1px solid transparent; font-size: 1em; text-align: center;
}
.kp-penilaian-modern-container .message h4 { margin-top: 0; }
.kp-penilaian-modern-container .message.success { background-color: #d1e7dd; color: #0f5132; border-color: #badbcc; }
.kp-penilaian-modern-container .message.error { background-color: #f8d7da; color: #842029; border-color: #f5c2c7; }

.kp-penilaian-modern-container .info-mahasiswa-block {
    display: flex; flex-direction: column; gap: 1rem;
    padding: 1.5rem; background-color: var(--bg-light);
    border: 1px solid var(--border-color); border-radius: 12px; margin-bottom: 2rem;
}
.kp-penilaian-modern-container .user-profile { display: flex; align-items: center; gap: 1rem; }
.kp-penilaian-modern-container .user-avatar {
    width: 50px; height: 50px; border-radius: 50%; background-color: var(--primary-color);
    color: #fff; display: flex; align-items: center; justify-content: center;
    font-weight: 600; font-size: 1.5rem; flex-shrink: 0;
}
.kp-penilaian-modern-container .user-info h4 { margin: 0; font-size: 1.25rem; }
.kp-penilaian-modern-container .user-info span { font-size: 1rem; color: var(--text-secondary); }
.kp-penilaian-modern-container .kp-info { margin-top: 0.5rem; padding-top: 1rem; border-top: 1px solid var(--border-color); }
.kp-penilaian-modern-container .kp-info p { margin: 0; line-height: 1.5; }

.kp-penilaian-modern-container .modern-form .form-step {
    border: 1px solid #f0f0f0; border-radius: 12px; padding: 1.5rem;
    background-color: #fff; box-shadow: 0 4px 15px rgba(0,0,0,0.03);
}
.kp-penilaian-modern-container .form-step-header { display: flex; align-items: center; gap: 1rem; margin-bottom: 1.5rem; }
.kp-penilaian-modern-container .form-step-icon {
    width: 40px; height: 40px; flex-shrink: 0; background: var(--bg-light);
    border-radius: 50%; display: flex; align-items: center; justify-content: center; color: var(--primary-color);
}
.kp-penilaian-modern-container .form-step-icon svg { width: 20px; height: 20px; stroke: currentColor; }
.kp-penilaian-modern-container .form-step-header h3 { margin: 0; font-weight: 600; }
.kp-penilaian-modern-container .form-group { margin-bottom: 1.5rem; }
.kp-penilaian-modern-container .form-group:last-child { margin-bottom: 0; }
.kp-penilaian-modern-container .form-group label { display: block; font-weight: 500; margin-bottom: .5rem; font-size: .95rem; }
.kp-penilaian-modern-container .form-group textarea {
    width: 100%; padding: 12px 15px; border: 1px solid var(--border-color);
    border-radius: 8px; font-size: 1em; font-family: Inter,sans-serif;
    transition: all .2s ease; background-color: var(--bg-light);
}
.kp-penilaian-modern-container .form-group textarea:focus {
    border-color: #667eea; background-color: #fff;
    box-shadow: 0 0 0 3px rgba(102,126,234,.2); outline: none;
}
.kp-penilaian-modern-container .form-group small { display: block; font-size: .85em; color: var(--text-secondary); margin-top: 8px; }

.kp-penilaian-modern-container .nilai-slider-group { display: flex; align-items: center; gap: 1rem; }
.kp-penilaian-modern-container input[type="range"] {
    -webkit-appearance: none; appearance: none;
    width: 100%; height: 8px; background: var(--border-color);
    border-radius: 5px; outline: none; transition: opacity .2s;
}
.kp-penilaian-modern-container input[type="range"]::-webkit-slider-thumb {
    -webkit-appearance: none; appearance: none;
    width: 24px; height: 24px; background: var(--primary-color);
    cursor: pointer; border-radius: 50%; border: 4px solid #fff;
    box-shadow: 0 0 5px rgba(0,0,0,0.2);
}
.kp-penilaian-modern-container input[type="range"]::-moz-range-thumb {
    width: 24px; height: 24px; background: var(--primary-color);
    cursor: pointer; border-radius: 50%; border: 4px solid #fff;
    box-shadow: 0 0 5px rgba(0,0,0,0.2);
}
.kp-penilaian-modern-container .nilai-input-box {
    width: 100px; padding: 12px; text-align: center; font-size: 1.2rem; font-weight: 600;
    border: 1px solid var(--border-color); border-radius: 8px;
    background-color: var(--bg-light); transition: all .2s ease;
}
.kp-penilaian-modern-container .nilai-input-box:focus {
    border-color: #667eea; background-color: #fff;
    box-shadow: 0 0 0 3px rgba(102,126,234,.2); outline: none;
}

.kp-penilaian-modern-container .form-actions { margin-top: 2rem; text-align: right; }
.kp-penilaian-modern-container .btn-submit {
    background: var(--primary-gradient); color: #fff; padding: 14px 30px;
    font-size: 1.1em; font-weight: 600; border: none; border-radius: 10px;
    display: inline-flex; align-items: center; gap: 10px; cursor: pointer;
    transition: all .3s ease; box-shadow: 0 4px 15px rgba(102,126,234,.3);
}
.kp-penilaian-modern-container .btn-submit:hover:not([disabled]) {
    transform: translateY(-3px); box-shadow: 0 8px 25px rgba(102,126,234,.4);
}
.kp-penilaian-modern-container .animate-on-scroll {
    opacity: 0; transform: translateY(30px); transition: opacity .6s ease-out,transform .6s ease-out;
}
.kp-penilaian-modern-container .animate-on-scroll.is-visible { opacity: 1; transform: translateY(0); }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const container = document.querySelector('.kp-penilaian-modern-container');
    if (!container) return;
    
    // Sinkronisasi slider dan input angka
    const nilaiSlider = container.querySelector('#nilai_slider');
    const nilaiInput = container.querySelector('#nilai_pembimbing_lapangan');
    if(nilaiSlider && nilaiInput) {
        nilaiSlider.addEventListener('input', () => {
            nilaiInput.value = parseFloat(nilaiSlider.value).toFixed(2);
        });
        nilaiInput.addEventListener('input', () => {
            nilaiSlider.value = nilaiInput.value;
        });
    }

    // Animasi saat scroll
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
if (isset($conn) && $conn instanceof mysqli) {
    $conn->close();
}
?>