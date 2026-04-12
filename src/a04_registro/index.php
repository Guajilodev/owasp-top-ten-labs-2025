<?php
/**
 * OWASP Top 10 Labs 2025 - A04: Cryptographic Failures
 * Módulo: Registro de Cuenta
 * 
 * ⚠️ VULNERABLE: Passwords almacenadas en MD5 sin salt
 */

session_start();
require_once __DIR__ . '/../config/db.php';

$pageTitle = 'Registro - Nexo';

// Info del lab
$labInfo = [
    'id' => 'A04:2025',
    'name' => 'Cryptographic Failures',
    'difficulty' => 'Básica',
    'description' => '
        <p>El sistema almacena contraseñas con <strong>MD5 sin salt</strong>.</p>
        <p>MD5 es rápido y reversible con rainbow tables. Sin salt, dos usuarios con la misma password tienen el mismo hash.</p>
        <p>Además, "olvidé mi contraseña" envía la password actual en texto plano.</p>
    ',
    'exploit' => '# 1. Obtené los hashes (ver pestaña "Ver Hashes")
# 2. Usá CrackStation para crackearlos:
#    https://crackstation.net/

# Hashes de ejemplo:
# 0192023a7bbd73250516f069df18b500 -> admin123
# 482c811da5d5b4bc6d497ffa98491e38 -> password123
# 5ebe2294ecd0e0f08eab7690d2a6ee69 -> secret',
    'prevention' => '// VULNERABLE (actual):
$hash = md5($password);

// SEGURO:
$hash = password_hash($password, PASSWORD_BCRYPT);

// Para verificar:
if (password_verify($input, $hash)) {
    // Login OK
}',
    'caseStudy' => [
        'title' => 'LinkedIn (2012)',
        'description' => '117 millones de passwords expuestas con SHA-1 sin salt. Crackeadas en horas. Vendidas en el mercado negro por años.'
    ],
    'cwes' => ['CWE-916', 'CWE-327'],
    'tools' => ['CrackStation', 'Hashcat', 'John the Ripper'],
    'secureVersion' => 'index_secure.php',
];

$success = null;
$error = null;

// Procesar registro
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($email) || empty($password)) {
        $error = "Todos los campos son obligatorios";
    } elseif (strlen($password) < 4) {
        $error = "La contraseña debe tener al menos 4 caracteres";
    } else {
        try {
            $pdo = getDbConnection();
            
            // Verificar si el usuario ya existe
            $check = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
            $check->execute([$username, $email]);
            
            if ($check->fetch()) {
                $error = "El usuario o email ya existe";
            } else {
                // ⚠️ VULNERABLE: MD5 sin salt
                $hash = md5($password);
                
                $stmt = $pdo->prepare("
                    INSERT INTO users (username, email, password_md5, role) 
                    VALUES (?, ?, ?, 'user')
                ");
                $stmt->execute([$username, $email, $hash]);
                
                $success = "Cuenta creada exitosamente. Tu password fue almacenada como: <code>$hash</code> (MD5)";
            }
        } catch (PDOException $e) {
            $error = "Error al crear cuenta: " . $e->getMessage();
        }
    }
}

include __DIR__ . '/../shared/header.php';
?>

<main class="container py-4">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h3 mb-1">👤 Registro de Cuenta</h1>
                    <p class="text-muted mb-0">Crea tu cuenta en Nexo</p>
                </div>
                <a href="/" class="btn btn-outline-secondary">← Volver</a>
            </div>
            
            <div class="row">
                <div class="col-md-6">
                    <!-- Formulario de registro -->
                    <div class="card mb-4">
                        <div class="card-header bg-white">
                            <strong>Crear cuenta</strong>
                        </div>
                        <div class="card-body">
                            
                            <?php if ($success): ?>
                            <div class="alert alert-success"><?= $success ?></div>
                            <?php endif; ?>
                            
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
                                    <label for="email" class="form-label">Email</label>
                                    <input type="email" class="form-control" id="email" name="email" 
                                           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="password" class="form-label">Contraseña</label>
                                    <input type="password" class="form-control" id="password" name="password" required>
                                    <small class="text-muted">Mínimo 4 caracteres</small>
                                </div>
                                
                                <button type="submit" class="btn btn-primary w-100">
                                    Crear cuenta
                                </button>
                            </form>
                            
                            <hr>
                            
                            <p class="text-center mb-0">
                                <a href="forgot.php">¿Olvidaste tu contraseña?</a>
                            </p>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <!-- Info de vulnerabilidad -->
                    <div class="card border-danger mb-4">
                        <div class="card-header bg-danger text-white">
                            🔴 Cómo se almacena tu password
                        </div>
                        <div class="card-body">
                            <p>Cuando te registrás, tu password se guarda así:</p>
                            <pre class="bg-light p-2 rounded">$hash = md5($password);
// Sin salt, sin bcrypt, solo MD5</pre>
                            
                            <p class="mb-2"><strong>Problemas:</strong></p>
                            <ul class="small">
                                <li>MD5 es <strong>rápido</strong> — se pueden probar billones de hashes/segundo</li>
                                <li><strong>Sin salt</strong> — dos usuarios con "password123" tienen el mismo hash</li>
                                <li><strong>Rainbow tables</strong> — hashes pre-computados permiten crackeo instantáneo</li>
                            </ul>
                        </div>
                    </div>
                    
                    <!-- Link a ver hashes -->
                    <div class="card border-warning">
                        <div class="card-header bg-warning">
                            🔓 Explorar la vulnerabilidad
                        </div>
                        <div class="card-body">
                            <p>Para este lab, podés ver los hashes directamente:</p>
                            <a href="hashes.php" class="btn btn-warning w-100 mb-3">
                                Ver hashes de la BD
                            </a>
                            <p class="small text-muted mb-0">
                                En un ataque real, estos hashes se obtendrían vía SQL injection (lab A05) 
                                o accediendo al backup expuesto (lab A02).
                            </p>
                        </div>
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
