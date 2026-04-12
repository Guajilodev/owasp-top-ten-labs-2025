    <!-- Footer -->
    <footer class="bg-dark text-light py-4 mt-auto">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <p class="mb-0">
                        <strong>⚡ Nexo Labs</strong> — OWASP Top 10 2025 Educational Environment
                    </p>
                    <small class="text-muted">
                        Esta aplicación es deliberadamente vulnerable. No usar en producción.
                    </small>
                </div>
                <div class="col-md-6 text-md-end mt-3 mt-md-0">
                    <span class="text-muted me-3">
                        Made with ❤️ by <a href="https://x.com/guajilodev" target="_blank" rel="noopener" class="text-light">@guajilodev</a>
                    </span>
                    <a href="https://owasp.org/Top10/" target="_blank" rel="noopener" class="text-light me-3">
                        OWASP Top 10
                    </a>
                    <a href="https://github.com/Guajilodev/owasp-top-ten-labs-2025" target="_blank" rel="noopener" class="text-light">
                        GitHub
                    </a>
                </div>
            </div>
        </div>
    </footer>
    
    <!-- Bootstrap JS (con SRI - Subresource Integrity) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" 
            integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" 
            crossorigin="anonymous"></script>
    
    <!-- Lab panel toggle -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const panel = document.querySelector('.lab-panel');
            const closeBtn = document.querySelector('.lab-panel-close');
            const openBtn = document.querySelector('.lab-panel-toggle');
            
            if (panel && closeBtn && openBtn) {
                // Cerrar panel (X dentro del panel)
                closeBtn.addEventListener('click', function() {
                    panel.classList.add('collapsed');
                    document.body.classList.add('panel-collapsed');
                });
                
                // Abrir panel (botón externo)
                openBtn.addEventListener('click', function() {
                    panel.classList.remove('collapsed');
                    document.body.classList.remove('panel-collapsed');
                });
            }
        });
    </script>
</body>
</html>
