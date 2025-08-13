<?php
date_default_timezone_set('Asia/Singapore');

session_start();
require '../db.php';

require __DIR__ . '/../vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

if (!isset($_SESSION['user_id'])) exit("Unauthorized");

$userId = $_SESSION['user_id'];
$message = trim($_POST['message'] ?? '');

$numbers = [];

if (!empty($_POST['phone'])) {
    $manualNumbers = explode(',', $_POST['phone']);
    foreach ($manualNumbers as $num) {
        $cleaned = preg_replace('/\D/', '', $num);
        // if ($cleaned) $numbers[] = '+' . $cleaned;
        if ($cleaned) $numbers[] = $cleaned;
    }
}

if (isset($_FILES['csv']) && $_FILES['csv']['error'] === UPLOAD_ERR_OK) {
    $csv = fopen($_FILES['csv']['tmp_name'], 'r');
    while (($line = fgetcsv($csv)) !== false) {
        $cleaned = preg_replace('/\D/', '', $line[0]);
        if ($cleaned) $numbers[] = $cleaned;
    }
    fclose($csv);
}

$numbers = array_unique($numbers);
$successCount = 0;
$failCount = 0;

$tag = $_POST['tag'] ?? '';

$courseName = $_POST['courseName'] ?? '';
$pillarEmail = $_POST['pillarEmail'] ?? '';
$tempDate = $_POST['tempDate'] ?? '';
$tempTime = '';
// $tempTime = $_POST['tempTime'] ?? '';
$tempLocation = $_POST['tempLocation'] ?? '';

// function formatTime($hour, $minute, $ampm)
// {
//     if ($hour && $minute && in_array($ampm, ['AM', 'PM'])) {
//         return "{$hour}.{$minute} {$ampm} SGT";
//     }
//     return null; // or false if you prefer
// }


$hour = $_POST['hour'] ?? '';
$minute = $_POST['minute'] ?? '';
$ampm = $_POST['ampm'] ?? '';
// $tempTime = formatTime($hour, $minute, $ampm);

if ($hour && $minute && in_array($ampm, ['AM', 'PM'])) {
    // Format: 09.30 AM SGT
    $tempTime = "{$hour}.{$minute} {$ampm} SGT";
}
// else {
//     echo "Invalid time input.";
// }

$Original_Date = $_POST['Original_Date'] ?? '';
$ODT_hour = $_POST['ODT_hour'] ?? '';
$ODT_minute = $_POST['ODT_minute'] ?? '';
$ODT_ampm = $_POST['ODT_ampm'] ?? '';
$Original_Time = "";
// $Original_Time = formatTime($ODT_hour, $ODT_minute, $ODT_ampm);
if ($ODT_hour && $ODT_minute && in_array($ODT_ampm, ['AM', 'PM'])) {
    $Original_Time = "{$ODT_hour}.{$ODT_minute} {$ODT_ampm} SGT";
}
$Original_Date_Time = $Original_Date . ' at ' . $Original_Time;



$New_Date = $_POST['New_Date'] ?? '';
$NDT_hour = $_POST['NDT_hour'] ?? '';
$NDT_minute = $_POST['NDT_minute'] ?? '';
$NDT_ampm = $_POST['NDT_ampm'] ?? '';
$New_Time = "";

// $New_Time = formatTime($NDT_hour, $NDT_minute, $NDT_ampm);
if ($NDT_hour && $NDT_minute && in_array($NDT_ampm, ['AM', 'PM'])) {
    $New_Time = "{$NDT_hour}.{$NDT_minute} {$NDT_ampm} SGT";
}
$New_Date_Time = $New_Date . ' at ' . $New_Time;



$messageType = $_POST['message_type'] ?? '';
$language = $_POST['language'] ?? '';
$jsConsoleOutput = [];

$name = $_POST['name'] ?? '';
$platformURL = $_POST['platformURL'] ?? '';
$launchDate = $_POST['launchDate'] ?? '';

