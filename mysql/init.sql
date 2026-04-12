-- ============================================
-- OWASP Top 10 Labs 2025 - Nexo
-- Datos semilla - Fuente de verdad del estado limpio
-- ============================================
-- Este archivo se ejecuta:
-- 1. Al crear el contenedor por primera vez
-- 2. Cada 4 horas via cron job para resetear el estado
--
-- Los datos son FICTICIOS pero creibles. Nombres chilenos,
-- RUTs con formato valido, montos en CLP realistas.
-- ============================================

-- Limpiar tablas si existen (para el reset del cron)
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS transfers;
DROP TABLE IF EXISTS wallets;
DROP TABLE IF EXISTS orders;
DROP TABLE IF EXISTS invoices;
DROP TABLE IF EXISTS clients;
DROP TABLE IF EXISTS products;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS plugins;
DROP TABLE IF EXISTS activity_log;
SET FOREIGN_KEY_CHECKS = 1;

-- ============================================
-- USUARIOS DE NEXO
-- ============================================
-- Passwords almacenadas en MD5 sin salt (VULNERABLE - Lab A04)
-- Esto es INTENCIONALMENTE inseguro para demostrar el problema

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL,
    password_md5 VARCHAR(32) NOT NULL COMMENT 'MD5 sin salt - VULNERABLE',
    role ENUM('admin', 'user', 'viewer') DEFAULT 'user',
    session_id INT DEFAULT NULL COMMENT 'ID secuencial - VULNERABLE (Lab A07)',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Passwords en texto plano (para referencia del estudiante):
-- admin    -> admin123      (Lab A02: creds por defecto)
-- alice    -> password123
-- bob      -> secret
-- carlos   -> nexo2024      (Lab A04: facil de crackear)
-- diana    -> D14n4.2025
INSERT INTO users (username, email, password_md5, role, session_id) VALUES
('admin', 'admin@nexo.cl', '0192023a7bbd73250516f069df18b500', 'admin', 1001),
('alice', 'alice.mendez@gmail.com', '482c811da5d5b4bc6d497ffa98491e38', 'user', 1002),
('bob', 'roberto.silva@empresa.cl', '5ebe2294ecd0e0f08eab7690d2a6ee69', 'user', 1003),
('carlos', 'carlos.gonzalez@constructora.cl', 'e8d95a51f3af4a3b134bf6bb680a213a', 'user', 1004),
('diana', 'diana.rojas@retail.cl', '7c6a180b36896a65c3ed5c1f3a9d4e2b', 'viewer', 1005);

-- ============================================
-- CLIENTES DE NEXO (empresas)
-- ============================================
-- Estas son las empresas que usan Nexo como SaaS

CREATE TABLE clients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    email VARCHAR(100) NOT NULL,
    rut VARCHAR(12) NOT NULL COMMENT 'RUT chileno formato XX.XXX.XXX-X',
    phone VARCHAR(20),
    address VARCHAR(200),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO clients (id, name, email, rut, phone, address) VALUES
(1, 'Constructora González y Asociados SpA', 'contacto@gonzalezasociados.cl', '76.543.210-K', '+56 2 2345 6789', 'Av. Providencia 1234, Of. 501, Santiago'),
(2, 'Importadora del Pacífico Ltda.', 'ventas@importadorapacifico.cl', '77.891.234-5', '+56 2 2987 6543', 'Blanco 1199, Valparaíso'),
(3, 'Comercial Fernández e Hijos', 'administracion@fernandez.cl', '78.456.789-0', '+56 41 222 3344', 'O\'Higgins 567, Concepción'),
(4, 'Servicios Técnicos Martínez SPA', 'gerencia@stmartinez.cl', '76.234.567-8', '+56 2 2111 2233', 'Las Condes 8899, Santiago'),
(5, 'Distribuidora Nacional de Alimentos S.A.', 'compras@dinalimentos.cl', '79.012.345-6', '+56 2 2444 5566', 'Autopista Central 5000, Quilicura'),
(6, 'Asesorías Legales Pérez & Muñoz', 'contacto@perezymunoz.cl', '77.654.321-0', '+56 2 2777 8899', 'Moneda 920, Of. 1201, Santiago'),
(7, 'Transportes Ruta Sur Ltda.', 'operaciones@rutasur.cl', '76.789.012-3', '+56 45 234 5678', 'Camino a Melipilla Km 25, Maipú'),
(8, 'Clínica Dental Sonrisas SpA', 'recepcion@sonrisas.cl', '78.901.234-5', '+56 2 2333 4455', 'Irarrázaval 2580, Ñuñoa');

-- ============================================
-- FACTURAS
-- ============================================
-- Lab A01: IDOR - el usuario puede ver facturas de otros cambiando el ID

