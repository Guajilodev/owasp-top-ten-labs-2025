<?php
/**
 * OWASP Top 10 Labs 2025 - A01: Broken Access Control
 * Módulo: Mis Facturas - Ver detalle
 * 
 * ⚠️ VULNERABLE: No verifica que la factura pertenezca al usuario.
 * Cualquiera puede ver cualquier factura cambiando el ?id=
 */

session_start();

// Simular usuario logueado
$_SESSION['user_id'] = $_SESSION['user_id'] ?? 2;
$_SESSION['username'] = $_SESSION['username'] ?? 'alice';

require_once __DIR__ . '/../config/db.php';

$pageTitle = 'Ver Factura - Nexo';

// Info del lab
$labInfo = [
    'id' => 'A01:2025',
    'name' => 'Broken Access Control',
    'difficulty' => 'Básica',
    'description' => '
        <p>Este endpoint <strong>no verifica ownership</strong>. La query solo filtra por <code>id</code>, no por <code>user_id</code>.</p>
        <p class="mb-0">Resultado: podés ver facturas de otros usuarios simplemente cambiando el número en la URL.</p>
    ',
    'exploit' => '# Facturas de alice: 1040, 1041, 1042
# Facturas de bob: 1043, 1044, 1045
# Facturas de carlos: 1046, 1047, 1048

# Probá estos (siendo alice):
curl http://localhost:8082/a01_facturas/ver.php?id=1043
curl http://localhost:8082/a01_facturas/ver.php?id=1046

# Vas a ver datos de otros clientes',
    'prevention' => '// VULNERABLE (este archivo):
$stmt = $pdo->prepare("
    SELECT * FROM invoices WHERE id = ?
");
$stmt->execute([$id]);

// SEGURO (ver_secure.php):
$stmt = $pdo->prepare("
    SELECT * FROM invoices 
    WHERE id = ? AND user_id = ?
");
$stmt->execute([$id, $_SESSION[\'user_id\']]);',
    'caseStudy' => [
        'title' => 'Optus, Australia (2022)',
        'description' => '11 millones de clientes expuestos. IDs secuenciales sin verificación de ownership.'
    ],
    'cwes' => ['CWE-639', 'CWE-285'],
    'tools' => ['curl', 'Burp Suite'],
];

$invoice = null;
$error = null;
$isOwnInvoice = true;

// Obtener ID de la URL
$id = $_GET['id'] ?? null;

if (!$id || !is_numeric($id)) {
    $error = "ID de factura inválido";
} else {
    try {
        $pdo = getDbConnection();
        
        // ⚠️ VULNERABLE: Solo filtra por ID, no verifica user_id
        // Debería ser: WHERE id = ? AND user_id = ?
        $stmt = $pdo->prepare("
            SELECT i.*, c.name as client_name, c.email as client_email, 
                   c.rut, c.phone, c.address,
                   u.username as created_by
            FROM invoices i 
            JOIN clients c ON i.client_id = c.id 
            JOIN users u ON i.user_id = u.id
            WHERE i.id = ?
        ");
        $stmt->execute([$id]);
        $invoice = $stmt->fetch();
        
        if (!$invoice) {
            $error = "Factura no encontrada";
        } else {
            // Verificar si es del usuario actual (para mostrar alerta)
            $isOwnInvoice = ($invoice['user_id'] == $_SESSION['user_id']);
        }
        
    } catch (PDOException $e) {
        $error = "Error de base de datos: " . $e->getMessage();
    }
}

include __DIR__ . '/../shared/header.php';
?>

<main class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1">📄 Factura #<?= htmlspecialchars($id) ?></h1>
            <p class="text-muted mb-0">
                Logueado como: <strong><?= htmlspecialchars($_SESSION['username']) ?></strong>
            </p>
        </div>
        <a href="index.php" class="btn btn-outline-secondary">← Volver a mis facturas</a>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    
    <?php elseif ($invoice): ?>
        
        <?php if (!$isOwnInvoice): ?>
        <!-- 🚨 ALERTA: Está viendo una factura que no es suya -->
        <div class="alert alert-danger d-flex align-items-center">
            <div class="fs-3 me-3">🚨</div>
            <div>
                <strong>¡IDOR EXPLOTADO!</strong><br>
                Esta factura es de <code><?= htmlspecialchars($invoice['created_by']) ?></code> (user_id=<?= $invoice['user_id'] ?>), 
                no de <code><?= htmlspecialchars($_SESSION['username']) ?></code> (user_id=<?= $_SESSION['user_id'] ?>).<br>
                <small>El servidor NO verificó que la factura te pertenece.</small>
            </div>
        </div>
        <?php endif; ?>

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
                        
                        <div class="mb-3">
                            <h6 class="text-muted">Creada por</h6>
                            <p><code><?= htmlspecialchars($invoice['created_by']) ?></code> (user_id=<?= $invoice['user_id'] ?>)</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4">
                <!-- Datos del cliente -->
                <div class="card <?= !$isOwnInvoice ? 'border-danger' : '' ?>">
                    <div class="card-header bg-white">
                        <strong>👤 Datos del Cliente</strong>
                        <?php if (!$isOwnInvoice): ?>
                            <span class="badge bg-danger ms-2">DATOS EXPUESTOS</span>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <p class="mb-2">
                            <strong><?= htmlspecialchars($invoice['client_name']) ?></strong>
                        </p>
                        <p class="mb-2">
                            <span class="text-muted">RUT:</span> 
                            <code class="<?= !$isOwnInvoice ? 'text-danger' : '' ?>"><?= htmlspecialchars($invoice['rut']) ?></code>
                        </p>
                        <p class="mb-2">
                            <span class="text-muted">Email:</span><br>
                            <small><?= htmlspecialchars($invoice['client_email']) ?></small>
                        </p>
                        <p class="mb-2">
                            <span class="text-muted">Teléfono:</span><br>
                            <small><?= htmlspecialchars($invoice['phone']) ?></small>
                        </p>
                        <p class="mb-0">
                            <span class="text-muted">Dirección:</span><br>
                            <small><?= htmlspecialchars($invoice['address']) ?></small>
                        </p>
                    </div>
                </div>
                
                <!-- Comparación con versión segura -->
                <div class="card mt-3 border-success">
                    <div class="card-header bg-success text-white">
                        🛡️ Versión Segura
                    </div>
                    <div class="card-body">
                        <p class="small">
                            <a href="ver_secure.php?id=<?= $id ?>" class="btn btn-sm btn-outline-success w-100">
                                Probar ver_secure.php
                            </a>
                        </p>
                        <p class="small text-muted mb-0">
                            La versión segura verifica que la factura te pertenezca antes de mostrarla.
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
