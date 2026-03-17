<?php 
include 'includes/header.php';
require_once 'includes/db.php';

/* -----------------------------
   Load Game Profiles
------------------------------*/

$stmt = $pdo->query("
SELECT mg.gameID, mg.gameName, m.memberName
FROM member_games mg
JOIN members m ON m.memberID = mg.memberID
ORDER BY m.memberName
");

$games = $stmt->fetchAll(PDO::FETCH_ASSOC);

$gameID = $_GET['gameID'] ?? null;

/* -----------------------------
   Load Unit Access Matrix
------------------------------*/

$unitAccess = [];

if($gameID){

$stmt = $pdo->prepare("
SELECT unitType, unitLevel
FROM member_unit_access
WHERE gameID = ?
ORDER BY unitType, unitLevel
");

$stmt->execute([$gameID]);

$unitAccess = $stmt->fetchAll(PDO::FETCH_ASSOC);

}

/* -----------------------------
   Load Saved Kill Sheets
------------------------------*/

$killSheets = [];

if($gameID){

$stmt = $pdo->prepare("
SELECT sa.squadAttackID, ms.name squadName
FROM squad_attack sa
JOIN Monster_Squad ms ON ms.squadID = sa.squadID
WHERE sa.gameID = ?
ORDER BY sa.squadAttackID DESC
");

$stmt->execute([$gameID]);

$killSheets = $stmt->fetchAll(PDO::FETCH_ASSOC);

}

/* -----------------------------
   Load Units Used in Attack
------------------------------*/

$attackUnits = [];

if(isset($_GET['attackID'])){

$stmt = $pdo->prepare("
SELECT unitName, unitLevel, quantity
FROM squad_attack_units
WHERE squadAttackID = ?
");

$stmt->execute([$_GET['attackID']]);

$attackUnits = $stmt->fetchAll(PDO::FETCH_ASSOC);

}

/* -----------------------------
   Create New Kill Sheet
------------------------------*/

if(isset($_POST['createAttack'])){

$stmt = $pdo->prepare("
INSERT INTO squad_attack (gameID, squadID)
VALUES (?,?)
");

$stmt->execute([
$_POST['gameID'],
$_POST['squadID']
]);

$attackID = $pdo->lastInsertId();

/* sample unit */

$stmt = $pdo->prepare("
INSERT INTO squad_attack_units
(squadAttackID, unitName, unitLevel, quantity)
VALUES (?,?,?,?)
");

$stmt->execute([
$attackID,
$_POST['unitName'],
$_POST['unitLevel'],
$_POST['quantity']
]);

header("Location: member_dashboard.php?gameID=".$_POST['gameID']);
exit;

}

?>

<!DOCTYPE html>
<html>
    <head>
    <title>Member Dashboard</title>

        <style>

        body{
        font-family:Arial;
        margin:40px;
        }

        .card{
        border:1px solid #ccc;
        padding:15px;
        margin-bottom:20px;
        }

        .row{
        display:flex;
        gap:20px;
        }

        .col{
        flex:1;
        }

        table{
        border-collapse:collapse;
        width:100%;
        }

        td,th{
        border:1px solid #ccc;
        padding:6px;
        text-align:center;
        }

        </style>

    </head>
    <body>

    <div class="card">
        <h3>Dashboard Sections</h3>
        <label>
            <input type="radio" name="view" checked onclick="showCard('units')">
            Unit Access
        </label>
        <label>
            <input type="radio" name="view" onclick="showCard('kills')">
            Kill Sheets
        </label>
        <label>
            <input type="radio" name="view" onclick="showCard('attack')">
            Attack Units
        </label>
        <label>
            <input type="radio" name="view" onclick="showCard('create')">
            Create Attack
        </label>
    </div>

    <h1>Member Dashboard</h1>
    <div class="card">
        <h3>Select Player</h3>
        <form>
            <select name="gameID" onchange="this.form.submit()">
                <option value="">Select Player</option>
                <?php foreach($games as $g): ?>
                <option value="<?= $g['gameID'] ?>"
                <?= ($gameID==$g['gameID'])?'selected':'' ?>>
                <?= htmlspecialchars($g['memberName']) ?>
                - <?= htmlspecialchars($g['gameName']) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>
    <?php if($gameID): ?>
    <div class="row">
    <!-- UNIT ACCESS -->
    <div class="card col">
        <h3>Unit Access</h3>
        <table>
            <tr>
                <th>Type</th>
                <th>Level</th>
            </tr>
            <?php foreach($unitAccess as $u): ?>
            <tr>
                <td><?= $u['unitType'] ?></td>
                <td><?= $u['unitLevel'] ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>
    <!-- SAVED ATTACKS -->
    <div class="card col">
        <h3>Saved Kill Sheets</h3>
        <ul>
            <?php foreach($killSheets as $k): ?>
                <li>
                    <a href="?gameID=<?= $gameID ?>&attackID=<?= $k['squadAttackID'] ?>">
                    <?= $k['squadName'] ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
    </div>
    <!-- ATTACK DETAILS -->
    <?php if($attackUnits): ?>
    <div class="card">
        <h3>Attack Units</h3>
        <table>
            <tr>
                <th>Unit</th>
                <th>Level</th>
                <th>Quantity</th>
            </tr>
            <?php foreach($attackUnits as $u): ?>
            <tr>
                <td><?= $u['unitName'] ?></td>
                <td><?= $u['unitLevel'] ?></td>
                <td><?= $u['quantity'] ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>
    <?php endif; ?>
    <!-- CREATE ATTACK -->
    <div class="card">
        <h3>Create Attack</h3>
        <form method="POST">
            <input type="hidden" name="gameID" value="<?= $gameID ?>">
            <label>Squad ID</label>
                <input type="number" name="squadID">
            <br><br>
            <label>Unit</label>
                <input name="unitName">
            <label>Level</label>
                <input type="number" name="unitLevel">
            <label>Qty</label>
                <input type="number" name="quantity">
            <br><br>
                <button name="createAttack">Save Attack</button>
        </form>
    </div>
    <?php endif; ?>
    </body>
</html>