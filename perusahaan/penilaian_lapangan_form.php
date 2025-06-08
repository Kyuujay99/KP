<?php
// /KP/perusahaan/penilaian_lapangan_form.php

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// 1. OTENTIKASI DAN OTORISASI
require_once '../includes/auth_check.php';
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'perusahaan') {
    session_unset();
    session_destroy();
    header("Location: /KP/index.php?error=unauthorized_perusahaan");
    exit();
}

$id_perusahaan_login = $_SESSION['user_id']; // ID perusahaan yang login
$nama_perusahaan_login = $_SESSION['user_nama'];
$id_pengajuan_url = null;

$pengajuan_info = null; // Info pengajuan KP dan mahasiswa
$nilai_kp_entry = null; // Data dari tabel nilai_kp jika sudah ada

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

// 3. FUNGSI UNTUK MENGAMBIL DATA PENGAJUAN DAN NILAI KP TERKAIT DARI PERUSAHAAN INI
function getPengajuanForPerusahaan($conn_db, $pengajuan_id, $id_perusahaan, &$out_error_message) {
    $data = ['pengajuan' => null, 'nilai_kp' => null];

    if (!$conn_db || !($conn_db instanceof mysqli) || $conn_db->connect_error) {
        $out_error_message = "Koneksi database tidak valid dalam fungsi.";
        return $data;
    }
    if ($pengajuan_id === null || $pengajuan_id <= 0) {
        $out_error_message = "ID Pengajuan tidak valid untuk mengambil data.";
        return $data;
    }

    // Ambil info pengajuan dan mahasiswa, pastikan KP ini ada di perusahaan yang login
    $sql_pengajuan = "SELECT pk.id_pengajuan, pk.judul_kp, pk.status_pengajuan,
                             m.nim, m.nama AS nama_mahasiswa, m.prodi
                      FROM pengajuan_kp pk
                      JOIN mahasiswa m ON pk.nim = m.nim
                      WHERE pk.id_pengajuan = ? AND pk.id_perusahaan = ?";
    $stmt_pengajuan = $conn_db->prepare($sql_pengajuan);
    if ($stmt_pengajuan) {
        $stmt_pengajuan->bind_param("ii", $pengajuan_id, $id_perusahaan);
        $stmt_pengajuan->execute();
        $result_pengajuan = $stmt_pengajuan->get_result();
        if ($result_pengajuan->num_rows === 1) {
            $data['pengajuan'] = $result_pengajuan->fetch_assoc();

            // Ambil data nilai_kp jika ada untuk pengajuan ini
            $sql_nilai = "SELECT id_nilai, nilai_pembimbing_lapangan, catatan_pembimbing_lapangan
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
                $out_error_message .= (empty($out_error_message) ? "" : "<br>") . "Gagal mengambil data nilai KP yang ada.";
            }
        } else {
            if (empty($out_error_message)) $out_error_message = "Pengajuan KP (ID: ".htmlspecialchars($pengajuan_id).") tidak ditemukan di perusahaan Anda atau tidak valid.";
        }
        $stmt_pengajuan->close();
    } else {
        $out_error_message = "Gagal menyiapkan query info pengajuan: " . htmlspecialchars($conn_db->error);
    }
    return $data;
}

