<?php
require 'db.php';

$type = $_GET['type']; // 'members' or 'clans'
$field = $_GET['field'] ?? 'shortname';
$value = $_GET['value'] ?? '';

if ($type === 'members') {
    $stmt = $conn->prepare("SELECT * FROM Members WHERE $field LIKE ?");
} else {
    $stmt = $conn->prepare("SELECT * FROM Clans WHERE $field LIKE ?");
}
$search = "%" . $value . "%";
$stmt->bind_param("s", $search);
$stmt->execute();
$result = $stmt->get_result();

$data = [];
while ($row = $result->fetch_assoc()) {
    $data[] = $row;
}
echo json_encode($data);
