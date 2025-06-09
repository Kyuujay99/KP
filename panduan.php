<?php
// /KP/panduan.php (Versi Baru: Dinamis, Detail, dan Modern)

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$page_title = "Panduan Pengguna";
require_once 'includes/header.php';

// Ambil peran pengguna yang sedang login
$user_role = $_SESSION['user_role'] ?? 'guest'; // 'guest' jika tidak ada yang login

// Data panduan yang terstruktur untuk setiap peran
$guides = [
    'mahasiswa' => [
        'title' => 'Panduan untuk Mahasiswa',
        'icon' => '<svg viewBox="0 0 24 24"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2H6.5A2.5 2.5 0 0 1 4 2.5V2.5c0-.41.17-.79.44-1.06A1.5 1.5 0 0 1 6.5 1H20a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6.5a2.5 2.5 0 0 1 0-5H20"></path></svg>',
        'content' => '
            <h4>Langkah 1: Pendaftaran dan Persiapan</h4>
            <p><strong>Registrasi Akun:</strong> Daftarkan diri Anda melalui halaman **Registrasi**. Pastikan Anda memilih peran sebagai "Mahasiswa" dan mengisi NIM, email, serta data lainnya dengan benar. Akun Anda perlu diverifikasi oleh Admin Prodi sebelum bisa digunakan.</p>
            <p><strong>Cek Prasyarat:</strong> Setelah login, buka menu <strong>Pengajuan KP Baru</strong>. Sistem akan secara otomatis menampilkan status prasyarat Anda (jumlah SKS dan IPK). Anda hanya bisa melanjutkan jika kedua syarat tersebut terpenuhi.</p>
            
            <h4>Langkah 2: Mengajukan Kerja Praktek</h4>
            <ol>
                <li>Buka menu **Pengajuan KP Baru**.</li>
                <li>Isi semua detail yang diperlukan, seperti judul KP yang menarik dan deskripsi rencana kegiatan yang jelas.</li>
                <li><strong>Pilih Perusahaan:</strong>
                    <ul>
                        <li>Jika perusahaan sudah menjadi mitra, pilih dari daftar yang tersedia.</li>
                        <li>Jika perusahaan belum terdaftar, pilih opsi <strong>`[LAINNYA] Input perusahaan baru`</strong> dan ketikkan nama perusahaan secara manual. Data ini akan diverifikasi oleh Admin.</li>
                    </ul>
                </li>
                <li>Unggah dokumen proposal jika sudah ada (opsional, bisa menyusul).</li>
                <li>Klik <strong>Kirim Pengajuan</strong> untuk memulai proses.</li>
            </ol>

            <h4>Langkah 3: Memantau Proses dan Pelaksanaan KP</h4>
            <ul>
                <li><strong>Pantau Status:</strong> Buka menu **Riwayat Pengajuan** untuk melihat status terbaru pengajuan Anda (misal, `Menunggu Verifikasi Dosen`, `Diterima Perusahaan`, dll.) serta membaca catatan penting dari Dosen atau Admin.</li>
                <li><strong>Isi Logbook:</strong> Selama KP berlangsung, sangat penting untuk mengisi <strong>Logbook</strong> secara rutin melalui menu yang tersedia. Catatan harian/mingguan ini akan menjadi bukti kegiatan Anda dan akan diperiksa oleh Dosen Pembimbing.</li>
                <li><strong>Upload Dokumen:</strong> Jika ada dokumen tambahan yang perlu diunggah (seperti laporan kemajuan, surat balasan, atau sertifikat), gunakan menu **Dokumen Saya** lalu pilih pengajuan KP yang sesuai.</li>
            </ul>
            
            <h4>Langkah 4: Tahap Akhir</h4>
            <p>Setelah semua proses selesai, Anda dapat melihat rincian nilai akhir Anda di menu **Nilai**. Informasi mengenai jadwal seminar juga dapat diakses melalui menu **Seminar** jika sudah ditetapkan oleh Admin Prodi.</p>
        '
    ],
    'dosen' => [
        'title' => 'Panduan untuk Dosen',
        'icon' => '<svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>',
        'content' => '
            <h4>Langkah 1: Verifikasi Proposal Pengajuan</h4>
            <p>Buka menu **Verifikasi Pengajuan**. Halaman ini berisi daftar mahasiswa yang telah ditugaskan oleh Admin Prodi kepada Anda untuk dibimbing. Klik **Verifikasi/Detail** untuk meninjau proposal, dokumen, dan data mahasiswa secara lengkap, lalu berikan keputusan (Setujui/Tolak) beserta catatan.</p>
            
            <h4>Langkah 2: Mengelola Bimbingan dan Memantau Logbook</h4>
            <p>Akses menu **Mahasiswa Bimbingan** untuk melihat daftar semua mahasiswa yang sedang aktif dalam bimbingan Anda. Dari sini, Anda dapat:</p>
            <ul>
                <li><strong>Mencatat Sesi Bimbingan:</strong> Klik **Kelola Bimbingan** untuk menambahkan catatan pertemuan, memberikan arahan, atau mengunggah file revisi untuk mahasiswa.</li>
                <li><strong>Memverifikasi Logbook:</strong> Masih di halaman yang sama, Anda bisa melihat dan memberikan status verifikasi pada setiap entri logbook yang diisi oleh mahasiswa.</li>
            </ul>
            
            <h4>Langkah 3: Memberikan Penilaian</h4>
            <p>Anda memiliki dua peran dalam penilaian:</p>
            <ul>
                <li><strong>Sebagai Pembimbing:</strong> Setelah mahasiswa menyelesaikan KP, buka menu **Input Nilai**. Berikan penilaian akhir Anda berdasarkan proses bimbingan dan kualitas laporan.</li>
                <li><strong>Sebagai Penguji:</strong> Jika Anda ditugaskan sebagai penguji seminar, buka menu **Jadwal Seminar**. Klik **Detail/Nilai** pada jadwal yang relevan untuk memberikan nilai seminar setelah pelaksanaannya.</li>
            </ul>
        '
    ],
    'admin_prodi' => [
        'title' => 'Panduan untuk Admin Prodi',
        'icon' => '<svg viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>',
        'content' => '
            <h4>Langkah 1: Manajemen Data Master</h4>
            <p>Sebagai pusat kendali sistem, Anda bertanggung jawab mengelola data utama:</p>
            <ul>
                <li><strong>Kelola Mahasiswa & Dosen:</strong> Tambah, edit, dan kelola status akun (aktif/suspend) untuk memastikan semua pengguna dapat mengakses sistem dengan benar.</li>
                <li><strong>Kelola Perusahaan:</strong> Verifikasi dan aktifkan pendaftaran perusahaan baru agar dapat dipilih oleh mahasiswa.</li>
            </ul>

            <h4>Langkah 2: Memproses Alur Kerja Praktek</h4>
            <ol>
                <li>Buka **Monitoring Pengajuan** untuk melihat semua pengajuan yang masuk dari mahasiswa.</li>
                <li>Klik **Kelola** pada pengajuan yang berstatus `Diajukan Mahasiswa`.</li>
                <li>Langkah krusial: **Tugaskan Dosen Pembimbing** melalui formulir yang tersedia di halaman detail. Tanpa ini, alur tidak dapat berlanjut.</li>
                <li>Setelah KP diterima perusahaan, Anda dapat membuat surat resmi melalui **Manajemen Surat**.</li>
            </ol>
            
            <h4>Langkah 3: Administrasi dan Finalisasi</h4>
            <ul>
                <li>Buka menu **Verifikasi Dokumen** untuk memeriksa dan memberikan status (setuju/revisi/tolak) pada semua dokumen penting yang diunggah mahasiswa.</li>
                <li>Setelah semua komponen nilai (dari dosen dan perusahaan) masuk, buka halaman detail pengajuan mahasiswa tersebut, lalu klik tombol untuk masuk ke halaman **Finalisasi Nilai** untuk menghitung dan menetapkan nilai akhir.</li>
            </ul>
        '
    ],
    'perusahaan' => [
        'title' => 'Panduan untuk Perusahaan',
        'icon' => '<svg viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path><polyline points="9 22 9 12 15 12 15 22"></polyline></svg>',
        'content' => '
            <h4>Langkah 1: Konfirmasi Pengajuan Lamaran KP</h4>
            <p>Buka menu **Pengajuan Masuk** untuk melihat daftar mahasiswa yang mengajukan permohonan Kerja Praktek di perusahaan Anda. Klik **Lihat & Konfirmasi** untuk meninjau detail proposal, profil mahasiswa, dan dokumen pendukung. Anda dapat memberikan keputusan **Terima** atau **Tolak**. Sangat disarankan untuk mengunggah surat balasan resmi jika ada.</p>
            
            <h4>Langkah 2: Input Penilaian Kinerja</h4>
            <p>Setelah mahasiswa menyelesaikan masa Kerja Praktek, buka menu **Input Penilaian Lapangan**. Anda akan melihat daftar mahasiswa yang memerlukan penilaian. Klik **Beri Nilai** untuk memberikan evaluasi kinerja mahasiswa selama berada di perusahaan Anda, yang akan menjadi salah satu komponen nilai akhir mereka.</p>

            <h4>Langkah 3: Mengelola Profil Perusahaan</h4>
            <p>Gunakan menu **Profil Perusahaan** untuk memperbarui detail seperti alamat, bidang usaha, atau data Kontak Person (PIC) agar informasi yang diterima mahasiswa dan universitas selalu akurat.</p>
        '
    ]
];
?>

