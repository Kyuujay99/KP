<?php
// /KP/admin_prodi/dokumen_verifikasi_form.php

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
$id_dokumen_url = null;
$id_pengajuan_url = null; // Untuk link kembali dan konteks
$dokumen_detail = null;
$pengajuan_konteks = null; // Untuk info mahasiswa dan judul KP
$error_message = '';
$success_message = '';

// 2. VALIDASI DAN AMBIL ID DOKUMEN & ID PENGAJUAN DARI URL
if (isset($_GET['id_dokumen']) && filter_var($_GET['id_dokumen'], FILTER_VALIDATE_INT)) {
    $id_dokumen_url = (int)$_GET['id_dokumen'];
} else {
    $error_message = "ID Dokumen tidak valid atau tidak ditemukan.";
}
if (isset($_GET['id_pengajuan']) && filter_var($_GET['id_pengajuan'], FILTER_VALIDATE_INT)) {
    $id_pengajuan_url = (int)$_GET['id_pengajuan'];
} else {
    if(empty($error_message)) $error_message = "ID Pengajuan tidak valid atau tidak disertakan untuk konteks.";
    // Jika id_pengajuan tidak ada, link kembali mungkin tidak berfungsi dengan benar
}

// Sertakan file koneksi database
require_once '../config/db_connect.php';

// 3. FUNGSI UNTUK MENGAMBIL DETAIL DOKUMEN DAN KONTEKS PENGAJUAN
function getDocumentAndPengajuanContext($conn_db, $doc_id, $pengajuan_id, &$out_error_message) {
    $data = ['dokumen' => null, 'pengajuan' => null];

    // Ambil detail dokumen
    $sql_doc = "SELECT dk.id_dokumen, dk.id_pengajuan, dk.nama_dokumen, dk.jenis_dokumen, dk.file_path, 
                       dk.deskripsi, dk.status_verifikasi_dokumen, dk.catatan_verifikator, dk.tanggal_upload,
                       m.nim, m.nama AS nama_mahasiswa, pk.judul_kp
                FROM dokumen_kp dk
                JOIN pengajuan_kp pk ON dk.id_pengajuan = pk.id_pengajuan
                JOIN mahasiswa m ON pk.nim = m.nim
                WHERE dk.id_dokumen = ? AND dk.id_pengajuan = ?";
    $stmt_doc = $conn_db->prepare($sql_doc);
    if ($stmt_doc) {
        $stmt_doc->bind_param("ii", $doc_id, $pengajuan_id);
        $stmt_doc->execute();
        $result_doc = $stmt_doc->get_result();
        if ($result_doc->num_rows === 1) {
            $doc_data = $result_doc->fetch_assoc();
            $data['dokumen'] = [
                'id_dokumen' => $doc_data['id_dokumen'],
                'id_pengajuan' => $doc_data['id_pengajuan'],
                'nama_dokumen' => $doc_data['nama_dokumen'],
                'jenis_dokumen' => $doc_data['jenis_dokumen'],
                'file_path' => $doc_data['file_path'],
                'deskripsi' => $doc_data['deskripsi'],
                'status_verifikasi_dokumen' => $doc_data['status_verifikasi_dokumen'],
                'catatan_verifikator' => $doc_data['catatan_verifikator'],
                'tanggal_upload' => $doc_data['tanggal_upload']
            ];
            $data['pengajuan'] = [
                'nim' => $doc_data['nim'],
                'nama_mahasiswa' => $doc_data['nama_mahasiswa'],
                'judul_kp' => $doc_data['judul_kp']
            ];
        } else {
            if(empty($out_error_message)) $out_error_message = "Dokumen tidak ditemukan atau tidak sesuai dengan pengajuan yang diberikan.";
        }
        $stmt_doc->close();
    } else {
        if(empty($out_error_message)) $out_error_message = "Gagal menyiapkan query untuk mengambil detail dokumen.";
    }
    return $data;
}