// 4. PROSES INPUT/UPDATE NILAI DARI PEMBIMBING LAPANGAN JIKA FORM DISUBMIT
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_nilai_lapangan'])) {
    if ($id_pengajuan_url === null || !empty($error_message_initial_load)) {
        $error_message = "Tidak dapat memproses form karena ID Pengajuan awal tidak valid: " . $error_message_initial_load;
    } elseif (!$conn || !($conn instanceof mysqli) || $conn->connect_error) {
        $error_message = "Koneksi database tidak tersedia untuk memproses permintaan.";
    } else {
        $nilai_lapangan_input = isset($_POST['nilai_pembimbing_lapangan']) && is_numeric($_POST['nilai_pembimbing_lapangan']) ? (float)$_POST['nilai_pembimbing_lapangan'] : null;
        $catatan_lapangan_input = trim($_POST['catatan_pembimbing_lapangan']);
        $id_pengajuan_form = (int)$_POST['id_pengajuan'];

        if ($id_pengajuan_form !== $id_pengajuan_url) {
            $error_message = "Kesalahan: ID Pengajuan pada form tidak cocok dengan ID pada URL.";
        } elseif ($nilai_lapangan_input === null || $nilai_lapangan_input < 0 || $nilai_lapangan_input > 100) {
            $error_message = "Nilai Pembimbing Lapangan harus berupa angka antara 0 dan 100.";
        }

        if (empty($error_message)) {
            $auth_error_temp = '';
            $current_data_for_auth = getPengajuanForPerusahaan($conn, $id_pengajuan_url, $id_perusahaan_login, $auth_error_temp);

            if (!is_array($current_data_for_auth) || !isset($current_data_for_auth['pengajuan']) || $current_data_for_auth['pengajuan'] === null) {
                $error_message = !empty($auth_error_temp) ? $auth_error_temp : "Otorisasi gagal: Pengajuan KP ini tidak terkait dengan perusahaan Anda (saat POST).";
            } else {
                $id_nilai_existing = (isset($current_data_for_auth['nilai_kp']) && is_array($current_data_for_auth['nilai_kp']) && isset($current_data_for_auth['nilai_kp']['id_nilai']))
                                     ? $current_data_for_auth['nilai_kp']['id_nilai']
                                     : null;

                $conn->begin_transaction();
                try {
                    if ($id_nilai_existing !== null) { // UPDATE
                        $sql_action = "UPDATE nilai_kp SET nilai_pembimbing_lapangan = ?, catatan_pembimbing_lapangan = ? WHERE id_nilai = ? AND id_pengajuan = ?";
                        $stmt_action = $conn->prepare($sql_action);
                        if (!$stmt_action) throw new Exception("Prepare update nilai lapangan gagal: " . $conn->error);
                        $stmt_action->bind_param("dsii", $nilai_lapangan_input, $catatan_lapangan_input, $id_nilai_existing, $id_pengajuan_url);
                    } else { // INSERT
                        $sql_action = "INSERT INTO nilai_kp (id_pengajuan, nilai_pembimbing_lapangan, catatan_pembimbing_lapangan) VALUES (?, ?, ?)";
                        $stmt_action = $conn->prepare($sql_action);
                        if (!$stmt_action) throw new Exception("Prepare insert nilai lapangan gagal: " . $conn->error);
                        $stmt_action->bind_param("ids", $id_pengajuan_url, $nilai_lapangan_input, $catatan_lapangan_input);
                    }

                    if (!$stmt_action->execute()) {
                        throw new Exception("Eksekusi simpan nilai lapangan gagal: " . $stmt_action->error);
                    }
                    $affected_rows = $stmt_action->affected_rows;
                    $stmt_action->close();
                    $conn->commit();

                    if ($affected_rows > 0 || ($id_nilai_existing === null && $stmt_action->insert_id)) {
                        $success_message = "Penilaian dari Pembimbing Lapangan berhasil disimpan.";
                    } else {
                        $success_message = "Data penilaian telah dikirim (tidak ada perubahan atau data sama).";
                    }
                } catch (Exception $e) {
                    $conn->rollback();
                    $error_message = "Gagal menyimpan penilaian: " . htmlspecialchars($e->getMessage());
                }
            }
        }
    }
}

// Selalu ambil data terbaru untuk ditampilkan
$display_error_message = $error_message_initial_load;
if (empty($display_error_message) && !empty($error_message)) {
    $display_error_message = $error_message;
}

if ($id_pengajuan_url !== null && empty($error_message_initial_load)) {
    if ($conn && ($conn instanceof mysqli) && !$conn->connect_error) {
        $fetch_error_temp = '';
        $fetched_data = getPengajuanForPerusahaan($conn, $id_pengajuan_url, $id_perusahaan_login, $fetch_error_temp);
        
        if (is_array($fetched_data) && isset($fetched_data['pengajuan']) && $fetched_data['pengajuan'] !== null) {
            $pengajuan_info = $fetched_data['pengajuan'];
            $nilai_kp_entry = isset($fetched_data['nilai_kp']) ? $fetched_data['nilai_kp'] : null;
            if (empty($display_error_message) && !empty($fetch_error_temp) && !$pengajuan_info ) {
                $display_error_message = $fetch_error_temp;
            }
        } elseif (empty($display_error_message)) {
            $display_error_message = !empty($fetch_error_temp) ? $fetch_error_temp : "Data pengajuan KP tidak dapat dimuat untuk perusahaan Anda (ID Pengajuan: ".htmlspecialchars($id_pengajuan_url).").";
        }
    } elseif (empty($display_error_message)) {
        $display_error_message = "Koneksi database tidak tersedia untuk memuat data.";
    }
}

