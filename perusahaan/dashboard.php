<?php
// /KP/perusahaan/dashboard.php

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// 1. OTENTIKASI DAN OTORISASI
require_once '../includes/auth_check.php'; // Cek apakah ada user login
// Sekarang, cek apakah peran user adalah 'perusahaan'
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'perusahaan') {
    session_unset();
    session_destroy();
    header("Location: /KP/index.php?error=unauthorized_perusahaan");
    exit();
}

// Ambil informasi perusahaan yang login dari session
// Asumsi: saat login perusahaan, $_SESSION['user_id'] diisi dengan id_perusahaan
//          dan $_SESSION['user_nama'] diisi dengan nama_perusahaan.
$id_perusahaan_login = $_SESSION['user_id'];
$nama_perusahaan_login = $_SESSION['user_nama'];

// Sertakan file koneksi database (jika perlu mengambil data untuk dashboard)
require_once '../config/db_connect.php';

// Contoh: Ambil jumlah mahasiswa yang sedang KP aktif di perusahaan ini
$jumlah_mahasiswa_kp_aktif = 0;
if ($conn && ($conn instanceof mysqli)) {
    $sql_count = "SELECT COUNT(DISTINCT pk.nim) AS total
                  FROM pengajuan_kp pk
                  WHERE pk.id_perusahaan = ? AND pk.status_pengajuan = 'kp_berjalan'";
    $stmt_count = $conn->prepare($sql_count);
    if ($stmt_count) {
        $stmt_count->bind_param("i", $id_perusahaan_login);
        $stmt_count->execute();
        $result_count = $stmt_count->get_result();
        if ($row_count = $result_count->fetch_assoc()) {
            $jumlah_mahasiswa_kp_aktif = $row_count['total'];
        }
        $stmt_count->close();
    }
}

// Contoh: Ambil jumlah mahasiswa yang perlu dinilai pembimbing lapangan
$jumlah_perlu_penilaian_lapangan = 0;
if ($conn && ($conn instanceof mysqli)) {
    // Asumsi: Mahasiswa siap dinilai lapangan jika KP berjalan atau selesai pelaksanaan
    // dan belum ada nilai pembimbing lapangan di tabel nilai_kp
    $sql_penilaian = "SELECT COUNT(DISTINCT pk.id_pengajuan) AS total
                      FROM pengajuan_kp pk
                      LEFT JOIN nilai_kp nk ON pk.id_pengajuan = nk.id_pengajuan
                      WHERE pk.id_perusahaan = ? 
                        AND pk.status_pengajuan IN ('kp_berjalan', 'selesai_pelaksanaan')
                        AND (nk.id_nilai IS NULL OR nk.nilai_pembimbing_lapangan IS NULL)";
    $stmt_penilaian = $conn->prepare($sql_penilaian);
     if ($stmt_penilaian) {
        $stmt_penilaian->bind_param("i", $id_perusahaan_login);
        $stmt_penilaian->execute();
        $result_penilaian = $stmt_penilaian->get_result();
        if ($row_penilaian = $result_penilaian->fetch_assoc()) {
            $jumlah_perlu_penilaian_lapangan = $row_penilaian['total'];
        }
        $stmt_penilaian->close();
    }
}


// Set judul halaman dan sertakan header
$page_title = "Dashboard Perusahaan";
require_once '../includes/header.php';
?>

