<?php
session_start();
include "../config/koneksi.php";

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401);
    exit;
}

$nis = $_POST['nis'] ?? '';
$pembimbing = $_POST['pembimbing'] ?? '';

if ($nis === '') {
    http_response_code(400);
    exit;
}

$nis = $conn->real_escape_string($nis);
$pembimbing = $conn->real_escape_string($pembimbing);

// Pastikan record penempatan ada
$cek = $conn->query("SELECT id FROM penempatan WHERE nis_siswa = '$nis'");

if ($cek->num_rows > 0) {
    $query = "
        UPDATE penempatan 
        SET nama_pembimbing = " . ($pembimbing === '' ? "NULL" : "'$pembimbing'") . "
        WHERE nis_siswa = '$nis'
    ";
} else {
    $query = "
        INSERT INTO penempatan (nis_siswa, nama_pembimbing)
        VALUES ('$nis', " . ($pembimbing === '' ? "NULL" : "'$pembimbing'") . ")
    ";
}

$conn->query($query);
echo "OK";
