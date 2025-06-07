<?php
// /KP/dosen/pengajuan_verifikasi_detail.php

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
$pengajuan_detail = null;
$mahasiswa_info = null;
$dokumen_terkait = [];
$error_message = '';
$success_message = '';

// 2. VALIDASI DAN AMBIL ID PENGAJUAN DARI URL
if (isset($_GET['id_pengajuan']) && filter_var($_GET['id_pengajuan'], FILTER_VALIDATE_INT)) {
    $id_pengajuan_url = (int)$_GET['id_pengajuan'];
} else {
    $error_message = "ID Pengajuan tidak valid atau tidak ditemukan.";
}

// Sertakan file koneksi database
require_once '../config/db_connect.php';

// 3. PROSES TINDAKAN VERIFIKASI JIKA FORM DISUBMIT (METHOD POST)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_verifikasi']) && $id_pengajuan_url !== null && empty($error_message)) {
    $new_status = $_POST['status_verifikasi'];
    $catatan_dosen_input = trim($_POST['catatan_dosen']);
    $id_pengajuan_form = (int)$_POST['id_pengajuan'];

    // Validasi dasar
    if ($id_pengajuan_form !== $id_pengajuan_url) {
        $error_message = "Kesalahan: ID Pengajuan tidak cocok.";
    } elseif (empty($new_status)) {
        $error_message = "Silakan pilih status verifikasi.";
    }
    // Daftar status yang valid untuk diubah oleh dosen dari form ini
    $allowed_new_statuses = ['disetujui_dospem', 'ditolak_dospem']; // Sesuaikan jika ada status lain seperti 'revisi_diperlukan'
    if (!in_array($new_status, $allowed_new_statuses)) {
        $error_message = "Status verifikasi yang dipilih tidak valid.";
    }

    if (empty($error_message)) {
        if ($conn && ($conn instanceof mysqli)) {
            // Pastikan dosen ini memang pembimbing untuk KP ini sebelum update
            // (atau memiliki hak untuk memverifikasi berdasarkan status awal)
            $sql_check_dosen = "SELECT status_pengajuan FROM pengajuan_kp WHERE id_pengajuan = ? AND nip_dosen_pembimbing_kp = ?";
            $stmt_check_dosen = $conn->prepare($sql_check_dosen);
            if ($stmt_check_dosen) {
                $stmt_check_dosen->bind_param("is", $id_pengajuan_url, $nip_dosen_login);
                $stmt_check_dosen->execute();
                $result_check = $stmt_check_dosen->get_result();
                if ($result_check->num_rows === 1) {
                    // $current_kp_data = $result_check->fetch_assoc();
                    // $current_status = $current_kp_data['status_pengajuan'];
                    // Logika tambahan: hanya boleh verifikasi jika statusnya 'diajukan_mahasiswa' atau 'diverifikasi_dospem'
                    // if (!in_array($current_status, ['diajukan_mahasiswa', 'diverifikasi_dospem'])) {
                    //    $error_message = "Pengajuan ini tidak dalam status yang dapat Anda verifikasi saat ini.";
                    // } else {
                        $sql_update = "UPDATE pengajuan_kp SET status_pengajuan = ?, catatan_dosen = CONCAT(IFNULL(catatan_dosen, ''), '\n\n[Update Dosen ".date("d M Y H:i")."]: ', ?) WHERE id_pengajuan = ? AND nip_dosen_pembimbing_kp = ?";
                        $stmt_update = $conn->prepare($sql_update);
                        if ($stmt_update) {
                            $stmt_update->bind_param("ssis", $new_status, $catatan_dosen_input, $id_pengajuan_url, $nip_dosen_login);
                            if ($stmt_update->execute()) {
                                if ($stmt_update->affected_rows > 0) {
                                    $success_message = "Status pengajuan berhasil diperbarui menjadi '" . ucfirst(str_replace('_', ' ', $new_status)) . "'.";
                                    // Mungkin redirect ke halaman list atau refresh data di halaman ini
                                    // header("Location: /KP/dosen/pengajuan_list.php?status_update=success");
                                    // exit();
                                } else {
                                    $error_message = "Tidak ada perubahan dilakukan, atau Anda tidak memiliki hak untuk mengubah status pengajuan ini.";
                                }
                            } else {
                                $error_message = "Gagal memperbarui status: " . (($stmt_update->error) ? htmlspecialchars($stmt_update->error) : "Kesalahan tidak diketahui.");
                            }
                            $stmt_update->close();
                        } else {
                            $error_message = "Gagal menyiapkan statement update: " . (($conn->error) ? htmlspecialchars($conn->error) : "Kesalahan tidak diketahui.");
                        }
                    // }
                } else {
                    $error_message = "Anda tidak ditugaskan untuk memverifikasi pengajuan KP ini atau pengajuan tidak ditemukan.";
                }
                $stmt_check_dosen->close();
            } else {
                 $error_message = "Gagal memeriksa otorisasi dosen: " . (($conn->error) ? htmlspecialchars($conn->error) : "Kesalahan tidak diketahui.");
            }
        } else {
            $error_message = "Koneksi database hilang saat akan update.";
        }
    }
}


