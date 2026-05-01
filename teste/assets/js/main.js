/* 
  L&J Editions 3D
  HTML + CSS + JS puro.
  Bibliotecas via CDN:
  - GSAP + ScrollTrigger
  - Lenis
  - Three.js
*/

const header = document.getElementById("siteHeader");
const menuToggle = document.getElementById("menuToggle");
const mainNav = document.getElementById("mainNav");
const year = document.getElementById("year");

if (year) {
  year.textContent = new Date().getFullYear();
}

const prefersReducedMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;

/* Menu mobile */
if (menuToggle && mainNav) {
  menuToggle.addEventListener("click", () => {
    const isOpen = mainNav.classList.toggle("is-open");
    menuToggle.classList.toggle("is-open", isOpen);
    menuToggle.setAttribute("aria-expanded", String(isOpen));
    document.body.classList.toggle("menu-open", isOpen);
  });

  mainNav.querySelectorAll("a").forEach((link) => {
    link.addEventListener("click", () => {
      mainNav.classList.remove("is-open");
      menuToggle.classList.remove("is-open");
      menuToggle.setAttribute("aria-expanded", "false");
      document.body.classList.remove("menu-open");
    });
  });
}

window.addEventListener("scroll", () => {
  header?.classList.toggle("is-scrolled", window.scrollY > 20);
}, { passive: true });

/* Lenis scroll suave */
let lenis = null;

if (!prefersReducedMotion && window.Lenis) {
  lenis = new Lenis({
    duration: 1.15,
    smoothWheel: true,
    wheelMultiplier: 0.95,
    touchMultiplier: 1.2
  });

  function raf(time) {
    lenis.raf(time);
    requestAnimationFrame(raf);
  }

  requestAnimationFrame(raf);
}

/* GSAP */
if (window.gsap && window.ScrollTrigger) {
  gsap.registerPlugin(ScrollTrigger);

  if (lenis) {
    lenis.on("scroll", ScrollTrigger.update);

    gsap.ticker.add((time) => {
      lenis.raf(time * 1000);
    });

    gsap.ticker.lagSmoothing(0);
  }

  gsap.utils.toArray(".reveal").forEach((el) => {
    gsap.to(el, {
      opacity: 1,
      y: 0,
      duration: 1,
      ease: "power3.out",
      scrollTrigger: {
        trigger: el,
        start: "top 84%"
      }
    });
  });

  gsap.fromTo(".hero-title", {
    y: 50,
    opacity: 0
  }, {
    y: 0,
    opacity: 1,
    duration: 1.25,
    ease: "power4.out",
    delay: 0.12
  });

  gsap.utils.toArray(".parallax-img").forEach((el) => {
    const speed = parseFloat(el.dataset.speed || "0.12");
    const yValue = speed * 420;

    gsap.to(el, {
      y: yValue,
      ease: "none",
      scrollTrigger: {
        trigger: el,
        start: "top bottom",
        end: "bottom top",
        scrub: true
      }
    });
  });

  gsap.utils.toArray(".feature-card").forEach((card) => {
    gsap.from(card, {
      scale: 0.96,
      opacity: 0.72,
      duration: 1,
      ease: "power3.out",
      scrollTrigger: {
        trigger: card,
        start: "top 85%"
      }
    });
  });

  gsap.utils.toArray(".showcase-item").forEach((item, index) => {
    gsap.from(item, {
      y: 70,
      opacity: 0,
      rotate: index % 2 === 0 ? -2 : 2,
      duration: 1,
      ease: "power3.out",
      scrollTrigger: {
        trigger: item,
        start: "top 88%"
      }
    });
  });

  const navLinks = gsap.utils.toArray(".edition-nav a");
  const navTargets = navLinks
    .map((link) => document.querySelector(link.getAttribute("href")))
    .filter(Boolean);

  navTargets.forEach((section) => {
    ScrollTrigger.create({
      trigger: section,
      start: "top 50%",
      end: "bottom 50%",
      onEnter: () => setActiveNav(section.id),
      onEnterBack: () => setActiveNav(section.id)
    });
  });

  function setActiveNav(id) {
    navLinks.forEach((link) => {
      link.classList.toggle("is-active", link.getAttribute("href") === `#${id}`);
    });
  }
}

/* Canvas 2D: partículas e conexões */
const canvas2d = document.getElementById("canvas2d");
const ctx = canvas2d?.getContext("2d", { alpha: true });

let cw = 0;
let ch = 0;
let dpr = Math.min(window.devicePixelRatio || 1, 2);
let particles = [];
let mouse = { x: 0, y: 0, active: false };

