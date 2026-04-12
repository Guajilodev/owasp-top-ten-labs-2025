# PRD — OWASP Top 10 Labs 2025
**Autor:** guajilodev  
**Versión:** 2.2  
**Fecha:** 2026-04-11  
**Estado:** Draft  

---

## 1. Contexto y Motivación

### 1.1 Proyecto anterior

El proyecto `owasp-top-ten-labs` implementó un laboratorio deliberadamente vulnerable basado en el **OWASP Top 10 2021**, con stack PHP 8.2 + MariaDB + Docker Compose. Todos los 10 labs fueron completados y funcionan correctamente.

### 1.2 ¿Por qué un nuevo proyecto y no un update?

Dos razones:

**Razón 1 — El OWASP 2025 tiene cambios estructurales reales:**

| # | 2021 | 2025 | Tipo de cambio |
|---|------|------|----------------|
| A01 | Broken Access Control | Broken Access Control | Mismo concepto |
| A02 | Cryptographic Failures | **Security Misconfiguration** | Subió de A05 → A02 |
| A03 | Injection | **Software Supply Chain Failures** | ⭐ NUEVO — reemplaza "Vulnerable Components" |
| A04 | Insecure Design | **Cryptographic Failures** | Bajó de A02 → A04 |
| A05 | Security Misconfiguration | **Injection** | Bajó de A03 → A05 |
| A06 | Vulnerable & Outdated Components | **Insecure Design** | Bajó de A04 → A06 |
| A07 | Identification & Authentication Failures | **Authentication Failures** | Mismo concepto, rename |
| A08 | Software and Data Integrity Failures | **Software or Data Integrity Failures** | Mismo concepto, rename |
| A09 | Security Logging & Monitoring Failures | **Security Logging and Alerting Failures** | Mismo concepto, rename |
| A10 | Server-Side Request Forgery (SSRF) | **Mishandling of Exceptional Conditions** | ⭐ NUEVO — SSRF desaparece |

**Razón 2 — El mayor problema del proyecto 2021 no era técnico, era pedagógico:**

Los escenarios del 2021 son correctos técnicamente pero demasiado abstractos. Un visor de notas genérico, un buscador sin contexto, una app sin nombre. La gente sale pensando "yo nunca haría algo tan obvio". Y ese es exactamente el pensamiento más peligroso en seguridad.

Este proyecto resuelve ese problema con **una sola app ficticia coherente** donde todos los labs son módulos del mismo sistema. El contexto es reconocible, la vulnerabilidad sigue siendo simple y clara.

---

## 2. La App: Nexo

**Nexo** es una plataforma SaaS ficticia de gestión para pequeñas y medianas empresas. Permite a sus clientes gestionar productos, clientes, facturas y pagos desde un único panel.

Es una app que cualquier desarrollador web ha construido o visto en algún punto de su carrera: un CRUD con login, panel de admin, buscador, carrito y pagos.

Cada lab de este proyecto es un módulo diferente de Nexo. El estudiante no entra a "Lab A01", entra al **módulo de Facturas de Nexo** y encuentra la vulnerabilidad ahí.

### Módulos de Nexo y su vulnerabilidad correspondiente

| Módulo de Nexo | OWASP 2025 | Vulnerabilidad concreta |
|----------------|-----------|------------------------|
| 📄 Mis Facturas | A01 Broken Access Control | IDOR en el ID de factura |
| ⚙️ Panel de Administración | A02 Security Misconfiguration | Creds por defecto + errores verbosos + backups expuestos |
| 🔌 Tienda de Plugins | A03 Software Supply Chain Failures | Plugin con backdoor en el vendor |
| 👤 Registro de Cuenta | A04 Cryptographic Failures | Contraseñas en MD5 sin salt |
| 🔍 Buscador de Clientes | A05 Injection | SQL Injection en el filtro de búsqueda |
| 🛒 Checkout | A06 Insecure Design | Precio enviado desde el cliente en campo hidden |
| 🔐 Login | A07 Authentication Failures | Sin rate limiting + sesión con ID predecible |
| 🧑‍💼 Preferencias de Cuenta | A08 Software or Data Integrity Failures | Cookie serializada sin firma |
| 📊 Actividad de Cuenta | A09 Security Logging and Alerting Failures | Accesos sin loguear + log expuesto públicamente |
| 💳 Transferir Créditos | A10 Mishandling of Exceptional Conditions | Failing open + stack trace con creds en respuesta de error |

---

## 3. Objetivos del Producto

### 3.1 Objetivo principal
Que quien use este proyecto entienda qué es el OWASP Top 10 2025, cómo se ve cada vulnerabilidad en código real y qué se hace para prevenirla. Todo dentro de un contexto reconocible, sin complejidad innecesaria.

### 3.2 Objetivos específicos
1. Cubrir el 100% del OWASP Top 10 2025 con labs funcionales.
2. Que cada lab viva dentro de un módulo reconocible de una app SaaS real.
3. Un solo escenario por lab, claro y explotable.
4. Mostrar la versión vulnerable y la versión corregida en el mismo lab.
5. Incluir una referencia a un caso real por lab — para que el estudiante entienda que esto no es académico.
6. Mantener la portabilidad total vía Docker Compose, sin configuración manual.

