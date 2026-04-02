<?php
include "../config/koneksi.php";

// AMBIL FILTER KELAS
$kelas = isset($_GET['kelas']) ? $_GET['kelas'] : '';

// AMBIL FILTER BULAN (format: YYYY-MM)
$bulan = isset($_GET['bulan']) ? $_GET['bulan'] : date("Y-m");

// Hitung jumlah hari dalam bulan
$jumlahHari = cal_days_in_month(CAL_GREGORIAN, substr($bulan, 5, 2), substr($bulan, 0, 4));

// Batas awal & akhir bulan
$awalBulan = $bulan . "-01";
$akhirBulan = $bulan . "-" . $jumlahHari;

// Ambil semua kelas
$listKelas = $conn->query("SELECT DISTINCT kelas FROM datasiswa ORDER BY kelas");

// QUERY DATA PRESENSI KHUSUS BULAN TERPILIH
$query = "
SELECT 
    ds.nis,
    ds.nama AS nama_siswa,
    ds.kelas,
    pen.nama_pembimbing,
    pen.nama_dudika,
    DATE(pres.timestamp) AS tgl,
    pres.ket
FROM datasiswa ds
LEFT JOIN penempatan pen ON ds.nis = pen.nis_siswa
LEFT JOIN presensi pres 
    ON ds.nis = pres.nis 
    AND DATE(pres.timestamp) BETWEEN '$awalBulan' AND '$akhirBulan'
";

if ($kelas !== "") {
    $query .= " WHERE ds.kelas = '$kelas' ";
}

$query .= " ORDER BY ds.kelas, ds.nama, pres.timestamp ";

$result = $conn->query($query);

// Susun data
$data = [];

while ($row = $result->fetch_assoc()) {
    $nis = $row['nis'];

    if (!isset($data[$nis])) {
        $data[$nis] = [
            'nama' => $row['nama_siswa'],
            'kelas' => $row['kelas'],
            'pembimbing' => $row['nama_pembimbing'],
            'dudika' => $row['nama_dudika'],
            'presensi' => array_fill(1, $jumlahHari, ""), // default kosong
            'count' => [
                'M' => 0,
                'S' => 0,
                'I' => 0,
                'L' => 0
            ]
        ];
    }

    if ($row['tgl']) {
        $hari = intval(date("d", strtotime($row['tgl'])));
        $data[$nis]['presensi'][$hari] = $row['ket'];
        // Hitung M | S | I | L
        $ketRaw = strtolower($row['ket']); // ubah ke huruf kecil dulu

        // Mapping kata → kode
        $map = [
            'masuk' => 'M',
            'sakit' => 'S',
            'izin' => 'I',
            'libur' => 'L'
        ];

        // Default kosong
        $kode = isset($map[$ketRaw]) ? $map[$ketRaw] : "";

        // Simpan ke presensi per tanggal sebagai kode (optional)
        $data[$nis]['presensi'][$hari] = $kode;

        // Hitung jika valid
        if ($kode !== "" && isset($data[$nis]['count'][$kode])) {
            $data[$nis]['count'][$kode]++;
        }
    }
}
?>
<html>

<head>
    <title>Rekap Presensi Bulanan</title>

    <style>
        body {
            font-family: Arial;
        }

        table {
            border-collapse: collapse;
            width: 100%;
            margin-bottom: 40px;
        }

        th,
        td {
            border: 1px solid black;
            padding: 5px;
            text-align: center;
            font-size: 11px;
        }

        th {
            background: #eee;
        }

        .filter-box {
            margin-bottom: 20px;
        }

        @media print {
            .filter-box {
                display: none;
            }
        }

        .nama-left {
            text-align: left !important;
            padding-left: 8px;
        }
    </style>

</head>

