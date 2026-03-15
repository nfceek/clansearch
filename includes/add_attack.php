<?php

require '../includes/db.php';

$data=json_decode(file_get_contents("php://input"),true);

$type=$data['rarity'] ?? 'Common';

/* create blank record */

$stmt=$pdo->prepare("
INSERT INTO squad_attack
(squadType,Level)
VALUES (?,1)
");

$stmt->execute([$type]);

$id=$pdo->lastInsertId();

echo json_encode([
"id"=>$id
]);