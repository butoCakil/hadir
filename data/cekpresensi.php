<?php
session_start();
date_default_timezone_set('Asia/Jakarta');
$tanggal = date('Y-m-d');
$tahun = date('Y');

if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    $_SESSION['redirect_to'] = $_SERVER['REQUEST_URI'];
    header("Location: ../app/login.php");
    exit();
}

include "../config/koneksi.php";

function buildPesanWA($conn, $mode, $value)
{

    $map = [
        'kelas' => 'p.kelas',
        'dudi' => 'p.nama_dudika',
        'pembimbing' => 'p.nama_pembimbing'
    ];

    $field = $map[$mode];

    $stmt = $conn->prepare("
        SELECT ds.nama, ds.kelas
        FROM penempatan p
        JOIN datasiswa ds ON p.nis_siswa = ds.nis
        LEFT JOIN presensi pr
            ON p.nis_siswa = pr.nis
            AND DATE(pr.timestamp) = CURDATE()
        WHERE $field = ?
        AND pr.nis IS NULL
        ORDER BY ds.nama
    ");

    $stmt->bind_param("s", $value);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res->num_rows == 0) {
        return "Semua siswa sudah melakukan presensi hari ini.";
    }

    $list = "";
    $no = 1;
    while ($r = $res->fetch_assoc()) {
        $list .= $no++ . ". {$r['nama']} ({$r['kelas']})\n";
    }

    return
        "Informasi Presensi PKL Hari Ini (" . date('d/m/Y') . ")\n\n" .
        "Berikut daftar siswa yang *BELUM* melakukan presensi:\n\n" .
        $list .
        "\nMohon tindak lanjutnya.\n\nTerima kasih.";
}

/* =====================================================
   AJAX HANDLER — HARUS PALING ATAS (SEBELUM HTML)
   ===================================================== */
