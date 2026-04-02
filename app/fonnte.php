<?php
include "sendchat2.php";

date_default_timezone_set('Asia/Jakarta');
$logFile = __DIR__ . "/log_fonnte.txt";

function logData($label, $data)
{
    global $logFile;
    $time = date("Y-m-d H:i:s");
    $entry = "\n[$time] $label:\n" . print_r($data, true) . "\n";
    file_put_contents($logFile, $entry, FILE_APPEND);
}

header('Content-Type: application/json; charset=utf-8');

// --- Tahap 1: Terima JSON ---
$json = file_get_contents('php://input');
// logData("RAW INPUT", $json);

$data = json_decode($json, true);
// logData("DECODED JSON", $data);

// --- Tahap 2: Ambil data utama ---
$device = $data['device'] ?? null;
$sender = $data['sender'] ?? null;
$message = $data['message'] ?? null;
$member = $data['member'] ?? null;
$name = $data['name'] ?? null;
$location = $data['location'] ?? null;
$url = $data['url'] ?? null;
$filename = $data['filename'] ?? null;
$extension = $data['extension'] ?? null;

if (!$sender || !$message) {
    logData("ERROR", "Missing sender or message");
    http_response_code(400);
    echo json_encode(["error" => "Missing sender or message"]);
    exit;
}

$reply = null;

// --- Tahap 3: Tentukan balasan ---
switch (strtolower(trim($message))) {
    case "test":
        $reply = "working great!";
        break;

    case "image":
        $reply = "image message\nhttps://filesamples.com/samples/image/jpg/sample_640%C3%97426.jpg";
        break;

    case "audio":
        $reply = "audio message\nhttps://filesamples.com/samples/audio/mp3/sample3.mp3";
        break;

    case "video":
        $reply = "video message\nhttps://filesamples.com/samples/video/mp4/sample_640x360.mp4";
        break;

    case "file":
        $reply = "file message\nhttps://filesamples.com/samples/document/docx/sample3.docx";
        break;

    default:
        break;
}


// logData("REPLY BUILT", $reply);

// --- Tahap 4: Kirim pesan ke API ---
if ($reply !== null) {
    $reply .= "\n\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n📝 _Sistem Presensi PKL_ *SMK Negeri Bansari*\n©️ ```2025```";
    $response = sendMessage($sender, $reply, null);
    // logData("API RESPONSE", $response);

    // --- Tahap 5: Balas webhook ---
// echo json_encode(["status" => "OK", "reply" => $reply]);
}
