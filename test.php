<?php

require 'includes/db.php';
/*
if($_SERVER['REQUEST_METHOD'] === 'GET'){
echo "Update endpoint ready";
exit;
}
*/
/* read json */

$raw = file_get_contents("php://input");
$data = json_decode($raw,true);

if(!$data){
echo "Invalid request";
exit;
}

$id    = $data['id'] ?? null;
$level = $data['level'] ?? null;

$stmt = $pdo->prepare("
UPDATE squad_attack
SET squadLevel=?
WHERE squadAttackID=?
");

$stmt->execute([$level,$id]);

echo "OK";