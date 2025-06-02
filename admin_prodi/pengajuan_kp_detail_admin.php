<?php
// /KP/admin_prodi/pengajuan_kp_detail_admin.php

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

$admin_identifier = $_SESSION['user_id']; // atau nama_admin, tergantung apa yang disimpan
$id_pengajuan_url = null;
$pengajuan_detail = null;
$dokumen_terkait = [];
$list_semua_dosen = [];
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

// 3. FUNGSI UNTUK MENGAMBIL DATA DETAIL PENGAJUAN (AGAR BISA DIPANGGIL ULANG SETELAH UPDATE)
function getPengajuanDetail($conn_db, $id_pengajuan, &$out_dokumen_terkait, &$out_error_message) {
    $detail = null;
    $out_dokumen_terkait = []; // Reset dokumen terkait

    $sql_detail = "SELECT
                       pk.id_pengajuan, pk.nim AS nim_mahasiswa_kp, pk.judul_kp, pk.deskripsi_kp,
                       p.nama_perusahaan, pk.id_perusahaan,
                       pk.tanggal_pengajuan, pk.tanggal_mulai_rencana, pk.tanggal_selesai_rencana,
                       pk.status_pengajuan, pk.nip_dosen_pembimbing_kp,
                       dospem.nama_dosen AS nama_dosen_pembimbing,
                       pk.catatan_admin, pk.catatan_dosen,
                       pk.surat_pengantar_path, pk.surat_balasan_perusahaan_path,
                       m.nama AS nama_mahasiswa, m.email AS email_mahasiswa, m.no_hp AS no_hp_mahasiswa, m.prodi, m.angkatan
                   FROM pengajuan_kp pk
                   JOIN mahasiswa m ON pk.nim = m.nim
                   LEFT JOIN perusahaan p ON pk.id_perusahaan = p.id_perusahaan
                   LEFT JOIN dosen_pembimbing dospem ON pk.nip_dosen_pembimbing_kp = dospem.nip
                   WHERE pk.id_pengajuan = ?";
    $stmt_detail = $conn_db->prepare($sql_detail);
    if ($stmt_detail) {
        $stmt_detail->bind_param("i", $id_pengajuan);
        $stmt_detail->execute();
        $result_detail = $stmt_detail->get_result();
        if ($result_detail->num_rows === 1) {
            $detail = $result_detail->fetch_assoc();

            // Ambil dokumen terkait
            $sql_dokumen = "SELECT id_dokumen, nama_dokumen, jenis_dokumen, file_path, tanggal_upload, status_verifikasi_dokumen, catatan_verifikator
                            FROM dokumen_kp
                            WHERE id_pengajuan = ?
                            ORDER BY tanggal_upload DESC";
            $stmt_dokumen = $conn_db->prepare($sql_dokumen);
            if ($stmt_dokumen) {
                $stmt_dokumen->bind_param("i", $id_pengajuan);
                $stmt_dokumen->execute();
                $result_dokumen = $stmt_dokumen->get_result();
                while ($row_dokumen = $result_dokumen->fetch_assoc()) {
                    $out_dokumen_terkait[] = $row_dokumen;
                }
                $stmt_dokumen->close();
            } else {
                 $out_error_message .= (empty($out_error_message)?"":"<br>") . "Gagal mengambil daftar dokumen.";
            }
        } else {
            if(empty($out_error_message)) $out_error_message = "Detail pengajuan KP tidak ditemukan.";
        }
        $stmt_detail->close();
    } else {
        if(empty($out_error_message)) $out_error_message = "Gagal menyiapkan query detail pengajuan.";
    }
    return $detail;
}

// 4. AMBIL DAFTAR SEMUA DOSEN AKTIF (UNTUK DROPDOWN PENUGASAN DOSPEM)
if ($conn && ($conn instanceof mysqli) && empty($error_message)) {
    $sql_dosen = "SELECT nip, nama_dosen FROM dosen_pembimbing WHERE status_akun = 'active' ORDER BY nama_dosen ASC";
    $result_dosen = $conn->query($sql_dosen);
    if ($result_dosen && $result_dosen->num_rows > 0) {
        while ($row_dosen = $result_dosen->fetch_assoc()) {
            $list_semua_dosen[] = $row_dosen;
        }
    }
}

