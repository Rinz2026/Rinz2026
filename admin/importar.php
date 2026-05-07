<?php
require_once '../includes/conexion.php';
require_once '../includes/auth.php';
require_once '../includes/funciones.php';
requiereLogin();

// ── AJAX: buscar una página ──────────────────────────────────────────────────
if (isset($_POST['ajax_page'])) {
    header('Content-Type: application/json');
    $año  = intval($_POST['anio'] ?? 0);
    $page = intval($_POST['ajax_page'] ?? 1);

    $query = json_encode([
        'query' => 'query ($page: Int, $year: Int) {
            Page(page: $page, perPage: 50) {
                pageInfo { hasNextPage }
                media(type: ANIME, seasonYear: $year, sort: POPULARITY_DESC) {
                    id title { romaji } coverImage { medium }
                    startDate { year } status genres description format
                }
            }
        }',
        'variables' => ['page' => $page, 'year' => $año]
    ]);

    $ch = curl_init('https://graphql.anilist.co');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Accept: application/json']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $query);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 429) {
        echo json_encode(['error' => 'rate_limit']);
        exit;
    }

    $data  = json_decode($response, true);
    $media = $data['data']['Page']['media'] ?? [];
    $hasNextPage = $data['data']['Page']['pageInfo']['hasNextPage'] ?? false;

    $resultados = [];
    foreach ($media as $a) {
        $desc = preg_replace('/<[^>]+>/', '', $a['description'] ?? '');
        $resultados[] = [
            'fuente'   => 'AL',
            'id'       => $a['id'] ?? 0,
            'title'    => $a['title']['romaji'] ?? 'Sin título',
            'year'     => $a['startDate']['year'] ?? $año,
            'genres'   => implode(', ', $a['genres'] ?? []),
            'genresList' => $a['genres'] ?? [],
            'image'    => $a['coverImage']['medium'] ?? '',
            'desc'     => $desc,
            'status'   => match($a['status'] ?? '') {
                'FINISHED'         => 'Finalizado',
                'RELEASING'        => 'En emisión',
                'NOT_YET_RELEASED' => 'Próximamente',
                default            => 'Desconocido'
            },
            'category' => match($a['format'] ?? '') {
                'TV'      => 'Anime',
                'MOVIE'   => 'Película',
                'OVA'     => 'OVA',
                'ONA'     => 'ONA',
                'SPECIAL' => 'Especial',
                'MUSIC'   => 'Music',
                default   => 'Anime'
            },
            'format' => $a['format'] ?? '',
        ];
    }

    echo json_encode(['items' => $resultados, 'hasNextPage' => $hasNextPage]);
    exit;
}

// ── Importar seleccionados ───────────────────────────────────────────────────
$mensaje = '';
$error   = '';

