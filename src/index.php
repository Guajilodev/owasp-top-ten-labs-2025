<?php
/**
 * OWASP Top 10 Labs 2025 - Nexo
 * Home: Presentación de Nexo + Grid de módulos
 */

$pageTitle = 'Nexo - Gestión Empresarial';
$showLabBanner = true; // Muestra el banner de que es un lab educativo

// Módulos de Nexo con su vulnerabilidad correspondiente
$modules = [
    [
        'id' => 'a01',
        'icon' => '📄',
        'name' => 'Mis Facturas',
        'description' => 'Consulta y gestiona las facturas de tus clientes',
        'href' => '/a01_facturas/',
        'owasp' => 'A01: Broken Access Control',
        'status' => 'ready',
    ],
    [
        'id' => 'a02',
        'icon' => '⚙️',
        'name' => 'Panel de Administración',
        'description' => 'Configuración del sistema y usuarios',
        'href' => '/a02_admin/',
        'owasp' => 'A02: Security Misconfiguration',
        'status' => 'ready',
    ],
    [
        'id' => 'a03',
        'icon' => '🔌',
        'name' => 'Tienda de Plugins',
        'description' => 'Extiende Nexo con plugins verificados',
        'href' => '/a03_plugins/',
        'owasp' => 'A03: Software Supply Chain Failures',
        'status' => 'pending',
    ],
    [
        'id' => 'a04',
        'icon' => '👤',
        'name' => 'Registro de Cuenta',
        'description' => 'Crea tu cuenta en Nexo',
        'href' => '/a04_registro/',
        'owasp' => 'A04: Cryptographic Failures',
        'status' => 'ready',
    ],
    [
        'id' => 'a05',
        'icon' => '🔍',
        'name' => 'Buscador de Clientes',
        'description' => 'Busca y filtra tu cartera de clientes',
        'href' => '/a05_clientes/',
        'owasp' => 'A05: Injection',
        'status' => 'pending',
    ],
    [
        'id' => 'a06',
        'icon' => '🛒',
        'name' => 'Checkout',
        'description' => 'Adquiere planes y módulos adicionales',
        'href' => '/a06_checkout/',
        'owasp' => 'A06: Insecure Design',
        'status' => 'pending',
    ],
    [
        'id' => 'a07',
        'icon' => '🔐',
        'name' => 'Login',
        'description' => 'Inicia sesión en tu cuenta',
        'href' => '/a07_login/',
        'owasp' => 'A07: Authentication Failures',
        'status' => 'ready',
    ],
    [
        'id' => 'a08',
        'icon' => '🧑‍💼',
        'name' => 'Preferencias de Cuenta',
        'description' => 'Personaliza tu experiencia en Nexo',
        'href' => '/a08_preferencias/',
        'owasp' => 'A08: Software or Data Integrity Failures',
        'status' => 'ready',
    ],
    [
        'id' => 'a09',
        'icon' => '📊',
        'name' => 'Actividad de Cuenta',
        'description' => 'Historial de acciones en tu cuenta',
        'href' => '/a09_actividad/',
        'owasp' => 'A09: Security Logging and Alerting Failures',
        'status' => 'ready',
    ],
    [
        'id' => 'a10',
        'icon' => '💳',
        'name' => 'Transferir Créditos',
        'description' => 'Transfiere saldo entre cuentas',
        'href' => '/a10_pagos/',
        'owasp' => 'A10: Mishandling of Exceptional Conditions',
        'status' => 'pending',
    ],
];

include __DIR__ . '/shared/header.php';
?>

