<?php
require_once '../includes/conexion.php';
require_once '../includes/auth.php';
require_once '../includes/funciones.php';
requiereLogin();

$mensaje = '';
$error   = '';
$anime   = null;
$episodios = [];

$id = intval($_GET['id'] ?? 0);
if ($id) {
    $stmt = $conn->prepare("SELECT * FROM anime WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $anime = $stmt->get_result()->fetch_assoc();

    if ($anime) {
        $stmt2 = $conn->prepare("SELECT * FROM episodios WHERE anime_id = ? ORDER BY episode_number ASC");
        $stmt2->bind_param("i", $id);
        $stmt2->execute();
        $episodios = $stmt2->get_result()->fetch_all(MYSQLI_ASSOC);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id          = intval($_POST['id'] ?? 0);
    $title       = trim($_POST['title'] ?? '');
    $category    = trim($_POST['category'] ?? 'Anime');
    $genres      = trim($_POST['genres'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $status      = $_POST['status'] ?? 'Finalizado';
    $year        = intval($_POST['year'] ?? 0) ?: null;
    $image_url   = trim($_POST['image_url'] ?? '');
    $image_actual = trim($_POST['image_actual'] ?? '');
    $banner_url  = trim($_POST['banner_url'] ?? '');
    $trailer_url = trim($_POST['trailer_url'] ?? '');

    if (!$title || !$id) {
        $error = 'Datos incompletos.';
    } else {
        $slug = generarSlug($title);
        $base = $slug; $i = 1;
        while (!slugUnico($slug, $conn, $id)) {
            $slug = $base . '-' . $i++;
        }

        $image = $image_actual;
        if (!empty($_FILES['image']['name'])) {
            $nueva = subirPortada($_FILES['image'], '../uploads/portadas/');
            if ($nueva) $image = $nueva;
            else $error = 'Error al subir imagen.';
        } elseif ($image_url) {
            $image = $image_url;
        }

        if (!$error) {
            $stmt = $conn->prepare("UPDATE anime SET title=?, slug=?, category=?, genres=?, image=?, banner=?, trailer_url=?, description=?, status=?, year=? WHERE id=?");
            $stmt->bind_param("sssssssssii", $title, $slug, $category, $genres, $image, $banner_url, $trailer_url, $description, $status, $year, $id);
            $stmt->execute();

            $ep_titles = $_POST['episode_title'] ?? [];
$ep_urls   = $_POST['episode_url']   ?? [];

// Obtener episodios existentes con su created_at
$existing = [];
$ex_stmt = $conn->prepare("SELECT episode_number, created_at FROM episodios WHERE anime_id = ?");
$ex_stmt->bind_param("i", $id);
$ex_stmt->execute();
$ex_result = $ex_stmt->get_result();
while ($row = $ex_result->fetch_assoc()) {
    $existing[$row['episode_number']] = $row['created_at'];
}

// Borrar todos y reinsertar conservando created_at original
$del_stmt = $conn->prepare("DELETE FROM episodios WHERE anime_id = ?");
$del_stmt->bind_param("i", $id);
$del_stmt->execute();

$ep_stmt = $conn->prepare("INSERT INTO episodios (anime_id, episode_number, episode_title, episode_url, created_at) VALUES (?,?,?,?,?)");

foreach ($ep_titles as $i => $ep_title) {
    $ep_title = trim($ep_title);
    $ep_url   = trim($ep_urls[$i] ?? '');
    $ep_num   = $i + 1;
    if ($ep_title && $ep_url) {
        // Si el episodio ya existía, conservar su created_at original
        $created = isset($existing[$ep_num]) ? $existing[$ep_num] : date('Y-m-d H:i:s');
        $ep_stmt->bind_param("iisss", $id, $ep_num, $ep_title, $ep_url, $created);
        $ep_stmt->execute();
    }
}
            $mensaje = "Anime actualizado correctamente.";

            $stmt = $conn->prepare("SELECT * FROM anime WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $anime = $stmt->get_result()->fetch_assoc();

            $stmt2 = $conn->prepare("SELECT * FROM episodios WHERE anime_id = ? ORDER BY episode_number ASC");
            $stmt2->bind_param("i", $id);
            $stmt2->execute();
            $episodios = $stmt2->get_result()->fetch_all(MYSQLI_ASSOC);
        }
    }
}

$lista = $conn->query("SELECT id, title FROM anime ORDER BY title ASC");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Anime - Admin</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { background: #0f0f0f; color: #e0e0e0; font-family: 'Segoe UI', sans-serif; min-height: 100vh; }

        .sidebar { position: fixed; top: 0; left: 0; width: 220px; height: 100vh; background: #1a1a1a; border-right: 1px solid #2a2a2a; display: flex; flex-direction: column; padding: 24px 0; }
        .sidebar .logo { font-size: 22px; font-weight: 700; color: #dc2626; letter-spacing: 2px; padding: 0 24px 24px; border-bottom: 1px solid #2a2a2a; }
        .sidebar .logo span { display: block; font-size: 11px; color: #555; font-weight: 400; letter-spacing: .05em; margin-top: 2px; }
        .nav { margin-top: 16px; flex: 1; }
        .nav a { display: flex; align-items: center; gap: 10px; padding: 10px 24px; color: #888; text-decoration: none; font-size: 14px; transition: all .2s; }
        .nav a:hover, .nav a.active { color: #fff; background: #222; border-left: 2px solid #dc2626; }
        .sidebar-footer { padding: 16px 24px; border-top: 1px solid #2a2a2a; }
        .sidebar-footer a { color: #555; font-size: 13px; text-decoration: none; }
        .sidebar-footer a:hover { color: #dc2626; }

        .main { margin-left: 220px; padding: 32px; }
        .page-title { font-size: 22px; font-weight: 600; color: #fff; margin-bottom: 24px; }

        .selector-card { background: #1a1a1a; border: 1px solid #2a2a2a; border-radius: 10px; padding: 20px 24px; margin-bottom: 24px; }
        .selector-card h3 { font-size: 12px; color: #555; text-transform: uppercase; letter-spacing: .05em; margin-bottom: 10px; }
        .selector-row { display: flex; gap: 8px; }
        .selector-row select { flex: 1; padding: 9px 12px; background: #0f0f0f; border: 1px solid #2a2a2a; border-radius: 8px; color: #fff; font-size: 14px; outline: none; }
        .selector-row select:focus { border-color: #dc2626; }
        .btn-cargar { padding: 9px 18px; background: #dc2626; border: none; border-radius: 8px; color: #fff; font-size: 13px; font-weight: 600; cursor: pointer; }
        .btn-cargar:hover { background: #b91c1c; }

        .form-card { background: #1a1a1a; border: 1px solid #2a2a2a; border-radius: 10px; padding: 24px; }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        .field { display: flex; flex-direction: column; gap: 6px; }
        .field.full { grid-column: 1 / -1; }
        .field label { font-size: 11px; color: #555; text-transform: uppercase; letter-spacing: .05em; font-weight: 500; }
        .field input, .field select, .field textarea { padding: 9px 12px; background: #0f0f0f; border: 1px solid #2a2a2a; border-radius: 8px; color: #fff; font-size: 13px; font-family: 'Segoe UI', sans-serif; outline: none; }
        .field input:focus, .field select:focus, .field textarea:focus { border-color: #dc2626; }
        .field textarea { resize: vertical; min-height: 90px; }

        .preview-wrap { display: flex; gap: 16px; align-items: flex-start; }
        #preview-img { width: 80px; height: 110px; object-fit: cover; border-radius: 8px; border: 1px solid #2a2a2a; flex-shrink: 0; }

        .section-label { font-size: 12px; color: #555; text-transform: uppercase; letter-spacing: .05em; font-weight: 500; margin: 24px 0 10px; }
        .ep-list { display: flex; flex-direction: column; gap: 8px; }
        .ep-row { display: flex; gap: 8px; align-items: center; }
        .ep-num { width: 36px; text-align: center; font-size: 12px; color: #555; flex-shrink: 0; }
        .ep-row input { flex: 1; padding: 8px 10px; background: #0f0f0f; border: 1px solid #2a2a2a; border-radius: 8px; color: #fff; font-size: 13px; outline: none; }
        .ep-row input:focus { border-color: #dc2626; }
        .btn-del-ep { padding: 6px 10px; background: rgba(220,38,38,.1); border: 1px solid rgba(220,38,38,.2); border-radius: 6px; color: #f87171; cursor: pointer; font-size: 12px; }
        .btn-del-ep:hover { background: rgba(220,38,38,.2); }
        .btn-add-ep { margin-top: 8px; width: 100%; padding: 8px; background: transparent; border: 1px dashed #2a2a2a; border-radius: 8px; color: #555; cursor: pointer; font-size: 13px; }
        .btn-add-ep:hover { border-color: #444; color: #888; }

        .form-actions { display: flex; gap: 10px; margin-top: 24px; }
        .btn-save { padding: 10px 24px; background: #dc2626; border: none; border-radius: 8px; color: #fff; font-size: 14px; font-weight: 600; cursor: pointer; }
        .btn-save:hover { background: #b91c1c; }
        .btn-secondary { padding: 10px 18px; background: transparent; border: 1px solid #2a2a2a; border-radius: 8px; color: #555; font-size: 14px; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; }
        .btn-secondary:hover { border-color: #444; color: #888; }

        .alert { padding: 12px 16px; border-radius: 8px; font-size: 14px; margin-bottom: 20px; }
        .alert-success { background: rgba(34,197,94,.1); border: 1px solid rgba(34,197,94,.2); color: #4ade80; }
        .alert-error   { background: rgba(220,38,38,.1); border: 1px solid rgba(220,38,38,.2); color: #f87171; }

        .empty-state { text-align: center; padding: 48px; color: #555; font-size: 14px; }
    </style>
</head>
<body>

<aside class="sidebar">
    <div class="logo">RINZ <span>Admin Panel</span></div>
    <nav class="nav">
        <a href="index.php"><span>▦</span> Dashboard</a>
        <a href="agregar.php"><span>＋</span> Agregar anime</a>
        <a href="editar.php" class="active"><span>✎</span> Editar anime</a>
        <a href="importar.php"><span>↓</span> Importar masivo</a>
        <a href="drive_manager.php">↓ Capitulos</a>
        <a href="logout.php?redirect=web" target="_blank"><span>↗</span> Ver web</a>
    </nav>
    <div class="sidebar-footer">
        <a href="logout.php">Cerrar sesión</a>
    </div>
</aside>

<main class="main">
    <div class="page-title">Editar anime</div>

    <?php if ($mensaje): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($mensaje); ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <div class="selector-card">
        <h3>Selecciona el anime a editar</h3>
        <div class="selector-row">
            <input type="text" id="search-anime" placeholder="Busca por nombre..." style="flex:1; padding: 9px 12px; background: #0f0f0f; border: 1px solid #2a2a2a; border-radius: 8px; color: #fff; font-size: 14px; outline: none;" onkeyup="filtrarAnimes()">
        </div>
        <div class="selector-row" style="margin-top: 8px;">
            <select id="anime-select" onchange="irAAnime()" style="flex: 1;">
                <option value="">— Elige un anime —</option>
                <?php while ($a = $lista->fetch_assoc()): ?>
                    <option value="<?php echo $a['id']; ?>" data-title="<?php echo htmlspecialchars($a['title']); ?>" <?php echo ($anime && $anime['id'] == $a['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($a['title']); ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>
    </div>

    <?php if ($anime): ?>
    <div class="form-card">
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="id" value="<?php echo $anime['id']; ?>">
            <input type="hidden" name="image_actual" value="<?php echo htmlspecialchars($anime['image']); ?>">

            <div class="form-grid">
                <div class="field full">
                    <label>Título</label>
                    <input type="text" name="title" value="<?php echo htmlspecialchars($anime['title']); ?>" required>
                </div>

                <div class="field">
                    <label>Categoría</label>
                    <input type="text" name="category" value="<?php echo htmlspecialchars($anime['category']); ?>">
                </div>

                <div class="field">
                    <label>Estado</label>
                    <select name="status">
                        <?php foreach (['Finalizado','En emisión','Próximamente'] as $s): ?>
                            <option <?php echo $anime['status'] === $s ? 'selected' : ''; ?>><?php echo $s; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="field">
                    <label>Año</label>
                    <input type="number" name="year" value="<?php echo $anime['year']; ?>" min="1950" max="2100">
                </div>

                <div class="field">
                    <label>Géneros</label>
                    <input type="text" name="genres" value="<?php echo htmlspecialchars($anime['genres']); ?>">
                </div>

                <div class="field full">
                    <label>Sinopsis</label>
                    <textarea name="description"><?php echo htmlspecialchars($anime['description']); ?></textarea>
                </div>

                <div class="field full">
                    <label>Portada actual — sube nueva o cambia URL</label>
                    <div class="preview-wrap">
                        <?php
                            $img_src = $anime['image'];
                            $es_url  = filter_var($img_src, FILTER_VALIDATE_URL);
                            $src     = $es_url ? $img_src : '../uploads/portadas/' . $img_src;
                        ?>
                        <img id="preview-img" src="<?php echo htmlspecialchars($src); ?>" onerror="this.style.opacity='.3'" alt="portada">
                        <div style="flex:1;display:flex;flex-direction:column;gap:8px">
                            <input type="file" name="image" accept="image/*" onchange="previewLocal(event)">
                            <input type="text" name="image_url" id="f-image-url" placeholder="O pega nueva URL de imagen" value="<?php echo $es_url ? htmlspecialchars($img_src) : ''; ?>">
                        </div>
                    </div>
                </div>

                <div class="field full">
    <label>URL del Banner</label>
    <div class="preview-wrap">
        <img id="preview-banner" src="<?= htmlspecialchars($anime['banner'] ?? '') ?>" 
             alt="banner" 
             style="width:180px;height:60px;object-fit:cover;border-radius:6px;border:1px solid #2a2a2a;<?= empty($anime['banner']) ? 'display:none' : '' ?>">
        <input type="text" id="f-banner-url" name="banner_url" 
               placeholder="https://..." 
               value="<?= htmlspecialchars($anime['banner'] ?? '') ?>"
               oninput="previewBanner(this.value)" style="flex:1">
    </div>
</div>

                <div class="field full">
                    <label>URL del Trailer (YouTube)</label>
                    <input type="text" name="trailer_url" value="<?php echo htmlspecialchars($anime['trailer_url'] ?? ''); ?>" placeholder="https://youtube.com/watch?v=...">
                </div>
            </div>

            <div class="section-label">Episodios</div>
            <div class="ep-list" id="ep-list">
                <?php foreach ($episodios as $i => $ep): ?>
                <div class="ep-row">
                    <span class="ep-num"><?php echo $i + 1; ?></span>
                    <input type="text" name="episode_title[]" value="<?php echo htmlspecialchars($ep['episode_title']); ?>">
                    <input type="text" name="episode_url[]" value="<?php echo htmlspecialchars($ep['episode_url']); ?>">
                    <button type="button" class="btn-del-ep" onclick="eliminarEp(this)">✕</button>
                </div>
                <?php endforeach; ?>
            </div>
            <button type="button" class="btn-add-ep" onclick="agregarEp()">+ Agregar episodio</button>

            <div class="form-actions">
                <button type="submit" class="btn-save">Guardar cambios</button>
                <a href="index.php" class="btn-secondary">Cancelar</a>
            </div>
        </form>
    </div>

    <?php else: ?>
    <div class="empty-state">Selecciona un anime de la lista para editarlo.</div>
    <?php endif; ?>
</main>

<script>
let epCount = <?php echo count($episodios); ?>;

function irAAnime() {
    const id = document.getElementById('anime-select').value;
    if (id) window.location.href = 'editar.php?id=' + id;
}

function filtrarAnimes() {
    const search = document.getElementById('search-anime').value.toLowerCase();
    const options = document.querySelectorAll('#anime-select option');
    
    options.forEach(opt => {
        if (opt.value === '') return; // No filtrar la opción vacía
        const title = opt.getAttribute('data-title').toLowerCase();
        opt.style.display = title.includes(search) ? '' : 'none';
    });
}

function agregarEp() {
    epCount++;
    const row = document.createElement('div');
    row.className = 'ep-row';
    row.innerHTML = `
        <span class="ep-num">${epCount}</span>
        <input type="text" name="episode_title[]" placeholder="Episodio ${epCount}">
        <input type="text" name="episode_url[]" placeholder="https://drive.google.com/...">
        <button type="button" class="btn-del-ep" onclick="eliminarEp(this)">✕</button>`;
    document.getElementById('ep-list').appendChild(row);
    renumerar();
}

function eliminarEp(btn) {
    btn.closest('.ep-row').remove();
    renumerar();
}

function renumerar() {
    document.querySelectorAll('.ep-num').forEach((el, i) => el.textContent = i + 1);
    epCount = document.querySelectorAll('.ep-row').length;
}

function previewLocal(e) {
    const file = e.target.files[0];
    if (!file) return;
    const pi = document.getElementById('preview-img');
    pi.src = URL.createObjectURL(file);
    document.getElementById('f-image-url').value = '';
}
function previewBanner(url) {
    const img = document.getElementById('preview-banner');
    if (url) {
        // Convertir URL de Drive al formato correcto
        const match = url.match(/\/file\/d\/([a-zA-Z0-9_-]+)/);
        if (match) {
            url = 'https://drive.google.com/uc?export=view&id=' + match[1];
        }
        img.src = url;
        img.style.display = 'block';
    } else {
        img.style.display = 'none';
    }
}
</script>
</body>
</html>
<?php $conn->close(); ?>