function resize2D() {
  if (!canvas2d || !ctx) return;

  dpr = Math.min(window.devicePixelRatio || 1, 2);
  cw = window.innerWidth;
  ch = window.innerHeight;

  canvas2d.width = Math.floor(cw * dpr);
  canvas2d.height = Math.floor(ch * dpr);
  canvas2d.style.width = `${cw}px`;
  canvas2d.style.height = `${ch}px`;
  ctx.setTransform(dpr, 0, 0, dpr, 0, 0);

  createParticles();
}

function createParticles() {
  const count = Math.max(45, Math.min(Math.floor((cw * ch) / 16500), 130));

  particles = Array.from({ length: count }, (_, index) => ({
    x: Math.random() * cw,
    y: Math.random() * ch,
    vx: (Math.random() - 0.5) * 0.36,
    vy: (Math.random() - 0.5) * 0.36,
    r: Math.random() * 1.8 + 0.8,
    depth: Math.random() * 0.8 + 0.25,
    hue: index % 3 === 0 ? 88 : index % 3 === 1 ? 182 : 266
  }));
}

function draw2D(time = 0) {
  if (!ctx) return;

  ctx.clearRect(0, 0, cw, ch);

  const scroll = window.scrollY || 0;
  const maxScroll = document.documentElement.scrollHeight - window.innerHeight;
  const ratio = maxScroll > 0 ? scroll / maxScroll : 0;

  const gradient = ctx.createRadialGradient(
    cw * (0.2 + ratio * 0.3),
    ch * 0.15,
    0,
    cw * 0.5,
    ch * 0.35,
    Math.max(cw, ch) * 0.82
  );

  gradient.addColorStop(0, "rgba(183,255,92,0.14)");
  gradient.addColorStop(0.35, "rgba(90,248,255,0.08)");
  gradient.addColorStop(0.75, "rgba(185,146,255,0.045)");
  gradient.addColorStop(1, "rgba(4,5,9,0)");

  ctx.fillStyle = gradient;
  ctx.fillRect(0, 0, cw, ch);

  drawGrid(time, ratio);
  moveParticles(ratio);
  connectParticles(ratio);

  requestAnimationFrame(draw2D);
}

function drawGrid(time, ratio) {
  const size = 58;
  const offset = (time * 0.008 + ratio * 260) % size;

  ctx.save();
  ctx.globalAlpha = 0.12;
  ctx.strokeStyle = "rgba(255,255,255,0.14)";
  ctx.lineWidth = 1;

  for (let x = -size + offset; x < cw + size; x += size) {
    ctx.beginPath();
    ctx.moveTo(x, 0);
    ctx.lineTo(x + ratio * 70, ch);
    ctx.stroke();
  }

  for (let y = -size + offset; y < ch + size; y += size) {
    ctx.beginPath();
    ctx.moveTo(0, y);
    ctx.lineTo(cw, y + ratio * 45);
    ctx.stroke();
  }

  ctx.restore();
}

function moveParticles(ratio) {
  for (const p of particles) {
    p.x += p.vx * p.depth;
    p.y += p.vy * p.depth;

    if (p.x < -20) p.x = cw + 20;
    if (p.x > cw + 20) p.x = -20;
    if (p.y < -20) p.y = ch + 20;
    if (p.y > ch + 20) p.y = -20;

    if (mouse.active) {
      const dx = mouse.x - p.x;
      const dy = mouse.y - p.y;
      const dist = Math.sqrt(dx * dx + dy * dy);
      const force = Math.max(0, 1 - dist / 170);

      p.x -= dx * force * 0.012;
      p.y -= dy * force * 0.012;
    }

    const drawY = p.y - ratio * 150 * p.depth;

    ctx.beginPath();
    ctx.fillStyle = `hsla(${p.hue}, 100%, 72%, ${0.26 + p.depth * 0.55})`;
    ctx.arc(p.x, drawY, p.r * p.depth, 0, Math.PI * 2);
    ctx.fill();
  }
}

function connectParticles(ratio) {
  const maxDistance = Math.min(148, Math.max(96, cw * 0.11));

  for (let i = 0; i < particles.length; i++) {
    for (let j = i + 1; j < particles.length; j++) {
      const a = particles[i];
      const b = particles[j];

      const ay = a.y - ratio * 150 * a.depth;
      const by = b.y - ratio * 150 * b.depth;
      const dx = a.x - b.x;
      const dy = ay - by;
      const distance = Math.sqrt(dx * dx + dy * dy);

      if (distance < maxDistance) {
        const alpha = (1 - distance / maxDistance) * 0.17;
        ctx.strokeStyle = `rgba(183,255,92,${alpha})`;
        ctx.lineWidth = 1;
        ctx.beginPath();
        ctx.moveTo(a.x, ay);
        ctx.lineTo(b.x, by);
        ctx.stroke();
      }
    }
  }
}

