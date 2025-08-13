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
$user_id = $_SESSION['user_id'] ?? null;

if (!$user_id) {
    die("User not logged in.");
}

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

// === Dashboard Data Fetching ===
$filter = $_GET['filter'] ?? '';
$allowedFilters = ['failed', 'sent', 'delivered'];
$params = [$user_id];
$types = 'i';
$conditions = ["m.user_id = ?"];

if (in_array($filter, $allowedFilters)) {
    $conditions[] = "m.status = ?";
    $params[] = $filter;
    $types .= 's';
}

$sql = "
    SELECT 
        m.id AS message_id,
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
        u.username,
        GROUP_CONCAT(r.reply_text ORDER BY r.received_at SEPARATOR ' || ') AS all_replies,
        MAX(r.received_at) AS latest_reply_time
    FROM messages m
    JOIN users u ON m.user_id = u.id
    LEFT JOIN message_replies r 
        ON r.message_from = m.phone 
        AND (m.delivered IS NULL OR r.received_at > m.delivered)
";

if (!empty($conditions)) {
    $sql .= " WHERE " . implode(" AND ", $conditions);
}

$sql .= " GROUP BY m.id";
$sql .= " ORDER BY m.sent_at DESC, latest_reply_time ASC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

$messages = [];
while ($row = $result->fetch_assoc()) {
    $id = $row['message_id'];
    if (!isset($messages[$id])) {
        $messages[$id] = $row;
        $messages[$id]['reply_status'] = '';
        $messages[$id]['reply_time'] = null;

        if (!empty($row['all_replies']) && stripos($row['all_replies'], 'confirm') !== false) {
            $messages[$id]['reply_status'] = 'Confirm';
            $messages[$id]['reply_time'] = $row['latest_reply_time'];
        }
    }
}



// === Summary Stats (user-specific) ===
$statsQuery = $conn->prepare("SELECT COUNT(*) AS total_conversations, SUM(cost) AS total_cost, AVG(cost) AS avg_cost, MAX(cost) AS max_cost, MIN(CASE WHEN cost > 0 THEN cost ELSE NULL END) AS min_nonzero_cost, COUNT(DISTINCT wa_id) AS unique_customers FROM (SELECT wa_id, conversation_id, MIN(cost) AS cost FROM messages WHERE cost IS NOT NULL AND user_id = ? GROUP BY wa_id, conversation_id) AS convs");
$statsQuery->bind_param("i", $user_id);
$statsQuery->execute();
$statsResult = $statsQuery->get_result();
$stats = $statsResult->fetch_assoc();

$todayCostQuery = $conn->prepare("SELECT SUM(cost) AS today_cost FROM (SELECT wa_id, conversation_id, MIN(cost) AS cost FROM messages WHERE cost IS NOT NULL AND DATE(sent) = CURDATE() AND user_id = ? GROUP BY wa_id, conversation_id) AS today_conversations");
$todayCostQuery->bind_param("i", $user_id);
$todayCostQuery->execute();
$todayCostResult = $todayCostQuery->get_result();
$todayCost = $todayCostResult->fetch_assoc()['today_cost'] ?? 0;

if (isset($_POST['download_csv'])) {

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
}
?>


<!DOCTYPE html>
<html>

