<?php

require_once 'includes/db.php'; // must define $pdo

$types = ["Bst","Drg","Ele","Epc","Fly","Frt","Mel","Mtd","Rng","Sge"];


/* ================================
   SAVE DATA
================================ */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    /* SAVE MONSTERS */

    if (!empty($_POST['monsterID'])) {

        foreach ($_POST['monsterID'] as $i => $id) {

            $name     = $_POST['name'][$i] ?? '';
            $type     = $_POST['type'][$i] ?? '';
            $level    = $_POST['level'][$i] ?? 0;            
            $strength = $_POST['strength'][$i] ?? 0;
            $health   = $_POST['health'][$i] ?? 0;

            if ($id === 'new' && $name !== '') {

                $stmt = $pdo->prepare("
                    INSERT INTO monster
                    (name,type,level,strength,health)
                    VALUES (?,?,?,?,?)
                ");

                $stmt->execute([
                    $name,
                    $type,
                    $level,
                    $strength,
                    $health
                ]);
            }

            elseif ($id !== 'new') {

                $stmt = $pdo->prepare("
                    UPDATE monster
                    SET
                        name=?,
                        type=?,
                        level=?,
                        strength=?,
                        health=?
                    WHERE monsterID=?
                ");

                $stmt->execute([
                    $name,
                    $type,
                    $level,
                    $strength,
                    $health,
                    $id
                ]);
            }
        }
    }


    /* SAVE BONUSES */

    if (!empty($_POST['bonusID'])) {

        foreach ($_POST['bonusID'] as $i => $bid) {

            $monsterID = $_POST['bonus_monsterID'][$i] ?? 0;
            $percent   = $_POST['bonus_percent'][$i] ?? 0;
            $against   = $_POST['bonus_against'][$i] ?? '';

            if ($bid === 'new' && $percent > 0) {

                $stmt = $pdo->prepare("
                    INSERT INTO monster_bonus
                    (monsterID,bonus_percent,bonus_against)
                    VALUES (?,?,?)
                ");

                $stmt->execute([
                    $monsterID,
                    $percent,
                    $against
                ]);
            }

            elseif ($bid !== 'new') {

                $stmt = $pdo->prepare("
                    UPDATE monster_bonus
                    SET
                        bonus_percent=?,
                        bonus_against=?
                    WHERE bonusID=?
                ");

                $stmt->execute([
                    $percent,
                    $against,
                    $bid
                ]);
            }
        }
    }
}



/* ================================
   LOAD DATA
================================ */

$stmt = $pdo->query("
SELECT
m.monsterID,
m.name,
m.type,
m.level,
m.strength,
m.health,
b.bonusID,
b.bonus_percent,
b.bonus_against
FROM monster m
LEFT JOIN monster_bonus b
ON m.monsterID = b.monsterID
ORDER BY m.name
");

$data = $stmt->fetchAll(PDO::FETCH_ASSOC);


/* GROUP BONUSES PER MONSTER */

$monsters = [];

foreach ($data as $row) {

    $mid = $row['monsterID'];

    if (!isset($monsters[$mid])) {

        $monsters[$mid] = [
            'info' => $row,
            'bonuses' => []
        ];
    }

    if (!empty($row['bonusID'])) {
        $monsters[$mid]['bonuses'][] = $row;
    }
}

?>
<!DOCTYPE html>
<html>
    <head>
        <style>
            body{
                font-family:Arial;
                background:#111;
                color:#eee;
            }
            table{
                width:100%;
                border-collapse:collapse;
            }
            /* top title + save button */
            .topbar{
                position:sticky;
                top:0;
                background:#000;
                z-index:10;
                padding-bottom:8px;
                border-bottom:1px solid #ccc;
                padding-top:12px;
            }
            /* sticky table header */
            thead th{
                position:sticky;
                background:#000;                
                top:75px;
                z-index:5;
            }
            td{
                padding:6px;
                border-bottom:1px solid #333;
                vertical-align:top;
            }
            input,select{
                width:100%;
                background:#222;
                color:#fff;
                border:1px solid #444;
                padding:4px;
            }
            .bonus-grid{
                display:grid;
                grid-template-columns:80px 1fr 80px 1fr;
                gap:6px;
            }
            button{
                padding:10px 20px;
                margin:10px 0;
                background:#444;
                color:#fff;
                border:none;
                cursor:pointer;
            }
            .col-small{
                width:80px;
            }
            .col-med{
                width:50px;
            }            
            .col-name{
                width:220px;
            }
        </style>
    </head>
    <body>
        <div class="topbar">
            <form method="post">
            <button type="submit">Save</button>
        </div>    
        <table>
            <thead>
                <tr>
                    <th>Name</th>
                    <th class="col-small">Type</th>
                    <th class="col-small">Level</th>
                    <th class="col-med">Strength</th>
                    <th class="col-med">Health</th>
                    <th>Bonuses</th>
                </tr>
            </thead>

        <tbody>
        <?php foreach ($monsters as $m): ?>
        <tr>
            <td>
                <input type="hidden" name="monsterID[]" value="<?= $m['info']['monsterID'] ?>">
                <input name="name[]" value="<?= htmlspecialchars($m['info']['name']) ?>">
                </td>
                <td>
                <select name="type[]">
                <?php
                foreach ($types as $t){
                $sel = ($m['info']['type'] === $t) ? 'selected' : '';
                echo "<option value=\"$t\" $sel>$t</option>";
                }
                ?>
                </select>
            </td>
            <td>
                <input name="level[]" value="<?= $m['info']['level'] ?>">
            </td>
            <td>
                <input name="strength[]" value="<?= $m['info']['strength'] ?>">
            </td>
            <td>
                <input name="health[]" value="<?= $m['info']['health'] ?>">
            </td>
            <td>
                <div class="bonus-grid">
                    <?php
                    $slots = 4;
                    $bonuses = $m['bonuses'];
                    for ($i=0;$i<$slots;$i++) {
                    $bonus = $bonuses[$i] ?? null;
                    $bid = $bonus['bonusID'] ?? 'new';
                    $pct = $bonus['bonus_percent'] ?? '';
                    $agt = $bonus['bonus_against'] ?? '';
                    ?>
                    <input type="hidden" name="bonusID[]" value="<?= $bid ?>">
                    <input type="hidden" name="bonus_monsterID[]" value="<?= $m['info']['monsterID'] ?>">
                    <input name="bonus_percent[]" value="<?= $pct ?>">
                    <select name="bonus_against[]">
                    <option value=""></option>
                    <?php
                    foreach ($types as $t){
                    $sel = ($agt === $t) ? 'selected' : '';
                    echo "<option value=\"$t\" $sel>$t</option>";
                    }
                    ?>
                    </select>
                    <?php } ?>
                </div>
            </td>
        </tr>
        <?php endforeach; ?>
        <!-- NEW MONSTER ROW -->
        <tr>
            <td>
                <input type="hidden" name="monsterID[]" value="new">
                <input name="name[]">
            </td>
            <td>
                <select name="type[]">
                    <?php
                    foreach ($types as $t){
                    echo "<option value=\"$t\">$t</option>";
                    }
                    ?>
                </select>
            </td>
                <input type="number" name="level[]">
                <input type="number" name="strength[]">
                <input type="number" name="health[]">
            <td></td>
        </tr>
        </tbody>
        </table>
        <button type="submit">Save</button>
        </form>
    </body>
</html>
