<?php 
include 'includes/header.php';

/* -----------------------------
   Helpers
------------------------------*/
function fetchAll($pdo, $sql, $params = []) {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/* -----------------------------
   Inputs
------------------------------*/
$playerLevel = isset($_GET['playerLevel']) ? (int)$_GET['playerLevel'] : 6;
$selectedSquad = $_GET['squadID'] ?? null;
$buildPlan = true;
$useCreatures = true;

/* -----------------------------
   Fetch Squad + Monsters
------------------------------*/
$monsters = [];
if ($selectedSquad) {
    $monsters = fetchAll($pdo, "
        SELECT m.monsterID, m.name, m.type, m.health, m.strength
        FROM Squad_Monster sm
        JOIN Monster m ON m.monsterID = sm.monsterID
        WHERE sm.squadID = ?
    ", [$selectedSquad]);
}

// Enemy type = type of first monster in squad
$enemyType = $monsters[0]['type'] ?? null;

/* -----------------------------
   Fetch Creatures
------------------------------*/
$creatures = [];
if($buildPlan && $useCreatures){
    $creatures = fetchAll($pdo, "
        SELECT 
            c.creatureID,
            c.name,
            c.type,
            c.level,
            c.strength,
            c.health,
            c.imgpath,
            JSON_OBJECTAGG(cb.bonus_against, cb.bonus_percent) AS bonuses
        FROM creature c
        LEFT JOIN creature_bonus cb ON cb.creatureID = c.creatureID
        WHERE c.level = ?
        GROUP BY c.creatureID
        ORDER BY c.strength DESC
        LIMIT 5
    ", [$playerLevel]);

    // decode bonuses
    foreach ($creatures as &$c) {
        $c['bonuses'] = $c['bonuses'] ? json_decode($c['bonuses'], true) : [];
    }
    unset($c);
}

/* -----------------------------
   Build Attack Groups
------------------------------*/
function buildAttackGroups($creatures, $enemyType = null, $maxGroups = 2) {
    foreach ($creatures as &$c) {
        $best = 0;
        $match = 0;
        foreach ($c['bonuses'] as $type => $val) {
            if ($val > $best) $best = $val;
            if ($enemyType && strtolower($type) === strtolower($enemyType)) $match = $val;
        }
        $final = $match ?: $best;
        $c['score'] = ($c['strength'] ?? 100) * (1 + $final / 100);
    }
    unset($c);

    usort($creatures, fn($a,$b) => $b['score'] <=> $a['score']);

    $groups = [];
    foreach ($creatures as $c) {
        $groups[] = [$c];
        if (count($groups) >= $maxGroups) break;
    }
    return $groups;
}

$attackGroups = buildAttackGroups($creatures, $enemyType, 2);

/* -----------------------------
   Total Units
------------------------------*/
$totalUnits = 0;
foreach ($attackGroups as $g) $totalUnits += count($g);

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Creature Attack Test</title>
    <style>
        .card { border:1px solid #ccc; padding:15px; width:500px; margin:20px auto; }
        .creature-img { width:120px; height:120px; display:block; margin-bottom:10px; }
        .focus-pill { color:black; background:#eee; padding:5px 10px; margin:5px 0; display:inline-block; border-radius:12px; }
        .group-switch button { padding:5px 10px; margin:5px; }
        .bonus-grid { width:100%; border-collapse:collapse; margin-top:10px; }
        .bonus-grid th, .bonus-grid td { border:1px solid #aaa; text-align:center; padding:4px; }
    </style>
</head>
<body>
<div class="card">
    <div class="creature-info-card">
        <?php if(!empty($attackGroups)): ?>
            <div id="creatureDisplay">
                <!-- This will be filled by JS -->
            </div>

            <div class="group-switch">
                <button id="prev">&lt; Prev</button>
                <button id="next">Next &gt;</button>
            </div>

            <p>Units: <?= $totalUnits ?> | Groups: <?= count($attackGroups) ?></p>

            <script>
                // ✅ Prepare data from PHP
                const attackGroups = <?= json_encode($attackGroups) ?>;
                let currentIndex = 0;

                function renderCreature(i) {
                    const creature = attackGroups[i][0]; // Each group has one creature
                    if (!creature) return;

                    const bonusParts = Object.entries(creature.bonuses || {}).map(
                        ([type, val]) => type.toLowerCase() + ' +' + Number(val).toLocaleString() + ' % '
                    ).join(' &nbsp;|&nbsp; ');

                    const html = `
                        <div class="creature-text-block" style="display:flex; gap:15px; align-items:flex-start;">
                            
                            <!-- IMAGE -->
                            <div class="creature-image-container" style="flex-shrink:0;">
                                <img src="${creature.imgpath}" class="creature-img" alt="${creature.name}" style="max-width:120px; border:1px solid #ccc; border-radius:8px;">
                            </div>

                            <!-- INFO -->
                            <div style="flex-grow:1;">
                                <!-- TITLE -->
                                <div class="reward-text-top">
                                    <h3>Formation #${attackGroups.length} | ${creature.name}</h3>
                                </div>

                                <!-- BONUS PILL -->
                                <div class="reward-text-middle">
                                    <div id="activeGroup" class="focus-pill">
                                        ${bonusParts}
                                    </div>
                                </div>

                                <!-- SUMMARY -->
                                <div class="reward-text-bottom">
                                    Units Used: <?= $totalUnits ?>
                                    &nbsp;|&nbsp;
                                    Groups: <?= count($attackGroups) ?>
                                    &nbsp;|&nbsp;
                                    Mode: <?= ucfirst($unitType ?? 'Mixed') ?>
                                    <br>
                                    <span style="opacity:.6;">
                                        Check creature info card in-game for bonus percentages
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- MONSTER GRID TABLE -->
                        <div class="monster-grid" style="margin-top:15px;">
                            <div id="activeGroupTbl">
                                <table class="bonus-grid">
                                    <thead>
                                        <tr>
                                            <th>${creature.name}</th>
                                            <th>Base</th>
                                            <th>200%</th>
                                            <th>400%</th>
                                            <th>600%</th>
                                            <th>800%</th>
                                            <th>1000%</th>
                                            <th>1200%</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td>Units to Send (STR)</td>
                                            <td></td><td></td><td></td><td></td><td></td><td></td><td></td>
                                        </tr>
                                        <tr>
                                            <td>Expected Losses (HLH)</td>
                                            <td></td><td></td><td></td><td></td><td></td><td></td><td></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    `;
                    document.getElementById('creatureDisplay').innerHTML = html;
                }
                document.getElementById('prev').addEventListener('click', () => {
                    currentIndex = (currentIndex - 1 + attackGroups.length) % attackGroups.length;
                    renderCreature(currentIndex);
                });
                document.getElementById('next').addEventListener('click', () => {
                    currentIndex = (currentIndex + 1) % attackGroups.length;
                    renderCreature(currentIndex);
                });
                // Initial render
                renderCreature(currentIndex);
            </script>
    </div>
    <?php else: ?>
        <p>No creatures found.</p>
    <?php endif; ?>
</div>

<script>
    window.attackGroups = <?= json_encode($attackGroups) ?>;
    window.currentGroup = 0;

    function renderGroup(i){
        const g = attackGroups[i][0];
        document.getElementById('creatureImg').src = g.imgpath;
        document.getElementById('creatureName').innerText = g.name;

        let parts = [];
        for(const [t,v] of Object.entries(g.bonuses)){
            parts.push(`${t.toLowerCase()} +${v}`);
        }
        document.getElementById('bonusLine').innerText = parts.join(' | ');

        // Update monster-grid table header
        const tbl = document.querySelector('#activeGroupTbl table thead th:first-child');
        if(tbl) tbl.innerText = g.name;
    }

    document.getElementById('next').onclick = () => {
        currentGroup++;
        if(currentGroup >= attackGroups.length) currentGroup = 0;
        renderGroup(currentGroup);
    };
    document.getElementById('prev').onclick = () => {
        currentGroup--;
        if(currentGroup < 0) currentGroup = attackGroups.length-1;
        renderGroup(currentGroup);
    };

    // init first creature
    if(attackGroups.length) renderGroup(0);
</script>

</body>
</html>