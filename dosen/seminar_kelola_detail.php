<?php
// /KP/dosen/seminar_kelola_detail.php (VERSI LENGKAP & FINAL)

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// 1. OTENTIKASI DAN OTORISASI
require_once '../includes/auth_check.php';
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'dosen') {
    session_unset();
    session_destroy();
    header("Location: /KP/index.php?error=unauthorized_dosen");
    exit();
}

// --- Inisialisasi Variabel ---
$nip_dosen_login = $_SESSION['user_id'];
$id_seminar_url = null;
$id_pengajuan_url = null;
$seminar_detail = null;
$nilai_kp_entry = null;
$dosen_peran_seminar = null;
$error_message = '';
$success_message = '';

// 2. VALIDASI PARAMETER URL
if (isset($_GET['id_seminar']) && filter_var($_GET['id_seminar'], FILTER_VALIDATE_INT)) {
    $id_seminar_url = (int)$_GET['id_seminar'];
}
if (isset($_GET['id_pengajuan']) && filter_var($_GET['id_pengajuan'], FILTER_VALIDATE_INT)) {
    $id_pengajuan_url = (int)$_GET['id_pengajuan'];
}
if ($id_seminar_url === null || $id_pengajuan_url === null) {
    $error_message = "ID Seminar atau ID Pengajuan tidak valid atau tidak disertakan.";
}

require_once '../config/db_connect.php';
if (empty($error_message) && (!$conn || !($conn instanceof mysqli) || $conn->connect_error)) {
    $error_message = "Koneksi ke database gagal.";
}

// 3. FUNGSI PENGAMBILAN DATA (ROBUST VERSION)
function getSeminarAndNilaiData($conn_db, $seminar_id, $pengajuan_id, $nip_dosen, &$out_error_message, &$out_dosen_peran) {
    $data = ['seminar' => null, 'nilai_kp' => null];
    $out_dosen_peran = null;

    $sql_seminar = "SELECT 
                        sk.*, 
                        pk.judul_kp, 
                        pk.nim, 
                        pk.nip_dosen_pembimbing_kp, 
                        m.nama as nama_mahasiswa, 
                        dp_pembimbing.nama_dosen as nama_pembimbing, 
                        dp1.nama_dosen as nama_penguji1, 
                        dp2.nama_dosen as nama_penguji2
                    FROM seminar_kp sk
                    JOIN pengajuan_kp pk ON sk.id_pengajuan = pk.id_pengajuan
                    JOIN mahasiswa m ON pk.nim = m.nim
                    LEFT JOIN dosen_pembimbing dp_pembimbing ON pk.nip_dosen_pembimbing_kp = dp_pembimbing.nip
                    LEFT JOIN dosen_pembimbing dp1 ON sk.nip_dosen_penguji1 = dp1.nip
                    LEFT JOIN dosen_pembimbing dp2 ON sk.nip_dosen_penguji2 = dp2.nip
                    WHERE sk.id_seminar = ? AND sk.id_pengajuan = ?";
    
    $stmt_seminar = $conn_db->prepare($sql_seminar);
    if (!$stmt_seminar) {
        $out_error_message = "Gagal menyiapkan query seminar: " . $conn_db->error;
        return $data;
    }
    $stmt_seminar->bind_param("ii", $seminar_id, $pengajuan_id);
    $stmt_seminar->execute();
    $result_seminar = $stmt_seminar->get_result();

    if ($result_seminar->num_rows === 0) {
        $out_error_message = "Detail seminar tidak ditemukan.";
        return $data;
    }
    $seminar_data = $result_seminar->fetch_assoc();
    $data['seminar'] = $seminar_data;
    $stmt_seminar->close();

    // Tentukan peran dosen dan otorisasi (Sangat Penting)
    if (isset($seminar_data['nip_dosen_penguji1']) && $seminar_data['nip_dosen_penguji1'] == $nip_dosen) {
        $out_dosen_peran = 'penguji1';
    } elseif (isset($seminar_data['nip_dosen_penguji2']) && $seminar_data['nip_dosen_penguji2'] == $nip_dosen) {
        $out_dosen_peran = 'penguji2';
    } elseif (isset($seminar_data['nip_dosen_pembimbing_kp']) && $seminar_data['nip_dosen_pembimbing_kp'] == $nip_dosen) {
        $out_dosen_peran = 'pembimbing';
    } else {
        $out_error_message = "Anda tidak memiliki akses untuk melihat atau menilai seminar ini.";
        return null; 
    }

    // Ambil data nilai jika ada
    $sql_nilai = "SELECT * FROM nilai_kp WHERE id_pengajuan = ?";
    $stmt_nilai = $conn_db->prepare($sql_nilai);
    $stmt_nilai->bind_param("i", $pengajuan_id);
    $stmt_nilai->execute();
    $result_nilai = $stmt_nilai->get_result();
    if ($result_nilai->num_rows > 0) {
        $data['nilai_kp'] = $result_nilai->fetch_assoc();
    }
    $stmt_nilai->close();
    
    return $data;
}

