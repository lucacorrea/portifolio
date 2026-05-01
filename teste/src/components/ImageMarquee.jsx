import { galleryImages } from '../data/editionData.js';

export default function ImageMarquee() {
  const row = [...galleryImages.slice(8, 15), ...galleryImages.slice(8, 15)];

  return (
    <section className="marquee-section" aria-label="Galeria em movimento">
      <div className="marquee-track">
        {row.map((image, index) => (
          <figure key={`${image.url}-${index}`} className="marquee-card">
            <img src={image.url} alt={image.title} loading="lazy" />
            <figcaption>
              <small>{image.tag}</small>
              <strong>{image.title}</strong>
            </figcaption>
          </figure>
        ))}
      </div>
    </section>
  );
}