### 3.3 Fuera del alcance (v1.0)
- Sistema de scoring o CTF
- Autenticación de usuarios del lab en sí
- Multi-idioma

---

## 4. Principios de Diseño

### 4.1 Principio pedagógico

> **Escenario reconocible + Vulnerabilidad simple y clara = Aprendizaje real**

Reglas que no se negocian:

- **Un solo concepto por lab.** Si el lab intenta explicar dos cosas, no explica ninguna bien.
- **El contexto importa tanto como la vulnerabilidad.** Un IDOR en "una nota genérica" vs. un IDOR en "tu factura de $4.200" son completamente distintos cognitivamente.
- **La versión segura tiene que vivir al lado de la vulnerable.** El estudiante necesita ver ambas para entender el delta.
- **Un caso real por lab.** No como relleno. Para que el estudiante sienta el peso de la vulnerabilidad.

### 4.2 Principio de volatilidad

> Este proyecto es una herramienta de demostración, no una plataforma de práctica sostenida. Los recursos generados durante un lab no deben persistir.

**El flujo esperado es:** mostrás la vulnerabilidad → la explotás → la explicás → el cron resetea → siguiente visitante ve estado limpio.

El mecanismo de reset es un **cron job en la VPS** que corre cada 4 horas. Es la fuente de verdad del reset, no un botón en la UI. El botón existe como conveniencia para el instructor durante una demo en vivo, pero el cron garantiza que el estado vuelva a limpio sin intervención manual.

**Qué persiste vs. qué no:**

| Tipo de dato | Persiste entre resets de cron | Persiste entre reinicios de Docker |
|---|---|---|
| Datos semilla (facturas, clientes, productos) | No — cron los restaura | Sí — volumen de MariaDB |
| Datos de explotación (XSS, registros falsos) | No — cron trunca las tablas | No |
| Archivos subidos (lab A02) | No — cron los borra | No — directorio en tmpfs |
| Logs generados (lab A09) | No — cron vacía el archivo | No — tmpfs |

**Reset nuclear** (para empezar de cero absoluto, incluyendo el volumen de MariaDB):
```bash
docker compose down -v && docker compose up -d
```
Documentado en el README. Produce ~30 segundos de downtime.

---

## 5. Stack Tecnológico

| Componente | Tecnología | Razón |
|------------|------------|-------|
| Backend | PHP 8.2+ (puro, sin framework) | Sin abstracción que oculte la vulnerabilidad |
| Base de datos | MariaDB 10.11 | Ligero, portable, bien documentado |
| Web server | Apache 2.4 | Necesario para el lab de misconfiguration |
| Reverse proxy | Nginx (Alpine) | Rate limiting, SSL, punto único de entrada público |
| Contenerización | Docker Compose v2 | Un solo `docker compose up -d` levanta todo |
| Frontend | HTML5 + Bootstrap 5 | Suficiente para parecer una app real |
| Reset automático | Cron job en la VPS | Garantía de volatilidad sin intervención manual |
| TLS | Certbot + Let's Encrypt | HTTPS obligatorio en producción |

**Sin composer, sin npm, sin nada que requiera setup adicional.** La excepción es el lab de Supply Chain, donde el vendor comprometido se incluye directamente en el repo como archivos PHP.

---

## 6. Especificación de Labs

---

### A01:2025 — Broken Access Control
**Módulo de Nexo:** Mis Facturas

**El escenario:**
El usuario está logueado como cliente de Nexo y puede ver sus facturas en `/facturas/ver.php?id=1042`. Al cambiar el número `1042` por `1041`, `1043`, etc., accede a las facturas de otros clientes con sus datos personales, montos y RUT.

El servidor no verifica que la factura solicitada pertenece al usuario autenticado. Confía ciegamente en el parámetro de la URL.

**Por qué este contexto y no el del 2021:**
Una factura con datos reales (nombre, RUT, monto, fecha) genera urgencia cognitiva. Una "nota genérica" no. El estudiante tiene que sentir que está viendo algo que no debería ver.

**Caso real:**
Optus, Australia (2022). 11 millones de clientes expuestos porque los registros de clientes tenían IDs numéricos secuenciales y el endpoint no verificaba ownership. Multa récord de AU$1.5M.

**Versión segura:**
Agregar `WHERE id = ? AND user_id = ?` a la query. No hay magia, es una línea de SQL.

**Herramientas:** curl, Burp Suite Repeater  
**CWEs:** CWE-639 (Authorization Bypass Through User-Controlled Key), CWE-285  
**Dificultad:** Básica

---

### A02:2025 — Security Misconfiguration
**Módulo de Nexo:** Panel de Administración

**El escenario:**
El panel de admin de Nexo tiene tres problemas visibles:

1. **Credenciales por defecto activas:** `admin` / `admin123` nunca fue cambiado desde el deploy.
2. **Errores verbosos en producción:** Cuando el buscador interno falla, PHP muestra el stack trace completo con la contraseña de la BD en las variables de entorno.
3. **Directorio `/backups/` sin protección:** Accesible desde el browser con directory listing activo. Contiene `nexo_db_2025-03-15.sql.gz`.

Los tres problemas son independientes y el estudiante puede explorarlos en cualquier orden.

**Por qué este contexto y no el del 2021:**
El panel de admin de un SaaS es el objetivo más valioso de cualquier atacante. Ver `admin/admin123` en algo que parece una app real genera más impacto que un formulario genérico.

**Caso real:**
Capital One (2019). Un WAF mal configurado permitió que una ex-empleada de AWS accediera a los datos de 100 millones de clientes. La misconfiguration era en permisos de IAM, no en credenciales, pero el principio es idéntico: alguien olvidó endurecer la configuración por defecto.

**Versión segura:**
Cambiar creds, `display_errors = Off` en php.ini, `Options -Indexes` en `.htaccess`.

**Herramientas:** curl -I, Nikto, browser directo  
**CWEs:** CWE-16, CWE-200, CWE-521  
**Dificultad:** Básica

---

### A03:2025 — Software Supply Chain Failures ⭐ NUEVO
**Módulo de Nexo:** Tienda de Plugins

**El escenario:**
Nexo tiene una tienda de plugins oficiales. El plugin `nexo/pdf-export v2.3.1` aparece publicado, verificado, con 4.8 estrellas y 12.000 instalaciones. El administrador lo instala desde el panel.

Lo que el admin no sabe: el plugin fue comprometido entre la versión `2.3.0` (limpia) y `2.3.1`. El atacante agregó cinco líneas al método `generatePdf()` que en segundo plano hacen un `file_get_contents()` a una URL externa enviando las variables de entorno del servidor — incluyendo las credenciales de la BD.

El lab tiene dos partes:
1. **Usar el plugin** (generar un PDF de factura) sin saber que está siendo exfiltrado.
2. **Analizar el código del vendor** (`/vendor/nexo/pdf-export/src/PdfGenerator.php`) para encontrar el código malicioso, línea por línea.

El código malicioso está deliberadamente ofuscado pero no imposible de encontrar — porque así es en la realidad.

**Por qué este contexto:**
El punto de A03 en 2025 no es "no uses librerías viejas". Es que el paquete que instalaste HOY, de un vendor confiable, puede tener código malicioso. Nadie mira el código de sus dependencias. Este lab hace exactamente eso.

**Caso real:**
xz-utils backdoor (2024). Un atacante pasó DOS AÑOS ganando confianza en el proyecto open source para finalmente insertar un backdoor en una versión de producción. Fue descubierto por accidente por un ingeniero de Microsoft que notó que SSH tardaba 500ms de más.

**Versión segura:**
Verificar checksum del paquete antes de instalar. Usar `composer audit`. Revisar diff entre versiones antes de actualizar una dependencia crítica.

**Herramientas:** Lectura directa de código en `/vendor/`, diff de versiones, `composer audit` (conceptual)  
**CWEs:** CWE-1395, CWE-1104, CWE-494  
**Dificultad:** Intermedia

---

### A04:2025 — Cryptographic Failures
**Módulo de Nexo:** Registro de Cuenta

**El escenario:**
Nexo almacena las contraseñas de sus usuarios con MD5 sin salt. La tabla `users` es visible si alguien explota la vulnerabilidad A05 (SQL Injection) — o si accede al backup expuesto del lab A02.

El estudiante tiene acceso a los hashes y puede crackearlos contra CrackStation o con Hashcat en segundos. La contraseña del admin resulta ser `nexo2024`.

Además, el formulario de "olvidé mi contraseña" envía la contraseña actual en texto plano por email — en lugar de un link de reset. Esto significa que Nexo tiene las contraseñas en forma reversible (o las guarda en texto plano además del hash).

**Por qué este contexto:**
La conexión entre labs es deliberada. Si el backup del lab A02 tiene el SQL dump con los hashes MD5, el estudiante empieza a ver cómo las vulnerabilidades se encadenan. Eso es lo que pasa en un breach real.

**Caso real:**
LinkedIn (2012). 117 millones de contraseñas expuestas hasheadas con SHA-1 sin salt. Crackeadas en su mayoría en horas. Cuatro años después seguían vendiéndose en el mercado negro.

**Versión segura:**
`password_hash($pass, PASSWORD_BCRYPT)` + `password_verify()`. El link de reset usa un token temporal de un solo uso, nunca la contraseña real.

**Herramientas:** CrackStation (web), Hashcat, John the Ripper  
**CWEs:** CWE-916, CWE-327  
**Dificultad:** Básica

---

### A05:2025 — Injection
**Módulo de Nexo:** Buscador de Clientes

**El escenario:**
El CRM de Nexo tiene un buscador de clientes que arma la query SQL por concatenación directa:

```php
$query = "SELECT * FROM clients WHERE name LIKE '%" . $_GET['q'] . "%'";
```

Con `q=' OR '1'='1` se obtienen todos los clientes. Con `q=' UNION SELECT username, password, 3, 4 FROM users-- ` se obtienen los hashes de usuarios.

El mismo buscador renderiza los resultados sin sanitizar, por lo que un cliente con nombre `<script>alert('XSS')</script>` ejecuta JavaScript en el browser de quien busca.

**Por qué este contexto:**
Un buscador de clientes es algo que todos los desarrolladores han construido. Es la excusa perfecta para concatenar strings. El lab hace evidente que la comodidad tiene precio.

**Caso real:**
Equifax (2017). La filtración de datos de 147 millones de personas empezó por una vulnerabilidad de injection en Apache Struts. Costo estimado: $1.4 billones de dólares.

**Versión segura:**
Prepared statements con `PDO::prepare()`. `htmlspecialchars()` en el output. Dos líneas de código.

**Herramientas:** SQLMap, Burp Suite, curl, browser  
**CWEs:** CWE-89 (SQLi), CWE-79 (XSS)  
**Dificultad:** Intermedia

---

### A06:2025 — Insecure Design
**Módulo de Nexo:** Checkout

**El escenario:**
El módulo de e-commerce de Nexo muestra productos y permite comprarlos. El precio del producto se envía en un campo `<input type="hidden" name="price" value="299.99">` dentro del formulario de compra.

El servidor toma ese valor y lo usa para procesar el pago sin verificarlo contra la base de datos:

```php
$amount = $_POST['price'];
procesarPago($user_id, $amount); // Cobra lo que el cliente diga
```

El estudiante intercepta el request con Burp Suite y cambia `price=299.99` a `price=0.01`. El sistema acepta el pago y confirma la compra.

**Por qué "Insecure Design" y no "Injection" o "Access Control":**
Esta no es una falla de implementación. Es una falla de diseño: el sistema fue diseñado confiando en el cliente para definir el precio. No existe ningún fix en el código que resuelva esto sin rediseñar el flujo. Eso es exactamente lo que OWASP define como Insecure Design.

**Por qué este contexto:**
E-commerce es el contexto más directo para business logic flaws. El estudiante inmediatamente entiende el impacto económico.

**Caso real:**
Múltiples casos documentados en tiendas online y marketplaces, incluyendo un caso de 2023 donde un marketplace latinoamericano permitió comprar artículos electrónicos por $0.01 durante varias horas antes de detectarlo. No se divulgó el nombre por acuerdo legal.

**Versión segura:**
El precio nunca viaja desde el cliente. El servidor calcula el precio a partir del `product_id` que sí viaja:
```php
$product = getProductById($_POST['product_id']);
procesarPago($user_id, $product->price);
```

**Herramientas:** Burp Suite Intercept, curl con -d  
**CWEs:** CWE-602, CWE-840  
**Dificultad:** Intermedia

---

### A07:2025 — Authentication Failures
**Módulo de Nexo:** Login

**El escenario:**
El login de Nexo tiene dos problemas:

1. **Sin rate limiting:** No hay límite de intentos. Se puede hacer brute force con Hydra sin que el sistema reaccione.
2. **Session ID predecible:** Después de un login exitoso, el session ID es un número entero secuencial (`SESS=1042`). Alguien que tenga una sesión activa puede iterar para hijackear sesiones de otros usuarios.

**Por qué estos dos vectores juntos:**
Son la combinación clásica. El primero permite entrar. El segundo permite quedarse sin que nadie lo note, porque no hay forma de distinguir entre el dueño legítimo y el atacante una vez que tienen el session ID.

**Caso real:**
Rockstar Games (2022). Credential stuffing masivo usando listas de contraseñas filtradas de otros sitios. Sin rate limiting ni detección de anomalías, miles de cuentas comprometidas antes de que el equipo de seguridad reaccionara.

**Versión segura:**
Bloqueo temporal después de 5 intentos fallidos. Session ID generado con `bin2hex(random_bytes(32))`. Regenerar session ID después de login exitoso (`session_regenerate_id(true)`).

**Herramientas:** Hydra, Burp Suite Intruder, curl  
**CWEs:** CWE-307, CWE-384, CWE-521  
**Dificultad:** Intermedia

---

### A08:2025 — Software or Data Integrity Failures
**Módulo de Nexo:** Preferencias de Cuenta

**El escenario:**
Las preferencias del usuario (tema, idioma, rol) se guardan en una cookie con un objeto PHP serializado:

```
Cookie: prefs=O:4:"User":2:{s:8:"username";s:5:"alice";s:8:"is_admin";b:0;}
```

El estudiante puede decodificar la cookie en base64, modificar `is_admin;b:0` por `is_admin;b:1`, re-encodearla y reemplazarla en el browser. El sistema deserializa el objeto sin verificar integridad y otorga acceso de admin.

