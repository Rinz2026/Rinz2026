<?php
require_once '../includes/conexion.php';
require_once '../includes/auth.php';
require_once '../includes/funciones.php';
requiereLogin();

$mensaje = '';
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title       = trim($_POST['title'] ?? '');
    $category    = trim($_POST['category'] ?? 'Anime');
    $genres      = trim($_POST['genres'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $status      = $_POST['status'] ?? 'Finalizado';
    $year        = intval($_POST['year'] ?? 0) ?: null;
    $mal_id      = intval($_POST['mal_id'] ?? 0) ?: null;
    $image_url   = trim($_POST['image_url'] ?? '');
    $banner_url  = trim($_POST['banner_url'] ?? '');
    $trailer_url = trim($_POST['trailer_url'] ?? '');

    if (!$title) {
        $error = 'El título es obligatorio.';
    } else {
        $slug = generarSlug($title);
        $base = $slug; $i = 1;
        while (!slugUnico($slug, $conn)) {
            $slug = $base . '-' . $i++;
        }

        $image = '';
        if (!empty($_FILES['image']['name'])) {
            $image = subirPortada($_FILES['image'], '../uploads/portadas/');
            if (!$image) $error = 'Error al subir la imagen. Solo JPG, PNG, WEBP (max 5MB).';
        } elseif ($image_url) {
            $image = $image_url;
        }

        if (!$error) {
            $stmt = $conn->prepare("INSERT INTO anime (title, slug, category, genres, image, banner, trailer_url, description, status, year, mal_id) VALUES (?,?,?,?,?,?,?,?,?,?,?)");
            $stmt->bind_param("sssssssssii", $title, $slug, $category, $genres, $image, $banner_url, $trailer_url, $description, $status, $year, $mal_id);

            if ($stmt->execute()) {
                $anime_id = $conn->insert_id;
                $ep_titles = $_POST['episode_title'] ?? [];
                $ep_urls   = $_POST['episode_url']   ?? [];
                $ep_stmt   = $conn->prepare("INSERT INTO episodios (anime_id, episode_number, episode_title, episode_url) VALUES (?,?,?,?)");

                foreach ($ep_titles as $i => $ep_title) {
                    $ep_title = trim($ep_title);
                    $ep_url   = trim($ep_urls[$i] ?? '');
                    $ep_num   = $i + 1;
                    if ($ep_title && $ep_url) {
                        $ep_stmt->bind_param("iiss", $anime_id, $ep_num, $ep_title, $ep_url);
                        $ep_stmt->execute();
                    }
                }
                $mensaje = "Anime \"$title\" guardado correctamente.";
            } else {
                $error = 'Error al guardar: ' . $conn->error;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agregar Anime - Admin</title>
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
        .api-search { background: #1a1a1a; border: 1px solid #2a2a2a; border-radius: 10px; padding: 20px 24px; margin-bottom: 24px; }
        .api-search h3 { font-size: 14px; color: #888; text-transform: uppercase; letter-spacing: .05em; margin-bottom: 12px; }
        .api-row { display: flex; gap: 8px; }
        .api-row input { flex: 1; padding: 9px 14px; background: #0f0f0f; border: 1px solid #2a2a2a; border-radius: 8px; color: #fff; font-size: 14px; outline: none; }
        .api-row input:focus { border-color: #dc2626; }
        .api-row select { padding: 9px 12px; background: #0f0f0f; border: 1px solid #2a2a2a; border-radius: 8px; color: #888; font-size: 13px; outline: none; }
        .btn-search { padding: 9px 18px; background: #dc2626; border: none; border-radius: 8px; color: #fff; font-size: 13px; font-weight: 600; cursor: pointer; white-space: nowrap; }
        .btn-search:hover { background: #b91c1c; }
        #search-status { font-size: 12px; color: #555; margin-top: 8px; min-height: 16px; }
        #results-list { display: flex; flex-direction: column; gap: 6px; margin-top: 10px; max-height: 240px; overflow-y: auto; }
        .result-item { display: flex; align-items: center; gap: 12px; padding: 8px 10px; border: 1px solid #2a2a2a; border-radius: 8px; cursor: pointer; background: #0f0f0f; transition: all .15s; }
        .result-item:hover { border-color: #dc2626; background: #1a1a1a; }
        .result-item img { width: 36px; height: 50px; object-fit: cover; border-radius: 4px; flex-shrink: 0; }
        .result-info .rtitle { font-size: 13px; font-weight: 600; color: #fff; }
        .result-info .rmeta  { font-size: 12px; color: #555; margin-top: 2px; }
        .result-info .rbanner { font-size: 11px; color: #4ade80; margin-top: 2px; }
        .form-card { background: #1a1a1a; border: 1px solid #2a2a2a; border-radius: 10px; padding: 24px; }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        .field { display: flex; flex-direction: column; gap: 6px; }
        .field.full { grid-column: 1 / -1; }
        .field label { font-size: 11px; color: #555; text-transform: uppercase; letter-spacing: .05em; font-weight: 500; }
        .field input, .field select, .field textarea { padding: 9px 12px; background: #0f0f0f; border: 1px solid #2a2a2a; border-radius: 8px; color: #fff; font-size: 13px; font-family: 'Segoe UI', sans-serif; outline: none; }
        .field input:focus, .field select:focus, .field textarea:focus { border-color: #dc2626; }
        .field textarea { resize: vertical; min-height: 90px; }
        .field select option { background: #1a1a1a; }
        .preview-wrap { display: flex; gap: 16px; align-items: flex-start; }
        #preview-img { width: 80px; height: 110px; object-fit: cover; border-radius: 8px; border: 1px solid #2a2a2a; display: none; flex-shrink: 0; }
        #preview-banner { width: 180px; height: 60px; object-fit: cover; border-radius: 6px; border: 1px solid #2a2a2a; display: none; flex-shrink: 0; }
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
        .btn-reset { padding: 10px 18px; background: transparent; border: 1px solid #2a2a2a; border-radius: 8px; color: #555; font-size: 14px; cursor: pointer; }
        .btn-reset:hover { border-color: #444; color: #888; }
        .alert { padding: 12px 16px; border-radius: 8px; font-size: 14px; margin-bottom: 20px; }
        .alert-success { background: rgba(34,197,94,.1); border: 1px solid rgba(34,197,94,.2); color: #4ade80; }
        .alert-error   { background: rgba(220,38,38,.1); border: 1px solid rgba(220,38,38,.2); color: #f87171; }
    </style>
</head>
<body>

<aside class="sidebar">
    <div class="logo">RINZ <span>Admin Panel</span></div>
    <nav class="nav">
        <a href="index.php">▦ Dashboard</a>
        <a href="agregar.php" class="active">＋ Agregar anime</a>
        <a href="editar.php">✎ Editar anime</a>
        <a href="importar.php">↓ Importar masivo</a>
        <a href="drive_manager.php">↓ Capitulos</a>
        <a href="logout.php?redirect=web" target="_blank">↗ Ver web</a>
    </nav>
    <div class="sidebar-footer">
        <a href="logout.php">Cerrar sesión</a>
    </div>
</aside>

<main class="main">
    <div class="page-title">Agregar anime</div>

    <?php if ($mensaje): ?>
        <div class="alert alert-success"><?= htmlspecialchars($mensaje) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="api-search">
        <h3>Buscar en API para autocompletar</h3>
        <div class="api-row">
            <input type="text" id="api-input" placeholder="ej: Vivid Strike, Uma Musume..." />
            <select id="api-select">
                <option value="jikan">Jikan (MAL)</option>
                <option value="anilist">AniList</option>
                <option value="ambas">Ambas</option>
            </select>
            <button class="btn-search" onclick="buscarAPI()">Buscar</button>
        </div>
        <div id="search-status"></div>
        <div id="results-list"></div>
    </div>

    <div class="form-card">
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" id="f-mal-id" name="mal_id" value="">
            <div class="form-grid">
                <div class="field full">
                    <label>Título</label>
                    <input type="text" id="f-title" name="title" required placeholder="Título del anime">
                </div>
                <div class="field">
                    <label>Categoría</label>
                    <input type="text" id="f-category" name="category" value="Anime">
                </div>
                <div class="field">
                    <label>Estado</label>
                    <select id="f-status" name="status">
                        <option>Finalizado</option>
                        <option>En emisión</option>
                        <option>Próximamente</option>
                    </select>
                </div>
                <div class="field">
                    <label>Año</label>
                    <input type="number" id="f-year" name="year" placeholder="2024" min="1950" max="2100">
                </div>
                <div class="field">
                    <label>Géneros</label>
                    <input type="text" id="f-genres" name="genres" placeholder="Acción, Romance, Deportes...">
                </div>
                <div class="field full">
                    <label>Sinopsis</label>
                    <textarea id="f-desc" name="description" placeholder="Sinopsis del anime..."></textarea>
                </div>
                <div class="field full">
                    <label>Portada — sube un archivo o usa la URL de la API</label>
                    <div class="preview-wrap">
                        <img id="preview-img" src="" alt="preview">
                        <div style="flex:1;display:flex;flex-direction:column;gap:8px">
                            <input type="file" name="image" id="f-image-file" accept="image/*" onchange="previewLocal(event)">
                            <input type="text" id="f-image-url" name="image_url" placeholder="O pega URL de imagen (de la API)">
                        </div>
                    </div>
                </div>
                <div class="field full">
                    <label>URL del Banner</label>
                    <div class="preview-wrap">
                        <img id="preview-banner" src="" alt="banner">
                        <input type="text" id="f-banner-url" name="banner_url" placeholder="https://..." oninput="previewBanner(this.value)" style="flex:1">
                    </div>
                </div>
                <div class="field full">
                    <label>URL del Trailer (YouTube)</label>
                    <input type="text" id="f-trailer-url" name="trailer_url" placeholder="https://youtube.com/watch?v=...">
                </div>
            </div>

            <div class="section-label">Episodios (URL de Google Drive)</div>
            <div class="ep-list" id="ep-list">
                <div class="ep-row">
                    <span class="ep-num">1</span>
                    <input type="text" name="episode_title[]" placeholder="Episodio 1">
                    <input type="text" name="episode_url[]" placeholder="https://drive.google.com/...">
                    <button type="button" class="btn-del-ep" onclick="eliminarEp(this)">✕</button>
                </div>
            </div>
            <button type="button" class="btn-add-ep" onclick="agregarEp()">+ Agregar episodio</button>

            <div class="form-actions">
                <button type="submit" class="btn-save">Guardar anime</button>
                <button type="reset" class="btn-reset" onclick="resetExtra()">Reset</button>
            </div>
        </form>
    </div>
</main>

<script>
let epCount = 1;

async function buscarAPI() {
    const q   = document.getElementById('api-input').value.trim();
    const api = document.getElementById('api-select').value;
    if (!q) return;

    const st   = document.getElementById('search-status');
    const list = document.getElementById('results-list');
    st.textContent = 'Buscando...';
    list.innerHTML = '';

    let resultados = [];

    try {
        if (api === 'jikan' || api === 'ambas') {
            const r = await fetch(`https://api.jikan.moe/v4/anime?q=${encodeURIComponent(q)}&limit=5`);
            const d = await r.json();
            if (d.data) resultados.push(...d.data.map(a => ({
                fuente:   'MAL',
                id:       a.mal_id,
                title:    a.title,
                year:     a.aired?.prop?.from?.year || '',
                status:   traducirStatus(a.status),
                genres:   (a.genres || []).map(g => g.name).join(', '),
                image:    a.images?.jpg?.image_url || '',
                desc:     a.synopsis || '',
                category: traducirFormato(a.type),
                banner:   '',
                trailer:  ''
            })));
        }

        if (api === 'anilist' || api === 'ambas') {
            const query = `{Page(page:1,perPage:5){media(search:"${q}",type:ANIME){id title{romaji} coverImage{extraLarge large} bannerImage startDate{year} status genres description format}}}`;
            const r = await fetch('https://graphql.anilist.co', {
                method: 'POST',
                headers: {'Content-Type':'application/json'},
                body: JSON.stringify({query})
            });
            const d = await r.json();
            if (d.data?.Page?.media) resultados.push(...d.data.Page.media.map(a => ({
                fuente:   'AL',
                id:       a.id,
                title:    a.title.romaji,
                year:     a.startDate?.year || '',
                status:   traducirStatusAL(a.status),
                genres:   (a.genres || []).join(', '),
                image:    a.coverImage?.extraLarge || a.coverImage?.large || '',
                desc:     (a.description || '').replace(/<[^>]+>/g, ''),
                category: traducirFormato(a.format),
                banner:   a.bannerImage || '',
                trailer:  ''
            })));
        }

        const vistos = new Set();
        resultados = resultados.filter(a => {
            if (vistos.has(a.title)) return false;
            vistos.add(a.title); return true;
        });

        st.textContent = resultados.length ? `${resultados.length} resultados` : 'Sin resultados.';

        resultados.forEach(a => {
            const item = document.createElement('div');
            item.className = 'result-item';
            item.innerHTML = `
                <img src="${a.image}" onerror="this.style.display='none'">
                <div class="result-info">
                    <div class="rtitle">${a.title} <span style="font-size:11px;color:#555">[${a.fuente}]</span></div>
                    <div class="rmeta">${a.year || '—'} · ${a.category} · ${a.status}</div>
                    ${a.banner ? '<div class="rbanner">✓ Banner disponible</div>' : ''}
                </div>`;
            item.onclick = () => llenar(a);
            list.appendChild(item);
        });

    } catch(e) {
        st.textContent = 'Error al buscar. Revisa tu conexión.';
    }
}

function llenar(a) {
    document.getElementById('f-title').value      = a.title;
    document.getElementById('f-desc').value       = a.desc;
    document.getElementById('f-year').value       = a.year;
    document.getElementById('f-genres').value     = a.genres;
    document.getElementById('f-image-url').value  = a.image;
    document.getElementById('f-mal-id').value     = a.id;
    document.getElementById('f-category').value   = a.category || 'Anime';
    document.getElementById('f-banner-url').value = a.banner || '';
    document.getElementById('f-trailer-url').value = a.trailer || '';

    const sel = document.getElementById('f-status');
    for (let o of sel.options) if (o.value === a.status) { sel.value = a.status; break; }

    const pi = document.getElementById('preview-img');
    if (a.image) { pi.src = a.image; pi.style.display = 'block'; }

    // Preview del banner
    const pb = document.getElementById('preview-banner');
    if (a.banner) { pb.src = a.banner; pb.style.display = 'block'; }
    else { pb.style.display = 'none'; }

    document.getElementById('results-list').innerHTML = '';
    document.getElementById('search-status').textContent = '✓ Datos cargados';
    setTimeout(() => document.getElementById('search-status').textContent = '', 2000);
}

function previewBanner(url) {
    const img = document.getElementById('preview-banner');
    if (url) {
        const match = url.match(/\/file\/d\/([a-zA-Z0-9_-]+)/);
        if (match) url = 'https://drive.google.com/uc?export=view&id=' + match[1];
        img.src = url;
        img.style.display = 'block';
    } else {
        img.style.display = 'none';
    }
}

function traducirFormato(f) {
    if (f === 'TV' || f === 'TV_SHORT') return 'Anime';
    if (f === 'MOVIE' || f === 'Movie') return 'Película';
    if (f === 'OVA')     return 'OVA';
    if (f === 'ONA')     return 'ONA';
    if (f === 'SPECIAL') return 'Especial';
    if (f === 'MUSIC')   return 'Music';
    return 'Anime';
}

function traducirStatus(s) {
    if (s === 'Finished Airing')  return 'Finalizado';
    if (s === 'Currently Airing') return 'En emisión';
    return 'Próximamente';
}

function traducirStatusAL(s) {
    if (s === 'FINISHED')  return 'Finalizado';
    if (s === 'RELEASING') return 'En emisión';
    return 'Próximamente';
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
    renumerarEps();
}

function eliminarEp(btn) {
    btn.closest('.ep-row').remove();
    renumerarEps();
}

function renumerarEps() {
    document.querySelectorAll('.ep-num').forEach((el, i) => el.textContent = i + 1);
    epCount = document.querySelectorAll('.ep-row').length;
}

function previewLocal(e) {
    const file = e.target.files[0];
    if (!file) return;
    const pi = document.getElementById('preview-img');
    pi.src = URL.createObjectURL(file);
    pi.style.display = 'block';
    document.getElementById('f-image-url').value = '';
}

function resetExtra() {
    document.getElementById('preview-img').style.display = 'none';
    document.getElementById('preview-banner').style.display = 'none';
    document.getElementById('ep-list').innerHTML = `
        <div class="ep-row">
            <span class="ep-num">1</span>
            <input type="text" name="episode_title[]" placeholder="Episodio 1">
            <input type="text" name="episode_url[]" placeholder="https://drive.google.com/...">
            <button type="button" class="btn-del-ep" onclick="eliminarEp(this)">✕</button>
        </div>`;
    epCount = 1;
}

document.getElementById('api-input').addEventListener('keydown', e => {
    if (e.key === 'Enter') buscarAPI();
});
</script>
</body>
</html>
<?php $conn->close(); ?>