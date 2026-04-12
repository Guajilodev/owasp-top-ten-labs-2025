<?php
/**
 * OWASP Top 10 Labs 2025 - A07: Authentication Failures
 * Dashboard post-login - muestra session ID vulnerable
 */

session_start();
require_once __DIR__ . '/../config/db.php';

// Verificar login (puede ser por sesión normal o por cookie vulnerable)
$loggedIn = false;
$sessionHijacked = false;

if (isset($_SESSION['user_logged_in']) && $_SESSION['user_logged_in']) {
    $loggedIn = true;
} elseif (isset($_COOKIE['NEXO_SESS'])) {
    // ⚠️ VULNERABLE: Aceptamos login por cookie sin verificación
    $sessId = intval($_COOKIE['NEXO_SESS']);
    
    try {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare("SELECT id, username, role FROM users WHERE session_id = ?");
        $stmt->execute([$sessId]);
        $user = $stmt->fetch();
        
        if ($user) {
            $loggedIn = true;
            $sessionHijacked = true;
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['vulnerable_session_id'] = $sessId;
        }
    } catch (PDOException $e) {
        // Ignorar
    }
}

if (!$loggedIn) {
    header('Location: index.php');
    exit;
}

$pageTitle = 'Dashboard - Nexo';

// Info del lab
$labInfo = [
    'id' => 'A07:2025',
    'name' => 'Authentication Failures',
    'difficulty' => 'Intermedia',
    'description' => '
        <p>Estás logueado. Mirá tu <strong>Session ID</strong> en la cookie <code>NEXO_SESS</code>.</p>
        <p>Es un número secuencial. Probá cambiar la cookie a otros números para hijackear otras sesiones.</p>
    ',
    'exploit' => '# Tu session ID actual: ' . ($_SESSION['vulnerable_session_id'] ?? '?') . '

# Probá estos en las DevTools:
document.cookie = "NEXO_SESS=1001"
document.cookie = "NEXO_SESS=1002"
document.cookie = "NEXO_SESS=1003"

# Recargá la página después de cada cambio',
    'prevention' => '// Después de login exitoso:
session_regenerate_id(true);

// PHP genera un ID como:
// "a7b3c2d1e4f5..."  (128+ bits random)
// No: "1001", "1002", "1003"...',
    'caseStudy' => [
        'title' => 'Rockstar Games (2022)',
        'description' => 'Credential stuffing + session hijacking permitieron acceso masivo a cuentas.'
    ],
    'cwes' => ['CWE-307', 'CWE-384'],
    'tools' => ['DevTools', 'curl'],
];

include __DIR__ . '/../shared/header.php';
?>

