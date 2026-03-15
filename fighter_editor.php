<?php

require_once 'includes/db.php'; // must define $pdo

/* -----------------------------
Save Updates
------------------------------*/

if(isset($_POST['save'])){

    foreach($_POST['fighterID'] as $i=>$id){

        $sql="
        UPDATE fighter SET
        name=?,
        type=?,
        unit=?,
        level=?,
        strength=?,
        health=?,
        health_bonus=?,
        strength_bonus=?
        WHERE fighterID=?
        ";

        $stmt=$pdo->prepare($sql);

        $stmt->execute([
            $_POST['name'][$i],
            $_POST['type'][$i],
            $_POST['unit'][$i],
            $_POST['level'][$i],
            $_POST['strength'][$i],
            $_POST['health'][$i],
            $_POST['health_bonus'][$i],
            $_POST['strength_bonus'][$i],
            $id
        ]);
    }
}

/* -----------------------------
Add New Fighter
------------------------------*/

if(isset($_POST['add'])){

$stmt=$pdo->prepare("
INSERT INTO fighter
(name,type,unit,level,strength,health,health_bonus,strength_bonus)
VALUES (?,?,?,?,?,?,?,?)
");

$stmt->execute([
$_POST['new_name'],
$_POST['new_type'],
$_POST['new_unit'],
$_POST['new_level'],
$_POST['new_strength'],
$_POST['new_health'],
$_POST['new_health_bonus'],
$_POST['new_strength_bonus']
]);

}

/* -----------------------------
Load Fighters
------------------------------*/

$stmt=$pdo->query("
SELECT *
FROM fighter
ORDER BY level,name
");

$fighters=$stmt->fetchAll(PDO::FETCH_ASSOC);


$sort = $_GET['sort'] ?? 'level';
$dir  = $_GET['dir'] ?? 'ASC';

$allowed = ['fighterID','name','type','unit','level','strength','health'];

if(!in_array($sort,$allowed)) $sort='level';

$dir = strtoupper($dir)=='DESC' ? 'DESC' : 'ASC';

$stmt=$pdo->query("
SELECT *
FROM fighter
ORDER BY $sort $dir, name
");

$fighters=$stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html>
<head>

<title>Fighter Editor</title>

<style>

body{
font-family:arial;
background:#111;
color:#ddd;
}

table{
border-collapse:collapse;
width:100%;
}

th,td{
border:1px solid #444;
padding:4px;
}

input{
width:100%;
background:#222;
color:#fff;
border:1px solid #555;
}

button{
padding:6px 12px;
margin-top:10px;
}

</style>

</head>

<body>

<h2>Fighter Editor (Bulk)</h2>

<form method="post">

<table>

    <tr>

        <th><a href="?sort=fighterID">ID</a></th>
        <th><a href="?sort=name">Name</a></th>
        <th><a href="?sort=type">Type</a></th>
        <th><a href="?sort=unit">Unit</a></th>
        <th><a href="?sort=level">Level</a></th>
        <th><a href="?sort=strength">Strength</a></th>
        <th><a href="?sort=health">Health</a></th>

    </tr>

<?php foreach($fighters as $i=>$f){ ?>

<tr>
    <td>
        <?= $f['fighterID'] ?>
        <input type="hidden" name="fighterID[]" value="<?= $f['fighterID'] ?>">
    </td>

    <td>
        <input name="name[]" value="<?= $f['name'] ?>">
    </td>

    <td>
        <select name="type[]">
        <option value="Mel" <?= $f['type']=='Mel'?'selected':'' ?>>Mel</option>
        <option value="Mtd" <?= $f['type']=='Mtd'?'selected':'' ?>>Mtd</option>
        <option value="Rng" <?= $f['type']=='Rng'?'selected':'' ?>>Rng</option>
        <option value="Fly" <?= $f['type']=='Fly'?'selected':'' ?>>Fly</option>
        <option value="Spy" <?= $f['type']=='Spy'?'selected':'' ?>>Spy</option>
        </select>
    </td>

    <td>
        <select name="unit[]">
        <option value="Reg" <?= $f['unit']=='Reg'?'selected':'' ?>>Reg</option>
        <option value="Spc" <?= $f['unit']=='Spc'?'selected':'' ?>>Spc</option>
        </select>
    </td>
    <td>
        <input name="level[]" value="<?= $f['level'] ?>">
    </td>

    <td>
        <input name="strength[]" value="<?= $f['strength'] ?>">
    </td>

    <td>
        <input name="health[]" value="<?= $f['health'] ?>">
    </td>

    <td>
        <input name="health_bonus[]" value="<?= $f['health_bonus'] ?>">
    </td>

    <td>
        <input name="strength_bonus[]" value="<?= $f['strength_bonus'] ?>">
    </td>

</tr>

<?php } ?>

</table>

<br>

<button type="submit" name="save">Save All Changes</button>

</form>

<hr>

<h3>Add New Fighter</h3>

<form method="post">

<table>

<tr>
<td>Name</td>
<td><input name="new_name"></td>
</tr>

<tr>
<td>Type</td>
<td><input name="new_type"></td>
</tr>

<tr>
<td>Unit</td>
<td><input name="new_unit"></td>
</tr>

<tr>
<td>Level</td>
<td><input name="new_level"></td>
</tr>

<tr>
<td>Strength</td>
<td><input name="new_strength"></td>
</tr>

<tr>
<td>Health</td>
<td><input name="new_health"></td>
</tr>

<tr>
<td>Health Bonus</td>
<td><input name="new_health_bonus" value="0"></td>
</tr>

<tr>
<td>Strength Bonus</td>
<td><input name="new_strength_bonus" value="0"></td>
</tr>

</table>

<br>

<button type="submit" name="add">Add Fighter</button>

</form>

</body>
</html>