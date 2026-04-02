<?php
session_start();
date_default_timezone_set('Asia/Jakarta');
$tanggal = date('Y-m-d');
$tahun = date('Y');

// Cek apakah pengguna sudah login
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    $_SESSION['redirect_to'] = $_SERVER['REQUEST_URI'];
    header("Location: ../app/login.php");
    exit();
}

include "../config/koneksi.php";

/* =========================
   DATA REKAP PER KELAS
   ========================= */
$qRekap = "
SELECT 
    kelas,
    COUNT(*) AS total_siswa,
    SUM(CASE WHEN nohp IS NOT NULL AND nohp != '' THEN 1 ELSE 0 END) AS sudah_daftar,
    SUM(CASE WHEN nohp IS NULL OR nohp = '' THEN 1 ELSE 0 END) AS belum_daftar
FROM datasiswa
GROUP BY kelas
ORDER BY kelas
";
$rekap = $conn->query($qRekap);

$qKelas = $conn->query("SELECT * FROM datawalikelas ORDER BY kelas ASC");

/* =====================================================
   AMBIL DATA PENEMPATAN + STATUS SISWA + PEMBIMBING
   ===================================================== */
$q = "
SELECT 
    p.nama_dudika,
    p.nama_siswa,
    p.nis_siswa,
    p.kelas,
    p.nama_pembimbing,
    d.nohp AS nohp_siswa,
    pb.nohp AS nohp_pembimbing
FROM penempatan p
LEFT JOIN datasiswa d 
    ON p.nis_siswa = d.nis
LEFT JOIN datapembimbing pb
    ON p.nama_pembimbing = pb.nama
ORDER BY p.nama_dudika, p.nama_siswa
";

$result = $conn->query($q);

/* =====================================================
   SUSUN DATA PER DUDI
   ===================================================== */
$dataDudi = [];

while ($row = $result->fetch_assoc()) {
    $dudi = $row['nama_dudika'];

    if (!isset($dataDudi[$dudi])) {
        $dataDudi[$dudi] = [
            'total' => 0,
            'sudah' => 0,
            'belum' => 0,
            'pembimbing' => $row['nama_pembimbing'],
            'nohp_pembimbing' => $row['nohp_pembimbing'],
            'belum_list' => []
        ];
    }

    $dataDudi[$dudi]['total']++;

    if (!empty($row['nohp_siswa'])) {
        $dataDudi[$dudi]['sudah']++;
    } else {
        $dataDudi[$dudi]['belum']++;
        $dataDudi[$dudi]['belum_list'][] = [
            'nis' => $row['nis_siswa'],
            'nama' => $row['nama_siswa'],
            'kelas' => $row['kelas']
        ];
    }
}

/* =====================================================
   RINGKASAN GLOBAL
   ===================================================== */
$totalAll = $totalSudah = $totalBelum = 0;

foreach ($dataDudi as $d) {
    $totalAll += $d['total'];
    $totalSudah += $d['sudah'];
    $totalBelum += $d['belum'];
}

/* =====================================================
   HELPER FORMAT WA
   ===================================================== */