if (isset($_POST['importar'])) {
    $ids_seleccionados = $_POST['seleccionar'] ?? [];
    $todos = json_decode($_POST['todos_datos'] ?? '[]', true);
    $importados = [];

    foreach ($ids_seleccionados as $idx) {
        if (isset($todos[$idx])) {
            $anime_data = $todos[$idx];
            $slug = generarSlug($anime_data['title']);
            $base = $slug;
            $i = 1;
            while (!slugUnico($slug, $conn)) {
                $slug = $base . '-' . $i++;
            }
            $stmt = $conn->prepare("INSERT INTO anime (title, slug, category, genres, image, description, status, year, mal_id) VALUES (?,?,?,?,?,?,?,?,?)");
            $cat    = $anime_data['category'] ?? 'Anime';
            $mal_id = intval($anime_data['id']);
            $stmt->bind_param("sssssssii",
                $anime_data['title'], $slug, $cat,
                $anime_data['genres'], $anime_data['image'],
                $anime_data['desc'], $anime_data['status'],
                $anime_data['year'], $mal_id
            );
            if ($stmt->execute()) {
                $importados[] = $anime_data['title'];
            }
        }
    }

    if (count($importados) > 0) {
        $mensaje = "Se importaron " . count($importados) . " anime(s) correctamente.";
    } else {
        $error = "Selecciona al menos un anime para importar.";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Importar Animes - Admin</title>
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
        .page-title { font-size: 22px; font-weight: 600; color: #fff; margin-bottom: 24px; }
        .card { background: #1a1a1a; border: 1px solid #2a2a2a; border-radius: 10px; padding: 24px; }

        .alert { padding: 12px 16px; border-radius: 8px; font-size: 14px; margin-bottom: 20px; }
        .alert-success { background: rgba(34,197,94,.1); border: 1px solid rgba(34,197,94,.2); color: #4ade80; }
        .alert-error   { background: rgba(220,38,38,.1); border: 1px solid rgba(220,38,38,.2); color: #f87171; }

        /* ── PASO 1: OPCIONES ── */
        .step-title { font-size: 13px; font-weight: 700; color: #fff; margin-bottom: 16px; text-transform: uppercase; letter-spacing: .08em; display: flex; align-items: center; gap: 8px; }
        .step-title span { background: #dc2626; color: #fff; width: 22px; height: 22px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-size: 11px; flex-shrink: 0; }

        .field { margin-bottom: 20px; }
        .field label { display: block; font-size: 12px; color: #555; text-transform: uppercase; letter-spacing: .05em; margin-bottom: 8px; }
        .field input[type="number"] { width: 100%; padding: 9px 12px; background: #0f0f0f; border: 1px solid #2a2a2a; border-radius: 8px; color: #fff; font-size: 13px; outline: none; max-width: 200px; }
        .field input[type="number"]:focus { border-color: #dc2626; }

        /* Grupos de checkboxes */
        .check-group { display: flex; flex-wrap: wrap; gap: 8px; }
        .check-chip { display: inline-flex; align-items: center; gap: 6px; padding: 6px 12px; background: #0f0f0f; border: 1px solid #2a2a2a; border-radius: 20px; font-size: 12px; color: #888; cursor: pointer; transition: all .15s; user-select: none; }
        .check-chip:hover { border-color: #555; color: #ccc; }
        .check-chip input { display: none; }
        .check-chip.checked { background: rgba(220,38,38,.12); border-color: rgba(220,38,38,.4); color: #f87171; }
        .check-chip .dot { width: 8px; height: 8px; border-radius: 50%; background: #333; flex-shrink: 0; transition: background .15s; }
        .check-chip.checked .dot { background: #dc2626; }

        /* Acciones rápidas */
        .quick-actions { display: flex; gap: 8px; margin-bottom: 10px; }
        .btn-quick { padding: 4px 10px; background: none; border: 1px solid #2a2a2a; border-radius: 6px; color: #666; font-size: 11px; cursor: pointer; transition: all .15s; }
        .btn-quick:hover { border-color: #555; color: #ccc; }

        .divider { border: none; border-top: 1px solid #2a2a2a; margin: 20px 0; }

        .btn-red  { padding: 10px 24px; background: #dc2626; border: none; border-radius: 8px; color: #fff; font-size: 13px; font-weight: 600; cursor: pointer; }
        .btn-red:hover { background: #b91c1c; }
        .btn-gray { padding: 9px 18px; background: #222; border: 1px solid #2a2a2a; border-radius: 8px; color: #aaa; font-size: 13px; font-weight: 600; cursor: pointer; }
        .btn-gray:hover { color: #fff; border-color: #555; }

        /* ── PASO 2: CARGANDO ── */
        #secLoading { display: none; text-align: center; padding: 48px 0; }
        .spinner { width: 40px; height: 40px; border: 3px solid #2a2a2a; border-top-color: #dc2626; border-radius: 50%; animation: spin .8s linear infinite; margin: 0 auto 14px; }
        @keyframes spin { to { transform: rotate(360deg); } }
        #loadingText { color: #555; font-size: 13px; }

        /* ── PASO 3: RESULTADOS ── */
        #secResultados { display: none; }
        .results-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px; flex-wrap: wrap; gap: 8px; }
        .results-header p { font-size: 13px; color: #888; }

        /* Resumen de filtros aplicados */
        .filtros-resumen { display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 14px; }
        .filtro-tag { display: inline-flex; align-items: center; gap: 4px; padding: 3px 10px; border-radius: 999px; font-size: 11px; font-weight: 600; }
        .filtro-tag.excluido { background: rgba(220,38,38,.1); border: 1px solid rgba(220,38,38,.2); color: #f87171; }
        .filtro-tag.incluido { background: rgba(59,130,246,.1); border: 1px solid rgba(59,130,246,.2); color: #60a5fa; }

        .results-list { display: flex; flex-direction: column; gap: 8px; max-height: 520px; overflow-y: auto; padding-right: 4px; }
        .results-list::-webkit-scrollbar { width: 6px; }
        .results-list::-webkit-scrollbar-track { background: #0f0f0f; }
        .results-list::-webkit-scrollbar-thumb { background: #333; border-radius: 3px; }

        .result-item { display: flex; gap: 12px; align-items: center; background: #0f0f0f; border: 1px solid #2a2a2a; border-radius: 8px; padding: 10px 12px; cursor: pointer; transition: border-color .15s; }
        .result-item:hover { border-color: #444; }
        .result-item.selected { border-color: #dc2626; background: rgba(220,38,38,.05); }
        .result-item input[type="checkbox"] { width: 16px; height: 16px; accent-color: #dc2626; flex-shrink: 0; cursor: pointer; }
        .result-item img { width: 38px; height: 54px; object-fit: cover; border-radius: 4px; flex-shrink: 0; background: #1a1a1a; }
        .result-info { flex: 1; min-width: 0; }
        .result-title { font-size: 13px; font-weight: 600; color: #fff; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .result-meta  { font-size: 11px; color: #555; margin-top: 3px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .result-genre-tags { display: flex; flex-wrap: wrap; gap: 4px; margin-top: 5px; }
        .genre-pill { font-size: 10px; padding: 2px 7px; border-radius: 999px; background: rgba(255,255,255,.05); color: #666; border: 1px solid #2a2a2a; }

        .actions { display: flex; gap: 10px; margin-top: 20px; align-items: center; flex-wrap: wrap; }
        .btn-import { padding: 10px 24px; background: #dc2626; border: none; border-radius: 8px; color: #fff; font-size: 14px; font-weight: 600; cursor: pointer; }
        .btn-import:hover { background: #b91c1c; }
        .selected-count { font-size: 12px; color: #555; margin-left: auto; }

        .no-results { text-align: center; padding: 48px 0; color: #555; font-size: 14px; }
        .no-results span { display: block; font-size: 32px; margin-bottom: 12px; }
    </style>
</head>
<body>

<aside class="sidebar">
    <div class="logo">RINZ <span>Admin Panel</span></div>
    <nav class="nav">
        <a href="index.php">▦ Dashboard</a>
        <a href="agregar.php">＋ Agregar anime</a>
        <a href="editar.php">✎ Editar anime</a>
        <a href="importar.php" class="active">↓ Importar masivo</a>
        <a href="drive_manager.php">↓ Capitulos</a>
        <a href="logout.php?redirect=web" target="_blank">↗ Ver web</a>
    </nav>
    <div class="sidebar-footer">
        <a href="logout.php">Cerrar sesión</a>
    </div>
</aside>

<main class="main">
    <div class="page-title">Importar animes por año</div>

    <?php if ($mensaje): ?>
        <div class="alert alert-success"><?= htmlspecialchars($mensaje) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="card">

        <!-- ══ PASO 1: OPCIONES ══ -->
        <div id="secOpciones">

            <!-- AÑO -->
            <div class="step-title"><span>1</span> Año a importar</div>
            <div class="field">
                <label>Año</label>
                <input type="number" id="inputAno" min="1950" max="2100" value="<?= date('Y') ?>">
            </div>

            <hr class="divider">

            <!-- GÉNEROS A EXCLUIR -->
            <div class="step-title"><span>2</span> Géneros a excluir</div>
            <div class="quick-actions">
                <button class="btn-quick" onclick="selExcluir('none')">Ninguno</button>
                <button class="btn-quick" onclick="selExcluir('all')">Todos</button>
                <button class="btn-quick" onclick="selExcluir('adult')">Solo adultos</button>
            </div>
            <div class="check-group" id="grupoExcluir">
                <?php
                $generosExcluir = [
                    'Hentai'       => true,
                    'Ecchi'        => false,
                    'Gore'         => false,
                    'Boys Love'    => false,
                    'Girls Love'   => false,
                    'Erotica'      => false,
                    'Mahou Shoujo' => false,
                    'Mecha'        => false,
                    'Music'        => false,
                    'Kids'         => false,
                ];
                foreach ($generosExcluir as $g => $checked):
                ?>
                <label class="check-chip <?= $checked ? 'checked' : '' ?>" data-grupo="excluir" data-valor="<?= $g ?>">
                    <input type="checkbox" <?= $checked ? 'checked' : '' ?>>
                    <span class="dot"></span>
                    <?= $g ?>
                </label>
                <?php endforeach; ?>
            </div>

            <hr class="divider">

            <!-- TIPOS A INCLUIR -->
            <div class="step-title"><span>3</span> Tipos a incluir</div>
            <div class="quick-actions">
                <button class="btn-quick" onclick="selTipos('all')">Todos</button>
                <button class="btn-quick" onclick="selTipos('none')">Ninguno</button>
            </div>
            <div class="check-group" id="grupoTipos">
                <?php
                $tipos = ['Anime', 'Película', 'OVA', 'ONA', 'Especial', 'Music'];
                $tiposFormato = ['Anime'=>'TV', 'Película'=>'MOVIE', 'OVA'=>'OVA', 'ONA'=>'ONA', 'Especial'=>'SPECIAL', 'Music'=>'MUSIC'];
                foreach ($tipos as $t):
                ?>
                <label class="check-chip checked" data-grupo="tipos" data-valor="<?= $tiposFormato[$t] ?>">
                    <input type="checkbox" checked>
                    <span class="dot"></span>
                    <?= $t ?>
                </label>
                <?php endforeach; ?>
            </div>

            <hr class="divider">

            <!-- ESTADOS A INCLUIR -->
            <div class="step-title"><span>4</span> Estados a incluir</div>
            <div class="quick-actions">
                <button class="btn-quick" onclick="selEstados('all')">Todos</button>
                <button class="btn-quick" onclick="selTipos('none')">Ninguno</button>
            </div>
            <div class="check-group" id="grupoEstados">
                <?php
                $estados = ['En emisión'=>'RELEASING', 'Finalizado'=>'FINISHED', 'Próximamente'=>'NOT_YET_RELEASED'];
                foreach ($estados as $label => $val):
                ?>
                <label class="check-chip checked" data-grupo="estados" data-valor="<?= $val ?>">
                    <input type="checkbox" checked>
                    <span class="dot"></span>
                    <?= $label ?>
                </label>
                <?php endforeach; ?>
            </div>

            <hr class="divider">

            <button class="btn-red" id="btnBuscar">🔍 Buscar animes</button>
        </div>

        <!-- ══ PASO 2: CARGANDO ══ -->
        <div id="secLoading">
            <div class="spinner"></div>
            <p id="loadingText">Buscando...</p>
        </div>

        <!-- ══ PASO 3: RESULTADOS ══ -->
        <div id="secResultados">
            <form method="POST" id="formImportar">

                <!-- Resumen de filtros aplicados -->
                <div class="filtros-resumen" id="resumenFiltros"></div>

                <div class="results-header">
                    <p>Se encontraron <strong style="color:#fff" id="totalEncontrados">0</strong> anime(s) tras aplicar filtros. Selecciona cuáles importar:</p>
                    <button type="button" class="btn-gray" id="btnSelAll">Seleccionar todos</button>
                </div>

                <div class="results-list" id="lista"></div>
                <input type="hidden" name="todos_datos" id="todosDatos" value="[]">

                <div class="actions">
                    <button type="submit" name="importar" class="btn-import">⬇ Importar seleccionados</button>
                    <button type="button" class="btn-gray" id="btnNueva">← Nueva búsqueda</button>
                    <span class="selected-count" id="contador">0 seleccionados</span>
                </div>
            </form>
        </div>

    </div>
</main>

<script>
var todosLosAnimes = [];
var todosSeleccionados = false;
var urlActual = '<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>';

// ── Chips interactivos ──────────────────────────────────────────────────────
document.querySelectorAll('.check-chip').forEach(function(chip) {
    chip.addEventListener('click', function() {
        var cb = chip.querySelector('input');
        cb.checked = !cb.checked;
        chip.classList.toggle('checked', cb.checked);
    });
});

function getChecked(grupo) {
    return Array.from(document.querySelectorAll('[data-grupo="' + grupo + '"].checked'))
        .map(function(el) { return el.dataset.valor; });
}

function selExcluir(modo) {
    var chips = document.querySelectorAll('[data-grupo="excluir"]');
    var adultGenres = ['Hentai', 'Ecchi', 'Erotica', 'Boys Love', 'Girls Love'];
    chips.forEach(function(chip) {
        var cb = chip.querySelector('input');
        var val = chip.dataset.valor;
        var checked = modo === 'all' ? true : modo === 'none' ? false : adultGenres.includes(val);
        cb.checked = checked;
        chip.classList.toggle('checked', checked);
    });
}

function selTipos(modo) {
    document.querySelectorAll('[data-grupo="tipos"]').forEach(function(chip) {
        var cb = chip.querySelector('input');
        cb.checked = modo === 'all';
        chip.classList.toggle('checked', modo === 'all');
    });
}

function selEstados(modo) {
    document.querySelectorAll('[data-grupo="estados"]').forEach(function(chip) {
        var cb = chip.querySelector('input');
        cb.checked = modo === 'all';
        chip.classList.toggle('checked', modo === 'all');
    });
}

// ── Mostrar secciones ───────────────────────────────────────────────────────
function mostrar(id) {
    ['secOpciones', 'secLoading', 'secResultados'].forEach(function(s) {
        document.getElementById(s).style.display = 'none';
    });
    document.getElementById(id).style.display = 'block';
}

function escHtml(str) {
    return String(str || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function actualizarContador() {
    var total = document.querySelectorAll('#lista input[type="checkbox"]:checked').length;
    document.getElementById('contador').textContent = total + ' seleccionados';
}

// ── Agregar ítem a la lista ─────────────────────────────────────────────────
function agregarItem(a, i) {
    var lista = document.getElementById('lista');
    var div = document.createElement('div');
    div.className = 'result-item';

    var genreHtml = (a.genresList || []).map(function(g) {
        return '<span class="genre-pill">' + escHtml(g) + '</span>';
    }).join('');

    div.innerHTML =
        '<input type="checkbox" name="seleccionar[]" value="' + i + '">' +
        '<img src="' + escHtml(a.image) + '" onerror="this.style.visibility=\'hidden\'" alt="">' +
        '<div class="result-info">' +
            '<div class="result-title">' + escHtml(a.title) + '</div>' +
            '<div class="result-meta">' + escHtml(a.year) + ' &bull; ' + escHtml(a.category) + ' &bull; ' + escHtml(a.status) + '</div>' +
            '<div class="result-genre-tags">' + genreHtml + '</div>' +
        '</div>';

    div.querySelector('input').addEventListener('change', function() {
        div.classList.toggle('selected', this.checked);
        actualizarContador();
    });
    div.addEventListener('click', function(e) {
        if (e.target.tagName === 'INPUT') return;
        var cb = div.querySelector('input');
        cb.checked = !cb.checked;
        div.classList.toggle('selected', cb.checked);
        actualizarContador();
    });
    lista.appendChild(div);
}

// ── Filtrar resultados según opciones elegidas ──────────────────────────────
function aplicarFiltros(items, generosExcluidos, tiposPermitidos, estadosPermitidos) {
    return items.filter(function(a) {
        // Excluir por género
        var genres = a.genresList || [];
        for (var i = 0; i < generosExcluidos.length; i++) {
            if (genres.includes(generosExcluidos[i])) return false;
        }
        // Filtrar por tipo/formato
        if (tiposPermitidos.length > 0 && !tiposPermitidos.includes(a.format)) return false;
        // Filtrar por estado
        var statusMap = { 'Finalizado': 'FINISHED', 'En emisión': 'RELEASING', 'Próximamente': 'NOT_YET_RELEASED' };
        if (estadosPermitidos.length > 0 && !estadosPermitidos.includes(statusMap[a.status] || '')) return false;
        return true;
    });
}

// ── Mostrar resumen de filtros ──────────────────────────────────────────────
function mostrarResumen(excluidos, tipos, estados) {
    var html = '';
    excluidos.forEach(function(g) {
        html += '<span class="filtro-tag excluido">✕ ' + escHtml(g) + '</span>';
    });
    var tipoLabels = { 'TV':'Anime','MOVIE':'Película','OVA':'OVA','ONA':'ONA','SPECIAL':'Especial','MUSIC':'Music' };
    var estadoLabels = { 'RELEASING':'En emisión','FINISHED':'Finalizado','NOT_YET_RELEASED':'Próximamente' };
    tipos.forEach(function(t) {
        html += '<span class="filtro-tag incluido">✓ ' + escHtml(tipoLabels[t] || t) + '</span>';
    });
    estados.forEach(function(e) {
        html += '<span class="filtro-tag incluido">✓ ' + escHtml(estadoLabels[e] || e) + '</span>';
    });
    document.getElementById('resumenFiltros').innerHTML = html;
}

// ── Buscar ──────────────────────────────────────────────────────────────────
document.getElementById('btnBuscar').addEventListener('click', function() {
    var ano = parseInt(document.getElementById('inputAno').value);
    if (isNaN(ano) || ano < 1950 || ano > 2100) {
        alert('Año inválido. Usa un año entre 1950 y 2100.');
        return;
    }

    var generosExcluidos = getChecked('excluir');
    var tiposPermitidos  = getChecked('tipos');
    var estadosPermitidos = getChecked('estados');

    todosLosAnimes = [];
    todosSeleccionados = false;
    document.getElementById('lista').innerHTML = '';
    mostrar('secLoading');

    var page = 1;
    var maxPages = 8;
    var todosRaw = []; // todos sin filtrar

    function buscarPagina() {
        document.getElementById('loadingText').textContent =
            'Buscando página ' + page + '... (' + todosRaw.length + ' encontrados)';

        var fd = new FormData();
        fd.append('ajax_page', page);
        fd.append('anio', ano);

        fetch(urlActual, { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function(res) { return res.text(); })
            .then(function(text) {
                var data;
                try { data = JSON.parse(text); }
                catch(e) { mostrarResultados(generosExcluidos, tiposPermitidos, estadosPermitidos, todosRaw); return; }

                if (data.error === 'rate_limit') {
                    setTimeout(buscarPagina, 2000);
                    return;
                }

                var items = data.items || [];
                items.forEach(function(item) {
                    var existe = todosRaw.some(function(a) { return a.title === item.title; });
                    if (!existe) todosRaw.push(item);
                });

                if (data.hasNextPage && page < maxPages) {
                    page++;
                    setTimeout(buscarPagina, 400);
                } else {
                    mostrarResultados(generosExcluidos, tiposPermitidos, estadosPermitidos, todosRaw);
                }
            })
            .catch(function() {
                mostrarResultados(generosExcluidos, tiposPermitidos, estadosPermitidos, todosRaw);
            });
    }

    buscarPagina();
});

function mostrarResultados(generosExcluidos, tiposPermitidos, estadosPermitidos, raw) {
    var filtrados = aplicarFiltros(raw, generosExcluidos, tiposPermitidos, estadosPermitidos);

    document.getElementById('lista').innerHTML = '';
    todosLosAnimes = [];

    filtrados.forEach(function(item) {
        agregarItem(item, todosLosAnimes.length);
        todosLosAnimes.push(item);
    });

    document.getElementById('totalEncontrados').textContent = filtrados.length;
    document.getElementById('todosDatos').value = JSON.stringify(todosLosAnimes);
    mostrarResumen(generosExcluidos, tiposPermitidos, estadosPermitidos);

    if (filtrados.length === 0) {
        document.getElementById('lista').innerHTML =
            '<div class="no-results"><span>🔍</span>No hay animes que coincidan con los filtros aplicados.</div>';
    }

    mostrar('secResultados');
}

// ── Seleccionar todos ───────────────────────────────────────────────────────
document.getElementById('btnSelAll').addEventListener('click', function() {
    todosSeleccionados = !todosSeleccionados;
    document.querySelectorAll('#lista input[type="checkbox"]').forEach(function(cb) {
        cb.checked = todosSeleccionados;
        cb.closest('.result-item').classList.toggle('selected', todosSeleccionados);
    });
    this.textContent = todosSeleccionados ? 'Deseleccionar todos' : 'Seleccionar todos';
    actualizarContador();
});

// ── Nueva búsqueda ──────────────────────────────────────────────────────────
document.getElementById('btnNueva').addEventListener('click', function() {
    todosLosAnimes = [];
    document.getElementById('lista').innerHTML = '';
    mostrar('secOpciones');
});
</script>

</body>
</html>
<?php $conn->close(); ?>