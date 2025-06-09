<?php
// /KP/admin_prodi/nilai_finalisasi_form.php (Versi Lengkap & Ditingkatkan)

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// 1. OTENTIKASI DAN OTORISASI
require_once '../includes/auth_check.php';
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin_prodi') {
    session_unset();
    session_destroy();
    header("Location: /KP/index.php?error=unauthorized_admin");
    exit();
}

$admin_identifier = $_SESSION['user_id'];
$id_pengajuan_url = null;
$pengajuan_info = null;
$nilai_kp_entry = null;
$seminar_detail = null; // Diperlukan untuk cek penguji 2
$calculated_nilai_angka = null;
$determined_nilai_huruf = '';
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

// --- DEFINISI BOBOT DAN SKALA NILAI ---
define('BOBOT_PEMBIMBING_LAPANGAN', 0.25); // 25%
define('BOBOT_DOSEN_PEMBIMBING', 0.35);    // 35%
define('BOBOT_SEMINAR_PENGUJI', 0.40);     // 40%

function getNilaiHuruf($nilai_angka) {
    if ($nilai_angka === null) return '';
    if ($nilai_angka >= 85) return 'A'; if ($nilai_angka >= 80) return 'A-';
    if ($nilai_angka >= 75) return 'B+'; if ($nilai_angka >= 70) return 'B';
    if ($nilai_angka >= 65) return 'B-'; if ($nilai_angka >= 60) return 'C+';
    if ($nilai_angka >= 55) return 'C';  if ($nilai_angka >= 50) return 'D';
    return 'E';
}

// 3. FUNGSI UNTUK MENGAMBIL DATA
function getPengajuanAndAllData($conn_db, $pengajuan_id, &$out_error_message) {
    $data = ['pengajuan' => null, 'nilai_kp' => null, 'seminar' => null];
    if (!$conn_db || !($conn_db instanceof mysqli) || $conn_db->connect_error) { $out_error_message = "Koneksi DB tidak valid."; return $data; }
    if (!$pengajuan_id) { $out_error_message = "ID Pengajuan tidak valid."; return $data; }

    $sql_pengajuan = "SELECT pk.id_pengajuan, pk.judul_kp, pk.status_pengajuan, m.nim, m.nama AS nama_mahasiswa, m.prodi FROM pengajuan_kp pk JOIN mahasiswa m ON pk.nim = m.nim WHERE pk.id_pengajuan = ?";
    $stmt_pengajuan = $conn_db->prepare($sql_pengajuan);
    if ($stmt_pengajuan) {
        $stmt_pengajuan->bind_param("i", $pengajuan_id); $stmt_pengajuan->execute(); $result_pengajuan = $stmt_pengajuan->get_result();
        if ($result_pengajuan->num_rows === 1) {
            $data['pengajuan'] = $result_pengajuan->fetch_assoc();
            $sql_nilai = "SELECT * FROM nilai_kp WHERE id_pengajuan = ?";
            $stmt_nilai = $conn_db->prepare($sql_nilai);
            if($stmt_nilai){ $stmt_nilai->bind_param("i", $pengajuan_id); $stmt_nilai->execute(); $result_nilai = $stmt_nilai->get_result(); if ($result_nilai->num_rows === 1) { $data['nilai_kp'] = $result_nilai->fetch_assoc(); } $stmt_nilai->close(); }
            $sql_seminar = "SELECT nip_dosen_penguji2 FROM seminar_kp WHERE id_pengajuan = ?";
            $stmt_seminar = $conn_db->prepare($sql_seminar);
            if($stmt_seminar){ $stmt_seminar->bind_param("i", $pengajuan_id); $stmt_seminar->execute(); $result_seminar = $stmt_seminar->get_result(); if ($result_seminar->num_rows === 1) { $data['seminar'] = $result_seminar->fetch_assoc(); } $stmt_seminar->close(); }
        } else { if (empty($out_error_message)) $out_error_message = "Pengajuan KP (ID: ".htmlspecialchars($pengajuan_id).") tidak ditemukan."; }
        $stmt_pengajuan->close();
    } else { $out_error_message = "Gagal siapkan query info pengajuan: " . htmlspecialchars($conn_db->error); }
    return $data;
}