// 4. PROSES UPDATE STATUS VERIFIKASI JIKA FORM DISUBMIT
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_verifikasi_dokumen']) && $id_dokumen_url !== null && $id_pengajuan_url !== null && empty($error_message)) {
    $new_status_verifikasi = $_POST['status_verifikasi_dokumen'];
    $catatan_verifikator_input = trim($_POST['catatan_verifikator']);
    $id_dokumen_form = (int)$_POST['id_dokumen'];

    if ($id_dokumen_form !== $id_dokumen_url) {
        $error_message = "Kesalahan: ID Dokumen tidak cocok.";
    }
    // Validasi status baru (sesuai ENUM di DB)
    $allowed_statuses_dokumen = ['pending', 'disetujui', 'revisi_diperlukan', 'ditolak'];
    if (empty($new_status_verifikasi) || !in_array($new_status_verifikasi, $allowed_statuses_dokumen)) {
        $error_message = "Status verifikasi dokumen yang dipilih tidak valid.";
    }

    if (empty($error_message)) {
        if ($conn && ($conn instanceof mysqli)) {
            $sql_update_doc = "UPDATE dokumen_kp SET status_verifikasi_dokumen = ?, catatan_verifikator = ? WHERE id_dokumen = ?";
            $stmt_update_doc = $conn->prepare($sql_update_doc);
            if ($stmt_update_doc) {
                $stmt_update_doc->bind_param("ssi", $new_status_verifikasi, $catatan_verifikator_input, $id_dokumen_form);
                if ($stmt_update_doc->execute()) {
                    if ($stmt_update_doc->affected_rows > 0) {
                        $success_message = "Status verifikasi dokumen berhasil diperbarui!";
                        // Data akan di-refresh di bawah untuk menampilkan status baru
                    } else {
                        // Bisa jadi tidak ada perubahan karena status dan catatan sama
                        $success_message = "Tidak ada perubahan pada status atau catatan verifikasi dokumen (mungkin data masih sama).";
                    }
                } else {
                    $error_message = "Gagal memperbarui status dokumen: " . (($stmt_update_doc->error) ? htmlspecialchars($stmt_update_doc->error) : "Kesalahan tidak diketahui.");
                }
                $stmt_update_doc->close();
            } else {
                $error_message = "Gagal menyiapkan statement update dokumen: " . (($conn->error) ? htmlspecialchars($conn->error) : "Kesalahan tidak diketahui.");
            }
        } else {
            $error_message = "Koneksi database hilang saat akan update status dokumen.";
        }
    }
}

// Selalu ambil data terbaru untuk ditampilkan (atau jika ada error sebelumnya, $dokumen_detail akan null)
if ($id_dokumen_url && $id_pengajuan_url && empty($error_message) && $conn && ($conn instanceof mysqli)) {
    $fetched_data = getDocumentAndPengajuanContext($conn, $id_dokumen_url, $id_pengajuan_url, $error_message);
    $dokumen_detail = $fetched_data['dokumen'];
    $pengajuan_konteks = $fetched_data['pengajuan'];
    if (!$dokumen_detail && empty($error_message)) { // Jika fungsi return null tapi tidak set error eksplisit
        $error_message = "Gagal memuat detail dokumen atau konteks pengajuan.";
    }
}

// Daftar status verifikasi dokumen (sesuai ENUM)
$opsi_status_verifikasi_dokumen = [
    'pending' => 'Pending (Menunggu Verifikasi)',
    'disetujui' => 'Disetujui',
    'revisi_diperlukan' => 'Revisi Diperlukan',
    'ditolak' => 'Ditolak'
];

// Set judul halaman
$page_title = "Verifikasi Dokumen";
if ($dokumen_detail && !empty($dokumen_detail['nama_dokumen'])) {
    $page_title = "Verifikasi: " . htmlspecialchars($dokumen_detail['nama_dokumen']);
}
require_once '../includes/header.php';
?>

