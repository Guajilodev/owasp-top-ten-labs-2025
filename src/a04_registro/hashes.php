<?php
/**
 * OWASP Top 10 Labs 2025 - A04: Cryptographic Failures
 * Módulo: Ver hashes de la base de datos
 * 
 * Este archivo simula haber obtenido acceso a los hashes
 * (vía SQLi, backup expuesto, o acceso a la BD)
 */

session_start();
require_once __DIR__ . '/../config/db.php';

$pageTitle = 'Hashes de Usuarios - Nexo';

// Info del lab
$labInfo = [
    'id' => 'A04:2025',
    'name' => 'Cryptographic Failures',
    'difficulty' => 'Básica',
    'description' => '
        <p>Estos son los hashes MD5 de la base de datos.</p>
        <p>Copialos y pegalos en <a href="https://crackstation.net/" target="_blank">CrackStation</a> para crackearlos.</p>
    ',
    'exploit' => '# Copiá estos hashes a CrackStation:
0192023a7bbd73250516f069df18b500
482c811da5d5b4bc6d497ffa98491e38
5ebe2294ecd0e0f08eab7690d2a6ee69
e8d95a51f3af4a3b134bf6bb680a213a

# En segundos vas a tener las passwords',
    'prevention' => '// Usar bcrypt en lugar de MD5:
$hash = password_hash($pass, PASSWORD_BCRYPT);

// Bcrypt es LENTO por diseño
// Incluye salt automático
// Resistente a rainbow tables',
    'caseStudy' => [
        'title' => 'LinkedIn (2012)',
        'description' => 'SHA-1 sin salt. 117M passwords crackeadas.'
    ],
    'cwes' => ['CWE-916', 'CWE-327'],
    'tools' => ['CrackStation', 'Hashcat'],
];

// Obtener usuarios con sus hashes
try {
    $pdo = getDbConnection();
    $stmt = $pdo->query("SELECT id, username, email, password_md5, role, created_at FROM users ORDER BY id");
    $users = $stmt->fetchAll();
} catch (PDOException $e) {
    $users = [];
    $error = $e->getMessage();
}

include __DIR__ . '/../shared/header.php';
?>

<main class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1">🔓 Hashes de Usuarios</h1>
            <p class="text-muted mb-0">Datos extraídos de la tabla <code>users</code></p>
        </div>
        <a href="index.php" class="btn btn-outline-secondary">← Volver al registro</a>
    </div>
    
    <div class="alert alert-danger">
        <strong>🚨 Simulación de breach:</strong> 
        Estás viendo los hashes como si hubieras explotado SQLi (A05) o accedido al backup (A02).
    </div>
    
    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <strong>Tabla: users</strong>
                    <span class="badge bg-danger"><?= count($users) ?> registros</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <thead class="table-dark">
                            <tr>
                                <th>id</th>
                                <th>username</th>
                                <th>email</th>
                                <th>password_md5</th>
                                <th>role</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                            <tr>
                                <td><?= $user['id'] ?></td>
                                <td><code><?= htmlspecialchars($user['username']) ?></code></td>
                                <td class="small"><?= htmlspecialchars($user['email']) ?></td>
                                <td>
                                    <code class="text-danger user-select-all"><?= $user['password_md5'] ?></code>
                                </td>
                                <td><span class="badge bg-secondary"><?= $user['role'] ?></span></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Hashes para copiar -->
            <div class="card mt-4">
                <div class="card-header bg-warning">
                    📋 Hashes para crackear (copiá y pegá)
                </div>
                <div class="card-body">
                    <textarea class="form-control font-monospace" rows="6" readonly onclick="this.select()"><?php 
foreach ($users as $user) {
    echo $user['password_md5'] . "\n";
}
?></textarea>
                    <small class="text-muted">
                        Pegá estos hashes en <a href="https://crackstation.net/" target="_blank">crackstation.net</a>
                    </small>
                </div>
            </div>
        </div>
        
        <div class="col-lg-4">
            <!-- Resultados esperados -->
            <div class="card border-success">
                <div class="card-header bg-success text-white">
                    ✅ Resultados esperados
                </div>
                <div class="card-body">
                    <p class="small">Después de crackear, deberías obtener:</p>
                    <table class="table table-sm small">
                        <tr>
                            <td><code>admin</code></td>
                            <td>→</td>
                            <td><strong>admin123</strong></td>
                        </tr>
                        <tr>
                            <td><code>alice</code></td>
                            <td>→</td>
                            <td><strong>password123</strong></td>
                        </tr>
                        <tr>
                            <td><code>bob</code></td>
                            <td>→</td>
                            <td><strong>secret</strong></td>
                        </tr>
                        <tr>
                            <td><code>carlos</code></td>
                            <td>→</td>
                            <td><strong>nexo2024</strong></td>
                        </tr>
                    </table>
                    <p class="small text-muted mb-0">
                        Todas son passwords comunes que están en rainbow tables.
                    </p>
                </div>
            </div>
            
            <!-- Por qué bcrypt -->
            <div class="card mt-3">
                <div class="card-header bg-white">
                    🛡️ ¿Por qué bcrypt?
                </div>
                <div class="card-body small">
                    <ul class="mb-0">
                        <li><strong>Lento por diseño:</strong> ~100ms por hash vs nanosegundos de MD5</li>
                        <li><strong>Salt automático:</strong> cada hash es único aunque la password sea igual</li>
                        <li><strong>Cost factor:</strong> se puede aumentar con el tiempo</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</main>

<?php 
include __DIR__ . '/../shared/lab_panel.php';
include __DIR__ . '/../shared/footer.php'; 
?>
