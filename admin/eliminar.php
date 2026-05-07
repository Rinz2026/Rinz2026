<?php
require_once '../includes/conexion.php';
require_once '../includes/auth.php';
requiereLogin();

$id = intval($_GET['id'] ?? 0);
if ($id) {
    $stmt = $conn->prepare("DELETE FROM anime WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
}

header('Location: index.php');
exit;