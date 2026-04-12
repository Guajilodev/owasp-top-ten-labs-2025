<?php
/**
 * OWASP Top 10 Labs 2025 - A02: Security Misconfiguration
 * Módulo: Panel de Administración - Login
 * 
 * ⚠️ VULNERABLE: Credenciales por defecto activas (admin / admin123)
 */

session_start();

$pageTitle = 'Admin Login - Nexo';

// Info del lab
$labInfo = [
    'id' => 'A02:2025',
    'name' => 'Security Misconfiguration',
    'difficulty' => 'Básica',
    'description' => '
        <p>Este panel tiene <strong>tres problemas</strong> de configuración:</p>
        <ol class="small">
            <li><strong>Credenciales por defecto:</strong> admin / admin123</li>
            <li><strong>Errores verbosos:</strong> El dashboard muestra stack traces con creds de BD</li>
            <li><strong>Backups expuestos:</strong> /a02_admin/backups/ tiene directory listing</li>
        </ol>
    ',
    'exploit' => '# 1. Login con creds por defecto:
Usuario: admin
Password: admin123

# 2. Una vez dentro, buscá algo inválido
# para provocar un error verboso

# 3. Accedé directamente a:
curl http://localhost:8082/a02_admin/backups/',
    'prevention' => '# 1. Cambiar credenciales en el deploy
# 2. display_errors = Off en producción
# 3. Options -Indexes en .htaccess

# En Apache (.htaccess):
Options -Indexes
<Files "*.sql*">
    Require all denied
</Files>',
    'caseStudy' => [
        'title' => 'Capital One (2019)',
        'description' => 'WAF mal configurado permitió acceso a datos de 100M de clientes. Misconfiguration en permisos de IAM.'
    ],
    'cwes' => ['CWE-16', 'CWE-200', 'CWE-521'],
    'tools' => ['curl', 'Nikto', 'browser'],
];

$error = null;
$showHint = false;

// Procesar login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    // ⚠️ VULNERABLE: Credenciales hardcodeadas por defecto
    // En producción esto debería venir de la BD con password hasheado
    if ($username === 'admin' && $password === 'admin123') {
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_username'] = 'admin';
        header('Location: dashboard.php');
        exit;
    } else {
        $error = "Credenciales inválidas";
        $showHint = true;
    }
}

// Si ya está logueado, redirigir al dashboard
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in']) {
    header('Location: dashboard.php');
    exit;
}

include __DIR__ . '/../shared/header.php';
?>

<main class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-5">
            
            <div class="text-center mb-4">
                <h1 class="h3">⚙️ Panel de Administración</h1>
                <p class="text-muted">Acceso restringido a administradores</p>
            </div>
            
            <div class="card shadow-sm">
                <div class="card-body p-4">
                    
                    <?php if ($error): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>
                    
                    <?php if ($showHint): ?>
                    <div class="alert alert-warning small">
                        <strong>💡 Pista del lab:</strong> 
                        Este sistema usa credenciales por defecto. ¿Cuáles son las más comunes?
                    </div>
                    <?php endif; ?>
                    
                    <form method="POST">
                        <div class="mb-3">
                            <label for="username" class="form-label">Usuario</label>
                            <input type="text" class="form-control" id="username" name="username" 
                                   value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="password" class="form-label">Contraseña</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>
                        
                        <button type="submit" class="btn btn-primary w-100">
                            Iniciar sesión
                        </button>
                    </form>
                </div>
            </div>
            
            <div class="text-center mt-3">
                <a href="/" class="text-muted small">← Volver al inicio</a>
            </div>
            
            <!-- Easter egg: backups link -->
            <div class="text-center mt-4">
                <small class="text-muted">
                    <!-- TODO: mover backups a ubicación segura -->
                    <!-- <a href="backups/">Backups del sistema</a> -->
                </small>
            </div>
            
        </div>
    </div>
</main>

<?php 
include __DIR__ . '/../shared/lab_panel.php';
include __DIR__ . '/../shared/footer.php'; 
?>
