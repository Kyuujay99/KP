<?php
// /KP/admin_prodi/surat_generate.php (Versi Final dan Disempurnakan)

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
$nama_kementerian = "KEMENTERIAN PENDIDIKAN, KEBUDAYAAN, RISET, DAN TEKNOLOGI";
$nama_universitas = "UNIVERSITAS TRUNOJOYO MADURA";
$nama_fakultas = "FAKULTAS TEKNIK";
$nama_prodi_penyelenggara = "PROGRAM STUDI SISTEM INFORMASI";
$alamat_kampus = "Jl. Raya Telang, PO. Box 2 Kamal, Bangkalan, Madura 69162";
$kontak_kampus = "Telp: (031) 3011146 | Fax: (031) 3011506";
$website_kampus = "Laman: www.trunojoyo.ac.id";

$kota_surat_dibuat = "Bangkalan";
$jabatan_penandatangan = "Ketua Program Studi Sistem Informasi";
$nama_penandatangan = "Dr. Ir. Budi Santoso, M.Kom.";
$nip_penandatangan = "197001011995031001";
$tanggal_surat = date("d F Y");

$page_title = "Generate Surat";
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?></title>
    <style>
        body {
            background-color: #e0e0e0;
            font-family: 'Times New Roman', Times, serif;
            font-size: 12pt;
            line-height: 1.5;
            margin: 0;
            padding: 20px 0;
        }
        .page-wrapper {
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        .print-controls {
            width: 21cm;
            text-align: center;
            padding: 20px;
            background-color: #f0f0f0;
            border-bottom: 1px solid #ccc;
            margin-bottom: 20px;
        }
        .print-controls button, .print-controls a {
            padding: 10px 20px;
            font-size: 16px;
            cursor: pointer;
            margin: 0 10px;
            text-decoration: none;
            border: 1px solid #ccc;
            background-color: #fff;
            border-radius: 5px;
        }
        .surat-a4 {
            width: 21cm;
            min-height: 29.7cm;
            padding: 2.5cm 2.5cm 2cm 2.5cm;
            margin: 0 auto;
            background-color: #fff;
            box-shadow: 0 0 10px rgba(0,0,0,0.5);
            box-sizing: border-box;
        }
        .kop-surat {
            display: flex;
            align-items: center;
            gap: 20px;
            border-bottom: 4px double black;
            padding-bottom: 10px;
            margin-bottom: 5px;
        }
        .logo-container {
            width: 90px;
            height: 90px;
        }
        .logo-container img {
            width: 100%;
            height: auto;
        }
        .kop-text {
            text-align: center;
            flex-grow: 1;
            line-height: 1.2;
        }
        .kop-text p { margin: 0; }
        .kop-kementerian { font-size: 12pt; font-weight: bold; }
        .kop-universitas { font-size: 14pt; font-weight: bold; }
        .kop-fakultas { font-size: 16pt; font-weight: bold; }
        .kop-prodi { font-size: 14pt; font-weight: bold; }
        .kop-kontak { font-size: 9pt; }
        
        .nomor-surat { margin-top: 20px; }
        .nomor-surat td { padding: 2px 0; vertical-align: top; }
        .nomor-surat .label { width: 80px; }
        .nomor-surat .separator { width: 10px; }

        .tujuan-surat { margin-top: 20px; }
        .isi-surat { margin-top: 20px; text-align: justify; }
        .isi-surat p { text-indent: 4em; margin: 1em 0; }
        
        .detail-table { margin: 1em 0 1em 4em; }
        .detail-table td { padding: 2px 0; vertical-align: top; }
        .detail-table .label { width: 120px; }
        .detail-table .separator { width: 10px; }
        
        .tanda-tangan { margin-top: 50px; }
        .ttd-blok {
            width: 50%;
            margin-left: 50%;
            line-height: 1.3;
        }

        @media print {
            body { background-color: #fff; padding: 0; }
            .print-controls { display: none !important; }
            .surat-a4 { margin: 0; box-shadow: none; border: none; }
        }
    </style>
</head>
<body>
    <div class="page-wrapper">
        <div class="print-controls">
            <button onclick="window.print()">Cetak Surat</button>
            <a href="surat_generate_list.php">Kembali ke Daftar</a>
        </div>

        <?php if (!empty($error_message)): ?>
            <div class="surat-a4" style="text-align:center;">
                <h2 style="color:red;">Error</h2>
                <p><?php echo htmlspecialchars($error_message); ?></p>
            </div>
        <?php elseif ($data_surat): ?>
            
            <?php if ($tipe_surat === 'pengantar'): ?>
                <div class="surat-a4">
                    <div class="kop-surat">
                        <div class="logo-container">
                            <img src="../assett/images/logo/utm.png" alt="Logo UTM">
                        </div>
                        <div class="kop-text">
                            <p class="kop-universitas"><?php echo $nama_universitas; ?></p>
                            <p class="kop-fakultas"><?php echo $nama_fakultas; ?></p>
                            <p class="kop-prodi"><?php echo $nama_prodi_penyelenggara; ?></p>
                            <p class="kop-kontak"><?php echo $alamat_kampus; ?></p>
                            <p class="kop-kontak"><?php echo $kontak_kampus; ?> | <?php echo $website_kampus; ?></p>
                        </div>
                    </div>

                    <p style="text-align: right; margin-top:20px;"><?php echo $kota_surat_dibuat . ", " . $tanggal_surat; ?></p>
                    
                    <table class="nomor-surat">
                        <tr><td class="label">Nomor</td><td class="separator">:</td><td> ... /UN46.4.8/KP/<?php echo date('Y'); ?></td></tr>
                        <tr><td class="label">Lampiran</td><td class="separator">:</td><td>-</td></tr>
                        <tr><td class="label">Perihal</td><td class="separator">:</td><td><strong>Permohonan Izin Kerja Praktek</strong></td></tr>
                    </table>

                    <div class="tujuan-surat">
                        <p>Yth. Pimpinan HRD<br>
                        <strong><?php echo htmlspecialchars($data_surat['nama_perusahaan'] ?? 'Perusahaan Terkait'); ?></strong><br>
                        Di Tempat</p>
                    </div>

                    <div class="isi-surat">
                        <p>Dengan hormat,</p>
                        <p>Sehubungan dengan salah satu mata kuliah wajib dalam kurikulum <?php echo htmlspecialchars($nama_prodi_penyelenggara); ?>, Fakultas Teknik, Universitas Trunojoyo Madura, yaitu Kerja Praktek (KP), dengan ini kami mengajukan permohonan agar mahasiswa kami berikut ini:</p>
                        
                        <table class="detail-table">
                            <tr><td class="label">Nama</td><td class="separator">:</td><td><?php echo htmlspecialchars($data_surat['nama_mahasiswa']); ?></td></tr>
                            <tr><td class="label">NIM</td><td class="separator">:</td><td><?php echo htmlspecialchars($data_surat['nim']); ?></td></tr>
                        </table>

                        <p>dapat diterima untuk melaksanakan Kerja Praktek di perusahaan/instansi yang Bapak/Ibu pimpin. Pelaksanaan KP direncanakan akan berlangsung pada periode <strong><?php echo date("d M Y", strtotime($data_surat['tanggal_mulai_rencana'])); ?></strong> sampai dengan <strong><?php echo date("d M Y", strtotime($data_surat['tanggal_selesai_rencana'])); ?></strong> (atau sesuai dengan kebijakan perusahaan).</p>
                        <p>Demikian surat permohonan ini kami sampaikan. Atas perhatian, bantuan, dan kerja sama Bapak/Ibu, kami ucapkan terima kasih.</p>
                    </div>

                    <!-- <div class="tanda-tangan">
                        <div class="ttd-blok">
                            <p>Hormat kami,<br>
                            <?php echo $jabatan_penandatangan; ?>,</p>
                            <br><br><br><br>
                            <p><strong><u><?php echo $nama_penandatangan; ?></u></strong><br>
                            NIP. <?php echo $nip_penandatangan; ?></p>
                        </div>
                    </div> -->
                    <div class="tanda-tangan">
                        <div class="ttd-blok">
                            <p>Hormat kami,<br>
                            a.n. Ketua Program Studi Sistem Informasi,<br>
                            Dosen Pembimbing,</p>
                            <br><br><br><br>
                            <p><strong><u><?php echo htmlspecialchars($data_surat['nama_dosen_pembimbing'] ?? 'Dosen Belum Ditentukan'); ?></u></strong><br>
                            NIP. <?php echo htmlspecialchars($data_surat['nip_dosen_pembimbing'] ?? '-'); ?></p>
                        </div>
                    </div>
                </div>

            <?php elseif ($tipe_surat === 'tugas'): ?>
                <div class="surat-a4">
                     <div class="kop-surat">
                        <div class="logo-container">
                             <img src="../assett/images/logo/utm.png" alt="Logo UTM">
                        </div>
                        <div class="kop-text">
                            <p class="kop-universitas"><?php echo $nama_universitas; ?></p>
                            <p class="kop-fakultas"><?php echo $nama_fakultas; ?></p>
                            <p class="kop-prodi"><?php echo $nama_prodi_penyelenggara; ?></p>
                            <p class="kop-kontak"><?php echo $alamat_kampus; ?></p>
                            <p class="kop-kontak"><?php echo $kontak_kampus; ?> | <?php echo $website_kampus; ?></p>
                        </div>
                    </div>
                    <div style="text-align: center; margin-top: 30px; margin-bottom: 30px;">
                        <h3 style="text-decoration: underline; margin-bottom: 5px; font-size: 14pt;">SURAT TUGAS</h3>
                        <span>Nomor: ... /UN46.4.8/ST-KP/<?php echo date('Y'); ?></span>
                    </div>

                    <div class="isi-surat">
                        <p style="text-indent: 0;">Yang bertanda tangan di bawah ini, <?php echo $jabatan_penandatangan; ?>, Fakultas Teknik, Universitas Trunojoyo Madura, dengan ini memberikan tugas kepada:</p>
                        
                        <table class="detail-table">
                            <tr><td class="label">Nama</td><td class="separator">:</td><td><?php echo htmlspecialchars($data_surat['nama_dosen_pembimbing'] ?? '<em>Belum ditentukan</em>'); ?></td></tr>
                            <tr><td class="label">NIP</td><td class="separator">:</td><td><?php echo htmlspecialchars($data_surat['nip_dosen_pembimbing'] ?? '<em>-</em>'); ?></td></tr>
                            <tr><td class="label">Jabatan</td><td class="separator">:</td><td>Dosen Program Studi Sistem Informasi</td></tr>
                        </table>

                        <p style="text-indent: 0;">Untuk menjadi Dosen Pembimbing Kerja Praktek (KP) bagi mahasiswa berikut:</p>
                        
                        <table class="detail-table">
                            <tr><td class="label">Nama</td><td class="separator">:</td><td><?php echo htmlspecialchars($data_surat['nama_mahasiswa']); ?></td></tr>
                            <tr><td class="label">NIM</td><td class="separator">:</td><td><?php echo htmlspecialchars($data_surat['nim']); ?></td></tr>
                            <tr><td class="label">Tempat KP</td><td class="separator">:</td><td><?php echo htmlspecialchars($data_surat['nama_perusahaan'] ?? 'N/A'); ?></td></tr>
                        </table>

                        <p>Demikian Surat Tugas ini dibuat untuk dapat dilaksanakan dengan sebaik-baiknya dan penuh tanggung jawab.</p>
                    </div>

                    <!-- <div class="tanda-tangan" style="margin-top: 30px;">
                         <div class="ttd-blok">
                            <p><?php echo $kota_surat_dibuat . ", " . $tanggal_surat; ?><br>
                            <?php echo $jabatan_penandatangan; ?>,</p>
                            <br><br><br><br>
                            <p><strong><u><?php echo $nama_penandatangan; ?></u></strong><br>
                            NIP. <?php echo $nip_penandatangan; ?></p>
                        </div>
                    </div> -->
                    <div class="tanda-tangan">
                        <div class="ttd-blok">
                            <p>Hormat kami,<br>
                            a.n. Ketua Program Studi Sistem Informasi,<br>
                            Dosen Pembimbing,</p>
                            <br><br><br><br>
                            <p><strong><u><?php echo htmlspecialchars($data_surat['nama_dosen_pembimbing'] ?? 'Dosen Belum Ditentukan'); ?></u></strong><br>
                            NIP. <?php echo htmlspecialchars($data_surat['nip_dosen_pembimbing'] ?? '-'); ?></p>
                        </div>
                    </div>
                </div>

            <?php else: ?>
                <div class="surat-a4" style="text-align:center;">
                    <h2 style="color:orange;">Peringatan</h2>
                    <p>Tipe surat "<?php echo htmlspecialchars($tipe_surat); ?>" tidak dikenali atau belum memiliki template.</p>
                </div>
            <?php endif; ?>

        <?php endif; ?>
    </div>
</body>
</html>