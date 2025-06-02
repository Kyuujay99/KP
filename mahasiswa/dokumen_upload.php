<?php
// /KP/mahasiswa/dokumen_upload.php

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
$id_pengajuan_url = null;
$judul_kp_konteks = "Pengajuan KP Tidak Ditemukan"; // Judul KP untuk konteks
$error_message = '';
$success_message = '';

// Sertakan file koneksi database
require_once '../config/db_connect.php';

// 2. VALIDASI DAN AMBIL ID PENGAJUAN DARI URL (GET PARAMETER)
//    DAN PASTIKAN PENGAJUAN INI MILIK MAHASISWA YANG LOGIN
if (isset($_GET['id_pengajuan']) && filter_var($_GET['id_pengajuan'], FILTER_VALIDATE_INT)) {
    $id_pengajuan_url = (int)$_GET['id_pengajuan'];

    if ($conn && ($conn instanceof mysqli)) {
        $sql_check_owner = "SELECT judul_kp FROM pengajuan_kp WHERE id_pengajuan = ? AND nim = ?";
        $stmt_check_owner = $conn->prepare($sql_check_owner);
        if ($stmt_check_owner) {
            $stmt_check_owner->bind_param("is", $id_pengajuan_url, $nim_mahasiswa);
            $stmt_check_owner->execute();
            $result_owner = $stmt_check_owner->get_result();
            if ($result_owner->num_rows === 1) {
                $pengajuan_info = $result_owner->fetch_assoc();
                $judul_kp_konteks = $pengajuan_info['judul_kp'];
            } else {
                $error_message = "Pengajuan KP tidak ditemukan atau Anda tidak memiliki izin untuk mengunggah dokumen ke pengajuan ini.";
                $id_pengajuan_url = null; // Invalidate ID jika tidak berhak
            }
            $stmt_check_owner->close();
        } else {
            $error_message = "Gagal memverifikasi kepemilikan pengajuan.";
            $id_pengajuan_url = null;
        }
    } else {
        $error_message = "Koneksi database gagal.";
        $id_pengajuan_url = null;
    }
} else {
    $error_message = "ID Pengajuan tidak valid atau tidak disertakan.";
}


