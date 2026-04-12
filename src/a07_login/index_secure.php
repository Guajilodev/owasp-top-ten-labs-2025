<?php
/**
 * OWASP Top 10 Labs 2025 - A07: Authentication Failures
 * Módulo: Login - VERSION SEGURA
 * 
 * PROTECCIONES:
 * 1. Rate limiting — bloquea después de 5 intentos
 * 2. Session ID aleatorio — usa session_regenerate_id()
 */

session_start();
require_once __DIR__ . '/../config/db.php';

$pageTitle = 'Login Seguro - Nexo';

$labInfo = [
    'id' => 'A07:2025',
    'name' => 'Authentication Failures (SEGURO)',
    'difficulty' => 'Intermedia',
    'description' => '
        <p>Esta versión implementa:</p>
        <ol>
            <li><strong>Rate limiting:</strong> Bloqueo temporal después de 5 intentos</li>
            <li><strong>Session ID seguro:</strong> Generado con session_regenerate_id()</li>
        </ol>
    ',
    'exploit' => '# No hay exploit - esta es la version segura

# Rate limiting bloquea brute force
# Session ID es aleatorio (128 bits)

# Probá hacer 6 intentos fallidos...',
    'prevention' => '// VERSION SEGURA (este archivo):

// 1. Rate limiting
if ($failedAttempts >= 5) {
    $lockoutTime = 30; // segundos
    die("Cuenta bloqueada temporalmente");
}

// 2. Session ID seguro
session_regenerate_id(true);
// PHP genera un ID criptográficamente seguro',
    'caseStudy' => [
        'title' => 'Rockstar Games (2022)',
        'description' => 'Si hubieran tenido rate limiting, el ataque se habría detenido en minutos.'
    ],
    'cwes' => ['CWE-307', 'CWE-384'],
    'tools' => ['Hydra (no funcionará)', 'session_regenerate_id()'],
];

$error = null;
$blocked = false;
$remainingTime = 0;

// Rate limiting storage (en producción usar Redis/DB)
$rateLimitFile = sys_get_temp_dir() . '/nexo_ratelimit_' . md5($_SERVER['REMOTE_ADDR'] ?? 'cli');

// Verificar bloqueo
if (file_exists($rateLimitFile)) {
    $data = json_decode(file_get_contents($rateLimitFile), true);
    if ($data && isset($data['blocked_until']) && time() < $data['blocked_until']) {
        $blocked = true;
        $remainingTime = $data['blocked_until'] - time();
    } elseif ($data && isset($data['blocked_until']) && time() >= $data['blocked_until']) {
        // Reset después del bloqueo
        unlink($rateLimitFile);
    }
}

// Procesar login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$blocked) {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    // Cargar datos de rate limiting
    $data = file_exists($rateLimitFile) ? json_decode(file_get_contents($rateLimitFile), true) : ['attempts' => 0];
    
    try {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare("SELECT id, username, password_md5, role FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        
        if ($user && $user['password_md5'] === md5($password)) {
            // Login exitoso - limpiar rate limiting
            if (file_exists($rateLimitFile)) {
                unlink($rateLimitFile);
            }
            
            // SEGURO: Regenerar session ID
            session_regenerate_id(true);
            
            $_SESSION['user_logged_in'] = true;
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['secure_login'] = true;
            
            header('Location: dashboard.php');
            exit;
        } else {
            // Incrementar contador de intentos fallidos
            $data['attempts'] = ($data['attempts'] ?? 0) + 1;
            
            // SEGURO: Bloquear después de 5 intentos
            if ($data['attempts'] >= 5) {
                $data['blocked_until'] = time() + 30; // 30 segundos
                $blocked = true;
                $remainingTime = 30;
            }
            
            file_put_contents($rateLimitFile, json_encode($data));
            
            $error = "Credenciales incorrectas. Intento " . $data['attempts'] . " de 5.";
        }
    } catch (PDOException $e) {
        $error = "Error de base de datos";
    }
}

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
                <h1 class="h3">
                    🔐 Login Seguro
                    <span class="badge bg-success">PROTEGIDO</span>
                </h1>
                <p class="text-muted">Con rate limiting y session ID seguro</p>
            </div>
            
            <?php if ($blocked): ?>
            <div class="alert alert-danger">
                <strong>🚫 Cuenta bloqueada temporalmente</strong><br>
                Demasiados intentos fallidos. Esperá <strong><?= $remainingTime ?> segundos</strong>.
                <div class="progress mt-2">
                    <div class="progress-bar bg-danger" style="width: <?= (30 - $remainingTime) / 30 * 100 ?>%"></div>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="card shadow-sm border-success">
                <div class="card-body p-4">
                    
                    <?php if ($error && !$blocked): ?>
                    <div class="alert alert-warning"><?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>
                    
                    <form method="POST" <?= $blocked ? 'style="opacity: 0.5; pointer-events: none;"' : '' ?>>
                        <div class="mb-3">
                            <label for="username" class="form-label">Usuario</label>
                            <input type="text" class="form-control" id="username" name="username" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="password" class="form-label">Contraseña</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>
                        
                        <button type="submit" class="btn btn-success w-100" <?= $blocked ? 'disabled' : '' ?>>
                            Iniciar sesión
                        </button>
                    </form>
                </div>
            </div>
            
            <div class="text-center mt-3">
                <a href="index.php" class="btn btn-outline-danger btn-sm">← Ver versión vulnerable</a>
            </div>
            
            <!-- Info de protecciones -->
            <div class="card mt-4 border-success">
                <div class="card-header bg-success text-white">
                    🛡️ Protecciones implementadas
                </div>
                <div class="card-body small">
                    <h6>1. Rate Limiting</h6>
                    <ul>
                        <li>Máximo 5 intentos fallidos</li>
                        <li>Bloqueo de 30 segundos</li>
                        <li>Basado en IP</li>
                    </ul>
                    
                    <h6>2. Session ID Seguro</h6>
                    <ul class="mb-0">
                        <li><code>session_regenerate_id(true)</code></li>
                        <li>ID criptográficamente aleatorio</li>
                        <li>No predecible como 1001, 1002...</li>
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
