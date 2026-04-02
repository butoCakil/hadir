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

// Koneksi ke database
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

    // Variabel untuk menyimpan data sebelumnya
    $previousData = [
        'nama_pembimbing' => '',
        'nama_dudika' => '',
        'alamat_dudika' => '',
        'no_telepon_dudika' => '',
        'nis_siswa' => '',
        'nama_siswa' => '',
        'kelas' => ''
    ];

    // Simpan ke database
    $stmtPenempatan = $conn->prepare("INSERT INTO penempatan (nama_siswa, nis_siswa, kelas, nama_dudika, alamat_dudika, nomor_telepon_dudika, nama_pembimbing) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmtDatadudi = $conn->prepare("INSERT INTO datadudi (nama, kode, alamat, ket) VALUES (?, ?, ?, ?)");

    foreach ($rows as $row) {
        // Cek dan isi kolom yang kosong dengan data dari baris sebelumnya
        $nama_pembimbing = !empty($row[0]) ? $row[0] : $previousData['nama_pembimbing'];
        $nama_dudika = !empty($row[1]) ? $row[1] : $previousData['nama_dudika'];
        $alamat_dudika = !empty($row[2]) ? $row[2] : $previousData['alamat_dudika'];
        $no_telepon_dudika = !empty($row[3]) ? $row[3] : $previousData['no_telepon_dudika'];
        $nis_siswa = !empty($row[5]) ? $row[5] : $previousData['nis_siswa'];
        $nama_siswa = !empty($row[4]) ? $row[4] : $previousData['nama_siswa'];
        $kelas = !empty($row[6]) ? $row[6] : $previousData['kelas'];

        // Simpan data untuk digunakan di baris berikutnya
        $previousData = [
            'nama_pembimbing' => $nama_pembimbing,
            'nama_dudika' => $nama_dudika,
            'alamat_dudika' => $alamat_dudika,
            'no_telepon_dudika' => $no_telepon_dudika,
            'nis_siswa' => $nis_siswa,
            'nama_siswa' => $nama_siswa,
            'kelas' => $kelas
        ];

        // Generate kode dengan 6 karakter pertama dari nama dudika (huruf kecil) + 2 digit angka random
        $nama_dudika_lower = strtolower($nama_dudika);
        $kode = substr($nama_dudika_lower, 0, 6) . str_pad(rand(0, 99), 2, '0', STR_PAD_LEFT);

        // Cek jika nama dudika dan nomor telepon belum ada di tabel datadudi
        $checkDudiStmt = $conn->prepare("SELECT id FROM datadudi WHERE nama = ? AND alamat = ? AND ket = ?");
        $checkDudiStmt->bind_param("sss", $nama_dudika, $alamat_dudika, $no_telepon_dudika);
        $checkDudiStmt->execute();
        $checkDudiStmt->store_result();

        if ($checkDudiStmt->num_rows == 0) {
            // Insert data ke tabel datadudi jika belum ada
            $stmtDatadudi->bind_param("ssss", $nama_dudika, $kode, $alamat_dudika, $no_telepon_dudika);
            $stmtDatadudi->execute();
        }

        // Insert data ke tabel penempatan
        $stmtPenempatan->bind_param("sssssss", $nama_siswa, $nis_siswa, $kelas, $nama_dudika, $alamat_dudika, $no_telepon_dudika, $nama_pembimbing);
        $stmtPenempatan->execute();
    }

    $stmtPenempatan->close();
    $stmtDatadudi->close();

    echo "Data berhasil diunggah ke database!";
} else {
    echo "Tidak ada file yang diunggah.";
}

$conn->close();