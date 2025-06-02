<?php
// /KP/mahasiswa/pengajuan_kp_form.php

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

// Sertakan file koneksi database
require_once '../config/db_connect.php';

$error_message = '';
$success_message = '';
$list_perusahaan = []; // Array untuk menyimpan daftar perusahaan

// 2. AMBIL DAFTAR PERUSAHAAN (yang statusnya 'active') DARI DATABASE UNTUK DROPDOWN
if ($conn && ($conn instanceof mysqli)) {
    $sql_perusahaan = "SELECT id_perusahaan, nama_perusahaan FROM perusahaan WHERE status_akun = 'active' ORDER BY nama_perusahaan ASC";
    $result_perusahaan = $conn->query($sql_perusahaan);
    if ($result_perusahaan && $result_perusahaan->num_rows > 0) {
        while ($row = $result_perusahaan->fetch_assoc()) {
            $list_perusahaan[] = $row;
        }
    }
    // Tidak perlu menutup koneksi di sini jika akan dipakai lagi di bawah
} else {
    $error_message = "Koneksi database gagal atau tidak valid. Tidak dapat memuat daftar perusahaan.";
}

// 3. PROSES PENGAJUAN KP JIKA FORM DISUBMIT (METHOD POST)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_pengajuan'])) {
    $judul_kp = trim($_POST['judul_kp']);
    $id_perusahaan_diajukan = !empty($_POST['id_perusahaan']) ? (int)$_POST['id_perusahaan'] : null;
    // Jika mahasiswa input nama perusahaan baru yang belum ada di list:
    $nama_perusahaan_baru = trim($_POST['nama_perusahaan_baru']);
    $deskripsi_kp = trim($_POST['deskripsi_kp']);
    $tanggal_mulai_rencana = $_POST['tanggal_mulai_rencana'];
    $tanggal_selesai_rencana = $_POST['tanggal_selesai_rencana'];

    // Variabel untuk file upload
    $proposal_file_path = null;
    $upload_ok = 1; // Status upload file

    // Validasi Dasar (Contoh)
    if (empty($judul_kp) || (empty($id_perusahaan_diajukan) && empty($nama_perusahaan_baru)) || empty($deskripsi_kp) || empty($tanggal_mulai_rencana) || empty($tanggal_selesai_rencana)) {
        $error_message = "Semua field yang ditandai (*) wajib diisi.";
        $upload_ok = 0;
    }

    if (strtotime($tanggal_selesai_rencana) <= strtotime($tanggal_mulai_rencana)) {
        $error_message = "Tanggal selesai rencana harus setelah tanggal mulai rencana.";
        $upload_ok = 0;
    }

    // Penanganan File Upload Proposal (jika ada file yang diupload)
    if ($upload_ok && isset($_FILES["proposal_kp"]) && $_FILES["proposal_kp"]["error"] == 0) {
        $target_dir = "../uploads/dokumen_kp/"; // Path relatif dari file PHP ini
        // Buat nama file unik untuk menghindari penimpaan, misal: NIM_proposal_timestamp.pdf
        $file_extension = strtolower(pathinfo($_FILES["proposal_kp"]["name"], PATHINFO_EXTENSION));
        $unique_filename = $nim_mahasiswa . "_proposal_" . time() . "." . $file_extension;
        $target_file = $target_dir . $unique_filename;

        // Cek tipe file (misalnya hanya PDF atau DOCX)
        $allowed_types = ['pdf', 'doc', 'docx'];
        if (!in_array($file_extension, $allowed_types)) {
            $error_message = "Maaf, hanya file PDF, DOC, & DOCX yang diizinkan untuk proposal.";
            $upload_ok = 0;
        }

        // Cek ukuran file (misalnya maksimal 5MB)
        if ($upload_ok && $_FILES["proposal_kp"]["size"] > 5000000) { // 5MB dalam bytes
            $error_message = "Maaf, ukuran file proposal terlalu besar (maksimal 5MB).";
            $upload_ok = 0;
        }

        // Jika semua OK, coba upload file
        if ($upload_ok) {
            if (move_uploaded_file($_FILES["proposal_kp"]["tmp_name"], $target_file)) {
                $proposal_file_path = "uploads/dokumen_kp/" . $unique_filename; // Path untuk disimpan di DB (relatif dari root /KP/)
            } else {
                $error_message = "Maaf, terjadi kesalahan saat mengupload file proposal Anda.";
                $upload_ok = 0; // Set agar tidak lanjut ke insert DB jika upload gagal
            }
        }
    } elseif (isset($_FILES["proposal_kp"]) && $_FILES["proposal_kp"]["error"] != UPLOAD_ERR_NO_FILE && $_FILES["proposal_kp"]["error"] != 0) {
        // Jika ada file dipilih tapi error selain "tidak ada file"
        $error_message = "Terjadi error pada file proposal yang diupload (Error code: ".$_FILES["proposal_kp"]["error"].").";
        $upload_ok = 0;
    }
    // Jika tidak ada file proposal diupload tapi field lain valid, $upload_ok tetap 1 (jika proposal tidak wajib)
    // Untuk contoh ini, kita asumsikan proposal adalah bagian dari pengajuan awal. Jika wajib:
    // if ($upload_ok && $proposal_file_path === null && (isset($_FILES["proposal_kp"]) && $_FILES["proposal_kp"]["error"] == UPLOAD_ERR_NO_FILE) ) {
    //    $error_message = "File proposal KP wajib diunggah.";
    //    $upload_ok = 0;
    // }


    // Jika tidak ada error validasi dan file (jika ada) berhasil di-upload
    if (empty($error_message) && $upload_ok) {
        if ($conn && ($conn instanceof mysqli)) {
            $conn->begin_transaction(); // Mulai transaksi

            try {
                // Jika mahasiswa input nama perusahaan baru, kita bisa tambahkan ke tabel perusahaan
                // dengan status 'pending_approval' atau 'diajukan_mahasiswa',
                // atau biarkan admin yang handle. Untuk sederhana, kita catat saja namanya di deskripsi KP
                // atau buat kolom baru di pengajuan_kp untuk "nama_perusahaan_diajukan_alternatif" jika id_perusahaan NULL.
                // Untuk contoh ini, jika id_perusahaan tidak dipilih dan nama_perusahaan_baru diisi, kita set id_perusahaan jadi NULL
                // dan admin perlu menindaklanjuti nama_perusahaan_baru.
                if (!empty($nama_perusahaan_baru) && empty($id_perusahaan_diajukan)) {
                    // Disini bisa ada logika untuk memasukkan $nama_perusahaan_baru ke tabel perusahaan
                    // atau cukup set $id_perusahaan_diajukan jadi null dan andalkan admin.
                    // Untuk sekarang, kita biarkan id_perusahaan null jika memilih input manual.
                    $id_perusahaan_diajukan = null;
                    // Tambahkan nama perusahaan baru ke deskripsi agar tidak hilang infonya
                    $deskripsi_kp .= "\n\n[Perusahaan diajukan (baru): " . $nama_perusahaan_baru . "]";
                }


                // 1. Insert ke tabel pengajuan_kp
                $tanggal_pengajuan = date("Y-m-d"); // Tanggal hari ini
                // Status awal bisa 'diajukan_mahasiswa' atau 'draft'
                $status_pengajuan = 'diajukan_mahasiswa';

                $sql_insert_pengajuan = "INSERT INTO pengajuan_kp (nim, id_perusahaan, judul_kp, deskripsi_kp, tanggal_pengajuan, tanggal_mulai_rencana, tanggal_selesai_rencana, status_pengajuan) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt_insert_pengajuan = $conn->prepare($sql_insert_pengajuan);
                if (!$stmt_insert_pengajuan) throw new Exception("Prepare statement pengajuan gagal: " . $conn->error);

                $stmt_insert_pengajuan->bind_param("sissssss", $nim_mahasiswa, $id_perusahaan_diajukan, $judul_kp, $deskripsi_kp, $tanggal_pengajuan, $tanggal_mulai_rencana, $tanggal_selesai_rencana, $status_pengajuan);

                if (!$stmt_insert_pengajuan->execute()) throw new Exception("Eksekusi statement pengajuan gagal: " . $stmt_insert_pengajuan->error);

                $id_pengajuan_baru = $conn->insert_id; // Ambil ID dari pengajuan yang baru saja di-insert
                $stmt_insert_pengajuan->close();

                // 2. Jika ada file proposal yang diupload, insert ke tabel dokumen_kp
                if ($proposal_file_path !== null && $id_pengajuan_baru > 0) {
                    $uploader_id = $nim_mahasiswa;
                    $tipe_uploader = 'mahasiswa';
                    $nama_dokumen = "Proposal KP - " . $judul_kp;
                    $jenis_dokumen = 'proposal_kp'; // Sesuai enum di tabel dokumen_kp
                    $status_verifikasi_dokumen = 'pending'; // Status awal

                    $sql_insert_dokumen = "INSERT INTO dokumen_kp (id_pengajuan, uploader_id, tipe_uploader, nama_dokumen, jenis_dokumen, file_path, status_verifikasi_dokumen) VALUES (?, ?, ?, ?, ?, ?, ?)";
                    $stmt_insert_dokumen = $conn->prepare($sql_insert_dokumen);
                    if (!$stmt_insert_dokumen) throw new Exception("Prepare statement dokumen gagal: " . $conn->error);

                    $stmt_insert_dokumen->bind_param("issssss", $id_pengajuan_baru, $uploader_id, $tipe_uploader, $nama_dokumen, $jenis_dokumen, $proposal_file_path, $status_verifikasi_dokumen);

                    if (!$stmt_insert_dokumen->execute()) throw new Exception("Eksekusi statement dokumen gagal: " . $stmt_insert_dokumen->error);
                    $stmt_insert_dokumen->close();
                }

                $conn->commit(); // Jika semua query berhasil, commit transaksi
                $success_message = "Pengajuan Kerja Praktek Anda berhasil dikirim!";
                // Kosongkan variabel POST agar form bersih setelah sukses (opsional, atau redirect)
                $_POST = array();


            } catch (Exception $e) {
                $conn->rollback(); // Jika ada error, rollback transaksi
                $error_message = "Terjadi kesalahan saat menyimpan data: " . htmlspecialchars($e->getMessage());
            }
        } else {
            $error_message = "Koneksi database hilang saat akan menyimpan data.";
        }
    }
}


