<?php
// Genera un slug limpio desde un título
// ej: "Vivid Strike!" -> "vivid-strike"
function generarSlug($titulo) {
    $slug = strtolower(trim($titulo));
    // Reemplazar caracteres especiales comunes
    $slug = preg_replace('/[^\x20-\x7E]/', '', $slug); // quitar todo lo que no sea ASCII imprimible
    $slug = preg_replace('/[^a-z0-9\s-]/', '', $slug);
    $slug = preg_replace('/[\s-]+/', '-', $slug);
    $slug = trim($slug, '-');
    // Si el slug queda vacío (título 100% japonés), usar un ID único
    if (empty($slug)) {
        $slug = 'anime-' . uniqid();
    }
    return $slug;
}
 
// Verifica que un slug no esté repetido en la BD
function slugUnico($slug, $conn, $excluir_id = null) {
    if ($excluir_id) {
        $stmt = $conn->prepare("SELECT id FROM anime WHERE slug = ? AND id != ?");
        $stmt->bind_param("si", $slug, $excluir_id);
    } else {
        $stmt = $conn->prepare("SELECT id FROM anime WHERE slug = ?");
        $stmt->bind_param("s", $slug);
    }
    $stmt->execute();
    return $stmt->get_result()->num_rows === 0;
}
 
// Convierte URL de Drive a URL de embed
// ej: https://drive.google.com/file/d/ID/view -> https://drive.google.com/file/d/ID/preview
function driveEmbed($url) {
    return preg_replace('/\/view(\?.*)?$/', '/preview', $url);
}
 
// Sube una imagen y devuelve el nombre del archivo guardado
function subirPortada($file, $carpeta = '../uploads/portadas/') {
    $extensiones = ['jpg', 'jpeg', 'png', 'webp', 'jfif'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
 
    if (!in_array($ext, $extensiones)) return null;
    if ($file['size'] > 5 * 1024 * 1024) return null; // max 5MB
 
    $nombre = uniqid('portada_') . '.' . $ext;
    if (move_uploaded_file($file['tmp_name'], $carpeta . $nombre)) {
        return $nombre;
    }
    return null;
}