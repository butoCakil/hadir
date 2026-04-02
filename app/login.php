<?php
// ini_set('display_errors', 1);
// ini_set('display_startup_errors', 1);
// error_reporting(E_ALL);

session_start();

include "../config/koneksi.php";

// Periksa apakah form login sudah disubmit
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $input_username = $_POST['username'];
    $input_password = $_POST['password'];

    // Mengamankan input dari SQL Injection
    $input_username = mysqli_real_escape_string($conn, $input_username);
    $input_password = mysqli_real_escape_string($conn, $input_password);

    // Query untuk mengecek username dan password
    $sql = "SELECT * FROM user WHERE username = '$input_username' AND password = '$input_password'";
    $result = $conn->query($sql);

    if ($result->num_rows > 0) {
        // Login berhasil, simpan session
        $row = $result->fetch_assoc();

        // Set session dan simpan dalam cookie selama 1 bulan
        $_SESSION['username'] = $row['username'];
        $_SESSION['logged_in'] = true;

        // Menyimpan cookie agar user tetap login selama 1 bulan
        setcookie('username', $row['username'], time() + (30 * 24 * 60 * 60), "/");  // 1 bulan

        // setelah validasi username & password berhasil

        if (!empty($_SESSION['redirect_to'])) {
            $redirect = $_SESSION['redirect_to'];
            unset($_SESSION['redirect_to']); // penting: cegah redirect ulang
            header("Location: $redirect");
            exit;
        }

        // fallback jika tidak ada halaman sebelumnya
        header("Location: ../");
        exit;
    } else {
        // Username atau password salah
        ?>
        <div class="container">
            <div class="alert alert-warning alert-dismissible fade show text-center mt-4" role="alert">
                <strong>Password Salah!</strong> Silakan cek kembali.
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        </div>
        <?php
    }
}

$conn->close();
?>

<?php
date_default_timezone_set('Asia/Jakarta');
$tanggal = date('Y-m-d');
$tahun = date('Y');
$timestamp = date('Y-m-d H:i:s');
$waktusekarang = date('H:i:s');
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>PiKaEL demo</title>
    <link rel="shortcut icon" href="../img/SMKNBansari.png" type="image/x-icon">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"
        integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
</head>

<body>
    <style>
        .divider:after,
        .divider:before {
            content: "";
            flex: 1;
            height: 1px;
            background: #eee;
        }

        .h-custom {
            height: calc(100% - 73px);
        }

        @media (max-width: 450px) {
            .h-custom {
                height: 100%;
            }
        }
    </style>

    <section class="vh-100">
        <div class="container-fluid h-custom">
            <div class="row d-flex justify-content-center align-items-center h-100">
                <div class="col-md-9 col-lg-6 col-xl-5">
                    <img src="../img/SMKBOS-min.png" class="img-fluid" alt="Sample image">
                </div>
                <div class="col-md-8 col-lg-6 col-xl-4 offset-xl-1">
                    <form action="" method="POST">
                        <!-- Email input -->
                        <div data-mdb-input-init class="form-outline mb-4">
                            <input type="text" id="username" name="username" class="form-control form-control-lg"
                                placeholder="Enter a valid username" />
                            <label class="form-label" for="username">Username</label>
                        </div>

                        <!-- Password input -->
                        <div data-mdb-input-init class="form-outline mb-3">
                            <input type="password" id="password" name="password" class="form-control form-control-lg"
                                placeholder="Enter password" />
                            <label class="form-label" for="password">Password</label>
                        </div>

                        <div class="text-center text-lg-start mt-4 pt-2">
                            <button type="submit" data-mdb-button-init data-mdb-ripple-init
                                class="btn btn-primary btn-lg" style="padding-left: 2.5rem; padding-right: 2.5rem;"
                                value="Login">Login</button>
                            <p class="small fw-bold mt-2 pt-1 mb-0">Don't have an account? <a
                                    href="https://wa.me/628993930090" target="_blank" class="link-danger">Contact
                                    Admin</a></p>
                        </div>

                    </form>
                </div>
            </div>
        </div>
        <div
            class="d-flex flex-column flex-md-row text-center text-md-start justify-content-between py-4 px-4 px-xl-5 bg-primary">
            <!-- Copyright -->
            <div class="text-white mb-3 mb-md-0">
                Copyright © <?= $tahun; ?>
            </div>
            <!-- Copyright -->

            <!-- Right -->
            <div>
                <a href="#!" class="text-white me-4">
                    <i class="fab fa-facebook-f"></i>
                </a>
                <a href="#!" class="text-white me-4">
                    <i class="fab fa-twitter"></i>
                </a>
                <a href="#!" class="text-white me-4">
                    <i class="fab fa-google"></i>
                </a>
                <a href="#!" class="text-white">
                    <i class="fab fa-linkedin-in"></i>
                </a>
            </div>
            <!-- Right -->
        </div>
    </section>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz"
        crossorigin="anonymous"></script>
</body>

</html>