<main class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1">🔐 Dashboard de Usuario</h1>
            <p class="text-muted mb-0">
                Bienvenido, <strong><?= htmlspecialchars($_SESSION['username']) ?></strong>
                <span class="badge bg-<?= $_SESSION['role'] === 'admin' ? 'danger' : 'secondary' ?> ms-2">
                    <?= $_SESSION['role'] ?>
                </span>
            </p>
        </div>
        <a href="logout.php" class="btn btn-outline-danger btn-sm">Cerrar sesión</a>
    </div>
    
    <?php if ($sessionHijacked): ?>
    <div class="alert alert-danger">
        <h5>🚨 ¡Session Hijacking exitoso!</h5>
        <p class="mb-0">
            Accediste usando la cookie <code>NEXO_SESS=<?= $_SESSION['vulnerable_session_id'] ?></code> 
            sin necesidad de usuario/password. Eso es session hijacking.
        </p>
    </div>
    <?php endif; ?>
    
    <div class="row">
        <div class="col-lg-6">
            <!-- Info de sesión vulnerable -->
            <div class="card border-danger mb-4">
                <div class="card-header bg-danger text-white">
                    🔴 Tu Session ID (vulnerable)
                </div>
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <span class="text-muted">Cookie NEXO_SESS:</span>
                        <code class="fs-4"><?= $_SESSION['vulnerable_session_id'] ?? $_COOKIE['NEXO_SESS'] ?? 'N/A' ?></code>
                    </div>
                    
                    <p class="small text-muted mb-2">
                        Este ID es un número secuencial. Otros usuarios tienen IDs cercanos:
                    </p>
                    
                    <div class="d-flex gap-2 flex-wrap">
                        <?php 
                        $currentId = $_SESSION['vulnerable_session_id'] ?? 1000;
                        for ($i = $currentId - 3; $i <= $currentId + 3; $i++): 
                            if ($i < 1001) continue;
                        ?>
                        <span class="badge <?= $i == $currentId ? 'bg-danger' : 'bg-secondary' ?>">
                            <?= $i ?>
                        </span>
                        <?php endfor; ?>
                    </div>
                </div>
                <div class="card-footer small">
                    <strong>Probá:</strong> Abrí DevTools → Application → Cookies → 
                    Cambiá NEXO_SESS a otro número → Recargá
                </div>
            </div>
            
            <!-- Sesiones activas (para hijackear) -->
            <div class="card border-warning">
                <div class="card-header bg-warning">
                    👥 Sesiones activas conocidas
                </div>
                <div class="card-body">
                    <?php
                    try {
                        $pdo = getDbConnection();
                        $stmt = $pdo->query("SELECT username, role, session_id FROM users WHERE session_id IS NOT NULL ORDER BY session_id");
                        $sessions = $stmt->fetchAll();
                    } catch (Exception $e) {
                        $sessions = [];
                    }
                    ?>
                    <table class="table table-sm small mb-0">
                        <thead>
                            <tr>
                                <th>Usuario</th>
                                <th>Rol</th>
                                <th>Session ID</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($sessions as $sess): ?>
                            <tr class="<?= $sess['session_id'] == ($_SESSION['vulnerable_session_id'] ?? 0) ? 'table-active' : '' ?>">
                                <td><?= htmlspecialchars($sess['username']) ?></td>
                                <td><span class="badge bg-secondary"><?= $sess['role'] ?></span></td>
                                <td><code><?= $sess['session_id'] ?></code></td>
                                <td>
                                    <?php if ($sess['session_id'] != ($_SESSION['vulnerable_session_id'] ?? 0)): ?>
                                    <button class="btn btn-xs btn-outline-danger hijack-btn" 
                                            data-sessid="<?= $sess['session_id'] ?>">
                                        Hijack
                                    </button>
                                    <?php else: ?>
                                    <span class="text-success">✓ Actual</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <div class="col-lg-6">
            <!-- Cómo debería ser -->
            <div class="card border-success mb-4">
                <div class="card-header bg-success text-white">
                    🛡️ Session ID seguro (cómo debería ser)
                </div>
                <div class="card-body">
                    <p>Un session ID seguro se ve así:</p>
                    <code class="d-block bg-light p-2 rounded mb-3" style="word-break: break-all;">
                        <?= bin2hex(random_bytes(32)) ?>
                    </code>
                    
                    <ul class="small mb-0">
                        <li>128+ bits de entropía</li>
                        <li>Generado con <code>random_bytes()</code></li>
                        <li>Imposible de adivinar</li>
                        <li>Se regenera después de login (<code>session_regenerate_id(true)</code>)</li>
                    </ul>
                </div>
            </div>
            
            <!-- Código fix -->
            <div class="card">
                <div class="card-header bg-white">
                    💻 Fix del código
                </div>
                <div class="card-body">
                    <p class="small text-muted">Después de login exitoso:</p>
                    <pre class="bg-light p-2 rounded small mb-0"><code>// VULNERABLE (actual):
$_SESSION['sess_id'] = $user['session_id'] + 1;
setcookie('NEXO_SESS', $newId, ...);

// SEGURO:
session_regenerate_id(true);
// PHP maneja todo automáticamente
// El ID es random y httpOnly</code></pre>
                </div>
            </div>
        </div>
    </div>
</main>

<script>
// Helper para hijack buttons
document.querySelectorAll('.hijack-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        const sessId = this.dataset.sessid;
        document.cookie = `NEXO_SESS=${sessId}; path=/`;
        alert(`Cookie cambiada a NEXO_SESS=${sessId}. Recargando...`);
        location.reload();
    });
});
</script>

<?php 
include __DIR__ . '/../shared/lab_panel.php';
include __DIR__ . '/../shared/footer.php'; 
?>
