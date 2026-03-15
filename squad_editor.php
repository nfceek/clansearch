<?php
require_once 'includes/db.php';

/* ---------- SAVE SQUADS ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {
    foreach ($_POST['squadID'] as $squadID) {
        $squadID = intval($squadID);

        $monsterIDs = $_POST['monsterID'][$squadID];
        $quantities = $_POST['quantity'][$squadID];

        /* remove existing squad composition */
        $stmt = $pdo->prepare("DELETE FROM squad_monster WHERE squadID = ?");
        $stmt->execute([$squadID]);

        /* insert slots */
        for ($i=0;$i<4;$i++) {
            $mID = intval($monsterIDs[$i] ?? 0);
            $qty = intval($quantities[$i] ?? 0);
            if ($mID > 0 && $qty > 0) {
                $stmt = $pdo->prepare("
                    INSERT INTO squad_monster
                    (squadID, slot, monsterID, quantity)
                    VALUES (?,?,?,?)
                ");
                $stmt->execute([
                    $squadID,
                    $i + 1,
                    $mID,
                    $qty
                ]);
            }
        }
    }
}

/* ---------- LOAD MONSTERS FOR DROPDOWN ---------- */
$monsters = $pdo->query("
    SELECT monsterID, Name
    FROM monster
    ORDER BY Name
")->fetchAll(PDO::FETCH_ASSOC);



/* ---------- LOAD SQUADS ---------- */

$squads = $pdo->query("
SELECT
    ms.squadID,
    ms.name AS squadName,
    ms.level,
    ms.rarity,
    sm.slot,
    sm.monsterID,
    sm.quantity
FROM monster_squad ms
LEFT JOIN squad_monster sm
    ON ms.squadID = sm.squadID
ORDER BY ms.rarity, ms.name, ms.level, sm.slot
")->fetchAll(PDO::FETCH_ASSOC);



/* ---------- FORMAT INTO SLOT STRUCTURE ---------- */

$squadData = [];

foreach ($squads as $row) {

    $sid = $row['squadID'];

    if (!isset($squadData[$sid])) {

        $squadData[$sid] = [
            'rarity'=>$row['rarity'],
            'name'  => $row['squadName'],
            'level' => $row['level'],
            'slots' => [
                ['monsterID'=>'','quantity'=>''],
                ['monsterID'=>'','quantity'=>''],
                ['monsterID'=>'','quantity'=>''],
                ['monsterID'=>'','quantity'=>'']
            ]
        ];
    }

    if ($row['slot']) {

        $slotIndex = intval($row['slot']) - 1;

        if ($slotIndex >= 0 && $slotIndex < 4) {

            $squadData[$sid]['slots'][$slotIndex] = [
                'monsterID'=>$row['monsterID'],
                'quantity'=>$row['quantity']
            ];
        }
    }
}

?>

<!DOCTYPE html>
<html>
    <head>
        <style>
            body{
                font-family:Arial;
                margin:20px;
            }
            table{
                border-collapse:collapse;
                width:100%;
            }
            th,td{
                border:1px solid #ccc;
                padding:6px;
                text-align:left;
            }
            /* top title + save button */
            .topbar{
                position:sticky;
                top:0;
                background:white;
                z-index:10;
                padding-bottom:8px;
                border-bottom:1px solid #ccc;
            }
            /* sticky table header */
            thead th{
                position:sticky;
                top:85px;
                background:#eee;
                z-index:5;
            }
            select{
                width:160px;
            }
            input{
                width:90px;
            }
            .savebar{
                margin:12px 0;
            }
        </style>
    </head>
    <body>
        <div class="topbar">
            <h2>Squad Editor</h2>
            <form method="post">
                <div class="savebar">
                    <button type="submit" name="save">Save Squads</button>
                </div>
        </div>
        <table>
            <thead>
                <tr>
                    <th>Squad</th>
                    <th>Monster 1</th>
                    <th>Qty</th>
                    <th>Monster 2</th>
                    <th>Qty</th>
                    <th>Monster 3</th>
                    <th>Qty</th>
                    <th>Monster 4</th>
                    <th>Qty</th>
                </tr>
                </thead>
            <tbody>
            <?php foreach ($squadData as $sid => $data): ?>
            <tr>
                <td>
                    <?= $data['rarity'] ?> <?= htmlspecialchars($data['name']) ?> (Lvl <?= $data['level'] ?>)
                    <input type="hidden" name="squadID[]" value="<?= $sid ?>">
                    </td>
                    <?php for ($i=0;$i<4;$i++): ?>
                    <td>
                    <select name="monsterID[<?= $sid ?>][]">
                    <option value="">--</option>
                    <?php foreach ($monsters as $m): ?>
                    <option value="<?= $m['monsterID'] ?>"
                    <?= ($data['slots'][$i]['monsterID']==$m['monsterID'])?'selected':'' ?>>
                    <?= htmlspecialchars($m['Name']) ?>
                    </option>
                    <?php endforeach; ?>
                    </select>
                </td>
                <td>
                    <input
                    type="number"
                    name="quantity[<?= $sid ?>][]"
                    value="<?= $data['slots'][$i]['quantity'] ?>"
                    min="0">

                </td>
                <?php endfor; ?>
            </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="savebar">
            <button type="submit" name="save">Save Squads</button>
        </div>

        </form>

    </body>
</html>