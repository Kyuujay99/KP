<?php
// /KP/dosen/seminar_kelola_detail.php

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

$nip_dosen_login = $_SESSION['user_id'];
$id_seminar_url = null;
$id_pengajuan_url = null;

$seminar_detail = null;
$nilai_kp_entry = null;
$dosen_peran_seminar = null; // penguji1, penguji2, pembimbing, atau null/tidak_terkait

$error_message = '';
$success_message = '';

// 2. VALIDASI PARAMETER URL
if (isset($_GET['id_seminar']) && filter_var($_GET['id_seminar'], FILTER_VALIDATE_INT)) {
    $id_seminar_url = (int)$_GET['id_seminar'];
} else {
    $error_message = "ID Seminar tidak valid atau tidak ditemukan dalam URL.";
}
if (isset($_GET['id_pengajuan']) && filter_var($_GET['id_pengajuan'], FILTER_VALIDATE_INT)) {
    $id_pengajuan_url = (int)$_GET['id_pengajuan'];
} else {
    if (empty($error_message)) $error_message = "ID Pengajuan tidak disertakan atau tidak valid dalam URL.";
}

// Sertakan file koneksi database
require_once '../config/db_connect.php';

// 3. FUNGSI UNTUK MENGAMBIL DATA DETAIL SEMINAR DAN NILAI KP TERKAIT
function getSeminarAndNilaiData($conn_db, $seminar_id, $pengajuan_id, $nip_dosen, &$out_error_message, &$out_dosen_peran) {
    // Inisialisasi $data sebagai array dengan kunci default null
    $data = ['seminar' => null, 'nilai_kp' => null];
    $out_dosen_peran = null; // Inisialisasi peran

    if (!$conn_db || !($conn_db instanceof mysqli)) {
        $out_error_message = "Koneksi database tidak valid saat mengambil data seminar.";
        return $data;
    }

    // Ambil detail seminar dan pastikan dosen ini terlibat (pembimbing atau penguji)
    $sql_seminar = "SELECT
                        sk.id_seminar, sk.id_pengajuan, pk.judul_kp, pk.nim AS nim_mahasiswa_kp,
                        m.nama AS nama_mahasiswa,
                        sk.tanggal_seminar, sk.tempat_seminar,
                        sk.nip_dosen_penguji1, dp1.nama_dosen AS nama_penguji1,
                        sk.nip_dosen_penguji2, dp2.nama_dosen AS nama_penguji2,
                        pk.nip_dosen_pembimbing_kp,
                        sk.status_kelayakan_seminar, sk.status_pelaksanaan_seminar, sk.catatan_hasil_seminar
                    FROM seminar_kp sk
                    JOIN pengajuan_kp pk ON sk.id_pengajuan = pk.id_pengajuan
                    JOIN mahasiswa m ON pk.nim = m.nim
                    LEFT JOIN dosen_pembimbing dp1 ON sk.nip_dosen_penguji1 = dp1.nip
                    LEFT JOIN dosen_pembimbing dp2 ON sk.nip_dosen_penguji2 = dp2.nip
                    WHERE sk.id_seminar = ? AND sk.id_pengajuan = ?
                      AND (pk.nip_dosen_pembimbing_kp = ? OR sk.nip_dosen_penguji1 = ? OR sk.nip_dosen_penguji2 = ?)";
    $stmt_seminar = $conn_db->prepare($sql_seminar);

    if ($stmt_seminar) {
        $stmt_seminar->bind_param("iisss", $seminar_id, $pengajuan_id, $nip_dosen, $nip_dosen, $nip_dosen);
        $stmt_seminar->execute();
        $result_seminar = $stmt_seminar->get_result();

        if ($result_seminar->num_rows === 1) {
            $seminar_data_row = $result_seminar->fetch_assoc();
            $data['seminar'] = $seminar_data_row; // Simpan data seminar

            // Tentukan peran dosen untuk seminar ini
            if ($seminar_data_row['nip_dosen_penguji1'] == $nip_dosen) {
                $out_dosen_peran = 'penguji1';
            } elseif ($seminar_data_row['nip_dosen_penguji2'] == $nip_dosen) {
                $out_dosen_peran = 'penguji2';
            } elseif ($seminar_data_row['nip_dosen_pembimbing_kp'] == $nip_dosen) {
                $out_dosen_peran = 'pembimbing';
            } else {
                // Ini seharusnya tidak tercapai jika WHERE clause di atas sudah benar
                $out_dosen_peran = 'tidak_terkait_langsung';
                $out_error_message = "Anda tidak memiliki peran langsung (pembimbing/penguji) pada seminar ini.";
                // $data['seminar'] = null; // Batalkan data seminar jika peran tidak valid
                return $data; // Kembalikan $data dengan seminar=null jika peran tidak valid
            }

            // Ambil data nilai_kp jika ada untuk pengajuan ini
            $sql_nilai = "SELECT id_nilai, nilai_pembimbing_lapangan, catatan_pembimbing_lapangan, 
                                 nilai_dosen_pembimbing, catatan_dosen_pembimbing,
                                 nilai_penguji1_seminar, catatan_penguji1_seminar,
                                 nilai_penguji2_seminar, catatan_penguji2_seminar,
                                 nilai_akhir_angka, nilai_akhir_huruf, is_final
                          FROM nilai_kp
                          WHERE id_pengajuan = ?";
            $stmt_nilai = $conn_db->prepare($sql_nilai);
            if ($stmt_nilai) {
                $stmt_nilai->bind_param("i", $pengajuan_id);
                $stmt_nilai->execute();
                $result_nilai = $stmt_nilai->get_result();
                if ($result_nilai->num_rows === 1) {
                    $data['nilai_kp'] = $result_nilai->fetch_assoc();
                } // $data['nilai_kp'] akan tetap null jika tidak ada hasil
                $stmt_nilai->close();
            } else {
                $out_error_message .= (empty($out_error_message) ? "" : "<br>") . "Gagal mengambil data nilai seminar yang ada.";
            }
        } else {
            if (empty($out_error_message)) $out_error_message = "Detail seminar tidak ditemukan atau Anda tidak berhak mengaksesnya (ID Seminar: $seminar_id, ID Pengajuan: $pengajuan_id).";
        }
        $stmt_seminar->close();
    } else {
        $out_error_message = "Gagal menyiapkan query detail seminar: " . (($conn_db->error) ? htmlspecialchars($conn_db->error) : "DB Error.");
    }
    return $data; // Selalu return array $data
}


