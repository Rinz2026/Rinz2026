<?php
require_once '../includes/conexion.php';
require_once '../includes/auth.php';
requiereLogin();

// Stats
$total_anime = $conn->query("SELECT COUNT(*) as c FROM anime")->fetch_assoc()['c'];
$total_eps   = $conn->query("SELECT COUNT(*) as c FROM episodios")->fetch_assoc()['c'];
$en_emision  = $conn->query("SELECT COUNT(*) as c FROM anime WHERE status='En emisión'")->fetch_assoc()['c'];

// Paginación y búsqueda
$buscar  = trim($_GET['q'] ?? '');
$pagina  = max(1, intval($_GET['p'] ?? 1));
$por_pag = 20;
$offset  = ($pagina - 1) * $por_pag;

if ($buscar) {
    $b = '%' . $conn->real_escape_string($buscar) . '%';
    $total_filtrado = $conn->query("SELECT COUNT(*) as c FROM anime WHERE title LIKE '$b'")->fetch_assoc()['c'];
    $animes = $conn->query("SELECT id, title, status, year, category, created_at FROM anime WHERE title LIKE '$b' ORDER BY id DESC LIMIT $por_pag OFFSET $offset");
} else {
    $total_filtrado = $total_anime;
    $animes = $conn->query("SELECT id, title, status, year, category, created_at FROM anime ORDER BY id DESC LIMIT $por_pag OFFSET $offset");
}

