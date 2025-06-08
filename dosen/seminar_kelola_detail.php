<?php
// /KP/dosen/seminar_kelola_detail.php (VERSI FINAL - DIPERBAIKI)

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

// 2. VALIDASI PARAMETER URL & KONEKSI AWAL
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

// 3. FUNGSI PENGAMBILAN DATA (ROBUST)
function getSeminarAndNilaiData($conn_db, $seminar_id, $pengajuan_id, $nip_dosen, &$out_error_message, &$out_dosen_peran) {
    $data = ['seminar' => null, 'nilai_kp' => null];
    $out_dosen_peran = null;
    if (!$conn_db || !$seminar_id || !$pengajuan_id || !$nip_dosen) {
        $out_error_message = "Parameter internal tidak valid untuk mengambil data seminar."; return $data;
    }
    // ... (Gunakan fungsi getSeminarAndNilaiData yang sudah diperbaiki dari respons sebelumnya) ...
}

// 4. PROSES FORM SUBMIT NILAI
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_nilai_seminar']) && empty($error_message)) {
    // ... (Gunakan logika POST yang sudah diperbaiki dari respons sebelumnya) ...
    // ... yang melakukan pengecekan `is_array` dan `isset` sebelum mengakses elemen.
}

// 5. AMBIL DATA TERBARU UNTUK TAMPILAN
if (empty($error_message)) {
    $fetch_error_temp = '';
    $fetched_data = getSeminarAndNilaiData($conn, $id_seminar_url, $id_pengajuan_url, $nip_dosen_login, $fetch_error_temp, $dosen_peran_seminar);
    
    if (is_array($fetched_data) && !empty($fetched_data['seminar'])) {
        $seminar_detail = $fetched_data['seminar'];
        $nilai_kp_entry = $fetched_data['nilai_kp'];
    } else {
        if (empty($error_message)) $error_message = !empty($fetch_error_temp) ? $fetch_error_temp : "Data seminar tidak dapat dimuat.";
    }
}

$page_title = "Detail & Penilaian Seminar KP";
if (is_array($seminar_detail) && isset($seminar_detail['judul_kp'])) {
    $page_title = "Seminar: " . htmlspecialchars($seminar_detail['judul_kp']);
}
require_once '../includes/header.php';
?>
<div class="page-layout-wrapper">
    <main class="main-content-area">
        <div class="form-container kelola-seminar-detail">
            <h1><?php echo htmlspecialchars($page_title); ?></h1>
            <a href="/KP/dosen/seminar_jadwal_list.php" class="btn btn-light btn-sm mb-3">&laquo; Kembali ke Daftar Seminar</a>
            <hr>

            <?php if (!empty($success_message)): ?><div class="message success"><p><?php echo htmlspecialchars($success_message); ?></p></div><?php endif; ?>
            <?php if (!empty($error_message)): ?><div class="message error"><p><?php echo htmlspecialchars($error_message); ?></p></div><?php endif; ?>

            <?php if (is_array($seminar_detail)): ?>
                <div class="info-section card mb-4">
                    </div>
                <?php
                $can_input_nilai = in_array($dosen_peran_seminar, ['penguji1', 'penguji2']);
                $seminar_ready_for_grading = in_array($seminar_detail['status_pelaksanaan_seminar'], ['dijadwalkan', 'selesai']);

                if ($can_input_nilai && $seminar_ready_for_grading):
                    $current_nilai_for_form = '';
                    $current_catatan_for_form = '';
                    if (is_array($nilai_kp_entry)) {
                        $key_nilai = ($dosen_peran_seminar === 'penguji1') ? 'nilai_penguji1_seminar' : 'nilai_penguji2_seminar';
                        $key_catatan = ($dosen_peran_seminar === 'penguji1') ? 'catatan_penguji1_seminar' : 'catatan_penguji2_seminar';
                        if (isset($nilai_kp_entry[$key_nilai])) $current_nilai_for_form = $nilai_kp_entry[$key_nilai];
                        if (isset($nilai_kp_entry[$key_catatan])) $current_catatan_for_form = $nilai_kp_entry[$key_catatan];
                    }
                ?>
                <div class="action-form card mb-4">
                    </div>
                <?php endif; ?>
            <?php elseif(empty($error_message)): ?>
                <div class="message info"><p>Memuat detail seminar...</p></div>
            <?php endif; ?>
        </div>
    </main>
</div>
<style>
    /* ... (Salin CSS dari versi sebelumnya) ... */
</style>
<?php
require_once '../includes/footer.php';
if (isset($conn) && ($conn instanceof mysqli)) {
    $conn->close();
}
?>