<?php
// echo "tes"; die;
date_default_timezone_set('Asia/Jakarta');
$tanggal = date('Y-m-d');
$tanggal2 = date('d-m-Y');
$tahun = date('Y');
$timestamp = date('Y-m-d H:i:s');
$waktusekarang = date('H:i:s');

// Mulai proses
$data = json_decode(file_get_contents('php://input'), true);

// JSON
/*
{
  "pushName": "Presensi Prakerin Skaneba",
  "from": "08993930090",
  "to": "6282241863393",
  "message": "Ini gambar",
  "media": "https://app.whacenter.com/api/media?path=EF6A7ED0D67F04241C8F2C161888D7BB.jpe",
  "is_group": false,
  "timestamp": "2024-09-29 10:45:06",
  "source": "WHACENTER"
}
*/

// $timestamp = isset($data["timestamp"]) ? $data["timestamp"] : null;
$pushName = isset($data["pushName"]) ? $data["pushName"] : null;
$timestampWA = isset($data["timestamp"]) ? $data["timestamp"] : null;
$number = isset($data["from"]) ? $data["from"] : null;
$message = isset($data["message"]) ? $data["message"] : null;
$url = isset($data["media"]) ? $data["media"] : null;
$file = null;
$sendmsg = null;

include "sendchat.php";

// Lokasi file JSON untuk mencatat nomor
$jsonFile = 'hubadmin.json';

// Fungsi untuk membaca data dari file JSON
// Fungsi untuk membaca data dari file JSON
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