// 4. PROSES FORM SUBMIT
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_finalisasi_nilai'])) {
    if ($id_pengajuan_url === null) {
        $error_message = "ID Pengajuan tidak valid.";
    } else {
        $id_pengajuan_form = (int)$_POST['id_pengajuan'];
        $nilai_akhir_angka_input = isset($_POST['nilai_akhir_angka']) && is_numeric($_POST['nilai_akhir_angka']) ? (float)$_POST['nilai_akhir_angka'] : null;
        $nilai_akhir_huruf_input = trim($_POST['nilai_akhir_huruf']);
        $is_final_input = isset($_POST['is_final']) ? 1 : 0;

        if ($id_pengajuan_form !== $id_pengajuan_url) {
            $error_message = "Kesalahan: ID Pengajuan pada form tidak cocok.";
        } elseif ($nilai_akhir_angka_input === null || $nilai_akhir_angka_input < 0 || $nilai_akhir_angka_input > 100) {
            $error_message = "Nilai Akhir Angka harus antara 0 dan 100.";
        } elseif (empty($nilai_akhir_huruf_input)) {
            $error_message = "Nilai Akhir Huruf wajib diisi.";
        }

        if(empty($error_message)){
            $conn->begin_transaction();
            try {
                $stmt_cek = $conn->prepare("SELECT id_nilai FROM nilai_kp WHERE id_pengajuan = ?");
                $stmt_cek->bind_param("i", $id_pengajuan_url);
                $stmt_cek->execute();
                $id_nilai_existing = $stmt_cek->get_result()->fetch_assoc()['id_nilai'] ?? null;
                $stmt_cek->close();

                if ($id_nilai_existing) { 
                    $sql_action = "UPDATE nilai_kp SET nilai_akhir_angka = ?, nilai_akhir_huruf = ?, is_final = ? WHERE id_nilai = ?";
                    $stmt_action = $conn->prepare($sql_action);
                    $stmt_action->bind_param("dsii", $nilai_akhir_angka_input, $nilai_akhir_huruf_input, $is_final_input, $id_nilai_existing);
                } else { 
                    $sql_action = "INSERT INTO nilai_kp (id_pengajuan, nilai_akhir_angka, nilai_akhir_huruf, is_final) VALUES (?, ?, ?, ?)";
                    $stmt_action = $conn->prepare($sql_action);
                    $stmt_action->bind_param("idsi", $id_pengajuan_url, $nilai_akhir_angka_input, $nilai_akhir_huruf_input, $is_final_input);
                }
                $stmt_action->execute();
                $stmt_action->close();
                
                if ($is_final_input == 1) {
                    $stmt_update_status = $conn->prepare("UPDATE pengajuan_kp SET status_pengajuan = 'selesai_dinilai' WHERE id_pengajuan = ?");
                    $stmt_update_status->bind_param("i", $id_pengajuan_url);
                    $stmt_update_status->execute();
                    $stmt_update_status->close();
                }
                
                $conn->commit();
                $success_message = "Nilai akhir KP berhasil disimpan" . ($is_final_input ? " dan difinalisasi." : " sebagai draf.");

            } catch (Exception $e) {
                $conn->rollback();
                $error_message = "Gagal menyimpan nilai akhir: " . $e->getMessage();
            }
        }
    }
}