// 5. PROSES FORM TINDAKAN ADMIN JIKA DISUBMIT (METHOD POST)
if ($_SERVER["REQUEST_METHOD"] == "POST" && $id_pengajuan_url !== null && empty($error_message)) {
    if ($conn && ($conn instanceof mysqli)) {
        $conn->begin_transaction();
        try {
            $update_berhasil = false;
            // Tindakan 1: Update Dosen Pembimbing
            if (isset($_POST['submit_dospem'])) {
                $nip_dospem_baru = !empty($_POST['nip_dosen_pembimbing_kp']) ? $_POST['nip_dosen_pembimbing_kp'] : null;
                $status_setelah_dospem = $_POST['status_setelah_dospem']; // Status baru setelah dospem dipilih

                // Validasi status baru
                $allowed_status_dospem = ['diajukan_mahasiswa', 'diverifikasi_dospem', 'kp_berjalan', 'penentuan_dospem_kp']; // Sesuaikan
                if (empty($status_setelah_dospem) || !in_array($status_setelah_dospem, $allowed_status_dospem) ) {
                     throw new Exception("Status yang dipilih setelah penentuan Dosen Pembimbing tidak valid.");
                }

                $sql_update_dospem = "UPDATE pengajuan_kp SET nip_dosen_pembimbing_kp = ?, status_pengajuan = ? WHERE id_pengajuan = ?";
                $stmt_update_dospem = $conn->prepare($sql_update_dospem);
                if (!$stmt_update_dospem) throw new Exception("Prepare statement update dospem gagal: " . $conn->error);
                $stmt_update_dospem->bind_param("ssi", $nip_dospem_baru, $status_setelah_dospem, $id_pengajuan_url);
                if (!$stmt_update_dospem->execute()) throw new Exception("Eksekusi update dospem gagal: " . $stmt_update_dospem->error);
                if ($stmt_update_dospem->affected_rows > 0) $update_berhasil = true;
                $stmt_update_dospem->close();
                $success_message = "Dosen Pembimbing dan status berhasil diperbarui.";
            }

            // Tindakan 2: Update Status Pengajuan & Catatan Admin
            if (isset($_POST['submit_status_catatan'])) {
                $status_baru_admin = $_POST['status_pengajuan_admin'];
                $catatan_admin_input = trim($_POST['catatan_admin']);
                $catatan_final = "";

                // Ambil catatan admin yang sudah ada (jika ada)
                $sql_get_catatan = "SELECT catatan_admin FROM pengajuan_kp WHERE id_pengajuan = ?";
                $stmt_get_catatan = $conn->prepare($sql_get_catatan);
                if (!$stmt_get_catatan) throw new Exception("Gagal mengambil catatan admin: " . $conn->error);
                $stmt_get_catatan->bind_param("i", $id_pengajuan_url);
                $stmt_get_catatan->execute();
                $res_catatan = $stmt_get_catatan->get_result();
                $current_data = $res_catatan->fetch_assoc();
                $stmt_get_catatan->close();
                
                $catatan_final = $current_data['catatan_admin'];
                if(!empty($catatan_admin_input)){
                    $catatan_final .= (empty($catatan_final) ? "" : "\n\n") . "[Update Admin " . date("d M Y H:i") . "]: " . $catatan_admin_input;
                }

                $sql_update_status = "UPDATE pengajuan_kp SET status_pengajuan = ?, catatan_admin = ? WHERE id_pengajuan = ?";
                $stmt_update_status = $conn->prepare($sql_update_status);
                if (!$stmt_update_status) throw new Exception("Prepare statement update status/catatan gagal: " . $conn->error);
                $stmt_update_status->bind_param("ssi", $status_baru_admin, $catatan_final, $id_pengajuan_url);
                if (!$stmt_update_status->execute()) throw new Exception("Eksekusi update status/catatan gagal: " . $stmt_update_status->error);
                if ($stmt_update_status->affected_rows > 0 || (!empty($catatan_admin_input) && $current_data['catatan_admin'] != $catatan_final)) $update_berhasil = true;
                $stmt_update_status->close();
                $success_message = "Status Pengajuan dan/atau Catatan Admin berhasil diperbarui.";
            }
            
            $conn->commit();
            if ($update_berhasil && empty($error_message)) { // Hanya set success jika benar ada update & tidak ada error lain
                 // Data akan di-refresh di bawah
            } elseif (!$update_berhasil && empty($error_message) && ($_SERVER["REQUEST_METHOD"] == "POST")) {
                $error_message = "Tidak ada perubahan data yang dilakukan.";
            }

        } catch (Exception $e) {
            $conn->rollback();
            $error_message = "Terjadi kesalahan saat memproses tindakan: " . htmlspecialchars($e->getMessage());
        }
    } else {
        $error_message = "Koneksi database hilang.";
    }
}


