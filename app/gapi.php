<?php
function uploadFileToGoogleDrive($accessToken, $filePath, $fileName) {
    $url = 'https://www.googleapis.com/upload/drive/v3/files?uploadType=multipart';
    $boundary = uniqid();

    // Metadata file
    $metadata = json_encode([
        'name' => $fileName
    ]);

    // Isi body request
    $body = "--$boundary\r\n" .
            "Content-Type: application/json; charset=UTF-8\r\n\r\n" .
            "$metadata\r\n" .
            "--$boundary\r\n" .
            "Content-Type: " . mime_content_type($filePath) . "\r\n\r\n" .
            file_get_contents($filePath) . "\r\n" .
            "--$boundary--";

    // cURL untuk mengunggah
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer $accessToken",
        "Content-Type: multipart/related; boundary=$boundary",
        "Content-Length: " . strlen($body)
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        echo "Error: " . curl_error($ch);
    } else {
        echo "Response: " . $response;
    }

    curl_close($ch);
}

// Contoh penggunaan
$accessToken = 'YOUR_ACCESS_TOKEN_HERE'; // Token yang diperoleh dari OAuth Playground
$filePath = 'path/to/your/file.jpg';
$fileName = 'example.jpg';
uploadFileToGoogleDrive($accessToken, $filePath, $fileName);