<body>

    <div class="filter-box">
        <form method="GET">
            <label><b>Kelas:</b></label>
            <select name="kelas">
                <option value="">Semua Kelas</option>
                <?php while ($k = $listKelas->fetch_assoc()): ?>
                    <option value="<?= $k['kelas'] ?>" <?= ($kelas == $k['kelas']) ? 'selected' : '' ?>>
                        <?= $k['kelas'] ?>
                    </option>
                <?php endwhile; ?>
            </select>

            <label><b>Bulan:</b></label>
            <input type="month" name="bulan" value="<?= $bulan ?>">

            <button type="submit">Tampilkan</button>
            <button onclick="window.print()">Print</button>
        </form>
    </div>

    <h2 style="text-align:center;">REKAP PRESENSI BULANAN</h2>
    <h3 style="text-align:center;">PKL SMK NEGERI BANSARI</h3>

    <?php
    $namaBulanIndo = [
        'January' => 'Januari',
        'February' => 'Februari',
        'March' => 'Maret',
        'April' => 'April',
        'May' => 'Mei',
        'June' => 'Juni',
        'July' => 'Juli',
        'August' => 'Agustus',
        'September' => 'September',
        'October' => 'Oktober',
        'November' => 'November',
        'December' => 'Desember'
    ];

    $bulanInggris = date("F", strtotime($bulan));
    $tahun = date("Y", strtotime($bulan));

    $bulanIndo = strtoupper($namaBulanIndo[$bulanInggris]);
    ?>

    <h4 style="text-align:center;">
        <?= $bulanIndo . " " . $tahun ?> |
        <?= $kelas == "" ? "Semua Kelas" : "Kelas $kelas" ?>
    </h4>

    <table>
        <tr>
            <th rowspan="2">No</th>
            <th rowspan="2">NIS</th>
            <th rowspan="2">Nama</th>
            <th rowspan="2">Kelas</th>
            <th colspan="<?= $jumlahHari ?>">Tanggal</th>
            <th rowspan="2">Pembimbing</th>
            <th rowspan="2">DUDIKA</th>
            <th colspan="4">Keterangan</th>
        </tr>

        <tr>
            <?php for ($i = 1; $i <= $jumlahHari; $i++): ?>
                <th><?= $i ?></th>
            <?php endfor; ?>
            <!-- Subkolom -->
            <th>M</th>
            <th>S</th>
            <th>I</th>
            <th>L</th>
        </tr>

        <?php
        $no = 1;
        foreach ($data as $nis => $d):
            ?>
            <tr>
                <td><?= $no++ ?></td>
                <td><?= $nis ?></td>
                <td class="nama-left"><?= $d['nama'] ?></td>
                <td><?= $d['kelas'] ?></td>

                <?php for ($i = 1; $i <= $jumlahHari; $i++): ?>
                    <td><?= $d['presensi'][$i] ?></td>
                <?php endfor; ?>

                <?php
                $pembimbing = $d['pembimbing'];

                // Pisahkan nama dan gelar
                $parts = explode(',', $pembimbing);

                // Format bagian nama saja
                $nama = ucwords(strtolower(trim($parts[0])));

                // Gabungkan lagi jika ada gelar
                if (isset($parts[1])) {
                    $gelar = trim($parts[1]);
                    $pembimbingFormatted = $nama . ', ' . $gelar;
                } else {
                    $pembimbingFormatted = $nama;
                }
                ?>

                <td class="nama-left"><?= $pembimbingFormatted ?></td>

                <td class="nama-left"><?= $d['dudika'] ?></td>
                <td><?= $d['count']['M'] ?></td>
                <td><?= $d['count']['S'] ?></td>
                <td><?= $d['count']['I'] ?></td>
                <td><?= $d['count']['L'] ?></td>
            </tr>
        <?php endforeach; ?>
    </table>

    <script>
        document.title = "Rekap Presensi <?= $bulanIndo . ' ' . $tahun ?> - <?= $kelas == '' ? 'Semua Kelas' : 'Kelas ' . $kelas ?>";
    </script>

</body>

</html>