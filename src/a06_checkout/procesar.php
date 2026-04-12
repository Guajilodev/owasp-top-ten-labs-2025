<?php
/**
 * OWASP Top 10 Labs 2025 - A06: Insecure Design
 * Modulo: Checkout - Procesar compra VULNERABLE
 * 
 * VULNERABILIDAD:
 * El precio se toma del POST enviado por el cliente, NO de la base de datos.
 * Un atacante puede modificar el valor del campo hidden "price" para pagar
 * cualquier monto que quiera.
 * 
 * Esto NO es un bug de implementacion - es un FALLO DE DISENO.
 * El flujo fue disenado confiando en el cliente para enviar el precio correcto.
 * 
 * NO USAR EN PRODUCCION - Solo para fines educativos
 */

session_start();

$_SESSION['user_id'] = $_SESSION['user_id'] ?? 2;
$_SESSION['username'] = $_SESSION['username'] ?? 'alice';
$_SESSION['client_id'] = $_SESSION['client_id'] ?? 1;

require_once __DIR__ . '/../config/db.php';

$pageTitle = 'Procesando Compra - Nexo';

$labInfo = [
    'id' => 'A06:2025',
    'name' => 'Insecure Design',
    'difficulty' => 'Intermedia',
    'description' => '
        <p>Este endpoint <strong>confia en el precio enviado por el cliente</strong>.</p>
        <p>No verifica el precio contra la base de datos antes de procesar el pago.</p>
        <p class="mb-0">Resultado: podes comprar cualquier producto por el precio que quieras.</p>
    ',
    'exploit' => '# El servidor acepta cualquier precio
curl -X POST http://localhost:8082/a06_checkout/procesar.php \\
  -d "product_id=3&price=1&quantity=1"

# Comprar Nexo Enterprise ($299.990) por $1',
    'prevention' => '// ESTE ARCHIVO (vulnerable):
$amount = $_POST[\'price\']; // Del cliente
procesarPago($user, $amount);

// procesar_secure.php (seguro):
$product = getProductById($_POST[\'product_id\']);
$amount = $product[\'price\']; // De la BD
procesarPago($user, $amount);',
    'caseStudy' => [
        'title' => 'Marketplace Latinoamericano (2023)',
        'description' => 'Articulos electronicos vendidos por $0.01 durante horas.'
    ],
    'cwes' => ['CWE-602', 'CWE-840'],
    'tools' => ['Burp Suite', 'curl', 'DevTools'],
    'secureVersion' => 'procesar_secure.php',
];

// Obtener datos del POST
$productId = $_POST['product_id'] ?? null;
$priceFromClient = $_POST['price'] ?? null; // VULNERABLE: Precio del cliente
$quantity = (int)($_POST['quantity'] ?? 1);

$error = null;
$success = false;
$order = null;
$product = null;
$isPriceTampering = false;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

if (!$productId || !$priceFromClient) {
    $error = "Datos de compra incompletos";
} else {
    try {
        $pdo = getDbConnection();
        
        // Obtener el producto (para mostrar info, no para validar precio)
        $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
        $stmt->execute([$productId]);
        $product = $stmt->fetch();
        
        if (!$product) {
            $error = "Producto no encontrado";
        } else {
            // VULNERABLE: Usar el precio enviado por el cliente
            // Deberia ser: $amount = $product['price'];
            $amount = (float)$priceFromClient;
            $total = $amount * $quantity;
            
            // Detectar price tampering (para mostrar alerta educativa)
            $realPrice = $product['price'];
            if ($amount < $realPrice * 0.9) { // Mas de 10% de descuento = sospechoso
                $isPriceTampering = true;
            }
            
            // "Procesar" el pago (simulado)
            // En un sistema real aqui iria la llamada a Stripe, Transbank, etc.
            $paymentSuccessful = true; // Simulamos que siempre funciona
            
            if ($paymentSuccessful) {
                // Crear la orden con el precio que envio el cliente
                $stmt = $pdo->prepare("
                    INSERT INTO orders (client_id, product_id, quantity, unit_price, total, status)
                    VALUES (?, ?, ?, ?, ?, 'completado')
                ");
                $stmt->execute([
                    $_SESSION['client_id'],
                    $productId,
                    $quantity,
                    $amount,      // VULNERABLE: Precio del cliente
                    $total,       // Total calculado con precio del cliente
                ]);
                
                $orderId = $pdo->lastInsertId();
                
                $order = [
                    'id' => $orderId,
                    'product' => $product,
                    'quantity' => $quantity,
                    'unit_price' => $amount,
                    'total' => $total,
                    'real_price' => $realPrice,
                    'real_total' => $realPrice * $quantity,
                    'savings' => ($realPrice * $quantity) - $total,
                ];
                
                $success = true;
            }
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
                <?= $success ? 'Compra Exitosa' : 'Error en la Compra' ?>
            </h1>
        </div>
        <a href="index.php" class="btn btn-outline-secondary">Volver a la tienda</a>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if ($success && $order): ?>
        
        <?php if ($isPriceTampering): ?>
        <!-- ALERTA: Price tampering detectado -->
        <div class="alert alert-danger d-flex align-items-center">
            <div class="fs-1 me-3">PRICE TAMPERING!</div>
            <div>
                <strong>Explotaste la vulnerabilidad Insecure Design</strong><br>
                <small>
                    El servidor acepto el precio que enviaste ($<?= number_format($order['unit_price'], 0, ',', '.') ?>) 
                    en lugar del precio real ($<?= number_format($order['real_price'], 0, ',', '.') ?>).
                </small>
            </div>
        </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-lg-8">
                <div class="card mb-4 <?= $isPriceTampering ? 'border-danger' : 'border-success' ?>">
                    <div class="card-header <?= $isPriceTampering ? 'bg-danger' : 'bg-success' ?> text-white">
                        <strong>Orden #<?= $order['id'] ?></strong>
                        <?php if ($isPriceTampering): ?>
                            <span class="badge bg-dark ms-2">VULNERABLE</span>
                        <?php endif; ?>
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
                                <th>Precio unitario cobrado</th>
                                <td class="<?= $isPriceTampering ? 'text-danger fw-bold' : '' ?>">
                                    $<?= number_format($order['unit_price'], 0, ',', '.') ?>
                                    <?php if ($isPriceTampering): ?>
                                        <small class="text-muted">(deberia ser $<?= number_format($order['real_price'], 0, ',', '.') ?>)</small>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr class="table-<?= $isPriceTampering ? 'danger' : 'success' ?>">
                                <th>Total cobrado</th>
                                <td class="h5 mb-0">
                                    $<?= number_format($order['total'], 0, ',', '.') ?>
                                </td>
                            </tr>
                            <?php if ($isPriceTampering): ?>
                            <tr class="table-warning">
                                <th>Ahorro ilicito</th>
                                <td class="text-danger fw-bold">
                                    $<?= number_format($order['savings'], 0, ',', '.') ?>
                                    <small class="text-muted">(<?= round(($order['savings'] / $order['real_total']) * 100, 1) ?>% de descuento)</small>
                                </td>
                            </tr>
                            <?php endif; ?>
                        </table>
                    </div>
                </div>
                
                <?php if ($isPriceTampering): ?>
                <!-- Explicacion tecnica -->
                <div class="card border-dark">
                    <div class="card-header bg-dark text-white">
                        ¿Por que funciono?
                    </div>
                    <div class="card-body">
                        <p>El codigo del servidor hizo esto:</p>
                        <pre class="vulnerable p-3"><code>// procesar.php (VULNERABLE)

// Toma el precio del POST, no de la BD
$amount = $_POST['price'];  // Tu enviaste: <?= number_format($order['unit_price'], 0, ',', '.') ?>

// Procesa el pago con ESE monto
procesarPago($user, $amount);

// Guarda la orden con ESE monto
INSERT INTO orders (..., unit_price, total)
VALUES (..., <?= $order['unit_price'] ?>, <?= $order['total'] ?>);</code></pre>

                        <p class="mb-2"><strong>Lo que DEBERIA hacer:</strong></p>
                        <pre class="secure p-3"><code>// procesar_secure.php (SEGURO)

// Obtener el precio de la BASE DE DATOS
$product = getProductById($_POST['product_id']);
$amount = $product['price'];  // De la BD: <?= number_format($order['real_price'], 0, ',', '.') ?>

// Procesa el pago con el precio REAL
procesarPago($user, $amount);</code></pre>
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
                        <pre class="bg-light p-2 small"><code>POST /a06_checkout/procesar.php

product_id=<?= htmlspecialchars($productId) ?>
price=<?= htmlspecialchars($priceFromClient) ?>
quantity=<?= htmlspecialchars($quantity) ?></code></pre>
                        
                        <?php if ($isPriceTampering): ?>
                        <div class="alert alert-warning small mb-0 mt-2">
                            El campo <code>price</code> fue modificado. 
                            El servidor lo acepto sin validar.
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Probar version segura -->
                <div class="card mt-3 border-success">
                    <div class="card-header bg-success text-white">
                        Version Segura
                    </div>
                    <div class="card-body">
                        <p class="small mb-2">
                            La version segura ignora el precio del POST y lo obtiene de la BD:
                        </p>
                        <form action="procesar_secure.php" method="POST">
                            <input type="hidden" name="product_id" value="<?= htmlspecialchars($productId) ?>">
                            <input type="hidden" name="price" value="1">
                            <input type="hidden" name="quantity" value="<?= $quantity ?>">
                            <button type="submit" class="btn btn-outline-success btn-sm w-100">
                                Probar con procesar_secure.php
                            </button>
                        </form>
                        <p class="small text-muted mt-2 mb-0">
                            Aunque envies price=1, cobrara el precio real.
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
