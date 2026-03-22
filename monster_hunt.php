<?php
  include __DIR__ . '/includes/header.php';

/* -----------------------------
   Helpers
------------------------------*/

function fetchAll($pdo, $sql, $params = []) {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getAttacksByRarity($pdo, $rarity) {
    return fetchAll($pdo, "
        SELECT 
            sa.squadAttackID,
            sa.gameID,
            sa.squadID,
            sa.rarity,
            sa.level,
            sa.troop,
            sa.qty,
            sa.characterID,
            sa.loss,
            c.name AS captainName
        FROM squad_attack sa
        LEFT JOIN characters c ON c.characterID = sa.characterID
        WHERE sa.gameID = 1
        AND sa.rarity = ?
        ORDER BY sa.level, sa.squadAttackID
    ", [$rarity]);
}

function getFighters($pdo, $playerLevel, $unitType) {
    return fetchAll($pdo, "
        SELECT
        f.name, f.type, f.level, f.strength, f.health,
        'fighter' AS unit_class,

        ROUND((f.strength+(f.strength*f.strength_bonus/100))+
        (f.strength*COALESCE(MAX(CASE WHEN fb.bonus_against='Mel' THEN fb.bonus_percent END),0)/100)) attack_vs_mel,

        ROUND((f.strength+(f.strength*f.strength_bonus/100))+
        (f.strength*COALESCE(MAX(CASE WHEN fb.bonus_against='Rng' THEN fb.bonus_percent END),0)/100)) attack_vs_rng,

        ROUND((f.strength+(f.strength*f.strength_bonus/100))+
        (f.strength*COALESCE(MAX(CASE WHEN fb.bonus_against='Mtd' THEN fb.bonus_percent END),0)/100)) attack_vs_mtd,

        ROUND((f.strength+(f.strength*f.strength_bonus/100))+
        (f.strength*COALESCE(MAX(CASE WHEN fb.bonus_against='Fly' THEN fb.bonus_percent END),0)/100)) attack_vs_fly

        FROM fighter f
        LEFT JOIN fighter_bonus fb ON fb.fighterID=f.fighterID
        WHERE f.level <= ? AND unit = ?
        GROUP BY f.fighterID
        ORDER BY f.level,f.name
    ", [$playerLevel, $unitType]);
}


/* -----------------------------
   Inputs
------------------------------*/

$rarity = $_GET['rarity'] ?? 'Common';

$monsterHealthList = [];
$monsterTotalHealth = 0;

$monsterStrengthList = [];
$monsterTotalStrength = 0;

if(!in_array($rarity, ['Common','Rare'])) $rarity = 'Common';
  $selectedSquad    = $_GET['squadID'] ?? '';
  $playerLevel      = isset($_GET['playerLevel']) ? (int)$_GET['playerLevel'] : 6;
  $useFighters      = isset($_GET['useFighters']);
  $useCreatures     = isset($_GET['useCreatures']);
  $buildPlan        = isset($_GET['buildPlan']);



/* -----------------------------
   Squads
------------------------------*/

$squads = fetchAll($pdo, "
    SELECT squadID, name, level, rarity, image_base
    FROM monster_squad
    WHERE rarity = ?
    ORDER BY name, level
", [$rarity]);

/* -----------------------------
   Attacks
------------------------------*/

$commonShots = getAttacksByRarity($pdo, 'Common');
$rareShots   = getAttacksByRarity($pdo, 'Rare');

/* -----------------------------
   History + Captains
------------------------------*/

$killShots = fetchAll($pdo, "
    SELECT
        sa.squadAttackID,
        sa.rarity,
        ms.name AS squadName,
        ms.level AS squadLevel,
        COUNT(sau.attackUnitID) AS unitCount
    FROM squad_attack sa
    LEFT JOIN monster_squad ms ON ms.squadID = sa.squadID
    LEFT JOIN squad_attack_units sau ON sau.squadAttackID = sa.squadAttackID
    GROUP BY sa.squadAttackID
    ORDER BY sa.rarity, ms.name
");

$captains = fetchAll($pdo, "
    SELECT characterID, name
    FROM characters
    WHERE role = 'Captain'
    ORDER BY name
");

/* split history */
$historyCommon = $historyRare = $historyEpic = [];

foreach($killShots as $k){
    if($k['rarity']=='Common') $historyCommon[] = $k;
    elseif($k['rarity']=='Rare') $historyRare[] = $k;
    elseif($k['rarity']=='Epic') $historyEpic[] = $k;
}

/* -----------------------------
   Fetch Squad + Monsters
------------------------------*/
$monsters = [];
if ($selectedSquad) {
    $monsters = fetchAll($pdo, "
        SELECT m.monsterID, m.name, m.type, m.health, m.strength
        FROM squad_monster sm
        JOIN monster m ON m.monsterID = sm.monsterID
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
   Squad Details
------------------------------*/

$squadStats = null;
$monsters   = [];

if ($selectedSquad) {

    $stats = fetchAll($pdo, "
        SELECT name, level, valor, frags, xp, rarity, image_base
        FROM monster_squad
        WHERE squadID = ? AND rarity = ?
    ", [$selectedSquad, $rarity]);

    $squadStats = $stats[0] ?? null;

    $monsters = fetchAll($pdo, "
        SELECT 
            m.monsterID,
            m.name,
            m.type,
            sm.quantity,
            m.health,
            m.strength,

            (sm.quantity * m.health)   AS total_health,
            (sm.quantity * m.strength) AS total_strength,

            COALESCE(MAX(CASE WHEN mb.bonus_against='Mel' THEN mb.bonus_percent END),0) AS bonus_mel,
            COALESCE(MAX(CASE WHEN mb.bonus_against='Mtd' THEN mb.bonus_percent END),0) AS bonus_mtd,
            COALESCE(MAX(CASE WHEN mb.bonus_against='Rng' THEN mb.bonus_percent END),0) AS bonus_rng,
            COALESCE(MAX(CASE WHEN mb.bonus_against='Fly' THEN mb.bonus_percent END),0) AS bonus_fly,
            COALESCE(MAX(CASE WHEN mb.bonus_against='Oth' THEN mb.bonus_percent END),0) AS bonus_oth

        FROM squad_monster sm
        JOIN monster m ON m.monsterID = sm.monsterID
        LEFT JOIN monster_bonus mb ON mb.monsterID = m.monsterID
        WHERE sm.squadID = ?
        GROUP BY m.monsterID
        ORDER BY total_strength DESC
    ", [$selectedSquad]);
}

/* -----------------------------
   build creature array
------------------------------*/

$units = [];
$creatures = []; // make sure it's defined

if($buildPlan){

    if($useFighters){
        $units = array_merge($units, getFighters($pdo,$playerLevel,'Reg'));
    }

    if($useCreatures){
        // fetch creatures into $creatures
        $creatures = fetchAll($pdo,"
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

        // decode bonuses & add formation number
        $i = 1;
        foreach ($creatures as &$c) {
            $c['formation_no'] = $i++;
            $c['bonuses'] = $c['bonuses'] ? json_decode($c['bonuses'], true) : [];
        }
        unset($c);

        // merge into units if you need both fighters and creatures
        $units = array_merge($units, $creatures);
    }
}

// now $creatures is defined, $units contains everything


  /* new creatures section */

  function calculateScore($creature, $enemyType = null) {
      $base = $creature['base_attack'] ?? 100;
      $creaturePath = $creature['imgpath'] ?? null;
      $bonusPercent = $creature['bonus_percent'] ?? 0;
      $bonusType = $creature['bonus_type'] ?? null;

      // Match vs enemy type (basic for now)
      $typeMultiplier = ($enemyType && $bonusType === $enemyType) ? 1.0 : 0.5;

      return $base * (1 + $bonusPercent / 100) * $typeMultiplier;
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

/* -----------------------------
   Attack Engine (unchanged logic)
------------------------------*/

$attackGroups = [];
$counterSignal = [];

if($buildPlan && $selectedSquad && $monsters){

    $weak = ['Mel'=>0,'Mtd'=>0,'Rng'=>0,'Fly'=>0,'Oth'=>0];

    foreach($monsters as $m){
        foreach($weak as $k=>$_){
            $weak[$k] += $m["bonus_".strtolower($k)];
            
        }
    }

    foreach($weak as $k=>$v){
        $weak[$k] = round($v / max(count($monsters),1));
        $counterSignal[$k] = $v == 0 ? 'green' : ($v > 50 ? 'red' : 'yellow');
    }

    $scores = [];

    foreach($units as $u){
        $score =
            ($u['attack_vs_mel'] ?? 0) * (100-$weak['Mel']) +
            ($u['attack_vs_mtd'] ?? 0) * (100-$weak['Mtd']) +
            ($u['attack_vs_rng'] ?? 0) * (100-$weak['Rng']) +
            ($u['attack_vs_fly'] ?? 0) * (100-$weak['Fly']);

        $scores[] = $u + ['score'=>$score];
    }

    usort($scores, fn($a,$b) => $b['score'] <=> $a['score']);

    //$groups = array_chunk(array_slice($scores,0,12),3);
    $groups = array_chunk(array_slice($scores,0,12),1);

    foreach($groups as $g){
        $attackGroups[] = $g;
        if(count($attackGroups)>=4) break;

    }
}

/* -----------------------------
   Squad Image Resolution
------------------------------*/

function resolveSquadImage($squadStats) {

    $base = strtolower($squadStats['image_base'] ?? 'default');
    $rarity = strtolower($squadStats['rarity'] ?? 'common');
    $level = (int)($squadStats['level'] ?? 1);

    $paths = [
        "/images/monsters/{$base}_{$rarity}_lvl{$level}.png",
        "/images/monsters/{$base}_{$rarity}.png",
        "/images/monsters/{$base}.png"
    ];

    foreach ($paths as $path) {
        if (file_exists($_SERVER['DOCUMENT_ROOT'] . $path)) {
            return $path;
        }
    }

    return '/images/monsters/default.png';
}

$imagePath = resolveSquadImage($squadStats ?? []);

?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8">
    <title>Clan Management</title>
    <link rel="stylesheet" href="/css/styles.css">
  </head>
  <body>
    <div class="page-container-monster">
        <h1>Monster Squad Dashboard</h1>
        <form method="GET">
          <div class="row">
            <div class="col-6"> <!-- monster selection -->
                <div class="card">
                    <h3>Monster Selection</h3>
                    <!-- Rarity -->
                    <div class="rarity-group">
                        <label>
                            <input type="radio" name="rarity" value="Common"
                            <?= ($rarity === 'Common') ? 'checked' : '' ?>
                            onchange="this.form.submit()" checked>
                            Common
                        </label>

                        <label>
                            <input type="radio" name="rarity" value="Rare"
                            <?= ($rarity === 'Rare') ? 'checked' : '' ?>
                            onchange="this.form.submit()" disabled>
                            Rare
                        </label>
                    </div>
                    <!-- Squad Dropdown -->
                    <div class="squad-select">
                        <select name="squadID" onchange="this.form.submit()">
                            <option value="">-- Choose Squad --</option>

                            <?php foreach ($squads as $squad): ?>
                                <option value="<?= $squad['squadID'] ?>"
                                <?= ($selectedSquad == $squad['squadID']) ? 'selected' : '' ?>>

                                    <?= htmlspecialchars($squad['name']) ?>
                                    (L<?= $squad['level'] ?>)

                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="squad-explain" style='padding-top:10px;margin-top:8px'>
                      <label name="squad-info" id="squad-info" style='padding-top:10px;margin-top:8px'>
                        <b>This is NOT a stack calc</b>
                      </label>
                      <br />
                       <label name="squad-info" id="squad-info" style="opacity:.6; padding-top:12px;margin-top:8px;font-size:.9rem;">
                        It is for Monsters on the World Map
                      </label>                     

                    </div>
                </div>
            </div>
            <?php if ($selectedSquad && $squadStats): ?>   <!-- squad form -->           
            <!-- ROW: Attack Planner -->
            <div class="col-6">
              <div class="card">
                <h3>Attack Planner</h3>
                <form method="GET">
                  <input type="hidden" name="squadID" value="<?= $selectedSquad ?>">
                  <input type="hidden" name="rarity" value="<?= htmlspecialchars($rarity) ?>">

                  <!-- Planner Section -->
                  <div class="planner-section">

                    <!-- Player Level -->
                    <label><strong>Player Level</strong></label>
                    <select name="playerLevel" class="selectLevel">
                      <?php for($i=1;$i<=10;$i++): ?>
                        <option value="<?=$i?>" <?=($playerLevel==$i)?'selected':''?>>
                          Level <?=$i?>
                        </option>
                      <?php endfor; ?>
                    </select>

                    <!-- Unit Types -->
                    <label><strong>Troops</strong></label>
                    <label><input type="checkbox" name="useFighters" value="1" <?= $useFighters ? 'checked' : '' ?> disabled> Fighters</label>
                    <label><input type="checkbox" name="useCreatures" value="1" <?= $useCreatures ? 'checked' : '' ?> checked> Creatures</label>
                  </div>
                  <div class="bonus-section">
                      <label></label>
                      <label></label>
                  </div>
                    <!-- Submit -->
                    <small id="unitHint" style="opacity:.6;">Select a unit type to build plan</small>
                    <div style="margin-top:12px;">
                      <button  type="submit" name="buildPlan"  value="1" class="btn-primary" id="buildPlanBtn">
                        Build Attack Plan
                      </button>
                    </div>
                </form>
              </div>
            </div>
          </div>

      <div class="row">
        <div class="col-6">
          <div class="card">
            <?php
              function shortNum($n){
                  if($n >= 1000000000) return round($n/1000000000,4).' B';
                  if($n >= 1000000) return round($n/1000000,4).' M';
                  if($n >= 1000) return round($n/1000,4).' K';
                  return $n;
              }

            function bonusDot($pct){
                if($pct == 0) return "dot-green";
                if($pct > 50) return "dot-red";
                return "dot-yellow";
            }
            ?>

            <!-- left 50% : Squad Rewards -->
          <div class="inner-card">
              <div class="squad-image-container">
                <img src="<?= htmlspecialchars($imagePath) ?>"  class="squad-img" alt="<?= htmlspecialchars($squad['name']) ?>">                      
                <div class="squad-text-block">
                  <div class="reward-text-top">
                    <h3><?= htmlspecialchars($squadStats['rarity']) ?> <?= htmlspecialchars($squadStats['name']) ?> | Lvl <?= $squadStats['level'] ?> </h3>
                  </div>
                  <div class="reward-text-middle"> 
                    <!--Monster Counter Bar -->
                    <?php if(!empty($counterSignal)): ?>
                      <div class="counter-bar">Damage Mods: 
                        <?php foreach($counterSignal as $t=>$c): ?>
                          <span class="counter <?=$c?>"><?=$t?></span>
                        <?php endforeach; ?>
                      </div>
                    <?php endif; ?>
                  </div>

                  <div class="reward-text-bottom">   
                    Valor: <?= shortNum($squadStats['valor']) ?>
                    &nbsp;|&nbsp;
                    Frags: <?= shortNum($squadStats['frags']) ?>
                    &nbsp;|&nbsp;
                    XP: <?= shortNum($squadStats['xp']) ?>
                  </div>
                </div>
              </div>
              <?php if ($monsters): ?>
                <div class="monster-grid">

                <?php foreach ($monsters as $monster): ?>

                <?php
                  $mel = $monster['bonus_mel'] ?? 0;
                  $mtd = $monster['bonus_mtd'] ?? 0;
                  $rng = $monster['bonus_rng'] ?? 0;
                  $fly = $monster['bonus_fly'] ?? 0;
                  $oth = $monster['bonus_oth'] ?? 0;

                  $health = $monster['total_health'] ?? 0;
                      $monsterHealthList[] = $health;
                      $monsterTotalHealth += $health;

                  $strength = $monster['total_strength'] ?? 0;
                      $monsterStrengthList[] = $strength;
                      $monsterTotalStrength += $strength;
                      
                ?>

                <?php 
                  $monsterMaxHealth = !empty($monsterHealthList) ? max($monsterHealthList) : 0; 
                  $monsterMaxStrength = !empty($monsterStrengthList) ? max($monsterStrengthList) : 0; ?>

                <details class="monster-row">
                  <summary class="monster-summary">
                    <span class="col col-name">
                    <?= htmlspecialchars($monster['name']) ?> (<?= htmlspecialchars($monster['type']) ?>)
                    </span>              
                    <span class="col col-qty"> 
                      Qty: <?= shortNum($monster['quantity']) ?> 
                    </span> 
                    <span class="col col-hlh">
                      Hth: <?= shortNum($monster['total_health']) ?>
                    </span>
                    <span class="col col-str">
                      Str: <?= shortNum($monster['total_strength']) ?>
                    </span>
                  </summary>
                  <div class="monster-calc">
                    <span class="bonus-col">
                      <span class="dot <?=bonusDot($mel)?>" title="Mel <?=$mel?>%"></span> Mel
                      <span class="dot <?=bonusDot($mtd)?>" title="Mtd <?=$mtd?>%"></span> Mtd
                      <span class="dot <?=bonusDot($rng)?>" title="Rng <?=$rng?>%"></span> Rng
                      <span class="dot <?=bonusDot($fly)?>" title="Fly <?=$fly?>%"></span> Fly
                      <span class="dot <?=bonusDot($oth)?>" title="Other <?=$oth?>%"></span> Oth
                    </span>
                  </div>
                </details> 
              <?php endforeach; ?> 
              </div> <?php else: ?> 
              <p>No monsters assigned.</p> 
              <?php endif; ?> 
            </div>
          </div>
        </div>

        <!-- RIGHT: Attack Formation -->
        <div class="col-6">
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

                    <p>Creature Attack Options: <?= count($attackGroups) ?></p>

                      <script>
                        const attackGroups = <?= json_encode($attackGroups) ?>;
                        const monsterMaxHealth = <?= (int)$monsterMaxHealth ?>;
                        const monsterMaxStrength = <?= (int)$monsterMaxStrength ?>;
                        let currentIndex = 0;

                        /* ------------------ CALCS ------------------ */
                        function calcUnitsNeeded(creatureStrength, percent = 0) {
                            const boosted = creatureStrength * (1 + percent / 100);
                            let units = Math.ceil(monsterMaxHealth / boosted);  

                            if (units < 1) return 1;

                            if (units > 500) return '<span style="color:red;">✖</span>';

                              return units.toLocaleString();
                        }

                        function calcLosses(creatureHealth, percent = 0, units) {
                            const boostedHP = creatureHealth * (1 + percent / 100) * units;
                            const diff = monsterMaxStrength - boostedHP;

                            /* ❌ catch bad inputs → show red X
                            if (units <= 0) {
                                return '<span style="color:red;">✖</span>';
                            }*/

                            if (diff >= boostedHP) {
                                if (monsterMaxStrength >= boostedHP) {
                                    // how many creatures die
                                    const spend = Math.ceil(monsterMaxStrength / creatureHealth);
                                      return `<span style="color:red;">${spend}</span>`;
                                }
                            }

                            if (diff <= 0) {
                                  // Creature survives completely → GREEN 0
                                  return '<span style="color:green;">NONE</span>';
                            }

                            // Partial losses: how many creatures "die" to cover the diff
                            const loss = Math.ceil(diff / creatureHealth);

                              // Never exceed the units sent
                              return Math.min(loss, units).toLocaleString();
                        }

                        /* ------------------ RENDER ------------------ */
                        function renderCreature(i) {
                            const creature = attackGroups[i][0];
                            if (!creature) return;

                            const bonusParts = Object.entries(creature.bonuses || {}).map(
                                ([type, val]) => `${type.toLowerCase()} +${Number(val).toLocaleString()}%`
                            ).join(' &nbsp;|&nbsp; ');

                            const levels = [0,200,400,600,800,1000,1200];

                            let strRow = '';
                            let hlhRow = '';

                            levels.forEach(p => {
                                const units = calcUnitsNeeded(creature.strength, p);
                                const losses = calcLosses(creature.health, units, p);

                                strRow += `<td>${units}</td>`;
                                hlhRow += `<td>${losses}</td>`;
                            });

                            const html = `
                                <div class="creature-text-block" style="display:flex; gap:15px; align-items:flex-start; padding-left:8px;">
                                    
                                    <div class="creature-image-container">
                                        <img src="${creature.imgpath}" class="creature-img" style="max-width:120px;">
                                    </div>

                                    <div style="flex-grow:1;">
                                        <div class="formation-text-top">
                                            <h3>Formation #${creature.formation_no} | ${creature.name} (${creature.type})</h3>
                                        </div>

                                        <div class="formation-text-middle">Bonus Mods: 
                                            <div class="focus-pill">
                                                ${bonusParts}
                                            </div>
                                        </div>

                                        <div class="formation-text-bottom">
                                            Base Str: ${Number(creature.strength).toLocaleString()}
                                            &nbsp;|&nbsp;
                                            Base Hth: ${Number(creature.health).toLocaleString()}
                                        </div>
                                    </div>
                                </div>

                                <div class="monster-grid" style="margin-top:15px;">
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
                                                ${strRow}
                                            </tr>
                                            <tr>
                                                <td>Expected Losses (HTH)</td>
                                                ${hlhRow}
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            `;

                            document.getElementById('creatureDisplay').innerHTML = html;
                        }

                        /* ------------------ NAV ------------------ */

                        document.getElementById('next').onclick = () => {
                            currentIndex = (currentIndex + 1) % attackGroups.length;
                            renderCreature(currentIndex);
                        };

                        document.getElementById('prev').onclick = () => {
                            currentIndex = (currentIndex - 1 + attackGroups.length) % attackGroups.length;
                            renderCreature(currentIndex);
                        };

                        /* ------------------ INIT ------------------ */

                        renderCreature(currentIndex);
                      </script>
            </div>
            <?php else: ?>
                <p>No creatures found.</p>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <div class="row">     <!-- Buttons -->
        <div class="col-12">
          <div class="card">
              <!--Monster Counter Bar--> 
              <?php if(!empty($counterSignal)): ?>
                <hr style="margin:15px 0;">
                <div class="counter-bar">
                  <?php foreach($counterSignal as $t=>$c): ?>
                    <span class="counter <?=$c?>"><?=$t?></span>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>
          </div>
        </div>
      </div>


      <!-- ROW (Spacer) -->
      <div class="row" style="min-height:240px;"></div>

    <?php endif; ?> <!-- end squad form -->
    </div>
  </div>

  <script>

  document.addEventListener('click', async function(e) {
    // Clear button (in results or in clan list)
    if (e.target.matches('.clear-btn') || e.target.matches('[data-clear-results]')) {
      const results = document.getElementById('results');
      if (results) results.innerHTML = '';
      return;
    }

    // Show clan members (delegated handler for dynamic content)
    if (e.target.matches('.show-members-btn')) {
      const clanId = e.target.dataset.clanId;
      if (!clanId) return;

      const results = document.getElementById('results');
      if (!results) {
        console.error('#results element not found');
        return;
      }

      // Visual feedback
      const originalText = e.target.textContent;
      e.target.textContent = 'Loading...';
      e.target.disabled = true;

      try {
        const res = await fetch('get_clan_members.php?clan_id=' + encodeURIComponent(clanId));
        if (!res.ok) throw new Error('Network response not OK: ' + res.status);
        const html = await res.text();
        results.innerHTML = html;
      } catch (err) {
        console.error(err);
        results.innerHTML = '<p style="color:red">Error loading members. See console.</p>';
      } finally {
        e.target.textContent = originalText;
        e.target.disabled = false;
      }
    }
  });

    window.currentGroup = 0;
    window.attackGroups = <?= json_encode($attackGroups) ?>;

      const attackGroups = <?= json_encode($attackGroups) ?>;
      document.addEventListener('DOMContentLoaded', () => {

        const checkboxes = document.querySelectorAll(
          'input[name="useFighters"], input[name="useCreatures"]'
        );

        const button = document.getElementById('buildPlanBtn');
        const hint = document.getElementById('unitHint');

        function updateButtonState() {
          const isChecked = [...checkboxes].some(cb => cb.checked);

          if (button) button.disabled = !isChecked;
          if (hint) hint.style.display = isChecked ? 'none' : 'block';
        }

        updateButtonState();
        checkboxes.forEach(cb => cb.addEventListener('change', updateButtonState));

      });

      document.querySelectorAll('.add-attack').forEach(btn=>{

      btn.addEventListener('click',function(){

      const type=this.dataset.type;
      const table=this.closest('.metric-card').querySelector('.metric-table');

        fetch("includes/add_attack.php",{
        method:"POST",
        headers:{
        "Content-Type":"application/json"
        },
        body:JSON.stringify({type:type})
        })
        .then(r=>r.json())
        .then(data=>{

        const row=document.createElement("div");
        row.className="metric-row";
        row.dataset.id=data.id;

        row.innerHTML=`
          <select class="lvl"></select>

          <span class="type">${type=='Common'?'C':'R'}</span>

          <select class="squad">
          <option value="">Select Squad</option>
            <?= $squadOptions ?>
          </select>

          <input class="troops" value="0">

          <select class="unit">
          <option value="1">Ruby Golem</option>
          </select>

          <select class="capt">
          <option value="1">Capt 1</option>
          </select>

          <span class="actions">
          <span class="edit">✏️</span>
          <span class="delete">✖</span>
          </span>
        `;
        const lvlSelect = row.querySelector('.lvl');

          for(let i=1;i<=40;i++){
            const opt=document.createElement('option');
            opt.value=i;
            opt.textContent=i;
            lvlSelect.appendChild(opt);
          }
          table.appendChild(row);

          });

        });

      });

      document.addEventListener('change',function(e){

      if(e.target.classList.contains('squad')){

      const row=e.target.closest('.metric-row');
      const lvl=row.querySelector('.lvl');

      const squadLevel=e.target.options[e.target.selectedIndex].dataset.level;

      if(squadLevel){
      lvl.value=squadLevel;
      }

      }

      });



      // init first creature
      if(attackGroups.length) renderGroup(0);

      // 🔹 BUTTONS
      document.getElementById('groupPrev')?.addEventListener('click', () => {
          currentGroup = (currentGroup - 1 + attackGroups.length) % attackGroups.length;
          renderGroup(currentGroup);
      });

      document.getElementById('groupNext')?.addEventListener('click', () => {
          currentGroup = (currentGroup + 1) % attackGroups.length;
          renderGroup(currentGroup);
      });

      /* -----------------------------
      GLOBAL CLICK HANDLER
      handles edit/delete on new rows
      ------------------------------*/

      document.addEventListener('click',function(e){

      /* DELETE */

      if(e.target.classList.contains('delete')){

      const row=e.target.closest('.metric-row');
      const id=row.dataset.id;

      if(!confirm("Delete this attack?")) return;

      fetch("includes/delete_attack.php",{
      method:"POST",
      headers:{
      "Content-Type":"application/x-www-form-urlencoded"
      },
      body:"id="+id
      })
      .then(()=>row.remove());

      }

      /* EDIT / SAVE */

      if(e.target.classList.contains('edit')){

      const row=e.target.closest('.metric-row');

      const data={
        id:row.dataset.id,
        squadID:row.querySelector('.squad').value,
        level:row.querySelector('.lvl').value,
        troops:row.querySelector('.troops').value,
        loss:row.querySelector('.loss').value  ,
        name:row.querySelector('.name').value      
      };

      fetch("includes/update_attack.php",{
      method:"POST",
      headers:{
      "Content-Type":"application/json"
      },
      body:JSON.stringify(data)
      })
      .then(r=>r.text())
      .then(res=>{
      console.log(res);
      alert("Saved");
      });

      }

      });

    </script>
  <?php include __DIR__ .  '/includes/footer.php'; ?>
  </body>
</html>