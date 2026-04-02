<?php
$ujicoba = false;
// $ujicoba = true;
$ujicoba = !empty($_GET['akses'] ?? null);

if ($ujicoba) {
    ini_set('max_execution_time', 0); // 0 = unlimited
    ignore_user_abort(true);
    echo "Mulai cron UJI COBA...<br>";
    flush();
    ob_flush();
}

date_default_timezone_set("Asia/Jakarta");
include "../config/koneksi.php";
include "sendchat2.php";
$logFile = __DIR__ . "/cronHarian.log";
$file = null;
$numberAdmin = "6282241863393";

function tulis_log($pesan_log, $baru = false)
{
    global $logFile;
    $pesan_log .= "\n";

    if ($baru)
        file_put_contents($logFile, ($pesan_log));
    else
        file_put_contents($logFile, $pesan_log, FILE_APPEND);
}

// ====== FUNGSI KONFIRMASI HARI LIBUR ======
/**
 * Catat hari libur untuk siswa.
 * 
 * @param mysqli $conn   Koneksi database
 * @param string $nis    NIS siswa
 * @param string $nama   Nama siswa
 * @param string $kelas  Kelas siswa
 * @param bool   $ujicoba Mode uji coba (default false)
 * 
 * @return string|null
 *   - string : pesan yang siap dikirim ke user
 *   - null   : tidak ada pesan (sudah ada catatan / uji coba)
 */
function catatLibur($conn, $nis, $nama, $kelas)
{
    global $ujicoba;

    if (!$conn) {
        return "❗ Koneksi database gagal.";
    }

    // Kalau mode uji coba, jangan insert ke DB
    if ($ujicoba) {
        return null;
    }

    $tgl_hari_ini = date("Y-m-d");

    // ✅ Cek apakah sudah ada catatan libur/hadir untuk hari ini
    $cek = $conn->prepare("SELECT ket FROM presensi WHERE nis=? AND DATE(timestamp)=?");
    $cek->bind_param("ss", $nis, $tgl_hari_ini);
    $cek->execute();
    $result = $cek->get_result();

    if ($result->num_rows > 0) {
        $cek->close();
        return null;
    }
    $cek->close();

    // Insert catatan libur
    $stmt = $conn->prepare("INSERT INTO presensi (nis, ket, timestamp) VALUES (?, 'libur', NOW())");
    if (!$stmt) {
        return "❗ Gagal menyiapkan query: " . $conn->error;
    }

    $stmt->bind_param("s", $nis);
    if ($stmt->execute()) {
        $sendmsg = "✅ Hai $nama ($kelas), Menurut analisis sistem, hari ini ($tgl_hari_ini) kamu dicatat sebagai *libur*.\n\nAbaikan pesan jika benar libur, hubungi admin jika tidak.";
    } else {
        $sendmsg = "❗ Gagal mencatat hari libur: " . $stmt->error;
    }

    $stmt->close();

    $logLine = "-> " . date("Y-m-d H:i:s") . "== Sudah dicatat Libur otomatis: - [{$nama}]";
    tulis_log($logLine);
    return $sendmsg;
}

// ===== AWAL CRON =====
// Kosongkan file log lalu tulis waktu mulai
$startTime = date('Y-m-d H:i:s');
tulis_log("=== CRON MULAI: {$startTime} ===");

// Kirim pesan ke admin
$logFileUrl = "https://hadir.masbendz.com/app/cronHarian.log"; // URL akses log
$sendmsg = "📌 Cron job pengingat presensi PKL dimulai.\n"
    . "🕒 Mulai: $startTime\n"
    . "📄 Log: $logFileUrl";

// Fungsi kirim pesan
sendMessage($numberAdmin, $sendmsg, $file);

function hari_indonesia($tanggal)
{
    $hariInggris = date('D', strtotime($tanggal));
    $namaHari = [
        'Sun' => 'Minggu',
        'Mon' => 'Senin',
        'Tue' => 'Selasa',
        'Wed' => 'Rabu',
        'Thu' => 'Kamis',
        'Fri' => 'Jumat',
        'Sat' => 'Sabtu'
    ];
    return isset($namaHari[$hariInggris]) ? $namaHari[$hariInggris] : $hariInggris;
}

