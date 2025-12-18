<?php
session_start();
require 'db.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

$lastId = isset($_GET['last_id']) ? intval($_GET['last_id']) : 0;

// Fetch new emails received after last checked ID
$stmt = $conn->prepare("
    SELECT m.id, m.subject, m.message, m.received_at, t.email as to_email, t.id as email_id
    FROM messages m
    JOIN temp_emails t ON m.email_id = t.id
    WHERE m.id > ?
    ORDER BY m.id ASC
    LIMIT 10
");

$stmt->bind_param("i", $lastId);
$stmt->execute();
$result = $stmt->get_result();

$emails = [];
while ($row = $result->fetch_assoc()) {
    $emails[] = [
        'id' => $row['id'],
        'email_id' => $row['email_id'],
        'to_email' => $row['to_email'],
        'subject' => $row['subject'],
        'message' => substr($row['message'], 0, 200), // Limit message length
        'received_at' => $row['received_at']
    ];
}

echo json_encode([
    'success' => true,
    'emails' => $emails
]);