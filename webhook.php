<?php
// === Initialization ===
date_default_timezone_set('Asia/Singapore');
require_once __DIR__ . '/vendor/autoload.php';
require 'db.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();
$baseUrl = $_ENV['BASE_URL'];

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$username = $_SESSION['username'] ?? '';
$verify_token = 'eduCLaaSCM_WA';
$logFile = 'webhook-log.txt';

// === Meta Verification ===
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['hub_mode'])) {
    if ($_GET['hub_mode'] === 'subscribe' && $_GET['hub_verify_token'] === $verify_token) {
        echo $_GET['hub_challenge'];
        exit;
    } else {
        http_response_code(403);
        echo 'Invalid verification token';
        exit;
    }
}

// === Webhook POST Handling ===
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = file_get_contents('php://input');
    file_put_contents($logFile, date('Y-m-d H:i:s') . "\n" . $input . "\n\n", FILE_APPEND);

    $data = json_decode($input, true);

    if (isset($data['entry'])) {
        foreach ($data['entry'] as $entry) {
            foreach ($entry['changes'] as $change) {
                $value = $change['value'];

                // === Handle statuses ===
                if (isset($value['statuses'])) {
                    foreach ($value['statuses'] as $status) {
                        $messageId = $status['id'] ?? null;
                        $statusType = $status['status'] ?? null;
                        $timestamp = isset($status['timestamp']) ? date('Y-m-d H:i:s', $status['timestamp']) : null;

                        if ($statusType && $messageId) {
                            $timeColumn = $statusType === 'read' ? '`read`' : $statusType;

                            $check = $conn->prepare("SELECT id FROM messages WHERE message_id = ?");
                            $check->bind_param("s", $messageId);
                            $check->execute();
                            $check->store_result();

                            if ($check->num_rows > 0) {
                                if ($statusType === 'sent') {
                                    $conversation_id = $status['conversation']['id'] ?? null;
                                    $conversation_exp = isset($status['conversation']['expiration_timestamp']) ? date('Y-m-d H:i:s', $status['conversation']['expiration_timestamp']) : null;
                                    $conversation_origin = $status['conversation']['origin']['type'] ?? null;

                                    $pricing_billable = $status['pricing']['billable'] ?? null;
                                    $pricing_model = $status['pricing']['pricing_model'] ?? null;

                                    // if ($pricing_billable === 'Yes') {
                                    //     if ($pricing_model === 'PMP') {
                                    //     } elseif ($pricing_model === 'CBP') {
                                    //         continue;
                                    //     } else {
                                    //     }
                                    // }

                                    $pricing_category = $status['pricing']['category'] ?? null;

                                    $update = $conn->prepare("UPDATE messages SET $timeColumn = ?, status = 'success', conversation_id = ?, conversation_exp = ?, conversation_origin = ?, pricing_billable = ?, pricing_model = ?, pricing_category = ?, errors_code = NULL, errors_title = NULL, errors_message = NULL, error_data_details = NULL, failed = NULL WHERE message_id = ?");
                                    $update->bind_param("ssssssss", $timestamp, $conversation_id, $conversation_exp, $conversation_origin, $pricing_billable, $pricing_model, $pricing_category, $messageId);
                                } elseif ($statusType === 'failed') {
                                    $errors_code = $status['errors'][0]['code'] ?? null;
                                    $errors_title = $status['errors'][0]['title'] ?? null;
                                    $errors_message = $status['errors'][0]['message'] ?? null;
                                    $error_data_details = $status['errors'][0]['error_data']['details'] ?? null;

                                    $update = $conn->prepare("UPDATE messages SET $timeColumn = ?, status = 'failed', errors_code = ?, errors_title = ?, errors_message = ?, error_data_details = ? WHERE message_id = ?");
                                    $update->bind_param("ssssss", $timestamp, $errors_code, $errors_title, $errors_message, $error_data_details, $messageId);
                                } else {
                                    $update = $conn->prepare("UPDATE messages SET $timeColumn = ? WHERE message_id = ?");
                                    $update->bind_param("ss", $timestamp, $messageId);
                                }

                                $update->execute();
                            }
                        }
                    }
                }

                // === Handle replies ===
                if (isset($value['messages'])) {
                    foreach ($value['messages'] as $msg) {
                        $replyId = $msg['id'] ?? null;
                        $replyFrom = $msg['from'] ?? null;
                        $replyText = $msg['text']['body'] ?? null;
                        $receivedAt = isset($msg['timestamp']) ? date('Y-m-d H:i:s', $msg['timestamp']) : null;

                        if ($replyId && $replyFrom && $replyText && $receivedAt) {
                            $stmt = $conn->prepare("INSERT INTO message_replies (reply_id, message_from, reply_text, received_at) VALUES (?, ?, ?, ?)");
                            $stmt->bind_param("ssss", $replyId, $replyFrom, $replyText, $receivedAt);
                            $stmt->execute();
                        }
                    }
                }
            }
        }
    }

    http_response_code(200);
    exit;
}
