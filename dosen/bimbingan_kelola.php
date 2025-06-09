<?php
// /KP/dosen/bimbingan_kelola.php (Versi dengan Fitur Edit Bimbingan)

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
$riwayat_bimbingan = [];
$error_message = '';
$success_message = '';

// 2. VALIDASI DAN AMBIL ID PENGAJUAN DARI URL
if (isset($_GET['id_pengajuan']) && filter_var($_GET['id_pengajuan'], FILTER_VALIDATE_INT)) {
    $id_pengajuan_url = (int)$_GET['id_pengajuan'];
} else {
    $error_message = "ID Pengajuan tidak valid atau tidak ditemukan.";
}

require_once '../config/db_connect.php';

// 3. FUNGSI UNTUK MENGAMBIL DATA (Reusable)
function getBimbinganData($conn_db, $id_pengajuan, $nip_dosen, &$out_error_message) {
    $data = ['pengajuan' => null, 'riwayat' => []];
    $sql_pengajuan = "SELECT pk.id_pengajuan, pk.judul_kp, m.nim, m.nama AS nama_mahasiswa
                      FROM pengajuan_kp pk JOIN mahasiswa m ON pk.nim = m.nim
                      WHERE pk.id_pengajuan = ? AND pk.nip_dosen_pembimbing_kp = ?";
    $stmt_pengajuan = $conn_db->prepare($sql_pengajuan);
    if ($stmt_pengajuan) {
        $stmt_pengajuan->bind_param("is", $id_pengajuan, $nip_dosen);
        $stmt_pengajuan->execute();
        $result_pengajuan = $stmt_pengajuan->get_result();
        if ($result_pengajuan->num_rows === 1) {
            $data['pengajuan'] = $result_pengajuan->fetch_assoc();
            $sql_riwayat = "SELECT * FROM bimbingan_kp WHERE id_pengajuan = ? ORDER BY tanggal_bimbingan DESC";
            $stmt_riwayat = $conn_db->prepare($sql_riwayat);
            if ($stmt_riwayat) {
                $stmt_riwayat->bind_param("i", $id_pengajuan);
                $stmt_riwayat->execute();
                $result_riwayat = $stmt_riwayat->get_result();
                while ($row_riwayat = $result_riwayat->fetch_assoc()) {
                    $data['riwayat'][] = $row_riwayat;
                }
                $stmt_riwayat->close();
            }
        } else {
            $out_error_message = "Pengajuan KP tidak ditemukan atau Anda bukan pembimbingnya.";
        }
        $stmt_pengajuan->close();
    }
    return $data;
}

