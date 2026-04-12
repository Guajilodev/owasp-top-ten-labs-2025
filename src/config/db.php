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
 * @return PDO
 * @throws PDOException si la conexión falla
 */
function getDbConnection(): PDO
{
    static $pdo = null;
    
    if ($pdo === null) {
        $host = getenv('NEXO_DB_HOST') ?: 'db';
        $dbname = getenv('NEXO_DB_NAME') ?: 'nexo_labs';
        $user = getenv('NEXO_DB_USER') ?: 'nexo_user';
        $pass = getenv('NEXO_DB_PASS') ?: 'nexo_password_2025';
        
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
 * @return mysqli
 */
function getVulnerableConnection(): mysqli
{
    $host = getenv('NEXO_DB_HOST') ?: 'db';
    $dbname = getenv('NEXO_DB_NAME') ?: 'nexo_labs';
    $user = getenv('NEXO_DB_USER') ?: 'nexo_user';
    $pass = getenv('NEXO_DB_PASS') ?: 'nexo_password_2025';
    
    $conn = new mysqli($host, $user, $pass, $dbname);
    
    if ($conn->connect_error) {
        // VULNERABLE: expone detalles de conexión en el error
        die("Error de conexión: " . $conn->connect_error);
    }
    
    $conn->set_charset("utf8mb4");
    
    return $conn;
}
