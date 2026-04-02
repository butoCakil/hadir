<?php
// Koneksi ke database
$host = "localhost";
// $user = "root";
// $password = "";
// $database = "datapkl";
$user = "dvttaulx_masbendz";
$password = "gk#F!X{gYTdxsD]Z";
$database = "dvttaulx_datapkl";

$conn = new mysqli($host, $user, $password, $database);
if ($conn->connect_error) {
    die("Koneksi gagal: " . $conn->connect_error);
}