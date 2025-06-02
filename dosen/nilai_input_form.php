<?php
// /KP/dosen/nilai_input_form.php

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
$id_pengajuan_url = null;

$pengajuan_info = null;
$nilai_kp_entry = null;

$error_message = '';
$success_message = '';
$error_message_initial_load = '';

// 2. VALIDASI PARAMETER URL
if (isset($_GET['id_pengajuan']) && filter_var($_GET['id_pengajuan'], FILTER_VALIDATE_INT) && (int)$_GET['id_pengajuan'] > 0) {
    $id_pengajuan_url = (int)$_GET['id_pengajuan'];
} else {
    $error_message_initial_load = "ID Pengajuan tidak valid atau tidak ditemukan dalam URL.";
}

// Sertakan file koneksi database
require_once '../config/db_connect.php';

// 3. FUNGSI UNTUK MENGAMBIL DATA PENGAJUAN DAN NILAI KP TERKAIT
function getPengajuanAndNilaiData($conn_db, $pengajuan_id, $nip_dosen, &$out_error_message) {
    $data = ['pengajuan' => null, 'nilai_kp' => null]; // Selalu inisialisasi sebagai array

    if (!$conn_db || !($conn_db instanceof mysqli) || $conn_db->connect_error) {
        $out_error_message = "Koneksi database tidak valid atau terputus dalam fungsi.";
        return $data;
    }
    if ($pengajuan_id === null || $pengajuan_id <= 0) {
        $out_error_message = "ID Pengajuan tidak valid untuk mengambil data dalam fungsi.";
        return $data;
    }

    $sql_pengajuan = "SELECT pk.id_pengajuan, pk.judul_kp, pk.status_pengajuan,
                             m.nim, m.nama AS nama_mahasiswa, m.prodi
                      FROM pengajuan_kp pk
                      JOIN mahasiswa m ON pk.nim = m.nim
                      WHERE pk.id_pengajuan = ? AND pk.nip_dosen_pembimbing_kp = ?";
    $stmt_pengajuan = $conn_db->prepare($sql_pengajuan);
    if ($stmt_pengajuan) {
        $stmt_pengajuan->bind_param("is", $pengajuan_id, $nip_dosen);
        $stmt_pengajuan->execute();
        $result_pengajuan = $stmt_pengajuan->get_result();
        if ($result_pengajuan->num_rows === 1) {
            $data['pengajuan'] = $result_pengajuan->fetch_assoc();
            // Ambil data nilai_kp jika pengajuan ditemukan
            $sql_nilai = "SELECT id_nilai, nilai_dosen_pembimbing, catatan_dosen_pembimbing
                          FROM nilai_kp
                          WHERE id_pengajuan = ?";
            $stmt_nilai = $conn_db->prepare($sql_nilai);
            if ($stmt_nilai) {
                $stmt_nilai->bind_param("i", $pengajuan_id);
                $stmt_nilai->execute();
                $result_nilai = $stmt_nilai->get_result();
                if ($result_nilai->num_rows === 1) {
                    $data['nilai_kp'] = $result_nilai->fetch_assoc();
                }
                $stmt_nilai->close();
            } else {
                $out_error_message .= (empty($out_error_message) ? "" : "<br>") . "Gagal menyiapkan query ambil data nilai KP.";
            }
        } else {
            if (empty($out_error_message)) $out_error_message = "Pengajuan KP (ID: ".htmlspecialchars($pengajuan_id).") tidak ditemukan atau Anda bukan Dosen Pembimbingnya.";
        }
        $stmt_pengajuan->close();
    } else {
        $out_error_message = "Gagal menyiapkan query info pengajuan: " . htmlspecialchars($conn_db->error);
    }
    return $data;
}