// Waktu target pengecekan
$jam_target = "12:34";
$jam_sekarang = date("H:i");

if ($jam_sekarang < $jam_target) {
    tulis_log("Belum waktunya cron cek presensi");
    exit("Belum waktunya cek.\n");
}

$pending_file = "tangguhan.json";
if (!file_exists($pending_file)) {
    file_put_contents($pending_file, json_encode([], JSON_PRETTY_PRINT));
}
$pending_data = json_decode(file_get_contents($pending_file), true) ?: [];

// Tanggal hari ini dan informasi minggu lalu
$tanggal_hari_ini = date("Y-m-d");
$hari_ini = hari_indonesia($tanggal_hari_ini); // Nama hari, misal Senin
$tanggal_minggu_lalu = date("Y-m-d", strtotime("-7 days"));

// Ambil semua siswa
$sql_siswa = "SELECT nis, nama, kelas, nohp FROM datasiswa";
$result_siswa = $conn->query($sql_siswa);

$pesan_kirim = [];
$nomerurut = 1;
while ($siswa = $result_siswa->fetch_assoc()) {
    $nis = $siswa['nis'];
    $nama = $siswa['nama'];
    $kelas = $siswa['kelas'];
    $nohp = $siswa['nohp'];

    if (!empty($nohp)) {
        // Cek presensi hari ini
        $cek_hari_ini = $conn->prepare("SELECT ket FROM presensi WHERE nis = ? AND DATE(timestamp) = ?");
        $cek_hari_ini->bind_param("ss", $nis, $tanggal_hari_ini);
        $cek_hari_ini->execute();
        $res_hari_ini = $cek_hari_ini->get_result();

        if ($res_hari_ini->num_rows == 0) {
            // Siswa belum presensi hari ini

            // --- Ambil status kemarin ---
            $tanggal_kemarin = date('Y-m-d', strtotime('-1 day'));
            $cek_kemarin = $conn->prepare("SELECT ket FROM presensi WHERE nis = ? AND DATE(timestamp) = ?");
            $cek_kemarin->bind_param("ss", $nis, $tanggal_kemarin);
            $cek_kemarin->execute();
            $res_kemarin = $cek_kemarin->get_result();
            $status_kemarin = strtolower((string) ($res_kemarin->fetch_assoc()['ket'] ?? ''));

            // --- Ambil status minggu lalu pada hari yang sama ---
            $cek_minggu_lalu = $conn->prepare("SELECT ket FROM presensi WHERE nis = ? AND DATE(timestamp) = ?");
            $cek_minggu_lalu->bind_param("ss", $nis, $tanggal_minggu_lalu);
            $cek_minggu_lalu->execute();
            $res_minggu_lalu = $cek_minggu_lalu->get_result();
            $status_minggu_lalu = strtolower((string) ($res_minggu_lalu->fetch_assoc()['ket'] ?? ''));

            // --- Ambil status 2 minggu lalu ---
            $tanggal_2minggu_lalu = date("Y-m-d", strtotime("-14 days"));
            $cek_2minggu_lalu = $conn->prepare("SELECT ket FROM presensi WHERE nis = ? AND DATE(timestamp) = ?");
            $cek_2minggu_lalu->bind_param("ss", $nis, $tanggal_2minggu_lalu);
            $cek_2minggu_lalu->execute();
            $res_2minggu_lalu = $cek_2minggu_lalu->get_result();
            $status_2minggu_lalu = strtolower((string) ($res_2minggu_lalu->fetch_assoc()['ket'] ?? ''));

            // --- Daftar status masuk ---
            $statusMasuk = ['masuk', 'izin', 'sakit'];

            // --- Tentukan pesan berdasarkan kombinasi kemarin & minggu lalu ---
            $sendmsg = '';
            // 1. Jika minggu lalu dan 2 minggu lalu = libur
            if ($status_minggu_lalu === 'libur' && $status_2minggu_lalu === 'libur') {
                $sendmsg = catatLibur($conn, $nis, $nama, $kelas);
            }

            // 2. Jika hari ini kosong, minggu lalu kosong, 2 minggu lalu kosong,
            // //    tetapi kemarin melakukan presensi
            else if (
                empty($status_minggu_lalu) &&
                empty($status_2minggu_lalu) &&
                $status_kemarin === 'masuk'
            ) {
                $sendmsg = catatLibur($conn, $nis, $nama, $kelas); // <-- ISI LIBUR DI SINI
            }

            // 3. Jika hari ini Sabtu/Minggu, dan minggu lalu serta 2 minggu lalu
            // //    tidak presensi atau libur
            else if (
                in_array(strtolower($hari_ini), ['sabtu', 'minggu']) &&
                (empty($status_minggu_lalu) || $status_minggu_lalu === 'libur') &&
                (empty($status_2minggu_lalu) || $status_2minggu_lalu === 'libur')
            ) {
                $sendmsg = catatLibur($conn, $nis, $nama, $kelas); // <-- ISI LIBUR DI SINI
            } else if (
                // 4. Jika HARI INI MINGGU + minggu lalu masuk/izin/sakit + 2 minggu lalu masuk/izin/sakit
                in_array(strtolower($hari_ini), ['sabtu', 'minggu']) &&
                in_array($status_minggu_lalu, $statusMasuk) &&
                in_array($status_2minggu_lalu, $statusMasuk)
            ) {
                $sendmsg = "📌 Hari ini kamu belum presensi? Biasanya hari Minggu kamu masuk. Atau hari ini libur?";
            } else {
                // kondisi lain
                if ($status_kemarin === 'masuk' && $status_minggu_lalu === 'masuk') {
                    $sendmsg = "📌 Sepertinya kamu belum presensi hari ini. Atau mungkin hari ini memang libur? 🤔";
                } elseif ($status_kemarin === 'sakit' && $status_minggu_lalu === 'masuk') {
                    $sendmsg = "😷 Kemarin kamu sakit. Masih belum fit atau hari ini memang libur?";
                } elseif ($status_kemarin === 'izin' && $status_minggu_lalu === 'masuk') {
                    $sendmsg = "📄 Kemarin kamu izin. Masih izin atau hari ini libur?";
                } elseif ($status_kemarin === 'libur' && $status_minggu_lalu === 'masuk') {
                    $sendmsg = "🌴 Kemarin libur. Hari ini masih libur juga?";
                } elseif ($status_minggu_lalu === 'libur') {
                    // Semua kondisi kalau minggu lalu libur
                    if ($status_kemarin === 'masuk') {
                        $sendmsg = "📅 Hari ini libur ya?";
                    } elseif ($status_kemarin === 'sakit') {
                        $sendmsg = "😷 Kemarin kamu sakit. Semoga cepat fit lagi ya! Hari ini libur kan?";
                    } elseif ($status_kemarin === 'izin') {
                        $sendmsg = "📄 Kemarin kamu izin. Hari ini libur kan?";
                    } elseif ($status_kemarin === 'libur') {
                        $sendmsg = "🌴 Kemarin libur, hari ini masih libur ya?";
                    } else {
                        $sendmsg = "📅 Hari ini libur ya?";
                    }
                } else {
                    // Selain masuk, libur, izin, sakit → anggap belum presensi
                    $sendmsg = "📌📌 Sepertinya kamu belum presensi hari ini. Atau mungkin hari ini memang libur? 🤔";
                }

                // Simpan ke JSON penangguhan
                $pending_data[$nohp] = [
                    "type" => "confirm_libur",
                    "nis" => $nis,
                    "nama" => $nama,
                    "kelas" => $kelas,
                    "waktu" => date("Y-m-d H:i:s")
                ];

                // Masukkan ke daftar pesan yang akan dikirim
                $pesan_kirim[] = [
                    'nohp' => $nohp,
                    'pesan' => $sendmsg
                ];

                $logLine = $nomerurut . ". " . date("Y-m-d H:i:s") . " - [{$nohp}] [{$nama}] - Terkirim";
                tulis_log($logLine);
            }

            $nomerurut++;
        }

    } else {
        echo "<pre>";
        echo "$nama ($kelas) ($nis) Tidak ada Nomor HP\n";
        echo "</pre>";

        $logLine = $nomerurut . ". " . date("Y-m-d H:i:s") . " - [TIDAK ADA] [{$nama}] - Tidak TerKirim";
        tulis_log($logLine);
    }
}