// 4. PROSES INPUT NILAI SEMINAR JIKA FORM DISUBMIT
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_nilai_seminar']) && $id_seminar_url && $id_pengajuan_url && empty($error_message)) {
    $nilai_seminar_input = isset($_POST['nilai_seminar']) && is_numeric($_POST['nilai_seminar']) ? (float)$_POST['nilai_seminar'] : null;
    $catatan_seminar_input = trim($_POST['catatan_seminar']);
    $peran_dosen_form = $_POST['peran_dosen_form'];

    if ($nilai_seminar_input === null || $nilai_seminar_input < 0 || $nilai_seminar_input > 100) {
        $error_message = "Nilai seminar harus berupa angka antara 0 dan 100.";
    } elseif (empty($peran_dosen_form) || !in_array($peran_dosen_form, ['penguji1', 'penguji2'])) {
        $error_message = "Peran penguji tidak valid untuk submit nilai.";
    }

    if (empty($error_message) && $conn && ($conn instanceof mysqli)) {
        // Ambil dulu $dosen_peran_seminar untuk memastikan dosen yang submit adalah penguji yang sah
        // Ini bisa juga diambil ulang dari DB untuk keamanan, tapi untuk sederhana kita pakai yang sudah ada jika $seminar_detail terisi
        // Untuk memastikan $dosen_peran_seminar terisi sebelum POST, kita panggil getSeminarAndNilaiData jika belum
        if ($seminar_detail === null) { // Jika $seminar_detail belum terisi (misalnya karena ini POST request pertama)
             $temp_error = ''; // Variabel error sementara
             $initial_data = getSeminarAndNilaiData($conn, $id_seminar_url, $id_pengajuan_url, $nip_dosen_login, $temp_error, $dosen_peran_seminar);
             if ($temp_error) $error_message = $temp_error; // Tangkap error jika ada saat fetch
             $seminar_detail = $initial_data['seminar']; // Update seminar_detail
             // $nilai_kp_entry juga bisa diupdate jika perlu
        }

        if ($peran_dosen_form !== $dosen_peran_seminar) {
             $error_message = "Anda mencoba submit nilai untuk peran penguji yang tidak sesuai.";
        }


        if (empty($error_message)) {
            $sql_cek_nilai = "SELECT id_nilai FROM nilai_kp WHERE id_pengajuan = ?";
            $stmt_cek = $conn->prepare($sql_cek_nilai);
            $stmt_cek->bind_param("i", $id_pengajuan_url);
            $stmt_cek->execute();
            $result_cek = $stmt_cek->get_result();
            $id_nilai_existing = null;
            if ($result_cek->num_rows === 1) {
                $id_nilai_existing = $result_cek->fetch_assoc()['id_nilai'];
            }
            $stmt_cek->close();

            $conn->begin_transaction();
            try {
                if ($id_nilai_existing) { // UPDATE
                    $sql_update_nilai = "UPDATE nilai_kp SET ";
                    $field_nilai_db = ($peran_dosen_form === 'penguji1') ? "nilai_penguji1_seminar" : "nilai_penguji2_seminar";
                    $field_catatan_db = ($peran_dosen_form === 'penguji1') ? "catatan_penguji1_seminar" : "catatan_penguji2_seminar";
                    $sql_update_nilai .= "$field_nilai_db = ?, $field_catatan_db = ? WHERE id_nilai = ?";
                    
                    $stmt_action_nilai = $conn->prepare($sql_update_nilai);
                    if(!$stmt_action_nilai) throw new Exception("Prepare update nilai gagal: ".$conn->error);
                    $stmt_action_nilai->bind_param("dsi", $nilai_seminar_input, $catatan_seminar_input, $id_nilai_existing);
                } else { // INSERT
                    $sql_insert_nilai = "INSERT INTO nilai_kp (id_pengajuan, ";
                    $field_nilai_db = ($peran_dosen_form === 'penguji1') ? "nilai_penguji1_seminar" : "nilai_penguji2_seminar";
                    $field_catatan_db = ($peran_dosen_form === 'penguji1') ? "catatan_penguji1_seminar" : "catatan_penguji2_seminar";
                    $sql_insert_nilai .= "$field_nilai_db, $field_catatan_db) VALUES (?, ?, ?)";
                    
                    $stmt_action_nilai = $conn->prepare($sql_insert_nilai);
                    if(!$stmt_action_nilai) throw new Exception("Prepare insert nilai gagal: ".$conn->error);
                    $stmt_action_nilai->bind_param("ids", $id_pengajuan_url, $nilai_seminar_input, $catatan_seminar_input);
                }

                if (!$stmt_action_nilai->execute()) {
                    throw new Exception("Eksekusi simpan nilai gagal: " . $stmt_action_nilai->error);
                }
                $affected_rows = $stmt_action_nilai->affected_rows;
                $stmt_action_nilai->close();
                
                $conn->commit();
                if ($affected_rows > 0) {
                    $success_message = "Nilai dan catatan seminar berhasil disimpan.";
                } else {
                    // Jika tidak ada baris terpengaruh, bisa jadi data sama atau kondisi WHERE tidak terpenuhi saat UPDATE
                    $success_message = "Data nilai seminar telah dikirim (tidak ada perubahan atau data sama).";
                }

            } catch (Exception $e) {
                $conn->rollback();
                $error_message = "Gagal menyimpan nilai seminar: " . htmlspecialchars($e->getMessage());
            }
        }
    }
}


