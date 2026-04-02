<?php
include "../config/koneksi.php";

function norm($s) {
    return strtolower(trim($s));
}

/* ambil pembimbing dari penempatan */
$res = $conn->query("
    SELECT DISTINCT nama_pembimbing
    FROM penempatan
    WHERE TRIM(nama_pembimbing) != ''
");

if ($res->num_rows === 0) {
    die("Tidak ada data pembimbing dari penempatan");
}

/* ambil pembimbing existing */
$db = [];
$q = $conn->query("SELECT id, nama FROM datapembimbing");
while ($r = $q->fetch_assoc()) {
    $db[] = $r;
}

while ($row = $res->fetch_assoc()) {

    $namaBaru = trim($row['nama_pembimbing']);
    $normBaru = norm($namaBaru);

    $ketemu = false;

    foreach ($db as $pb) {
        $normLama = norm($pb['nama']);

        // prefix match: "budi" cocok dengan "budi santosa"
        if (
            str_starts_with($normBaru, $normLama) ||
            str_starts_with($normLama, $normBaru)
        ) {
            // update nama (lebih lengkap menang)
            if (strlen($namaBaru) > strlen($pb['nama'])) {
                $stmt = $conn->prepare(
                    "UPDATE datapembimbing SET nama=? WHERE id=?"
                );
                $stmt->bind_param("si", $namaBaru, $pb['id']);
                $stmt->execute();
                echo "Updated Pembimbing ID {$pb['id']} {$normLama} ke {$namaBaru}<br>";
                $stmt->close();
            }
            $ketemu = true;
            break;
        }
    }

    if (!$ketemu) {
        // INSERT baru
        $kode = substr(md5($namaBaru . uniqid()), 0, 6);
        $stmt = $conn->prepare(
            "INSERT INTO datapembimbing (nama, kode) VALUES (?,?)"
        );
        $stmt->bind_param("ss", $namaBaru, $kode);
        $stmt->execute();
        echo "Inserted pembimbing baru: {$namaBaru}<br>";
        $stmt->close();
    }
}

echo "Sinkron selesai (tanpa hapus data lama)";