// 4. PROSES FORM (Bisa untuk Tambah atau Update)
if ($_SERVER["REQUEST_METHOD"] == "POST" && $id_pengajuan_url !== null && empty($error_message)) {
    // A. Proses Penambahan Bimbingan Baru
    if (isset($_POST['submit_bimbingan'])) {
        $tanggal_bimbingan_input = $_POST['tanggal_bimbingan'];
        $topik_bimbingan_input = trim($_POST['topik_bimbingan']);
        $catatan_dosen_input = trim($_POST['catatan_dosen']);
        
        if (empty($tanggal_bimbingan_input) || empty($topik_bimbingan_input)) {
            $error_message = "Tanggal dan Topik Bimbingan wajib diisi.";
        } else {
            try {
                $tanggal_bimbingan_db = (new DateTime($tanggal_bimbingan_input))->format('Y-m-d H:i:s');
                $sql_insert = "INSERT INTO bimbingan_kp (id_pengajuan, nip_pembimbing, tanggal_bimbingan, topik_bimbingan, catatan_dosen, status_bimbingan) VALUES (?, ?, ?, ?, ?, 'selesai')";
                $stmt_insert = $conn->prepare($sql_insert);
                $stmt_insert->bind_param("issss", $id_pengajuan_url, $nip_dosen_login, $tanggal_bimbingan_db, $topik_bimbingan_input, $catatan_dosen_input);
                if ($stmt_insert->execute()) {
                    $success_message = "Sesi bimbingan baru berhasil ditambahkan!";
                } else {
                    $error_message = "Gagal menyimpan sesi bimbingan: " . $stmt_insert->error;
                }
                $stmt_insert->close();
            } catch (Exception $e) {
                $error_message = "Format tanggal bimbingan tidak valid.";
            }
        }
    }

    // B. Proses Update Bimbingan
    if (isset($_POST['update_bimbingan'])) {
        $id_bimbingan_update = filter_input(INPUT_POST, 'id_bimbingan', FILTER_VALIDATE_INT);
        $tanggal_bimbingan_input = $_POST['edit_tanggal_bimbingan'];
        $topik_bimbingan_input = trim($_POST['edit_topik_bimbingan']);
        $catatan_dosen_input = trim($_POST['edit_catatan_dosen']);

        if (!$id_bimbingan_update || empty($tanggal_bimbingan_input) || empty($topik_bimbingan_input)) {
            $error_message = "Data untuk update tidak lengkap.";
        } else {
            try {
                $tanggal_bimbingan_db = (new DateTime($tanggal_bimbingan_input))->format('Y-m-d H:i:s');
                // Query update, pastikan hanya dosen yg bersangkutan yg bisa update
                $sql_update = "UPDATE bimbingan_kp SET tanggal_bimbingan = ?, topik_bimbingan = ?, catatan_dosen = ? WHERE id_bimbingan = ? AND nip_pembimbing = ?";
                $stmt_update = $conn->prepare($sql_update);
                $stmt_update->bind_param("sssis", $tanggal_bimbingan_db, $topik_bimbingan_input, $catatan_dosen_input, $id_bimbingan_update, $nip_dosen_login);
                if ($stmt_update->execute() && $stmt_update->affected_rows > 0) {
                    $success_message = "Sesi bimbingan berhasil diperbarui!";
                } else if ($stmt_update->affected_rows === 0) {
                    $error_message = "Tidak ada perubahan data atau Anda tidak berwenang mengubah sesi ini.";
                } else {
                    $error_message = "Gagal memperbarui sesi bimbingan: " . $stmt_update->error;
                }
                $stmt_update->close();
            } catch (Exception $e) {
                 $error_message = "Format tanggal untuk update tidak valid.";
            }
        }
    }
}

// Selalu ambil data terbaru setelah POST
if ($id_pengajuan_url && empty($error_message_on_load)) {
    $fetched_data = getBimbinganData($conn, $id_pengajuan_url, $nip_dosen_login, $error_message);
    $pengajuan_info = $fetched_data['pengajuan'];
    $riwayat_bimbingan = $fetched_data['riwayat'];
}

$page_title = "Kelola Bimbingan KP";
if ($pengajuan_info) {
    $page_title = "Bimbingan: " . htmlspecialchars($pengajuan_info['nama_mahasiswa']);
}
require_once '../includes/header.php';
?>

