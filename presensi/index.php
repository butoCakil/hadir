<?php
session_start();

date_default_timezone_set('Asia/Jakarta');
$tanggal = date('Y-m-d');
$tahun = date('Y');
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PKL SMKN Bansari</title>
    <link rel="shortcut icon" href="../img/SMKNBansari.png" type="image/x-icon">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"
        integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">

    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">

    <style>
        body {
            font-family: 'Poppins', sans-serif;
            margin: 0;
            padding: 0;
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

        .hero {
            text-align: center;
            padding: 50px 20px;
            background-color: #222;
            color: #aaaaaa;
        }

        .hero h1 {
            font-size: 48px;
            font-weight: 700;
        }

        .hero p {
            font-size: 18px;
            margin-top: 10px;
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
        }

        .hero .btn {
            background-color: #FF5722;
            color: white;
            text-decoration: none;
            padding: 10px 30px;
            border-radius: 5px;
            margin-top: 20px;
            display: inline-block;
            font-weight: 600;
        }

        .btn:hover {
            background-color: #e64a19;
        }

        table.dataTable {
            width: 100% !important;
        }

        div.dataTables_wrapper {
            overflow-x: auto;
        }

        #tabelPresensi {
            font-size: 12px;
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
                        <li class="nav-item">
                            <span class="nav-link disabled">About</span>
                        </li>
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

    <section class="hero">
        <div>
            <h2 class="text-center">Rekap Presensi Siswa</h2>
            <?php
            include "../config/koneksi.php";

            $selected_pembimbing = trim($_GET['pembimbing'] ?? '');

            // Ambil nama pembimbing unik (tidak NULL dan tidak kosong)
            $query = "SELECT DISTINCT nama_pembimbing FROM penempatan WHERE nama_pembimbing IS NOT NULL AND nama_pembimbing != '' ORDER BY nama_pembimbing ASC";
            $result = $conn->query($query);
            ?>

            <!-- Select dengan onchange untuk redirect -->
            <select name="pembimbing" id="pembimbing" onchange="redirectToFilter()">
                <option value="" <?= @$selected_pembimbing == '' ? 'selected' : '' ?>>-- Semua Pembimbing --</option>
                <?php
                if ($result && $result->num_rows > 0) {
                    while ($row = $result->fetch_assoc()) {
                        $nama = htmlspecialchars($row['nama_pembimbing']);
                        $selected = ($nama === $selected_pembimbing) ? 'selected' : '';
                        echo "<option value=\"$nama\" $selected>$nama</option>";
                    }
                }
                ?>
            </select>

            <?php
            $selected_kelas = trim($_GET['kelas'] ?? '');


            $queryKelas = "SELECT DISTINCT kelas FROM datasiswa WHERE kelas IS NOT NULL AND kelas != '' ORDER BY kelas ASC";
            $resultKelas = $conn->query($queryKelas);
            ?>

            <!-- Select Kelas -->
            <select name="kelas" id="kelas" onchange="redirectToFilter()">
                <option value="" <?= @$selected_kelas == '' ? 'selected' : '' ?>>-- Semua Kelas --</option>
                <?php
                if ($resultKelas && $resultKelas->num_rows > 0) {
                    while ($row = $resultKelas->fetch_assoc()) {
                        $kelas = htmlspecialchars($row['kelas']);
                        $selected = ($kelas === $selected_kelas) ? 'selected' : '';
                        echo "<option value=\"$kelas\" $selected>$kelas</option>";
                    }
                }
                ?>
            </select>
            
            <a href="rekap_print.php" class="" style="text-decoration: none; background-color: #aaaaaa; padding: 5px; border-radius: 5px; margin: 5px; color: #111;">Print</a>

        </div>
        <div class="container">
            <?php
            // Konfigurasi koneksi database
            include "../config/koneksi.php";

            // Mendapatkan tanggal hari ini dan 7 hari sebelumnya
            $tanggalHariIni = date('Y-m-d');
            $tanggal7HariLalu = date('Y-m-d', strtotime('-7 days'));

            // Fungsi untuk format tanggal dalam Bahasa Indonesia
            function formatTanggalIndonesia($tanggal)
            {
                $hari = ["Minggu", "Senin", "Selasa", "Rabu", "Kamis", "Jumat", "Sabtu"];
                $bulan = [
                    "Jan",
                    "Feb",
                    "Mar",
                    "Apr",
                    "Mei",
                    "Jun",
                    "Jul",
                    "Ags",
                    "Sep",
                    "Okt",
                    "Nov",
                    "Des"
                ];

                $timestamp = strtotime($tanggal);
                $namaHari = $hari[date("w", $timestamp)];
                $namaBulan = $bulan[date("n", $timestamp) - 1];

                return "$namaHari, " . date("d", $timestamp) . " $namaBulan " . date("Y", $timestamp);
            }

            $nama_pembimbing = trim($_GET['pembimbing'] ?? '');
            // Query untuk mencocokkan data siswa, pembimbing, dudika, dan presensi dalam rentang waktu 7 hari terakhir
            $query = "
    SELECT 
        ds.nis,
        ds.nama AS nama_siswa,
        ds.kelas,
        ds.nohp,
        pen.nama_pembimbing,
        pen.nama_dudika,
        GROUP_CONCAT(DATE(pres.timestamp) SEPARATOR '|') AS tanggal_presensi,
        GROUP_CONCAT(pres.ket SEPARATOR '|') AS keterangan_presensi,
        GROUP_CONCAT(pres.link SEPARATOR '|') AS link_foto
    FROM datasiswa ds
    LEFT JOIN penempatan pen ON ds.nis = pen.nis_siswa
    LEFT JOIN presensi pres ON ds.nis = pres.nis AND DATE(pres.timestamp) BETWEEN '$tanggal7HariLalu' AND '$tanggalHariIni'
";

            $whereClauses = [];

            if (!empty($nama_pembimbing)) {
                $nama_pembimbing_safe = $conn->real_escape_string($nama_pembimbing);
                $whereClauses[] = "pen.nama_pembimbing = '$nama_pembimbing_safe'";
            }

            if (!empty($selected_kelas)) {
                $kelas_safe = $conn->real_escape_string($selected_kelas);
                $whereClauses[] = "ds.kelas = '$kelas_safe'";
            }

            if (!empty($whereClauses)) {
                $query .= " WHERE " . implode(" AND ", $whereClauses);
            }


            $query .= "GROUP BY ds.nis ORDER BY pen.nama_pembimbing ASC, pen.nama_dudika ASC, ds.kelas ASC, ds.nama ASC";

            $result = $conn->query($query);

            // Menampilkan tabel
            if ($result->num_rows > 0) {
                echo "<table id='tabelPresensi' class='table table-bordered table-striped text-white'>";
                echo "
        <thead>
            <tr>
                <th>No.</th>
                <th>NIS</th>
                <th>Kelas</th>
                <th>Nama Siswa</th>
                <th>No WA</th>
                <th>Pembimbing</th>
                <th>DUDIKA</th>";

                // Tambahkan kolom tanggal dari -7 hingga hari ini
                for ($i = -7; $i <= 0; $i++) {
                    $tanggal = date('Y-m-d', strtotime("$i days"));

                    $dayOfWeek = date('w', strtotime($tanggal));
                    $cellClass = '';
                    if ($dayOfWeek == 0)
                        $cellClass = 'table-danger'; // Minggu
                    elseif ($dayOfWeek == 6)
                        $cellClass = 'table-success'; // Jumat
            
                    echo "<th class='$cellClass'>" . formatTanggalIndonesia($tanggal) . "</th>";
                }

                echo "<th>
                <span>Detail</span>
                    <div class='d-flex'>
                        <span class='badge bg-success badge-sm'>M</span>
                        <span class='badge bg-warning badge-sm'>S</span>
                        <span class='badge bg-info badge-sm'>I</span>
                        <span class='badge bg-dark badge-sm'>L</span>
                    </div>
                </th>
            </tr>
        </thead>
        <tbody>";

                $no = 1;
                while ($row = $result->fetch_assoc()) {
                    echo "<tr>";
                    echo "<td>" . $no++ . "</td>";
                    echo "<td>" . $row['nis'] . "</td>";
                    echo "<td>" . $row['kelas'] . "</td>";
                    echo "<td>" . $row['nama_siswa'] . "</td>";
                    // echo "<td>" . @$row['nohp'] . "</td>";
                    if (!empty($row['nohp'])) {

                    echo '<td class="text-center">
                        <form action="open_wa.php" method="post" target="_blank" style="display:inline;">
                            <input type="hidden" name="id" value="'.$row['nis'].'">
                            <button type="submit" title="Chat WhatsApp" style="
                                cursor:pointer;
                                border:none;
                                background:none;
                                padding:4px;
                            ">
                                <svg xmlns="http://www.w3.org" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#25D366" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                  <path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"></path>
                                </svg>
                            </button>
                        </form>
                    </td>';

                    
                    } else {
                    
                        echo "<td class='text-center'>❌</td>";
                    
                    }

                    echo "<td>" . (!empty($row['nama_pembimbing']) ? $row['nama_pembimbing'] : '-') . "</td>";
                    echo "<td>" . (!empty($row['nama_dudika']) ? $row['nama_dudika'] : '-') . "</td>";

                    // Pecah tanggal dan keterangan presensi menjadi array
                    $tanggalPresensi = explode('|', $row['tanggal_presensi'] ?? '');
                    $keteranganPresensi = explode('|', $row['keterangan_presensi'] ?? '');
                    $linkPresensi = explode('|', $row['link_foto'] ?? '');
                    $dataPresensi = array_combine($tanggalPresensi, $keteranganPresensi);
                    $datalinkPresensi = array_combine($tanggalPresensi, $linkPresensi);

                    // Tampilkan data presensi untuk setiap tanggal dalam rentang -7 hingga hari ini
                    for ($i = -7; $i <= 0; $i++) {
                        $tanggal = date('Y-m-d', strtotime("$i days"));

                        $dayOfWeek = date('w', strtotime($tanggal));
                        $cellClass = '';
                        if ($dayOfWeek == 0)
                            $cellClass = 'table-danger'; // Minggu
                        elseif ($dayOfWeek == 6)
                            $cellClass = 'table-success'; // Jumat
            
                        $ket = isset($dataPresensi[$tanggal]) ? $dataPresensi[$tanggal] : '';
                        $link_pre = isset($datalinkPresensi[$tanggal]) ? $datalinkPresensi[$tanggal] : '';

                        if (strtolower($ket) == "masuk") {
                            $ket = "M";
                            $bgggg = "bg-success";
                        } elseif (strtolower($ket) == "sakit") {
                            $ket = "S";
                            $bgggg = "bg-warning";
                        } elseif (strtolower($ket) == "izin") {
                            $ket = "I";
                            $bgggg = "bg-info";
                        } elseif (strtolower($ket) == "libur") {
                            $ket = "L";
                            $bgggg = "bg-dark";
                        } else {
                            $ket = "-";
                            $bgggg = "";
                        }

                        if (!empty($link_pre)) {
                            echo "<td class='$cellClass'><a target='_blank' href='" . $link_pre . "' class='text-decoration-none' style='cursor: pointer;'><span class='badge $bgggg'>" . (isset($ket) ? $ket : '-') . "</span></a></td>";
                        } else {
                            echo "<td class='$cellClass'><span class='badge $bgggg'>" . (isset($ket) ? $ket : '-') . "</span></td>";
                        }
                    }

                    $nis = $conn->real_escape_string($row['nis']);

                    // Query untuk mengambil semua presensi siswa
                    $tanggal_mulai = '2025-07-17';
                    $sqlHit = "SELECT ket 
                                FROM presensi 
                                -- WHERE nis='$nis' AND DATE(timestamp) >= '$tanggal_mulai'
                                WHERE nis='$nis'
                                GROUP BY DATE(timestamp)";
                    $resultHit = $conn->query($sqlHit);

                    // Inisialisasi hitungan
                    $ketM = 0; // masuk
                    $ketI = 0; // izin
                    $ketS = 0; // sakit
                    $ketL = 0; // libur
            
                    if ($resultHit->num_rows > 0) {
                        while ($data = $resultHit->fetch_assoc()) {
                            switch (strtolower($data['ket'])) {
                                case 'masuk':
                                    $ketM++;
                                    break;
                                case 'izin':
                                    $ketI++;
                                    break;
                                case 'sakit':
                                    $ketS++;
                                    break;
                                case 'libur':
                                    $ketL++;
                                    break;
                            }
                        }
                    }

                    echo "<td>
                        <div class='d-flex'>
                            <span class='badge bg-success badge-sm'>$ketM</span>
                            <span class='badge bg-warning badge-sm'>$ketS</span>
                            <span class='badge bg-info badge-sm'>$ketI</span>
                            <span class='badge bg-dark badge-sm'>$ketL</span>
                        </div>
                        <div>
                            <a href='detail.php?nis=" . $nis . "' class='text-decoration-none'>
                                Selengkapnya
                            </a>
                        <div>
                    </td>";

                    echo "</tr>";
                }

                echo "</tbody></table>";
            } else {
                echo "Tidak ada data presensi dalam rentang waktu ini.";
            }

            // Tutup koneksi
            $conn->close();
            ?>

        </div>
    </section>

    <div class="text-white text-center mb-3 mb-md-0">
        Copyright © <?= $tahun; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz"
        crossorigin="anonymous"></script>

    <!-- jQuery dan DataTables JS -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>

    <script>
        $(function () {
            $('#tabelPresensi').DataTable({
                responsive: true,
                paging: true,

                pageLength: -1,
                lengthMenu: [
                    [-1, 10, 25, 50, 100, -1],
                    ["Semua", 10, 25, 50, 100, "Semua"]
                ],
                pagingType: "full_numbers",
                language: {
                    url: "//cdn.datatables.net/plug-ins/1.13.6/i18n/id.json"
                }
            });
        });
    </script>
    <script>
        function redirectToFilter() {
            const pembimbing = document.getElementById("pembimbing").value;
            const kelas = document.getElementById("kelas").value;
            const url = new URL(window.location.href);

            if (pembimbing === "") {
                url.searchParams.delete("pembimbing");
            } else {
                url.searchParams.set("pembimbing", pembimbing);
            }

            if (kelas === "") {
                url.searchParams.delete("kelas");
            } else {
                url.searchParams.set("kelas", kelas);
            }

            window.location.href = url.toString();
        }
    </script>

</body>

</html>