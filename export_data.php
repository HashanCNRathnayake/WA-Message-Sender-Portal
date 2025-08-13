<?php
date_default_timezone_set('Asia/Singapore');

// session_start(); // Not needed for webhook POST
require_once __DIR__ . '/vendor/autoload.php';
require 'db.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();
$baseUrl = $_ENV['BASE_URL'];

$username = $_SESSION['username'] ?? '';

if ($_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit;
}

// 1. Get all messages for the current user
$messageQuery = $conn->prepare("
    SELECT 
        m.id,
        m.user_id,
        m.phone,
        m.wa_id,
        m.tag,
        m.message,
        m.message_id,
        m.sent_at,
        m.status,
        m.sent_at_display,
        m.sent,
        m.failed,
        m.delivered,
        m.read,
        m.conversation_id,
        m.conversation_exp,
        m.conversation_origin,
        m.pricing_billable,
        m.pricing_model,
        m.pricing_category,
        m.errors_code,
        m.errors_title,
        m.errors_message,
        m.error_data_details,
        m.cost,
        m.cost_calculated_at,
        u.username
    FROM messages m
    JOIN users u ON m.user_id = u.id
    ORDER BY m.sent_at ASC
");

$messageQuery->execute();
$messageResult = $messageQuery->get_result();

$messages = [];
while ($row = $messageResult->fetch_assoc()) {
    $messages[$row['message_id']] = $row;
    $messages[$row['message_id']]['all_replies'] = '';
    $messages[$row['message_id']]['latest_reply_time'] = null;
}

// 2. Get replies and flag if 'confirm' exists
$replyQuery = "SELECT message_from, reply_text, received_at FROM message_replies ORDER BY received_at DESC";
$replyResult = $conn->query($replyQuery);

foreach ($messages as &$msg) {
    $msg['all_replies'] = '';
    $msg['latest_reply_time'] = null;

    $replyResult->data_seek(0);
    while ($reply = $replyResult->fetch_assoc()) {
        if ($msg['phone'] === $reply['message_from']) {
            if (stripos($reply['reply_text'], 'confirm') !== false) {
                $msg['all_replies'] = 'Confirm';
                $msg['latest_reply_time'] = $reply['received_at'];
                break;
            }
        }
    }
}
unset($msg);
// 3. Output CSV headers
header('Content-Type: text/csv');
header('Content-Disposition: attachment;filename="whatsapp_full_export.csv"');
$output = fopen('php://output', 'w');

// 4. Header Row
fputcsv($output, [
    'ID',
    'User Name',
    'Phone',
    'WA ID',
    'Cohart Name',
    'Message',
    'Message ID',
    'Sent At',
    'Status',
    'Sent At Display',
    'Sent',
    'Failed',
    'Delivered',
    'Read',
    'Conversation ID',
    'Conversation Expiry',
    'Conversation Origin',
    'Pricing Billable',
    'Pricing Model',
    'Pricing Category',
    'Error Code',
    'Error Title',
    'Error Message',
    'Error Data Details',
    'Cost',
    'Cost Calculated At',
    'Reply Status',
    'Reply Time'
]);

function formatDateTime($val)
{
    return $val ? (new DateTime($val))->format('Y-m-d H:i:s') : '';
}

// 5. Write Rows
foreach ($messages as $msg) {
    fputcsv($output, [
        $msg['id'],
        $msg['username'],
        $msg['phone'],
        $msg['wa_id'],
        $msg['tag'],
        $msg['message'],
        $msg['message_id'],
        formatDateTime($msg['sent_at']),
        $msg['status'],
        $msg['sent_at_display'],
        formatDateTime($msg['sent']),
        formatDateTime($msg['failed']),
        formatDateTime($msg['delivered']),
        formatDateTime($msg['read']),
        $msg['conversation_id'],
        formatDateTime($msg['conversation_exp']),
        $msg['conversation_origin'],
        is_null($msg['pricing_billable']) ? '' : ($msg['pricing_billable'] ? 'Yes' : 'No'),
        $msg['pricing_model'],
        $msg['pricing_category'],
        $msg['errors_code'],
        $msg['errors_title'],
        $msg['errors_message'],
        $msg['error_data_details'],
        $msg['cost'],
        formatDateTime($msg['cost_calculated_at']),
        $msg['all_replies'],
        formatDateTime($msg['latest_reply_time'])
    ]);
}

fclose($output);
exit;
