<?php
$msg = '';

// URL Apps Script Anda
$appsScriptUrl = "https://script.google.com/macros/s/AKfycbwX24NNLJIlXlkpYEjePyEaGAu5nPQPyP1tW_A_25rgGc-DVA00qTXkb_cvynNhGNWX6Q/exec";

// Fungsi untuk mengirimkan GET Request ke Apps Script menggunakan cURL

function sendGetRequest($url, $params, $maxRetries = 3)
{
    $query = http_build_query($params);
    $finalUrl = $url . '?' . $query;

    $attempt = 0;
    $response = false;

    while ($attempt < $maxRetries) {
        $attempt++;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $finalUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        // Timeout terpisah: connect & total
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);

        // Ikuti redirect
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

        // Set User-Agent biar tidak diblok
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');

        // Eksekusi
        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            $errorMsg = curl_error($ch);
            error_log("Percobaan {$attempt} gagal: {$errorMsg} | URL: {$finalUrl}");

            // Tunggu sebelum retry (exponential backoff)
            sleep(pow(2, $attempt));
        } else {
            curl_close($ch);
            return $response; // Berhasil
        }

        curl_close($ch);
    }

    // Kalau semua percobaan gagal, jangan langsung fatal error
    return false;
}


// Ambil input NIS dan Kode dari GET (bisa dari form atau parameter URL)
$nis = isset($_GET['nis']) ? $_GET['nis'] : null;
$kode = isset($_GET['kode']) ? $_GET['kode'] : null;
$link = isset($_GET['link']) ? $_GET['link'] : null;

// Pastikan parameter NIS, Kode, dan Link ada
if (!$nis || !$kode || !$link) {
    $msg = json_encode([
        'status' => 'gagal',
        'message' => "Parameter NIS, Kode, dan Link wajib diisi."
    ]);
    exit;
}

// Kirim GET Request ke Apps Script
$params = [
    'nis' => $nis,
    'kode' => $kode,
    'link' => $link
];

$response = sendGetRequest($appsScriptUrl, $params);
$json = json_decode($response, true); // Decode JSON response

// Periksa jika JSON berhasil
if (!$json || $json['status'] != 'berhasil') {
    $msg = json_encode([
        'status' => 'gagal',
        'message' => "Apps Script gagal: " . ($json['message'] ?? 'Unknown error')
    ]);
    exit;
}

// Ambil data dari JSON
$link_tersimpan = $json['link_tersimpan'];

// Koneksi ke Database menggunakan MySQLi
include "../config/koneksi.php";

$statuslink = "OK";

// Update kolom link di tabel presensi berdasarkan NIS dan Kode
$sql = "UPDATE presensi SET link = ?, statuslink = ? WHERE nis = ? AND kode = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ssss", $link_tersimpan, $statuslink, $nis, $kode);

if ($stmt->execute()) {
    // Respon Berhasil
    $msg .= json_encode([
        'status' => 'berhasil',
        'message' => 'Data berhasil diperbarui di database.',
        'nis' => $nis,
        'kode' => $kode,
        'link_baru' => $link_tersimpan
    ]);
} else {
    // Gagal update database
    $msg .= json_encode([
        'status' => 'gagal',
        'message' => "Gagal memperbarui database: " . $stmt->error
    ]);
}

echo $msg;

// Tutup koneksi
$stmt->close();
$conn->close();
?>