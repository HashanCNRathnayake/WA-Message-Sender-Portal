<?php
date_default_timezone_set('Asia/Singapore');
session_start();

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../db.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

$baseUrl = $_ENV['BASE_URL'] ?? '/';

if ($_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit;
}

$username = $_SESSION['username'] ?? '';
$verify_token = 'eduCLaaSCM_WA';
$logFile = 'webhook-log.txt';

// === Dashboard Data Fetching ===
$filter = $_GET['filter'] ?? '';
$allowedFilters = ['failed', 'sent', 'delivered'];
$params = [];
$types = '';

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

if (in_array($filter, $allowedFilters)) {
    $sql .= " WHERE m.status = ?";
    $params[] = $filter;
    $types .= 's';
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

        if (!empty($row['reply_text']) && stripos($row['reply_text'], 'confirm') !== false) {
            $messages[$id]['reply_status'] = 'Confirm';
            $messages[$id]['reply_time'] = $row['reply_received_at'];
        }
    }
}

// === Cost Calculation Logic ===
$pricingCache = [];
$codeMapCache = [];
$sql = "SELECT m.wa_id, m.conversation_id, MIN(m.pricing_category) AS pricing_category
FROM messages m
LEFT JOIN (
    SELECT wa_id, conversation_id
    FROM messages
    WHERE cost IS NOT NULL
    GROUP BY wa_id, conversation_id
) billed ON m.wa_id = billed.wa_id AND m.conversation_id = billed.conversation_id
WHERE billed.wa_id IS NULL AND m.cost IS NULL 
  AND m.wa_id IS NOT NULL AND m.conversation_id IS NOT NULL
GROUP BY m.wa_id, m.conversation_id
";
$result = $conn->query($sql);

while ($row = $result->fetch_assoc()) {
    $wa_id = $row['wa_id'];
    $conversation_id = $row['conversation_id'];
    $category = $row['category'];
    $region = null;

    for ($i = 3; $i >= 1; $i--) {
        $prefix = substr($wa_id, 0, $i);
        if (isset($codeMapCache[$prefix])) {
            $region = $codeMapCache[$prefix];
            break;
        }

        $stmt = $conn->prepare("SELECT region FROM country_code_map WHERE country_code = ? LIMIT 1");
        $stmt->bind_param("s", $prefix);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res && $res->num_rows > 0) {
            $regionRow = $res->fetch_assoc();
            $region = $regionRow['region'];
            $codeMapCache[$prefix] = $region;
            break;
        }
    }

    $region = $region ?: 'Other';

    if (!isset($pricingCache[$region])) {
        $stmt2 = $conn->prepare("SELECT marketing, utility, authentication FROM meta_msg_cost WHERE region = ? LIMIT 1");
        $stmt2->bind_param("s", $region);
        $stmt2->execute();
        $res2 = $stmt2->get_result();
        $pricingCache[$region] = $res2 && $res2->num_rows > 0 ? $res2->fetch_assoc() : null;
    }

    $pricing = $pricingCache[$region];
    if (!$pricing) continue;

    $cost = floatval($pricing[$category] ?? $pricing['marketing'] ?? 0.0);
    $now = date('Y-m-d H:i:s');

    $stmt3 = $conn->prepare("UPDATE messages SET cost = ?, cost_calculated_at = ? 
        WHERE id = (
            SELECT id FROM (
                SELECT id 
                FROM messages 
                WHERE wa_id = ? AND conversation_id = ? AND cost IS NULL 
                ORDER BY sent ASC LIMIT 1
            ) AS sub
        )
    ");
    $stmt3->bind_param("dsss", $cost, $now, $wa_id, $conversation_id);
    $stmt3->execute();
}

// === Summary Stats ===
$statsQuery = $conn->query("SELECT COUNT(*) AS total_conversations, SUM(cost) AS total_cost, AVG(cost) AS avg_cost, MAX(cost) AS max_cost, MIN(CASE WHEN cost > 0 THEN cost ELSE NULL END) AS min_nonzero_cost, COUNT(DISTINCT wa_id) AS unique_customers FROM (SELECT wa_id, conversation_id, MIN(cost) AS cost FROM messages WHERE cost IS NOT NULL GROUP BY wa_id, conversation_id) AS convs");
$stats = $statsQuery->fetch_assoc();

$todayCostQuery = $conn->query("SELECT SUM(cost) AS today_cost FROM (SELECT wa_id, conversation_id, MIN(cost) AS cost FROM messages WHERE cost IS NOT NULL AND DATE(sent) = CURDATE() GROUP BY wa_id, conversation_id) AS today_conversations");
$todayCost = $todayCostQuery->fetch_assoc()['today_cost'] ?? 0;

?>


<!DOCTYPE html>
<html>

<head>
    <title>Dashboard</title>
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
        <?php include __DIR__ . '/../components/navbar.php'; ?>

        <div class="d-flex flex-column justify-content-between">
            <div class="d-flex flex-row justify-content-between">
                <h4 class="mb-3">Dashboard</h4>
                <div class="d-flex flex-row justify-content-end">

                    <form action="/../export_data.php" method="post">
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