<div class="page-layout-wrapper">

    <?php require_once '../includes/sidebar_perusahaan.php'; // Memanggil sidebar perusahaan ?>

    <main class="main-content-area">
        <div class="dashboard-content perusahaan-dashboard">
            <h2>Selamat Datang, <?php echo htmlspecialchars($nama_perusahaan_login); ?>!</h2>
            <p>Ini adalah dashboard Anda untuk mengelola informasi terkait mahasiswa Kerja Praktek.</p>
            <hr>

            <div class="dashboard-summary-perusahaan">
                <div class="summary-item-perusahaan">
                    <h4><i class="icon-mhs-kp"></i> Mahasiswa KP Aktif</h4>
                    <p class="summary-count-perusahaan"><?php echo $jumlah_mahasiswa_kp_aktif; ?></p>
                    <a href="/KP/perusahaan/mahasiswa_kp_list.php?status=aktif" class="btn btn-info btn-sm">Lihat Daftar</a>
                </div>
                <div class="summary-item-perusahaan">
                    <h4><i class="icon-penilaian"></i> Perlu Penilaian Lapangan</h4>
                    <p class="summary-count-perusahaan"><?php echo $jumlah_perlu_penilaian_lapangan; ?></p>
                    <a href="/KP/perusahaan/penilaian_lapangan_list.php?status=belum_dinilai" class="btn btn-warning btn-sm">Input Nilai</a>
                </div>
                </div>
            <hr>

            <div class="quick-actions-perusahaan">
                <h3>Aksi Cepat</h3>
                <ul>
                    <li><a href="/KP/perusahaan/mahasiswa_kp_list.php">Lihat Semua Mahasiswa KP di Perusahaan Anda</a></li>
                    <li><a href="/KP/perusahaan/penilaian_lapangan_list.php">Berikan Penilaian untuk Mahasiswa KP</a></li>
                    <li><a href="/KP/perusahaan/profil_perusahaan.php">Lihat/Edit Profil Perusahaan Anda</a></li>
                </ul>
            </div>

        </div>
    </main>

</div>

<style>
    /* Asumsikan CSS untuk .page-layout-wrapper, .main-content-area, .sidebar-perusahaan sudah ada */
    /* Ikon sederhana */
    .icon-mhs-kp::before { content: "🧑‍🎓 "; margin-right: 8px; }
    .icon-penilaian::before { content: "📝 "; margin-right: 8px; }

    .perusahaan-dashboard h2 { margin-top: 0; color: #333; }
    .perusahaan-dashboard p { color: #444; }
    .perusahaan-dashboard hr { margin-top: 20px; margin-bottom: 20px; }

    .dashboard-summary-perusahaan {
        display: flex;
        gap: 20px;
        justify-content: space-around; /* Atau flex-start jika item lebih sedikit */
        margin-bottom: 25px;
        flex-wrap: wrap;
    }
    .summary-item-perusahaan {
        background-color: #fff;
        padding: 25px 20px;
        border-radius: 8px;
        box-shadow: 0 3px 10px rgba(0,0,0,0.09);
        text-align: center;
        flex-basis: 250px; /* Lebar dasar item */
        flex-grow: 1;
        border-left: 5px solid; /* Aksen warna */
         transition: transform 0.2s ease;
    }
    .summary-item-perusahaan:hover {
        transform: translateY(-3px);
    }
    .summary-item-perusahaan:nth-child(1) { border-left-color: #17a2b8; /* Info */ }
    .summary-item-perusahaan:nth-child(2) { border-left-color: #ffc107; /* Warning */ }


    .summary-item-perusahaan h4 {
        margin-top: 0;
        margin-bottom: 12px;
        font-size: 1.15em;
        color: #495057;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .summary-item-perusahaan .summary-count-perusahaan {
        font-size: 2.8em;
        font-weight: bold;
        color: #343a40;
        margin-bottom: 18px;
        display: block;
    }
    .summary-item-perusahaan .btn {
        text-decoration: none;
        font-size: 0.9em;
    }

    .quick-actions-perusahaan h3 {
        font-size: 1.4em;
        color: #333;
        margin-top: 30px;
        margin-bottom: 15px;
        padding-bottom: 5px;
        border-bottom: 1px solid #eee;
    }
    .quick-actions-perusahaan ul {
        list-style: none; 
        padding-left: 0;
    }
    .quick-actions-perusahaan ul li {
        margin-bottom: 10px;
        padding: 8px 0;
        border-bottom: 1px dotted #e0e0e0;
    }
     .quick-actions-perusahaan ul li:last-child {
        border-bottom: none;
    }
    .quick-actions-perusahaan ul li a {
        text-decoration: none;
        color: #007bff; /* Menggunakan warna primer untuk link aksi */
        font-size: 1.05em;
    }
    .quick-actions-perusahaan ul li a:hover {
        text-decoration: underline;
        color: #0056b3;
    }
    /* Pastikan class tombol .btn-info, .btn-warning, .btn-sm ada di CSS global (header.php) */
</style>

<?php
require_once '../includes/footer.php';

if (isset($conn) && ($conn instanceof mysqli)) {
    $conn->close();
}
?>