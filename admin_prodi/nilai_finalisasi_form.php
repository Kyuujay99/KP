<?php
// /KP/admin_prodi/nilai_finalisasi_form.php

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// 1. OTENTIKASI DAN OTORISASI (Sama seperti sebelumnya)
require_once '../includes/auth_check.php';
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin_prodi') {
    session_unset();
    session_destroy();
    header("Location: /KP/index.php?error=unauthorized_admin");
    exit();
}

$admin_identifier = $_SESSION['user_id'];
$id_pengajuan_url = null;

$pengajuan_info = null;
$nilai_kp_entry = null;
$komponen_nilai_lengkap = false;
$calculated_nilai_angka = null;
$determined_nilai_huruf = '';

$error_message = '';
$success_message = '';
$error_message_initial_load = '';

// 2. VALIDASI PARAMETER URL (Sama seperti sebelumnya)
if (isset($_GET['id_pengajuan']) && filter_var($_GET['id_pengajuan'], FILTER_VALIDATE_INT) && (int)$_GET['id_pengajuan'] > 0) {
    $id_pengajuan_url = (int)$_GET['id_pengajuan'];
} else {
    $error_message_initial_load = "ID Pengajuan tidak valid atau tidak ditemukan dalam URL.";
}

// Sertakan file koneksi database
require_once '../config/db_connect.php';

// --- DEFINISI BOBOT DAN SKALA NILAI (HARUS DISESUAIKAN) ---
define('BOBOT_PEMBIMBING_LAPANGAN', 0.25); // 25%
define('BOBOT_DOSEN_PEMBIMBING', 0.35);    // 35%
define('BOBOT_SEMINAR_PENGUJI', 0.40);     // 40% (Total dari penguji seminar)

function getNilaiHuruf($nilai_angka) {
    if ($nilai_angka === null) return '';
    if ($nilai_angka >= 85) return 'A';
    if ($nilai_angka >= 80) return 'A-';
    if ($nilai_angka >= 75) return 'B+';
    if ($nilai_angka >= 70) return 'B';
    if ($nilai_angka >= 65) return 'B-';
    if ($nilai_angka >= 60) return 'C+';
    if ($nilai_angka >= 55) return 'C';
    if ($nilai_angka >= 50) return 'D';
    return 'E';
}
// --- END DEFINISI BOBOT DAN SKALA ---


// 3. FUNGSI UNTUK MENGAMBIL DATA (Sama, tapi kita akan kalkulasi di luar)
function getPengajuanAndAllNilai($conn_db, $pengajuan_id, &$out_error_message) {
    // ... (Isi fungsi sama seperti versi sebelumnya, mengambil $data['pengajuan'] dan $data['nilai_kp'])
    // (Pastikan fungsi ini mengembalikan semua field dari tabel nilai_kp)
    $data = ['pengajuan' => null, 'nilai_kp' => null];
    if (!$conn_db || !($conn_db instanceof mysqli) || $conn_db->connect_error) {
        $out_error_message = "Koneksi database tidak valid."; return $data;
    }
    if ($pengajuan_id === null || $pengajuan_id <= 0) {
        $out_error_message = "ID Pengajuan tidak valid untuk mengambil data."; return $data;
    }

    $sql_pengajuan = "SELECT pk.id_pengajuan, pk.judul_kp, pk.status_pengajuan,
                             m.nim, m.nama AS nama_mahasiswa, m.prodi
                      FROM pengajuan_kp pk
                      JOIN mahasiswa m ON pk.nim = m.nim
                      WHERE pk.id_pengajuan = ?";
    $stmt_pengajuan = $conn_db->prepare($sql_pengajuan);
    if ($stmt_pengajuan) {
        $stmt_pengajuan->bind_param("i", $pengajuan_id);
        $stmt_pengajuan->execute();
        $result_pengajuan = $stmt_pengajuan->get_result();
        if ($result_pengajuan->num_rows === 1) {
            $data['pengajuan'] = $result_pengajuan->fetch_assoc();
            $sql_nilai = "SELECT * FROM nilai_kp WHERE id_pengajuan = ?"; // Ambil semua field nilai_kp
            $stmt_nilai = $conn_db->prepare($sql_nilai);
            if ($stmt_nilai) {
                $stmt_nilai->bind_param("i", $pengajuan_id);
                $stmt_nilai->execute();
                $result_nilai = $stmt_nilai->get_result();
                if ($result_nilai->num_rows === 1) {
                    $data['nilai_kp'] = $result_nilai->fetch_assoc();
                }
                $stmt_nilai->close();
            } else { $out_error_message .= " Gagal ambil data nilai KP."; }
        } else { if (empty($out_error_message)) $out_error_message = "Pengajuan KP (ID: ".htmlspecialchars($pengajuan_id).") tidak ditemukan."; }
        $stmt_pengajuan->close();
    } else { $out_error_message = "Gagal siapkan query info pengajuan: " . htmlspecialchars($conn_db->error); }
    return $data;
}

