<?php
include "../config/koneksi.php";
date_default_timezone_set("Asia/Jakarta");

$nohpAdmin = "6287754446580";
$Admin = "6282241863393";
$message = "";
$siswa = null;

function e($string)
{
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

function formatTanggalIndo($tanggalString)
{
    $timestamp = strtotime($tanggalString); // format: YYYY-MM-DD (ideal) atau lainnya yang bisa diparse
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

// fungsi load & save JSON lupa presensi
function loadLupaJson()
{
    $file = __DIR__ . "/lupa.json";
    if (!file_exists($file))
        return [];
    return json_decode(file_get_contents($file), true) ?: [];
}

function saveLupaJson($data)
{
    $file = __DIR__ . "/lupa.json";
    file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT));
}

// mapping jurusan
$mapJur = [
    'TE' => 'Teknik Elektronika',
    'AT' => 'Agribisnis Tanaman',
    'DKV' => 'Desain Komunikasi Visual'
];

/**
 * Helper: simpan presensi (umum) -> dipakai untuk hari ini & lupa.
 * - Jika $isToday = true: timestamp = now
 * - Jika $isToday = false: timestamp = "$tanggal 08:00:00"
 * - Wajib foto jika $ket == "Masuk"
 * - Validasi ukuran foto (<=100KB) bila ada
 */
function simpanPresensi($conn, $siswa, $ket, $catatan, $tanggalInput, $isToday, &$message)
{
    $timestamp = $isToday ? date("Y-m-d H:i:s") : (date("Y-m-d", strtotime($tanggalInput)) . " 08:00:00");
    $fotoName = "";

    // Validasi ket
    $validKet = ["Masuk", "Izin", "Sakit", "Libur"];
    if (!in_array($ket, $validKet)) {
        $message = "<div class='alert alert-danger'>Keterangan presensi tidak valid.</div>";
        return false;
    }

    // Jika Masuk → wajib foto
    if ($ket === "Masuk") {
        if (!(isset($_FILES['foto']) && $_FILES['foto']['error'] === 0)) {
            $message = "<div class='alert alert-danger'>Presensi dengan keterangan Masuk wajib menyertakan foto.</div>";
            return false;
        }
        if ($_FILES['foto']['size'] > 100 * 1024) {
            $message = "<div class='alert alert-danger'>Ukuran foto terlalu besar (maksimal 100KB).</div>";
            return false;
        }
        $ext = pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION);
        // format: NIS_YYYY-MM-DD
        $tanggalFile = date("Ymd", strtotime($tanggalInput));
        $fotoName = $siswa['nis'] . "_" . $tanggalFile . "." . $ext;
        $target = __DIR__ . "/../img/presensi/" . $fotoName;
        if (!@move_uploaded_file($_FILES['foto']['tmp_name'], $target)) {
            $message = "<div class='alert alert-danger'>Gagal mengunggah foto.</div>";
            return false;
        }
    }

    // Insert (gunakan kolom lengkap agar konsisten)
    $stmt = $conn->prepare("INSERT INTO presensi (nis, namasiswa, kelas, ket, catatan, link, statuslink, kode, timestamp) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $statuslink = "";
    $kode = "";
    $stmt->bind_param(
        "sssssssss",
        $siswa['nis'],
        $siswa['nama'],
        $siswa['kelas'],
        $ket,
        $catatan,
        $fotoName,
        $statuslink,
        $kode,
        $timestamp
    );

    if ($stmt->execute()) {
        $waktu = $isToday ? date("H:i:s") : date("H:i:s", strtotime($timestamp));
        $tanggalShow = $isToday ? date("Y-m-d") : date("Y-m-d", strtotime($timestamp));
        $tanggalHidden = $isToday ? date("d-m-Y") : date("d-m-Y", strtotime($timestamp));
        $ketE = e($ket);
        $waktuE = e($waktu);

        $message = "<div class='alert alert-info'>
            ✅ Presensi Berhasil.<br>
            Tanggal: <strong>" . formatTanggalIndo($tanggalShow) . "</strong><br>
            Keterangan: <strong>$ketE</strong><br>
            Catatan: " . ($catatan ? e($catatan) : "-") . "<br>
            Waktu: <strong>$waktuE</strong><br><br>
            <a href='../presensi/detail.php?nis=" . e($siswa['nis']) . "' class='btn btn-secondary w-100 mb-2'>Rekap&nbsp;Presensi</a>
            <form method='post' class='mt-2'>
                <input type='hidden' name='action' value='batal'>
                <input type='hidden' name='nis' value='" . e($siswa['nis']) . "'>
                <input type='hidden' name='tanggal' value='" . e($tanggalHidden) . "'>
                <button type='submit' class='btn btn-danger w-100'>Batal&nbsp;Presensi</button>
            </form>
        </div>";
        return true;
    } else {
        $message = "<div class='alert alert-danger'>Gagal menyimpan presensi.</div>";
        return false;
    }
}

