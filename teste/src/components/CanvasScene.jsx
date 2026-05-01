import { useEffect, useRef } from 'react';

export default function CanvasScene() {
  const canvasRef = useRef(null);

  useEffect(() => {
    const canvas = canvasRef.current;
    const context = canvas.getContext('2d', { alpha: true });

    let width = 0;
    let height = 0;
    let dpr = Math.min(window.devicePixelRatio || 1, 2);
    let particles = [];
    let ribbons = [];
    let raf = 0;
    let scrollRatio = 0;
    let pointer = { x: 0, y: 0, active: false };
    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    function resizeCanvas() {
      width = window.innerWidth;
      height = window.innerHeight;
      dpr = Math.min(window.devicePixelRatio || 1, 2);

      canvas.width = Math.floor(width * dpr);
      canvas.height = Math.floor(height * dpr);
      canvas.style.width = `${width}px`;
      canvas.style.height = `${height}px`;
      context.setTransform(dpr, 0, 0, dpr, 0, 0);

      const particleCount = Math.max(80, Math.min(165, Math.floor((width * height) / 13500)));
      particles = Array.from({ length: particleCount }, (_, index) => ({
        x: Math.random() * width,
        y: Math.random() * height,
        vx: (Math.random() - 0.5) * 0.44,
        vy: (Math.random() - 0.5) * 0.44,
        r: Math.random() * 2 + 0.7,
        depth: Math.random() * 0.9 + 0.16,
        hue: index % 4 === 0 ? 92 : index % 4 === 1 ? 178 : index % 4 === 2 ? 267 : 36
      }));

      ribbons = Array.from({ length: 5 }, (_, index) => ({
        y: height * (0.12 + index * 0.19),
        amp: 30 + index * 18,
        speed: 0.0024 + index * 0.0011,
        hue: index % 2 === 0 ? 92 : 178,
        offset: Math.random() * 1000
      }));
    }

    function updateScroll() {
      const max = document.documentElement.scrollHeight - window.innerHeight;
      scrollRatio = max > 0 ? window.scrollY / max : 0;
    }

    function drawGradient() {
      const gradient = context.createRadialGradient(
        width * (0.2 + scrollRatio * 0.34),
        height * 0.12,
        0,
        width * 0.5,
        height * 0.34,
        Math.max(width, height) * 0.86
      );
      gradient.addColorStop(0, 'rgba(196,255,92,.18)');
      gradient.addColorStop(0.26, 'rgba(90,248,255,.10)');
      gradient.addColorStop(0.58, 'rgba(185,146,255,.07)');
      gradient.addColorStop(1, 'rgba(5,6,8,0)');
      context.fillStyle = gradient;
      context.fillRect(0, 0, width, height);
    }

    function drawGrid(time) {
      const size = 62;
      const offset = (time * 0.006 + scrollRatio * 280) % size;
      context.save();
      context.globalAlpha = 0.12;
      context.strokeStyle = 'rgba(255,255,255,.16)';
      context.lineWidth = 1;

      for (let x = -size + offset; x < width + size; x += size) {
        context.beginPath();
        context.moveTo(x, 0);
        context.lineTo(x + scrollRatio * 90, height);
        context.stroke();
      }

      for (let y = -size + offset; y < height + size; y += size) {
        context.beginPath();
        context.moveTo(0, y);
        context.lineTo(width, y + scrollRatio * 70);
        context.stroke();
      }

      context.restore();
    }

    function drawRibbons(time) {
      ribbons.forEach((ribbon, index) => {
        const gradient = context.createLinearGradient(0, 0, width, 0);
        gradient.addColorStop(0, `hsla(${ribbon.hue},100%,70%,0)`);
        gradient.addColorStop(0.5, `hsla(${ribbon.hue},100%,70%,.68)`);
        gradient.addColorStop(1, `hsla(${ribbon.hue},100%,70%,0)`);

        context.save();
        context.globalAlpha = 0.16;
        context.strokeStyle = gradient;
        context.lineWidth = 1.4 + index * 0.55;
        context.beginPath();

        for (let x = 0; x <= width; x += 16) {
          const wave =
            Math.sin(x * 0.007 + time * ribbon.speed + ribbon.offset) * ribbon.amp +
            Math.cos(x * 0.003 + time * ribbon.speed) * ribbon.amp * 0.42;
          const y = ribbon.y + wave + scrollRatio * (index % 2 ? 190 : -190);
          x === 0 ? context.moveTo(x, y) : context.lineTo(x, y);
        }

        context.stroke();
        context.restore();
      });
    }

    function updateParticles() {
      for (const particle of particles) {
        particle.x += particle.vx * particle.depth;
        particle.y += particle.vy * particle.depth;

        if (particle.x < -40) particle.x = width + 40;
        if (particle.x > width + 40) particle.x = -40;
        if (particle.y < -40) particle.y = height + 40;
        if (particle.y > height + 40) particle.y = -40;

        if (pointer.active) {
          const dx = pointer.x - particle.x;
          const dy = pointer.y - particle.y;
          const distance = Math.hypot(dx, dy);
          const force = Math.max(0, 1 - distance / 190);
          particle.x -= dx * force * 0.013;
          particle.y -= dy * force * 0.013;
        }
      }
    }

    function drawParticles() {
      const maxDistance = Math.min(165, Math.max(112, width * 0.12));
      updateParticles();

      for (let i = 0; i < particles.length; i++) {
        const a = particles[i];
        const ay = a.y - scrollRatio * 230 * a.depth;

        context.beginPath();
        context.fillStyle = `hsla(${a.hue},100%,72%,${0.25 + a.depth * 0.5})`;
        context.arc(a.x, ay, a.r * a.depth, 0, Math.PI * 2);
        context.fill();

        for (let j = i + 1; j < particles.length; j++) {
          const b = particles[j];
          const by = b.y - scrollRatio * 230 * b.depth;
          const distance = Math.hypot(a.x - b.x, ay - by);

          if (distance < maxDistance) {
            const alpha = (1 - distance / maxDistance) * 0.14;
            context.strokeStyle = `rgba(196,255,92,${alpha})`;
            context.lineWidth = 1;
            context.beginPath();
            context.moveTo(a.x, ay);
            context.lineTo(b.x, by);
            context.stroke();
          }
        }
      }
    }

    function frame(time = 0) {
      context.clearRect(0, 0, width, height);
      drawGradient();
      drawGrid(time);
      drawRibbons(time);
      drawParticles();
      raf = requestAnimationFrame(frame);
    }

    function onPointerMove(event) {
      pointer = { x: event.clientX, y: event.clientY, active: true };
    }

    resizeCanvas();
    updateScroll();
    window.addEventListener('resize', resizeCanvas);
    window.addEventListener('scroll', updateScroll, { passive: true });
    window.addEventListener('pointermove', onPointerMove);
    window.addEventListener('pointerleave', () => {
      pointer.active = false;
    });

    if (reduceMotion) {
      drawGradient();
      drawGrid(0);
      drawRibbons(0);
      drawParticles();
    } else {
      raf = requestAnimationFrame(frame);
    }

    return () => {
      cancelAnimationFrame(raf);
      window.removeEventListener('resize', resizeCanvas);
      window.removeEventListener('scroll', updateScroll);
      window.removeEventListener('pointermove', onPointerMove);
    };
  }, []);

  return <canvas ref={canvasRef} className="canvas-scene" aria-hidden="true" />;
}