// 4. PROSES FORM SUBMIT NILAI
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_nilai_seminar']) && empty($error_message)) {
    $nilai_input = $_POST['nilai_seminar'] ?? null;
    $catatan_input = trim($_POST['catatan_seminar'] ?? '');
    
    if ($nilai_input === null || !is_numeric($nilai_input) || $nilai_input < 0 || $nilai_input > 100) {
        $error_message = "Nilai harus berupa angka antara 0 dan 100.";
    } else {
        $temp_err = '';
        getSeminarAndNilaiData($conn, $id_seminar_url, $id_pengajuan_url, $nip_dosen_login, $temp_err, $dosen_peran_seminar);

        if ($dosen_peran_seminar === 'penguji1' || $dosen_peran_seminar === 'penguji2') {
            $nilai_col = ($dosen_peran_seminar === 'penguji1') ? 'nilai_penguji1_seminar' : 'nilai_penguji2_seminar';
            $catatan_col = ($dosen_peran_seminar === 'penguji1') ? 'catatan_penguji1_seminar' : 'catatan_penguji2_seminar';
            
            $stmt_check = $conn->prepare("SELECT id_nilai FROM nilai_kp WHERE id_pengajuan = ?");
            $stmt_check->bind_param("i", $id_pengajuan_url);
            $stmt_check->execute();
            $id_nilai_exists = $stmt_check->get_result()->fetch_assoc()['id_nilai'] ?? null;
            $stmt_check->close();

            if ($id_nilai_exists) {
                $sql = "UPDATE nilai_kp SET {$nilai_col} = ?, {$catatan_col} = ? WHERE id_nilai = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("dsi", $nilai_input, $catatan_input, $id_nilai_exists);
            } else {
                $sql = "INSERT INTO nilai_kp (id_pengajuan, {$nilai_col}, {$catatan_col}) VALUES (?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ids", $id_pengajuan_url, $nilai_input, $catatan_input);
            }
            
            if ($stmt && $stmt->execute()) {
                $success_message = "Nilai seminar berhasil disimpan.";
            } else {
                $error_message = "Gagal menyimpan nilai: " . ($stmt ? $stmt->error : $conn->error);
            }
            if ($stmt) $stmt->close();
        } else {
            $error_message = "Anda tidak dapat memberikan nilai karena peran Anda bukan sebagai Penguji.";
        }
    }
}

// 5. AMBIL DATA TERBARU UNTUK TAMPILAN
if (empty($error_message)) {
    $fetch_error_temp = '';
    $fetched_data = getSeminarAndNilaiData($conn, $id_seminar_url, $id_pengajuan_url, $nip_dosen_login, $fetch_error_temp, $dosen_peran_seminar);
    
    if ($fetched_data === null) {
        $error_message = $fetch_error_temp;
    } elseif (is_array($fetched_data) && !empty($fetched_data['seminar'])) {
        $seminar_detail = $fetched_data['seminar'];
        $nilai_kp_entry = $fetched_data['nilai_kp'];
    } else {
        if(empty($error_message)) $error_message = "Gagal memuat data seminar detail.";
    }
}