// === PROSES: Input NIS (cek_nis) ===
if (isset($_POST['cek_nis'])) {
    $nis = trim($_POST['nis']);

    // Validasi: hanya angka
    if (!ctype_digit($nis)) {
        $message = "<div class='alert alert-danger'>NIS hanya boleh berisi angka.</div>";
    } else {
        $stmt = $conn->prepare("SELECT * FROM datasiswa WHERE nis = ?");
        $stmt->bind_param("s", $nis);
        $stmt->execute();
        $result = $stmt->get_result();
        $siswa = $result->fetch_assoc();

        if (!$siswa) {
            $message = "<div class='alert alert-danger'>NIS tidak ditemukan.</div>";
        } else {
            // Cek sudah presensi hari ini
            $today = date("Y-m-d");
            $stmt = $conn->prepare("SELECT ket, timestamp FROM presensi WHERE nis = ? AND DATE(timestamp) = ?");
            $stmt->bind_param("ss", $nis, $today);
            $stmt->execute();
            $cekPresensi = $stmt->get_result();

            if ($cekPresensi->num_rows > 0) {
                $row = $cekPresensi->fetch_assoc();
                $waktu = date("H:i:s", strtotime($row['timestamp']));
                $haritanggal = formatTanggalIndo(date("Y-m-d", strtotime($row['timestamp'])));
                $tanggal = date("d-m-Y");
                $ket = e($row['ket']);
                $waktu = e($waktu);

                $message = "<div class='alert alert-warning'>
                    Anda sudah melakukan presensi hari ini (<strong>$haritanggal</strong>).<br>
                    Keterangan: <strong>$ket</strong><br>
                    Waktu: <strong>$waktu</strong><br><br>
                    <a href='../presensi/detail.php?nis=" . e($siswa['nis']) . "' class='btn btn-secondary w-100'>Rekap&nbsp;Presensi</a>
                    
                    <form method='post' class='mt-2'>
                        <input type='hidden' name='action' value='batal'>
                        <input type='hidden' name='nis' value='" . e($siswa['nis']) . "'>
                        <input type='hidden' name='tanggal' value='" . e($tanggal) . "'>
                        <button type='submit' class='btn btn-danger w-100'>Batal&nbsp;Presensi</button>
                    </form>
                </div>";

                // $siswa = null; // agar form presensi tidak muncul
            }
        }
    }
}

