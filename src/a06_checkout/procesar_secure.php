<?php
/**
 * OWASP Top 10 Labs 2025 - A06: Insecure Design
 * Modulo: Checkout - Procesar compra VERSION SEGURA
 * 
 * PROTECCION:
 * El precio se obtiene de la base de datos usando el product_id.
 * NUNCA se confia en el precio enviado por el cliente.
 * 
 * Este es el diseno correcto para un checkout.
 */

session_start();

$_SESSION['user_id'] = $_SESSION['user_id'] ?? 2;
$_SESSION['username'] = $_SESSION['username'] ?? 'alice';
$_SESSION['client_id'] = $_SESSION['client_id'] ?? 1;

require_once __DIR__ . '/../config/db.php';

$pageTitle = 'Compra Segura - Nexo';

$labInfo = [
    'id' => 'A06:2025',
    'name' => 'Insecure Design (SEGURO)',
    'difficulty' => 'Intermedia',
    'description' => '
        <p>Esta version <strong>ignora el precio del cliente</strong>.</p>
        <p>El precio se obtiene directamente de la base de datos usando el <code>product_id</code>.</p>
        <p class="mb-0">Aunque intentes enviar <code>price=1</code>, se cobrara el precio real.</p>
    ',
    'exploit' => '# Este ataque NO funciona aqui:
curl -X POST http://localhost:8082/a06_checkout/procesar_secure.php \\
  -d "product_id=3&price=1&quantity=1"

# El servidor ignora price=1 y cobra $299.990',
    'prevention' => '// VERSION SEGURA (este archivo):

// 1. Solo aceptar product_id del cliente
$productId = $_POST[\'product_id\'];

// 2. Obtener precio de la BD (NUNCA del cliente)
$product = getProductById($productId);
$amount = $product[\'price\'];

// 3. El campo "price" del POST se IGNORA
// Aunque el atacante lo modifique, no tiene efecto',
    'caseStudy' => [
        'title' => 'Marketplace Latinoamericano (2023)',
        'description' => 'Si hubieran validado el precio en el servidor, no habrian perdido dinero.'
    ],
    'cwes' => ['CWE-602', 'CWE-840'],
    'tools' => ['Burp Suite (no funcionara)', 'curl'],
];

// Obtener datos del POST
$productId = $_POST['product_id'] ?? null;
$priceFromClient = $_POST['price'] ?? null; // Se ignora
$quantity = (int)($_POST['quantity'] ?? 1);

$error = null;
$success = false;
$order = null;
$product = null;
$attemptedTampering = false;

// Permitir demo via GET
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['demo'])) {
    $productId = 3; // Nexo Enterprise para demo
    $priceFromClient = 1;
    $quantity = 1;
}

if (!$productId && $_SERVER['REQUEST_METHOD'] !== 'GET') {
    header('Location: index.php');
    exit;
}

if (!$productId) {
    $error = "Producto no especificado";
} else {
    try {
        $pdo = getDbConnection();
        
        // Obtener el producto de la BD
        $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
        $stmt->execute([$productId]);
        $product = $stmt->fetch();
        
        if (!$product) {
            $error = "Producto no encontrado";
        } else {
            // SEGURO: Usar el precio de la BASE DE DATOS, no del cliente
            $amount = $product['price']; // De la BD
            $total = $amount * $quantity;
            
            // Detectar si intentaron price tampering (para mostrar que no funciono)
            if ($priceFromClient !== null && (float)$priceFromClient < $amount * 0.9) {
                $attemptedTampering = true;
            }
            
            // Solo procesar si es POST (no en demo GET)
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                // Crear la orden con el precio REAL
                $stmt = $pdo->prepare("
                    INSERT INTO orders (client_id, product_id, quantity, unit_price, total, status)
                    VALUES (?, ?, ?, ?, ?, 'completado')
                ");
                $stmt->execute([
                    $_SESSION['client_id'],
                    $productId,
                    $quantity,
                    $amount,  // SEGURO: Precio de la BD
                    $total,
                ]);
                
                $orderId = $pdo->lastInsertId();
            } else {
                $orderId = 'DEMO';
            }
            
            $order = [
                'id' => $orderId,
                'product' => $product,
                'quantity' => $quantity,
                'unit_price' => $amount,
                'total' => $total,
                'attempted_price' => $priceFromClient,
            ];
            
            $success = true;
        }
        
    } catch (PDOException $e) {
        $error = "Error al procesar la compra: " . $e->getMessage();
    }
}

include __DIR__ . '/../shared/header.php';
?>