$page_title = "Detail & Penilaian Seminar KP";
if (is_array($seminar_detail) && isset($seminar_detail['judul_kp'])) {
    $page_title = "Seminar: " . htmlspecialchars($seminar_detail['judul_kp']);
}
require_once '../includes/header.php';
?>

<div class="main-content-dark">
    <div class="detail-container">
        <div class="detail-header">
            <h1><i class="icon-seminar"></i><?php echo htmlspecialchars($page_title); ?></h1>
            <a href="/KP/dosen/seminar_jadwal_list.php" class="btn btn-secondary btn-sm"><i class="icon-back"></i> Kembali</a>
        </div>
        
        <?php if (!empty($success_message)): ?><div class="message success"><p><?php echo htmlspecialchars($success_message); ?></p></div><?php endif; ?>
        <?php if (!empty($error_message)): ?><div class="message error"><p><?php echo htmlspecialchars($error_message); ?></p></div><?php endif; ?>

        <?php if (is_array($seminar_detail)): ?>
            <div class="content-grid">
                <!-- Kolom Kiri: Informasi Seminar -->
                <div class="info-column">
                    <div class="info-card">
                        <div class="card-header"><h3><i class="icon-info"></i>Informasi Seminar</h3></div>
                        <div class="card-body">
                            <div class="detail-item"><span>Mahasiswa</span><p><?php echo htmlspecialchars($seminar_detail['nama_mahasiswa']); ?> (<?php echo htmlspecialchars($seminar_detail['nim']); ?>)</p></div>
                            <div class="detail-item"><span>Judul KP</span><p><?php echo htmlspecialchars($seminar_detail['judul_kp']); ?></p></div>
                            <div class="detail-item"><span>Jadwal</span><p><?php echo $seminar_detail['tanggal_seminar'] ? date("l, d F Y, H:i", strtotime($seminar_detail['tanggal_seminar'])) . " WIB" : "Belum diatur"; ?></p></div>
                            <div class="detail-item"><span>Tempat</span><p><?php echo htmlspecialchars($seminar_detail['tempat_seminar'] ?: '-'); ?></p></div>
                            <div class="detail-item"><span>Status</span><p><span class="status-badge status-<?php echo strtolower(str_replace('_', '-', $seminar_detail['status_pelaksanaan_seminar'])); ?>"><?php echo ucfirst(str_replace('_', ' ', htmlspecialchars($seminar_detail['status_pelaksanaan_seminar']))); ?></span></p></div>
                            <div class="detail-item"><span>Peran Anda</span><p><span class="peran-badge peran-<?php echo strtolower(str_replace(' ', '-', $dosen_peran_seminar ?? '')); ?>"><?php echo ucfirst($dosen_peran_seminar ?? 'Tidak Terdefinisi'); ?></span></p></div>
                        </div>
                    </div>
                     <div class="info-card">
                        <div class="card-header"><h3><i class="icon-team"></i>Tim Dosen</h3></div>
                        <div class="card-body">
                             <div class="detail-item"><span>Pembimbing</span><p><?php echo htmlspecialchars($seminar_detail['nama_pembimbing'] ?? '-'); ?></p></div>
                             <div class="detail-item"><span>Penguji 1</span><p><?php echo htmlspecialchars($seminar_detail['nama_penguji1'] ?? '-'); ?></p></div>
                             <div class="detail-item"><span>Penguji 2</span><p><?php echo htmlspecialchars($seminar_detail['nama_penguji2'] ?? '-'); ?></p></div>
                        </div>
                    </div>
                </div>

                <!-- Kolom Kanan: Form Penilaian & Nilai Lain -->
                <div class="action-column">
                    <?php
                        $can_input_nilai = in_array($dosen_peran_seminar, ['penguji1', 'penguji2']);
                        $seminar_ready_for_grading = in_array($seminar_detail['status_pelaksanaan_seminar'], ['dijadwalkan', 'selesai']);

                        if ($can_input_nilai && $seminar_ready_for_grading):
                            $key_nilai = ($dosen_peran_seminar === 'penguji1') ? 'nilai_penguji1_seminar' : 'nilai_penguji2_seminar';
                            $key_catatan = ($dosen_peran_seminar === 'penguji1') ? 'catatan_penguji1_seminar' : 'catatan_penguji2_seminar';
                            
                            $current_nilai_for_form = (is_array($nilai_kp_entry) && isset($nilai_kp_entry[$key_nilai])) ? $nilai_kp_entry[$key_nilai] : '';
                            $current_catatan_for_form = (is_array($nilai_kp_entry) && isset($nilai_kp_entry[$key_catatan])) ? $nilai_kp_entry[$key_catatan] : '';
                    ?>
                    <div class="form-card">
                        <div class="card-header"><h3><i class="icon-grade"></i>Input Nilai Seminar (Peran: <?php echo ucfirst($dosen_peran_seminar); ?>)</h3></div>
                        <div class="card-body">
                             <form action="" method="POST">
                                <div class="form-group">
                                    <label for="nilai_seminar">Nilai (0-100)</label>
                                    <input type="number" id="nilai_seminar" name="nilai_seminar" class="form-control" min="0" max="100" step="0.01" value="<?php echo htmlspecialchars($current_nilai_for_form); ?>" required>
                                </div>
                                <div class="form-group">
                                    <label for="catatan_seminar">Catatan/Revisi</label>
                                    <textarea id="catatan_seminar" name="catatan_seminar" class="form-control" rows="5" placeholder="Berikan catatan atau revisi untuk mahasiswa..."><?php echo htmlspecialchars($current_catatan_for_form); ?></textarea>
                                </div>
                                <button type="submit" name="submit_nilai_seminar" class="btn btn-primary">Simpan Nilai</button>
                             </form>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <div class="info-card">
                         <div class="card-header"><h3><i class="icon-summary"></i>Rekapitulasi Nilai</h3></div>
                         <div class="card-body">
                             <div class="detail-item"><span>Nilai Pembimbing</span><p><?php echo (is_array($nilai_kp_entry) && isset($nilai_kp_entry['nilai_dosen_pembimbing'])) ? '<b>'.htmlspecialchars($nilai_kp_entry['nilai_dosen_pembimbing']).'</b>' : '<i>Belum ada</i>'; ?></p></div>
                             <div class="detail-item"><span>Nilai Lapangan</span><p><?php echo (is_array($nilai_kp_entry) && isset($nilai_kp_entry['nilai_pembimbing_lapangan'])) ? htmlspecialchars($nilai_kp_entry['nilai_pembimbing_lapangan']) : '<i>Belum ada</i>'; ?></p></div>
                             <div class="detail-item"><span>Nilai Penguji 1</span><p><?php echo (is_array($nilai_kp_entry) && isset($nilai_kp_entry['nilai_penguji1_seminar'])) ? '<b>'.htmlspecialchars($nilai_kp_entry['nilai_penguji1_seminar']).'</b>' : '<i>Belum ada</i>'; ?></p></div>
                             <div class="detail-item"><span>Nilai Penguji 2</span><p><?php echo (is_array($nilai_kp_entry) && isset($nilai_kp_entry['nilai_penguji2_seminar'])) ? '<b>'.htmlspecialchars($nilai_kp_entry['nilai_penguji2_seminar']).'</b>' : '<i>Belum ada</i>'; ?></p></div>
                         </div>
                    </div>
                </div>
            </div>
        <?php elseif(empty($error_message)): ?>
            <div class="message info"><p>Memuat detail seminar...</p></div>
        <?php endif; ?>
    </div>
