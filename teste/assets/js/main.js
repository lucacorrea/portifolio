/*
  L&J Ultra Editions Experience
  Static version: HTML + CSS + JavaScript
  CDN libs: GSAP, ScrollTrigger, Lenis, Three.js
*/

const $ = (selector, root = document) => root.querySelector(selector);
const $$ = (selector, root = document) => Array.from(root.querySelectorAll(selector));

const preloader = $("#preloader");
const preloaderLine = $("#preloaderLine");
const preloaderCount = $("#preloaderCount");
const header = $("#siteHeader");
const menuToggle = $("#menuToggle");
const mainNav = $("#mainNav");
const year = $("#year");
const scrollProgress = $("#scrollProgress");
const cursor = $("#cursor");

const prefersReducedMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
const isTouch = window.matchMedia("(pointer: coarse)").matches;

if (year) {
  year.textContent = new Date().getFullYear();
}

/* Preloader */
document.body.classList.add("is-loading");

let loadValue = 0;
const loadTimer = setInterval(() => {
  loadValue += Math.floor(Math.random() * 12) + 4;
  loadValue = Math.min(loadValue, 96);

  if (preloaderLine) preloaderLine.style.width = `${loadValue}%`;
  if (preloaderCount) preloaderCount.textContent = loadValue;
}, 70);

window.addEventListener("load", () => {
  clearInterval(loadTimer);

  if (preloaderLine) preloaderLine.style.width = "100%";
  if (preloaderCount) preloaderCount.textContent = "100";

  setTimeout(() => {
    preloader?.classList.add("is-hidden");
    document.body.classList.remove("is-loading");
  }, 420);
});

/* Menu */
if (menuToggle && mainNav) {
  menuToggle.addEventListener("click", () => {
    const isOpen = mainNav.classList.toggle("is-open");
    menuToggle.classList.toggle("is-open", isOpen);
    menuToggle.setAttribute("aria-expanded", String(isOpen));
    document.body.classList.toggle("menu-open", isOpen);
  });

  $$("a", mainNav).forEach((link) => {
    link.addEventListener("click", () => {
      mainNav.classList.remove("is-open");
      menuToggle.classList.remove("is-open");
      menuToggle.setAttribute("aria-expanded", "false");
      document.body.classList.remove("menu-open");
    });
  });
}

/* Scroll progress/header */
function updateScrollProgress() {
  const scrollTop = window.scrollY || document.documentElement.scrollTop;
  const max = document.documentElement.scrollHeight - window.innerHeight;
  const progress = max > 0 ? (scrollTop / max) * 100 : 0;

  if (scrollProgress) scrollProgress.style.width = `${progress}%`;
  header?.classList.toggle("is-scrolled", scrollTop > 20);
}

window.addEventListener("scroll", updateScrollProgress, { passive: true });
updateScrollProgress();

/* Custom cursor */
if (cursor && !isTouch && !prefersReducedMotion) {
  window.addEventListener("pointermove", (event) => {
    cursor.style.left = `${event.clientX}px`;
    cursor.style.top = `${event.clientY}px`;
    cursor.classList.add("is-active");
  });

  $$("a, button, .tilt-card, .magnetic").forEach((el) => {
    el.addEventListener("mouseenter", () => cursor.classList.add("is-hover"));
    el.addEventListener("mouseleave", () => cursor.classList.remove("is-hover"));
  });
}

/* Smooth scroll */
let lenis = null;

if (!prefersReducedMotion && window.Lenis) {
  lenis = new Lenis({
    duration: 1.18,
    smoothWheel: true,
    wheelMultiplier: 0.92,
    touchMultiplier: 1.25
  });

  function raf(time) {
    lenis.raf(time);
    requestAnimationFrame(raf);
  }

  requestAnimationFrame(raf);
}

