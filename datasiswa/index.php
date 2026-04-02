<?php
session_start();
$_SESSION['redirect_to'] = $_SERVER['REQUEST_URI'];

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
            color: #BBBBBB;
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

        .edited-success {
            background-color: #198754 !important;
            /* Bootstrap success */
            color: #fff;
            transition: background-color 1.5s ease;
        }

        .edited-error {
            background-color: #dc3545 !important;
            /* Bootstrap danger */
            color: #fff;
        }
        
        /* Normalisasi link di tabel */
        table a {
            color: inherit;          /* ikut warna teks */
            text-decoration: none;   /* hilangkan garis bawah */
        }
        
        table a:hover {
            text-decoration: underline; /* opsional: underline saat hover */
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
                        <!-- <li class="nav-item">
                            <span class="nav-link disabled">About</span>
                        </li> -->
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
        <div class="container">
            <h2 class="text-center">Data Penempatan Siswa</h2>

            <?php
            include "../config/koneksi.php";

            $selected_pembimbing = trim($_GET['pembimbing'] ?? '');

            // Ambil nama pembimbing unik (tidak NULL dan tidak kosong)
            $query = "SELECT DISTINCT nama_pembimbing FROM penempatan WHERE nama_pembimbing IS NOT NULL AND nama_pembimbing != '' ORDER BY nama_pembimbing ASC";
            $result = $conn->query($query);
            ?>

            <!-- Select dengan onchange untuk redirect -->
            <select name="pembimbing" id="pembimbing" onchange="redirectToPembimbing(this.value)">
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
            // Query untuk mengambil data dari tabel `datasiswa` dan mencocokkannya ke tabel `penempatan` berdasarkan NIS
            
            $nama_pembimbing = trim($_GET['pembimbing'] ?? '');

            // Buat query dasar
            $query = "
            SELECT
            ds.nis,
            ds.nama AS nama_siswa,
            ds.kelas,
            ds.jur AS jurusan,
            ds.lp,
            ds.nohp,
            pen.nama_pembimbing,
            pen.nama_dudika
            FROM datasiswa ds
            LEFT JOIN penempatan pen ON ds.nis = pen.nis_siswa
            ";

            // Tambahkan filter jika nama pembimbing tidak kosong
            if (!empty($nama_pembimbing)) {
                // Gunakan real_escape_string untuk menghindari SQL injection
                $nama_pembimbing_safe = $conn->real_escape_string($nama_pembimbing);
                $query .= " WHERE pen.nama_pembimbing = '$nama_pembimbing_safe'";
            }

            // Tambahkan urutan hasil
            $query .= " ORDER BY pen.nama_pembimbing ASC, pen.nama_dudika ASC, ds.nis ASC";

            // Jalankan query
            $result = $conn->query($query);

            // Ambil daftar pembimbing unik
            $listPembimbing = [];
            $qPembimbing = $conn->query("
                SELECT DISTINCT nama_pembimbing 
                FROM penempatan 
                WHERE nama_pembimbing IS NOT NULL 
                AND nama_pembimbing != ''
                ORDER BY nama_pembimbing ASC
            ");

            if ($qPembimbing && $qPembimbing->num_rows > 0) {
                while ($p = $qPembimbing->fetch_assoc()) {
                    $listPembimbing[] = $p['nama_pembimbing'];
                }
            }



            // Cek dan tampilkan hasil
            if ($result && $result->num_rows > 0) {
                echo "<table id='tabelSiswa' class='table table-striped table-bordered' cellspacing='0' width='100%'>";

                // echo "<table class='table' border='1' cellspacing='0' cellpadding='5'>";
                echo "
                    <thead>
                        <tr>
                            <th>No.</th>
                            <th>Nama Siswa</th>
                            <th>WA</th>
                            <th>LP</th>
                            <th>Kelas</th>
                            <th>Jurusan</th>
                            <th>Pembimbing</th>
                            <th>DUDIKA</th>
                        </tr>
                    </thead>
                    <tbody>";

                $no = 1;
                while ($row = $result->fetch_assoc()) {
                    echo '<tr>
                        <td>' . $no++ . '</td>
                        <td style="text-align:left;">
                            <a href="../presensi/detail.php?nis=' . $row['nis_siswa'] . '">' . $row['nama_siswa'] . '</a>
                        </td>
                        <td>' . $row['nohp'] . '</td>
                        <td>' . $row['lp'] . '</td>
                        <td>' . $row['kelas'] . '</td>
                        <td>' . $row['jurusan'] . '</td>';

                    if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
                        ?>
                        <td>
                            <select class="form-select form-select-sm editable-pembimbing" data-nis="<?= $row['nis']; ?>">

                                <option value="">-- Pilih Pembimbing --</option>

                                <?php foreach ($listPembimbing as $pembimbing): ?>
                                    <option value="<?= htmlspecialchars($pembimbing); ?>" <?= ($pembimbing === $row['nama_pembimbing']) ? 'selected' : ''; ?>>
                                        <?= htmlspecialchars($pembimbing); ?>
                                    </option>
                                <?php endforeach; ?>

                            </select>
                        </td>

                        <?php

                    } else {
                        echo "<td>" . (!empty($row['nama_pembimbing']) ? $row['nama_pembimbing'] : '-') . "</td>";
                    }
                    echo "<td class='editable-dudika' contenteditable='true' data-nis='" . $row['nis'] . "'>" . (!empty($row['nama_dudika']) ? $row['nama_dudika'] : '-') . "</td>
                    </tr>";

                }
                echo "
                    </tbody>
                </table>";
            } else {
                echo "<br>Tidak ada data ditemukan.";
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
            $('#tabelSiswa').DataTable({
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
        function redirectToPembimbing(nama) {
            const url = new URL(window.location.href);
            if (nama === "") {
                url.searchParams.delete("pembimbing"); // Hapus filter jika kosong
            } else {
                url.searchParams.set("pembimbing", nama); // Set filter pembimbing
            }
            window.location.href = url.toString();
        }
    </script>

    <script>
        $(document).on('blur', '.editable-dudika', function () {
            const cell = $(this);
            const dudika = cell.text().trim();
            const nis = cell.data('nis');

            $.ajax({
                url: 'update_dudika.php',
                method: 'POST',
                data: {
                    nis: nis,
                    dudika: dudika
                },

                // 1. SEBELUM REQUEST DIKIRIM
                beforeSend: function () {
                    cell.attr('contenteditable', false);
                    cell.addClass('opacity-75'); // optional: efek "sedang diproses"
                },

                // 2. JIKA SUKSES
                success: function () {
                    cell.removeClass('opacity-75');
                    cell.addClass('edited-success');

                    setTimeout(() => {
                        cell.removeClass('edited-success');
                        cell.attr('contenteditable', true);
                    }, 1500);
                },

                // 3. JIKA GAGAL
                error: function (xhr) {
                    cell.removeClass('opacity-75');
                    cell.attr('contenteditable', true);

                    if (xhr.status === 401) {
                        window.location.href =
                            '../app/login.php?redirect=' +
                            encodeURIComponent(window.location.pathname + window.location.search);
                    } else {
                        cell.addClass('edited-error');

                        setTimeout(() => {
                            cell.removeClass('edited-error');
                        }, 2000);
                    }
                }
            });
        });

        $(document).on('change', '.editable-pembimbing', function () {
            const select = $(this);
            const pembimbing = select.val();
            const nis = select.data('nis');

            $.ajax({
                url: 'update_pembimbing.php',
                method: 'POST',
                data: {
                    nis: nis,
                    pembimbing: pembimbing
                },

                beforeSend: function () {
                    select.prop('disabled', true);
                },

                success: function () {
                    select.prop('disabled', false);
                    select.addClass('border border-success');

                    setTimeout(() => {
                        select.removeClass('border border-success');
                    }, 1500);
                },

                error: function (xhr) {
                    select.prop('disabled', false);

                    if (xhr.status === 401) {
                        window.location.href =
                            '../app/login.php?redirect=' +
                            encodeURIComponent(window.location.pathname + window.location.search);
                    } else {
                        select.addClass('border border-danger');
                    }
                }
            });
        });
    </script>

</body>

</html>