</div>

<style>
/* Ikon */
.icon-seminar::before { content: "🏛️"; margin-right: 12px; }
.icon-back::before { content: "⬅️"; margin-right: 6px; }
.icon-info::before, .icon-team::before, .icon-grade::before, .icon-summary::before { margin-right: 10px; }
.icon-info::before { content: "ℹ️"; }
.icon-team::before { content: "👥"; }
.icon-grade::before { content: "📝"; }
.icon-summary::before { content: "📊"; }

/* Variabel Warna Mode Gelap */
:root {
    --primary-color: #3b82f6; --primary-hover: #2563eb; --success-color: #10b981;
    --secondary-color: #94a3b8; --text-color: #e2e8f0; --border-color: #334155;
    --bg-dark: #0f172a; --bg-card: #1e293b; --bg-hover: #334155;
    --card-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.3), 0 2px 4px -1px rgba(0, 0, 0, 0.2);
    --border-radius: 12px;
}

/* Layout Utama */
.main-content-dark { background-color: var(--bg-dark); padding: 2rem; color: var(--text-color); min-height: 100vh; }
.detail-container { max-width: 1400px; margin: 0 auto; }
.detail-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; padding-bottom: 1.5rem; border-bottom: 1px solid var(--border-color); }
.detail-header h1 { font-size: 2em; margin: 0; display: flex; align-items: center; }

