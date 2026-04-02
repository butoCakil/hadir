<?php
include "../config/koneksi.php";

/* ===== AMBIL DATA DARI PENEMPATAN ===== */
$res = $conn->query("
    SELECT DISTINCT nama_siswa, nis_siswa, kelas
    FROM penempatan
    WHERE TRIM(nama_siswa) != ''
");

if ($res->num_rows === 0) {
    die("Tidak ada data siswa dari penempatan");
}

/* ===== AMBIL DATA SISWA EXISTING ===== */
$db = [];
$q = $conn->query("
    SELECT id, nis, nama, kelas
    FROM datasiswa
");

while ($r = $q->fetch_assoc()) {
    $db[] = $r;
}

/* ===== PROSES SINKRON ===== */
while ($row = $res->fetch_assoc()) {

    $namaBaru = trim($row['nama_siswa']);
    $nisBaru = trim($row['nis_siswa']);
    $kelasBaru = trim($row['kelas']);
    $jurBaru = ambilJur($kelasBaru);

    $normNamaBaru = norm($namaBaru);
    $ketemu = false;

    foreach ($db as $s) {

        /* PRIORITAS 1: NIS SAMA */
        if ($nisBaru !== '' && $s['nis'] === $nisBaru) {

            $stmt = $conn->prepare("
                UPDATE datasiswa
                SET nama = ?, kelas = ?, jur = ?
                WHERE id = ?
            ");
            $stmt->bind_param(
                "sssi",
                $namaBaru,
                $kelasBaru,
                $jurBaru,
                $s['id']
            );
            $stmt->execute();
            $stmt->close();

            $ketemu = true;
            break;
        }

        /* PRIORITAS 2: PREFIX NAMA */
        $normNamaLama = norm($s['nama']);

        if (
            str_starts_with($normNamaBaru, $normNamaLama) ||
            str_starts_with($normNamaLama, $normNamaBaru)
        ) {
            // update hanya jika nama baru lebih lengkap
            if (strlen($namaBaru) > strlen($s['nama'])) {

                $stmt = $conn->prepare("
                    UPDATE datasiswa
                    SET nama = ?, nis = ?, kelas = ?, jur = ?
                    WHERE id = ?
                ");
                $stmt->bind_param(
                    "ssssi",
                    $namaBaru,
                    $nisBaru,
                    $kelasBaru,
                    $jurBaru,
                    $s['id']
                );
                $stmt->execute();

                echo "Updated siswa ID {$s['id']} {$normNamaLama} ke {$namaBaru}<br>";

                $stmt->close();
            }

            $ketemu = true;
            break;
        }
    }

    /* ===== INSERT JIKA BENAR-BENAR BARU ===== */
    if (!$ketemu) {

        $stmt = $conn->prepare("
            INSERT INTO datasiswa (nis, nama, kelas, jur)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->bind_param(
            "ssss",
            $nisBaru,
            $namaBaru,
            $kelasBaru,
            $jurBaru
        );
        $stmt->execute();

        echo "Inserted siswa baru: {$namaBaru} (NIS: {$nisBaru})<br>";
        $stmt->close();

        // tambahkan ke cache supaya tidak double insert
        $db[] = [
            'id' => $conn->insert_id,
            'nis' => $nisBaru,
            'nama' => $namaBaru,
            'kelas' => $kelasBaru
        ];
    }
}

$conn->close();
echo "Sinkron siswa selesai (tanpa menghapus data lama)";
/* ===== FUNGSI BANTUAN ===== */
function norm($s)
{
    return strtolower(trim(preg_replace('/\s+/', ' ', $s)));
}

function ambilJur($kelas)
{
    // ambil huruf kapital berurutan (AT, DKV, AKL, RPL, dll)
    if (preg_match('/\b[A-Z]{2,5}\b/', strtoupper($kelas), $m)) {
        return $m[0];
    }
    return null;
}