// 4. AMBIL DATA DETAIL PENGAJUAN KP (SETELAH POTENSI UPDATE)
if ($id_pengajuan_url && $conn && ($conn instanceof mysqli)) {
    // Ambil ulang data pengajuan untuk memastikan data terbaru ditampilkan
    $sql_detail = "SELECT
                       pk.id_pengajuan, pk.nim AS nim_mahasiswa_kp, pk.judul_kp, pk.deskripsi_kp,
                       p.nama_perusahaan,
                       pk.tanggal_pengajuan, pk.tanggal_mulai_rencana, pk.tanggal_selesai_rencana,
                       pk.status_pengajuan, pk.nip_dosen_pembimbing_kp,
                       pk.catatan_admin, pk.catatan_dosen,
                       m.nama AS nama_mahasiswa, m.email AS email_mahasiswa, m.no_hp AS no_hp_mahasiswa, m.prodi, m.angkatan
                   FROM pengajuan_kp pk
                   JOIN mahasiswa m ON pk.nim = m.nim
                   LEFT JOIN perusahaan p ON pk.id_perusahaan = p.id_perusahaan
                   WHERE pk.id_pengajuan = ? 
                     AND (pk.nip_dosen_pembimbing_kp = ? OR EXISTS (SELECT 1 FROM dosen_pembimbing dp WHERE dp.nip = ? AND dp.status_akun = 'koordinator_kp'))"; // Dosen ybs atau Koordinator KP bisa lihat. Sesuaikan logika ini.

    $stmt_detail = $conn->prepare($sql_detail);
    if ($stmt_detail) {
        $stmt_detail->bind_param("iss", $id_pengajuan_url, $nip_dosen_login, $nip_dosen_login); // NIP Dosen login untuk kedua placeholder ?
        $stmt_detail->execute();
        $result_detail = $stmt_detail->get_result();
        if ($result_detail->num_rows === 1) {
            $pengajuan_detail = $result_detail->fetch_assoc();

            // Ambil dokumen terkait
            $sql_dokumen = "SELECT id_dokumen, nama_dokumen, jenis_dokumen, file_path, tanggal_upload, status_verifikasi_dokumen, catatan_verifikator
                            FROM dokumen_kp
                            WHERE id_pengajuan = ?
                            ORDER BY tanggal_upload DESC";
            $stmt_dokumen = $conn->prepare($sql_dokumen);
            if ($stmt_dokumen) {
                $stmt_dokumen->bind_param("i", $id_pengajuan_url);
                $stmt_dokumen->execute();
                $result_dokumen = $stmt_dokumen->get_result();
                while ($row_dokumen = $result_dokumen->fetch_assoc()) {
                    $dokumen_terkait[] = $row_dokumen;
                }
                $stmt_dokumen->close();
            } else {
                $error_message .= (empty($error_message)?"":"<br>") . "Gagal mengambil daftar dokumen.";
            }
        } else {
            // Jika $error_message masih kosong, berarti ID valid tapi tidak berhak atau tidak ketemu
            if(empty($error_message)) $error_message = "Detail pengajuan KP tidak ditemukan atau Anda tidak memiliki akses untuk melihatnya.";
        }
        $stmt_detail->close();
    } else {
         if(empty($error_message)) $error_message = "Gagal menyiapkan query detail pengajuan.";
    }
} elseif (!$id_pengajuan_url && empty($error_message)) {
    $error_message = "ID Pengajuan tidak disediakan.";
}


