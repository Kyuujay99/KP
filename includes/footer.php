<?php
// /KP/includes/footer.php

// Mendapatkan tahun saat ini secara dinamis untuk ditampilkan di copyright.
$current_year = date("Y");

// Variabel $base_url mungkin sudah didefinisikan di header.php.
// Jika belum atau ingin memastikan, bisa didefinisikan ulang di sini atau di file konfigurasi global.
// Untuk konsistensi, jika header.php sudah punya $base_url, kita bisa menggunakannya.
// Jika tidak, kita definisikan di sini untuk contoh path JavaScript (jika ada).
if (!isset($base_url)) {
    $base_url = "/KP"; // Sesuaikan jika nama folder proyekmu berbeda
}
?>

        </div> <footer class="site-footer">
        <p>&copy; <?php echo $current_year; ?> Sistem Informasi Manajemen Kerja Praktek. Hak Cipta Dilindungi.</p>
        </footer>

    </body>
</html>