// 3. PROSES UPLOAD DOKUMEN JIKA FORM DISUBMIT (METHOD POST)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_dokumen']) && $id_pengajuan_url !== null && empty($error_message)) {
    // Ambil id_pengajuan dari hidden field (seharusnya sama dengan $id_pengajuan_url)
    $id_pengajuan_form = (int)$_POST['id_pengajuan'];

    // Double check ID
    if ($id_pengajuan_form !== $id_pengajuan_url) {
        $error_message = "Kesalahan: ID Pengajuan tidak cocok.";
    } else {
        $nama_dokumen_input = trim($_POST['nama_dokumen']);
        $jenis_dokumen_input = $_POST['jenis_dokumen'];
        $deskripsi_dokumen_input = trim($_POST['deskripsi_dokumen']);

        // Validasi input dasar
        if (empty($nama_dokumen_input) || empty($jenis_dokumen_input)) {
            $error_message = "Nama dokumen dan jenis dokumen wajib diisi.";
        } elseif (!isset($_FILES["file_dokumen"]) || $_FILES["file_dokumen"]["error"] == UPLOAD_ERR_NO_FILE) {
            $error_message = "Anda belum memilih file untuk diunggah.";
        } elseif ($_FILES["file_dokumen"]["error"] != 0) {
            $error_message = "Terjadi kesalahan saat mengunggah file (Error code: " . $_FILES["file_dokumen"]["error"] . "). Silakan coba lagi.";
        } else {
            // Semua input dasar valid dan file ada, lanjutkan proses file
            $target_dir = "../uploads/dokumen_kp/"; // Path relatif dari file PHP ini
            $original_filename = basename($_FILES["file_dokumen"]["name"]);
            $file_extension = strtolower(pathinfo($original_filename, PATHINFO_EXTENSION));
            // Buat nama file unik: IDPengajuan_NIM_JenisDok_NamaDokClean_Timestamp.ext
            $cleaned_nama_dok = preg_replace('/[^a-zA-Z0-9_-]/', '_', substr($nama_dokumen_input, 0, 50)); // Bersihkan nama dok untuk filename
            $unique_filename = $id_pengajuan_form . "_" . $nim_mahasiswa . "_" . $jenis_dokumen_input . "_" . $cleaned_nama_dok . "_" . time() . "." . $file_extension;
            $target_file = $target_dir . $unique_filename;
            $upload_ok = 1;

            // Cek tipe file (misalnya PDF, DOC, DOCX, JPG, PNG)
            $allowed_types = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png', 'zip', 'rar'];
            if (!in_array($file_extension, $allowed_types)) {
                $error_message = "Maaf, hanya file PDF, DOC(X), JPG, PNG, ZIP, RAR yang diizinkan.";
                $upload_ok = 0;
            }

            // Cek ukuran file (misalnya maksimal 10MB)
            if ($upload_ok && $_FILES["file_dokumen"]["size"] > 10000000) { // 10MB in bytes
                $error_message = "Maaf, ukuran file terlalu besar (maksimal 10MB).";
                $upload_ok = 0;
            }

            if ($upload_ok) {
                if (move_uploaded_file($_FILES["file_dokumen"]["tmp_name"], $target_file)) {
                    $file_path_db = "uploads/dokumen_kp/" . $unique_filename; // Path untuk disimpan di DB (relatif dari root /KP/)

                    // Simpan informasi dokumen ke database
                    if ($conn && ($conn instanceof mysqli)) {
                        $uploader_id = $nim_mahasiswa;
                        $tipe_uploader = 'mahasiswa';
                        $status_verifikasi = 'pending'; // Default status

                        $sql_insert_doc = "INSERT INTO dokumen_kp (id_pengajuan, uploader_id, tipe_uploader, nama_dokumen, jenis_dokumen, file_path, deskripsi, status_verifikasi_dokumen) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                        $stmt_insert_doc = $conn->prepare($sql_insert_doc);
                        if ($stmt_insert_doc) {
                            $stmt_insert_doc->bind_param("isssssss", $id_pengajuan_form, $uploader_id, $tipe_uploader, $nama_dokumen_input, $jenis_dokumen_input, $file_path_db, $deskripsi_dokumen_input, $status_verifikasi);
                            if ($stmt_insert_doc->execute()) {
                                $success_message = "Dokumen '" . htmlspecialchars($nama_dokumen_input) . "' berhasil diunggah!";
                                // Pertimbangkan redirect ke halaman detail pengajuan setelah sukses
                                // header("Location: /KP/mahasiswa/pengajuan_kp_detail.php?id=" . $id_pengajuan_form . "&upload_success=1");
                                // exit();
                            } else {
                                $error_message = "Gagal menyimpan informasi dokumen ke database: " . (($stmt_insert_doc->error) ? htmlspecialchars($stmt_insert_doc->error) : "Kesalahan tidak diketahui.");
                                // Jika gagal insert DB, hapus file yang sudah terupload (opsional, untuk konsistensi)
                                if (file_exists($target_file)) unlink($target_file);
                            }
                            $stmt_insert_doc->close();
                        } else {
                            $error_message = "Gagal menyiapkan statement penyimpanan dokumen: " . (($conn->error) ? htmlspecialchars($conn->error) : "Kesalahan tidak diketahui.");
                            if (file_exists($target_file)) unlink($target_file);
                        }
                    } else {
                         $error_message = "Koneksi database hilang saat menyimpan dokumen.";
                         if (file_exists($target_file)) unlink($target_file);
                    }
                } else {
                    $error_message = "Maaf, terjadi kesalahan teknis saat memindahkan file Anda.";
                }
            }
        }
    }
}

// Daftar jenis dokumen sesuai ENUM di tabel `dokumen_kp`
$enum_jenis_dokumen = [
    'ktm' => 'KTM (Kartu Tanda Mahasiswa)',
    'khs' => 'KHS (Kartu Hasil Studi)',
    'proposal_kp' => 'Proposal KP',
    'surat_pengantar_kp' => 'Surat Pengantar KP (dari Prodi)',
    'surat_balasan_perusahaan' => 'Surat Balasan Perusahaan',
    'laporan_kemajuan' => 'Laporan Kemajuan',
    'draft_laporan_akhir' => 'Draft Laporan Akhir',
    'laporan_akhir_final' => 'Laporan Akhir Final (Revisi)',
    'lembar_pengesahan' => 'Lembar Pengesahan',
    'sertifikat_kp' => 'Sertifikat KP dari Perusahaan',
    'form_penilaian_perusahaan' => 'Form Penilaian Perusahaan',
    'form_penilaian_dosen' => 'Form Penilaian Dosen',
    'lainnya' => 'Lainnya'
];


// Set judul halaman dan sertakan header
$page_title = "Upload Dokumen Kerja Praktek";
require_once '../includes/header.php';
?>

