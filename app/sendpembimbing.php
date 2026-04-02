<?php
// echo "wkwkwkwkw"; die;
// $ujicoba = false;
// $ujicoba = true;
$ujicoba = !empty($_GET['akses'] ?? null);

ini_set('max_execution_time', 0); // 0 = unlimited
    
if ($ujicoba) {
    ignore_user_abort(true);
    echo "Mulai cron...<br>";
    flush();
    ob_flush();
}

date_default_timezone_set('Asia/Jakarta');
$logFile = __DIR__ . "/cron.log";

// ===== AWAL CRON =====
// Kosongkan file log lalu tulis waktu mulai
$startTime = date('Y-m-d H:i:s');
file_put_contents($logFile, '', LOCK_EX);
file_put_contents($logFile, "=== CRON MULAI: {$startTime} ===\n", FILE_APPEND);

include "../config/koneksi.php";
include "sendchat2.php";
$file = null;

function hari_indonesia($tanggal)
{
    $hariInggris = date('D', strtotime($tanggal));
    $namaHari = [
        'Sun' => 'Min',
        'Mon' => 'Sen',
        'Tue' => 'Sel',
        'Wed' => 'Rab',
        'Thu' => 'Kam',
        'Fri' => 'Jum',
        'Sat' => 'Sab'
    ];
    return isset($namaHari[$hariInggris]) ? $namaHari[$hariInggris] : $hariInggris;
}

// Ambil tanggal hari ini dan 6 hari ke belakang
$tanggalList = [];
$hariList = [];
for ($i = 0; $i < 7; $i++) {
    $tgl = date('Y-m-d', strtotime("-" . (6 - $i) . " days"));
    $tanggalList[] = $tgl;
    $hariList[] = hari_indonesia($tgl);
}

$sqlPembimbing = "SELECT * FROM datapembimbing ORDER BY nama ASC";
$resPembimbing = $conn->query($sqlPembimbing);