$total_pags = ceil($total_filtrado / $por_pag);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Admin</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><rect width='100' height='100' rx='12' fill='%23dc2626'/><text x='50' y='78' font-size='72' font-family='Arial' font-weight='bold' fill='white' text-anchor='middle'>R</text></svg>">
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

        .stats { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; margin-bottom: 32px; }
        .stat-card { background: #1a1a1a; border: 1px solid #2a2a2a; border-radius: 10px; padding: 20px 24px; }
        .stat-card .label { font-size: 12px; color: #555; text-transform: uppercase; letter-spacing: .05em; margin-bottom: 8px; }
        .stat-card .value { font-size: 32px; font-weight: 700; color: #fff; }
        .stat-card .value.red { color: #dc2626; }

        .section-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px; flex-wrap: wrap; gap: 10px; }
        .section-header h2 { font-size: 16px; font-weight: 600; color: #fff; }

        .header-right { display: flex; gap: 8px; align-items: center; }
        .search-box { display: flex; gap: 6px; }
        .search-box input { padding: 7px 12px; background: #1a1a1a; border: 1px solid #2a2a2a; border-radius: 8px; color: #fff; font-size: 13px; outline: none; width: 220px; }
        .search-box input:focus { border-color: #dc2626; }
        .search-box button { padding: 7px 14px; background: #dc2626; border: none; border-radius: 8px; color: #fff; font-size: 13px; cursor: pointer; }
        .search-box button:hover { background: #b91c1c; }
        .btn-clear { padding: 7px 12px; background: #222; border: 1px solid #2a2a2a; border-radius: 8px; color: #888; font-size: 13px; cursor: pointer; text-decoration: none; }
        .btn-clear:hover { color: #fff; }

        .btn-add { padding: 8px 16px; background: #dc2626; border: none; border-radius: 8px; color: #fff; font-size: 13px; font-weight: 600; cursor: pointer; text-decoration: none; }
        .btn-add:hover { background: #b91c1c; }

        table { width: 100%; border-collapse: collapse; background: #1a1a1a; border-radius: 10px; overflow: hidden; border: 1px solid #2a2a2a; }
        thead th { background: #222; padding: 12px 16px; text-align: left; font-size: 12px; color: #555; text-transform: uppercase; letter-spacing: .05em; font-weight: 500; }
        tbody tr { border-top: 1px solid #222; transition: background .15s; }
        tbody tr:hover { background: #222; }
        tbody td { padding: 12px 16px; font-size: 14px; color: #ccc; }

        .badge { display: inline-block; padding: 2px 10px; border-radius: 999px; font-size: 11px; font-weight: 600; }
        .badge-green { background: rgba(34,197,94,.15); color: #4ade80; }
        .badge-gray  { background: rgba(255,255,255,.07); color: #888; }
        .badge-blue  { background: rgba(59,130,246,.15); color: #60a5fa; }
        .badge-cat   { background: rgba(168,85,247,.15); color: #c084fc; }

        .actions { display: flex; gap: 8px; }
        .btn-edit, .btn-del { padding: 4px 12px; border-radius: 6px; font-size: 12px; font-weight: 500; cursor: pointer; text-decoration: none; border: 1px solid transparent; }
        .btn-edit { background: rgba(59,130,246,.1); color: #60a5fa; border-color: rgba(59,130,246,.2); }
        .btn-edit:hover { background: rgba(59,130,246,.2); }
        .btn-del { background: rgba(220,38,38,.1); color: #f87171; border-color: rgba(220,38,38,.2); }
        .btn-del:hover { background: rgba(220,38,38,.2); }

        .pagination { display: flex; gap: 6px; margin-top: 20px; align-items: center; flex-wrap: wrap; }
        .pagination a, .pagination span { padding: 6px 12px; border-radius: 6px; font-size: 13px; text-decoration: none; border: 1px solid #2a2a2a; background: #1a1a1a; color: #888; }
        .pagination a:hover { border-color: #dc2626; color: #fff; }
        .pagination .active { background: #dc2626; border-color: #dc2626; color: #fff; }
        .pagination .disabled { opacity: .3; pointer-events: none; }
        .pag-info { font-size: 13px; color: #555; margin-left: auto; }
    </style>
</head>
<body>

<aside class="sidebar">
    <div class="logo">RINZ <span>Admin Panel</span></div>
    <nav class="nav">
        <a href="index.php" class="active">▦ Dashboard</a>
        <a href="agregar.php">＋ Agregar anime</a>
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
    <div class="page-title">Dashboard</div>

    <div class="stats">
        <div class="stat-card">
            <div class="label">Total animes</div>
            <div class="value"><?= $total_anime ?></div>
        </div>
        <div class="stat-card">
            <div class="label">Total episodios</div>
            <div class="value"><?= $total_eps ?></div>
        </div>
        <div class="stat-card">
            <div class="label">En emisión</div>
            <div class="value red"><?= $en_emision ?></div>
        </div>
    </div>

    <div class="section-header">
        <h2>
            <?= $buscar ? 'Resultados para "' . htmlspecialchars($buscar) . '" (' . $total_filtrado . ')' : 'Todos los animes (' . $total_anime . ')' ?>
        </h2>
        <div class="header-right">
            <form class="search-box" method="GET">
                <input type="text" name="q" placeholder="Buscar por título..." value="<?= htmlspecialchars($buscar) ?>">
                <button type="submit">Buscar</button>
                <?php if ($buscar): ?>
                    <a href="index.php" class="btn-clear">✕ Limpiar</a>
                <?php endif; ?>
            </form>
            <a href="agregar.php" class="btn-add">+ Agregar</a>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Título</th>
                <th>Categoría</th>
                <th>Año</th>
                <th>Estado</th>
                <th>Agregado</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($a = $animes->fetch_assoc()): ?>
            <tr>
                <td style="color:#555"><?= $a['id'] ?></td>
                <td><?= htmlspecialchars($a['title']) ?></td>
                <td><span class="badge badge-cat"><?= htmlspecialchars($a['category'] ?: 'Anime') ?></span></td>
                <td><?= $a['year'] ?: '—' ?></td>
                <td>
                    <?php if ($a['status'] === 'En emisión'): ?>
                        <span class="badge badge-green">En emisión</span>
                    <?php elseif ($a['status'] === 'Próximamente'): ?>
                        <span class="badge badge-blue">Próximamente</span>
                    <?php else: ?>
                        <span class="badge badge-gray">Finalizado</span>
                    <?php endif; ?>
                </td>
                <td style="color:#555"><?= date('d/m/Y', strtotime($a['created_at'])) ?></td>
                <td>
                    <div class="actions">
                        <a href="editar.php?id=<?= $a['id'] ?>" class="btn-edit">Editar</a>
                        <a href="eliminar.php?id=<?= $a['id'] ?>" class="btn-del"
                           onclick="return confirm('¿Eliminar este anime?')">Eliminar</a>
                    </div>
                </td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>

    <!-- Paginación -->
    <?php if ($total_pags > 1): ?>
    <div class="pagination">
        <a href="?q=<?= urlencode($buscar) ?>&p=<?= max(1, $pagina-1) ?>"
           class="<?= $pagina <= 1 ? 'disabled' : '' ?>">← Anterior</a>

        <?php
        $inicio = max(1, $pagina - 3);
        $fin    = min($total_pags, $pagina + 3);
        if ($inicio > 1): ?>
            <a href="?q=<?= urlencode($buscar) ?>&p=1">1</a>
            <?php if ($inicio > 2): ?><span>...</span><?php endif; ?>
        <?php endif; ?>

        <?php for ($i = $inicio; $i <= $fin; $i++): ?>
            <a href="?q=<?= urlencode($buscar) ?>&p=<?= $i ?>"
               class="<?= $i === $pagina ? 'active' : '' ?>"><?= $i ?></a>
        <?php endfor; ?>

        <?php if ($fin < $total_pags): ?>
            <?php if ($fin < $total_pags - 1): ?><span>...</span><?php endif; ?>
            <a href="?q=<?= urlencode($buscar) ?>&p=<?= $total_pags ?>"><?= $total_pags ?></a>
        <?php endif; ?>

        <a href="?q=<?= urlencode($buscar) ?>&p=<?= min($total_pags, $pagina+1) ?>"
           class="<?= $pagina >= $total_pags ? 'disabled' : '' ?>">Siguiente →</a>

        <span class="pag-info">Página <?= $pagina ?> de <?= $total_pags ?></span>
    </div>
    <?php endif; ?>

</main>

</body>
</html>
<?php $conn->close(); ?>