// === PROSES: Simpan presensi (1 form untuk hari ini & lupa) ===
if (isset($_POST['simpan_presensi'])) {
    $nis = $_POST['nis'];
    $tanggal = $_POST['tanggal']; // Y-m-d dari input date
    $ket = $_POST['ket'];
    $catatan = $_POST['catatan'] ?? "";

    // Ambil data siswa
    $stmt = $conn->prepare("SELECT * FROM datasiswa WHERE nis = ?");
    $stmt->bind_param("s", $nis);
    $stmt->execute();
    $result = $stmt->get_result();
    $siswa = $result->fetch_assoc();

    if (!$siswa) {
        $message = "<div class='alert alert-danger'>NIS tidak ditemukan.</div>";
    } else {
        $today = date("Y-m-d");
        $kemarin = date("Y-m-d", strtotime("-1 day"));
        $minTanggal = "2025-07-17";

        // Normalisasi tanggal input
        $tanggalYmd = date("Y-m-d", strtotime($tanggal));

        if ($tanggalYmd > $today) {
            $message = "<div class='alert alert-danger'>Tanggal tidak boleh melebihi hari ini.</div>";
        } else {
            // Cek apakah sudah ada presensi pada tanggal yang dipilih
            $stmt = $conn->prepare("SELECT ket, timestamp FROM presensi WHERE nis = ? AND DATE(timestamp) = ?");
            $stmt->bind_param("ss", $nis, $tanggalYmd);
            $stmt->execute();
            $cekAda = $stmt->get_result();

            if ($cekAda->num_rows > 0) {
                $row = $cekAda->fetch_assoc();
                $waktu = date("H:i:s", strtotime($row['timestamp']));
                $ketAda = e($row['ket']);
                $haritanggal = formatTanggalIndo($tanggalYmd);

                $message = "<div class='alert alert-warning'>
                    Anda sudah melakukan presensi pada tanggal <strong>$haritanggal</strong>.<br>
                    Keterangan: <strong>$ketAda</strong><br>
                    Catatan: " . ($catatan ? e($catatan) : "-") . "<br><br>
                    <a href='../presensi/detail.php?nis=" . e($siswa['nis']) . "' class='btn btn-secondary w-100 mb-2'>Rekap&nbsp;Presensi</a>
                    <form method='post' class='mt-2'>
                        <input type='hidden' name='action' value='batal'>
                        <input type='hidden' name='nis' value='" . e($siswa['nis']) . "'>
                        <input type='hidden' name='tanggal' value='" . e($tanggalYmd) . "'>
                        <button type='submit' class='btn btn-danger w-100'>Batal&nbsp;Presensi</button>
                    </form>
                </div>";
            } else {
                // Tentukan mode: hari ini vs lupa
                if ($tanggalYmd === $today) {
                    // === HARI INI ===
                    // Simpan presensi hari ini
                    simpanPresensi($conn, $siswa, $ket, $catatan, $tanggalYmd, true, $message);
                } else {
                    // === LUPA PRESENSI ===
                    // // Validasi rentang tanggal
                    if ($tanggalYmd < $minTanggal || $tanggalYmd > $kemarin) {
                        $message = "<div class='alert alert-danger'>Tanggal $tanggalYmd tidak valid untuk lupa presensi.</div>";
                    } else {
                        // Hitung & batasi via JSON (maks 2x / hari input)
                        $lupaData = loadLupaJson();
                        if (!isset($lupaData[$nis]))
                            $lupaData[$nis] = [];

                        // Gunakan tanggal hari ini sebagai kunci pembatasan
                        $todayKey = date("Y-m-d");
                        if (!isset($lupaData[$nis][$todayKey]))
                            $lupaData[$nis][$todayKey] = 0;

                        if ($lupaData[$nis][$todayKey] >= 2) {
                            $message = "<div class='alert alert-danger'>Batas maksimal lupa presensi (2x) untuk hari ini sudah tercapai.</div>";
                        } else {
                            // Simpan presensi lupa (timestamp jam 08:00)
                            $ok = simpanPresensi($conn, $siswa, $ket, $catatan, $tanggalYmd, false, $message);
                            if ($ok) {
                                // update counter JSON
                                $lupaData[$nis][$todayKey] += 1;
                                saveLupaJson($lupaData);
                            }
                        }
                    }

                }
            }
        }
    }
}

