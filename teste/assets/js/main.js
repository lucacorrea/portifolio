const $ = (selector, root = document) => root.querySelector(selector);
const $$ = (selector, root = document) => Array.from(root.querySelectorAll(selector));

const preloader = $("#preloader");
const preloaderBar = $("#preloaderBar");
const preloaderCount = $("#preloaderCount");
const topbar = $("#topbar");
const scrollbar = $("#scrollbar");
const mobileButton = $("#mobileButton");
const mainMenu = $("#mainMenu");
const cursor = $("#cursor");
const year = $("#year");

const searchOverlay = $("#searchOverlay");
const openSearch = $("#openSearch");
const closeSearch = $("#closeSearch");
const searchInput = $("#searchInput");
const searchResults = $("#searchResults");

const modal = $("#updateModal");
const modalTitle = $("#modalTitle");
const modalText = $("#modalText");
const modalClose = $("#modalClose");
const modalBackdrop = $("#modalBackdrop");
const modalCta = $("#modalCta");

const isTouch = window.matchMedia("(pointer: coarse)").matches;
const prefersReducedMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;

if (year) year.textContent = new Date().getFullYear();

/* Preloader */
let load = 0;
const loadTimer = setInterval(() => {
  load = Math.min(96, load + Math.floor(Math.random() * 12) + 4);
  if (preloaderBar) preloaderBar.style.width = `${load}%`;
  if (preloaderCount) preloaderCount.textContent = load;
}, 70);

window.addEventListener("load", () => {
  clearInterval(loadTimer);
  if (preloaderBar) preloaderBar.style.width = "100%";
  if (preloaderCount) preloaderCount.textContent = "100";

  setTimeout(() => {
    preloader?.classList.add("is-hidden");
    document.body.classList.remove("is-loading");
  }, 420);
});

/* Header, progress */
function updateProgress() {
  const scrollTop = window.scrollY || document.documentElement.scrollTop;
  const max = document.documentElement.scrollHeight - window.innerHeight;
  const value = max > 0 ? (scrollTop / max) * 100 : 0;

  if (scrollbar) scrollbar.style.width = `${value}%`;
  topbar?.classList.toggle("is-scrolled", scrollTop > 20);
}

window.addEventListener("scroll", updateProgress, { passive: true });
updateProgress();

/* Mobile menu */
if (mobileButton && mainMenu) {
  mobileButton.addEventListener("click", () => {
    const isOpen = mainMenu.classList.toggle("is-open");
    mobileButton.classList.toggle("is-open", isOpen);
    mobileButton.setAttribute("aria-expanded", String(isOpen));
    document.body.classList.toggle("menu-open", isOpen);
  });

  $$("a", mainMenu).forEach((link) => {
    link.addEventListener("click", () => {
      mainMenu.classList.remove("is-open");
      mobileButton.classList.remove("is-open");
      mobileButton.setAttribute("aria-expanded", "false");
      document.body.classList.remove("menu-open");
    });
  });
}

/* Cursor */
if (cursor && !isTouch && !prefersReducedMotion) {
  window.addEventListener("pointermove", (event) => {
    cursor.style.left = `${event.clientX}px`;
    cursor.style.top = `${event.clientY}px`;
    cursor.classList.add("is-visible");
  });

  $$("a, button, .tilt-card, .magnetic, input").forEach((el) => {
    el.addEventListener("mouseenter", () => cursor.classList.add("is-hover"));
    el.addEventListener("mouseleave", () => cursor.classList.remove("is-hover"));
  });
}

/* Search */
const searchableItems = [
  ...$$(".update-card"),
  ...$$(".mini-update"),
  ...$$(".index-card")
].map((el) => ({
  title: el.querySelector("h3,h4")?.textContent?.trim() || "Update",
  text: el.querySelector("p")?.textContent?.trim() || "",
  keywords: el.dataset.search || el.textContent,
  href: el.closest("section") ? `#${el.closest("section").id}` : "#editionNav"
}));

function openSearchOverlay() {
  searchOverlay?.classList.add("is-open");
  searchOverlay?.setAttribute("aria-hidden", "false");
  document.body.classList.add("search-open");
  setTimeout(() => searchInput?.focus(), 80);
  renderSearch("");
}

