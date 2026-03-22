<?php
    include __DIR__ . '/includes/header.php';

$kingdom = $_GET['kingdom'] ?? '';
$membername = trim($_GET['membername'] ?? '');

if (empty($kingdom) && empty($membername)) {
    echo "<p>Please enter search criteria.</p>";
    exit;
}

$query = "
    SELECT 
        m.*, 
        c.name AS clan_name, 
        c.shortname AS clan_shortname
    FROM members AS m
    LEFT JOIN clans AS c ON m.clan = c.id
    WHERE 1=1
";
$params = [];

if (!empty($kingdom)) {
    $query .= " AND m.kingdom = :kingdom";
    $params['kingdom'] = $kingdom;
}

if (!empty($membername)) {
    $query .= " AND m.name LIKE :membername";
    $params['membername'] = "%$membername%";
}

$query .= " ORDER BY m.name ASC LIMIT 5";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$members = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!$members) {
    echo "<p>No members found matching your search.</p>";
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Member Search Results</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">

<style>
  body { font-family: Arial, sans-serif; background: #f7f7f7; color: #222; }

  .result-row {
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 12px;
    margin-bottom: 16px;
  }

  .result-inline {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    align-items: center;
  }

  .field {
    display: flex;
    flex-direction: column;
    min-width: 140px;
    font-size: 14px;
  }

  .coords-inline {
    display: flex;
    align-items: center;
    gap: 4px;
  }

  .coord-input {
    width: 48px;
    padding: 4px;
    border: 1px solid #ccc;
    border-radius: 4px;
    text-align: center;
  }

  .coord-input[readonly] {
    background: transparent;
    border-color: transparent;
  }

  .edit-btn {
    border: none;
    background: transparent;
    cursor: pointer;
    font-size: 16px;
  }

  .highlight-success {
    background-color: #e6ffed;
    transition: background 0.4s;
  }

  /* ---- Voting ---- */
  .member-voting {
    margin-top: 10px;
    padding: 12px;
    border: 1px solid #ccc;
    border-radius: 8px;
    background: #fafafa;
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 10px 20px;
  }

  .vote-item {
    display: flex;
    align-items: center;
    gap: 8px;
  }

  .vote-item span { min-width: 120px; font-weight: 600; color: #333; }

  .stars i {
    color: #bbb;
    cursor: pointer;
    transition: color 0.2s;
  }

  .stars i.active, .stars i:hover { color: gold; }

  .submit-vote {
    grid-column: 1 / span 2;
    justify-self: center;
    padding: 6px 12px;
    border: none;
    background: #2d6cdf;
    color: #fff;
    border-radius: 4px;
    cursor: pointer;
    font-weight: 600;
  }

  /* ---- Bounty ---- */
  .bounty-section {
    margin-top: 12px;
    padding: 12px;
    border: 1px solid #ccc;
    border-radius: 8px;
    background: #fafafa;
  }

  .bounty-section h4 {
    margin-top: 0;
    margin-bottom: 8px;
  }

  .bounty-form {
    display: flex;
    flex-wrap: wrap;
    gap: 10px 16px;
  }

  .bounty-form label {
    display: block;
    font-size: 0.9em;
    margin-bottom: 3px;
  }

  .bounty-form input, .bounty-form textarea {
    padding: 6px 8px;
    border: 1px solid #ccc;
    border-radius: 4px;
    font-size: 0.95em;
  }

  .bounty-form textarea {
    flex: 1 1 100%;
    resize: vertical;
  }

  .bounty-submit {
    background: #2d6cdf;
    color: #fff;
    border: none;
    border-radius: 4px;
    padding: 6px 12px;
    cursor: pointer;
    font-weight: 600;
  }

  /* ---- Clear Button ---- */
  .result-actions {
    text-align: right;
    margin-top: 20px;
  }

  .clear-btn {
    background: #8b0000;
    color: #fff;
    border: none;
    border-radius: 4px;
    padding: 6px 12px;
    cursor: pointer;
  }

  
</style>
</head>

<body>
<h3>Member Search Results</h3>

<?php foreach ($members as $row): ?>
  <div class="result-row" data-id="<?= htmlspecialchars($row['id']) ?>">
    <div class="result-inline">
      <div class="field"><strong>Name</strong>
        <input type="text" value="<?= htmlspecialchars($row['name'] ?? '') ?>" readonly>
      </div>
      <div class="field"><strong>Kingdom</strong>
        <input type="text" value="<?= htmlspecialchars($row['kingdom'] ?? '') ?>" readonly>
      </div>
      <div class="field"><strong>Clan</strong>
        <?php 
          $clanDisplay = trim(($row['clan_name'] ?? '') . 
                              (!empty($row['clan_shortname']) ? " (" . $row['clan_shortname'] . ")" : ''));
        ?>
        <input type="text" value="<?= htmlspecialchars($clanDisplay) ?>" readonly>
      </div>

      <div class="field">
        <strong>Coords</strong>
        <div class="coords-inline">
          <label>K</label><input class="coord-input" name="k" value="<?= htmlspecialchars($row['k'] ?? '') ?>" readonly>
          <label>X</label><input class="coord-input" name="x" value="<?= htmlspecialchars($row['x'] ?? '') ?>" readonly>
          <label>Y</label><input class="coord-input" name="y" value="<?= htmlspecialchars($row['y'] ?? '') ?>" readonly>
          <button type="button" class="edit-btn" title="Edit Coords">🖉</button>
        </div>
      </div>
    </div>

<!-- ⭐ + 💰 Inline Container -->
<div class="member-actions-inline">
  
  <!-- ⭐ Voting -->
  <div class="member-voting" data-member-id="<?= $row['id'] ?>">
    <h4>⭐ Member Voting</h4>
    <div class="vote-item">
      <span>Fair Player</span>
      <div class="stars" data-field="fair_player">
        <?php for ($i=1; $i<=5; $i++): ?><i class="fa fa-star" data-value="<?= $i ?>"></i><?php endfor; ?>
      </div>
    </div>

    <div class="vote-item">
      <span>Support Others</span>
      <div class="stars" data-field="support_others">
        <?php for ($i=1; $i<=5; $i++): ?><i class="fa fa-star" data-value="<?= $i ?>"></i><?php endfor; ?>
      </div>
    </div>

    <div class="vote-item">
      <span>Would Have a Mead</span>
      <div class="stars" data-field="mead_with_player">
        <?php for ($i=1; $i<=5; $i++): ?><i class="fa fa-star" data-value="<?= $i ?>"></i><?php endfor; ?>
      </div>
    </div>

    <button class="submit-vote">Submit Vote</button>
  </div>

  <!-- 💰 Bounty -->
  <div class="bounty-section">
    <h4>💰 Place Bounty</h4>
    <form class="bounty-form" data-member-id="<?= $row['id'] ?>">
      <div>
        <label>Posting Member:</label>
        <input type="text" name="posting_member" placeholder="Your Member ID" required>
      </div>

      <div>
        <label>Bounty Amount:</label>
        <input type="number" name="bounty_amount" step="0.01" placeholder="Gold" required>
      </div>

      <div>
        <label>Reason:</label>
        <input type="text" name="reason" placeholder="Reason for bounty" required>
      </div>

      <div class="date-range">
        <div>
          <label>Start Date:</label>
          <input type="date" name="start_date" required>
        </div>
        <div>
          <label>End Date:</label>
          <input type="date" name="end_date">
        </div>
      </div>

      <textarea name="notes" placeholder="Optional notes..."></textarea>
      <button type="submit" class="bounty-submit">Post Bounty</button>
    </form>
  </div>

</div>

<?php endforeach; ?>

<div class="result-actions">
  <button class="clear-btn" onclick="document.body.innerHTML=''">Clear Results</button>
</div>

<script>
// Coordinate edit/save logic (same as before)
document.addEventListener('click', async e => {
  const btn = e.target.closest('.edit-btn');
  if (!btn) return;
  const row = btn.closest('.result-row');
  const inputs = row.querySelectorAll('.coord-input');
  const inEdit = btn.dataset.mode === 'edit';

  if (!inEdit) {
    btn.dataset.mode = 'edit';
    btn.textContent = '💾';
    inputs.forEach(i => { i.removeAttribute('readonly'); i.focus(); });
    return;
  }

  const [k,x,y] = [...inputs].map(i=>i.value.trim());
  const id = row.dataset.id;
  const res = await fetch('update_coords.php', {
    method:'POST',
    headers:{'Content-Type':'application/x-www-form-urlencoded'},
    body:`id=${id}&k=${k}&x=${x}&y=${y}`
  });
  if(res.ok){
    row.classList.add('highlight-success');
    setTimeout(()=>row.classList.remove('highlight-success'),1000);
  }
  btn.dataset.mode='';
  btn.textContent='🖉';
  inputs.forEach(i=>i.setAttribute('readonly',true));
});

// ⭐ Voting
document.addEventListener('click', e => {
  if (e.target.matches('.stars i')) {
    const star = e.target;
    const stars = star.parentElement.querySelectorAll('i');
    const val = parseInt(star.dataset.value);
    stars.forEach(s => s.classList.toggle('active', parseInt(s.dataset.value) <= val));
    star.parentElement.dataset.selected = val;
  }
});

document.addEventListener('click', e => {
  if (!e.target.matches('.submit-vote')) return;
  const wrap = e.target.closest('.member-voting');
  const id = wrap.dataset.memberId;
  const fair = wrap.querySelector('[data-field="fair_player"]').dataset.selected || 0;
  const support = wrap.querySelector('[data-field="support_others"]').dataset.selected || 0;
  const mead = wrap.querySelector('[data-field="mead_with_player"]').dataset.selected || 0;
  if (fair==0||support==0||mead==0) return alert('Rate all before submitting');
  const data = new FormData();
  data.append('member_id', id);
  data.append('fair_player', fair);
  data.append('support_others', support);
  data.append('mead_with_player', mead);
  fetch('submit_vote.php',{method:'POST',body:data}).then(r=>r.text()).then(alert);
});
</script>
</body>
</html>
