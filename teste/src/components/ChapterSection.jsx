import { useEffect, useRef } from 'react';
import { gsap } from 'gsap';
import { ScrollTrigger } from 'gsap/ScrollTrigger';

gsap.registerPlugin(ScrollTrigger);

export default function ChapterSection({ chapter, index }) {
  const sectionRef = useRef(null);
  const imageRef = useRef(null);
  const innerImageRef = useRef(null);

  useEffect(() => {
    const imageTween = gsap.fromTo(imageRef.current, { y: 140, scale: 0.96 }, {
      y: -140,
      scale: 1.02,
      ease: 'none',
      scrollTrigger: {
        trigger: sectionRef.current,
        start: 'top bottom',
        end: 'bottom top',
        scrub: true
      }
    });

    const innerTween = gsap.fromTo(innerImageRef.current, { yPercent: -8, scale: 1.12 }, {
      yPercent: 8,
      scale: 1.2,
      ease: 'none',
      scrollTrigger: {
        trigger: sectionRef.current,
        start: 'top bottom',
        end: 'bottom top',
        scrub: true
      }
    });

    return () => {
      imageTween.kill();
      innerTween.kill();
    };
  }, []);

  return (
    <section id={chapter.id} ref={sectionRef} className="chapter-section">
      <div className={`chapter-card container ${index % 2 ? 'is-reverse' : ''}`}>
        <div className="chapter-copy" data-reveal>
          <span className="chapter-number">{chapter.number}</span>
          <p className="chapter-eyebrow">{chapter.eyebrow}</p>
          <h2>{chapter.title}</h2>
          <p>{chapter.text}</p>
          <div className="chapter-points">
            {chapter.points.map((point) => (
              <div key={point}><span /> <strong>{point}</strong></div>
            ))}
          </div>
        </div>

        <figure ref={imageRef} className="chapter-image" data-reveal>
          <img ref={innerImageRef} src={chapter.image} alt={chapter.title} loading="lazy" />
          <div className="chapter-image-caption">
            <small>Parallax stage</small>
            <strong>{chapter.eyebrow}</strong>
          </div>
        </figure>
      </div>
    </section>
  );
}
