<?php
session_start();
include "../config/koneksi.php";

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['status' => 'unauthorized']);
    exit;
}

include "../config/koneksi.php";

$nis = $_POST['nis'] ?? '';
$dudika = $_POST['dudika'] ?? '';

if ($nis === '') {
    http_response_code(400);
    exit('NIS kosong');
}

$nis = $conn->real_escape_string($nis);
$dudika = $conn->real_escape_string($dudika);

// Jika data penempatan belum ada, INSERT
$queryCheck = "SELECT id FROM penempatan WHERE nis_siswa = '$nis'";
$res = $conn->query($queryCheck);

if ($res->num_rows > 0) {
    $query = "UPDATE penempatan SET nama_dudika = '$dudika' WHERE nis_siswa = '$nis'";
} else {
    $query = "INSERT INTO penempatan (nis_siswa, nama_dudika) VALUES ('$nis', '$dudika')";
}

$conn->query($query);
echo "OK";
$conn->close();