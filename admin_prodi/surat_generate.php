<?php
// /KP/admin_prodi/surat_generate.php (Versi Final dan Lengkap)

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once '../includes/auth_check.php';
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin_prodi') {
    header("Location: /KP/index.php?error=unauthorized_admin_surat");
    exit();
}

$id_pengajuan_url = null;
$tipe_surat = null;
$data_surat = null;
$error_message = '';

if (isset($_GET['id_pengajuan']) && filter_var($_GET['id_pengajuan'], FILTER_VALIDATE_INT)) {
    $id_pengajuan_url = (int)$_GET['id_pengajuan'];
} else {
    $error_message = "ID Pengajuan tidak valid.";
}
if (isset($_GET['tipe'])) {
    $tipe_surat = $_GET['tipe'];
} else {
    if (empty($error_message)) $error_message = "Tipe surat tidak ditentukan.";
}

if (empty($error_message)) {
    require_once '../config/db_connect.php';
    if ($conn) {
        $sql_base = "SELECT
                        m.nama AS nama_mahasiswa, m.nim, m.prodi AS prodi_mahasiswa, m.angkatan,
                        pk.judul_kp, pk.tanggal_mulai_rencana, pk.tanggal_selesai_rencana,
                        p.nama_perusahaan, p.alamat AS alamat_perusahaan,
                        d.nama_dosen AS nama_dosen_pembimbing, d.nip AS nip_dosen_pembimbing
                    FROM pengajuan_kp pk
                    JOIN mahasiswa m ON pk.nim = m.nim
                    LEFT JOIN perusahaan p ON pk.id_perusahaan = p.id_perusahaan
                    LEFT JOIN dosen_pembimbing d ON pk.nip_dosen_pembimbing_kp = d.nip
                    WHERE pk.id_pengajuan = ?";

        $stmt = $conn->prepare($sql_base);
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
            $error_message = "Gagal menyiapkan query: " . $conn->error;
        }
    } else {
        $error_message = "Koneksi database gagal.";
    }
}

// Informasi statis untuk surat
$nama_universitas = "UNIVERSITAS TTUNOJOYO MADURA";
$nama_fakultas = "FAKULTAS TEKNIK";
$nama_prodi_penyelenggara = "Program Studi SISTEM INFORMASI";
$alamat_kampus = "Jl. Pendidikan No. 123, Kota Belajar, 54321";
$kontak_kampus = "Telp: (021) 1234567 | Email: info@utm.ac.id";
$kota_surat_dibuat = "Bangkalan";
$jabatan_penandatangan = "Ketua Program Studi Teknik Informatika";
$nama_penandatangan = "Dr. Ir. Budi Santoso, M.Kom.";
$nip_penandatangan = "19700101 199503 1 001";
$tanggal_surat = date("d F Y");

