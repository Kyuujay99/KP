<?php
// /KP/admin_prodi/pengajuan_kp_detail_admin.php (Versi Final - Tampilan Disempurnakan)

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once '../includes/auth_check.php';
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin_prodi') {
    header("Location: /KP/index.php?error=unauthorized_admin");
    exit();
}

require_once '../config/db_connect.php';

$id_pengajuan_url = null;
$pengajuan_detail = null;
$dokumen_terkait = [];
$list_semua_dosen = [];
$error_message = '';
$success_message = '';

if (isset($_GET['id_pengajuan']) && filter_var($_GET['id_pengajuan'], FILTER_VALIDATE_INT)) {
    $id_pengajuan_url = (int)$_GET['id_pengajuan'];
} else {
    $error_message = "ID Pengajuan tidak valid atau tidak ditemukan.";
}

// Fungsi untuk mengambil detail lengkap pengajuan KP
function getPengajuanDetail($conn_db, $id_pengajuan, &$out_dokumen_terkait, &$out_error_message) {
    $detail = null;
    $out_dokumen_terkait = [];

    $sql_detail = "SELECT
                       pk.id_pengajuan, pk.nim AS nim_mahasiswa_kp, pk.judul_kp, pk.deskripsi_kp,
                       p.nama_perusahaan, pk.id_perusahaan,
                       pk.tanggal_pengajuan, pk.tanggal_mulai_rencana, pk.tanggal_selesai_rencana,
                       pk.status_pengajuan, pk.nip_dosen_pembimbing_kp,
                       dospem.nama_dosen AS nama_dosen_pembimbing,
                       pk.catatan_admin, pk.catatan_dosen,
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
            $sql_dokumen = "SELECT id_dokumen, nama_dokumen, jenis_dokumen, file_path, tanggal_upload, status_verifikasi_dokumen, catatan_verifikator FROM dokumen_kp WHERE id_pengajuan = ? ORDER BY tanggal_upload DESC";
            $stmt_dokumen = $conn_db->prepare($sql_dokumen);
            if ($stmt_dokumen) {
                $stmt_dokumen->bind_param("i", $id_pengajuan);
                $stmt_dokumen->execute();
                $result_dokumen = $stmt_dokumen->get_result();
                while ($row_dokumen = $result_dokumen->fetch_assoc()) {
                    $out_dokumen_terkait[] = $row_dokumen;
                }
                $stmt_dokumen->close();
            }
        } else {
            $out_error_message = "Detail pengajuan KP tidak ditemukan.";
        }
        $stmt_detail->close();
    } else {
        $out_error_message = "Gagal menyiapkan query detail pengajuan: " . $conn_db->error;
    }
    return $detail;
}

// Proses form update oleh admin
if ($conn && empty($error_message) && $_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_admin_update'])) {
    $id_pengajuan_form = (int)$_POST['id_pengajuan'];
    $new_status = $_POST['status_pengajuan'];
    $new_dospem_nip = !empty($_POST['nip_dosen_pembimbing_kp']) ? $_POST['nip_dosen_pembimbing_kp'] : null;
    $catatan_admin = trim($_POST['catatan_admin']);

    if ($id_pengajuan_form === $id_pengajuan_url) {
        $current_catatan_result = $conn->query("SELECT catatan_admin FROM pengajuan_kp WHERE id_pengajuan = $id_pengajuan_url");
        $current_catatan = $current_catatan_result ? $current_catatan_result->fetch_assoc()['catatan_admin'] : '';
        
        $new_catatan_admin = $current_catatan;
        if (!empty($catatan_admin)) {
            $new_catatan_admin .= "\n\n[Update Admin " . date("d/m/Y H:i") . "]:\n" . $catatan_admin;
        }

        $sql_update = "UPDATE pengajuan_kp SET status_pengajuan = ?, nip_dosen_pembimbing_kp = ?, catatan_admin = ? WHERE id_pengajuan = ?";
        $stmt_update = $conn->prepare($sql_update);
        if ($stmt_update) {
            $stmt_update->bind_param("sssi", $new_status, $new_dospem_nip, $new_catatan_admin, $id_pengajuan_url);
            if ($stmt_update->execute()) {
                $success_message = "Data pengajuan berhasil diperbarui.";
            } else {
                $error_message = "Gagal memperbarui pengajuan: " . $stmt_update->error;
            }
            $stmt_update->close();
        }
    }
}

