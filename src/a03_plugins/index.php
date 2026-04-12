<?php
/**
 * OWASP Top 10 Labs 2025 - A03: Software Supply Chain Failures
 * Modulo: Tienda de Plugins
 * 
 * Este archivo muestra la tienda de plugins de Nexo.
 * El plugin "Nexo PDF Export v2.3.1" fue comprometido entre v2.3.0 y v2.3.1.
 * El atacante agrego codigo malicioso que exfiltra variables de entorno.
 */

session_start();

$_SESSION['user_id'] = $_SESSION['user_id'] ?? 1; // Admin para este lab
$_SESSION['username'] = $_SESSION['username'] ?? 'admin';

require_once __DIR__ . '/../config/db.php';

$pageTitle = 'Tienda de Plugins - Nexo';

// Info del lab para el panel lateral
$labInfo = [
    'id' => 'A03:2025',
    'name' => 'Software Supply Chain Failures',
    'difficulty' => 'Intermedia',
    'description' => '
        <p>La tienda de plugins muestra paquetes "verificados". El plugin <strong>Nexo PDF Export v2.3.1</strong> 
        fue comprometido entre la version 2.3.0 (limpia) y 2.3.1.</p>
        <p>El atacante agrego <strong>5 lineas de codigo malicioso</strong> en <code>generatePdf()</code> que 
        exfiltran las variables de entorno del servidor.</p>
        <p class="mb-0">El lab tiene dos partes: <strong>usar</strong> el plugin y <strong>analizar</strong> su codigo.</p>
    ',
    'exploit' => '# Parte 1: Usar el plugin
# Al generar un PDF, el backdoor intenta enviar
# las credenciales de la BD a un servidor externo

# Parte 2: Analizar el codigo
# Abre vendor/nexo/pdf-export/src/PdfGenerator.php
# Busca el metodo collectAnonymousMetrics()
# Busca la cadena base64: aHR0cHM6Ly9ldmlsLmF0dGFja2VyLmNvbS9leGZpbD9kPQ==
# Decodificala: echo "aHR0cHM6Ly9ldmlsLmF0dGFja2VyLmNvbS9leGZpbD9kPQ==" | base64 -d',
    'prevention' => '// ANTES de instalar un plugin:

// 1. Verificar checksum
$expected = "a3f2c1d4e5..."; // Del sitio oficial
$actual = hash_file("sha256", $package);
if ($expected !== $actual) die("Checksum mismatch!");

// 2. Revisar diff entre versiones
git diff v2.3.0..v2.3.1

// 3. Auditar dependencias
composer audit

// 4. Revisar el codigo antes de actualizar
// Especialmente en metodos que hacen HTTP requests',
    'caseStudy' => [
        'title' => 'xz-utils backdoor (2024)',
        'description' => 'Un atacante paso DOS AÑOS ganando confianza en el proyecto open source para insertar un backdoor. Fue descubierto por accidente porque SSH tardaba 500ms de mas.'
    ],
    'cwes' => ['CWE-1395', 'CWE-1104', 'CWE-494'],
    'tools' => ['Lectura de codigo', 'base64 -d', 'diff', 'composer audit'],
];

// Obtener plugins de la BD
try {
    $pdo = getDbConnection();
    $stmt = $pdo->query("SELECT * FROM plugins ORDER BY downloads DESC");
    $plugins = $stmt->fetchAll();
} catch (PDOException $e) {
    $plugins = [];
    $error = "Error al cargar plugins: " . $e->getMessage();
}

// Verificar si el plugin PDF Export esta "instalado"
$pdfExportInstalled = isset($_SESSION['plugin_pdf_export_installed']);

include __DIR__ . '/../shared/header.php';
?>