// 4. PROSES FINALISASI NILAI JIKA FORM DISUBMIT (Sama, tapi nilai angka dan huruf bisa di-override)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_finalisasi_nilai'])) {
    // ... (Logika POST sama seperti versi sebelumnya, mengambil nilai_akhir_angka, nilai_akhir_huruf, is_final dari form)
    // ... (Validasi input juga sama)
    // ... (Logika INSERT/UPDATE ke tabel nilai_kp dan update status_pengajuan juga sama)
    if ($id_pengajuan_url === null || !empty($error_message_initial_load)) {
        $error_message = "Tidak dapat memproses: ID Pengajuan awal tidak valid. " . $error_message_initial_load;
    } elseif (!$conn || !($conn instanceof mysqli) || $conn->connect_error) {
        $error_message = "Koneksi database tidak tersedia.";
    } else {
        $id_pengajuan_form = (int)$_POST['id_pengajuan'];
        $nilai_akhir_angka_input = isset($_POST['nilai_akhir_angka']) && is_numeric($_POST['nilai_akhir_angka']) ? (float)$_POST['nilai_akhir_angka'] : null;
        $nilai_akhir_huruf_input = trim($_POST['nilai_akhir_huruf']);
        $is_final_input = isset($_POST['is_final']) ? 1 : 0;

        if ($id_pengajuan_form !== $id_pengajuan_url) {
            $error_message = "Kesalahan: ID Pengajuan pada form tidak cocok.";
        } elseif ($nilai_akhir_angka_input === null || $nilai_akhir_angka_input < 0 || $nilai_akhir_angka_input > 100) {
            $error_message = "Nilai Akhir Angka harus antara 0 dan 100.";
        } elseif (empty($nilai_akhir_huruf_input)) {
            $error_message = "Nilai Akhir Huruf wajib diisi.";
        }
        $allowed_huruf = ['A', 'A-', 'B+', 'B', 'B-', 'C+', 'C', 'D', 'E'];
        if (!in_array(strtoupper($nilai_akhir_huruf_input), $allowed_huruf) && !empty($nilai_akhir_huruf_input)) { // Cek jika tidak kosong
            $error_message = "Format Nilai Akhir Huruf tidak valid (Contoh: A, B+, C).";
        }

        if (empty($error_message)) {
            $sql_cek_nilai = "SELECT id_nilai FROM nilai_kp WHERE id_pengajuan = ?";
            $stmt_cek = $conn->prepare($sql_cek_nilai);
            $stmt_cek->bind_param("i", $id_pengajuan_url);
            $stmt_cek->execute();
            $result_cek = $stmt_cek->get_result();
            $id_nilai_existing = ($result_cek->num_rows === 1) ? $result_cek->fetch_assoc()['id_nilai'] : null;
            $stmt_cek->close();

            $conn->begin_transaction();
            try {
                if ($id_nilai_existing) { 
                    $sql_action = "UPDATE nilai_kp SET nilai_akhir_angka = ?, nilai_akhir_huruf = ?, is_final = ? WHERE id_nilai = ?";
                    $stmt_action = $conn->prepare($sql_action);
                    if (!$stmt_action) throw new Exception("Prepare update nilai akhir gagal: " . $conn->error);
                    $stmt_action->bind_param("dsii", $nilai_akhir_angka_input, $nilai_akhir_huruf_input, $is_final_input, $id_nilai_existing);
                } else { 
                    // Jika belum ada record nilai_kp, buat baru. Ini seharusnya jarang terjadi jika komponen nilai lain sudah diinput.
                    // Idealnya, record nilai_kp dibuat saat komponen pertama (misal nilai dospem) diinput.
                    // Untuk robust, kita tambahkan semua field yang mungkin (dengan nilai default null jika tidak diinput di form ini)
                    $sql_action = "INSERT INTO nilai_kp (id_pengajuan, nilai_akhir_angka, nilai_akhir_huruf, is_final) VALUES (?, ?, ?, ?)";
                    $stmt_action = $conn->prepare($sql_action);
                    if (!$stmt_action) throw new Exception("Prepare insert nilai akhir gagal: " . $conn->error);
                    $stmt_action->bind_param("idsi", $id_pengajuan_url, $nilai_akhir_angka_input, $nilai_akhir_huruf_input, $is_final_input);
                }

                if (!$stmt_action->execute()) {
                    throw new Exception("Eksekusi simpan nilai akhir gagal: " . $stmt_action->error);
                }
                $affected_rows_nilai = $stmt_action->affected_rows;
                $new_insert_id = ($id_nilai_existing === null) ? $stmt_action->insert_id : null;
                $stmt_action->close();

                if ($is_final_input == 1) {
                    $sql_update_status_kp = "UPDATE pengajuan_kp SET status_pengajuan = 'selesai_dinilai' WHERE id_pengajuan = ?";
                    $stmt_update_status_kp = $conn->prepare($sql_update_status_kp);
                    if (!$stmt_update_status_kp) throw new Exception("Prepare update status KP gagal: ". $conn->error);
                    $stmt_update_status_kp->bind_param("i", $id_pengajuan_url);
                    if (!$stmt_update_status_kp->execute()) throw new Exception("Eksekusi update status KP gagal: ". $stmt_update_status_kp->error);
                    $stmt_update_status_kp->close();
                }
                
                $conn->commit();
                if ($affected_rows_nilai > 0 || $new_insert_id) {
                    $success_message = "Nilai akhir KP berhasil disimpan" . ($is_final_input == 1 ? " dan difinalisasi." : ".");
                } else {
                    $success_message = "Data nilai akhir telah dikirim (tidak ada perubahan atau data sama).";
                }

            } catch (Exception $e) {
                $conn->rollback();
                $error_message = "Gagal menyimpan nilai akhir: " . htmlspecialchars($e->getMessage());
            }
        }
    }
}