CREATE TABLE invoices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    client_id INT NOT NULL,
    user_id INT NOT NULL COMMENT 'Usuario de Nexo que creo la factura',
    amount DECIMAL(12,2) NOT NULL,
    description VARCHAR(255) NOT NULL,
    invoice_date DATE NOT NULL,
    due_date DATE NOT NULL,
    status ENUM('pendiente', 'pagada', 'vencida', 'anulada') DEFAULT 'pendiente',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES clients(id),
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Facturas de diferentes usuarios (Lab A01: alice no deberia ver las de bob)
INSERT INTO invoices (id, client_id, user_id, amount, description, invoice_date, due_date, status) VALUES
-- Facturas de alice (user_id=2)
(1040, 1, 2, 4250000.00, 'Servicio de consultoría mensual - Marzo 2025', '2025-03-15', '2025-04-15', 'pagada'),
(1041, 1, 2, 4250000.00, 'Servicio de consultoría mensual - Abril 2025', '2025-04-15', '2025-05-15', 'pendiente'),
(1042, 3, 2, 1890000.00, 'Implementación módulo de inventario', '2025-04-01', '2025-04-30', 'pendiente'),
-- Facturas de bob (user_id=3) - alice NO deberia poder verlas
(1043, 2, 3, 7500000.00, 'Desarrollo sistema de seguimiento de envíos', '2025-03-20', '2025-04-20', 'vencida'),
(1044, 5, 3, 2340000.00, 'Mantenimiento anual plataforma e-commerce', '2025-04-10', '2025-05-10', 'pendiente'),
(1045, 7, 3, 15600000.00, 'Sistema de gestión de flota v2.0', '2025-02-28', '2025-03-28', 'pagada'),
-- Facturas de carlos (user_id=4)
(1046, 4, 4, 890000.00, 'Soporte técnico Q1 2025', '2025-04-01', '2025-04-30', 'pendiente'),
(1047, 6, 4, 3200000.00, 'Portal de clientes con firma electrónica', '2025-03-25', '2025-04-25', 'pagada'),
(1048, 8, 4, 1450000.00, 'Sistema de agendamiento online', '2025-04-05', '2025-05-05', 'pendiente');

-- ============================================
-- PRODUCTOS (para Lab A06 - Checkout)
-- ============================================

CREATE TABLE products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    price DECIMAL(10,2) NOT NULL,
    stock INT DEFAULT 100,
    category VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO products (id, name, description, price, stock, category) VALUES
(1, 'Nexo Starter', 'Plan básico para emprendedores. Hasta 5 usuarios, 1GB almacenamiento.', 29990.00, 999, 'planes'),
(2, 'Nexo Professional', 'Plan profesional para PYMES. Hasta 25 usuarios, 10GB almacenamiento, soporte prioritario.', 89990.00, 999, 'planes'),
(3, 'Nexo Enterprise', 'Plan empresarial sin límites. Usuarios ilimitados, 100GB, SLA 99.9%.', 299990.00, 999, 'planes'),
(4, 'Módulo Facturación Electrónica', 'Integración con SII para boletas y facturas electrónicas.', 49990.00, 500, 'modulos'),
(5, 'Módulo Inventario Avanzado', 'Control de stock multi-bodega con alertas automáticas.', 39990.00, 500, 'modulos'),
(6, 'Módulo CRM', 'Gestión de clientes, pipeline de ventas, seguimiento de oportunidades.', 59990.00, 500, 'modulos'),
(7, 'Capacitación Presencial (4 hrs)', 'Capacitación en sus oficinas para hasta 10 personas.', 350000.00, 50, 'servicios'),
(8, 'Migración de Datos', 'Importación de datos desde su sistema actual a Nexo.', 190000.00, 100, 'servicios');

-- ============================================
-- PEDIDOS
-- ============================================

CREATE TABLE orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    client_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT DEFAULT 1,
    unit_price DECIMAL(10,2) NOT NULL COMMENT 'Precio al momento de la compra',
    total DECIMAL(12,2) NOT NULL,
    status ENUM('pendiente', 'procesando', 'completado', 'cancelado') DEFAULT 'pendiente',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES clients(id),
    FOREIGN KEY (product_id) REFERENCES products(id)
);

INSERT INTO orders (client_id, product_id, quantity, unit_price, total, status) VALUES
(1, 3, 1, 299990.00, 299990.00, 'completado'),
(1, 4, 1, 49990.00, 49990.00, 'completado'),
(2, 2, 1, 89990.00, 89990.00, 'completado'),
(3, 1, 1, 29990.00, 29990.00, 'procesando'),
(4, 2, 1, 89990.00, 89990.00, 'completado'),
(4, 6, 1, 59990.00, 59990.00, 'completado'),
(5, 3, 1, 299990.00, 299990.00, 'pendiente'),
(6, 7, 2, 350000.00, 700000.00, 'completado');