$page_title = "Generate Surat"; // Judul default
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    <style>
        /* ... (Seluruh CSS dari file asli bisa disalin di sini) ... */
        body { font-family: 'Times New Roman', Times, serif; font-size: 12pt; line-height: 1.5; background-color: #e0e0e0; margin: 0; padding: 20px 0; }
        .surat-container { width: 21cm; min-height: 29.7cm; padding: 2.5cm; margin: 20px auto; background-color: #fff; box-shadow: 0 0 10px rgba(0,0,0,0.5); box-sizing: border-box; }
        .print-button-container { text-align: center; padding: 20px; background-color: #f0f0f0; border-bottom: 1px solid #ccc; }
        .print-button { padding: 10px 20px; font-size: 16px; cursor: pointer; }
        .kop-surat { text-align: center; border-bottom: 3px solid black; padding-bottom: 10px; margin-bottom: 20px; }
        .kop-surat h1, .kop-surat h2 { margin: 0; text-transform: uppercase; }
        .kop-surat h1 { font-size: 16pt; } .kop-surat h2 { font-size: 14pt; }
        .isi-surat { text-align: justify; }
        .detail-mahasiswa table { margin-left: 40px; }
        .tanda-tangan-section { margin-top: 50px; }
        .tanda-tangan-blok { width: 45%; float: right; }
        @media print { .print-button-container { display: none !important; } .surat-container { margin: 0; box-shadow: none; border: none; } }
    </style>
</head>
<body>
    <div class="print-button-container">
        <button onclick="window.print()" class="print-button">Cetak Surat Ini</button>
        <a href="surat_generate_list.php">Kembali ke Daftar</a>
    </div>

    <?php if (!empty($error_message)): ?>
        <div class="surat-container" style="text-align:center;">
            <h2 style="color:red;">Error</h2>
            <p><?php echo htmlspecialchars($error_message); ?></p>
        </div>
    <?php elseif ($data_surat): ?>
        
        <?php if ($tipe_surat === 'pengantar'): ?>
            <div class="surat-container">
                <div class="kop-surat">
                    <h1><?php echo $nama_universitas; ?></h1>
                    <h2><?php echo $nama_fakultas; ?></h2>
                </div>
                <p style="text-align: right;"><?php echo $kota_surat_dibuat . ", " . $tanggal_surat; ?></p>
                <p><strong>Perihal: Permohonan Izin Kerja Praktek</strong></p>
                <p>Yth. Pimpinan HRD<br>
                <strong><?php echo htmlspecialchars($data_surat['nama_perusahaan']); ?></strong><br>
                Di Tempat</p>
                <div class="isi-surat">
                    <p>Dengan hormat,</p>
                    <p>Sehubungan dengan pelaksanaan program Kerja Praktek (KP) bagi mahasiswa <?php echo htmlspecialchars($nama_prodi_penyelenggara); ?>, dengan ini kami mengajukan permohonan agar mahasiswa kami berikut ini:</p>
                    <div class="detail-mahasiswa">
                        <table>
                            <tr><td>Nama</td><td>: <?php echo htmlspecialchars($data_surat['nama_mahasiswa']); ?></td></tr>
                            <tr><td>NIM</td><td>: <?php echo htmlspecialchars($data_surat['nim']); ?></td></tr>
                        </table>
                    </div>
                    <p>dapat melaksanakan Kerja Praktek di perusahaan/instansi yang Bapak/Ibu pimpin, yang direncanakan pada periode <strong><?php echo date("d M Y", strtotime($data_surat['tanggal_mulai_rencana'])); ?></strong> sampai dengan <strong><?php echo date("d M Y", strtotime($data_surat['tanggal_selesai_rencana'])); ?></strong>.</p>
                    <p>Demikian permohonan ini kami sampaikan. Atas perhatian dan kerjasama Bapak/Ibu, kami ucapkan terima kasih.</p>
                </div>
                <div class="tanda-tangan-section">
                    <div class="tanda-tangan-blok">
                        Hormat kami,<br>
                        <?php echo $jabatan_penandatangan; ?>,<br><br><br><br>
                        <strong><u><?php echo $nama_penandatangan; ?></u></strong><br>
                        NIP. <?php echo $nip_penandatangan; ?>
                    </div>
                </div>
            </div>

        <?php elseif ($tipe_surat === 'tugas'): ?>
            <div class="surat-container">
                <div class="kop-surat">
                    <h1><?php echo $nama_universitas; ?></h1>
                    <h2><?php echo $nama_fakultas; ?></h2>
                </div>
                <div style="text-align: center; margin-bottom: 30px;">
                    <h3 style="text-decoration: underline; font-size: 14pt;">SURAT TUGAS</h3>
                    <span>Nomor: ST/<?php echo $id_pengajuan_url; ?>/FTIK-TI/KP/<?php echo date('Y'); ?></span>
                </div>
                <div class="isi-surat">
                    <p>Yang bertanda tangan di bawah ini, <?php echo $jabatan_penandatangan; ?>, dengan ini memberikan tugas kepada:</p>
                    <div class="detail-mahasiswa">
                        <table>
                            <tr><td>Nama</td><td>: <?php echo htmlspecialchars($data_surat['nama_dosen_pembimbing'] ?: 'N/A'); ?></td></tr>
                            <tr><td>NIP</td><td>: <?php echo htmlspecialchars($data_surat['nip_dosen_pembimbing'] ?: 'N/A'); ?></td></tr>
                            <tr><td>Jabatan</td><td>: Dosen <?php echo $nama_prodi_penyelenggara; ?></td></tr>
                        </table>
                    </div>
                    <p>Untuk menjadi Dosen Pembimbing Kerja Praktek (KP) bagi mahasiswa berikut:</p>
                    <div class="detail-mahasiswa">
                        <table>
                            <tr><td>Nama</td><td>: <?php echo htmlspecialchars($data_surat['nama_mahasiswa']); ?></td></tr>
                            <tr><td>NIM</td><td>: <?php echo htmlspecialchars($data_surat['nim']); ?></td></tr>
                            <tr><td>Tempat KP</td><td>: <?php echo htmlspecialchars($data_surat['nama_perusahaan'] ?: 'N/A'); ?></td></tr>
                        </table>
                    </div>
                    <p>Demikian Surat Tugas ini dibuat untuk dapat dilaksanakan dengan sebaik-baiknya.</p>
                </div>
                <div class="tanda-tangan-section">
                    <div class="tanda-tangan-blok">
                        Hormat kami,<br>
                        <?php echo $jabatan_penandatangan; ?>,<br><br><br><br>
                        <strong><u><?php echo $nama_penandatangan; ?></u></strong><br>
                        NIP. <?php echo $nip_penandatangan; ?>
                    </div>
                </div>
            </div>

        <?php else: ?>
            <div class="surat-container" style="text-align:center;">
                <h2 style="color:orange;">Peringatan</h2>
                <p>Tipe surat "<?php echo htmlspecialchars($tipe_surat); ?>" tidak dikenali atau belum memiliki template.</p>
            </div>
        <?php endif; ?>

    <?php endif; ?>
</body>
</html>