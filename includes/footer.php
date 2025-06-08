<?php
// /KP/includes/footer.php

$current_year = date("Y");
?>

    <footer class="site-footer">
        <p>&copy; <?php echo $current_year; ?> Sistem Informasi Manajemen Kerja Praktek. By <a href="https://www.instagram.com/wkamdo" target="_blank" rel="noopener noreferrer">@Wkamdo</a>.</p>
    </footer>

    <style>
        .site-footer {
            background-color: var(--dark-color);
            color: var(--light-color);
            text-align: center;
            padding: 1.5rem 1rem;
            margin-top: auto; /* Mendorong footer ke bawah */
            font-size: 0.9em;
            opacity: 0.9;
        }
        
        /* Style untuk link di footer */
        .site-footer a {
            color: var(--light-color);
            font-weight: 600;
            text-decoration: none;
        }
        
        .site-footer a:hover {
            text-decoration: underline;
        }
    </style>
</body>
</html>