/* GSAP Animations */
if (window.gsap && window.ScrollTrigger) {
  gsap.registerPlugin(ScrollTrigger);

  if (lenis) {
    lenis.on("scroll", ScrollTrigger.update);

    gsap.ticker.add((time) => {
      lenis.raf(time * 1000);
    });

    gsap.ticker.lagSmoothing(0);
  }

  gsap.to(".split-reveal", {
    opacity: 1,
    y: 0,
    duration: 0.9,
    delay: 0.55,
    ease: "power3.out"
  });

  gsap.to(".split-title", {
    opacity: 1,
    y: 0,
    duration: 1.2,
    delay: 0.72,
    ease: "power4.out"
  });

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

  gsap.utils.toArray(".float-layer").forEach((el) => {
    const speed = parseFloat(el.dataset.speed || "0.12");

    gsap.to(el, {
      y: speed * 520,
      rotate: `+=${speed * 24}`,
      ease: "none",
      scrollTrigger: {
        trigger: ".hero",
        start: "top top",
        end: "bottom top",
        scrub: true
      }
    });
  });

  gsap.utils.toArray(".img-parallax").forEach((el) => {
    const speed = parseFloat(el.dataset.speed || "0.12");

    gsap.to(el, {
      y: speed * 380,
      ease: "none",
      scrollTrigger: {
        trigger: el,
        start: "top bottom",
        end: "bottom top",
        scrub: true
      }
    });
  });

  gsap.utils.toArray(".chapter-card").forEach((card, index) => {
    gsap.from(card, {
      y: 80,
      opacity: 0,
      rotate: index % 2 === 0 ? -2.5 : 2.5,
      duration: 1,
      ease: "power3.out",
      scrollTrigger: {
        trigger: card,
        start: "top 88%"
      }
    });
  });

  gsap.utils.toArray(".story-panel").forEach((panel) => {
    gsap.from(panel, {
      scale: 0.96,
      opacity: 0.58,
      y: 60,
      duration: 0.9,
      ease: "power3.out",
      scrollTrigger: {
        trigger: panel,
        start: "top 82%"
      }
    });
  });

  gsap.utils.toArray(".feature-card").forEach((card) => {
    gsap.from(card, {
      scale: 0.965,
      opacity: 0.72,
      duration: 1,
      ease: "power3.out",
      scrollTrigger: {
        trigger: card,
        start: "top 85%"
      }
    });
  });

  const galleryTrack = $("#galleryTrack");
  const gallerySection = $(".horizontal-gallery");

  if (galleryTrack && gallerySection && window.innerWidth > 860) {
    const getScrollAmount = () => {
      return -(galleryTrack.scrollWidth - window.innerWidth + window.innerWidth * 0.08);
    };

    gsap.to(galleryTrack, {
      x: getScrollAmount,
      ease: "none",
      scrollTrigger: {
        trigger: gallerySection,
        start: "top top",
        end: () => `+=${galleryTrack.scrollWidth}`,
        scrub: 1,
        pin: true,
        invalidateOnRefresh: true
      }
    });
  }

  gsap.utils.toArray(".gallery-card").forEach((card, index) => {
    gsap.from(card, {
      y: 70,
      opacity: 0,
      rotate: index % 2 === 0 ? -2 : 2,
      duration: 1,
      ease: "power3.out",
      scrollTrigger: {
        trigger: card,
        start: "top 88%"
      }
    });
  });
} else {
  $$(".reveal, .split-reveal, .split-title").forEach((el) => {
    el.style.opacity = "1";
    el.style.transform = "none";
  });
}

/* Tilt Cards */
if (!isTouch && !prefersReducedMotion) {
  $$(".tilt-card").forEach((card) => {
    card.addEventListener("mousemove", (event) => {
      const rect = card.getBoundingClientRect();
      const x = event.clientX - rect.left;
      const y = event.clientY - rect.top;
      const rotateY = ((x / rect.width) - 0.5) * 10;
      const rotateX = ((y / rect.height) - 0.5) * -10;

      card.style.transform = `perspective(900px) rotateX(${rotateX}deg) rotateY(${rotateY}deg) translateY(-4px)`;
    });

    card.addEventListener("mouseleave", () => {
      card.style.transform = "";
    });
  });

  $$(".magnetic").forEach((el) => {
    el.addEventListener("mousemove", (event) => {
      const rect = el.getBoundingClientRect();
      const x = event.clientX - rect.left - rect.width / 2;
      const y = event.clientY - rect.top - rect.height / 2;

      el.style.transform = `translate(${x * 0.08}px, ${y * 0.12}px)`;
    });

    el.addEventListener("mouseleave", () => {
      el.style.transform = "";
    });
  });
}

/* Canvas 2D field */
const canvas = $("#fieldCanvas");
const ctx = canvas?.getContext("2d", { alpha: true });

let cw = 0;
let ch = 0;
let dpr = Math.min(window.devicePixelRatio || 1, 2);
let particles = [];
let mouse = { x: 0, y: 0, active: false };