function closeSearchOverlay() {
  searchOverlay?.classList.remove("is-open");
  searchOverlay?.setAttribute("aria-hidden", "true");
  document.body.classList.remove("search-open");
}

function renderSearch(query) {
  if (!searchResults) return;

  const q = query.toLowerCase().trim();
  const matches = searchableItems
    .filter((item) => !q || `${item.title} ${item.text} ${item.keywords}`.toLowerCase().includes(q))
    .slice(0, 8);

  searchResults.innerHTML = matches.length
    ? matches.map((item) => `
      <a class="search-result" href="${item.href}">
        <strong>${item.title}</strong>
        <span>${item.text}</span>
      </a>
    `).join("")
    : `<div class="search-result"><strong>Nenhum resultado</strong><span>Tente buscar por site, dashboard, automação ou WhatsApp.</span></div>`;

  $$(".search-result", searchResults).forEach((link) => {
    link.addEventListener("click", closeSearchOverlay);
  });
}

openSearch?.addEventListener("click", openSearchOverlay);
closeSearch?.addEventListener("click", closeSearchOverlay);
searchInput?.addEventListener("input", (event) => renderSearch(event.target.value));

window.addEventListener("keydown", (event) => {
  if (event.key === "Escape") {
    closeSearchOverlay();
    closeModal();
  }

  if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === "k") {
    event.preventDefault();
    openSearchOverlay();
  }
});

/* Modal */
$$(".read-more").forEach((button) => {
  button.addEventListener("click", () => {
    if (modalTitle) modalTitle.textContent = button.dataset.modalTitle || "Update";
    if (modalText) modalText.textContent = button.dataset.modalText || "Detalhes do update.";
    modal?.classList.add("is-open");
    modal?.setAttribute("aria-hidden", "false");
    document.body.classList.add("modal-open");
  });
});

function closeModal() {
  modal?.classList.remove("is-open");
  modal?.setAttribute("aria-hidden", "true");
  document.body.classList.remove("modal-open");
}

modalClose?.addEventListener("click", closeModal);
modalBackdrop?.addEventListener("click", closeModal);
modalCta?.addEventListener("click", closeModal);

/* Lenis */
let lenis = null;

