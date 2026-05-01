import { useEffect, useRef } from 'react';
import { gsap } from 'gsap';
import { ScrollTrigger } from 'gsap/ScrollTrigger';
import { galleryImages } from '../data/editionData.js';

gsap.registerPlugin(ScrollTrigger);

function EditionPill() {
  return (
    <div className="edition-pill">
      <span />
      <strong>L&J Winter Edition 2026</strong>
    </div>
  );
}

function FloatingImage({ image, className, speed = 120 }) {
  const ref = useRef(null);

  useEffect(() => {
    const element = ref.current;
    const animation = gsap.fromTo(
      element,
      { y: speed },
      {
        y: -speed,
        ease: 'none',
        scrollTrigger: {
          trigger: element,
          start: 'top bottom',
          end: 'bottom top',
          scrub: true
        }
      }
    );

    return () => animation.kill();
  }, [speed]);

  return (
    <figure ref={ref} className={`floating-image ${className}`}>
      <img src={image.url} alt={image.title} loading="lazy" />
      <figcaption>
        <small>{image.tag}</small>
        <strong>{image.title}</strong>
      </figcaption>
    </figure>
  );
}

export default function Hero() {
  const titleRef = useRef(null);

  useEffect(() => {
    const animation = gsap.fromTo(
      titleRef.current,
      { y: 0, autoAlpha: 1 },
      {
        y: -100,
        autoAlpha: 0.32,
        ease: 'none',
        scrollTrigger: {
          trigger: titleRef.current,
          start: 'top 16%',
          end: 'bottom top',
          scrub: true
        }
      }
    );

    return () => animation.kill();
  }, []);

  return (
    <section id="inicio" className="hero-section">
      <div className="container hero-grid">
        <div ref={titleRef} className="hero-copy">
          <EditionPill />
          <h1>Sites com profundidade de marca.</h1>
          <p>
            Uma experiência React com parallax, Canvas 2D, imagens editoriais, blocos gigantes e navegação estilo lançamento premium para apresentar a L&J Soluções Tecnológicas com força visual.
          </p>
          <div className="hero-actions">
            <a className="button button-primary" href="#contato">Quero esse projeto</a>
            <a className="button button-secondary" href="#visao">Ver experiência</a>
          </div>
          <div className="hero-metrics">
            <article><strong>2D</strong><span>Canvas interativo</span></article>
            <article><strong>3 camadas</strong><span>Parallax real</span></article>
            <article><strong>React</strong><span>GSAP + Lenis</span></article>
          </div>
        </div>

        <div className="hero-gallery" aria-label="Imagens em parallax">
          <FloatingImage image={galleryImages[0]} className="fi-one" speed={160} />
          <FloatingImage image={galleryImages[1]} className="fi-two" speed={250} />
          <FloatingImage image={galleryImages[2]} className="fi-three" speed={115} />
          <FloatingImage image={galleryImages[3]} className="fi-four" speed={210} />
          <div className="hero-glass-card" data-reveal>
            <small>live preview</small>
            <strong>Experiência editorial com imagens em camadas.</strong>
          </div>
        </div>
      </div>
    </section>
  );
}