function resizeCanvas() {
  if (!canvas || !ctx) return;

  dpr = Math.min(window.devicePixelRatio || 1, 2);
  cw = window.innerWidth;
  ch = window.innerHeight;

  canvas.width = Math.floor(cw * dpr);
  canvas.height = Math.floor(ch * dpr);
  canvas.style.width = `${cw}px`;
  canvas.style.height = `${ch}px`;
  ctx.setTransform(dpr, 0, 0, dpr, 0, 0);

  createParticles();
}

function createParticles() {
  const count = Math.max(48, Math.min(Math.floor((cw * ch) / 14500), 150));

  particles = Array.from({ length: count }, (_, index) => ({
    x: Math.random() * cw,
    y: Math.random() * ch,
    vx: (Math.random() - 0.5) * 0.38,
    vy: (Math.random() - 0.5) * 0.38,
    radius: Math.random() * 1.9 + 0.7,
    depth: Math.random() * 0.85 + 0.2,
    hue: index % 4 === 0 ? 88 : index % 4 === 1 ? 182 : index % 4 === 2 ? 266 : 34
  }));
}

function drawCanvas(time = 0) {
  if (!ctx) return;

  ctx.clearRect(0, 0, cw, ch);

  const maxScroll = document.documentElement.scrollHeight - window.innerHeight;
  const ratio = maxScroll > 0 ? window.scrollY / maxScroll : 0;

  drawGlow(ratio);
  drawGrid(time, ratio);
  drawWaves(time, ratio);
  drawParticles(ratio);
  drawConnections(ratio);

  requestAnimationFrame(drawCanvas);
}

function drawGlow(ratio) {
  const gradient = ctx.createRadialGradient(
    cw * (0.18 + ratio * 0.35),
    ch * 0.12,
    0,
    cw * 0.48,
    ch * 0.28,
    Math.max(cw, ch) * 0.85
  );

  gradient.addColorStop(0, "rgba(183,255,92,0.15)");
  gradient.addColorStop(0.32, "rgba(90,248,255,0.08)");
  gradient.addColorStop(0.7, "rgba(185,146,255,0.045)");
  gradient.addColorStop(1, "rgba(5,6,10,0)");

  ctx.fillStyle = gradient;
  ctx.fillRect(0, 0, cw, ch);
}

function drawGrid(time, ratio) {
  const size = 64;
  const offset = (time * 0.008 + ratio * 260) % size;

  ctx.save();
  ctx.globalAlpha = 0.11;
  ctx.strokeStyle = "rgba(255,255,255,0.14)";
  ctx.lineWidth = 1;

  for (let x = -size + offset; x < cw + size; x += size) {
    ctx.beginPath();
    ctx.moveTo(x, 0);
    ctx.lineTo(x + ratio * 90, ch);
    ctx.stroke();
  }

  for (let y = -size + offset; y < ch + size; y += size) {
    ctx.beginPath();
    ctx.moveTo(0, y);
    ctx.lineTo(cw, y + ratio * 50);
    ctx.stroke();
  }

  ctx.restore();
}

function drawWaves(time, ratio) {
  const lines = 4;

  for (let i = 0; i < lines; i++) {
    ctx.save();
    ctx.globalAlpha = 0.12;
    const hue = i % 2 === 0 ? 88 : 182;
    const gradient = ctx.createLinearGradient(0, 0, cw, 0);
    gradient.addColorStop(0, `hsla(${hue}, 100%, 70%, 0)`);
    gradient.addColorStop(0.5, `hsla(${hue}, 100%, 70%, 0.8)`);
    gradient.addColorStop(1, `hsla(${hue}, 100%, 70%, 0)`);

    ctx.strokeStyle = gradient;
    ctx.lineWidth = 1.2 + i * 0.45;
    ctx.beginPath();

    for (let x = 0; x <= cw; x += 18) {
      const baseY = ch * (0.18 + i * 0.2);
      const wave =
        Math.sin(x * 0.008 + time * (0.003 + i * 0.0008)) * (35 + i * 18) +
        Math.cos(x * 0.004 + time * 0.002) * 22;

      const y = baseY + wave + ratio * (i % 2 === 0 ? -120 : 120);

      if (x === 0) ctx.moveTo(x, y);
      else ctx.lineTo(x, y);
    }

    ctx.stroke();
    ctx.restore();
  }
}