<!-- KONTENER UTAMA UNTUK PANDUAN -->
<div class="guide-container">

    <div class="guide-hero-section">
        <div class="guide-hero-content">
            <div class="guide-hero-icon">
                <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>
            </div>
            <h1>Panduan Pengguna SIM-KP</h1>
            <p>Temukan semua informasi yang Anda butuhkan untuk menggunakan sistem ini secara efektif sesuai dengan peran Anda.</p>
        </div>
    </div>

    <div class="guide-wrapper">
        <div class="guide-section animate-on-scroll">
            <div class="section-header">
                <svg viewBox="0 0 24 24"><path d="M17.5 1.917a6.4 6.4 0 0 0-5.5 3.083a6.4 6.4 0 0 0-5.5-3.083A6.4 6.4 0 0 0 .5 8.318c0 3.545 2.867 6.417 6.417 6.417a6.4 6.4 0 0 0 5.5-3.083a6.4 6.4 0 0 0 5.5 3.083A6.4 6.4 0 0 0 23.5 8.318a6.4 6.4 0 0 0-6-6.4zM.5 15.682A6.4 6.4 0 0 0 6.917 22a6.4 6.4 0 0 0 5.5-3.083a6.4 6.4 0 0 0 5.5 3.083a6.4 6.4 0 0 0 6.417-6.318h-23z"></path></svg>
                <h2>Alur Global Kerja Praktek</h2>
            </div>
            <ol class="timeline">
                <li><strong>Persiapan & Pengajuan:</strong> Mahasiswa mengajukan proposal KP setelah memenuhi syarat.</li>
                <li><strong>Verifikasi & Penugasan:</strong> Admin Prodi menugaskan Dosen Pembimbing, yang kemudian memverifikasi proposal.</li>
                <li><strong>Konfirmasi Industri:</strong> Perusahaan menerima atau menolak pengajuan.</li>
                <li><strong>Pelaksanaan:</strong> Mahasiswa mengisi logbook, dosen melakukan bimbingan.</li>
                <li><strong>Penilaian & Seminar:</strong> Dosen & perusahaan memberi nilai, diakhiri dengan seminar dan finalisasi nilai oleh Admin.</li>
            </ol>
        </div>

        <div class="guide-section animate-on-scroll">
            <div class="section-header">
                <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                <h2>Panduan Berdasarkan Peran</h2>
            </div>
            <div class="accordion">
                <?php foreach ($guides as $role => $data): ?>
                    <?php if ($user_role === 'guest' || $user_role === $role): ?>
                        <div class="accordion-item">
                            <button class="accordion-header <?php echo ($user_role === $role) ? 'active' : ''; ?>">
                                <span><?php echo $data['title']; ?></span>
                                <span class="icon"></span>
                            </button>
                            <div class="accordion-content <?php echo ($user_role === $role) ? 'active' : ''; ?>">
                                <?php echo $data['content']; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<style>