// Selalu ambil data terbaru untuk ditampilkan, terutama setelah POST
// $error_message_fetch akan menampung error spesifik dari proses fetch ini
$error_message_fetch = ''; 
if ($id_seminar_url && $id_pengajuan_url && $conn && ($conn instanceof mysqli)) {
    // Jika $error_message sudah ada dari proses POST, jangan timpa dengan error fetch jika fetch berhasil
    // Tapi jika fetch gagal, $error_message_fetch akan berisi pesan baru.
    $fetched_data = getSeminarAndNilaiData($conn, $id_seminar_url, $id_pengajuan_url, $nip_dosen_login, $error_message_fetch, $dosen_peran_seminar);
    
    if ($fetched_data && $fetched_data['seminar']) {
        $seminar_detail = $fetched_data['seminar'];
        $nilai_kp_entry = $fetched_data['nilai_kp']; // Bisa null jika belum ada
    } else {
        // Jika seminar detail tidak bisa diambil dan $error_message utama masih kosong, set $error_message
        if (empty($error_message) && !empty($error_message_fetch)) {
            $error_message = $error_message_fetch;
        } elseif (empty($error_message) && !$fetched_data['seminar']) {
            $error_message = "Data seminar tidak dapat dimuat sepenuhnya.";
        }
    }
} elseif (empty($error_message)) { // Jika ID tidak ada atau koneksi gagal dari awal
    $error_message = "Parameter URL tidak lengkap atau koneksi database bermasalah.";
}


