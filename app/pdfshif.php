<?php
include "../config/koneksi.php";

// API Key dari https://pdfshift.io
$apiKey = 'sk_4b0ca28975e46849fead7947c86b14d5b749dc86'; // Ganti dengan API key kamu

// Folder output PDF
$output_dir = '/home/dvttaulx/hadir.masbendz.com/pdf_output/';
if (!is_dir($output_dir)) {
    mkdir($output_dir, 0755, true);
}

// Ambil nama pembimbing unik
$sql = "SELECT DISTINCT nama_pembimbing FROM penempatan";
$result = $conn->query($sql);

echo "<pre>";

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $pembimbing = $row['nama_pembimbing'];
        $safeName = preg_replace('/[^A-Za-z0-9_\-]/', '', $pembimbing);
        $url = "https://hadir.masbendz.com/app/proxylinktojpg.php?pembimbing=" . urlencode($pembimbing);
        $tanggal = date('Ymd');
        $output_file = $output_dir . $tanggal . '_' . $safeName . '.pdf';

        if (file_exists($output_file)) {
            echo "⏩ Dilewati (sudah ada): $output_file<br>";
            continue;
        }

        echo "📄 Membuat PDF dari: $url<br>";

        $postData = json_encode([
            'source' => $url,
            'landscape' => true,
            'use_print' => true
        ]);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => "https://api.pdfshift.io/v3/convert/pdf",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $postData,
            CURLOPT_HTTPHEADER => [
                "X-API-Key: $apiKey",
                "Content-Type: application/json"
            ]
        ]);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);

        curl_close($ch);

        if ($http_code == 200 && !$error && $response) {
            file_put_contents($output_file, $response);
            echo "✅ Berhasil membuat: $output_file<br>";
        } else {
            echo "❌ Gagal membuat PDF untuk $pembimbing. HTTP $http_code<br>";
            if ($error) {
                echo "Curl error: $error<br>";
            }
            echo "Respon: $response<br>";

            $log = "ERROR saat memproses: $url\n";
            $log .= "Pembimbing: $pembimbing\n";
            $log .= "HTTP Code: $http_code\n";
            $log .= "Curl Error: $error\n";
            $log .= "Response: $response\n\n";

            file_put_contents($output_dir . 'log.txt', $log, FILE_APPEND);
        }
    }
} else {
    echo "Tidak ada data pembimbing ditemukan.";
}

echo "</pre>";
$conn->close();
?>