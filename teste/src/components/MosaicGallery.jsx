import { projectCards } from '../data/editionData.js';

export default function MosaicGallery() {
  return (
    <section id="projetos" className="projects-section section-pad">
      <div className="container">
        <div className="section-kicker" data-reveal><span /> Projetos</div>
        <h2 className="big-statement short" data-reveal>Blocos visuais grandes para vender capacidade técnica.</h2>
        <div className="project-grid">
          {projectCards.map((project) => (
            <article key={project.title} className="project-card" data-reveal>
              <img src={project.image} alt={project.title} loading="lazy" />
              <div className="project-card-content">
                <small>{project.label}</small>
                <h3>{project.title}</h3>
                <p>{project.text}</p>
              </div>
            </article>
          ))}
        </div>
      </div>
    </section>
  );
}
