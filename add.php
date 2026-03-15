<?php
require_once 'header.php';
require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'];
    $shortname = $_POST['shortname'];
    $k = $_POST['k'];
    $x = $_POST['x'];
    $y = $_POST['y'];
    $kingdom = $_POST['kingdom'];
    $lvl = $_POST['lvl'];
    $isOpen = $_POST['isOpen'];
    $ROE = $_POST['ROE'];
    $notes = $_POST['notes'];

    $stmt = $conn->prepare("INSERT INTO Clans (name, shortname, k, x, y, kingdom, lvl, isOpen, ROE, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssiiiiisss", $name, $shortname, $k, $x, $y, $kingdom, $lvl, $isOpen, $ROE, $notes);
    $stmt->execute();

    header("Location: index.html");
    exit;
}
?>

<div class="add-form">
  <h2>Add Clan</h2>

  <form method="POST" action="save_clan.php">
    <div class="add-form-row">
      <label for="name">Name:</label>
      <input type="text" name="name" id="name" required>
    </div>

    <div class="add-form-row">
      <label for="shortname">Shortname:</label>
      <input type="text" name="shortname" id="shortname" required>
    </div>

    <div class="add-form-row">
      <label for="kingdom">Kingdom:</label>
      <input type="number" name="kingdom" id="kingdom" required>
    </div>

    <div class="add-form-row">
      <label for="k">K:</label>
      <input type="number" name="k" id="k" required>

      <label for="x">X:</label>
      <input type="number" name="x" id="x" required>

      <label for="y">Y:</label>
      <input type="number" name="y" id="y" required>
    </div>

    <div class="add-form-row">
      <label for="lvl">Level:</label>
      <input type="number" name="lvl" id="lvl" required>
    </div>

    <div class="add-form-row">
      <label for="isOpen">Open:</label>
      <select name="isOpen" id="isOpen">
        <option value="1">Yes</option>
        <option value="0">No</option>
      </select>
    </div>

    <div class="add-form-row">
      <label for="ROE">ROE:</label>
      <input type="text" name="ROE" id="ROE" maxlength="100">
    </div>

    <div class="add-form-row">
      <label for="notes">Notes:</label>
      <textarea name="notes" id="notes"></textarea>
    </div>

    <button type="submit">Add Clan</button>
  </form>
</div>

</body>
</html>
