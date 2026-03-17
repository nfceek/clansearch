<?php
include 'db.php'; // your connection file

$input = json_decode(file_get_contents("php://input"), true);

if (!$input || empty($input['id']) || empty($input['type'])) {
    http_response_code(400);
    echo "Invalid input.";
    exit;
}

$id = (int)$input['id'];
$type = $input['type'];
$data = $input['data'];

if ($type === "clan") {
    $stmt = $conn->prepare("UPDATE Clans SET k=?, x=?, y=?, lvl=?, notes=? WHERE id=?");
   $stmt->bind_param("iiissi", $data['k'], $data['x'], $data['y'], $data['rank'], $data['notes'], $id);
} elseif ($type === "member") {
    $stmt = $conn->prepare("UPDATE Members SET lvl=?, notes=? WHERE id=?");
    $stmt->bind_param("ssi", $data['rank'], $data['notes'], $id);
} else {
    echo "Invalid type.";
    exit;
}

if ($stmt->execute()) {
    echo ucfirst($type) . " updated successfully.";
} else {
    echo "Error updating " . $type . ": " . $stmt->error;
}

$stmt->close();
$conn->close();
?>