window.addEventListener("pointermove", (event) => {
  mouse.x = event.clientX;
  mouse.y = event.clientY;
  mouse.active = true;
});

window.addEventListener("pointerleave", () => {
  mouse.active = false;
});

window.addEventListener("resize", resize2D);

resize2D();

if (!prefersReducedMotion) {
  requestAnimationFrame(draw2D);
}

/* Three.js 3D */
const threeCanvas = document.getElementById("threeScene");

let scene;
let camera;
let renderer;
let group;
let shapes = [];

function initThree() {
  if (!threeCanvas || !window.THREE || prefersReducedMotion) return;

  scene = new THREE.Scene();

  camera = new THREE.PerspectiveCamera(45, window.innerWidth / window.innerHeight, 0.1, 100);
  camera.position.z = 9;

  renderer = new THREE.WebGLRenderer({
    canvas: threeCanvas,
    alpha: true,
    antialias: true
  });

  renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 2));
  renderer.setSize(window.innerWidth, window.innerHeight);

  group = new THREE.Group();
  scene.add(group);

  const materialA = new THREE.MeshBasicMaterial({
    color: 0xb7ff5c,
    wireframe: true,
    transparent: true,
    opacity: 0.23
  });

  const materialB = new THREE.MeshBasicMaterial({
    color: 0x5af8ff,
    wireframe: true,
    transparent: true,
    opacity: 0.2
  });

  const materialC = new THREE.MeshBasicMaterial({
    color: 0xb992ff,
    wireframe: true,
    transparent: true,
    opacity: 0.17
  });

  const geometryA = new THREE.IcosahedronGeometry(1.25, 1);
  const geometryB = new THREE.TorusKnotGeometry(0.75, 0.22, 120, 12);
  const geometryC = new THREE.OctahedronGeometry(1, 1);

  const shapeData = [
    { geometry: geometryA, material: materialA, x: -4.6, y: 1.8, z: -1.5, s: 1.35 },
    { geometry: geometryB, material: materialB, x: 4.1, y: 1.1, z: -1.2, s: 1.2 },
    { geometry: geometryC, material: materialC, x: 3.5, y: -2.4, z: -1.0, s: 1.05 },
    { geometry: geometryA, material: materialB, x: -3.2, y: -2.1, z: -1.8, s: 0.9 }
  ];

  shapes = shapeData.map((item) => {
    const mesh = new THREE.Mesh(item.geometry, item.material);
    mesh.position.set(item.x, item.y, item.z);
    mesh.scale.setScalar(item.s);
    group.add(mesh);
    return mesh;
  });

  const starGeometry = new THREE.BufferGeometry();
  const starCount = 250;
  const positions = new Float32Array(starCount * 3);

  for (let i = 0; i < starCount; i++) {
    positions[i * 3] = (Math.random() - 0.5) * 14;
    positions[i * 3 + 1] = (Math.random() - 0.5) * 8;
    positions[i * 3 + 2] = (Math.random() - 0.5) * 8;
  }

  starGeometry.setAttribute("position", new THREE.BufferAttribute(positions, 3));

  const starMaterial = new THREE.PointsMaterial({
    color: 0xffffff,
    size: 0.012,
    transparent: true,
    opacity: 0.45
  });

  const stars = new THREE.Points(starGeometry, starMaterial);
  group.add(stars);

  animateThree();
}

function animateThree() {
  if (!renderer || !scene || !camera) return;

  const scroll = window.scrollY || 0;
  const maxScroll = document.documentElement.scrollHeight - window.innerHeight;
  const ratio = maxScroll > 0 ? scroll / maxScroll : 0;

  group.rotation.y += 0.0015;
  group.rotation.x = ratio * 0.9;

  shapes.forEach((shape, index) => {
    shape.rotation.x += 0.004 + index * 0.0008;
    shape.rotation.y += 0.006 + index * 0.0007;
    shape.position.y += Math.sin(Date.now() * 0.001 + index) * 0.0009;
  });

  camera.position.y = ratio * -1.2;
  renderer.render(scene, camera);

  requestAnimationFrame(animateThree);
}

function resizeThree() {
  if (!renderer || !camera) return;

  camera.aspect = window.innerWidth / window.innerHeight;
  camera.updateProjectionMatrix();
  renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 2));
  renderer.setSize(window.innerWidth, window.innerHeight);
}

window.addEventListener("resize", resizeThree);

initThree();
