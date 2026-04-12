<?php
/**
 * OWASP Top 10 Labs 2025 - A10: Mishandling of Exceptional Conditions
 * Modulo: Procesar Transferencia - VERSION SEGURA
 * 
 * PROTECCIONES:
 * 1. Failing closed: Si algo falla, responde "error" con codigo HTTP apropiado
 * 2. Sin stack trace: Solo mensaje generico al usuario, log interno detallado
 * 
 * Este es el manejo correcto de excepciones.
 */

session_start();

$_SESSION['user_id'] = $_SESSION['user_id'] ?? 2;
$_SESSION['username'] = $_SESSION['username'] ?? 'alice';

require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json');

$toUserId = $_POST['to_user'] ?? null;
$amount = (float)($_POST['amount'] ?? 0);
$fromUserId = $_SESSION['user_id'];

// Para requests que no son POST, mostrar pagina de demo
if ($_SERVER['REQUEST_METHOD'] !== 'POST' && !isset($_GET['demo'])) {
    header('Content-Type: text/html');
    $pageTitle = 'Transferencia Segura - Nexo';
    
    $labInfo = [
        'id' => 'A10:2025',
        'name' => 'Mishandling of Exceptional Conditions (SEGURO)',
        'difficulty' => 'Avanzada',
        'description' => '<p>Este endpoint maneja correctamente las excepciones.</p>',
        'exploit' => '# Estos ataques NO funcionan aqui:
curl -X POST http://localhost:8082/a10_pagos/transferir_secure.php -d "to_user=999&amount=100"
# Responde: error (no success)

curl -X POST http://localhost:8082/a10_pagos/transferir_secure.php -d "to_user=\' OR 1=1--&amount=100"
# Responde: error generico (sin stack trace)',
        'prevention' => 'Este archivo ES la version segura',
        'caseStudy' => ['title' => 'Knight Capital (2012)', 'description' => 'Si hubieran fallado cerrado, no habrian perdido $440M'],
        'cwes' => ['CWE-636', 'CWE-209'],
        'tools' => ['curl'],
    ];
    
    include __DIR__ . '/../shared/header.php';
    ?>
    <main class="container py-4">
        <div class="alert alert-success">
            <h5>Version Segura</h5>
            <p class="mb-0">Este endpoint maneja correctamente las excepciones. No hay failing open ni stack traces expuestos.</p>
        </div>
        
        <div class="card">
            <div class="card-header bg-success text-white">Comparacion de comportamiento</div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6 class="text-danger">transferir.php (vulnerable)</h6>
                        <pre class="vulnerable p-2 small"><code># Usuario inexistente
curl -X POST .../transferir.php \
  -d "to_user=999&amount=100"

{"status":"success"} ← MENTIRA

# SQL injection attempt
curl -X POST .../transferir.php \
  -d "to_user='; DROP--&amount=100"

{"debug":{"message":"...pass: nexo_password..."}} ← LEAK</code></pre>
                    </div>
                    <div class="col-md-6">
                        <h6 class="text-success">transferir_secure.php (seguro)</h6>
                        <pre class="secure p-2 small"><code># Usuario inexistente
curl -X POST .../transferir_secure.php \
  -d "to_user=999&amount=100"

{"status":"error"} ← CORRECTO (HTTP 400)

# SQL injection attempt
curl -X POST .../transferir_secure.php \
  -d "to_user='; DROP--&amount=100"

{"status":"error","message":"..."} ← Sin leak</code></pre>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="card mt-4 border-success">
            <div class="card-header bg-success text-white">Principios aplicados</div>
            <div class="card-body">
                <ol>
                    <li><strong>Fail closed:</strong> Si algo falla, la operacion NO se completa y se reporta error</li>
                    <li><strong>HTTP status correcto:</strong> 400 para errores del cliente, 500 para errores del servidor</li>
                    <li><strong>Log interno, mensaje generico:</strong> Los detalles van al log, el usuario ve mensaje generico</li>
                    <li><strong>Nunca exponer internals:</strong> Sin stack traces, sin credenciales, sin rutas de archivos</li>
                </ol>
            </div>
        </div>
    </main>
    <?php
    include __DIR__ . '/../shared/lab_panel.php';
    include __DIR__ . '/../shared/footer.php';
    exit;
}

