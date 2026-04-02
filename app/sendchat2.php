<?php
function sendMessage($number, $message, $file)
{
    // $url = 'https://app.whacenter.com/api/send';
    $url = 'https://api.whacenter.com/api/send';

    $ch = curl_init($url);

    $data = array(
        'device_id' => 'xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx',
        'number' => $number,
        'message' => $message,
        'file' => $file,
    );

    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $response = curl_exec($ch);
    $error = curl_error($ch);

    curl_close($ch);

    // $datamsg = array(
    //     'number' => $number,
    //     'message' => $message,
    //     'file' => $file,
    // );

    // header('Content-Type: application/json');
    // echo json_encode($datamsg);
    
    // Log tambahan (jaga-jaga)
    // $logFile = __DIR__ . "/log_sendchat.txt";
    // $time = date("Y-m-d H:i:s");
    // $log = "\n[$time] SENDMESSAGE CALL\nRequest Data:\n" . print_r($data, true);
    // if ($error) {
    //     $log .= "CURL ERROR: $error\n";
    // }
    // $log .= "Response:\n$response\n";
    // file_put_contents($logFile, $log, FILE_APPEND);

    // echo $response;
}
