<?php
/**
 * OWASP Top 10 Labs 2025 - A03: Software Supply Chain Failures
 * Modulo: Analizar codigo del plugin
 * 
 * Guia al estudiante para encontrar el codigo malicioso en el plugin.
 */

session_start();

$_SESSION['user_id'] = $_SESSION['user_id'] ?? 1;
$_SESSION['username'] = $_SESSION['username'] ?? 'admin';

$pageTitle = 'Analizar Codigo - Nexo';

$labInfo = [
    'id' => 'A03:2025',
    'name' => 'Software Supply Chain Failures',
    'difficulty' => 'Intermedia',
    'description' => '
        <p>Tu mision: encontrar el codigo malicioso en el plugin.</p>
        <p>Pistas:</p>
        <ul>
            <li>El backdoor esta en <code>PdfGenerator.php</code></li>
            <li>Busca metodos relacionados con "telemetry" o "metrics"</li>
            <li>Busca uso de <code>getenv()</code></li>
            <li>Busca cadenas en <code>base64</code></li>
        </ul>
    ',
    'exploit' => '# Comandos utiles:

# Buscar llamadas a getenv
grep -n "getenv" vendor/nexo/pdf-export/src/*.php

# Buscar base64
grep -n "base64" vendor/nexo/pdf-export/src/*.php

# Buscar file_get_contents
grep -n "file_get_contents" vendor/nexo/pdf-export/src/*.php

# Decodificar la URL ofuscada
echo "aHR0cHM6Ly9ldmlsLmF0dGFja2VyLmNvbS9leGZpbD9kPQ==" | base64 -d',
    'prevention' => '// Al actualizar dependencias:

// 1. Revisar el diff
git diff v2.3.0..v2.3.1 -- vendor/nexo/pdf-export/

// 2. Buscar patrones sospechosos
grep -rE "(getenv|base64|file_get_contents|curl)" vendor/

// 3. Revisar metodos nuevos
// Especialmente los que hacen HTTP requests',
    'caseStudy' => [
        'title' => 'xz-utils (2024)',
        'description' => 'El codigo malicioso estaba ofuscado en un archivo de pruebas comprimido. Solo se activaba bajo condiciones muy especificas.'
    ],
    'cwes' => ['CWE-506', 'CWE-1395'],
    'tools' => ['grep', 'base64', 'diff'],
];

// Leer el codigo del plugin
$pluginPath = __DIR__ . '/vendor/nexo/pdf-export/src/PdfGenerator.php';
$pluginCode = file_exists($pluginPath) ? file_get_contents($pluginPath) : null;

// Destacar las lineas maliciosas
$maliciousPatterns = [
    'getenv()' => 'Recopila TODAS las variables de entorno',
    'NEXO_DB_HOST' => 'Extrae credenciales de la BD',
    'NEXO_DB_PASS' => 'Extrae la contraseña de la BD',
    'base64_decode' => 'URL del atacante ofuscada',
    'aHR0cHM6Ly9ldmlsLmF0dGFja2VyLmNvbS9leGZpbD9kPQ==' => 'URL codificada: evil.attacker.com',
    '@file_get_contents' => 'Envia datos al atacante (@ suprime errores)',
];

include __DIR__ . '/../shared/header.php';
?>

<main class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1">Analizar Codigo</h1>
            <p class="text-muted mb-0">Encuentra el backdoor en el plugin</p>
        </div>
        <a href="index.php" class="btn btn-outline-secondary">Volver</a>
    </div>

    <!-- Instrucciones -->
    <div class="alert alert-info">
        <strong>Mision:</strong> El plugin tiene codigo malicioso insertado entre v2.3.0 y v2.3.1. 
        Tu trabajo es encontrarlo analizando el codigo fuente.
    </div>

    <div class="row">
        <div class="col-md-8">
            <!-- Codigo del plugin -->
            <div class="card mb-4">
                <div class="card-header bg-dark text-white d-flex justify-content-between">
                    <span><code>vendor/nexo/pdf-export/src/PdfGenerator.php</code></span>
                    <span class="badge bg-danger">COMPROMETIDO</span>
                </div>
                <div class="card-body p-0">
                    <?php if ($pluginCode): ?>
                    <pre class="m-0 p-3" style="max-height: 600px; overflow-y: auto; font-size: 0.8rem; line-height: 1.4;"><code><?php
                    $lines = explode("\n", $pluginCode);
                    foreach ($lines as $num => $line) {
                        $lineNum = $num + 1;
                        $isMalicious = false;
                        $reason = '';
                        
                        foreach ($maliciousPatterns as $pattern => $desc) {
                            if (stripos($line, $pattern) !== false) {
                                $isMalicious = true;
                                $reason = $desc;
                                break;
                            }
                        }
                        
                        $class = $isMalicious ? 'style="background: #ffe0e0;"' : '';
                        $lineNumFormatted = str_pad($lineNum, 3, ' ', STR_PAD_LEFT);
                        
                        echo "<span $class>";
                        echo "<span style='color: #999; user-select: none;'>$lineNumFormatted | </span>";
                        echo htmlspecialchars($line);
                        if ($isMalicious) {
                            echo " <span style='color: red; font-weight: bold;'>← $reason</span>";
                        }
                        echo "</span>\n";
                    }
                    ?></code></pre>
                    <?php else: ?>
                    <div class="p-3 text-danger">No se pudo leer el archivo del plugin</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <!-- Pistas y guia -->
            <div class="card mb-4 border-warning">
                <div class="card-header bg-warning">
                    Pistas
                </div>
                <div class="card-body small">
                    <p><strong>El codigo malicioso:</strong></p>
                    <ol>
                        <li>Esta en los metodos <code>collectAnonymousMetrics()</code> y <code>sendTelemetryAsync()</code></li>
                        <li>Usa <code>getenv()</code> para extraer variables de entorno</li>
                        <li>La URL del atacante esta en base64</li>
                        <li>Usa <code>@</code> para suprimir errores</li>
                    </ol>
                </div>
            </div>
            
            <!-- Patrones a buscar -->
            <div class="card mb-4">
                <div class="card-header bg-white">
                    <strong>Patrones sospechosos</strong>
                </div>
                <ul class="list-group list-group-flush small">
                    <?php foreach ($maliciousPatterns as $pattern => $desc): ?>
                    <li class="list-group-item d-flex justify-content-between">
                        <code><?= htmlspecialchars($pattern) ?></code>
                        <span class="text-danger"><?= htmlspecialchars($desc) ?></span>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            
            <!-- Decodificar URL -->
            <div class="card border-danger">
                <div class="card-header bg-danger text-white">
                    Decodifica la URL
                </div>
                <div class="card-body">
                    <p class="small">La URL del atacante esta en base64:</p>
                    <pre class="bg-light p-2 small"><code>aHR0cHM6Ly9ldmlsLmF0dGFja2VyLmNvbS9leGZpbD9kPQ==</code></pre>
                    
                    <p class="small mt-3">Decodificala:</p>
                    <pre class="bg-light p-2 small"><code>echo "aHR0cHM6Ly9ldmlsLmF0dGFja2VyLmNvbS9leGZpbD9kPQ==" | base64 -d</code></pre>
                    
                    <button class="btn btn-sm btn-outline-danger w-100 mt-2" 
                            onclick="document.getElementById('decoded').classList.toggle('d-none')">
                        Revelar respuesta
                    </button>
                    <div id="decoded" class="d-none mt-2">
                        <pre class="bg-danger text-white p-2"><code>https://evil.attacker.com/exfil?d=</code></pre>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Resumen del ataque -->
    <div class="card border-dark mt-4">
        <div class="card-header bg-dark text-white">
            Resumen del ataque
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <h6>Flujo del backdoor:</h6>
                    <ol class="small">
                        <li>Usuario llama a <code>generatePdf()</code></li>
                        <li>El metodo llama a <code>initTelemetry()</code></li>
                        <li><code>collectAnonymousMetrics()</code> extrae:
                            <ul>
                                <li>Todas las variables de entorno</li>
                                <li>Credenciales de la BD especificamente</li>
                            </ul>
                        </li>
                        <li><code>sendTelemetryAsync()</code> envia los datos a <code>evil.attacker.com</code></li>
                        <li>El <code>@</code> suprime cualquier error para no alertar</li>
                    </ol>
                </div>
                <div class="col-md-6">
                    <h6>Como se pudo prevenir:</h6>
                    <ul class="small">
                        <li><strong>Verificar checksum</strong> antes de instalar</li>
                        <li><strong>Revisar el diff</strong> entre v2.3.0 y v2.3.1</li>
                        <li><strong>Auditar el codigo</strong> antes de actualizar dependencias criticas</li>
                        <li><strong>Monitorear conexiones</strong> salientes del servidor</li>
                        <li><strong>Usar SCA</strong> (Software Composition Analysis) automatizado</li>
                    </ul>
                </div>
            </div>
            
            <div class="alert alert-warning mb-0 mt-3">
                <strong>Leccion del caso xz-utils:</strong> El atacante paso 2 años ganando confianza 
                antes de insertar el backdoor. La verificacion automatica no lo habria detectado 
                porque el codigo fue "legitimamente" mergeado por un mantenedor. 
                La unica defensa es la revision humana del codigo.
            </div>
        </div>
    </div>
</main>

<?php 
include __DIR__ . '/../shared/lab_panel.php';
include __DIR__ . '/../shared/footer.php'; 
?>
