<?php
// /KP/mahasiswa/logbook_form.php

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
$error_message = '';
$success_message = '';
$active_kp_list = []; // Untuk menyimpan daftar KP mahasiswa yang aktif
$selected_id_pengajuan = null; // ID Pengajuan yang akan diisi logbooknya

// Sertakan file koneksi database
require_once '../config/db_connect.php';

// 2. AMBIL DATA PENGAJUAN KP YANG STATUSNYA 'kp_berjalan' UNTUK MAHASISWA INI
if ($conn && ($conn instanceof mysqli)) {
    $sql_active_kp = "SELECT id_pengajuan, judul_kp 
                      FROM pengajuan_kp 
                      WHERE nim = ? AND status_pengajuan = 'kp_berjalan' 
                      ORDER BY tanggal_mulai_rencana DESC"; // Ambil yang paling baru jika ada >1
    $stmt_active_kp = $conn->prepare($sql_active_kp);
    if ($stmt_active_kp) {
        $stmt_active_kp->bind_param("s", $nim_mahasiswa);
        $stmt_active_kp->execute();
        $result_active_kp = $stmt_active_kp->get_result();
        while ($row = $result_active_kp->fetch_assoc()) {
            $active_kp_list[] = $row;
        }
        $stmt_active_kp->close();

        // Jika hanya ada satu KP aktif, langsung pilih itu
        if (count($active_kp_list) === 1) {
            $selected_id_pengajuan = $active_kp_list[0]['id_pengajuan'];
        }
    } else {
        $error_message = "Gagal mengambil data KP aktif: " . $conn->error;
    }
} else {
    $error_message = "Koneksi database gagal.";
}


// 3. PROSES PENYIMPANAN LOGBOOK JIKA FORM DISUBMIT
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_logbook'])) {
    $id_pengajuan_logbook = isset($_POST['id_pengajuan']) ? (int)$_POST['id_pengajuan'] : null;
    // Jika $selected_id_pengajuan sudah ada dari atas (karena cuma 1 KP aktif), pakai itu.
    if ($selected_id_pengajuan && !$id_pengajuan_logbook) {
        $id_pengajuan_logbook = $selected_id_pengajuan;
    }

    $tanggal_kegiatan = $_POST['tanggal_kegiatan'];
    $jam_mulai = !empty($_POST['jam_mulai']) ? $_POST['jam_mulai'] : null;
    $jam_selesai = !empty($_POST['jam_selesai']) ? $_POST['jam_selesai'] : null;
    $uraian_kegiatan = trim($_POST['uraian_kegiatan']);

    // Validasi
    if (empty($id_pengajuan_logbook)) {
        $error_message = "Kerja Praktek aktif tidak dipilih atau tidak ditemukan.";
    } elseif (empty($tanggal_kegiatan)) {
        $error_message = "Tanggal kegiatan wajib diisi.";
    } elseif (empty($uraian_kegiatan)) {
        $error_message = "Uraian kegiatan wajib diisi.";
    } elseif ($jam_mulai && $jam_selesai && strtotime($jam_selesai) <= strtotime($jam_mulai)) {
        $error_message = "Jam selesai harus setelah jam mulai.";
    } else {
        // Cek apakah KP yang dipilih memang milik mahasiswa dan statusnya 'kp_berjalan'
        $can_proceed = false;
        if ($conn && ($conn instanceof mysqli)) {
            $sql_verify_kp = "SELECT id_pengajuan FROM pengajuan_kp WHERE id_pengajuan = ? AND nim = ? AND status_pengajuan = 'kp_berjalan'";
            $stmt_verify = $conn->prepare($sql_verify_kp);
            $stmt_verify->bind_param("is", $id_pengajuan_logbook, $nim_mahasiswa);
            $stmt_verify->execute();
            $stmt_verify->store_result();
            if ($stmt_verify->num_rows === 1) {
                $can_proceed = true;
            } else {
                $error_message = "Pengajuan KP yang dipilih tidak valid atau bukan milik Anda/tidak aktif.";
            }
            $stmt_verify->close();
        }


        if ($can_proceed && empty($error_message)) {
            if ($conn && ($conn instanceof mysqli)) {
                $status_verifikasi_logbook = 'pending'; // Default status

                $sql_insert_logbook = "INSERT INTO logbook (id_pengajuan, tanggal_kegiatan, jam_mulai, jam_selesai, uraian_kegiatan, status_verifikasi_logbook) 
                                       VALUES (?, ?, ?, ?, ?, ?)";
                $stmt_insert = $conn->prepare($sql_insert_logbook);
                if ($stmt_insert) {
                    $stmt_insert->bind_param("isssss",
                        $id_pengajuan_logbook,
                        $tanggal_kegiatan,
                        $jam_mulai,
                        $jam_selesai,
                        $uraian_kegiatan,
                        $status_verifikasi_logbook
                    );
                    if ($stmt_insert->execute()) {
                        $success_message = "Catatan logbook untuk tanggal " . date("d M Y", strtotime($tanggal_kegiatan)) . " berhasil disimpan!";
                        // Kosongkan uraian untuk entri berikutnya, tapi tanggal mungkin mau dipertahankan atau +1 hari
                        $_POST['uraian_kegiatan'] = '';
                        // Atau redirect ke halaman view logbook
                        // header("Location: /KP/mahasiswa/logbook_view.php?id_pengajuan=" . $id_pengajuan_logbook . "&success=1");
                        // exit();
                    } else {
                        $error_message = "Gagal menyimpan logbook: " . $stmt_insert->error;
                    }
                    $stmt_insert->close();
                } else {
                    $error_message = "Gagal menyiapkan statement penyimpanan logbook: " . $conn->error;
                }
            } else {
                $error_message = "Koneksi database hilang saat menyimpan logbook.";
            }
        }
    }
}


