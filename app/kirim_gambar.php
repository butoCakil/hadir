<?php
include "../config/koneksi.php";
include "sendchat2.php";

echo "<pre>";

// Format tanggal hari ini
$today = date("Ymd");             // contoh: 20250728
$tanggalDisplay = date("Y-m-d");  // contoh: 2025-07-28

// Ambil data pembimbing
$sql = "SELECT id, nama, nohp, ket FROM datapembimbing";
$result = mysqli_query($conn, $sql);

while ($row = mysqli_fetch_assoc($result)) {
    $id = $row['id'];
    $nama = $row['nama'];
    $nohp = $row['nohp'];
    $ket = $row['ket'];

    $waktu = date("H:i:s");

    if (empty($nohp) || $nohp == "-") {
        echo "$waktu - 🚫 Lewati $nama: tidak ada nohp<br>";
        continue;
    }

    if ($ket === $tanggalDisplay) {
        echo "$waktu - ✔️ Lewati $nama: sudah kirim hari ini<br>";
        continue;
    }

    $safeName = preg_replace('/[^A-Za-z0-9_\-]/', '', $nama);
    $localFile = "../img/screenshots/screenshot_{$safeName}_{$today}.jpg";

    if (!file_exists($localFile)) {
        echo "$waktu - 🚫 Lewati $nama: tidak ada file gambar<br>";
        continue;
    }

    $urlgambar = "https://hadir.masbendz.com/img/screenshots/screenshot_{$safeName}_{$today}.jpg";
    $message = "Rekap Presensi 7 hari sebelumnya sampai tanggal $tanggalDisplay";

    sendMessage($nohp, $message, $urlgambar);

    echo "$waktu - ✅ Dikirim ke: $nama ($nohp) dengan file $urlgambar<br>";

    $update = "UPDATE datapembimbing SET ket = '$tanggalDisplay' WHERE id = '$id'";
    mysqli_query($conn, $update);

    echo "$waktu - 👍🏻 Update selesai<br>";

    // Delay 10 detik sebelum kirim ke pembimbing berikutnya
    flush();
    ob_flush();
    sleep(5);
    // sendMessage($nohp, "💡Jika ada kesalahan Data, tolong kerjasamanya untuk konfirmasi ke Admin, Terimakasih 🙏🏻", null);
    sleep(5);
}

echo "</pre>";
echo "👍🏻👍🏻👍🏻 SELESAI";
?>