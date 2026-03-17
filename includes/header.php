<?php
require_once 'db.php';

// Fetch all kingdoms for dropdown
$kingdoms = $pdo->query("SELECT DISTINCT Num FROM Kingdom ORDER BY Num ASC")->fetchAll(PDO::FETCH_COLUMN);
$defaultKingdom = 265;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Clan Management</title>
  <link rel="stylesheet" href="/css/styles.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>⚔️</text></svg>">
</head>
<body>

  <!-- Header Image -->
  <div class="header-image">
    <img src="/images/site-header-2.png" alt="Clan Management Header" style="width:100%; max-height:150px; object-fit:cover;">
  </div>

  <!-- Navigation Bar -->
  <nav class="navbar">
    <div class="nav-left">
      <a href="index.php" class="nav-link">Home</a>
      <!--<a href="add.php" class="nav-link">Add Record</a>-->

      <!-- Full Clan List Form 
      <form method="GET" action="clan_list.php" class="nav-inline-form">
        <label for="kingdom" class="nav-label">Full Clan List:</label>
        <select id="kingdom" name="kingdom" class="nav-select" required>
          <?php foreach ($kingdoms as $k): ?>
            <option value="<?= htmlspecialchars($k) ?>" <?= $k == $defaultKingdom ? 'selected' : '' ?>>
              <?= htmlspecialchars($k) ?>
            </option>
          <?php endforeach; ?>
        </select>
        <button type="submit" class="nav-btn">View</button>
      </form>
      -->
    </div>

    <div class="nav-right">
      <div class="menu">
        <button class="menu-btn">Menu ▾</button>
        <div class="menu-content">
          <!-- you can add user settings or admin links here later -->
          <a href="/monster_hunt.php">⚔️ Monster Hunt</a>
          <!--<a href="/monster_editor.php">👹 Monster Editor </a>
          <a href="/squad_editor.php">🪖 Squad Editor </a> 
          <a href="/matrix_data.php">🐲 Matrix Data </a>  
          <a href="">🎯Monster_hunt </a>     -->    
          <a href="member_dashboard.php">👥 Manage Members</a>
          <a href="#">🧬 Settings</a>
        </div>
      </div>
    </div>
  </nav>

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
</script>