// Selalu ambil data terbaru untuk ditampilkan setelah potensi update atau saat load awal
if ($id_pengajuan_url && empty($error_message) && $conn && ($conn instanceof mysqli)) {
    $pengajuan_detail = getPengajuanDetail($conn, $id_pengajuan_url, $dokumen_terkait, $error_message);
}


// Daftar semua status pengajuan untuk dropdown Admin (sesuai ENUM)
$opsi_status_admin = [
    'draft' => 'Draft (oleh Mahasiswa)',
    'diajukan_mahasiswa' => 'Diajukan Mahasiswa',
    'diverifikasi_dospem' => 'Diverifikasi Dosen Pembimbing (awal)', // Mungkin status ini dilewati jika admin yg tentukan dospem
    'disetujui_dospem' => 'Disetujui Dosen Pembimbing',
    'ditolak_dospem' => 'Ditolak Dosen Pembimbing',
    'menunggu_konfirmasi_perusahaan' => 'Menunggu Konfirmasi Perusahaan',
    'diterima_perusahaan' => 'Diterima Perusahaan',
    'ditolak_perusahaan' => 'Ditolak Perusahaan',
    'penentuan_dospem_kp' => 'Penentuan Dosen Pembimbing KP (oleh Admin)',
    'kp_berjalan' => 'KP Berjalan',
    'selesai_pelaksanaan' => 'Selesai Pelaksanaan',
    'laporan_disetujui' => 'Laporan Disetujui',
    'selesai_dinilai' => 'Selesai Dinilai',
    'dibatalkan' => 'Dibatalkan'
];

// Set judul halaman dan sertakan header
$page_title = "Detail & Kelola Pengajuan KP";
if ($pengajuan_detail && !empty($pengajuan_detail['judul_kp'])) {
    $page_title = "Kelola: " . htmlspecialchars($pengajuan_detail['judul_kp']);
}
require_once '../includes/header.php';
?>

