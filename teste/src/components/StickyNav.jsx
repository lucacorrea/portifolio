import { navItems } from '../data/editionData.js';

export default function StickyNav() {
  return (
    <div className="edition-nav">
      <div className="edition-nav-inner container">
        {navItems.slice(1).map((item) => (
          <a key={item.href} href={item.href}>{item.label}</a>
        ))}
      </div>
    </div>
  );
}