// Set judul halaman dan sertakan header
$page_title = "Isi Logbook Kegiatan Kerja Praktek";
require_once '../includes/header.php';
?>

<div class="page-layout-wrapper">
    <main class="main-content-area">
        <div class="form-container logbook-form-container">
            <h1><?php echo htmlspecialchars($page_title); ?></h1>
            <p>Catat kegiatan harian atau mingguan Anda selama pelaksanaan Kerja Praktek di sini.</p>
            <hr>

            <?php if (!empty($success_message)): ?>
                <div class="message success"><p><?php echo htmlspecialchars($success_message); ?></p></div>
            <?php endif; ?>
            <?php if (!empty($error_message)): ?>
                <div class="message error"><p><?php echo htmlspecialchars($error_message); ?></p></div>
            <?php endif; ?>

            <?php if (empty($active_kp_list) && empty($error_message)): ?>
                <div class="message warning">
                    <p>Anda saat ini tidak memiliki pengajuan Kerja Praktek dengan status 'KP Berjalan'.</p>
                    <p>Anda hanya dapat mengisi logbook jika KP Anda sudah aktif dan berjalan. Silakan periksa status pengajuan KP Anda atau hubungi Admin Prodi/Dosen Pembimbing.</p>
                    <p><a href="/KP/mahasiswa/pengajuan_kp_view.php" class="btn btn-info">Lihat Pengajuan KP Saya</a></p>
                </div>
            <?php elseif (!empty($active_kp_list)): ?>
                <form action="/KP/mahasiswa/logbook_form.php" method="POST">
                    <?php if (count($active_kp_list) > 1): // Jika ada lebih dari 1 KP aktif, tampilkan dropdown ?>
                        <div class="form-group">
                            <label for="id_pengajuan">Pilih Pengajuan KP (*):</label>
                            <select id="id_pengajuan" name="id_pengajuan" required>
                                <option value="">-- Pilih KP Aktif --</option>
                                <?php foreach ($active_kp_list as $kp): ?>
                                    <option value="<?php echo $kp['id_pengajuan']; ?>" <?php echo (isset($_POST['id_pengajuan']) && $_POST['id_pengajuan'] == $kp['id_pengajuan']) ? 'selected' : ($selected_id_pengajuan == $kp['id_pengajuan'] && !isset($_POST['id_pengajuan']) ? 'selected' : ''); ?>>
                                        <?php echo htmlspecialchars($kp['judul_kp']); ?> (ID: <?php echo $kp['id_pengajuan']; ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php elseif (count($active_kp_list) === 1 && $selected_id_pengajuan): // Jika hanya satu, gunakan hidden input ?>
                        <input type="hidden" name="id_pengajuan" value="<?php echo $selected_id_pengajuan; ?>">
                        <p>Anda mengisi logbook untuk KP: <strong><?php echo htmlspecialchars($active_kp_list[0]['judul_kp']); ?></strong></p>
                    <?php endif; ?>

                    <div class="form-group">
                        <label for="tanggal_kegiatan">Tanggal Kegiatan (*):</label>
                        <input type="date" id="tanggal_kegiatan" name="tanggal_kegiatan" value="<?php echo isset($_POST['tanggal_kegiatan']) ? htmlspecialchars($_POST['tanggal_kegiatan']) : date('Y-m-d'); ?>" required>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="jam_mulai">Jam Mulai (Opsional):</label>
                            <input type="time" id="jam_mulai" name="jam_mulai" value="<?php echo isset($_POST['jam_mulai']) ? htmlspecialchars($_POST['jam_mulai']) : ''; ?>">
                        </div>
                        <div class="form-group">
                            <label for="jam_selesai">Jam Selesai (Opsional):</label>
                            <input type="time" id="jam_selesai" name="jam_selesai" value="<?php echo isset($_POST['jam_selesai']) ? htmlspecialchars($_POST['jam_selesai']) : ''; ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="uraian_kegiatan">Uraian Kegiatan (*):</label>
                        <textarea id="uraian_kegiatan" name="uraian_kegiatan" rows="8" required placeholder="Jelaskan secara detail kegiatan yang Anda lakukan..."><?php echo isset($_POST['uraian_kegiatan']) ? htmlspecialchars($_POST['uraian_kegiatan']) : ''; ?></textarea>
                    </div>

                    <div class="form-actions">
                        <button type="submit" name="submit_logbook" class="btn btn-primary">Simpan Catatan Logbook</button>
                        <button type="reset" class="btn btn-secondary">Reset Form</button>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </main>
</div>

<style>
    /* Asumsikan CSS umum dari header, sidebar, form-container, fieldset, legend, form-group, message, btn sudah ada */
    .logbook-form-container h1 { margin-top: 0; margin-bottom: 10px; }
    .logbook-form-container hr { margin-bottom: 20px; }
    .logbook-form-container p { margin-bottom: 15px; }

    .form-row {
        display: flex;
        gap: 20px; /* Jarak antar field dalam satu baris */
    }
    .form-row .form-group {
        flex: 1; /* Agar kedua field berbagi ruang secara merata */
    }
    .form-group small { display: block; font-size: 0.85em; color: #6c757d; margin-top: 4px; }
    .form-actions { margin-top: 20px; padding-top: 20px; border-top: 1px solid #eee; }
    .form-actions .btn { margin-right: 10px; }
    .message.warning { background-color: #fff3cd; color: #856404; border-color: #ffeeba; }

</style>

<?php
require_once '../includes/footer.php';

if (isset($conn) && ($conn instanceof mysqli)) {
    $conn->close();
}
?>