import { useEffect } from 'react';
import Lenis from 'lenis';
import { gsap } from 'gsap';
import { ScrollTrigger } from 'gsap/ScrollTrigger';
import CanvasScene from './components/CanvasScene.jsx';
import Header from './components/Header.jsx';
import Hero from './components/Hero.jsx';
import StickyNav from './components/StickyNav.jsx';
import Vision from './components/Vision.jsx';
import ChapterSection from './components/ChapterSection.jsx';
import ImageMarquee from './components/ImageMarquee.jsx';
import MosaicGallery from './components/MosaicGallery.jsx';
import TechStack from './components/TechStack.jsx';
import Contact from './components/Contact.jsx';
import Footer from './components/Footer.jsx';
import { chapters } from './data/editionData.js';

gsap.registerPlugin(ScrollTrigger);

export default function App() {
  useEffect(() => {
    const lenis = new Lenis({
      duration: 1.12,
      easing: (t) => Math.min(1, 1.001 - Math.pow(2, -10 * t)),
      smoothWheel: true,
      wheelMultiplier: 0.9
    });

    function raf(time) {
      lenis.raf(time);
      requestAnimationFrame(raf);
    }

    const rafId = requestAnimationFrame(raf);
    lenis.on('scroll', ScrollTrigger.update);

    const reveals = gsap.utils.toArray('[data-reveal]');
    reveals.forEach((item) => {
      gsap.fromTo(
        item,
        { y: 44, autoAlpha: 0 },
        {
          y: 0,
          autoAlpha: 1,
          duration: 0.9,
          ease: 'power3.out',
          scrollTrigger: {
            trigger: item,
            start: 'top 84%'
          }
        }
      );
    });

    return () => {
      cancelAnimationFrame(rafId);
      lenis.destroy();
      ScrollTrigger.getAll().forEach((trigger) => trigger.kill());
    };
  }, []);

  return (
    <>
      <CanvasScene />
      <div className="grain" aria-hidden="true" />
      <Header />
      <main>
        <Hero />
        <StickyNav />
        <Vision />
        <ImageMarquee />
        {chapters.map((chapter, index) => (
          <ChapterSection key={chapter.id} chapter={chapter} index={index} />
        ))}
        <MosaicGallery />
        <TechStack />
        <Contact />
      </main>
      <Footer />
    </>
  );
}
