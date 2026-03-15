<?php
require_once 'db.php';

$stmt = $pdo->prepare("
  INSERT INTO member_votes (member_id, fair_player, support_others, mead_with_player, notes)
  VALUES (:member_id, :fair_player, :support_others, :mead_with_player, :notes)
");

$stmt->execute([
  'member_id' => $_POST['member_id'],
  'fair_player' => $_POST['fair_player'],
  'support_others' => $_POST['support_others'],
  'mead_with_player' => $_POST['mead_with_player'],
  'notes' => $_POST['notes'] ?? null
]);

echo "Vote submitted successfully!";
