<?php
require_once 'header.php';
require_once 'db.php';

// Fetch clans with PDO
$stmt = $pdo->prepare("SELECT name, shortname, k, x, y, kingdom, lvl, memberList, ROE, notes FROM Clans ORDER BY name ASC");
$stmt->execute();
$clans = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="page-container">
    <h2>Clan List</h2>

<table class="clan-table">
  <thead>
    <tr>
      <th>Name</th>
      <th>Shortname</th>
      <th>K</th>
      <th>X</th>
      <th>Y</th>
      <th>Kingdom</th>
      <th>Member List</th>
      <th>Open</th>
      <th>ROE</th>
      <th>Notes</th>
    </tr>
  </thead>
  <tbody>
    <?php if (!empty($clans)): ?>
      <?php foreach ($clans as $row): ?>
        <tr data-id="<?= esc($row['id'] ?? '') ?>">
        <td><?= esc($row['name']) ?></td>
        <td><?= esc($row['shortname']) ?></td>
        <td><?= esc($row['k']) ?></td>
        <td><?= esc($row['x']) ?></td>
        <td><?= esc($row['y']) ?></td>
        <td><?= esc($row['kingdom']) ?></td>
        <td class="toggle-memberlist" data-value="<?= (int)($row['memberList'] ?? 0) ?>" style="cursor:pointer; text-align:center;">
            <?= ($row['memberList'] ?? 0) ? 'Yes' : 'No' ?>
        </td>
        <td><?= ($row['isOpen'] ?? 0) ? 'Yes' : 'No' ?></td>
        <td><?= esc($row['ROE']) ?></td>
        <td><?= esc($row['notes']) ?></td>
        </tr>
      <?php endforeach; ?>
    <?php else: ?>
      <tr><td colspan="10">No clans found.</td></tr>
    <?php endif; ?>
  </tbody>
</table>

</div>

<script>
document.addEventListener('click', async e => {
  const cell = e.target.closest('.toggle-memberlist');
  if (!cell) return;

  const row = cell.closest('tr');
  const clanId = row.dataset.id;
  let current = parseInt(cell.dataset.value);

  // flip 0 → 1 or 1 → 0
  const newValue = current ? 0 : 1;
  cell.textContent = newValue ? 'Yes' : 'No';
  cell.dataset.value = newValue;
  cell.style.opacity = "0.5";

  try {
    const res = await fetch('update_memberlist.php', {
      method: 'POST',
      headers: {'Content-Type': 'application/x-www-form-urlencoded'},
      body: `id=${encodeURIComponent(clanId)}&memberList=${encodeURIComponent(newValue)}`
    });
    const text = await res.text();
    console.log(text);
    cell.style.opacity = "1";
  } catch (err) {
    console.error('Error updating memberList:', err);
    cell.textContent = current ? 'Yes' : 'No'; // revert
    cell.dataset.value = current;
    cell.style.opacity = "1";
    alert('Failed to update. Try again.');
  }
});
</script>

</body>
</html>