$delhubadmin = false;
$menuadmin = false;
if (strtolower($message) === "p") {
    $sendmsg = "Balas dengan ketik `info`.\nUntuk mendapatkan informasi Layanan Presensi.\n\n©️ _Sistem Presensi PKL SMK Negeri Bansari_";
} elseif (strtolower($message) === "info") {
    $sendmsg = "Layanan Presensi PKL Kelas XI SMKN Bansari $tahun\n1. Langkah Penggunaan Presensi.\n2. Lupa Presensi (belum aktif)\n3. Pendaftaran Nomor / Pergantian Nomor\n4. Periksa status nomor\n5. Hapus / Batal / Ganti nomor\n6. Rekap Presensi\n7. Hubungi Admin\n\nBalas dengan ketik angka di depan pilihan.\n\n©️ _Sistem Presensi PKL SMK Negeri Bansari_";
} elseif (strtolower($message === "1")) {
    $sendmsg = "Langkah Penggunaan Presensi.

Pastikan nomor telah terdaftar.
(Balas dengan ketik `3` untuk panduan pendaftaran nomor WA)

Langkah Presensi:
1. Ambil Foto Selfie.
2. Berikan keterangan pada foto dengan format:
    `KETERANGAN<spasi>CATATAN KEGIATAN`

    Pilihan Keterangan:
    - masuk
    - izin
    - sakit
    - libur

    CONTOH:
    `Masuk Memperbaiki instalasi`

    atau

    `Sakit Demam batuk pilek`

    atau

    `Izin sedang ada acara keluarga`

    atau 

    `Libur Sedang tidak ada job`

3. Kirim, tunggu respon berhasil.\n\n©️ _Sistem Presensi PKL SMK Negeri Bansari_";
} elseif (strtolower($message === "2")) {
    $sendmsg = "Lupa Absen.
Apabila di hari kemarin lupa melakukan presensi.
Bisa presensi di hari ini.
Tetapi kesempatan melakukan lupa absen hanya 1 kali dalam 1 hari.

*Belum Aktif* - Sementara Hubungi Admin.\n\nBalas dengan ketik `7` atau `admin` untuk menghubungi Admin.\n\n©️ _Sistem Presensi PKL SMK Negeri Bansari_";
} elseif (strtolower($message === "3")) {
    $sendmsg = "Pendaftaran nomor WA:\nbalas dengan ketik:\n`reg<spasi>NIS`\n\n*) huruf besar/kecil tidak berpengaruh

Contoh:
    Jika NIS nya adalah 1234
    maka ketiknya:
        `reg 1234`
        lalu kirim\n\n©️ _Sistem Presensi PKL SMK Negeri Bansari_";
} elseif (strtolower($message === "5")) {
    $sendmsg = "Balas dengan ketik `unreg` untuk membatalkan pendaftaran nomor.\n\n©️ _Sistem Presensi PKL SMK Negeri Bansari_";
} elseif (strtolower($message === "6")) {
    include "../config/koneksi.php";

    // Ambil nama siswa dari tabel 'datasiswa' berdasarkan NIS
    $stmt = $conn->prepare("SELECT nis FROM datasiswa WHERE nohp = ?");
    $stmt->bind_param("s", $number);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $nis = $row['nis'];
        $sendmsg = "Rekap Presensi bisa diakses melalui link berikut.\nhttps://pklbos.smknbansari.sch.id/?akses=detail&nis=$nis\n\n©️ _Sistem Presensi PKL SMK Negeri Bansari_";
        // $sendmsg = "Rekap Presensi bisa diakses melalui link berikut.\nhttps://hadir.masbendz.com/presensi/detail.php?nis=$nis";
        // $sendmsg = "Rekap Presensi.\nuntuk mengetahui rekap presensi berdasarkan NIS\n\nBalas dengan ketik `Rekap<spasi>NIS`";
    } else {
        $sendmsg = "❗Nomor HP tidak terdaftar untuk presensi.\n\nDaftarkan nomor WA dengan cara ketik:\nREG<spasi>NIS\n\n©️ _Sistem Presensi PKL SMK Negeri Bansari_";
    }

    $stmt->close();
    $conn->close();
} elseif (strtolower($message) == '7' || strtolower($message) == 'admin') {
    $menuadmin = true;
    // Pesan balasan untuk user
    $sendmsg = "Silakan ajukan pertanyaan, untuk kemudian akan dijawab oleh admin";

    // Pastikan nomor pengguna diubah dari format 08 ke format internasional (62)

    // Nomor admin (format internasional)
    $adminNumber = "6282241863393";

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
        $sendmsg = "Silakan ajukan pertanyaan, untuk kemudian akan dijawab oleh admin\n\nBalas dengan ketik `info` atau memilih menu untuk mengakhiri obloran dengan admin.\n\n©️ _Sistem Presensi PKL SMK Negeri Bansari_";

        // Kirim notifikasi ke admin
        $adminNumber = "6282241863393";
        $adminMessage = "$nono ~ $pushName,\nTelah menghubungi Admin di WA Presensi.\n\n_Tolong segera direspon._\n\n©️ _Sistem Presensi PKL SMK Negeri Bansari_";
        sendMessage($adminNumber, $adminMessage, $file);
    }
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
                $sendmsg = "Nomor $number sudah terdaftar.\n";
                $sendmsg .= "Nama: $nama\nKelas: $kelas\n\n©️ _Sistem Presensi PKL SMK Negeri Bansari_";
            } else {
                // Jika nomor tidak ditemukan
                $sendmsg = "❗Nomor WA tidak ditemukan atau belum terdaftar.\n\nUntuk mendaftarkan nomor,\nBalas dengan ketik `reg<spasi>NIS`\n\nAtau Silakan hubungi Admin atau Pembimbing jika ada kendala.\n\n©️ _Sistem Presensi PKL SMK Negeri Bansari_";
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
                $sendmsg = "Nama: $nama,\nKelas: $kelas\nNIS: $nis\n";

                // Logika untuk mengosongkan nomor HP
                if ($nohp_lama !== null) {
                    $update_stmt = $conn->prepare("UPDATE datasiswa SET nohp = NULL WHERE nis LIKE ?");
                    $update_stmt->bind_param("s", $nis);

                    if ($update_stmt->execute()) {
                        $sendmsg .= "Nomor WA ($nohp_lama) telah dihapus.";
                    } else {
                        $sendmsg .= "❗❗Gagal mengosongkan nomor WA: " . $update_stmt->error;
                    }

                    $update_stmt->close();
                } else {
                    $sendmsg .= "❗Nomor WA belum terdaftar.";
                }
            } else {
                $sendmsg = "❗Nomor WA ini belum terdaftar.\n\nUntuk mendaftarkan nomor,\nBalas dengan ketik `reg<spasi>NIS`\n\nAtau Silakan hubungi Admin atau Pembimbing jika ada kendala.\n\n©️ _Sistem Presensi PKL SMK Negeri Bansari_";
            }
        } else {
            $sendmsg = "❗❗Gagal menjalankan: " . $stmt->error;
        }

        $stmt->close();
        $conn->close();
    }

} elseif (strpos(strtolower($message), "reg") === 0) {
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

                        // Tampilkan informasi nama dan kelas
                        $sendmsg = "Nama: $nama,\nKelas: $kelas\n";

                        if ($nohp_lama === $number) {
                            $sendmsg .= "Nomor yang sama ($number) sudah terdaftar sebelumnya.";
                        } else {
                            // Update jika nomor belum ada
                            $update_stmt = $conn->prepare("UPDATE datasiswa SET nohp = ? WHERE nis LIKE ?");
                            $update_stmt->bind_param("ss", $number, $nis_pattern);

                            if ($update_stmt->execute()) {
                                if (empty($nohp_lama)) {
                                    $sendmsg .= "Berhasil mendaftarkan nomor $number.\n\nSekarang sudah bisa melakukan presensi PKL.\n\nBalas dengan ketik `1` untuk panduan langkah presensi\n\n©️ _Sistem Presensi PKL SMK Negeri Bansari_";
                                } else {
                                    $sendmsg .= "Nomor lama $nohp_lama telah diganti dengan nomor baru $number.\n\n©️ _Sistem Presensi PKL SMK Negeri Bansari_";
                                }
                            } else {
                                $sendmsg .= "Gagal memperbarui nomor: " . $update_stmt->error;
                            }
                            $update_stmt->close();
                        }
                    } else {
                        $sendmsg = "❗NIS $result tidak terdaftar.\n\nSilakan hubungi Admin atau Pembimbing.\n\nKetik `7` atau `admin` untuk menghubungi admin.\n\n©️ _Sistem Presensi PKL SMK Negeri Bansari_";
                    }
                } else {
                    $sendmsg = "❗❗Gagal menjalankan query: " . $stmt->error;
                }

                $stmt->close();
            }

            $conn->close();
        } else {
            $sendmsg = "❗Koreksi kembali formatnya.\nKata kedua bukan angka.\n\n$result\n\n©️ _Sistem Presensi PKL SMK Negeri Bansari_";
        }
    } else {
        $sendmsg = "❗Format REG tidak valid.\n\nREG<spasi>NIS\n\n©️ _Sistem Presensi PKL SMK Negeri Bansari_";
    }
} elseif (strpos(strtolower($message), "req") === 0) {
    $sendmsg = "`reg` bukan `req`.\nPakai `g` bukan `q`.\n\n©️ _Sistem Presensi PKL SMK Negeri Bansari_";
} elseif (strpos(strtolower($message), "masuk") === 0 || strpos(strtolower($message), "izin") === 0 || strpos(strtolower($message), "sakit") === 0 || strpos(strtolower($message), "libur") === 0) {
    // Pisahkan pesan berdasarkan spasi, ambil kata pertama dan sisa pesan
    $message_parts = explode(" ", $message, 2);
    $status = strtolower($message_parts[0]);
    $status = preg_replace("/[^a-z]/", "", $status);
    $catatan = isset($message_parts[1]) ? trim($message_parts[1]) : ""; // Ambil semua teks setelah kata pertama, hilangkan spasi awal

    // Masukkan ke tabel presensi jika status valid
    if (in_array($status, ["masuk", "izin", "sakit", "libur"])) {
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

                // Periksa apakah NIS sudah melakukan presensi pada tanggal tertentu
                $stmt = $conn->prepare("SELECT timestamp FROM presensi WHERE nis = ? AND DATE(timestamp) = ?");
                $stmt->bind_param("ss", $nis, $tanggal); // 's' untuk string
                $stmt->execute();
                $stmt->bind_result($timestamp);

                // Ambil hasil query
                if ($stmt->fetch()) {
                    // Format timestamp menjadi tanggal dan waktu
                    $datetime = new DateTime($timestamp);
                    $formattedDate = $datetime->format('d-m-Y'); // Format: Tanggal dalam format DD-MM-YYYY
                    $formattedTime = $datetime->format('H:i:s'); // Format: Jam dalam format HH:MM:SS

                    // Tampilkan informasi presensi
                    $sendmsg = "NIS $nis telah melakukan presensi untuk tanggal $formattedDate pukul $formattedTime.";
                } else {
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

                        // Cek error
                        if (curl_errno($ch)) {
                            $sendmsg .= "\n\n🚫 Gagal menyimpan Foto Presensi: " . curl_error($ch);
                        } else {
                            echo "\n\nLink berhasil dieksekusi: " . $response;
                            // Mengubah JSON menjadi array assosiatif
                            $data = json_decode($response, true);

                            // Mengambil nilai link_baru dan menghapus backslash
                            $link_baru = str_replace('\\', '', $data['link_baru']);

                            $sendmsg = "✅ Presensi Berhasil.";
                        }

                        $sendmsg .= "\n\nStatus: $status, \nCatatan: $catatan, \nNama: $namasiswa, \nKelas: $kelas.\n\n$timestamp";

                        // if(!empty($link_baru)){
                        //     $sendmsg .= "\n\n" . $link_baru;
                        // }

                        // $sendmsg .= "\n\nCek Rekap presensi di sini:\n" . "https://hadir.masbendz.com/presensi/detail.php?nis=$nis";
                        $sendmsg .= "\n\nCek Rekap presensi di sini:\n" . "https://pklbos.smknbansari.sch.id/?akses=detail&nis=$nis\n\n©️ _Sistem Presensi PKL SMK Negeri Bansari_";

                        // $sendmsg .= "\n\n" . $urls;

                        // Tutup cURL
                        curl_close($ch);


                    } else {
                        $sendmsg = "🚫 Gagal menambahkan presensi: " . $stmt->error;
                    }
                }

                $stmt->close();
            } else {
                $sendmsg = "🚫 Nomor HP tidak terdaftar untuk presensi.\n\nDaftarkan nomor WA dengan cara ketik:\nREG<spasi>NIS\n\n©️ _Sistem Presensi PKL SMK Negeri Bansari_";
            }

            // Menutup koneksi
            $conn->close();
        } else {
            $sendmsg = "🚫 Presensi Gagal\n\nPresensi wajib menyertakan foto selfie\n\n©️ _Sistem Presensi PKL SMK Negeri Bansari_";
        }
    } else {
        $sendmsg = "🚫 Keterangan presensi tidak valid. Harus diawali salah satu dari (masuk, izin, sakit, libur).\n\nDiikuti dengan SPASI Tanpa tanda baca di sekitar keterangan masuk.\n\n©️ _Sistem Presensi PKL SMK Negeri Bansari_";
    }
} elseif (strpos($message, "#") === 0) {
    $sendmsg = "Layanan Presensi Prakerin untuk Kelas XI Periode Juli - Desember 2025 telah diperbaharui.\n\nBalas dengan ketik `info` untuk mendapatkan informasi Layanan Presensi PKL.\n\nHubungi admin untuk informasi lebih lanjut.\n\nBalas dengan ketik `7` atau `admin` untuk berbicara dengan admin.\n\nTerimakasih\n\n©️ _Sistem Presensi PKL SMK Negeri Bansari_";
} elseif (strpos(strtolower($message), 'rekap') === 0) {
    // Cek apakah ada spasi dan angka setelah 'rekap'
    $parts = explode(' ', $message, 2);
    if (isset($parts[1]) && is_numeric($parts[1])) {
        $angka = (int) $parts[1]; // Konversi ke integer
        if ($angka > 2766) {
            $sendmsg = "Rekap presensi anda NIS $angka\n\nhttps://pkl.smknbansari.sch.id/rekap.php?nis=$angka\n\nhttps://pkl.smknbansari.sch.id/semuarekap.php?nis=$angka\n\n©️ _Sistem Presensi PKL SMK Negeri Bansari_";
        } else {
            $sendmsg = "Rekap presensi anda NIS $angka\n\nhttps://pkl.smknbansari.sch.id/?akses=detail&nis=$angka\n\n©️ _Sistem Presensi PKL SMK Negeri Bansari_";
        }
    } else {
        $sendmsg = "Pesan diawali dengan 'rekap'. Tambahkan <spasi> dan NIS setelahnya.\n\n©️ _Sistem Presensi PKL SMK Negeri Bansari_";
    }
} elseif (strpos(strtolower($message), 'balas ') === 0) {
    $parts = explode(' ', $message, 3); // Memisahkan pesan menjadi 3 bagian: 'balas', nomor, dan pesan
    if (count($parts) >= 3) { // Pastikan formatnya sesuai
        $sendnumber = $parts[1]; // Bagian kedua adalah nomor
        $response = $parts[2] . "\n\n~ Admin"; // Bagian ketiga dan seterusnya adalah pesan

        if ($number == "082241863393")
            sendMessage($sendnumber, $response, $file);
    }
} else {
    $delhubadmin = true;

    if (!empty($url)) {
        $sendmsg = "❓Jika anda ingin melakukan presensi, tambahkan keterangan pada foto yang sesuai dengan format.\n\nkemudian ulangi pengiriman.\n\nBalas dengan ketik `1` untuk mendapatkan petunjuk presensi.\n\nAtau\n\nBalas dengan ketik `info` kemudian pilih menu langkah presensi\n\n©️ _Sistem Presensi PKL SMK Negeri Bansari_";
    }

    // Format nomor pengguna (ganti 0 di awal menjadi 62)
    $nono = preg_replace('/^0/', '62', $number);

    // Baca data JSON
    $adminData = readJsonFile($jsonFile);

    if (isset($adminData[$nono])) {
        // Nomor sudah tercatat, langsung teruskan pesan ke admin
        $adminNumber = "6282241863393";
        $adminMessage = "$nono ~ $pushName:\n$message.\n\n©️ _Sistem Presensi PKL SMK Negeri Bansari_";
        sendMessage($adminNumber, $adminMessage, $file);
    }
}

if ((!isset($delhubadmin) || !$delhubadmin) && (!isset($menuadmin) || !$menuadmin)) {
    $nono = preg_replace('/^0/', '62', $number);

    // Baca data JSON
    $adminData = readJsonFile($jsonFile);

    if (isset($adminData[$nono])) {
        // Hapus nomor dari JSON
        if (deleteFromJsonFile($jsonFile, $nono)) {
            $sendmsg .= "\n\nDengan mengakses menu ini, Sesi berbicara ke admin sebelumnya telah berakhir.\n\n©️ _Sistem Presensi PKL SMK Negeri Bansari_";
        }
    }
}

if (!$sendmsg == null) {
    print_r($number);
    echo "\n";
    print_r($sendmsg);
    sendMessage($number, $sendmsg, $file);
}

