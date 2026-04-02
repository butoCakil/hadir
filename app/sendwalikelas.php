<?php
date_default_timezone_set('Asia/Jakarta');
$tanggal = date('Y-m-d');
$tanggal2 = date('d-m-Y');
$tahun = date('Y');
// ================= CONFIG MODE =================
$ujicoba = !empty($_GET['akses'] ?? null);

ini_set('max_execution_time', 0);
    
if ($ujicoba) {
    ignore_user_abort(true);
    echo "Mulai cron wali kelas...<br>";
    flush();
    ob_flush();
}

date_default_timezone_set('Asia/Jakarta');
$logFile = __DIR__ . "/cron_walikelas.log";

// ================= START LOG =================
$startTime = date('Y-m-d H:i:s');
file_put_contents($logFile, '', LOCK_EX);
file_put_contents($logFile, "=== CRON MULAI: {$startTime} ===\n", FILE_APPEND);

// ================= DEPENDENCY =================
include "../config/koneksi.php";
include "sendchat2.php";
$file = null;

// ================= HELPER =================
function hari_indonesia($tanggal)
{
    return [
        'Sun' => 'Min',
        'Mon' => 'Sen',
        'Tue' => 'Sel',
        'Wed' => 'Rab',
        'Thu' => 'Kam',
        'Fri' => 'Jum',
        'Sat' => 'Sab'
    ]
    [date('D', strtotime($tanggal))] ?? date('D', strtotime($tanggal));
}

// ================= RANGE TANGGAL =================
// ================= RANGE MINGGU SEBELUMNYA =================

// Tentukan hari ini
$today = date('Y-m-d');

// Cari Senin minggu ini
$seninMingguIni = date(
    'Y-m-d',
    strtotime('monday this week', strtotime($today))
);

// Minggu lalu: Senin minggu ini - 7 hari
$seninMingguLalu = date(
    'Y-m-d',
    strtotime('-7 days', strtotime($seninMingguIni))
);

// Minggu kemarin: 6 hari setelah Senin minggu lalu
$mingguKemarin = date(
    'Y-m-d',
    strtotime('+6 days', strtotime($seninMingguLalu))
);

// Generate tanggal
$tanggalList = [];
$hariList = [];

for ($i = 0; $i < 7; $i++) {
    $tgl = date(
        'Y-m-d',
        strtotime("+{$i} days", strtotime($seninMingguLalu))
    );
    $tanggalList[] = $tgl;
    $hariList[] = hari_indonesia($tgl);
}

// ================= LOOP WALI KELAS =================
$sqlWalikelas = "SELECT * FROM datawalikelas ORDER BY kelas ASC";
$resWalikelas = $conn->query($sqlWalikelas);

$ujiiii = 0;

$done = 0;

