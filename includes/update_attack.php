<?php

require 'db.php';

    $data=json_decode(file_get_contents("php://input"),true);

    $id      = $data['id'] ?? null;
    $squadID = $data['squadID'] ?? null;
    $level   = $data['level'] ?? null;
    $troops  = $data['troops'] ?? null;
    $loss  = $data['loss'] ?? null;

    $stmt=$pdo->prepare("
        UPDATE squad_attack
        SET
        squadID=?,
        Level=?,
        qty=?,
        loss=?
        WHERE squadAttackID=?
        ");

    $stmt->execute([
        $squadID,
        $level,
        $troops,
        $id
    ]);

echo "OK";