// Selalu ambil data terbaru untuk ditampilkan
$display_error_message = $error_message_initial_load;
if (empty($display_error_message) && !empty($error_message)) {
    $display_error_message = $error_message;
}

if ($id_pengajuan_url !== null && empty($error_message_initial_load)) {
    if ($conn && ($conn instanceof mysqli) && !$conn->connect_error) {
        $fetch_error_temp = '';
        $fetched_data = getPengajuanAndAllNilai($conn, $id_pengajuan_url, $fetch_error_temp);
        
        if (is_array($fetched_data) && isset($fetched_data['pengajuan']) && $fetched_data['pengajuan'] !== null) {
            $pengajuan_info = $fetched_data['pengajuan'];
            $nilai_kp_entry = isset($fetched_data['nilai_kp']) ? $fetched_data['nilai_kp'] : null;

            // Lakukan perhitungan nilai jika data komponen ada
            if ($nilai_kp_entry) {
                $n_lap = $nilai_kp_entry['nilai_pembimbing_lapangan'];
                $n_dospem = $nilai_kp_entry['nilai_dosen_pembimbing'];
                $n_uji1 = $nilai_kp_entry['nilai_penguji1_seminar'];
                $n_uji2 = $nilai_kp_entry['nilai_penguji2_seminar'];
                
                $nilai_seminar_avg = null;
                $jumlah_penguji_valid = 0;
                $total_nilai_penguji = 0;

                if ($n_uji1 !== null) { $total_nilai_penguji += $n_uji1; $jumlah_penguji_valid++; }
                if ($n_uji2 !== null) { $total_nilai_penguji += $n_uji2; $jumlah_penguji_valid++; }

                if ($jumlah_penguji_valid > 0) {
                    $nilai_seminar_avg = $total_nilai_penguji / $jumlah_penguji_valid;
                }

                // Kalkulasi hanya jika semua komponen utama ada (sesuaikan jika ada komponen opsional)
                // Untuk contoh, kita asumsikan dospem dan seminar (jika ada penguji) itu penting. Lapangan bisa opsional.
                $komponen_dospem_ada = ($n_dospem !== null);
                // Anggap seminar penting jika setidaknya ada satu penguji yang memberi nilai ATAU tidak ada penguji sama sekali (tergantung aturan)
                $komponen_seminar_ada = ($nilai_seminar_avg !== null || ($n_uji1 === null && $n_uji2 === null)); 
                                        // Atau jika ingin seminar selalu ada nilainya: $komponen_seminar_ada = ($nilai_seminar_avg !== null);

                // Bobot efektif jika ada komponen yang hilang
                $efektif_bobot_lap = ($n_lap !== null) ? BOBOT_PEMBIMBING_LAPANGAN : 0;
                $efektif_bobot_dospem = ($n_dospem !== null) ? BOBOT_DOSEN_PEMBIMBING : 0;
                $efektif_bobot_seminar = ($nilai_seminar_avg !== null) ? BOBOT_SEMINAR_PENGUJI : 0;
                
                $total_bobot_efektif = $efektif_bobot_lap + $efektif_bobot_dospem + $efektif_bobot_seminar;

                if ($total_bobot_efektif > 0) {
                     $calculated_nilai_angka = 0;
                     if($n_lap !== null) $calculated_nilai_angka += ($n_lap * BOBOT_PEMBIMBING_LAPANGAN);
                     if($n_dospem !== null) $calculated_nilai_angka += ($n_dospem * BOBOT_DOSEN_PEMBIMBING);
                     if($nilai_seminar_avg !== null) $calculated_nilai_angka += ($nilai_seminar_avg * BOBOT_SEMINAR_PENGUJI);
                     
                     // Normalisasi jika total bobot efektif kurang dari 1 (karena ada komponen hilang)
                     // Namun, untuk sistem penilaian yang adil, biasanya semua komponen wajib diisi.
                     // Jika kita asumsikan bobot selalu 1 jika semua komponen ada:
                     // $calculated_nilai_angka = ($n_lap * BOBOT_PEMBIMBING_LAPANGAN) + ($n_dospem * BOBOT_DOSEN_PEMBIMBING) + ($nilai_seminar_avg * BOBOT_SEMINAR_PENGUJI);
                     // Jika ingin normalisasi:
                     // $calculated_nilai_angka = $calculated_nilai_angka / $total_bobot_efektif;
                     // Untuk sekarang, kita hitung saja berdasarkan bobot yang ada, dan admin bisa override.
                }


                if ($calculated_nilai_angka !== null) {
                    $determined_nilai_huruf = getNilaiHuruf($calculated_nilai_angka);
                }
            }


            if (empty($display_error_message) && !empty($fetch_error_temp) && !$pengajuan_info ) {
                $display_error_message = $fetch_error_temp;
            }
        } elseif (empty($display_error_message)) {
            $display_error_message = !empty($fetch_error_temp) ? $fetch_error_temp : "Data pengajuan KP tidak dapat dimuat (ID: ".htmlspecialchars($id_pengajuan_url).").";
        }
    } elseif (empty($display_error_message)) {
        $display_error_message = "Koneksi database tidak tersedia untuk memuat data.";
    }
}