if (!prefersReducedMotion && window.Lenis) {
  lenis = new Lenis({
    duration: 1.16,
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

/* GSAP */
if (window.gsap && window.ScrollTrigger) {
  gsap.registerPlugin(ScrollTrigger);

  if (lenis) {
    lenis.on("scroll", ScrollTrigger.update);
    gsap.ticker.add((time) => lenis.raf(time * 1000));
    gsap.ticker.lagSmoothing(0);
  }

  gsap.to(".reveal", {
    opacity: 1,
    y: 0,
    duration: 1,
    ease: "power3.out",
    stagger: 0.06,
    scrollTrigger: {
      trigger: ".hero-edition",
      start: "top 80%"
    }
  });

  gsap.to(".reveal-title", {
    opacity: 1,
    y: 0,
    duration: 1.2,
    ease: "power4.out",
    delay: 0.32
  });

  gsap.utils.toArray(".reveal").forEach((el) => {
    gsap.to(el, {
      opacity: 1,
      y: 0,
      duration: 0.95,
      ease: "power3.out",
      scrollTrigger: {
        trigger: el,
        start: "top 85%"
      }
    });
  });

  gsap.utils.toArray(".parallax-float").forEach((el) => {
    const speed = parseFloat(el.dataset.speed || "0.12");
    gsap.to(el, {
      y: speed * 560,
      rotate: `+=${speed * 28}`,
      ease: "none",
      scrollTrigger: {
        trigger: ".hero-edition",
        start: "top top",
        end: "bottom top",
        scrub: true
      }
    });
  });

  gsap.utils.toArray(".img-depth").forEach((el) => {
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

  gsap.utils.toArray(".edition-section").forEach((section) => {
    ScrollTrigger.create({
      trigger: section,
      start: "top 45%",
      end: "bottom 45%",
      onEnter: () => setActiveCategory(section.id),
      onEnterBack: () => setActiveCategory(section.id)
    });
  });

  function setActiveCategory(id) {
    $$(".category-track a").forEach((link) => {
      link.classList.toggle("is-active", link.getAttribute("href") === `#${id}`);
    });
  }

  gsap.utils.toArray(".index-card, .update-card, .mini-update, .product-card").forEach((card, index) => {
    gsap.from(card, {
      y: 60,
      opacity: 0,
      rotate: index % 2 === 0 ? -1.4 : 1.4,
      duration: 0.9,
      ease: "power3.out",
      scrollTrigger: {
        trigger: card,
        start: "top 88%"
      }
    });
  });

  const horizontalTrack = $("#horizontalTrack");
  const horizontalSection = $(".horizontal-edition");

  if (horizontalTrack && horizontalSection && window.innerWidth > 860) {
    gsap.to(horizontalTrack, {
      x: () => -(horizontalTrack.scrollWidth - window.innerWidth + window.innerWidth * 0.1),
      ease: "none",
      scrollTrigger: {
        trigger: horizontalSection,
        start: "top top",
        end: () => `+=${horizontalTrack.scrollWidth}`,
        scrub: 1,
        pin: true,
        invalidateOnRefresh: true
      }
    });
  }
} else {
  $$(".reveal, .reveal-title").forEach((el) => {
    el.style.opacity = "1";
    el.style.transform = "none";
  });
}

/* Tilt and magnetic */
if (!isTouch && !prefersReducedMotion) {
  $$(".tilt-card").forEach((card) => {
    card.addEventListener("mousemove", (event) => {
      const rect = card.getBoundingClientRect();
      const x = event.clientX - rect.left;
      const y = event.clientY - rect.top;
      const rotateY = ((x / rect.width) - 0.5) * 9;
      const rotateX = ((y / rect.height) - 0.5) * -9;

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

/* Canvas 2D */
const canvas = $("#canvasField");
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
  const count = Math.max(52, Math.min(Math.floor((cw * ch) / 14500), 150));
  particles = Array.from({ length: count }, (_, index) => ({
    x: Math.random() * cw,
    y: Math.random() * ch,
    vx: (Math.random() - 0.5) * 0.36,
    vy: (Math.random() - 0.5) * 0.36,
    r: Math.random() * 1.9 + 0.7,
    depth: Math.random() * 0.85 + 0.2,
    hue: index % 4 === 0 ? 86 : index % 4 === 1 ? 184 : index % 4 === 2 ? 264 : 34
  }));
}

function drawCanvas(time = 0) {
  if (!ctx) return;

  ctx.clearRect(0, 0, cw, ch);

  const maxScroll = document.documentElement.scrollHeight - window.innerHeight;
  const ratio = maxScroll > 0 ? window.scrollY / maxScroll : 0;

  const glow = ctx.createRadialGradient(cw * (0.18 + ratio * 0.33), ch * 0.12, 0, cw * 0.5, ch * 0.32, Math.max(cw, ch) * 0.82);
  glow.addColorStop(0, "rgba(185,255,102,0.15)");
  glow.addColorStop(0.32, "rgba(112,245,255,0.08)");
  glow.addColorStop(0.7, "rgba(196,165,255,0.045)");
  glow.addColorStop(1, "rgba(8,10,6,0)");
  ctx.fillStyle = glow;
  ctx.fillRect(0, 0, cw, ch);

  drawGrid(time, ratio);
  drawWaves(time, ratio);
  drawParticles(ratio);
  connectParticles(ratio);

  requestAnimationFrame(drawCanvas);
}

function drawGrid(time, ratio) {
  const size = 64;
  const offset = (time * 0.007 + ratio * 260) % size;

  ctx.save();
  ctx.globalAlpha = 0.1;
  ctx.strokeStyle = "rgba(243,242,233,0.14)";
  ctx.lineWidth = 1;

  for (let x = -size + offset; x < cw + size; x += size) {
    ctx.beginPath();
    ctx.moveTo(x, 0);
    ctx.lineTo(x + ratio * 85, ch);
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

function drawWaves(time, ratio) {
  for (let i = 0; i < 4; i++) {
    ctx.save();
    ctx.globalAlpha = 0.11;
    const hue = i % 2 === 0 ? 86 : 184;
    const gradient = ctx.createLinearGradient(0, 0, cw, 0);
    gradient.addColorStop(0, `hsla(${hue}, 100%, 70%, 0)`);
    gradient.addColorStop(0.5, `hsla(${hue}, 100%, 70%, 0.78)`);
    gradient.addColorStop(1, `hsla(${hue}, 100%, 70%, 0)`);
    ctx.strokeStyle = gradient;
    ctx.lineWidth = 1.1 + i * 0.42;
    ctx.beginPath();

    for (let x = 0; x <= cw; x += 18) {
      const baseY = ch * (0.18 + i * 0.2);
      const wave = Math.sin(x * 0.008 + time * (0.003 + i * 0.0008)) * (34 + i * 18) + Math.cos(x * 0.004 + time * 0.002) * 22;
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

    const y = p.y - ratio * 170 * p.depth;
    ctx.beginPath();
    ctx.fillStyle = `hsla(${p.hue}, 100%, 72%, ${0.25 + p.depth * 0.55})`;
    ctx.arc(p.x, y, p.r * p.depth, 0, Math.PI * 2);
    ctx.fill();
  }
}

function connectParticles(ratio) {
  const maxDistance = Math.min(152, Math.max(95, cw * 0.11));
  for (let i = 0; i < particles.length; i++) {
    for (let j = i + 1; j < particles.length; j++) {
      const a = particles[i];
      const b = particles[j];
      const ay = a.y - ratio * 170 * a.depth;
      const by = b.y - ratio * 170 * b.depth;
      const dx = a.x - b.x;
      const dy = ay - by;
      const dist = Math.sqrt(dx * dx + dy * dy);
      if (dist < maxDistance) {
        const alpha = (1 - dist / maxDistance) * 0.15;
        ctx.strokeStyle = `rgba(185,255,102,${alpha})`;
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

if (!prefersReducedMotion) requestAnimationFrame(drawCanvas);

/* Three.js */
const threeCanvas = $("#threeField");
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
    new THREE.MeshBasicMaterial({ color: 0xb9ff66, wireframe: true, transparent: true, opacity: 0.24 }),
    new THREE.MeshBasicMaterial({ color: 0x70f5ff, wireframe: true, transparent: true, opacity: 0.2 }),
    new THREE.MeshBasicMaterial({ color: 0xc4a5ff, wireframe: true, transparent: true, opacity: 0.18 }),
    new THREE.MeshBasicMaterial({ color: 0xffbd68, wireframe: true, transparent: true, opacity: 0.16 })
  ];

  const geometries = [
    new THREE.IcosahedronGeometry(1.22, 1),
    new THREE.TorusKnotGeometry(0.72, 0.2, 128, 12),
    new THREE.OctahedronGeometry(1.05, 1),
    new THREE.DodecahedronGeometry(0.92, 1)
  ];

  const data = [
    { x: -4.7, y: 1.85, z: -1.7, s: 1.28, g: 0, m: 0 },
    { x: 4.25, y: 1.15, z: -1.2, s: 1.18, g: 1, m: 1 },
    { x: 3.55, y: -2.35, z: -1.1, s: 1.05, g: 2, m: 2 },
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
  const count = 320;
  const positions = new Float32Array(count * 3);

  for (let i = 0; i < count; i++) {
    positions[i * 3] = (Math.random() - 0.5) * 15;
    positions[i * 3 + 1] = (Math.random() - 0.5) * 9;
    positions[i * 3 + 2] = (Math.random() - 0.5) * 8;
  }

  starGeometry.setAttribute("position", new THREE.BufferAttribute(positions, 3));

  const starMaterial = new THREE.PointsMaterial({
    color: 0xf3f2e9,
    size: 0.012,
    transparent: true,
    opacity: 0.42
  });

  const stars = new THREE.Points(starGeometry, starMaterial);
  group.add(stars);

  animateThree();
}

function animateThree() {
  if (!renderer || !scene || !camera || !group) return;

  const max = document.documentElement.scrollHeight - window.innerHeight;
  const ratio = max > 0 ? window.scrollY / max : 0;

  group.rotation.y += 0.0015;
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
