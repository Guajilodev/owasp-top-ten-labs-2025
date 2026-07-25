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
        'status' => 'ready',
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
        'status' => 'ready',
    ],
    [
        'id' => 'a06',
        'icon' => '🛒',
        'name' => 'Checkout',
        'description' => 'Adquiere planes y módulos adicionales',
        'href' => '/a06_checkout/',
        'owasp' => 'A06: Insecure Design',
        'status' => 'ready',
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
        'status' => 'ready',
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

    <!-- Orientación del laboratorio -->
    <section class="row justify-content-center mb-5" id="como-funciona">
        <div class="col-lg-10">
            <div class="card border-0 bg-light">
                <div class="card-body p-4">
                    <h2 class="h3 card-title mb-2">🎯 ¿Cómo usar este laboratorio?</h2>
                    <p class="text-muted mb-4">
                        Nexo es un laboratorio educativo deliberadamente vulnerable: cada módulo convierte en un caso concreto un concepto del OWASP Top 10 2025.
                    </p>
                    <div class="row row-cols-1 row-cols-md-3 g-3 mb-4">
                        <div class="col">
                            <h3 class="h5">1. Elige y explora</h3>
                            <p class="text-muted mb-0">Abre un módulo y úsalo como lo haría una persona usuaria.</p>
                        </div>
                        <div class="col">
                            <h3 class="h5">2. Identifica y explota</h3>
                            <p class="text-muted mb-0">Identifica y explota de forma segura la vulnerabilidad prevista, siguiendo la guía del módulo.</p>
                        </div>
                        <div class="col">
                            <h3 class="h5">3. Compara la versión segura</h3>
                            <p class="text-muted mb-0">Contrasta la implementación vulnerable con la corrección para entender qué cambió.</p>
                        </div>
                    </div>
                    <div class="alert alert-info mb-0">
                        <strong>💡 El estado del laboratorio se restablece automáticamente cada 4 horas.</strong><br>
                        <small>Los datos creados durante las prácticas, como registros falsos o archivos subidos, se eliminan de forma intencional.</small>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Grid de módulos -->
    <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 row-cols-xl-5 g-4 mb-5">
        <?php foreach ($modules as $module): ?>
        <div class="col">
            <?php if ($module['status'] === 'ready'): ?>
            <a href="<?= $module['href'] ?>" class="text-decoration-none">
            <?php endif; ?>
            <div class="card h-100 shadow-sm module-card <?= $module['status'] === 'pending' ? 'border-secondary' : 'border-primary' ?> <?= $module['status'] === 'ready' ? 'card-clickable' : '' ?>">
                <div class="card-body text-center">
                    <div class="fs-1 mb-2"><?= $module['icon'] ?></div>
                    <h5 class="card-title text-dark"><?= htmlspecialchars($module['name']) ?></h5>
                    <p class="card-text small text-muted">
                        <?= htmlspecialchars($module['description']) ?>
                    </p>
                </div>
                <div class="card-footer bg-transparent border-top-0 text-center">
                    <?php if ($module['status'] === 'ready'): ?>
                        <span class="btn btn-primary btn-sm w-100">
                            Abrir módulo
                        </span>
                    <?php else: ?>
                        <span class="badge bg-secondary">Próximamente</span>
                    <?php endif; ?>
                </div>
                <!-- Badge OWASP (visible en hover) -->
                <div class="owasp-badge">
                    <small class="text-danger fw-bold"><?= $module['owasp'] ?></small>
                </div>
            </div>
            <?php if ($module['status'] === 'ready'): ?>
            </a>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
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

.card-clickable {
    cursor: pointer;
}

.card-clickable:hover {
    border-color: #0d6efd !important;
}
</style>

<?php include __DIR__ . '/shared/footer.php'; ?>
