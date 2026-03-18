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
        FROM Squad_Attack sa
        LEFT JOIN Characters c ON c.characterID = sa.characterID
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
if(!in_array($rarity, ['Common','Rare'])) $rarity = 'Common';

$selectedSquad = $_GET['squadID'] ?? '';
$playerLevel   = isset($_GET['playerLevel']) ? (int)$_GET['playerLevel'] : 6;

$useFighters  = isset($_GET['useFighters']);

$useCreatures = isset($_GET['useCreatures']);

$buildPlan       = isset($_GET['buildPlan']);

/* -----------------------------
   Squads
------------------------------*/

$squads = fetchAll($pdo, "
    SELECT squadID, name, level, rarity, image_base
    FROM Monster_Squad
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
    LEFT JOIN Monster_Squad ms ON ms.squadID = sa.squadID
    LEFT JOIN squad_attack_units sau ON sau.squadAttackID = sa.squadAttackID
    GROUP BY sa.squadAttackID
    ORDER BY sa.rarity, ms.name
");

$captains = fetchAll($pdo, "
    SELECT characterID, name
    FROM Characters
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
   Squad Details
------------------------------*/

$squadStats = null;
$monsters   = [];

if ($selectedSquad) {

    $stats = fetchAll($pdo, "
        SELECT name, level, valor, frags, xp, rarity, image_base
        FROM Monster_Squad
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

        FROM Squad_Monster sm
        JOIN Monster m ON m.monsterID = sm.monsterID
        LEFT JOIN monster_bonus mb ON mb.monsterID = m.monsterID
        WHERE sm.squadID = ?
        GROUP BY m.monsterID
    ", [$selectedSquad]);
}

/* -----------------------------
   Units (ONLY when needed)
------------------------------*/

$units = [];

if($buildPlan){

    if($useFighters){
        $units = array_merge($units, getFighters($pdo,$playerLevel,'Reg'));
    }

    if($useCreatures){
        $units = array_merge($units, fetchAll($pdo,"
            SELECT c.name, c.type, c.level, c.strength, c.health,
            'creature' AS unit_class, cb.bonus_percent, cb.bonus_against
            FROM creature c 
            JOIN creature_bonus cb on cb.creatureID =c.creatureID
            WHERE level = ?
            LIMIT 2
        ",[$playerLevel]));
    }
}

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
/*
echo '<pre>';
print_r($scores);
echo '</pre>';
echo '<br>';
*/
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

<main>
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
                      <div class="squad-explain" style='padding-top:10px;'>
                        <label name="squad-info" id="squad-info"><br /><b>This is NOT a stack calc</b><br /><br />It is meant for Monsters on the World Map
                          <!--
                          <ul>
                            <li>Select Common/Rare</li>
                            <li>Pick A Monster Squad</li>
                            <li>Select Attack Units</li>
                            <li>Use Dashboard to dermine best Attack Group</li>
                          </ul>
                          -->
                        </label>

                      </div>
                  </div>
              </div>
  <?php if ($selectedSquad && $squadStats): ?>   <!-- squad form -->           
      <div class="col-6"> <!-- Monster Values -->

    <!-- ROW: Attack Planner -->
      <div class="col-12">
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
              <label><input type="checkbox" name="useFighters" value="1" <?= $useFighters ? 'checked' : '' ?>> Fighters</label>
              <label><input type="checkbox" name="useCreatures" value="1" <?= $useCreatures ? 'checked' : '' ?>> Creatures</label>
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
                <h3><?= htmlspecialchars($squadStats['rarity']) ?> <?= htmlspecialchars($squadStats['name']) ?> | Level <?= $squadStats['level'] ?></h3>
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

              $monsterTotalHealth = $monster['total_health'];
              $monsterHealthList[] = $monsterTotalHealth;
            ?>

            <details class="monster-row">

              <summary class="monster-summary">

                <span class="col col-name">
                <?= htmlspecialchars($monster['name']) ?>
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

    <!-- ROW: Attack Results -->
      <div class="col-6">
        <div class="card">
          <details open class="card-toggle">
            <summary class="card-title">Attack Formation</summary>
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
          </details>
        </div>
      </div>
    </div>

  </div>

  <div class="row">     <!-- Button -->
    <div class="col-12">
      <div class="card">
          <!--Monster Counter Bar -->
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
    <!-- ROW: Saved Attacks -- future use item
    <div class="row">
      <div class="col-12">
        <div class="card">
          <?php /*
          function renderMetricRows($rows, $squads, $captains){
              if(empty($rows)){
                  echo '<p class="muted">No saved attacks</p>';
                  return;
              }

              foreach($rows as $s){
                  echo '<div class="metric-row" data-id="'.$s['squadAttackID'].'">';

                  echo '<select class="lvl">';
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
                  echo '<input class="loss" value="'.htmlspecialchars($s['loss'] ?? '').'">';

                  echo '<span class="actions"><span class="edit">✏️</span><span class="delete">✖</span></span>';
                  echo '</div>';
              }
          } */
          ?>
        </div>
      </div>
    </div>
              -->


  </div>
    <!-- ROW (Spacer) -->
    <div class="row" style="height:40px;"></div>

  <?php endif; ?> <!-- end squad form -->

  </div>
</main>

<script>

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