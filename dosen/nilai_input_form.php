<?php
// /KP/dosen/nilai_input_form.php (Versi Diperbarui)

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

require_once '../includes/auth_check.php';
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'dosen') {
    header("Location: /KP/index.php?error=unauthorized_dosen");
    exit();
}

$nip_dosen_login = $_SESSION['user_id'];
$id_pengajuan_url = null;
$pengajuan_info = null;
$nilai_kp_entry = null;
$error_message = '';
$success_message = '';

if (isset($_GET['id_pengajuan']) && filter_var($_GET['id_pengajuan'], FILTER_VALIDATE_INT)) {
    $id_pengajuan_url = (int)$_GET['id_pengajuan'];
} else {
    $error_message = "ID Pengajuan tidak valid.";
}

require_once '../config/db_connect.php';
if (empty($error_message) && (!$conn || $conn->connect_error)) {
    $error_message = "Koneksi ke database gagal.";
}

function getPengajuanAndNilaiDataForDospem($conn_db, $pengajuan_id, $nip_dosen, &$out_error_message) {
    $data = ['pengajuan' => null, 'nilai_kp' => null];
    $sql_pengajuan = "SELECT pk.id_pengajuan, pk.judul_kp, m.nim, m.nama AS nama_mahasiswa FROM pengajuan_kp pk JOIN mahasiswa m ON pk.nim = m.nim WHERE pk.id_pengajuan = ? AND pk.nip_dosen_pembimbing_kp = ?";
    $stmt_pengajuan = $conn_db->prepare($sql_pengajuan);
    if ($stmt_pengajuan) {
        $stmt_pengajuan->bind_param("is", $pengajuan_id, $nip_dosen);
        $stmt_pengajuan->execute();
        $result_pengajuan = $stmt_pengajuan->get_result();
        if ($result_pengajuan->num_rows === 1) {
            $data['pengajuan'] = $result_pengajuan->fetch_assoc();
            $sql_nilai = "SELECT id_nilai, nilai_dosen_pembimbing, catatan_dosen_pembimbing FROM nilai_kp WHERE id_pengajuan = ?";
            $stmt_nilai = $conn_db->prepare($sql_nilai);
            if ($stmt_nilai) {
                $stmt_nilai->bind_param("i", $pengajuan_id);
                $stmt_nilai->execute();
                $result_nilai = $stmt_nilai->get_result();
                if ($result_nilai->num_rows === 1) $data['nilai_kp'] = $result_nilai->fetch_assoc();
                $stmt_nilai->close();
            }
        } else {
            $out_error_message = "Pengajuan KP tidak ditemukan atau Anda bukan Dosen Pembimbingnya.";
        }
        $stmt_pengajuan->close();
    }
    return $data;
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_nilai_dospem']) && empty($error_message)) {
    $nilai_dospem_input = isset($_POST['nilai_dosen_pembimbing']) && is_numeric($_POST['nilai_dosen_pembimbing']) ? (float)$_POST['nilai_dosen_pembimbing'] : null;
    $catatan_dospem_input = trim($_POST['catatan_dosen_pembimbing']);

    if ($nilai_dospem_input === null || $nilai_dospem_input < 0 || $nilai_dospem_input > 100) {
        $error_message = "Nilai harus berupa angka antara 0 dan 100.";
    } else {
        $data_for_auth = getPengajuanAndNilaiDataForDospem($conn, $id_pengajuan_url, $nip_dosen_login, $error_message);
        if (!empty($data_for_auth['pengajuan'])) {
            $id_nilai_existing = $data_for_auth['nilai_kp']['id_nilai'] ?? null;
            try {
                $conn->begin_transaction();
                if ($id_nilai_existing) {
                    $stmt_action = $conn->prepare("UPDATE nilai_kp SET nilai_dosen_pembimbing = ?, catatan_dosen_pembimbing = ? WHERE id_nilai = ?");
                    $stmt_action->bind_param("dsi", $nilai_dospem_input, $catatan_dospem_input, $id_nilai_existing);
                } else {
                    $stmt_action = $conn->prepare("INSERT INTO nilai_kp (id_pengajuan, nilai_dosen_pembimbing, catatan_dosen_pembimbing) VALUES (?, ?, ?)");
                    $stmt_action->bind_param("ids", $id_pengajuan_url, $nilai_dospem_input, $catatan_dospem_input);
                }
                $stmt_action->execute();
                $stmt_action->close();
                $conn->commit();
                $success_message = "Nilai berhasil disimpan.";
            } catch (Exception $e) {
                $conn->rollback();
                $error_message = "Gagal menyimpan nilai: " . $e->getMessage();
            }
        }
    }
}

