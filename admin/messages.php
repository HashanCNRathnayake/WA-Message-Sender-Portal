<?php
session_start();
require '../db.php';

require __DIR__ . '/../vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();
$baseUrl = $_ENV['BASE_URL'] ?? '/';

if ($_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit;
}
$sql = "SELECT m.phone, m.message, m.sent_at, m.sent_at_display, m.status, u.username
        FROM messages m
        JOIN users u ON m.user_id = u.id
        ORDER BY m.sent_at DESC";

$res = $conn->query($sql);
?>
<!DOCTYPE html>
<html>

<head>
    <title>All Message Logs</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="bg-light">

    <div class="container">
        <?php include '../components/navbar.php'; ?>

        <!-- <a href="../index.php" class="btn btn-secondary my-3">← Back</a> -->

        <h4 class="mb-3">All Message Logs</h4>
        <?php if ($res->num_rows > 0): ?>

            <table class="table table-bordered table-striped table-hover">
                <thead class="table-dark">
                    <tr>
                        <th>User</th>
                        <th>Phone</th>
                        <th>Message</th>
                        <th>Time (SGT)</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $res->fetch_assoc()): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['username']) ?></td>
                            <td><?= htmlspecialchars($row['phone']) ?></td>
                            <td><?= nl2br(htmlspecialchars($row['message'])) ?></td>
                            <td><?= $row['sent_at_display'] ?></td>
                            <td>
                                <?php if ($row['status'] === 'success'): ?>
                                    <span class="badge bg-success">✅ Success</span>
                                <?php else: ?>
                                    <span class="badge bg-danger">❌ Failed</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="alert alert-warning text-center">No logs found yet.</div>
        <?php endif; ?>

    </div>

</body>

</html>