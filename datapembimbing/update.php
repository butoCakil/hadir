<?php
include "../config/koneksi.php";

$id = intval($_POST['id'] ?? 0);
$field = $_POST['field'] ?? '';
$value = trim($_POST['value'] ?? '');
$table = $_POST['table'] ?? '';

/* ===== VALIDASI TABEL ===== */
$allowed_tables = [
    'datapembimbing' => ['nip', 'nama', 'nohp'],
    'datawalikelas' => ['nip', 'nama', 'kelas', 'nohp']
];

if (!isset($allowed_tables[$table])) {
    http_response_code(400);
    exit("Tabel tidak valid");
}

if (!in_array($field, $allowed_tables[$table])) {
    http_response_code(400);
    exit("Field tidak valid");
}

/* ===== KHUSUS PEMBIMBING: CASCADE NAMA ===== */
if ($table === 'datapembimbing' && $field === 'nama') {

    $stmt = $conn->prepare("SELECT nama FROM datapembimbing WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->bind_result($oldNama);
    $stmt->fetch();
    $stmt->close();

    if (!$oldNama) {
        exit("Nama lama tidak ditemukan");
    }

    // Update datapembimbing
    $stmt1 = $conn->prepare("UPDATE datapembimbing SET nama = ? WHERE id = ?");
    $stmt1->bind_param("si", $value, $id);
    $stmt1->execute();
    $stmt1->close();

    // Sinkron ke penempatan
    $stmt2 = $conn->prepare(
        "UPDATE penempatan SET nama_pembimbing = ? WHERE nama_pembimbing = ?"
    );
    $stmt2->bind_param("ss", $value, $oldNama);
    $stmt2->execute();
    $stmt2->close();

    echo "Nama pembimbing disinkronkan";
    exit;
}

/* ===== UPDATE NORMAL ===== */
$sql = "UPDATE {$table} SET {$field} = ? WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("si", $value, $id);
$stmt->execute();
$stmt->close();

echo "Data diperbarui";
$conn->close();