// Set judul halaman
$page_title = "Finalisasi Nilai Kerja Praktek";
if ($pengajuan_info && isset($pengajuan_info['judul_kp'])) {
    $page_title = "Finalisasi Nilai: " . htmlspecialchars($pengajuan_info['judul_kp']);
} elseif ($id_pengajuan_url !== null) {
     $page_title = "Finalisasi Nilai KP (ID: ".htmlspecialchars($id_pengajuan_url).")";
}
require_once '../includes/header.php';
?>

<div class="page-layout-wrapper">

    <main class="main-content-area">
        <div class="form-container finalisasi-nilai-form">
            <h1><?php echo htmlspecialchars($page_title); ?></h1>
            <a href="/KP/admin_prodi/pengajuan_kp_detail_admin.php?id_pengajuan=<?php echo $id_pengajuan_url; ?>" class="btn btn-light btn-sm mb-3">&laquo; Kembali ke Detail Pengajuan</a>
            <hr>

            <?php if (!empty($success_message)): ?>
                <div class="message success"><p><?php echo htmlspecialchars($success_message); ?></p></div>
            <?php endif; ?>
            <?php if (!empty($display_error_message)): ?>
                <div class="message error"><p><?php echo htmlspecialchars($display_error_message); ?></p></div>
            <?php endif; ?>

            <?php if ($pengajuan_info): ?>
                <div class="info-section card mb-4">
                    <div class="card-header"><h3>Informasi Mahasiswa & Kerja Praktek</h3></div>
                    <div class="card-body">
                        <dl>
                            <dt>Nama Mahasiswa:</dt><dd><?php echo htmlspecialchars($pengajuan_info['nama_mahasiswa']); ?></dd>
                            <dt>NIM:</dt><dd><?php echo htmlspecialchars($pengajuan_info['nim']); ?></dd>
                            <dt>Judul KP:</dt><dd><strong><?php echo htmlspecialchars($pengajuan_info['judul_kp']); ?></strong></dd>
                        </dl>
                    </div>
                </div>

                <div class="info-section card mb-4">
                    <div class="card-header"><h3>Rincian Komponen Nilai Yang Telah Masuk</h3></div>
                    <div class="card-body">
                        <?php if ($nilai_kp_entry): ?>
                            <dl class="nilai-komponen-display">
                                <dt>Pembimbing Lapangan:</dt><dd><?php echo $nilai_kp_entry['nilai_pembimbing_lapangan'] !== null ? htmlspecialchars(number_format($nilai_kp_entry['nilai_pembimbing_lapangan'],2)) : '<em>Belum diinput</em>'; ?></dd>
                                <dt>Dosen Pembimbing:</dt><dd><?php echo $nilai_kp_entry['nilai_dosen_pembimbing'] !== null ? htmlspecialchars(number_format($nilai_kp_entry['nilai_dosen_pembimbing'],2)) : '<em>Belum diinput</em>'; ?></dd>
                                <dt>Penguji 1 Seminar:</dt><dd><?php echo $nilai_kp_entry['nilai_penguji1_seminar'] !== null ? htmlspecialchars(number_format($nilai_kp_entry['nilai_penguji1_seminar'],2)) : '<em>Belum diinput</em>'; ?></dd>
                                <dt>Penguji 2 Seminar:</dt><dd><?php echo $nilai_kp_entry['nilai_penguji2_seminar'] !== null ? htmlspecialchars(number_format($nilai_kp_entry['nilai_penguji2_seminar'],2)) : '<em>Belum diinput</em>'; ?></dd>
                            </dl>
                            <?php
                                $all_components_filled = ($nilai_kp_entry['nilai_pembimbing_lapangan'] !== null &&
                                                          $nilai_kp_entry['nilai_dosen_pembimbing'] !== null &&
                                                          $nilai_kp_entry['nilai_penguji1_seminar'] !== null &&
                                                          ($nilai_kp_entry['nilai_penguji2_seminar'] !== null || $seminar_detail['nip_dosen_penguji2'] === null) /* Anggap penguji 2 opsional jika NIP nya null di seminar_kp */
                                                         );
                                if (!$all_components_filled):
                            ?>
                                <div class="message warning mt-3"><p>Perhatian: Belum semua komponen nilai utama terisi. Perhitungan nilai akhir mungkin belum optimal atau akurat.</p></div>
                            <?php endif; ?>
                        <?php else: ?>
                            <p><em>Belum ada komponen nilai yang dimasukkan untuk KP ini. Silakan pastikan semua pihak terkait telah menginput nilai.</em></p>
                        <?php endif; ?>
                    </div>
                </div>

                <form action="/KP/admin_prodi/nilai_finalisasi_form.php?id_pengajuan=<?php echo $id_pengajuan_url; ?>" method="POST" class="action-form card">
                    <div class="card-header"><h3><i class="icon-checkmark"></i> Formulir Finalisasi Nilai Akhir</h3></div>
                    <div class="card-body">
                        <input type="hidden" name="id_pengajuan" value="<?php echo $id_pengajuan_url; ?>">

                        <div class="form-group">
                            <label for="nilai_akhir_angka_calc">Nilai Akhir Angka (Hasil Kalkulasi Sistem):</label>
                            <input type="text" id="nilai_akhir_angka_calc" class="form-control readonly-input"
                                   value="<?php echo ($calculated_nilai_angka !== null) ? htmlspecialchars(number_format($calculated_nilai_angka, 2)) : 'Komponen belum lengkap'; ?>" readonly
                                   title="Nilai ini dihitung otomatis berdasarkan bobot. Anda bisa override di bawah.">
                            <small>Bobot: Lapangan (<?php echo BOBOT_PEMBIMBING_LAPANGAN*100;?>%), Dospem (<?php echo BOBOT_DOSEN_PEMBIMBING*100;?>%), Seminar (<?php echo BOBOT_SEMINAR_PENGUJI*100;?>%)</small>
                        </div>

                        <div class="form-group">
                            <label for="nilai_akhir_angka">Input/Override Nilai Akhir Angka (0-100) (*):</label>
                            <input type="number" id="nilai_akhir_angka" name="nilai_akhir_angka" class="form-control"
                                   min="0" max="100" step="0.01"
                                   value="<?php echo htmlspecialchars( ($nilai_kp_entry && isset($nilai_kp_entry['nilai_akhir_angka'])) ? $nilai_kp_entry['nilai_akhir_angka'] : ($calculated_nilai_angka !== null ? number_format($calculated_nilai_angka, 2) : (isset($_POST['nilai_akhir_angka']) ? $_POST['nilai_akhir_angka'] : '')) ); ?>" required
                                   onchange="updateNilaiHuruf(this.value)">
                        </div>

                        <div class="form-group">
                            <label for="nilai_akhir_huruf">Nilai Akhir Huruf (*):</label>
                            <input type="text" id="nilai_akhir_huruf" name="nilai_akhir_huruf" class="form-control"
                                   value="<?php echo htmlspecialchars( ($nilai_kp_entry && isset($nilai_kp_entry['nilai_akhir_huruf'])) ? $nilai_kp_entry['nilai_akhir_huruf'] : ($determined_nilai_huruf ?: (isset($_POST['nilai_akhir_huruf']) ? $_POST['nilai_akhir_huruf'] : '')) ); ?>" required maxlength="3">
                            <small>Otomatis terisi berdasarkan nilai angka, atau input manual (Contoh: A, AB, B).</small>
                        </div>
                        
                        <div class="form-group">
                            <input type="checkbox" id="is_final" name="is_final" value="1" <?php echo ($nilai_kp_entry && isset($nilai_kp_entry['is_final']) && $nilai_kp_entry['is_final'] == 1) ? 'checked' : ''; ?>>
                            <label for="is_final" style="display:inline; font-weight:normal;"> Tandai sebagai Nilai Final</label>
                            <small style="display:block;">Jika dicentang, nilai dianggap final dan status KP akan diubah menjadi 'Selesai Dinilai'.</small>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" name="submit_finalisasi_nilai" class="btn btn-success">Simpan & Finalisasi Nilai</button>
                        </div>
                    </div>
                </form>

            <?php elseif(empty($display_error_message)): ?>
                <div class="message info"><p>Silakan periksa kembali ID Pengajuan pada URL.</p></div>
            <?php endif; ?>
        </div>
    </main>