<div class="page-layout-wrapper">

    <?php require_once '../includes/sidebar_admin_prodi.php'; ?>

    <main class="main-content-area">
        <div class="form-container verifikasi-dokumen-form">
            <h1><?php echo htmlspecialchars($page_title); ?></h1>
            <?php if ($id_pengajuan_url): ?>
                <a href="/KP/admin_prodi/pengajuan_kp_detail_admin.php?id_pengajuan=<?php echo $id_pengajuan_url; ?>" class="btn btn-light btn-sm mb-3">&laquo; Kembali ke Detail Pengajuan KP</a>
            <?php else: ?>
                 <a href="/KP/admin_prodi/pengajuan_kp_monitoring.php" class="btn btn-light btn-sm mb-3">&laquo; Kembali ke Monitoring KP</a>
            <?php endif; ?>
            <hr>

            <?php if (!empty($success_message)): ?>
                <div class="message success"><p><?php echo htmlspecialchars($success_message); ?></p></div>
            <?php endif; ?>
            <?php if (!empty($error_message)): ?>
                <div class="message error"><p><?php echo $error_message; ?></p></div>
            <?php endif; ?>

            <?php if ($dokumen_detail && $pengajuan_konteks): ?>
                <div class="info-section card mb-4">
                    <div class="card-header"><h3>Informasi Dokumen & Pengajuan Terkait</h3></div>
                    <div class="card-body">
                        <dl>
                            <dt>Nama Dokumen:</dt><dd><strong><?php echo htmlspecialchars($dokumen_detail['nama_dokumen']); ?></strong></dd>
                            <dt>Jenis Dokumen:</dt><dd><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $dokumen_detail['jenis_dokumen']))); ?></dd>
                            <dt>Tanggal Upload:</dt><dd><?php echo date("d M Y, H:i", strtotime($dokumen_detail['tanggal_upload'])); ?></dd>
                            <?php if(!empty($dokumen_detail['deskripsi'])): ?>
                            <dt>Deskripsi Mhs:</dt><dd><?php echo nl2br(htmlspecialchars($dokumen_detail['deskripsi'])); ?></dd>
                            <?php endif; ?>
                            <dt>File:</dt>
                            <dd>
                                <?php if(!empty($dokumen_detail['file_path'])): ?>
                                    <a href="/KP/<?php echo htmlspecialchars($dokumen_detail['file_path']); ?>" target="_blank" class="btn btn-outline-primary btn-sm">Unduh/Lihat File</a>
                                <?php else: ?>
                                    <em>File tidak ditemukan.</em>
                                <?php endif; ?>
                            </dd>
                        </dl>
                        <hr style="margin: 15px 0;">
                        <p style="font-size:0.9em; color:#555;">
                            Dokumen ini terkait dengan pengajuan KP: <strong>"<?php echo htmlspecialchars($pengajuan_konteks['judul_kp']); ?>"</strong><br>
                            Oleh Mahasiswa: <?php echo htmlspecialchars($pengajuan_konteks['nama_mahasiswa']); ?> (NIM: <?php echo htmlspecialchars($pengajuan_konteks['nim']); ?>)
                        </p>
                    </div>
                </div>

                <form action="/KP/admin_prodi/dokumen_verifikasi_form.php?id_dokumen=<?php echo $id_dokumen_url; ?>&id_pengajuan=<?php echo $id_pengajuan_url; ?>" method="POST" class="action-form card">
                    <div class="card-header"><h3>Formulir Verifikasi Dokumen</h3></div>
                    <div class="card-body">
                        <input type="hidden" name="id_dokumen" value="<?php echo $id_dokumen_url; ?>">
                        <input type="hidden" name="id_pengajuan" value="<?php echo $id_pengajuan_url; ?>"> <div class="form-group">
                            <label for="status_verifikasi_dokumen_current">Status Verifikasi Saat Ini:</label>
                            <input type="text" id="status_verifikasi_dokumen_current" value="<?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $dokumen_detail['status_verifikasi_dokumen']))); ?>" readonly class="readonly-input">
                        </div>

                        <div class="form-group">
                            <label for="status_verifikasi_dokumen">Ubah Status Verifikasi Menjadi (*):</label>
                            <select id="status_verifikasi_dokumen" name="status_verifikasi_dokumen" required>
                                <option value="">-- Pilih Status Baru --</option>
                                <?php foreach ($opsi_status_verifikasi_dokumen as $value => $text): ?>
                                    <option value="<?php echo $value; ?>" <?php echo ($dokumen_detail['status_verifikasi_dokumen'] == $value) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($text); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="catatan_verifikator">Catatan Verifikator:</label>
                            <textarea id="catatan_verifikator" name="catatan_verifikator" rows="5" placeholder="Berikan catatan jika dokumen ditolak atau memerlukan revisi..."><?php echo htmlspecialchars($dokumen_detail['catatan_verifikator']); ?></textarea>
                            <small>Catatan ini akan menggantikan catatan verifikator sebelumnya (jika ada).</small>
                        </div>

                        <div class="form-actions">
                            <button type="submit" name="submit_verifikasi_dokumen" class="btn btn-success">Simpan Verifikasi</button>
                        </div>
                    </div>
                </form>

            <?php elseif(empty($error_message)): ?>
                <div class="message info"><p>Memuat detail dokumen...</p></div>
            <?php endif; ?>

        </div>
    </main>
</div>

<style>
    /* Asumsikan CSS umum dari header, sidebar, .card, .form-group, .message, .btn sudah ada */
    .verifikasi-dokumen-form h1 { margin-top: 0; margin-bottom: 5px; }
    .verifikasi-dokumen-form hr { margin-top:15px; margin-bottom: 20px; }
    .btn.mb-3 { margin-bottom: 1rem !important; }

    .info-section.card .card-header h3 { font-size: 1.2em; color: #007bff; }
    .info-section.card .card-body dl dt { width: 160px; }
    .info-section.card .card-body dl dd { margin-left: 170px; }

    .action-form.card .card-header h3 { font-size: 1.2em; color: #28a745; }
    .readonly-input { background-color: #e9ecef; cursor: not-allowed; }
</style>

<?php
require_once '../includes/footer.php';

if (isset($conn) && ($conn instanceof mysqli)) {
    $conn->close();
}
?>