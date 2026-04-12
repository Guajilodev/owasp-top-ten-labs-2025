<?php
/**
 * OWASP Top 10 Labs 2025 - A10: Mishandling of Exceptional Conditions
 * Modulo: Procesar Transferencia - VULNERABLE
 * 
 * VULNERABILIDADES:
 * 1. Failing open: Si algo falla, responde "success" igual
 * 2. Stack trace: Expone credenciales de la BD en errores
 * 3. CSRF: No valida token anti-CSRF (vulnerabilidad histórica bonus)
 * 
 * La vulnerabilidad #3 permite que un atacante cree un formulario en
 * su sitio malicioso que transfiera fondos de la víctima sin su conocimiento.
 * 
 * NO USAR EN PRODUCCION - Solo para fines educativos
 */

session_start();

$_SESSION['user_id'] = $_SESSION['user_id'] ?? 2;
$_SESSION['username'] = $_SESSION['username'] ?? 'alice';

require_once __DIR__ . '/../config/db.php';

// Headers para respuesta JSON
header('Content-Type: application/json');

$toUserId = $_POST['to_user'] ?? null;
$amount = (float)($_POST['amount'] ?? 0);
$fromUserId = $_SESSION['user_id'];

// Para requests que no son POST, mostrar pagina de demo
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: text/html');
    $pageTitle = 'Transferencia Vulnerable - Nexo';
    
    $labInfo = [
        'id' => 'A10:2025',
        'name' => 'Mishandling of Exceptional Conditions',
        'difficulty' => 'Avanzada',
        'description' => '
            <p>Este endpoint tiene <strong>tres vulnerabilidades</strong>:</p>
            <ol>
                <li><strong>Failing open:</strong> Si algo falla, responde "success" igual</li>
                <li><strong>Stack trace:</strong> Expone credenciales de la BD en errores</li>
                <li><strong>CSRF (bonus):</strong> No valida token anti-falsificacion</li>
            </ol>
            <p class="mb-0"><small>CSRF es una vulnerabilidad historica incluida por completitud pedagogica.</small></p>
        ',
        'exploit' => '# 1. Failing open
curl -X POST http://localhost:8082/a10_pagos/transferir.php -d "to_user=999&amount=100"

# 2. Stack trace
curl -X POST http://localhost:8082/a10_pagos/transferir.php -d "to_user=\' OR 1=1--&amount=100"

# 3. CSRF - Un atacante pone esto en su sitio:
<form action="http://nexo.com/a10_pagos/transferir.php" method="POST">
  <input type="hidden" name="to_user" value="666">
  <input type="hidden" name="amount" value="50000">
</form>
<script>document.forms[0].submit()</script>',
        'prevention' => 'Ver transferir_secure.php - incluye:
- Fail closed
- Sin stack trace
- Token CSRF obligatorio',
        'caseStudy' => ['title' => 'Knight Capital (2012)', 'description' => '$440M perdidos por excepcion no manejada'],
        'cwes' => ['CWE-636', 'CWE-209', 'CWE-352'],
        'tools' => ['curl'],
        'secureVersion' => 'transferir_secure.php',
    ];
    
    include __DIR__ . '/../shared/header.php';
    ?>
    <main class="container py-4">
        <div class="alert alert-info">
            Este endpoint espera un POST. Usa curl o el formulario de <a href="index.php">index.php</a>.
        </div>
        
        <div class="card">
            <div class="card-header bg-dark text-white">Ejemplos con curl</div>
            <div class="card-body">
                <h6>1. Failing Open (transferencia a usuario inexistente):</h6>
                <pre class="vulnerable p-3"><code>curl -X POST http://localhost:8082/a10_pagos/transferir.php \
  -d "to_user=999&amount=100"

# Respuesta: {"status":"success","message":"Transferencia completada"}
# Pero el usuario 999 NO existe!</code></pre>
                
                <h6 class="mt-4">2. Stack Trace (provocar error de SQL):</h6>
                <pre class="vulnerable p-3"><code>curl -X POST http://localhost:8082/a10_pagos/transferir.php \
  -d "to_user='; SELECT * FROM users--&amount=100"

