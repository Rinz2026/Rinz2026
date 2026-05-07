<?php
require_once '../includes/conexion.php';

// Filtros
$filtro_status = trim($_GET['status'] ?? '');
$filtro_tipo   = trim($_GET['tipo'] ?? '');
$filtro_genero = trim($_GET['genero'] ?? '');
$filtro_año    = trim($_GET['anio'] ?? '');
$search        = trim($_GET['search'] ?? '');

// WHERE
$where = ['1=1'];
if ($filtro_status) $where[] = "status = '" . $conn->real_escape_string($filtro_status) . "'";
if ($filtro_tipo)   $where[] = "category = '" . $conn->real_escape_string($filtro_tipo) . "'";
if ($filtro_año)    $where[] = "year = " . intval($filtro_año);
if ($search)        $where[] = "(title LIKE '%" . $conn->real_escape_string($search) . "%' OR genres LIKE '%" . $conn->real_escape_string($search) . "%')";
if ($filtro_genero) $where[] = "genres LIKE '%" . $conn->real_escape_string($filtro_genero) . "%'";
$where_sql = implode(' AND ', $where);

// Paginación
$por_pag    = 24;
$pagina     = max(1, intval($_GET['p'] ?? 1));
$offset     = ($pagina - 1) * $por_pag;
$total_res  = $conn->query("SELECT COUNT(*) as c FROM anime WHERE $where_sql")->fetch_assoc()['c'];
$total_pags = ceil($total_res / $por_pag);
$animes     = $conn->query("SELECT * FROM anime WHERE $where_sql ORDER BY created_at DESC LIMIT $por_pag OFFSET $offset");

// Datos para filtros
$todos_generos_raw = $conn->query("SELECT genres FROM anime WHERE genres != '' AND genres IS NOT NULL");
$generos_set = [];
while ($row = $todos_generos_raw->fetch_assoc()) {
    foreach (array_map('trim', explode(',', $row['genres'])) as $g) {
        if ($g) $generos_set[$g] = true;
    }
}
ksort($generos_set);

$años_raw = $conn->query("SELECT DISTINCT year FROM anime WHERE year IS NOT NULL AND year > 0 ORDER BY year DESC");
$años = [];
while ($row = $años_raw->fetch_assoc()) $años[] = $row['year'];