if (isset($_GET['ajax'])) {

    /* ================= DASHBOARD DINAMIS ================= */
    if ($_GET['ajax'] === 'dashboard') {

        $mode = $_GET['mode'] ?? '';
        $map = [
            'kelas' => 'p.kelas',
            'dudi' => 'p.nama_dudika',
            'pembimbing' => 'p.nama_pembimbing'
        ];

        if (!isset($map[$mode]))
            exit;

        $field = $map[$mode];

        $q = "
        SELECT 
            $field AS label,
            COUNT(p.nis_siswa) AS total,
            COUNT(pr.nis) AS sudah,
            COUNT(p.nis_siswa) - COUNT(pr.nis) AS belum,

            dp.nohp AS nohp_pembimbing,
            p.nomor_telepon_dudika AS nohp_dudi,
            wk.nohp AS nohp_walikelas

        FROM penempatan p

        LEFT JOIN presensi pr
            ON p.nis_siswa = pr.nis
            AND DATE(pr.timestamp) = CURDATE()

        LEFT JOIN datapembimbing dp
            ON p.nama_pembimbing = dp.nama

        LEFT JOIN datawalikelas wk
            ON p.kelas = wk.kelas

        GROUP BY $field
        ORDER BY $field
        ";

        $res = $conn->query($q);

        while ($r = $res->fetch_assoc()) {
            ?>
            <?php
                $pesan = urlencode(buildPesanWA($conn, $mode, $r['label']));
                ?>
            
            <div class="card1">
                <strong><?= htmlspecialchars($r['label']) ?></strong><br>
                Total: <?= $r['total'] ?> |
                Sudah: <span class="ok"><?= $r['sudah'] ?></span> |
                Belum: <span class="no"><?= $r['belum'] ?></span><br>
            
                <div class="mt-2 d-flex flex-wrap gap-2">
            
                    <button class="btn btn-sm btn-primary"
                        onclick="showDetail('<?= $mode ?>','<?= htmlspecialchars($r['label'], ENT_QUOTES) ?>')">
                        Lihat Detail
                    </button>
            
                    <?php if ($mode === 'pembimbing' && $r['nohp_pembimbing']) { ?>
                        <a class="btn btn-sm btn-success" target="_blank"
                            href="https://wa.me/<?= preg_replace('/\D/', '', $r['nohp_pembimbing']) ?>?text=<?= $pesan ?>">
                            WA Pembimbing
                        </a>
                    <?php } ?>
            
                    <?php if ($mode === 'dudi' && $r['nohp_dudi']) { ?>
                        <a class="btn btn-sm btn-warning" target="_blank"
                            href="https://wa.me/<?= preg_replace('/\D/', '', $r['nohp_dudi']) ?>?text=<?= $pesan ?>">
                            WA DUDI
                        </a>
                    <?php } ?>
            
                    <?php if ($mode === 'kelas' && $r['nohp_walikelas']) { ?>
                        <a class="btn btn-sm btn-info" target="_blank"
                            href="https://wa.me/<?= preg_replace('/\D/', '', $r['nohp_walikelas']) ?>?text=<?= $pesan ?>">
                            WA Wali Kelas
                        </a>
                    <?php } ?>
            
                </div>
            </div>
            <?php
        }
        exit;
    }

    /* ================= DETAIL MODAL ================= */
    if ($_GET['ajax'] === 'detail') {

        $mode = $_GET['mode'];
        $value = $_GET['value'];

        $map = [
            'kelas' => 'p.kelas',
            'dudi' => 'p.nama_dudika',
            'pembimbing' => 'p.nama_pembimbing'
        ];

        if (!isset($map[$mode]))
            exit;

        $field = $map[$mode];

        $stmt = $conn->prepare("
        SELECT 
            p.nis_siswa,
            ds.nama,
            ds.kelas,
            ds.nohp AS nohp_siswa,
            dp.nohp AS nohp_pembimbing,
            IF(pr.nis IS NULL,'BELUM','SUDAH') AS status
        FROM penempatan p
        JOIN datasiswa ds 
            ON p.nis_siswa = ds.nis
        LEFT JOIN datapembimbing dp 
            ON p.nama_pembimbing = dp.nama
        LEFT JOIN presensi pr
            ON p.nis_siswa = pr.nis
            AND DATE(pr.timestamp) = CURDATE()
        WHERE $field = ?
        ORDER BY ds.nama
    ");

        $stmt->bind_param("s", $value);
        $stmt->execute();

        $rows = [];
        $res = $stmt->get_result();
        while ($r = $res->fetch_assoc()) {
            $rows[] = [
                'nis' => $r['nis_siswa'],
                'nama' => $r['nama'],
                'kelas' => $r['kelas'],
                'nohp_siswa' => $r['nohp_siswa'],
                'nohp_pembimbing' => $r['nohp_pembimbing'] ?? '-',
                'status' => $r['status']
            ];
        }

        header('Content-Type: application/json');
        echo json_encode([
            'title' => strtoupper($mode) . ' : ' . $value,
            'rows' => $rows
        ]);
        exit;
    }
}

/* =====================================================
   DASHBOARD GLOBAL (HANYA SEKALI LOAD)
   ===================================================== */
$qGlobal = "
SELECT 
    COUNT(DISTINCT p.nis_siswa) AS total,
    COUNT(DISTINCT pr.nis) AS sudah,
    COUNT(DISTINCT p.nis_siswa) - COUNT(DISTINCT pr.nis) AS belum
FROM penempatan p
LEFT JOIN presensi pr
    ON p.nis_siswa = pr.nis
    AND DATE(pr.timestamp) = CURDATE()
";
$global = $conn->query($qGlobal)->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Dashboard Presensi PKL</title>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
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
    <style>
        .summary {
            display: flex;
            gap: 15px;
            margin-bottom: 30px;
        }

        .summary-box {
            flex: 1;
            background: #272626;
            padding: 15px;
            border-radius: 6px;
            border-left: 6px solid #aaa;
        }

        .summary-box.total {
            border-color: #3498db;
        }

        .summary-box.ok {
            border-color: #2ecc71;
        }

        .summary-box.bad {
            border-color: #e74c3c;
        }

        .value {
            font-size: 28px;
            font-weight: bold;
        }

        .card1 {
            background: #272626;
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 12px;
            border-left: 6px solid #888;
        }

        .ok {
            color: #2ecc71;
            font-weight: bold;
        }

        .no {
            color: #e74c3c;
            font-weight: bold;
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

    <div class="container py-4">

        <h2>Dashboard Presensi PKL (<?= date('d F Y') ?>)</h2>

        <!-- ================= GLOBAL SUMMARY ================= -->
        <div class="summary">
            <div class="summary-box total">
                <div>Total Siswa PKL</div>
                <div class="value"><?= $global['total'] ?></div>
            </div>
            <div class="summary-box ok">
                <div>Sudah Presensi</div>
                <div class="value"><?= $global['sudah'] ?></div>
            </div>
            <div class="summary-box bad">
                <div>Belum Presensi</div>
                <div class="value"><?= $global['belum'] ?></div>
            </div>
        </div>
        
        <!-- Tampilkan waktu timestamp load di sebelah kanan -->
        <div style="text-align: right; font-size: 12px; color: #ccc;">
            Last updated: <?= date('d M Y H:i:s'); ?>
        </div>

        <!-- ================= SELECT MODE ================= -->
        <div class="mb-3">
            <label class="form-label">Tampilkan Berdasarkan</label>
            <select class="form-select" id="mode">
                <option value="">-- Pilih --</option>
                <option value="kelas">Kelas</option>
                <option value="dudi">DUDI</option>
                <option value="pembimbing">Pembimbing</option>
            </select>
        </div>

        <div id="dashboard-area"></div>

    </div>

    <!-- ================= MODAL DETAIL ================= -->
    <div class="modal fade" id="detailModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content bg-dark text-white">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle"></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <table class="table table-dark table-sm">
                        <thead>
                            <tr>
                                <th>NIS</th>
                                <th>Nama</th>
                                <th>Kelas</th>
                                <th>No HP Siswa</th>
                                <th>No HP Pembimbing</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody id="modalBody"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        document.getElementById('mode').addEventListener('change', function () {
            const area = document.getElementById('dashboard-area');
            area.innerHTML = ''; // reset

            if (!this.value) return;

            fetch('cekpresensi.php?ajax=dashboard&mode=' + this.value)
                .then(res => res.text())
                .then(html => area.innerHTML = html);
        });

        function showDetail(mode, value) {
            fetch(`cekpresensi.php?ajax=detail&mode=${mode}&value=${encodeURIComponent(value)}`)
                .then(res => res.json())
                .then(data => {
                    document.getElementById('modalTitle').innerText = data.title;

                    let html = '';
                    data.rows.forEach(r => {
                        html += `
                <tr>
                    <td>${r.nis}</td>
                    <td>${r.nama}</td>
                    <td>${r.kelas}</td>
                    <td>${r.nohp_siswa}</td>
                    <td>${r.nohp_pembimbing}</td>
                    <td class="${r.status === 'SUDAH' ? 'ok' : 'no'}">${r.status}</td>
                </tr>`;
                    });

                    document.getElementById('modalBody').innerHTML = html;
                    new bootstrap.Modal(document.getElementById('detailModal')).show();
                });
        }
    </script>

    <div class="text-white text-center mb-3 mb-md-0">
        Copyright © <?= $tahun; ?>
    </div>
</body>

</html>