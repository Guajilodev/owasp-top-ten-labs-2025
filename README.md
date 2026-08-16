# Nexo - OWASP Top 10 2025 Labs

> **Un laboratorio deliberadamente vulnerable para aprender seguridad web**

**Demo en vivo:** [https://nexolab.guajilodev.com](https://nexolab.guajilodev.com)

---

## ¡ADVERTENCIA DE SEGURIDAD!

**Este proyecto contiene vulnerabilidades INTENCIONALES.** Fue diseñado exclusivamente para fines educativos.

- **NO** lo ejecutes en un servidor de produccion sin las protecciones documentadas
- **NO** lo expongas a internet sin aislamiento de red
- **NO** lo uses como base para aplicaciones reales
- **SI** lo usas para aprender, practicar y entender seguridad web

---

## ¿Que es Nexo?

**Nexo** es una plataforma SaaS ficticia de gestion empresarial. Cada modulo de la aplicación contiene una vulnerabilidad del OWASP Top 10 2025, presentada en un contexto realista y reconocible.

A diferencia de otros labs con escenarios abstractos, Nexo te pone en situaciones que vas a encontrar en el mundo real: facturas, paneles de admin, checkouts, logins.

## OWASP Top 10 2025 - Cobertura

| # | Vulnerabilidad | Modulo de Nexo | Escenario |
|---|----------------|----------------|-----------|
| A01 | Broken Access Control | Mis Facturas | IDOR - ver facturas de otros usuarios |
| A02 | Security Misconfiguration | Panel Admin | Credenciales default + backups expuestos |
| A03 | Software Supply Chain | Tienda Plugins | Plugin con backdoor oculto |
| A04 | Cryptographic Failures | Registro | Passwords en MD5 sin salt |
| A05 | Injection | Buscador | SQL Injection en filtro de busqueda |
| A06 | Insecure Design | Checkout | Precio manipulable desde el cliente |
| A07 | Authentication Failures | Login | Sin rate limiting + sesion predecible |
| A08 | Data Integrity Failures | Preferencias | Cookie serializada sin firma HMAC |
| A09 | Logging Failures | Actividad | Sin logs + log file expuesto |
| A10 | Exceptional Conditions | Transferencias | Fail-open + stack trace con credenciales |

Cada lab incluye:
- Versión vulnerable (para explotar)
- Versión segura (para comparar)
- Panel lateral con explicación y referencia a caso real

## Quick Start - Desarrollo Local

### Requisitos
- Docker + Docker Compose
- Git

### Instalación

```bash
# Clonar el repositorio
git clone https://github.com/guajilodev/owasp2025.git
cd owasp2025

# Copiar variables de entorno
cp .env.example .env

# Levantar los contenedores
docker compose up -d

# Abrir en el navegador
open http://localhost:8082
```

La base de datos se inicializa automáticamente con datos de ejemplo.
Docker Compose asigna dinámicamente las redes del proyecto; los servicios se comunican a través de sus nombres (web y db) en lugar de mediante direcciones IP fijas de los contenedores. Consulta la sección [Aislamiento local de Docker](https://github.com/Guajilodev/owasp-top-ten-labs-2025/blob/main/docs/LOCAL_CONTAINMENT.md) antes de utilizar el entorno de pruebas, que es intencionadamente vulnerable, en un equipo local compartido.

### Reset manual

```bash
# Solo desarrollo local: re-ejecutar el script de inicialización
docker compose exec -T db mariadb -u"$NEXO_DB_USER" -p"$NEXO_DB_PASS" nexo_labs \
  -e "SOURCE /docker-entrypoint-initdb.d/init.sql"
```

No uses este atajo en producción: usa el reset autenticado y acotado del
[runbook de producción](deploy/production/README.md).

## Deploy en Produccion (Seguro)

Si quieres exponer el lab a internet (para workshops, clases, CTFs), **SIGUE ESTAS INSTRUCCIONES**. Sin ellas, cualquiera que explote las vulnerabilidades podría comprometer tu servidor.

### Arquitectura de Producción

```
Internet
    |
    v
[host nginx :443] ---> [127.0.0.1:8082] ---> [owasp-web-2025:80] ---> [owasp-db-2025:3306]
   TLS + rate limit          loopback only          backend (internal, dynamic)
```

El puerto del contenedor `web` se publica **solo** en loopback. nginx corre en
el host y es el único proxy que debe alcanzarlo; `db` no publica puertos. La
red `backend` queda `internal` y Docker asigna dinámicamente sus subnets y
direcciones. La red `frontend` permite el encaminamiento que requiere el puerto
loopback publicado; la política de firewall del host limita sus flujos nuevos a
nginx del host y `web` → `db`.

La configuración ejecutable es [`docker-compose.prod.yml`](docker-compose.prod.yml).
Los artefactos de host (política de firewall por interfaces dinámicas, servicio
systemd, reset y rotación del log) están en
[`deploy/production/`](deploy/production/). No copies el Compose de desarrollo
como configuración de producción.

### Principios de Seguridad

1. **Aislamiento de red**: el contenedor web NO puede conectarse a internet
2. **Exposicion minima**: nginx del host hace proxy solo a `127.0.0.1:8082`; `web` y `db` no tienen puertos públicos
3. **Red interna dinamica**: `backend` conecta `web` con `db`, sin IPs ni subnets fijas
4. **Limites de recursos**: CPU, memoria, swap, PIDs y logs se limitan por servicio
5. **Sin credenciales en código**: todo via variables de entorno
6. **Capabilities mínimas**: `cap_drop: ALL` + solo las necesarias
7. **Reset automático**: cron job cada 4 horas limpia datos y uploads

### Runbook de deploy

El procedimiento reproducible de aprovisionamiento, validación, backup y rollback
está en [deploy/production/README.md](deploy/production/README.md). No copies
fragmentos de Compose a mano: `docker-compose.prod.yml` y ese runbook son la
fuente de verdad. El flujo requerido es validación local → revisión →
commit/push → pull/deploy aprobado en el VPS.

## Verificación de Aislamiento

Despues del deploy, verifica que el contenedor web este correctamente aislado:

```bash
# web queda disponible solo para nginx local en el puerto publicado
curl --fail --silent --show-error http://127.0.0.1:8082/ >/dev/null

# db debe seguir accesible solo mediante el nombre de servicio interno
docker exec owasp-web-2025 php -r 'new PDO("mysql:host=db;dbname=" . getenv("NEXO_DB_NAME"), getenv("NEXO_DB_USER"), getenv("NEXO_DB_PASS")); echo "db-ok", PHP_EOL;'

# La inspección no debe mostrar ningún puerto publicado para db
docker inspect owasp-db-2025 --format '{{json .NetworkSettings.Ports}}'
```

## Estructura del Proyecto

```
owasp2025/
├── src/                    # Código fuente PHP
│   ├── a01_facturas/       # Lab A01 - Broken Access Control
│   ├── a02_admin/          # Lab A02 - Security Misconfiguration
│   ├── a03_plugins/        # Lab A03 - Supply Chain
│   ├── ...
│   ├── config/             # Configuración de DB
│   └── shared/             # Header, footer, panel lateral
├── php/                    # Dockerfile de PHP/Apache
├── mysql/                  # Init SQL y config de MariaDB
├── nginx/                  # Config de nginx (dev)
├── cron/                   # Scripts de reset
├── docker-compose.yml      # Desarrollo local
└── PRD.md                  # Documento de diseño completo
```

## Contribuir

¡Las contribuciones son bienvenidas! Por favor:

1. Fork del repositorio
2. Crea una rama (`git checkout -b feature/nueva-vuln`)
3. Commit con mensaje descriptivo
4. Push y abre un Pull Request

### Ideas para contribuir
- Traducciones (inglés, portugués)
- Nuevos escenarios para vulnerabilidades existentes
- Mejoras en la documentacion
- Fix de bugs en la infraestructura (NO en las vulnerabilidades intencionales)

## Licencia

Este proyecto esta licenciado bajo [Creative Commons Attribution-ShareAlike 4.0 International (CC BY-SA 4.0)](https://creativecommons.org/licenses/by-sa/4.0/).

### Atribución

Este laboratorio esta basado en el [OWASP Top 10](https://owasp.org/Top10/), un proyecto de la [OWASP Foundation](https://owasp.org/) tambien licenciado bajo CC BY-SA 4.0.

Eres libre de:
- **Compartir**, copiar y redistribuir el material en cualquier medio o formato
- **Adaptar**, remezclar, transformar y construir sobre el material para cualquier propósito, incluso comercial

Bajo las siguientes condiciones:
- **Atribución**, debes dar credito apropiado y linkear a la licencia
- **ShareAlike**, si modificas el material, debes distribuirlo bajo la misma licencia

## Agradecimientos

El proyecto fue desarrollado con asistencia de flujo de trabajo y herramientas de [Gentleman Programming](https://github.com/Gentleman-Programming) y [Gentle AI](https://github.com/Gentleman-Programming/gentle-ai). Este reconocimiento se limita a dicha asistencia y no implica patrocinio, respaldo, afiliación ni propiedad del proyecto por parte de dichas organizaciones.

## Disclaimer Legal

Este software se proporciona "tal cual", sin garantias de ningún tipo. El autor no es responsable de:

- Uso indebido del conocimiento adquirido con este laboratorio
- Daños causados por ejecutar este software sin las protecciones adecuadas
- Consecuencias legales de atacar sistemas sin autorización

**Hackear sistemas sin autorización explícita es ilegal.** Este laboratorio existe para que practiques en un entorno controlado y legal.

---

Creado por [@guajilodev](https://github.com/guajilodev) | OWASP Top 10 2025