**Por qué este contexto:**
Las cookies de preferencias son algo que los devs implementan "rápido" y que raramente auditan. "¿Qué daño puede hacer guardar el tema oscuro en una cookie?" — este lab responde esa pregunta.

**Caso real:**
Aplicaciones Java con Apache Commons Collections (2015). La deserialización insegura fue explotada en varios sistemas empresariales permitiendo Remote Code Execution. Jenkins, WebLogic, y otros afectados.

**Versión segura:**
Firmar la cookie con HMAC:
```php
$signature = hash_hmac('sha256', $serialized, SECRET_KEY);
// Validar la firma antes de deserializar
```
O mejor aún: no serializar objetos en cookies. Usar session storage en el servidor.

**Herramientas:** Burp Suite, PHP manual, curl  
**CWEs:** CWE-502, CWE-345  
**Dificultad:** Avanzada

---

### A09:2025 — Security Logging and Alerting Failures
**Módulo de Nexo:** Actividad de Cuenta

**El escenario:**
El panel de actividad de Nexo muestra las últimas acciones del usuario. Pero hay tres problemas en el backend:

1. **Los intentos de login fallidos no se loguean.** Un atacante puede hacer brute force del lab A07 sin dejar rastro.
2. **El archivo de log está accesible vía URL:** `http://localhost:8082/logs/app.log`. Cualquiera puede leerlo.
3. **Log injection:** El campo de búsqueda del lab A05 inyecta el input directamente en el log sin sanitizar. Un atacante puede escribir entradas falsas para cubrir sus huellas o confundir al equipo de respuesta.

**Por qué "Alerting" en 2025 y no solo "Monitoring":**
En 2021 el énfasis era en "logear". En 2025 OWASP agrega explícitamente que no basta con logear si nadie recibe una alerta. El lab muestra cómo 500 intentos fallidos de login no generan ninguna notificación.

**Caso real:**
Uber (2016). Un atacante tuvo acceso a los sistemas de Uber durante más de un año sin ser detectado, en parte porque no existía monitoreo efectivo de accesos anómalos.

**Versión segura:**
Logear intentos fallidos con IP + timestamp. Mover logs fuera del webroot. Sanitizar el input antes de escribirlo en el log. Configurar alertas por umbral de errores.

**Herramientas:** curl, browser, lectura directa de logs  
**CWEs:** CWE-778, CWE-223, CWE-117  
**Dificultad:** Básica

---

### A10:2025 — Mishandling of Exceptional Conditions ⭐ NUEVO
**Módulo de Nexo:** Transferir Créditos

**El escenario:**
El módulo de pagos de Nexo permite transferir créditos entre usuarios. El código es este:

```php
try {
    $result = $db->query("SELECT balance FROM wallets WHERE user_id = $user_id");
    // ... lógica de transferencia
} catch (Exception $e) {
    // "Si algo falla, no rompamos la experiencia del usuario"
    http_response_code(200);
    echo json_encode(['status' => 'success', 'message' => 'Transferencia completada']);
}
```

**Tres vulnerabilidades en uno:**

1. **Failing open:** Si la query falla (por ejemplo enviando `user_id='; DROP TABLE wallets--`), la excepción se captura y el sistema responde con "éxito" aunque no haya procesado nada — o peor, haya procesado a medias.

2. **Stack trace en el response:** Cuando el error es diferente (un tipo de input que llega al handler interno), la app devuelve el stack trace completo de PHP con la ruta del servidor, la versión de PHP, las credenciales de conexión a la BD visibles en el trace de PDO, y los nombres de todas las tablas.

3. **CSRF (vulnerabilidad histórica bonus):** El formulario no valida un token anti-falsificación. Un atacante puede crear un sitio malicioso con un formulario oculto que, al ser visitado por la víctima, ejecuta transferencias sin su consentimiento.

El estudiante puede explotar las tres enviando requests malformados con curl y observar las respuestas. Para CSRF, debe crear un HTML con un formulario que apunte al endpoint vulnerable.

**Por qué CSRF aunque no esté en el Top 10:**
CSRF fue removido del OWASP Top 10 en 2017 porque los frameworks modernos (Laravel, Django, Rails) lo mitigan por defecto. Este proyecto usa PHP puro SIN framework, exactamente el caso donde CSRF sigue siendo un problema real. Se incluye por completitud pedagógica.

**Caso real:**
Knight Capital Group (2012). Una excepción no manejada en su sistema de trading algorítmico causó que la firma comprara y vendiera acciones de forma errática durante 45 minutos. Pérdida: $440 millones de dólares. La empresa quebró días después.

**Versión segura:**
```php
// Validar CSRF token
if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    http_response_code(403);
    exit(json_encode(['error' => 'CSRF token invalid']));
}

// ... lógica de transferencia ...

catch (Exception $e) {
    // Log interno con detalles
    error_log("Transfer error: " . $e->getMessage());
    // Respuesta al usuario sin información interna
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'No se pudo completar la transferencia']);
    // NUNCA http 200 en un catch. Nunca.
}
```

