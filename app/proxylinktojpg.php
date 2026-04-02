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
            padding: 10px 10px;
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
            /* max-width: 600px; */
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

        </div>
        <div class="container mt-3">
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


            $query .= "GROUP BY ds.nis ORDER BY pen.nama_pembimbing ASC, ds.kelas ASC, ds.nama ASC";

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
                <th>Nama&nbsp;Siswa</th>
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

                echo "
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
                    echo "<td>" . (!empty($row['nama_pembimbing']) ? $row['nama_pembimbing'] : '-') . "</td>";
                    echo "<td>" . (!empty($row['nama_dudika']) ? $row['nama_dudika'] : '-') . "</td>";

                    // Pecah tanggal dan keterangan presensi menjadi array
                    $tanggalPresensi = explode('|', $row['tanggal_presensi']);
                    $keteranganPresensi = explode('|', $row['keterangan_presensi']);
                    $linkPresensi = explode('|', $row['link_foto']);
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

                    // echo "<td><a href='detail.php?nis=" . $row['nis'] . "' class='text-decoration-none'>Lihat</a></td>";
            
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz"
        crossorigin="anonymous"></script>

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