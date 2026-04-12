<?php
/**
 * OWASP Top 10 Labs 2025 - A09: Security Logging and Alerting Failures
 * Módulo: Actividad de Cuenta
 * 
 * ⚠️ VULNERABLE:
 * 1. No loguea intentos de login fallidos
 * 2. El log está accesible públicamente
 * 3. Log injection - input sin sanitizar
 */

session_start();
require_once __DIR__ . '/../config/db.php';

$pageTitle = 'Actividad de Cuenta - Nexo';

// Info del lab
$labInfo = [
    'id' => 'A09:2025',
    'name' => 'Security Logging and Alerting Failures',
    'difficulty' => 'Básica',
    'description' => '
        <p>Este módulo tiene <strong>tres problemas de logging</strong>:</p>
        <ol class="small">
            <li>Los intentos de login fallidos <strong>no se loguean</strong></li>
            <li>El archivo de log está <strong>accesible públicamente</strong></li>
            <li><strong>Log injection:</strong> podés inyectar texto falso en el log</li>
        </ol>
    ',
    'exploit' => '# 1. Ver el log público:
curl http://localhost:8082/a09_actividad/logs/app.log

# 2. Log injection - buscar:
# Probá buscar: "\n[2025-04-12 10:00:00] admin LOGIN_SUCCESS"
# Eso va a insertar una línea falsa en el log

# 3. Hacé brute force en A07 - no queda rastro',
    'prevention' => '# 1. Loguear SIEMPRE intentos fallidos:
log_event("login_failed", $username, $ip);

# 2. Mover logs FUERA del webroot:
error_log = /var/log/nexo/app.log  # NO en /var/www/html/

# 3. Sanitizar input antes de loguear:
$safe = str_replace(["\r", "\n"], "", $input);',
    'caseStudy' => [
        'title' => 'Uber (2016)',
        'description' => 'Atacante tuvo acceso por más de un año sin ser detectado por falta de monitoreo de accesos anómalos.'
    ],
    'cwes' => ['CWE-778', 'CWE-223', 'CWE-117'],
    'tools' => ['curl', 'Browser'],
];

// Ruta del log (dentro del webroot - VULNERABLE)
$logFile = __DIR__ . '/logs/app.log';

// Función para escribir en el log (VULNERABLE - sin sanitizar)
function writeLog($action, $details = '', $userId = null) {
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $userPart = $userId ? "user_id=$userId" : "anonymous";
    
    // ⚠️ VULNERABLE: No sanitiza $details - permite log injection
    $logLine = "[$timestamp] $ip $userPart $action: $details\n";
    
    file_put_contents($logFile, $logLine, FILE_APPEND | LOCK_EX);
}

// Procesar búsqueda (con log injection)
$searchQuery = '';
$searchResults = [];

