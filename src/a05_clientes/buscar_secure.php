<?php
/**
 * OWASP Top 10 Labs 2025 - A05: Injection
 * Modulo: Buscador de Clientes - VERSION SEGURA
 * 
 * PROTECCIONES:
 * 1. Prepared statements con PDO (previene SQL Injection)
 * 2. htmlspecialchars() en todo el output (previene XSS)
 * 
 * Este archivo demuestra las buenas practicas.
 */

session_start();

$_SESSION['user_id'] = $_SESSION['user_id'] ?? 2;
$_SESSION['username'] = $_SESSION['username'] ?? 'alice';

require_once __DIR__ . '/../config/db.php';

$pageTitle = 'Busqueda Segura - Nexo';

$labInfo = [
    'id' => 'A05:2025',
    'name' => 'Injection (SEGURO)',
    'difficulty' => 'Intermedia',
    'description' => '
        <p>Esta version <strong>previene ambas vulnerabilidades</strong>:</p>
        <ol>
            <li><strong>SQL Injection:</strong> Usa prepared statements con PDO</li>
            <li><strong>XSS:</strong> Usa htmlspecialchars() en todo el output</li>
        </ol>
        <p class="mb-0">Proba los mismos payloads que en buscar.php - no van a funcionar.</p>
    ',
    'exploit' => '# Estos payloads NO funcionan aqui:

# SQL Injection - se busca literalmente
?q=\' OR \'1\'=\'1

# XSS - se muestra como texto
?q=<script>alert(\'XSS\')</script>

# La query parametrizada escapa el input
# El htmlspecialchars() convierte < > a entidades',
    'prevention' => '// VERSION SEGURA (este archivo):

// 1. Prepared statement con placeholder
$stmt = $pdo->prepare("
    SELECT * FROM clients 
    WHERE name LIKE ?
");
$stmt->execute([\'%\' . $search . \'%\']);

// 2. Output escapado
echo htmlspecialchars($row[\'name\'], ENT_QUOTES, \'UTF-8\');

// El placeholder ? nunca se interpreta como SQL
// htmlspecialchars convierte < > " \' a entidades HTML',
    'caseStudy' => [
        'title' => 'Equifax (2017)',
        'description' => 'Si hubieran usado prepared statements, 147 millones de personas no habrian sido afectadas.'
    ],
    'cwes' => ['CWE-89', 'CWE-79'],
    'tools' => ['SQLMap (no funcionara)', 'Burp Suite'],
];

$searchTerm = $_GET['q'] ?? '';
$results = [];
$error = null;
$attemptedInjection = false;

if ($searchTerm !== '') {
    try {
        $pdo = getDbConnection();
        
        // SEGURO: Prepared statement con placeholder
        // El valor de $searchTerm NUNCA se interpreta como SQL
        $stmt = $pdo->prepare("
            SELECT * FROM clients 
            WHERE name LIKE ?
            ORDER BY name
        ");
        
        // El % se agrega en PHP, no en la query
        $stmt->execute(['%' . $searchTerm . '%']);
        $results = $stmt->fetchAll();
        
        // Detectar si intentaron injection (para mostrar que no funciono)
        $injectionPatterns = ["'", 'UNION', 'SELECT', 'OR ', '--', '#', '/*', '<', '>'];
        foreach ($injectionPatterns as $pattern) {
            if (stripos($searchTerm, $pattern) !== false) {
                $attemptedInjection = true;
                break;
            }
        }
        
    } catch (PDOException $e) {
        // SEGURO: No exponer detalles del error
        error_log("Error en busqueda: " . $e->getMessage());
        $error = "Ocurrio un error al realizar la busqueda. Intente nuevamente.";
    }
}

include __DIR__ . '/../shared/header.php';
?>

<main class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1">
                Busqueda Segura
                <span class="badge bg-success">PROTEGIDO</span>
            </h1>
            <p class="text-muted mb-0">
                Busqueda: <strong><?= htmlspecialchars($searchTerm, ENT_QUOTES, 'UTF-8') ?></strong>
            </p>
        </div>
        <a href="index.php" class="btn btn-outline-secondary">Nueva busqueda</a>
    </div>

    <?php if ($attemptedInjection): ?>
    <!-- Alerta educativa: Intento de injection bloqueado -->
    <div class="alert alert-success d-flex align-items-center">
        <div class="fs-3 me-3">BLOQUEADO</div>
        <div>
            <strong>Intento de injection detectado pero NEUTRALIZADO</strong><br>
            <small>
                El payload se busco literalmente en la base de datos. 
                No hay clientes llamados "<code><?= htmlspecialchars($searchTerm) ?></code>".
            </small>
        </div>
    </div>
    
    <!-- Mostrar como se proceso la query -->
    <div class="card mb-4 border-success">
        <div class="card-header bg-success text-white">
            <strong>Como se proceso tu input</strong>
        </div>
        <div class="card-body">
            <p class="mb-2"><strong>Tu input:</strong></p>
            <pre class="bg-light p-2"><code><?= htmlspecialchars($searchTerm) ?></code></pre>
            
            <p class="mb-2"><strong>Query preparada (placeholder):</strong></p>
            <pre class="secure p-2"><code>SELECT * FROM clients WHERE name LIKE ?</code></pre>
            
            <p class="mb-2"><strong>Valor del placeholder (escapado por PDO):</strong></p>
            <pre class="bg-light p-2"><code>%<?= htmlspecialchars($searchTerm) ?>%</code></pre>
            
            <p class="mb-0 text-muted small">
                PDO escapa automaticamente el valor. Los caracteres especiales como <code>'</code> 
                se tratan como texto literal, no como SQL.
            </p>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if (empty($results) && !$error && $searchTerm !== ''): ?>
        <div class="alert alert-info">
            No se encontraron clientes con "<strong><?= htmlspecialchars($searchTerm) ?></strong>"
            <?php if ($attemptedInjection): ?>
            <br><small class="text-muted">
                (El payload de injection se busco literalmente - por eso no hay resultados)
            </small>
            <?php endif; ?>
        </div>
    <?php elseif (!empty($results)): ?>
        
        <div class="card">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <strong>Resultados</strong>
                <span class="badge bg-success"><?= count($results) ?> registros</span>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Nombre</th>
                            <th>RUT</th>
                            <th>Email</th>
                            <th>Telefono</th>
                            <th>Direccion</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($results as $row): ?>
                        <tr>
                            <!-- SEGURO: Todos los valores escapados con htmlspecialchars -->
                            <td><code><?= htmlspecialchars($row['id']) ?></code></td>
                            <td><?= htmlspecialchars($row['name']) ?></td>
                            <td><code><?= htmlspecialchars($row['rut']) ?></code></td>
                            <td><small><?= htmlspecialchars($row['email']) ?></small></td>
                            <td><small><?= htmlspecialchars($row['phone']) ?></small></td>
                            <td><small class="text-muted"><?= htmlspecialchars($row['address']) ?></small></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

    <?php elseif ($searchTerm === ''): ?>
        <div class="alert alert-secondary">
            Ingresa un termino de busqueda para comenzar.
        </div>
    <?php endif; ?>

    <!-- Formulario de busqueda -->
    <div class="card mt-4">
        <div class="card-body">
            <form action="buscar_secure.php" method="GET" class="row g-2 align-items-end">
                <div class="col">
                    <input type="text" 
                           class="form-control" 
                           name="q" 
                           value="<?= htmlspecialchars($searchTerm) ?>"
                           placeholder="Buscar cliente...">
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-success">Buscar (seguro)</button>
                </div>
                <div class="col-auto">
                    <a href="buscar.php?q=<?= urlencode($searchTerm) ?>" class="btn btn-outline-danger">
                        Probar version vulnerable
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Diferencias clave -->
    <div class="card mt-4 border-success">
        <div class="card-header bg-success text-white">
            Diferencias clave con la version vulnerable
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <h6 class="text-danger">buscar.php (VULNERABLE)</h6>
                    <pre class="vulnerable p-2 small"><code>// Concatenacion directa = PELIGRO
$q = "SELECT * FROM clients 
      WHERE name LIKE '%" . $_GET['q'] . "%'";
$conn->query($q);

// Sin escapar = XSS
echo $row['name'];</code></pre>
                </div>
                <div class="col-md-6">
                    <h6 class="text-success">buscar_secure.php (SEGURO)</h6>
                    <pre class="secure p-2 small"><code>// Prepared statement = SEGURO
$stmt = $pdo->prepare("SELECT * FROM clients 
                       WHERE name LIKE ?");
$stmt->execute(['%' . $q . '%']);

// Escapado = Sin XSS
echo htmlspecialchars($row['name']);</code></pre>
                </div>
            </div>
            
            <div class="alert alert-info mb-0 mt-3">
                <strong>Regla de oro:</strong> 
                Nunca concatenes input del usuario directamente en SQL. 
                Siempre usa prepared statements (PDO o mysqli con bind_param).
            </div>
        </div>
    </div>
</main>

<?php 
include __DIR__ . '/../shared/lab_panel.php';
include __DIR__ . '/../shared/footer.php'; 
?>
