<?php
require "../config/koneksi.php";

if (!isset($_POST['id'])) {
    die('Akses tidak valid');
}

$id = intval($_POST['id']);

$query = mysqli_query($conn, "
    SELECT nohp 
    FROM datasiswa 
    WHERE nis = '$id'
    LIMIT 1
");

$data = mysqli_fetch_assoc($query);

if (!$data || empty($data['nohp'])) {
    die('Nomor tidak tersedia');
}

// bersihkan nomor
$nohp = preg_replace('/[^0-9]/', '', $data['nohp']);

// pastikan format 62
if (substr($nohp, 0, 1) === '0') {
    $nohp = '62' . substr($nohp, 1);
}

// redirect ke WhatsApp
header("Location: https://wa.me/$nohp");
exit;