while ($pemb = $resPembimbing->fetch_assoc()) {
    $sendmsg = "📅 Rekap presensi Siswa PKL\n";
    $sendmsg .= "👨‍🏫 Pembimbing: {$pemb['nama']}\n";
    $pembimbingURL = rawurlencode($pemb['nama']);
    ;

    $nip = !empty($pemb['nip']) ? $pemb['nip'] : "-";

    $sendmsg .= "🆔 NIP: $nip\n\n";
    $sendmsg .= "📋 Daftar DUDI dan Siswa + Rekap Kehadiran 7 Hari:\n";
    $sendmsg .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    $sendmsg .= "      " . implode(' ', $hariList) . "\n";
    // $sendmsg .= "      " . implode('  ', array_map(fn($t) => date('d', strtotime($t)), $tanggalList)) . "  \n";
    $sendmsg .= "      " . implode('  ', array_map(function ($t) {
        return date('d', strtotime($t));
    }, $tanggalList)) . "  \n";

    $sendmsg .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

    // Ambil daftar DUDI untuk pembimbing ini
    $sqlPenempatan = "SELECT * FROM penempatan WHERE nama_pembimbing='{$pemb['nama']}' ORDER BY nama_dudika ASC";
    $resPenempatan = $conn->query($sqlPenempatan);

    $noDudi = 1;
    $lastDudi = "";
    $counterSiswa = 1;

    while ($pen = $resPenempatan->fetch_assoc()) {
        if ($lastDudi != $pen['nama_dudika']) {
            if ($lastDudi != "")
                $sendmsg .= ""; // pemisah antar DUDI
            $sendmsg .= $noDudi . ". " . $pen['nama_dudika'] . "\n";
            $lastDudi = $pen['nama_dudika'];
            $noDudi++;
            $counterSiswa = 1;
        }

        // Ambil data siswa dari datasiswa
        $nis = $pen['nis_siswa'];
        $sqlSiswa = "SELECT * FROM datasiswa WHERE nis='$nis' ORDER BY kelas ASC, nama ASC LIMIT 1";
        $resSiswa = $conn->query($sqlSiswa);
        $siswa = $resSiswa->fetch_assoc();

        // Ambil data presensi 7 hari terakhir
        $statusHarian = [];
        foreach ($tanggalList as $tgl) {
            $sqlPresensi = "SELECT ket FROM presensi WHERE nis='$nis' AND DATE(timestamp)='$tgl' LIMIT 1";
            $resPresensi = $conn->query($sqlPresensi);

            $hariNama = hari_indonesia($tgl);
            if ($resPresensi->num_rows > 0) {
                $row = $resPresensi->fetch_assoc();
                switch (strtolower($row['ket'])) {
                    case 'masuk':
                        $statusHarian[] = "✅";
                        break;
                    case 'izin':
                        $statusHarian[] = "🔵";
                        break;
                    case 'sakit':
                        $statusHarian[] = "🟡";
                        break;
                    case 'libur':
                        $statusHarian[] = "🔴";
                        break;
                    default:
                        $statusHarian[] = "❌";
                        break;
                }
            } else {
                if ($hariNama == 'Sab' || $hariNama == 'Min') {
                    $statusHarian[] = "➖"; // weekend default
                } else {
                    $statusHarian[] = "❌"; // hari biasa, tidak presensi
                }
            }
        }

        $nohp = isset($siswa['nohp']) ? $siswa['nohp'] : '-';

        $sendmsg .= "   {$counterSiswa}) {$siswa['nama']}\n";
        $sendmsg .= "      ({$siswa['kelas']} | {$siswa['nis']} | {$nohp})\n";
        $sendmsg .= "      " . implode(' ', $statusHarian) . " \n";

        $counterSiswa++;
    }

    $sendmsg .= "Keterangan:\n";
    $sendmsg .= "✅ = Masuk\n";
    $sendmsg .= "🔵 = Izin\n";
    $sendmsg .= "🟡 = Sakit\n";
    $sendmsg .= "🔴 = Libur\n";
    $sendmsg .= "➖ = Libur Weekend\n";
    $sendmsg .= "❌ = Tidak Presensi\n";
    $sendmsg .= "Selengkapnya ada di link berikut ini:\n";
    $sendmsg .= "https://pklbos.smknbansari.sch.id/?akses=presensi&pembimbing=$pembimbingURL\n";

    $sendmsg .= "\n\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n📝 _Sistem Presensi PKL_ *SMK Negeri Bansari*\n©️ ```2025```";

    // Kirim sendmsg ke nomor pembimbing
    $number = $pemb['nohp'];
    // echo "<pre>";
    // echo "Kirim ke {$number}:\n" . $sendmsg . "\n\n";
    // echo "</pre>";

    if ($ujicoba) {
        echo "[UJICOBA] Tidak mengirim pesan ke {$number}<br>";
    } else {
        sendMessage($number, $sendmsg, $file);
    }

    sleep(10);

    $sendmsg = "*📌 Layanan Presensi PKL untuk Pembimbing*\n\n";
    $sendmsg .= "Berikut perintah yang tersedia:\n\n";
    $sendmsg .= "1️⃣ `cek`\n    ➜ Lihat status nomor Anda.\n\n";
    $sendmsg .= "2️⃣ `cek <NIS/NoHP>`\n    ➜ Lihat data siswa.\n    Contoh: `cek 1234` atau `cek 089123456789`\n\n";
    $sendmsg .= "3️⃣ `cek rekap`\n    ➜ Lihat rekap *Kelas*, *Pembimbing*, atau *DUDI*.\n\n";
    $sendmsg .= "4️⃣ `cek presensi <NIS/NoHP>`\n    ➜ Presensi individu hari ini.\n    Contoh: `cek presensi 1234`\n\n";
    $sendmsg .= "5️⃣ `cek rekap <NIS/NoHP>`\n    ➜ Rekap semua presensi individu.\n    Contoh: `cek rekap 1234`\n\n";
    $sendmsg .= "6️⃣ `cek rekap <KELAS>`\n    ➜ Rekap presensi per kelas.\n    Contoh: `cek rekap xiat1`\n\n";
    $sendmsg .= "💡 *Tips*: Gunakan huruf kecil tanpa spasi untuk kode kelas.\n";
    $sendmsg .= "📢 Ada data yang salah? Beri tahu Admin ➜ Balas dengan ketik `Admin`.\n\n";
    $sendmsg .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    $sendmsg .= "📝 _Sistem Presensi PKL_ *SMK Negeri Bansari*\n©️ 2025";

    // echo "<pre>";
    // echo "Kirim ke {$number}:\n" . $sendmsg . "\n\n";
    // echo "</pre>";

    
    if ($ujicoba) {
        echo "Terkirim ke {$pemb['nama']} ({$number})<br>";
        flush();
        ob_flush();
    } else {
        sendMessage($number, $sendmsg, $file);
    }


    $logLine = date("Y-m-d H:i:s") . " - [Nomor: {$number}] [Nama: {$pemb['nama']}] - Terkirim\n";
    file_put_contents($logFile, $logLine, FILE_APPEND);

    sleep(20);
}

// ===== AKHIR CRON =====
$endTime = date('Y-m-d H:i:s');
$duration = strtotime($endTime) - strtotime($startTime);

$hours = floor($duration / 3600);
$minutes = floor(($duration % 3600) / 60);
$seconds = $duration % 60;

$durationFormatted = sprintf("%02d:%02d:%02d", $hours, $minutes, $seconds);

file_put_contents($logFile, "=== Cron job selesai: {$endTime} ===\n", FILE_APPEND);
file_put_contents($logFile, "Durasi: {$durationFormatted} ({$duration} detik)\n", FILE_APPEND);
file_put_contents($logFile, "==============================\n\n", FILE_APPEND);

sleep(10);

// Path log file
$logFileUrl = "https://hadir.masbendz.com/app/cron.log"; // URL akses log

// Kirim pesan ke admin
$number = "6282241863393"; // nomor admin
$sendmsg = "✅ Cron job rekap pembimbing PKL selesai.\n"
    . "🕒 Mulai: $startTime\n"
    . "🕒 Selesai: $endTime\n"
    . "📄 Log: $logFileUrl";
$sendmsg .= "\n⏳ Durasi: {$durationFormatted} - {$duration} detik";

// Fungsi kirim pesan
sendMessage($number, $sendmsg, $logFileUrl);