// === HANDLE BATAL PRESENSI ===
if (isset($_POST['action']) && $_POST['action'] === 'batal' && isset($_POST['nis']) && isset($_POST['tanggal'])) {
    $nis = $_POST['nis'];
    $tanggal = $_POST['tanggal']; // bisa d-m-Y atau Y-m-d
    $formattedDate = date("Y-m-d", strtotime($tanggal));

    // Cari & hapus presensi pada tanggal tsb
    $stmt = $conn->prepare("SELECT * FROM presensi WHERE nis=? AND DATE(timestamp)=?");
    $stmt->bind_param("ss", $nis, $formattedDate);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $stmtDel = $conn->prepare("DELETE FROM presensi WHERE nis=? AND DATE(timestamp)=?");
        $stmtDel->bind_param("ss", $nis, $formattedDate);

        if ($stmtDel->execute()) {
            // update JSON juga (jika pernah dihitung lupa)
            $lupaData = loadLupaJson();
            if (isset($lupaData[$nis][$formattedDate]) && $lupaData[$nis][$formattedDate] > 0) {
                $lupaData[$nis][$formattedDate] -= 1;
                saveLupaJson($lupaData);
            }

            // Hapus file foto
            $tanggalFile = date("Ymd", strtotime($formattedDate));
            $pattern = __DIR__ . "/../img/presensi/" . $nis . "_" . $tanggalFile . ".*"; // bisa jpg/png/jpeg
            foreach (glob($pattern) as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }

            $message = "<div class='alert alert-success'>
                ✅ Presensi berhasil dibatalkan.<br>
                NIS: <strong>" . e($nis) . "</strong><br>
                Tanggal: <strong>" . e($formattedDate) . "</strong><br><br>
                <a href='presensi.php' class='btn btn-secondary w-100'>⬅️ Kembali</a>
            </div>";
        } else {
            $message = "<div class='alert alert-danger'>❌ Gagal membatalkan presensi.</div>";
        }
    } else {
        $message = "<div class='alert alert-warning'>
            Data presensi tidak ditemukan untuk NIS: <strong>" . e($nis) . "</strong> pada tanggal <strong>" . e($formattedDate) . "</strong>.
        </div>";
    }
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Presensi Siswa</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="shortcut icon" href="../img/SMKNBansari.png" type="image/x-icon">
    <style>
        body {
            background: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            padding-bottom: 100px;
            /* ruang tombol floating */
        }

        .container-presensi {
            max-width: 500px;
            margin: 20px auto;
            background: #fff;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.05);
        }

        .form-label {
            font-weight: 600;
        }

        .btn {
            width: 100%;
            border-radius: 8px;
            padding: 12px;
        }

        .alert {
            font-size: 0.9rem;
            border-radius: 8px;
        }

        .preview-img {
            margin-top: 10px;
            max-height: 100px;
            border-radius: 8px;
            border: 1px solid #ddd;
        }

        @media (max-width: 576px) {
            body {
                padding: 10px;
                padding-bottom: 140px;
            }

            .container-presensi {
                padding: 15px;
            }

            .form-label {
                font-size: 0.9rem;
            }

            .btn {
                font-size: 1rem;
            }
        }

        .floating-btn {
            position: fixed;
            bottom: calc(20px + env(safe-area-inset-bottom));
            z-index: 9999;
        }
    </style>
</head>