<main class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1">Tienda de Plugins</h1>
            <p class="text-muted mb-0">
                Extiende Nexo con plugins verificados por la comunidad
            </p>
        </div>
        <a href="/" class="btn btn-outline-secondary">Volver al inicio</a>
    </div>

    <!-- Alerta del lab -->
    <div class="alert alert-warning">
        <strong>Contexto del lab:</strong> 
        El plugin <strong>Nexo PDF Export v2.3.1</strong> fue comprometido. 
        Tu mision es usarlo para ver el efecto y luego <strong>analizar el codigo</strong> para encontrar el backdoor.
    </div>

    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- Grid de plugins -->
    <div class="row row-cols-1 row-cols-md-2 g-4 mb-4">
        <?php foreach ($plugins as $plugin): ?>
        <?php 
            $isCompromised = ($plugin['slug'] === 'nexo-pdf-export');
            $isInstalled = ($plugin['slug'] === 'nexo-pdf-export' && $pdfExportInstalled);
        ?>
        <div class="col">
            <div class="card h-100 <?= $isCompromised ? 'border-danger' : '' ?>">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <div>
                        <strong><?= htmlspecialchars($plugin['name']) ?></strong>
                        <span class="badge bg-secondary ms-2">v<?= htmlspecialchars($plugin['version']) ?></span>
                    </div>
                    <?php if ($plugin['is_verified']): ?>
                        <span class="badge bg-success">Verificado</span>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <p class="card-text small">
                        <?= htmlspecialchars($plugin['description']) ?>
                    </p>
                    
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <span class="text-warning">
                                <?= str_repeat('★', (int)$plugin['rating']) ?><?= str_repeat('☆', 5 - (int)$plugin['rating']) ?>
                            </span>
                            <small class="text-muted ms-1">(<?= number_format($plugin['rating'], 1) ?>)</small>
                        </div>
                        <small class="text-muted">
                            <?= number_format($plugin['downloads']) ?> descargas
                        </small>
                    </div>
                    
                    <p class="small text-muted mb-0">
                        <strong>Vendor:</strong> <?= htmlspecialchars($plugin['vendor']) ?>
                    </p>
                    
                    <?php if ($isCompromised): ?>
                    <div class="alert alert-danger mt-3 mb-0 py-2">
                        <small>
                            <strong>COMPROMETIDO</strong> - Este plugin tiene un backdoor
                        </small>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="card-footer bg-transparent">
                    <?php if ($isCompromised): ?>
                        <?php if ($isInstalled): ?>
                            <div class="btn-group w-100">
                                <a href="usar.php" class="btn btn-danger">
                                    Usar plugin
                                </a>
                                <a href="analizar.php" class="btn btn-outline-dark">
                                    Analizar codigo
                                </a>
                            </div>
                        <?php else: ?>
                            <a href="instalar.php?slug=<?= $plugin['slug'] ?>" class="btn btn-primary w-100">
                                Instalar
                            </a>
                        <?php endif; ?>
                    <?php else: ?>
                        <button class="btn btn-outline-secondary w-100" disabled>
                            Solo demo (no funcional)
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Informacion sobre supply chain -->
    <div class="card border-dark">
        <div class="card-header bg-dark text-white">
            ¿Que es Software Supply Chain Attack?
        </div>
        <div class="card-body">
            <p>Un ataque a la cadena de suministro de software ocurre cuando un atacante compromete 
            una dependencia que tu aplicacion usa. El codigo malicioso se ejecuta automaticamente 
            cuando instalas o actualizas el paquete.</p>
            
            <h6>Vectores comunes:</h6>
            <ul class="small">
                <li><strong>Typosquatting:</strong> Paquetes con nombres similares a los populares (lodash vs 1odash)</li>
                <li><strong>Account takeover:</strong> Robo de credenciales del mantenedor</li>
                <li><strong>Insider threat:</strong> Mantenedor que inserta codigo malicioso (caso xz-utils)</li>
                <li><strong>Build system compromise:</strong> CI/CD comprometido que inyecta codigo</li>
            </ul>
            
            <h6>Como detectarlo:</h6>
            <ul class="small mb-0">
                <li>Verificar checksums antes de instalar</li>
                <li>Revisar el diff entre versiones antes de actualizar</li>
                <li>Usar <code>composer audit</code> o <code>npm audit</code></li>
                <li>Monitorear conexiones de red salientes sospechosas</li>
            </ul>
        </div>
    </div>
</main>

<?php 
include __DIR__ . '/../shared/lab_panel.php';
include __DIR__ . '/../shared/footer.php'; 
?>
