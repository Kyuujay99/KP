<?php
// /KP/dosen/nilai_input_list.php

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

// Sertakan file koneksi database
require_once '../config/db_connect.php';

$list_kp_untuk_dinilai = [];
$error_db = '';

// 2. AMBIL DATA PENGAJUAN KP DARI MAHASISWA BIMBINGAN YANG SIAP DINILAI
if ($conn && ($conn instanceof mysqli)) {
    // Status KP yang dianggap siap untuk dinilai oleh Dosen Pembimbing.
    // Misalnya: 'selesai_pelaksanaan', 'laporan_disetujui', atau setelah seminar 'selesai'
    // Sesuaikan dengan alur bisnis Anda.
    $status_siap_dinilai = ['selesai_pelaksanaan', 'laporan_disetujui', 'selesai_dinilai']; // 'selesai_dinilai' disertakan untuk melihat yg sudah dinilai
    $status_placeholders = implode(',', array_fill(0, count($status_siap_dinilai), '?'));

    $sql = "SELECT
                pk.id_pengajuan,
                m.nim,
                m.nama AS nama_mahasiswa,
                pk.judul_kp,
                pk.status_pengajuan,
                nk.nilai_dosen_pembimbing, /* Untuk mengecek apakah sudah dinilai */
                nk.id_nilai /* Untuk mengecek apakah record nilai sudah ada */
            FROM pengajuan_kp pk
            JOIN mahasiswa m ON pk.nim = m.nim
            LEFT JOIN nilai_kp nk ON pk.id_pengajuan = nk.id_pengajuan
            WHERE pk.nip_dosen_pembimbing_kp = ? 
              AND pk.status_pengajuan IN ($status_placeholders)
            ORDER BY pk.status_pengajuan ASC, m.nama ASC, pk.id_pengajuan DESC";
            // Urutkan agar yang belum dinilai (jika bisa dideteksi dari status) atau yang statusnya lebih awal muncul duluan

    $stmt = $conn->prepare($sql);

    if ($stmt) {
        $types = 's' . str_repeat('s', count($status_siap_dinilai));
        $bind_params = array_merge([$nip_dosen_login], $status_siap_dinilai);

        $ref_params = [];
        foreach ($bind_params as $key => $value) {
            $ref_params[$key] = &$bind_params[$key];
        }
        array_unshift($ref_params, $types);
        call_user_func_array([$stmt, 'bind_param'], $ref_params);
        
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $list_kp_untuk_dinilai[] = $row;
            }
        }
        $stmt->close();
    } else {
        $error_db = "Gagal menyiapkan query untuk mengambil data KP yang akan dinilai: " . (($conn->error) ? htmlspecialchars($conn->error) : "Kesalahan tidak diketahui.");
    }
} else {
    $error_db = "Koneksi database gagal atau tidak valid.";
}

// Set judul halaman dan sertakan header
$page_title = "Daftar KP untuk Penilaian Dosen Pembimbing";
require_once '../includes/header.php';
?>

<div class="page-layout-wrapper">

    <?php require_once '../includes/sidebar_dosen.php'; ?>

    <main class="main-content-area">
        <div class="list-container nilai-input-dosen-list">
            <h1><?php echo htmlspecialchars($page_title); ?></h1>
            <p>Berikut adalah daftar Kerja Praktek mahasiswa bimbingan Anda yang telah mencapai tahap untuk diberikan penilaian akhir oleh Dosen Pembimbing.</p>
            <hr>

            <?php if (!empty($error_db)): ?>
                <div class="message error">
                    <p><?php echo $error_db; ?></p>
                </div>
            <?php endif; ?>

            <?php if (empty($list_kp_untuk_dinilai) && empty($error_db)): ?>
                <div class="message info">
                    <p>Saat ini tidak ada Kerja Praktek mahasiswa bimbingan Anda yang siap untuk dinilai atau sudah selesai dinilai semua.</p>
                </div>
            <?php elseif (!empty($list_kp_untuk_dinilai)): ?>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>No.</th>
                                <th>NIM</th>
                                <th>Nama Mahasiswa</th>
                                <th>Judul KP</th>
                                <th>Status KP</th>
                                <th>Nilai Dospem</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $counter = 1; ?>
                            <?php foreach ($list_kp_untuk_dinilai as $kp): ?>
                                <tr>
                                    <td><?php echo $counter++; ?></td>
                                    <td><?php echo htmlspecialchars($kp['nim']); ?></td>
                                    <td><?php echo htmlspecialchars($kp['nama_mahasiswa']); ?></td>
                                    <td><?php echo htmlspecialchars($kp['judul_kp']); ?></td>
                                    <td>
                                        <span class="status-badge status-<?php echo strtolower(str_replace([' ', '_'], '-', $kp['status_pengajuan'])); ?>">
                                            <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $kp['status_pengajuan']))); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if (isset($kp['nilai_dosen_pembimbing']) && $kp['nilai_dosen_pembimbing'] !== null): ?>
                                            <span class="nilai-sudah"><?php echo htmlspecialchars($kp['nilai_dosen_pembimbing']); ?> (Sudah Diinput)</span>
                                        <?php elseif (isset($kp['id_nilai']) && $kp['nilai_dosen_pembimbing'] === null): ?>
                                             <span class="nilai-kosong"><em>Belum Diinput</em></span>
                                        <?php else: ?>
                                            <span class="nilai-kosong"><em>Belum Ada Record Nilai</em></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="/KP/dosen/nilai_input_form.php?id_pengajuan=<?php echo $kp['id_pengajuan']; ?>" class="btn btn-primary btn-sm">
                                            <?php echo (isset($kp['nilai_dosen_pembimbing']) && $kp['nilai_dosen_pembimbing'] !== null) ? 'Edit Nilai' : 'Input Nilai'; ?>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

        </div>
    </main>

</div>

<style>
    /* Asumsikan CSS umum dari header, sidebar, tabel, message, btn, status-badge sudah ada */
    .nilai-input-dosen-list h1 { margin-top: 0; margin-bottom: 10px; }
    .nilai-input-dosen-list hr { margin-bottom: 20px; }
    .nilai-input-dosen-list p { margin-bottom: 15px; }

    .data-table td .nilai-sudah {
        color: green;
        font-weight: bold;
    }
    .data-table td .nilai-kosong {
        color: #777;
        font-style: italic;
    }

    /* Pastikan styling untuk status-badge sudah ada dan konsisten */
    /* Contoh beberapa status yang mungkin relevan (sudah ada di file lain) */
    .status-selesai-pelaksanaan { background-color: #28a745; /* Hijau */ }
    .status-laporan-disetujui { background-color: #d63384; /* Pink */ }
    .status-selesai-dinilai { background-color: #1f2023; /* Dark */ }
</style>

<?php
require_once '../includes/footer.php';

if (isset($conn) && ($conn instanceof mysqli)) {
    $conn->close();
}
?>