<div class="page-layout-wrapper">

    <?php require_once '../includes/sidebar_mahasiswa.php'; ?>

    <main class="main-content-area">
        <div class="form-container upload-dokumen-form">
            <h1><?php echo htmlspecialchars($page_title); ?></h1>
            <?php if ($id_pengajuan_url !== null): ?>
                <p>Anda akan mengunggah dokumen untuk pengajuan KP: <strong>"<?php echo htmlspecialchars($judul_kp_konteks); ?>"</strong> (ID: <?php echo $id_pengajuan_url; ?>).</p>
            <?php else: ?>
                <p>Pilih pengajuan KP terlebih dahulu dari halaman detail pengajuan untuk mengunggah dokumen.</p>
            <?php endif; ?>
            <hr>

            <?php if (!empty($success_message)): ?>
                <div class="message success">
                    <p><?php echo htmlspecialchars($success_message); ?></p>
                    <?php if ($id_pengajuan_url): ?>
                    <p><a href="/KP/mahasiswa/pengajuan_kp_detail.php?id=<?php echo $id_pengajuan_url; ?>">Kembali ke Detail Pengajuan</a></p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            <?php if (!empty($error_message)): ?>
                <div class="message error">
                    <p><?php echo htmlspecialchars($error_message); ?></p>
                </div>
            <?php endif; ?>

            <?php if ($id_pengajuan_url !== null && !$success_message): // Tampilkan form hanya jika ID pengajuan valid dan belum ada pesan sukses ?>
            <form action="/KP/mahasiswa/dokumen_upload.php?id_pengajuan=<?php echo $id_pengajuan_url; ?>" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="id_pengajuan" value="<?php echo $id_pengajuan_url; ?>">

                <div class="form-group">
                    <label for="nama_dokumen">Nama/Judul Dokumen (*):</label>
                    <input type="text" id="nama_dokumen" name="nama_dokumen" value="<?php echo isset($_POST['nama_dokumen']) ? htmlspecialchars($_POST['nama_dokumen']) : ''; ?>" required>
                    <small>Contoh: Proposal KP Final, Scan KHS Semester 5, Sertifikat Pelatihan</small>
                </div>

                <div class="form-group">
                    <label for="jenis_dokumen">Jenis Dokumen (*):</label>
                    <select id="jenis_dokumen" name="jenis_dokumen" required>
                        <option value="">-- Pilih Jenis Dokumen --</option>
                        <?php foreach ($enum_jenis_dokumen as $value => $text): ?>
                            <option value="<?php echo $value; ?>" <?php echo (isset($_POST['jenis_dokumen']) && $_POST['jenis_dokumen'] == $value) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($text); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="file_dokumen">Pilih File Dokumen (*):</label>
                    <input type="file" id="file_dokumen" name="file_dokumen" required accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.zip,.rar">
                    <small>Format yang diizinkan: PDF, DOC, DOCX, JPG, PNG, ZIP, RAR. Maksimal 10MB.</small>
                </div>

                <div class="form-group">
                    <label for="deskripsi_dokumen">Deskripsi Tambahan (Opsional):</label>
                    <textarea id="deskripsi_dokumen" name="deskripsi_dokumen" rows="4"><?php echo isset($_POST['deskripsi_dokumen']) ? htmlspecialchars($_POST['deskripsi_dokumen']) : ''; ?></textarea>
                </div>

                <div class="form-actions">
                    <button type="submit" name="submit_dokumen" class="btn btn-primary">Upload Dokumen</button>
                    <a href="/KP/mahasiswa/pengajuan_kp_detail.php?id=<?php echo $id_pengajuan_url; ?>" class="btn btn-secondary">Batal</a>
                </div>
            </form>
            <?php endif; ?>

        </div>
    </main>

</div>

<style>
    /* Asumsikan CSS umum dari header, sidebar, form-container sudah ada */
    .upload-dokumen-form h1 { margin-top: 0; margin-bottom: 10px; }
    .upload-dokumen-form hr { margin-bottom: 25px; }
    .upload-dokumen-form p { margin-bottom: 20px; }

    /* Styling spesifik jika ada, atau pastikan styling form global sudah mencakup */
    .form-group { margin-bottom: 18px; }
    .form-group label { display: block; font-weight: bold; margin-bottom: 6px; }
    .form-group input[type="text"],
    .form-group input[type="file"],
    .form-group textarea,
    .form-group select { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
    .form-group small { display: block; font-size: 0.85em; color: #666; margin-top: 4px; }
    .form-actions { margin-top: 20px; padding-top: 20px; border-top: 1px solid #eee; }
    .form-actions .btn { margin-right: 10px; }

    .message { padding: 10px 15px; margin-bottom: 20px; border-radius: 5px; border: 1px solid transparent; }
    .message.success { background-color: #d4edda; color: #155724; border-color: #c3e6cb; }
    .message.success a { color: #0f5132; font-weight:bold; }
    .message.error { background-color: #f8d7da; color: #721c24; border-color: #f5c6cb; }
    /* Tombol (pastikan konsisten dengan global CSS) */
    .btn-primary { color: #fff; background-color: #007bff; border-color: #007bff; }
    .btn-primary:hover { background-color: #0069d9; border-color: #0062cc; }
    .btn-secondary { color: #333; background-color: #f8f9fa; border: 1px solid #ccc; }
    .btn-secondary:hover { background-color: #e2e6ea; }
</style>

<?php
require_once '../includes/footer.php';

if (isset($conn) && ($conn instanceof mysqli)) {
    $conn->close();
}
?>