export default function Footer() {
  return (
    <footer className="footer">
      <div className="container footer-inner">
        <p>© {new Date().getFullYear()} L&J Soluções Tecnológicas. Todos os direitos reservados.</p>
        <a href="#inicio">Voltar ao topo</a>
      </div>
      <a className="float-whatsapp" href="https://wa.me/5592991515710?text=Ol%C3%A1%2C%20conheci%20a%20Parallax%20Canvas%20Edition%20e%20quero%20conversar." target="_blank" rel="noreferrer">WhatsApp</a>
    </footer>
  );
}
