<?php

// Client ID dan Client Secret yang Anda dapatkan dari Google Developer Console
$clientId = "716208219569-28ovbdejptmpoimbhtkanv4dtjnjje35.apps.googleusercontent.com"; // Ganti dengan Client ID Anda
$clientSecret = "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"; // Ganti dengan Client Secret Anda
$redirectUri = "https://hadir.masbendz.com/oauth2callback/";  // Sesuaikan dengan URI Anda

// Cek apakah ada kode authorization yang diterima dari Google
if (isset($_GET['code'])) {
    // Dapatkan authorization code dari URL
    $authorizationCode = $_GET['code'];

    // URL untuk mendapatkan access token
    $tokenUrl = "https://oauth2.googleapis.com/token";

    // Data yang akan dikirimkan ke Google untuk mendapatkan access token
    $data = [
        'code' => $authorizationCode,
        'client_id' => $clientId,
        'client_secret' => $clientSecret,
        'redirect_uri' => $redirectUri,
        'grant_type' => 'authorization_code',
    ];

    // Konfigurasi permintaan POST untuk mendapatkan access token
    $options = [
        'http' => [
            'method' => 'POST',
            'header' => 'Content-type: application/x-www-form-urlencoded',
            'content' => http_build_query($data),
        ]
    ];

    // Kirim permintaan ke Google untuk mendapatkan access token
    $context = stream_context_create($options);
    $response = file_get_contents($tokenUrl, false, $context);

    if ($response === FALSE) {
        die('Error occurred while exchanging authorization code for access token');
    }

    // Decode JSON response untuk mendapatkan access token
    $responseData = json_decode($response, true);

    // Ambil access token dan refresh token dari respon
    $accessToken = $responseData['access_token'];
    $refreshToken = $responseData['refresh_token']; // Anda bisa menyimpan ini untuk refresh token di masa depan

    // Simpan access token dan refresh token (misalnya, di session atau database)
    session_start();
    $_SESSION['access_token'] = $accessToken;
    $_SESSION['refresh_token'] = $refreshToken;

    // Sekarang Anda bisa menggunakan access token untuk mengakses API Google
    // echo "Access token berhasil diperoleh!";
} else {
    // Jika tidak ada authorization code di URL, tampilkan pesan kesalahan
    // echo "Authorization code tidak ditemukan!";
}