/* Grid Layout */
.content-grid { display: grid; grid-template-columns: 1fr; gap: 2rem; }
@media (min-width: 992px) { .content-grid { grid-template-columns: 1fr 1fr; } }

/* Kartu Informasi dan Form */
.info-card, .form-card { background-color: var(--bg-card); border: 1px solid var(--border-color); border-radius: var(--border-radius); margin-bottom: 2rem; box-shadow: var(--card-shadow); }
.card-header { background-color: var(--bg-dark); padding: 1rem 1.5rem; border-bottom: 1px solid var(--border-color); }
.card-header h3 { font-size: 1.2em; margin: 0; display: flex; align-items: center; }
.card-body { padding: 1.5rem; }

/* Detail Item di dalam Kartu */
.detail-item { display: flex; justify-content: space-between; align-items: flex-start; gap: 1rem; padding: 0.75rem 0; border-bottom: 1px solid var(--border-color); }
.detail-item:last-child { border-bottom: none; }
.detail-item span { color: var(--secondary-color); font-weight: 500; }
.detail-item p { margin: 0; font-weight: 600; text-align: right; word-break: break-word; }

/* Form Elements */
.form-group { margin-bottom: 1.5rem; }
.form-group label { display: block; margin-bottom: 0.5rem; font-weight: 500; }
.form-control { width: 100%; padding: 0.75rem 1rem; background-color: var(--bg-dark); border: 2px solid var(--border-color); color: var(--text-color); border-radius: 8px; transition: border-color 0.2s; }
.form-control:focus { outline: none; border-color: var(--primary-color); }
textarea.form-control { resize: vertical; min-height: 120px; }

/* Tombol dan Pesan */
.btn { text-decoration: none; padding: 10px 20px; border-radius: 8px; font-weight: 600; transition: background-color 0.2s; border: none; cursor: pointer; display: inline-flex; align-items: center;}
.btn-primary { background-color: var(--primary-color); color: white; width: 100%; justify-content: center; }
.btn-primary:hover { background-color: var(--primary-hover); }
.btn-secondary { background-color: var(--bg-hover); color: var(--text-color); }
.message { padding: 1rem 1.5rem; border-radius: 8px; margin-bottom: 1.5rem; }
.message.info { background-color: rgba(59, 130, 246, 0.1); color: #93c5fd; }
.message.success { background-color: rgba(16, 185, 129, 0.1); color: #6ee7b7; }
.message.error { background-color: rgba(239, 68, 68, 0.1); color: #fca5a5; }

/* Badge (re-use from list) */
.status-badge, .peran-badge { padding: 6px 12px; border-radius: 20px; font-size: 0.85em; font-weight: 600; text-transform: capitalize; display: inline-block; }
.peran-pembimbing { background: rgba(16, 185, 129, 0.2); color: #6ee7b7; }
.peran-penguji-1, .peran-penguji-2 { background: rgba(59, 130, 246, 0.2); color: #93c5fd; }
.status-dijadwalkan { background: rgba(14, 165, 233, 0.2); color: #7dd3fc; }
.status-selesai { background: rgba(59, 130, 246, 0.2); color: #93c5fd; }
.status-dibatalkan { background: rgba(148, 163, 184, 0.2); color: #94a3b8; }
.status-ditunda { background: rgba(245, 158, 11, 0.2); color: #fcd34d; }
</style>

<?php
require_once '../includes/footer.php';
if (isset($conn) && ($conn instanceof mysqli)) {
    $conn->close();
}
?>
