<?php
/* 
1. datasiswa (id, nis, nama, kelas, jur, lp, nohp, ket), 
2. presensi (id, nis, namasiswa, kelas, ket, catatan, link, statuslink, kode, timestamp),
3. datapembimbing (`id`, `nip`, `nama`, `kode`, `nohp`, `ket`)
4. `penempatan`(`id`, `nama_siswa`, `nis_siswa`, `kelas`, `nama_dudika`, `alamat_dudika`, `nomor_telepon_dudika`, `nama_pembimbing`)
*/

// echo "tes"; die;
date_default_timezone_set('Asia/Jakarta');
$tanggal = date('Y-m-d');
$tanggal2 = date('d-m-Y');
$tahun = date('Y');
$timestamp = date('Y-m-d H:i:s');
$waktusekarang = date('H:i:s');

// Nomor admin (format internasional)
$adminNumber = "6282241863393"; // nomor admin utama
$adminNumbers = ['082241863393']; // Daftar nomor admin

// Mulai proses

// header('Content-Type: application/json; charset=utf-8');
$data = json_decode(file_get_contents('php://input'), true);

// JSON
/*
{
  "pushName": "MasBen",
  "from": "082241863393",
  "to": "6287754446580",
  "message_type": "media",
  "message": "Masuk testing",
  "media": "https://api.whacenter.com/api/media?path=3D469D82AC3433185D1B19AF3FA85BFD.jpe",
  "is_group": false,
  "timestamp": "2025-07-21 08:27:56",
  "id_group": "",
  "source": "WHACENTER",
  "ad_reply": {
    "source_type": null,
    "source_id": null,
    "source_url": null
  }
}

{
    "pushName": "UP Teknik Elektronika",
    "from": "087754446580",
    "to": "082241863393",
    "message_type": "text",
    "message": "Halo",
    "media": "",
    "is_group": false,
    "timestamp": "2025-08-17 10:47:33",
    "id_group": "",
    "source": "WHACENTER",
    "ad_reply": {
        "source_type": null,
        "source_id": null,
        "source_url": null
    }
}

{
    "pushName":"UP Teknik Elektronika",
    "from":"087754446580",
    "to":"082241863393",
    "message_type":"live-location",
    "message":"-7.290894,110.072132",
    "media":"",
    "is_group":false,
    "timestamp":"2025-09-09 08:09:52",
    "id_group":"",
    "source":"WHACENTER",
    "ad_reply": {
        "source_type":null,
        "source_id":null,
        "source_url":null
    }
}
*/

include "sendchat2.php";

$timestamp = isset($data["timestamp"]) ? $data["timestamp"] : null;
$pushName = isset($data["pushName"]) ? $data["pushName"] : null;
$timestampWA = isset($data["timestamp"]) ? strtotime($data["timestamp"]) : time();
$number = isset($data["from"]) ? $data["from"] : null;
$message = isset($data["message"]) ? $data["message"] : null;
$url = isset($data["media"]) ? $data["media"] : null;
$message_type = $data["message_type"] ?? null;

// Hanya izinkan source WHACENTER
$source = isset($data["source"]) ? trim($data["source"]) : "";

if (strtoupper($source) !== "WHACENTER") {
    http_response_code(403);
    exit("Invalid source [$source]");
}

$file = null;
$sendmsg = null;

// $pushName = isset($data["name"]) ? $data["name"] : null;
// $timestampWA = time();
// $device = $data['device'] ?? null;
// $number = $data['sender'] ?? null;
// $message = $data['message'] ?? null;
// $member = $data['member'] ?? null;
// $name = $data['name'] ?? null;
// $location = $data['location'] ?? null;
// $url = $data['url'] ?? null;
// $message_type = $data["type"] ?? null;
// $filename = $data['filename'] ?? null;
// $extension = $data['extension'] ?? null;

include "../config/koneksi.php";

// Cek apakah $number cocok dengan kolom encryp
$stmt = $conn->prepare("SELECT nohp FROM datasiswa WHERE encryp = ? LIMIT 1");
$stmt->bind_param("s", $number);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    // Jika ditemukan, ganti $number dengan nohp sebenarnya
    $number = $row['nohp'];
}

$stmt->close();

// nomor → hanya angka, hapus karakter lain
$number = preg_replace('/[^0-9+]/', '', $number);
$number = normalisasi_nomor_62_ke_0($number);

// url → hanya biarkan URL aman, hapus karakter kontrol
$url = preg_replace('/[\x00-\x1F\x7F]/u', '', $url);
$url = filter_var($url, FILTER_SANITIZE_URL); // pastikan format URL aman

// Lokasi file JSON untuk mencatat nomor
$jsonFile = 'hubadmin.json';

// Tangani konfirmasi YA atau TIDAK
$pending_file = "tangguhan.json";

$fileLaporan = "https://hadir.masbendz.com/data/Format_Laporan_PKL.pdf";
// $fileLaporan = "https://hadir.masbendz.com/data/Format Laporan PKL kelas XII.jpeg";
$fileDokumentasi = "https://hadir.masbendz.com/data/Panduan Presensi PKL melalui WA.pdf";

//==================================
//simpan tmp untuk statistik
//==================================
// include "../config/koneksi.php";

$stmt = $conn->prepare("INSERT INTO tmp (number, msg, timestamp) VALUES (?, ?, ?)");
$stmt->bind_param("sss", $number, $message, $timestamp);
$stmt->execute();
$stmt->close();
$conn->close();

//==================================
// fungsi validasi koordinat
//==================================
function isValidCoordinate($coord)
{
    if (!preg_match('/^-?\d{1,2}\.\d+,-?\d{1,3}\.\d+$/', $coord)) {
        return false;
    }
    list($lat, $lon) = explode(",", $coord);
    return ($lat >= -90 && $lat <= 90) && ($lon >= -180 && $lon <= 180);
}

//==================================
// Fungsi cek nomor terdaftar
//==================================

function cekNomorTerdaftar($conn, $number)
{
    // Nomor dalam 2 format
    $nomorSiswa = normalisasi_nomor_62_ke_0($number);
    $nomorPembimbing = normalisasi_nomor_0_ke_62($number);

    $sql = "
        SELECT 'siswa' AS type, nis AS id, nama, kelas
        FROM datasiswa
        WHERE nohp = ?
        UNION
        SELECT 'pembimbing' AS type, nip AS id, nama, NULL AS kelas
        FROM datapembimbing
        WHERE nohp = ?
        LIMIT 1
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $nomorSiswa, $nomorPembimbing);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($row = $result->fetch_assoc()) {
        $dataNomorTerdaftar = [
            "type" => $row['type'],
            ($row['type'] === 'pembimbing' ? "nip" : "nis") => $row['id'],
            "nama" => $row['nama'],
        ];

        if ($row['type'] === 'siswa') {
            $dataNomorTerdaftar['kelas'] = $row['kelas'];
        }

        return $dataNomorTerdaftar;
    }

    $stmt->close();
    return null; // jika tidak ditemukan
}

//==================================
// Fungsi untuk membaca data dari file JSON
//==================================

function readJsonFile($file)
{
    if (!file_exists($file)) {
        // Jika file tidak ada, buat file kosong
        file_put_contents($file, json_encode([], JSON_PRETTY_PRINT));
        return [];
    }
    $jsonData = file_get_contents($file);
    return json_decode($jsonData, true);
}

// Fungsi untuk menulis data ke file JSON
function writeJsonFile($file, $data)
{
    file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT));
}

// Fungsi untuk menghapus nomor dari JSON
function deleteFromJsonFile($file, $number)
{
    $data = readJsonFile($file);
    if (isset($data[$number])) {
        unset($data[$number]); // Hapus nomor dari data
        writeJsonFile($file, $data); // Simpan perubahan ke file
        return true; // Berhasil dihapus
    }
    return false; // Nomor tidak ditemukan
}

function generateRandomCode($length = 6)
{
    $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $randomCode = '';
    for ($i = 0; $i < $length; $i++) {
        $randomCode .= $characters[random_int(0, strlen($characters) - 1)];
    }
    return $randomCode;
}

function normalizeTanggal($input)
{
    $input = strtolower(trim($input)); // pastikan seluruh input huruf kecil

    // Mapping nama bulan Indonesia ke angka
    $bulanMap = [
        'januari' => '01',
        'februari' => '02',
        'maret' => '03',
        'april' => '04',
        'mei' => '05',
        'juni' => '06',
        'juli' => '07',
        'agustus' => '08',
        'september' => '09',
        'oktober' => '10',
        'november' => '11',
        'desember' => '12'
    ];

    // Ganti koma, slash, dan strip jadi spasi
    $input = str_replace([",", "/", "-", "."], " ", $input);
    $parts = preg_split("/\s+/", $input);

    if (count($parts) >= 2) {
        // Format: DD MM YYYY atau DD <nama bulan> YYYY
        $day = str_pad($parts[0], 2, "0", STR_PAD_LEFT);
        $bulanInput = strtolower($parts[1]); // <-- ubah ke lowercase
        $year = strlen($parts[2]) === 2 ? "20" . $parts[2] : $parts[2];

        // Cek apakah bulan berupa angka atau teks
        if (is_numeric($bulanInput)) {
            $month = str_pad($bulanInput, 2, "0", STR_PAD_LEFT);
        } else {
            $month = $bulanMap[$bulanInput] ?? null;
        }

        if ($month && checkdate((int) $month, (int) $day, (int) $year)) {
            return "$year-$month-$day";
        }
    }

    return false; // Format tidak dikenali
}

function formatTanggalIndo($tanggalString)
{
    $timestamp = strtotime($tanggalString); // Pastikan format: YYYY-MM-DD

    // Array nama hari dan bulan dalam bahasa Indonesia
    $namaHari = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
    $namaBulan = [
        1 => 'Januari',
        'Februari',
        'Maret',
        'April',
        'Mei',
        'Juni',
        'Juli',
        'Agustus',
        'September',
        'Oktober',
        'November',
        'Desember'
    ];

    $hari = $namaHari[date('w', $timestamp)];
    $tanggal = date('j', $timestamp);
    $bulan = $namaBulan[(int) date('n', $timestamp)];
    $tahun = date('Y', $timestamp);

    return "$hari, $tanggal $bulan $tahun";
}

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

function hari_indonesia2($tanggal)
{
    $hariInggris = date('D', strtotime($tanggal));
    $namaHari = [
        'Sun' => 'Mi',
        'Mon' => 'Sn',
        'Tue' => 'Sl',
        'Wed' => 'Ra',
        'Thu' => 'Ka',
        'Fri' => 'Ju',
        'Sat' => 'Sb'
    ];
    return isset($namaHari[$hariInggris]) ? $namaHari[$hariInggris] : $hariInggris;
}

function typo($message)
{
    // Peta tetangga keyboard QWERTY (sederhana, bisa diperluas)
    $keyboardMap = [
        'q' => ['w', 'a'],
        'w' => ['q', 'e', 's', 'a'],
        'e' => ['w', 'r', 'd', 's'],
        'r' => ['e', 't', 'f', 'd'],
        't' => ['r', 'y', 'g', 'f'],
        'y' => ['t', 'u', 'h', 'g'],
        'u' => ['y', 'i', 'j', 'h'],
        'i' => ['u', 'o', 'k', 'j'],
        'o' => ['i', 'p', 'l', 'k', 'u'],
        'p' => ['o', 'p'],
        'a' => ['q', 's', 'z', 'w'],
        's' => ['a', 'd', 'w', 'x', 'e', 'z'],
        'd' => ['s', 'f', 'e', 'c', 'x', 'r'],
        'f' => ['d', 'g', 'r', 'v', 't'],
        'g' => ['f', 'h', 't', 'b', 'v', 'y'],
        'h' => ['g', 'j', 'y', 'n', 'b', 'u'],
        'j' => ['h', 'k', 'u', 'm', 'i'],
        'k' => ['j', 'l', 'i', 'm', 'o'],
        'l' => ['k', 'o', 'p'],
        'z' => ['a', 'x', 's'],
        'x' => ['z', 'c', 'd'],
        'c' => ['x', 'v', 'f', 'd', 'g'],
        'v' => ['c', 'b', 'g', 'f', 'h'],
        'b' => ['v', 'n', 'h', 'g', 'j'],
        'n' => ['b', 'm', 'h', 'j', 'k'],
        'm' => ['n', 'j', 'k', 'l'],
    ];

    // Daftar kata yang valid
    $validWords = ["reg", "info", "masuk", "izin", "batal", "ya", "tidak", "help", "admin", "batal", "lupa", "balas", "cari", "jurnal", "input", "cek", "rekap", "unreg"];

    // Bersihkan tanda baca
    $cleanMessage = preg_replace('/[^a-zA-Z0-9 ]/', '', $message);
    $firstWord = strtolower(explode(" ", trim($cleanMessage))[0]);

    if (in_array($firstWord, $validWords, true)) {
        return null;
    }

    foreach ($validWords as $word) {
        $typos = [];

        // 1. Typo dari salah tekan keyboard (QWERTY adjacency)
        for ($i = 0; $i < strlen($word); $i++) {
            $char = $word[$i];
            if (isset($keyboardMap[$char])) {
                foreach ($keyboardMap[$char] as $neighbor) {
                    $typos[] = substr($word, 0, $i) . $neighbor . substr($word, $i + 1);
                }
            }
        }

        // 2. Typo dari huruf dobel
        for ($i = 0; $i < strlen($word); $i++) {
            $typos[] = substr($word, 0, $i + 1) . $word[$i] . substr($word, $i + 1); // huruf dobel
        }

        // 3. Typo dari huruf hilang
        for ($i = 0; $i < strlen($word); $i++) {
            $typos[] = substr($word, 0, $i) . substr($word, $i + 1); // huruf hilang
        }

        // Cek apakah kata yang diketik termasuk typo
        if (in_array($firstWord, $typos)) {
            return "`$message` sepertinya salah tulis.\nMungkin seharusnya: *$word*\n\nCoba ulangi kirim pesan dengan ejaan yang benar.";
        }
    }

    return null;
}

function normalisasi_nomor_0_ke_62($nomor)
{
    $nomor = preg_replace('/[^0-9]/', '', $nomor); // Hanya angka
    if (substr($nomor, 0, 1) == '0') {
        $nomor = '62' . substr($nomor, 1);
    }
    return $nomor;
}

function normalisasi_nomor_62_ke_0($nomor)
{
    // Hilangkan semua karakter selain angka
    $nomor = preg_replace('/[^0-9]/', '', $nomor);
    // Jika diawali dengan 62, ganti dengan 0
    if (substr($nomor, 0, 2) == '62') {
        $nomor = '0' . substr($nomor, 2);
    }

    return $nomor;
}


//==================================
// Mengecek apakah nomor sedang dalam masa penangguhan Presensi
//==================================

$penangguhanPresensi = "presensi_tmp/{$number}.json";
$penangguhanTimeout = 3600; // ⏱ 1 jam (dalam detik)

// 🔹 Cek apakah ada file penangguhan dan apakah sudah kedaluwarsa
if (file_exists($penangguhanPresensi)) {
    $pendingData = json_decode(file_get_contents($penangguhanPresensi), true) ?: [];

    // Jika file punya timestamp dan sudah lewat batas waktu → hapus
    if (isset($pendingData['timestamp']) && (time() - $pendingData['timestamp']) > $penangguhanTimeout) {
        unlink($penangguhanPresensi);
        sendMessage($number, "⚠️ Penangguhan presensi telah *kedaluwarsa* karena lebih dari 1 jam.\n\nSilakan ulangi presensi dari awal.", null);
        return; // Stop supaya tidak lanjut proses
    }
}