if (empty($error_message)) {
    $fetched_data = getPengajuanAndNilaiDataForDospem($conn, $id_pengajuan_url, $nip_dosen_login, $error_message);
    if (!empty($fetched_data['pengajuan'])) {
        $pengajuan_info = $fetched_data['pengajuan'];
        $nilai_kp_entry = $fetched_data['nilai_kp'];
    }
}

$page_title = "Input Nilai Pembimbing";
if ($pengajuan_info) {
    $page_title = "Nilai Dospem: " . htmlspecialchars($pengajuan_info['nama_mahasiswa']);
}
require_once '../includes/header.php';
?>

<div class="main-content-full">
    <div class="form-container-modern">
        <div class="form-header">
            <h1><?php echo htmlspecialchars($page_title); ?></h1>
            <a href="nilai_input_list.php" class="btn btn-secondary">&laquo; Kembali ke Daftar Penilaian</a>
        </div>

        <?php if (!empty($success_message)): ?><div class="message success"><p><?php echo htmlspecialchars($success_message); ?></p></div><?php endif; ?>
        <?php if (!empty($error_message)): ?><div class="message error"><p><?php echo htmlspecialchars($error_message); ?></p></div><?php endif; ?>

        <?php if ($pengajuan_info): ?>
            <div class="info-section">
                <p><strong>Mahasiswa:</strong> <?php echo htmlspecialchars($pengajuan_info['nama_mahasiswa'] . ' (' . $pengajuan_info['nim'] . ')'); ?></p>
                <p><strong>Judul KP:</strong> <?php echo htmlspecialchars($pengajuan_info['judul_kp']); ?></p>
            </div>
            
            <form action="nilai_input_form.php?id_pengajuan=<?php echo $id_pengajuan_url; ?>" method="POST" class="modern-form">
                <fieldset>
                    <div class="fieldset-header">
                        <span class="fieldset-number">📝</span>
                        <h4>Formulir Penilaian Pembimbing</h4>
                    </div>
                    <div class="form-group">
                        <label for="nilai_dosen_pembimbing">Nilai dari Dosen Pembimbing (0-100) (*)</label>
                        <input type="number" id="nilai_dosen_pembimbing" name="nilai_dosen_pembimbing" class="form-control"
                               min="0" max="100" step="0.01"
                               value="<?php echo htmlspecialchars($nilai_kp_entry['nilai_dosen_pembimbing'] ?? ''); ?>" required>
                        <small>Nilai ini mencakup evaluasi terhadap laporan, proses bimbingan, dan penguasaan materi.</small>
                    </div>
                    <div class="form-group">
                        <label for="catatan_dosen_pembimbing">Catatan / Feedback Keseluruhan (Opsional)</label>
                        <textarea id="catatan_dosen_pembimbing" name="catatan_dosen_pembimbing" class="form-control" rows="6" placeholder="Berikan evaluasi atau catatan keseluruhan..."><?php echo htmlspecialchars($nilai_kp_entry['catatan_dosen_pembimbing'] ?? ''); ?></textarea>
                    </div>
                </fieldset>
                <div class="form-actions">
                    <button type="submit" name="submit_nilai_dospem" class="btn btn-primary btn-submit">Simpan Nilai</button>
                </div>
            </form>
        <?php elseif(empty($error_message)): ?>
            <div class="message info"><p>Memuat data untuk penilaian...</p></div>
        <?php endif; ?>
    </div>
</div>

<style>
    /* Menggunakan gaya dari halaman form sebelumnya */
    .form-container-modern { max-width: 900px; margin: 20px auto; background: #fff; padding: 2.5rem; border-radius: var(--border-radius); box-shadow: var(--card-shadow); }
    .info-section { background-color: #e9f5ff; border-left: 5px solid var(--primary-color); padding: 1rem 1.5rem; margin-bottom: 2rem; border-radius: 8px; }
</style>

<?php
require_once '../includes/footer.php';
if (isset($conn) && $conn) { $conn->close(); }
?>