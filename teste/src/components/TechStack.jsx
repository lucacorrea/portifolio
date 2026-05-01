const techs = ['React', 'Canvas 2D', 'GSAP', 'ScrollTrigger', 'Lenis', 'CSS responsivo', 'Imagens remotas', 'Vite'];

export default function TechStack() {
  return (
    <section className="stack-section section-pad">
      <div className="container stack-grid">
        <div data-reveal>
          <div className="section-kicker"><span /> Tecnologias</div>
          <h2 className="big-statement stack-title">Feito para parecer grande e rodar leve.</h2>
          <p className="large-text">Canvas 2D cuida da atmosfera visual. GSAP e ScrollTrigger cuidam do parallax. Lenis deixa a rolagem suave. React organiza tudo em componentes.</p>
        </div>
        <div className="terminal" data-reveal>
          <div className="terminal-bar"><span /><span /><span /><small>stack.config</small></div>
          <pre>{`{
  "empresa": "L&J Soluções Tecnológicas",
  "front": ["React", "Vite", "CSS3"],
  "motion": ["Canvas 2D", "GSAP", "Lenis"],
  "parallax": "imagens em múltiplas camadas",
  "contato": ["WhatsApp", "E-mail"]
}`}</pre>
          <div className="tech-tags">
            {techs.map((tech) => <span key={tech}>{tech}</span>)}
          </div>
        </div>
      </div>
    </section>
  );
}
