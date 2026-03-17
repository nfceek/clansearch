<?php
require_once 'db.php';

$kingdom = isset($_GET['kingdom']) ? (int)$_GET['kingdom'] : 0;
$membername = trim($_GET['membername'] ?? '');

if ($kingdom <= 0 || $membername === '') {
    echo "<p>Please select a kingdom and enter a member name.</p>";
    exit;
}

$stmt = $pdo->prepare("
    SELECT 
        m.*, 
        c.name AS clan_name, 
        c.shortname AS clan_shortname
    FROM members AS m
    LEFT JOIN clans AS c ON m.clan = c.id
    WHERE m.kingdom = :kingdom
      AND m.name LIKE :name
    ORDER BY m.name ASC
    LIMIT 5
");

$stmt->execute([
    'kingdom' => $kingdom,
    'name' => '%' . $membername . '%'
]);

$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!$results) {
    echo "<p>No members found matching '<strong>" . htmlspecialchars($membername, ENT_QUOTES) . "</strong>' in kingdom $kingdom.</p>";
    exit;
}

echo '<div class="member-results">';
foreach ($results as $r) {
    echo '<div class="member-card">';
    echo '<strong>' . htmlspecialchars($r['name']) . '</strong>';
    if (!empty($r['clan_name'])) {
        echo '<br><em>Clan: ' . htmlspecialchars($r['clan_name']) . '</em>';
    }
    echo '<br>Coords: K' . htmlspecialchars($r['kingdom']) . ' X' . htmlspecialchars($r['x']) . ' Y' . htmlspecialchars($r['y']);
    echo '</div>';
}
echo '</div>';
?>
