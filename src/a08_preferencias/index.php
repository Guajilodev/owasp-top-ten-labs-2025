<?php
/**
 * OWASP Top 10 Labs 2025 - A08: Software or Data Integrity Failures
 * Módulo: Preferencias de Cuenta
 * 
 * ⚠️ VULNERABLE: Cookie con objeto PHP serializado sin firma HMAC
 * El usuario puede modificar is_admin y escalar privilegios
 */

session_start();

$pageTitle = 'Preferencias - Nexo';

// Info del lab
$labInfo = [
    'id' => 'A08:2025',
    'name' => 'Software or Data Integrity Failures',
    'difficulty' => 'Avanzada',
    'description' => '
        <p>Las preferencias se guardan en una cookie con un <strong>objeto PHP serializado</strong>.</p>
        <p>La cookie NO tiene firma (HMAC). Podés decodificarla, modificar <code>is_admin</code>, y re-encodearla.</p>
    ',
    'exploit' => '# 1. Decodificá la cookie actual (base64):
echo "BASE64_AQUI" | base64 -d

# 2. Vas a ver algo como:
# O:4:"User":3:{s:8:"username";s:5:"alice";s:8:"is_admin";b:0;s:5:"theme";s:5:"light";}

# 3. Cambiá is_admin de b:0 a b:1:
# ...s:8:"is_admin";b:1;...

# 4. Re-encodá en base64 y reemplazá la cookie',
    'prevention' => '// VULNERABLE (actual):
$prefs = unserialize(base64_decode($cookie));

// SEGURO - firmar con HMAC:
$data = base64_decode($cookie);
$signature = hash_hmac("sha256", $data, SECRET_KEY);
// Verificar firma antes de deserializar

// MEJOR AÚN - no serializar en cookies:
// Guardar prefs en la sesión del servidor',
    'caseStudy' => [
        'title' => 'Apache Commons Collections (2015)',
        'description' => 'Deserialización insegura permitió RCE en Jenkins, WebLogic y otros sistemas enterprise.'
    ],
    'cwes' => ['CWE-502', 'CWE-345'],
    'tools' => ['base64', 'Burp Suite', 'DevTools'],
    'secureVersion' => 'index_secure.php',
];

// Clase de preferencias (deliberadamente simple)
class UserPrefs {
    public $username;
    public $is_admin = false;
    public $theme = 'light';
    public $language = 'es';
    public $notifications = true;
}

// Leer preferencias de la cookie
$prefs = new UserPrefs();
$prefs->username = 'guest';
$cookieRaw = '';
$cookieDecoded = '';
$isEscalated = false;

if (isset($_COOKIE['NEXO_PREFS'])) {
    $cookieRaw = $_COOKIE['NEXO_PREFS'];
    
    try {
        // ⚠️ VULNERABLE: Deserialización sin verificación de integridad
        $decoded = base64_decode($cookieRaw);
        $cookieDecoded = $decoded;
        $prefs = unserialize($decoded);
        
        // Verificar si escaló privilegios
        if ($prefs->is_admin === true && $prefs->username !== 'admin') {
            $isEscalated = true;
        }
    } catch (Exception $e) {
        // Cookie inválida, usar defaults
        $prefs = new UserPrefs();
    }
}

// Guardar preferencias si se envía el form
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_prefs'])) {
    $prefs->username = $_POST['username'] ?? 'guest';
    $prefs->theme = $_POST['theme'] ?? 'light';
    $prefs->language = $_POST['language'] ?? 'es';
    $prefs->notifications = isset($_POST['notifications']);
    $prefs->is_admin = false; // Siempre false desde el form (pero la cookie se puede manipular)
    
    // ⚠️ VULNERABLE: Serializar sin firmar
    $serialized = serialize($prefs);
    $encoded = base64_encode($serialized);
    
    setcookie('NEXO_PREFS', $encoded, time() + 86400 * 30, '/');
    $cookieRaw = $encoded;
    $cookieDecoded = $serialized;
    
    header('Location: index.php?saved=1');
    exit;
}

include __DIR__ . '/../shared/header.php';
?>

