<?php

require_once 'includes/db.php';

$types = ["Bst","Drg","Ele","Epc","Fly","Frt","Mel","Mtd","Rng","Sge"];


/* ======================
SAVE
====================== */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    foreach ($_POST['bonusID'] as $i => $id) {

        $monsterID = $_POST['monsterID'][$i];
        $percent   = $_POST['bonus_percent'][$i];
        $against   = $_POST['bonus_against'][$i];

        if ($id === 'new' && $percent > 0) {

            $stmt = $pdo->prepare("
                INSERT INTO monster_bonus
                (monsterID, bonus_percent, bonus_against)
                VALUES (?,?,?)
            ");

            $stmt->execute([$monsterID,$percent,$against]);

        } elseif ($id !== 'new') {

            $stmt = $pdo->prepare("
                UPDATE monster_bonus
                SET bonus_percent=?, bonus_against=?
                WHERE bonusID=?
            ");

            $stmt->execute([$percent,$against,$id]);
        }
    }
}


/* ======================
LOAD DATA
====================== */

$stmt = $pdo->query("
SELECT
    m.monsterID,
    m.name,
    m.type,
    m.level,
    b.bonusID,
    b.bonus_percent,
    b.bonus_against
FROM monster m
LEFT JOIN monster_bonus b
ON m.monsterID = b.monsterID
ORDER BY m.name
");

$data = $stmt->fetchAll(PDO::FETCH_ASSOC);


/* group bonuses */

$monsters = [];

foreach ($data as $row) {

    $mid = $row['monsterID'];

    if (!isset($monsters[$mid])) {

        $monsters[$mid] = [
            'info'=>$row,
            'bonuses'=>[]
        ];
    }

    if ($row['bonusID']) {
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

td,th{
padding:6px;
border-bottom:1px solid #333;
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

</style>

</head>

<body>

<h2>Monster Bonus Editor</h2>

<form method="post">

<table>

<thead>
<tr>
<th>Name</th>
<th>Type</th>
<th>Level</th>
<th>Bonuses</th>
</tr>
</thead>

<tbody>

<?php foreach ($monsters as $m): ?>

<tr>

<td><?= $m['info']['name'] ?></td>
<td><?= $m['info']['type'] ?></td>
<td><?= $m['info']['level'] ?></td>

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
<input type="hidden" name="monsterID[]" value="<?= $m['info']['monsterID'] ?>">

<input name="bonus_percent[]" value="<?= $pct ?>">

<select name="bonus_against[]">
<option></option>

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

</tbody>

</table>

<button type="submit">Save Bonuses</button>

</form>

</body>
</html>