<main class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1">
                Compra <?= $success ? 'Procesada' : 'Fallida' ?>
                <span class="badge bg-success">SEGURO</span>
            </h1>
        </div>
        <a href="index.php" class="btn btn-outline-secondary">Volver a la tienda</a>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if ($success && $order): ?>
        
        <?php if ($attemptedTampering): ?>
        <!-- Alerta: Intento de price tampering BLOQUEADO -->
        <div class="alert alert-success d-flex align-items-center">
            <div class="fs-2 me-3">BLOQUEADO</div>
            <div>
                <strong>Intento de Price Tampering neutralizado</strong><br>
                <small>
                    Enviaste <code>price=<?= htmlspecialchars($order['attempted_price']) ?></code> 
                    pero el servidor uso el precio real de la BD: <strong>$<?= number_format($order['unit_price'], 0, ',', '.') ?></strong>
                </small>
            </div>
        </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-lg-8">
                <div class="card mb-4 border-success">
                    <div class="card-header bg-success text-white">
                        <strong>Orden #<?= $order['id'] ?></strong>
                        <span class="badge bg-light text-success ms-2">Precio verificado</span>
                    </div>
                    <div class="card-body">
                        <table class="table mb-0">
                            <tr>
                                <th>Producto</th>
                                <td><?= htmlspecialchars($order['product']['name']) ?></td>
                            </tr>
                            <tr>
                                <th>Cantidad</th>
                                <td><?= $order['quantity'] ?></td>
                            </tr>
                            <tr>
                                <th>Precio unitario</th>
                                <td>
                                    $<?= number_format($order['unit_price'], 0, ',', '.') ?>
                                    <small class="text-success">(de la BD)</small>
                                </td>
                            </tr>
                            <tr class="table-success">
                                <th>Total</th>
                                <td class="h5 mb-0">
                                    $<?= number_format($order['total'], 0, ',', '.') ?>
                                </td>
                            </tr>
                            <?php if ($attemptedTampering): ?>
                            <tr class="table-warning">
                                <th>Precio que intentaste enviar</th>
                                <td>
                                    <del class="text-danger">$<?= number_format((float)$order['attempted_price'], 0, ',', '.') ?></del>
                                    <span class="badge bg-secondary ms-2">IGNORADO</span>
                                </td>
                            </tr>
                            <?php endif; ?>
                        </table>
                    </div>
                </div>
                
                <?php if ($attemptedTampering): ?>
                <!-- Explicacion de por que no funciono -->
                <div class="card border-success">
                    <div class="card-header bg-success text-white">
                        ¿Por que no funciono el ataque?
                    </div>
                    <div class="card-body">
                        <p>El codigo del servidor hace esto:</p>
                        <pre class="secure p-3"><code>// procesar_secure.php (SEGURO)

// Solo acepta el product_id del cliente
$productId = $_POST['product_id'];  // <?= $productId ?>

// Obtiene el precio de la BASE DE DATOS
$product = getProductById($productId);
$amount = $product['price'];  // $<?= number_format($order['unit_price'], 0, ',', '.') ?> (de la BD)

// El campo "price" del POST se IGNORA COMPLETAMENTE
// $_POST['price'] = <?= $order['attempted_price'] ?>  <-- Ignorado

// Procesa el pago con el precio REAL
procesarPago($user, $amount);</code></pre>

                        <div class="alert alert-info mb-0">
                            <strong>Regla de oro:</strong> 
                            Nunca confies en datos del cliente para calcular precios, descuentos, 
                            totales o cualquier valor que afecte dinero. 
                            Siempre calcula todo en el servidor usando datos de tu base de datos.
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="col-lg-4">
                <!-- Request que se envio -->
                <div class="card">
                    <div class="card-header bg-white">
                        <strong>Request recibido</strong>
                    </div>
                    <div class="card-body">
                        <pre class="bg-light p-2 small"><code>POST /a06_checkout/procesar_secure.php

product_id=<?= htmlspecialchars($productId) ?>
price=<?= htmlspecialchars($priceFromClient ?? 'N/A') ?>  <-- IGNORADO
quantity=<?= htmlspecialchars($quantity) ?></code></pre>
                        
                        <div class="alert alert-success small mb-0 mt-2">
                            El campo <code>price</code> fue ignorado. 
                            El precio real vino de la BD.
                        </div>
                    </div>
                </div>
                
                <!-- Comparar con vulnerable -->
                <div class="card mt-3 border-danger">
                    <div class="card-header bg-danger text-white">
                        Version Vulnerable
                    </div>
                    <div class="card-body">
                        <p class="small mb-2">
                            La version vulnerable SI acepta el precio del cliente:
                        </p>
                        <form action="procesar.php" method="POST">
                            <input type="hidden" name="product_id" value="<?= htmlspecialchars($productId) ?>">
                            <input type="hidden" name="price" value="1">
                            <input type="hidden" name="quantity" value="<?= $quantity ?>">
                            <button type="submit" class="btn btn-outline-danger btn-sm w-100">
                                Probar con procesar.php (vulnerable)
                            </button>
                        </form>
                        <p class="small text-muted mt-2 mb-0">
                            Ahi SI va a cobrar $1.
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Diferencias clave -->
        <div class="card mt-4">
            <div class="card-header bg-dark text-white">
                Diferencia de diseno
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6 class="text-danger">procesar.php (INSECURE DESIGN)</h6>
                        <pre class="vulnerable p-2 small"><code>// Confia en el cliente
$amount = $_POST['price'];

// Procesa con ese monto
procesarPago($user, $amount);</code></pre>
                        <p class="small text-muted">
                            El cliente controla el precio. 
                            No hay forma de "parchear" esto - hay que redisenar.
                        </p>
                    </div>
                    <div class="col-md-6">
                        <h6 class="text-success">procesar_secure.php (SECURE DESIGN)</h6>
                        <pre class="secure p-2 small"><code>// Obtiene de la BD
$product = getProduct($_POST['product_id']);
$amount = $product['price'];

// Procesa con precio real
procesarPago($user, $amount);</code></pre>
                        <p class="small text-muted">
                            El servidor controla el precio. 
                            El cliente solo puede elegir QUE comprar, no A QUE PRECIO.
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
