<?php
require_once '../includes/conexion.php';

function fixDriveImg($url) {
    if (empty($url)) return $url;
    if (preg_match('/\/file\/d\/([a-zA-Z0-9_-]+)/', $url, $m)) {
        return 'https://drive.google.com/uc?export=view&id=' . $m[1];
    }
    return $url;
}

$id = intval($_GET['id'] ?? 0);
if (!$id) { header('Location: index.php'); exit; }

$stmt = $conn->prepare("SELECT * FROM anime WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$anime = $stmt->get_result()->fetch_assoc();
if (!$anime) { header('Location: index.php'); exit; }

$eps_stmt = $conn->prepare("SELECT * FROM episodios WHERE anime_id = ? ORDER BY episode_number ASC");
$eps_stmt->bind_param("i", $id);
$eps_stmt->execute();
$episodios = $eps_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$ep_activo = intval($_GET['ep'] ?? 1);
$ep_actual = null;
foreach ($episodios as $ep) {
    if ($ep['episode_number'] == $ep_activo) { $ep_actual = $ep; break; }
}
if (!$ep_actual && count($episodios) > 0) { $ep_actual = $episodios[0]; $ep_activo = $ep_actual['episode_number']; }

function driveEmbed($url) {
    if (strpos($url, '/preview') !== false) return $url;
    if (preg_match('/\/file\/d\/([a-zA-Z0-9_-]+)/', $url, $m)) {
        return 'https://drive.google.com/file/d/' . $m[1] . '/preview';
    }
    if (preg_match('/[?&]id=([a-zA-Z0-9_-]+)/', $url, $m)) {
        return 'https://drive.google.com/file/d/' . $m[1] . '/preview';
    }
    return $url;
}

function youtubeEmbed($url) {
    if (preg_match('/(?:v=|youtu\.be\/|shorts\/)([a-zA-Z0-9_-]{11})/', $url, $m)) {
        return 'https://www.youtube.com/embed/' . $m[1] . '?autoplay=1';
    }
    return '';
}

$embed_url     = $ep_actual ? driveEmbed($ep_actual['episode_url']) : '';
$trailer_embed = !empty($anime['trailer_url']) ? youtubeEmbed($anime['trailer_url']) : '';

$img_src = $anime['image'];
$src = filter_var($img_src, FILTER_VALIDATE_URL) ? $img_src : '../uploads/portadas/' . $img_src;

$banner_raw = $anime['banner'] ?? '';
$banner = '';
if (!empty($banner_raw)) {
    $url = filter_var($banner_raw, FILTER_VALIDATE_URL) ? $banner_raw : '../uploads/portadas/' . $banner_raw;
    $banner = fixDriveImg($url);
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo htmlspecialchars($anime['title']); ?> - Rinz</title>
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
#live-results { position: absolute; top: calc(100% + 6px); left: 0; right: 0; background: #111; border: 1px solid #222; border-radius: 8px; overflow: hidden; display: none; z-index: 200; max-height: 320px; overflow-y: auto; }
.live-item { display: flex; align-items: center; gap: 10px; padding: 8px 12px; cursor: pointer; transition: background .15s; }
.live-item:hover { background: #1a1a1a; }
.live-item img { width: 30px; height: 42px; object-fit: cover; border-radius: 4px; flex-shrink: 0; }
.live-item .li-title { font-size: 13px; color: #fff; }
.live-item .li-meta { font-size: 11px; color: #555; }
nav { display: flex; gap: 20px; flex-shrink: 0; }
nav a { color: #888; text-decoration: none; font-size: 14px; transition: color .2s; }
nav a:hover { color: #fff; }
.menu-btn { display: none; background: none; border: none; color: #fff; font-size: 22px; cursor: pointer; flex-shrink: 0; padding: 4px 8px; }

.page { margin-top: 60px; }

/* BANNER */
.banner-section { position: relative; width: 100%; height: 320px; overflow: hidden; background: #111; }
.banner-bg { width: 100%; height: 100%; object-fit: cover; object-position: center top; filter: brightness(.4); display: block; }
.banner-overlay { position: absolute; inset: 0; background: linear-gradient(to bottom, transparent 30%, #0a0a0a 100%); }
.banner-info { position: absolute; bottom: 28px; left: 32px; right: 32px; display: flex; align-items: flex-end; gap: 20px; }
.banner-poster { width: 100px; height: 140px; object-fit: cover; border-radius: 8px; border: 2px solid rgba(255,255,255,.1); flex-shrink: 0; box-shadow: 0 8px 24px rgba(0,0,0,.6); }
.banner-text .anime-title-banner { font-family: 'Rajdhani', sans-serif; font-size: 34px; font-weight: 700; color: #fff; line-height: 1; margin-bottom: 8px; text-shadow: 0 2px 8px rgba(0,0,0,.8); }
.banner-text .meta-row { margin-bottom: 12px; }
.btn-trailer { display: inline-flex; align-items: center; gap: 8px; padding: 9px 20px; background: rgba(255,255,255,.15); border: 1px solid rgba(255,255,255,.25); border-radius: 8px; color: #fff; font-size: 13px; font-weight: 600; cursor: pointer; transition: all .2s; }
.btn-trailer:hover { background: rgba(220,38,38,.7); border-color: #dc2626; }

/* MODAL TRAILER */
.modal-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,.85); z-index: 999; align-items: center; justify-content: center; }
.modal-overlay.open { display: flex; }
.modal-box { position: relative; width: 90%; max-width: 860px; aspect-ratio: 16/9; background: #000; border-radius: 10px; overflow: hidden; }
.modal-box iframe { width: 100%; height: 100%; border: none; }
.modal-close { position: absolute; top: -38px; right: 0; background: none; border: none; color: #fff; font-size: 28px; cursor: pointer; line-height: 1; }

/* PLAYER */
.player-section { background: #000; display: flex; justify-content: center; }
.player-wrap { width: 100%; max-width: 1100px; aspect-ratio: 16/9; }
.player-wrap iframe { width: 100%; height: 100%; border: none; display: block; }
.no-video { width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; flex-direction: column; gap: 12px; color: #555; font-size: 15px; }
.no-video span { font-size: 40px; }

/* CONTENT */
.content { max-width: 1100px; margin: 0 auto; padding: 28px 24px; display: grid; grid-template-columns: 1fr 300px; gap: 28px; }
.ep-label { font-size: 12px; color: #dc2626; text-transform: uppercase; letter-spacing: .08em; font-weight: 600; margin-bottom: 6px; }
.anime-title { font-family: 'Rajdhani', sans-serif; font-size: 28px; font-weight: 700; color: #fff; line-height: 1.1; margin-bottom: 4px; }
.ep-title { font-size: 15px; color: #888; margin-bottom: 16px; }
.meta-row { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; margin-bottom: 20px; }
.badge { font-size: 11px; font-weight: 600; padding: 3px 10px; border-radius: 999px; }
.badge-green { background: rgba(34,197,94,.15); color: #4ade80; }
.badge-gray  { background: rgba(255,255,255,.07); color: #888; }
.badge-blue  { background: rgba(59,130,246,.15); color: #60a5fa; }
.badge-genre { background: rgba(220,38,38,.1); color: #f87171; border: 1px solid rgba(220,38,38,.2); }
.year-tag { font-size: 13px; color: #555; }
.description { font-size: 14px; line-height: 1.7; color: #999; border-top: 1px solid #1a1a1a; padding-top: 16px; }
.ep-nav { display: flex; gap: 10px; margin-top: 20px; }
.ep-nav a { flex: 1; padding: 10px; background: #111; border: 1px solid #1a1a1a; border-radius: 8px; color: #888; text-decoration: none; font-size: 13px; text-align: center; transition: all .2s; }
.ep-nav a:hover { border-color: #dc2626; color: #fff; }
.ep-nav a.disabled { opacity: .3; pointer-events: none; }
.anime-card-mini { display: flex; gap: 16px; align-items: flex-start; background: #111; border: 1px solid #1a1a1a; border-radius: 10px; padding: 14px; margin-bottom: 20px; }
.anime-card-mini img { width: 60px; height: 85px; object-fit: cover; border-radius: 6px; flex-shrink: 0; }
.mini-title { font-size: 13px; font-weight: 600; color: #fff; margin-bottom: 4px; }
.mini-meta  { font-size: 11px; color: #555; }
.sidebar-title { font-size: 12px; color: #555; text-transform: uppercase; letter-spacing: .08em; font-weight: 600; margin-bottom: 12px; padding-bottom: 10px; border-bottom: 1px solid #1a1a1a; }
.ep-list { display: flex; flex-direction: column; gap: 4px; max-height: 420px; overflow-y: auto; padding-right: 4px; }
.ep-list::-webkit-scrollbar { width: 4px; }
.ep-list::-webkit-scrollbar-thumb { background: #2a2a2a; border-radius: 4px; }
.ep-item { display: flex; align-items: center; gap: 10px; padding: 10px 12px; border-radius: 8px; text-decoration: none; color: #888; font-size: 13px; border: 1px solid transparent; transition: all .15s; }
.ep-item:hover { background: #111; color: #fff; border-color: #222; }
.ep-item.active { background: rgba(220,38,38,.1); border-color: rgba(220,38,38,.3); color: #fff; }
.ep-num { width: 28px; height: 28px; background: #1a1a1a; border-radius: 6px; display: flex; align-items: center; justify-content: center; font-size: 11px; font-weight: 700; color: #555; flex-shrink: 0; }
.ep-item.active .ep-num { background: #dc2626; color: #fff; }
.ep-name { flex: 1; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.no-eps { color: #444; font-size: 13px; text-align: center; padding: 24px 0; }

/* PANTALLA GRANDE */
@media (min-width: 1440px) {
    .content { max-width: 1300px; }
    .player-wrap { max-width: 1300px; }
    .banner-section { height: 380px; }
    .banner-text .anime-title-banner { font-size: 42px; }
    .banner-poster { width: 120px; height: 170px; }
}

/* TABLET */
@media (max-width: 1024px) {
    .content { grid-template-columns: 1fr 260px; gap: 20px; padding: 20px 16px; }
    .banner-section { height: 280px; }
    .banner-text .anime-title-banner { font-size: 28px; }
    .banner-poster { width: 85px; height: 120px; }
    .ep-list { max-height: 340px; }
}

/* MÓVIL GRANDE */
@media (max-width: 768px) {
    header { padding: 0 16px; gap: 12px; }
    .menu-btn { display: block; }
    nav { display: none; flex-direction: column; position: absolute; top: 60px; right: 0; background: #111; border: 1px solid #222; border-radius: 0 0 8px 8px; padding: 12px 20px; gap: 14px; min-width: 140px; z-index: 99; }
    nav.open { display: flex; }
    .search-wrap { max-width: 100%; }
    .banner-section { height: 220px; }
    .banner-info { left: 16px; right: 16px; bottom: 16px; gap: 12px; }
    .banner-poster { width: 65px; height: 92px; }
    .banner-text .anime-title-banner { font-size: 20px; margin-bottom: 6px; }
    .banner-text .meta-row { gap: 5px; margin-bottom: 8px; }
    .btn-trailer { padding: 7px 14px; font-size: 12px; }
    .content { grid-template-columns: 1fr; padding: 16px; gap: 16px; }
    .ep-sidebar { order: -1; }
    .ep-list { max-height: 220px; }
    .anime-title { font-size: 22px; }
    .ep-nav a { font-size: 12px; padding: 8px; }
    .anime-card-mini { padding: 10px; gap: 12px; }
    .anime-card-mini img { width: 50px; height: 70px; }
}

/* MÓVIL PEQUEÑO — FIX BANNER */
@media (max-width: 480px) {
    header { padding: 0 12px; height: 54px; }
    .logo { font-size: 22px; letter-spacing: 2px; }
    .search-wrap input { font-size: 13px; padding: 7px 12px 7px 34px; }
    nav { top: 54px; }
    .page { margin-top: 54px; }

    /* BANNER FIX - cover en vez de contain */
    .banner-section { height: 200px; }
    .banner-bg { object-fit: cover; object-position: center top; filter: brightness(.4); }
    .banner-info { left: 12px; right: 12px; bottom: 12px; gap: 10px; }
    .banner-poster { width: 50px; height: 70px; border-radius: 5px; }
    .banner-text .anime-title-banner { font-size: 15px; line-height: 1.2; }
    .banner-text .meta-row { display: flex; gap: 4px; margin-bottom: 6px; flex-wrap: wrap; }
    .btn-trailer { padding: 5px 10px; font-size: 11px; }

    .content { padding: 12px; gap: 12px; }
    .anime-title { font-size: 19px; }
    .description { font-size: 13px; }
    .ep-list { max-height: 180px; }
    .ep-item { padding: 8px 10px; font-size: 12px; }
    .ep-num { width: 24px; height: 24px; font-size: 10px; }
    .ep-nav { gap: 8px; }
    .ep-nav a { font-size: 11px; padding: 8px 6px; }
    .modal-box { width: 98%; }
}
</style>
</head>
<body>

<header>
    <a href="index.php" class="logo">RINZ</a>
    <div class="search-wrap">
        <span class="icon">🔍</span>
        <input type="text" id="search-input" placeholder="Buscar anime..."
               oninput="buscarLive(this.value)"
               onkeydown="if(event.key==='Enter') buscarForm(this.value)">
        <div id="live-results"></div>
    </div>
    <nav id="main-nav">
        <a href="index.php">Inicio</a>
        <a href="directorio.php">Directorio</a>
        <a href="ruleta.php">RuletaTV</a>
    </nav>
    <button class="menu-btn" onclick="toggleMenu()">☰</button>
</header>

<div class="page">

    <?php if ($banner): ?>
    <div class="banner-section">
        <img class="banner-bg" src="<?php echo htmlspecialchars($banner); ?>" alt="banner"
             onerror="this.closest('.banner-section').style.display='none'">
        <div class="banner-overlay"></div>
        <div class="banner-info">
            <img class="banner-poster" src="<?php echo htmlspecialchars($src); ?>" alt="portada"
                 onerror="this.style.display='none'">
            <div class="banner-text">
                <div class="anime-title-banner"><?php echo htmlspecialchars($anime['title']); ?></div>
                <div class="meta-row">
                    <?php if ($anime['status'] === 'En emisión'): ?>
                        <span class="badge badge-green">En emisión</span>
                    <?php elseif ($anime['status'] === 'Próximamente'): ?>
                        <span class="badge badge-blue">Próximamente</span>
                    <?php else: ?>
                        <span class="badge badge-gray">Finalizado</span>
                    <?php endif; ?>
                    <?php if ($anime['year']): ?><span class="year-tag"><?php echo $anime['year']; ?></span><?php endif; ?>
                    <?php if ($anime['genres']): foreach (array_slice(array_map('trim', explode(',', $anime['genres'])), 0, 3) as $g): ?>
                        <span class="badge badge-genre"><?php echo htmlspecialchars($g); ?></span>
                    <?php endforeach; endif; ?>
                </div>
                <?php if ($trailer_embed): ?>
                <button class="btn-trailer" onclick="abrirTrailer()">▶ Ver trailer</button>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($trailer_embed): ?>
    <div class="modal-overlay" id="trailer-modal" onclick="cerrarTrailer(event)">
        <div class="modal-box">
            <button class="modal-close" onclick="cerrarTrailer()">✕</button>
            <iframe id="trailer-iframe" src="" allow="autoplay; fullscreen" allowfullscreen></iframe>
        </div>
    </div>
    <?php endif; ?>

    <div class="player-section">
        <div class="player-wrap">
            <?php if ($embed_url): ?>
                <iframe src="<?php echo htmlspecialchars($embed_url); ?>"
                    allow="autoplay; fullscreen" allowfullscreen></iframe>
            <?php else: ?>
                <div class="no-video"><span>📺</span><p>Sin episodios disponibles</p></div>
            <?php endif; ?>
        </div>
    </div>

    <div class="content">
        <div class="info-section">
            <?php if ($ep_actual): ?>
                <div class="ep-label">Episodio <?php echo $ep_actual['episode_number']; ?></div>
            <?php endif; ?>
            <h1 class="anime-title"><?php echo htmlspecialchars($anime['title']); ?></h1>
            <?php if ($ep_actual): ?>
                <div class="ep-title"><?php echo htmlspecialchars($ep_actual['episode_title']); ?></div>
            <?php endif; ?>

            <div class="meta-row">
                <?php if ($anime['status'] === 'En emisión'): ?>
                    <span class="badge badge-green">En emisión</span>
                <?php elseif ($anime['status'] === 'Próximamente'): ?>
                    <span class="badge badge-blue">Próximamente</span>
                <?php else: ?>
                    <span class="badge badge-gray">Finalizado</span>
                <?php endif; ?>
                <?php if ($anime['year']): ?><span class="year-tag"><?php echo $anime['year']; ?></span><?php endif; ?>
                <?php if ($anime['category']): ?><span class="badge badge-gray"><?php echo htmlspecialchars($anime['category']); ?></span><?php endif; ?>
                <?php if ($anime['genres']): foreach (array_slice(array_map('trim', explode(',', $anime['genres'])), 0, 4) as $g): ?>
                    <span class="badge badge-genre"><?php echo htmlspecialchars($g); ?></span>
                <?php endforeach; endif; ?>
            </div>

            <?php if ($anime['description']): ?>
                <div class="description"><?php echo nl2br(htmlspecialchars($anime['description'])); ?></div>
            <?php endif; ?>

            <?php
            $prev_ep = null; $next_ep = null;
            foreach ($episodios as $i => $ep) {
                if ($ep['episode_number'] == $ep_activo) {
                    $prev_ep = $episodios[$i - 1] ?? null;
                    $next_ep = $episodios[$i + 1] ?? null;
                    break;
                }
            }
            ?>
            <?php if (count($episodios) > 1): ?>
            <div class="ep-nav">
                <a href="?id=<?php echo $id; ?>&ep=<?php echo $prev_ep ? $prev_ep['episode_number'] : $ep_activo; ?>"
                   class="<?php echo $prev_ep ? '' : 'disabled'; ?>">← Anterior</a>
                <a href="?id=<?php echo $id; ?>&ep=<?php echo $next_ep ? $next_ep['episode_number'] : $ep_activo; ?>"
                   class="<?php echo $next_ep ? '' : 'disabled'; ?>">Siguiente →</a>
            </div>
            <?php endif; ?>
        </div>

        <div class="ep-sidebar">
            <div class="anime-card-mini">
                <img src="<?php echo htmlspecialchars($src); ?>" alt="<?php echo htmlspecialchars($anime['title']); ?>"
                     onerror="this.style.display='none'">
                <div>
                    <div class="mini-title"><?php echo htmlspecialchars($anime['title']); ?></div>
                    <div class="mini-meta"><?php echo count($episodios); ?> episodio<?php echo count($episodios) != 1 ? 's' : ''; ?></div>
                </div>
            </div>

            <div class="sidebar-title">Lista de episodios</div>
            <?php if (count($episodios) > 0): ?>
            <div class="ep-list">
                <?php foreach ($episodios as $ep): ?>
                <a href="?id=<?php echo $id; ?>&ep=<?php echo $ep['episode_number']; ?>"
                   class="ep-item <?php echo $ep['episode_number'] == $ep_activo ? 'active' : ''; ?>">
                    <div class="ep-num"><?php echo $ep['episode_number']; ?></div>
                    <div class="ep-name"><?php echo htmlspecialchars($ep['episode_title']); ?></div>
                </a>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
                <div class="no-eps">Sin episodios cargados</div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function toggleMenu() {
    document.getElementById('main-nav').classList.toggle('open');
}

document.addEventListener('click', e => {
    const nav = document.getElementById('main-nav');
    if (!e.target.closest('nav') && !e.target.closest('.menu-btn')) {
        nav.classList.remove('open');
    }
    if (!e.target.closest('.search-wrap')) {
        document.getElementById('live-results').style.display = 'none';
    }
});

let debounceTimer;
function buscarLive(q) {
    clearTimeout(debounceTimer);
    const box = document.getElementById('live-results');
    if (q.length < 2) { box.style.display = 'none'; return; }
    debounceTimer = setTimeout(() => {
        fetch(`buscar.php?q=${encodeURIComponent(q)}`)
            .then(r => r.json())
            .then(data => {
                if (!data.length) { box.style.display = 'none'; return; }
                box.innerHTML = data.map(a => {
                    const img = a.image_is_url ? a.image : `../uploads/portadas/${a.image}`;
                    return `<div class="live-item" onclick="location.href='series.php?id=${a.id}'">
                        <img src="${img}" onerror="this.style.display='none'">
                        <div>
                            <div class="li-title">${a.title}</div>
                            <div class="li-meta">${a.status ?? ''} ${a.year ? '· ' + a.year : ''}</div>
                        </div>
                    </div>`;
                }).join('');
                box.style.display = 'block';
            })
            .catch(() => box.style.display = 'none');
    }, 300);
}

function buscarForm(q) {
    if (q.trim().length < 2) return;
    const box = document.getElementById('live-results');
    const first = box.querySelector('.live-item');
    if (first) first.click();
    else location.href = `directorio.php?search=${encodeURIComponent(q.trim())}`;
}

function abrirTrailer() {
    document.getElementById('trailer-iframe').src = '<?php echo addslashes($trailer_embed); ?>';
    document.getElementById('trailer-modal').classList.add('open');
    document.body.style.overflow = 'hidden';
}

function cerrarTrailer(e) {
    if (e && e.target !== document.getElementById('trailer-modal') && !e.target.classList.contains('modal-close')) return;
    document.getElementById('trailer-iframe').src = '';
    document.getElementById('trailer-modal').classList.remove('open');
    document.body.style.overflow = '';
}

document.addEventListener('keydown', e => {
    if (e.key === 'Escape') cerrarTrailer({ target: document.getElementById('trailer-modal') });
});
</script>
</body>
</html>