<body class="bg-light">
    <div class="container py-5">
        <div class="card shadow-lg">
            <div id="judulHariini" class="card-header bg-primary text-white">
                <h4 class="mb-0">📅 Presensi Hari ini</h4>
            </div>

            <div id="judulLupa" class="d-none card-header bg-warning text-white">
                <h4 class="mb-0">⏪ Lupa Presensi</h4>
            </div>
            <div class="card-body">
                <?= $message ?>

                <?php if (!$siswa && !isset($_POST['simpan_presensi'])): ?>
                    <!-- Form input NIS -->
                    <form method="post" class="mb-3">
                        <div class="mb-3">
                            <label for="nis" class="form-label">Masukkan NIS</label>
                            <input type="number" name="nis" id="nis" class="form-control" required>
                        </div>
                        <button type="submit" name="cek_nis" class="btn btn-primary w-100">🔍&nbsp;Cek&nbsp;NIS</button>
                    </form>
                <?php endif; ?>

                <?php if ($siswa) { ?>
                    <!-- Data siswa + 1 form presensi (hari ini & lupa) -->
                    <div class="alert alert-info">
                        <strong><?= e($siswa['nama']); ?></strong><br>
                        NIS: <?= e($siswa['nis']); ?><br>
                        Kelas: <?= e($siswa['kelas']); ?><br>
                        Jurusan: <?= isset($mapJur[$siswa['jur']]) ? $mapJur[$siswa['jur']] : e($siswa['jur']); ?>
                    </div>

                    <form method="post" id="form-presensi" enctype="multipart/form-data">
                        <input type="hidden" name="nis" value="<?= e($siswa['nis']); ?>">

                        <!-- Tombol flip mode -->
                        <style>
                            .toggle-container {
                                display: flex;
                                justify-content: center;
                                align-items: center;
                                gap: 12px;
                            }

                            .switch {
                                position: relative;
                                display: inline-block;
                                width: 80px;
                                height: 40px;
                            }

                            .switch input {
                                display: none;
                            }

                            .slider {
                                position: absolute;
                                cursor: pointer;
                                top: 0;
                                left: 0;
                                right: 0;
                                bottom: 0;
                                background-color: #0d6efd;
                                /* biru Bootstrap */
                                transition: .4s;
                                border-radius: 50px;
                            }

                            .slider:before {
                                position: absolute;
                                content: "📅";
                                height: 32px;
                                width: 32px;
                                left: 4px;
                                bottom: 4px;
                                background-color: white;
                                display: flex;
                                align-items: center;
                                justify-content: center;
                                font-size: 18px;
                                border-radius: 50%;
                                transition: .4s;
                            }

                            input:checked+.slider {
                                background-color: #ffc107;
                                /* kuning Bootstrap */
                            }

                            input:checked+.slider:before {
                                transform: translateX(40px);
                                content: "⏪";
                            }

                            .toggle-label {
                                cursor: pointer;
                                font-weight: 600;
                                min-width: 90px;
                                text-align: center;
                                transition: color 0.3s;
                            }

                            .text-muted {
                                color: #6c757d !important;
                                font-weight: normal !important;
                            }
                        </style>

                        <div class="text-center mb-3">
                            <div class="mb-2 fw-bold">Pilih mode presensi</div>
                            <div class="toggle-container">
                                <div id="btnHariIni" class="toggle-label">Hari Ini</div>
                                <label class="switch">
                                    <input type="checkbox" id="modeToggle">
                                    <span class="slider"></span>
                                </label>
                                <div id="btnLupa" class="toggle-label">Lupa Absen</div>
                            </div>
                        </div>

                        <!-- Tampilan default: Hari Ini (label/alert + hidden input untuk dikirim ke server) -->
                        <div class="alert alert-success" id="tanggalHariIni">
                            Presensi untuk Hari ini<br><strong><?= formatTanggalIndo(date("Y-m-d")); ?></strong>
                            <!-- hidden input untuk dikirim ke server saat mode Hari Ini -->
                            <input type="hidden" name="tanggal" id="hiddenTanggal" value="<?= date("d-m-Y"); ?>">
                        </div>

                        <!-- <label class="form-label">
                            <span class="text-danger fst-italic small">* Wajib diisi</span>
                        </label> -->


                        <!-- Input tanggal (hanya muncul di mode Lupa Presensi) -->
                        <div class="mb-3 d-none" id="tanggalGroup">
                            <label for="tanggal" class="form-label">
                                Tanggal Presensi
                                <span class="text-danger">*</span>
                            </label>
                            <input type="text" name="tanggal" id="tanggal" class="form-control">
                        </div>

                        <div class="mb-3">
                            <label for="ket" class="form-label">
                                Keterangan
                                <span class="text-danger">*</span>
                            </label>
                            <select name="ket" id="ket" class="form-select" required onchange="toggleFotoInput()">
                                <option value="">-- Pilih --</option>
                                <option value="Masuk">Masuk</option>
                                <option value="Izin">Izin</option>
                                <option value="Sakit">Sakit</option>
                                <option value="Libur">Libur</option>
                            </select>
                        </div>

                        <!-- Ambil Foto -->
                        <div class="mb-3" id="fotoGroup" style="display:none;">
                            <label class="form-label d-block">Upload Foto</label>
                            <div class="form-text text-danger fst-italic mb-2">
                                * <strong>Masuk</strong> wajib menyertakan foto
                            </div>

                            <!-- Input file disembunyikan -->
                            <video id="video" autoplay playsinline
                                style="width:100%; max-width:300px; border:1px solid #ccc; border-radius:8px;"></video>
                            <canvas id="canvas" style="display:none;"></canvas>
                            <br>
                            <button type="button" onclick="takePhoto()"
                                class="btn btn-primary d-flex align-items-center justify-content-center shadow-sm mt-2"
                                style="width:100%; max-width:300px; border-radius:12px; font-size:16px; font-weight:500;">
                                <i class="fa-solid fa-camera me-2"></i> Ambil Foto
                            </button>

                            <input type="file" name="foto" id="foto" style="display:none;">

                            <style>
                                #video {
                                    filter: brightness(1.1) contrast(1.05) saturate(1.2) sepia(0.05);
                                    /* mirror horizontal */
                                    transform: scaleX(-1);
                                }
                            </style>

                            <script>
                                async function startCamera() {
                                    try {
                                        const stream = await navigator.mediaDevices.getUserMedia({
                                            video: { facingMode: "user" } // paksa kamera depan
                                        });
                                        document.getElementById("video").srcObject = stream;
                                    } catch (err) {
                                        alert("Tidak bisa akses kamera: " + err);
                                    }
                                }

                                function takePhoto() {
                                    const video = document.getElementById("video");
                                    const canvas = document.getElementById("canvas");
                                    const ctx = canvas.getContext("2d");

                                    canvas.width = video.videoWidth;
                                    canvas.height = video.videoHeight;
                                    ctx.filter = "brightness(1.1) contrast(1.05) saturate(1.2) sepia(0.05)";
                                    ctx.drawImage(video, 0, 0);


                                    canvas.toBlob((blob) => {
                                        // buat event mirip onchange input file
                                        const fakeEvent = { target: { files: [new File([blob], "foto.jpg", { type: "image/jpeg" })] } };
                                        compressAndPreview(fakeEvent);
                                    }, "image/jpeg", 0.8);
                                }

                                startCamera();
                            </script>

                            <!-- Preview -->
                            <div class="mt-2">
                                <img id="preview" src="#" alt="Preview Foto"
                                    style="display:none; max-height:100px; border:1px solid #ccc; padding:2px; border-radius:5px;">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="catatan" class="form-label">Catatan Kegiatan</label>
                            <textarea name="catatan" id="catatan" class="form-control"></textarea>
                        </div>

                        <button type="submit" name="simpan_presensi" class="btn btn-success w-100">💾&nbsp;Simpan&nbsp;<span
                                id="spanTombolSimpan" class="d-none">Lupa&nbsp;</span>Presensi</button>
                    </form>
                <?php } ?>
            </div>
        </div>
    </div>

    <!-- Tombol Floating -->
    <a href="#" onclick="if (history.length > 1) { history.back(); } else { window.location.href='/index.php'; }"
        class="btn btn-secondary btn-lg rounded-circle shadow-lg floating-btn"
        style="position: fixed; bottom: 20px; left: 20px; width: 55px; height: 55px; display:flex; align-items:center; justify-content:center; font-size:22px;">
        ←
    </a>

    <a href="/index.php" class="btn btn-primary btn-lg rounded-circle shadow-lg floating-btn"
        style="position: fixed; bottom: 20px; right: 20px; width: 55px; height: 55px; display:flex; align-items:center; justify-content:center; font-size:22px;">
        🏠
    </a>

    <script>
        function toggleFotoInput() {
            const ket = document.getElementById("ket").value;
            const fotoGroup = document.getElementById("fotoGroup");
            const fotoInput = document.getElementById("foto");
            if (ket === "Masuk") {
                fotoGroup.style.display = "block";
                fotoInput.required = true;
            } else {
                fotoGroup.style.display = "none";
                fotoInput.required = false;
            }
        }

        // Kompres & preview foto (maks 100KB server-side; di sini bantu kompres agar kecil)
        function compressAndPreview(event) {
            const file = event.target.files[0];
            if (!file) return;

            const reader = new FileReader();
            reader.readAsDataURL(file);
            reader.onload = function (e) {
                const img = new Image();
                img.src = e.target.result;

                img.onload = function () {
                    const canvas = document.createElement("canvas");
                    const ctx = canvas.getContext("2d");

                    const MAX_WIDTH = 800;
                    const MAX_HEIGHT = 800;
                    let width = img.width;
                    let height = img.height;

                    if (width > height && width > MAX_WIDTH) {
                        height *= MAX_WIDTH / width;
                        width = MAX_WIDTH;
                    } else if (height >= width && height > MAX_HEIGHT) {
                        width *= MAX_HEIGHT / height;
                        height = MAX_HEIGHT;
                    }

                    canvas.width = width;
                    canvas.height = height;
                    ctx.drawImage(img, 0, 0, width, height);

                    // kompres ke JPEG kualitas 0.7 (turunkan lagi jika masih > 100KB)
                    canvas.toBlob(function (blob) {
                        if (!blob) return;
                        if (blob.size > 100 * 1024) {
                            canvas.toBlob(function (blob2) {
                                replaceFileInput(blob2, file.name);
                                showPreview(canvas.toDataURL("image/jpeg", 0.7));
                            }, "image/jpeg", 0.7);
                        } else {
                            replaceFileInput(blob, file.name);
                            showPreview(URL.createObjectURL(blob));
                        }
                    }, "image/jpeg", 0.8);
                }
            };
        }

        function replaceFileInput(blob, fileName) {
            const file = new File([blob], fileName, { type: "image/jpeg" });
            const dataTransfer = new DataTransfer();
            dataTransfer.items.add(file);
            const fotoInput = document.getElementById("foto");
            fotoInput.files = dataTransfer.files;

            // pastikan value berubah supaya required tidak gagal
            if (fotoInput.files.length > 0) {
                fotoInput.setCustomValidity(""); // clear error
            } else {
                fotoInput.setCustomValidity("Wajib ambil foto");
            }
        }

        function showPreview(src) {
            const preview = document.getElementById('preview');
            preview.src = src;
            preview.style.display = 'block';
        }
    </script>
    <!-- Flatpickr (pastikan hanya satu kali include) -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

    <script>
        const tanggalGroup = document.getElementById("tanggalGroup");
        const tanggalHariIni = document.getElementById("tanggalHariIni");
        const hiddenTanggal = document.getElementById("hiddenTanggal");
        const tanggalInput = document.getElementById("tanggal");
        const judulHariini = document.getElementById("judulHariini");
        const judulLupa = document.getElementById("judulLupa");
        const spanTombolSimpan = document.getElementById("spanTombolSimpan");
        const btnHariIni = document.getElementById("btnHariIni");
        const btnLupa = document.getElementById("btnLupa");
        const toggle = document.getElementById("modeToggle");
        let fp = null;

        // Inisialisasi: default = Hari Ini
        document.addEventListener("DOMContentLoaded", () => {
            // Pastikan kondisi awal: hiddenTanggal aktif, input tanggal disabled
            setHariIniMode();
        });

        // update tampilan label
        function updateLabel() {
            if (toggle.checked) {
                btnHariIni.classList.add("text-muted");
                btnLupa.classList.remove("text-muted");
            } else {
                btnHariIni.classList.remove("text-muted");
                btnLupa.classList.add("text-muted");
            }
        }

        // klik label kiri
        btnHariIni.addEventListener("click", () => setMode(false));

        // klik label kanan
        btnLupa.addEventListener("click", () => setMode(true));

        // toggle manual
        toggle.addEventListener("change", () => {
            updateLabel();
            toggle.checked ? setLupaMode() : setHariIniMode();
        });

        // set awal
        updateLabel();

        function setHariIniMode() {
            // tampilkan alert hari ini, sembunyikan input tanggal
            tanggalGroup.classList.add("d-none");
            tanggalHariIni.classList.remove("d-none");

            judulHariini.classList.remove("d-none");
            judulLupa.classList.add("d-none");

            spanTombolSimpan.classList.add("d-none");

            btnHariIni.classList.add("disabled");
            btnLupa.classList.remove("disabled");


            // isi ulang hidden input dengan tanggal hari ini (format d-m-Y)
            const today = new Date();
            const dd = String(today.getDate()).padStart(2, '0');
            const mm = String(today.getMonth() + 1).padStart(2, '0');
            const yyyy = today.getFullYear();
            hiddenTanggal.value = dd + "-" + mm + "-" + yyyy;

            // PENTING: enable hiddenTanggal (supaya dikirim), disable visible input
            hiddenTanggal.disabled = false;
            tanggalInput.disabled = true;
            tanggalInput.required = false;

            // jika flatpickr aktif sebelumnya, destroy agar tidak menyebabkan masalah
            if (fp) {
                fp.destroy();
                fp = null;
            }
        }

        function setLupaMode() {
            // sembunyikan alert hari ini, tampilkan input tanggal
            tanggalHariIni.classList.add("d-none");
            tanggalGroup.classList.remove("d-none");

            judulHariini.classList.add("d-none");
            judulLupa.classList.remove("d-none");

            spanTombolSimpan.classList.remove("d-none");

            btnHariIni.classList.remove("disabled");
            btnLupa.classList.add("disabled");

            // PENTING: non-aktifkan hiddenTanggal sehingga tidak ikut dikirim
            hiddenTanggal.disabled = true;

            // aktifkan visible input agar dikirim; required agar user memilih
            tanggalInput.disabled = false;
            tanggalInput.required = true;

            // aktifkan flatpickr hanya saat lupa mode
            if (!fp) {
                fp = flatpickr("#tanggal", {
                    dateFormat: "d-m-Y",
                    minDate: "2025-07-17",
                    maxDate: "today",
                    defaultDate: "today",
                    // pastikan value terisi
                    onReady: function (selectedDates, dateStr, instance) {
                        if (!dateStr) instance.setDate(new Date());
                    }
                });
                // pastikan input mendapat nilai sekarang setelah inisialisasi
                fp.setDate(new Date(), true, "d-m-Y");
            }
        }
    </script>

    <?php
    // echo "<pre>";
    // print_r($siswa);
    // echo "</pre>";
    
    // if (empty($siswa)) {
    //     echo "Tidak ada data siswa";
    // }
    ?>
</body>

</html>