<div class="main-content-dark">
    <div class="detail-container">
        <div class="detail-header">
            <h1><i class="icon-manage"></i><?php echo htmlspecialchars($page_title); ?></h1>
            <a href="bimbingan_mahasiswa_list.php" class="btn btn-secondary btn-sm"><i class="icon-back"></i> Kembali</a>
        </div>
        
        <?php if (!empty($success_message)): ?><div class="message success"><p><?php echo htmlspecialchars($success_message); ?></p></div><?php endif; ?>
        <?php if (!empty($error_message)): ?><div class="message error"><p><?php echo htmlspecialchars($error_message); ?></p></div><?php endif; ?>
        
        <?php if ($pengajuan_info): ?>
            <div class="content-grid">
                <!-- Kolom Kiri: Form Input Bimbingan -->
                <div class="form-column">
                    <div class="form-card">
                        <div class="card-header"><h3><i class="icon-add"></i>Catat Sesi Bimbingan Baru</h3></div>
                        <div class="card-body">
                            <form action="bimbingan_kelola.php?id_pengajuan=<?php echo $id_pengajuan_url; ?>" method="POST">
                                <div class="form-group">
                                    <label for="tanggal_bimbingan">Tanggal & Waktu Bimbingan (*)</label>
                                    <input type="datetime-local" id="tanggal_bimbingan" name="tanggal_bimbingan" class="form-control" value="<?php echo date('Y-m-d\TH:i'); ?>" required>
                                </div>
                                <div class="form-group">
                                    <label for="topik_bimbingan">Topik Bimbingan (*)</label>
                                    <input type="text" id="topik_bimbingan" name="topik_bimbingan" class="form-control" required placeholder="Contoh: Review Bab 1 & 2">
                                </div>
                                <div class="form-group">
                                    <label for="catatan_dosen">Catatan / Arahan untuk Mahasiswa</label>
                                    <textarea id="catatan_dosen" name="catatan_dosen" class="form-control" rows="5" placeholder="Tuliskan feedback, arahan, atau poin revisi..."></textarea>
                                </div>
                                <button type="submit" name="submit_bimbingan" class="btn btn-primary">Simpan Catatan</button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Kolom Kanan: Riwayat Bimbingan -->
                <div class="history-column">
                    <div class="history-header">
                        <h3><i class="icon-history"></i>Riwayat Bimbingan</h3>
                    </div>
                    <ul class="bimbingan-history-list">
                        <?php if (empty($riwayat_bimbingan)): ?>
                            <li class="bimbingan-item-empty"><p>Belum ada riwayat bimbingan.</p></li>
                        <?php else: ?>
                            <?php foreach ($riwayat_bimbingan as $sesi): ?>
                                <li class="bimbingan-item">
                                    <div class="bimbingan-header">
                                        <div class="header-info">
                                            <span class="bimbingan-date"><?php echo date("d F Y, H:i", strtotime($sesi['tanggal_bimbingan'])); ?></span>
                                            <h5 class="bimbingan-topic"><?php echo htmlspecialchars($sesi['topik_bimbingan']); ?></h5>
                                        </div>
                                        <div class="header-actions">
                                            <span class="status-bimbingan status-bimbingan-<?php echo strtolower(str_replace('_', '-', $sesi['status_bimbingan'])); ?>"><?php echo ucfirst(str_replace('_', ' ', htmlspecialchars($sesi['status_bimbingan']))); ?></span>
                                            <button type="button" class="btn-icon btn-edit" onclick="openEditModal(this)"
                                                data-id="<?php echo $sesi['id_bimbingan']; ?>"
                                                data-tanggal="<?php echo (new DateTime($sesi['tanggal_bimbingan']))->format('Y-m-d\TH:i'); ?>"
                                                data-topik="<?php echo htmlspecialchars($sesi['topik_bimbingan']); ?>"
                                                data-catatan="<?php echo htmlspecialchars($sesi['catatan_dosen']); ?>">
                                                <i class="icon-edit"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <?php if (!empty($sesi['catatan_mahasiswa'])): ?>
                                        <div class="catatan catatan-mahasiswa"><strong>Catatan Mahasiswa:</strong><p><?php echo nl2br(htmlspecialchars($sesi['catatan_mahasiswa'])); ?></p></div>
                                    <?php endif; ?>
                                    <?php if (!empty($sesi['catatan_dosen'])): ?>
                                        <div class="catatan catatan-dosen"><strong>Catatan Anda:</strong><p><?php echo nl2br(htmlspecialchars($sesi['catatan_dosen'])); ?></p></div>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        <?php elseif(empty($error_message)): ?>
            <div class="message info"><p>Memuat data bimbingan...</p></div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal untuk Edit Bimbingan -->