<main class="container py-4">
    
    <?php if ($showLabBanner): ?>
    <!-- Banner educativo -->
    <div class="alert alert-warning d-flex align-items-center mb-4" role="alert">
        <div class="me-3 fs-3">🎓</div>
        <div>
            <strong>Entorno educativo OWASP Top 10 2025</strong><br>
            <small>Esta aplicación es <strong>deliberadamente vulnerable</strong> con fines de aprendizaje. 
            Cada módulo contiene una vulnerabilidad del OWASP Top 10 2025. 
            <a href="#como-funciona" class="alert-link">¿Cómo funciona?</a></small>
        </div>
    </div>
    <?php endif; ?>

    <!-- Hero de Nexo -->
    <div class="text-center mb-5">
        <h1 class="display-4 fw-bold text-primary">
            <span class="me-2">⚡</span>Nexo
        </h1>
        <p class="lead text-muted">
            Plataforma de gestión empresarial para PYMES
        </p>
        <p class="text-secondary">
            Gestiona clientes, facturas, inventario y pagos desde un único panel.
        </p>
    </div>

    <!-- Grid de módulos -->
    <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 row-cols-xl-5 g-4 mb-5">
        <?php foreach ($modules as $module): ?>
        <div class="col">
            <div class="card h-100 shadow-sm module-card <?= $module['status'] === 'pending' ? 'border-secondary' : 'border-primary' ?>">
                <div class="card-body text-center">
                    <div class="fs-1 mb-2"><?= $module['icon'] ?></div>
                    <h5 class="card-title"><?= htmlspecialchars($module['name']) ?></h5>
                    <p class="card-text small text-muted">
                        <?= htmlspecialchars($module['description']) ?>
                    </p>
                </div>
                <div class="card-footer bg-transparent border-top-0 text-center">
                    <?php if ($module['status'] === 'ready'): ?>
                        <a href="<?= $module['href'] ?>" class="btn btn-primary btn-sm w-100">
                            Abrir módulo
                        </a>
                    <?php else: ?>
                        <span class="badge bg-secondary">Próximamente</span>
                    <?php endif; ?>
                </div>
                <!-- Badge OWASP (visible en hover) -->
                <div class="owasp-badge">
                    <small class="text-danger fw-bold"><?= $module['owasp'] ?></small>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Sección explicativa -->
    <div class="row justify-content-center" id="como-funciona">
        <div class="col-lg-8">
            <div class="card border-0 bg-light">
                <div class="card-body p-4">
                    <h3 class="card-title mb-3">🎯 ¿Cómo usar este laboratorio?</h3>
                    
                    <div class="mb-4">
                        <h5>1. Explora el módulo como usuario</h5>
                        <p class="text-muted">
                            Cada módulo es una funcionalidad real de una app SaaS. Úsala normalmente 
                            para entender qué hace antes de buscar la vulnerabilidad.
                        </p>
                    </div>
                    
                    <div class="mb-4">
                        <h5>2. Identifica y explota la vulnerabilidad</h5>
                        <p class="text-muted">
                            El panel lateral de cada módulo te dice qué vulnerabilidad OWASP contiene 
                            y te da pistas de cómo encontrarla. Usa las herramientas sugeridas.
                        </p>
                    </div>
                    
                    <div class="mb-4">
                        <h5>3. Compara con la versión segura</h5>
                        <p class="text-muted">
                            Cada módulo muestra el código vulnerable y el código corregido lado a lado. 
                            Entiende el <strong>delta</strong>: qué cambió para arreglar el problema.
                        </p>
                    </div>
                    
                    <div class="alert alert-info mb-0">
                        <strong>💡 El estado se resetea automáticamente cada 4 horas.</strong><br>
                        <small>Cualquier dato que crees (XSS, registros falsos, archivos subidos) 
                        desaparecerá. Esto es intencional.</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

</main>

<style>
.module-card {
    transition: transform 0.2s ease, box-shadow 0.2s ease;
    position: relative;
    overflow: hidden;
}

.module-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15) !important;
}

.module-card .owasp-badge {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    background: rgba(255, 255, 255, 0.95);
    padding: 0.5rem;
    text-align: center;
    transform: translateY(100%);
    transition: transform 0.2s ease;
}

.module-card:hover .owasp-badge {
    transform: translateY(0);
}
</style>

<?php include __DIR__ . '/shared/footer.php'; ?>