function wa($no)
{
    if (!$no)
        return '';
    $no = preg_replace('/[^0-9]/', '', $no);
    if (substr($no, 0, 1) == '0') {
        $no = '62' . substr($no, 1);
    }
    return $no;
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Dashboard Pendaftaran PKL</title>

    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f5f6f8;
            font-size: 14px;
            margin: 20px;
        }

        table {
            border-collapse: collapse;
            width: 100%;
            margin-top: 10px;
            border: 1px solid #636060ff;
            padding: 5px;
            text-align: center;
        }

        .ok {
            color: green;
            font-weight: bold;
        }

        .no {
            color: red;
            font-weight: bold;
        }

        h1,
        h2 {
            margin-top: 30px;
        }

        th {
            background: #34495e;
            color: #fff;
            padding: 10px;
            text-align: center;
        }

        td {
            padding: 8px;
            border-bottom: 1px solid #ddd;
        }

        .summary {
            display: flex;
            gap: 15px;
            margin-bottom: 30px;
        }

        .summary-box {
            flex: 1;
            background: #f3f2f2ff;
            border-radius: 6px;
            padding: 15px;
            border-left: 6px solid #ccc;
        }

        .summary-box.total {
            border-color: #34495e;
            color: #111;
        }

        .summary-box.ok {
            border-color: #2ecc71;
        }

        .summary-box.bad {
            border-color: #e74c3c;
            color: #9e2012ff;
        }

        .summary-box .value {
            font-size: 28px;
            font-weight: bold;
        }

        .dudi-card {
            background: #272626ff;
            border-radius: 6px;
            padding: 15px;
            margin-bottom: 15px;
            border-left: 6px solid #ccc;
        }

        .dudi-card.problem {
            border-color: #e74c3c;
            background: #2b2a2aff;
        }

        .dudi-card.safe {
            border-color: #bdc3c7;
        }

        .dudi-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
        }

        .badge.red {
            background: #e74c3c;
            color: #fff;
        }

        .badge.green {
            background: #2ecc71;
            color: #fff;
        }

        .btn {
            display: inline-block;
            padding: 5px 10px;
            background: #2ecc71;
            color: #fff;
            text-decoration: none;
            border-radius: 4px;
            font-size: 12px;
        }

        .btn.wa {
            background: #25D366;
        }

        .btn.toggle {
            background: #34495e;
            margin-bottom: 10px;
        }

        .small-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }

        .small-table th,
        .small-table td {
            border: 1px solid #ddd;
            padding: 6px;
        }

        .p-red {
            color: #c0392b;
            font-weight: bold;
        }

        .p-orange {
            color: #e67e22;
            font-weight: bold;
        }

        .p-yellow {
            color: #f1c40f;
            font-weight: bold;
        }

        .p-green {
            color: #27ae60;
            font-weight: bold;
        }

        .p-blue {
            color: #2980b9;
            font-weight: bold;
        }

        .p-max {
            color: #8916a0ff;
            font-weight: bold;
        }

        .card1 {
            background: #272626ff;
            border-radius: 6px;
            border: 1px solid #ccc;
            padding: 15px;
            margin-bottom: 20px;
            border-left: 6px solid #a8b404ff;
        }

        .tb-left {
            text-align: left;
        }
    </style>

    <script>
        function toggleAman() {
            const el = document.getElementById('dudi-aman');
            el.style.display = (el.style.display === 'none') ? 'block' : 'none';
        }
    </script>

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
            /* color: #aaaaaa; */
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

    <div class="container">

        <h1>Dashboard Pendaftaran PKL</h1>

        <!-- ================= RINGKASAN ================= -->
        <?php
        $persenTerdaftar = ($totalAll > 0) ? round(($totalSudah / $totalAll) * 100, 2) : 0;
        $persenBelum = ($totalAll > 0) ? round(($totalBelum / $totalAll) * 100, 2) : 0;
        ?>
        <div class="summary">
            <div class="summary-box total">
                <div>Total Siswa PKL</div>
                <div class="value"><?= $totalAll ?></div>
            </div>
            <div class="summary-box ok">
                <div>Sudah Terdaftar <?= "(" . $persenTerdaftar . " %)"; ?></div>
                <div class="value"><?= $totalSudah ?></div>
            </div>
            <div class="summary-box bad">
                <div>Belum Terdaftar <?= "(" . $persenBelum . " %)"; ?></div>
                <div class="value"><?= $totalBelum ?></div>
            </div>
        </div>
        
        <!-- Tampilkan waktu timestamp load di sebelah kanan -->
        <div style="text-align: right; font-size: 12px; color: #ccc;">
            Last updated: <?= date('d M Y H:i:s'); ?>
        </div>

        <!-- =========================
     REKAP PER KELAS
     ========================= -->
        <h2>Rekap Pendaftaran per Kelas</h2>
        <table class="small-table mb-4">
            <tr>
                <th>No.</th>
                <th>Kelas</th>
                <th>Total Siswa</th>
                <th>Sudah Terdaftar</th>
                <th>Belum Terdaftar</th>
                <th>% Persentase</th>
            </tr>
            <?php 
            $nom = 1;
            while ($r = $rekap->fetch_assoc()): 
            ?>
                <tr>
                    <td><?= $nom++; ?></td>
                    <td><?= $r['kelas'] ?></td>
                    <td><?= $r['total_siswa'] ?></td>
                    <td class="ok"><?= $r['sudah_daftar'] ?></td>
                    <td class="no"><?= $r['belum_daftar'] ?></td>

                    <?php
                    $pesenkelas = ($r['total_siswa'] > 0) ? round(($r['sudah_daftar'] / $r['total_siswa']) * 100, 2) : 0;

                    $pesenkelas = ($r['total_siswa'] > 0)
                        ? round(($r['sudah_daftar'] / $r['total_siswa']) * 100, 2)
                        : 0;

                    if ($pesenkelas < 25) {
                        $kelasWarna = "p-red";
                    } elseif ($pesenkelas < 50) {
                        $kelasWarna = "p-orange";
                    } elseif ($pesenkelas < 75) {
                        $kelasWarna = "p-yellow";
                    } elseif ($pesenkelas < 100) {
                        $kelasWarna = "p-green";
                    } else {
                        $kelasWarna = "p-blue";
                    }

                    ?>
                    <td class="<?= $kelasWarna ?>">
                        <?= $pesenkelas ?> %
                    </td>

                </tr>
            <?php endwhile; ?>
        </table>

        <h2>Kelas Belum Mendaftar</h2>
        <?php
        $nokelas = 0;
        while ($wk = $qKelas->fetch_assoc()) {
            $kelas = $wk['kelas'];

            $qSiswa = $conn->query("
        SELECT nis, nama 
        FROM datasiswa 
        WHERE kelas='$kelas'
        AND (nohp IS NULL OR nohp = '')
    ");

            if ($qSiswa->num_rows == 0) {
                continue;
            }
            $nokelas++;
            ?>
            <div class="card1">
                <h3><?= $nokelas; ?>. Kelas <?= htmlspecialchars($kelas) ?></h3>
                <p>
                    &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Wali Kelas: <strong><?= htmlspecialchars($wk['nama']) ?></strong><br>
                    <!--&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;No HP: <?= htmlspecialchars($wk['nohp']) ?>-->
                </p>

                <table class="small-table">
                    <tr>
                        <th>No</th>
                        <th>NIS</th>
                        <th>Nama</th>
                    </tr>

                    <?php
                    $no = 1;
                    $list = "";

                    while ($s = $qSiswa->fetch_assoc()) {
                        $list .= "- {$s['nis']} {$s['nama']}\n";
                        ?>
                        <tr>
                            <td><?= $no++ ?></td>
                            <td><?= $s['nis'] ?></td>
                            <td class="tb-left"><?= $s['nama'] ?></td>
                        </tr>
                    <?php } ?>
                </table>
                <?php
                $pesan = urlencode(
                    "Assalamu’alaikum.\n\n" .
                    "Yth. Bapak/Ibu {$wk['nama']} (Wali Kelas $kelas)\n\n" .
                    "Berikut siswa yang BELUM melakukan pendaftaran Presensi PKL:\n\n" .
                    $list .
                    "\nMohon ditindaklanjuti.\nTerima kasih."
                );
                ?>
                <div class="mt-3">
                    <a class="btn wa" target="_blank"
                        href="https://wa.me/<?= preg_replace('/[^0-9]/', '', $wk['nohp']) ?>?text=<?= $pesan ?>">
                        Hubungi Wali Kelas
                    </a>
                </div>
            </div>
        <?php } ?>


        <!-- ================= DUDI BERMASALAH ================= -->
        <h2>DUDI Bermasalah</h2>

        <?php foreach ($dataDudi as $namaDudi => $d): ?>
            <?php if ($d['belum'] > 0): ?>

                <div class="dudi-card problem">
                    <div class="dudi-header">
                        <strong><?= $namaDudi ?></strong>
                        <span class="badge red"><?= $d['belum'] ?> BELUM</span>
                    </div>

                    <div style="margin-top:5px;">
                        Pembimbing: <strong><?= $d['pembimbing'] ?></strong>
                        <?php if ($d['nohp_pembimbing']): ?>
                            | <a class="btn wa" target="_blank"
                                href="https://wa.me/<?= wa($d['nohp_pembimbing']) ?>?text=Assalamu’alaikum%20Pak/Bu%20<?= urlencode($d['pembimbing']) ?>,%0ABerikut%20siswa%20PKL%20yang%20belum%20mendaftar:">
                                Hubungi Pembimbing
                            </a>
                        <?php endif; ?>
                    </div>

                    <table class="small-table">
                        <tr>
                            <th>NIS</th>
                            <th>Nama</th>
                            <th>Kelas</th>
                        </tr>
                        <?php foreach ($d['belum_list'] as $s): ?>
                            <tr>
                                <td><?= $s['nis'] ?></td>
                                <td class="tb-left"><?= $s['nama'] ?></td>
                                <td><?= $s['kelas'] ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                </div>

            <?php endif; endforeach; ?>

        <!-- ================= DUDI AMAN ================= -->
        <h2>
            DUDI Aman
            <button class="btn toggle" onclick="toggleAman()">Hide / Unhide</button>
        </h2>

        <div id="dudi-aman" style="display:none;">
            <?php foreach ($dataDudi as $namaDudi => $d): ?>
                <?php if ($d['belum'] == 0): ?>
                    <div class="dudi-card safe">
                        <div class="dudi-header">
                            <strong><?= $namaDudi ?></strong>
                            <span class="badge green">AMAN</span>
                        </div>
                        Pembimbing: <?= $d['pembimbing'] ?> | Total: <?= $d['total'] ?>
                    </div>
                <?php endif; endforeach; ?>
        </div>
    </div>

    <div class="text-white text-center mb-3 mb-md-0">
        Copyright © <?= $tahun; ?>
    </div>
</body>

</html>