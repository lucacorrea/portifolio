import { useEffect, useState } from 'react';
import { navItems } from '../data/editionData.js';

export default function Header() {
  const [open, setOpen] = useState(false);
  const [scrolled, setScrolled] = useState(false);

  useEffect(() => {
    function onScroll() {
      setScrolled(window.scrollY > 22);
    }

    onScroll();
    window.addEventListener('scroll', onScroll, { passive: true });
    return () => window.removeEventListener('scroll', onScroll);
  }, []);

  return (
    <header className={`header ${scrolled ? 'is-scrolled' : ''}`}>
      <a href="#inicio" className="brand" aria-label="L&J Soluções Tecnológicas">
        <span className="brand-mark">L&J</span>
        <span className="brand-copy">
          <strong>Soluções Tecnológicas</strong>
          <small>Parallax Canvas Edition</small>
        </span>
      </a>

      <button className={`menu-button ${open ? 'is-open' : ''}`} type="button" aria-label="Abrir menu" aria-expanded={open} onClick={() => setOpen((value) => !value)}>
        <span />
        <span />
      </button>

      <nav className={`site-nav ${open ? 'is-open' : ''}`} aria-label="Menu principal">
        {navItems.map((item) => (
          <a key={item.href} href={item.href} onClick={() => setOpen(false)} className={item.label === 'Contato' ? 'nav-cta' : ''}>
            {item.label}
          </a>
        ))}
      </nav>
    </header>
  );
}
