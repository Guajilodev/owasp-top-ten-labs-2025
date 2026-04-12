<?php
/**
 * OWASP Top 10 Labs 2025 - A06: Insecure Design
 * Modulo: Checkout
 * 
 * Este archivo muestra el catalogo de planes y modulos de Nexo.
 * La vulnerabilidad esta en procesar.php que confia en el precio
 * enviado desde el cliente en lugar de obtenerlo de la BD.
 */

session_start();

// Simular usuario logueado
$_SESSION['user_id'] = $_SESSION['user_id'] ?? 2;
$_SESSION['username'] = $_SESSION['username'] ?? 'alice';
$_SESSION['client_id'] = $_SESSION['client_id'] ?? 1; // Constructora Gonzalez

require_once __DIR__ . '/../config/db.php';

$pageTitle = 'Tienda - Nexo';

// Info del lab para el panel lateral
$labInfo = [
    'id' => 'A06:2025',
    'name' => 'Insecure Design',
    'difficulty' => 'Intermedia',
    'description' => '
        <p>El checkout de Nexo envia el <strong>precio en un campo hidden</strong>:</p>
        <pre class="vulnerable small p-2">&lt;input type="hidden" 
       name="price" 
       value="299990"&gt;</pre>
        <p>El servidor <strong>confia en ese valor</strong> para procesar el pago, sin verificarlo contra la base de datos.</p>
        <p class="mb-0">Esto es un <strong>fallo de diseno</strong>, no de implementacion.</p>
    ',
    'exploit' => '# Metodo 1: Modificar el HTML con DevTools
# 1. Inspecciona el formulario
# 2. Cambia el value del input hidden "price"
# 3. Envia el formulario

# Metodo 2: Interceptar con Burp Suite
# 1. Activa el proxy
# 2. Envia el formulario
# 3. Modifica price=299990 por price=1
# 4. Forward

# Metodo 3: curl directo
curl -X POST http://localhost:8082/a06_checkout/procesar.php \\
  -d "product_id=3&price=1&quantity=1"',
    'prevention' => '// VULNERABLE (procesar.php):
$amount = $_POST[\'price\']; // Precio del cliente
procesarPago($user, $amount);

// SEGURO (procesar_secure.php):
$product = getProductById($_POST[\'product_id\']);
$amount = $product[\'price\']; // Precio de la BD
procesarPago($user, $amount);

// El precio NUNCA debe venir del cliente
// Siempre calcularlo en el servidor',
    'caseStudy' => [
        'title' => 'Marketplace Latinoamericano (2023)',
        'description' => 'Un marketplace permitio comprar articulos electronicos por $0.01 durante varias horas. El precio se enviaba desde el cliente. No se divulgo el nombre por acuerdo legal.'
    ],
    'cwes' => ['CWE-602', 'CWE-840'],
    'tools' => ['Burp Suite Intercept', 'DevTools', 'curl'],
];

// Obtener productos disponibles
try {
    $pdo = getDbConnection();
    $stmt = $pdo->query("SELECT * FROM products ORDER BY category, price");
    $products = $stmt->fetchAll();
    
    // Agrupar por categoria
    $byCategory = [];
    foreach ($products as $p) {
        $byCategory[$p['category']][] = $p;
    }
    
} catch (PDOException $e) {
    $products = [];
    $byCategory = [];
    $error = "Error al cargar productos: " . $e->getMessage();
}

// Mensaje de exito si viene de una compra
$success = $_GET['success'] ?? null;
$orderId = $_GET['order_id'] ?? null;
$paidAmount = $_GET['amount'] ?? null;

include __DIR__ . '/../shared/header.php';
?>

<main class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1">Tienda Nexo</h1>
            <p class="text-muted mb-0">
                Planes, modulos y servicios para tu empresa
            </p>
        </div>
        <a href="/" class="btn btn-outline-secondary">Volver al inicio</a>
    </div>

    <?php if ($success && $orderId): ?>
    <div class="alert alert-success d-flex align-items-center">
        <div class="fs-3 me-3">Compra exitosa!</div>
        <div>
            <strong>Orden #<?= htmlspecialchars($orderId) ?> procesada</strong><br>
            <small>
                Monto cobrado: <strong>$<?= number_format((int)$paidAmount, 0, ',', '.') ?></strong>
                <?php if ((int)$paidAmount < 1000): ?>
                <span class="badge bg-danger ms-2">PRICE TAMPERING!</span>
                <?php endif; ?>
            </small>
        </div>
    </div>
    
    <?php if ((int)$paidAmount < 1000): ?>
    <div class="alert alert-danger">
        <strong>Detectaste la vulnerabilidad!</strong><br>
        Pagaste $<?= number_format((int)$paidAmount, 0, ',', '.') ?> por un producto que costaba mucho mas. 
        El servidor acepto el precio que enviaste desde el cliente.
    </div>
    <?php endif; ?>
    <?php endif; ?>

    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- Hint para el lab -->
    <div class="alert alert-warning">
        <strong>Pista del lab:</strong> 
        Inspecciona el formulario de compra con DevTools (F12). 
        Busca el <code>&lt;input type="hidden" name="price"&gt;</code> y cambia su valor antes de enviar.
    </div>

    <!-- Catalogo por categoria -->
    <?php 
    $categoryNames = [
        'planes' => ['icon' => '', 'name' => 'Planes de Suscripcion'],
        'modulos' => ['icon' => '', 'name' => 'Modulos Adicionales'],
        'servicios' => ['icon' => '', 'name' => 'Servicios Profesionales'],
    ];
    
    foreach ($byCategory as $category => $items): 
        $catInfo = $categoryNames[$category] ?? ['icon' => '', 'name' => ucfirst($category)];
    ?>
    
    <h4 class="mt-4 mb-3"><?= $catInfo['icon'] ?> <?= $catInfo['name'] ?></h4>
    
    <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4 mb-4">
        <?php foreach ($items as $product): ?>
        <div class="col">
            <div class="card h-100">
                <div class="card-body">
                    <h5 class="card-title"><?= htmlspecialchars($product['name']) ?></h5>
                    <p class="card-text small text-muted">
                        <?= htmlspecialchars($product['description']) ?>
                    </p>
                    <p class="h4 text-primary mb-3">
                        $<?= number_format($product['price'], 0, ',', '.') ?>
                    </p>
                    
                    <!-- Formulario de compra VULNERABLE -->
                    <form action="procesar.php" method="POST" class="purchase-form">
                        <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
                        <!-- VULNERABLE: El precio viene del cliente -->
                        <input type="hidden" name="price" value="<?= $product['price'] ?>">
                        <input type="hidden" name="quantity" value="1">
                        
                        <button type="submit" class="btn btn-primary w-100">
                            Comprar
                        </button>
                    </form>
                </div>
                <div class="card-footer bg-transparent">
                    <small class="text-muted">
                        ID: <?= $product['id'] ?> | 
                        Stock: <?= $product['stock'] ?>
                    </small>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    
    <?php endforeach; ?>

    <!-- Comparacion vulnerable vs seguro -->
    <div class="row mt-4">
        <div class="col-md-6">
            <div class="card border-danger">
                <div class="card-header bg-danger text-white">
                    Version Vulnerable
                </div>
                <div class="card-body">
                    <p class="small mb-2">Los formularios de arriba envian a:</p>
                    <code>procesar.php</code>
                    <p class="small text-muted mt-2 mb-0">
                        Toma el precio del POST, no verifica contra la BD.
                    </p>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card border-success">
                <div class="card-header bg-success text-white">
                    Version Segura
                </div>
                <div class="card-body">
                    <p class="small mb-2">Proba tambien:</p>
                    <a href="procesar_secure.php?demo=1" class="btn btn-outline-success btn-sm">
                        procesar_secure.php
                    </a>
                    <p class="small text-muted mt-2 mb-0">
                        Obtiene el precio de la BD usando solo el product_id.
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Codigo del formulario para que lo vean -->
    <div class="card mt-4">
        <div class="card-header bg-dark text-white">
            Codigo del formulario (inspeccionalo)
        </div>
        <div class="card-body">
            <pre class="vulnerable p-3 small"><code>&lt;form action="procesar.php" method="POST"&gt;
    &lt;input type="hidden" name="product_id" value="3"&gt;
    &lt;!-- VULNERABLE: El precio viene del HTML --&gt;
    &lt;input type="hidden" name="price" value="299990"&gt;
    &lt;input type="hidden" name="quantity" value="1"&gt;
    &lt;button type="submit"&gt;Comprar&lt;/button&gt;
&lt;/form&gt;

&lt;!-- Cambia el value de "price" a 1 y envia el formulario --&gt;</code></pre>
        </div>
    </div>
</main>

<?php 
include __DIR__ . '/../shared/lab_panel.php';
include __DIR__ . '/../shared/footer.php'; 
?>