// SELALU AMBIL DATA TERBARU UNTUK DITAMPILKAN
if ($id_pengajuan_url !== null && empty($error_message_initial_load)) {
    if ($conn) {
        $fetch_error_temp = '';
        $fetched_data = getPengajuanAndAllData($conn, $id_pengajuan_url, $fetch_error_temp);
        if ($fetched_data['pengajuan']) {
            $pengajuan_info = $fetched_data['pengajuan'];
            $nilai_kp_entry = $fetched_data['nilai_kp'];
            $seminar_detail = $fetched_data['seminar'];
            
            if ($nilai_kp_entry) {
                $n_lap = $nilai_kp_entry['nilai_pembimbing_lapangan'];
                $n_dospem = $nilai_kp_entry['nilai_dosen_pembimbing'];
                $n_uji1 = $nilai_kp_entry['nilai_penguji1_seminar'];
                $n_uji2 = $nilai_kp_entry['nilai_penguji2_seminar'];
                
                $nilai_seminar_avg = null;
                $jumlah_penguji_valid = 0;
                $total_nilai_penguji = 0;

                if ($n_uji1 !== null) { $total_nilai_penguji += $n_uji1; $jumlah_penguji_valid++; }
                if ($n_uji2 !== null) { $total_nilai_penguji += $n_uji2; $jumlah_penguji_valid++; }

                if ($jumlah_penguji_valid > 0) {
                    $nilai_seminar_avg = $total_nilai_penguji / $jumlah_penguji_valid;
                }

                $calculated_nilai_angka = 0;
                if($n_lap !== null) $calculated_nilai_angka += ($n_lap * BOBOT_PEMBIMBING_LAPANGAN);
                if($n_dospem !== null) $calculated_nilai_angka += ($n_dospem * BOBOT_DOSEN_PEMBIMBING);
                if($nilai_seminar_avg !== null) $calculated_nilai_angka += ($nilai_seminar_avg * BOBOT_SEMINAR_PENGUJI);
                
                if ($calculated_nilai_angka !== null) {
                    $determined_nilai_huruf = getNilaiHuruf($calculated_nilai_angka);
                }
            }
        } else {
            $error_message = $fetch_error_temp ?: "Data pengajuan KP tidak dapat dimuat.";
        }
    } else {
        $error_message = "Koneksi database tidak tersedia.";
    }
} else {
    $error_message = $error_message_initial_load;
}

$page_title = "Finalisasi Nilai Kerja Praktek";
if ($pengajuan_info) {
    $page_title = "Finalisasi Nilai: " . htmlspecialchars($pengajuan_info['nama_mahasiswa']);
}
require_once '../includes/header.php';
?>

