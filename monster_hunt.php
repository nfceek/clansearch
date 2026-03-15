<?php 
include 'includes/header.php';
require_once 'includes/db.php';

/* -----------------------------
   Fetch Squads by Type
------------------------------*/

$rarity = $_GET['rarity'] ?? 'Common';

if($rarity !== 'Common' && $rarity !== 'Rare'){
    $rarity = 'Common';
}

$selectedSquad = $_GET['squadID'] ?? '';

$stmtSquads = $pdo->prepare("
SELECT squadID, name, level, rarity, image_base
FROM Monster_Squad
WHERE rarity = ?
ORDER BY name, level
");

$stmtSquads->execute([$rarity]);
$squads = $stmtSquads->fetchAll(PDO::FETCH_ASSOC);
$squadList = $squads;


/* -----------------------------
   Kill Shot History
------------------------------*/

$stmtAttacksC = $pdo->prepare("
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
FROM Squad_Attack sa
LEFT JOIN Characters c 
    ON c.characterID = sa.characterID
WHERE sa.gameID = 1
AND sa.rarity = ?
ORDER BY sa.level, sa.squadAttackID
");

$stmtAttacksC->execute(['Common']);

$commonShots = $stmtAttacksC->fetchAll(PDO::FETCH_ASSOC);

$stmtAttacksR = $pdo->prepare("
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
FROM Squad_Attack sa
LEFT JOIN Characters c 
    ON c.characterID = sa.characterID
WHERE sa.gameID = 1
AND sa.rarity = ?
ORDER BY sa.level, sa.squadAttackID
");

$stmtAttacksR->execute(['rare']);

$rareShots = $stmtAttacksR->fetchAll(PDO::FETCH_ASSOC);

/* -----------------------------
   Kill Shot edit
------------------------------*/
$stmtHistory = $pdo->query("
SELECT
    sa.squadAttackID,
    sa.rarity,
    ms.name AS squadName,
    ms.level AS squadLevel,
    COUNT(sau.attackUnitID) AS unitCount
FROM squad_attack sa

LEFT JOIN Monster_Squad ms
    ON ms.squadID = sa.squadID

LEFT JOIN squad_attack_units sau
    ON sau.squadAttackID = sa.squadAttackID

GROUP BY sa.squadAttackID
ORDER BY sa.rarity, ms.name
");

$killShots = $stmtHistory->fetchAll(PDO::FETCH_ASSOC);

/* capt   */
$stmtCapt = $pdo->prepare("
SELECT characterID, name
FROM Characters
WHERE role = 'Captain'
ORDER BY name
");

$stmtCapt->execute();

$captains = $stmtCapt->fetchAll(PDO::FETCH_ASSOC);

/* split into columns */
$historyCommon = [];
$historyRare   = [];
$historyEpic   = [];

foreach($killShots as $k){

    if($k['rarity']=='Common') $historyCommon[] = $k;
    if($k['rarity']=='Rare')   $historyRare[]   = $k;
    if($k['rarity']=='Epic')   $historyEpic[]   = $k;

}

//print_r($commonSquads);
/* -----------------------------
   Planner Inputs
------------------------------*/

$playerLevel = isset($_GET['playerLevel']) ? (int)$_GET['playerLevel'] : 6;

/* default fighters ON */
$useFighters  = isset($_GET['useFighters']);
$useCreatures = isset($_GET['useCreatures']) ? true : true;
$useMercs     = isset($_GET['useMercs']);
$useSpecialists = isset($_GET['useSpecialists']);

$buildPlan = isset($_GET['buildPlan']);

$stackLevel   = $_GET['stackLevel'] ?? null;
$captMaxUnits = $_GET['captMaxUnits'] ?? null;

$monsterHealthList = [];
/*
echo "<pre>";
print_r($_GET);
echo "</pre>";


print_r($_GET);
*/
$monsters   = [];
$squadStats = null;
$squadOptions = '';

foreach($squadList as $sq){

  $label = $sq['name']." (L".$sq['level'].")";

  $squadOptions .=
  '<option value="'.$sq['squadID'].'">'.
  htmlspecialchars($label).
  '</option>';

}


if ($selectedSquad) {

    /* -----------------------------
       Squad Stats
    ------------------------------*/

    $stmtStats = $pdo->prepare("
        SELECT name, level, valor, frags, xp, rarity, image_base
        FROM Monster_Squad
        WHERE squadID = ?
        AND rarity = ?
    ");

    $stmtStats->execute([$selectedSquad, $rarity]);
    $squadStats = $stmtStats->fetch(PDO::FETCH_ASSOC);


    /* -----------------------------
       Squad Monsters + Bonuses
    ------------------------------*/

    $stmt = $pdo->prepare("
SELECT
    squad.*,

    AVG(bonus_mel) OVER() AS squad_mel,
    AVG(bonus_mtd) OVER() AS squad_mtd,
    AVG(bonus_rng) OVER() AS squad_rng,
    AVG(bonus_fly) OVER() AS squad_fly,
    AVG(bonus_oth) OVER() AS squad_oth,

    SUM(total_health) OVER()   AS squad_total_health,
    SUM(total_strength) OVER() AS squad_total_strength

FROM (

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

    FROM Squad_Monster sm

    JOIN Monster m
        ON m.monsterID = sm.monsterID

    LEFT JOIN monster_bonus mb
        ON mb.monsterID = m.monsterID

    WHERE sm.squadID = ?

    GROUP BY
        m.monsterID,
        m.name,
        m.type,
        sm.quantity,
        m.health,
        m.strength,
        sm.slot

) AS squad

ORDER BY squad.monsterID;
    ");

    $stmt->execute([$selectedSquad]);
    $monsters = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/*-----------------------------
   Pull Available Units
------------------------------*/

$units = [];

/* Fighters */
$sql="

  SELECT
  f.name,
  f.type,
  f.level,
  f.strength,
  f.health,

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

  WHERE f.level <= ? and unit = 'Reg'

  GROUP BY f.fighterID
  ORDER BY f.level,f.name

";

if($useFighters){
  $stmt=$pdo->prepare($sql);
  $stmt->execute([$playerLevel]);

  $units = array_merge($units,$stmt->fetchAll(PDO::FETCH_ASSOC));
}

/*specialists*/

$sql="

  SELECT
  f.name,
  f.type,
  f.level,
  f.strength,
  f.health,

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

  WHERE f.level <= ? and unit = 'Spc'

  GROUP BY f.fighterID
  ORDER BY f.level,f.name
";

if($useSpecialists){
  $stmt=$pdo->prepare($sql);
  $stmt->execute([$playerLevel]);

  $units = array_merge($units,$stmt->fetchAll(PDO::FETCH_ASSOC));
}


/* Creatures */
$sql="
  SELECT
  c.name,
  c.type,
  c.level,
  c.strength,
  c.health,

  'creature' AS unit_class,

  ROUND(c.strength+(c.strength*c.strength_bonus/100)) total_strength,
  ROUND(c.health+(c.health*c.health_bonus/100)) total_health,

  ROUND((c.strength+(c.strength*c.strength_bonus/100))+
  (c.strength*COALESCE(MAX(CASE WHEN cb.bonus_against='Mel' THEN cb.bonus_percent END),0)/100)) attack_vs_mel,

  ROUND((c.strength+(c.strength*c.strength_bonus/100))+
  (c.strength*COALESCE(MAX(CASE WHEN cb.bonus_against='Rng' THEN cb.bonus_percent END),0)/100)) attack_vs_rng,

  ROUND((c.strength+(c.strength*c.strength_bonus/100))+
  (c.strength*COALESCE(MAX(CASE WHEN cb.bonus_against='Mtd' THEN cb.bonus_percent END),0)/100)) attack_vs_mtd,

  ROUND((c.strength+(c.strength*c.strength_bonus/100))+
  (c.strength*COALESCE(MAX(CASE WHEN cb.bonus_against='Fly' THEN cb.bonus_percent END),0)/100)) attack_vs_fly

  FROM creature c
  LEFT JOIN creature_bonus cb ON cb.creatureID=c.creatureID

  WHERE c.level <= ?

  GROUP BY c.creatureID
  ORDER BY c.level,c.name
";

if($useCreatures){
  $stmt=$pdo->prepare($sql);
  $stmt->execute([$playerLevel]);

  $units = array_merge($units,$stmt->fetchAll(PDO::FETCH_ASSOC));
}

/* Mercs */
$sql="
  SELECT
  m.name,
  m.type,
  m.level,
  m.strength,
  m.health,

  'merc' AS unit_class,

  ROUND(m.strength+(m.strength*m.strength_bonus/100)) total_strength,
  ROUND(m.health+(m.health*m.health_bonus/100)) total_health,

  ROUND((m.strength+(m.strength*m.strength_bonus/100))+
  (m.strength*COALESCE(MAX(CASE WHEN mb.bonus_against='Mel' THEN mb.bonus_percent END),0)/100)) attack_vs_mel,

  ROUND((m.strength+(m.strength*m.strength_bonus/100))+
  (m.strength*COALESCE(MAX(CASE WHEN mb.bonus_against='Rng' THEN mb.bonus_percent END),0)/100)) attack_vs_rng,

  ROUND((m.strength+(m.strength*m.strength_bonus/100))+
  (m.strength*COALESCE(MAX(CASE WHEN mb.bonus_against='Mtd' THEN mb.bonus_percent END),0)/100)) attack_vs_mtd,

  ROUND((m.strength+(m.strength*m.strength_bonus/100))+
  (m.strength*COALESCE(MAX(CASE WHEN mb.bonus_against='Fly' THEN mb.bonus_percent END),0)/100)) attack_vs_fly

  FROM merc m
  LEFT JOIN merc_bonus mb ON mb.mercID=m.mercID

  WHERE m.level <= ?

  GROUP BY m.mercID
  ORDER BY m.level,m.name
";

if($useMercs){
  $stmt=$pdo->prepare($sql);
  $stmt->execute([$playerLevel]);

  $units = array_merge($units,$stmt->fetchAll(PDO::FETCH_ASSOC));
}
/* -----------------------------
   Attack Engine
------------------------------*/

$attackGroups = [];
$counterSignal = [];

if(isset($_GET['buildPlan']) && $selectedSquad && $monsters){

    /* Determine squad weakness profile */

    $weak = [
        'Mel'=>0,
        'Mtd'=>0,
        'Rng'=>0,
        'Fly'=>0,
        'Oth'=>0
    ];

    foreach($monsters as $m){

        $weak['Mel'] += $m['bonus_mel'];
        $weak['Mtd'] += $m['bonus_mtd'];
        $weak['Rng'] += $m['bonus_rng'];
        $weak['Fly'] += $m['bonus_fly'];
        $weak['Oth'] += $m['bonus_oth'];
    }

    /* Normalize */

    foreach($weak as $k=>$v){
        $weak[$k] = round($v / max(count($monsters),1));
    }


    /* Determine counter colors */

    foreach($weak as $type=>$pct){

        if($pct == 0) $counterSignal[$type] = "green";
        elseif($pct > 40) $counterSignal[$type] = "red";
        else $counterSignal[$type] = "yellow";
    }


    /* Score units */

    $scores = [];

    foreach($units as $u){

        $score =
        ($u['attack_vs_mel'] * (100-$weak['Mel'])) +
        ($u['attack_vs_mtd'] * (100-$weak['Mtd'])) +
        ($u['attack_vs_rng'] * (100-$weak['Rng'])) +
        ($u['attack_vs_fly'] * (100-$weak['Fly']));

        $scores[] = [
            'name'=>$u['name'],
            'level'=>$u['level'],
            'strength'=>$u['strength'],
            'score'=>$score
        ];
    }


    usort($scores,function($a,$b){
        return $b['score'] <=> $a['score'];
    });


    /* Build attack groups */

    $top = array_slice($scores,0,12);

    $groups = array_chunk($top,3);

    foreach($groups as $g){

        $attackGroups[] = $g;

        if(count($attackGroups)>=4) break;
    }
}

/* -----------------------------
   Squad Image Resolution
------------------------------*/

$base   = strtolower($squadStats['image_base'] ?? 'default');
$imageRarity = strtolower($squadStats['rarity'] ?? 'common'); 
$level  = (int)($squadStats['level'] ?? 1);

$levelImage   = "/images/monsters/{$base}_{$imageRarity}_lvl{$level}.png";
$rarityImage  = "/images/monsters/{$base}_{$imageRarity}.png";
$defaultImage = "/images/monsters/{$base}.png";

$docRoot = $_SERVER['DOCUMENT_ROOT'];

if (file_exists($docRoot . $levelImage)) {
    $imagePath = $levelImage;
} elseif (file_exists($docRoot . $rarityImage)) {
    $imagePath = $rarityImage;
} else {
    $imagePath = $defaultImage;
}
?>

<main>
  <div class="page-container-monster">

    <h1>Monster Squad Dashboard</h1>
    <form method="GET">
        <!-- ROW 1 -->
        <div class="row">

            <div class="card col-2">
                <h3>Type</h3>
                <label>
                  <input type="radio" name="rarity" value="Common"
                  <?= ($rarity === 'Common') ? 'checked' : '' ?>
                    onchange="this.form.submit()">
                  Common
                </label>

                <label>
                  <input type="radio" name="rarity" value="Rare"
                  <?= ($rarity === 'Rare') ? 'checked' : '' ?>
                    onchange="this.form.submit()">
                  Rare
              </label>
            </div>

            <div class="card col-4">
                <h3>Select Squad</h3>
                <select name="squadID" onchange="this.form.submit()">
                  <option value="">-- Choose Squad --</option>

                    <?php foreach ($squads as $squad): ?>

                    <option value="<?= $squad['squadID'] ?>"
                    <?= ($selectedSquad == $squad['squadID']) ? 'selected' : '' ?>>

                    <?= htmlspecialchars($squad['rarity']) ?>
                    <?= htmlspecialchars($squad['name']) ?>
                    (L<?= $squad['level'] ?>)

                  </option>

                <?php endforeach; ?>

                </select>
            </div>

            <?php if ($selectedSquad && $squadStats): ?>
              <!-- LEFT 50% : Squad Rewards -->
              <div class="card col-4">
                <h3><?= htmlspecialchars($squadStats['rarity']) ?> <?= htmlspecialchars($squadStats['name']) ?> | Level <?= $squadStats['level'] ?></h3>
                  <div class="squad-reward-line">

                      <img src="<?= $imagePath ?>" 
                          class="squad-img" 
                          alt="<?= htmlspecialchars($squad['name']) ?>">

                    <div class="reward-text">
                      &nbsp;&nbsp;&nbsp;&nbsp;    
                      Valor: <?= number_format($squadStats['valor']) ?>
                      &nbsp;|&nbsp;
                      Frags: <?= number_format($squadStats['frags']) ?>
                      &nbsp;|&nbsp;
                      XP: <?= number_format($squadStats['xp']) ?>
                    </div>
                  </div>
              </div>
            <?php endif; ?>
            
      </div>
    </form>
    <!-- ROW -->
    <div class="row">
    </div>

    <?php if ($selectedSquad && $squadStats): ?>

  <div class="dashboard-grid">

    <!-- ROW 1 (unchanged above this section) -->

    <?php

      function shortNum($n){

          if($n >= 1000000000) return round($n/1000000000,4).' B';
          if($n >= 1000000) return round($n/1000000,4).' M';
          if($n >= 1000) return round($n/1000,4).' K';

          return $n;
      }

    function bonusDot($pct){
        if($pct == 0) return "dot-green";
        if($pct > 40) return "dot-red";
        return "dot-yellow";
    }
    ?>

    <!-- ROW 2 -->
    <div class="row center-row">

      <!-- Monsters -->
      <div class="card col-6">
        <h3>Monsters</h3>

        <?php if ($monsters): ?>
        <div class="monster-grid">

        <?php foreach ($monsters as $monster): ?>

        <?php
          $mel = $monster['bonus_mel'] ?? 0;
          $mtd = $monster['bonus_mtd'] ?? 0;
          $rng = $monster['bonus_rng'] ?? 0;
          $fly = $monster['bonus_fly'] ?? 0;
          $oth = $monster['bonus_oth'] ?? 0;

          $monsterTotalHealth = $monster['total_health'];
          $monsterHealthList[] = $monsterTotalHealth;
        ?>

        <details class="monster-row">

          <summary class="monster-summary">

            <span class="col col-name">
            <?= htmlspecialchars($monster['name']) ?>
            </span>

            <span class="col col-hlh">
            H: <?= shortNum($monster['total_health']) ?>
            </span>

            <span class="col col-str">
            S: <?= shortNum($monster['total_strength']) ?>
            </span>

            <span class="col bonus-col">

            <span class="dot <?=bonusDot($mel)?>" title="Mel <?=$mel?>%"></span> Mel
            <span class="dot <?=bonusDot($mtd)?>" title="Mtd <?=$mtd?>%"></span> Mtd
            <span class="dot <?=bonusDot($rng)?>" title="Rng <?=$rng?>%"></span> Rng
            <span class="dot <?=bonusDot($fly)?>" title="Fly <?=$fly?>%"></span> Fly
            <span class="dot <?=bonusDot($oth)?>" title="Other <?=$oth?>%"></span> Oth

            </span>

          </summary>
        <div class="monster-calc"> <span></span> <span class="col col-bns">
          <?= shortNum($monster['quantity']) ?> × <?= shortNum($monster['health']) ?>
            </span> 
            <span class="col col-bns"> 
              <?= shortNum($monster['quantity']) ?> × <?= shortNum($monster['strength']) ?>
            </span> 
            <span class="bonus-detail"> Mel <?=$mel?>% | Mtd <?=$mtd?>% | Rng <?=$rng?>% | Fly <?=$fly?>% | Oth <?=$oth?>% 
            </span> 
        </div> 
      </details> 
      <?php endforeach; ?> 
      </div> <?php else: ?> 
      <p>No monsters assigned.</p> 
      <?php endif; ?> 
    </div>

    <!-- Attack Planner -->
    <div class="card col-6">

      <h3>Attack Planner</h3>

      <form method="GET">

        <input type="hidden" name="squadID" value="<?= $selectedSquad ?>">
        <input type="hidden" name="rarity" value="<?= htmlspecialchars($rarity) ?>">

        <!-- Player Level -->
        <div class="planner-section">
          <label><strong>Player Level</strong></label>
            <select name="playerLevel" class="selectLevel">
              <?php for($i=1;$i<=10;$i++): ?>
              <option value="<?=$i?>" <?=($playerLevel==$i)?'selected':''?>>
              Level <?=$i?>
              </option>
            <?php endfor; ?>
            </select>

          <!-- Captain Capacity -->
          <label><strong>Captain Max Units</strong></label>
          <input type="number"
          class="captMaxUnits troopCap"
          name="captMaxUnits"
          value="<?= htmlspecialchars($captMaxUnits ?? '') ?>"
          placeholder="Future: troop cap">
        </div>

        <div class="planner-section">
          <!-- Unit Types -->
          <label><strong>Units Available</strong></label>
            <label>
              <input type="checkbox" name="useFighters" value="1"
              <?= $useFighters ? 'checked' : '' ?>>
              Fighters
            </label>

            <label>
              <input type="checkbox" name="useCreatures" value="1"
              <?= $useCreatures ? 'checked' : '' ?>>
              Creatures
            </label>

            <label>
              <input type="checkbox" name="useMercs" value="1"
              <?= $useMercs ? 'checked' : '' ?>>
              Mercs
            </label>

            <label>
              <input type="checkbox" name="useSpecialists" value="1"
              <?= $useSpecialists ? 'checked' : '' ?>>
              Specialists
            </label>

        </div>

        <!-- Stacking Future 
        <div class="planner-section">
          <label><strong>Stack Lower Level Units</strong></label>
          <select name="stackLevel">
            <option value="">None</option>
            <?php for($i=1;$i<$playerLevel;$i++): ?>
            <option value="<?=$i?>">
            Include Level <?=$i?>
            </option>
            <?php endfor; ?>
          </select>
        </div>
        -->
        <div style="margin-top:12px;">
          <button type="submit" name="buildPlan" value="1">Build Attack Plan</button>
        </div>

      </form>

      <hr style="margin:15px 0;">

      <!-- counter Signal -->
      <?php if(!empty($counterSignal)): ?>
        <div class="counter-bar">
          <?php foreach($counterSignal as $t=>$c): ?>
          <span class="counter <?=$c?>">
            <?=$t?>
          </span>
        <?php endforeach; ?>
          </div>
      <?php endif; ?>

    </div>
  </div>

    <!-- ROW 3 (Spacer) -->
    <div class="row" style="height:20px;"></div>
        <!-- ROW -->
    <div class="row">
      <div class="card col-12">
        <?php
        function renderMetricRows($rows, $squads, $captains){

            if(empty($rows)){
                echo '<p class="muted">No saved attacks</p>';
                return;
            }
            foreach($rows as $s){

                echo '<div class="metric-row" data-id="'.$s['squadAttackID'].'">';
                echo '<select class="lvl" style="width:55px;">';
                for($c=1;$c<=40;$c++){
                    $sel = ($c==$s['level']) ? 'selected' : '';
                    echo "<option value='$c' $sel>$c</option>";
                }
                echo '</select>';
                echo '<select class="squad">';
                echo '<option value="">Select Squad</option>';
                foreach($squads as $sq){
                    $sel = ($s['squadID']==$sq['squadID'])?'selected':'';
                    echo '<option value="'.$sq['squadID'].'" '.$sel.'>'.
                        htmlspecialchars($sq['name']).' (L'.$sq['level'].')</option>';
                }
                echo '</select>';
                echo '<input class="troops" value="'.htmlspecialchars($s['qty']).'">';
                echo '<input class="unit" value="'.htmlspecialchars($s['troop']).'">';
                echo '<input type="hidden" class="captID" value="'.$s['characterID'].'">';               
                echo '<input class="captainName" placeholder="Select Captain" value="'.htmlspecialchars($s['captainName'] ?? '', ENT_QUOTES).'">';
                /* capt name dropdown -- not working
                echo '<select class="capt">';
                echo '<option value="">Select Captain</option>';
                foreach($captains as $c){
                    $sel = ($s['characterID']==$c['characterID'])?'selected':'';
                    echo '<option value="'.$c['characterID'].'" '.$sel.'>'.
                        htmlspecialchars($c['name']).'</option>';
                }
                echo '</select>';
                */
                echo '<input class="loss" value="'.htmlspecialchars($s['loss'] ?? '').'">';
                echo '<span class="actions"><span class="edit">✏️</span><span class="delete">✖</span></span>';
                echo '</div>';
            }
        }
        ?>
      <details open class="card-toggle">
      <summary class="card-title">Hunting Parties</summary>

      <div class="scroll-box">
      <div class="metrics-grid">

      <div class="metric-card">
      <h4>Common Squads</h4>

      <div class="metric-table">

      <div class="metric-head">
      <span>Lvl</span>
      <span>Squad</span>
      <span>Troops</span>
      <span>Unit</span>
      <span>Capt</span>
      <span>Expected Loss</span>      
      <span></span>
      </div>



  <?php renderMetricRows($commonShots, $squads, $captains); ?>

  </div>

    <div class="metric-add">
    <button class="add-attack" data-rarity="Common">+ Add</button>
    </div>
  </div>
</div>
        <!-- ROW (Spacer) -->
  <div class="row" style="height:10px;"></div>
  <!-- RARE -->
  <div class="metric-card">
    <h4>Rare Squads</h4>
    <div class="metric-table">
      <div class="metric-head">
        <span>Lvl</span>
        <span>Type</span>
        <span>Squad</span>
        <span>Troops</span>
        <span>Unit</span>
        <span>Capt</span>
        <span></span>
      </div>
      <?php if($rareShots): ?>
      <?php foreach($rareShots as $s): ?>
          <div class="metric-row" data-id="<?= $s['squadAttackID'] ?>">

          <select class="lvl">
          <option selected><?= $s['squadLevel'] ?></option>
          </select>

          <span class="type">R</span>

          <select class="squad">

            <option value="">Select Squad</option>

            <?php foreach($rareSquads as $sq): ?>

              <option value="<?= $sq['squadID'] ?>"
              data-level="<?= $sq['level'] ?>"
              <?= ($selectedSquad == $sq['squadID']) ? 'selected' : '' ?>>

              <?= htmlspecialchars($sq['name']) ?> (L<?= $sq['level'] ?>)

              </option>

            <?php endforeach; ?>

          </select>
          <input class="troops" value="<?= $s['unitCount'] ?>">

        <select class="unit">
          <option>Ruby Golem</option>
          <option>Storm Archer</option>
          </select>

            <select class="capt">
            <option>Capt 1</option>
        </select>

      <span class="actions">
      <span class="edit">✏️</span>
      <span class="delete">✖</span>
      </span>

      </div>
      <?php endforeach; ?>
      <?php else: ?>
      <p class="muted">No saved attacks</p>
      <?php endif; ?>
      </div>
        <div class="metric-add">
          <button class="add-attack" data-type="Rare">+ Add</button>
        </div>
        </div>


          <!-- FUTURE -->
          <!--
          <div class="metric-card">
          <h4>Epic Squads</h4>
          </div>
          -->
    </div>
  </div>
  </div>


  <!-- ROW (Spacer) -->
  <div class="row" style="height:20px;"></div>
  
  <!-- ROW 4 -->
  <div class="row">
    <div class="card col-12">
      <details open class="card-toggle">
      <summary class="card-title">Troop Groups</summary>
        <div id="attackResults" class="scroll-box">
          <?php if($attackGroups): ?>
            <?php $gnum=1; ?>
            <?php foreach($attackGroups as $group): ?>
              <div class="attack-group">
                <strong>Group <?=$gnum?></strong>
                <ul>
                  <?php foreach($group as $unit): ?>
                  <?php foreach($monsterHealthList as $monsterTotalHealth): ?>
                  <?php
                    $base = ceil($monsterTotalHealth / max(1,$unit['strength']));
                    $troops = $base + 4;
                  ?>
                  <li>
                    <?=$troops?> × <?=$unit['name']?> (L<?=$unit['level']?>)
                    <br>
                    <small>
                    <?=number_format($monsterTotalHealth)?> /
                    <?=number_format($unit['strength'])?>
                    = <?=$base?> (+4 safety)
                    </small>
                  </li>
                  <?php endforeach; ?>
                  <?php endforeach; ?>
                </ul>
              </div>
              <?php $gnum++; ?>
            <?php endforeach; ?>
          <?php else: ?>
            <p style="opacity:.6;">Press Build Attack Plan</p>
          <?php endif; ?>
        </div>
          <?php endif; ?>
        </div>
      </details>
    </div>
  </div>

  <!-- ROW (Spacer) -->
  <div class="row" style="height:40px;"></div>
</main>

  <script>

    /* -----------------------------
    RESET SQUAD WHEN RARITY CHANGES
    ------------------------------*/

    document.querySelectorAll('input[name="rarity"]').forEach(r=>{
      r.addEventListener('change', ()=>{
        const squad = document.querySelector('select[name="squadID"]');
        if(squad) squad.value="";
      });
    });


    /* -----------------------------
    ADD NEW ATTACK ROW
    ------------------------------*/

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
<?php include 'includes/footer.php'; ?>