**Herramientas:** curl, Burp Suite  
**CWEs:** CWE-636 (Failing Open), CWE-209 (Error con info sensible), CWE-703, CWE-352 (CSRF)  
**Dificultad:** Avanzada

---

## 7. Estructura del Proyecto

```
owasp2025/
├── docker-compose.yml
├── .env.example
├── .env                          # NO versionar
├── .gitignore
├── README.md
├── DEVELOPMENT_LOG.md
├── EXPLOTACION_GUIDE.md
├── nginx/
│   ├── nginx.conf                # Config del reverse proxy
│   └── conf.d/
│       └── nexo.conf             # Virtualhost, rate limiting, zonas general y exploit
├── certbot/                      # Volumen de certificados Let's Encrypt
├── mysql/
│   ├── init.sql                  # Datos semilla — fuente de verdad del estado limpio
│   └── my.cnf
├── php/
│   └── Dockerfile
├── cron/
│   └── reset.cron                # Definición del cron job (se instala en la VPS)
└── src/
    ├── index.php                 # Home: presentación de Nexo + grid de 10 módulos
    ├── config/
    │   └── db.php
    ├── shared/
    │   ├── header.php
    │   ├── footer.php
    │   └── lab_panel.php
    ├── a01_facturas/
    ├── a02_admin/
    ├── a03_plugins/
    ├── a04_registro/
    ├── a05_clientes/
    ├── a06_checkout/
    ├── a07_login/
    ├── a08_preferencias/
    ├── a09_actividad/
    └── a10_pagos/
```

**Decisión de naming:** Los directorios usan nombres del dominio de negocio (`a01_facturas`), no el nombre OWASP. El nombre técnico aparece dentro del lab.

**No existe `reset.php`.** El reset no tiene interfaz HTTP. Lo maneja exclusivamente el cron job vía `docker exec` contra MariaDB. No hay botón en la UI, no hay endpoint que bloquear — simplemente no existe el vector.

---

## 8. Template de Lab (Panel Lateral)

Cada módulo tiene un panel lateral fijo con cuatro secciones colapsables:

```
┌─────────────────────────────────┐
│  🔴 VULNERABILIDAD ACTIVA       │  ← Badge con nombre OWASP 2025
│  A01: Broken Access Control     │
├─────────────────────────────────┤
│  📖 ¿Qué está pasando?          │  ← Explicación en lenguaje simple
│  [texto explicativo]            │
├─────────────────────────────────┤
│  🛠 Cómo explotarlo             │  ← Comandos concretos
│  $ curl http://...?id=1041      │
├─────────────────────────────────┤
│  🛡 Cómo se previene            │  ← Fix con código, explicado
│  [diff vulnerable vs. seguro]   │
├─────────────────────────────────┤
│  🌐 Caso real                   │  ← El breach real
│  Optus, 2022 · 11M clientes     │
└─────────────────────────────────┘
```

La app sigue funcionando en la parte principal de la pantalla. El panel es informativo pero no interrumpe la interacción con la vulnerabilidad.

---

## 9. Base de Datos

```sql
-- Datos de Nexo (compartidos entre labs)
clients      (id, name, email, rut, created_at)
invoices     (id, client_id, amount, description, date, status)
products     (id, name, description, price, stock)
orders       (id, client_id, product_id, quantity, total, status, created_at)

-- Usuarios de Nexo
users        (id, username, email, password_md5, role, session_id, created_at)

-- A10: Módulo de pagos
wallets      (id, user_id, balance)
transfers    (id, from_user, to_user, amount, status, created_at)

-- A03: Plugins instalados
plugins      (id, name, version, vendor, installed_at, checksum)
```

Los datos iniciales en `init.sql` tienen nombres, montos y empresas ficticias pero creíbles (no "test user 1", sino "Empresa Constructora González SRL").

---

## 10. Infraestructura

### 10.1 Arquitectura de red

```
Internet
    │
    ▼
[ Nginx :443 ]  ←── SSL termination, rate limiting (zonas general/exploit)
    │
    │  red: frontend (internal: true)
    ▼
[ Apache/PHP ]  ←── App vulnerable, SIN salida a internet
    │
    │  red: backend (internal: true)
    ▼
[ MariaDB ]     ←── Solo accesible desde el contenedor web
```

**La regla clave:** `web` y `db` están ÚNICAMENTE en redes `internal: true`. No tienen ruta de salida a internet. Nginx es el único contenedor con acceso exterior.

Esto significa que:
- SQL injection vuelca la BD local con datos ficticios → no importa
- El backdoor de A03 intenta hacer un request externo → falla silenciosamente, el concepto se demuestra igual
- Un webshell subido vía A02 no puede hacer nada útil sin red saliente
- El contenedor no puede ser usado como pivot contra otros sistemas

### 10.2 Docker Compose (esquema)

