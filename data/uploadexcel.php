<?php
session_start();

// Cek apakah pengguna sudah login
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    $_SESSION['redirect_to'] = $_SERVER['REQUEST_URI'];
    header("Location: ../app/login.php");
    exit();
}
?>

<?php
require '../dist/excel/vendor/autoload.php'; // Autoload PhpSpreadsheet

use PhpOffice\PhpSpreadsheet\IOFactory;

include "../config/koneksi.php";

// Proses file upload
if (isset($_FILES['file']['name'])) {
    $fileName = $_FILES['file']['name'];
    $fileTmpName = $_FILES['file']['tmp_name'];

    // Cek apakah file adalah Excel
    $fileType = pathinfo($fileName, PATHINFO_EXTENSION);
    if ($fileType != 'xls' && $fileType != 'xlsx') {
        die("Hanya file Excel yang diizinkan!");
    }

    // Membaca file Excel
    $spreadsheet = IOFactory::load($fileTmpName);
    $worksheet = $spreadsheet->getActiveSheet();
    $rows = $worksheet->toArray();

    // Abaikan baris pertama (header)
    unset($rows[0]);

    // Simpan ke database
    $stmt = $conn->prepare("INSERT INTO datasiswa (nis, nama, kelas, jur, lp) VALUES (?, ?, ?, ?, ?)");
    foreach ($rows as $row) {
        $nama = $row[3]; // Kolom NIS
        $kelas = $row[2]; // Kolom Nama
        $lp = $row[5]; // Kolom Tingkat Kelas
        $jur = $row[0]; // Kolom Program Keahlian
        $nis = $row[4]; // Kolom L/P

        $stmt->bind_param("sssss", $nis, $nama, $kelas, $jur, $lp);
        $stmt->execute();
    }
    $stmt->close();

    echo "Data berhasil diunggah ke database!";
} else {
    echo "Tidak ada file yang diunggah.";
}

$conn->close();