<?php
/**
 * OWASP Top 10 Labs 2025 - A02: Security Misconfiguration
 * Módulo: Panel de Administración - Dashboard
 * 
 * ⚠️ VULNERABLE: 
 * - Errores verbosos muestran stack trace con creds de BD
 * - Directorio /backups/ accesible con directory listing
 */

session_start();

// Verificar que esté logueado como admin
if (!isset($_SESSION['admin_logged_in']) || !$_SESSION['admin_logged_in']) {
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/../config/db.php';

$pageTitle = 'Dashboard Admin - Nexo';

// Info del lab
$labInfo = [
    'id' => 'A02:2025',
    'name' => 'Security Misconfiguration',
    'difficulty' => 'Básica',
    'description' => '
        <p>Estás dentro del panel admin. Ahora explorá los otros problemas:</p>
        <ol class="small">
            <li>Buscá algo que genere un error (ej: caracteres especiales)</li>
            <li>Visitá <code>/a02_admin/backups/</code></li>
        </ol>
    ',
    'exploit' => '# Provocar error verboso:
# Buscá: \' OR 1=1 --
# O simplemente: \'

# Ver backups expuestos:
curl http://localhost:8082/a02_admin/backups/

# Descargar el backup:
curl -O http://localhost:8082/a02_admin/backups/nexo_db_2025-03-15.sql.gz',
    'prevention' => '# php.ini (producción):
display_errors = Off
log_errors = On

# .htaccess para backups:
Options -Indexes
<Files "*.sql*">
    Require all denied
</Files>',
    'caseStudy' => [
        'title' => 'Capital One (2019)',
        'description' => 'Configuración por defecto de WAF permitió acceso no autorizado.'
    ],
    'cwes' => ['CWE-16', 'CWE-200', 'CWE-521'],
    'tools' => ['curl', 'Nikto'],
];

$searchResults = null;
$searchQuery = $_GET['q'] ?? '';
$searchError = null;

// Búsqueda de usuarios (vulnerable a errores verbosos)
if (!empty($searchQuery)) {
    try {
        // ⚠️ VULNERABLE: Query sin prepared statements para provocar errores SQL
        // que muestren información sensible en el stack trace
        $conn = getVulnerableConnection();
        
        // Esta query es vulnerable a SQL injection, pero el punto aquí
        // es mostrar errores verbosos, no SQLi (eso es lab A05)
        $sql = "SELECT id, username, email, role FROM users WHERE username LIKE '%" . $conn->real_escape_string($searchQuery) . "%' OR email LIKE '%" . $conn->real_escape_string($searchQuery) . "%'";
        
        $result = $conn->query($sql);
        
        if ($result) {
            $searchResults = $result->fetch_all(MYSQLI_ASSOC);
        }
        
        $conn->close();
        
    } catch (Exception $e) {
        // ⚠️ VULNERABLE: Mostrando el error completo al usuario
        $searchError = $e->getMessage();
    }
}

// Si el query tiene caracteres que rompen la conexión, forzar error
if (strpos($searchQuery, "'") !== false && strpos($searchQuery, "--") !== false) {
    // Simular error de conexión que expone credenciales
    $searchError = "Fatal error: Uncaught PDOException: SQLSTATE[HY000] [1045] Access denied for user 'nexo_user'@'db' (using password: YES) 
    
Stack trace:
#0 /var/www/html/config/db.php(25): PDO->__construct('mysql:host=db;d...', 'nexo_user', 'nexo_password_2025', Array)
#1 /var/www/html/a02_admin/dashboard.php(45): getDbConnection()
#2 {main}

Database credentials exposed:
  Host: db
  User: nexo_user  
  Pass: nexo_password_2025
  Database: nexo_labs

thrown in /var/www/html/config/db.php on line 25";
}

include __DIR__ . '/../shared/header.php';
?>

<main class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1">⚙️ Panel de Administración</h1>
            <p class="text-muted mb-0">
                Bienvenido, <strong><?= htmlspecialchars($_SESSION['admin_username']) ?></strong>
                <span class="badge bg-danger ms-2">admin</span>
            </p>
        </div>
        <div>
            <a href="backups/" class="btn btn-outline-secondary btn-sm me-2">
                📁 Backups
            </a>
            <a href="logout.php" class="btn btn-outline-danger btn-sm">
                Cerrar sesión
            </a>
        </div>
    </div>
    
    <!-- Alerta de vulnerabilidades -->
    <div class="alert alert-danger">
        <strong>🔴 Vulnerabilidades activas en este panel:</strong>
        <ul class="mb-0 mt-2">
            <li>✓ Credenciales por defecto (<code>admin</code> / <code>admin123</code>)</li>
            <li>Errores verbosos — buscá algo con comillas para provocar un error</li>
            <li>Backups expuestos — hacé clic en "Backups" arriba</li>
        </ul>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <!-- Buscador de usuarios -->
            <div class="card mb-4">
                <div class="card-header bg-white">
                    <strong>🔍 Buscar usuarios</strong>
                </div>
                <div class="card-body">
                    <form method="GET" class="mb-3">
                        <div class="input-group">
                            <input type="text" class="form-control" name="q" 
                                   placeholder="Buscar por nombre o email..." 
                                   value="<?= htmlspecialchars($searchQuery) ?>">
                            <button class="btn btn-primary" type="submit">Buscar</button>
                        </div>
                        <small class="text-muted">
                            Pista: Probá buscar <code>' OR 1=1 --</code> para ver qué pasa
                        </small>
                    </form>
                    
                    <?php if ($searchError): ?>
                    <!-- ⚠️ VULNERABLE: Mostrando error completo con stack trace -->
                    <div class="alert alert-danger">
                        <strong>Error de base de datos:</strong>
                        <pre class="mb-0 mt-2 small" style="white-space: pre-wrap; word-break: break-all;"><?= htmlspecialchars($searchError) ?></pre>
                    </div>
                    
                    <div class="alert alert-warning">
                        <strong>🚨 ¡Información sensible expuesta!</strong><br>
                        <small>El error muestra credenciales de la base de datos. En producción, 
                        <code>display_errors</code> debería estar en <code>Off</code>.</small>
                    </div>
                    
                    <?php elseif ($searchResults !== null): ?>
                        <?php if (empty($searchResults)): ?>
                            <p class="text-muted">No se encontraron resultados.</p>
                        <?php else: ?>
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Usuario</th>
                                        <th>Email</th>
                                        <th>Rol</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($searchResults as $user): ?>
                                    <tr>
                                        <td><?= $user['id'] ?></td>
                                        <td><?= htmlspecialchars($user['username']) ?></td>
                                        <td><?= htmlspecialchars($user['email']) ?></td>
                                        <td><span class="badge bg-secondary"><?= $user['role'] ?></span></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Stats falsas -->
            <div class="row">
                <div class="col-md-4">
                    <div class="card text-center">
                        <div class="card-body">
                            <h3 class="text-primary">8</h3>
                            <small class="text-muted">Clientes activos</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card text-center">
                        <div class="card-body">
                            <h3 class="text-success">$24.5M</h3>
                            <small class="text-muted">Facturado</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card text-center">
                        <div class="card-body">
                            <h3 class="text-warning">5</h3>
                            <small class="text-muted">Usuarios</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-4">
            <!-- Info del servidor (vulnerable) -->
            <div class="card border-danger">
                <div class="card-header bg-danger text-white">
                    🔴 Info del servidor (expuesta)
                </div>
                <div class="card-body small">
                    <p class="mb-1"><strong>PHP:</strong> <?= phpversion() ?></p>
                    <p class="mb-1"><strong>Server:</strong> <?= $_SERVER['SERVER_SOFTWARE'] ?? 'Apache' ?></p>
                    <p class="mb-1"><strong>Document Root:</strong> <code><?= $_SERVER['DOCUMENT_ROOT'] ?></code></p>
                    <p class="mb-0"><strong>display_errors:</strong> <code><?= ini_get('display_errors') ? 'On' : 'Off' ?></code></p>
                </div>
                <div class="card-footer small text-muted">
                    Esta info no debería ser visible en producción
                </div>
            </div>
            
            <!-- Versión segura -->
            <div class="card mt-3 border-success">
                <div class="card-header bg-success text-white">
                    🛡️ Configuración segura
                </div>
                <div class="card-body small">
                    <p><strong>php.ini:</strong></p>
                    <pre class="bg-light p-2 rounded mb-2">display_errors = Off
log_errors = On
error_log = /var/log/php/error.log</pre>
                    
                    <p><strong>.htaccess para /backups/:</strong></p>
                    <pre class="bg-light p-2 rounded mb-0">Options -Indexes
&lt;Files "*.sql*"&gt;
    Require all denied
&lt;/Files&gt;</pre>
                </div>
            </div>
        </div>
    </div>
</main>

<?php 
include __DIR__ . '/../shared/lab_panel.php';
include __DIR__ . '/../shared/footer.php'; 
?>
