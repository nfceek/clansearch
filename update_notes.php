<?php
// Database connection
require_once 'db.php';

$table = $_POST['table'] ?? '';
$id    = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$notes = $_POST['notes'] ?? '';

if ($table !== 'Clans' || $id <= 0) {
    http_response_code(400);
    echo 'Invalid request';
    exit;
}

$stmt = $pdo->prepare("UPDATE Clans SET notes = :notes WHERE id = :id");
$stmt->execute(['notes' => $notes, 'id' => $id]);
echo 'OK';
?>

