<?php
require '../db.php';
if ($_SESSION['role'] !== 'admin') exit('Access denied');

$id = (int)$_GET['id'];
$conn->query("DELETE FROM users WHERE id=$id");
$conn->query("DELETE FROM messages WHERE user_id=$id"); // Clean up
header("Location: ../admin/users.php");