$moduleName = $_POST['moduleName'] ?? '';
$deadlineDate = $_POST['deadlineDate'] ?? '';
$portalName = $_POST['portalName'] ?? '';

$SUS_hour = $_POST['SUS_hour'] ?? '';
$SUS_minute = $_POST['SUS_minute'] ?? '';
$SUS_ampm = $_POST['SUS_ampm'] ?? '';

if ($SUS_hour && $SUS_minute && in_array($SUS_ampm, ['AM', 'PM'])) {
    $SUS_Time = "{$SUS_hour}.{$SUS_minute} {$SUS_ampm} SGT";
}

$SUE_hour = $_POST['SUE_hour'] ?? '';
$SUE_minute = $_POST['SUE_minute'] ?? '';
$SUE_ampm = $_POST['SUE_ampm'] ?? '';

if ($SUE_hour && $SUE_minute && in_array($SUE_ampm, ['AM', 'PM'])) {
    $SUE_Time = "{$SUE_hour}.{$SUE_minute} {$SUE_ampm} SGT";
}



foreach ($numbers as $phone) {

    $payload = [
        "messaging_product" => "whatsapp",
        "recipient_type" => "individual",
        "to" => $phone,
        "type" => $messageType,
    ];

    if ($messageType === 'template') {
        $payload["template"] = [
            "name" => $message,
            "language" => ["code" => $language],
        ];

        if ($message === 'orientation_session_missout') {
            $payload["template"]["components"] = [
                [
                    "type" => "body",
                    "parameters" => [
                        ["type" => "text", "text" => $courseName],
                        ["type" => "text", "text" => $tempDate],
                        ["type" => "text", "text" => $tempTime],
                        ["type" => "text", "text" => $tempLocation]
                    ]
                ]

            ];
        } elseif ($message === 'orientation_message') {
            $payload["template"]["components"] = [
                [
                    "type" => "body",
                    "parameters" => [
                        ["type" => "text", "text" => $courseName],
                        ["type" => "text", "text" => $pillarEmail]
                    ]
                ]

            ];
        } elseif ($message === 'class_rescheduled') {
            $payload["template"]["components"] = [
                [
                    "type" => "body",
                    "parameters" => [
                        ["type" => "text", "text" => $Original_Date_Time],
                        ["type" => "text", "text" => $New_Date_Time]
                    ]
                ]

            ];
        } elseif ($message === 'attendance__learner_missed_multiple_sessions') {
            $payload["template"]["components"] = [
                [
                    "type" => "body",
                    "parameters" => [
                        ["type" => "text", "text" => $name]
                    ]
                ]

            ];
        } elseif ($message === 'new_ai_chatbot_rollout_support_channel') {
            $payload["template"]["components"] = [
                [
                    "type" => "body",
                    "parameters" => [
                        ["type" => "text", "text" => $name],
                        ["type" => "text", "text" => $platformURL],
                        ["type" => "text", "text" => $launchDate]
                    ]
                ]

            ];
        } elseif ($message === 'payment_reminder__1_week_after_class_started') {
            $payload["template"]["components"] = [
                [
                    "type" => "header",
                    "parameters" => [
                        ["type" => "text", "text" => $name]
                    ]
                ],
                [
                    "type" => "body",
                    "parameters" => [
                        ["type" => "text", "text" => $name],
                        ["type" => "text", "text" => $moduleName],
                        ["type" => "text", "text" => $deadlineDate]
                    ]
                ]

            ];
        } elseif ($message === 'system_update_notification_any_platform') {
            $payload["template"]["components"] = [
                [
                    "type" => "header",
                    "parameters" => [
                        ["type" => "text", "text" => $name]
                    ]
                ],
                [
                    "type" => "body",
                    "parameters" => [
                        ["type" => "text", "text" => $name],
                        ["type" => "text", "text" => $portalName],
                        ["type" => "text", "text" => $launchDate],
                        ["type" => "text", "text" => $SUS_Time],
                        ["type" => "text", "text" => $SUE_Time]
                    ]
                ]

            ];
        } else {
            $payload["template"] = [
                "name" => $message,
                "language" => ["code" => $language]
            ];
        }
    } else {
        continue;
    }


    $ch = curl_init("https://graph.facebook.com/v23.0/" . $_ENV['PHONE_NUMBER_ID'] . "/messages");
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Content-Type: application/json",
        "Authorization: Bearer " . $_ENV['WHATSAPP_TOKEN']
    ]);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // "{"messaging_product":"whatsapp","contacts":[{"input":"+94787189456","wa_id":"94787189456"}],"messages":[{"id":"wamid.HBgLOTQ3ODcxODk0NTYVAgARGBIwOTFCNjY0MzA1QjY5NkU0Q0YA","message_status":"accepted"}]}"

    //     {
    //     "phone": "94787189456",
    //     "status": "failed",
    //     "http_code": 400,
    //     "message_status": "pending",
    //     "response": {
    //         "error": {
    //             "message": "(#100) Invalid parameter",
    //             "type": "OAuthException",
    //             "code": 100,
    //             "error_data": {
    //                 "messaging_product": "whatsapp",
    //                 "details": "Parameter 'text' is mandatory for component parameter type 'text'"
    //             },
    //             "fbtrace_id": "AAb2bA2KZ-OCh88SG8P2WQm"
    //         }
    //     }
    // 
    //   {
    //     "phone": "94787189456",
    //     "status": "success",
    //     "http_code": 200,
    //     "message_status": "accepted",
    //     "response": {
    //         "messaging_product": "whatsapp",
    //         "contacts": [
    //             {
    //                 "input": "94787189456",
    //                 "wa_id": "94787189456"
    //             }
    //         ],
    //         "messages": [
    //             {
    //                 "id": "wamid.HBgLOTQ3ODcxODk0NTYVAgARGBI4QjMyQkFCNkNEQzM5NzkxNjEA",
    //                 "message_status": "accepted"
    //             }
    //         ]
    //     }
    // 

    $responseData = json_decode($response, true);
    $messageStatus = $responseData['messages'][0]['message_status'] ?? 'pending';
    // $messageId = $responseData['messages'][0]['id'] ?? '';
    $messageId = $responseData['messages'][0]['id'] ?? $responseData['error']['fbtrace_id'] ?? '';

    $waId = $responseData['contacts'][0]['wa_id'] ?? '';


    //THIS is the real success check now:
    $status = ($messageStatus === 'accepted') ? 'success' : 'failed';

    $allResponses[] = [
        'phone' => $phone,
        'status' => $status,
        'http_code' => $code,
        'message_status' => $messageStatus,
        'response' => $responseData,
        'payload' => $payload
    ];

    // Log to DB
    $timestamp = date('Y-m-d H:i:s');
    $formattedTime = date('Y M d | g.i a');
    $stmt = $conn->prepare("INSERT INTO messages (user_id, phone, wa_id, tag, message, message_id, sent_at, sent_at_display) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("isssssss", $userId, $phone, $waId, $tag, $message, $messageId, $timestamp, $formattedTime);
    $stmt->execute();

    // Count real success
    if ($status === 'success') $successCount++;
    else $failCount++;

    // Optional log file
    $logEntry = date('Y-m-d H:i:s') . " | Phone: $phone | wa_id: $waId | Accept_Status: $status ($messageStatus) | HTTP: $code | Response: $response" . PHP_EOL;
    file_put_contents('../logs/whatsapp_log.txt', $logEntry, FILE_APPEND);
}

// echo "<script>console.log(" . json_encode($jsConsoleOutput) . ");</script>";

// Set flash message
$_SESSION['flash'] = [
    'type' => ($failCount === 0) ? 'success' : 'warning',
    'message' => "✅ Accepted: $successCount | ❌ Rejected: $failCount",
    'response_log' => $allResponses
];

header("Location: ../index.php");
exit;