<div id="editBimbinganModal" class="modal-overlay">
    <div class="modal-container">
        <form action="bimbingan_kelola.php?id_pengajuan=<?php echo $id_pengajuan_url; ?>" method="POST">
            <div class="modal-header">
                <h2><i class="icon-edit"></i> Edit Sesi Bimbingan</h2>
                <button type="button" class="modal-close" onclick="closeEditModal()">&times;</button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="id_bimbingan" id="edit_id_bimbingan">
                <div class="form-group">
                    <label for="edit_tanggal_bimbingan">Tanggal & Waktu Bimbingan (*)</label>
                    <input type="datetime-local" id="edit_tanggal_bimbingan" name="edit_tanggal_bimbingan" class="form-control" required>
                </div>
                <div class="form-group">
                    <label for="edit_topik_bimbingan">Topik Bimbingan (*)</label>
                    <input type="text" id="edit_topik_bimbingan" name="edit_topik_bimbingan" class="form-control" required>
                </div>
                <div class="form-group">
                    <label for="edit_catatan_dosen">Catatan / Arahan untuk Mahasiswa</label>
                    <textarea id="edit_catatan_dosen" name="edit_catatan_dosen" class="form-control" rows="5"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeEditModal()">Batal</button>
                <button type="submit" name="update_bimbingan" class="btn btn-primary">Simpan Perubahan</button>
            </div>
        </form>
    </div>
</div>

<style>
/* Ikon */
.icon-manage::before { content: "✍️"; margin-right: 12px; }
.icon-back::before { content: "⬅️"; margin-right: 6px; }
.icon-add::before, .icon-history::before, .icon-edit::before { margin-right: 10px; }
.icon-add::before { content: "➕"; }
.icon-history::before { content: "📖"; }
.icon-edit::before { content: "✏️"; }

/* Variabel Warna */
:root {
    --primary-color: #3b82f6; --primary-hover: #2563eb; --success-color: #10b981;
    --secondary-color: #94a3b8; --text-color: #e2e8f0; --border-color: #334155;
    --bg-dark: #0f172a; --bg-card: #1e293b; --bg-hover: #334155;
    --purple-bg: rgba(139, 92, 246, 0.1); --purple-border: #8b5cf6;
    --blue-bg: rgba(59, 130, 246, 0.1); --blue-border: #3b82f6;
    --card-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.3); --border-radius: 12px;
}

/* Layout & Grid */
.main-content-dark { background-color: var(--bg-dark); padding: 2rem; color: var(--text-color); min-height: 100vh; }
.detail-container { max-width: 1400px; margin: 0 auto; }
.detail-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; }
.detail-header h1 { font-size: 2em; margin: 0; }
.content-grid { display: grid; grid-template-columns: 1fr; gap: 2rem; }
@media (min-width: 992px) { .content-grid { grid-template-columns: minmax(0, 1fr) minmax(0, 1.2fr); } }

/* Kartu & Form */
.form-card, .history-column { background-color: var(--bg-card); border: 1px solid var(--border-color); border-radius: var(--border-radius); }
.card-header, .history-header { background-color: var(--bg-dark); padding: 1rem 1.5rem; border-bottom: 1px solid var(--border-color); }
.card-header h3, .history-header h3 { font-size: 1.2em; margin: 0; display: flex; align-items: center; }
.card-body { padding: 1.5rem; }
.form-group { margin-bottom: 1.5rem; }
.form-group label { display: block; margin-bottom: 0.5rem; font-weight: 500; }
.form-control { width: 100%; padding: 0.75rem 1rem; background-color: var(--bg-dark); border: 2px solid var(--border-color); color: var(--text-color); border-radius: 8px; transition: border-color 0.2s; }
.form-control:focus { outline: none; border-color: var(--primary-color); }
textarea.form-control { resize: vertical; min-height: 120px; }