// Set judul halaman
$page_title = "Input Penilaian Pembimbing Lapangan";
if ($pengajuan_info && isset($pengajuan_info['judul_kp'])) {
    $page_title = "Nilai Lapangan: " . htmlspecialchars($pengajuan_info['judul_kp']);
} elseif ($id_pengajuan_url !== null) {
     $page_title = "Input Penilaian Lapangan (ID KP: ".htmlspecialchars($id_pengajuan_url).")";
}
require_once '../includes/header.php';
?>

<div class="page-layout-wrapper">
    <main class="main-content-area">
        <div class="form-container penilaian-lapangan-form">
            <h1><?php echo htmlspecialchars($page_title); ?></h1>
            <a href="/KP/perusahaan/mahasiswa_kp_list.php" class="btn btn-light btn-sm mb-3">&laquo; Kembali ke Daftar Mahasiswa KP</a>
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

                <form action="/KP/perusahaan/penilaian_lapangan_form.php?id_pengajuan=<?php echo $id_pengajuan_url; ?>" method="POST" class="action-form card">
                    <div class="card-header"><h3><i class="icon-pencil"></i> Formulir Penilaian Pembimbing Lapangan</h3></div>
                    <div class="card-body">
                        <input type="hidden" name="id_pengajuan" value="<?php echo $id_pengajuan_url; ?>">

                        <div class="form-group">
                            <label for="nilai_pembimbing_lapangan">Nilai dari Pembimbing Lapangan (0-100) (*):</label>
                            <input type="number" id="nilai_pembimbing_lapangan" name="nilai_pembimbing_lapangan" class="form-control"
                                   min="0" max="100" step="0.01"
                                   value="<?php echo htmlspecialchars( ($nilai_kp_entry && isset($nilai_kp_entry['nilai_pembimbing_lapangan'])) ? $nilai_kp_entry['nilai_pembimbing_lapangan'] : (isset($_POST['nilai_pembimbing_lapangan']) ? $_POST['nilai_pembimbing_lapangan'] : '') ); ?>" required>
                            <small>Masukkan nilai berdasarkan evaluasi Anda terhadap kinerja mahasiswa selama KP.</small>
                        </div>

                        <div class="form-group">
                            <label for="catatan_pembimbing_lapangan">Catatan Pembimbing Lapangan (Evaluasi Umum):</label>
                            <textarea id="catatan_pembimbing_lapangan" name="catatan_pembimbing_lapangan" class="form-control" rows="6" placeholder="Berikan evaluasi, feedback, atau catatan penting mengenai kinerja mahasiswa..."><?php echo htmlspecialchars( ($nilai_kp_entry && isset($nilai_kp_entry['catatan_pembimbing_lapangan'])) ? $nilai_kp_entry['catatan_pembimbing_lapangan'] : (isset($_POST['catatan_pembimbing_lapangan']) ? $_POST['catatan_pembimbing_lapangan'] : '') ); ?></textarea>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" name="submit_nilai_lapangan" class="btn btn-success">Simpan Penilaian Lapangan</button>
                        </div>
                    </div>
                </form>

            <?php elseif(empty($display_error_message)): ?>
                <div class="message info"><p>Silakan periksa kembali ID Pengajuan pada URL atau pastikan KP ini terkait dengan perusahaan Anda.</p></div>
            <?php endif; ?>
        </div>
    </main>
</div>

<style>
    /* Asumsikan CSS umum dari header, sidebar, .card, .form-group, .message, .btn, .status-badge sudah ada */
    .penilaian-lapangan-form h1 { margin-top: 0; margin-bottom: 5px; }
    .penilaian-lapangan-form hr { margin-top:15px; margin-bottom: 20px; }
    .btn.mb-3 { margin-bottom: 1rem !important; }
    .icon-pencil::before { content: "📝 "; } /* Ganti ikon jika perlu */

    .info-section.card .card-header h3 { font-size: 1.2em; color: #007bff; } /* Warna bisa disesuaikan untuk tema perusahaan */
    .info-section.card .card-body dl dt { width: 180px; }
    .info-section.card .card-body dl dd { margin-left: 190px; }

    .action-form.card .card-header h3 { font-size: 1.2em; color: #17a2b8; } /* Warna tema perusahaan */
</style>

<?php
require_once '../includes/footer.php';

if (isset($conn) && ($conn instanceof mysqli) && !$conn->connect_error) {
    $conn->close();
}
?>