<?php
// /KP/includes/footer.php (Versi Baru & Ditingkatkan)

$current_year = date("Y");
?>

    <footer class="site-footer-enhanced">
        <div class="footer-content">
            <div class="footer-section about">
                <h3 class="footer-brand">SIM-KP</h3>
                <p>Sistem Informasi Manajemen Kerja Praktek yang membantu mengelola alur kerja praktek dari pengajuan hingga penilaian secara efisien.</p>
            </div>
            <div class="footer-section links">
                <h4>Tautan Cepat</h4>
                <ul>
                    <li><a href="/KP/index.php">Halaman Utama</a></li>
                    <li><a href="../panduan.php">Panduan</a></li>
                    <li><a href="https://wa.me/082338112859/">Tanya Jawab</a></li>
                    <li><a href="https://wkamdo.wordpress.com/">Kontak Admin</a></li>
                </ul>
            </div>
            <div class="footer-section contact">
                <h4>Hubungi Kami</h4>
                <p>
                    <i class="fas fa-map-marker-alt"></i> Universitas Trunojoyo Madura<br>
                    Jl. Raya Telang, Kamal, Bangkalan
                </p>
                <p>
                    <i class="fas fa-envelope"></i> admin@trunojoyo.ac.id
                </p>
            </div>
        </div>
        <div class="footer-bottom">
            <p>&copy; <?php echo $current_year; ?> Sistem Informasi Manajemen Kerja Praktek. Dibuat oleh <a href="https://www.instagram.com/wkamdo" target="_blank" rel="noopener noreferrer">@Wkamdo</a>.</p>
        </div>
    </footer>

    <!-- Pastikan Font Awesome sudah dimuat, jika belum, tambahkan link ini di header.php -->
    <!-- <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css"> -->

    <style>
        /* Definisi variabel warna untuk footer */
        :root {
            --footer-bg: #1f2937; /* Biru sangat gelap */
            --footer-bg-bottom: #111827; /* Hitam kebiruan */
            --footer-text: #d1d5db; /* Abu-abu terang */
            --footer-heading: #ffffff; /* Putih */
            --footer-link: #93c5fd; /* Biru muda */
            --footer-link-hover: #ffffff; /* Putih saat hover */
        }

        .site-footer-enhanced {
            background-color: var(--footer-bg);
            color: var(--footer-text);
            padding: 3rem 2rem 0; /* Padding atas lebih besar, bawah 0 karena akan ditangani oleh .footer-bottom */
            margin-top: auto; /* Mendorong footer ke bawah halaman */
            font-size: 0.95em;
            line-height: 1.7;
            width: 100%;
            box-sizing: border-box; /* Pastikan padding tidak menambah lebar */
        }
        
        .footer-content {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 2rem;
            max-width: 1200px;
            margin: 0 auto;
            padding-bottom: 2rem;
        }

        .footer-section h3, .footer-section h4 {
            color: var(--footer-heading);
            margin-bottom: 1rem;
            font-weight: 600;
        }
        
        .footer-brand {
            font-size: 1.5rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .footer-section p {
            margin-bottom: 0.5rem;
        }

        .footer-section.links ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .footer-section.links ul li {
            margin-bottom: 0.5rem;
        }

        .footer-section a {
            color: var(--footer-link);
            text-decoration: none;
            transition: color 0.3s ease;
        }
        
        .footer-section a:hover {
            color: var(--footer-link-hover);
            text-decoration: underline;
        }
        
        .footer-section.contact i {
            margin-right: 10px;
            color: var(--footer-link);
            width: 20px;
            text-align: center;
        }

        .footer-bottom {
            background-color: var(--footer-bg-bottom);
            text-align: center;
            padding: 1.5rem 1rem;
            /* Trik untuk membuat background full-width meski parent punya padding */
            margin-left: -2rem;
            margin-right: -2rem;
        }
        
        .footer-bottom p {
            margin: 0;
            font-size: 0.9em;
        }
        
        .footer-bottom a {
            color: var(--footer-link);
            font-weight: 500;
        }
    </style>
</body>
</html>
