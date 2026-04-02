<?php
function sendMessage($number, $message, $file)
{
    // $url = 'https://app.whacenter.com/api/send';
    $url = 'https://api.whacenter.com/api/send';

    $ch = curl_init($url);

    $data = array(
        // 'device_id' => '221c83d8eedc48fb8a7405303a439363',
        'device_id' => 'e4331d2e-6419-41d3-acda-4dc2cf2ab88f',
        'number' => $number,
        'message' => $message,
        'file' => $file,
    );

    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    curl_exec($ch);

    curl_close($ch);

    $datamsg = array(
        'number' => $number,
        'message' => $message,
        'file' => $file,
    );

    header('Content-Type: application/json');
    echo json_encode($datamsg);
}