// Set judul halaman dan sertakan header
$page_title = "Verifikasi Detail Pengajuan KP";
if ($pengajuan_detail && !empty($pengajuan_detail['judul_kp'])) {
    $page_title = "Verifikasi: " . htmlspecialchars($pengajuan_detail['judul_kp']);
}
require_once '../includes/header.php';
?>

<div class="page-layout-wrapper">
    <main class="main-content-area">
        <div class="detail-verifikasi-container">
            <h1><?php echo htmlspecialchars($page_title); ?></h1>
            <a href="/KP/dosen/pengajuan_list.php" class="btn btn-light btn-sm mb-3">&laquo; Kembali ke Daftar Pengajuan</a>
            <hr>

            <?php if (!empty($success_message)): ?>
                <div class="message success"><p><?php echo htmlspecialchars($success_message); ?></p></div>
            <?php endif; ?>
            <?php if (!empty($error_message)): ?>
                <div class="message error"><p><?php echo $error_message; ?></p></div>
            <?php endif; ?>

            <?php if ($pengajuan_detail): ?>
                <div class="info-section">
                    <h3>Informasi Mahasiswa</h3>
                    <dl>
                        <dt>NIM:</dt><dd><?php echo htmlspecialchars($pengajuan_detail['nim_mahasiswa_kp']); ?></dd>
                        <dt>Nama Mahasiswa:</dt><dd><?php echo htmlspecialchars($pengajuan_detail['nama_mahasiswa']); ?></dd>
                        <dt>Email:</dt><dd><?php echo htmlspecialchars($pengajuan_detail['email_mahasiswa']); ?></dd>
                        <dt>No. HP:</dt><dd><?php echo htmlspecialchars($pengajuan_detail['no_hp_mahasiswa'] ?: '-'); ?></dd>
                        <dt>Prodi:</dt><dd><?php echo htmlspecialchars($pengajuan_detail['prodi'] ?: '-'); ?></dd>
                        <dt>Angkatan:</dt><dd><?php echo htmlspecialchars($pengajuan_detail['angkatan'] ?: '-'); ?></dd>
                    </dl>
                </div>

                <div class="info-section">
                    <h3>Detail Pengajuan KP</h3>
                    <dl>
                        <dt>ID Pengajuan:</dt><dd><?php echo $pengajuan_detail['id_pengajuan']; ?></dd>
                        <dt>Judul KP:</dt><dd><?php echo htmlspecialchars($pengajuan_detail['judul_kp']); ?></dd>
                        <dt>Status Saat Ini:</dt>
                        <dd>
                            <span class="status-badge status-<?php echo strtolower(str_replace([' ', '_'], '-', $pengajuan_detail['status_pengajuan'])); ?>">
                                <?php echo ucfirst(str_replace('_', ' ', htmlspecialchars($pengajuan_detail['status_pengajuan']))); ?>
                            </span>
                        </dd>
                        <dt>Tanggal Pengajuan:</dt><dd><?php echo date("d F Y", strtotime($pengajuan_detail['tanggal_pengajuan'])); ?></dd>
                        <dt>Perusahaan Tujuan:</dt><dd><?php echo $pengajuan_detail['nama_perusahaan'] ? htmlspecialchars($pengajuan_detail['nama_perusahaan']) : '<em>Belum ditentukan / Diajukan manual</em>'; ?></dd>
                        <dt>Deskripsi KP:</dt><dd><?php echo nl2br(htmlspecialchars($pengajuan_detail['deskripsi_kp'])); ?></dd>
                        <dt>Rencana Mulai:</dt><dd><?php echo date("d F Y", strtotime($pengajuan_detail['tanggal_mulai_rencana'])); ?></dd>
                        <dt>Rencana Selesai:</dt><dd><?php echo date("d F Y", strtotime($pengajuan_detail['tanggal_selesai_rencana'])); ?></dd>
                    </dl>
                </div>

                <div class="info-section">
                    <h3>Catatan Terkait</h3>
                    <?php if(!empty($pengajuan_detail['catatan_admin'])): ?>
                    <div class="catatan catatan-admin">
                        <strong>Catatan dari Admin Prodi:</strong>
                        <p><?php echo nl2br(htmlspecialchars($pengajuan_detail['catatan_admin'])); ?></p>
                    </div>
                    <?php endif; ?>
                     <?php if(!empty($pengajuan_detail['catatan_dosen'])): ?>
                    <div class="catatan catatan-dosen">
                        <strong>Catatan dari Dosen (Sebelumnya):</strong>
                        <p><?php echo nl2br(htmlspecialchars($pengajuan_detail['catatan_dosen'])); ?></p>
                    </div>
                    <?php else: ?>
                         <p><em>Belum ada catatan dari dosen.</em></p>
                    <?php endif; ?>
                </div>


                <div class="info-section">
                    <h3>Dokumen Unggahan Mahasiswa</h3>
                    <?php if (!empty($dokumen_terkait)): ?>
                        <ul class="dokumen-list">
                            <?php foreach ($dokumen_terkait as $dokumen): ?>
                                <li>
                                    <strong><?php echo htmlspecialchars($dokumen['nama_dokumen']); ?></strong>
                                    (Jenis: <?php echo ucfirst(str_replace('_', ' ', htmlspecialchars($dokumen['jenis_dokumen']))); ?>)
                                    <br>
                                    <small>Diupload: <?php echo date("d M Y, H:i", strtotime($dokumen['tanggal_upload'])); ?> | Status Dokumen: 
                                        <span class="status-dokumen-<?php echo strtolower(str_replace([' ', '_'], '-', $dokumen['status_verifikasi_dokumen'])); ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', htmlspecialchars($dokumen['status_verifikasi_dokumen']))); ?>
                                        </span>
                                    </small>
                                    <?php if (!empty($dokumen['catatan_verifikator'])): ?>
                                        <div class="catatan catatan-verifikator"><small>Catatan Verifikator: <?php echo nl2br(htmlspecialchars($dokumen['catatan_verifikator'])); ?></small></div>
                                    <?php endif; ?>
                                    <?php if (!empty($dokumen['file_path'])): ?>
                                        <br><a href="/KP/<?php echo htmlspecialchars($dokumen['file_path']); ?>" target="_blank" class="btn btn-outline-primary btn-sm">Unduh/Lihat Dokumen</a>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p>Mahasiswa belum mengunggah dokumen apapun untuk pengajuan ini.</p>
                    <?php endif; ?>
                </div>

                <?php
                // Tampilkan form verifikasi hanya jika statusnya memungkinkan untuk diverifikasi oleh dosen ini
                $status_memungkinkan_verifikasi = ['diajukan_mahasiswa', 'diverifikasi_dospem']; // Sesuaikan
                if (in_array($pengajuan_detail['status_pengajuan'], $status_memungkinkan_verifikasi) && $pengajuan_detail['nip_dosen_pembimbing_kp'] == $nip_dosen_login ):
                ?>
                <div class="verification-form-section info-section">
                    <h3>Tindakan Verifikasi/Persetujuan</h3>
                    <form action="/KP/dosen/pengajuan_verifikasi_detail.php?id_pengajuan=<?php echo $id_pengajuan_url; ?>" method="POST">
                        <input type="hidden" name="id_pengajuan" value="<?php echo $id_pengajuan_url; ?>">
                        <div class="form-group">
                            <label for="status_verifikasi">Pilih Tindakan (*):</label>
                            <select id="status_verifikasi" name="status_verifikasi" required>
                                <option value="">-- Pilih Status Baru --</option>
                                <option value="disetujui_dospem">Setujui Pengajuan Ini (Disetujui Dosen Pembimbing)</option>
                                <option value="ditolak_dospem">Tolak Pengajuan Ini (Ditolak Dosen Pembimbing)</option>
                                </select>
                        </div>
                        <div class="form-group">
                            <label for="catatan_dosen">Catatan / Alasan (jika ditolak atau ada masukan):</label>
                            <textarea id="catatan_dosen" name="catatan_dosen" rows="5" placeholder="Berikan catatan atau alasan jika menolak, atau masukan lain..."></textarea>
                        </div>
                        <div class="form-actions">
                            <button type="submit" name="submit_verifikasi" class="btn btn-success">Simpan Tindakan</button>
                        </div>
                    </form>
                </div>
                <?php elseif ($pengajuan_detail['nip_dosen_pembimbing_kp'] != $nip_dosen_login && !in_array($pengajuan_detail['status_pengajuan'], $status_memungkinkan_verifikasi)): ?>
                     <div class="message info"><p>Anda tidak ditugaskan sebagai pembimbing untuk pengajuan ini atau status tidak memerlukan tindakan verifikasi dari Anda saat ini.</p></div>
                <?php elseif (!in_array($pengajuan_detail['status_pengajuan'], $status_memungkinkan_verifikasi)): ?>
                     <div class="message info"><p>Status pengajuan saat ini (<?php echo ucfirst(str_replace('_', ' ', htmlspecialchars($pengajuan_detail['status_pengajuan']))); ?>) tidak memerlukan tindakan verifikasi lebih lanjut melalui form ini.</p></div>
                <?php endif; ?>


            <?php elseif(empty($error_message)): // Jika $pengajuan_detail null tapi tidak ada $error_message eksplisit ?>
                <div class="message info"><p>Memuat detail pengajuan...</p></div>
            <?php endif; ?>
        </div>
    </main>