-- ============================================
-- WALLETS (Lab A10 - Transferencias)
-- ============================================

CREATE TABLE wallets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    balance DECIMAL(12,2) DEFAULT 0.00,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

INSERT INTO wallets (user_id, balance) VALUES
(1, 1000000.00),  -- admin
(2, 250000.00),   -- alice
(3, 180000.00),   -- bob
(4, 75000.00),    -- carlos
(5, 50000.00);    -- diana

-- ============================================
-- TRANSFERENCIAS (Lab A10)
-- ============================================

CREATE TABLE transfers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    from_user_id INT NOT NULL,
    to_user_id INT NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    status ENUM('pendiente', 'completada', 'fallida', 'revertida') DEFAULT 'pendiente',
    error_message VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (from_user_id) REFERENCES users(id),
    FOREIGN KEY (to_user_id) REFERENCES users(id)
);

INSERT INTO transfers (from_user_id, to_user_id, amount, status) VALUES
(1, 2, 50000.00, 'completada'),
(2, 3, 25000.00, 'completada'),
(3, 4, 10000.00, 'completada'),
(1, 4, 15000.00, 'completada');

-- ============================================
-- PLUGINS (Lab A03 - Supply Chain)
-- ============================================

CREATE TABLE plugins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(50) NOT NULL UNIQUE,
    version VARCHAR(20) NOT NULL,
    vendor VARCHAR(100) NOT NULL,
    description TEXT,
    downloads INT DEFAULT 0,
    rating DECIMAL(2,1) DEFAULT 0.0,
    is_verified BOOLEAN DEFAULT FALSE,
    installed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    checksum VARCHAR(64) COMMENT 'SHA256 del paquete'
);

INSERT INTO plugins (name, slug, version, vendor, description, downloads, rating, is_verified, checksum) VALUES
('Nexo PDF Export', 'nexo-pdf-export', '2.3.1', 'Nexo Labs', 'Genera PDFs profesionales de facturas y reportes. Compatible con todos los módulos.', 12847, 4.8, TRUE, 'a3f2c1d4e5b6789012345678901234567890123456789012345678901234abcd'),
('Nexo Charts Pro', 'nexo-charts', '1.5.0', 'DataViz SpA', 'Gráficos interactivos y dashboards para tus reportes.', 8234, 4.5, TRUE, 'b4e3d2c1f6a7890123456789012345678901234567890123456789012345bcde'),
('Nexo Backup Cloud', 'nexo-backup', '3.0.2', 'CloudSafe Inc', 'Respaldos automáticos a AWS S3 o Google Cloud Storage.', 5621, 4.7, TRUE, 'c5f4e3d2a7b8901234567890123456789012345678901234567890123456cdef'),
('Nexo WhatsApp Notifier', 'nexo-whatsapp', '2.1.0', 'MsgAPI Solutions', 'Envía notificaciones automáticas por WhatsApp a tus clientes.', 15432, 4.2, TRUE, 'd6a5f4e3b8c9012345678901234567890123456789012345678901234567defa');

-- ============================================
-- LOG DE ACTIVIDAD (Lab A09)
-- ============================================

CREATE TABLE activity_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    action VARCHAR(100) NOT NULL,
    details TEXT,
    ip_address VARCHAR(45),
    user_agent VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Log con entradas reales (Lab A09 muestra que NO se loguean intentos fallidos)
INSERT INTO activity_log (user_id, action, details, ip_address) VALUES
(1, 'login_success', 'Admin login', '192.168.1.100'),
(2, 'login_success', 'User login', '201.220.45.123'),
(2, 'invoice_view', 'Viewed invoice #1040', '201.220.45.123'),
(2, 'invoice_view', 'Viewed invoice #1041', '201.220.45.123'),
(3, 'login_success', 'User login', '186.45.78.90'),
(3, 'invoice_create', 'Created invoice #1045', '186.45.78.90'),
(1, 'settings_change', 'Updated company logo', '192.168.1.100');
-- NOTA: No hay login_failed en este log - eso es el problema del Lab A09

-- ============================================
-- INDICES para performance
-- ============================================

CREATE INDEX idx_invoices_user ON invoices(user_id);
CREATE INDEX idx_invoices_client ON invoices(client_id);
CREATE INDEX idx_orders_client ON orders(client_id);
CREATE INDEX idx_transfers_from ON transfers(from_user_id);
CREATE INDEX idx_transfers_to ON transfers(to_user_id);
CREATE INDEX idx_activity_user ON activity_log(user_id);
CREATE INDEX idx_activity_created ON activity_log(created_at);
