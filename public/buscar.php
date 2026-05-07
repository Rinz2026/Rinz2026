<?php
require_once '../includes/conexion.php';
header('Content-Type: application/json');

$q = trim($_GET['q'] ?? '');
if (strlen($q) < 2) { echo '[]'; exit; }

$stmt = $conn->prepare("SELECT id, title, image, status, year FROM anime WHERE title LIKE ? LIMIT 6");
$like = '%' . $q . '%';
$stmt->bind_param("s", $like);
$stmt->execute();
$result = $stmt->get_result();

$data = [];
while ($a = $result->fetch_assoc()) {
    $a['image_is_url'] = filter_var($a['image'], FILTER_VALIDATE_URL) ? true : false;
    $data[] = $a;
}

echo json_encode($data);
$conn->close();