<main class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1">🧑‍💼 Preferencias de Cuenta</h1>
            <p class="text-muted mb-0">
                Usuario: <strong><?= htmlspecialchars($prefs->username) ?></strong>
                <?php if ($prefs->is_admin): ?>
                <span class="badge bg-danger ms-2">ADMIN</span>
                <?php endif; ?>
            </p>
        </div>
        <a href="/" class="btn btn-outline-secondary">← Volver</a>
    </div>
    
    <?php if (isset($_GET['saved'])): ?>
    <div class="alert alert-success">Preferencias guardadas. Revisá la cookie.</div>
    <?php endif; ?>
    
    <?php if ($isEscalated): ?>
    <div class="alert alert-danger">
        <h5>🚨 ¡Escalación de privilegios exitosa!</h5>
        <p class="mb-0">
            Modificaste la cookie para cambiar <code>is_admin</code> de <code>false</code> a <code>true</code>.
            Ahora el sistema te ve como admin aunque no lo sos.
        </p>
    </div>
    <?php endif; ?>
    
    <div class="row">
        <div class="col-lg-6">
            <!-- Formulario de preferencias -->
            <div class="card mb-4">
                <div class="card-header bg-white">
                    <strong>Tus preferencias</strong>
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
                        
                        <!-- Campo is_admin NO está en el form - hay que manipular la cookie -->
                        
                        <button type="submit" name="save_prefs" class="btn btn-primary w-100">
                            Guardar preferencias
                        </button>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="col-lg-6">
            <!-- Cookie actual -->
            <div class="card border-danger mb-4">
                <div class="card-header bg-danger text-white">
                    🔴 Cookie NEXO_PREFS (vulnerable)
                </div>
                <div class="card-body">
                    <p class="small text-muted mb-2">Base64:</p>
                    <textarea class="form-control font-monospace small mb-3" rows="2" readonly 
                              onclick="this.select()"><?= htmlspecialchars($cookieRaw) ?></textarea>
                    
                    <p class="small text-muted mb-2">Decodificado (objeto serializado):</p>
                    <textarea class="form-control font-monospace small mb-3" rows="3" readonly 
                              onclick="this.select()"><?= htmlspecialchars($cookieDecoded) ?></textarea>
                    
                    <p class="small mb-0">
                        <strong>is_admin</strong> actual: 
                        <code class="<?= $prefs->is_admin ? 'text-danger' : '' ?>">
                            <?= $prefs->is_admin ? 'true (b:1)' : 'false (b:0)' ?>
                        </code>
                    </p>
                </div>
            </div>
            
            <!-- Instrucciones de explotación -->
            <div class="card border-warning">
                <div class="card-header bg-warning">
                    🔓 Cómo escalar privilegios
                </div>
                <div class="card-body small">
                    <ol class="mb-3">
                        <li>Copiá el valor de la cookie (Base64)</li>
                        <li>Decodificá: <code>echo "..." | base64 -d</code></li>
                        <li>Buscá <code>is_admin";b:0</code></li>
                        <li>Cambialo a <code>is_admin";b:1</code></li>
                        <li>Re-encodá: <code>echo "..." | base64</code></li>
                        <li>Reemplazá la cookie en DevTools</li>
                        <li>Recargá la página</li>
                    </ol>
                    
                    <p class="mb-2"><strong>Ejemplo de payload escalado:</strong></p>
                    <?php
                    $escalatedPrefs = clone $prefs;
                    $escalatedPrefs->is_admin = true;
                    $escalatedPayload = base64_encode(serialize($escalatedPrefs));
                    ?>
                    <code class="d-block bg-light p-2 rounded small" style="word-break: break-all;">
                        <?= $escalatedPayload ?>
                    </code>
                    
                    <button class="btn btn-sm btn-outline-danger mt-2 w-100" onclick="
                        document.cookie = 'NEXO_PREFS=<?= $escalatedPayload ?>; path=/';
                        location.reload();
                    ">
                        🚀 Aplicar payload escalado
                    </button>
                </div>
            </div>
        </div>
    </div>
</main>

<?php 
include __DIR__ . '/../shared/lab_panel.php';
include __DIR__ . '/../shared/footer.php'; 
?>