```yaml
services:
  nginx:
    image: nginx:alpine
    ports:
      - "80:80"
      - "443:443"
    volumes:
      - ./nginx/conf.d:/etc/nginx/conf.d:ro
      - ./certbot/www:/var/www/certbot:ro
      - ./certbot/conf:/etc/letsencrypt:ro
    networks:
      - public      # tiene salida a internet (necesario para responder requests)
      - frontend    # para llegar al contenedor web

  web:
    build: ./php
    volumes:
      - ./src:/var/www/html
    tmpfs:
      - /var/www/html/a02_admin/uploads:size=50m,mode=1777
      - /var/log/nexo:size=20m           # logs del lab A09
    networks:
      - frontend    # recibe de nginx
      - backend     # habla con db
    # SIN ports expuestos al host

  db:
    image: mariadb:10.11
    volumes:
      - ./mysql/init.sql:/docker-entrypoint-initdb.d/init.sql:ro
      - ./mysql/my.cnf:/etc/mysql/conf.d/my.cnf:ro
      - nexo_db:/var/lib/mysql
    networks:
      - backend     # solo accesible desde web
    # SIN ports expuestos al host

networks:
  public:
    internal: false   # nginx puede responder a internet
  frontend:
    internal: true    # nginx ↔ web, aislado
  backend:
    internal: true    # web ↔ db, aislado

volumes:
  nexo_db:
```

### 10.3 Nginx — rate limiting y bloqueos

Dos zonas con propósitos distintos:

- **`general`** — para browsing normal y scanners. Estricta.
- **`exploit`** — para los labs que requieren requests rápidos (A05, A07, A10). Permisiva porque la protección real viene del aislamiento de red, no del rate limiting.

```nginx
# /nginx/conf.d/nexo.conf

limit_req_zone $binary_remote_addr zone=general:10m rate=10r/m;
limit_req_zone $binary_remote_addr zone=exploit:10m rate=120r/m;

server {
    listen 443 ssl;
    server_name nexo.tudominio.com;

    # Labs que necesitan requests rápidos para la demo
    # A05 (SQLMap), A07 (Hydra/brute force), A10 (race condition)
    # La protección es el aislamiento de red — el contenedor no tiene salida a internet
    location ~ ^/(a05_clientes|a07_login|a10_pagos)/ {
        limit_req zone=exploit burst=50 nodelay;
        proxy_pass http://web;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
    }

    # Todo lo demás — estricto
    location / {
        limit_req zone=general burst=5 nodelay;
        proxy_pass http://web;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
    }
}
```

### 10.4 Cron job de reset automático

Archivo `cron/reset.cron` — se instala en la VPS con `crontab -e` o copiando a `/etc/cron.d/`:

```bash
# Reset automático de Nexo Labs cada 4 horas
# Trunca tablas con datos generados por explotaciones y re-inserta seed data
# Sin downtime — el contenedor sigue corriendo

# Reset de base de datos
0 */4 * * * root docker exec owasp-db-2025 \
  mysql -u"${NEXO_DB_USER}" -p"${NEXO_DB_PASS}" nexo_labs \
  -e "SOURCE /docker-entrypoint-initdb.d/init.sql" >> /var/log/nexo-reset.log 2>&1

# Limpiar archivos subidos (lab A02) — tmpfs se limpia solo al reiniciar,
# pero esto garantiza limpieza durante uptime largo
0 */4 * * * root docker exec owasp-web-2025 \
  find /var/www/html/a02_admin/uploads -type f -not -name ".gitkeep" -delete

# Limpiar logs del lab A09
0 */4 * * * root docker exec owasp-web-2025 \
  sh -c "> /var/log/nexo/app.log"
```

**Importante:** Las variables `NEXO_DB_USER` y `NEXO_DB_PASS` se leen del `.env` de la VPS o se definen directamente en el cron. No van hardcodeadas.

### 10.5 TLS con Let's Encrypt

Certbot corre como contenedor separado en el compose. El README incluye el comando de obtención inicial:

```bash
docker compose run --rm certbot certonly \
  --webroot -w /var/www/certbot \
  -d nexo.tudominio.com \
  --email tu@email.com --agree-tos
```

Renovación automática vía cron:
```bash
0 3 * * 1 root docker compose run --rm certbot renew --quiet && \
  docker compose exec nginx nginx -s reload
```

---

## 11. Criterios de Aceptación

### Por lab:
- [ ] La vulnerabilidad es explotable sin configuración adicional
- [ ] El panel lateral explica la vulnerabilidad en lenguaje claro
- [ ] El código vulnerable y el fix están ambos presentes y comentados
- [ ] El caso real es correcto y tiene fuente verificable
- [ ] El lab queda en estado limpio después del cron de reset

### Por proyecto — local:
- [ ] `docker compose up -d` levanta todo sin errores en Linux y macOS
- [ ] Los 10 módulos son accesibles desde el home de Nexo en `localhost:8082`
- [ ] El home presenta a Nexo como una app real antes de revelar que es un lab de seguridad
- [ ] No hay credenciales en el código — todo en `.env`