// 4. PROSES INPUT/UPDATE NILAI DARI DOSEN PEMBIMBING JIKA FORM DISUBMIT
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_nilai_dospem'])) {
    if ($id_pengajuan_url === null || !empty($error_message_initial_load)) {
        $error_message = "Tidak dapat memproses form karena ID Pengajuan awal tidak valid: " . $error_message_initial_load;
    } elseif (!$conn || !($conn instanceof mysqli) || $conn->connect_error) { // Cek koneksi sebelum memanggil fungsi
        $error_message = "Koneksi database tidak tersedia untuk memproses permintaan.";
    } else {
        $nilai_dospem_input = isset($_POST['nilai_dosen_pembimbing']) && is_numeric($_POST['nilai_dosen_pembimbing']) ? (float)$_POST['nilai_dosen_pembimbing'] : null;
        $catatan_dospem_input = trim($_POST['catatan_dosen_pembimbing']);
        $id_pengajuan_form = (int)$_POST['id_pengajuan'];

        if ($id_pengajuan_form !== $id_pengajuan_url) {
            $error_message = "Kesalahan: ID Pengajuan pada form tidak cocok dengan ID pada URL.";
        } elseif ($nilai_dospem_input === null || $nilai_dospem_input < 0 || $nilai_dospem_input > 100) {
            $error_message = "Nilai Dosen Pembimbing harus berupa angka antara 0 dan 100.";
        }

        if (empty($error_message)) {
            $auth_error_temp = '';
            // Panggil fungsi untuk otorisasi dan mendapatkan data nilai_kp yang mungkin sudah ada
            $current_data_for_auth = getPengajuanAndNilaiData($conn, $id_pengajuan_url, $nip_dosen_login, $auth_error_temp);

            // Pastikan $current_data_for_auth adalah array dan kunci 'pengajuan' ada & tidak null
            if (!is_array($current_data_for_auth) || !isset($current_data_for_auth['pengajuan']) || $current_data_for_auth['pengajuan'] === null) {
                $error_message = !empty($auth_error_temp) ? $auth_error_temp : "Otorisasi gagal atau data pengajuan tidak ditemukan saat akan memproses form.";
            } else {
                // $current_data_for_auth['pengajuan'] valid, sekarang cek $current_data_for_auth['nilai_kp']
                $id_nilai_existing = (isset($current_data_for_auth['nilai_kp']) && is_array($current_data_for_auth['nilai_kp']) && isset($current_data_for_auth['nilai_kp']['id_nilai']))
                                     ? $current_data_for_auth['nilai_kp']['id_nilai']
                                     : null;

                $conn->begin_transaction();
                try {
                    if ($id_nilai_existing !== null) { // UPDATE
                        $sql_action = "UPDATE nilai_kp SET nilai_dosen_pembimbing = ?, catatan_dosen_pembimbing = ? WHERE id_nilai = ? AND id_pengajuan = ?";
                        $stmt_action = $conn->prepare($sql_action);
                        if (!$stmt_action) throw new Exception("Prepare update nilai dospem gagal: " . $conn->error);
                        $stmt_action->bind_param("dsii", $nilai_dospem_input, $catatan_dospem_input, $id_nilai_existing, $id_pengajuan_url);
                    } else { // INSERT
                        $sql_action = "INSERT INTO nilai_kp (id_pengajuan, nilai_dosen_pembimbing, catatan_dosen_pembimbing) VALUES (?, ?, ?)";
                        $stmt_action = $conn->prepare($sql_action);
                        if (!$stmt_action) throw new Exception("Prepare insert nilai dospem gagal: " . $conn->error);
                        $stmt_action->bind_param("ids", $id_pengajuan_url, $nilai_dospem_input, $catatan_dospem_input);
                    }

                    if (!$stmt_action->execute()) {
                        throw new Exception("Eksekusi simpan nilai dospem gagal: " . $stmt_action->error);
                    }
                    $affected_rows = $stmt_action->affected_rows;
                    $stmt_action->close();
                    $conn->commit();

                    if ($affected_rows > 0 || $id_nilai_existing === null && $stmt_action->insert_id) { // Sukses jika ada baris terpengaruh ATAU ini adalah insert baru yang berhasil
                        $success_message = "Nilai dan catatan Dosen Pembimbing berhasil disimpan.";
                    } else {
                        $success_message = "Tidak ada perubahan data yang dilakukan (mungkin data masih sama).";
                    }

                } catch (Exception $e) {
                    $conn->rollback();
                    $error_message = "Gagal menyimpan nilai: " . htmlspecialchars($e->getMessage());
                }
            }
        }
    }
}

// Selalu ambil data terbaru untuk ditampilkan (saat GET atau setelah POST)
$display_error_message = $error_message_initial_load;
if (empty($display_error_message) && !empty($error_message)) {
    $display_error_message = $error_message; // Tampilkan error dari proses POST jika ada
}

