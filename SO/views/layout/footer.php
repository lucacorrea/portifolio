        </div> <!-- container-xl -->
    </main> <!-- page-body -->

    <footer class="no-print" style="
        padding: 1.5rem 0;
        font-size: 0.8rem;
        color: var(--text-muted);
        border-top: 1px solid var(--border-color);
        background: var(--white);
        margin-top: auto;
    ">
        <div class="container-xl">

            <!-- FLEX CONTAINER -->
            <div class="footer-content">
                
                <!-- ESQUERDA -->
                <div class="footer-left">
                    SGAO &copy; <?php echo date('Y'); ?> - Sistema de Gestão de Ofícios e Aquisições        </div>

                <!-- DIREITA -->
                <div class="footer-right">
                    Desenvolvido por <strong>Junior Praia, Lucas Correa e Luiz Frota.</strong>
                </div>

            </div>

        </div>
    </footer>

</div> <!-- page-wrapper -->

<style>
.footer-content {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 1rem;
}

/* TEXTO DIREITA */
.footer-right {
    text-align: right;
    font-size: 0.75rem;
    color: #999;
}

/* RESPONSIVO */
@media (max-width: 768px) {
    .footer-content {
        flex-direction: column;
        text-align: center;
    }

    .footer-right {
        text-align: center;
    }
}
</style>

</body>
</html>