### Por proyecto — VPS pública:
- [ ] El contenedor `web` no tiene salida a internet (verificar con `docker exec owasp-web-2025 curl https://google.com` — debe fallar)
- [ ] Nginx responde en HTTPS con certificado válido
- [ ] El cron de reset corre cada 4 horas y deja log en `/var/log/nexo-reset.log`
- [ ] El rate limiting de Nginx está activo (verificar con `ab -n 100 -c 10 https://nexo.dominio.com/`)

---

## 12. Plan de Implementación

### Fase 1 — Infraestructura base (1-2 sesiones)
- `docker-compose.yml` con Nginx + web + db y redes aisladas
- `nginx/conf.d/nexo.conf` con rate limiting (zonas general y exploit)
- `php/Dockerfile`
- `.env.example` con todas las variables
- `mysql/init.sql` con datos ficticios creíbles
- `src/index.php` — home de Nexo
- `src/shared/` — header, footer, panel lateral
- `cron/reset.cron` — definición del cron job

### Fase 2 — Labs contexto 2021 actualizado a Nexo (3-4 sesiones)
A01, A02, A04, A07, A08, A09 — misma vulnerabilidad, contexto Nexo.

### Fase 3 — Labs rediseñados (1-2 sesiones)
A05 (buscador de clientes), A06 (checkout con price tampering).

### Fase 4 — Labs nuevos (2-3 sesiones)
A03 (plugin con backdoor en vendor/), A10 (pagos con failing open).

### Fase 5 — Despliegue y pulido (1-2 sesiones)
- Deploy en VPS: DNS, certbot, instalación del cron
- Verificar aislamiento de red (`docker exec web curl https://google.com` debe fallar)
- `README.md` con guía de deploy paso a paso
- `EXPLOTACION_GUIDE.md` con comandos

---

## 13. Riesgos

| Riesgo | Mitigación |
|--------|-----------|
| A03 Supply Chain no es "explotable" activamente | El lab funciona en dos pasos: usar el plugin (efecto visible) + revisar el código del vendor para encontrar el backdoor |
| A10 race condition difícil de reproducir de forma confiable | `sleep(1)` artificial en el código vulnerable para ampliar la ventana. El concepto importa más que la reproducción exacta |
| Los datos ficticios rompen la inmersión | `init.sql` con nombres, RUTs y montos creíbles. Nada de "Test User 1" |
| Alguien usa el lab A07 (brute force) contra targets externos | El contenedor `web` no tiene salida a internet. El brute force solo funciona contra la BD local del lab |
| El cron job falla silenciosamente en la VPS | El cron loguea en `/var/log/nexo-reset.log`. El README incluye cómo verificar que está corriendo |
| Certbot no renueva y el sitio queda con cert vencido | Cron de renovación semanal + alerta por email en certbot |

---

## 14. Protecciones de Infraestructura del Lab

Aunque el proyecto es deliberadamente vulnerable para fines educativos, implementamos protecciones a nivel de infraestructura para evitar que el lab sea usado como vector de ataque real.

### 14.1 Supply Chain — Práctica lo que predicamos

| Protección | Implementación |
|------------|----------------|
| **SRI (Subresource Integrity)** | Bootstrap CSS y JS cargan con atributo `integrity` que verifica hash SHA384. Si el CDN es comprometido, el browser rechaza el archivo. |
| **Docker image pinning** | `php:8.2-apache@sha256:...` en lugar de tag flotante. Garantiza imagen exacta. |
| **Sin credenciales hardcodeadas** | `db.php` falla si no encuentra env vars. No hay fallback a defaults. |

### 14.2 Session Security

Las versiones `_secure.php` implementan:

```php
ini_set('session.cookie_httponly', 1);    // JS no puede leer la cookie
ini_set('session.cookie_samesite', 'Strict'); // Previene CSRF via cookies
ini_set('session.use_strict_mode', 1);    // Rechaza session IDs inventados
```

### 14.3 CSP Headers

`buscar_secure.php` incluye Content Security Policy como defensa en profundidad:

```php
header("Content-Security-Policy: default-src 'self'; script-src 'self' https://cdn.jsdelivr.net; ...");
```

Incluso si un XSS escapara el `htmlspecialchars()`, el browser bloquea scripts inline.

### 14.4 CSRF (Vulnerabilidad Histórica)

Incluida en A10 como bonus pedagógico:
- **Versión vulnerable:** Sin token, cualquier sitio puede enviar el form.
- **Versión segura:** Token generado con `bin2hex(random_bytes(32))`, validado con `hash_equals()`.

**Razón de inclusión:** CSRF salió del Top 10 en 2017 porque los frameworks lo mitigan por defecto. Este proyecto usa PHP puro — exactamente donde CSRF sigue siendo un problema real.

### 14.5 Aislamiento de Red (ya documentado en §10)

- Contenedor `web` en red `internal: true` — sin salida a internet.
- El backdoor de A03 intenta exfiltrar pero falla silenciosamente.
- Ningún webshell subido puede conectar a C2 externos.

---

*Este PRD es un documento vivo. Se actualiza durante la implementación.*
