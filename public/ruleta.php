<?php
require_once '../includes/conexion.php';

function fixDriveImg($url) {
    if (empty($url)) return $url;
    if (preg_match('/\/file\/d\/([a-zA-Z0-9_-]+)/', $url, $m)) {
        return 'https://drive.google.com/uc?export=view&id=' . $m[1];
    }
    return $url;
}

// Géneros únicos
$gen_raw = $conn->query("SELECT genres FROM anime WHERE genres != '' AND genres IS NOT NULL");
$generos_set = [];
while ($row = $gen_raw->fetch_assoc()) {
    foreach (array_map('trim', explode(',', $row['genres'])) as $g) {
        if ($g) $generos_set[$g] = true;
    }
}
ksort($generos_set);

// Años
$años_raw = $conn->query("SELECT DISTINCT year FROM anime WHERE year IS NOT NULL AND year > 0 ORDER BY year DESC");
$años = [];
while ($r = $años_raw->fetch_assoc()) $años[] = $r['year'];

$tipos = ['Anime','Película','OVA','ONA','Especial','Music'];
$conn->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Ruleta Anime - Rinz</title>
<link rel="icon" href="favicon.svg">
<style>
@import url('https://fonts.googleapis.com/css2?family=Rajdhani:wght@500;700&family=Inter:wght@400;500&display=swap');
*{box-sizing:border-box;margin:0;padding:0}
body{background:#0a0a0a;color:#e0e0e0;font-family:'Inter',sans-serif;min-height:100vh;overflow-x:hidden}

/* HEADER */
header{position:fixed;top:0;left:0;width:100%;z-index:100;background:rgba(10,10,10,.95);border-bottom:1px solid #1a1a1a;padding:0 32px;height:60px;display:flex;align-items:center;justify-content:space-between;gap:24px}
.logo{font-family:'Rajdhani',sans-serif;font-size:26px;font-weight:700;color:#dc2626;letter-spacing:3px;text-decoration:none;flex-shrink:0}
.search-wrap{flex:1;max-width:480px;position:relative}
.search-wrap input{width:100%;padding:8px 16px 8px 40px;background:#111;border:1px solid #222;border-radius:8px;color:#fff;font-size:14px;outline:none;transition:border-color .2s}
.search-wrap input:focus{border-color:#dc2626}
.search-wrap .icon{position:absolute;left:12px;top:50%;transform:translateY(-50%);color:#555;font-size:16px;pointer-events:none}
#live-results{position:absolute;top:calc(100% + 6px);left:0;right:0;background:#111;border:1px solid #222;border-radius:8px;overflow:hidden;display:none;z-index:200;max-height:320px;overflow-y:auto}
.live-item{display:flex;align-items:center;gap:10px;padding:8px 12px;cursor:pointer;transition:background .15s}
.live-item:hover{background:#1a1a1a}
.live-item img{width:30px;height:42px;object-fit:cover;border-radius:4px;flex-shrink:0}
.live-item .li-title{font-size:13px;color:#fff}
.live-item .li-meta{font-size:11px;color:#555}
nav{display:flex;gap:20px;flex-shrink:0}
nav a{color:#888;text-decoration:none;font-size:14px;transition:color .2s}
nav a:hover,nav a.active{color:#fff}
.menu-btn{display:none;background:none;border:none;color:#fff;font-size:22px;cursor:pointer;flex-shrink:0;padding:4px 8px}

/* PÁGINA */
.page{margin-top:60px;min-height:calc(100vh - 60px);display:flex;flex-direction:column;align-items:center;padding:40px 20px}

.page-title{font-family:'Rajdhani',sans-serif;font-size:28px;font-weight:700;color:#fff;letter-spacing:2px;text-align:center;margin-bottom:6px}
.page-sub{font-size:13px;color:#555;text-align:center;margin-bottom:36px}

/* FILTROS */
.filtros{display:flex;flex-wrap:wrap;gap:10px;justify-content:center;margin-bottom:40px;max-width:700px;width:100%}
.filtro-group{display:flex;flex-direction:column;gap:5px}
.filtro-label{font-size:11px;color:#555;text-transform:uppercase;letter-spacing:.08em;font-weight:600}
.filtro-select{background:#111;border:1px solid #222;color:#e0e0e0;padding:8px 12px;border-radius:8px;font-size:13px;outline:none;cursor:pointer;transition:border-color .2s;min-width:140px}
.filtro-select:focus{border-color:#dc2626}

/* ESCENA 3D */
.scene-wrap{position:relative;width:320px;height:320px;margin-bottom:32px}
#canvas3d{display:block;border-radius:16px}

/* BOTÓN */
.btn-girar{padding:14px 40px;background:#dc2626;border:none;border-radius:10px;color:#fff;font-family:'Rajdhani',sans-serif;font-size:20px;font-weight:700;letter-spacing:2px;cursor:pointer;transition:all .2s;display:flex;align-items:center;gap:10px}
.btn-girar:hover{background:#b91c1c;transform:translateY(-2px)}
.btn-girar:disabled{background:#333;color:#555;cursor:not-allowed;transform:none}
.btn-girar svg{width:20px;height:20px}

/* RESULTADO - CARTA GACHA */
.resultado-wrap{margin-top:40px;display:none;flex-direction:column;align-items:center;gap:16px}
.resultado-wrap.show{display:flex}

.carta-scene{perspective:1000px;width:220px;height:320px}
.carta{width:100%;height:100%;position:relative;transform-style:preserve-3d;animation:cartaReveal .8s ease forwards}
@keyframes cartaReveal{
    0%{transform:rotateY(180deg) scale(.6);opacity:0}
    60%{transform:rotateY(-10deg) scale(1.05);opacity:1}
    100%{transform:rotateY(0deg) scale(1);opacity:1}
}
.carta-front{position:absolute;inset:0;border-radius:14px;overflow:hidden;border:2px solid #dc2626;background:#111}
.carta-front img{width:100%;height:100%;object-fit:cover;display:block}
.carta-front .carta-overlay{position:absolute;bottom:0;left:0;right:0;background:linear-gradient(to top,rgba(0,0,0,.95) 0%,transparent 60%);padding:14px 12px 12px}
.carta-front .carta-titulo{font-family:'Rajdhani',sans-serif;font-size:15px;font-weight:700;color:#fff;line-height:1.2;margin-bottom:5px}
.carta-front .carta-badges{display:flex;gap:4px;flex-wrap:wrap}
.carta-badge{font-size:10px;font-weight:600;padding:2px 7px;border-radius:999px}
.carta-badge-rojo{background:rgba(220,38,38,.3);color:#f87171;border:1px solid rgba(220,38,38,.4)}
.carta-badge-gris{background:rgba(255,255,255,.08);color:#888}
.carta-badge-genre{background:rgba(139,92,246,.2);color:#c4b5fd;border:1px solid rgba(139,92,246,.3)}

/* brillo gacha */
.carta-front::after{content:'';position:absolute;inset:0;background:linear-gradient(135deg,rgba(255,255,255,.15) 0%,transparent 50%,rgba(255,255,255,.05) 100%);border-radius:14px;pointer-events:none;animation:brilloIn .8s ease .5s both}
@keyframes brilloIn{from{opacity:0}to{opacity:1}}

.resultado-titulo{font-family:'Rajdhani',sans-serif;font-size:22px;font-weight:700;color:#fff;text-align:center}
.resultado-meta{font-size:13px;color:#666;text-align:center}
.btn-ver{display:inline-flex;align-items:center;gap:8px;padding:11px 28px;background:#dc2626;border-radius:8px;color:#fff;font-size:14px;font-weight:600;text-decoration:none;transition:background .2s;margin-top:4px}
.btn-ver:hover{background:#b91c1c}
.btn-otra{background:none;border:1px solid #333;color:#888;padding:10px 24px;border-radius:8px;font-size:13px;cursor:pointer;transition:all .2s;margin-top:4px}
.btn-otra:hover{border-color:#dc2626;color:#fff}

/* PARTÍCULAS */
.particulas{position:fixed;inset:0;pointer-events:none;z-index:50;overflow:hidden}
.particula{position:absolute;width:8px;height:8px;border-radius:2px;opacity:0;animation:particula-anim 1.2s ease forwards}
@keyframes particula-anim{
    0%{opacity:1;transform:translateY(0) rotate(0deg) scale(1)}
    100%{opacity:0;transform:translateY(-300px) rotate(720deg) scale(0)}
}

/* RESPONSIVE */
@media(max-width:768px){
    header{padding:0 16px;gap:12px}
    .menu-btn{display:block}
    nav{display:none;flex-direction:column;position:absolute;top:60px;right:0;background:#111;border:1px solid #222;border-radius:0 0 8px 8px;padding:12px 20px;gap:14px;min-width:140px;z-index:99}
    nav.open{display:flex}
    .search-wrap{max-width:100%}
    .scene-wrap{width:260px;height:260px}
    .filtros{gap:8px}
    .filtro-select{min-width:120px;font-size:12px}
    .btn-girar{padding:12px 28px;font-size:17px}
    .carta-scene{width:180px;height:260px}
    .resultado-titulo{font-size:18px}
    .page-title{font-size:22px}
}
@media(max-width:480px){
    header{padding:0 12px;height:54px}
    .logo{font-size:22px;letter-spacing:2px}
    nav{top:54px}
    .page{margin-top:54px;padding:20px 12px}
    .scene-wrap{width:200px;height:200px}
    .filtros{flex-direction:column;align-items:stretch;width:100%;max-width:300px}
    .filtro-group{width:100%}
    .filtro-select{width:100%;min-width:unset}
    .btn-girar{padding:11px 24px;font-size:15px;width:100%;max-width:300px;justify-content:center}
    .carta-scene{width:160px;height:230px}
    .resultado-titulo{font-size:16px}
    .resultado-meta{font-size:12px}
    .page-title{font-size:18px;letter-spacing:1px}
    .page-sub{font-size:12px}
    .btn-ver{padding:9px 20px;font-size:13px}
    .btn-otra{padding:8px 20px;font-size:12px}
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
        <a href="ruleta.php" class="active">RuletaTV</a>
    </nav>
    <button class="menu-btn" onclick="toggleMenu()">☰</button>
</header>

<div class="particulas" id="particulas"></div>

<div class="page">
    <div class="page-title">🎲 RULETA ANIME</div>
    <div class="page-sub">Filtra y deja que el destino elija qué ver hoy</div>

    <!-- FILTROS -->
    <div class="filtros">
        <div class="filtro-group">
            <span class="filtro-label">Género</span>
            <select class="filtro-select" id="f-genero">
                <option value="">Cualquier género</option>
                <?php foreach ($generos_set as $g => $_): ?>
                <option value="<?= htmlspecialchars($g) ?>"><?= htmlspecialchars($g) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="filtro-group">
            <span class="filtro-label">Año</span>
            <select class="filtro-select" id="f-año">
                <option value="">Cualquier año</option>
                <?php foreach ($años as $a): ?>
                <option value="<?= $a ?>"><?= $a ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="filtro-group">
            <span class="filtro-label">Tipo</span>
            <select class="filtro-select" id="f-tipo">
                <option value="">Cualquier tipo</option>
                <?php foreach ($tipos as $t): ?>
                <option value="<?= $t ?>"><?= $t ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="filtro-group">
            <span class="filtro-label">Estado</span>
            <select class="filtro-select" id="f-estado">
                <option value="">Cualquier estado</option>
                <option value="En emisión">En emisión</option>
                <option value="Finalizado">Finalizado</option>
                <option value="Próximamente">Próximamente</option>
            </select>
        </div>
    </div>

    <!-- CAJA 3D -->
    <div class="scene-wrap" id="scene-wrap">
        <canvas id="canvas3d"></canvas>
    </div>

    <!-- BOTÓN -->
    <button class="btn-girar" id="btn-girar" onclick="girar()">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <path d="M21 2v6h-6M3 12a9 9 0 0 1 15-6.7L21 8M3 22v-6h6M21 12a9 9 0 0 1-15 6.7L3 16"/>
        </svg>
        GIRAR
    </button>

    <!-- RESULTADO -->
    <div class="resultado-wrap" id="resultado">
        <div class="carta-scene">
            <div class="carta" id="carta">
                <div class="carta-front">
                    <img id="carta-img" src="" alt="">
                    <div class="carta-overlay">
                        <div class="carta-titulo" id="carta-titulo"></div>
                        <div class="carta-badges" id="carta-badges"></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="resultado-titulo" id="res-titulo"></div>
        <div class="resultado-meta" id="res-meta"></div>
        <a href="#" class="btn-ver" id="btn-ver">▶ Ver anime</a>
        <button class="btn-otra" onclick="girar()">↻ Otra vez</button>
    </div>
</div>

<!-- THREE.JS -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js"></script>
<script>
/* =========================================================
   CAJA 3D con Three.js
   ========================================================= */
const canvas = document.getElementById('canvas3d');
const renderer = new THREE.WebGLRenderer({ canvas, alpha: true, antialias: true });
renderer.setPixelRatio(window.devicePixelRatio);

const scene = new THREE.Scene();
const camera = new THREE.PerspectiveCamera(45, 1, 0.1, 100);
camera.position.set(0, 1.2, 4.5);
camera.lookAt(0, 0, 0);

function resizeCanvas() {
    const wrap = document.getElementById('scene-wrap');
    const size = wrap.offsetWidth;
    renderer.setSize(size, size);
    camera.aspect = 1;
    camera.updateProjectionMatrix();
}
resizeCanvas();
window.addEventListener('resize', resizeCanvas);

// Luces
const ambLight = new THREE.AmbientLight(0xffffff, 0.5);
scene.add(ambLight);
const dirLight = new THREE.DirectionalLight(0xffffff, 1.2);
dirLight.position.set(3, 4, 3);
scene.add(dirLight);
const redLight = new THREE.PointLight(0xdc2626, 1.5, 8);
redLight.position.set(-2, 2, 2);
scene.add(redLight);

// Materiales
const matCaja = new THREE.MeshStandardMaterial({ color: 0xd4a017, roughness: 0.4, metalness: 0.3 });
const matTapa = new THREE.MeshStandardMaterial({ color: 0xe8b820, roughness: 0.35, metalness: 0.35 });
const matBorde = new THREE.MeshStandardMaterial({ color: 0xb8860b, roughness: 0.5, metalness: 0.4 });

// Grupo principal
const grupo = new THREE.Group();
scene.add(grupo);

// Cuerpo de la caja
const geoCuerpo = new THREE.BoxGeometry(2, 1.6, 2);
const cuerpo = new THREE.Mesh(geoCuerpo, matCaja);
cuerpo.position.y = -0.2;
grupo.add(cuerpo);

// Tapa (separada para animarla)
const geoTapa = new THREE.BoxGeometry(2.05, 0.45, 2.05);
const tapa = new THREE.Mesh(geoTapa, matTapa);
tapa.position.y = 0.82;
grupo.add(tapa);

// Bordes decorativos
const geoBorde = new THREE.BoxGeometry(2.12, 0.08, 2.12);
[0.6, -0.2, -0.98].forEach(y => {
    const b = new THREE.Mesh(geoBorde, matBorde);
    b.position.y = y;
    grupo.add(b);
});

// Lazo encima
const geoLazo = new THREE.TorusGeometry(0.25, 0.05, 8, 20);
const matLazo = new THREE.MeshStandardMaterial({ color: 0xdc2626, roughness: 0.5 });
const lazo = new THREE.Mesh(geoLazo, matLazo);
lazo.position.y = 1.1;
lazo.rotation.x = Math.PI / 2;
grupo.add(lazo);

// Estado de animación
let estado = 'idle'; // idle | temblando | abriendo | abierta
let tiempoAnim = 0;
let tapaPosY = 0.82;
let tapaRotX = 0;

function animate() {
    requestAnimationFrame(animate);
    tiempoAnim += 0.016;

    if (estado === 'idle') {
        grupo.rotation.y += 0.006;
        grupo.position.y = Math.sin(tiempoAnim * 0.8) * 0.06;
    }

    if (estado === 'temblando') {
        grupo.rotation.z = Math.sin(tiempoAnim * 30) * 0.08;
        grupo.rotation.x = Math.sin(tiempoAnim * 25) * 0.05;
        grupo.position.y = Math.abs(Math.sin(tiempoAnim * 20)) * 0.12;
        redLight.intensity = 1.5 + Math.sin(tiempoAnim * 20) * 1;
    }

    if (estado === 'abriendo') {
        tapaRotX = Math.min(tapaRotX + 0.06, Math.PI * 0.75);
        tapa.rotation.x = -tapaRotX;
        tapa.position.y = 0.82 + Math.sin(tapaRotX) * 0.4;
        tapa.position.z = -Math.sin(tapaRotX) * 0.6;
        lazo.rotation.x = Math.PI / 2 + tapaRotX;
        lazo.position.z = -Math.sin(tapaRotX) * 0.6;
        lazo.position.y = 1.1 + Math.sin(tapaRotX) * 0.4;

        if (tapaRotX >= Math.PI * 0.75) {
            estado = 'abierta';
            mostrarCarta();
        }
    }

    if (estado === 'abierta') {
        grupo.rotation.y += 0.003;
        grupo.position.y = Math.sin(tiempoAnim * 0.5) * 0.04;
        redLight.intensity = 1.5 + Math.sin(tiempoAnim * 2) * 0.3;
    }

    renderer.render(scene, camera);
}
animate();

/* =========================================================
   LÓGICA RULETA
   ========================================================= */
let animeActual = null;

function girar() {
    if (estado === 'temblando' || estado === 'abriendo') return;

    const btn = document.getElementById('btn-girar');
    btn.disabled = true;

    // Reset caja
    resetCaja();

    // Ocultar resultado anterior
    document.getElementById('resultado').classList.remove('show');

    // Obtener filtros
    const genero = document.getElementById('f-genero').value;
    const año = document.getElementById('f-año').value;
    const tipo = document.getElementById('f-tipo').value;
    const est = document.getElementById('f-estado').value;

    const params = new URLSearchParams({ action: 'random' });
    if (genero) params.append('genero', genero);
    if (año) params.append('anio', año);
    if (tipo) params.append('tipo', tipo);
    if (est) params.append('estado', est);

    // Fase 1: temblar
    estado = 'temblando';
    tiempoAnim = 0;

    fetch('ruleta_api.php?' + params)
        .then(r => r.json())
        .then(data => {
            animeActual = data;
            // Después de 1.8s de temblor, abrir
            setTimeout(() => {
                estado = 'abriendo';
                tapaRotX = 0;
            }, 1800);
        })
        .catch(() => {
            estado = 'idle';
            btn.disabled = false;
            alert('No se encontraron animes con esos filtros.');
        });
}

function resetCaja() {
    grupo.rotation.set(0, 0, 0);
    grupo.position.set(0, 0, 0);
    tapa.rotation.set(0, 0, 0);
    tapa.position.set(0, 0.82, 0);
    lazo.rotation.set(Math.PI / 2, 0, 0);
    lazo.position.set(0, 1.1, 0);
    tapaPosY = 0.82;
    tapaRotX = 0;
}

function mostrarCarta() {
    if (!animeActual || animeActual.error) {
        document.getElementById('btn-girar').disabled = false;
        estado = 'idle';
        alert(animeActual?.error || 'Sin resultados para esos filtros.');
        return;
    }

    const a = animeActual;
    const img = a.image_is_url ? a.image : `../uploads/portadas/${a.image}`;

    document.getElementById('carta-img').src = img;
    document.getElementById('carta-titulo').textContent = a.title;
    document.getElementById('res-titulo').textContent = a.title;
    document.getElementById('res-meta').textContent = [a.category, a.year, a.status].filter(Boolean).join(' · ');
    document.getElementById('btn-ver').href = `series.php?id=${a.id}`;

    // Badges
    const badges = document.getElementById('carta-badges');
    badges.innerHTML = '';
    if (a.status) badges.innerHTML += `<span class="carta-badge carta-badge-gris">${a.status}</span>`;
    if (a.year) badges.innerHTML += `<span class="carta-badge carta-badge-gris">${a.year}</span>`;
    if (a.genres) {
        a.genres.split(',').slice(0, 2).forEach(g => {
            badges.innerHTML += `<span class="carta-badge carta-badge-genre">${g.trim()}</span>`;
        });
    }

    // Resetear animación de la carta
    const carta = document.getElementById('carta');
    carta.style.animation = 'none';
    carta.offsetHeight;
    carta.style.animation = '';

    document.getElementById('resultado').classList.add('show');
    lanzarParticulas();

    document.getElementById('btn-girar').disabled = false;
}

function lanzarParticulas() {
    const cont = document.getElementById('particulas');
    cont.innerHTML = '';
    const colores = ['#dc2626','#fbbf24','#a78bfa','#34d399','#f472b6','#60a5fa'];
    for (let i = 0; i < 40; i++) {
        const p = document.createElement('div');
        p.className = 'particula';
        p.style.cssText = `
            left:${Math.random()*100}%;
            top:${60 + Math.random()*30}%;
            background:${colores[Math.floor(Math.random()*colores.length)]};
            animation-delay:${Math.random()*0.5}s;
            animation-duration:${0.8 + Math.random()*0.8}s;
            width:${4 + Math.random()*8}px;
            height:${4 + Math.random()*8}px;
            border-radius:${Math.random() > 0.5 ? '50%' : '2px'};
        `;
        cont.appendChild(p);
    }
    setTimeout(() => cont.innerHTML = '', 2000);
}

/* =========================================================
   HEADER - búsqueda y menú (igual que el resto del sitio)
   ========================================================= */
function toggleMenu() {
    document.getElementById('main-nav').classList.toggle('open');
}
document.addEventListener('click', e => {
    const nav = document.getElementById('main-nav');
    if (!e.target.closest('nav') && !e.target.closest('.menu-btn')) nav.classList.remove('open');
    if (!e.target.closest('.search-wrap')) document.getElementById('live-results').style.display = 'none';
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
                        <div><div class="li-title">${a.title}</div><div class="li-meta">${a.status ?? ''} ${a.year ? '· ' + a.year : ''}</div></div>
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
</script>
</body>
</html>