if (isset($_GET['q']) && !empty($_GET['q'])) {
    $searchQuery = $_GET['q'];
    
    // ⚠️ VULNERABLE: Log injection - el input se escribe directo al log
    writeLog('SEARCH', $searchQuery);
    
    // Buscar en activity_log
    try {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare("
            SELECT al.*, u.username 
            FROM activity_log al 
            LEFT JOIN users u ON al.user_id = u.id 
            WHERE al.action LIKE ? OR al.details LIKE ?
            ORDER BY al.created_at DESC
            LIMIT 50
        ");
        $term = "%$searchQuery%";
        $stmt->execute([$term, $term]);
        $searchResults = $stmt->fetchAll();
    } catch (PDOException $e) {
        // Error silencioso
    }
}

// Obtener actividad reciente de la BD
try {
    $pdo = getDbConnection();
    $stmt = $pdo->query("
        SELECT al.*, u.username 
        FROM activity_log al 
        LEFT JOIN users u ON al.user_id = u.id 
        ORDER BY al.created_at DESC 
        LIMIT 20
    ");
    $activities = $stmt->fetchAll();
} catch (PDOException $e) {
    $activities = [];
}

include __DIR__ . '/../shared/header.php';
?>

<main class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1">📊 Actividad de Cuenta</h1>
            <p class="text-muted mb-0">Historial de acciones en el sistema</p>
        </div>
        <a href="/" class="btn btn-outline-secondary">← Volver</a>
    </div>
    
    <div class="row">
        <div class="col-lg-8">
            <!-- Buscador con log injection -->
            <div class="card mb-4">
                <div class="card-header bg-white">
                    <strong>🔍 Buscar en actividad</strong>
                </div>
                <div class="card-body">
                    <form method="GET" class="mb-3">
                        <div class="input-group">
                            <input type="text" class="form-control" name="q" 
                                   placeholder="Buscar acciones..." 
                                   value="<?= htmlspecialchars($searchQuery) ?>">
                            <button class="btn btn-primary" type="submit">Buscar</button>
                        </div>
                        <small class="text-muted">
                            💡 Pista: Lo que busques se escribe en el log. 
                            Probá: <code>fake\n[2025-04-12 00:00:00] 127.0.0.1 admin LOGIN_SUCCESS: Logged in</code>
                        </small>
                    </form>
                    
                    <?php if (!empty($searchResults)): ?>
                    <h6>Resultados:</h6>
                    <ul class="list-group list-group-flush">
                        <?php foreach ($searchResults as $result): ?>
                        <li class="list-group-item small">
                            <code><?= htmlspecialchars($result['action']) ?></code>
                            — <?= htmlspecialchars($result['details'] ?? '') ?>
                            <span class="text-muted">(<?= $result['created_at'] ?>)</span>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                    <?php elseif (!empty($searchQuery)): ?>
                    <p class="text-muted">No se encontraron resultados.</p>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Tabla de actividad -->
            <div class="card">
                <div class="card-header bg-white d-flex justify-content-between">
                    <strong>Actividad reciente</strong>
                    <span class="badge bg-secondary"><?= count($activities) ?> registros</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Fecha</th>
                                <th>Usuario</th>
                                <th>Acción</th>
                                <th>Detalles</th>
                                <th>IP</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($activities as $act): ?>
                            <tr>
                                <td class="small"><?= $act['created_at'] ?></td>
                                <td><code><?= htmlspecialchars($act['username'] ?? 'N/A') ?></code></td>
                                <td>
                                    <?php
                                    $actionColors = [
                                        'login_success' => 'success',
                                        'login_failed' => 'danger',
                                        'invoice_view' => 'info',
                                        'invoice_create' => 'primary',
                                        'settings_change' => 'warning',
                                    ];
                                    $color = $actionColors[$act['action']] ?? 'secondary';
                                    ?>
                                    <span class="badge bg-<?= $color ?>"><?= $act['action'] ?></span>
                                </td>
                                <td class="small"><?= htmlspecialchars($act['details'] ?? '') ?></td>
                                <td class="small"><code><?= htmlspecialchars($act['ip_address'] ?? '') ?></code></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="card-footer small text-muted">
                    <strong>⚠️ Nota:</strong> No hay registros de <code>login_failed</code>. 
                    Los intentos fallidos no se loguean.
                </div>
            </div>
        </div>
        
        <div class="col-lg-4">
            <!-- Log público -->
            <div class="card border-danger mb-4">
                <div class="card-header bg-danger text-white">
                    🔴 Log público (vulnerable)
                </div>
                <div class="card-body">
                    <p class="small">El archivo de log está accesible públicamente:</p>
                    <a href="logs/app.log" target="_blank" class="btn btn-outline-danger btn-sm w-100 mb-3">
                        📄 Ver /logs/app.log
                    </a>
                    
                    <p class="small text-muted mb-2">Contenido actual:</p>
                    <pre class="bg-light p-2 rounded small" style="max-height: 200px; overflow: auto;"><?php
                        if (file_exists($logFile)) {
                            $content = file_get_contents($logFile);
                            echo htmlspecialchars($content ?: '(vacío)');
                        } else {
                            echo '(archivo no existe)';
                        }
                    ?></pre>
                </div>
            </div>
            
            <!-- Problemas identificados -->
            <div class="card border-warning mb-4">
                <div class="card-header bg-warning">
                    ⚠️ Problemas de logging
                </div>
                <div class="card-body small">
                    <ol class="mb-0">
                        <li class="mb-2">
                            <strong>Sin login_failed:</strong><br>
                            Brute force en A07 no deja rastro
                        </li>
                        <li class="mb-2">
                            <strong>Log en webroot:</strong><br>
                            Cualquiera puede leer <code>/logs/app.log</code>
                        </li>
                        <li>
                            <strong>Log injection:</strong><br>
                            El buscador escribe directo al log sin sanitizar
                        </li>
                    </ol>
                </div>
            </div>
            
            <!-- Fix -->
            <div class="card border-success">
                <div class="card-header bg-success text-white">
                    🛡️ Cómo arreglarlo
                </div>
                <div class="card-body small">
                    <pre class="bg-light p-2 rounded mb-0"><code># 1. Loguear intentos fallidos
log_event('login_failed', $user, $ip);

# 2. Mover log fuera del webroot
error_log = /var/log/nexo/app.log

# 3. Sanitizar antes de loguear
$safe = str_replace(
    ["\r", "\n", "\t"], 
    ['', '', ''], 
    $input
);

# 4. Configurar alertas
if (failed_attempts > 10) {
    send_alert_to_security_team();
}</code></pre>
                </div>
            </div>
        </div>
    </div>
</main>

<?php 
include __DIR__ . '/../shared/lab_panel.php';
include __DIR__ . '/../shared/footer.php'; 
?>
