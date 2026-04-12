<?php
/**
 * OWASP Top 10 Labs 2025 - A01: Broken Access Control
 * Módulo: Mis Facturas - Ver detalle (VERSIÓN SEGURA)
 * 
 * ✅ SEGURO: 
 * - Verifica que la factura pertenezca al usuario autenticado
 * - Session config segura (HttpOnly, SameSite)
 */

// SEGURO: Session config antes de session_start()
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.use_strict_mode', 1);
session_start();

// Simular usuario logueado
$_SESSION['user_id'] = $_SESSION['user_id'] ?? 2;
$_SESSION['username'] = $_SESSION['username'] ?? 'alice';

require_once __DIR__ . '/../config/db.php';

$pageTitle = 'Ver Factura (Seguro) - Nexo';

// Info del lab
$labInfo = [
    'id' => 'A01:2025',
    'name' => 'Broken Access Control',
    'difficulty' => 'Básica',
    'description' => '
        <p>Esta es la <strong>versión segura</strong> del endpoint.</p>
        <p>La query incluye <code>AND user_id = ?</code>, verificando que la factura pertenezca al usuario autenticado.</p>
        <p class="mb-0">Intentá acceder a una factura de otro usuario — vas a recibir "Factura no encontrada".</p>
    ',
    'exploit' => '# Esta versión es SEGURA
# Probá acceder a facturas de bob (1043, 1044):

curl http://localhost:8082/a01_facturas/ver_secure.php?id=1043

# Respuesta: "Factura no encontrada"
# (aunque la factura existe, no es tuya)',
    'prevention' => '// La diferencia es UNA línea:

// VULNERABLE:
WHERE id = ?

// SEGURO:
WHERE id = ? AND user_id = ?

// El user_id viene de la sesión, no del request.
// El atacante no puede manipularlo.',
    'caseStudy' => [
        'title' => 'Optus, Australia (2022)',
        'description' => 'El fix hubiera sido exactamente esto: agregar verificación de ownership.'
    ],
    'cwes' => ['CWE-639', 'CWE-285'],
    'tools' => ['curl', 'Burp Suite'],
];

$invoice = null;
$error = null;
$accessDenied = false;

// Obtener ID de la URL
$id = $_GET['id'] ?? null;