// Opsi status pelaksanaan seminar (jika admin/koordinator bisa edit ini)
$opsi_status_pelaksanaan_seminar = [
    'dijadwalkan' => 'Dijadwalkan',
    'selesai' => 'Selesai Dilaksanakan',
    'dibatalkan' => 'Dibatalkan',
    'ditunda' => 'Ditunda'
];

$page_title = "Detail & Penilaian Seminar KP";
if ($seminar_detail && isset($seminar_detail['judul_kp'])) {
    $page_title = "Seminar: " . htmlspecialchars($seminar_detail['judul_kp']);
}
require_once '../includes/header.php';
?>

<div class="page-layout-wrapper">
    <?php require_once '../includes/sidebar_dosen.php'; ?>

    <main class="main-content-area">
        <div class="form-container kelola-seminar-detail">
            <h1><?php echo htmlspecialchars($page_title); ?></h1>
            <a href="/KP/dosen/seminar_jadwal_list.php" class="btn btn-light btn-sm mb-3">&laquo; Kembali ke Daftar Seminar</a>
            <hr>

            <?php if (!empty($success_message)): ?>
                <div class="message success"><p><?php echo htmlspecialchars($success_message); ?></p></div>
            <?php endif; ?>
            <?php if (!empty($error_message)): ?>
                <div class="message error"><p><?php echo htmlspecialchars($error_message); ?></p></div>
            <?php endif; ?>

            <?php if ($seminar_detail): ?>
                <div class="info-section card mb-4">
                    <div class="card-header"><h3>Informasi Seminar & Mahasiswa</h3></div>
                    <div class="card-body">
                        <dl>
                            <dt>Judul KP:</dt><dd><strong><?php echo htmlspecialchars($seminar_detail['judul_kp']); ?></strong></dd>
                            <dt>Mahasiswa:</dt><dd><?php echo htmlspecialchars($seminar_detail['nama_mahasiswa']); ?> (NIM: <?php echo htmlspecialchars($seminar_detail['nim_mahasiswa_kp']); ?>)</dd>
                            <dt>ID Pengajuan KP:</dt><dd><?php echo $seminar_detail['id_pengajuan']; ?></dd>
                            <dt>ID Seminar:</dt><dd><?php echo $seminar_detail['id_seminar']; ?></dd>
                            <hr style="margin:10px 0;">
                            <dt>Jadwal Seminar:</dt>
                            <dd><?php echo $seminar_detail['tanggal_seminar'] ? date("d F Y, H:i", strtotime($seminar_detail['tanggal_seminar'])) : '<em>Belum dijadwalkan</em>'; ?></dd>
                            <dt>Tempat Seminar:</dt><dd><?php echo $seminar_detail['tempat_seminar'] ? htmlspecialchars($seminar_detail['tempat_seminar']) : '<em>-</em>'; ?></dd>
                            <dt>Status Kelayakan:</dt>
                            <dd><span class="status-seminar status-kelayakan-<?php echo strtolower(str_replace('_', '-', $seminar_detail['status_kelayakan_seminar'])); ?>"><?php echo ucfirst(str_replace('_', ' ', htmlspecialchars($seminar_detail['status_kelayakan_seminar']))); ?></span></dd>
                            <dt>Status Pelaksanaan:</dt>
                            <dd><span class="status-seminar status-pelaksanaan-<?php echo strtolower(str_replace('_', '-', $seminar_detail['status_pelaksanaan_seminar'])); ?>"><?php echo ucfirst(str_replace('_', ' ', htmlspecialchars($seminar_detail['status_pelaksanaan_seminar']))); ?></span></dd>
                            <dt>Dosen Penguji 1:</dt><dd><?php echo $seminar_detail['nama_penguji1'] ? htmlspecialchars($seminar_detail['nama_penguji1']) . ($seminar_detail['nip_dosen_penguji1'] == $nip_dosen_login ? ' <strong>(Anda)</strong>' : '') : '<em>-</em>'; ?></dd>
                            <dt>Dosen Penguji 2:</dt><dd><?php echo $seminar_detail['nama_penguji2'] ? htmlspecialchars($seminar_detail['nama_penguji2']) . ($seminar_detail['nip_dosen_penguji2'] == $nip_dosen_login ? ' <strong>(Anda)</strong>' : '') : '<em>-</em>'; ?></dd>
                            <?php if(!empty($seminar_detail['catatan_hasil_seminar'])): ?>
                            <dt>Catatan Hasil Seminar (Umum):</dt>
                            <dd class="catatan"><?php echo nl2br(htmlspecialchars($seminar_detail['catatan_hasil_seminar'])); ?></dd>
                            <?php endif; ?>
                        </dl>
                    </div>
                </div>

                <?php
                $can_input_nilai = false;
                $current_nilai_for_form = ''; // Nilai yang akan ditampilkan di form
                $current_catatan_for_form = ''; // Catatan yang akan ditampilkan di form

                if ($dosen_peran_seminar === 'penguji1') {
                    $can_input_nilai = true;
                    if($nilai_kp_entry && isset($nilai_kp_entry['nilai_penguji1_seminar'])) {
                        $current_nilai_for_form = $nilai_kp_entry['nilai_penguji1_seminar'];
                    }
                    if($nilai_kp_entry && isset($nilai_kp_entry['catatan_penguji1_seminar'])) {
                        $current_catatan_for_form = $nilai_kp_entry['catatan_penguji1_seminar'];
                    }
                } elseif ($dosen_peran_seminar === 'penguji2') {
                    $can_input_nilai = true;
                    if($nilai_kp_entry && isset($nilai_kp_entry['nilai_penguji2_seminar'])) {
                        $current_nilai_for_form = $nilai_kp_entry['nilai_penguji2_seminar'];
                    }
                    if($nilai_kp_entry && isset($nilai_kp_entry['catatan_penguji2_seminar'])) {
                        $current_catatan_for_form = $nilai_kp_entry['catatan_penguji2_seminar'];
                    }
                }

                $seminar_ready_for_grading = $seminar_detail && in_array($seminar_detail['status_pelaksanaan_seminar'], ['dijadwalkan', 'selesai']);

                if ($can_input_nilai && $seminar_ready_for_grading):
                ?>
                <div class="action-form card mb-4">
                    <div class="card-header"><h3><i class="icon-pencil"></i> Input Nilai & Catatan Seminar (Sebagai <?php echo ($dosen_peran_seminar == 'penguji1' ? 'Penguji 1' : 'Penguji 2'); ?>)</h3></div>
                    <div class="card-body">
                        <form action="/KP/dosen/seminar_kelola_detail.php?id_seminar=<?php echo $id_seminar_url; ?>&id_pengajuan=<?php echo $id_pengajuan_url; ?>" method="POST">
                            <input type="hidden" name="id_seminar" value="<?php echo $id_seminar_url; ?>">
                            <input type="hidden" name="id_pengajuan" value="<?php echo $id_pengajuan_url; ?>">
                            <input type="hidden" name="peran_dosen_form" value="<?php echo $dosen_peran_seminar; ?>">

                            <div class="form-group">
                                <label for="nilai_seminar">Nilai Seminar (0-100) (*):</label>
                                <input type="number" id="nilai_seminar" name="nilai_seminar" class="form-control"
                                       min="0" max="100" step="0.01"
                                       value="<?php echo htmlspecialchars($current_nilai_for_form ?: (isset($_POST['nilai_seminar']) ? $_POST['nilai_seminar'] : '')); ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="catatan_seminar">Catatan Seminar (untuk mahasiswa):</label>
                                <textarea id="catatan_seminar" name="catatan_seminar" class="form-control" rows="5" placeholder="Berikan feedback, poin revisi, atau catatan lainnya..."><?php echo htmlspecialchars($current_catatan_for_form ?: (isset($_POST['catatan_seminar']) ? $_POST['catatan_seminar'] : '')); ?></textarea>
                            </div>
                            <div class="form-actions">
                                <button type="submit" name="submit_nilai_seminar" class="btn btn-success">Simpan Nilai & Catatan</button>
                            </div>
                        </form>
                    </div>
                </div>
                <?php elseif ($can_input_nilai && !$seminar_ready_for_grading): ?>
                     <div class="message info"><p>Anda adalah <?php echo ($dosen_peran_seminar == 'penguji1' ? 'Penguji 1' : 'Penguji 2'); ?> untuk seminar ini. Input nilai akan tersedia jika status pelaksanaan seminar adalah 'Dijadwalkan' atau 'Selesai'. Status saat ini: <?php echo ucfirst(str_replace('_', ' ', htmlspecialchars($seminar_detail['status_pelaksanaan_seminar']))); ?>.</p></div>
                <?php elseif ($dosen_peran_seminar === 'pembimbing' && !$can_input_nilai): ?>
                    <div class="message info"><p>Anda adalah Dosen Pembimbing untuk KP ini. Penilaian seminar dilakukan oleh Dosen Penguji yang ditugaskan.</p></div>
                <?php endif; ?>

            <?php elseif(empty($error_message)): ?>
                <div class="message info"><p>Memuat detail seminar...</p></div>
            <?php endif; ?>
        </div>
    </main>