<!-- KONTENER BARU UNTUK TAMPILAN MODERN -->
<div class="kp-finalisasi-nilai-container">

    <div class="form-hero-section">
        <div class="form-hero-content">
            <div class="form-hero-icon"><svg viewBox="0 0 24 24"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg></div>
            <h1><?php echo htmlspecialchars($page_title); ?></h1>
            <p>Rekapitulasi, kalkulasi, dan finalisasi nilai akhir Kerja Praktek mahasiswa.</p>
        </div>
    </div>
    
    <div class="form-wrapper">
        <a href="/KP/admin_prodi/pengajuan_kp_detail_admin.php?id_pengajuan=<?php echo $id_pengajuan_url; ?>" class="back-link">&larr; Kembali ke Detail Pengajuan</a>
        
        <?php if (!empty($success_message)): ?>
            <div class="message success animate-on-scroll"><h4>Berhasil!</h4><p><?php echo htmlspecialchars($success_message); ?></p></div>
        <?php endif; ?>
        <?php if (!empty($error_message)): ?>
            <div class="message error animate-on-scroll"><p><?php echo htmlspecialchars($error_message); ?></p></div>
        <?php endif; ?>

        <?php if ($pengajuan_info): ?>
            <div class="info-grid">
                <div class="info-card animate-on-scroll">
                    <div class="info-card-header">
                        <svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                        <h3>Informasi Mahasiswa</h3>
                    </div>
                    <div class="info-card-body">
                        <div class="info-item"><span>Nama Mahasiswa</span><strong><?php echo htmlspecialchars($pengajuan_info['nama_mahasiswa']); ?></strong></div>
                        <div class="info-item"><span>NIM</span><strong><?php echo htmlspecialchars($pengajuan_info['nim']); ?></strong></div>
                        <div class="info-item"><span>Judul KP</span><strong><?php echo htmlspecialchars($pengajuan_info['judul_kp']); ?></strong></div>
                    </div>
                </div>

                <div class="info-card animate-on-scroll">
                    <div class="info-card-header">
                        <svg viewBox="0 0 24 24"><path d="M12 20V10"></path><path d="M18 20V4"></path><path d="M6 20v-4"></path></svg>
                        <h3>Rekapitulasi Komponen Nilai</h3>
                    </div>
                    <div class="info-card-body">
                        <?php if ($nilai_kp_entry): ?>
                            <div class="info-item"><span>Pembimbing Lapangan</span><strong><?php echo $nilai_kp_entry['nilai_pembimbing_lapangan'] !== null ? number_format($nilai_kp_entry['nilai_pembimbing_lapangan'], 2) : '<em>Belum Ada</em>'; ?></strong></div>
                            <div class="info-item"><span>Dosen Pembimbing</span><strong><?php echo $nilai_kp_entry['nilai_dosen_pembimbing'] !== null ? number_format($nilai_kp_entry['nilai_dosen_pembimbing'], 2) : '<em>Belum Ada</em>'; ?></strong></div>
                            <div class="info-item"><span>Penguji Seminar 1</span><strong><?php echo $nilai_kp_entry['nilai_penguji1_seminar'] !== null ? number_format($nilai_kp_entry['nilai_penguji1_seminar'], 2) : '<em>Belum Ada</em>'; ?></strong></div>
                            <div class="info-item"><span>Penguji Seminar 2</span><strong><?php echo $nilai_kp_entry['nilai_penguji2_seminar'] !== null ? number_format($nilai_kp_entry['nilai_penguji2_seminar'], 2) : '<em>Belum Ada</em>'; ?></strong></div>
                        <?php else: ?>
                            <p class="text-muted">Belum ada komponen nilai yang diinput.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <form action="/KP/admin_prodi/nilai_finalisasi_form.php?id_pengajuan=<?php echo $id_pengajuan_url; ?>" method="POST" class="modern-form animate-on-scroll">
                <div class="form-step">
                    <div class="form-step-header">
                        <div class="form-step-icon"><svg viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-6h2v6zm0-8h-2V7h2v2z"></path></svg></div>
                        <h3>Finalisasi Nilai Akhir</h3>
                    </div>
                    <div class="form-group">
                        <label for="nilai_akhir_angka_calc">Nilai Akhir Kalkulasi Sistem</label>
                        <input type="text" id="nilai_akhir_angka_calc" class="readonly-input" value="<?php echo ($calculated_nilai_angka !== null) ? number_format($calculated_nilai_angka, 2) : 'Komponen belum lengkap'; ?>" readonly>
                        <small>Bobot: Lapangan (25%), Dospem (35%), Seminar (40%)</small>
                    </div>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="nilai_akhir_angka">Input/Override Nilai Angka (*)</label>
                            <input type="number" id="nilai_akhir_angka" name="nilai_akhir_angka" min="0" max="100" step="0.01" value="<?php echo htmlspecialchars( ($nilai_kp_entry && isset($nilai_kp_entry['nilai_akhir_angka'])) ? $nilai_kp_entry['nilai_akhir_angka'] : ($calculated_nilai_angka !== null ? number_format($calculated_nilai_angka, 2) : '') ); ?>" required oninput="updateNilaiHuruf(this.value)">
                        </div>
                        <div class="form-group">
                            <label for="nilai_akhir_huruf">Nilai Huruf (*)</label>
                            <input type="text" id="nilai_akhir_huruf" name="nilai_akhir_huruf" value="<?php echo htmlspecialchars( ($nilai_kp_entry && isset($nilai_kp_entry['nilai_akhir_huruf'])) ? $nilai_kp_entry['nilai_akhir_huruf'] : $determined_nilai_huruf ); ?>" required maxlength="3">
                        </div>
                    </div>
                    <div class="form-group checkbox-group">
                        <input type="checkbox" id="is_final" name="is_final" value="1" <?php echo ($nilai_kp_entry && isset($nilai_kp_entry['is_final']) && $nilai_kp_entry['is_final'] == 1) ? 'checked' : ''; ?>>
                        <label for="is_final">Tandai sebagai Nilai Final</label>
                        <small>Mencentang ini akan mengubah status KP menjadi 'Selesai Dinilai' dan tidak dapat diubah lagi.</small>
                    </div>
                    <div class="form-actions"><button type="submit" name="submit_finalisasi_nilai" class="btn-submit"><svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"></polyline></svg> Simpan & Finalisasi</button></div>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>

