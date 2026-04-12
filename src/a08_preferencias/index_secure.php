<?php
/**
 * OWASP Top 10 Labs 2025 - A08: Software or Data Integrity Failures
 * Módulo: Preferencias de Cuenta - VERSION SEGURA
 * 
 * PROTECCION: Cookie firmada con HMAC-SHA256
 * Si se modifica el contenido, la firma no coincide y se rechaza
 */

session_start();

$pageTitle = 'Preferencias Seguras - Nexo';

// Clave secreta para HMAC (en producción: variable de entorno)
define('HMAC_SECRET', 'nexo_super_secret_key_2025_do_not_share');

$labInfo = [
    'id' => 'A08:2025',
    'name' => 'Software or Data Integrity Failures (SEGURO)',
    'difficulty' => 'Avanzada',
    'description' => '
        <p>Esta versión firma la cookie con <strong>HMAC-SHA256</strong>.</p>
        <p>Si modificás el contenido, la firma no va a coincidir y se rechaza.</p>
    ',
    'exploit' => '# No hay exploit - esta es la version segura

# Si intentás modificar la cookie:
# 1. El servidor recalcula el HMAC
# 2. No coincide con la firma enviada
# 3. Se rechaza y usa defaults',
    'prevention' => '// VERSION SEGURA (este archivo):

// Al guardar:
$data = serialize($prefs);
$signature = hash_hmac("sha256", $data, SECRET_KEY);
$cookie = base64_encode($data . "|" . $signature);

// Al leer:
list($data, $signature) = explode("|", $decoded);
$expected = hash_hmac("sha256", $data, SECRET_KEY);
if (!hash_equals($expected, $signature)) {
    die("Cookie manipulada!");
}
$prefs = unserialize($data);',
    'caseStudy' => [
        'title' => 'Apache Commons Collections (2015)',
        'description' => 'Si hubieran verificado integridad antes de deserializar, no habría habido RCE.'
    ],
    'cwes' => ['CWE-502', 'CWE-345'],
    'tools' => ['HMAC', 'hash_equals()'],
];

class UserPrefs {
    public $username;
    public $is_admin = false;
    public $theme = 'light';
    public $language = 'es';
    public $notifications = true;
}

/**
 * Firma datos con HMAC-SHA256
 */
function signData(string $data): string {
    return hash_hmac('sha256', $data, HMAC_SECRET);
}

/**
 * Verifica firma HMAC
 */
function verifySignature(string $data, string $signature): bool {
    $expected = hash_hmac('sha256', $data, HMAC_SECRET);
    return hash_equals($expected, $signature); // Timing-safe comparison
}

$prefs = new UserPrefs();
$prefs->username = 'guest';
$cookieRaw = '';
$cookieData = '';
$cookieSignature = '';
$signatureValid = null;
$tamperingDetected = false;

// Leer preferencias de la cookie SEGURA
if (isset($_COOKIE['NEXO_PREFS_SECURE'])) {
    $cookieRaw = $_COOKIE['NEXO_PREFS_SECURE'];
    
    try {
        $decoded = base64_decode($cookieRaw);
        
        // Separar datos y firma
        $parts = explode('|', $decoded, 2);
        
        if (count($parts) === 2) {
            list($cookieData, $cookieSignature) = $parts;
            
            // SEGURO: Verificar firma ANTES de deserializar
            if (verifySignature($cookieData, $cookieSignature)) {
                $signatureValid = true;
                $prefs = unserialize($cookieData);
            } else {
                // Firma inválida - posible tampering
                $signatureValid = false;
                $tamperingDetected = true;
                $prefs = new UserPrefs();
            }
        } else {
            $tamperingDetected = true;
        }
    } catch (Exception $e) {
        $prefs = new UserPrefs();
    }
}

// Guardar preferencias
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_prefs'])) {
    $prefs->username = $_POST['username'] ?? 'guest';
    $prefs->theme = $_POST['theme'] ?? 'light';
    $prefs->language = $_POST['language'] ?? 'es';
    $prefs->notifications = isset($_POST['notifications']);
    $prefs->is_admin = false;
    
    // SEGURO: Serializar Y firmar
    $serialized = serialize($prefs);
    $signature = signData($serialized);
    $encoded = base64_encode($serialized . '|' . $signature);
    
    setcookie('NEXO_PREFS_SECURE', $encoded, time() + 86400 * 30, '/');
    
    header('Location: index_secure.php?saved=1');
    exit;
}

include __DIR__ . '/../shared/header.php';
?>