// 🔹 Penanganan balasan penangguhan
if (file_exists($penangguhanPresensi)) {
    $pendingData = json_decode(file_get_contents($penangguhanPresensi), true) ?: [];

    if (isset($pendingData['type']) && $pendingData['type'] === "pending_presensi") {
        $status = $pendingData['status'];
        $catatan = $pendingData['catatan'];
        $nis = $pendingData['nis'];
        $namasiswa = $pendingData['namasiswa'];
        $kelas = $pendingData['kelas'];
        $genKode = generateRandomCode();

        include "../config/koneksi.php";

        // Periksa apakah NIS sudah melakukan presensi pada tanggal tertentu
        $stmt = $conn->prepare("SELECT timestamp FROM presensi WHERE nis = ? AND DATE(timestamp) = ?");
        $stmt->bind_param("ss", $nis, $tanggal); // 's' untuk string
        $stmt->execute();
        $stmt->bind_result($timestamp);

        // Ambil hasil query
        if ($stmt->fetch()) {
            // Format timestamp menjadi tanggal dan waktu
            $datetime = new DateTime($timestamp);
            $formattedDate = $datetime->format('Y-m-d'); // Format: YYYY-MM-DD
            $formattedTime = $datetime->format('H:i:s');

            // Bandingkan dengan tanggal hari ini
            $tanggal = date('Y-m-d');
            if ($formattedDate === $tanggal) {
                $formattedDate = "Hari ini";
            } else {
                // Jika bukan hari ini, ubah format ke tampilan d-m-Y
                $tanggalFormatted = formatTanggalIndo($tanggal);
                $formattedDate = "Hari/Tanggal:\n" . $tanggalFormatted;
            }

            if (in_array($status, ["masuk", "izin", "sakit", "libur"]) || (!empty($url))) {
                // Tampilkan informasi presensi
                $sendmsg = "✅ Hai $namasiswa ($nis - $kelas),\n\nPresensimu untuk $formattedDate pada pukul $formattedTime *sudah tercatat* sebelumnya.\n\nJadi, tidak perlu presensi ulang ya. Terima kasih! 🙌\n\n";

                unlink($penangguhanPresensi);
            }
        } else {
            // 📌 Kasus 1: Pending minta foto → user kirim foto
            if (empty($pendingData['foto']) && !empty($url)) {
                $pendingData['foto'] = $url;
                file_put_contents($penangguhanPresensi, json_encode($pendingData, JSON_PRETTY_PRINT));

                // Kalau status+catatan sudah ada → langsung proses
                if (!empty($status)) {
                    $stmt = $conn->prepare("INSERT INTO presensi (nis, ket, catatan, namasiswa, kelas, link, kode) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $stmt->bind_param("sssssss", $nis, $status, $catatan, $namasiswa, $kelas, $url, $genKode);

                    if ($stmt->execute()) {
                        $tanggalIndo = formatTanggalIndo(date('Y-m-d'));
                        $jamSaja = date('H:i:s');
                        $sendmsg = "```";
                        $sendmsg .= "✅ Presensi Berhasil\n\n";
                        $sendmsg .= "🗓️ Status   : $status\n";
                        $sendmsg .= "📝 Catatan  : $catatan\n";
                        $sendmsg .= "👤 Nama     : $namasiswa\n";
                        $sendmsg .= "🏫 Kelas    : $kelas\n\n";
                        $sendmsg .= "⏰ Waktu    : $tanggalIndo\nPukul $jamSaja";
                        $sendmsg .= "```";

                        unlink($penangguhanPresensi);
                    }
                } else {
                    $sendmsg = "📄 Foto sudah diterima.\nSekarang silakan kirim keterangan presensi beserta catatannya.";
                }
            }

            // 📌 Kasus 2: Pending minta keterangan → user kirim teks status
            else if (!empty($pendingData['foto']) && empty($status) && !empty($message)) {
                $message_parts = explode(" ", $message, 2);
                $status = strtolower($message_parts[0]);
                $status = preg_replace("/[^a-z]/", "", $status);
                $catatan = isset($message_parts[1]) ? trim($message_parts[1]) : "";

                if (in_array($status, ["masuk", "izin", "sakit", "libur"])) {
                    include "../config/koneksi.php";
                    $stmt = $conn->prepare("INSERT INTO presensi (nis, ket, catatan, namasiswa, kelas, link, kode) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $stmt->bind_param("sssssss", $nis, $status, $catatan, $namasiswa, $kelas, $pendingData['foto'], $genKode);

                    if ($stmt->execute()) {
                        $tanggalIndo = formatTanggalIndo(date('Y-m-d'));
                        $jamSaja = date('H:i:s');
                        $sendmsg = "```";
                        $sendmsg .= "✅ Presensi Berhasil\n\n";
                        $sendmsg .= "🗓️ Status   : $status\n";
                        $sendmsg .= "📝 Catatan  : $catatan\n";
                        $sendmsg .= "👤 Nama     : $namasiswa\n";
                        $sendmsg .= "🏫 Kelas    : $kelas\n\n";
                        $sendmsg .= "⏰ Waktu    : $tanggalIndo\nPukul $jamSaja";
                        $sendmsg .= "```";

                        unlink($penangguhanPresensi);
                    }
                } else {
                    $sendmsg = typo($message);

                    if (empty($sendmsg))
                        $sendmsg = "🚫 *Keterangan presensi* `$status` *tidak valid!*\n\n📌 Pastikan ejaan *benar, sesuai, dan persis* dengan salah satu kata berikut:\n- `masuk`\n- `izin`\n- `sakit`\n- `libur`\n\n⚠️ Harus *diawali* dengan salah satu kata di atas, kemudian diikuti *spasi* dan keterangan kegiatan. \n*Tidak boleh* ada tanda baca di sekitar kata pertama.\n\n📝 *Contoh yang benar:*\n- `Masuk Memasang instalasi listrik`\n- `Izin Pergi ke acara keluarga`";
                }
            }
        }

        $stmt->close();
        $conn->close();

        if ($sendmsg !== null) {
            $sendmsg .= "📊 Lihat rekap presensi kamu, bisa balas dengan ketik `rekap` atau ketik `6` atau klik link ini:\n";
            $sendmsg .= "https://pklbos.smknbansari.sch.id/?akses=detail&nis=$nis\n\n";
            $sendmsg .= "ℹ️ Fitur *Lupa Absen* sudah aktif.\nBalas dengan ketik `2` untuk petunjuk penggunaannya.\n\n";
            $sendmsg .= "ℹ️ Fitur *Batal Absen / Hapus Absen* sudah aktif.\nBalas dengan ketik `batal` untuk petunjuk penggunaannya.";
            $sendmsg .= "\n\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n📝 _Sistem Presensi PKL_ *SMK Negeri Bansari*\n©️ ```$tahun```";
            sendMessage($number, $sendmsg, $file);
            return;
        }
    }
}

//==================================
// Mengecek apakah nomor sedang dalam masa penangguhan rekap
//==================================
$penangguhanFile = "rekap_tmp/{$number}.json";

if (file_exists($penangguhanFile)) {
    $penangguhanContent = file_get_contents($penangguhanFile);
    $penangguhanData = json_decode($penangguhanContent, true);

    // Validasi format JSON
    if (json_last_error() === JSON_ERROR_NONE && is_array($penangguhanData) && isset($penangguhanData['step'], $penangguhanData['menu'])) {
        if ($penangguhanData['menu'] === 'rekap') {
            $message = 'cek rekap ' . trim($message);  // Menambahkan input pengguna
        }
    }
}

$delhubadmin = false;
$menuadmin = false;
$tangguhan = false;

// Cek apakah formatnya "set <NIS> <NoHP>"
if (preg_match('/^set\s+(\d{4})\s+([\d\s\+\-]+)/i', strtolower($message), $matches)) {

    include "../config/koneksi.php";

    $nis = trim($matches[1]);
    $nohp_input = trim($matches[2]);

    // Normalisasi nomor HP
    $nohp = preg_replace('/[\s\-]/', '', $nohp_input); // hilangkan spasi & strip
    if (strpos($nohp, '+62') === 0) {
        $nohp = '0' . substr($nohp, 3);
    } elseif (strpos($nohp, '62') === 0) {
        $nohp = '0' . substr($nohp, 2);
    }

    // Validasi format nomor HP
    if (!preg_match('/^08\d{7,12}$/', $nohp)) {
        $sendmsg = "❌ Format nomor HP tidak valid. Gunakan contoh: set 1234 08812345678";
        $adminMessage .= "$nono ~ $pushName:\n";
        $adminMessage .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        $adminMessage .= "$message\n";
        $adminMessage .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        $adminMessage .= "Error: $sendmsg\n";
        $adminMessage .= "SET";
        sendMessage($adminNumber, $adminMessage, $file);
    }

    // Periksa apakah NIS ada di database
    $stmt = $conn->prepare("SELECT nama, kelas, jur, nohp FROM datasiswa WHERE nis = ?");
    $stmt->bind_param("s", $nis);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        $sendmsg = "⚠️ NIS *$nis* tidak ditemukan dalam database.";
        $adminMessage .= "$nono ~ $pushName:\n";
        $adminMessage .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        $adminMessage .= "$message\n";
        $adminMessage .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        $adminMessage .= "Error: $sendmsg\n";
        $adminMessage .= "SET";
        sendMessage($adminNumber, $adminMessage, $file);
    } else {

        $row = $result->fetch_assoc();

        // Update nohp dan encryp berdasarkan NIS
        $update = $conn->prepare("UPDATE datasiswa SET nohp = ?, encryp = ? WHERE nis = ?");
        $update->bind_param("sss", $nohp, $number, $nis);

        if ($update->execute()) {
            $nama = $row["nama"];
            $kelas = $row["kelas"];
            $jur = $row["jur"];

            $sendmsg = "✅ Data berhasil diperbaiki!\n\nNama: *$nama*\nKelas: *$kelas*\nJurusan: *$jur*\nNo. HP: *$nohp*\n\nSilakan bisa mengulangi presensi sebelumnya atau melakukan presensi.";
            $number = $nohp;
        } else {
            $sendmsg = "❌ Gagal menyimpan data. Coba lagi nanti.";
            $adminMessage .= "$nono ~ $pushName:\n";
            $adminMessage .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
            $adminMessage .= "$message\n";
            $adminMessage .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
            $adminMessage .= "Error: $sendmsg\n";
            $adminMessage .= "SET";
            sendMessage($adminNumber, $adminMessage, $file);
        }

        $update->close();
    }

    $stmt->close();
} elseif (strtolower($message) === "p") {
    $sendmsg_variants = [
        "Balas dengan ketik `info`.\nUntuk mendapatkan informasi Layanan Presensi.",
        "📌 Untuk melihat panduan layanan presensi, balas dengan ketik `info`.\n\n🧾 Informasi lengkap tersedia dalam menu tersebut.",
        "Ketik `info` untuk melihat petunjuk layanan presensi.\n\n⏳ Sistem hanya merespon perintah yang tersedia.",
        "Hai! 😊 Butuh panduan layanan?\nBalas dengan ketik `info` untuk mulai.",
        "Pesan Anda belum sesuai format.\n\n📖 Untuk memulai, silakan balas dengan `info` untuk melihat petunjuk layanan.",
        "Silakan balas dengan ketik `info` untuk melihat informasi dan panduan penggunaan layanan presensi.",
    ];

    // Ambil satu pesan acak dari variasi yang tersedia
    $sendmsg = $sendmsg_variants[array_rand($sendmsg_variants)];

} elseif (strtolower($message) === "tes") {
    $variations = [
        "✅ testing OK",
        "✅ Tes berhasil. Sistem aktif!",
        "📶 Koneksi aman, presensi siap digunakan.",
        "🆗 Sistem merespon. Lanjutkan penggunaan.",
        "✅ Tes OK. Silakan kirim presensi seperti biasa.",
        "🚀 Sistem online! Tes berhasil.",
        "👍 Respon diterima. Sistem siap melayani.",
        "✅ Sistem berjalan normal.",
        "🟢 Tes sukses. Tidak ada gangguan.",
        "👌 Terhubung ke sistem. Lanjutkan kegiatanmu.",
        "✅ Tes diterima. Presensi bisa dilakukan sekarang."
    ];

    // Pilih balasan secara acak
    $sendmsg = $variations[array_rand($variations)];
} elseif (strtolower($message) === "info" || strtolower($message) === "menu") {
    $menus = [];
    $menus[] = "📋 *Layanan Presensi PKL SMK Negeri Bansari - Tahun $tahun*\n\nBerikut pilihan menu yang tersedia:\n\n1⃣ Panduan Presensi  \n2⃣ Lupa Presensi *(SUDAH AKTIF)*  \n3⃣ Daftar / Ganti Nomor WhatsApp  \n4⃣ Cek Status Nomor  \n5⃣ Hapus / Batal / Ganti Nomor  \n6⃣ Rekap Presensi  \n7⃣ Hubungi Admin  \n8⃣ *Panduan Laporan PKL*  \n\n🔁 *Balas dengan ketik angka sesuai menu yang dipilih.*  \nContoh: ketik `3` untuk daftar atau ganti nomor.";
    $menus[] = "Hai! 👋  \nIni dia layanan Presensi PKL SMKN Bansari $tahun:\n\n1. 📖 Cara Presensi  \n2. 🕒 Lupa Presensi  \n3. 📱 Daftar/Ganti Nomor  \n4. 🔍 Cek Status  \n5. ❌ Hapus Nomor  \n6. 📊 Rekap Presensi  \n7. 📞 Admin  \n8⃣ *Panduan Laporan PKL*  \n\n👉 *Ketik angka yang kamu pilih, misalnya `1` untuk cara presensi.*";
    $menus[] = "🗂 *Sistem Layanan Presensi PKL SMKN Bansari - $tahun*\n\nSilakan pilih layanan berikut:\n\n1⃣ Langkah-langkah Presensi  \n2⃣ Fitur Lupa Presensi  \n3⃣ Pendaftaran / Perubahan Nomor WA  \n4⃣ Pemeriksaan Status Nomor  \n5⃣ Penghapusan / Penggantian Nomor  \n6⃣ Lihat Rekap Presensi  \n7⃣ Kontak Admin  \n8⃣ *Panduan Laporan PKL*  \n\n📝 Balas pesan ini dengan angka dari 1 sampai 8.";
    $menus[] = "📋 *Menu Layanan Presensi PKL*  \n📚 Untuk Siswa SMKN Bansari Tahun $tahun\n\nYuk pilih menu yang kamu butuhkan:\n\n1⃣ *Panduan Presensi*  \n    📝 Cara presensi harian yang benar.\n\n2⃣ *Lupa Presensi* *(SUDAH AKTIF)*  \n    🕒 Kalau kamu lupa presensi kemarin, bisa pakai fitur ini (maks 2x sehari).\n\n3⃣ *Daftar / Ganti Nomor WA*  \n    📱 Ganti atau daftarkan nomor WA supaya bisa akses presensi.\n\n4⃣ *Cek Status Nomor*  \n    🔍 Cek apakah nomor WA kamu sudah terdaftar atau belum.\n\n5⃣ *Hapus / Ganti Nomor*  \n    🧹 Ganti atau hapus nomor WA yang sebelumnya terdaftar.\n\n6⃣ *Rekap Presensi*  \n    📊 Lihat rangkuman presensimu selama PKL.\n\n7⃣ *Hubungi Admin*  \n    📩 Ada kendala? Mau tanya? Bisa langsung ke Admin.\n\n8⃣ *Panduan Laporan PKL*  \n    🗂 Info lengkap soal laporan PKL (akan segera tersedia).\n\n🔁 *Ketik angka sesuai menu pilihanmu.*  \nContoh: ketik `2` untuk pakai fitur Lupa Presensi.";
    // Pilih salah satu secara acak
    $sendmsg = $menus[array_rand($menus)];

} elseif (strtolower($message === "1")) {
    $sendmsg = "📸 *Panduan Presensi PKL SMK Negeri Bansari*\n\n✅ *Pastikan nomor WhatsApp Anda telah terdaftar!*\nJika belum, balas dengan ketik `3` untuk panduan pendaftaran.\n\n🔹 *Langkah Presensi:*\n1️⃣ Ambil foto selfie saat berada di lokasi PKL.\n2️⃣ Tambahkan *keterangan* pada foto dengan format:\n`KETERANGAN<spasi>CATATAN`\n\n🔸 *Pilihan KETERANGAN:*\n    - masuk\n    - izin\n    - sakit\n    - libur\n\n🔸 *Contoh Penggunaan:*\n    - `Masuk Memasang instalasi listrik`\n    - `Sakit Demam dan batuk`\n    - `Izin Ada acara keluarga`\n    - `Libur Tidak ada kegiatan hari ini`\n\n3️⃣ Kirim foto tersebut.\n🕐 Tunggu respon konfirmasi berhasil dari sistem.\nJika belum ada balasan, *silakan kirim ulang atau hubungi admin*.";

    sendMessage($number, "Contoh Presensi", "https://hadir.masbendz.com/app/contohpresensi.jpg");
} elseif (strtolower($message === "2") || (strtolower($message) === "lupa absen")) {
    $sendmsg = "🕒 *Panduan Lupa Absen*\n\nJika kamu *lupa presensi kemarin*, kamu masih bisa mengisi hari ini.\nNamun, *maksimal hanya 2 kali lupa absen dalam 1 hari!*\n\n🔹 *Langkah Lupa Absen:*\n\n1️⃣ Ambil *foto selfie* seperti biasa saat presensi.\n\n2️⃣ Tambahkan caption dengan format:\n`LUPA<spasi>KETERANGAN<spasi>TANGGAL<spasi>CATATAN`\n\n🔸 *Pilihan KETERANGAN:*\n- Masuk\n- Izin\n- Sakit\n- Libur\n\n🔸 *Format TANGGAL:*\n- `22-07-2025` atau `22/07/2025`\n\n🔸 *Contoh:*\n- `LUPA Masuk 22-07-2025 Input data alat lab`\n- `LUPA Sakit 21/07/2025 Demam dan pusing`\n\n3️⃣ Kirim seperti biasa.\n🕐 Jika belum ada balasan dari sistem, *kirim ulang atau teruskan atau tanya admin.*\n\n📌 *Catatan:*\nJangan gunakan fitur ini untuk mengakali presensi. Sistem mencatat semua aktivitas.";

    sendMessage($number, "Contoh Lupa Absen", "https://hadir.masbendz.com/app/contohlupaabsen.jpg");
} elseif (strtolower($message === "3")) {
    include "../config/koneksi.php";

    if (!$conn) {
        $sendmsg = "❗❗Koneksi database gagal. Silakan coba lagi.";
    } else {
        // Query untuk memeriksa apakah nomor sudah terdaftar
        $stmt = $conn->prepare("SELECT nama, kelas, nohp FROM datasiswa WHERE nohp LIKE ?");
        $number_pattern = "%$number%"; // Pola LIKE untuk mencocokkan nomor
        $stmt->bind_param("s", $number_pattern);

        if ($stmt->execute()) {
            $query_result = $stmt->get_result();

            if ($query_result->num_rows > 0) {
                $row = $query_result->fetch_assoc();
                $nama = $row['nama'];
                $kelas = $row['kelas'];

                // Informasi siswa ditemukan
                $sendmsg = "✅ *Nomor $number sudah terdaftar.*\n\n🧑 Nama : $nama\n🏫 Kelas : $kelas\n\nNomor ini *sudah bisa digunakan* untuk melakukan presensi PKL.\n\n📸 Silakan lakukan presensi sesuai panduan.";
            } else {
                // Jika nomor tidak ditemukan
                $sendmsg = "📝 *Pendaftaran Nomor WhatsApp*\n\nNomor ini *belum terdaftar* di sistem.\n\nUntuk mendaftarkan, balas dengan format berikut:\n\n🔡 *reg<spasi>NIS*\nContoh:\nJika NIS kamu adalah 1234\nKetik: `reg 1234`\nLalu kirim.\n\n*) Huruf besar / kecil tidak berpengaruh.";
            }
        } else {
            $sendmsg = "⚠️ *Terjadi kesalahan saat memproses permintaan.*\nSilakan coba lagi nanti atau hubungi admin.\n\n🧰 Detail error: " . $stmt->error . "";
        }

        $stmt->close();
        $conn->close();
    }
} elseif (strtolower($message === "5")) {
    $sendmsg = "Ingin membatalkan pendaftaran nomor ini?\n\n🔁 *Balas dengan ketik:* `unreg`\n\n📌 Setelah dibatalkan, nomor ini *tidak bisa digunakan* untuk presensi sampai *didaftarkan ulang*.\n\nJika kamu tidak yakin, silakan konsultasikan ke admin terlebih dahulu ya 😊";
} elseif (strtolower($message === "6" || strtolower($message) === "rekap")) {
    include "../config/koneksi.php";

    // Ambil nama siswa dari tabel 'datasiswa' berdasarkan NIS
    $stmt = $conn->prepare("SELECT nis FROM datasiswa WHERE nohp = ?");
    $stmt->bind_param("s", $number);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $nis = $row['nis'];

        // Lanjutkan proses rekap presensi berdasarkan NIS
        if ($nis) {
            // Ambil data siswa
            $siswa = mysqli_query($conn, "SELECT nama, kelas, nohp FROM datasiswa WHERE nis = '$nis' LIMIT 1");
            if (!$row = mysqli_fetch_assoc($siswa)) {
                $sendmsg = "❌ NIS tidak ditemukan dalam data siswa.";
                return;
            }

            $nama = $row['nama'];
            $kelas = $row['kelas'];
            $nohp = $row['nohp'];

            // Ambil semua data presensi berdasarkan NIS
            $res = mysqli_query($conn, "SELECT DATE(timestamp) as tanggal, ket FROM presensi WHERE nis = '$nis' ORDER BY tanggal ASC");
            if (mysqli_num_rows($res) == 0) {
                $sendmsg = "📋 *Rekap Presensi*\n\nNama: $nama\nKelas: $kelas\nNIS: $nis\nNo HP: $nohp\n\nTidak ditemukan data presensi.";
                return;
            }

            // Map ikon
            $ikonMap = [
                'masuk' => '✅',
                'izin' => '🔵',
                'sakit' => '🟡',
                'libur' => '🔴'
            ];

            // Susun data per tanggal dan bulan
            $data = [];
            $bulanLabels = [];

            // Inisialisasi counter
            $counter = [
                'masuk' => 0,
                'izin' => 0,
                'sakit' => 0,
                'libur' => 0
            ];

            while ($r = mysqli_fetch_assoc($res)) {
                $tgl = date('j', strtotime($r['tanggal']));
                $bln = date('n', strtotime($r['tanggal']));
                $namaBulan = date('F', strtotime($r['tanggal']));

                $ket = strtolower($r['ket']);
                $ikon = $ikonMap[$ket] ?? '❌';
                $data[$tgl][$bln] = $ikon;
                $bulanLabels[$bln] = $namaBulan;

                // Hitung jumlah berdasarkan ket
                if (isset($counter[$ket])) {
                    $counter[$ket]++;
                }
            }

            // Buat daftar bulan yang muncul
            ksort($bulanLabels);

            // Buat tabel
            $text = "```";
            $text .= "📋 Rekap Presensi\n";
            $text .= "Nama : $nama\n";
            $text .= "Kelas: $kelas\n";
            $text .= "NIS  : $nis\n\n";
            $text .= "Masuk : {$counter['masuk']} x\n";
            $text .= "Izin  : {$counter['izin']} x\n";
            $text .= "Sakit : {$counter['sakit']} x\n";
            $text .= "Libur : {$counter['libur']} x\n\n";
            $text .= str_pad("Tgl", 5);
            foreach ($bulanLabels as $bln => $namaBln) {
                $text .= str_pad(substr($namaBln, 0, 3), 5);
            }
            $text .= "\n";

            // Baris tanggal
            for ($tgl = 1; $tgl <= 31; $tgl++) {
                $barisIsi = false;
                $text .= str_pad($tgl, 5);
                foreach (array_keys($bulanLabels) as $bln) {
                    if (isset($data[$tgl][$bln])) {
                        $text .= str_pad($data[$tgl][$bln], 5);
                        $barisIsi = true;
                    } else {
                        $text .= str_pad("-", 5);
                    }
                }

                $text .= "\n";
            }

            $text .= "```\n";
            $text .= "Keterangan:\n✅ = Masuk\n🔵 = Izin\n🟡 = Sakit\n🔴 = Libur\n❌ = Tidak Presensi\n\n";
            $sendmsg = $text;

            $sendmsg .= "📊 Rekap kehadiranmu bisa dilihat melalui link berikut:\n\n🔗 https://pklbos.smknbansari.sch.id/?akses=detail&nis=$nis\n\n📌 Silakan buka link di atas untuk melihat detail kehadiranmu.";
        }
    } else {
        $sendmsg = "❗ *Nomor WhatsApp ini belum terdaftar* untuk presensi.\n\nUntuk mendaftarkan, balas dengan format:\n📌 `reg<spasi>NIS`\n\n🔹 *Contoh:*  \n`reg 1234`\n\n*) Tidak masalah huruf besar atau kecil.";
    }

    $stmt->close();
    $conn->close();
} elseif (strtolower($message) == '7' || strtolower($message) == 'admin') {
    $menuadmin = true;
    // Pesan balasan untuk user
    // Pastikan nomor pengguna diubah dari format 08 ke format internasional (62)

    // catat
    $nono = preg_replace('/^0/', '62', $number);

    // Baca data JSON
    $adminData = readJsonFile($jsonFile);

    if (!isset($adminData[$nono])) {
        // Tambahkan nomor ke JSON
        $adminData[$nono] = [
            'name' => $pushName,
            'time' => date('Y-m-d H:i:s') // Tanggal dan waktu user pertama kali menghubungi admin
        ];
        writeJsonFile($jsonFile, $adminData);

        // Kirim pesan konfirmasi ke user
        // $sendmsg = "Silakan ajukan pertanyaan, untuk kemudian akan dijawab oleh admin\n\nBalas dengan ketik `info` atau memilih menu untuk mengakhiri obloran dengan admin.";

        $admin_chat_messages = [
            "🧑🏻‍💻 *Anda terhubung ke Admin!*\n\n🗨️ Silakan sampaikan pertanyaan atau pesanmu. Admin akan segera membalas.\n\nUntuk keluar dari percakapan dengan admin, ketik `info` atau pilih menu utama.",
            "🧑🏻 *Anda terhubung ke Admin!*\n\n👋 Kamu sekarang sedang dalam sesi chat dengan admin.\nSilakan ajukan pertanyaan atau keluhan.\n\nKetik `info` jika ingin kembali ke menu awal.",
            "🙋🏻‍♂️ *Anda terhubung ke Admin!*\n\n📬 Admin siap membantu. Silakan ketik pesan yang ingin disampaikan.\n\nUntuk mengakhiri chat dengan admin, balas dengan `info` atau gunakan menu.",
            "🙋🏻 *Anda terhubung ke Admin!*\n\n🧾 Kamu bisa bertanya atau menyampaikan kendala sekarang.\nAdmin akan membalas sesuai antrean.\n\nKetik `info` jika ingin kembali ke layanan utama."
        ];

        $sendmsg = $admin_chat_messages[array_rand($admin_chat_messages)];

        // Kirim notifikasi ke admin
        // $adminMessage = "$nono ~ $pushName,\nTelah menghubungi Admin di WA Presensi.\n\n_Tolong segera direspon._";

        include "../config/koneksi.php";
        $daNomorTerdaftar = cekNomorTerdaftar($conn, $number);

        $typeMap = [
            'pembimbing' => "Dari Pembimbing: {$daNomorTerdaftar['nama']}\n",
            'siswa' => "Dari: {$daNomorTerdaftar['nama']}\nKelas: {$daNomorTerdaftar['kelas']}\n"
        ];

        $adminMessage = "🚨\n";
        $adminMessage .= $typeMap[$daNomorTerdaftar['type']] ?? "Dari: Nomor Tidak terdaftar\n";
        $adminMessage .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        $adminMessage .= "$nono ~ $pushName mengirim pesan melalui layanan WA Presensi.\n\n🛎️ Mohon dicek dan ditanggapi segera.";

        sendMessage($adminNumber, $adminMessage, $file);
    }
} elseif (strtolower($message) === "8") {
    $sendmsg = "📄 Panduan Laporan PKL\n";
    $file = $fileLaporan;
} elseif (strpos(strtolower($message), "reg status") === 0 || strtolower($message === "4")) {
    include "../config/koneksi.php";

    if (!$conn) {
        $sendmsg = "❗❗Koneksi database gagal. Silakan coba lagi.";
    } else {
        // Query untuk memeriksa apakah nomor sudah terdaftar
        $stmt = $conn->prepare("SELECT nama, kelas, nohp FROM datasiswa WHERE nohp LIKE ?");
        $number_pattern = "%$number%"; // Pola LIKE untuk mencocokkan nomor
        $stmt->bind_param("s", $number_pattern);

        if ($stmt->execute()) {
            $query_result = $stmt->get_result();

            if ($query_result->num_rows > 0) {
                $row = $query_result->fetch_assoc();
                $nama = $row['nama'];
                $kelas = $row['kelas'];

                // Informasi siswa ditemukan
                $sendmsg = "✅ Nomor *$number* sudah terdaftar di sistem.\n\n🧑 Nama  : *$nama*\n🏫 Kelas : *$kelas*\n\nNomor ini sudah bisa digunakan untuk melakukan presensi PKL.";
            } else {
                // Jika nomor tidak ditemukan
                $sendmsg = "❗Nomor WA ini belum terdaftar di sistem.\n\nUntuk mendaftarkan nomor:\nKetik: `reg <spasi> NIS`\n\n📝 Contoh:\n`reg 1234`\n\nJika mengalami kendala, silakan hubungi Admin.";
            }
        } else {
            $sendmsg = "Gagal menjalankan query: " . $stmt->error;
        }

        $stmt->close();
        $conn->close();
    }
} elseif (strpos(strtolower($message), "unreg") === 0) {
    include "../config/koneksi.php";

    if (!$conn) {
        $sendmsg = "❗❗Koneksi database gagal. Silakan coba lagi.";
    } else {
        // Periksa apakah NIS terdaftar
        $stmt = $conn->prepare("SELECT nama, kelas, nis, nohp FROM datasiswa WHERE nohp LIKE ?");
        $no_pattern = "%$number%"; // Pola LIKE untuk mencocokkan NIS
        $stmt->bind_param("s", $no_pattern);

        if ($stmt->execute()) {
            $query_result = $stmt->get_result();

            if ($query_result->num_rows > 0) {
                $row = $query_result->fetch_assoc();
                $nama = $row['nama'];
                $kelas = $row['kelas'];
                $nis = $row['nis'];
                $nohp_lama = $row['nohp'];

                // Tampilkan informasi nama dan kelas
                // $sendmsg = "Nama: $nama,\nKelas: $kelas\nNIS: $nis\n";
                $sendmsg = "📄 Data Siswa:\n\n👤 Nama  : *$nama*\n🏫 Kelas : *$kelas*\n🆔 NIS   : *$nis*";

                // Logika untuk mengosongkan nomor HP
                if ($nohp_lama !== null) {
                    $update_stmt = $conn->prepare("UPDATE datasiswa SET nohp = NULL WHERE nohp LIKE ?");
                    $update_stmt->bind_param("s", $nohp_lama);

                    if ($update_stmt->execute()) {
                        // $sendmsg .= "Nomor WA ($nohp_lama) telah dihapus.";
                        $hapus_messages = [
                            "✅ Nomor WA *($nohp_lama)* berhasil dihapus dari sistem.",
                            "🗑️ Nomor *$nohp_lama* telah dihapus dari data presensi.",
                            "Nomor lama *$nohp_lama* sudah tidak terdaftar lagi.",
                            "⚠️ Nomor *$nohp_lama* sudah dihapus. Tidak bisa digunakan untuk presensi.",
                            "✂️ Nomor *$nohp_lama* telah dicabut dari sistem presensi.",
                            "✔️ Penghapusan nomor *$nohp_lama* berhasil.",
                            "Nomor WA *$nohp_lama* sudah kami hapus sesuai permintaan.",
                            "ℹ️ Info: Nomor *$nohp_lama* tidak lagi terhubung ke sistem presensi.",
                            "🎯 Selesai. Nomor *$nohp_lama* sudah dihapus dari sistem.",
                            "🧹 Nomor WA *$nohp_lama* telah dibersihkan dari database."
                        ];

                        $sendmsg = $hapus_messages[array_rand($hapus_messages)];
                    } else {
                        $sendmsg .= "❗Gagal menghapus nomor WA karena terjadi kesalahan sistem: " . $update_stmt->error . "\nSilakan coba lagi atau hubungi admin jika masalah berlanjut.";
                    }

                    $update_stmt->close();
                } else {
                    $sendmsg .= "❗Nomor WA Anda belum terdaftar dalam sistem presensi.\n\nSilakan daftar dengan cara mengetik:\nREG<spasi>NIS\n\nContoh: reg 1234\n\nJika mengalami kendala, hubungi admin untuk bantuan.";
                }
            } else {
                $sendmsg = "❗Nomor WA ini belum terdaftar.\n\nUntuk mendaftarkan nomor,\nBalas dengan ketik reg<spasi>NIS\n\nAtau Silakan hubungi Admin atau Pembimbing jika ada kendala.";
            }
        } else {
            $sendmsg = "❗❗Gagal menjalankan: " . $stmt->error;
        }

        $stmt->close();
        $conn->close();
    }

} elseif (preg_match('/^ya($|\s|a+$)/i', $message) || strtolower($message) === 'y' || strpos(strtolower($message), 'iya') === 0) {
    $tangguhan = true;

    if (!file_exists($pending_file)) {
        file_put_contents($pending_file, json_encode([], JSON_PRETTY_PRINT));
    }

    $pending_data = json_decode(file_get_contents($pending_file), true);

    if (isset($pending_data[$number])) {
        $entry = $pending_data[$number];
        $type = $entry['type']; // Ambil tipe konfirmasi
        $nis = $entry['nis'];

        include "../config/koneksi.php";

        if (!$conn) {
            $sendmsg = "❗❗Koneksi database gagal saat memproses konfirmasi.";
        } else {
            if ($type === "confirm_reg") {
                // ====== PROSES KONFIRMASI REGISTRASI ======
                // Ambil nomor lama dari database
                $cek_stmt = $conn->prepare("SELECT nohp FROM datasiswa WHERE nis = ?");
                $cek_stmt->bind_param("s", $nis);
                $cek_stmt->execute();
                $cek_result = $cek_stmt->get_result();

                $nohp_lama = "";
                if ($cek_result->num_rows > 0) {
                    $row = $cek_result->fetch_assoc();
                    $nohp_lama = $row['nohp'];
                }
                $cek_stmt->close();

                // Update nomor baru
                $update_stmt = $conn->prepare("UPDATE datasiswa SET nohp = ? WHERE nis = ?");
                $update_stmt->bind_param("ss", $number, $nis);

                if ($update_stmt->execute()) {
                    $sendmsg = "✅ *Pendaftaran Berhasil!*\n\nNomor *$number* telah didaftarkan atas nama:\n👤 *" . $entry['nama'] . "*  \n🏫 *Kelas:* " . $entry['kelas'] . "\n\nSekarang kamu bisa melakukan presensi PKL.\n\nKetik `1` untuk panduan presensi.";

                    // Jika nomor lama tidak kosong dan berbeda dengan nomor baru
                    if (!empty($nohp_lama) && $nohp_lama !== $number) {
                        $pesan_ke_lama = "🔔 *Pemberitahuan*\n\nNomor kamu ini ($nohp_lama) telah digantikan oleh nomor baru ($number) atas nama:\n👤 *" . $entry['nama'] . "*\n🏫 *Kelas:* " . $entry['kelas'] . "\n\nJika kamu merasa tidak melakukan ini, segera hubungi admin.\natau daftarkan kembali nomor kamu.";

                        // Kirim pesan ke nomor lama
                        sendMessage($nohp_lama, $pesan_ke_lama, $file); // <--- Pastikan fungsi ini tersedia
                    }
                } else {
                    $sendmsg = "❗Gagal mendaftar: " . $update_stmt->error;
                }

                $update_stmt->close();
            } elseif ($type === "confirm_libur") {
                // ====== PROSES KONFIRMASI HARI LIBUR ======
                $nis = $entry['nis'];
                $nama = $entry['nama'];
                $kelas = $entry['kelas'];
                $waktu = date('Y-m-d', strtotime($entry['waktu']));
                $timestamp = date('Y-m-d H:i:s', strtotime($entry['waktu']));

                // Periksa apakah NIS sudah melakukan presensi pada tanggal tertentu
                $stmt = $conn->prepare("SELECT 1 FROM presensi WHERE nis = ? AND DATE(timestamp) = ?");
                $stmt->bind_param("ss", $nis, $waktu);
                $stmt->execute();
                $stmt->store_result();

                if ($stmt->num_rows === 0) {
                    $stmt = $conn->prepare("INSERT INTO presensi (nis, namasiswa, kelas, ket, timestamp) VALUES (?, ?, ?, 'libur', ?)");
                    $stmt->bind_param("ssss", $nis, $nama, $kelas, $timestamp);
                    if ($stmt->execute()) {
                        // Bandingkan dengan tanggal hari ini
                        $tanggal = date('Y-m-d');
                        if ($waktu === $tanggal) {
                            $formattedDate = "Hari ini";
                        } else {
                            // Jika bukan hari ini, ubah format ke tampilan d-m-Y
                            $tanggalFormatted = formatTanggalIndo($waktu);
                            $formattedDate = "Hari/Tanggal:\n" . $tanggalFormatted;
                        }

                        $sendmsg = "✅ Oke, $formattedDate dicatat sebagai *libur* untuk $nama ($kelas).";
                    } else {
                        $sendmsg = "❗Gagal mencatat hari libur: " . $stmt->error;
                    }
                }

                $stmt->close();
            }

            $conn->close();
        }

        // Hapus data tertangguh setelah konfirmasi
        unset($pending_data[$number]);
        file_put_contents($pending_file, json_encode($pending_data, JSON_PRETTY_PRINT));
    } else {
        $adminMessage = "$number ~ $pushName:\n$message\n\nSesi Hub.Admin *Tidak Aktif*\nSesi bukan Konfirmasi Reg: ya";
        sendMessage($adminNumber, $adminMessage, $file);
    }
} elseif (strpos(strtolower($message), "reg") === 0) {
    // Hilangkan spasi ganda atau lebih menjadi satu spasi
    $message = preg_replace('/\s+/', ' ', $message);

    $chars_to_remove = ["\"", "'", ";"];
    // Hapus karakter-karakter tersebut dari pesan
    $message = str_replace($chars_to_remove, "", $message);

    // Memecah pesan berdasarkan spasi
    $parts = explode(' ', $message);

    // Periksa apakah hanya ada dua bagian (kata pertama dan satu kata setelah spasi)
    if (count($parts) == 2) {
        $result = $parts[1];

        // Periksa apakah kata kedua hanya angka
        if (ctype_digit($result)) {
            // masukkan nomor HP ke database dengan NIS tersebut
            include "../config/koneksi.php";

            if (!$conn) {
                $sendmsg = "❗❗Koneksi database gagal. Silakan coba lagi.";
            } else {
                // Cek apakah nomor WhatsApp ini ($number) sudah terdaftar di data siswa
                $cek_nomor_stmt = $conn->prepare("SELECT nama, nis FROM datasiswa WHERE nohp = ?");
                $cek_nomor_stmt->bind_param("s", $number);
                $cek_nomor_stmt->execute();
                $cek_nomor_result = $cek_nomor_stmt->get_result();

                $ganti_nomor = false;

                if ($cek_nomor_result->num_rows > 0) {
                    $row = $cek_nomor_result->fetch_assoc();
                    $nama_terdaftar = $row['nama'];
                    $nis_terdaftar = $row['nis'];

                    $ganti_nomor = true;

                    $cek_nomor_stmt->close();
                }

                // Periksa apakah NIS terdaftar
                $stmt = $conn->prepare("SELECT nama, kelas, nohp FROM datasiswa WHERE nis LIKE ?");
                $result = trim($result);
                $nis_pattern = "%$result%"; // Pola LIKE untuk mencocokkan NIS
                $stmt->bind_param("s", $nis_pattern);

                if ($stmt->execute()) {
                    $query_result = $stmt->get_result();

                    if ($query_result->num_rows > 0) {
                        $row = $query_result->fetch_assoc();
                        $nama = $row['nama'];
                        $kelas = $row['kelas'];
                        $nohp_lama = $row['nohp'];

                        if ($nohp_lama === $number) {
                            $sendmsg .= "ℹ️ *Nomor ini ($number) sudah terdaftar sebelumnya.*\n\n👤 *Nama:* $nama  \n🏫 *Kelas:* $kelas\n\n✅ Karena sudah terdaftar, kamu *tidak perlu mengulang pendaftaran (reg)*.  \nSilakan langsung melakukan *presensi* seperti biasa.\n\nTerima kasih 🙏";
                        } else {
                            // Tangguhkan dulu: simpan ke file JSON
                            $pending_file = "tangguhan.json";
                            $pending_data = [];

                            if (!file_exists($pending_file)) {
                                file_put_contents($pending_file, json_encode([], JSON_PRETTY_PRINT));
                            }

                            $json_content = file_get_contents($pending_file);
                            $pending_data = json_decode($json_content, true) ?: [];

                            // Simpan nomor ke daftar tangguhan
                            $pending_data[$number] = [
                                "type" => "confirm_reg", // Tipe konfirmasi
                                "nis" => $result,
                                "nama" => $nama,
                                "kelas" => $kelas,
                                "waktu" => date("Y-m-d H:i:s")
                            ];

                            if (empty($nohp_lama)) {
                                // ✅ Nomor belum pernah digunakan sebelumnya
                                $sendmsg = "❓ *Apakah data berikut benar milik kamu?*\n";
                                $sendmsg .= "✍️ *Balas dengan ketik:*\n";
                                $sendmsg .= "`ya` – untuk *konfirmasi dan lanjut mendaftar*\n";
                                $sendmsg .= "`tidak` – jika *data salah* dan ingin registrasi ulang*\n\n";
                                $sendmsg .= "━━━━━━━━━━━━━━━━━━━━\n";
                                $sendmsg .= "📄 *DATA DITEMUKAN:*\n";
                                $sendmsg .= "━━━━━━━━━━━━━━━━━━━━\n";
                                $sendmsg .= "👤 Nama  : *$nama*\n";
                                $sendmsg .= "🏫 Kelas : *$kelas*\n";
                                $sendmsg .= "🆔 NIS   : *$result*\n";
                                // $sendmsg .= "📱 Nomor sekarang: *$number*\n";
                                $sendmsg .= "🔰 Status: *Belum Terdaftar*\n";
                                $sendmsg .= "━━━━━━━━━━━━━━━━━━━━\n";
                                $sendmsg .= "⏳ *Catatan: Nomor kamu sedang DITANGGUHKAN dan TIDAK BISA PRESENSI sebelum ada konfirmasi.*\n";

                                file_put_contents($pending_file, json_encode($pending_data, JSON_PRETTY_PRINT));
                            } elseif ($ganti_nomor) {
                                // ⚠️ Nomor ini sedang digunakan oleh user lain
                                $sendmsg = "🚫 *NOMOR INI SUDAH TERDAFTAR!*\n\n";
                                $sendmsg .= "📱 Nomor         : *$number*\n";
                                $sendmsg .= "👤 Nama terdaftar: *$nama_terdaftar*\n";
                                $sendmsg .= "🆔 NIS           : *$nis_terdaftar*\n\n";
                                $sendmsg .= "✅ Nomor ini *sudah bisa melakukan presensi* tanpa harus mendaftar ulang.\n\n";
                                $sendmsg .= "🔄 Jika kamu ingin mengganti nomor ini untuk user lain:\n";
                                $sendmsg .= "✍️ *Balas dengan ketik:*\n";
                                $sendmsg .= "`unreg` – untuk *melepaskan nomor dari data sebelumnya*\n\n";
                                $sendmsg .= "Setelah itu kamu bisa *daftar ulang kembali.*\n";

                            } else {
                                // 🚫 Data ini sudah terdaftar, tapi nomor berbeda
                                $sendmsg = "❓ *Apakah data berikut benar milik kamu";

                                if (!empty($nohp_lama)) {
                                    $sendmsg .= " dan kamu ingin mengganti nomor";
                                }

                                $sendmsg .= "?*\n";
                                $sendmsg .= "✍️ *Balas dengan ketik:*\n";
                                $sendmsg .= "`ya` – untuk *konfirmasi ganti nomor*\n";
                                $sendmsg .= "`tidak` – jika *data salah* dan ingin *registrasi ulang*\n\n";
                                $sendmsg .= "━━━━━━━━━━━━━━━━━━━━\n";
                                $sendmsg .= "📄 *DATA DITEMUKAN:*\n";
                                $sendmsg .= "━━━━━━━━━━━━━━━━━━━━\n";
                                $sendmsg .= "👤 Nama  : *$nama*\n";
                                $sendmsg .= "🏫 Kelas : *$kelas*\n";
                                $sendmsg .= "🆔 NIS   : *$result*\n";

                                if (!empty($nohp_lama)) {
                                    $sendmsg .= "📞 Nomor yg terdaftar sebelumnya : *$nohp_lama*\n";
                                }

                                // $sendmsg .= "📱 Nomor sekarang   : *$number*\n";
                                $sendmsg .= "🔄 Status: *Terdaftar dengan Nomor Berbeda*\n";
                                $sendmsg .= "━━━━━━━━━━━━━━━━━━━━\n";
                                $sendmsg .= "⏳ *Catatan: Nomor kamu sedang DITANGGUHKAN dan TIDAK BISA PRESENSI sebelum ada konfirmasi.*\n";

                                file_put_contents($pending_file, json_encode($pending_data, JSON_PRETTY_PRINT));
                            }

                            $sendmsg = trim($sendmsg);
                        }
                    } else {
                        $sendmsg = "❗ *NIS $result tidak terdaftar.*\n\nSilakan hubungi *Admin* atau *Pembimbing* untuk informasi lebih lanjut.\n\n📲 Ketik `7` atau `admin` untuk langsung menghubungi admin.";
                    }
                } else {
                    $sendmsg = "❗❗Gagal menjalankan query: " . $stmt->error;
                }

                $stmt->close();
            }

            $conn->close();
        } else {
            $sendmsg = "❗ *Format pesan tidak sesuai.*\n\nKata kedua harus berupa angka, tapi yang kamu kirim adalah:\n\n$result\n\nMohon koreksi dan coba kirim ulang ya.";
        }
    } else {
        $sendmsg = "❗ *Format REG tidak valid.*\n\n📌 Gunakan format:  \n`REG <spasi> NIS`\n\nContoh:  \n`REG 1234`";
    }
} elseif (strpos(strtolower($message), "masuk") === 0 || strpos(strtolower($message), "izin") === 0 || strpos(strtolower($message), "sakit") === 0 || strpos(strtolower($message), "libur") === 0) {
    // Pisahkan pesan berdasarkan spasi, ambil kata pertama dan sisa pesan
    $message_parts = explode(" ", $message, 2);
    $status = strtolower($message_parts[0]);
    $status = preg_replace("/[^a-z]/", "", $status);
    $catatan = isset($message_parts[1]) ? trim($message_parts[1]) : ""; // Ambil semua teks setelah kata pertama, hilangkan spasi awal
    $catatan = htmlspecialchars($catatan, ENT_QUOTES, 'UTF-8');

    // Masukkan ke tabel presensi jika status valid
    if (in_array($status, ["masuk", "izin", "sakit", "libur"])) {
        include "../config/koneksi.php";

        // Ambil nama siswa dari tabel 'datasiswa' berdasarkan NIS
        $number = normalisasi_nomor_62_ke_0($number);
        $stmt = $conn->prepare("SELECT nama, nis, kelas FROM datasiswa WHERE nohp = ?");
        $stmt->bind_param("s", $number);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $namasiswa = $row['nama'];
            $kelas = $row['kelas'];
            $nis = $row['nis'];  // Ambil NIS berdasarkan nomor HP
            $genKode = generateRandomCode();

            // Periksa apakah NIS sudah melakukan presensi pada tanggal tertentu
            $stmt = $conn->prepare("SELECT ket, timestamp FROM presensi WHERE nis = ? AND DATE(timestamp) = ?");
            $stmt->bind_param("ss", $nis, $tanggal); // 's' untuk string
            $stmt->execute();
            $stmt->bind_result($ket, $timestamp);

            // Ambil hasil query
            if ($stmt->fetch()) {
                // Format timestamp menjadi tanggal dan waktu
                $datetime = new DateTime($timestamp);
                $formattedDate = $datetime->format('Y-m-d'); // Format: YYYY-MM-DD
                $formattedTime = $datetime->format('H:i:s');

                // Bandingkan dengan tanggal hari ini
                $tanggal = date('Y-m-d');
                if ($formattedDate === $tanggal) {
                    $formattedDate = "Hari ini";
                } else {
                    // Jika bukan hari ini, ubah format ke tampilan d-m-Y
                    $tanggalFormatted = formatTanggalIndo($tanggal);
                    $formattedDate = "Hari/Tanggal:\n" . $tanggalFormatted;
                }

                // Tampilkan informasi presensi
                // $sendmsg = "$namasiswa ($nis - $kelas),\ntelah melakukan presensi untuk $formattedDate pukul $formattedTime.\n\nJadi Presensimu yang tadi sudah masuk ya";
                $sendmsg = "✅ $namasiswa ($nis - $kelas),\n\n";
                $sendmsg .= "Presensimu untuk $formattedDate pada pukul $formattedTime *sudah tercatat* sebelumnya.\nKet: $ket\n";
                $sendmsg .= "Jadi, tidak perlu presensi ulang ya. Terima kasih! 🙌";
            } else {
                if ($status !== "masuk" || !empty($url)) {
                    // konfirmasi libur
                    if (in_array($status, ["izin", "sakit", "libur"])) {
                        if (!file_exists($pending_file)) {
                            file_put_contents($pending_file, json_encode([], JSON_PRETTY_PRINT));
                        }

                        // $pending_data = json_decode(file_get_contents($pending_file), true);
                        // Pastikan jika file kosong atau JSON rusak → dianggap array kosong
                        $pending_data = json_decode(file_get_contents($pending_file), true) ?: [];

                        if (isset($pending_data[$number])) {
                            $entry = $pending_data[$number];
                            $type = isset($entry['type']) ? $entry['type'] : null;

                            if ($type === "confirm_libur") {
                                // Kirim pesan presensi
                                unset($pending_data[$number]);
                                file_put_contents($pending_file, json_encode($pending_data, JSON_PRETTY_PRINT));
                            }
                        }
                    }

                    // Masukkan data presensi
                    $stmt = $conn->prepare("INSERT INTO presensi (nis, ket, catatan, namasiswa, kelas, link, kode) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $stmt->bind_param("sssssss", $nis, $status, $catatan, $namasiswa, $kelas, $url, $genKode);

                    // Eksekusi statement INSERT
                    if ($stmt->execute()) {
                        // Jalankan download file
                        // URL yang akan dieksekusi
                        $urls = "https://hadir.masbendz.com/app/proseschat.php?nis=$nis&kode=$genKode&link=$url";

                        // Inisialisasi cURL
                        $ch = curl_init();

                        // Konfigurasi cURL
                        curl_setopt($ch, CURLOPT_URL, $urls);
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); // Mengikuti redirect jika ada

                        // Eksekusi cURL
                        $response = curl_exec($ch);

                        $sendmsg = "";

                        // Cek error
                        if (curl_errno($ch)) {
                            $sendmsg = "\n\n🚫 Gagal menyimpan Foto Presensi: " . curl_error($ch);
                        } else {
                            // echo "\n\nLink berhasil dieksekusi: " . $response;
                            // Mengubah JSON menjadi array assosiatif
                            $data = json_decode($response, true);

                            // Mengambil nilai link_baru dan menghapus backslash
                            // $link_baru = str_replace('\\', '', $data['link_baru']);


                            $datetime = new DateTime($timestamp);
                            $tanggalSaja = $datetime->format('Y-m-d');
                            $jamSaja = $datetime->format('H:i:s');

                            $tanggalIndo = formatTanggalIndo($tanggalSaja); // pakai fungsi yang sudah ada
                            $formattedWaktu = "$tanggalIndo\nPukul $jamSaja";

                            $sendmsg = "```";
                            $sendmsg .= "✅ Presensi Berhasil\n\n";
                            $sendmsg .= "🗓️ Status   : $status\n";
                            $sendmsg .= "📝 Catatan  : $catatan\n";
                            $sendmsg .= "👤 Nama     : $namasiswa\n";
                            $sendmsg .= "🏫 Kelas    : $kelas\n\n";
                            $sendmsg .= "⏰ Waktu    : $formattedWaktu";
                            $sendmsg .= "```\n\n";

                            if ($status == 'sakit') {
                                $sendmsg .= "🌼 Semoga cepat sembuh dan bisa kembali beraktivitas seperti biasa.\nTetap jaga kesehatan ya 💪\n\n";
                            } elseif ($status == 'izin') {
                                $sendmsg .= "📌 Semoga urusan atau kegiatanmu hari ini berjalan lancar.\nTetap semangat dan jangan lupa kembali presensi besok ya!\n\n";
                            } elseif ($status == 'libur') {
                                $sendmsg .= "🌴 Selamat menikmati waktu liburmu.\nGunakan waktu istirahat dengan baik agar kembali fresh dan siap beraktivitas.\n\n";
                            }

                            $sendmsg .= "📊 Lihat rekap presensi kamu, bisa balas dengan ketik `rekap` atau ketik `6` atau klik link ini:\n";
                            $sendmsg .= "https://pklbos.smknbansari.sch.id/?akses=detail&nis=$nis\n\n";
                            $sendmsg .= "ℹ️ Fitur *Lupa Absen* sudah aktif.\nBalas dengan ketik `2` untuk petunjuk penggunaannya.\n\n";
                            $sendmsg .= "ℹ️ Fitur *Batal Absen / Hapus Absen* sudah aktif.\nBalas dengan ketik `batal` untuk petunjuk penggunaannya.\n\n";
                            $sendmsg .= "📄 Panduan Laporan PKL Dapat di lihat pada menu nomor `8`. pilih menu balas dengan ketik `8`";
                        }

                        // Tutup cURL
                        curl_close($ch);
                    } else {
                        $sendmsg = "🚫 Gagal menambahkan presensi: " . $stmt->error;
                    }
                } else {
                    // Simpan penangguhan
                    $pending = [
                        "type" => "pending_presensi",
                        "status" => $status,
                        "catatan" => $catatan,
                        "nis" => $nis,
                        "namasiswa" => $namasiswa,
                        "kelas" => $kelas,
                        "tanggal" => date('Y-m-d')
                    ];

                    if (!is_dir("presensi_tmp"))
                        mkdir("presensi_tmp", 0777, true);
                    file_put_contents($penangguhanPresensi, json_encode($pending, JSON_PRETTY_PRINT));

                    $sendmsg = "⏳ Presensi *$status* Anda hampir selesai!\n"
                        . "📸 Namun, sistem belum menerima foto presensi.\n\n"
                        . "Untuk melengkapi dan menyimpan presensi hari ini:\n"
                        . "➡️ Balas pesan ini dengan *mengirim foto saja* (tanpa teks tambahan).\n\n"
                        . "Terima kasih 🙂 _cheers_ 🥂";

                }
            }

            $stmt->close();
        } else {
            $sendmsg = "🚫 *Nomor WhatsApp Anda belum terdaftar untuk presensi!*\n\nNomer: $number\n\n📲 Silakan *daftarkan nomor WA* terlebih dahulu dengan format berikut:\n`REG<spasi>NIS`\n\n🔹 *Contoh:*\n`REG 1234`\n\nSetelah berhasil terdaftar, Anda bisa langsung melakukan presensi.";
        }

        // Menutup koneksi
        $conn->close();
    } else {
        $sendmsg = "🚫 *Keterangan presensi* `$status` *tidak valid!*\n\n📌 Pastikan ejaan *benar, sesuai, dan persis* dengan salah satu kata berikut:\n- `masuk`\n- `izin`\n- `sakit`\n- `libur`\n\n⚠️ Harus *diawali* dengan salah satu kata di atas, kemudian diikuti *spasi* dan keterangan kegiatan. \n*Tidak boleh* ada tanda baca di sekitar kata pertama.\n\n📝 *Contoh yang benar:*\n- `Masuk Memasang instalasi listrik`\n- `Izin Pergi ke acara keluarga`";
    }

} elseif (strpos(strtolower($message), 'balas ') === 0) {
    // Hilangkan spasi ganda atau lebih menjadi satu spasi
    // $message = preg_replace('/\s+/', ' ', $message);
    $parts = explode(' ', $message, 3); // Memisahkan pesan menjadi 3 bagian: 'balas', nomor, dan pesan
    if (count($parts) >= 3) { // Pastikan formatnya sesuai
        $sendnumber = $parts[1]; // Bagian kedua adalah nomor
        $response = $parts[2] . "\n\n👤 ~ Admin 1"; // Bagian ketiga dan seterusnya adalah pesan

        if ($number == normalisasi_nomor_62_ke_0($adminNumber)) {
            sendMessage($sendnumber, $response, $file);

            $menuadmin = true;

            // catat
            $nono = normalisasi_nomor_0_ke_62($sendnumber);

            // Baca data JSON
            $adminData = readJsonFile($jsonFile);

            if (!isset($adminData[$nono])) {
                // Tambahkan nomor ke JSON
                $adminData[$nono] = [
                    'name' => $pushName,
                    'time' => date('Y-m-d H:i:s') // Tanggal dan waktu user pertama kali menghubungi admin
                ];
                writeJsonFile($jsonFile, $adminData);
            }
        } else {
            $sendmsg = "🚫 Perintah ini hanya untuk admin";
        }
    }
} elseif (strpos(strtolower($message), "lupa") === 0) {
    // Ambil tanggal hari ini (bukan tanggal yang dilaporkan)
    $hariIni = date("Y-m-d");

    // Lokasi file penyimpanan
    $filejson = "lupa.json";

    // Ambil data lama (jika ada)
    $datalupa = [];
    if (file_exists($filejson)) {
        $json = file_get_contents($filejson);
        $datalupa = json_decode($json, true);
    }

    // Hitung jumlah penggunaan oleh user hari ini
    $jumlahHariIni = isset($datalupa[$number][$hariIni]) ? $datalupa[$number][$hariIni] : '0';

    if ($jumlahHariIni >= 2) {
        $sendmsg = "❌ *Maaf*, Anda sudah menggunakan fitur *Lupa Presensi* sebanyak *2 kali* hari ini.\n\n🎯 *Kesempatan harian Anda telah habis.*\n\nSilakan coba kembali besok ya 🙏 Terima kasih atas pengertiannya.";
    } else {
        $sendmsg = typo($message);

        if (empty($sendmsg)) {
            // Proses isi pesan
            $text = trim(substr($message, 4));

            // Hilangkan spasi ganda atau lebih menjadi satu spasi
            $text = preg_replace('/\s+/', ' ', $text);

            // Bagi string menjadi 3 bagian berdasarkan spasi
            $parts = explode(" ", $text, 3);

            if (count($parts) >= 2) {
                if (!empty($url)) {
                    include "../config/koneksi.php";

                    // Ambil nama siswa dari tabel 'datasiswa' berdasarkan NIS
                    $stmt = $conn->prepare("SELECT nama, nis, kelas FROM datasiswa WHERE nohp = ?");
                    $stmt->bind_param("s", $number);
                    $stmt->execute();
                    $result = $stmt->get_result();

                    if ($result->num_rows > 0) {
                        $row = $result->fetch_assoc();
                        $namasiswa = $row['nama'];
                        $kelas = $row['kelas'];
                        $nis = $row['nis'];  // Ambil NIS berdasarkan nomor HP
                        $genKode = generateRandomCode();

                        $keterangan = strtolower(preg_replace("/[^a-zA-Z]/", "", $parts[0]));
                        $tanggalRaw = $parts[1];
                        $catatan = isset($parts[2]) ? $parts[2] : '';
                        $catatan = htmlspecialchars($catatan, ENT_QUOTES, 'UTF-8');
                        $tanggal = normalizeTanggal($tanggalRaw);

                        // Periksa apakah NIS sudah melakukan presensi pada tanggal tertentu
                        $stmt = $conn->prepare("SELECT timestamp FROM presensi WHERE nis = ? AND DATE(timestamp) = ?");
                        $stmt->bind_param("ss", $nis, $tanggal); // 's' untuk string
                        $stmt->execute();
                        $stmt->bind_result($timestamp);

                        // Ambil hasil query
                        if ($stmt->fetch()) {
                            // Format timestamp menjadi tanggal dan waktu
                            $datetime = new DateTime($timestamp);
                            $formattedDate = $datetime->format('Y-m-d'); // Format: YYYY-MM-DD
                            $formattedTime = $datetime->format('H:i:s');

                            // Bandingkan dengan tanggal hari ini
                            $tanggalNow = date('Y-m-d');
                            if ($formattedDate === $tanggalNow) {
                                $formattedDate = "Hari ini";
                            } else {
                                // Jika bukan hari ini, ubah format ke tampilan d-m-Y
                                $tanggalFormatted = formatTanggalIndo($tanggal);
                                $formattedDate = "Hari/Tanggal:*\n*" . $tanggalFormatted;
                            }

                            // Tampilkan informasi presensi
                            $sendmsg = "✅ *Halo $namasiswa* ($nis - $kelas),\n\n📅 Kamu sudah melakukan presensi pada *$formattedDate* pukul *$formattedTime*.\n\n📌 Artinya, presensimu untuk hari tersebut *sudah tercatat sebelumnya*, jadi *tidak perlu melakukan ulang*.\n\n🔁 Penggunaan fitur *Lupa Absen* hari ini: *$jumlahHariIni dari maksimal 2 kali*.\n\nTerima kasih 🙌";
                        } else {
                            if (in_array($keterangan, ["masuk", "izin", "sakit", "libur"])) {
                                $formattedTime = date("H:i:s", $timestampWA);
                                $timestamp = $tanggal . ' ' . $formattedTime;

                                if ($tanggal) {
                                    $today = date("Y-m-d");
                                    $StrAwalPKL = "15-12-2025";
                                    $awalPKL = date("Y-m-d", strtotime($StrAwalPKL));

                                    if ($tanggal === $today) {
                                        $sendmsg = "📅 *Tanggal yang kamu masukkan adalah tanggal hari ini:* `$tanggalRaw`\n\n📌Silakan lakukan *presensi seperti biasa* untuk hari ini tanpa menggunakan fitur *Lupa Absen*.\n\nJika ada kendala, hubungi admin ya. 🙏";
                                    } elseif ($tanggal > $today) {
                                        $sendmsg = "😄 Wah, kamu butuh *mesin waktu* buat presensi tanggal *$tanggalRaw* 🤯🚀\n\n📅 Sekarang masih tanggal *$tanggal2* lho. 😁\n\n📌Silakan koreksi tanggalnya dan coba lagi ya!";
                                    } elseif ($tanggal < $awalPKL) {
                                        $sendmsg = "🤔 Wah, sepertinya kamu salah ketik.\n📅 Tanggal yang kamu masukkan: $tanggalRaw dan Kita mulai PKL saja tanggal $StrAwalPKL.\n🧋 Minum dulu deh biar fokus.😁\n\n📌Silakan koreksi tanggalnya dan coba lagi ya!";
                                    } else {
                                        // Masukkan data presensi
                                        $stmt = $conn->prepare("INSERT INTO presensi (nis, ket, catatan, namasiswa, kelas, link, kode, timestamp) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                                        $stmt->bind_param("ssssssss", $nis, $keterangan, $catatan, $namasiswa, $kelas, $url, $genKode, $timestamp);

                                        // Eksekusi statement INSERT
                                        if ($stmt->execute()) {
                                            // Jalankan download file
                                            // URL yang akan dieksekusi
                                            $urls = "https://hadir.masbendz.com/app/proseschat.php?nis=$nis&kode=$genKode&link=$url";

                                            // Inisialisasi cURL
                                            $ch = curl_init();

                                            // Konfigurasi cURL
                                            curl_setopt($ch, CURLOPT_URL, $urls);
                                            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                                            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); // Mengikuti redirect jika ada

                                            // Eksekusi cURL
                                            $response = curl_exec($ch);

                                            // Cek error
                                            if (curl_errno($ch)) {
                                                $sendmsg = "\n\n🚫 Gagal menyimpan Foto Presensi: " . curl_error($ch);
                                            } else {
                                                echo "\n\nLink berhasil dieksekusi: " . $response;
                                                // Mengubah JSON menjadi array assosiatif
                                                $responseData = json_decode($response, true);

                                                // Mengambil nilai link_baru dan menghapus backslash
                                                // $link_baru = str_replace('\\', '', $responseData['link_baru']);

                                                // Simpan penggunaan baru
                                                $jumlahHariIni++;

                                                if (!isset($datalupa[$number])) {
                                                    $datalupa[$number] = [];
                                                }

                                                $datalupa[$number][$hariIni] = $jumlahHariIni;

                                                // Simpan ke file
                                                file_put_contents($filejson, json_encode($datalupa, JSON_PRETTY_PRINT));

                                                $tanggalFormatted = formatTanggalIndo($tanggal);

                                                $sendmsg = "```";
                                                $sendmsg .= "✅ Presensi `Lupa Absen` Berhasil Diterima\n\n";
                                                $sendmsg .= "📅 Tanggal     : $tanggalFormatted\n";
                                                $sendmsg .= "📝 Keterangan  : $keterangan\n";
                                                $sendmsg .= "🙍 Nama        : $namasiswa\n";
                                                $sendmsg .= "🏫 Kelas       : $kelas\n";
                                                $sendmsg .= "🗒️ Catatan     : $catatan\n";
                                                $sendmsg .= "📊 Pemakaian   : $jumlahHariIni dari 2 kali\n";
                                                $sendmsg .= "⚠️ Maksimal presensi: 2 kali per hari";
                                                $sendmsg .= "```\n\n";
                                                $sendmsg .= "ℹ️ Fitur *Batal Absen / Hapus Absen* sudah aktif.\nBalas dengan ketik `batal` untuk petunjuk penggunaannya.\n\n";
                                                $sendmsg .= "";
                                            }

                                            curl_close($ch);

                                            // $stmt->close();
                                        } else {
                                            $sendmsg = "🚫 Gagal menambahkan presensi: " . $stmt->error;
                                        }
                                    }
                                } else {
                                    $sendmsg = "❌ *Maaf $namasiswa*,\nTanggal yang kamu masukkan: *$tanggal* tidak valid.\n\n📅 *Panduan Format Tanggal untuk Lupa Presensi:*\n\nGunakan format tanggal yang benar dan sesuai sistem agar bisa diproses.\n\n🔹 *Format TANGGAL yang didukung:*\n- 22-07-2025\n- 22/07/2025\n- 22.07.2025\n- 22/Juli/2025  (Jika pakai nama bulan tetap pakai tanda pemisah dan tidak pakai spasi)\n\n🔸 *Catatan:*\n- Gunakan *dua digit angka* untuk hari & bulan (01–31 dan 01–12).\n- untuk tahun bisa 2 digit atau 4 digit.\n- Jangan campur tanda baca (gunakan salah satu format saja).\n\n🔹 *Contoh Format Lengkap:*\n- `LUPA Masuk 22-07-2025 Input data alat lab`\n- `LUPA Sakit 21/07/2025 Demam dan pusing`\n\nSilakan koreksi format tanggalmu, lalu kirim ulang ya 😊";
                                }
                            } else {
                                $sendmsg = "🚫 *Keterangan Tidak Dikenali*\n\nHalo $namasiswa, sistem tidak dapat mengenali keterangan yang kamu tulis.\nGunakan format *LUPA PRESENSI* yang benar agar data bisa tercatat.\n\n🔹 *Langkah Format Lupa Presensi:*\n\n1️⃣ Ambil *foto selfie* seperti saat presensi biasa.\n\n2️⃣ Tambahkan caption dengan format:\n`LUPA<spasi>KETERANGAN<spasi>TANGGAL<spasi>CATATAN`\n\n🔸 *Pilihan KETERANGAN:*\n- Masuk\n- Izin\n- Sakit\n- Libur\n\n🔸 *Format TANGGAL:*\n- `22-07-2025` atau `22/07/2025`\n\n🔸 *Contoh Penggunaan:*\n- `LUPA Masuk 22-07-2025 Input data alat lab`\n- `LUPA Sakit 21/07/2025 Demam dan pusing`\n\n3️⃣ Kirim ke sistem seperti biasa.\n\n📌 *Catatan:*\n- Pastikan ejaan keterangan *tepat dan tanpa tanda baca*.\n- Maksimal 2 kali lupa absen dalam 1 hari.\n\nJika masih bingung, balas dengan ketik `1` untuk panduan lengkap.";
                            }
                        }

                        $stmt->close();
                    } else {
                        $sendmsg = "🚫 *Nomor WhatsApp ini belum terdaftar* dalam sistem presensi.\n\n📌 Untuk mendaftarkan nomor, silakan kirim pesan dengan format berikut:\n`REG<spasi>NIS`\n\n🔹 *Contoh:*\n`reg 1234`\n\nJika mengalami kendala, silakan hubungi *admin* atau *pembimbing* untuk bantuan lebih lanjut.";
                    }

                    // Menutup koneksi
                    $conn->close();
                } else {
                    $sendmsg = "🚫 *Presensi Gagal!*\n\nPresensi tidak berhasil karena *tidak ada foto selfie* yang dikirim.\n\n📸 Silakan *kirim ulang presensi* dengan melampirkan *foto selfie* sesuai ketentuan sebagai bukti kehadiran.\n\nTerima kasih atas perhatian dan kerjasamanya 🙏";
                }
            } else {
                $sendmsg = "❌ *Format Lupa Absen tidak sesuai.*\n\n📌 Gunakan format berikut:\n`lupa <keterangan> <tanggal> <catatan>`\n\n🔹 *Contoh:*\n`lupa masuk 22-07-2025 Membantu input data`\n\n✅ Keterangan harus salah satu dari: *masuk, izin, sakit, libur*\n✅ Tanggal gunakan format seperti: *22-07-2025*\n\nSilakan koreksi dan kirim ulang ya 😊";
            }
        }
    }
} elseif (strpos(strtolower($message), 'batal') === 0) {
    // Hilangkan spasi ganda atau lebih menjadi satu spasi
    $message = preg_replace('/\s+/', ' ', $message);

    include "../config/koneksi.php";

    $sendmsg = typo($message);

    if (empty($sendmsg)) {
        $parts = explode(' ', $message);

        if (count($parts) === 2) {
            // Versi 1: Siswa membatalkan presensinya sendiri
            $ambiltanggal = trim($parts[1]);
            $tanggal = normalizeTanggal($ambiltanggal);

            if ($tanggal) {
                // Ambil data siswa berdasarkan nomor pengirim
                $number = normalisasi_nomor_62_ke_0($number);
                $stmt = $conn->prepare("SELECT nama, nis, kelas FROM datasiswa WHERE nohp = ?");
                $stmt->bind_param("s", $number);
                $stmt->execute();
                $result = $stmt->get_result();

                if ($row = $result->fetch_assoc()) {
                    $nis = $row['nis'];
                    $nama = $row['nama'];
                    $kelas = $row['kelas'];

                    // Hapus presensi berdasarkan NIS dan tanggal
                    $stmt_del = $conn->prepare("DELETE FROM presensi WHERE nis = ? AND DATE(timestamp) = ?");
                    $stmt_del->bind_param("ss", $nis, $tanggal);
                    $stmt_del->execute();

                    if ($stmt_del->affected_rows > 0) {
                        $sendmsg = "✅ Presensi untuk tanggal $ambiltanggal telah berhasil dibatalkan.\n\nAtas nama:\n👤 Nama  : $nama\n🏫 Kelas : $kelas\n🆔 NIS   : $nis\n\nSilakan lakukan presensi ulang jika diperlukan.";
                    } else {
                        $sendmsg = "⚠️ Tidak ada presensi atas nama $nama ($kelas) di tanggal $ambiltanggal.\nSilakan periksa kembali.";
                    }
                } else {
                    $sendmsg = "❌ Nomor Anda ($number) tidak terdaftar sebagai siswa di sistem presensi.\n\nSilakan daftarkan nomor WhatsApp Anda dengan cara ketik:\nREG<spasi>NIS\n\nContoh: REG 1234";
                }
            } else {
                $sendmsg = "❌ Maaf,\nTanggal yang kamu masukkan: *$ambiltanggal* tidak valid.\n\n✅ Gunakan format tanggal yang benar, seperti berikut ini:\n- 22-07-2025\n\n📌 Gunakan angka dua digit untuk hari & bulan.";
            }
        } elseif (count($parts) === 3) {
            // Versi 2: Admin membatalkan presensi siswa lain
            if (!in_array($number, $adminNumbers)) {
                echo "❌ Akses ditolak.\nAnda hanya bisa membatalkan presensi milik sendiri, bukan milik siswa lain.";
                return;
            }

            $nohp_target = trim($parts[1]);
            $ambiltanggal = trim($parts[2]);
            $tanggal = normalizeTanggal($ambiltanggal);

            if ($tanggal) {
                // Ambil data siswa berdasarkan nomor HP target
                $nohp_target = normalisasi_nomor_62_ke_0($nohp_target);
                $stmt = $conn->prepare("SELECT nama, nis, kelas FROM datasiswa WHERE nohp = ?");
                $stmt->bind_param("s", $nohp_target);
                $stmt->execute();
                $result = $stmt->get_result();

                if ($row = $result->fetch_assoc()) {
                    $nis = $row['nis'];
                    $nama = $row['nama'];
                    $kelas = $row['kelas'];

                    // Hapus presensi berdasarkan NIS dan tanggal
                    $stmt_del = $conn->prepare("DELETE FROM presensi WHERE nis = ? AND DATE(timestamp) = ?");
                    $stmt_del->bind_param("ss", $nis, $tanggal);
                    $stmt_del->execute();

                    if ($stmt_del->affected_rows > 0) {
                        $sendmsg = "✅ Presensi siswa atas nama $nama (NIS: $nis, Kelas: $kelas) pada tanggal $ambiltanggal berhasil dibatalkan oleh Admin.";

                        $adminMessage = "✅ Data presensi untuk tanggal $ambiltanggal sudah dibatalkan ya!\n\n👤 Nama  : $nama\n🏫 Kelas : $kelas\n🆔 NIS   : $nis\n\n🛠️ Proses ini dilakukan oleh Admin untuk pembaruan data.";

                        sendMessage($nohp_target, $adminMessage, $file);
                    } else {
                        $sendmsg = "⚠️ Tidak ada presensi atas nama $nama ($kelas) di tanggal $ambiltanggal.\nSilakan periksa kembali.";
                    }
                } else {
                    $sendmsg = "❌ Nomor HP siswa tidak ditemukan.";
                }
            } else {
                $sendmsg = "❌ Maaf,\nTanggal yang kamu masukkan: *$ambiltanggal* tidak valid.\n\n✅ Gunakan format tanggal yang benar, seperti berikut ini:\n- 22-07-2025\n\n📌 Gunakan angka dua digit untuk hari & bulan.";
            }
        } else {
            if (in_array($number, $adminNumbers)) {
                // Jika nomor adalah admin
                $sendmsg = "ℹ️ Format batal / hapus presensi.\n\nSebagai Admin, Anda dapat menggunakan salah satu format berikut:\n\n1⃣ Untuk membatalkan presensi siswa:\n📌 batal <nomor_wa_siswa> <tanggal>\nContoh: `batal 6281234567890 27-07-2025`\n\n2⃣ Untuk membatalkan presensi Anda sendiri (jika diperlukan):\n📌 batal <tanggal>\nContoh: `batal 27-07-2025`\n\n📝 Tanggal wajib menggunakan format: DD-MM-YYYY (tanggal-bulan-tahun).";
            } else {
                // Jika nomor adalah siswa
                $sendmsg = "ℹ️ Format batal / hapus presensi.\n\n📌 Gunakan format:\nbatal <tanggal>\nContoh: `batal 27-07-2025`\n\n💡 Tanggal harus dalam format DD-MM-YYYY (tanggal-bulan-tahun).";
            }
        }
    }


    $adminMessage = "";
    $daNomorTerdaftar = cekNomorTerdaftar($conn, $number);

    if ($daNomorTerdaftar["type"] === 'pembimbing') {
        $namaPembimbing = $daNomorTerdaftar['nama'];
        $adminMessage .= "Dari Pembimbing: $namaPembimbing\n";
    } else if ($daNomorTerdaftar["type"] === 'siswa') {
        $namasiswa = $daNomorTerdaftar['nama'];
        $kelassiswa = $daNomorTerdaftar['kelas'];
        $nissiswa = $daNomorTerdaftar['nis'];
        $adminMessage .= "Dari: $namasiswa\n";
    }

    $nono = normalisasi_nomor_0_ke_62($number);
    $adminMessage .= "⏳ Ada yang mengakses Batal presensi:\n$nono ~ $pushName:\n$message\n\nSesi Hub.Admin *Tidak Aktif* 🚫";
    sendMessage($adminNumber, $adminMessage, $file);
    $conn->close();
} elseif (preg_match('/^tidak(\s|$)/i', $message) || strtolower($message) === 'tdk' || strtolower($message) === 'tak') {
    $tangguhan = true;

    if (!file_exists($pending_file)) {
        file_put_contents($pending_file, json_encode([], JSON_PRETTY_PRINT));
    }

    $pending_data = json_decode(file_get_contents($pending_file), true);

    if (isset($pending_data[$number])) {
        $entry = $pending_data[$number];
        $type = isset($entry['type']) ? $entry['type'] : null;

        if ($type === "confirm_reg") {
            // Batal registrasi
            unset($pending_data[$number]);
            file_put_contents($pending_file, json_encode($pending_data, JSON_PRETTY_PRINT));

            $sendmsg = "⚠️ *Pendaftaran dibatalkan.*\n\nUntuk mengulangi pendaftaran, balas dengan mengetik:\n`reg <spasi> NIS`\n\nContoh:\n`reg 1234`";

        } elseif ($type === "confirm_libur") {
            // Kirim pesan presensi
            unset($pending_data[$number]);
            file_put_contents($pending_file, json_encode($pending_data, JSON_PRETTY_PRINT));

            $sendmsg = "🚨 *Ingat!*\n\nKamu belum melakukan presensi hari ini.\nSegera lakukan presensi seperti biasa ya.";
        }
    } else {
        if (empty($url)) {
            $sendmsg = "📸 *Ingin melakukan presensi?* ❓ Harap kirimkan *foto diri* dan *tambahkan keterangan* sesuai format yang benar.\n\n🔹 *Format Keterangan yang Disarankan:*\n- `Masuk Memasang instalasi listrik`\n- `Sakit Demam dan batuk`\n- `Izin Ada acara keluarga`\n- `Libur Tidak ada kegiatan hari ini`\n\n✅ Setelah itu, *ulangi pengiriman* jika sebelumnya belum sesuai.\n\n📩 *Balas pesan ini dengan mengetik:*\n1️⃣ `1` → Untuk mendapatkan *petunjuk lengkap presensi*\nℹ️ `info` → Untuk menampilkan *menu dan langkah-langkah presensi*\nℹ️ `admin` atau `7` → Untuk terhubung dan bertanya ke admin.";
        } else {
            $sendmsg = "🚫 *Keterangan foto tidak valid!*\n\nPastikan Anda menggunakan salah satu *jenis keterangan* berikut ini:\n\n🔹 *Pilihan KETERANGAN WAJIB diawali dengan:*\n   - `Masuk`\n   - `Izin`\n   - `Sakit`\n   - `Libur`\n\n📝 *Contoh Penggunaan yang Benar:*\n   - `Masuk Memasang instalasi listrik`\n   - `Sakit Demam dan batuk`\n   - `Izin Ada acara keluarga`\n   - `Libur Tidak ada kegiatan hari ini`\n\n📌 Silakan perbaiki keterangan Anda sesuai format di atas, lalu kirim ulang bersama foto.";
        }
    }
} elseif (strpos(strtolower($message), 'cek') === 0) {
    include "../config/koneksi.php";

    $pesan = explode(' ', strtolower(trim($message)));
    $sub1 = $pesan[1] ?? '';
    $sub2 = $pesan[2] ?? '';
    $sub3 = $pesan[3] ?? '';

    $penangguhanFile = "rekap_tmp/{$number}.json";

    if (file_exists($penangguhanFile)) {
        $penangguhan = json_decode(file_get_contents($penangguhanFile), true);

        // Tambah input baru ke langkah
        $input = end($pesan); // hanya ambil input terakhir (misal: 01, 101, dst)
        $penangguhan['step'][] = $input;
        $step = $penangguhan['step'];

        if (count($step) == 2) {
            // Pilihan utama (01/02/03)
            if ($step[1] == '01') {
                $res = mysqli_query($conn, "SELECT DISTINCT kelas FROM datasiswa ORDER BY kelas ASC");
                $text = "Pilih kelas untuk rekap:\n";
                $list = [];
                $i = 1;
                while ($row = mysqli_fetch_assoc($res)) {
                    $kode = "1" . str_pad($i, 2, "0", STR_PAD_LEFT);
                    $text .= "$kode {$row['kelas']}\n";
                    $list[$kode] = $row['kelas'];
                    $i++;
                }
                $penangguhan['sublist'] = $list;
                file_put_contents($penangguhanFile, json_encode($penangguhan));
                $sendmsg = $text . "\nBalas dengan ketik 3 digit kode di depan nama kelas.";
            } elseif ($step[1] == '02') {
                $res = mysqli_query($conn, "SELECT id, nama FROM datapembimbing ORDER BY nama ASC");
                $text = "Pilih pembimbing untuk rekap:\n";
                $list = [];
                $i = 1;
                while ($row = mysqli_fetch_assoc($res)) {
                    $kode = "2" . str_pad($i, 2, "0", STR_PAD_LEFT);
                    $text .= "$kode {$row['nama']}\n";
                    $list[$kode] = $row['nama'];
                    $i++;
                }
                $penangguhan['sublist'] = $list;
                file_put_contents($penangguhanFile, json_encode($penangguhan));
                $sendmsg = $text . "\nBalas dengan ketik 2 digit kode di depan nama pembimbing.";
            } elseif ($step[1] == '03') {
                $res = mysqli_query($conn, "SELECT DISTINCT nama_dudika FROM penempatan WHERE nama_dudika IS NOT NULL AND nama_dudika != '' ORDER BY nama_dudika ASC");
                $text = "Pilih DUDI untuk rekap:\n";
                $list = [];
                $i = 1;
                while ($row = mysqli_fetch_assoc($res)) {
                    $kode = "3" . str_pad($i, 2, "0", STR_PAD_LEFT);
                    $text .= "$kode {$row['nama_dudika']}\n";
                    $list[$kode] = $row['nama_dudika'];
                    $i++;
                }
                $penangguhan['sublist'] = $list;
                file_put_contents($penangguhanFile, json_encode($penangguhan));
                $sendmsg = $text . "\nBalas dengan ketik 3 digit kode di depan nama DUDI.";
            } else {
                unlink($penangguhanFile);
                $sendmsg = "⚠️ *Pilihan Tidak Valid!*\n\nSebelumnya Anda berada di *Menu Rekap Presensi*, namun pilihan yang Anda kirim tidak dikenali.\n\n✅ Jika ingin kembali ke Menu Rekap Presensi, balas dengan:\n`cek rekap`\n\n📌 Jika ingin memilih menu lain, silakan ulangi atau lanjutkan dengan memilih menu yang sesuai.";
            }

        } elseif (count($step) == 3) {
            // Step akhir: ambil kode pilihan
            $kode = $step[2];
            $selected = $penangguhan['sublist'][$kode] ?? null;

            if ($selected) {
                // Ambil semua siswa sesuai filter
                if (str_starts_with($kode, '1')) {
                    $sql = "SELECT * FROM datasiswa WHERE kelas = '$selected' ORDER BY nama";
                    $pilih = "Kelas: $selected";
                } elseif (str_starts_with($kode, '2')) {
                    $sql = "SELECT d.* FROM datasiswa d 
            JOIN penempatan p ON d.nis = p.nis_siswa 
            WHERE p.nama_pembimbing = '$selected' 
            ORDER BY d.nama";
                    $pilih = "Pembimbing: $selected";
                } elseif (str_starts_with($kode, '3')) {
                    $sql = "SELECT d.* FROM datasiswa d 
            JOIN penempatan p ON d.nis = p.nis_siswa 
            WHERE p.nama_dudika = '$selected' 
            ORDER BY d.nama";
                    $pilih = "DUDI: $selected";
                }

                $result = mysqli_query($conn, $sql);

                if (mysqli_num_rows($result) > 0) {
                    // Ambil tanggal 6 hari terakhir
                    $dates = [];
                    for ($i = 5; $i >= 0; $i--) {
                        $dates[] = date('Y-m-d', strtotime("-$i days"));
                    }

                    // Siapkan map ikon berdasarkan keterangan
                    $ikonMap = [
                        'masuk' => '✅',
                        'izin' => '🔵',
                        'sakit' => '🟡',
                        'libur' => '🔴'
                    ];

                    // Bangun array dasar siswa
                    $presensiData = [];
                    while ($row = mysqli_fetch_assoc($result)) {
                        $nis = $row['nis'];
                        $presensiData[$nis] = [
                            'nama' => $row['nama'],
                            'kelas' => $row['kelas'],
                            'nohp' => $row['nohp'] ?: '-',
                            'nis' => $nis,
                            'kehadiran' => [] // isi manual di bawah
                        ];

                        // Set nilai default: ➖ untuk Sabtu/Minggu, ❌ untuk hari lainnya
                        foreach ($dates as $tanggal) {
                            $hari = date('N', strtotime($tanggal)); // 6 = Sabtu, 7 = Minggu
                            $presensiData[$nis]['kehadiran'][$tanggal] = ($hari == 6 || $hari == 7) ? '➖' : '❌';
                        }
                    }

                    // Ambil data presensi 6 hari terakhir untuk siswa-siswa tersebut
                    $daftarNIS = array_map(function ($d) {
                        return "'" . $d['nis'] . "'";
                    }, $presensiData);

                    $inNIS = implode(",", $daftarNIS);
                    $tanggalAwal = $dates[0];

                    $qPresensi = "SELECT nis, DATE(timestamp) as tanggal, LOWER(ket) as ket FROM presensi 
              WHERE nis IN ($inNIS) AND DATE(timestamp) >= '$tanggalAwal'";

                    $resPresensi = mysqli_query($conn, $qPresensi);
                    while ($row = mysqli_fetch_assoc($resPresensi)) {
                        $nis = $row['nis'];
                        $tanggal = $row['tanggal'];
                        $ket = $row['ket'];
                        $ikon = $ikonMap[$ket] ?? '❌';

                        if (isset($presensiData[$nis])) {
                            $presensiData[$nis]['kehadiran'][$tanggal] = $ikon;
                        }
                    }

                    // Bangun pesan rekap
                    $text = "```";
                    $text .= "📅 Rekap presensi 6 hari terakhir:\n$pilih\n";
                    $text .= "Keterangan:\n✅ = Masuk\n🔵 = Izin\n🟡 = Sakit\n🔴 = Libur\n➖ = Libur Weekend\n❌ = Tidak Presensi\n\n";

                    // Header tabel
                    // Header tanggal
                    $text .= str_pad("No", 4) . str_pad("Nama & Kelas", 18);
                    foreach ($dates as $d) {
                        $text .= date('d', strtotime($d)) . "'";
                    }
                    $text .= "\n";

                    // Header hari
                    $text .= str_repeat(" ", 4) . str_repeat(" ", 18);
                    foreach ($dates as $d) {
                        $text .= hari_indonesia2($d) . " ";
                    }
                    $text .= "\n" . str_repeat("-", 40) . "\n";

                    // Isi tabel
                    $no = 1;
                    foreach ($presensiData as $siswa) {
                        $text .= str_pad($no++, 4);
                        $kelasNoSpace = $siswa['kelas'] . " | " . $siswa['nis'] . " | " . $siswa['nohp'];
                        $namaBaris = str_pad(substr($siswa['nama'], 0, 18), 18);
                        $kelasBaris = str_pad($kelasNoSpace, 20);

                        $text .= $namaBaris;
                        foreach ($dates as $d) {
                            $text .= $siswa['kehadiran'][$d] ?? '❌';
                            $text .= " ";
                        }
                        $text .= "\n";
                        $text .= str_repeat(' ', 4) . $kelasBaris . "\n";
                    }

                    $text .= "```";
                    $sendmsg = $text;
                } else {
                    $sendmsg = "Tidak ditemukan data siswa untuk '$selected'.";
                }
            } else {
                $sendmsg = "❌ *Pilihan Tidak Valid!*\n\nAnda berada di *menu rekap berdasarkan pilihan sebelumnya*, namun input Anda tidak dikenali.\n\n🔁 Untuk mengulang, balas dengan:\n`cek rekap`\n\n📌 Jika ingin mengakses menu lain, silakan kirim perintah menu yang sesuai.";
            }

            unlink($penangguhanFile);
        }
    } elseif ($sub1 === 'wa') {

        // === BAGIAN 1: All Time ===
        $query = "SELECT HOUR(timestamp) AS hour, COUNT(*) AS count
              FROM tmp
              GROUP BY hour
              ORDER BY hour";
        $result = $conn->query($query);

        $counts = array_fill(0, 24, 0);
        $total_access = 0;
        while ($row = $result->fetch_assoc()) {
            $hour = (int) $row['hour'];
            $count = (int) $row['count'];
            $counts[$hour] = $count;
            $total_access += $count;
        }

        function makeBar($percent, $maxBars = 30)
        {
            $bars = round($percent / 100 * $maxBars);
            return str_repeat("█", $bars);
        }

        $sendmsg = "📊 Persentase Akses WA per Jam (All Time)\n\n";

        for ($h = 0; $h < 24; $h++) {
            if ($total_access == 0) {
                $percent = 0;
            } else {
                $percent = ($counts[$h] / $total_access) * 100;
            }
            if ($percent == 0)
                continue;

            $bar = makeBar($percent);
            $hour_label = str_pad($h, 2, "0", STR_PAD_LEFT) . ":00";
            $percent_text = number_format($percent, 1) . "%";

            $sendmsg .= "$hour_label → $bar $percent_text ({$counts[$h]})\n";
        }

        if (trim($sendmsg) === "📊 Persentase Akses WA per Jam (All Time)") {
            $sendmsg .= "Tidak ada data akses WA.\n";
        }

        // === BAGIAN 2: Hari Ini ===
        $query_today = "SELECT HOUR(timestamp) AS hour, COUNT(*) AS count
                    FROM tmp
                    WHERE DATE(timestamp) = CURDATE()
                    GROUP BY hour
                    ORDER BY hour";
        $result_today = $conn->query($query_today);

        $counts_today = array_fill(0, 24, 0);
        $total_access_today = 0;
        while ($row = $result_today->fetch_assoc()) {
            $hour = (int) $row['hour'];
            $count = (int) $row['count'];
            $counts_today[$hour] = $count;
            $total_access_today += $count;
        }

        $sendmsg .= "\n📊 Persentase Akses WA per Jam (Hari Ini)\n\n";

        for ($h = 0; $h < 24; $h++) {
            if ($total_access_today == 0) {
                $percent_today = 0;
            } else {
                $percent_today = ($counts_today[$h] / $total_access_today) * 100;
            }
            if ($percent_today == 0)
                continue;

            $bar_today = makeBar($percent_today);
            $hour_label = str_pad($h, 2, "0", STR_PAD_LEFT) . ":00";
            $percent_text_today = number_format($percent_today, 1) . "%";

            $sendmsg .= "$hour_label → $bar_today $percent_text_today ({$counts_today[$h]})\n";
        }

        if (trim($sendmsg) === "📊 Persentase Akses WA per Jam (Hari Ini)") {
            $sendmsg .= "Tidak ada data akses WA hari ini.";
        }
    } elseif ($sub1 === 'presensi') {

        // === CEK PRESENSI ===

        if (!$sub2) {
            // ============================
            // 1. Grafik Presensi per Jam (24 jam penuh)
            // ============================
            $grafik = "";
            $dataJam = array_fill(0, 24, 0);

            $result = mysqli_query($conn, "
                SELECT HOUR(timestamp) AS jam, COUNT(DISTINCT nis) AS jumlah
                FROM presensi
                WHERE DATE(timestamp) = CURDATE()
                GROUP BY jam
            ");

            while ($row = mysqli_fetch_assoc($result)) {
                $dataJam[(int) $row['jam']] = $row['jumlah'];
            }

            $maxJumlah = max($dataJam);
            $maxBlok = 20;

            foreach ($dataJam as $jam => $jumlah) {
                if (!empty($jumlah)) {
                    $jamStr = str_pad($jam, 2, '0', STR_PAD_LEFT) . ":00";
                    $panjangBar = ($maxJumlah > 0) ? round(($jumlah / $maxJumlah) * $maxBlok) : 0;
                    $bar = str_repeat("█", $panjangBar);
                    $grafik .= "$jamStr → $bar $jumlah\n";
                }
            }

            // ============================
            // 2. Grafik Aktivitas Harian (7 hari terakhir)
            // ============================
            $grafik7hari = "";
            $dataHari = [];
            $dhary = 15;

            // Buat array tanggal 10 hari terakhir (mundur dari hari ini)
            for ($i = $dhary; $i >= 0; $i--) {
                $tgl = date('Y-m-d', strtotime("-$i day"));
                $dataHari[$tgl] = 0;
            }

            $result = mysqli_query($conn, "
                SELECT DATE(timestamp) AS tgl, COUNT(DISTINCT nis) AS jumlah
                FROM presensi
                WHERE timestamp >= DATE_SUB(CURDATE(), INTERVAL $dhary DAY)
                AND DATE(`timestamp`) <= CURDATE()
                GROUP BY tgl
            ");

            while ($row = mysqli_fetch_assoc($result)) {
                $dataHari[$row['tgl']] = $row['jumlah'];
            }

            $maxJumlahHari = max($dataHari);
            $maxBlokHari = 20;

            foreach ($dataHari as $tgl => $jumlah) {
                $namahari2 = hari_indonesia2($tgl);
                $hariLabel = date('d/m', strtotime($tgl));
                $panjangBar = ($maxJumlahHari > 0) ? round(($jumlah / $maxJumlahHari) * $maxBlokHari) : 0;
                $bar = str_repeat("█", $panjangBar);
                $grafik7hari .= "$hariLabel ($namahari2) $bar $jumlah\n";
            }

            // ============================
            // Persentase Presensi per Jam (All Time)
            // ============================
            $grafikPersen = "";
            $dataJamPersen = array_fill(0, 24, 0);

            // Hitung jumlah presensi per jam
            $result = mysqli_query($conn, "
                SELECT HOUR(timestamp) AS jam, COUNT(*) AS total
                FROM presensi
                WHERE DATE(`timestamp`) <= CURDATE()
                GROUP BY jam
            ");

            $totalKeseluruhan = 0;
            while ($row = mysqli_fetch_assoc($result)) {
                $dataJamPersen[(int) $row['jam']] = $row['total'];
                $totalKeseluruhan += $row['total'];
            }

            // Hitung persentase per jam
            foreach ($dataJamPersen as $jam => $jumlah) {
                $dataJamPersen[$jam] = ($totalKeseluruhan > 0)
                    ? round(($jumlah / $totalKeseluruhan) * 100, 1)
                    : 0;

            }

            // Cari max untuk skala bar
            $maxPersen = max($dataJamPersen);
            $maxBlok = 20;

            foreach ($dataJamPersen as $jam => $persen) {
                if (!empty($persen)) {
                    $jamStr = str_pad($jam, 2, '0', STR_PAD_LEFT) . ":00";
                    $panjangBar = ($maxPersen > 0) ? round(($persen / $maxPersen) * $maxBlok) : 0;
                    $bar = str_repeat("█", $panjangBar);
                    $grafikPersen .= "$jamStr → $bar {$persen}%\n";
                }
            }

            // ============================
            // Data Ektrim
            // ============================
            // 1. Ambil data per hari
            $sqlHarian = "
                SELECT DATE(timestamp) AS tanggal, COUNT(*) AS jumlah
                FROM presensi
                WHERE DATE(`timestamp`) <= CURDATE()
                GROUP BY DATE(timestamp)
                HAVING jumlah > 0
                ORDER BY jumlah DESC
            ";
            $resultHarian = $conn->query($sqlHarian);

            $dataHarian = [];
            while ($row = $resultHarian->fetch_assoc()) {
                $dataHarian[] = $row;
            }

            // 2. Cek kalau ada data
            if (count($dataHarian) > 0) {
                // Hari terbanyak (paling atas)
                $hariMax = $dataHarian[0];

                // Hari terkecil > 0 (paling bawah)
                $hariMin = $dataHarian[count($dataHarian) - 1];

                $maxJumlah = $hariMax['jumlah']; // untuk skala bar
                $maxBlok = 20; // panjang maksimal bar

                // 3. Grafik hari terbanyak
                $barMax = str_repeat("█", round(($hariMax['jumlah'] / $maxJumlah) * $maxBlok));
                $grafikHariMax = sprintf(
                    "%s →\n%-20s %d siswa",
                    date('d-m-Y', strtotime($hariMax['tanggal'])),
                    $barMax,
                    $hariMax['jumlah']
                );

                // 4. Grafik hari terkecil (> 0)
                $barMin = str_repeat("█", round(($hariMin['jumlah'] / $maxJumlah) * $maxBlok));
                $grafikHariMin = sprintf(
                    "%s →\n%-2s %d siswa",
                    date('d-m-Y', strtotime($hariMin['tanggal'])),
                    $barMin,
                    $hariMin['jumlah']
                );
            }

            // ============================
            // --- GRAFIK JUMLAH PER STATUS (Hari Ini) ---
            // ============================

            $grafikStstushariini = "";
            $ikonMap = [
                'masuk' => '✅',
                'izin' => '🔵',
                'sakit' => '🟡',
                'libur' => '🔴'
            ];

            // Ganti `timestamp` dengan nama kolom waktu di tabel presensi kamu
            $sqlStatus = "
                SELECT ket, COUNT(*) AS jumlah
                FROM presensi
                WHERE DATE(`timestamp`) = CURDATE()
                GROUP BY ket
            ";
            $resultStatus = $conn->query($sqlStatus);

            $dataStatus = [];
            $maxJumlahStatus = 0;

            while ($row = $resultStatus->fetch_assoc()) {
                $dataStatus[] = $row;
                if ($row['jumlah'] > $maxJumlahStatus) {
                    $maxJumlahStatus = $row['jumlah'];
                }
            }

            if ($maxJumlahStatus > 0) {
                $grafikStstushariini .= "\n📊 Jumlah Presensi Hari Ini Berdasarkan Status:\n";
                foreach ($dataStatus as $row) {
                    $status = $row['ket'];
                    $jumlah = $row['jumlah'];
                    $ikon = $ikonMap[$status] ?? '❓';
                    $bar = str_repeat("█", round(($jumlah / $maxJumlahStatus) * 20));
                    $grafikStstushariini .= sprintf(
                        "%s %-6s → %-2s %d\n",
                        $ikon,
                        ucfirst($status),
                        $bar,
                        $jumlah
                    );
                }
            }
            // ============================
            // --- GRAFIK PERSEN PER STATUS (Semua Data) ---
            // ============================

            $grafikStstus = "";
            $ikonMap = [
                'masuk' => '✅',
                'izin' => '🔵',
                'sakit' => '🟡',
                'libur' => '🔴'
            ];

            $sqlStatus = "
                SELECT ket, COUNT(*) AS jumlah
                FROM presensi
                WHERE DATE(`timestamp`) <= CURDATE()
                GROUP BY ket
            ";
            $resultStatus = $conn->query($sqlStatus);

            $dataStatus = [];
            $totalSemua = 0;

            // Hitung total seluruh presensi
            while ($row = $resultStatus->fetch_assoc()) {
                $dataStatus[] = $row;
                $totalSemua += $row['jumlah'];
            }

            if ($totalSemua > 0) {
                $grafikStstus .= "\n📊 Persentase Presensi Berdasarkan Status:\n";
                foreach ($dataStatus as $row) {
                    $status = $row['ket'];
                    $jumlah = $row['jumlah'];
                    $persen = round(($jumlah / $totalSemua) * 100); // dibulatkan tanpa koma
                    $ikon = $ikonMap[$status] ?? '❓';
                    $bar = str_repeat("█", round($persen / 5)); // skala bar 0-20 blok
                    $grafikStstus .= sprintf(
                        "%s %-6s → %-2s %d%%\n",
                        $ikon,
                        ucfirst($status),
                        $bar,
                        $persen
                    );
                }
            }

            // ============================
            // Gabungkan ke pesan WA
            // ============================
            // Jumlah siswa yang presensi hari ini
            $result = mysqli_query($conn, "SELECT COUNT(DISTINCT nis) as hadir FROM presensi WHERE DATE(timestamp) = CURDATE()");
            $dataHadir = mysqli_fetch_assoc($result);
            $jumlahHadir = $dataHadir['hadir'] ?? 0;

            // Total seluruh siswa
            $result = mysqli_query($conn, "SELECT COUNT(*) as total FROM datasiswa");
            $dataTotal = mysqli_fetch_assoc($result);
            $totalSiswa = $dataTotal['total'] ?? 0;

            $jumlahBelumHadir = $totalSiswa - $jumlahHadir;

            // Hitung persentase
            $persentase = $totalSiswa > 0 ? round(($jumlahHadir / $totalSiswa) * 100, 2) : 0;
            $persenBelumHadir = $totalSiswa > 0 ? round(($jumlahBelumHadir / $totalSiswa) * 100, 2) : 0;

            // Jumlah siswa yang memiliki nomor HP (tidak NULL dan tidak kosong)
            $result = mysqli_query($conn, "SELECT COUNT(*) as punya_nohp FROM datasiswa WHERE nohp IS NOT NULL AND TRIM(nohp) != ''");
            $dataPunyaNoHP = mysqli_fetch_assoc($result);
            $jumlahNoHP = $dataPunyaNoHP['punya_nohp'];
            $jumlahBelumNoHP = $totalSiswa - $jumlahNoHP;

            // Hitung persentase yang punya nomor HP
            $persenNoHP = $totalSiswa > 0 ? round(($jumlahNoHP / $totalSiswa) * 100, 2) : 0;
            $persenBelumNoHP = $totalSiswa > 0 ? round(($jumlahBelumNoHP / $totalSiswa) * 100, 2) : 0;

            $sendmsg = "📊 *Rekap Presensi*\n";
            $sendmsg .= "Per: " . formatTanggalIndo(date('Y-m-d H:i:s')) . " Pukul " . date('H:i:s') . "\n\n";
            $sendmsg .= "```";
            $sendmsg .= "\n👥 Total siswa     : $totalSiswa siswa";
            $sendmsg .= "\n✅ Sudah presensi  : $jumlahHadir siswa ($persentase%)";
            $sendmsg .= "\n❌ Belum presensi  : $jumlahBelumHadir siswa ($persenBelumHadir%)";
            $sendmsg .= "\n📱 WA terdaftar    : $jumlahNoHP siswa ($persenNoHP%)";
            $sendmsg .= "\n⚠️ WA tak terdaftar: $jumlahBelumNoHP siswa ($persenBelumNoHP%)";
            $sendmsg .= "\n```";

            $sendmsg .= "\n\n📈 *Aktivitas Presensi per Jam (Hari Ini)*\n";
            $sendmsg .= "```\n$grafik```";
            $sendmsg .= "```\n$grafikStstushariini```";

            $sendmsg .= "\n\n📊 *Persentase Presensi per Jam (All Time)*\n";
            $sendmsg .= "```\n$grafikPersen```";
            $sendmsg .= "```\n$grafikStstus```";

            $sendmsg .= "\n\n📅 *Aktivitas Presensi $dhary Hari Terakhir*\n";
            $sendmsg .= "```\n$grafik7hari```";

            $sendmsg .= "\n📆 Aktivitas Ekstrem:";
            $sendmsg .= "\n📈 Tertinggi : " . $grafikHariMax;
            $sendmsg .= "\n📉 Terendah  : " . $grafikHariMin;

            $sendmsg .= "\n\n📌 Data ini bersifat *real-time* dan akan terus diperbarui.";

        } else {
            $target = trim($sub2);
            if (is_numeric($target)) {
                $isNIS = strlen($target) <= 10;
                if ($isNIS) {
                    $result = mysqli_query($conn, "SELECT * FROM presensi WHERE nis = '$target' AND DATE(timestamp) = CURDATE()");
                } else {
                    $target = normalisasi_nomor_62_ke_0($target);
                    $result = mysqli_query($conn, "SELECT p.* FROM presensi p JOIN datasiswa d ON p.nis = d.nis WHERE d.nohp = '$target' AND DATE(p.timestamp) = CURDATE()");
                }
                $sendmsg = mysqli_num_rows($result) > 0
                    ? "✅ *Sudah presensi hari ini.*\n\n👍"
                    : "❌ *Belum presensi hari ini.*\n\n😊";
                $sendmsg .= "";
            } else {
                $sendmsg = "❌ *Format Salah*\n\nGunakan format berikut:\n`cek presensi <NIS/NoHP>`\n\n📌 Contoh:\n- `cek presensi 1234`\n- `cek presensi 6281234567890`\n\nSilakan koreksi dan kirim ulang ya 😊";
            }
        }
    } elseif ($sub1 === 'rekap' && $sub2) {

        // === CEK REKAP INDIVIDU BERDASARKAN NIS / NOHP ===

        include "../config/koneksi.php";
        $input = trim($sub2);

        // Tentukan apakah input adalah NIS atau no HP
        $nis = '';
        if (is_numeric($input)) {
            if (strlen($input) < 10) {
                $nis = $input; // NIS langsung
            } else {
                // Cari NIS berdasarkan noHP
                $input = normalisasi_nomor_62_ke_0($input);
                $query = mysqli_query($conn, "SELECT nis FROM datasiswa WHERE nohp = '$input' LIMIT 1");
                if ($row = mysqli_fetch_assoc($query)) {
                    $nis = $row['nis'];
                } else {
                    $sendmsg = "❌ Nomor HP tidak ditemukan dalam data siswa.";
                    return;
                }
            }
        } else {
            // berdasarkan kelas
            // $sendmsg = "❌ Sepertinya anda ingin mengamnil data rekap kelas.";
        }

        if ($nis) {
            // Ambil data siswa
            $siswa = mysqli_query($conn, "SELECT nama, kelas, nohp FROM datasiswa WHERE nis = '$nis' LIMIT 1");
            if (!$row = mysqli_fetch_assoc($siswa)) {
                $sendmsg = "❌ NIS tidak ditemukan dalam data siswa.";
                return;
            }

            $nama = $row['nama'];
            $kelas = $row['kelas'];
            $nohp = $row['nohp'];

            // Ambil semua data presensi berdasarkan NIS
            $res = mysqli_query($conn, "SELECT DATE(timestamp) as tanggal, ket FROM presensi WHERE nis = '$nis' ORDER BY tanggal ASC");
            if (mysqli_num_rows($res) == 0) {
                $sendmsg = "📋 *Rekap Presensi*\n\nNama: $nama\nKelas: $kelas\nNIS: $nis\nNo HP: $nohp\n\nTidak ditemukan data presensi.";
                return;
            }

            // Map ikon
            $ikonMap = [
                'masuk' => '✅',
                'izin' => '🔵',
                'sakit' => '🟡',
                'libur' => '🔴'
            ];


            // Susun data per tanggal dan bulan
            $data = [];
            $bulanLabels = [];

            // Tambahan: Inisialisasi counter
            $counter = [
                'masuk' => 0,
                'izin' => 0,
                'sakit' => 0,
                'libur' => 0
            ];

            while ($r = mysqli_fetch_assoc($res)) {
                $tgl = date('j', strtotime($r['tanggal']));
                $bln = date('n', strtotime($r['tanggal']));
                $namaBulan = date('F', strtotime($r['tanggal']));

                $ket = strtolower($r['ket']);
                $ikon = $ikonMap[$ket] ?? '❌';
                $data[$tgl][$bln] = $ikon;
                $bulanLabels[$bln] = $namaBulan;

                // Tambahan: Hitung jumlah berdasarkan ket
                if (isset($counter[$ket])) {
                    $counter[$ket]++;
                }
            }

            // Buat daftar bulan yang muncul
            ksort($bulanLabels);

            // Buat tabel
            $text = "```";
            $text .= "📋 Rekap Presensi\n";
            $text .= "Nama : $nama\n";
            $text .= "Kelas: $kelas\n";
            $text .= "NIS  : $nis\n\n";
            $text .= "Masuk : {$counter['masuk']} x\n";
            $text .= "Izin  : {$counter['izin']} x\n";
            $text .= "Sakit : {$counter['sakit']} x\n";
            $text .= "Libur : {$counter['libur']} x\n\n";
            $text .= str_pad("Tgl", 5);
            foreach ($bulanLabels as $bln => $namaBln) {
                $text .= str_pad(substr($namaBln, 0, 3), 5);
            }
            $text .= "\n";

            // Baris tanggal
            for ($tgl = 1; $tgl <= 31; $tgl++) {
                $barisIsi = false;
                $text .= str_pad($tgl, 5);
                foreach (array_keys($bulanLabels) as $bln) {
                    if (isset($data[$tgl][$bln])) {
                        $text .= str_pad($data[$tgl][$bln], 5);
                        $barisIsi = true;
                    } else {
                        $text .= str_pad("-", 5);
                    }
                }

                $text .= "\n";
            }

            $text .= "```\n";
            $text .= "Keterangan:\n✅ = Masuk\n🔵 = Izin\n🟡 = Sakit\n🔴 = Libur\n❌ = Tidak Presensi\n\n";
            $sendmsg = $text;
        } else {
            // berdasarkan kelas
            $input = strtolower($input); // Ubah ke huruf kecil semua agar tidak sensitif kapital

            // Cocokkan pola: tingkat (xi/xii), jurusan (at/dkv/te), lalu nomor
            if (preg_match('/^(x|xii|xii)(at|dkv|te)(\d+)$/', $input, $matches)) {
                // Pisahkan bagian-bagian
                $tingkat = strtoupper($matches[1]);  // XI atau XII
                $jurusan = strtoupper($matches[2]);  // AT, DKV, TE
                $nomor = $matches[3];              // Nomor kelas

                // Gabungkan kembali dengan format benar
                $input = "$tingkat $jurusan $nomor";

                // Query SQL
                $sql = "SELECT * FROM datasiswa WHERE kelas = '$input' ORDER BY nama";

                $result = mysqli_query($conn, $sql);

                if (mysqli_num_rows($result) > 0) {
                    // Ambil tanggal 6 hari terakhir
                    $dates = [];
                    for ($i = 5; $i >= 0; $i--) {
                        $dates[] = date('Y-m-d', strtotime("-$i days"));
                    }

                    // Siapkan map ikon berdasarkan keterangan
                    $ikonMap = [
                        'masuk' => '✅',
                        'izin' => '🔵',
                        'sakit' => '🟡',
                        'libur' => '🔴'
                    ];

                    // Bangun array dasar siswa
                    $presensiData = [];
                    while ($row = mysqli_fetch_assoc($result)) {
                        $nis = $row['nis'];
                        $presensiData[$nis] = [
                            'nama' => $row['nama'],
                            'kelas' => $row['kelas'],
                            'nohp' => $row['nohp'] ?: '-',
                            'nis' => $nis,
                            'kehadiran' => [] // isi manual di bawah
                        ];

                        // Set nilai default: ➖ untuk Sabtu/Minggu, ❌ untuk hari lainnya
                        foreach ($dates as $tanggal) {
                            $hari = date('N', strtotime($tanggal)); // 6 = Sabtu, 7 = Minggu
                            $presensiData[$nis]['kehadiran'][$tanggal] = ($hari == 6 || $hari == 7) ? '➖' : '❌';
                        }
                    }

                    // Ambil data presensi 6 hari terakhir untuk siswa-siswa tersebut
                    $daftarNIS = array_map(function ($d) {
                        return "'" . $d['nis'] . "'";
                    }, $presensiData);

                    $inNIS = implode(",", $daftarNIS);
                    $tanggalAwal = $dates[0];

                    $qPresensi = "SELECT nis, DATE(timestamp) as tanggal, LOWER(ket) as ket FROM presensi 
              WHERE nis IN ($inNIS) AND DATE(timestamp) >= '$tanggalAwal'";

                    $resPresensi = mysqli_query($conn, $qPresensi);
                    while ($row = mysqli_fetch_assoc($resPresensi)) {
                        $nis = $row['nis'];
                        $tanggal = $row['tanggal'];
                        $ket = $row['ket'];
                        $ikon = $ikonMap[$ket] ?? '❌';

                        if (isset($presensiData[$nis])) {
                            $presensiData[$nis]['kehadiran'][$tanggal] = $ikon;
                        }
                    }

                    // Bangun pesan rekap
                    $text = "```";
                    $text .= "📅 Rekap presensi 6 hari terakhir:\nKelas: $input\n";
                    $text .= "Keterangan:\n✅ = Masuk\n🔵 = Izin\n🟡 = Sakit\n🔴 = Libur\n➖ = Libur Weekend\n❌ = Tidak Presensi\n\n";

                    // Header tabel
                    // Header tanggal
                    $text .= str_pad("No", 4) . str_pad("Nama & Kelas", 18);
                    foreach ($dates as $d) {
                        $text .= date('d', strtotime($d)) . "'";
                    }
                    $text .= "\n";

                    // Header hari
                    $text .= str_repeat(" ", 4) . str_repeat(" ", 18);
                    foreach ($dates as $d) {
                        $text .= hari_indonesia2($d) . " ";
                    }
                    $text .= "\n" . str_repeat("-", 40) . "\n";

                    // Isi tabel
                    $no = 1;
                    foreach ($presensiData as $siswa) {
                        $text .= str_pad($no++, 4);
                        $kelasNoSpace = $siswa['kelas'] . " | " . $siswa['nis'] . " | " . $siswa['nohp'];
                        $namaBaris = str_pad(substr($siswa['nama'], 0, 18), 18);
                        $kelasBaris = str_pad($kelasNoSpace, 20);

                        $text .= $namaBaris;
                        foreach ($dates as $d) {
                            $text .= $siswa['kehadiran'][$d] ?? '❌';
                            $text .= " ";
                        }
                        $text .= "\n";
                        $text .= str_repeat(' ', 4) . $kelasBaris . "\n";
                    }

                    $text .= "```";
                    $sendmsg = $text;
                } else {
                    $sendmsg = "Tidak ditemukan data siswa untuk `cek rekap $input`.";
                }
            } else {
                $sendmsg = "Format kelas tidak valid. Gunakan format seperti 'xiat1', 'xiidkv2', dst. tanpa spasi";
            }
        }
    } elseif (strtolower($message) === 'cek rekap') {

        // === CEK REKAP PERTAMA KALI ===

        if (!is_dir('rekap_tmp'))
            mkdir('rekap_tmp', 0777, true);
        $penangguhanData = ['step' => ['cek rekap'], 'menu' => 'rekap'];
        file_put_contents($penangguhanFile, json_encode($penangguhanData));

        $sendmsg = "📊 *Menu Rekap Presensi*\n\nAnda meminta rekap data presensi. Silakan pilih salah satu menu berikut dengan membalas 2 digit angka di depannya:\n\n";
        $sendmsg .= "01. Rekap berdasarkan *KELAS*\n";
        $sendmsg .= "02. Rekap berdasarkan *PEMBIMBING*\n";
        $sendmsg .= "03. Rekap berdasarkan *DUDI*\n\n";
        $sendmsg .= "🔁 *Balas dengan hanya angka 2 digit*, contoh: `01`";
        $sendmsg .= "";
    } else {
        // Cek apakah nomor HP atau NIS terdaftar
        $target = $sub1;
        if (is_numeric($target)) {
            $query = "SELECT * FROM datasiswa WHERE nis = '$target'";
            $result = mysqli_query($conn, $query);
            if (mysqli_num_rows($result) > 0) {
                $row = mysqli_fetch_assoc($result);
                $nohp = isset($row['nohp']) ? $row['nohp'] : "Tidak ada nomor WA";
                $sendmsg = "✅ *NIS ditemukan!*\n\n👤 *Nama:* {$row['nama']}\n🏫 *Kelas:* {$row['kelas']}\n🆔 *NIS:* {$row['nis']}\n📱 *No WA:* $nohp\n\n📌 Data ini terdaftar di sistem presensi.";
            } else {
                $target = normalisasi_nomor_62_ke_0($target);
                $query = "SELECT * FROM datasiswa WHERE nohp = '$target'";
                $result = mysqli_query($conn, $query);
                if (mysqli_num_rows($result) > 0) {
                    $row = mysqli_fetch_assoc($result);
                    $sendmsg = "✅ *Nomor ditemukan!*\n\n📱 *Nomor:* $target\n👤 *Nama:* {$row['nama']}\n🏫 *Kelas:* {$row['kelas']}\n🆔 *NIS:* {$row['nis']}\n\n📌 Data ini terdaftar di sistem presensi.";
                } else {
                    $target = normalisasi_nomor_0_ke_62($target);
                    $query = "SELECT * FROM datapembimbing WHERE nohp = '$target'";
                    $result = mysqli_query($conn, $query);
                    if (mysqli_num_rows($result) > 0) {
                        $row = mysqli_fetch_assoc($result);
                        $sendmsg = "✅ *Nomor ditemukan!*\n\n📱 *Nomor:* $target\n👤 *Nama:* {$row['nama']}\n🆔 *NIP:* {$row['nip']}\n\n📌 Data ini terdaftar di sistem presensi sebagai Pembimbing PKL $tahun.";
                    } else {
                        $sendmsg = "❌ *Nomor atau NIS tidak ditemukan di sistem.*\n\n📌 Pastikan Anda sudah terdaftar dengan benar.\nJika belum, silakan lakukan pendaftaran terlebih dahulu atau hubungi admin untuk bantuan.";
                    }
                }
            }
        } else {
            // Cek apakah nomor terdaftar di datasiswa'
            $target = normalisasi_nomor_62_ke_0($target);
            $query = "SELECT * FROM datasiswa WHERE nohp = '$number'";
            $result = mysqli_query($conn, $query);

            if (mysqli_num_rows($result) > 0) {
                $row = mysqli_fetch_assoc($result);
                $nis = $row['nis'];
                $nama = $row['nama'];
                $kelas = $row['kelas'];

                // Cek apakah sudah presensi hari ini
                $cekPresensi = mysqli_query($conn, "SELECT * FROM presensi WHERE nis = '$nis' AND DATE(timestamp) = CURDATE()");
                $status = mysqli_num_rows($cekPresensi) > 0 ? "✅ Sudah presensi hari ini." : "❌ Belum presensi hari ini.";

                $sendmsg = "📱 *Nomor Ini Terdaftar*\n\n👤 *Nama:* $nama\n🏫 *Kelas:* $kelas\n🆔 *NIS:* $nis\n\n📌 *Status Presensi:* $status";
            } else {
                // Cek apakah nomor adalah pembimbing
                $number = normalisasi_nomor_0_ke_62($number);
                $queryPembimbing = "SELECT * FROM datapembimbing WHERE nohp = '$number'";
                $resultPembimbing = mysqli_query($conn, $queryPembimbing);

                if (mysqli_num_rows($resultPembimbing) > 0) {
                    $rowPembimbing = mysqli_fetch_assoc($resultPembimbing);
                    $namaPembimbing = $rowPembimbing['nama'];

                    // Ambil data penempatan berdasarkan nama pembimbing
                    $queryPenempatan = "SELECT * FROM penempatan WHERE nama_pembimbing = '$namaPembimbing'";
                    $resultPenempatan = mysqli_query($conn, $queryPenempatan);

                    // Kelompokkan data siswa berdasarkan DUDI
                    $dataDudi = [];
                    while ($row = mysqli_fetch_assoc($resultPenempatan)) {
                        $dudi = $row['nama_dudika'];
                        $dataDudi[$dudi][] = [
                            'nama' => $row['nama_siswa'],
                            'nis' => $row['nis_siswa'],
                            'kelas' => $row['kelas']
                        ];
                    }

                    // Peta ikon presensi
                    $ikonMap = [
                        'masuk' => '✅',
                        'izin' => '🔵',
                        'sakit' => '🟡',
                        'libur' => '🔴'
                    ];

                    // Ambil tanggal 7 hari terakhir
                    $tanggal7Hari = [];
                    for ($i = 6; $i >= 0; $i--) {
                        $tanggal7Hari[] = date('Y-m-d', strtotime("-$i days"));
                    }

                    // Susun pesan
                    $msg = "📱 *Nomor Ini Terdaftar Sebagai Pembimbing*\n\n👨‍🏫 *Nama:* {$rowPembimbing['nama']}\n" .
                        (!empty($rowPembimbing['nip']) ? "🆔 *NIP:* {$rowPembimbing['nip']}\n" : "") .
                        "\n📋 *Daftar DUDI dan Siswa + Rekap Kehadiran 7 Hari:*\n```";

                    $msg .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
                    $msg .= "      ";

                    foreach ($tanggal7Hari as $tgl) {
                        $msg .= hari_indonesia($tgl) . " ";
                    }
                    $msg .= "\n      ";

                    foreach ($tanggal7Hari as $tgl) {
                        $msg .= date('d', strtotime($tgl)) . "  ";
                    }
                    $msg .= "\n";
                    $msg .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

                    $i = 1;
                    foreach ($dataDudi as $dudi => $siswaList) {
                        $msg .= "$i. $dudi\n";
                        $j = 1;
                        foreach ($siswaList as $siswaData) {
                            $siswa = $siswaData['nama'];
                            $nis = $siswaData['nis'];
                            $kelas = $siswaData['kelas'];

                            $qNoHP = "SELECT nohp FROM datasiswa WHERE nis = '$nis' LIMIT 1";
                            $resNoHP = mysqli_query($conn, $qNoHP);
                            $rowNoHP = mysqli_fetch_assoc($resNoHP);
                            $nohp = isset($rowNoHP['nohp']) ? $rowNoHP['nohp'] : '-Belum Reg-';

                            // Ambil data presensi 7 hari terakhir untuk siswa ini
                            $rekap = "";
                            foreach ($tanggal7Hari as $tgl) {
                                // Cek apakah hari ini Sabtu atau Minggu
                                $hari = date('D', strtotime($tgl));
                                $isWeekend = ($hari === 'Sat' || $hari === 'Sun');

                                // Cek apakah siswa presensi di tanggal itu
                                $qPresensi = "SELECT ket FROM presensi WHERE nis = '$nis' AND DATE(timestamp) = '$tgl' LIMIT 1";
                                $resPresensi = mysqli_query($conn, $qPresensi);

                                if ($rowPresensi = mysqli_fetch_assoc($resPresensi)) {
                                    $ket = strtolower($rowPresensi['ket']);
                                    $ikon = isset($ikonMap[$ket]) ? $ikonMap[$ket] : '❌';
                                } else {
                                    $ikon = $isWeekend ? '➖' : '❌';
                                }

                                $rekap .= $ikon . " ";
                            }

                            // $msg .= "   $j) $siswa\n      ($kelas)\n      $rekap\n";
                            $msg .= "   $j) $siswa\n      ($kelas | $nis | $nohp)\n      $rekap\n";
                            $j++;
                        }
                        $i++;
                    }

                    $msg .= "Keterangan:\n✅ = Masuk\n🔵 = Izin\n🟡 = Sakit\n🔴 = Libur\n➖ = Libur Weekend\n❌ = Tidak Presensi\n\n";
                    $msg .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━";

                    $msg .= "```\n";
                    $msg .= "*📌 Layanan Presensi PKL untuk Pembimbing*\n\n";
                    $msg .= "Berikut perintah yang tersedia:\n\n";
                    $msg .= "1️⃣ `cek`\n    ➜ Lihat status nomor Anda.\n\n";
                    $msg .= "2️⃣ `cek <NIS/NoHP>`\n    ➜ Lihat data siswa.\n    Contoh: `cek 1234` atau `cek 089123456789`\n\n";
                    $msg .= "3️⃣ `cek rekap`\n    ➜ Lihat rekap *Kelas*, *Pembimbing*, atau *DUDI*.\n\n";
                    $msg .= "4️⃣ `cek presensi <NIS/NoHP>`\n    ➜ Presensi individu hari ini.\n    Contoh: `cek presensi 1234`\n\n";
                    $msg .= "5️⃣ `cek rekap <NIS/NoHP>`\n    ➜ Rekap semua presensi individu.\n    Contoh: `cek rekap 1234`\n\n";
                    $msg .= "6️⃣ `cek rekap <KELAS>`\n    ➜ Rekap presensi per kelas.\n    Contoh: `cek rekap xiat1`\n\n";
                    $msg .= "💡 *Tips*: Gunakan huruf kecil tanpa spasi untuk kode kelas.\n";
                    $msg .= "📢 Ada data yang salah? Beri tahu Admin ➜ Balas dengan ketik `Admin`.";

                    $sendmsg = $msg;
                } else {
                    // Jika tidak ditemukan sebagai siswa maupun pembimbing
                    $sendmsg = "🚫 *Nomor WhatsApp ini belum terdaftar di sistem presensi.*\n\n📌 Jika kamu adalah siswa, pastikan nomor ini sudah didaftarkan oleh admin atau pembimbing.\n\n🔰 Untuk mendaftar mandiri, silakan ketik:\n`REG <spasi> NIS`\n\nContoh:\n`reg 1234`\n\nJika mengalami kendala, hubungi admin dengan ketik `admin` atau `7`.";
                }
            }
        }
    }

    $conn->close();
    // $sendmsg .= "\n\n_*) Fitur ini masih tahap uji coba_";

} elseif (strpos(strtolower($message), 'input') === 0) {
    $parts = preg_split('/\s+/', trim($message));
    if (count($parts) < 3) {
        $sendmsg = "⚠️ *Format Salah!*\n\n";
        $sendmsg .= "📅 *Untuk hari ini:*\n";
        $sendmsg .= "`input <nis/noHP> <keterangan>`\n\n";
        $sendmsg .= "📆 *Untuk rentang tanggal:*\n";
        $sendmsg .= "`input <nis/noHP> <keterangan> <tgl_awal> <tgl_akhir>`\n\n";
        $sendmsg .= "ℹ️ *Keterangan:* masuk, izin, sakit, libur.";
    } else {
        include "../config/koneksi.php";

        // --- CEK NOMOR PENGIRIM (PEMBIMBING) ---
        $numberx = normalisasi_nomor_0_ke_62($number);
        $stmt = $conn->prepare("SELECT kode FROM datapembimbing WHERE nohp = ?");
        $stmt->bind_param("s", $numberx);
        $stmt->execute();
        $stmt->bind_result($kodePembimbing);
        if (!$stmt->fetch()) {
            $sendmsg = "⚠️ *Akses Ditolak*\n";
            $sendmsg .= "Nomor Anda ($numberx) tidak terdaftar sebagai *Pembimbing* dalam sistem.\n\n";
            $sendmsg .= "📌 Jika Anda merasa ini keliru, silakan hubungi admin untuk verifikasi.";
        } else {
            $stmt->close();

            $targetId = trim($parts[1]);
            $keterangan = strtolower(trim($parts[2]));
            $validKet = ['masuk', 'izin', 'sakit', 'libur'];
            if (!in_array($keterangan, $validKet)) {
                $sendmsg = "⚠️ Keterangan '$targetId' tidak valid!\n📌 Gunakan salah satu dari: masuk, izin, sakit, libur.\n📌 Pastikan ejaan benar.";
            } else {
                $nohp_target = null;

                // --- AMBIL DATA SISWA ---
                if (is_numeric($targetId) && strlen($targetId) <= 15) {
                    // Cek dari NIS atau noHP
                    $stmt = $conn->prepare("SELECT nis, nama, kelas, nohp FROM datasiswa WHERE nis = ? OR nohp = ?");
                    $stmt->bind_param("ss", $targetId, $targetId);
                } else {
                    $stmt = $conn->prepare("SELECT nis, nama, kelas, nohp FROM datasiswa WHERE nis = ?");
                    $stmt->bind_param("s", $targetId);
                }
                $stmt->execute();
                $stmt->bind_result($nis, $namaSiswa, $kelas, $nohp_target);
                if (!$stmt->fetch()) {
                    $sendmsg = "⚠️ *Data Tidak Ditemukan*\n";
                    $sendmsg .= "Tidak ada siswa dengan NIS/NoHP: `$targetId`.\n\n";
                    $sendmsg .= "📌 Pastikan:\n";
                    $sendmsg .= "- NIS/NoHP yang dimasukkan benar.\n";
                } else {
                    $stmt->close();

                    $tanggalList = [];
                    $minDate = strtotime("2025-07-01");
                    $maxDate = strtotime("2025-12-31");

                    // --- Tentukan tanggal ---
                    if (count($parts) == 3) {
                        $tanggalList[] = date('Y-m-d'); // Hari ini
                    } elseif (count($parts) == 4) {
                        $tglAwal = normalizeTanggal($parts[3]);
                        $start = strtotime($tglAwal);
                        $tanggalList[] = date("Y-m-d", $start);
                    } elseif (count($parts) >= 5) {
                        $tglAwal = normalizeTanggal($parts[3]);
                        $tglAkhir = normalizeTanggal($parts[4]);
                        if (!$tglAwal || !$tglAkhir) {
                            $sendmsg = "Format tanggal tidak valid.";
                        } else {
                            $start = strtotime($tglAwal);
                            $end = strtotime($tglAkhir);
                            if ($end < $start) {
                                $sendmsg = "Tanggal akhir tidak boleh sebelum tanggal awal.";
                            } else {
                                if (strtotime($tglAwal) < $minDate || strtotime($tglAkhir) > $maxDate) {
                                    $sendmsg = "Tanggal di luar rentang yang diizinkan (" . formatTanggalIndo(date("Y-m-d", $minDate)) . " - " . formatTanggalIndo(date("Y-m-d", $maxDate)) . ".";
                                } else {
                                    while ($start <= $end) {
                                        $tanggalList[] = date('Y-m-d', $start);
                                        $start = strtotime("+1 day", $start);
                                    }
                                }
                            }
                        }
                    } else {
                        $sendmsg = "❗ *Format Salah!*\n\nGunakan format:\n`input <NIS/NoHP> <keterangan> <tgl_awal> <tgl_akhir>`\n\nContoh:\n`input 12345 masuk 22-07-2025 24-07-2025`\n`input 081234567890 izin 01/08/2025 03/08/2025`\n\n📌 Catatan:\n- Keterangan: `masuk` | `izin` | `sakit` | `libur`\n- Format tanggal: `dd-mm-yyyy` atau `dd/mm/yyyy` (pakai dua digit)\n- Jangan gunakan tanda baca di antara parameter.\n\nSilakan koreksi dan kirim ulang.";
                    }

                    // --- Proses Input ---
                    if (empty($sendmsg)) {
                        $resultList = [];
                        foreach ($tanggalList as $tanggal) {
                            // Cek presensi sebelumnya dan ambil keterangannya
                            $stmt = $conn->prepare("SELECT ket FROM presensi WHERE nis = ? AND DATE(timestamp) = ?");
                            $stmt->bind_param("ss", $nis, $tanggal);
                            $stmt->execute();
                            $stmt->store_result();

                            if ($stmt->num_rows == 0) {
                                $stmt->close();
                                // Insert baru
                                $stmt = $conn->prepare("INSERT INTO presensi (nis, namasiswa, kelas, ket, kode, timestamp) VALUES (?, ?, ?, ?, ?, ?)");
                                $ts = $tanggal . " 07:00:00"; // jam default
                                $stmt->bind_param("ssssss", $nis, $namaSiswa, $kelas, $keterangan, $kodePembimbing, $ts);
                                if ($stmt->execute()) {
                                    $resultList[] = [
                                        'tanggal' => formatTanggalIndo(date('d-m-Y', strtotime($tanggal))),
                                        'status' => ucfirst($keterangan),
                                        'lama' => null
                                    ];
                                }
                            } else {
                                // Sudah ada, ambil keterangannya
                                $stmt->bind_result($ketLama);
                                $stmt->fetch();
                                $resultList[] = [
                                    'tanggal' => formatTanggalIndo(date('d-m-Y', strtotime($tanggal))),
                                    'status' => 'Sudah terisi',
                                    'lama' => ucfirst($ketLama)
                                ];
                                $stmt->close();
                            }
                        }

                        if (!empty($resultList)) {
                            $sendmsg = "✅ *Presensi Berhasil Dicatat*\n";
                            $sendmsg .= "──────────────────────\n";
                            $sendmsg .= "👤 *Nama*  : $namaSiswa\n";
                            $sendmsg .= "🆔 *NIS*   : $nis\n";
                            $sendmsg .= "📅 *Detail Presensi Baru:*\n";

                            $no = 1;
                            foreach ($resultList as $row) {
                                if ($row['lama'] === null) {
                                    $sendmsg .= "$no. {$row['tanggal']} — {$row['status']}\n";
                                } else {
                                    $sendmsg .= "$no. {$row['tanggal']} — {$row['status']} ({$row['lama']})\n";
                                }
                                $no++;
                            }

                            sendMessage($nohp_target, $sendmsg, null);
                        } else {
                            $sendmsg = "⚠️ *Tidak Ada Presensi Baru*\n";
                            $sendmsg .= "Data presensi mungkin sudah tercatat sebelumnya.";
                        }
                    }
                }
            }

            $conn->close();
        }
    }
} elseif (strpos(strtolower($message), 'jurnal') === 0) {
    // Pecah pesan berdasarkan spasi
    $parts = explode(' ', trim($message));

    // Ambil nomor pengirim
    $number = normalisasi_nomor_62_ke_0($number); // asumsikan sudah tersedia variabel $number

    // Fungsi bantu untuk format tanggal jam
    function formatTanggalJam($timestamp)
    {
        $dt = new DateTime($timestamp);
        return [
            'tanggal' => $dt->format('d-m-Y'),
            'jam' => $dt->format('H:i')
        ];
    }

    include "../config/koneksi.php";

    // Jika hanya "jurnal"
    if (count($parts) == 1) {
        // Cari di datasiswa berdasarkan nohp = $number
        $sqlSiswa = "SELECT nis, nama, kelas FROM datasiswa WHERE nohp = '$number' LIMIT 1";
        $resSiswa = mysqli_query($conn, $sqlSiswa);

        if (mysqli_num_rows($resSiswa) > 0) {
            // Ketemu siswa
            $siswa = mysqli_fetch_assoc($resSiswa);
            $nis = $siswa['nis'];
            $nama = $siswa['nama'];
            $kelas = $siswa['kelas'];

            // Ambil data presensi berdasarkan nis
            $sqlPresensi = "SELECT ket, catatan, timestamp FROM presensi WHERE nis = '$nis' ORDER BY timestamp ASC";
            $resPresensi = mysqli_query($conn, $sqlPresensi);

            $ikonMap = [
                'masuk' => '✅',
                'izin' => '🔵',
                'sakit' => '🟡',
                'libur' => '🔴'
            ];

            $sendmsg = "```\n";
            $sendmsg .= "Rekap Jurnal PKL\n";
            $sendmsg .= "Nama : $nama\n";
            $sendmsg .= "NIS  : $nis\n";
            $sendmsg .= "Kelas: $kelas\n\n";

            $sendmsg .= sprintf("%-4s %-11s %-6s %-3s %s\n", "No.", "Tanggal", "Jam", "Ket", "Catatan");
            $sendmsg .= str_repeat("-", 40) . "\n";

            function cutAtWordBoundary($text, $maxLen)
            {
                if (mb_strlen($text) <= $maxLen) {
                    return $text;
                }
                $cut = mb_substr($text, 0, $maxLen);
                $pos = mb_strrpos($cut, ' ');
                if ($pos !== false) {
                    return mb_substr($cut, 0, $pos);
                }
                return $cut;
            }

            $maxFirstLineWidth = 15;
            $maxNextLinesWidth = 35;
            $indentNextLines = 5;

            $no = 1;
            while ($row = mysqli_fetch_assoc($resPresensi)) {
                $fmt = formatTanggalJam($row['timestamp']);
                $tgl = $fmt['tanggal'];
                $jam = $fmt['jam'];
                $ketKey = strtolower($row['ket']);
                $ikon = isset($ikonMap[$ketKey]) ? $ikonMap[$ketKey] : '❓';

                $ketWithIcon = $ikon;

                $catatan = $row['catatan'];

                // Baris pertama potong tanpa memotong kata
                $firstLine = cutAtWordBoundary($catatan, $maxFirstLineWidth);

                // Sisanya
                $restCatatan = mb_substr($catatan, mb_strlen($firstLine));

                // Wrap sisanya, tidak memotong kata
                $wrappedRest = wordwrap(trim($restCatatan), $maxNextLinesWidth, "\n", false);
                $restLines = $wrappedRest ? explode("\n", $wrappedRest) : [];

                $sendmsg .= sprintf(
                    "%-4d %-11s %-6s %-3s %s\n",
                    $no,
                    $tgl,
                    $jam,
                    $ketWithIcon,
                    $firstLine
                );

                foreach ($restLines as $line) {
                    $sendmsg .= str_repeat(" ", $indentNextLines) . $line . "\n";
                }

                $no++;
            }

            if ($no == 1) {
                $sendmsg .= "Belum ada data presensi.\n";
            } else {
                $sendmsg .= "\nKeterangan:\n✅ = Masuk\n🔵 = Izin\n🟡 = Sakit\n🔴 = Libur\n";
            }

            $sendmsg .= "```";
        } else {
            $number = normalisasi_nomor_0_ke_62($number);
            // Jika tidak ketemu di datasiswa, coba cek di datapembimbing
            $sqlPembimbing = "SELECT nip, nama FROM datapembimbing WHERE nohp = '$number' LIMIT 1";
            $resPembimbing = mysqli_query($conn, $sqlPembimbing);
            if (mysqli_num_rows($resPembimbing) > 0) {
                // Jika pembimbing, harus pakai format jurnal <nis/noHP>
                $sendmsg = "Format perintah untuk pembimbing: jurnal <nis/noHP>";
            } else {
                $sendmsg = "Nomor tidak terdaftar sebagai siswa atau pembimbing.";
            }
        }
    }
    // Jika format "jurnal <nis/noHP>"
    else if (count($parts) == 2) {
        $queryKey = $parts[1];

        // Cek apakah input adalah pembimbing (nip atau nohp)
        $number = normalisasi_nomor_0_ke_62($number);
        $sqlCekPembimbing = "SELECT id FROM datapembimbing WHERE nohp = '$number' LIMIT 1";
        $resCekPembimbing = mysqli_query($conn, $sqlCekPembimbing);

        if (mysqli_num_rows($resCekPembimbing) > 0) {
            // Jika ditemukan pembimbing
            // Cari apakah yang dimasukkan nis atau nohp
            // Cek di datasiswa dulu berdasarkan nis atau nohp
            $sqlSiswa = "SELECT nis, nama, kelas FROM datasiswa WHERE nis = '$queryKey' OR nohp = '$queryKey' LIMIT 1";
            $resSiswa = mysqli_query($conn, $sqlSiswa);

            if (mysqli_num_rows($resSiswa) > 0) {
                $siswa = mysqli_fetch_assoc($resSiswa);
                $nis = $siswa['nis'];
                $nama = $siswa['nama'];
                $kelas = $siswa['kelas'];

                // Ambil data presensi
                $sqlPresensi = "SELECT ket, catatan, timestamp FROM presensi WHERE nis = '$nis' ORDER BY timestamp ASC";
                $resPresensi = mysqli_query($conn, $sqlPresensi);

                $ikonMap = [
                    'masuk' => '✅',
                    'izin' => '🔵',
                    'sakit' => '🟡',
                    'libur' => '🔴'
                ];

                $sendmsg = "```\n";
                $sendmsg .= "Rekap Jurnal PKL\n";
                $sendmsg .= "Nama : $nama\n";
                $sendmsg .= "NIS  : $nis\n";
                $sendmsg .= "Kelas: $kelas\n\n";

                $sendmsg .= sprintf("%-4s %-11s %-6s %-3s %s\n", "No.", "Tanggal", "Jam", "Ket", "Catatan");
                $sendmsg .= str_repeat("-", 40) . "\n";

                function cutAtWordBoundary($text, $maxLen)
                {
                    if (mb_strlen($text) <= $maxLen) {
                        return $text;
                    }
                    $cut = mb_substr($text, 0, $maxLen);
                    $pos = mb_strrpos($cut, ' ');
                    if ($pos !== false) {
                        return mb_substr($cut, 0, $pos);
                    }
                    return $cut;
                }

                $maxFirstLineWidth = 15;
                $maxNextLinesWidth = 35;
                $indentNextLines = 5;

                $no = 1;
                while ($row = mysqli_fetch_assoc($resPresensi)) {
                    $fmt = formatTanggalJam($row['timestamp']);
                    $tgl = $fmt['tanggal'];
                    $jam = $fmt['jam'];
                    $ketKey = strtolower($row['ket']);
                    $ikon = isset($ikonMap[$ketKey]) ? $ikonMap[$ketKey] : '❓';

                    $ketWithIcon = $ikon;

                    $catatan = $row['catatan'];

                    // Baris pertama potong tanpa memotong kata
                    $firstLine = cutAtWordBoundary($catatan, $maxFirstLineWidth);

                    // Sisanya
                    $restCatatan = mb_substr($catatan, mb_strlen($firstLine));

                    // Wrap sisanya, tidak memotong kata
                    $wrappedRest = wordwrap(trim($restCatatan), $maxNextLinesWidth, "\n", false);
                    $restLines = $wrappedRest ? explode("\n", $wrappedRest) : [];

                    $sendmsg .= sprintf(
                        "%-4d %-11s %-6s %-3s %s\n",
                        $no,
                        $tgl,
                        $jam,
                        $ketWithIcon,
                        $firstLine
                    );

                    foreach ($restLines as $line) {
                        $sendmsg .= str_repeat(" ", $indentNextLines) . $line . "\n";
                    }

                    $no++;
                }
                if ($no == 1) {
                    $sendmsg .= "Belum ada data jurnal presensi.\n";
                }
            } else {
                $sendmsg = "Data siswa tidak ditemukan dengan NIS/noHP: $queryKey";
            }
        } else {
            $sendmsg = "Anda bukan pembimbing PKL. Nomor tidak terdaftar sebagai pembimbing";
        }
    } else {
        $sendmsg = "Format perintah jurnal salah.\nGunakan:\n1. jurnal\n2. jurnal <nis/noHP>";
    }

    $conn->close();
} elseif (strpos(strtolower($message), 'help') === 0) {
    $file = $fileDokumentasi;
    $sendmsg = "Panduan Penggunaan ChatBot Presensi PKL - $tahun SMKN Bansari";
} elseif (isValidCoordinate($message)) {
    if ($message_type === "live-location") {

        // Pisahkan lat & lon dari message
        list($lat, $lon) = explode(",", $message);

        $radius = 300; // radius pencarian dalam meter

        // Query Overpass: ambil node, way, dan relation
        $query = '
        [out:json];
        (
        node(around:' . $radius . ',' . $lat . ',' . $lon . ')[name];
        way(around:' . $radius . ',' . $lat . ',' . $lon . ')[name];
        relation(around:' . $radius . ',' . $lat . ',' . $lon . ')[name];
        );
        out center;
        ';

        $url = "https://overpass-api.de/api/interpreter?data=" . urlencode($query);

        $opts = [
            "http" => [
                "header" => "User-Agent: MyApp/1.0\r\n"
            ]
        ];
        $context = stream_context_create($opts);

        $response = file_get_contents($url, false, $context);
        $dataLokasi = json_decode($response, true);

        $results = [];
        if (!empty($dataLokasi["elements"])) {
            foreach ($dataLokasi["elements"] as $element) {
                if (!empty($element["tags"]["name"])) {
                    $poiLat = $element["lat"];
                    $poiLon = $element["lon"];

                    // Hitung jarak dengan Haversine formula
                    $theta = $lon - $poiLon;
                    $dist = sin(deg2rad($lat)) * sin(deg2rad($poiLat))
                        + cos(deg2rad($lat)) * cos(deg2rad($poiLat)) * cos(deg2rad($theta));
                    $dist = acos($dist);
                    $dist = rad2deg($dist);
                    $miles = $dist * 60 * 1.1515;
                    $meters = $miles * 1609.34;

                    $results[] = [
                        "name" => $element["tags"]["name"],
                        "distance_m" => round($meters)
                    ];
                }
            }

            // Urutkan berdasarkan jarak
            usort($results, function ($a, $b) {
                return $a["distance_m"] <=> $b["distance_m"];
            });

            // Ambil 3 terdekat
            $nearest = array_slice($results, 0, 3);

            // Susun jadi pesan teks
            $sendmsg = "📍 Lokasi Anda: $lat, $lon\n";
            $sendmsg .= "🔗 [Lihat di Google Maps]\nhttps://www.google.com/maps?q=$lat,$lon\n\n";
            $sendmsg .= "Lokasi terdekat:\n";
            foreach ($nearest as $i => $place) {
                $sendmsg .= ($i + 1) . ". " . $place["name"] . " (" . $place["distance_m"] . " m)\n";
            }
        } else {
            $sendmsg = "Tidak ada lokasi terdekat ditemukan di sekitar koordinat $lat, $lon.";
        }
        // $sendmsg = "Data ini live-location VALID\n$message";
    } elseif ($message_type === "location") {
        // Pisahkan lat & lon dari message
        list($lat, $lon) = explode(",", $message);
        $sendmsg = "Data live-location TIDAK valid.\nIni bukan lokasi anda sekarang\n";
        $sendmsg .= "🔗 https://www.google.com/maps?q=$lat,$lon";
    } else {
        $sendmsg = "Data live-location TIDAK valid atau hasil copy-paste\n$message";
    }
} elseif (strpos(strtolower($message), 'cari') === 0) {
    $parts = explode(" ", $message, 2);

    $sendmsg = "";
    if (count($parts) < 2 || trim($parts[1]) === "") {
        $sendmsg = "📚 *Panduan Pencarian Data*\n\n";
        $sendmsg .= "Gunakan perintah berikut untuk mencari data siswa atau pembimbing:\n";
        $sendmsg .= "▶️ Format: cari <keyword>\n";
        $sendmsg .= "▶️ Contoh: cari Andi\n\n";
        $sendmsg .= "🔍 Keyword bisa berupa nama, NIS, kelas, atau jurusan siswa, nama DUDI, serta nama atau NIP pembimbing, dll.";
    } else {
        include "../config/koneksi.php";

        $keyword = $conn->real_escape_string(trim($parts[1]));

        // Cari siswa + penempatan
        $sql_siswa = "SELECT d.*, p.nama_dudika, p.alamat_dudika, p.nomor_telepon_dudika, p.nama_pembimbing
                      FROM datasiswa d
                      LEFT JOIN penempatan p ON d.nis = p.nis_siswa
                      WHERE d.nis LIKE '%$keyword%'
                         OR d.nama LIKE '%$keyword%'
                         OR d.kelas LIKE '%$keyword%'
                         OR d.jur LIKE '%$keyword%'";
        $result_siswa = $conn->query($sql_siswa);

        // Cari pembimbing
        $sql_pembimbing = "SELECT * FROM datapembimbing
                           WHERE nama LIKE '%$keyword%'
                              OR nip LIKE '%$keyword%'
                              OR kode LIKE '%$keyword%'";
        $result_pembimbing = $conn->query($sql_pembimbing);

        // Jika tidak ditemukan di siswa & pembimbing, cari di penempatan
        $sql_penempatan = "SELECT nama_dudika, alamat_dudika, nama_pembimbing
                               FROM penempatan
                               WHERE nama_dudika LIKE '%$keyword%'
                                  OR alamat_dudika LIKE '%$keyword%'
                                  OR nama_pembimbing LIKE '%$keyword%'
                               GROUP BY nama_dudika, alamat_dudika, nama_pembimbing";
        $result_penempatan = $conn->query($sql_penempatan);

        $found_siswa = ($result_siswa && $result_siswa->num_rows > 0);
        $found_pembimbing = ($result_pembimbing && $result_pembimbing->num_rows > 0);
        $found_dudi = ($result_penempatan && $result_penempatan->num_rows > 0);

        if ($found_siswa || $found_pembimbing || $found_dudi) {
            // Tampilkan hasil siswa
            if ($found_siswa) {
                $sendmsg .= "=======================================\n";
                $sendmsg .= "📋 *Hasil Pencarian Data Siswa:*\n";
                $sendmsg .= "=======================================\n";
                $sendmsg .= "```\n";

                while ($row = $result_siswa->fetch_assoc()) {
                    $sendmsg .= "NIS        : " . $row['nis'] . "\n";
                    $sendmsg .= "Nama       : " . $row['nama'] . "\n";
                    $sendmsg .= "Kelas      : " . $row['kelas'] . "\n";
                    $sendmsg .= "Jurusan    : " . $row['jur'] . "\n";
                    $sendmsg .= "No HP      : " . $row['nohp'] . "\n\n";

                    if (!empty($row['nama_dudika'])) {
                        $sendmsg .= "🏢 *Penempatan DUDI:*\n";
                        $sendmsg .= "  - Nama DUDI    : " . $row['nama_dudika'] . "\n";
                        $sendmsg .= "  - Alamat       : " . $row['alamat_dudika'] . "\n";
                        // $sendmsg .= "  - No Telp DUDI : " . $row['nomor_telepon_dudika'] . "\n";
                        $sendmsg .= "  - Pembimbing   : " . $row['nama_pembimbing'] . "\n";
                    }
                    $sendmsg .= "───────────────\n";
                }

                $sendmsg .= "```\n";
            }

            // Tampilkan hasil pembimbing + DUDI bimbingan
            if ($found_pembimbing) {
                $sendmsg .= "=======================================\n";
                $sendmsg .= "📋 *Hasil Pencarian Pembimbing:*\n";
                $sendmsg .= "=======================================\n";
                $sendmsg .= "```\n";  // buka blok kode
                while ($row = $result_pembimbing->fetch_assoc()) {
                    $sendmsg .= "NIP      : " . $row['nip'] . "\n";
                    $sendmsg .= "Nama     : " . $row['nama'] . "\n";
                    $sendmsg .= "No HP    : " . $row['nohp'] . "\n";

                    // Cari DUDI yang dibimbing
                    $sql_dudi = "SELECT DISTINCT nama_dudika, alamat_dudika, nomor_telepon_dudika 
                 FROM penempatan 
                 WHERE nama_pembimbing = '" . $conn->real_escape_string($row['nama']) . "'";
                    $result_dudi = $conn->query($sql_dudi);

                    if ($result_dudi && $result_dudi->num_rows > 0) {
                        $sendmsg .= "DUDI yang dibimbing:\n";
                        $nonono = 1;
                        while ($dudi = $result_dudi->fetch_assoc()) {
                            $sendmsg .= "  $nonono. " . $dudi['nama_dudika'] . "\n";
                            $sendmsg .= "    Alamat : " . $dudi['alamat_dudika'] . "\n";
                            // $sendmsg .= "    No Telp: " . $dudi['nomor_telepon_dudika'] . "\n";
                            $sendmsg .= "\n";
                            $nonono++;
                        }
                    } else {
                        $sendmsg .= "⚠️ Belum ada DUDI yang dibimbing.\n";
                    }
                    $sendmsg .= "───────────────\n";
                }

                $sendmsg .= "```\n";  // tutup blok kode
            }

            // Tampilkan Hasil Dudi
            if ($found_dudi) {
                $sendmsg .= "=======================================\n";
                $sendmsg .= "🏢 *Hasil Pencarian Penempatan (DUDI):*\n";
                $sendmsg .= "=======================================\n";
                $sendmsg .= "```\n"; // buka blok kode

                while ($row = $result_penempatan->fetch_assoc()) {
                    $sendmsg .= "Nama DUDI  : " . $row['nama_dudika'] . "\n";
                    $sendmsg .= "Alamat     : " . $row['alamat_dudika'] . "\n";
                    $sendmsg .= "Pembimbing : " . $row['nama_pembimbing'] . "\n\n";

                    // Cari siswa yang ada di DUDI ini
                    $nama_dudika_esc = $conn->real_escape_string($row['nama_dudika']);
                    $sql_siswa_dudi = "SELECT d.nama, d.nis, d.kelas, d.jur, d.nohp FROM penempatan p JOIN datasiswa d ON p.nis_siswa = d.nis WHERE p.nama_dudika = '$nama_dudika_esc'";

                    $res_siswa_dudi = $conn->query($sql_siswa_dudi);

                    if ($res_siswa_dudi && $res_siswa_dudi->num_rows > 0) {
                        $sendmsg .= "👥 Siswa di DUDI ini:\n";
                        while ($s = $res_siswa_dudi->fetch_assoc()) {
                            $sendmsg .= "- " . $s['nama'] . "\n  (NIS: " . $s['nis'] . ", Kelas: " . $s['kelas'] . ")\n  No WA: " . (!empty($s['nohp']) ? $s['nohp'] : "-tidak ada-") . "\n";
                        }
                    } else {
                        $sendmsg .= "⚠️ Tidak ada siswa di DUDI ini.\n";
                    }
                    $sendmsg .= "───────────────\n";
                }

                $sendmsg .= "```"; // tutup blok kode
            }
        } else {
            $sendmsg .= "⚠️ Tidak ditemukan data siswa, pembimbing, maupun DUDI dengan keyword \"$keyword\".\n";
        }

        $conn->close();
    }
} elseif (strpos($message, "#") === 0) {
    $sendmsg = "📢 *Layanan Presensi Prakerin Telah Diperbarui!*\n\n";
    $sendmsg .= "Periode: *Juli – Desember 2025*\nKhusus untuk: *Siswa Kelas XI*\n\n";
    $sendmsg .= "Periode: *Desember 2025 – April 2026*\nKhusus untuk: *Siswa Kelas XII*\n\n";
    $sendmsg .= "ℹ️ Untuk melihat informasi lengkap tentang layanan presensi PKL, balas dengan mengetik: `info`\n\n";
    $sendmsg .= "💬 Jika membutuhkan bantuan atau ingin berbicara dengan admin, balas dengan:\n- `7`\n- `admin`\n\n";
    $sendmsg .= "🙏 Terima kasih atas perhatiannya.\n\n";
    $sendmsg .= "©️ _Sistem Presensi PKL SMK Negeri Bansari_";
} else {
    $delhubadmin = true;
    $tangguhan = true;

    $nono = normalisasi_nomor_0_ke_62($number);
    $adminData = readJsonFile($jsonFile);
    $isInAdminSession = isset($adminData[$nono]);

    // Jika ada file media (foto) masuk
    if (!empty($url)) {
        include "../config/koneksi.php";
        $sendmsg = typo($message);

        // Foto tanpa keterangan → pending presensi
        if (empty($sendmsg)) {
            $daNomorTerdaftar = cekNomorTerdaftar($conn, $number);

            if ($daNomorTerdaftar['type'] === 'siswa') {
                $stmt = $conn->prepare(
                    "SELECT ket, timestamp FROM presensi 
                     WHERE nis = ? AND DATE(timestamp) = ?"
                );
                $stmt->bind_param("ss", $daNomorTerdaftar['nis'], $tanggal);
                $stmt->execute();
                $stmt->bind_result($ket, $timestamp);

                if ($stmt->fetch()) {
                    $formattedTime = (new DateTime($timestamp))->format('H:i:s');
                    $sendmsg = "Sip!👍\n"
                        . "{$daNomorTerdaftar['nama']},\n"
                        . "Kamu sudah presensi hari ini.\n"
                        . "Ket: $ket.\n"
                        . "Jam: $formattedTime.";
                } else {
                    // Simpan sementara data pending presensi
                    $pending = [
                        "type" => "pending_presensi",
                        "nis" => $daNomorTerdaftar['nis'],
                        "namasiswa" => $daNomorTerdaftar['nama'],
                        "kelas" => $daNomorTerdaftar['kelas'],
                        "foto" => $url,
                        "tanggal" => date('Y-m-d'),
                        "timestamp" => time()
                    ];

                    if (!is_dir("presensi_tmp"))
                        mkdir("presensi_tmp", 0777, true);
                    file_put_contents($penangguhanPresensi, json_encode($pending, JSON_PRETTY_PRINT));

                    $sendmsg = "📸 Foto sudah kami terima, tapi belum ada *keterangan kegiatan*.\n\n"
                        . "📝 *Balas pesan ini dengan keterangan saja* (tanpa kirim foto lagi).\n\n"
                        . "🔹 Contoh:\n"
                        . "- `Masuk Memasang instalasi listrik`\n"
                        . "- `Sakit Demam dan batuk`\n"
                        . "- `Izin Ada acara keluarga`\n"
                        . "- `Libur Tidak ada kegiatan hari ini`\n\n"
                        . "✅ Setelah mengirim keterangan, presensi akan tersimpan otomatis.\n\n"
                        . "ℹ️ Balas dengan:\n"
                        . "1️⃣ `1` → Petunjuk presensi\n"
                        . "`info` → Menu presensi\n"
                        . "`admin` atau `7` → Hubungi admin";

                    sendMessage($number, $sendmsg, null);
                    return;
                }
            } elseif ($daNomorTerdaftar['type'] === 'pembimbing') {
                $sendmsg = "👋 Selamat datang, *{$daNomorTerdaftar['nama']}*!\n\n"
                    . "📌 Nomor ini terdaftar sebagai *Pembimbing PKL SMKN Bansari $tahun*.\n\n"
                    . "💡 Melalui chatbot ini Anda dapat:\n"
                    . "• Memantau presensi siswa\n"
                    . "• Input/koreksi presensi\n"
                    . "• Akses rekap kehadiran\n"
                    . "• Hubungi admin\n\n"
                    . "➡️ Balas `help` untuk panduan lengkap.";
            } else {
                $sendmsg = "📢 *Sistem Presensi PKL SMKN Bansari*\n\n"
                    . "🚫 Nomor Anda *tidak terdaftar*.\n\n"
                    . "📌 Jika Anda siswa, segera daftarkan nomor.\n"
                    . "☎️ Hubungi admin bila ada pertanyaan.\n\n"
                    . "💬 Balas `admin` untuk hubungi admin.";
            }
        }

        // Jika ada sesi admin aktif → forward pesan
        if ($isInAdminSession) {
            $adminMessage = "⏳ Belum terbalas sistem main_messages:\n";
            $adminMessage .= "$nono ~ $pushName:\n";
            $adminMessage .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
            $adminMessage .= "$message\n";
            $adminMessage .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
            $adminMessage .= "Sesi Hub.Admin *Aktif* ✅";
            sendMessage($adminNumber, $adminMessage, $url);
        }

        $conn->close();
    }

    // Jika bukan admin session
    elseif (!$isInAdminSession) {
        $sendmsg = typo($message);

        if (empty($sendmsg)) {
            include "../config/koneksi.php";
            $daNomorTerdaftar = cekNomorTerdaftar($conn, $number);

            // Pesan default untuk semua user
            $default_msgs = [
                "📌 Balas dengan Ketik `info` untuk info layanan.\n📞 Balas dengan ketik `admin` atau `7` untuk hubungi admin.",
                "✅ Untuk daftar layanan, balas dengan ketik `info`.\n👨‍💼 Untuk bantuan admin, balas dengan ketik `admin` atau `7`.",
                "📖 Balas dengan ketik `info` untuk panduan.\n🆘 Butuh bantuan? Balas `admin` atau `7`.",
                "🚫 Pesan tanpa format tidak diproses. Balas dengan ketik `info` atau `admin`."
            ];

            if ($daNomorTerdaftar["type"] === 'pembimbing') {
                // Kosongkan balasan jika pembimbing
                $default_msgs = null;
            } elseif ($daNomorTerdaftar["type"] === 'siswa') {
                $hello = ["👋 Hai {$daNomorTerdaftar['nama']}", "👋 Hallo {$daNomorTerdaftar['nama']}"];
                $default_msgs = [$hello[array_rand($hello)] . "\n\n" . $default_msgs[array_rand($default_msgs)]];
            }

            $typeMap = [
                'pembimbing' => "Dari Pembimbing: {$daNomorTerdaftar['nama']}\n",
                'siswa' => "Dari: {$daNomorTerdaftar['nama']}\nKelas: {$daNomorTerdaftar['kelas']}\n"
            ];

            $adminMessage = $typeMap[$daNomorTerdaftar['type']] ?? "Dari: Nomor Tidak terdaftar\n";

            // Anti-spam bebas.json
            $bebasFile = 'bebas.json';
            $bebasData = readJsonFile($bebasFile);
            $now = time();
            $limitDetik = 180;
            $limitPesan = 5;

            if (!isset($bebasData[$nono])) {
                $bebasData[$nono] = ['count' => 1, 'last_reply' => date('Y-m-d H:i:s', 0)];
            } else
                $bebasData[$nono]['count']++;

            $selisih = $now - strtotime($bebasData[$nono]['last_reply']);

            if ($default_msgs && ($bebasData[$nono]['count'] >= $limitPesan || $selisih >= $limitDetik)) {
                $sendmsg = $default_msgs[array_rand($default_msgs)];
                $bebasData[$nono] = ['count' => 0, 'last_reply' => date('Y-m-d H:i:s')];

                $adminMessage .= "☑ Chat ini telah dibalas sistem main_messages:\n";
                $adminMessage .= "$nono ~ $pushName:\n";
                $adminMessage .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
                $adminMessage .= "$message\n";
                $adminMessage .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
            } else {
                $adminMessage .= "⏳ Belum terbalas sistem main_messages:\n";
                $adminMessage .= "$nono ~ $pushName:\n";
                $adminMessage .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
                $adminMessage .= "$message\n";
                $adminMessage .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
            }

            $adminMessage .= "Sesi Hub.Admin *Tidak Aktif* 🚫";

            sendMessage($adminNumber, $adminMessage, $file);
            $conn->close();
            writeJsonFile($bebasFile, $bebasData);
        }

    }

    // Jika sedang dalam sesi admin
    elseif ($isInAdminSession && !empty($message)) {
        include "../config/koneksi.php";
        $daNomorTerdaftar = cekNomorTerdaftar($conn, $number);

        $typeMap = [
            'pembimbing' => "Dari Pembimbing: {$daNomorTerdaftar['nama']}\n",
            'siswa' => "Dari: {$daNomorTerdaftar['nama']}\nKelas: {$daNomorTerdaftar['kelas']}\n"
        ];

        $adminMessage = $typeMap[$daNomorTerdaftar['type']] ?? "Dari: Nomor Tidak terdaftar\n";

        $sendmsg = typo($message);
        if (!empty($sendmsg) && $daNomorTerdaftar["type"] === 'siswa') {
            $adminMessage .= "\n\n-----\nTelah dibalas sistem dengan:\n$sendmsg\n";
            $adminMessage .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        }

        $adminMessage .= "$nono ~ $pushName:\n";
        $adminMessage .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        $adminMessage .= "$message\n";
        $adminMessage .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        $adminMessage .= "Sesi Hub.Admin *Aktif* ✅";

        $conn->close();
        sendMessage($adminNumber, $adminMessage, $file);
    } else {
        $sendmsg = null;
    }
}

// file_put_contents("debug.log", print_r($sendmsg, true), FILE_APPEND);

if ((!isset($delhubadmin) || !$delhubadmin) && (!isset($menuadmin) || !$menuadmin)) {
    $nono = preg_replace('/^0/', '62', $number);

    // Baca data JSON
    $adminData = readJsonFile($jsonFile);

    if (isset($adminData[$nono])) {
        // Hapus nomor dari JSON
        if (deleteFromJsonFile($jsonFile, $nono)) {
            $sendmsg .= "\n\n📌 Sesi berbicara ke admin sebelumnya telah berakhir.";
        }
    }
}

if (!isset($tangguhan)) {
    // Hapus data tertangguh setelah jika tidak dikonfirmasi 
    unset($pending_data[$number]);
    file_put_contents($pending_file, json_encode($pending_data, JSON_PRETTY_PRINT));
}

if ($sendmsg !== null) {
    $sendmsg .= "\n\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n> 📝 _Sistem Presensi PKL_ *SMK Negeri Bansari*\n©️ ```2026```";
    print_r($number);
    echo "\n";
    print_r($sendmsg);

    if ($number == '268362861035668')
        $number = '62882003480995';
    sendMessage($number, $sendmsg, $file);
}