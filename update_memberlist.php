<?php
require_once 'db.php';

$id = $_POST['id'] ?? null;
$memberList = isset($_POST['memberList']) ? (int)$_POST['memberList'] : null;

if (!$id || $memberList === null) {
    http_response_code(400);
    exit('Invalid input');
}

$stmt = $pdo->prepare("UPDATE clans SET memberList = :memberList WHERE id = :id");
$stmt->execute(['memberList' => $memberList, 'id' => $id]);

echo "Updated clan #$id memberList to " . ($memberList ? 'Yes' : 'No');