<style>
.kp-finalisasi-nilai-container{--primary-gradient:linear-gradient(135deg,#667eea 0%,#764ba2 100%);--success-color:#16a34a;--danger-color:#dc2626;--text-primary:#1f2937;--text-secondary:#6b7280;--bg-light:#f9fafb;--border-color:#e5e7eb;--card-shadow:0 10px 30px rgba(0,0,0,.07);--border-radius:16px;font-family:Inter,sans-serif;color:var(--text-primary);max-width:1000px;margin:0 auto;padding:2rem 1rem}.kp-finalisasi-nilai-container svg{stroke-width:2;stroke-linecap:round;stroke-linejoin:round;fill:none;stroke:currentColor}.kp-finalisasi-nilai-container .form-hero-section{padding:3rem 2rem;background:var(--primary-gradient);border-radius:var(--border-radius);margin-bottom:2rem;color:#fff;text-align:center}.kp-finalisasi-nilai-container .form-hero-content{max-width:600px;margin:0 auto}.kp-finalisasi-nilai-container .form-hero-icon{width:60px;height:60px;background:rgba(255,255,255,.1);border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 1.5rem}.kp-finalisasi-nilai-container .form-hero-icon svg{width:28px;height:28px;stroke:#fff}.kp-finalisasi-nilai-container .form-hero-section h1{font-size:2.5rem;font-weight:700;margin-bottom:.5rem}.kp-finalisasi-nilai-container .form-hero-section p{font-size:1.1rem;opacity:.9;font-weight:300}.kp-finalisasi-nilai-container .form-wrapper{background-color:#fff;padding:2.5rem;border-radius:var(--border-radius);box-shadow:var(--card-shadow)}.kp-finalisasi-nilai-container .back-link{text-decoration:none;color:var(--text-secondary);font-weight:500;display:inline-block;margin-bottom:2rem;transition:color .2s ease}.kp-finalisasi-nilai-container .back-link:hover{color:var(--text-primary)}.kp-finalisasi-nilai-container .message{padding:1rem 1.5rem;margin-bottom:2rem;border-radius:12px;border:1px solid transparent;font-size:1em;text-align:center}.kp-finalisasi-nilai-container .message h4{margin-top:0}.kp-finalisasi-nilai-container .message.success{background-color:#dcfce7;color:#166534}.kp-finalisasi-nilai-container .message.error{background-color:#fee2e2;color:#991b1b}.info-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(350px,1fr));gap:1.5rem;margin-bottom:2.5rem}.info-card{background:var(--bg-light);border:1px solid var(--border-color);border-radius:var(--border-radius);overflow:hidden}.info-card-header{display:flex;align-items:center;gap:12px;padding:1rem 1.5rem;border-bottom:1px solid var(--border-color)}.info-card-header svg{width:20px;height:20px;color:#667eea}.info-card-header h3{margin:0;font-size:1.1rem;font-weight:600}.info-card-body{padding:1.5rem}.info-card-body .info-item{display:flex;justify-content:space-between;align-items:center;padding:.75rem 0;border-bottom:1px dashed var(--border-color)}.info-card-body .info-item:last-child{border-bottom:none}.info-item span{color:var(--text-secondary)}.info-item strong{font-weight:600;color:var(--text-primary)}.info-item em{color:var(--text-secondary);font-style:normal}.text-muted{color:var(--text-secondary)}.modern-form .form-step{margin-bottom:0;border:1px solid #f0f0f0;border-radius:12px;padding:1.5rem;background-color:#fff;box-shadow:0 4px 15px rgba(0,0,0,.03)}.form-step-header{display:flex;align-items:center;gap:1rem;margin-bottom:1.5rem}.form-step-icon{width:40px;height:40px;flex-shrink:0;background:var(--bg-light);border-radius:50%;display:flex;align-items:center;justify-content:center;color:#667eea}.form-step-icon svg{width:20px;height:20px;stroke:currentColor}.form-step-header h3{margin:0;font-weight:600}.form-group{margin-bottom:1.5rem}.form-group label{display:block;font-weight:500;margin-bottom:.5rem;font-size:.95rem}.form-group input,.form-group textarea{width:100%;padding:12px 15px;border:1px solid var(--border-color);border-radius:8px;font-size:1em;font-family:Inter,sans-serif;transition:all .2s ease;background-color:var(--bg-light)}.form-group input:focus,.form-group textarea:focus{border-color:#667eea;background-color:#fff;box-shadow:0 0 0 3px rgba(102,126,234,.2);outline:none}.form-group .readonly-input{background-color:#e9ecef;cursor:not-allowed}.form-group small{display:block;font-size:.85em;color:var(--text-secondary);margin-top:8px}.form-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:1.5rem}.checkbox-group{display:flex;align-items:center;gap:10px}.checkbox-group input[type=checkbox]{width:1.5em;height:1.5em;accent-color:var(--primary-color)}.checkbox-group label{margin-bottom:0}.form-actions{margin-top:2rem;text-align:right}.btn-submit{background:linear-gradient(135deg,#16a34a 0%,#15803d 100%);color:#fff;padding:14px 30px;font-size:1.1em;font-weight:600;border:none;border-radius:10px;display:inline-flex;align-items:center;gap:10px;cursor:pointer;transition:all .3s ease;box-shadow:0 4px 15px rgba(22,163,74,.3)}.btn-submit:hover:not([disabled]){transform:translateY(-3px);box-shadow:0 8px 25px rgba(22,163,74,.4)}.animate-on-scroll{opacity:0;transform:translateY(30px);transition:opacity .6s ease-out,transform .6s ease-out}.animate-on-scroll.is-visible{opacity:1;transform:translateY(0)}
</style>

<script>
function updateNilaiHuruf(nilaiAngka) {
    const fieldNilaiHuruf = document.getElementById('nilai_akhir_huruf');
    if (!fieldNilaiHuruf) return;
    const angka = parseFloat(nilaiAngka);
    if (isNaN(angka)) { fieldNilaiHuruf.value = ''; return; }
    if (angka >= 85) fieldNilaiHuruf.value = 'A'; else if (angka >= 80) fieldNilaiHuruf.value = 'A-';
    else if (angka >= 75) fieldNilaiHuruf.value = 'B+'; else if (angka >= 70) fieldNilaiHuruf.value = 'B';
    else if (angka >= 65) fieldNilaiHuruf.value = 'B-'; else if (angka >= 60) fieldNilaiHuruf.value = 'C+';
    else if (angka >= 55) fieldNilaiHuruf.value = 'C'; else if (angka >= 50) fieldNilaiHuruf.value = 'D';
    else fieldNilaiHuruf.value = 'E';
}
document.addEventListener('DOMContentLoaded', function() {
    const container = document.querySelector('.kp-finalisasi-nilai-container');
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
    
    const nilaiAngkaInput = document.getElementById('nilai_akhir_angka');
    if (nilaiAngkaInput && nilaiAngkaInput.value !== '') {
        const nilaiHurufInput = document.getElementById('nilai_akhir_huruf');
        if (nilaiHurufInput && nilaiHurufInput.value === '') {
            updateNilaiHuruf(nilaiAngkaInput.value);
        }
    }
});
</script>

<?php
require_once '../includes/footer.php';
if (isset($conn) && ($conn instanceof mysqli)) {
    $conn->close();
}
?>