/* GAYA BARU YANG TERISOLASI DALAM KONTENER */
.guide-container { --primary-color:#667eea; --primary-gradient:linear-gradient(135deg,#667eea 0%,#764ba2 100%); --text-primary:#1f2937; --text-secondary:#6b7280; --bg-light:#f9fafb; --border-color:#e5e7eb; --card-shadow:0 4px 6px -1px rgba(0,0,0,.1),0 2px 4px -1px rgba(0,0,0,.06); --border-radius:16px; font-family:Inter,sans-serif; color:var(--text-primary); max-width:1000px; margin:0 auto; padding:2rem }
.guide-container svg { stroke-width:2; stroke-linecap:round; stroke-linejoin:round; fill:none; stroke:currentColor }
.guide-hero-section { padding:3rem 2rem; background:var(--primary-gradient); border-radius:var(--border-radius); margin-bottom:2.5rem; color:#fff; text-align:center }
.guide-hero-content { max-width:700px; margin:0 auto }
.guide-hero-icon { width:60px; height:60px; background:rgba(255,255,255,.1); border-radius:50%; display:flex; align-items:center; justify-content:center; margin:0 auto 1.5rem }
.guide-hero-icon svg { width:28px; height:28px; stroke:#fff }
.guide-hero-section h1 { font-size:2.5rem; font-weight:700; margin-bottom:.5rem }
.guide-hero-section p { font-size:1.1rem; opacity:.9; font-weight:300 }
.guide-wrapper { background-color:#fff; padding:2.5rem; border-radius:var(--border-radius); box-shadow:var(--card-shadow) }
.guide-section { margin-bottom:2.5rem }
.section-header { display:flex; align-items:center; gap:1rem; margin-bottom:1.5rem; padding-bottom:1rem; border-bottom:1px solid var(--border-color) }
.section-header svg { width:24px; height:24px; color:var(--primary-color) }
.section-header h2 { font-size:1.5rem; margin:0 }
.timeline { list-style:none; padding:0 }
.timeline li { position:relative; padding-left:25px; margin-bottom:10px }
.timeline li::before { content:'✓'; position:absolute; left:0; top:0; width:20px; height:20px; background-color:#d1fae5; color:#059669; border-radius:50%; display:flex; align-items:center; justify-content:center; font-weight:700; font-size:12px }
.accordion { display:flex; flex-direction:column; gap:1rem }
.accordion-item { border:1px solid var(--border-color); border-radius:12px; overflow:hidden }
.accordion-header { width:100%; background-color:var(--bg-light); padding:1rem 1.5rem; border:none; text-align:left; font-size:1.1rem; font-weight:600; cursor:pointer; display:flex; justify-content:space-between; align-items:center; transition:background-color .2s ease }
.accordion-header:hover { background-color:#f3f4f6 }
.accordion-header .icon { width:10px; height:10px; border-right:2px solid var(--text-secondary); border-bottom:2px solid var(--text-secondary); transform:rotate(45deg); transition:transform .3s ease }
.accordion-header.active .icon { transform:rotate(-135deg) }
.accordion-content { max-height:0; overflow:hidden; transition:max-height .4s ease-out,padding .4s ease-out; padding:0 1.5rem }
.accordion-content.active { padding:1.5rem }
.accordion-content h4 { margin-top:0; margin-bottom:1rem; color:var(--primary-color) }
.accordion-content p,.accordion-content ul,.accordion-content ol { margin-bottom:1rem; line-height:1.7 }
.accordion-content ul,.accordion-content ol { padding-left:20px }
.animate-on-scroll { opacity:0; transform:translateY(30px); transition:opacity .6s ease-out,transform .6s ease-out }
.animate-on-scroll.is-visible { opacity:1; transform:translateY(0) }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Accordion Logic
    const accordionItems = document.querySelectorAll('.accordion-item');
    accordionItems.forEach(item => {
        const header = item.querySelector('.accordion-header');
        const content = item.querySelector('.accordion-content');

        // Buka accordion yang aktif secara default
        if (header.classList.contains('active')) {
            content.style.maxHeight = content.scrollHeight + "px";
        }

        header.addEventListener('click', () => {
            const isActive = header.classList.contains('active');
            
            // Tutup semua accordion
            accordionItems.forEach(otherItem => {
                otherItem.querySelector('.accordion-header').classList.remove('active');
                const otherContent = otherItem.querySelector('.accordion-content');
                otherContent.classList.remove('active');
                otherContent.style.maxHeight = null;
            });

            // Buka atau tutup item yang diklik
            if (!isActive) {
                header.classList.add('active');
                content.classList.add('active');
                content.style.maxHeight = content.scrollHeight + "px";
            }
        });
    });

    // Scroll Animation Logic
    const animatedElements = document.querySelectorAll('.animate-on-scroll');
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('is-visible');
                observer.unobserve(entry.target);
            }
        });
    }, { threshold: 0.1 });
    animatedElements.forEach(el => observer.observe(el));
});
</script>

<?php
require_once 'includes/footer.php';
?>