</div>

<script>
function updateNilaiHuruf(nilaiAngka) {
    const fieldNilaiHuruf = document.getElementById('nilai_akhir_huruf');
    if (!fieldNilaiHuruf) return;

    const angka = parseFloat(nilaiAngka);
    if (isNaN(angka)) {
        fieldNilaiHuruf.value = '';
        return;
    }

    // Skala nilai (sesuaikan dengan definisi di PHP)
    if (angka >= 85) fieldNilaiHuruf.value = 'A';
    else if (angka >= 80) fieldNilaiHuruf.value = 'A-';
    else if (angka >= 75) fieldNilaiHuruf.value = 'B+';
    else if (angka >= 70) fieldNilaiHuruf.value = 'B';
    else if (angka >= 65) fieldNilaiHuruf.value = 'B-';
    else if (angka >= 60) fieldNilaiHuruf.value = 'C+';
    else if (angka >= 55) fieldNilaiHuruf.value = 'C';
    else if (angka >= 50) fieldNilaiHuruf.value = 'D';
    else fieldNilaiHuruf.value = 'E';
}
// Panggil saat load jika nilai angka sudah ada untuk mengisi nilai huruf awal
document.addEventListener('DOMContentLoaded', function() {
    const nilaiAngkaInput = document.getElementById('nilai_akhir_angka');
    if (nilaiAngkaInput && nilaiAngkaInput.value !== '') {
        // Hanya update nilai huruf jika field nilai huruf masih kosong (agar tidak override input manual admin jika ada)
        const nilaiHurufInput = document.getElementById('nilai_akhir_huruf');
        if (nilaiHurufInput && nilaiHurufInput.value === '') {
            updateNilaiHuruf(nilaiAngkaInput.value);
        }
    }
});
</script>