/* Tombol & Pesan */
.btn { text-decoration: none; padding: 10px 20px; border-radius: 8px; font-weight: 600; transition: all 0.2s; border: none; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; }
.btn-primary { background-color: var(--primary-color); color: white; width: 100%; }
.btn-primary:hover { background-color: var(--primary-hover); }
.btn-secondary { background-color: var(--bg-hover); color: var(--text-color); }
.message { padding: 1rem 1.5rem; border-radius: 8px; margin-bottom: 1.5rem; }
.message.success { background-color: rgba(16, 185, 129, 0.1); color: #6ee7b7; }
.message.error { background-color: rgba(239, 68, 68, 0.1); color: #fca5a5; }
.message.info { background-color: var(--blue-bg); color: #93c5fd; }

/* Riwayat Bimbingan */
.history-header { border-radius: var(--border-radius) var(--border-radius) 0 0; }
.bimbingan-history-list { list-style: none; padding: 1.5rem; margin: 0; max-height: 800px; overflow-y: auto; }
.bimbingan-item { background-color: var(--bg-dark); border: 1px solid var(--border-color); padding: 1.5rem; margin-bottom: 1rem; border-radius: 10px; }
.bimbingan-item:last-child { margin-bottom: 0; }
.bimbingan-item-empty { text-align: center; padding: 2rem; color: var(--secondary-color); }
.bimbingan-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1rem; padding-bottom: 1rem; border-bottom: 1px dashed var(--border-color); }
.header-actions { display: flex; align-items: center; gap: 10px; }
.btn-icon { background: none; border: none; color: var(--secondary-color); cursor: pointer; padding: 5px; border-radius: 50%; }
.btn-icon:hover { background-color: var(--bg-hover); color: var(--text-color); }
.bimbingan-date { font-size: 0.9em; color: var(--secondary-color); font-weight: 500; }
.bimbingan-topic { margin: 5px 0 0 0; font-size: 1.2em; font-weight: 600; color: var(--text-color); }
.catatan { padding: 1rem; margin-top: 1rem; border-radius: 8px; font-size: 0.95em; border-left: 4px solid; }
.catatan strong { display: block; margin-bottom: 0.5rem; font-weight: 600; }
.catatan p { margin: 0; line-height: 1.6; color: var(--secondary-color); }
.catatan-mahasiswa { border-color: var(--purple-border); background-color: var(--purple-bg); }
.catatan-mahasiswa strong { color: #c4b5fd; }
.catatan-dosen { border-color: var(--blue-border); background-color: var(--blue-bg); }
.catatan-dosen strong { color: #93c5fd; }
.status-bimbingan { padding: 5px 12px; border-radius: 20px; font-size: 0.8em; font-weight: bold; }
.status-bimbingan-selesai { background-color: #28a745; color: #fff; }

/* Modal */
.modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.7); backdrop-filter: blur(8px); z-index: 1000; animation: fadeIn 0.3s ease-out; }
.modal-overlay.active { display: flex; align-items: center; justify-content: center; }
.modal-container { background: var(--bg-card); color: var(--text-color); border: 1px solid var(--border-color); border-radius: var(--border-radius); width: 100%; max-width: 600px; }
.modal-header, .modal-footer { background: var(--bg-dark); border-color: var(--border-color); padding: 1.5rem; display: flex; align-items: center; }
.modal-header { justify-content: space-between; border-bottom: 1px solid var(--border-color); }
.modal-footer { justify-content: flex-end; gap: 1rem; border-top: 1px solid var(--border-color); }
.modal-body { padding: 2rem; }
.modal-close { background: none; border: none; font-size: 1.5rem; color: var(--secondary-color); cursor: pointer; }
</style>

<script>
function openEditModal(button) {
    const modal = document.getElementById('editBimbinganModal');
    // Ambil data dari atribut data-* tombol yang diklik
    const id = button.dataset.id;
    const tanggal = button.dataset.tanggal;
    const topik = button.dataset.topik;
    const catatan = button.dataset.catatan;

    // Isi form di dalam modal dengan data yang ada
    document.getElementById('edit_id_bimbingan').value = id;
    document.getElementById('edit_tanggal_bimbingan').value = tanggal;
    document.getElementById('edit_topik_bimbingan').value = topik;
    document.getElementById('edit_catatan_dosen').value = catatan;

    // Tampilkan modal
    modal.style.display = 'flex';
}

function closeEditModal() {
    document.getElementById('editBimbinganModal').style.display = 'none';
}
</script>

<?php
require_once '../includes/footer.php';
if (isset($conn)) { $conn->close(); }
?>