<div class="page-layout-wrapper">

    <?php require_once '../includes/sidebar_admin_prodi.php'; ?>

    <main class="main-content-area">
        <div class="detail-admin-container">
            <h1><?php echo htmlspecialchars($page_title); ?></h1>
            <a href="/KP/admin_prodi/pengajuan_kp_monitoring.php" class="btn btn-light btn-sm mb-3">&laquo; Kembali ke Monitoring</a>
            <hr>

            <?php if (!empty($success_message)): ?>
                <div class="message success"><p><?php echo htmlspecialchars($success_message); ?></p></div>
            <?php endif; ?>
            <?php if (!empty($error_message)): ?>
                <div class="message error"><p><?php echo $error_message; ?></p></div>
            <?php endif; ?>

            <?php if ($pengajuan_detail): ?>
                <div class="info-section card">
                    <div class="card-header"><h3>Informasi Mahasiswa</h3></div>
                    <div class="card-body">
                        <dl>
                            <dt>NIM:</dt><dd><?php echo htmlspecialchars($pengajuan_detail['nim_mahasiswa_kp']); ?></dd>
                            <dt>Nama:</dt><dd><?php echo htmlspecialchars($pengajuan_detail['nama_mahasiswa']); ?></dd>
                            <dt>Prodi:</dt><dd><?php echo htmlspecialchars($pengajuan_detail['prodi'] ?: '-'); ?></dd>
                            <dt>Angkatan:</dt><dd><?php echo htmlspecialchars($pengajuan_detail['angkatan'] ?: '-'); ?></dd>
                            <dt>Email:</dt><dd><?php echo htmlspecialchars($pengajuan_detail['email_mahasiswa']); ?></dd>
                            <dt>No. HP:</dt><dd><?php echo htmlspecialchars($pengajuan_detail['no_hp_mahasiswa'] ?: '-'); ?></dd>
                        </dl>
                    </div>
                </div>

                <div class="info-section card">
                    <div class="card-header"><h3>Detail Pengajuan KP</h3></div>
                    <div class="card-body">
                        <dl>
                            <dt>ID Pengajuan:</dt><dd><?php echo $pengajuan_detail['id_pengajuan']; ?></dd>
                            <dt>Judul KP:</dt><dd><?php echo htmlspecialchars($pengajuan_detail['judul_kp']); ?></dd>
                            <dt>Status Saat Ini:</dt>
                            <dd><span class="status-badge status-<?php echo strtolower(str_replace([' ', '_'], '-', $pengajuan_detail['status_pengajuan'])); ?>">
                                <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $pengajuan_detail['status_pengajuan']))); ?></span>
                            </dd>
                            <dt>Tgl Diajukan Mhs:</dt><dd><?php echo date("d M Y", strtotime($pengajuan_detail['tanggal_pengajuan'])); ?></dd>
                            <dt>Perusahaan:</dt><dd><?php echo $pengajuan_detail['nama_perusahaan'] ? htmlspecialchars($pengajuan_detail['nama_perusahaan']) : '<em>Diajukan manual / Belum ada</em>'; ?></dd>
                            <dt>Deskripsi KP:</dt><dd><?php echo nl2br(htmlspecialchars($pengajuan_detail['deskripsi_kp'])); ?></dd>
                            <dt>Rencana Mulai:</dt><dd><?php echo date("d M Y", strtotime($pengajuan_detail['tanggal_mulai_rencana'])); ?></dd>
                            <dt>Rencana Selesai:</dt><dd><?php echo date("d M Y", strtotime($pengajuan_detail['tanggal_selesai_rencana'])); ?></dd>
                            <dt>Dosen Pembimbing Saat Ini:</dt><dd><?php echo $pengajuan_detail['nama_dosen_pembimbing'] ? htmlspecialchars($pengajuan_detail['nama_dosen_pembimbing']) . " (NIP: ".htmlspecialchars($pengajuan_detail['nip_dosen_pembimbing_kp']).")" : '<em>Belum Ditentukan</em>'; ?></dd>
                        </dl>
                    </div>
                </div>

                <div class="info-section card">
                    <div class="card-header"><h3>Catatan Terkait Pengajuan</h3></div>
                    <div class="card-body">
                        <?php if(!empty($pengajuan_detail['catatan_dosen'])): ?>
                        <div class="catatan catatan-dosen"><strong>Catatan dari Dosen Pembimbing:</strong><p><?php echo nl2br(htmlspecialchars($pengajuan_detail['catatan_dosen'])); ?></p></div>
                        <?php endif; ?>
                        <?php if(!empty($pengajuan_detail['catatan_admin'])): ?>
                        <div class="catatan catatan-admin"><strong>Catatan dari Admin Prodi:</strong><p><?php echo nl2br(htmlspecialchars($pengajuan_detail['catatan_admin'])); ?></p></div>
                        <?php endif; ?>
                        <?php if(empty($pengajuan_detail['catatan_dosen']) && empty($pengajuan_detail['catatan_admin'])): ?>
                        <p><em>Belum ada catatan.</em></p>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="info-section card">
                    <div class="card-header"><h3>Dokumen Unggahan Mahasiswa</h3></div>
                    <div class="card-body">
                        <?php if (!empty($dokumen_terkait)): ?>
                            <ul class="dokumen-list">
                                <?php foreach ($dokumen_terkait as $dokumen): ?>
                                    <li>
                                        <strong><?php echo htmlspecialchars($dokumen['nama_dokumen']); ?></strong>
                                        (<?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $dokumen['jenis_dokumen']))); ?>)
                                        <br><small>Tgl Upload: <?php echo date("d M Y H:i", strtotime($dokumen['tanggal_upload'])); ?> | Status:
                                            <span class="status-dokumen-<?php echo strtolower(str_replace([' ', '_'], '-', $dokumen['status_verifikasi_dokumen'])); ?>">
                                            <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $dokumen['status_verifikasi_dokumen']))); ?></span>
                                        </small>
                                        <?php if (!empty($dokumen['catatan_verifikator'])): ?>
                                            <div class="catatan catatan-verifikator"><small>Catatan Verifikator: <?php echo nl2br(htmlspecialchars($dokumen['catatan_verifikator'])); ?></small></div>
                                        <?php endif; ?>
                                        <?php if(!empty($dokumen['file_path'])): ?>
                                            <br><a href="/KP/<?php echo htmlspecialchars($dokumen['file_path']); ?>" target="_blank" class="btn btn-outline-primary btn-sm">Unduh/Lihat</a>
                                        <?php endif; ?>
                                        <a href="/KP/admin_prodi/dokumen_verifikasi_form.php?id_dokumen=<?php echo $dokumen['id_dokumen']; ?>&id_pengajuan=<?php echo $id_pengajuan_url; ?>" class="btn btn-outline-warning btn-sm">Verifikasi Dokumen Ini</a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <p>Belum ada dokumen yang diunggah oleh mahasiswa untuk pengajuan ini.</p>
                        <?php endif; ?>
                         <p style="margin-top:15px;"><small>Mahasiswa dapat mengunggah dokumen melalui halaman detail pengajuan mereka.</small></p>
                    </div>
                </div>


                <div class="admin-actions-section card">
                    <div class="card-header"><h3>Tindakan Administratif</h3></div>
                    <div class="card-body">
                        <form action="/KP/admin_prodi/pengajuan_kp_detail_admin.php?id_pengajuan=<?php echo $id_pengajuan_url; ?>" method="POST" class="action-form">
                            <h4>1. Penetapan/Perubahan Dosen Pembimbing</h4>
                            <div class="form-group">
                                <label for="nip_dosen_pembimbing_kp">Pilih Dosen Pembimbing:</label>
                                <select name="nip_dosen_pembimbing_kp" id="nip_dosen_pembimbing_kp">
                                    <option value="">-- Belum Ditentukan / Hapus Dospem --</option>
                                    <?php foreach ($list_semua_dosen as $dosen): ?>
                                        <option value="<?php echo htmlspecialchars($dosen['nip']); ?>" <?php echo ($pengajuan_detail['nip_dosen_pembimbing_kp'] == $dosen['nip']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($dosen['nama_dosen']); ?> (<?php echo htmlspecialchars($dosen['nip']); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="status_setelah_dospem">Set Status Pengajuan Setelah Dospem Dipilih/Diubah ke:</label>
                                <select name="status_setelah_dospem" id="status_setelah_dospem" required>
                                    <option value="penentuan_dospem_kp" <?php echo ($pengajuan_detail['status_pengajuan'] == 'penentuan_dospem_kp') ? 'selected' : ''; ?>>Penentuan Dospem KP (Default)</option>
                                    <option value="diajukan_mahasiswa" <?php echo ($pengajuan_detail['status_pengajuan'] == 'diajukan_mahasiswa') ? 'selected' : ''; ?>>Diajukan (untuk diverifikasi Dospem)</option>
                                    <option value="diverifikasi_dospem" <?php echo ($pengajuan_detail['status_pengajuan'] == 'diverifikasi_dospem') ? 'selected' : ''; ?>>Diverifikasi Dospem (awal)</option>
                                    <option value="kp_berjalan" <?php echo ($pengajuan_detail['status_pengajuan'] == 'kp_berjalan') ? 'selected' : ''; ?>>KP Berjalan (jika langsung)</option>
                                </select>
                                <small>Status ini akan diterapkan bersamaan dengan update Dosen Pembimbing.</small>
                            </div>
                            <button type="submit" name="submit_dospem" class="btn btn-primary">Update Dosen Pembimbing & Status</button>
                        </form>
                        <hr class="form-separator">

                        <form action="/KP/admin_prodi/pengajuan_kp_detail_admin.php?id_pengajuan=<?php echo $id_pengajuan_url; ?>" method="POST" class="action-form">
                            <h4>2. Perubahan Status Pengajuan & Catatan Admin</h4>
                            <div class="form-group">
                                <label for="status_pengajuan_admin">Ubah Status Pengajuan ke:</label>
                                <select name="status_pengajuan_admin" id="status_pengajuan_admin" required>
                                    <option value="">-- Pilih Status Baru --</option>
                                    <?php foreach ($opsi_status_admin as $value => $text): ?>
                                        <option value="<?php echo $value; ?>" <?php echo ($pengajuan_detail['status_pengajuan'] == $value) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($text); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="catatan_admin">Tambahkan Catatan Admin Baru:</label>
                                <textarea name="catatan_admin" id="catatan_admin" rows="4" placeholder="Catatan akan ditambahkan ke catatan yang sudah ada..."></textarea>
                            </div>
                            <button type="submit" name="submit_status_catatan" class="btn btn-success">Update Status & Catatan</button>
                        </form>
                        <hr class="form-separator">
                        
                        <h4>3. Manajemen Surat Resmi</h4>
                        <div class="form-group">
                        <?php if(!empty($pengajuan_detail['surat_pengantar_path'])): ?>
                            <p>Surat Pengantar sudah ada: <a href="/KP/<?php echo htmlspecialchars($pengajuan_detail['surat_pengantar_path']); ?>" target="_blank">Lihat/Unduh</a></p>
                        <?php endif; ?>
                        <a href="/KP/admin_prodi/surat_generate.php?tipe=pengantar&id_pengajuan=<?php echo $id_pengajuan_url; ?>" class="btn btn-warning" target="_blank">
                            <?php echo empty($pengajuan_detail['surat_pengantar_path']) ? "Generate Surat Pengantar KP" : "Generate Ulang Surat Pengantar KP"; ?>
                        </a>
                        <small style="display:block; margin-top:5px;">Ini akan membuka halaman baru untuk preview dan finalisasi surat. Setelah digenerate, path surat akan tersimpan.</small>
                        </div>
                         <?php if(!empty($pengajuan_detail['surat_balasan_perusahaan_path'])): ?>
                            <p>Surat Balasan Perusahaan sudah ada: <a href="/KP/<?php echo htmlspecialchars($pengajuan_detail['surat_balasan_perusahaan_path']); ?>" target="_blank">Lihat/Unduh</a></p>
                        <?php else: ?>
                             <p><small><em>Admin dapat mengupload surat balasan perusahaan melalui fitur 'Verifikasi Dokumen KP' dengan jenis 'Surat Balasan Perusahaan'.</em></small></p>
                        <?php endif; ?>


                    </div>
                </div>

            <?php elseif(empty($error_message)): ?>
                <div class="message info"><p>Memuat detail pengajuan...</p></div>
            <?php endif; ?>
        </div>
    </main>
</div>

<style>
    /* Asumsikan CSS umum dari header, sidebar, .info-section, .card, .status-badge, dll sudah ada */
    .detail-admin-container h1 { margin-top: 0; margin-bottom: 5px; }
    .detail-admin-container hr { margin-top:15px; margin-bottom: 20px; }
    .btn.mb-3 { margin-bottom: 1rem !important; }
    .btn-light { /* ...sesuaikan... */ }

    .info-section.card { margin-bottom: 25px; }
    .card-header { background-color: #e9ecef; padding: 0.75rem 1.25rem; border-bottom: 1px solid rgba(0,0,0,.125); }
    .card-header h3 { margin-bottom: 0; font-size: 1.25rem; }
    .card-body { padding: 1.25rem; }
    .card-body dl dt { width: 180px; } /* Sesuaikan lebar label di dl */
    .card-body dl dd { margin-left: 190px; } /* Sesuaikan margin value */

    .admin-actions-section.card .card-header h3 { color: #dc3545; } /* Warna beda untuk judul section admin */
    .action-form { margin-bottom: 20px; }
    .action-form h4 { font-size: 1.1em; color: #444; margin-top:0; margin-bottom:15px; }
    .form-separator { margin-top: 25px; margin-bottom: 25px; }

    .dokumen-list li { background-color: #fdfdfe; padding: 12px; border: 1px solid #f0f0f0; margin-bottom: 8px; border-radius: 4px; }
    .dokumen-list li small { font-size: 0.85em; }
    .btn-outline-warning { color: #ffc107; border-color: #ffc107; }
    .btn-outline-warning:hover { color: #000; background-color: #ffc107; border-color: #ffc107; }
    .btn-warning { color: #000; background-color: #ffc107; border-color: #ffc107; }
    .btn-warning:hover { background-color: #e0a800; border-color: #d39e00; }
    .btn-success { /* ...sesuaikan... */ }
</style>

<?php
require_once '../includes/footer.php';

if (isset($conn) && ($conn instanceof mysqli)) {
    $conn->close();
}
?>