<?php
/**
 * OWASP Top 10 Labs 2025 - A01: Broken Access Control
 * Módulo: Mis Facturas
 * 
 * Este archivo lista las facturas del usuario actual.
 * El problema está en ver.php que NO verifica ownership.
 */

session_start();

// Simular usuario logueado (alice, user_id=2)
// En un sistema real esto vendría del login (Lab A07)
$_SESSION['user_id'] = $_SESSION['user_id'] ?? 2;
$_SESSION['username'] = $_SESSION['username'] ?? 'alice';

require_once __DIR__ . '/../config/db.php';

$pageTitle = 'Mis Facturas - Nexo';

// Info del lab para el panel lateral
$labInfo = [
    'id' => 'A01:2025',
    'name' => 'Broken Access Control',
    'difficulty' => 'Básica',
    'description' => '
        <p>El módulo de facturas permite ver cualquier factura cambiando el parámetro <code>id</code> en la URL.</p>
        <p>El servidor <strong>no verifica</strong> que la factura pertenezca al usuario autenticado. Confía ciegamente en el ID que viene de la URL.</p>
        <p>Esto se llama <strong>IDOR</strong> (Insecure Direct Object Reference).</p>
    ',
    'exploit' => '# Estás logueado como alice (user_id=2)
# Tus facturas son: 1040, 1041, 1042

# Probá acceder a facturas de otros usuarios:
curl http://localhost:8082/a01_facturas/ver.php?id=1043
curl http://localhost:8082/a01_facturas/ver.php?id=1044
curl http://localhost:8082/a01_facturas/ver.php?id=1045

# Esas son de bob (user_id=3)
# Podés ver sus datos, montos y RUT',
    'prevention' => '// VULNERABLE (actual):
$sql = "SELECT * FROM invoices WHERE id = ?";

// SEGURO (ver_secure.php):
$sql = "SELECT * FROM invoices 
        WHERE id = ? AND user_id = ?";
// Agrega user_id a la query = solo ve sus facturas',
    'caseStudy' => [
        'title' => 'Optus, Australia (2022)',
        'description' => '11 millones de clientes expuestos porque los registros tenían IDs numéricos secuenciales y el endpoint no verificaba ownership. Multa de AU$1.5M.'
    ],
    'cwes' => ['CWE-639', 'CWE-285'],
    'tools' => ['curl', 'Burp Suite Repeater'],
];

// Obtener facturas del usuario actual
try {
    $pdo = getDbConnection();
    $stmt = $pdo->prepare("
        SELECT i.*, c.name as client_name, c.rut 
        FROM invoices i 
        JOIN clients c ON i.client_id = c.id 
        WHERE i.user_id = ?
        ORDER BY i.invoice_date DESC
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $invoices = $stmt->fetchAll();
} catch (PDOException $e) {
    $invoices = [];
    $error = "Error al cargar facturas: " . $e->getMessage();
}

include __DIR__ . '/../shared/header.php';
?>

<main class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1">📄 Mis Facturas</h1>
            <p class="text-muted mb-0">
                Logueado como: <strong><?= htmlspecialchars($_SESSION['username']) ?></strong>
                <span class="badge bg-secondary ms-2">user_id=<?= $_SESSION['user_id'] ?></span>
            </p>
        </div>
        <a href="/" class="btn btn-outline-secondary">← Volver al inicio</a>
    </div>

    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if (empty($invoices)): ?>
        <div class="alert alert-info">No tenés facturas registradas.</div>
    <?php else: ?>
        <div class="card">
            <div class="card-header bg-white">
                <strong>Tus facturas (<?= count($invoices) ?>)</strong>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Cliente</th>
                            <th>RUT</th>
                            <th>Monto</th>
                            <th>Fecha</th>
                            <th>Estado</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($invoices as $inv): ?>
                        <tr>
                            <td><code>#<?= $inv['id'] ?></code></td>
                            <td><?= htmlspecialchars($inv['client_name']) ?></td>
                            <td><code><?= htmlspecialchars($inv['rut']) ?></code></td>
                            <td class="text-end">$<?= number_format($inv['amount'], 0, ',', '.') ?></td>
                            <td><?= date('d/m/Y', strtotime($inv['invoice_date'])) ?></td>
                            <td>
                                <?php
                                $statusColors = [
                                    'pendiente' => 'warning',
                                    'pagada' => 'success',
                                    'vencida' => 'danger',
                                    'anulada' => 'secondary'
                                ];
                                $color = $statusColors[$inv['status']] ?? 'secondary';
                                ?>
                                <span class="badge bg-<?= $color ?>"><?= $inv['status'] ?></span>
                            </td>
                            <td>
                                <a href="ver.php?id=<?= $inv['id'] ?>" class="btn btn-sm btn-outline-primary">
                                    Ver detalle
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Hint para el lab -->
        <div class="alert alert-warning mt-4">
            <strong>💡 Pista del lab:</strong> 
            Tus facturas tienen IDs <code><?= implode(', ', array_column($invoices, 'id')) ?></code>. 
            ¿Qué pasa si cambiás el número en la URL cuando hacés clic en "Ver detalle"?
        </div>
    <?php endif; ?>
</main>

<?php 
include __DIR__ . '/../shared/lab_panel.php';
include __DIR__ . '/../shared/footer.php'; 
?>
