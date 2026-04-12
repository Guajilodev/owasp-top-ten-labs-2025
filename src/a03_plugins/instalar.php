<?php
/**
 * OWASP Top 10 Labs 2025 - A03: Software Supply Chain Failures
 * Modulo: Instalar Plugin
 * 
 * Simula la instalacion del plugin comprometido.
 */

session_start();

$_SESSION['user_id'] = $_SESSION['user_id'] ?? 1;
$_SESSION['username'] = $_SESSION['username'] ?? 'admin';

require_once __DIR__ . '/../config/db.php';

$pageTitle = 'Instalar Plugin - Nexo';

$labInfo = [
    'id' => 'A03:2025',
    'name' => 'Software Supply Chain Failures',
    'difficulty' => 'Intermedia',
    'description' => '
        <p>Estas a punto de instalar un plugin <strong>comprometido</strong>.</p>
        <p>En el mundo real, esto pasaria automaticamente con <code>composer update</code> 
        sin que te des cuenta.</p>
    ',
    'exploit' => '# El plugin se instala sin verificacion
# No se compara checksum
# No se revisa el codigo

# El backdoor ya esta en tu sistema',
    'prevention' => '// Antes de instalar:
$checksum = hash_file("sha256", $package);
if ($checksum !== $expected) {
    throw new Exception("Checksum mismatch!");
}

// Revisar el changelog y diff
// Especialmente para updates menores (2.3.0 -> 2.3.1)',
    'caseStudy' => [
        'title' => 'event-stream (2018)',
        'description' => 'Un atacante gano acceso al paquete npm event-stream y agrego un modulo malicioso que robaba bitcoins. 8 millones de descargas semanales.'
    ],
    'cwes' => ['CWE-494', 'CWE-1104'],
    'tools' => ['sha256sum', 'diff'],
];

$slug = $_GET['slug'] ?? null;
$installed = false;
$error = null;

if ($slug === 'nexo-pdf-export') {
    // Obtener info del plugin
    try {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare("SELECT * FROM plugins WHERE slug = ?");
        $stmt->execute([$slug]);
        $plugin = $stmt->fetch();
        
        if (!$plugin) {
            $error = "Plugin no encontrado";
        }
    } catch (PDOException $e) {
        $error = "Error de base de datos";
    }
    
    // Simular instalacion si viene por POST
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$error) {
        // "Instalar" el plugin (en realidad ya esta en vendor/)
        $_SESSION['plugin_pdf_export_installed'] = true;
        $_SESSION['plugin_pdf_export_installed_at'] = date('Y-m-d H:i:s');
        $installed = true;
    }
} else {
    $error = "Este lab solo funciona con el plugin nexo-pdf-export";
}

include __DIR__ . '/../shared/header.php';
?>

<main class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1">Instalar Plugin</h1>
        </div>
        <a href="index.php" class="btn btn-outline-secondary">Volver a la tienda</a>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    
    <?php elseif ($installed): ?>
        <!-- Instalacion completada -->
        <div class="alert alert-success d-flex align-items-center">
            <div class="fs-2 me-3">Plugin instalado!</div>
            <div>
                <strong><?= htmlspecialchars($plugin['name']) ?> v<?= htmlspecialchars($plugin['version']) ?></strong><br>
                <small>El plugin esta listo para usar.</small>
            </div>
        </div>
        
        <div class="alert alert-danger">
            <strong>Acabas de instalar un plugin comprometido.</strong><br>
            El codigo malicioso ya esta en tu sistema. Cuando lo uses, intentara exfiltrar 
            las credenciales de tu base de datos a un servidor externo.
        </div>
        
        <div class="row">
            <div class="col-md-6">
                <div class="card border-danger">
                    <div class="card-header bg-danger text-white">
                        Paso 1: Usar el plugin
                    </div>
                    <div class="card-body">
                        <p class="small">
                            Genera un PDF para ver el backdoor en accion.
                            El plugin intentara enviar tus credenciales a un servidor externo.
                        </p>
                        <a href="usar.php" class="btn btn-danger w-100">
                            Usar Nexo PDF Export
                        </a>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card border-dark">
                    <div class="card-header bg-dark text-white">
                        Paso 2: Analizar el codigo
                    </div>
                    <div class="card-body">
                        <p class="small">
                            Revisa el codigo fuente del plugin para encontrar el backdoor.
                            Busca en <code>PdfGenerator.php</code>.
                        </p>
                        <a href="analizar.php" class="btn btn-outline-dark w-100">
                            Analizar codigo
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
    <?php elseif ($plugin): ?>
        <!-- Formulario de instalacion -->
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card border-danger">
                    <div class="card-header bg-white">
                        <strong><?= htmlspecialchars($plugin['name']) ?></strong>
                        <span class="badge bg-secondary ms-2">v<?= htmlspecialchars($plugin['version']) ?></span>
                        <?php if ($plugin['is_verified']): ?>
                            <span class="badge bg-success ms-2">Verificado</span>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <p><?= htmlspecialchars($plugin['description']) ?></p>
                        
                        <table class="table table-sm">
                            <tr>
                                <th>Vendor</th>
                                <td><?= htmlspecialchars($plugin['vendor']) ?></td>
                            </tr>
                            <tr>
                                <th>Descargas</th>
                                <td><?= number_format($plugin['downloads']) ?></td>
                            </tr>
                            <tr>
                                <th>Rating</th>
                                <td>
                                    <span class="text-warning">
                                        <?= str_repeat('★', (int)$plugin['rating']) ?>
                                    </span>
                                    (<?= $plugin['rating'] ?>)
                                </td>
                            </tr>
                            <tr>
                                <th>Checksum (SHA256)</th>
                                <td><code class="small"><?= htmlspecialchars($plugin['checksum']) ?></code></td>
                            </tr>
                        </table>
                        
                        <div class="alert alert-warning">
                            <strong>En el mundo real, deberias:</strong>
                            <ul class="mb-0 small">
                                <li>Verificar el checksum contra el sitio oficial</li>
                                <li>Revisar el changelog de v2.3.0 a v2.3.1</li>
                                <li>Ejecutar <code>composer audit</code></li>
                                <li>Leer el codigo antes de instalar</li>
                            </ul>
                        </div>
                        
                        <form method="POST" class="mt-4">
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" id="acceptRisk" required>
                                <label class="form-check-label text-danger" for="acceptRisk">
                                    Entiendo que este plugin esta <strong>comprometido</strong> y es solo para fines educativos
                                </label>
                            </div>
                            <button type="submit" class="btn btn-danger w-100">
                                Instalar <?= htmlspecialchars($plugin['name']) ?>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</main>

<?php 
include __DIR__ . '/../shared/lab_panel.php';
include __DIR__ . '/../shared/footer.php'; 
?>
