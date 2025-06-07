<?php
// /KP/dosen/pengajuan_list.php (Versi Diperbarui)

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
require_once '../config/db_connect.php';

$list_pengajuan_kp = [];
$error_db = '';

// 2. AMBIL DATA PENGAJUAN KP YANG PERLU DITINJAU
if ($conn && ($conn instanceof mysqli)) {
    // Status yang relevan untuk dosen tinjau/verifikasi
    $relevan_statuses = ['diajukan_mahasiswa', 'diverifikasi_dospem'];
    $status_placeholders = implode(',', array_fill(0, count($relevan_statuses), '?'));

    $sql = "SELECT
                pk.id_pengajuan,
                m.nim,
                m.nama AS nama_mahasiswa,
                pk.judul_kp,
                pr.nama_perusahaan,
                pk.tanggal_pengajuan,
                pk.status_pengajuan
            FROM pengajuan_kp pk
            JOIN mahasiswa m ON pk.nim = m.nim
            LEFT JOIN perusahaan pr ON pk.id_perusahaan = pr.id_perusahaan
            WHERE pk.nip_dosen_pembimbing_kp = ? 
              AND pk.status_pengajuan IN ($status_placeholders)
            ORDER BY pk.tanggal_pengajuan ASC, pk.id_pengajuan ASC";

    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $types = 's' . str_repeat('s', count($relevan_statuses));
        $params = array_merge([$nip_dosen_login], $relevan_statuses);
        
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $list_pengajuan_kp[] = $row;
        }
        $stmt->close();
    } else {
        $error_db = "Gagal menyiapkan query: " . $conn->error;
    }
} else {
    $error_db = "Koneksi database gagal atau tidak valid.";
}

$page_title = "Verifikasi Pengajuan KP";
require_once '../includes/header.php';
?>

<div class="main-content-full">
    <div class="list-container">
        <div class="list-header">
            <h1><?php echo htmlspecialchars($page_title); ?></h1>
            <p>Daftar pengajuan Kerja Praktek dari mahasiswa yang memerlukan tinjauan dan persetujuan dari Anda.</p>
        </div>

        <?php if (!empty($error_db)): ?>
            <div class="message error"><p><?php echo $error_db; ?></p></div>
        <?php endif; ?>

        <?php if (empty($list_pengajuan_kp) && empty($error_db)): ?>
            <div class="message info">
                <h4>Tidak Ada Tugas</h4>
                <p>Saat ini tidak ada pengajuan Kerja Praktek yang memerlukan tindakan dari Anda. Semua sudah terverifikasi.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Mahasiswa</th>
                            <th>Judul & Perusahaan</th>
                            <th>Tanggal Diajukan</th>
                            <th>Status Saat Ini</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($list_pengajuan_kp as $pengajuan): ?>
                            <tr>
                                <td>
                                    <div class="mahasiswa-info">
                                        <div class="mahasiswa-avatar"><?php echo strtoupper(substr($pengajuan['nama_mahasiswa'], 0, 1)); ?></div>
                                        <div>
                                            <div class="mahasiswa-nama"><?php echo htmlspecialchars($pengajuan['nama_mahasiswa']); ?></div>
                                            <div class="mahasiswa-nim"><?php echo htmlspecialchars($pengajuan['nim']); ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="judul-kp-text"><?php echo htmlspecialchars($pengajuan['judul_kp']); ?></div>
                                    <div class="perusahaan-text"><?php echo $pengajuan['nama_perusahaan'] ? htmlspecialchars($pengajuan['nama_perusahaan']) : '<em>Diajukan Manual</em>'; ?></div>
                                </td>
                                <td><?php echo date("d M Y", strtotime($pengajuan['tanggal_pengajuan'])); ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo strtolower(str_replace('_', '-', $pengajuan['status_pengajuan'])); ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', htmlspecialchars($pengajuan['status_pengajuan']))); ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="pengajuan_verifikasi_detail.php?id_pengajuan=<?php echo $pengajuan['id_pengajuan']; ?>" class="btn btn-primary btn-sm">
                                        Verifikasi / Detail
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
    .list-container { max-width: 1400px; margin: 20px auto; padding: 2rem; }
    .list-header { margin-bottom: 1.5rem; text-align: center; }
    .list-header h1 { font-weight: 700; color: var(--dark-color); }
    .list-header p { font-size: 1.1em; color: var(--secondary-color); }

    .table-responsive {
        background-color: #fff;
        border-radius: var(--border-radius);
        box-shadow: var(--card-shadow);
        overflow: hidden;
    }
    .data-table { width: 100%; border-collapse: collapse; }
    .data-table th, .data-table td {
        padding: 1rem 1.5rem;
        border-bottom: 1px solid var(--border-color);
        text-align: left;
        vertical-align: middle;
    }
    .data-table th {
        background-color: #f8f9fa;
        font-weight: 600;
        font-size: 0.9em;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: #495057;
    }
    .data-table tbody tr:hover { background-color: #f1f7ff; }

    .mahasiswa-info { display: flex; align-items: center; gap: 15px; }
    .mahasiswa-avatar {
        width: 45px; height: 45px;
        border-radius: 50%;
        background-color: var(--primary-color); color: white;
        display: inline-flex; align-items: center; justify-content: center;
        font-size: 1.2em; font-weight: 600; flex-shrink: 0;
    }
    .mahasiswa-nama { font-weight: 600; color: var(--dark-color); }
    .mahasiswa-nim { font-size: 0.9em; color: #6c757d; }
    
    .judul-kp-text { font-weight: 500; color: var(--dark-color); }
    .perusahaan-text { font-size: 0.9em; color: #6c757d; }
    
    .status-badge {
        padding: 5px 12px; border-radius: 20px;
        font-size: 0.8em; font-weight: 600; color: #fff;
    }
    .status-diajukan-mahasiswa { background-color: #ffc107; color:#212529; }
    .status-diverifikasi-dospem { background-color: #fd7e14; }

    .message { padding: 1.5rem; border-radius: 8px; text-align: center; }
    .message.info { background-color: #e9f5ff; color: #0056b3; }
    .message.info h4 { margin-top: 0; margin-bottom: 0.5rem; color: #0056b3; }
    
    .btn-primary { background-color: var(--primary-color); color: white; border: none; font-weight: 600; }
    .btn-primary:hover { background-color: var(--primary-hover); }
</style>

<?php
require_once '../includes/footer.php';
if (isset($conn) && $conn) {
    $conn->close();
}
?>