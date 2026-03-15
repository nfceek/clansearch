<?php
require_once 'includes/db.php';
include 'includes/header.php';

/* -----------------------------
   Load Kingdom + Clan Defaults
------------------------------*/
$defaultKingdom = 265;

$stmt = $pdo->query("SELECT DISTINCT Num FROM Kingdom ORDER BY Num ASC");
$kingdoms = $stmt->fetchAll(PDO::FETCH_COLUMN);

$stmt = $pdo->prepare("
    SELECT name 
    FROM Clans 
    WHERE kingdom = :kingdom 
    ORDER BY name ASC
");
$stmt->execute(['kingdom' => $defaultKingdom]);
$clans = $stmt->fetchAll(PDO::FETCH_COLUMN);
?>

<div class="page-container">
  <div class="search-row">

    <!-- =========================
         CLAN SEARCH
    ========================== -->
    <div class="search-box-clan">
      <form method="GET" action="search_clans.php" id="clanSearchForm" class="search-form">

        <div class="search-box-inner">
          <div class="form-heading"><h3>Search Clans</h3></div>

          <div class="form-inline">
            <label for="kingdom_clan">Kingdom:</label>
            <select id="kingdom_clan" name="kingdom" required>
              <?php foreach ($kingdoms as $k): ?>
                <option value="<?= htmlspecialchars($k) ?>" <?= $k == $defaultKingdom ? 'selected' : '' ?>>
                  <?= htmlspecialchars($k) ?>
                </option>
              <?php endforeach; ?>
            </select>

            <label for="clan_name">Clan Name:</label>
            <select id="clan_name" name="clan_name" required>
              <option value="">Select a Clan...</option>
              <?php foreach ($clans as $clan): ?>
                <option value="<?= htmlspecialchars($clan) ?>">
                  <?= htmlspecialchars($clan) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="form-button">
            <button type="submit">Search</button>
          </div>
        </div>
      </form>
    </div>

    <!-- =========================
         MEMBER SEARCH
    ========================== -->
    <div class="search-box-member">
      <form method="GET" action="search_members.php" id="memberSearchForm" class="search-form">

        <div class="search-box-inner">
          <div class="form-heading"><h3>Search Members</h3></div>

          <div class="form-inline">
            <label for="kingdom_member">Kingdom:</label>
            <select id="kingdom_member" name="kingdom" required>
              <?php foreach ($kingdoms as $k): ?>
                <option value="<?= htmlspecialchars($k) ?>" <?= $k == $defaultKingdom ? 'selected' : '' ?>>
                  <?= htmlspecialchars($k) ?>
                </option>
              <?php endforeach; ?>
            </select>

            <label for="membername">Member Name:</label>
            <input
              type="text"
              id="membername"
              name="membername"
              placeholder="Enter name..."
              required
            >
          </div>

          <div class="form-button">
            <button type="submit">Search</button>
          </div>
        </div>

      </form>
    </div>

  </div>
</div>

<div id="results" class="results-offset"></div>

<?php include 'includes/footer.php'; ?>