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
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <link rel="shortcut icon" href="img/SMKNBansari.png" type="image/x-icon">
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
            padding: 150px 20px;
            background-color: #222;
            color: white;
        }

        .hero h1 {
            font-size: 48px;
            font-weight: 700;
        }

        .hero p {
            font-size: 16px;
            line-height: 1.6;
            /* color: #ddd; */
            /* lebih lembut daripada putih */
            font-weight: 300;
            /* tipis elegan */
            margin-top: 15px;
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
            color: rgba(255, 255, 255, 0.8);
        }

        .hero .btnwa {
            background-color: #25D366;
        }

        .hero .btn {
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

        .logo2 {
            height: 100px;
        }

        .logo1 {
            height: 45px;
        }

        .logo {
            display: flex;
            gap: 20px
        }

        .logotext1 {
            color: #09a530ff;
        }

        .logotext2 {
            color: #da9f20ff;
        }

        .logotext3 {
            color: #2f78bdff;
        }

        .logo-title {
            display: flex;
            justify-content: center;
            flex-wrap: wrap;
            /* biar bisa turun ke bawah kalau sempit */
            gap: 10px;
            /* jarak antar kata, ganti &nbsp;&nbsp; */
        }

        .logo-title h1 {
            font-size: 48px;
            margin: 0;
        }

        /* untuk layar kecil (HP) */
        @media (max-width: 576px) {
            .logo-title h1 {
                font-size: 28px;
                /* perkecil biar muat */
            }
        }
    </style>
</head>

<body>

    <header>
        <div class="logo">
            <img src="img/SMKNBansari.png" alt="" class="logo1">
            <h2>SMK Negeri Bansari</h2>
        </div>
        <nav class="navbar navbar-expand-lg navbar-dark">
            <div class="container-fluid">
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav"
                    aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="navbarNav">
                    <ul class="navbar-nav">
                        <li class="nav-item">
                            <a class="nav-link disabled" href="/">Home</a>
                        </li>
                        <!-- <li class="nav-item">
                            <span class="nav-link disabled">About</span>
                        </li> -->
                        <li class="nav-item">
                            <a class="nav-link" href="datasiswa">Data Penempatan Siswa</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="presensi">Data Presensi</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="data/Dokumentasi Presensi PKL via WA.html">Doc</a>
                        </li>

                        <?php
                        if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
                        } else {
                            ?>
                            <li class="nav-item">
                                <a class="nav-link" href="app/logout.php">Log Out</a>
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
        <div class="logo-img">
            <!-- <img src="img/SMKNBansari.png" alt="" class="logo1"> -->
            <img src="img/SMKBOS-min.png" alt="" class="logo2">
        </div>
        <div class="logo-title">
            <h1 class="logotext1">Sistem</h1>
            <h1 class="logotext2">Presensi</h1>
            <h1 class="logotext3">PKL</h1>
        </div>

        <h2>SMK Negeri Bansari.</h2>
        <p>Presensi dilakukan melalui chat WhatsApp atau halaman Web. <br>Klink Tombol di bawah ini untuk memulai
            Presensi.</p>
        <!--<form action="https://wa.me/62993930090" method="get" target="_blank">-->
        <form action="https://wa.me/6287754446580" method="get" target="_blank">
            <button type="submit" class="btn btnwa"><i class="fa fa-whatsapp"
                    style="font-size:20px;color:white"></i>&nbsp;&nbsp;Presensi WA</button>
        </form>
        <form action="app/presensi.php" method="get">
            <button type="submit" class="btn btn-primary"><i class="fa fa-globe"
                    style="font-size:20px;color:white"></i>&nbsp;&nbsp;Presensi Web</button>
        </form>

    </section>

    <div class="text-white text-center mb-3 mb-md-0">
        Copyright © <?= $tahun; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz"
        crossorigin="anonymous"></script>

</body>

</html>