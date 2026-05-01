import { useEffect, useRef } from 'react';
import { gsap } from 'gsap';
import { ScrollTrigger } from 'gsap/ScrollTrigger';
import { galleryImages } from '../data/editionData.js';

gsap.registerPlugin(ScrollTrigger);

export default function Vision() {
  const leftRef = useRef(null);
  const rightRef = useRef(null);

  useEffect(() => {
    const first = gsap.fromTo(leftRef.current, { y: 90 }, {
      y: -90,
      ease: 'none',
      scrollTrigger: { trigger: leftRef.current, start: 'top bottom', end: 'bottom top', scrub: true }
    });
    const second = gsap.fromTo(rightRef.current, { y: -70 }, {
      y: 120,
      ease: 'none',
      scrollTrigger: { trigger: rightRef.current, start: 'top bottom', end: 'bottom top', scrub: true }
    });

    return () => {
      first.kill();
      second.kill();
    };
  }, []);

  return (
    <section id="visao" className="vision-section section-pad">
      <div className="container">
        <div className="section-kicker" data-reveal><span /> O pensamento correto</div>
        <h2 className="big-statement" data-reveal>O efeito certo não é enfeite. É percepção de valor.</h2>
        <p className="large-text" data-reveal>
          A linguagem visual de uma página de grande lançamento usa imagens grandes, movimento suave, categorias, contraste e ritmo. O visitante sente que está diante de uma empresa mais forte.
        </p>

        <div className="vision-grid">
          <div ref={leftRef} className="vision-card" data-reveal>
            <small>01 / narrativa</small>
            <h3>A empresa vira uma experiência.</h3>
            <p>Não é só mostrar serviços. É conduzir o visitante por uma jornada visual que mostra autoridade, organização e capacidade técnica.</p>
          </div>
          <div ref={rightRef} className="mini-gallery">
            {galleryImages.slice(4, 8).map((image, index) => (
              <figure key={image.url} className={index % 2 ? 'shift-down' : ''} data-reveal>
                <img src={image.url} alt={image.title} loading="lazy" />
                <figcaption>
                  <small>{image.tag}</small>
                  <strong>{image.title}</strong>
                </figcaption>
              </figure>
            ))}
          </div>
        </div>
      </div>
    </section>
  );
}