if (!$id || !is_numeric($id)) {
    $error = "ID de factura inválido";
} else {
    try {
        $pdo = getDbConnection();
        
        // ✅ SEGURO: Filtra por ID Y por user_id
        // Si la factura existe pero no es del usuario, no la muestra
        $stmt = $pdo->prepare("
            SELECT i.*, c.name as client_name, c.email as client_email, 
                   c.rut, c.phone, c.address,
                   u.username as created_by
            FROM invoices i 
            JOIN clients c ON i.client_id = c.id 
            JOIN users u ON i.user_id = u.id
            WHERE i.id = ? AND i.user_id = ?
        ");
        $stmt->execute([$id, $_SESSION['user_id']]);
        $invoice = $stmt->fetch();
        
        if (!$invoice) {
            // Verificar si la factura existe (para mostrar mensaje apropiado)
            $checkStmt = $pdo->prepare("SELECT id, user_id FROM invoices WHERE id = ?");
            $checkStmt->execute([$id]);
            $exists = $checkStmt->fetch();
            
            if ($exists) {
                $accessDenied = true;
                $error = "Factura no encontrada"; // Mensaje genérico (no revelar que existe)
            } else {
                $error = "Factura no encontrada";
            }
        }
        
    } catch (PDOException $e) {
        $error = "Error de base de datos";
        // En producción NO mostrar $e->getMessage()
    }
}

include __DIR__ . '/../shared/header.php';
?>

<main class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1">
                📄 Factura #<?= htmlspecialchars($id) ?>
                <span class="badge bg-success">VERSIÓN SEGURA</span>
            </h1>
            <p class="text-muted mb-0">
                Logueado como: <strong><?= htmlspecialchars($_SESSION['username']) ?></strong>
            </p>
        </div>
        <a href="index.php" class="btn btn-outline-secondary">← Volver a mis facturas</a>
    </div>

    <?php if ($accessDenied): ?>
        <!-- Acceso denegado (pero no revelamos que la factura existe) -->
        <div class="alert alert-success d-flex align-items-center">
            <div class="fs-3 me-3">✅</div>
            <div>
                <strong>IDOR BLOQUEADO</strong><br>
                La factura #<?= htmlspecialchars($id) ?> existe, pero no te pertenece.<br>
                <small>La versión segura respondió "Factura no encontrada" — no revela que existe.</small>
            </div>
        </div>
        
        <div class="card border-success">
            <div class="card-header bg-success text-white">
                🛡️ ¿Por qué esto es seguro?
            </div>
            <div class="card-body">
                <p>La query incluye <code>AND user_id = ?</code>:</p>
                <pre class="bg-light p-3 rounded"><code>SELECT * FROM invoices 
WHERE id = ? <span class="text-success fw-bold">AND user_id = ?</span></code></pre>
                <p class="mb-0">
                    El <code>user_id</code> viene de la <strong>sesión del servidor</strong>, no del request. 
                    El atacante no puede manipularlo.
                </p>
            </div>
        </div>
        
        <div class="mt-3">
            <a href="ver.php?id=<?= htmlspecialchars($id) ?>" class="btn btn-outline-danger">
                🔓 Ver en versión vulnerable
            </a>
        </div>

    <?php elseif ($error): ?>
        <div class="alert alert-warning"><?= htmlspecialchars($error) ?></div>
    
    <?php elseif ($invoice): ?>
        <!-- Factura propia - mostrar normalmente -->
        <div class="alert alert-success">
            <strong>✅ Esta factura es tuya.</strong> Acceso permitido.
        </div>
        
        <div class="row">
            <div class="col-lg-8">
                <div class="card mb-4">
                    <div class="card-header bg-white d-flex justify-content-between">
                        <strong>Detalle de Factura</strong>
                        <?php
                        $statusColors = [
                            'pendiente' => 'warning',
                            'pagada' => 'success',
                            'vencida' => 'danger',
                            'anulada' => 'secondary'
                        ];
                        $color = $statusColors[$invoice['status']] ?? 'secondary';
                        ?>
                        <span class="badge bg-<?= $color ?>"><?= $invoice['status'] ?></span>
                    </div>
                    <div class="card-body">
                        <div class="row mb-4">
                            <div class="col-sm-6">
                                <h6 class="text-muted">Factura</h6>
                                <p class="h4">#<?= $invoice['id'] ?></p>
                            </div>
                            <div class="col-sm-6 text-sm-end">
                                <h6 class="text-muted">Monto Total</h6>
                                <p class="h4 text-primary">$<?= number_format($invoice['amount'], 0, ',', '.') ?></p>
                            </div>
                        </div>
                        
                        <hr>
                        
                        <div class="row">
                            <div class="col-sm-6 mb-3">
                                <h6 class="text-muted">Fecha de Emisión</h6>
                                <p><?= date('d/m/Y', strtotime($invoice['invoice_date'])) ?></p>
                            </div>
                            <div class="col-sm-6 mb-3">
                                <h6 class="text-muted">Fecha de Vencimiento</h6>
                                <p><?= date('d/m/Y', strtotime($invoice['due_date'])) ?></p>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <h6 class="text-muted">Descripción</h6>
                            <p><?= htmlspecialchars($invoice['description']) ?></p>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header bg-white">
                        <strong>👤 Datos del Cliente</strong>
                    </div>
                    <div class="card-body">
                        <p class="mb-2">
                            <strong><?= htmlspecialchars($invoice['client_name']) ?></strong>
                        </p>
                        <p class="mb-2">
                            <span class="text-muted">RUT:</span> 
                            <code><?= htmlspecialchars($invoice['rut']) ?></code>
                        </p>
                        <p class="mb-2">
                            <span class="text-muted">Email:</span><br>
                            <small><?= htmlspecialchars($invoice['client_email']) ?></small>
                        </p>
                        <p class="mb-0">
                            <span class="text-muted">Teléfono:</span><br>
                            <small><?= htmlspecialchars($invoice['phone']) ?></small>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</main>

<?php 
include __DIR__ . '/../shared/lab_panel.php';
include __DIR__ . '/../shared/footer.php'; 
?>
