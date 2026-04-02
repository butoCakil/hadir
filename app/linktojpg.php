<?php
// Pastikan error ditampilkan
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Tambah waktu maksimal dan memory limit
set_time_limit(300); // 5 menit
ini_set('memory_limit', '256M');

// Flush output ke browser untuk mencegah timeout via cron
@ob_implicit_flush(true);
@ob_end_flush();

date_default_timezone_set('Asia/Jakarta');
include "../config/koneksi.php";

// API ScreenshotMachine
$customer_key = "847c40"; // Ganti dengan API key kamu
$secret_phrase = ""; // Kosongkan jika tidak ada
$logFile = __DIR__ . '/screenshot_error.log';

// Fungsi pembuat signature (jika diperlukan di masa depan)
function generate_signature($customer_key, $secret_phrase, $url)
{
    return md5($url . $secret_phrase);
}

$sql = "SELECT DISTINCT nama_pembimbing FROM penempatan";
$result = $conn->query($sql);

$counter = 1;

if ($result->num_rows > 0) {
    $jumlah = $result->num_rows;
    while ($row = $result->fetch_assoc()) {
        $pembimbing = $row['nama_pembimbing'];
        $safeName = preg_replace('/[^A-Za-z0-9_\-]/', '', $pembimbing);
        $encodedUrl = urlencode("https://hadir.masbendz.com/app/proxylinktojpg.php?pembimbing=" . urlencode($pembimbing));

        // Parameter ScreenshotMachine
        $params = [
            "key" => $customer_key,
            "url" => "https://hadir.masbendz.com/app/proxylinktojpg.php?pembimbing=" . urlencode($pembimbing),
            "dimension" => "1024xfull",
            "device" => "desktop",
            "format" => "jpg",
            "cacheLimit" => "0",
            "delay" => "200",
            "zoom" => "100"
        ];

        // Jika kamu ingin memakai secret_phrase:
        // $params['hash'] = generate_signature($customer_key, $secret_phrase, $params['url']);

        // Build URL
        $api_url = "https://api.screenshotmachine.com?" . http_build_query($params);

        // Simpan ke folder
        $folderPath = "../img/screenshots";
        if (!file_exists($folderPath)) {
            mkdir($folderPath, 0755, true);
        }

        $timestamp = date("Ymd");
        $savePath = "$folderPath/screenshot_{$safeName}_$timestamp.jpg";

        // Lewati jika file sudah ada dan valid
        if (file_exists($savePath) && filesize($savePath) > 1024) { // bisa disesuaikan batas minimalnya
            echo "($counter/$jumlah) ⚠️ File sudah ada, lewati: $savePath<br>";
            $counter++;
            continue;
        }

        // Ambil gambar dengan cURL
        $ch = curl_init($api_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        $image = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode != 200 || !$image || strlen($image) < 1000) {
            $logMsg = "Gagal screenshot $pembimbing (HTTP $httpCode) " . date('Y-m-d H:i:s') . "\n";
            file_put_contents($logFile, $logMsg, FILE_APPEND);
            echo "❌ $logMsg<br>";
            continue;
        }

        sleep(2); // Jeda 2 detik antar screenshot

        file_put_contents($savePath, $image);

        echo "($counter/$jumlah) ✅ Screenshot untuk  $counter. $pembimbing disimpan ke: $savePath<br>";
        echo "$encodedUrl<br>";
        file_put_contents(__DIR__ . '/screenshot_status.log', "Proses pembimbing ke-$counter: $pembimbing\n", FILE_APPEND);
        flush();
        sleep(2);
        $counter++;
    }
} else {
    echo "Tidak ada pembimbing ditemukan.";
}

$conn->close();
?>