// Selalu ambil data terbaru untuk ditampilkan
if ($conn && empty($error_message)) {
    $pengajuan_detail = getPengajuanDetail($conn, $id_pengajuan_url, $dokumen_terkait, $error_message);
    $sql_dosen = "SELECT nip, nama_dosen FROM dosen_pembimbing WHERE status_akun = 'active' ORDER BY nama_dosen ASC";
    $result_dosen = $conn->query($sql_dosen);
    if ($result_dosen) {
        while ($row_dosen = $result_dosen->fetch_assoc()) {
            $list_semua_dosen[] = $row_dosen;
        }
    }
}

$opsi_status_admin = [
    'draft' => 'Draft', 'diajukan_mahasiswa' => 'Diajukan Mahasiswa',
    'diverifikasi_dospem' => 'Diverifikasi Dosen', 'disetujui_dospem' => 'Disetujui Dosen',
    'ditolak_dospem' => 'Ditolak Dosen', 'menunggu_konfirmasi_perusahaan' => 'Menunggu Konfirmasi Perusahaan',
    'diterima_perusahaan' => 'Diterima Perusahaan', 'ditolak_perusahaan' => 'Ditolak Perusahaan',
    'penentuan_dospem_kp' => 'Penentuan Dospem', 'kp_berjalan' => 'KP Berjalan',
    'selesai_pelaksanaan' => 'Selesai Pelaksanaan', 'laporan_disetujui' => 'Laporan Disetujui',
    'selesai_dinilai' => 'Selesai Dinilai', 'dibatalkan' => 'Dibatalkan'
];

$page_title = "Kelola Detail Pengajuan KP";
if ($pengajuan_detail) {
    $page_title = "Kelola: " . htmlspecialchars($pengajuan_detail['nama_mahasiswa']);
}
require_once '../includes/header.php';
?>