function drawParticles(ratio) {
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
      const force = Math.max(0, 1 - dist / 180);

      p.x -= dx * force * 0.012;
      p.y -= dy * force * 0.012;
    }

    const drawY = p.y - ratio * 175 * p.depth;

    ctx.beginPath();
    ctx.fillStyle = `hsla(${p.hue}, 100%, 72%, ${0.25 + p.depth * 0.55})`;
    ctx.arc(p.x, drawY, p.radius * p.depth, 0, Math.PI * 2);
    ctx.fill();
  }
}

function drawConnections(ratio) {
  const maxDistance = Math.min(152, Math.max(95, cw * 0.11));

  for (let i = 0; i < particles.length; i++) {
    for (let j = i + 1; j < particles.length; j++) {
      const a = particles[i];
      const b = particles[j];

      const ay = a.y - ratio * 175 * a.depth;
      const by = b.y - ratio * 175 * b.depth;
      const dx = a.x - b.x;
      const dy = ay - by;
      const distance = Math.sqrt(dx * dx + dy * dy);

      if (distance < maxDistance) {
        const alpha = (1 - distance / maxDistance) * 0.16;
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

window.addEventListener("resize", resizeCanvas);
resizeCanvas();

if (!prefersReducedMotion) {
  requestAnimationFrame(drawCanvas);
}

/* Three.js Scene */
const threeCanvas = $("#threeCanvas");

let scene;
let camera;
let renderer;
let group;
let shapes = [];

function initThree() {
  if (!threeCanvas || !window.THREE || prefersReducedMotion) return;

  scene = new THREE.Scene();

  camera = new THREE.PerspectiveCamera(46, window.innerWidth / window.innerHeight, 0.1, 100);
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

  const materials = [
    new THREE.MeshBasicMaterial({ color: 0xb7ff5c, wireframe: true, transparent: true, opacity: 0.24 }),
    new THREE.MeshBasicMaterial({ color: 0x5af8ff, wireframe: true, transparent: true, opacity: 0.2 }),
    new THREE.MeshBasicMaterial({ color: 0xb992ff, wireframe: true, transparent: true, opacity: 0.18 }),
    new THREE.MeshBasicMaterial({ color: 0xffb45e, wireframe: true, transparent: true, opacity: 0.16 })
  ];

  const geometries = [
    new THREE.IcosahedronGeometry(1.24, 1),
    new THREE.TorusKnotGeometry(0.72, 0.2, 128, 12),
    new THREE.OctahedronGeometry(1.05, 1),
    new THREE.DodecahedronGeometry(0.92, 1)
  ];

  const data = [
    { x: -4.7, y: 1.8, z: -1.7, s: 1.28, g: 0, m: 0 },
    { x: 4.25, y: 1.18, z: -1.2, s: 1.18, g: 1, m: 1 },
    { x: 3.55, y: -2.36, z: -1.1, s: 1.05, g: 2, m: 2 },
    { x: -3.25, y: -2.05, z: -1.8, s: 0.92, g: 3, m: 1 },
    { x: 0.65, y: 2.7, z: -2.6, s: 0.72, g: 1, m: 3 }
  ];

  shapes = data.map((item) => {
    const mesh = new THREE.Mesh(geometries[item.g], materials[item.m]);
    mesh.position.set(item.x, item.y, item.z);
    mesh.scale.setScalar(item.s);
    group.add(mesh);
    return mesh;
  });

  const starGeometry = new THREE.BufferGeometry();
  const starCount = 300;
  const positions = new Float32Array(starCount * 3);

  for (let i = 0; i < starCount; i++) {
    positions[i * 3] = (Math.random() - 0.5) * 15;
    positions[i * 3 + 1] = (Math.random() - 0.5) * 9;
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
  if (!renderer || !scene || !camera || !group) return;

  const maxScroll = document.documentElement.scrollHeight - window.innerHeight;
  const ratio = maxScroll > 0 ? window.scrollY / maxScroll : 0;

  group.rotation.y += 0.0016;
  group.rotation.x = ratio * 0.95;

  shapes.forEach((shape, index) => {
    shape.rotation.x += 0.004 + index * 0.0009;
    shape.rotation.y += 0.006 + index * 0.0008;
    shape.position.y += Math.sin(Date.now() * 0.001 + index) * 0.0009;
  });

  camera.position.y = ratio * -1.35;
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