# Respuesta: Stack trace completo con credenciales de la BD</code></pre>
            </div>
        </div>
    </main>
    <?php
    include __DIR__ . '/../shared/lab_panel.php';
    include __DIR__ . '/../shared/footer.php';
    exit;
}

// Detectar si es un intento de SQL injection para mostrar stack trace
$isSqlInjectionAttempt = preg_match("/['\";]|--|\bOR\b|\bAND\b|\bUNION\b/i", $toUserId ?? '');

try {
    // Simular error de SQL si hay caracteres sospechosos
    if ($isSqlInjectionAttempt) {
        // VULNERABLE: Crear una conexion que exponga credenciales en el error
        $host = getenv('NEXO_DB_HOST') ?: 'db';
        $dbname = getenv('NEXO_DB_NAME') ?: 'nexo_labs';
        $user = getenv('NEXO_DB_USER') ?: 'nexo_user';
        $pass = getenv('NEXO_DB_PASS') ?: 'nexo_password_2025';
        
        // Forzar un error que exponga info
        throw new PDOException(
            "SQLSTATE[42000]: Syntax error near '$toUserId' " .
            "at line 1 in query: SELECT balance FROM wallets WHERE user_id = '$toUserId'\n" .
            "Connection: mysql:host=$host;dbname=$dbname (user: $user, pass: $pass)\n" .
            "Stack trace:\n" .
            "#0 /var/www/html/a10_pagos/transferir.php(45): PDO->query()\n" .
            "#1 /var/www/html/a10_pagos/transferir.php(78): processTransfer()\n" .
            "#2 {main}\n" .
            "Server: Apache/2.4.57 (Debian) PHP/" . PHP_VERSION
        );
    }
    
    $pdo = getDbConnection();
    
    // Verificar que el destinatario existe
    $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ?");
    $stmt->execute([$toUserId]);
    $toUser = $stmt->fetch();
    
    // VULNERABLE: Failing open
    // Si el usuario no existe, el codigo sigue y eventualmente
    // el catch devuelve "success" igual
    if (!$toUser) {
        throw new Exception("Usuario destinatario no encontrado");
    }
    
    // Verificar balance
    $stmt = $pdo->prepare("SELECT balance FROM wallets WHERE user_id = ?");
    $stmt->execute([$fromUserId]);
    $wallet = $stmt->fetch();
    
    if (!$wallet || $wallet['balance'] < $amount) {
        throw new Exception("Balance insuficiente");
    }
    
    // Procesar transferencia
    $pdo->beginTransaction();
    
    // Restar del origen
    $stmt = $pdo->prepare("UPDATE wallets SET balance = balance - ? WHERE user_id = ?");
    $stmt->execute([$amount, $fromUserId]);
    
    // Sumar al destino
    $stmt = $pdo->prepare("UPDATE wallets SET balance = balance + ? WHERE user_id = ?");
    $stmt->execute([$amount, $toUserId]);
    
    // Registrar transferencia
    $stmt = $pdo->prepare("INSERT INTO transfers (from_user_id, to_user_id, amount, status) VALUES (?, ?, ?, 'completada')");
    $stmt->execute([$fromUserId, $toUserId, $amount]);
    
    $pdo->commit();
    
    echo json_encode([
        'status' => 'success',
        'message' => 'Transferencia completada',
        'transfer_id' => $pdo->lastInsertId()
    ]);
    
} catch (PDOException $e) {
    // VULNERABLE: Stack trace expuesto
    // Esto muestra toda la info interna incluyendo credenciales
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Error de base de datos',
        'debug' => [
            'exception' => get_class($e),
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString(),
        ]
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    // VULNERABLE: Failing open
    // El desarrollador penso "si algo falla, no rompamos la experiencia del usuario"
    // Resultado: reporta exito aunque la operacion no se completo
    
    // Log interno (esto esta bien)
    error_log("[TRANSFER ERROR] " . $e->getMessage());
    
    // PROBLEMA: Responde 200 OK y "success" aunque fallo
    http_response_code(200); // Deberia ser 400 o 500
    echo json_encode([
        'status' => 'success', // MENTIRA - esto deberia ser 'error'
        'message' => 'Transferencia completada', // MENTIRA - no se completo
        // El desarrollador comento esto pensando que era "mejor UX":
        // 'actual_error' => $e->getMessage()
    ]);
}
