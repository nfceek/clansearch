<?php
require_once 'db.php';

$stmt = $pdo->prepare("
  INSERT INTO bounties (
    posting_member, bounty_amount, bounty_member, reason, start_date, end_date, notes
  ) VALUES (
    :posting_member, :bounty_amount, :bounty_member, :reason, :start_date, :end_date, :notes
  )
");

$stmt->execute([
  'posting_member' => $_POST['posting_member'] ?? null,
  'bounty_amount' => $_POST['bounty_amount'] ?? 0,
  'bounty_member' => $_POST['bounty_member'],
  'reason' => $_POST['reason'] ?? null,
  'start_date' => $_POST['start_date'],
  'end_date' => $_POST['end_date'] ?? null,
  'notes' => $_POST['notes'] ?? null
]);

echo "Bounty posted successfully!";