<head>
    <title>Send History</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<style>
    .fixed-col {
        width: auto;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .fixed-col-wide {
        width: 180px;
        max-width: 180px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .expand-col {
        min-width: 200px;
        white-space: normal;
        word-break: break-word;
    }
</style>

<body class="bg-light">

    <div class="container">
        <?php include 'components/navbar.php'; ?>

        <div class="d-flex flex-column justify-content-between">
            <div class="d-flex flex-row justify-content-between">
                <h4 class="mb-3">Your Message History</h4>
                <div class="d-flex flex-row justify-content-end">

                    <form action="export_data.php" method="post">
                        <button type="submit" class="btn btn-sm btn-primary">Download Data Report</button>
                    </form>
                    <!-- <form action="update_database.php" method="post" onsubmit="return confirm('Run log processor?');">
                        <button type="submit" class="btn btn-sm btn-warning ms-1">📂 Process Log File</button>
                    </form>
                    <form action="functions/calculate_message_cost.php" method="post" onsubmit="return confirm('Calculate the costs and save?');">
                        <button type="submit" class="btn btn-sm btn-warning ms-1 ">Calculate Cost</button>
                    </form> -->
                    <div class="d-flex flex-row justify-content-end align-items-start ms-2">
                        <?php if (($_GET['filter'] ?? '') === 'failed'): ?>
                            <a href="?" class="btn btn-outline-primary btn-sm">
                                🔄 Show All
                            </a>
                        <?php else: ?>
                            <a href="?filter=failed" class="btn btn-outline-danger btn-sm">
                                ❌ Show Failed Messages
                            </a>
                        <?php endif; ?>
                    </div>

                </div>
            </div>
            <div class="row text-center">
                <div class="col mb-3">
                    <div class="card shadow-sm border-0 bg-light">
                        <div class="card-body">
                            <h6>Today's Cost</h6>
                            <h4 class="text-primary">$<?= number_format($todayCost, 4) ?></h4>
                        </div>
                    </div>
                </div>

                <div class="col mb-3">
                    <div class="card shadow-sm border-0">
                        <div class="card-body">
                            <h6>Total Cost</h6>
                            <h4 class="text-success">$<?= number_format($stats['total_cost'], 4) ?></h4>
                        </div>
                    </div>
                </div>
                <div class="col mb-3">
                    <div class="card shadow-sm border-0">
                        <div class="card-body">
                            <h6>Avg Cost / Conversation</h6>
                            <h4>$<?= number_format($stats['avg_cost'], 4) ?></h4>
                        </div>
                    </div>
                </div>
                <div class="col mb-3">
                    <div class="card shadow-sm border-0">
                        <div class="card-body">
                            <h6>Total Unique Conversations</h6>
                            <h4><?= number_format($stats['total_conversations']) ?></h4>
                        </div>
                    </div>
                </div>
            </div>


        </div>



        <div class="table-responsive" style="max-height: 450px; overflow: auto;">
            <table class="table table-bordered table-striped table-hover table-sm" style="min-width: 2000px;">
                <thead class="table-dark sticky-top">
                    <tr>
                        <th class="fixed-col">User</th>
                        <th class="fixed-col">Phone</th>
                        <th class="fixed-col">WA ID</th>
                        <th class="fixed-col">Cohart</th>
                        <th class="fixed-col">Message</th>
                        <th class="fixed-col-wide">Message ID</th>
                        <th class="fixed-col">Status</th>
                        <th class="fixed-col-wide">Sent At (SGT)</th>
                        <th class="fixed-col">Sent</th>
                        <th class="fixed-col">Failed</th>
                        <th class="fixed-col">Delivered</th>
                        <th class="fixed-col">Read</th>
                        <th class="fixed-col-wide">Conversation ID</th>
                        <th class="fixed-col">Conv. Exp.</th>
                        <th class="fixed-col">Origin</th>
                        <th class="fixed-col">Billable</th>
                        <th class="fixed-col">Model</th>
                        <th class="fixed-col">Category</th>
                        <th class="fixed-col">Cost</th>
                        <th class="fixed-col">Err Code</th>
                        <th class="fixed-col-wide">Err Title</th>
                        <th class="expand-col">Err Message</th>
                        <th class="expand-col">Err Details</th>
                        <th class="fixed-col">Reply</th>
                        <th class="fixed-col-wide">Reply At</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($messages as $row): ?>
                        <tr>
                            <td class="fixed-col"><?= htmlspecialchars($row['username']) ?></td>
                            <td class="fixed-col"><?= htmlspecialchars($row['phone']) ?></td>
                            <td class="fixed-col"><?= htmlspecialchars($row['wa_id']) ?></td>
                            <td class="fixed-col"><?= htmlspecialchars($row['tag']) ?></td>
                            <td class="fixed-col"><?= nl2br(htmlspecialchars($row['message'])) ?></td>
                            <td class="fixed-col-wide"><?= htmlspecialchars($row['message_id']) ?></td>
                            <td class="fixed-col">
                                <?php if ($row['status'] === 'success'): ?>
                                    <span class="badge bg-success">✅</span>
                                <?php elseif ($row['status'] === 'failed'): ?>
                                    <span class="badge bg-danger">❌</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary"><?= htmlspecialchars($row['status']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="fixed-col-wide"><?= $row['sent_at_display'] ?></td>
                            <td class="fixed-col"><?= $row['sent'] ? date('M d | g.i a', strtotime($row['sent'])) : '—' ?></td>
                            <td class="fixed-col"><?= $row['failed'] ? date('M d | g.i a', strtotime($row['failed'])) : '—' ?></td>
                            <td class="fixed-col"><?= $row['delivered'] ? date('M d | g.i a', strtotime($row['delivered'])) : '—' ?></td>
                            <td class="fixed-col"><?= $row['read'] ? date('M d | g.i a', strtotime($row['read'])) : '—' ?></td>
                            <td class="fixed-col-wide"><?= htmlspecialchars($row['conversation_id']) ?></td>
                            <td class="fixed-col"><?= $row['conversation_exp'] ? date('M d | g.i a', strtotime($row['conversation_exp'])) : '—' ?></td>
                            <td class="fixed-col"><?= htmlspecialchars($row['conversation_origin']) ?></td>
                            <td class="fixed-col"><?= is_null($row['pricing_billable']) ? '—' : ($row['pricing_billable'] ? 'Yes' : 'No') ?></td>
                            <td class="fixed-col"><?= htmlspecialchars($row['pricing_model']) ?></td>
                            <td class="fixed-col"><?= htmlspecialchars($row['pricing_category']) ?></td>
                            <td class="fixed-col"><?= htmlspecialchars($row['cost']) ?></td>
                            <td class="fixed-col"><?= htmlspecialchars($row['errors_code']) ?></td>
                            <td class="fixed-col-wide"><?= htmlspecialchars($row['errors_title']) ?></td>
                            <td class="expand-col"><?= nl2br(htmlspecialchars($row['errors_message'])) ?></td>
                            <td class="expand-col"><?= nl2br(htmlspecialchars($row['error_data_details'])) ?></td>
                            <td class="fixed-col">
                                <?php if (!empty($row['all_replies']) && $row['status'] === 'success'): ?>
                                    <span class="badge bg-success">✅Confirm</span>
                                <?php else: ?>
                                    <span class="text-muted">–</span>
                                <?php endif; ?>
                            </td>
                            <td class="fixed-col-wide">
                                <?= !empty($row['latest_reply_time']) ? date('M d | g.i a', strtotime($row['latest_reply_time'])) : '—' ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

    </div>

</body>

</html>