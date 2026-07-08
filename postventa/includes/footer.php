    </main>
    <!-- /site-main -->
    
    <!-- Footer -->
    <footer class="site-footer">
        <div class="footer-container">
            <div class="footer-top">
                <div class="footer-col">
                    <h4><i class="fas fa-building"></i> Centinela Inmobiliaria</h4>
                    <p>Postventa y atención al cliente. Estamos comprometidos con la calidad y satisfacción de nuestros propietarios.</p>
                </div>
                <div class="footer-col">
                    <h4><i class="fas fa-phone-alt"></i> Contacto</h4>
                    <ul>
                        <li><i class="fas fa-map-marker-alt"></i> Narciso Goycolea 4040, Piso 1, Vitacura</li>
                        <li><i class="fas fa-phone"></i> +56 2 27 07 67 00</li>
                        <li><i class="fas fa-envelope"></i> postventa@icentinela.cl</li>
                    </ul>
                </div>
                <div class="footer-col">
                    <h4><i class="fas fa-link"></i> Enlaces</h4>
                    <ul>
                        <li><a href="https://icentinela.cl" target="_blank">Inmobiliaria Centinela</a></li>
                        <li><a href="https://www.sigro.cl" target="_blank">Constructora SIGRO</a></li>
                        <li><a href="https://web.sigro.cl" target="_blank">Sistema SIGRO</a></li>
                    </ul>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; <?php echo date('Y'); ?> Inmobiliaria Centinela. Todos los derechos reservados.</p>
            </div>
        </div>
    </footer>
    
    <!-- JavaScript -->
    <script src="<?php echo ASSETS_URL; ?>js/main.js"></script>
    
    <?php if ($currentPage == 'nueva-solicitud.php'): ?>
    <script src="<?php echo ASSETS_URL; ?>js/nueva-solicitud.js"></script>
    <?php endif; ?>
    
    <?php if ($currentPage == 'admin.php' || $currentPage == 'admin-detalle.php'): ?>
    <script src="<?php echo ASSETS_URL; ?>js/admin.js"></script>
    <?php endif; ?>
</body>
</html>