if ($id_pengajuan_url !== null && empty($error_message_initial_load)) {
    if ($conn && ($conn instanceof mysqli) && !$conn->connect_error) { // Pastikan koneksi masih valid
        $fetch_error_temp = '';
        $fetched_data = getPengajuanAndNilaiData($conn, $id_pengajuan_url, $nip_dosen_login, $fetch_error_temp);
        
        if (is_array($fetched_data) && isset($fetched_data['pengajuan']) && $fetched_data['pengajuan'] !== null) {
            $pengajuan_info = $fetched_data['pengajuan'];
            $nilai_kp_entry = isset($fetched_data['nilai_kp']) ? $fetched_data['nilai_kp'] : null;
            // Jika error utama masih kosong TAPI fetch data menghasilkan error, gunakan error fetch
            if (empty($display_error_message) && !empty($fetch_error_temp) && !$pengajuan_info ) {
                $display_error_message = $fetch_error_temp;
            }
        } elseif (empty($display_error_message)) {
            $display_error_message = !empty($fetch_error_temp) ? $fetch_error_temp : "Data pengajuan tidak dapat dimuat untuk ID: ".htmlspecialchars($id_pengajuan_url).". Pastikan Anda adalah pembimbing yang sesuai.";
        }
    } elseif (empty($display_error_message)) {
        $display_error_message = "Koneksi database tidak tersedia untuk memuat data.";
    }
}

// Set judul halaman
$page_title = "Input Nilai KP Dosen Pembimbing";
if ($pengajuan_info && isset($pengajuan_info['judul_kp'])) {
    $page_title = "Nilai Dospem: " . htmlspecialchars($pengajuan_info['judul_kp']);
} elseif ($id_pengajuan_url !== null) {
     $page_title = "Input Nilai KP (ID: ".htmlspecialchars($id_pengajuan_url).")";
}
require_once '../includes/header.php';
?>

<div class="page-layout-wrapper">
    <?php require_once '../includes/sidebar_dosen.php'; ?>

    <main class="main-content-area">
        <div class="form-container nilai-dospem-form">
            <h1><?php echo htmlspecialchars($page_title); ?></h1>
            <a href="/KP/dosen/nilai_input_list.php" class="btn btn-light btn-sm mb-3">&laquo; Kembali ke Daftar Penilaian</a>
            <hr>

            <?php if (!empty($success_message)): ?>
                <div class="message success"><p><?php echo htmlspecialchars($success_message); ?></p></div>
            <?php endif; ?>
            <?php if (!empty($display_error_message)): ?>
                <div class="message error"><p><?php echo htmlspecialchars($display_error_message); ?></p></div>
            <?php endif; ?>

            <?php if ($pengajuan_info): ?>
                <div class="info-section card mb-4">
                    <div class="card-header"><h3>Informasi Mahasiswa & Kerja Praktek</h3></div>
                    <div class="card-body">
                        <dl>
                            <dt>Nama Mahasiswa:</dt><dd><?php echo htmlspecialchars($pengajuan_info['nama_mahasiswa']); ?></dd>
                            <dt>NIM:</dt><dd><?php echo htmlspecialchars($pengajuan_info['nim']); ?></dd>
                            <dt>Prodi:</dt><dd><?php echo htmlspecialchars($pengajuan_info['prodi']); ?></dd>
                            <dt>Judul KP:</dt><dd><strong><?php echo htmlspecialchars($pengajuan_info['judul_kp']); ?></strong></dd>
                            <dt>Status KP Saat Ini:</dt>
                            <dd><span class="status-badge status-<?php echo strtolower(str_replace([' ', '_'], '-', $pengajuan_info['status_pengajuan'])); ?>">
                                <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $pengajuan_info['status_pengajuan']))); ?></span>
                            </dd>
                        </dl>
                    </div>
                </div>

                <form action="/KP/dosen/nilai_input_form.php?id_pengajuan=<?php echo $id_pengajuan_url; ?>" method="POST" class="action-form card">
                    <div class="card-header"><h3><i class="icon-pencil"></i> Formulir Penilaian Dosen Pembimbing</h3></div>
                    <div class="card-body">
                        <input type="hidden" name="id_pengajuan" value="<?php echo $id_pengajuan_url; ?>">

                        <div class="form-group">
                            <label for="nilai_dosen_pembimbing">Nilai dari Dosen Pembimbing (0-100) (*):</label>
                            <input type="number" id="nilai_dosen_pembimbing" name="nilai_dosen_pembimbing" class="form-control"
                                   min="0" max="100" step="0.01"
                                   value="<?php echo htmlspecialchars( ($nilai_kp_entry && isset($nilai_kp_entry['nilai_dosen_pembimbing'])) ? $nilai_kp_entry['nilai_dosen_pembimbing'] : (isset($_POST['nilai_dosen_pembimbing']) ? $_POST['nilai_dosen_pembimbing'] : '') ); ?>" required>
                            <small>Masukkan nilai akhir dari Anda sebagai dosen pembimbing.</small>
                        </div>

                        <div class="form-group">
                            <label for="catatan_dosen_pembimbing">Catatan Dosen Pembimbing (Keseluruhan):</label>
                            <textarea id="catatan_dosen_pembimbing" name="catatan_dosen_pembimbing" class="form-control" rows="6" placeholder="Berikan evaluasi atau catatan keseluruhan terhadap pelaksanaan KP mahasiswa..."><?php echo htmlspecialchars( ($nilai_kp_entry && isset($nilai_kp_entry['catatan_dosen_pembimbing'])) ? $nilai_kp_entry['catatan_dosen_pembimbing'] : (isset($_POST['catatan_dosen_pembimbing']) ? $_POST['catatan_dosen_pembimbing'] : '') ); ?></textarea>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" name="submit_nilai_dospem" class="btn btn-success">Simpan Nilai Dosen Pembimbing</button>
                        </div>
                    </div>
                </form>

            <?php elseif(empty($display_error_message)): ?>
                 <div class="message info"><p>Tidak ada data pengajuan yang dapat ditampilkan atau Anda tidak memiliki hak akses. Pastikan ID Pengajuan pada URL sudah benar.</p></div>
            <?php endif; ?>
        </div>
    </main>
