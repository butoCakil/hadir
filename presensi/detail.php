<?php
session_start();

date_default_timezone_set('Asia/Jakarta');

// Konfigurasi koneksi database
include '../config/koneksi.php';

// Mendapatkan parameter NIS dari URL
$nis = isset($_GET['nis']) ? $_GET['nis'] : '';

// Validasi dan sanitasi input NIS
$nis = trim($nis); // Menghapus spasi di awal dan akhir
$nis = stripslashes($nis); // Menghapus karakter escape (\)
$nis = htmlspecialchars($nis); // Mengonversi karakter khusus menjadi entitas HTML

// Menyaring karakter mencurigakan (misalnya tanda kutip atau karakter yang tidak diinginkan)
if (preg_match('/[^0-9]/', $nis)) {
    // Jika terdapat karakter selain alfanumerik, beri respons error
    die("Input NIS tidak valid.");
}

// Menyiapkan prepared statement untuk mendapatkan data siswa berdasarkan NIS
$querySiswa = "SELECT * FROM datasiswa WHERE nis = ?";
$stmt = $conn->prepare($querySiswa);

// Mengikat parameter untuk prepared statement
$stmt->bind_param("s", $nis); // "s" untuk string

// Menjalankan prepared statement
$stmt->execute();

// Mendapatkan hasil
$resultSiswa = $stmt->get_result();

// Mengecek apakah data ditemukan
if ($resultSiswa->num_rows > 0) {
    // Mengambil data siswa
    $siswa = $resultSiswa->fetch_assoc();
    // Lanjutkan dengan penggunaan data siswa
} else {
    echo "Data siswa tidak ditemukan.";
}

// Menutup statement
$stmt->close();

// Mendefinisikan bulan dan jumlah hari maksimal per bulan
// $bulan = [
//     '12' => 'Desember',
//     '01' => 'Januari',
//     '02' => 'Februari',
//     '03' => 'Maret',
//     '04' => 'April',
//     '05' => 'Mei',
//     '06' => 'Juni'
// ];

$bulan = [
    // '07' => 'Juli',
    // '08' => 'Agustus',
    // '09' => 'September',
    // '10' => 'Oktober',
    // '11' => 'November',
    '12' => 'Desember',
    '01' => 'Januari',
    '02' => 'Februari',
    '03' => 'Maret',
    '04' => 'April',
    '05' => 'Mei',
    '06' => 'Juni'
];

