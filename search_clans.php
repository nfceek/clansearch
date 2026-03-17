<?php
require_once 'includes/db.php';

$name = trim($_GET['clan_name'] ?? '');
if ($name === '') {
    echo "<p>No clan name provided.</p>";
    exit;
}

// Fetch matching clans (limit to a few to prevent overload)
$stmt = $pdo->prepare("SELECT * FROM Clans WHERE name LIKE :name ORDER BY name ASC LIMIT 5");
$stmt->execute(['name' => "%$name%"]);
$clans = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($clans)) {
    echo "<p>No clans found for '<strong>" . htmlspecialchars($name, ENT_QUOTES) . "</strong>'.</p>";
    echo '<button class="clear-btn" onclick="document.getElementById(\'results\').innerHTML = \'\'">Clear</button>';
    exit;
}

echo "<h3>Clan Results</h3>";

foreach ($clans as $row) {
    $clanId = (int)$row['id'];

    echo '<div class="result-row">';
    echo '  <div class="result-inline">';
    echo '    <span class="field"><strong>Kingdom:</strong> <input type="text" value="' . htmlspecialchars($row['kingdom'] ?? '', ENT_QUOTES) . '" readonly></span>';
    echo '    <span class="field"><strong>Name:</strong> <input type="text" value="' . htmlspecialchars($row['name'] ?? '', ENT_QUOTES) . '" readonly></span>';
    echo '    <span class="field"><strong>Shortname:</strong> <input type="text" value="' . htmlspecialchars($row['shortname'] ?? '', ENT_QUOTES) . '" readonly></span>';
    echo '    <span class="field"><strong>K:</strong> <input type="text" value="' . htmlspecialchars($row['k'] ?? '', ENT_QUOTES) . '" readonly></span>';
    echo '    <span class="field"><strong>X:</strong> <input type="text" value="' . htmlspecialchars($row['x'] ?? '', ENT_QUOTES) . '" readonly></span>';
    echo '    <span class="field"><strong>Y:</strong> <input type="text" value="' . htmlspecialchars($row['y'] ?? '', ENT_QUOTES) . '" readonly></span>';
    echo '  </div>';

    // === Fetch and display members ===
    $memberStmt = $pdo->prepare("SELECT name, k, x, y FROM members WHERE clan = :clan_id ORDER BY name ASC");
    $memberStmt->execute(['clan_id' => $clanId]);
    $members = $memberStmt->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($members)) {
        echo '<div class="member-results">';
        echo '<h4>Members</h4>';
        echo '<div class="member-grid">';
        foreach ($members as $m) {
            $name = htmlspecialchars($m['name'] ?? '', ENT_QUOTES);
            $k = htmlspecialchars($m['k'] ?? '-', ENT_QUOTES);
            $x = htmlspecialchars($m['x'] ?? '-', ENT_QUOTES);
            $y = htmlspecialchars($m['y'] ?? '-', ENT_QUOTES);
            echo '<div class="member-item">';
            echo "<strong>{$name}</strong><br>Coords: K: {$k} X: {$x} Y: {$y}";
            echo '</div>';
        }
        echo '</div>';
        echo '</div>';
    } else {
        echo '<div class="member-results"><em>No members found for this clan.</em></div>';
    }

    echo '</div>';
}

// === Clear button at bottom ===
echo '<div class="results-clear" style="text-align:center; margin-top:10px;">';
echo '<button class="clear-btn" onclick="document.getElementById(\'results\').innerHTML = \'\'">Clear</button>';
echo '</div>';
?>