$tipos   = ['Anime', 'Película', 'OVA', 'ONA', 'Especial', 'Music'];
$estados = ['En emisión', 'Finalizado', 'Próximamente'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rinz - Directorio</title>
    <link rel="icon" href="favicon.svg">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Rajdhani:wght@500;700&family=Inter:wght@400;500&display=swap');
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { background: #0a0a0a; color: #e0e0e0; font-family: 'Inter', sans-serif; min-height: 100vh; }

        /* HEADER */
        header { position: fixed; top: 0; left: 0; width: 100%; z-index: 100; background: rgba(10,10,10,.95); border-bottom: 1px solid #1a1a1a; padding: 0 32px; height: 60px; display: flex; align-items: center; justify-content: space-between; gap: 24px; }
        .logo { font-family: 'Rajdhani', sans-serif; font-size: 26px; font-weight: 700; color: #dc2626; letter-spacing: 3px; text-decoration: none; flex-shrink: 0; }
        .search-wrap { flex: 1; max-width: 480px; position: relative; }
        .search-wrap input { width: 100%; padding: 8px 16px 8px 40px; background: #111; border: 1px solid #222; border-radius: 8px; color: #fff; font-size: 14px; outline: none; transition: border-color .2s; }
        .search-wrap input:focus { border-color: #dc2626; }
        .search-wrap .icon { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #555; font-size: 16px; pointer-events: none; }
        #live-results { position: absolute; top: calc(100% + 6px); left: 0; right: 0; background: #111; border: 1px solid #222; border-radius: 8px; overflow: hidden; display: none; z-index: 200; }
        .live-item { display: flex; align-items: center; gap: 10px; padding: 8px 12px; cursor: pointer; transition: background .15s; }
        .live-item:hover { background: #1a1a1a; }
        .live-item img { width: 30px; height: 42px; object-fit: cover; border-radius: 4px; flex-shrink: 0; }
        .live-item .li-title { font-size: 13px; color: #fff; }
        .live-item .li-meta  { font-size: 11px; color: #555; }
        nav { display: flex; gap: 20px; flex-shrink: 0; }
        nav a { color: #888; text-decoration: none; font-size: 14px; transition: color .2s; }
        nav a:hover, nav a.active { color: #fff; }
        .menu-btn { display: none; background: none; border: none; color: #fff; font-size: 22px; cursor: pointer; flex-shrink: 0; padding: 4px 8px; }

        .page { margin-top: 60px; display: flex; min-height: calc(100vh - 60px); }

        /* SIDEBAR */
        .sidebar { width: 200px; flex-shrink: 0; background: #0f0f0f; border-right: 1px solid #1a1a1a; padding: 20px 14px; position: sticky; top: 60px; height: calc(100vh - 60px); overflow-y: auto; }
        .sidebar::-webkit-scrollbar { width: 4px; }
        .sidebar::-webkit-scrollbar-thumb { background: #2a2a2a; border-radius: 4px; }
        .filter-section { margin-bottom: 20px; }
        .filter-label { font-size: 10px; color: #555; text-transform: uppercase; letter-spacing: .08em; font-weight: 600; margin-bottom: 8px; display: block; }
        .filter-tags { display: flex; flex-direction: column; gap: 2px; max-height: 180px; overflow-y: auto; padding-right: 2px; }
        .filter-tags::-webkit-scrollbar { width: 3px; }
        .filter-tags::-webkit-scrollbar-thumb { background: #2a2a2a; border-radius: 3px; }
        .filter-tag { padding: 5px 10px; border-radius: 6px; font-size: 12px; color: #777; cursor: pointer; border: 1px solid transparent; transition: all .15s; text-decoration: none; display: block; }
        .filter-tag:hover { background: #1a1a1a; color: #fff; border-color: #2a2a2a; }
        .filter-tag.active { background: rgba(220,38,38,.15); color: #f87171; border-color: rgba(220,38,38,.3); }
        .filter-select { width: 100%; padding: 7px 10px; background: #1a1a1a; border: 1px solid #2a2a2a; border-radius: 8px; color: #ccc; font-size: 12px; outline: none; cursor: pointer; }
        .filter-select:focus { border-color: #dc2626; }
        .filter-select option { background: #1a1a1a; }
        .btn-limpiar { width: 100%; padding: 8px; background: #dc2626; border: none; border-radius: 8px; color: #fff; font-size: 12px; font-weight: 600; cursor: pointer; text-decoration: none; display: block; text-align: center; margin-top: 8px; }
        .btn-limpiar:hover { background: #b91c1c; }

        /* CONTENIDO */
        .content { flex: 1; padding: 24px 20px; overflow: hidden; }
        .content-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px; flex-wrap: wrap; gap: 8px; }
        .content-header h1 { font-family: 'Rajdhani', sans-serif; font-size: 20px; font-weight: 700; color: #fff; }
        .result-count { font-size: 13px; color: #555; }
        .filtros-activos { display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 14px; }
        .filtro-chip { display: inline-flex; align-items: center; gap: 6px; padding: 3px 10px; background: rgba(220,38,38,.1); border: 1px solid rgba(220,38,38,.2); border-radius: 999px; font-size: 12px; color: #f87171; text-decoration: none; }
        .filtro-chip:hover { background: rgba(220,38,38,.2); }

        /* GRID */
        .grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(148px, 1fr)); gap: 12px; }
        .card { background: #111; border-radius: 10px; overflow: hidden; border: 1px solid #1a1a1a; text-decoration: none; color: inherit; display: block; transition: transform .2s, border-color .2s; }
        .card:hover { transform: translateY(-4px); border-color: #dc2626; }
        .card-img-wrap { position: relative; }
        .card-img { width: 100%; aspect-ratio: 2/3; object-fit: cover; display: block; background: #1a1a1a; }
        .card-tipo { position: absolute; top: 6px; left: 6px; font-size: 10px; font-weight: 700; padding: 2px 7px; border-radius: 4px; background: #dc2626; color: #fff; }
        .card-body { padding: 8px 10px 10px; }
        .card-title { font-size: 12px; font-weight: 500; color: #e0e0e0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin-bottom: 4px; }
        .card-meta { display: flex; gap: 5px; align-items: center; }
        .badge { font-size: 10px; font-weight: 600; padding: 2px 6px; border-radius: 4px; }
        .badge-green { background: rgba(34,197,94,.15); color: #4ade80; }
        .badge-gray  { background: rgba(255,255,255,.07); color: #666; }
        .badge-blue  { background: rgba(59,130,246,.15); color: #60a5fa; }
        .card-year { font-size: 11px; color: #555; }

        /* PAGINACIÓN */
        .paginacion { display: flex; gap: 5px; margin-top: 28px; align-items: center; flex-wrap: wrap; justify-content: center; padding-bottom: 20px; }
        .pag-btn { padding: 7px 13px; border-radius: 8px; font-size: 13px; text-decoration: none; border: 1px solid #2a2a2a; background: #111; color: #888; transition: all .15s; }
        .pag-btn:hover { border-color: #dc2626; color: #fff; }
        .pag-btn.active { background: #dc2626; border-color: #dc2626; color: #fff; pointer-events: none; }
        .pag-btn.disabled { opacity: .3; pointer-events: none; }
        .pag-dots { color: #555; font-size: 13px; padding: 0 4px; line-height: 2; }
        .pag-info { font-size: 12px; color: #555; margin-left: 8px; }
        .empty { text-align: center; padding: 80px 20px; color: #555; }
        .empty h2 { font-size: 18px; color: #333; margin-bottom: 8px; }

        /* OVERLAY MÓVIL */
        .sidebar-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,.6); z-index: 98; }
        .sidebar-overlay.open { display: block; }

        /* RESPONSIVE */
        @media (min-width: 1440px) {
            .sidebar { width: 240px; }
            .grid { grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); }
        }

        @media (max-width: 1024px) {
            .sidebar { width: 180px; }
            .grid { grid-template-columns: repeat(auto-fill, minmax(130px, 1fr)); }
        }

        @media (max-width: 768px) {
            header { padding: 0 16px; gap: 12px; }
            .menu-btn { display: block; }
            nav { display: none; }
            .search-wrap { max-width: 100%; }

            /* Sidebar como panel deslizable */
            .sidebar {
                position: fixed;
                top: 60px;
                left: 0;
                height: calc(100vh - 60px);
                z-index: 99;
                transform: translateX(-100%);
                transition: transform .3s ease;
                width: 240px;
            }
            .sidebar.open { transform: translateX(0); }

            .content { padding: 16px; }
            .grid { grid-template-columns: repeat(auto-fill, minmax(130px, 1fr)); gap: 10px; }
        }

        @media (max-width: 480px) {
            header { padding: 0 12px; height: 54px; }
            .logo { font-size: 22px; letter-spacing: 2px; }
            .search-wrap input { font-size: 13px; padding: 7px 12px 7px 34px; }
            .page { margin-top: 54px; }
            .sidebar { top: 54px; height: calc(100vh - 54px); }
            .content { padding: 12px; }
            .grid { grid-template-columns: repeat(3, 1fr); gap: 8px; }
            .card-title { font-size: 11px; }
        }
    </style>
</head>
<body>

<header>
    <a href="index.php" class="logo">RINZ</a>
    <div class="search-wrap">
        <span class="icon">🔍</span>
        <input type="text" id="search-input" placeholder="Buscar anime..."
               value="<?= htmlspecialchars($search) ?>"
               oninput="buscarLive(this.value)"
               onkeydown="if(event.key==='Enter') buscarForm(this.value)">
        <div id="live-results"></div>
    </div>
    <nav id="main-nav">
        <a href="index.php">Inicio</a>
        <a href="directorio.php" class="active">Directorio</a>
        <a href="ruleta.php">RuletaTV</a>
    </nav>
    <button class="menu-btn" onclick="toggleSidebar()">☰</button>
</header>

<div class="sidebar-overlay" id="sidebar-overlay" onclick="toggleSidebar()"></div>

<div class="page">

    <aside class="sidebar" id="sidebar">
        <div class="filter-section">
            <span class="filter-label">Estado</span>
            <div class="filter-tags">
                <?php foreach ($estados as $e): ?>
                <a href="?<?= http_build_query(array_merge($_GET, ['status' => $e, 'p' => 1])) ?>"
                   class="filter-tag <?= $filtro_status === $e ? 'active' : '' ?>" onclick="closeSidebar()">
                    <?= htmlspecialchars($e) ?>
                </a>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="filter-section">
            <span class="filter-label">Tipo</span>
            <div class="filter-tags">
                <?php foreach ($tipos as $t): ?>
                <a href="?<?= http_build_query(array_merge($_GET, ['tipo' => $t, 'p' => 1])) ?>"
                   class="filter-tag <?= $filtro_tipo === $t ? 'active' : '' ?>" onclick="closeSidebar()">
                    <?= htmlspecialchars($t) ?>
                </a>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="filter-section">
            <span class="filter-label">Género</span>
            <div class="filter-tags">
                <?php foreach (array_keys($generos_set) as $g): ?>
                <a href="?<?= http_build_query(array_merge($_GET, ['genero' => $g, 'p' => 1])) ?>"
                   class="filter-tag <?= $filtro_genero === $g ? 'active' : '' ?>" onclick="closeSidebar()">
                    <?= htmlspecialchars($g) ?>
                </a>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="filter-section">
    <span class="filter-label">Año</span>
    <div class="filter-tags">
        <?php foreach ($años as $y): ?>
        <a href="?<?= http_build_query(array_merge($_GET, ['anio' => $y, 'p' => 1])) ?>"
           class="filter-tag <?= $filtro_año == $y ? 'active' : '' ?>" onclick="closeSidebar()">
            <?= $y ?>
        </a>
        <?php endforeach; ?>
    </div>
</div>

        <?php if ($filtro_status || $filtro_tipo || $filtro_genero || $filtro_año || $search): ?>
        <a href="directorio.php" class="btn-limpiar">✕ Limpiar filtros</a>
        <?php endif; ?>
    </aside>

    <div class="content">
        <div class="content-header">
            <h1>Directorio</h1>
            <span class="result-count"><?= $total_res ?> título<?= $total_res != 1 ? 's' : '' ?></span>
        </div>

        <?php if ($filtro_status || $filtro_tipo || $filtro_genero || $filtro_año || $search): ?>
        <div class="filtros-activos">
            <?php if ($filtro_status): ?>
                <a href="?<?= http_build_query(array_merge(array_diff_key($_GET, ['status'=>'']), ['p'=>1])) ?>" class="filtro-chip"><?= htmlspecialchars($filtro_status) ?> ✕</a>
            <?php endif; ?>
            <?php if ($filtro_tipo): ?>
                <a href="?<?= http_build_query(array_merge(array_diff_key($_GET, ['tipo'=>'']), ['p'=>1])) ?>" class="filtro-chip"><?= htmlspecialchars($filtro_tipo) ?> ✕</a>
            <?php endif; ?>
            <?php if ($filtro_genero): ?>
                <a href="?<?= http_build_query(array_merge(array_diff_key($_GET, ['genero'=>'']), ['p'=>1])) ?>" class="filtro-chip"><?= htmlspecialchars($filtro_genero) ?> ✕</a>
            <?php endif; ?>
            <?php if ($filtro_año): ?>
                <a href="?<?= http_build_query(array_merge(array_diff_key($_GET, ['anio'=>'']), ['p'=>1])) ?>" class="filtro-chip"><?= $filtro_año ?> ✕</a>
            <?php endif; ?>
            <?php if ($search): ?>
                <a href="?<?= http_build_query(array_merge(array_diff_key($_GET, ['search'=>'']), ['p'=>1])) ?>" class="filtro-chip">"<?= htmlspecialchars($search) ?>" ✕</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if ($animes && $animes->num_rows > 0): ?>
        <div class="grid">
            <?php while ($a = $animes->fetch_assoc()):
                $src = filter_var($a['image'], FILTER_VALIDATE_URL) ? $a['image'] : '../uploads/portadas/' . $a['image'];
            ?>
            <a href="series.php?id=<?= $a['id'] ?>" class="card">
                <div class="card-img-wrap">
                    <img class="card-img" src="<?= htmlspecialchars($src) ?>" alt="<?= htmlspecialchars($a['title']) ?>" onerror="this.style.display='none'">
                    <span class="card-tipo"><?= htmlspecialchars($a['category'] ?: 'Anime') ?></span>
                </div>
                <div class="card-body">
                    <div class="card-title"><?= htmlspecialchars($a['title']) ?></div>
                    <div class="card-meta">
                        <?php if ($a['status'] === 'En emisión'): ?>
                            <span class="badge badge-green">En emisión</span>
                        <?php elseif ($a['status'] === 'Próximamente'): ?>
                            <span class="badge badge-blue">Próximo</span>
                        <?php else: ?>
                            <span class="badge badge-gray">Finalizado</span>
                        <?php endif; ?>
                        <?php if ($a['year']): ?><span class="card-year"><?= $a['year'] ?></span><?php endif; ?>
                    </div>
                </div>
            </a>
            <?php endwhile; ?>
        </div>

        <?php if ($total_pags > 1):
            $params = array_diff_key($_GET, ['p' => '']);
        ?>
        <div class="paginacion">
            <a href="?<?= http_build_query(array_merge($params, ['p' => max(1, $pagina-1)])) ?>"
               class="pag-btn <?= $pagina <= 1 ? 'disabled' : '' ?>">← Ant</a>

            <?php
            $ini = max(1, $pagina - 2);
            $fin = min($total_pags, $pagina + 2);
            if ($ini > 1): ?>
                <a href="?<?= http_build_query(array_merge($params, ['p' => 1])) ?>" class="pag-btn">1</a>
                <?php if ($ini > 2): ?><span class="pag-dots">...</span><?php endif; ?>
            <?php endif; ?>

            <?php for ($i = $ini; $i <= $fin; $i++): ?>
                <a href="?<?= http_build_query(array_merge($params, ['p' => $i])) ?>"
                   class="pag-btn <?= $i === $pagina ? 'active' : '' ?>"><?= $i ?></a>
            <?php endfor; ?>

            <?php if ($fin < $total_pags): ?>
                <?php if ($fin < $total_pags - 1): ?><span class="pag-dots">...</span><?php endif; ?>
                <a href="?<?= http_build_query(array_merge($params, ['p' => $total_pags])) ?>" class="pag-btn"><?= $total_pags ?></a>
            <?php endif; ?>

            <a href="?<?= http_build_query(array_merge($params, ['p' => min($total_pags, $pagina+1)])) ?>"
               class="pag-btn <?= $pagina >= $total_pags ? 'disabled' : '' ?>">Sig →</a>

            <span class="pag-info">Página <?= $pagina ?> de <?= $total_pags ?></span>
        </div>
        <?php endif; ?>

        <?php else: ?>
        <div class="empty">
            <h2>Sin resultados</h2>
            <p>No se encontró ningún anime con esos filtros.</p>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
// SIDEBAR MÓVIL
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebar-overlay');
    sidebar.classList.toggle('open');
    overlay.classList.toggle('open');
}

function closeSidebar() {
    document.getElementById('sidebar').classList.remove('open');
    document.getElementById('sidebar-overlay').classList.remove('open');
}

// BUSCADOR
let timer = null;

async function buscarLive(q) {
    clearTimeout(timer);
    const box = document.getElementById('live-results');
    if (q.length < 2) { box.style.display = 'none'; return; }
    timer = setTimeout(async () => {
        const r = await fetch(`buscar.php?q=${encodeURIComponent(q)}`);
        const data = await r.json();
        box.innerHTML = '';
        if (!data.length) { box.style.display = 'none'; return; }
        data.forEach(a => {
            const item = document.createElement('div');
            item.className = 'live-item';
            const src = a.image_is_url ? a.image : `../uploads/portadas/${a.image}`;
            item.innerHTML = `<img src="${src}" onerror="this.style.display='none'">
                <div><div class="li-title">${a.title}</div><div class="li-meta">${a.year || ''} · ${a.status}</div></div>`;
            item.onclick = () => window.location.href = `series.php?id=${a.id}`;
            box.appendChild(item);
        });
        box.style.display = 'block';
    }, 300);
}

function buscarForm(q) {
    if (q.trim().length < 2) return;
    const box = document.getElementById('live-results');
    const first = box.querySelector('.live-item');
    if (first) { first.click(); }
    else { window.location.href = `directorio.php?search=${encodeURIComponent(q.trim())}`; }
}

document.addEventListener('click', e => {
    if (!e.target.closest('.search-wrap')) {
        document.getElementById('live-results').style.display = 'none';
    }
});
</script>

</body>
</html>
<?php $conn->close(); ?>