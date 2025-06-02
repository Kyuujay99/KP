<?php
// /KP/admin_prodi/surat_generate.php

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// 1. OTENTIKASI DAN OTORISASI (Admin Prodi)
require_once '../includes/auth_check.php'; // Asumsi auth_check.php hanya cek login umum
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin_prodi') {
    session_unset();
    session_destroy();
    header("Location: /KP/index.php?error=unauthorized_admin_surat");
    exit();
}

$id_pengajuan_url = null;
$tipe_surat = null;
$data_surat = null; // Akan berisi data gabungan mahasiswa, pengajuan, perusahaan
$error_message = '';

// 2. VALIDASI PARAMETER URL
if (isset($_GET['id_pengajuan']) && filter_var($_GET['id_pengajuan'], FILTER_VALIDATE_INT)) {
    $id_pengajuan_url = (int)$_GET['id_pengajuan'];
} else {
    $error_message = "ID Pengajuan tidak valid atau tidak ditemukan.";
}

if (isset($_GET['tipe'])) {
    $tipe_surat = $_GET['tipe'];
} else {
    if(empty($error_message)) $error_message = "Tipe surat tidak ditentukan.";
}

// Hanya proses jika tipe surat adalah 'pengantar' untuk contoh ini
if ($tipe_surat !== 'pengantar' && empty($error_message)) {
    $error_message = "Tipe surat yang diminta tidak didukung saat ini.";
}

// Sertakan file koneksi database
require_once '../config/db_connect.php';

// 3. AMBIL DATA UNTUK SURAT DARI DATABASE
if ($id_pengajuan_url && $tipe_surat === 'pengantar' && empty($error_message) && $conn && ($conn instanceof mysqli)) {
    $sql = "SELECT
                m.nama AS nama_mahasiswa, m.nim, m.prodi AS prodi_mahasiswa, m.angkatan,
                pk.judul_kp,
                pk.tanggal_mulai_rencana, pk.tanggal_selesai_rencana,
                p.nama_perusahaan, p.alamat AS alamat_perusahaan, p.kontak_person_nama AS nama_kontak_perusahaan
            FROM pengajuan_kp pk
            JOIN mahasiswa m ON pk.nim = m.nim
            LEFT JOIN perusahaan p ON pk.id_perusahaan = p.id_perusahaan
            WHERE pk.id_pengajuan = ?";
    
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("i", $id_pengajuan_url);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows === 1) {
            $data_surat = $result->fetch_assoc();
        } else {
            $error_message = "Data pengajuan KP tidak ditemukan untuk ID: " . $id_pengajuan_url;
        }
        $stmt->close();
    } else {
        $error_message = "Gagal menyiapkan query pengambilan data surat: " . (($conn->error) ? htmlspecialchars($conn->error) : "Kesalahan tidak diketahui.");
    }
    // Pertimbangkan untuk menyimpan path surat pengantar ke tabel pengajuan_kp jika sudah digenerate
    // $update_path_surat_sql = "UPDATE pengajuan_kp SET surat_pengantar_path = ? WHERE id_pengajuan = ?";
    // ... (logika ini bisa ditambahkan jika ada penyimpanan file fisik atau logging)
} elseif (empty($error_message) && $tipe_surat === 'pengantar' && (!$conn || !($conn instanceof mysqli)) ) {
     $error_message = "Koneksi database gagal.";
}

// Informasi statis untuk kop surat dan penandatangan (bisa diambil dari setting/DB nanti)
$nama_universitas = "UNIVERSITAS TEKNOLOGI MAJU";
$nama_fakultas = "FAKULTAS TEKNIK DAN ILMU KOMPUTER";
$nama_prodi_penyelenggara = "Program Studi Teknik Informatika"; // Sesuaikan atau ambil dari data mahasiswa
$alamat_kampus = "Jl. Pendidikan No. 123, Kota Belajar, 54321";
$kontak_kampus = "Telp: (021) 1234567 | Email: info@utem.ac.id | Website: www.utem.ac.id";

$kota_surat_dibuat = "Kota Belajar"; // Kota tempat surat diterbitkan
$tanggal_surat = date("d F Y"); // Tanggal surat dibuat (hari ini)