// Set judul halaman dan sertakan header
$page_title = "Formulir Pengajuan Kerja Praktek";
require_once '../includes/header.php';
?>

<div class="page-layout-wrapper">

    <?php require_once '../includes/sidebar_mahasiswa.php'; ?>

    <main class="main-content-area">
        <div class="form-container pengajuan-kp-form">
            <h1><?php echo htmlspecialchars($page_title); ?></h1>
            <p>Silakan isi formulir di bawah ini untuk mengajukan Kerja Praktek. Field yang ditandai (*) wajib diisi.</p>
            <hr>

            <?php if (!empty($success_message)): ?>
                <div class="message success">
                    <p><?php echo htmlspecialchars($success_message); ?></p>
                    <p><a href="/KP/mahasiswa/pengajuan_kp_view.php">Lihat status pengajuan Anda</a></p>
                </div>
            <?php endif; ?>
            <?php if (!empty($error_message)): ?>
                <div class="message error">
                    <p><?php echo htmlspecialchars($error_message); ?></p>
                </div>
            <?php endif; ?>

            <?php if (!$success_message): // Hanya tampilkan form jika belum sukses atau jika ada error ?>
            <form action="/KP/mahasiswa/pengajuan_kp_form.php" method="POST" enctype="multipart/form-data">
                <fieldset>
                    <legend>Informasi Proposal Kerja Praktek</legend>
                    <div class="form-group">
                        <label for="judul_kp">Judul Kerja Praktek (*):</label>
                        <input type="text" id="judul_kp" name="judul_kp" value="<?php echo isset($_POST['judul_kp']) ? htmlspecialchars($_POST['judul_kp']) : ''; ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="deskripsi_kp">Deskripsi Singkat KP (*):</label>
                        <textarea id="deskripsi_kp" name="deskripsi_kp" rows="5" required><?php echo isset($_POST['deskripsi_kp']) ? htmlspecialchars($_POST['deskripsi_kp']) : ''; ?></textarea>
                    </div>
                </fieldset>

                <fieldset>
                    <legend>Informasi Perusahaan Tujuan</legend>
                    <div class="form-group">
                        <label for="id_perusahaan">Pilih Perusahaan (jika sudah terdaftar) (*):</label>
                        <select id="id_perusahaan" name="id_perusahaan" onchange="toggleNamaPerusahaanBaru(this.value)">
                            <option value="">-- Pilih Perusahaan --</option>
                            <?php foreach ($list_perusahaan as $perusahaan): ?>
                                <option value="<?php echo $perusahaan['id_perusahaan']; ?>" <?php echo (isset($_POST['id_perusahaan']) && $_POST['id_perusahaan'] == $perusahaan['id_perusahaan']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($perusahaan['nama_perusahaan']); ?>
                                </option>
                            <?php endforeach; ?>
                             <option value="BARU">-- Saya akan input nama perusahaan baru --</option>
                        </select>
                    </div>
                    <div class="form-group" id="input_nama_perusahaan_baru" style="<?php echo (isset($_POST['id_perusahaan']) && $_POST['id_perusahaan'] == 'BARU') || (empty($_POST['id_perusahaan']) && !empty($_POST['nama_perusahaan_baru'])) ? 'display:block;' : 'display:none;'; ?>">
                        <label for="nama_perusahaan_baru">Nama Perusahaan Baru (jika tidak ada di daftar) (*):</label>
                        <input type="text" id="nama_perusahaan_baru" name="nama_perusahaan_baru" value="<?php echo isset($_POST['nama_perusahaan_baru']) ? htmlspecialchars($_POST['nama_perusahaan_baru']) : ''; ?>">
                        <small>Isi jika perusahaan Anda belum terdaftar di sistem. Admin akan memverifikasi.</small>
                    </div>
                </fieldset>

                <fieldset>
                    <legend>Rencana Pelaksanaan</legend>
                    <div class="form-group">
                        <label for="tanggal_mulai_rencana">Tanggal Mulai Rencana (*):</label>
                        <input type="date" id="tanggal_mulai_rencana" name="tanggal_mulai_rencana" value="<?php echo isset($_POST['tanggal_mulai_rencana']) ? htmlspecialchars($_POST['tanggal_mulai_rencana']) : ''; ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="tanggal_selesai_rencana">Tanggal Selesai Rencana (*):</label>
                        <input type="date" id="tanggal_selesai_rencana" name="tanggal_selesai_rencana" value="<?php echo isset($_POST['tanggal_selesai_rencana']) ? htmlspecialchars($_POST['tanggal_selesai_rencana']) : ''; ?>" required>
                    </div>
                </fieldset>

                <fieldset>
                    <legend>Dokumen Pendukung</legend>
                    <div class="form-group">
                        <label for="proposal_kp">Upload File Proposal KP (PDF/DOC/DOCX, maks. 5MB):</label>
                        <input type="file" id="proposal_kp" name="proposal_kp" accept=".pdf,.doc,.docx">
                        <small>Proposal awal untuk ditinjau. Anda bisa mengunggah revisi nanti.</small>
                    </div>
                    </fieldset>

                <div class="form-actions">
                    <button type="submit" name="submit_pengajuan" class="btn btn-primary">Kirim Pengajuan</button>
                    <button type="reset" class="btn btn-secondary">Reset Form</button>
                </div>
            </form>
            <?php endif; // Penutup if (!$success_message) ?>

        </div>
    </main>

</div>

<script>
// JavaScript sederhana untuk menampilkan/menyembunyikan input nama perusahaan baru
function toggleNamaPerusahaanBaru(selectedValue) {
    var inputNamaPerusahaanBaru = document.getElementById('input_nama_perusahaan_baru');
    var fieldNamaPerusahaanBaru = document.getElementById('nama_perusahaan_baru');
    if (selectedValue === 'BARU') {
        inputNamaPerusahaanBaru.style.display = 'block';
        fieldNamaPerusahaanBaru.required = true; // Jadikan wajib jika opsi BARU dipilih
    } else {
        inputNamaPerusahaanBaru.style.display = 'none';
        fieldNamaPerusahaanBaru.required = false; // Tidak wajib jika perusahaan dipilih dari daftar
        fieldNamaPerusahaanBaru.value = ''; // Kosongkan nilainya
    }
}
// Panggil saat load untuk inisialisasi jika ada old value dari POST
document.addEventListener('DOMContentLoaded', function() {
    var selectPerusahaan = document.getElementById('id_perusahaan');
    if (selectPerusahaan) { // Pastikan elemen ada
        toggleNamaPerusahaanBaru(selectPerusahaan.value);
    }
});
</script>

<style>
    /* Asumsikan CSS untuk .page-layout-wrapper, .sidebar-mahasiswa, .main-content-area sudah ada */
    .form-container h1 { margin-top: 0; margin-bottom: 10px; font-size: 1.8em; }
    .form-container hr { margin-bottom: 25px; }
    .form-container p { margin-bottom: 20px; font-size:0.95em; color:#555; }

    .pengajuan-kp-form fieldset {
        border: 1px solid #ddd;
        padding: 20px;
        margin-bottom: 25px;
        border-radius: 5px;
        background-color: #fcfcfc;
    }
    .pengajuan-kp-form legend {
        font-weight: bold;
        color: #007bff;
        padding: 0 10px;
        font-size: 1.1em;
    }
    .form-group {
        margin-bottom: 18px;
    }
    .form-group label {
        display: block;
        font-weight: bold;
        margin-bottom: 6px;
        color: #495057;
    }
    .form-group input[type="text"],
    .form-group input[type="date"],
    .form-group input[type="file"],
    .form-group textarea,
    .form-group select {
        width: 100%;
        padding: 10px 12px;
        border: 1px solid #ced4da;
        border-radius: 4px;
        box-sizing: border-box;
        font-size: 1em;
        background-color: #fff; /* Pastikan input terlihat jelas */
    }
    .form-group input[type="text"]:focus,
    .form-group input[type="date"]:focus,
    .form-group input[type="file"]:focus,
    .form-group textarea:focus,
    .form-group select:focus {
        border-color: #80bdff;
        box-shadow: 0 0 0 0.2rem rgba(0,123,255,.25);
        outline: none;
    }
    .form-group textarea {
        min-height: 100px;
        resize: vertical;
    }
    .form-group small {
        display: block;
        font-size: 0.85em;
        color: #6c757d;
        margin-top: 4px;
    }
    .form-actions {
        margin-top: 20px;
        padding-top: 20px;
        border-top: 1px solid #eee;
    }
    .form-actions .btn {
        margin-right: 10px;
    }
    /* Styling untuk message (success/error) dan tombol sudah ada di contoh sebelumnya atau di header.php */
    /* Pastikan konsisten */
    .message { padding: 10px 15px; margin-bottom: 20px; border-radius: 5px; border: 1px solid transparent; }
    .message.success { background-color: #d4edda; color: #155724; border-color: #c3e6cb; }
    .message.success a { color: #0f5132; font-weight:bold; }
    .message.error { background-color: #f8d7da; color: #721c24; border-color: #f5c6cb; }

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