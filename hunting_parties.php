<?php 
include 'includes/header.php';
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