// Nomor surat bisa dibuat dinamis atau diinput manual oleh admin nanti
// Format: NoSurat/KodeUnit/KodeSurat/BulanRomawi/Tahun
$bulan_romawi = ['I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X', 'XI', 'XII'];
$nomor_surat = "123/FTIK-TI/KP/" . $bulan_romawi[date('n') - 1] . "/" . date('Y'); // Contoh nomor surat

$jabatan_penandatangan = "Ketua Program Studi Teknik Informatika"; // Atau Koordinator KP
$nama_penandatangan = "Dr. Ir. Budi Santoso, M.Kom.";
$nip_penandatangan = "19700101 199503 1 001";

// Set judul halaman (tidak akan terlalu terlihat karena ini halaman surat)
$page_title = "Generate Surat Pengantar KP";
// Untuk halaman surat, kita tidak akan menggunakan header dan footer standar website.
// Kita akan buat struktur HTML minimalis yang cocok untuk surat.
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title) . ($data_surat ? " - " . htmlspecialchars($data_surat['nim']) : ""); ?></title>
    <style>
        /* CSS untuk format surat dan print */
        @media print {
            body {
                margin: 0;
                padding: 0;
                font-family: 'Times New Roman', Times, serif; /* Font umum untuk surat resmi */
                font-size: 12pt; /* Ukuran font umum */
                line-height: 1.5;
            }
            .surat-container {
                width: 100%;
                margin: 0;
                padding: 1.5cm 2cm 1.5cm 2.5cm; /* Margin standar: Atas, Kanan, Bawah, Kiri */
                box-shadow: none;
                border: none;
            }
            .print-button-container {
                display: none !important; /* Sembunyikan tombol print saat mencetak */
            }
            /* Hindari page break di dalam elemen tertentu jika memungkinkan */
            .no-break-inside { page-break-inside: avoid; }
            table { page-break-inside: auto; }
            tr    { page-break-inside: avoid; page-break-after: auto; }
        }

        /* Styling untuk tampilan di browser */
        body {
            font-family: 'Times New Roman', Times, serif;
            font-size: 12pt;
            line-height: 1.5;
            background-color: #e0e0e0; /* Latar abu-abu untuk membedakan kertas surat */
            margin: 0;
            padding: 20px 0; /* Padding atas bawah body */
        }
        .surat-container {
            width: 21cm; /* Lebar kertas A4 */
            min-height: 29.7cm; /* Tinggi kertas A4, tapi bisa lebih jika konten panjang */
            padding: 1.5cm 2cm 1.5cm 2.5cm; /* Margin standar: Atas, Kanan, Bawah, Kiri */
            margin: 20px auto; /* Posisi tengah */
            background-color: #fff; /* Warna kertas putih */
            box-shadow: 0 0 10px rgba(0,0,0,0.5); /* Bayangan agar terlihat seperti kertas */
            box-sizing: border-box; /* Agar padding tidak menambah width/height */
        }
        .print-button-container {
            text-align: center;
            padding: 20px;
            background-color: #f0f0f0;
            border-bottom: 1px solid #ccc;
        }
        .print-button {
            padding: 10px 20px;
            font-size: 16px;
            background-color: #007bff;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }
        .print-button:hover { background-color: #0056b3; }
        .btn-back {
            padding: 10px 15px; font-size: 14px; background-color: #6c757d;
            color: white; border: none; border-radius: 5px; cursor: pointer; text-decoration: none;
            margin-left: 10px;
        }
        .btn-back:hover { background-color: #5a6268; }


        /* Styling Kop Surat */
        .kop-surat {
            text-align: center;
            border-bottom: 3px solid black;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        .kop-surat .logo { /* Jika ada logo */
            /* float: left; width: 80px; height: 80px; margin-right: 10px; */
            /* Untuk contoh ini, kita tidak pakai logo fisik */
        }
        .kop-surat h1, .kop-surat h2, .kop-surat h3 { margin: 0; line-height: 1.2; text-transform: uppercase;}
        .kop-surat h1 { font-size: 16pt; font-weight: bold; }
        .kop-surat h2 { font-size: 14pt; font-weight: bold; }
        .kop-surat p { font-size: 10pt; margin: 2px 0; }

        /* Info Surat (Nomor, Tanggal) */
        .info-surat {
            margin-bottom: 20px;
            overflow: auto; /* Clearfix untuk float */
        }
        .info-surat .tanggal-surat {
            float: right;
        }
        .info-surat table {
            width: 100%;
            font-size: 12pt;
        }
        .info-surat td {
            vertical-align: top;
        }
        .info-surat .label {
            width: 100px; /* Lebar untuk label seperti "Nomor:", "Lampiran:" */
        }

        /* Tujuan Surat */
        .tujuan-surat {
            margin-bottom: 20px;
        }

        /* Isi Surat */
        .isi-surat {
            text-align: justify; /* Rata kiri-kanan */
            margin-bottom: 20px;
        }
        .isi-surat .paragraf-pembuka, .isi-surat .paragraf-penutup {
            margin-bottom: 1em; /* Jarak antar paragraf */
        }
        .isi-surat .detail-mahasiswa table {
            margin-left: 40px; /* Indentasi untuk detail mahasiswa */
            margin-top: 0.5em;
            margin-bottom: 0.5em;
            border-collapse: collapse; /* Untuk menghilangkan spasi antar border jika ada */
        }
        .isi-surat .detail-mahasiswa td {
            padding: 2px 0; /* Sedikit padding atas bawah */
            vertical-align: top;
        }
        .isi-surat .detail-mahasiswa td:first-child {
            width: 150px; /* Lebar untuk label detail mahasiswa */
        }


        /* Tanda Tangan */
        .tanda-tangan-section {
            margin-top: 40px;
            overflow: auto; /* Untuk layout tanda tangan jika ada lebih dari satu atau float */
        }
        .tanda-tangan-blok {
            width: 45%; /* Kira-kira separuh halaman, bisa disesuaikan */
            float: right; /* Tanda tangan biasanya di kanan */
            text-align: left; /* Atau center jika diinginkan */
            line-height: 1.4;
        }
        .tanda-tangan-blok .jabatan, .tanda-tangan-blok .nama-pejabat, .tanda-tangan-blok .nip {
            display: block;
        }
        .tanda-tangan-blok .ruang-ttd {
            height: 70px; /* Ruang kosong untuk tanda tangan basah */
            margin-bottom: 5px;
        }
        .tanda-tangan-blok .nama-pejabat {
            font-weight: bold;
            text-decoration: underline;
        }

        /* Tembusan */
        .tembusan {
            margin-top: 30px;
            font-size: 11pt;
        }
        .tembusan ol {
            margin: 0;
            padding-left: 20px;
        }
    </style>
</head>
<body>

    <div class="print-button-container">
        <button onclick="window.print()" class="print-button">Cetak Surat Ini</button>
        <?php if ($id_pengajuan_url): ?>
        <a href="/KP/admin_prodi/pengajuan_kp_detail_admin.php?id_pengajuan=<?php echo $id_pengajuan_url; ?>" class="btn-back">Kembali ke Detail Pengajuan</a>
        <?php else: ?>
        <a href="/KP/admin_prodi/pengajuan_kp_monitoring.php" class="btn-back">Kembali ke Monitoring</a>
        <?php endif; ?>
    </div>

    <?php if (!empty($error_message)): ?>
        <div class="surat-container" style="text-align:center;">
            <h2 style="color:red;">Error</h2>
            <p><?php echo htmlspecialchars($error_message); ?></p>
        </div>
    <?php elseif ($data_surat && $tipe_surat === 'pengantar'): ?>
        <div class="surat-container">
            <div class="kop-surat">
                <h1><?php echo htmlspecialchars($nama_universitas); ?></h1>
                <h2><?php echo htmlspecialchars($nama_fakultas); ?></h2>
                <p><?php echo htmlspecialchars($nama_prodi_penyelenggara); ?></p>
                <p><?php echo htmlspecialchars($alamat_kampus); ?></p>
                <p><?php echo htmlspecialchars($kontak_kampus); ?></p>
            </div>

            <div class="info-surat">
                <div class="tanggal-surat"><?php echo htmlspecialchars($kota_surat_dibuat); ?>, <?php echo $tanggal_surat; ?></div>
                <table>
                    <tr><td class="label">Nomor</td><td>: <?php echo htmlspecialchars($nomor_surat); ?></td></tr>
                    <tr><td class="label">Lampiran</td><td>: -</td></tr>
                    <tr><td class="label">Perihal</td><td>: <strong>Permohonan Izin Kerja Praktek dan Pembimbing Lapangan</strong></td></tr>
                </table>
            </div>

            <div class="tujuan-surat">
                Yth. Pimpinan HRD / Bagian Terkait<br>
                <?php if (!empty($data_surat['nama_perusahaan'])): ?>
                    <strong><?php echo htmlspecialchars($data_surat['nama_perusahaan']); ?></strong><br>
                    <?php echo !empty($data_surat['alamat_perusahaan']) ? nl2br(htmlspecialchars($data_surat['alamat_perusahaan'])) : '<em>Alamat Perusahaan Tidak Tercantum</em>'; ?><br>
                <?php else: ?>
                    <em>[Nama & Alamat Perusahaan Tujuan]</em><br> <?php endif; ?>
                di Tempat
            </div>

            <div class="isi-surat">
                <p class="paragraf-pembuka">Dengan hormat,</p>
                <p>Sehubungan dengan pelaksanaan program Kerja Praktek (KP) bagi mahasiswa <?php echo htmlspecialchars($nama_prodi_penyelenggara); ?>, <?php echo htmlspecialchars($nama_fakultas); ?>, <?php echo htmlspecialchars($nama_universitas); ?>, dengan ini kami mengajukan permohonan agar mahasiswa kami dapat melaksanakan Kerja Praktek di perusahaan/instansi yang Bapak/Ibu pimpin. Adapun data mahasiswa yang bersangkutan adalah sebagai berikut:</p>

                <div class="detail-mahasiswa no-break-inside">
                    <table>
                        <tr><td>Nama</td><td>: <?php echo htmlspecialchars($data_surat['nama_mahasiswa']); ?></td></tr>
                        <tr><td>NIM</td><td>: <?php echo htmlspecialchars($data_surat['nim']); ?></td></tr>
                        <tr><td>Program Studi</td><td>: <?php echo htmlspecialchars($data_surat['prodi_mahasiswa']); ?></td></tr>
                        <tr><td>Angkatan</td><td>: <?php echo htmlspecialchars($data_surat['angkatan']); ?></td></tr>
                    </table>
                </div>

                <p>Kerja Praktek ini direncanakan akan dilaksanakan pada periode:</p>
                <div class="detail-mahasiswa no-break-inside">
                     <table>
                        <tr><td>Tanggal Mulai</td><td>: <?php echo date("d F Y", strtotime($data_surat['tanggal_mulai_rencana'])); ?></td></tr>
                        <tr><td>Tanggal Selesai</td><td>: <?php echo date("d F Y", strtotime($data_surat['tanggal_selesai_rencana'])); ?></td></tr>
                        <?php if(!empty($data_surat['judul_kp'])): ?>
                        <tr><td>Topik/Judul Rencana KP</td><td>: <?php echo htmlspecialchars($data_surat['judul_kp']); ?></td></tr>
                        <?php endif; ?>
                    </table>
                </div>

                <p>Kami berharap mahasiswa kami dapat memperoleh pengalaman praktis serta bimbingan yang berharga dari pihak perusahaan/instansi Bapak/Ibu. Kami juga memohon kesediaan Bapak/Ibu untuk menunjuk seorang pembimbing lapangan bagi mahasiswa kami selama pelaksanaan Kerja Praktek.</p>
                <p class="paragraf-penutup">Demikian permohonan ini kami sampaikan. Atas perhatian dan kerjasama yang baik dari Bapak/Ibu, kami ucapkan terima kasih.</p>
            </div>

            <div class="tanda-tangan-section">
                <div class="tanda-tangan-blok no-break-inside">
                    Hormat kami,<br>
                    <span class="jabatan"><?php echo htmlspecialchars($jabatan_penandatangan); ?>,</span>
                    <div class="ruang-ttd">
                        <br><br><br><br>
                    </div>
                    <span class="nama-pejabat"><?php echo htmlspecialchars($nama_penandatangan); ?></span>
                    <span class="nip">NIP. <?php echo htmlspecialchars($nip_penandatangan); ?></span>
                </div>
            </div>

            </div> <?php elseif (empty($error_message)): ?>
        <div class="surat-container" style="text-align:center;">
            <p>Memuat data surat...</p>
        </div>
    <?php endif; ?>

    <?php
    // Tutup koneksi database jika dibuka di halaman ini
    if (isset($conn) && ($conn instanceof mysqli)) {
        $conn->close();
    }
    ?>
</body>
</html>