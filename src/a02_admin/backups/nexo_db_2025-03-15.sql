-- ============================================
-- NEXO DATABASE BACKUP
-- Fecha: 2025-03-15 03:00:00
-- Host: db
-- Database: nexo_labs
-- ============================================
-- 
-- ⚠️ ESTE ARCHIVO NO DEBERÍA SER ACCESIBLE PÚBLICAMENTE
-- El hecho de que lo estés leyendo es una vulnerabilidad (A02)
--

-- Estructura de tabla users
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password_md5` varchar(32) NOT NULL COMMENT 'MD5 sin salt - VULNERABLE',
  `role` enum('admin','user','viewer') DEFAULT 'user',
  `session_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
);

-- Datos de usuarios (SENSIBLE)
INSERT INTO `users` VALUES 
(1,'admin','admin@nexo.cl','0192023a7bbd73250516f069df18b500','admin',1001,'2025-01-15 10:00:00'),
(2,'alice','alice.mendez@gmail.com','482c811da5d5b4bc6d497ffa98491e38','user',1002,'2025-01-20 14:30:00'),
(3,'bob','roberto.silva@empresa.cl','5ebe2294ecd0e0f08eab7690d2a6ee69','user',1003,'2025-02-01 09:15:00'),
(4,'carlos','carlos.gonzalez@constructora.cl','e8d95a51f3af4a3b134bf6bb680a213a','user',1004,'2025-02-10 16:45:00'),
(5,'diana','diana.rojas@retail.cl','7c6a180b36896a65c3ed5c1f3a9d4e2b','viewer',1005,'2025-03-01 11:20:00');

-- ============================================
-- PASSWORDS EN TEXTO PLANO (para referencia):
-- admin    -> admin123
-- alice    -> password123  
-- bob      -> secret
-- carlos   -> nexo2024
-- diana    -> D14n4.2025
-- ============================================

-- Estructura de tabla clients
CREATE TABLE `clients` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL,
  `email` varchar(100) NOT NULL,
  `rut` varchar(12) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address` varchar(200) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
);

-- Datos de clientes
INSERT INTO `clients` VALUES 
(1,'Constructora González y Asociados SpA','contacto@gonzalezasociados.cl','76.543.210-K','+56 2 2345 6789','Av. Providencia 1234, Of. 501, Santiago','2025-01-10 00:00:00'),
(2,'Importadora del Pacífico Ltda.','ventas@importadorapacifico.cl','77.891.234-5','+56 2 2987 6543','Blanco 1199, Valparaíso','2025-01-15 00:00:00'),
(3,'Comercial Fernández e Hijos','administracion@fernandez.cl','78.456.789-0','+56 41 222 3344','O\'Higgins 567, Concepción','2025-01-20 00:00:00');

-- ... (más datos truncados para el ejemplo)

-- ============================================
-- FIN DEL BACKUP
-- Próximo backup programado: 2025-03-16 03:00:00
-- ============================================
