<?php
date_default_timezone_set('Asia/Singapore');

session_start();
require '../db.php';

require __DIR__ . '/../vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

if (!isset($_SESSION['user_id'])) exit("Unauthorized");

$userId = $_SESSION['user_id'];

$sql = "
    SELECT DISTINCT m.wa_id, m.conversation_id, m.pricing_category
    FROM messages m
    WHERE m.cost IS NULL AND m.wa_id IS NOT NULL AND m.conversation_id IS NOT NULL
";
$result = $conn->query($sql);

if (!$result || $result->num_rows === 0) {
    // echo "No uncalculated records found.\n";
    header("Location: ../webhook.php");
    exit;
}

while ($row = $result->fetch_assoc()) {
    $wa_id = $row['wa_id'];
    $conversation_id = $row['conversation_id'];
    $category = strtolower($row['pricing_category'] ?? 'marketing');
    $country_code = null;
    $region = null;

    // Step 1: Detect country code (try 3 → 2 → 1 digits)
    for ($i = 3; $i >= 1; $i--) {
        $prefix = substr($wa_id, 0, $i);
        $stmt = $conn->prepare("SELECT region FROM country_code_map WHERE country_code = ? LIMIT 1");
        $stmt->bind_param("s", $prefix);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res && $res->num_rows > 0) {
            $regionRow = $res->fetch_assoc();
            $region = $regionRow['region'];
            break;
        }
    }

    // Step 2: Fallback to 'Other' region if not found
    if (!$region) {
        $region = 'Other';
    }

    // Step 3: Get cost based on region + category
    $stmt2 = $conn->prepare("SELECT marketing, utility, authentication FROM meta_msg_cost WHERE region = ? LIMIT 1");
    $stmt2->bind_param("s", $region);
    $stmt2->execute();
    $res2 = $stmt2->get_result();
    $pricing = $res2->fetch_assoc();
    if (!$pricing) {
        echo "⚠️ No pricing found for region: $region\n";
        continue;
    }

    $cost = $pricing[$category] ?? $pricing['marketing'] ?? 0.0;
    $now = date('Y-m-d H:i:s');

    // Step 4: Update all matching messages with cost and timestamp
    $stmt3 = $conn->prepare("UPDATE messages SET cost = ?, cost_calculated_at = ? WHERE wa_id = ? AND conversation_id = ?");
    $stmt3->bind_param("dsss", $cost, $now, $wa_id, $conversation_id);
    $stmt3->execute();

    // echo "✅ Updated $wa_id / $conversation_id → $region → $category = $cost\n";
}

// echo "🎯 All applicable message costs calculated.\n";
header("Location: ../webhook.php");
exit;
