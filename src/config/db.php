<?php
/**
 * OWASP Top 10 Labs 2025 - Nexo
 * Conexión a base de datos
 * 
 * NOTA: Este archivo usa variables de entorno. En producción,
 * las credenciales vienen del .env via Docker Compose.
 */

// Prevenir acceso directo
if (basename($_SERVER['PHP_SELF']) === 'db.php') {
    http_response_code(403);
    exit('Acceso directo no permitido');
}

/**
 * Obtiene una conexión PDO a la base de datos
 * 
 * SEGURIDAD: No hay credenciales por defecto. Si no están configuradas
 * las variables de entorno, el sistema FALLA inmediatamente.
 * Esto es intencional - nunca debe haber fallback a credenciales hardcodeadas.
 * 
 * @return PDO
 * @throws PDOException si la conexión falla
 * @throws RuntimeException si faltan variables de entorno
 */
function getDbConnection(): PDO
{
    static $pdo = null;
    
    if ($pdo === null) {
        $host = getenv('NEXO_DB_HOST');
        $dbname = getenv('NEXO_DB_NAME');
        $user = getenv('NEXO_DB_USER');
        $pass = getenv('NEXO_DB_PASS');
        
        // SEGURIDAD: Fail fast si faltan credenciales
        // Nunca usar valores por defecto - eso es un riesgo de seguridad
        $missing = [];
        if (!$host) $missing[] = 'NEXO_DB_HOST';
        if (!$dbname) $missing[] = 'NEXO_DB_NAME';
        if (!$user) $missing[] = 'NEXO_DB_USER';
        if (!$pass) $missing[] = 'NEXO_DB_PASS';
        
        if (!empty($missing)) {
            $errorMsg = 'ERROR: Faltan variables de entorno: ' . implode(', ', $missing) . '. ';
            $errorMsg .= 'Copia .env.example a .env y configura las credenciales.';
            
            // En desarrollo, mostrar error claro
            if (php_sapi_name() !== 'cli') {
                http_response_code(500);
                echo '<div style="font-family:monospace;background:#fee;border:2px solid #c00;padding:20px;margin:20px;">';
                echo '<h2>⚠️ Configuración incompleta</h2>';
                echo '<p>' . htmlspecialchars($errorMsg) . '</p>';
                echo '<pre>cp .env.example .env</pre>';
                echo '</div>';
            }
            throw new RuntimeException($errorMsg);
        }
        
        $dsn = "mysql:host={$host};dbname={$dbname};charset=utf8mb4";
        
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        
        $pdo = new PDO($dsn, $user, $pass, $options);
    }
    
    return $pdo;
}

/**
 * Versión VULNERABLE de conexión usando mysqli
 * Usada en labs que requieren mostrar errores específicos
 * 
 * NOTA: Esta función es INTENCIONALMENTE vulnerable para demos.
 * El error handling expone información que no debería.
 * Pero las credenciales DEBEN venir de env vars (sin fallback).
 * 
 * @return mysqli
 * @throws RuntimeException si faltan variables de entorno
 */
function getVulnerableConnection(): mysqli
{
    $host = getenv('NEXO_DB_HOST');
    $dbname = getenv('NEXO_DB_NAME');
    $user = getenv('NEXO_DB_USER');
    $pass = getenv('NEXO_DB_PASS');
    
    // Incluso la versión "vulnerable" requiere credenciales de env vars
    // La vulnerabilidad es en el ERROR HANDLING, no en las credenciales hardcodeadas
    if (!$host || !$dbname || !$user || !$pass) {
        throw new RuntimeException('Variables de entorno de BD no configuradas');
    }
    
    $conn = new mysqli($host, $user, $pass, $dbname);
    
    if ($conn->connect_error) {
        // VULNERABLE: expone detalles de conexión en el error
        // Esto es INTENCIONAL para demostrar A02/A10
        die("Error de conexión: " . $conn->connect_error);
    }
    
    $conn->set_charset("utf8mb4");
    
    return $conn;
}
