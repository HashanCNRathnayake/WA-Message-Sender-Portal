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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

    <link href="dashboardStyles.css" rel="stylesheet">

</head>

<body class="bg-light">

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

        .table thead th {
            border-top: none;
            font-weight: 600;
            color: #495057;
            background-color: #f8f9fa;
            border-bottom: 2px solid #dee2e6;
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .badge-outline {
            border: 1px solid;
            border-radius: 50rem;
            /* pill shape */
            padding: 0.35em 0.65em;
            font-size: 0.75em;
            font-weight: 600;
        }

        .badge-pending {
            background-color: #f8f9fa;
            /* light gray */
            color: #6c757d;
            /* dark gray */
            border-color: #6c757d;
        }

        .badge-sent {
            background-color: #d4edda;
            /* light green */
            color: #155724;
            /* dark green */
            border-color: #155724;
        }

        .badge-delivered {
            background-color: #d1ecf1;
            /* light blue */
            color: #0c5460;
            /* dark blue */
            border-color: #0c5460;
        }

        .badge-read {
            background-color: #e2e3f3;
            /* light indigo */
            color: #383d8a;
            /* dark indigo */
            border-color: #383d8a;
        }

        .badge-failed {
            background-color: #f8d7da;
            /* light red */
            color: #721c24;
            /* dark red */
            border-color: #721c24;
        }

        .no_border:focus {
            outline: none !important;
            box-shadow: none !important;
        }

        .no_border .dropdown-menu {
            background-color: #000000ff !important;
        }
    </style>

    <div class="container">
        <?php include __DIR__ . '/../components/navbar.php'; ?>

        <div class="d-flex flex-column justify-content-between">
            <!-- <div class="d-flex flex-row justify-content-between">
                <h4 class="mb-3">Dashboard</h4>
                <div class="d-flex flex-row justify-content-end">

                    <form action="/../export_data.php" method="post">
                        <button type="submit" class="btn btn-sm btn-primary">Download Data Report</button>
                    </form>
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
            </div> -->

            <div class="row">
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="card metric-card h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="text-muted mb-1 small mb-4">Today's Cost</h6>
                                    <h4 class="mb-0 fw-bold">$<?= number_format($todayCost, 4) ?></h4>
                                    <small class="text-muted">+0% from yesterday</small>
                                </div>
                                <div class="metric-icon text-dark mb-auto">
                                    <!-- <i class="fas fa-dollar-sign"></i> -->
                                    <i class="bi bi-currency-dollar"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="card metric-card h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="text-muted mb-1 small mb-4">Total Cost</h6>
                                    <h4 class="mb-0 text-primary fw-bold">$<?= number_format($stats['total_cost'], 4) ?></h4>
                                    <small class="text-muted">Lifetime spending</small>
                                </div>
                                <div class="metric-icon text-dark mb-auto">
                                    <!-- <i class="fas fa-chart-line"></i> -->
                                    <i class="bi bi-graph-up-arrow"></i>

                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="card metric-card h-100">
                        <div class="card-body">

                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="text-muted mb-1 small mb-4">Avg Cost/Conversation</h6>
                                    <h4 class="mb-0 fw-bold">$<?= number_format($stats['avg_cost'], 4) ?></h4>
                                    <small class="text-muted">Per Conversation</small>
                                </div>
                                <div class="metric-icon text-dark mb-auto">
                                    <!-- <i class="fa-regular fa-comment"></i> -->
                                    <i class="bi bi-chat-left"></i>

                                </div>
                            </div>

                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="card metric-card h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="text-muted mb-1 small mb-4">Avg Cost/Conversation</h6>
                                    <h4 class="mb-0 fw-bold"><?= number_format($stats['total_conversations']) ?></h4>
                                    <small class="text-muted">Unique conversations</small>
                                </div>
                                <div class="metric-icon text-dark mb-auto">
                                    <!-- <i class="fa fa-users"></i> -->
                                    <i class="bi bi-people"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>

        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header bg-white">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h5 class="mb-1">Message History</h5>
                                <p class="text-muted mb-0 small">View and manage all your WhatsApp messages</p>
                            </div>
                            <div class="d-flex align-items-center">
                                <button id="columnSettingsBtn" class="btn btn-sm btn-outline-dark mb-2 me-2">
                                    <i class="bi bi-gear me-2"></i>Columns
                                </button>
                                <form action="../export_data.php" method="post">
                                    <button type="submit" class="btn btn-sm btn-outline-dark mb-2">
                                        <i class="bi bi-download me-2"></i>Report</button>
                                </form>


                            </div>

                        </div>
                    </div>
                    <div class="card-body">
                        <!-- Column Settings Panel -->
                        <div id="columnSettings" class="card p-3 mb-3 d-none">
                            <div id="columnCheckboxes" class="row">
                                <div class="col mb-3"><strong>Main Details</strong>
                                    <div class="col mb-2">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="col-user" checked="">
                                            <label class="form-check-label" for="col-user">User</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="col-phone" checked="">
                                            <label class="form-check-label" for="col-phone">Phone</label>
                                        </div>


                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="col-wa_id" checked="">
                                            <label class="form-check-label" for="col-wa_id">Wa id</label>
                                        </div>

                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="col-cohort" checked="">
                                            <label class="form-check-label" for="col-cohort">Cohort</label>
                                        </div>


                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="col-message" checked="">
                                            <label class="form-check-label" for="col-message">Message</label>
                                        </div>


                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="col-status" checked="">
                                            <label class="form-check-label" for="col-status">Status</label>
                                        </div>
                                    </div>
                                </div>
                                <div class="col mb-3"><strong>Status Details</strong>
                                    <div class="col mb-2">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="col-message_id">
                                            <label class="form-check-label" for="col-message_id">Message id</label>
                                        </div>


                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="col-sent_at_display">
                                            <label class="form-check-label" for="col-sent_at_display">Sent at display</label>
                                        </div>


                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="col-sent">
                                            <label class="form-check-label" for="col-sent">Sent</label>
                                        </div>


                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="col-failed" checked="">
                                            <label class="form-check-label" for="col-failed">Failed</label>
                                        </div>


                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="col-delivered" checked="">
                                            <label class="form-check-label" for="col-delivered">Delivered</label>
                                        </div>


                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="col-read">
                                            <label class="form-check-label" for="col-read">Read</label>
                                        </div>

                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="col-conversation_id">
                                            <label class="form-check-label" for="col-conversation_id">Conversation id</label>
                                        </div>


                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="col-conversation_exp">
                                            <label class="form-check-label" for="col-conversation_exp">Conversation exp</label>
                                        </div>


                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="col-conversation_origin">
                                            <label class="form-check-label" for="col-conversation_origin">Conversation origin</label>
                                        </div>


                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="col-all_replies" checked="">
                                            <label class="form-check-label" for="col-all_replies">All replies</label>
                                        </div>


                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="col-latest_reply_time">
                                            <label class="form-check-label" for="col-latest_reply_time">Latest reply time</label>
                                        </div>
                                    </div>
                                </div>
                                <div class="col mb-3"><strong>Price Details</strong>
                                    <div class="col mb-2">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="col-pricing_billable">
                                            <label class="form-check-label" for="col-pricing_billable">Pricing billable</label>
                                        </div>


                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="col-pricing_model">
                                            <label class="form-check-label" for="col-pricing_model">Pricing model</label>
                                        </div>


                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="col-pricing_category">
                                            <label class="form-check-label" for="col-pricing_category">Pricing category</label>
                                        </div>


                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="col-cost">
                                            <label class="form-check-label" for="col-cost">Cost</label>
                                        </div>
                                    </div>
                                </div>
                                <div class="col mb-3"><strong>Error Details</strong>
                                    <div class="col mb-2">

                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="col-errors_code">
                                            <label class="form-check-label" for="col-errors_code">Errors code</label>
                                        </div>


                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="col-errors_title">
                                            <label class="form-check-label" for="col-errors_title">Errors title</label>
                                        </div>


                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="col-errors_message" checked="">
                                            <label class="form-check-label" for="col-errors_message">Errors message</label>
                                        </div>


                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="col-error_data_details">
                                            <label class="form-check-label" for="col-error_data_details">Error data details</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Filters -->
                        <div class="row mb-4">
                            <div class="col-md-4">
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-search"></i></span>
                                    <input type="text" class="form-control no_border" id="searchInput"
                                        placeholder="Search messages, phone numbers, or users...">
                                </div>
                            </div>
                            <div class="col-md-2">
                                <select class="form-select no_border" id="statusFilter">
                                    <option value="">All Statuses</option>
                                    <option value="sent">Sent</option>
                                    <option value="delivered">Delivered</option>
                                    <option value="read">Read</option>
                                    <option value="failed">Failed</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <select class="form-select no_border" id="categoryFilter">
                                    <option value="">All Categories</option>
                                    <option value="marketing">Marketing</option>
                                    <option value="support">Support</option>
                                    <option value="notification">Notification</option>
                                    <option value="system">System</option>
                                </select>
                            </div>
                        </div>

                        <!-- Table -->
                        <div class="table-responsive" style="max-height: 450px; overflow: auto;">
                            <table id="messagesTable" class="table table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th data-col="user" class="fixed-col">User</th>
                                        <th data-col="phone" class="fixed-col">Phone</th>
                                        <th data-col="wa_id" class="fixed-col">WA ID</th>
                                        <th data-col="cohort" class="fixed-col">Cohort</th>
                                        <th data-col="message" class="fixed-col">Message</th>
                                        <th data-col="message_id" class="d-none fixed-col-wide">Message ID</th>
                                        <th data-col="status" class="fixed-col">Status</th>
                                        <th data-col="sent_at_display" class="d-none fixed-col-wide">Sent At (SGT)</th>
                                        <th data-col="sent" class="d-none fixed-col">Sent</th>
                                        <th data-col="failed" class="fixed-col">Failed</th>
                                        <th data-col="delivered" class="fixed-col">Delivered</th>
                                        <th data-col="read" class="d-none fixed-col">Read</th>
                                        <th data-col="conversation_id" class="d-none fixed-col-wide">Conversation ID</th>
                                        <th data-col="conversation_exp" class="d-none fixed-col">Conv. Exp.</th>
                                        <th data-col="conversation_origin" class="d-none fixed-col">Origin</th>
                                        <th data-col="pricing_billable" class="d-none fixed-col">Billable</th>
                                        <th data-col="pricing_model" class="d-none fixed-col">Model</th>
                                        <th data-col="pricing_category" class="d-none fixed-col">Category</th>
                                        <th data-col="cost" class="d-none fixed-col">Cost</th>
                                        <th data-col="errors_code" class="d-none fixed-col">Err Code</th>
                                        <th data-col="errors_title" class="d-none fixed-col-wide">Err Title</th>
                                        <th data-col="errors_message" class="expand-col">Err Message</th>
                                        <th data-col="error_data_details" class="d-none expand-col">Err Details</th>
                                        <th data-col="all_replies" class="fixed-col">Reply</th>
                                        <th data-col="latest_reply_time" class="d-none fixed-col-wide">Reply At</th>
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
                                            <td class="d-none fixed-col-wide"><?= htmlspecialchars($row['message_id']) ?></td>
                                            <td class="fixed-col">
                                                <?php if (!empty($row['failed'])): ?>
                                                    <span class="badge-outline badge-failed">Failed</span>
                                                <?php elseif (!empty($row['read'])): ?>
                                                    <span class="badge-outline badge-read">Read</span>
                                                <?php elseif (!empty($row['delivered'])): ?>
                                                    <span class="badge-outline badge-delivered">Delivered</span>
                                                <?php elseif (!empty($row['sent'])): ?>
                                                    <span class="badge-outline badge-sent">Sent</span>
                                                <?php else: ?>
                                                    <span class="badge-outline badge-pending">Pending</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="d-none fixed-col-wide"><?= $row['sent_at_display'] ?></td>
                                            <td class="d-none fixed-col"><?= $row['sent'] ? date('M d | g.i a', strtotime($row['sent'])) : '—' ?></td>
                                            <td class="fixed-col"><?= $row['failed'] ? date('M d | g.i a', strtotime($row['failed'])) : '—' ?></td>
                                            <td class="fixed-col"><?= $row['delivered'] ? date('M d | g.i a', strtotime($row['delivered'])) : '—' ?></td>
                                            <td class="d-none fixed-col"><?= $row['read'] ? date('M d | g.i a', strtotime($row['read'])) : '—' ?></td>
                                            <td class="d-none fixed-col-wide"><?= htmlspecialchars($row['conversation_id']) ?></td>
                                            <td class="d-none fixed-col"><?= $row['conversation_exp'] ? date('M d | g.i a', strtotime($row['conversation_exp'])) : '—' ?></td>
                                            <td class="d-none fixed-col"><?= htmlspecialchars($row['conversation_origin']) ?></td>
                                            <td class="d-none fixed-col"><?= is_null($row['pricing_billable']) ? '—' : ($row['pricing_billable'] ? 'Yes' : 'No') ?></td>
                                            <td class="d-none fixed-col"><?= htmlspecialchars($row['pricing_model']) ?></td>
                                            <td class="d-none fixed-col"><?= htmlspecialchars($row['pricing_category']) ?></td>
                                            <td class="d-none fixed-col"><?= htmlspecialchars($row['cost']) ?></td>
                                            <td class="d-none fixed-col"><?= htmlspecialchars($row['errors_code']) ?></td>
                                            <td class="d-none fixed-col-wide"><?= htmlspecialchars($row['errors_title']) ?></td>
                                            <td class="expand-col"><?= nl2br(htmlspecialchars($row['errors_message'])) ?></td>
                                            <td class="d-none expand-col"><?= nl2br(htmlspecialchars($row['error_data_details'])) ?></td>
                                            <td class="fixed-col">
                                                <?php if (!empty($row['all_replies']) && $row['status'] === 'success'): ?>
                                                    <span class="badge bg-success">✅Confirm</span>
                                                <?php else: ?>
                                                    <span class="text-muted">–</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="d-none fixed-col-wide">
                                                <?= !empty($row['latest_reply_time']) ? date('M d | g.i a', strtotime($row['latest_reply_time'])) : '—' ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                    </div>

                    <!-- Pagination -->
                    <div class="d-flex justify-content-between align-items-center mt-4">
                        <div class="text-muted small" id="paginationInfo">
                            <!-- Pagination info will be populated by JavaScript -->
                        </div>
                        <nav>
                            <ul class="pagination pagination-sm mb-0" id="pagination">
                                <!-- Pagination will be populated by JavaScript -->
                            </ul>
                        </nav>
                    </div>

                </div>
            </div>
        </div>
    </div>


    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="dashboardScript.js"></script>


</body>

</html>