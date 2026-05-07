<?php
require_once '../includes/conexion.php';
require_once '../includes/auth.php';
require_once '../includes/funciones.php';
requiereLogin();

$mensaje = '';
$error   = '';

// ── AJAX: buscar anime en la BD por título ────────────────────────────────────
if (isset($_GET['ajax_search'])) {
    header('Content-Type: application/json');
    $q = '%' . $conn->real_escape_string(trim($_GET['q'] ?? '')) . '%';
    $res = $conn->query("SELECT id, title, slug, image FROM anime WHERE title LIKE '$q' ORDER BY title LIMIT 15");
    $rows = [];
    while ($row = $res->fetch_assoc()) $rows[] = $row;
    echo json_encode($rows);
    exit;
}

// ── AJAX: guardar episodios en la BD ─────────────────────────────────────────
if (isset($_POST['ajax_guardar'])) {
    header('Content-Type: application/json');
    $anime_id = intval($_POST['anime_id'] ?? 0);
    $episodes = json_decode($_POST['episodes'] ?? '[]', true);

    if (!$anime_id || empty($episodes)) {
        echo json_encode(['ok' => false, 'error' => 'Datos incompletos']);
        exit;
    }

    $stmt = $conn->prepare(
        "INSERT INTO episodios (anime_id, episode_number, episode_title, episode_url)
         VALUES (?,?,?,?)
         ON DUPLICATE KEY UPDATE episode_title=VALUES(episode_title), episode_url=VALUES(episode_url)"
    );

    $guardados = 0;
    foreach ($episodes as $ep) {
        $num   = intval($ep['number']);
        $title = trim($ep['title'] ?? "Episodio $num");
        $url   = trim($ep['embedUrl'] ?? $ep['directUrl'] ?? '');
        if (!$url || !$num) continue;
        $stmt->bind_param("iiss", $anime_id, $num, $title, $url);
        if ($stmt->execute()) $guardados++;
    }

    echo json_encode(['ok' => true, 'guardados' => $guardados]);
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Drive Manager - Admin</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { background: #0f0f0f; color: #e0e0e0; font-family: 'Segoe UI', sans-serif; min-height: 100vh; }

        .sidebar { position: fixed; top: 0; left: 0; width: 220px; height: 100vh; background: #1a1a1a; border-right: 1px solid #2a2a2a; display: flex; flex-direction: column; padding: 24px 0; }
        .sidebar .logo { font-size: 22px; font-weight: 700; color: #dc2626; letter-spacing: 2px; padding: 0 24px 24px; border-bottom: 1px solid #2a2a2a; }
        .sidebar .logo span { display: block; font-size: 11px; color: #555; font-weight: 400; margin-top: 2px; }
        .nav { margin-top: 16px; flex: 1; }
        .nav a { display: flex; align-items: center; gap: 10px; padding: 10px 24px; color: #888; text-decoration: none; font-size: 14px; transition: all .2s; }
        .nav a:hover, .nav a.active { color: #fff; background: #222; border-left: 2px solid #dc2626; }
        .sidebar-footer { padding: 16px 24px; border-top: 1px solid #2a2a2a; }
        .sidebar-footer a { color: #555; font-size: 13px; text-decoration: none; }
        .sidebar-footer a:hover { color: #dc2626; }

        .main { margin-left: 220px; padding: 32px; }
        .page-title { font-size: 22px; font-weight: 600; color: #fff; margin-bottom: 6px; }
        .page-sub { font-size: 13px; color: #555; margin-bottom: 28px; }

        .tabs { display: flex; gap: 4px; margin-bottom: 24px; border-bottom: 1px solid #2a2a2a; padding-bottom: 0; }
        .tab { padding: 10px 20px; font-size: 13px; font-weight: 600; color: #555; cursor: pointer; border-bottom: 2px solid transparent; margin-bottom: -1px; transition: all .2s; }
        .tab:hover { color: #aaa; }
        .tab.active { color: #fff; border-bottom-color: #dc2626; }

        .tab-content { display: none; }
        .tab-content.active { display: block; }

        .card { background: #1a1a1a; border: 1px solid #2a2a2a; border-radius: 10px; padding: 24px; margin-bottom: 20px; }
        .card-title { font-size: 14px; font-weight: 700; color: #fff; margin-bottom: 16px; text-transform: uppercase; letter-spacing: .05em; }

        .alert { padding: 12px 16px; border-radius: 8px; font-size: 14px; margin-bottom: 20px; }
        .alert-success { background: rgba(34,197,94,.1); border: 1px solid rgba(34,197,94,.2); color: #4ade80; }
        .alert-error   { background: rgba(220,38,38,.1); border: 1px solid rgba(220,38,38,.2); color: #f87171; }

        /* ── Steps badge ── */
        .step-title { font-size: 12px; font-weight: 700; color: #888; text-transform: uppercase; letter-spacing: .08em; display: flex; align-items: center; gap: 8px; margin-bottom: 12px; }
        .step-badge { background: #dc2626; color: #fff; width: 20px; height: 20px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-size: 10px; flex-shrink: 0; }

        /* ── Textarea / Input ── */
        .field { margin-bottom: 16px; }
        .field label { display: block; font-size: 12px; color: #555; text-transform: uppercase; letter-spacing: .05em; margin-bottom: 8px; }
        textarea, input[type="text"] { width: 100%; padding: 10px 14px; background: #0f0f0f; border: 1px solid #2a2a2a; border-radius: 8px; color: #e0e0e0; font-size: 13px; font-family: 'Courier New', monospace; outline: none; resize: vertical; transition: border-color .2s; }
        textarea:focus, input[type="text"]:focus { border-color: #dc2626; }

        /* ── Search box ── */
        .search-wrap { position: relative; }
        .search-wrap input { padding-left: 36px; font-family: 'Segoe UI', sans-serif; }
        .search-icon { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #555; font-size: 14px; pointer-events: none; }
        .search-results { position: absolute; top: calc(100% + 4px); left: 0; right: 0; background: #1a1a1a; border: 1px solid #2a2a2a; border-radius: 8px; max-height: 220px; overflow-y: auto; z-index: 100; display: none; }
        .search-result-item { display: flex; align-items: center; gap: 10px; padding: 10px 14px; cursor: pointer; transition: background .15s; }
        .search-result-item:hover { background: #222; }
        .search-result-item img { width: 30px; height: 42px; object-fit: cover; border-radius: 3px; background: #111; }
        .search-result-item .si-title { font-size: 13px; color: #fff; font-weight: 600; }
        .search-result-item .si-id { font-size: 11px; color: #555; margin-top: 2px; }

        /* ── Selected anime chip ── */
        .selected-anime { display: none; align-items: center; gap: 12px; padding: 12px 16px; background: rgba(220,38,38,.07); border: 1px solid rgba(220,38,38,.2); border-radius: 8px; margin-top: 10px; }
        .selected-anime img { width: 36px; height: 50px; object-fit: cover; border-radius: 4px; }
        .selected-anime .sa-name { font-size: 14px; font-weight: 600; color: #fff; flex: 1; }
        .selected-anime .sa-id { font-size: 11px; color: #dc2626; }
        .btn-clear-sel { background: none; border: none; color: #555; cursor: pointer; font-size: 16px; }
        .btn-clear-sel:hover { color: #f87171; }

        /* ── Anime batch cards ── */
        .anime-batch-list { display: flex; flex-direction: column; gap: 16px; }
        .anime-card { background: #111; border: 1px solid #2a2a2a; border-radius: 8px; overflow: hidden; }
        .anime-card-header { display: flex; align-items: center; gap: 14px; padding: 14px 18px; cursor: pointer; transition: background .15s; }
        .anime-card-header:hover { background: #1a1a1a; }
        .anime-card-title { font-size: 14px; font-weight: 700; color: #fff; flex: 1; }
        .anime-card-meta { font-size: 12px; color: #555; }
        .anime-card-badge { padding: 3px 10px; border-radius: 999px; font-size: 11px; font-weight: 700; }
        .badge-ok   { background: rgba(34,197,94,.1); color: #4ade80; border: 1px solid rgba(34,197,94,.2); }
        .badge-fail { background: rgba(220,38,38,.1); color: #f87171; border: 1px solid rgba(220,38,38,.2); }
        .badge-wait { background: rgba(234,179,8,.1);  color: #facc15; border: 1px solid rgba(234,179,8,.2); }
        .anime-card-body { display: none; border-top: 1px solid #2a2a2a; padding: 14px 18px; }
        .anime-card-body.open { display: block; }

        /* ── Episodios lista ── */
        .ep-list { display: flex; flex-direction: column; gap: 6px; max-height: 320px; overflow-y: auto; margin-bottom: 14px; }
        .ep-item { display: flex; align-items: center; gap: 10px; padding: 8px 12px; background: #0f0f0f; border: 1px solid #1e1e1e; border-radius: 6px; }
        .ep-num { font-size: 12px; font-weight: 700; color: #dc2626; width: 40px; flex-shrink: 0; }
        .ep-title { font-size: 12px; color: #ccc; flex: 1; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .ep-link { font-size: 11px; color: #555; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 280px; }
        .ep-check { width: 16px; height: 16px; accent-color: #dc2626; }

        /* ── Selector de anime para asignar ── */
        .assign-row { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
        .assign-row label { font-size: 12px; color: #555; white-space: nowrap; }
        .assign-row .search-wrap { flex: 1; min-width: 200px; }

        /* ── Botones ── */
        .btn-red  { padding: 10px 22px; background: #dc2626; border: none; border-radius: 8px; color: #fff; font-size: 13px; font-weight: 600; cursor: pointer; transition: background .2s; }
        .btn-red:hover { background: #b91c1c; }
        .btn-gray { padding: 9px 18px; background: #222; border: 1px solid #2a2a2a; border-radius: 8px; color: #aaa; font-size: 13px; font-weight: 600; cursor: pointer; }
        .btn-gray:hover { color: #fff; border-color: #555; }
        .btn-sm   { padding: 6px 14px; font-size: 12px; background: #dc2626; border: none; border-radius: 6px; color: #fff; font-weight: 600; cursor: pointer; }
        .btn-sm:hover { background: #b91c1c; }
        .btn-sm-gray { padding: 6px 14px; font-size: 12px; background: #222; border: 1px solid #333; border-radius: 6px; color: #888; cursor: pointer; }

        /* ── Info box ── */
        .info-box { background: #111; border: 1px solid #2a2a2a; border-radius: 8px; padding: 16px 20px; }
        .info-box code { font-family: 'Courier New', monospace; font-size: 12px; color: #4ade80; background: rgba(34,197,94,.05); padding: 2px 6px; border-radius: 4px; }
        .info-box p { font-size: 13px; color: #666; line-height: 1.7; margin-bottom: 8px; }
        .info-box p:last-child { margin-bottom: 0; }

        /* ── Toast ── */
        #toast { position: fixed; bottom: 28px; right: 28px; background: #1a1a1a; border: 1px solid #2a2a2a; border-radius: 10px; padding: 14px 20px; font-size: 13px; color: #fff; z-index: 9999; opacity: 0; transform: translateY(10px); transition: all .3s; pointer-events: none; }
        #toast.show { opacity: 1; transform: translateY(0); }
        #toast.ok   { border-color: rgba(34,197,94,.3); color: #4ade80; }
        #toast.err  { border-color: rgba(220,38,38,.3); color: #f87171; }

        .divider { border: none; border-top: 1px solid #1e1e1e; margin: 16px 0; }
        .text-muted { color: #555; font-size: 12px; }
        .flex { display: flex; }
        .gap-2 { gap: 8px; }
        .items-center { align-items: center; }
        .justify-between { justify-content: space-between; }
        .mb-3 { margin-bottom: 12px; }
    </style>
</head>
<body>

<aside class="sidebar">
    <div class="logo">RINZ <span>Admin Panel</span></div>
    <nav class="nav">
        <a href="index.php">▦ Dashboard</a>
        <a href="agregar.php">＋ Agregar anime</a>
        <a href="editar.php">✎ Editar anime</a>
        <a href="importar.php">↓ Importar masivo</a>
        <a href="drive_manager.php" class="active">☁ Drive Manager</a>
        <a href="logout.php?redirect=web" target="_blank">↗ Ver web</a>
    </nav>
    <div class="sidebar-footer">
        <a href="logout.php">Cerrar sesión</a>
    </div>
</aside>

<main class="main">
    <div class="page-title">☁ Drive Manager</div>
    <div class="page-sub">Importa episodios subidos a Google Drive directamente a tu base de datos</div>

    <!-- TABS -->
    <div class="tabs">
        <div class="tab active" data-tab="batch">📦 Importar JSON del Pipeline</div>
        <div class="tab" data-tab="manual">✏️ Agregar Episodio Manual</div>
        <div class="tab" data-tab="ayuda">📖 Cómo usar</div>
    </div>

    <!-- ════════ TAB: IMPORTAR JSON ════════ -->
    <div class="tab-content active" id="tab-batch">

        <div class="card">
            <div class="step-title"><span class="step-badge">1</span> Pega el contenido de batch_results.json</div>
            <div class="field">
                <textarea id="jsonInput" rows="8" placeholder='{ "animes": [ { "title": "Naruto", "episodes": [...] } ] }'></textarea>
            </div>
            <div class="flex gap-2">
                <button class="btn-red" onclick="parseBatchJson()">🔍 Cargar y previsualizar</button>
                <button class="btn-gray" onclick="document.getElementById('jsonInput').value=''">Limpiar</button>
            </div>
        </div>

        <!-- Resultados del JSON -->
        <div id="batchPreview" style="display:none;">
            <div class="flex items-center justify-between mb-3">
                <div style="font-size:14px;color:#fff;font-weight:700;" id="batchSummary"></div>
                <button class="btn-red" id="btnGuardarTodo" onclick="guardarTodo()">💾 Guardar todo en BD</button>
            </div>
            <div class="anime-batch-list" id="animeBatchList"></div>
        </div>
    </div>

    <!-- ════════ TAB: MANUAL ════════ -->
    <div class="tab-content" id="tab-manual">
        <div class="card">
            <div class="step-title"><span class="step-badge">1</span> Selecciona el anime</div>
            <div class="search-wrap" style="margin-bottom:10px;">
                <span class="search-icon">🔍</span>
                <input type="text" id="manualSearch" placeholder="Buscar anime por nombre..." autocomplete="off">
                <div class="search-results" id="manualResults"></div>
            </div>
            <div class="selected-anime" id="selectedAnime">
                <img id="selAnimeImg" src="" alt="">
                <div>
                    <div class="sa-name" id="selAnimeName"></div>
                    <div class="sa-id" id="selAnimeId"></div>
                </div>
                <button class="btn-clear-sel" onclick="clearAnimeSelection()">✕</button>
            </div>
        </div>

        <div class="card">
            <div class="step-title"><span class="step-badge">2</span> Datos del episodio</div>
            <div class="field">
                <label>Número de episodio</label>
                <input type="text" id="epNum" placeholder="1" style="max-width:120px;">
            </div>
            <div class="field">
                <label>Título del episodio (opcional)</label>
                <input type="text" id="epTitle" placeholder="Episodio 1">
            </div>
            <div class="field">
                <label>URL de Drive (embed o directa)</label>
                <input type="text" id="epUrl" placeholder="https://drive.google.com/file/d/XXXX/preview">
            </div>
            <button class="btn-red" onclick="guardarManual()">💾 Guardar episodio</button>
        </div>
    </div>

    <!-- ════════ TAB: AYUDA ════════ -->
    <div class="tab-content" id="tab-ayuda">
        <div class="card">
            <div class="card-title">¿Cómo usar el Pipeline?</div>
            <div class="info-box">
                <p><strong style="color:#fff;">Paso 1</strong> — Instala las dependencias del descargador:</p>
                <p><code>cd anime1v-api-main && npm install</code></p>
                <hr class="divider">
                <p><strong style="color:#fff;">Paso 2</strong> — Para descargar UN anime:</p>
                <p><code>node pipeline.js</code> &nbsp;(modo interactivo)</p>
                <p><code>node pipeline.js --url https://animeav1.com/media/naruto --eps 1-12 --variant SUB</code></p>
                <hr class="divider">
                <p><strong style="color:#fff;">Paso 3</strong> — Para descargar VARIOS animes en lote:</p>
                <p><code>node batch_pipeline.js</code> &nbsp;(modo interactivo)</p>
                <p><code>node batch_pipeline.js --list animes_batch.json</code> &nbsp;(desde archivo)</p>
                <hr class="divider">
                <p><strong style="color:#fff;">Paso 4</strong> — Una vez terminado, el script genera <code>batch_results.json</code>.</p>
                <p>Pega el contenido de ese archivo en la pestaña <strong style="color:#fff;">Importar JSON</strong> de arriba y haz clic en Guardar.</p>
                <hr class="divider">
                <p><strong style="color:#fff;">Estructura en Google Drive:</strong></p>
                <p><code>Animes / Naruto / Naruto - Ep 001.mp4</code></p>
                <p><code>Animes / Bleach / Bleach - Ep 001.mp4</code></p>
                <p>Cada episodio se hace <strong style="color:#fff;">público automáticamente</strong> para que el reproductor pueda accederlo.</p>
            </div>
        </div>

        <div class="card">
            <div class="card-title">Formato del JSON de batch</div>
            <div class="info-box">
                <p>Archivo <code>animes_batch.json</code> de ejemplo:</p>
                <pre style="font-size:12px;color:#4ade80;margin-top:8px;line-height:1.6;">[
  { "url": "https://animeav1.com/media/naruto", "eps": "1-12", "variant": "SUB" },
  { "url": "https://animeav1.com/media/bleach", "eps": "todos", "variant": "SUB" },
  { "url": "https://tioanime.com/anime/one-piece", "eps": "1-5", "variant": "DUB" }
]</pre>
            </div>
        </div>
    </div>
</main>

<div id="toast"></div>

<script>
// ── TABS ─────────────────────────────────────────────────────────────────────
document.querySelectorAll('.tab').forEach(tab => {
    tab.addEventListener('click', () => {
        document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
        document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
        tab.classList.add('active');
        document.getElementById('tab-' + tab.dataset.tab).classList.add('active');
    });
});

// ── TOAST ────────────────────────────────────────────────────────────────────
function toast(msg, type = 'ok') {
    const el = document.getElementById('toast');
    el.textContent = msg;
    el.className = 'show ' + type;
    setTimeout(() => el.className = '', 3000);
}

// ── PARSE JSON DEL PIPELINE ──────────────────────────────────────────────────
let batchData = null;

function parseBatchJson() {
    const raw = document.getElementById('jsonInput').value.trim();
    if (!raw) return toast('Pega el JSON primero', 'err');

    try {
        batchData = JSON.parse(raw);
    } catch (e) {
        return toast('JSON inválido: ' + e.message, 'err');
    }

    // Soporta tanto batch_results.json ({ animes: [...] }) como pipeline.json ({ episodes: [...] })
    const animes = batchData.animes || (batchData.episodes ? [batchData] : null);
    if (!animes || !animes.length) return toast('No se encontraron animes en el JSON', 'err');

    const preview = document.getElementById('batchPreview');
    const list    = document.getElementById('animeBatchList');
    const summary = document.getElementById('batchSummary');

    const total = animes.reduce((a, b) => a + (b.episodes?.length || 0), 0);
    summary.textContent = `${animes.length} anime(s) | ${total} episodios listos para importar`;

    list.innerHTML = '';

    animes.forEach((anime, idx) => {
        const eps     = anime.episodes || [];
        const failed  = anime.failed || [];
        const hasEps  = eps.length > 0;

        const card = document.createElement('div');
        card.className = 'anime-card';
        card.innerHTML = `
            <div class="anime-card-header" onclick="toggleCard(${idx})">
                <div>
                    <div class="anime-card-title">${escHtml(anime.title || anime.animeTitle || 'Sin título')}</div>
                    <div class="anime-card-meta">${eps.length} ep(s) listos ${failed.length ? '· ' + failed.length + ' fallidos' : ''}</div>
                </div>
                <span class="anime-card-badge ${hasEps ? 'badge-ok' : 'badge-fail'}">${hasEps ? '✅ Listo' : '❌ Sin eps'}</span>
            </div>
            <div class="anime-card-body" id="body-${idx}">
                ${hasEps ? `
                    <div class="assign-row mb-3">
                        <label>Asignar a anime en BD:</label>
                        <div class="search-wrap" style="position:relative;">
                            <span class="search-icon">🔍</span>
                            <input type="text" class="batch-search" data-idx="${idx}"
                                placeholder="Buscar '${escHtml(anime.title || '')}' en BD..."
                                value="${escHtml(anime.title || anime.animeTitle || '')}"
                                autocomplete="off">
                            <div class="search-results batch-results" id="bres-${idx}"></div>
                        </div>
                        <span class="badge-wait anime-card-badge" id="sel-badge-${idx}" style="display:none;"></span>
                    </div>
                    <div class="ep-list">
                        ${eps.map(ep => `
                            <div class="ep-item">
                                <span class="ep-num">Ep ${ep.number}</span>
                                <span class="ep-title">${escHtml(ep.title || 'Episodio ' + ep.number)}</span>
                                <span class="ep-link">${escHtml(ep.embedUrl || ep.directUrl || '')}</span>
                            </div>
                        `).join('')}
                    </div>
                    <div class="flex gap-2">
                        <button class="btn-sm" onclick="guardarAnime(${idx})">💾 Guardar en BD</button>
                        <span class="text-muted" id="save-status-${idx}"></span>
                    </div>
                ` : '<p class="text-muted">No hay episodios disponibles para este anime.</p>'}
            </div>
        `;
        list.appendChild(card);
    });

    preview.style.display = 'block';

    // Attach search listeners
    document.querySelectorAll('.batch-search').forEach(input => {
        input.addEventListener('input', function() {
            batchSearchAnime(this.dataset.idx, this.value);
        });
        input.addEventListener('focus', function() {
            if (this.value.length >= 2) batchSearchAnime(this.dataset.idx, this.value);
        });
    });

    toast('JSON cargado correctamente ✅');
}

function toggleCard(idx) {
    const body = document.getElementById('body-' + idx);
    body.classList.toggle('open');
}

// ── Buscar anime en BD (batch) ───────────────────────────────────────────────
const selectedAnimes = {}; // idx → { id, title }

function batchSearchAnime(idx, q) {
    if (!q || q.length < 2) {
        document.getElementById('bres-' + idx).style.display = 'none';
        return;
    }
    fetch('drive_manager.php?ajax_search=1&q=' + encodeURIComponent(q))
        .then(r => r.json())
        .then(data => {
            const box = document.getElementById('bres-' + idx);
            if (!data.length) { box.style.display = 'none'; return; }
            box.innerHTML = data.map(a => `
                <div class="search-result-item" onclick="selectBatchAnime(${idx}, ${a.id}, '${escJs(a.title)}', '${escJs(a.image || '')}')">
                    <img src="${escHtml(a.image || '')}" onerror="this.style.display='none'" alt="">
                    <div>
                        <div class="si-title">${escHtml(a.title)}</div>
                        <div class="si-id">ID: ${a.id}</div>
                    </div>
                </div>
            `).join('');
            box.style.display = 'block';
        });
}

function selectBatchAnime(idx, id, title, image) {
    selectedAnimes[idx] = { id, title };
    const input = document.querySelector(`.batch-search[data-idx="${idx}"]`);
    if (input) input.value = title;
    const box = document.getElementById('bres-' + idx);
    if (box) box.style.display = 'none';
    const badge = document.getElementById('sel-badge-' + idx);
    if (badge) { badge.textContent = '✓ ID ' + id; badge.style.display = 'inline-flex'; }
}

// ── Guardar un anime individual ──────────────────────────────────────────────
async function guardarAnime(idx) {
    const sel = selectedAnimes[idx];
    if (!sel) return toast('Selecciona el anime en la BD primero', 'err');

    const animes = batchData.animes || [batchData];
    const anime  = animes[idx];
    const eps    = anime.episodes || [];

    const status = document.getElementById('save-status-' + idx);
    status.textContent = 'Guardando...';

    const fd = new FormData();
    fd.append('ajax_guardar', '1');
    fd.append('anime_id', sel.id);
    fd.append('episodes', JSON.stringify(eps));

    const res  = await fetch('drive_manager.php', { method: 'POST', body: fd });
    const data = await res.json();

    if (data.ok) {
        status.textContent = `✅ ${data.guardados} ep(s) guardados`;
        toast(`✅ ${data.guardados} episodios guardados para "${sel.title}"`);
    } else {
        status.textContent = '❌ Error';
        toast('Error: ' + data.error, 'err');
    }
}

// ── Guardar TODO el batch ────────────────────────────────────────────────────
async function guardarTodo() {
    const animes = batchData?.animes || (batchData ? [batchData] : []);
    let total = 0;
    let errores = 0;

    for (let idx = 0; idx < animes.length; idx++) {
        const sel = selectedAnimes[idx];
        if (!sel) { errores++; continue; }

        const eps = animes[idx].episodes || [];
        if (!eps.length) continue;

        const fd = new FormData();
        fd.append('ajax_guardar', '1');
        fd.append('anime_id', sel.id);
        fd.append('episodes', JSON.stringify(eps));

        const res  = await fetch('drive_manager.php', { method: 'POST', body: fd });
        const data = await res.json();
        if (data.ok) total += data.guardados;
        else errores++;
    }

    if (errores > 0) toast(`⚠️ ${total} eps guardados, ${errores} anime(s) sin asignar`, 'err');
    else toast(`✅ ${total} episodios guardados correctamente`);
}

// ── BÚSQUEDA MANUAL ──────────────────────────────────────────────────────────
let selectedManualId = null;

document.getElementById('manualSearch').addEventListener('input', function() {
    const q = this.value.trim();
    if (q.length < 2) { document.getElementById('manualResults').style.display = 'none'; return; }

    fetch('drive_manager.php?ajax_search=1&q=' + encodeURIComponent(q))
        .then(r => r.json())
        .then(data => {
            const box = document.getElementById('manualResults');
            if (!data.length) { box.style.display = 'none'; return; }
            box.innerHTML = data.map(a => `
                <div class="search-result-item" onclick="selectManualAnime(${a.id}, '${escJs(a.title)}', '${escJs(a.image || '')}')">
                    <img src="${escHtml(a.image || '')}" onerror="this.style.display='none'" alt="">
                    <div>
                        <div class="si-title">${escHtml(a.title)}</div>
                        <div class="si-id">ID: ${a.id}</div>
                    </div>
                </div>
            `).join('');
            box.style.display = 'block';
        });
});

function selectManualAnime(id, title, image) {
    selectedManualId = id;
    document.getElementById('manualResults').style.display = 'none';
    document.getElementById('manualSearch').value = '';
    const chip = document.getElementById('selectedAnime');
    chip.style.display = 'flex';
    document.getElementById('selAnimeName').textContent = title;
    document.getElementById('selAnimeId').textContent = 'ID: ' + id;
    document.getElementById('selAnimeImg').src = image || '';
    document.getElementById('epTitle').placeholder = 'Episodio 1';
}

function clearAnimeSelection() {
    selectedManualId = null;
    document.getElementById('selectedAnime').style.display = 'none';
    document.getElementById('manualSearch').value = '';
}

async function guardarManual() {
    if (!selectedManualId) return toast('Selecciona un anime primero', 'err');

    const num   = parseInt(document.getElementById('epNum').value);
    const title = document.getElementById('epTitle').value.trim() || `Episodio ${num}`;
    const url   = document.getElementById('epUrl').value.trim();

    if (!num || !url) return toast('Número y URL son obligatorios', 'err');

    const ep = [{ number: num, title, embedUrl: url }];
    const fd = new FormData();
    fd.append('ajax_guardar', '1');
    fd.append('anime_id', selectedManualId);
    fd.append('episodes', JSON.stringify(ep));

    const res  = await fetch('drive_manager.php', { method: 'POST', body: fd });
    const data = await res.json();

    if (data.ok) {
        toast(`✅ Episodio ${num} guardado`);
        document.getElementById('epNum').value = '';
        document.getElementById('epTitle').value = '';
        document.getElementById('epUrl').value = '';
    } else {
        toast('Error: ' + data.error, 'err');
    }
}

// ── Utils ────────────────────────────────────────────────────────────────────
function escHtml(str) {
    return String(str || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
function escJs(str) {
    return String(str || '').replace(/'/g, "\\'").replace(/\n/g, '');
}

// Cerrar dropdowns al hacer click fuera
document.addEventListener('click', (e) => {
    if (!e.target.closest('.search-wrap')) {
        document.querySelectorAll('.search-results').forEach(el => el.style.display = 'none');
    }
});
</script>

</body>
</html>
<?php $conn->close(); ?>