while ($wk = $resWalikelas->fetch_assoc()) {

    $kelas = $wk['kelas'];
    $sendmsg = "📅 *Rekap Presensi PKL Kelas {$kelas}*\n";
    $sendmsg .= "👩‍🏫 Wali Kelas: {$wk['nama']}\n";
    $sendmsg .= "🆔 NIP: " . ($wk['nip'] ?: '-') . "\n\n";

    $sendmsg .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    $sendmsg .= "```";
    $sendmsg .= "      " . implode(' ', $hariList) . "\n";
    $sendmsg .= "      " . implode('  ', array_map(fn($t) => date('d', strtotime($t)), $tanggalList)) . "\n";
    $sendmsg .= "```";
    $sendmsg .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

    // ================= DATA SISWA =================
    $sql = "
        SELECT 
            p.nama_dudika,
            p.nama_pembimbing,
            s.nama,
            s.nis,
            s.nohp
        FROM penempatan p
        JOIN datasiswa s ON s.nis = p.nis_siswa
        WHERE s.kelas = '{$kelas}'
        ORDER BY p.nama_dudika, p.nama_pembimbing, s.nama ASC
    ";

    $res = $conn->query($sql);

    $lastDudi = $lastPemb = "";
    $no = 1;

    while ($row = $res->fetch_assoc()) {
        
        if ($lastDudi !== $row['nama_dudika']) {
            $sendmsg .= "\n🏭 *{$row['nama_dudika']}*\n";
            $lastDudi = $row['nama_dudika'];
            $lastPemb = "";
        }
        
        $sendmsg .= "```";
        if ($lastPemb !== $row['nama_pembimbing']) {
            $sendmsg .= "👨‍🏫 Pembimbing: {$row['nama_pembimbing']}\n";
            $lastPemb = $row['nama_pembimbing'];
            $no = 1;
        }

        // ===== PRESENSI 7 HARI =====
        $status = [];
        foreach ($tanggalList as $tgl) {
            $q = "SELECT ket FROM presensi 
                  WHERE nis='{$row['nis']}' 
                  AND DATE(timestamp)='{$tgl}' LIMIT 1";
            $rp = $conn->query($q);

            if ($rp->num_rows) {
                $ket = strtolower($rp->fetch_assoc()['ket']);
                $status[] = match ($ket) {
                    'masuk' => '✅', 'izin' => '🔵', 'sakit' => '🟡', 'libur' => '🔴', default => '❌'
                };
            } else {
                $hn = hari_indonesia($tgl);
                $status[] = ($hn == 'Sab' || $hn == 'Min') ? '➖' : '❌';
            }
        }

        $nohp = trim($row['nohp'] ?? '');
        $iconWarning = $nohp === '' ? '⚠' : '';
        $sendmsg .= "   {$no}) {$row['nama']} $iconWarning\n";

        $nohpDisplay = $nohp !== ''
            ? $nohp
            : 'BELUM TERDAFTAR';

        $sendmsg .= "      ({$row['nis']} | {$nohpDisplay})\n";

        $sendmsg .= "      " . implode(' ', $status) . "\n";

        $sendmsg .= "```";
        $no++;
    }

    // ================= FOOTER =================
    $sendmsg .= "\nKeterangan:\n";
    $sendmsg .= "✅ Masuk\n";
    $sendmsg .= "🔵 Izin\n";
    $sendmsg .= "🟡 Sakit\n";
    $sendmsg .= "🔴 Libur\n";
    $sendmsg .= "➖ Weekend\n";
    $sendmsg .= "❌ Tidak Presensi\n";
    $sendmsg .= "⚠ No HP Belum Terdaftar\n";
    $sendmsg .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    $sendmsg .= "📝 _Sistem Presensi PKL_ *SMK Negeri Bansari*\n©️ $tahun";

    // ================= SEND =================
    $number = $wk['nohp'];

    if ($ujicoba) {
        echo "[UJICOBA] {$wk['nama']} ({$number})<br>";
        echo nl2br(($sendmsg)) . "<br><br>";

        if ($ujiiii == 0) {
            sendMessage("6282241863393", $sendmsg, $file);
            $ujiiii++;
        }
    } else {
        sendMessage($number, $sendmsg, $file);
    }

    file_put_contents(
        $logFile,
        date("Y-m-d H:i:s") . " - {$wk['nama']} ({$kelas}) terkirim\n",
        FILE_APPEND
    );

    sleep(10);

    $sendmsg = "*📌 Layanan Presensi PKL untuk Walikelas*\n\n";
    $sendmsg .= "Berikut perintah yang tersedia, balas pesan ini dengan ketik:\n\n";
    $sendmsg .= "1️⃣ `cek <NIS/NoHP>`\n    ➜ Lihat data siswa.\n    Contoh: `cek 1234` atau `cek 089123456789`\n\n";
    $sendmsg .= "2️⃣ `cek rekap`\n    ➜ Lihat rekap *Kelas*, *Pembimbing*, atau *DUDI*.\n\n";
    $sendmsg .= "3️⃣ `cek presensi <NIS/NoHP>`\n    ➜ Lihat Presensi individu hari ini.\n    Contoh: `cek presensi 1234`\n\n";
    $sendmsg .= "4️⃣ `cek rekap <NIS/NoHP>`\n    ➜ Lihat Rekap semua presensi individu.\n    Contoh: `cek rekap 1234`\n\n";
    $sendmsg .= "5️⃣ `cek rekap <KELAS>`\n    ➜ Rekap presensi per kelas.\n    Contoh: `cek rekap xiat1`\n\n";
    $sendmsg .= "💡 *Tips*: Gunakan huruf kecil tanpa spasi untuk kode kelas.\n\n";
    $sendmsg .= "Selengkapnya ada di link berikut ini:\nhttps://pklbos.smknbansari.sch.id/?akses=presensi\n\n";
    $sendmsg .= "📢 Ada data yang salah? Beri tahu Admin ➜ Balas dengan ketik `Admin`.\n\n";
    $sendmsg .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    $sendmsg .= "📝 _Sistem Presensi PKL_ *SMK Negeri Bansari*\n©️ 2025";

    if ($ujicoba) {
        echo "[UJICOBA] {$wk['nama']} ({$number})<br>";
        if ($ujiiii == 1) {
            sendMessage("6282241863393", $sendmsg, $file);
            $ujiiii++;
        }
    } else {
        sendMessage($number, $sendmsg, $file);
    }

    sleep(20);
}

// ================= END CRON =================
$endTime = date('Y-m-d H:i:s');
$dur = strtotime($endTime) - strtotime($startTime);
file_put_contents(
    $logFile,
    "=== SELESAI {$endTime} | Durasi {$dur} detik ===\n\n",
    FILE_APPEND
);
