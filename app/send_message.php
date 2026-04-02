<?php
include "../config/koneksi.php";

function sendMessage($number, $message, $fileOrUrl = null)
{
    $url = 'https://api.whacenter.com/api/send';
    $data = [
        'device_id' => '933bbd2c-8931-421c-8432-8e1ba9b3d795',
        'number' => $number,
        'message' => $message,
    ];

    // Jika ada file atau URL, tambahkan ke payload
    if ($fileOrUrl) {
        $data['file'] = $fileOrUrl;
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $data,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15
    ]);

    $response = curl_exec($ch);
    curl_close($ch);

    return $response;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $number = $_POST['number'];
    $message = $_POST['response'];
    $filePath = null;

    // Prioritaskan URL jika diisi
    if (!empty($_POST['file_url'])) {
        $filePath = trim($_POST['file_url']);
    }
    // Jika tidak ada URL, tapi ada upload
    elseif (isset($_FILES['file']) && $_FILES['file']['error'] == 0) {
        $uploadDir = "uploads/";
        if (!file_exists($uploadDir))
            mkdir($uploadDir, 0777, true);
        $fileName = basename($_FILES['file']['name']);
        $filePath = $uploadDir . $fileName;

        if (!file_exists($filePath)) {
            move_uploaded_file($_FILES['file']['tmp_name'], $filePath);
        }

        // Buat URL file agar bisa diakses dari luar
        $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http")
            . "://{$_SERVER['HTTP_HOST']}" . dirname($_SERVER['PHP_SELF']) . "/";
        $filePath = $baseUrl . $filePath;
    }

    sendMessage($number, $message, $filePath);

    header('Content-Type: application/json');
    echo json_encode([
        'number' => $number,
        'status' => 'sent',
        'file_used' => $filePath ?? '(tanpa file)'
    ]);
}
?>