<?php
date_default_timezone_set('Asia/Singapore');
require_once __DIR__ . '/vendor/autoload.php';
require 'db.php';

$logFile = 'webhook-log.txt';
$lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
$count = count($lines);

for ($i = 0; $i < $count; $i++) {
    $line = trim($lines[$i]);

    if (strpos($line, '{') !== 0) continue;

    $json = $line;
    $data = json_decode($json, true);
    if (!$data || !isset($data['entry'])) continue;

    foreach ($data['entry'] as $entry) {
        foreach ($entry['changes'] as $change) {
            $value = $change['value'];

            if (isset($value['statuses'])) {
                foreach ($value['statuses'] as $status) {
                    $messageId = $status['id'] ?? null;
                    $statusType = $status['status'] ?? null;
                    $timestamp = isset($status['timestamp']) ? date('Y-m-d H:i:s', $status['timestamp']) : null;

                    $conversation_id = $status['conversation']['id'] ?? null;
                    $conversation_exp = isset($status['conversation']['expiration_timestamp']) ? date('Y-m-d H:i:s', $status['conversation']['expiration_timestamp']) : null;
                    $conversation_origin = $status['conversation']['origin']['type'] ?? null;

                    $pricing_billable = $status['pricing']['billable'] ?? null;
                    $pricing_model = $status['pricing']['pricing_model'] ?? null;
                    $pricing_category = $status['pricing']['category'] ?? null;

                    $errors_code = $status['errors'][0]['code'] ?? null;
                    $errors_title = $status['errors'][0]['title'] ?? null;
                    $errors_message = $status['errors'][0]['message'] ?? null;
                    $error_data_details = $status['errors'][0]['error_data']['details'] ?? null;

                    $wa_id = $status['recipient_id'] ?? null;

                    $timeColumn = match ($statusType) {
                        'sent' => 'sent',
                        'delivered' => 'delivered',
                        'read' => 'read',
                        'failed' => 'failed',
                        default => null
                    };

                    if ($timeColumn && $messageId) {
                        $check = $conn->prepare("SELECT id FROM messages WHERE message_id = ?");
                        $check->bind_param("s", $messageId);
                        $check->execute();
                        $check->store_result();

                        if ($check->num_rows > 0) {
                            $column = ($timeColumn === 'read') ? "`read`" : $timeColumn;

                            if ($statusType === 'sent') {
                                $sql = "UPDATE messages SET $column = ?, status = 'success', wa_id = ?, conversation_id = ?, conversation_exp = ?, conversation_origin = ?, pricing_billable = ?, pricing_model = ?, pricing_category = ?, errors_code = NULL, errors_title = NULL, errors_message = NULL, error_data_details = NULL, failed = NULL WHERE message_id = ?";
                                $stmt = $conn->prepare($sql);
                                $stmt->bind_param("sssssssss", $timestamp, $wa_id, $conversation_id, $conversation_exp, $conversation_origin, $pricing_billable, $pricing_model, $pricing_category, $messageId);
                                $stmt->execute();
                            } elseif ($statusType === 'failed') {
                                $sql = "UPDATE messages SET $column = ?, status = 'failed', errors_code = ?, errors_title = ?, errors_message = ?, error_data_details = ? WHERE message_id = ?";
                                $stmt = $conn->prepare($sql);
                                $stmt->bind_param("ssssss", $timestamp, $errors_code, $errors_title, $errors_message, $error_data_details, $messageId);
                                $stmt->execute();
                            } else {
                                $stmt = $conn->prepare("UPDATE messages SET $column = ? WHERE message_id = ?");
                                $stmt->bind_param("ss", $timestamp, $messageId);
                                $stmt->execute();
                            }
                        }
                    }
                }
            }

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

// echo "✅ Log processing completed.\n";
header("Location: webhook.php");
exit;