</div>

<style>
    /* Asumsikan CSS umum dari header, sidebar, .info-section, .catatan, .status-badge, .dokumen-list, .message, .btn sudah ada */
    .detail-verifikasi-container h1 { margin-top: 0; margin-bottom: 5px; }
    .detail-verifikasi-container hr { margin-top:15px; margin-bottom: 25px; }
    .btn.mb-3 { margin-bottom: 1rem !important; } /* Untuk tombol kembali */

    .verification-form-section {
        background-color: #f0f8ff; /* AliceBlue, sedikit beda untuk area form */
        padding: 20px;
        border-radius: 8px;
        margin-top: 20px;
        border: 1px solid #cce5ff;
    }
    .verification-form-section h3 {
        color: #004085; /* Biru tua untuk judul form */
        margin-top: 0;
    }
    .form-group { margin-bottom: 18px; }
    .form-group label { display: block; font-weight: bold; margin-bottom: 6px; }
    .form-group select, .form-group textarea {
        width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box;
    }
    .form-actions { margin-top: 20px; }
    .btn-success { color: #fff; background-color: #28a745; border-color: #28a745; }
    .btn-success:hover { background-color: #218838; border-color: #1e7e34; }
</style>

<?php
require_once '../includes/footer.php';

if (isset($conn) && ($conn instanceof mysqli)) {
    $conn->close();
}
?>