</div>

<style>
    /* ... (CSS styles dari versi sebelumnya bisa tetap digunakan) ... */
    .nilai-dospem-form h1 { margin-top: 0; margin-bottom: 5px; }
    .nilai-dospem-form hr { margin-top:15px; margin-bottom: 20px; }
    .btn.mb-3 { margin-bottom: 1rem !important; }
    .icon-pencil::before { content: "✏️ "; }

    .info-section.card { margin-bottom: 1.5rem; }
    .info-section.card .card-header h3 { font-size: 1.2em; color: #007bff; }
    .info-section.card .card-body dl dt { width: 180px; float:left; font-weight:bold; margin-bottom:0.5rem; padding-right: 10px; box-sizing: border-box;}
    .info-section.card .card-body dl dd { margin-left: 180px; margin-bottom:0.5rem; } /* Disesuaikan dengan dt */

    .action-form.card .card-header h3 { font-size: 1.2em; color: #28a745; }
    .form-group { margin-bottom: 1rem; }
    .form-group label { display: block; margin-bottom: .5rem; font-weight:bold; }
    .form-control {display: block; width: 100%; padding: .375rem .75rem; font-size: 1rem; line-height: 1.5; color: #495057; background-color: #fff; background-clip: padding-box; border: 1px solid #ced4da; border-radius: .25rem; transition: border-color .15s ease-in-out,box-shadow .15s ease-in-out; }
    .form-group small { display: block; font-size: 0.85em; color: #6c757d; margin-top: 4px; }
    .form-actions { margin-top: 1.5rem; }
    .message { padding: 10px 15px; margin-bottom: 20px; border-radius: 5px; border: 1px solid transparent; }
    .message.success { background-color: #d4edda; color: #155724; border-color: #c3e6cb; }
    .message.error { background-color: #f8d7da; color: #721c24; border-color: #f5c6cb; }
    .message.info { background-color: #d1ecf1; color: #0c5460; border-color: #bee5eb; }
</style>

<?php
require_once '../includes/footer.php';

if (isset($conn) && ($conn instanceof mysqli) && !$conn->connect_error) { // Tambahkan pengecekan error koneksi
    $conn->close();
}
?>