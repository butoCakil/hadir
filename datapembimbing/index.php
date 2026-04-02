<?php
session_start();

// Cek apakah pengguna sudah login
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    $_SESSION['redirect_to'] = $_SERVER['REQUEST_URI'];
    header("Location: ../app/login.php");
    exit();
}

date_default_timezone_set('Asia/Jakarta');
$tahun = date('Y');
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Pembimbing - PKL SMKN Bansari</title>
    <link rel="shortcut icon" href="../img/SMKNBansari.png" type="image/x-icon">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"
        integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
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

        .hero h2 {
            font-size: 36px;
            font-weight: 700;
            margin-bottom: 30px;
        }

        .table {
            color: white;
        }

        .dataTables_wrapper .dataTables_filter input {
            color: black;
        }

        /* Highlight sukses */
        .td-success {
            background-color: #198754 !important;
            /* Bootstrap green */
            color: #fff !important;
            transition: background-color 0.6s ease;
        }

        /* Highlight gagal */
        .td-error {
            background-color: #dc3545 !important;
            /* Bootstrap red */
            color: #fff !important;
            transition: background-color 0.6s ease;
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
                        <li class="nav-item"><a class="nav-link" href="/">Home</a></li>
                        <li class="nav-item"><a class="nav-link" href="../datasiswa">Data Penempatan Siswa</a></li>
                        <li class="nav-item"><a class="nav-link" href="../presensi">Data Presensi</a></li>
                        <?php if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true): ?>
                            <li class="nav-item"><a class="nav-link" href="../app/logout.php">Log Out</a></li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </nav>
    </header>

    <section class="hero">
        <div class="container">
            <h2 class="text-center">Data Pembimbing</h2>

            <!-- <div>
                <a href="../data/sinkron_pembimbing.php" class="btn btn-danger btn-sm">Sinkron Pembimbing</a>
            </div> -->

            <?php
            include "../config/koneksi.php";

            $query = "SELECT * FROM datapembimbing ORDER BY nama ASC";
            $result = $conn->query($query);

            if ($result && $result->num_rows > 0):
                ?>
                <table id="tabelPembimbing" class="table table-striped table-bordered" cellspacing="0" width="100%">
                    <thead>
                        <tr>
                            <th>No.</th>
                            <th>NIP</th>
                            <th>Nama</th>
                            <th>WA</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $no = 1;
                        while ($row = $result->fetch_assoc()):
                            // Lewati baris jika semua data kosong
                            if (empty($row['nip']) && empty($row['nama']) && empty($row['nohp'])) {
                                continue;
                            }
                            ?>
                            <tr data-id="<?= $row['id'] ?>" data-table="datapembimbing">
                                <td><?= $no++ ?></td>
                                <td contenteditable="true" class="editable" data-field="nip">
                                    <?= htmlspecialchars(!empty($row['nip']) ? $row['nip'] : '-') ?>
                                </td>
                                <td contenteditable="true" class="editable" data-field="nama" style='text-align: left;'>
                                    <?= htmlspecialchars(!empty($row['nama']) ? $row['nama'] : '-') ?>
                                </td>
                                <td contenteditable="true" class="editable" data-field="nohp">
                                    <?= htmlspecialchars(!empty($row['nohp']) ? $row['nohp'] : '-') ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>Data pembimbing tidak ditemukan.</p>
            <?php endif;

            $conn->close();
            ?>
        </div>
    </section>

    <section class="hero">
        <div class="container">
            <h2 class="text-center">Data Wali Kelas</h2>

            <!-- <div>
                <a href="../data/sinkron_pembimbing.php" class="btn btn-danger btn-sm">Sinkron Pembimbing</a>
            </div> -->

            <?php
            include "../config/koneksi.php";

            $query = "SELECT * FROM datawalikelas ORDER BY kelas ASC";
            $result = $conn->query($query);

            if ($result && $result->num_rows > 0):
                ?>
                <table id="tabelWalikelas" class="table table-striped table-bordered" cellspacing="0" width="100%">
                    <thead>
                        <tr>
                            <th>No.</th>
                            <th>NIP</th>
                            <th>Nama</th>
                            <th>Kelas</th>
                            <th>WA</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $no = 1;
                        while ($row = $result->fetch_assoc()):
                            // Lewati baris jika semua data kosong
                            if (empty($row['nip']) && empty($row['nama']) && empty($row['nohp'])) {
                                continue;
                            }
                            ?>
                            <tr data-id="<?= $row['id'] ?>" data-table="datawalikelas">
                                <td><?= $no++ ?></td>
                                <td contenteditable="true" class="editable" data-field="nip">
                                    <?= htmlspecialchars(!empty($row['nip']) ? $row['nip'] : '-') ?>
                                </td>
                                <td contenteditable="true" class="editable" data-field="nama" style='text-align: left;'>
                                    <?= htmlspecialchars(!empty($row['nama']) ? $row['nama'] : '-') ?>
                                </td>
                                <td contenteditable="true" class="editable" data-field="kelas">
                                    <?= htmlspecialchars(!empty($row['kelas']) ? $row['kelas'] : '-') ?>
                                </td>
                                <td contenteditable="true" class="editable" data-field="nohp">
                                    <?= htmlspecialchars(!empty($row['nohp']) ? $row['nohp'] : '-') ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>Data Walikelas tidak ditemukan.</p>
            <?php endif;

            $conn->close();
            ?>
        </div>
    </section>

    <div class="text-white text-center mb-3 mt-4">
        Copyright © <?= $tahun ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz"
        crossorigin="anonymous"></script>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script>
        $(function () {
            $('#tabelPembimbing').DataTable({
                paging: true,
                pageLength: 10,
                language: {
                    url: "//cdn.datatables.net/plug-ins/1.13.6/i18n/id.json"
                }
            });
        });
    </script>

    <script>
        $(document).ready(function () {
            // Batasi input hanya angka untuk kolom nohp
            $(document).on('input', 'td[data-field="nohp"]', function () {
                let val = $(this).text();
                let filtered = val.replace(/\D/g, ''); // Hanya sisakan angka
                if (val !== filtered) {
                    $(this).text(filtered);
                }
            });

            $('.editable').on('blur', function () {
                const td = $(this);
                const field = td.data('field');
                const tr = td.closest('tr');
                const id = tr.data('id');
                const table = tr.data('table');

                let newValue = td.text().trim();

                if (field === 'nohp') {
                    newValue = newValue.replace(/\D/g, '');
                    td.text(newValue);
                }

                // Bersihkan highlight lama
                td.removeClass('td-success td-error');

                $.ajax({
                    url: 'update.php',
                    method: 'POST',
                    data: { id, field, value: newValue, table },

                    success: function () {
                        td.addClass('td-success');

                        setTimeout(() => {
                            td.removeClass('td-success');
                        }, 1200);
                    },

                    error: function () {
                        td.addClass('td-error');

                        setTimeout(() => {
                            td.removeClass('td-error');
                        }, 2000);
                    }
                });
            });
        });
    </script>

</body>

</html>