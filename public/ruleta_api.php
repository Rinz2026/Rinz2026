<?php
require_once '../includes/conexion.php';
header('Content-Type: application/json');

$genero = trim($_GET['genero'] ?? '');
$anio   = intval($_GET['anio'] ?? 0);
$tipo   = trim($_GET['tipo'] ?? '');
$estado = trim($_GET['estado'] ?? '');

$where = ['1=1', "image != ''", "image IS NOT NULL"];

if ($genero) $where[] = "genres LIKE '%" . $conn->real_escape_string($genero) . "%'";
if ($anio)   $where[] = "year = " . $anio;
if ($tipo)   $where[] = "category = '" . $conn->real_escape_string($tipo) . "'";
if ($estado) $where[] = "status = '" . $conn->real_escape_string($estado) . "'";

$where_sql = implode(' AND ', $where);

$total = $conn->query("SELECT COUNT(*) as c FROM anime WHERE $where_sql")->fetch_assoc()['c'];

if ($total == 0) {
    echo json_encode(['error' => 'No hay animes con esos filtros.']);
    exit;
}

$offset = rand(0, $total - 1);
$result = $conn->query("SELECT id, title, image, status, year, genres, category FROM anime WHERE $where_sql LIMIT 1 OFFSET $offset");
$anime  = $result->fetch_assoc();

$anime['image_is_url'] = filter_var($anime['image'], FILTER_VALIDATE_URL) ? true : false;

// Fix Drive URL
if (!$anime['image_is_url']) {
    $anime['image'] = $anime['image'];
} elseif (preg_match('/\/file\/d\/([a-zA-Z0-9_-]+)/', $anime['image'], $m)) {
    $anime['image'] = 'https://drive.google.com/uc?export=view&id=' . $m[1];
    $anime['image_is_url'] = true;
}

echo json_encode($anime);
$conn->close();
