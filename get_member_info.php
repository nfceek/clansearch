<?php
require_once 'db.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    echo '<p>Invalid member.</p>';
    exit;
}

$stmt = $pdo->prepare("
    SELECT m.name, m.lvl, m.k, m.x, m.y, m.clan, c.name AS clan_name, c.shortname AS clan_shortname
    FROM members AS m
    LEFT JOIN clans AS c ON m.clan = c.id
    WHERE m.id = :id
");
$stmt->execute(['id' => $id]);
$member = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$member) {
    echo '<p>Member not found.</p>';
    exit;
}

$name = htmlspecialchars($member['name'] ?? '', ENT_QUOTES);
$lvl = htmlspecialchars($member['lvl'] ?? '—', ENT_QUOTES);
$k = htmlspecialchars($member['k'] ?? '—', ENT_QUOTES);
$x = htmlspecialchars($member['x'] ?? '—', ENT_QUOTES);
$y = htmlspecialchars($member['y'] ?? '—', ENT_QUOTES);
$clan = htmlspecialchars($member['clan_name'] ?? '', ENT_QUOTES);
$short = htmlspecialchars($member['clan_shortname'] ?? '', ENT_QUOTES);

echo '<div class="member-info">';
echo '  <div><strong>Name:</strong> ' . $name . '</div>';
echo '  <div><strong>Level:</strong> ' . $lvl . '</div>';
echo '  <div><strong>Coords:</strong> K: ' . $k . ' X: ' . $x . ' Y: ' . $y . '</div>';
echo '  <div><strong>Clan:</strong> ' . $clan . ' (' . $short . ')</div>';
echo '</div>';