if ($ujicoba) {
    echo "<pre>";
    print_r($pesan_kirim);
    echo "</pre>";

    $logLine = date("Y-m-d H:i:s") . " - UJI COBA SELESAI - Tidak TerKirim";
    tulis_log($logLine);
    die;
}

// Simpan kembali JSON
file_put_contents($pending_file, json_encode($pending_data, JSON_PRETTY_PRINT));

$conn->close();

$akhirPesan = date('Y-m-d H:i:s');
$logLine = "=== Pengumpulan Pesan selesai: {$akhirPesan} ===";
tulis_log($logLine);

$duration = strtotime($akhirPesan) - strtotime($startTime);
$hours = floor($duration / 3600);
$minutes = floor(($duration % 3600) / 60);
$seconds = $duration % 60;

$durationFormatted = sprintf("%02d:%02d:%02d", $hours, $minutes, $seconds);

$logLine = "Durasi: {$durationFormatted} ({$duration} detik)";
tulis_log($logLine);

$mulaikirim = date('Y-m-d H:i:s');
$logLine = "=== Pengiriman Dimulai: {$mulaikirim} ===";
tulis_log($logLine);

// Path log file
$logFileUrl = "https://hadir.masbendz.com/app/cronHarian.log"; // URL akses log
// Kirim pesan ke admin
$sendmsg = "⏳ Cron job Belum Presensi Dimulai.\n"
    . "🕒 Mulai pengumpulan data dan pesan: $startTime\n"
    . "🕒 Selesai kumpul data dan pesan, mulai pengiriman: $akhirPesan\n"
    . "📄 Log: $logFileUrl";