// Fungsi untuk mendapatkan jumlah hari dalam bulan tertentu
function getDaysInMonth($month, $year)
{
    return cal_days_in_month(CAL_GREGORIAN, $month, $year);
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

// Menentukan tahun berjalan
$tahun = 2025;

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rekap Presensi</title>
    <link rel="shortcut icon" href="../img/SMKNBansari.png" type="image/x-icon">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"
        integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <style>
        body {
            background-color: #333;
            color: white;
        }

        header {
            display: flex;
            justify-content: space-between;
            padding: 20px 50px;
            background-color: #111;
        }

        header .logo {
            font-size: 30px;
            font-weight: 600;
            color: white;
        }

        nav ul {
            list-style: none;
            display: flex;
            margin: 0;
            padding: 0;
        }

        nav ul li {
            margin-left: 20px;
        }

        nav ul li a {
            color: white;
            text-decoration: none;
            font-weight: 600;
        }

        .btn:hover {
            background-color: #e64a19;
        }
    </style>
</head>

<body>
    <header>
        <div class="logo">PKL SMKN Bansari</div>
        <nav class="navbar navbar-expand-lg navbar-dark">
            <div class="container-fluid">
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav"
                    aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="navbarNav">
                    <ul class="navbar-nav">
                        <li class="nav-item">
                            <a class="nav-link" href="/">Home</a>
                        </li>
                        <!--<li class="nav-item">-->
                        <!--    <span class="nav-link disabled">About</span>-->
                        <!--</li>-->
                        <li class="nav-item">
                            <a class="nav-link" href="../datasiswa">Data Penempatan Siswa</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="../presensi">Data Presensi</a>
                        </li>
                        <?php
                        if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
                        } else {
                            ?>
                            <li class="nav-item">
                                <a class="nav-link" href="../app/logout.php">Log Out</a>
                            </li>
                            <?php
                        }
                        ?>
                    </ul>
                </div>
            </div>
        </nav>
    </header>

    <div class="container mt-4">
        <h2>Rekap Presensi Siswa</h2>
        <p><strong>Nama:</strong> <?= $siswa['nama']; ?></p>
        <p><strong>NIS:</strong> <?= $siswa['nis']; ?></p>
        <div class="table-responsive">
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Bulan</th>
                        <?php for ($i = 1; $i <= 31; $i++): ?>
                            <th><?= $i; ?></th>
                        <?php endfor; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $no = 1;
                    $ketM = 0;
                    $ketS = 0;
                    $ketI = 0;
                    $ketL = 0;
                    foreach ($bulan as $numBulan => $namaBulan):
                        if ($numBulan === '01') {
                            $tahun++;
                        }

                        $daysInMonth = getDaysInMonth($numBulan, $tahun);
                        ?>
                        <tr>
                            <td><?= $no++; ?></td>
                            <td><?= $namaBulan . " " . $tahun; ?></td>
                            <?php
                            for ($i = 1; $i <= 31; $i++):
                                $tanggal = sprintf('%04d-%02d-%02d', $tahun, $numBulan, $i);
                                if ($i > $daysInMonth):
                                    echo '<td class="bg-dark"></td>';
                                else:
                                    $queryPresensi = "SELECT ket FROM presensi WHERE nis = '$nis' AND DATE(timestamp) = '$tanggal'";
                                    $resultPresensi = $conn->query($queryPresensi);
                                    $presensi = $resultPresensi->fetch_assoc();
                                    $ket = isset($presensi['ket']) ? strtolower($presensi['ket']) : '-';
                                    $hari = isset($presensi['ket']) ? hari_indonesia($tanggal) : '';

                                    $badgeClass = '';
                                    if ($ket == 'masuk' || $ket == 'm') {
                                        $badgeClass = 'badge bg-success';
                                        $ketM++;
                                    } elseif ($ket == 'sakit' || $ket == 's') {
                                        $badgeClass = 'badge bg-warning';
                                        $ketS++;
                                    } elseif ($ket == 'izin' || $ket == 'i') {
                                        $badgeClass = 'badge bg-info';
                                        $ketI++;
                                    } elseif ($ket == 'libur' || $ket == 'l') {
                                        $badgeClass = 'badge bg-dark';
                                        $ketL++;
                                    }

                                    $dayOfWeek = date('w', strtotime($tanggal));
                                    $cellClass = '';
                                    if ($dayOfWeek == 0)
                                        $cellClass = 'table-danger'; // Minggu
                                    elseif ($dayOfWeek == 6)
                                        $cellClass = 'table-success'; // Jumat
                        
                                    echo "<td class='$cellClass'>
                                        <span style='font-size: 10px;'>$hari</span>
                                        <span class=' $badgeClass'>" . strtoupper($ket[0]) . "</span>
                                    </td>";
                                endif;
                            endfor;
                            ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div>
            <table>
                <tr>
                    <td>Masuk</td>
                    <td>:</td>
                    <td><?= $ketM; ?></td>
                </tr>
                <tr>
                    <td>Sakit</td>
                    <td>:</td>
                    <td><?= $ketS; ?></td>
                </tr>
                <tr>
                    <td>Izin</td>
                    <td>:</td>
                    <td><?= $ketI; ?></td>
                </tr>
                <tr>
                    <td>Libur</td>
                    <td>:</td>
                    <td><?= $ketL; ?></td>
                </tr>
            </table>
        </div>
    </div>

    <div class="text-white text-center mb-3 mb-md-0">
        <p>Tanggal Akses: <?= date('d-m-Y'); ?></p>
        Copyright © <?= date('Y'); ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz"
        crossorigin="anonymous"></script>
</body>

</html>