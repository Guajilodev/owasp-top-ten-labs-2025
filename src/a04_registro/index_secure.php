<?php
/**
 * OWASP Top 10 Labs 2025 - A04: Cryptographic Failures
 * Módulo: Registro de Cuenta - VERSION SEGURA
 * 
 * PROTECCION: Usa password_hash() con bcrypt
 */

session_start();
require_once __DIR__ . '/../config/db.php';

$pageTitle = 'Registro Seguro - Nexo';

$labInfo = [
    'id' => 'A04:2025',
    'name' => 'Cryptographic Failures (SEGURO)',
    'difficulty' => 'Básica',
    'description' => '
        <p>Esta versión usa <strong>password_hash()</strong> con bcrypt.</p>
        <p>Bcrypt es lento por diseño, incluye salt automático, y es resistente a rainbow tables.</p>
    ',
    'exploit' => '# No hay exploit - esta es la version segura

# La password se hashea asi:
password_hash($password, PASSWORD_BCRYPT)

# Produce algo como:
$2y$10$randomsalt...longhash...',
    'prevention' => '// VERSION SEGURA (este archivo):
$hash = password_hash($password, PASSWORD_BCRYPT);

// Para verificar:
if (password_verify($input, $storedHash)) {
    // Login OK
}

// Bcrypt incluye salt automatico
// Cada hash es unico aunque la password sea igual',
    'caseStudy' => [
        'title' => 'LinkedIn (2012)',
        'description' => 'Si hubieran usado bcrypt, crackear 117M passwords habria tomado siglos en lugar de horas.'
    ],
    'cwes' => ['CWE-916', 'CWE-327'],
    'tools' => ['password_hash()', 'password_verify()'],
];

$success = null;
$error = null;
$hashGenerated = null;

// Procesar registro
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($email) || empty($password)) {
        $error = "Todos los campos son obligatorios";
    } elseif (strlen($password) < 8) {
        $error = "La contraseña debe tener al menos 8 caracteres";
    } else {
        // SEGURO: bcrypt con salt automatico
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $hashGenerated = $hash;
        
        $success = "Password hasheada de forma segura con bcrypt.";
    }
}

include __DIR__ . '/../shared/header.php';
?>

<main class="container py-4">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h3 mb-1">
                        👤 Registro Seguro
                        <span class="badge bg-success">PROTEGIDO</span>
                    </h1>
                    <p class="text-muted mb-0">Usando bcrypt en lugar de MD5</p>
                </div>
                <a href="index.php" class="btn btn-outline-danger">← Ver versión vulnerable</a>
            </div>
            
            <div class="row">
                <div class="col-md-6">
                    <div class="card mb-4 border-success">
                        <div class="card-header bg-success text-white">
                            <strong>Crear cuenta (seguro)</strong>
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
                                    <input type="text" class="form-control" id="username" name="username" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="email" class="form-label">Email</label>
                                    <input type="email" class="form-control" id="email" name="email" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="password" class="form-label">Contraseña</label>
                                    <input type="password" class="form-control" id="password" name="password" required>
                                    <small class="text-success">Mínimo 8 caracteres (más seguro)</small>
                                </div>
                                
                                <button type="submit" class="btn btn-success w-100">
                                    Hashear con bcrypt
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <?php if ($hashGenerated): ?>
                    <div class="card border-success mb-4">
                        <div class="card-header bg-success text-white">
                            🛡️ Hash generado (bcrypt)
                        </div>
                        <div class="card-body">
                            <pre class="bg-light p-2 rounded small" style="word-break: break-all;"><?= htmlspecialchars($hashGenerated) ?></pre>
                            
                            <h6 class="mt-3">Características:</h6>
                            <ul class="small">
                                <li><strong>$2y$</strong> — Indica algoritmo bcrypt</li>
                                <li><strong>10$</strong> — Cost factor (2^10 iteraciones)</li>
                                <li><strong>Salt incluido</strong> — Los primeros 22 chars después del cost</li>
                                <li><strong>Único</strong> — Cada vez genera un hash diferente</li>
                            </ul>
                            
                            <div class="alert alert-info small mb-0">
                                Probá registrar la misma password varias veces — cada hash será diferente porque el salt es aleatorio.
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <div class="card border-success">
                        <div class="card-header bg-white">
                            <strong>Comparación MD5 vs bcrypt</strong>
                        </div>
                        <div class="card-body">
                            <table class="table table-sm small">
                                <thead>
                                    <tr>
                                        <th></th>
                                        <th class="text-danger">MD5</th>
                                        <th class="text-success">bcrypt</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>Velocidad</td>
                                        <td>~10B/seg</td>
                                        <td>~1K/seg</td>
                                    </tr>
                                    <tr>
                                        <td>Salt</td>
                                        <td>❌ Manual</td>
                                        <td>✅ Automático</td>
                                    </tr>
                                    <tr>
                                        <td>Rainbow tables</td>
                                        <td>❌ Vulnerable</td>
                                        <td>✅ Inmune</td>
                                    </tr>
                                    <tr>
                                        <td>Crackear "password123"</td>
                                        <td>~0.001 seg</td>
                                        <td>~años</td>
                                    </tr>
                                </tbody>
                            </table>
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