</div>

<style>
    /* CSS dari contoh sebelumnya untuk .kelola-seminar-detail, .info-section, .card, .form-group, .message, .btn, .status-seminar, .catatan, .icon-pencil sudah di-copy */
    /* Pastikan semua styling yang relevan dari versi sebelumnya ada di sini atau di CSS global */
    .kelola-seminar-detail h1 { margin-top: 0; margin-bottom: 5px; }
    .kelola-seminar-detail hr { margin-top:15px; margin-bottom: 20px; }
    .btn.mb-3 { margin-bottom: 1rem !important; }
    .icon-pencil::before { content: "✏️ "; }

    .info-section.card { margin-bottom: 1.5rem; }
    .info-section.card .card-header h3 { font-size: 1.2em; color: #007bff; }
    .info-section.card .card-body dl dt { width: 200px; float: left; font-weight: bold; margin-bottom: 0.5rem; }
    .info-section.card .card-body dl dd { margin-left: 210px; margin-bottom: 0.5rem; }
    .info-section.card .card-body dd.catatan { margin-left:0; background-color: #f9f9f9; padding: 8px; border: 1px solid #eee; border-radius: 4px; white-space: pre-wrap; margin-top: 5px; }

    .action-form.card .card-header h3 { font-size: 1.2em; color: #28a745; }
    .form-control { width: 100%; padding: .375rem .75rem; font-size: 1rem; line-height: 1.5; color: #495057; background-color: #fff; background-clip: padding-box; border: 1px solid #ced4da; border-radius: .25rem; transition: border-color .15s ease-in-out,box-shadow .15s ease-in-out;}
    .form-actions { margin-top: 1rem; }
</style>

<?php
require_once '../includes/footer.php';

if (isset($conn) && ($conn instanceof mysqli)) {
    $conn->close();
}
?>