<main class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1">
                🧑‍💼 Preferencias Seguras
                <span class="badge bg-success">PROTEGIDO</span>
            </h1>
            <p class="text-muted mb-0">
                Usuario: <strong><?= htmlspecialchars($prefs->username) ?></strong>
                <?php if ($prefs->is_admin): ?>
                <span class="badge bg-info ms-2">ADMIN</span>
                <?php endif; ?>
            </p>
        </div>
        <a href="index.php" class="btn btn-outline-danger">← Ver versión vulnerable</a>
    </div>
    
    <?php if (isset($_GET['saved'])): ?>
    <div class="alert alert-success">Preferencias guardadas con firma HMAC.</div>
    <?php endif; ?>
    
    <?php if ($tamperingDetected): ?>
    <div class="alert alert-danger">
        <h5>🚫 Tampering detectado y BLOQUEADO</h5>
        <p class="mb-0">
            La firma HMAC no coincide. La cookie fue modificada o está corrupta.
            Se usaron valores por defecto.
        </p>
    </div>
    <?php endif; ?>
    
    <div class="row">
        <div class="col-lg-6">
            <div class="card mb-4 border-success">
                <div class="card-header bg-success text-white">
                    <strong>Tus preferencias (protegidas)</strong>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <div class="mb-3">
                            <label for="username" class="form-label">Nombre de usuario</label>
                            <input type="text" class="form-control" id="username" name="username" 
                                   value="<?= htmlspecialchars($prefs->username) ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label for="theme" class="form-label">Tema</label>
                            <select class="form-select" id="theme" name="theme">
                                <option value="light" <?= $prefs->theme === 'light' ? 'selected' : '' ?>>Claro</option>
                                <option value="dark" <?= $prefs->theme === 'dark' ? 'selected' : '' ?>>Oscuro</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="language" class="form-label">Idioma</label>
                            <select class="form-select" id="language" name="language">
                                <option value="es" <?= $prefs->language === 'es' ? 'selected' : '' ?>>Español</option>
                                <option value="en" <?= $prefs->language === 'en' ? 'selected' : '' ?>>English</option>
                            </select>
                        </div>
                        
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="notifications" name="notifications"
                                   <?= $prefs->notifications ? 'checked' : '' ?>>
                            <label class="form-check-label" for="notifications">Recibir notificaciones</label>
                        </div>
                        
                        <button type="submit" name="save_prefs" class="btn btn-success w-100">
                            Guardar preferencias
                        </button>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="col-lg-6">
            <!-- Cookie actual con firma -->
            <div class="card border-success mb-4">
                <div class="card-header bg-success text-white">
                    🛡️ Cookie NEXO_PREFS_SECURE (firmada)
                </div>
                <div class="card-body">
                    <p class="small text-muted mb-2">Cookie completa (base64):</p>
                    <textarea class="form-control font-monospace small mb-3" rows="2" readonly><?= htmlspecialchars($cookieRaw) ?></textarea>
                    
                    <p class="small text-muted mb-2">Datos serializados:</p>
                    <textarea class="form-control font-monospace small mb-3" rows="2" readonly><?= htmlspecialchars($cookieData) ?></textarea>
                    
                    <p class="small text-muted mb-2">Firma HMAC-SHA256:</p>
                    <code class="d-block bg-light p-2 rounded small mb-3" style="word-break: break-all;">
                        <?= htmlspecialchars($cookieSignature) ?>
                    </code>
                    
                    <?php if ($signatureValid !== null): ?>
                    <p class="mb-0">
                        <strong>Estado de firma:</strong>
                        <?php if ($signatureValid): ?>
                        <span class="badge bg-success">✓ Válida</span>
                        <?php else: ?>
                        <span class="badge bg-danger">✗ Inválida</span>
                        <?php endif; ?>
                    </p>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Explicación -->
            <div class="card border-info">
                <div class="card-header bg-info text-white">
                    ¿Por qué no funciona el ataque?
                </div>
                <div class="card-body small">
                    <ol class="mb-3">
                        <li>La cookie contiene: <code>datos|firma</code></li>
                        <li>Si modificás los datos, necesitás recalcular la firma</li>
                        <li>Para calcular la firma necesitás la <strong>clave secreta</strong></li>
                        <li>Sin la clave, la firma será inválida</li>
                        <li>El servidor rechaza cookies con firma inválida</li>
                    </ol>
                    
                    <div class="alert alert-warning mb-0">
                        <strong>Probalo:</strong> Copiá la cookie de la versión vulnerable, 
                        modificá is_admin, y pegala aquí. Vas a ver el mensaje de tampering.
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<?php 
include __DIR__ . '/../shared/lab_panel.php';
include __DIR__ . '/../shared/footer.php'; 
?>