<div class="main-content-full">
    <div class="form-container-modern">
        <div class="form-header">
            <div>
                <h1><?php echo htmlspecialchars($page_title); ?></h1>
                <p>Kelola setiap aspek pengajuan KP, mulai dari penentuan dosen hingga manajemen dokumen dan surat resmi.</p>
            </div>
            <a href="pengajuan_kp_monitoring.php" class="btn btn-secondary">&laquo; Kembali ke Monitoring</a>
        </div>

        <?php if (!empty($success_message)): ?>
            <div class="message success"><p><?php echo htmlspecialchars($success_message); ?></p></div>
        <?php endif; ?>
        <?php if (!empty($error_message) && !$pengajuan_detail): ?>
            <div class="message error"><p><?php echo htmlspecialchars($error_message); ?></p></div>
        <?php endif; ?>

        <?php if ($pengajuan_detail): ?>
            <div class="content-grid">
                <div class="main-content-column">
                    <div class="info-card">
                        <div class="card-header"><h4><i class="icon">🎓</i>Informasi Mahasiswa</h4></div>
                        <div class="card-body">
                            <div class="detail-item"><span class="detail-label">Nama</span><span class="detail-value"><?php echo htmlspecialchars($pengajuan_detail['nama_mahasiswa']); ?></span></div>
                            <div class="detail-item"><span class="detail-label">NIM</span><span class="detail-value"><?php echo htmlspecialchars($pengajuan_detail['nim_mahasiswa_kp']); ?></span></div>
                            <div class="detail-item"><span class="detail-label">Prodi / Angkatan</span><span class="detail-value"><?php echo htmlspecialchars($pengajuan_detail['prodi'] . ' / ' . $pengajuan_detail['angkatan']); ?></span></div>
                            <div class="detail-item"><span class="detail-label">Email</span><span class="detail-value"><?php echo htmlspecialchars($pengajuan_detail['email_mahasiswa']); ?></span></div>
                            <div class="detail-item"><span class="detail-label">No. HP</span><span class="detail-value"><?php echo htmlspecialchars($pengajuan_detail['no_hp_mahasiswa'] ?: '-'); ?></span></div>
                        </div>
                    </div>

                    <div class="info-card">
                        <div class="card-header"><h4><i class="icon">📝</i>Detail Pengajuan KP</h4></div>
                        <div class="card-body">
                             <div class="detail-item"><span class="detail-label">Judul KP</span><span class="detail-value main-detail"><?php echo htmlspecialchars($pengajuan_detail['judul_kp']); ?></span></div>
                             <div class="detail-item"><span class="detail-label">Deskripsi</span><p class="description-text"><?php echo nl2br(htmlspecialchars($pengajuan_detail['deskripsi_kp'])); ?></p></div>
                             <div class="detail-item"><span class="detail-label">Perusahaan</span><span class="detail-value"><?php echo htmlspecialchars($pengajuan_detail['nama_perusahaan'] ?: 'Diajukan Manual'); ?></span></div>
                             <div class="detail-item"><span class="detail-label">Periode Rencana</span><span class="detail-value"><?php echo date("d M Y", strtotime($pengajuan_detail['tanggal_mulai_rencana'])) . " - " . date("d M Y", strtotime($pengajuan_detail['tanggal_selesai_rencana'])); ?></span></div>
                        </div>
                    </div>
                    
                    <div class="info-card">
                        <div class="card-header"><h4><i class="icon">📎</i>Dokumen Terkait</h4></div>
                        <div class="card-body">
                             <?php if (!empty($dokumen_terkait)): ?>
                                <ul class="dokumen-list-admin">
                                    <?php foreach ($dokumen_terkait as $doc): ?>
                                    <li>
                                        <div class="dok-info">
                                            <strong><?php echo htmlspecialchars($doc['nama_dokumen']); ?></strong>
                                            <span><?php echo ucfirst(str_replace('_', ' ', htmlspecialchars($doc['jenis_dokumen']))); ?></span>
                                            <span class="status-badge status-dokumen-<?php echo strtolower(htmlspecialchars($doc['status_verifikasi_dokumen'])); ?>"><?php echo ucfirst(str_replace('_', ' ', htmlspecialchars($doc['status_verifikasi_dokumen']))); ?></span>
                                        </div>
                                        <div class="dok-actions">
                                            <a href="/KP/<?php echo htmlspecialchars($doc['file_path']); ?>" target="_blank" class="btn-icon" title="Lihat File">👁️</a>
                                            <a href="dokumen_verifikasi_form.php?id_dokumen=<?php echo $doc['id_dokumen']; ?>&id_pengajuan=<?php echo $id_pengajuan_url; ?>" class="btn-icon" title="Verifikasi">✔️</a>
                                        </div>
                                    </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php else: ?>
                                <p><em>Belum ada dokumen yang diunggah.</em></p>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="info-card">
                        <div class="card-header"><h4><i class="icon">💬</i>Catatan Historis</h4></div>
                        <div class="card-body">
                            <?php if(!empty($pengajuan_detail['catatan_dosen'])): ?>
                                <div class="catatan-historis"><span class="catatan-label">Dosen:</span><p><?php echo nl2br(htmlspecialchars($pengajuan_detail['catatan_dosen'])); ?></p></div>
                            <?php endif; ?>
                            <?php if(!empty($pengajuan_detail['catatan_admin'])): ?>
                                <div class="catatan-historis"><span class="catatan-label">Admin:</span><p><?php echo nl2br(htmlspecialchars($pengajuan_detail['catatan_admin'])); ?></p></div>
                            <?php endif; ?>
                            <?php if(empty($pengajuan_detail['catatan_dosen']) && empty($pengajuan_detail['catatan_admin'])): ?>
                                <p><em>Belum ada catatan.</em></p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="sidebar-column">
                    <form action="pengajuan_kp_detail_admin.php?id_pengajuan=<?php echo $id_pengajuan_url; ?>" method="POST" class="action-card">
                         <div class="card-header"><h4><i class="icon">⚙️</i>Kelola Pengajuan</h4></div>
                         <div class="card-body">
                            <input type="hidden" name="id_pengajuan" value="<?php echo $id_pengajuan_url; ?>">
                            <div class="form-group">
                                <label for="status_pengajuan">Status Pengajuan</label>
                                <select id="status_pengajuan" name="status_pengajuan">
                                    <?php foreach($opsi_status_admin as $value => $text): ?>
                                    <option value="<?php echo $value; ?>" <?php echo ($pengajuan_detail['status_pengajuan'] == $value) ? 'selected' : ''; ?>>
                                        <?php echo $text; ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="nip_dosen_pembimbing_kp">Dosen Pembimbing</label>
                                <select id="nip_dosen_pembimbing_kp" name="nip_dosen_pembimbing_kp">
                                    <option value="">-- Belum Ditentukan --</option>
                                    <?php foreach($list_semua_dosen as $dosen): ?>
                                    <option value="<?php echo $dosen['nip']; ?>" <?php echo ($pengajuan_detail['nip_dosen_pembimbing_kp'] == $dosen['nip']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($dosen['nama_dosen']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="catatan_admin">Catatan Admin (Baru)</label>
                                <textarea id="catatan_admin" name="catatan_admin" rows="4" placeholder="Tambahkan catatan baru..."></textarea>
                                <small>Catatan baru akan ditambahkan di bawah catatan lama.</small>
                            </div>
                         </div>
                         <div class="card-footer">
                             <button type="submit" name="submit_admin_update" class="btn btn-primary">Simpan Perubahan</button>
                         </div>
                    </form>

                    <div class="action-card">
                        <div class="card-header"><h4><i class="icon">📜</i>Manajemen Surat</h4></div>
                        <div class="card-body action-buttons">
                             <a href="surat_generate.php?tipe=pengantar&id_pengajuan=<?php echo $id_pengajuan_url; ?>" class="btn btn-secondary" target="_blank"><i class="icon">✉️</i>Generate Surat Pengantar</a>
                             <a href="surat_generate.php?tipe=tugas&id_pengajuan=<?php echo $id_pengajuan_url; ?>" class="btn btn-secondary" target="_blank"><i class="icon">👨‍🏫</i>Generate Surat Tugas Dospem</a>
                        </div>
                    </div>

                    <div class="action-card">
                        <div class="card-header"><h4><i class="icon">💯</i>Manajemen Nilai</h4></div>
                        <div class="card-body action-buttons">
                             <a href="nilai_finalisasi_form.php?id_pengajuan=<?php echo $id_pengajuan_url; ?>" class="btn btn-success"><i class="icon">✔️</i>Finalisasi Nilai Akhir</a>
                        </div>
                    </div>
                </div>
            </div>
        <?php elseif(empty($error_message)): ?>
            <div class="message info"><p>Memuat detail pengajuan...</p></div>
        <?php endif; ?>
    </div>
</div>

<style>
/* Modern Detail & Action Layout */
:root {
    --primary-color: #667eea;
    --success-color: #28a745;
    --danger-color: #dc3545;
    --warning-color: #ffc107;
    --info-color: #17a2b8;
    --text-primary: #212529;
    --text-secondary: #6c757d;
    --bg-light: #f8f9fa;
    --border-color: #e9ecef;
    --card-shadow: 0 4px 6px -1px rgba(0,0,0,0.05), 0 2px 4px -1px rgba(0,0,0,0.04);
    --border-radius: 12px;
}
.form-container-modern { max-width: 1400px; padding: 2rem; }
.form-header { text-align: left; border-bottom: 1px solid var(--border-color); padding-bottom: 1rem; margin-bottom: 2rem; display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 1rem; }
.form-header h1 { margin: 0; font-size: 1.8em; }
.form-header p { margin: 5px 0 0 0; color: var(--text-secondary); }

.content-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 2rem; align-items: flex-start; }
@media (max-width: 1024px) { .content-grid { grid-template-columns: 1fr; } }

.info-card, .action-card { background-color: #fff; border: 1px solid var(--border-color); border-radius: var(--border-radius); margin-bottom: 1.5rem; box-shadow: var(--card-shadow); }
.card-header { display:flex; align-items:center; gap: 0.75rem; padding: 1rem 1.5rem; background-color: var(--bg-light); border-bottom: 1px solid var(--border-color); }
.card-header h4 { margin: 0; font-size: 1.1em; color: var(--text-primary); font-weight:600; }
.card-header .icon { font-style: normal; font-size: 1.2em; color: var(--primary-color); }
.card-body { padding: 1.5rem; }
.card-footer { padding: 1rem 1.5rem; background-color: var(--bg-light); border-top: 1px solid var(--border-color); text-align: right; }

.form-group { margin-bottom: 1.25rem; }
.form-group:last-child { margin-bottom: 0; }
.form-group label { display:block; font-weight: 500; margin-bottom: 0.5rem; font-size: 0.9em; }
.form-group select, .form-group textarea { width: 100%; padding: 0.75rem; border-radius: 8px; border: 1px solid #ccc; font-family: inherit; font-size: 1em; }
.form-group small { font-size: 0.85em; color: var(--text-secondary); margin-top: 5px; display:block;}

.detail-item { padding: 0.75rem 0; display:flex; flex-wrap: wrap; justify-content: space-between; align-items: flex-start; }
.detail-item:not(:last-child){ border-bottom: 1px dashed var(--border-color); }
.detail-label { font-size: 0.95em; color: var(--text-secondary); flex-basis: 30%; margin-bottom: 0.5rem; }
.detail-value { font-size: 1em; font-weight: 500; color: var(--text-primary); flex-basis: 70%; text-align: right; }
.detail-value.main-detail { font-weight: 600; font-size: 1.1em; color: var(--primary-color); }
.description-text { white-space: pre-wrap; line-height: 1.6; padding: 1rem; border-radius: 8px; background-color: var(--bg-light); margin-top: 1rem; width:100%; text-align:left; }

.action-buttons { display: flex; flex-direction: column; gap: 1rem; }
.action-buttons .btn { width: 100%; text-align: center; display:flex; align-items:center; justify-content:center; gap: 0.5rem; }
.btn-primary { background-color: var(--primary-color); border:none; color: white; padding: 10px 15px; border-radius:8px; cursor:pointer; }
.btn-primary:hover { background-color: #5a62a8; }
.btn-success { background-color: var(--success-color); border:none; color: white; padding: 10px 15px; border-radius:8px; cursor:pointer;}
.btn-success:hover { background-color: #218838; }
.btn-secondary { background-color: var(--text-secondary); border:none; color: white; padding: 10px 15px; border-radius:8px; cursor:pointer; text-decoration: none;}
.btn-secondary:hover { background-color: #5a6268; }

.catatan-historis { border-left: 3px solid var(--border-color); padding-left: 1rem; margin-bottom: 1rem; }
.catatan-historis .catatan-label { font-weight: 600; color: var(--text-secondary); font-size: 0.9em; }
.catatan-historis p { margin: 0.25rem 0 0 0; white-space: pre-wrap; color: var(--text-primary); }

.dokumen-list-admin { list-style: none; padding: 0; }
.dokumen-list-admin li { display: flex; justify-content: space-between; align-items: center; padding: 1rem; border-radius: 8px; transition: background-color 0.2s; }
.dokumen-list-admin li:not(:last-child) { border-bottom: 1px solid var(--border-color); }
.dokumen-list-admin li:hover { background-color: var(--bg-light); }
.dok-info { flex-grow: 1; display: flex; flex-direction: column; }
.dok-info strong { color: var(--text-primary); margin-bottom: 0.25rem; }
.dok-info span { font-size: 0.85em; color: var(--text-secondary); margin-right: 10px; }
.dok-actions { display: flex; gap: 0.5rem; }
.btn-icon { background: none; border: none; cursor: pointer; font-size: 1.2rem; padding: 5px; text-decoration: none; color: var(--text-secondary); }
.btn-icon:hover { color: var(--primary-color); }

/* Status Dokumen (Warna lebih bijak) */
.status-badge { padding: 4px 10px; border-radius: 20px; font-size: 0.8em; font-weight: 600; border: 1px solid; }
.status-dokumen-pending { background-color: #fffbeb; color: #b45309; border-color: #fde68a; }
.status-dokumen-disetujui { background-color: #f0fdf4; color: #15803d; border-color: #bbf7d0; }
.status-dokumen-revisi_diperlukan { background-color: #fff7ed; color: #c2410c; border-color: #fed7aa; }
.status-dokumen-ditolak { background-color: #fef2f2; color: #b91c1c; border-color: #fecaca; }
</style>

<?php
require_once '../includes/footer.php';
if (isset($conn) && $conn) {
    $conn->close();
}
?>