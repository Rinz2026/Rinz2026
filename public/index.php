<?php
require_once '../includes/conexion.php';

function fixDriveImg($url) {
    if (empty($url)) return $url;
    if (preg_match('/\/file\/d\/([a-zA-Z0-9_-]+)/', $url, $m)) {
        return 'https://drive.google.com/uc?export=view&id=' . $m[1];
    }
    return $url;
}

// Carousel - animes en emisión con imagen
$carousel = $conn->query("SELECT * FROM anime WHERE status = 'En emisión' AND image != '' ORDER BY created_at DESC LIMIT 6");

// Episodios recientes
$eps_recientes = $conn->query("
    SELECT e.*, a.title, a.image, a.slug
    FROM episodios e
    JOIN anime a ON e.anime_id = a.id
    ORDER BY e.created_at DESC
    LIMIT 12
");

// Animes en emisión
$en_emision = $conn->query("SELECT * FROM anime WHERE status = 'En emisión' ORDER BY created_at DESC LIMIT 12");

// Próximamente
$proximamente = $conn->query("SELECT * FROM anime WHERE status = 'Próximamente' ORDER BY created_at DESC LIMIT 12");

// Agregados recientemente
$recientes = $conn->query("SELECT * FROM anime ORDER BY created_at DESC LIMIT 12");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rinz - Anime</title>
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

        main { margin-top: 60px; }

        /* CAROUSEL */
        .carousel { position: relative; width: 100%; height: 480px; overflow: hidden; background: #111; }
        .carousel-slides { display: flex; width: 100%; height: 100%; transition: transform .5s ease; }
        .slide { min-width: 100%; height: 100%; position: relative; flex-shrink: 0; }
        .slide-bg { width: 100%; height: 100%; object-fit: cover; object-position: center top; filter: brightness(.35); display: block; }
        .slide-overlay { position: absolute; inset: 0; background: linear-gradient(to right, rgba(10,10,10,.95) 35%, transparent 70%), linear-gradient(to top, #0a0a0a 0%, transparent 40%); }
        .slide-content { position: absolute; bottom: 60px; left: 60px; max-width: 480px; }
        .slide-badge { display: inline-block; padding: 3px 10px; background: #dc2626; border-radius: 4px; font-size: 11px; font-weight: 700; color: #fff; margin-bottom: 12px; letter-spacing: .05em; }
        .slide-title { font-family: 'Rajdhani', sans-serif; font-size: 40px; font-weight: 700; color: #fff; line-height: 1.1; margin-bottom: 10px; text-shadow: 0 2px 8px rgba(0,0,0,.8); }
        .slide-desc { font-size: 13px; color: #aaa; line-height: 1.6; margin-bottom: 20px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
        .slide-genres { display: flex; gap: 6px; flex-wrap: wrap; margin-bottom: 20px; }
        .slide-genre { padding: 3px 10px; background: rgba(255,255,255,.1); border-radius: 4px; font-size: 11px; color: #ccc; }
        .btn-ver { display: inline-flex; align-items: center; gap: 8px; padding: 11px 24px; background: #dc2626; border-radius: 8px; color: #fff; font-size: 14px; font-weight: 600; text-decoration: none; transition: background .2s; }
        .btn-ver:hover { background: #b91c1c; }
        .carousel-dots { position: absolute; bottom: 20px; left: 60px; display: flex; gap: 8px; }
        .dot { width: 8px; height: 8px; border-radius: 50%; background: rgba(255,255,255,.3); cursor: pointer; transition: all .2s; border: none; }
        .dot.active { background: #dc2626; width: 24px; border-radius: 4px; }
        .carousel-btn { position: absolute; top: 50%; transform: translateY(-50%); background: rgba(0,0,0,.5); border: 1px solid rgba(255,255,255,.1); color: #fff; width: 40px; height: 40px; border-radius: 50%; cursor: pointer; font-size: 16px; display: flex; align-items: center; justify-content: center; transition: all .2s; }
        .carousel-btn:hover { background: #dc2626; }
        .carousel-btn.prev { left: 20px; }
        .carousel-btn.next { right: 20px; }

        /* SECCIONES */
        .section { padding: 32px; }
        .section-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; }
        .section-title { font-family: 'Rajdhani', sans-serif; font-size: 20px; font-weight: 700; color: #fff; letter-spacing: 1px; display: flex; align-items: center; gap: 10px; }
        .section-title::before { content: ''; width: 4px; height: 20px; background: #dc2626; border-radius: 2px; display: block; }
        .section-link { font-size: 12px; color: #555; text-decoration: none; transition: color .2s; }
        .section-link:hover { color: #dc2626; }

        /* GRID EPISODIOS */
        .ep-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 12px; }
        .ep-card { background: #111; border-radius: 10px; overflow: hidden; border: 1px solid #1a1a1a; text-decoration: none; color: inherit; display: block; transition: transform .2s, border-color .2s; }
        .ep-card:hover { transform: translateY(-4px); border-color: #dc2626; }
        .ep-card-img { position: relative; }
        .ep-card-img img { width: 100%; aspect-ratio: 16/9; object-fit: cover; display: block; background: #1a1a1a; }
        .ep-num-badge { position: absolute; bottom: 6px; right: 6px; background: rgba(0,0,0,.8); border-radius: 4px; padding: 2px 7px; font-size: 11px; font-weight: 600; color: #fff; }
        .ep-card-body { padding: 8px 10px 10px; }
        .ep-card-title { font-size: 12px; font-weight: 600; color: #e0e0e0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin-bottom: 3px; }
        .ep-card-meta { font-size: 11px; color: #555; }

        /* GRID ANIMES */
        .anime-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(148px, 1fr)); gap: 12px; }
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

        footer { text-align: center; padding: 32px; color: #333; font-size: 13px; border-top: 1px solid #111; margin-top: 20px; }

        /* PANTALLA GRANDE (1440px+) */
        @media (min-width: 1440px) {
            .carousel { height: 560px; }
            .slide-content { left: 80px; max-width: 600px; }
            .slide-title { font-size: 52px; }
            .section { padding: 40px 60px; }
        }

        /* TABLET (769px - 1024px) */
        @media (max-width: 1024px) {
            .carousel { height: 380px; }
            .slide-content { left: 40px; max-width: 380px; }
            .slide-title { font-size: 32px; }
            .carousel-dots { left: 40px; }
            .section { padding: 24px; }
            .anime-grid { grid-template-columns: repeat(auto-fill, minmax(130px, 1fr)); }
            .ep-grid { grid-template-columns: repeat(auto-fill, minmax(170px, 1fr)); }
        }

        /* MÓVIL GRANDE (481px - 768px) */
        @media (max-width: 768px) {
            header { padding: 0 16px; gap: 12px; }
            .menu-btn { display: block; }
            nav { display: none; flex-direction: column; position: absolute; top: 60px; right: 0; background: #111; border: 1px solid #222; border-radius: 0 0 8px 8px; padding: 12px 20px; gap: 14px; min-width: 140px; z-index: 99; }
            nav.open { display: flex; }
            .search-wrap { max-width: 100%; }

            .carousel { height: 300px; }
            .slide-content { left: 20px; bottom: 40px; max-width: 260px; }
            .slide-title { font-size: 22px; }
            .slide-desc { display: none; }
            .carousel-dots { left: 20px; bottom: 12px; }
            .carousel-btn { width: 32px; height: 32px; font-size: 14px; }

            .section { padding: 16px; }
            .ep-grid { grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 10px; }
            .anime-grid { grid-template-columns: repeat(auto-fill, minmax(120px, 1fr)); gap: 10px; }
        }

        /* MÓVIL PEQUEÑO (hasta 480px) */
        @media (max-width: 480px) {
            header { padding: 0 12px; height: 54px; }
            .logo { font-size: 22px; letter-spacing: 2px; }
            .search-wrap input { font-size: 13px; padding: 7px 12px 7px 34px; }
            nav { top: 54px; }
            main { margin-top: 54px; }

            .carousel { height: 240px; }
            .slide-content { left: 14px; bottom: 30px; max-width: 220px; }
            .slide-title { font-size: 18px; margin-bottom: 6px; }
            .slide-badge { font-size: 10px; margin-bottom: 6px; }
            .slide-genres { display: none; }
            .btn-ver { padding: 8px 16px; font-size: 12px; }
            .carousel-dots { left: 14px; bottom: 8px; }
            .carousel-btn { display: none; }

            .section { padding: 12px; }
            .section-title { font-size: 16px; }
            .ep-grid { grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 8px; }
            .anime-grid { grid-template-columns: repeat(3, 1fr); gap: 8px; }
            .card-title { font-size: 11px; }
        }
        /* INTRO */
#intro-screen { position: fixed; inset: 0; background: #0a0a0a; z-index: 9999; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 16px; }
#intro-screen.hide { animation: introFade .6s ease forwards; pointer-events: none; }
@keyframes introFade { to { opacity: 0; visibility: hidden; } }

.intro-logo { display: flex; gap: 6px; }
.intro-logo span { font-family: 'Rajdhani', sans-serif; font-size: 72px; font-weight: 700; color: #dc2626; opacity: 0; transform: translateY(20px); animation: letterIn .4s ease forwards; }
.intro-logo span:nth-child(1) { animation-delay: .1s; }
.intro-logo span:nth-child(2) { animation-delay: .2s; }
.intro-logo span:nth-child(3) { animation-delay: .3s; }
.intro-logo span:nth-child(4) { animation-delay: .4s; }
@keyframes letterIn { to { opacity: 1; transform: translateY(0); } }

.intro-line { width: 0; height: 2px; background: #dc2626; animation: lineGrow .5s ease .6s forwards; }
@keyframes lineGrow { to { width: 120px; } }
    </style>
</head>
<body>
    <!-- INTRO -->
<div id="intro-screen">
    <div class="intro-logo">
        <span>R</span><span>I</span><span>N</span><span>Z</span>
    </div>
    <div class="intro-line"></div>
</div>

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

<main>

    <!-- CAROUSEL -->
    <?php
    $slides = [];
    while ($s = $carousel->fetch_assoc()) $slides[] = $s;
    ?>
    <?php if (count($slides) > 0): ?>
    <div class="carousel" id="carousel">
        <div class="carousel-slides" id="slides">
            <?php foreach ($slides as $s):
                $src = filter_var($s['image'], FILTER_VALIDATE_URL) ? $s['image'] : '../uploads/portadas/' . $s['image'];
                $banner = !empty($s['banner'])
                    ? fixDriveImg(filter_var($s['banner'], FILTER_VALIDATE_URL) ? $s['banner'] : '../uploads/portadas/' . $s['banner'])
                    : $src;
            ?>
            <div class="slide">
                <img class="slide-bg" src="<?= htmlspecialchars($banner) ?>" alt="">
                <div class="slide-overlay"></div>
                <div class="slide-content">
                    <span class="slide-badge">En emisión</span>
                    <div class="slide-title"><?= htmlspecialchars($s['title']) ?></div>
                    <?php if ($s['description']): ?>
                    <div class="slide-desc"><?= htmlspecialchars(substr($s['description'], 0, 150)) ?>...</div>
                    <?php endif; ?>
                    <?php if ($s['genres']): ?>
                    <div class="slide-genres">
                        <?php foreach (array_slice(array_map('trim', explode(',', $s['genres'])), 0, 3) as $g): ?>
                        <span class="slide-genre"><?= htmlspecialchars($g) ?></span>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                    <a href="series.php?id=<?= $s['id'] ?>" class="btn-ver">▶ Ver ahora</a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <button class="carousel-btn prev" onclick="moverCarousel(-1)">‹</button>
        <button class="carousel-btn next" onclick="moverCarousel(1)">›</button>

        <div class="carousel-dots" id="dots">
            <?php for ($i = 0; $i < count($slides); $i++): ?>
            <button class="dot <?= $i === 0 ? 'active' : '' ?>" onclick="irSlide(<?= $i ?>)"></button>
            <?php endfor; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- EPISODIOS RECIENTES -->
    <?php if ($eps_recientes->num_rows > 0): ?>
    <div class="section">
        <div class="section-header">
            <div class="section-title">Episodios recientes</div>
        </div>
        <div class="ep-grid">
            <?php while ($ep = $eps_recientes->fetch_assoc()):
                $src = filter_var($ep['image'], FILTER_VALIDATE_URL) ? $ep['image'] : '../uploads/portadas/' . $ep['image'];
            ?>
            <a href="series.php?id=<?= $ep['anime_id'] ?>&ep=<?= $ep['episode_number'] ?>" class="ep-card">
                <div class="ep-card-img">
                    <img src="<?= htmlspecialchars($src) ?>" alt="" onerror="this.style.display='none'">
                    <span class="ep-num-badge">Ep. <?= $ep['episode_number'] ?></span>
                </div>
                <div class="ep-card-body">
                    <div class="ep-card-title"><?= htmlspecialchars($ep['title']) ?></div>
                    <div class="ep-card-meta"><?= htmlspecialchars($ep['episode_title']) ?></div>
                </div>
            </a>
            <?php endwhile; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- EN EMISIÓN -->
    <?php if ($en_emision->num_rows > 0): ?>
    <div class="section">
        <div class="section-header">
            <div class="section-title">En emisión</div>
            <a href="directorio.php?status=En+emisión" class="section-link">Ver todos →</a>
        </div>
        <div class="anime-grid">
            <?php while ($a = $en_emision->fetch_assoc()):
                $src = filter_var($a['image'], FILTER_VALIDATE_URL) ? $a['image'] : '../uploads/portadas/' . $a['image'];
            ?>
            <a href="series.php?id=<?= $a['id'] ?>" class="card">
                <div class="card-img-wrap">
                    <img class="card-img" src="<?= htmlspecialchars($src) ?>" alt="" onerror="this.style.display='none'">
                    <span class="card-tipo"><?= htmlspecialchars($a['category'] ?: 'Anime') ?></span>
                </div>
                <div class="card-body">
                    <div class="card-title"><?= htmlspecialchars($a['title']) ?></div>
                    <div class="card-meta">
                        <span class="badge badge-green">En emisión</span>
                        <?php if ($a['year']): ?><span class="card-year"><?= $a['year'] ?></span><?php endif; ?>
                    </div>
                </div>
            </a>
            <?php endwhile; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- AGREGADOS RECIENTEMENTE -->
    <div class="section">
        <div class="section-header">
            <div class="section-title">Agregados recientemente</div>
        </div>
        <div class="anime-grid">
            <?php while ($a = $recientes->fetch_assoc()):
                $src = filter_var($a['image'], FILTER_VALIDATE_URL) ? $a['image'] : '../uploads/portadas/' . $a['image'];
            ?>
            <a href="series.php?id=<?= $a['id'] ?>" class="card">
                <div class="card-img-wrap">
                    <img class="card-img" src="<?= htmlspecialchars($src) ?>" alt="" onerror="this.style.display='none'">
                    <span class="card-tipo"><?= htmlspecialchars($a['category'] ?: 'Anime') ?></span>
                </div>
                <div class="card-body">
                    <div class="card-title"><?= htmlspecialchars($a['title']) ?></div>
                    <div class="card-meta">
                        <span class="badge badge-gray"><?= htmlspecialchars($a['status'] ?: '') ?></span>
                        <?php if ($a['year']): ?><span class="card-year"><?= $a['year'] ?></span><?php endif; ?>
                    </div>
                </div>
            </a>
            <?php endwhile; ?>
        </div>
    </div>

</main>

<footer>Rinz Anime © 2025</footer>

<script>
// INTRO
(function() {
    if (sessionStorage.getItem('intro_shown')) {
        document.getElementById('intro-screen').style.display = 'none';
        return;
    }
    setTimeout(() => {
        document.getElementById('intro-screen').classList.add('hide');
        sessionStorage.setItem('intro_shown', '1');
    }, 1800);
})();
// MENU MÓVIL
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

// BUSCADOR
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
    if (first) { first.click(); }
    else { location.href = `directorio.php?q=${encodeURIComponent(q.trim())}`; }
}

// CAROUSEL
let currentSlide = 0;
const slidesEl = document.getElementById('slides');
const dotsEl = document.querySelectorAll('.dot');

function irSlide(n) {
    currentSlide = n;
    if (slidesEl) slidesEl.style.transform = `translateX(-${n * 100}%)`;
    dotsEl.forEach((d, i) => d.classList.toggle('active', i === n));
}

function moverCarousel(dir) {
    const total = dotsEl.length;
    if (total > 0) irSlide((currentSlide + dir + total) % total);
}

if (dotsEl.length > 0) setInterval(() => moverCarousel(1), 5000);
</script>
</body>
</html>