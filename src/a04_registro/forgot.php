<?php
/**
 * OWASP Top 10 Labs 2025 - A04: Cryptographic Failures
 * Módulo: Olvidé mi contraseña
 * 
 * ⚠️ VULNERABLE: Envía la password actual en texto plano
 * (implica que la password está almacenada de forma reversible)
 */

session_start();
require_once __DIR__ . '/../config/db.php';

$pageTitle = 'Recuperar Contraseña - Nexo';

// Info del lab
$labInfo = [
    'id' => 'A04:2025',
    'name' => 'Cryptographic Failures',
    'difficulty' => 'Básica',
    'description' => '
        <p>El sistema de "olvidé mi contraseña" tiene un problema grave:</p>
        <p><strong>Envía la password actual</strong> en lugar de un link de reset.</p>
        <p>Esto implica que Nexo puede recuperar tu password — o sea que no está hasheada correctamente.</p>
    ',
    'exploit' => '# Probá con un email existente:
alice.mendez@gmail.com

# El sistema te va a "enviar" la password
# (en un sistema real, interceptarías el email)',
    'prevention' => '// NUNCA enviar la password actual.
// Generar token temporal de un solo uso:

$token = bin2hex(random_bytes(32));
$expiry = date("Y-m-d H:i:s", strtotime("+1 hour"));

// Guardar token en BD, enviar link:
// /reset.php?token=abc123...

// El link expira en 1 hora y se invalida al usarse',
    'caseStudy' => [
        'title' => 'LinkedIn (2012)',
        'description' => 'No solo los hashes eran débiles — el breach expuso que no tenían proceso de reset seguro.'
    ],
    'cwes' => ['CWE-916', 'CWE-327', 'CWE-640'],
    'tools' => ['Browser'],
];

$result = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    
    if (empty($email)) {
        $error = "Ingresá tu email";
    } else {
        try {
            $pdo = getDbConnection();
            $stmt = $pdo->prepare("SELECT username, email, password_md5 FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if ($user) {
                // ⚠️ VULNERABLE: "Recuperamos" la password del hash MD5
                // En realidad esto es imposible si el hash fuera seguro
                // Simulamos que tenemos una tabla de passwords en texto plano
                
                $knownPasswords = [
                    '0192023a7bbd73250516f069df18b500' => 'admin123',
                    '482c811da5d5b4bc6d497ffa98491e38' => 'password123',
                    '5ebe2294ecd0e0f08eab7690d2a6ee69' => 'secret',
                    'e8d95a51f3af4a3b134bf6bb680a213a' => 'nexo2024',
                    '7c6a180b36896a65c3ed5c1f3a9d4e2b' => 'D14n4.2025',
                ];
                
                $plainPassword = $knownPasswords[$user['password_md5']] ?? '[hash no reconocido]';
                
                $result = [
                    'username' => $user['username'],
                    'email' => $user['email'],
                    'password' => $plainPassword,
                ];
            } else {
                // En un sistema seguro, NO revelaríamos si el email existe
                $error = "Email no encontrado";
            }
        } catch (PDOException $e) {
            $error = "Error de base de datos";
        }
    }
}

include __DIR__ . '/../shared/header.php';
?>

<main class="container py-4">
    <div class="row justify-content-center">
        <div class="col-md-6">
            
            <div class="text-center mb-4">
                <h1 class="h3">🔑 Recuperar Contraseña</h1>
                <p class="text-muted">Te enviaremos tu contraseña por email</p>
            </div>
            
            <?php if ($result): ?>
            <!-- ⚠️ VULNERABLE: Mostrando la password -->
            <div class="alert alert-danger">
                <h5>🚨 ¡Problema de seguridad!</h5>
                <p>El sistema acaba de "enviarte" tu contraseña actual:</p>
                <div class="bg-white p-3 rounded border">
                    <p class="mb-1"><strong>Usuario:</strong> <?= htmlspecialchars($result['username']) ?></p>
                    <p class="mb-1"><strong>Email:</strong> <?= htmlspecialchars($result['email']) ?></p>
                    <p class="mb-0"><strong>Contraseña:</strong> <code class="fs-5"><?= htmlspecialchars($result['password']) ?></code></p>
                </div>
            </div>
            
            <div class="alert alert-warning">
                <strong>¿Por qué esto es un problema?</strong>
                <ul class="mb-0 mt-2 small">
                    <li>Si pueden recuperar tu password, significa que la almacenan de forma reversible</li>
                    <li>El email viaja en texto plano por internet</li>
                    <li>Cualquiera con acceso al email (IT, atacante) puede ver tu password</li>
                    <li>No hay forma de saber si alguien más la vio</li>
                </ul>
            </div>
            
            <div class="card border-success">
                <div class="card-header bg-success text-white">
                    🛡️ Cómo debería funcionar
                </div>
                <div class="card-body small">
                    <ol class="mb-0">
                        <li>Usuario pide reset</li>
                        <li>Sistema genera <strong>token único</strong> (ej: <code>a7f3b2c1...</code>)</li>
                        <li>Envía link: <code>/reset?token=a7f3b2c1...</code></li>
                        <li>Link expira en 1 hora</li>
                        <li>Usuario crea <strong>nueva</strong> password</li>
                        <li>Token se invalida después de usarse</li>
                    </ol>
                </div>
            </div>
            
            <div class="text-center mt-3">
                <a href="index.php" class="btn btn-outline-secondary">← Volver al registro</a>
            </div>
            
            <?php else: ?>
            
            <div class="card">
                <div class="card-body">
                    <?php if ($error): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>
                    
                    <form method="POST">
                        <div class="mb-3">
                            <label for="email" class="form-label">Email de tu cuenta</label>
                            <input type="email" class="form-control" id="email" name="email" 
                                   placeholder="ej: alice.mendez@gmail.com"
                                   value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                        </div>
                        
                        <button type="submit" class="btn btn-primary w-100">
                            Enviar contraseña
                        </button>
                    </form>
                    
                    <hr>
                    
                    <p class="small text-muted text-center mb-0">
                        Emails de prueba: <code>alice.mendez@gmail.com</code>, 
                        <code>roberto.silva@empresa.cl</code>
                    </p>
                </div>
            </div>
            
            <div class="text-center mt-3">
                <a href="index.php">← Volver al registro</a>
            </div>
            
            <?php endif; ?>
            
        </div>
    </div>
</main>

<?php 
include __DIR__ . '/../shared/lab_panel.php';
include __DIR__ . '/../shared/footer.php'; 
?>
