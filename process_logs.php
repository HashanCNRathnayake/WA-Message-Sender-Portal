<?php
$logFile = 'webhook-log.txt';
date_default_timezone_set('Asia/Singapore');

// session_start(); // Not needed for webhook POST
require_once __DIR__ . '/vendor/autoload.php';
require 'db.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();
$baseUrl = $_ENV['BASE_URL'];

$username = $_SESSION['username'] ?? '';

$lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
$count = count($lines);

for ($i = 0; $i < $count; $i++) {
    $line = trim($lines[$i]);

    if (strpos($line, '{') === 0) continue; // skip JSON without date
    $timestamp = $line;

    $i++;
    if (!isset($lines[$i]) || strpos(trim($lines[$i]), '{') !== 0) continue;

    $json = trim($lines[$i]);
    $data = json_decode($json, true);

    if (!isset($data['entry'])) continue;

    foreach ($data['entry'] as $entry) {
        foreach ($entry['changes'] as $change) {
            $value = $change['value'];

            // === STATUSES ===
            if (isset($value['statuses'])) {
                foreach ($value['statuses'] as $status) {
                    $statuses_id = $status['id'] ?? null;
                    $statusType = $status['status'] ?? null;
                    $timestamp = isset($status['timestamp']) ? date('Y-m-d H:i:s', $status['timestamp']) : null;
                    $statuses_recipient_id = $status['recipient_id'] ?? null;

                    $conversation_id = $status['conversation']['id'] ?? null;
                    $conversation_exp = isset($status['conversation']['expiration_timestamp'])
                        ? date('Y-m-d H:i:s', $status['conversation']['expiration_timestamp'])
                        : null;
                    $conversation_origin = $status['conversation']['origin']['type'] ?? null;

                    $pricing_billable = $status['pricing']['billable'] ?? null;
                    $pricing_model = $status['pricing']['pricing_model'] ?? null;
                    $pricing_category = $status['pricing']['category'] ?? null;

                    $errors_code = $status['errors'][0]['code'] ?? null;
                    $errors_title = $status['errors'][0]['title'] ?? null;
                    $errors_message = $status['errors'][0]['message'] ?? null;
                    $error_data_details = $status['errors'][0]['error_data']['details'] ?? null;

                    // Check if message already exists
                    $check = $conn->prepare("SELECT statuses_id FROM statuses WHERE statuses_id = ?");
                    $check->bind_param("s", $statuses_id);
                    $check->execute();
                    $check->store_result();

                    if ($check->num_rows > 0) {
                        $column = $statusType;
                        if (in_array($column, ['sent', 'delivered', 'read', 'failed'])) {
                            $columnSafe = ($column === 'read') ? "`read`" : $column;
                            $stmt = $conn->prepare("UPDATE statuses SET $columnSafe = ?, conversation_id = ?, conversation_expiration_timestamp = ?, conversation_origin_type = ?, pricing_billable = ?, pricing_model = ?, pricing_category = ?, errors_code = ?, errors_title = ?, errors_message = ?, error_data_details = ? WHERE statuses_id = ?");
                            $stmt->bind_param(
                                "ssssssssssss",
                                $timestamp,
                                $conversation_id,
                                $conversation_exp,
                                $conversation_origin,
                                $pricing_billable,
                                $pricing_model,
                                $pricing_category,
                                $errors_code,
                                $errors_title,
                                $errors_message,
                                $error_data_details,
                                $statuses_id
                            );
                            $stmt->execute();
                        }
                    } else {
                        // Insert new with only the appropriate status column filled
                        $sent = $delivered = $read = $failed = null;
                        if ($statusType === 'sent') $sent = $timestamp;
                        if ($statusType === 'delivered') $delivered = $timestamp;
                        if ($statusType === 'read') $read = $timestamp;
                        if ($statusType === 'failed') $failed = $timestamp;

                        $stmt = $conn->prepare("INSERT INTO statuses (statuses_id, statuses_recipient_id, sent, failed, delivered, `read`, conversation_id, conversation_expiration_timestamp, conversation_origin_type, pricing_billable, pricing_model, pricing_category, errors_code, errors_title, errors_message, error_data_details) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

                        $stmt->bind_param(
                            "ssssssssssssssss",
                            $statuses_id,
                            $statuses_recipient_id,
                            $sent,
                            $failed,
                            $delivered,
                            $read,
                            $conversation_id,
                            $conversation_exp,
                            $conversation_origin,
                            $pricing_billable,
                            $pricing_model,
                            $pricing_category,
                            $errors_code,
                            $errors_title,
                            $errors_message,
                            $error_data_details
                        );
                        $stmt->execute();
                    }
                }
            }

            // === MESSAGES ===
            if (isset($value['messages'])) {
                foreach ($value['messages'] as $msg) {
                    $messages_id = $msg['id'] ?? null;
                    $messages_from = $msg['from'] ?? null;
                    $messages_timestamp = isset($msg['timestamp']) ? date('Y-m-d H:i:s', $msg['timestamp']) : null;
                    $messages_text_body = $msg['text']['body'] ?? null;
                    $messages_type = $msg['type'] ?? null;

                    $contacts_profile_name = $value['contacts'][0]['profile']['name'] ?? null;
                    $contacts_wa_id = $value['contacts'][0]['wa_id'] ?? null;

                    $stmt = $conn->prepare("INSERT INTO message_replies_updated (messages_id, messages_from, messages_timestamp, messages_text_body, messages_type, contacts_profile_name, contacts_wa_id) VALUES (?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE messages_text_body=VALUES(messages_text_body), messages_timestamp=VALUES(messages_timestamp)");

                    $stmt->bind_param(
                        "sssssss",
                        $messages_id,
                        $messages_from,
                        $messages_timestamp,
                        $messages_text_body,
                        $messages_type,
                        $contacts_profile_name,
                        $contacts_wa_id
                    );
                    $stmt->execute();
                }
            }
        }
    }
}

echo "Log processing completed.\n";

?>

<!DOCTYPE html>
<html>

<head>
    <title>WhatsApp Logs</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .scroll-table-wrapper {
            overflow-x: auto;
            overflow-y: auto;
            max-height: 500px;
            border: 1px solid #ccc;
        }

        table {
            white-space: nowrap;
        }
    </style>
</head>

<body class="p-4">

    <h2>Statuses Table</h2>
    <div class="scroll-table-wrapper mb-5">
        <table class="table table-bordered table-striped table-sm">
            <thead class="table-dark">
                <tr>
                    <th>statuses_id</th>
                    <th>statuses_recipient_id</th>
                    <th>sent</th>
                    <th>failed</th>
                    <th>delivered</th>
                    <th>read</th>
                    <th>conversation_id</th>
                    <th>conversation_expiration_timestamp</th>
                    <th>conversation_origin_type</th>
                    <th>pricing_billable</th>
                    <th>pricing_model</th>
                    <th>pricing_category</th>
                    <th>errors_code</th>
                    <th>errors_title</th>
                    <th>errors_message</th>
                    <th>error_data_details</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $res = $conn->query("SELECT * FROM statuses ORDER BY COALESCE(sent, failed) ");
                while ($row = $res->fetch_assoc()) {
                    echo "<tr>";
                    foreach ($row as $val) {
                        echo "<td>" . htmlspecialchars($val) . "</td>";
                    }
                    echo "</tr>";
                }
                ?>
            </tbody>
        </table>
    </div>

    <h2>Message Replies Table</h2>
    <div class="scroll-table-wrapper">
        <table class="table table-bordered table-striped table-sm">
            <thead class="table-dark">
                <tr>
                    <th>messages_id</th>
                    <th>messages_from</th>
                    <th>messages_timestamp</th>
                    <th>messages_text_body</th>
                    <th>messages_type</th>
                    <th>contacts_profile_name</th>
                    <th>contacts_wa_id</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $res = $conn->query("SELECT * FROM message_replies_updated ORDER BY messages_timestamp DESC");
                while ($row = $res->fetch_assoc()) {
                    echo "<tr>";
                    foreach ($row as $val) {
                        echo "<td>" . htmlspecialchars($val) . "</td>";
                    }
                    echo "</tr>";
                }
                ?>
            </tbody>
        </table>
    </div>

</body>

</html>