<style>
    /* CSS dari versi sebelumnya bisa tetap digunakan */
    .finalisasi-nilai-form h1 { margin-top: 0; margin-bottom: 5px; }
    .finalisasi-nilai-form hr { margin-top:15px; margin-bottom: 20px; }
    .btn.mb-3 { margin-bottom: 1rem !important; }
    .icon-checkmark::before { content: "✔️ "; }

    .info-section.card { margin-bottom: 1.5rem; }
    .info-section.card .card-header h3 { font-size: 1.2em; }
    .info-section.card .card-body dl dt { width: 180px; float:left; font-weight:bold; margin-bottom:0.5rem; padding-right: 10px; box-sizing: border-box;}
    .info-section.card .card-body dl dd { margin-left: 180px; margin-bottom:0.5rem; }

    .nilai-komponen-display dt { width: 200px !important; } /* Perlebar untuk label komponen nilai */
    .nilai-komponen-display dd { margin-left: 210px !important; }


    .action-form.card .card-header h3 { font-size: 1.2em; color: #28a745; }
    .message.warning { background-color: #fff3cd; color: #856404; border-color: #ffeeba; }
    .readonly-input { background-color: #e9ecef; cursor: not-allowed; }
</style>

<?php
require_once '../includes/footer.php';

if (isset($conn) && ($conn instanceof mysqli) && !$conn->connect_error) {
    $conn->close();
}
?>