$sendmsg .= "\n⏳ Durasi: {$durationFormatted} - {$duration} detik";

// Fungsi kirim pesan
sendMessage($numberAdmin, $sendmsg, null);

sleep(5);

// Kirim pesan satu per satu
$n = 1;
foreach ($pesan_kirim as $item) {
    $nohp = $item['nohp'];
    $pesan = $item['pesan'];


    // Kirim pesan
    if ($ujicoba) {
        echo "[UJICOBA] Tidak mengirim pesan {$pesan}<br>ke {$nohp}<br>";
        flush();
        ob_flush();
        $n++;
    } else {
        if (!empty($pesan)) {
            $pesan .= "\n━━━━━━━━━━━━━━━━━━━━\n";
            $pesan .= "✍️ *Balas pesan ini dengan salah satu pilihan:*\n";
            $pesan .= " `ya` – Jika *hari ini libur*\n";
            $pesan .= " `tidak` – Jika *masuk* tapi belum presensi\n";
            $pesan .= " `izin` – Jika *tidak masuk* karena *izin*\n";
            $pesan .= " `sakit` – Jika *tidak masuk* karena *sakit*\n";
            $pesan .= "\n\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n📝 _Sistem Presensi PKL_ *SMK Negeri Bansari*\n©️ ```2025```";
            sendMessage($nohp, $pesan, $file);
        }
    }

    sleep(15); // jeda antar pesan
}

// ===== AKHIR CRON =====
$endTime = date('Y-m-d H:i:s');
$duration = strtotime($endTime) - strtotime($startTime);

$hours = floor($duration / 3600);
$minutes = floor(($duration % 3600) / 60);
$seconds = $duration % 60;

$durationFormatted = sprintf("%02d:%02d:%02d", $hours, $minutes, $seconds);

$logLine = "=== Cron job selesai: {$endTime} ===\n";
$logLine .= "Durasi: {$durationFormatted} ({$duration} detik)\n";
$logLine .= "==============================\n";
tulis_log($logLine);

sleep(10);

// Path log file
$logFileUrl = "https://hadir.masbendz.com/app/cronHarian.log"; // URL akses log

// Kirim pesan ke admin
$sendmsg = "✅ Cron job pengingat presensi selesai.\n"
    . "🕒 Mulai: $startTime\n"
    . "🕒 Selesai: $endTime\n"
    . "📄 Log: $logFileUrl";
$sendmsg .= "\n⏳ Durasi: {$durationFormatted} - {$duration} detik";

// Fungsi kirim pesan
sendMessage($numberAdmin, $sendmsg, $logFileUrl);