// Demo mode - simular una transferencia fallida
if (isset($_GET['demo'])) {
    echo json_encode([
        'status' => 'error',
        'message' => 'No se pudo completar la transferencia. Por favor intente nuevamente.',
        'code' => 'TRANSFER_FAILED'
    ], JSON_PRETTY_PRINT);
    exit;
}

try {
    // Validar input primero (fail fast)
    if (!$toUserId || !is_numeric($toUserId)) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'Destinatario invalido',
            'code' => 'INVALID_RECIPIENT'
        ]);
        exit;
    }
    
    if ($amount <= 0) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'Monto invalido',
            'code' => 'INVALID_AMOUNT'
        ]);
        exit;
    }
    
    $pdo = getDbConnection();
    
    // Verificar que el destinatario existe
    $stmt = $pdo->prepare("SELECT id, username FROM users WHERE id = ?");
    $stmt->execute([$toUserId]);
    $toUser = $stmt->fetch();
    
    // SEGURO: Fail closed - si no existe, reportar error
    if (!$toUser) {
        // Log interno con detalles
        error_log("[TRANSFER] Recipient not found: to_user=$toUserId, from_user=$fromUserId");
        
        // Respuesta al usuario sin detalles internos
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'El destinatario no existe',
            'code' => 'RECIPIENT_NOT_FOUND'
        ]);
        exit;
    }
    
    // Verificar balance
    $stmt = $pdo->prepare("SELECT balance FROM wallets WHERE user_id = ?");
    $stmt->execute([$fromUserId]);
    $wallet = $stmt->fetch();
    
    if (!$wallet || $wallet['balance'] < $amount) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'Balance insuficiente',
            'code' => 'INSUFFICIENT_BALANCE'
        ]);
        exit;
    }
    
    // Procesar transferencia
    $pdo->beginTransaction();
    
    try {
        // Restar del origen
        $stmt = $pdo->prepare("UPDATE wallets SET balance = balance - ? WHERE user_id = ?");
        $stmt->execute([$amount, $fromUserId]);
        
        // Sumar al destino
        $stmt = $pdo->prepare("UPDATE wallets SET balance = balance + ? WHERE user_id = ?");
        $stmt->execute([$amount, $toUserId]);
        
        // Registrar transferencia
        $stmt = $pdo->prepare("INSERT INTO transfers (from_user_id, to_user_id, amount, status) VALUES (?, ?, ?, 'completada')");
        $stmt->execute([$fromUserId, $toUserId, $amount]);
        
        $transferId = $pdo->lastInsertId();
        
        $pdo->commit();
        
        // Solo ahora reportamos exito
        echo json_encode([
            'status' => 'success',
            'message' => 'Transferencia completada',
            'transfer_id' => $transferId
        ]);
        
    } catch (Exception $e) {
        // Rollback si algo falla
        $pdo->rollBack();
        throw $e; // Re-throw para el catch externo
    }
    
} catch (PDOException $e) {
    // SEGURO: Log interno detallado
    error_log("[TRANSFER DB ERROR] " . $e->getMessage() . " | Trace: " . $e->getTraceAsString());
    
    // SEGURO: Respuesta generica al usuario (sin detalles internos)
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Error interno. Por favor intente nuevamente.',
        'code' => 'INTERNAL_ERROR',
        // NO incluir: debug, trace, exception, etc.
    ]);
    
} catch (Exception $e) {
    // SEGURO: Fail closed - reportar error, no exito
    error_log("[TRANSFER ERROR] " . $e->getMessage());
    
    http_response_code(500); // O 400 segun el caso
    echo json_encode([
        'status' => 'error', // CORRECTO - no 'success'
        'message' => 'No se pudo completar la transferencia',
        'code' => 'TRANSFER_FAILED'
    ]);
}
