<?php
session_start();

// Hapus semua session yang ada
session_unset();

// Hapus cookie username yang diset sebelumnya
if (isset($_COOKIE['username'])) {
    setcookie('username', '', time() - 3600, "/");  // Menghapus cookie dengan waktu kedaluwarsa 1 jam sebelumnya
}

// Hancurkan sesi
session_destroy();

// Redirect ke halaman login
header("Location: /");
exit();