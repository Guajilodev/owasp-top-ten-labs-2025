# Nexo - OWASP Top 10 2025 Labs

> **Un laboratorio deliberadamente vulnerable para aprender seguridad web**

---

## !! ADVERTENCIA DE SEGURIDAD !!

**Este proyecto contiene vulnerabilidades INTENCIONALES.** Fue diseñado exclusivamente para fines educativos.

- **NO** lo ejecutes en un servidor de produccion sin las protecciones documentadas
- **NO** lo expongas a internet sin aislamiento de red
- **NO** lo uses como base para aplicaciones reales
- **SI** lo usas para aprender, practicar y entender seguridad web

---

## Que es Nexo?

**Nexo** es una plataforma SaaS ficticia de gestion empresarial. Cada modulo de la aplicacion contiene una vulnerabilidad del OWASP Top 10 2025, presentada en un contexto realista y reconocible.

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
- Version vulnerable (para explotar)
- Version segura (para comparar)
- Panel lateral con explicacion y referencia a caso real

## Quick Start - Desarrollo Local

### Requisitos
- Docker + Docker Compose
- Git

### Instalacion

```bash
# Clonar el repositorio
git clone https://github.com/guajilodev/owasp2025.git
cd owasp2025

# Copiar variables de entorno
cp .env.example .env

# Levantar los contenedores
docker compose up -d

# Abrir en el navegador
open http://localhost:8080
```

La base de datos se inicializa automaticamente con datos de ejemplo.

### Reset manual

```bash
# Re-ejecutar el script de inicializacion
docker exec owasp-db mariadb -u"$NEXO_DB_USER" -p"$NEXO_DB_PASS" nexo_labs \
  -e "SOURCE /docker-entrypoint-initdb.d/init.sql"
```

## Deploy en Produccion (Seguro)

Si queres exponer el lab a internet (para workshops, clases, CTFs), **SEGUI ESTAS INSTRUCCIONES**. Sin ellas, cualquiera que explote las vulnerabilidades podria comprometer tu servidor.

### Arquitectura de Produccion

```
Internet
    |
    v
[nginx host:443] -----> [nexolab-web:80] -----> [nexolab-db:3306]
     SSL                  |                           |
     Rate Limit           |-- frontend network        |-- backend network
                          |   (port publishing)           (internal only)
                          |
                    [iptables DOCKER-USER]
                          |
                          v
                    BLOQUEA salida a internet
                    (sin exfiltracion, sin C2)
```

### Principios de Seguridad

1. **Aislamiento de red**: El contenedor web NO puede conectarse a internet
2. **Redes separadas**: `frontend` para recibir requests, `backend` (internal) para DB
3. **IP fija**: El contenedor web tiene IP fija para reglas de firewall persistentes
4. **Sin credenciales en codigo**: Todo via variables de entorno
5. **Capabilities minimas**: `cap_drop: ALL` + solo las necesarias
6. **Reset automatico**: Cron job cada 4 horas limpia datos y uploads

### Pasos de Deploy

#### 1. Clonar en el servidor

```bash
cd /opt
git clone https://github.com/guajilodev/owasp2025.git
cd owasp2025
```

#### 2. Crear archivo .env con credenciales seguras

```bash
cat > .env << EOF
NEXO_DB_HOST=db
NEXO_DB_NAME=nexo_labs
NEXO_DB_USER=nexo_prod_user
NEXO_DB_PASS=$(openssl rand -base64 24)
NEXO_DB_ROOT_PASS=$(openssl rand -base64 24)
NEXO_ENV=production
EOF

chmod 600 .env
```

#### 3. Crear docker-compose.prod.yml

```yaml
services:
  web:
    build: ./php
    container_name: nexolab-web
    ports:
      - "127.0.0.1:8082:80"
    volumes:
      - ./src:/var/www/html
    tmpfs:
      - /var/www/html/a02_admin/uploads:size=50m,mode=1777
      - /var/log/nexo:size=20m
    environment:
      - NEXO_DB_HOST=${NEXO_DB_HOST}
      - NEXO_DB_NAME=${NEXO_DB_NAME}
      - NEXO_DB_USER=${NEXO_DB_USER}
      - NEXO_DB_PASS=${NEXO_DB_PASS}
      - NEXO_ENV=${NEXO_ENV}
    networks:
      frontend:
        ipv4_address: 172.30.0.10
      backend:
    depends_on:
      - db
    restart: unless-stopped
    security_opt:
      - no-new-privileges:true
    cap_drop:
      - ALL
    cap_add:
      - CHOWN
      - SETUID
      - SETGID
      - DAC_OVERRIDE

  db:
    image: mariadb:10.11
    container_name: nexolab-db
    volumes:
      - ./mysql/init.sql:/docker-entrypoint-initdb.d/init.sql:ro
      - ./mysql/my.cnf:/etc/mysql/conf.d/my.cnf:ro
      - nexolab_db:/var/lib/mysql
    environment:
      - MARIADB_ROOT_PASSWORD=${NEXO_DB_ROOT_PASS}
      - MARIADB_DATABASE=${NEXO_DB_NAME}
      - MARIADB_USER=${NEXO_DB_USER}
      - MARIADB_PASSWORD=${NEXO_DB_PASS}
    networks:
      - backend
    restart: unless-stopped
    security_opt:
      - no-new-privileges:true
    cap_drop:
      - ALL
    cap_add:
      - CHOWN
      - SETUID
      - SETGID
      - DAC_OVERRIDE

networks:
  frontend:
    driver: bridge
    ipam:
      config:
        - subnet: 172.30.0.0/24
  backend:
    driver: bridge
    internal: true

volumes:
  nexolab_db:
    name: nexolab_db_prod
```

#### 4. Configurar nginx (en el host)

```nginx
server {
    listen 80;
    server_name tu-dominio.com;
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl http2;
    server_name tu-dominio.com;

    ssl_certificate /etc/letsencrypt/live/tu-dominio.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/tu-dominio.com/privkey.pem;

    # Rate limiting
    limit_req_zone $binary_remote_addr zone=nexo:10m rate=10r/s;
    limit_req zone=nexo burst=20 nodelay;

    location / {
        proxy_pass http://127.0.0.1:8082;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}
```

#### 5. Obtener certificado SSL

```bash
certbot --nginx -d tu-dominio.com
```

#### 6. Configurar firewall de aislamiento

```bash
# Crear servicio de systemd para persistencia
cat > /etc/systemd/system/nexolab-firewall.service << 'EOF'
[Unit]
Description=Nexolab firewall rules for container isolation
After=docker.service
Requires=docker.service

[Service]
Type=oneshot
RemainAfterExit=yes
ExecStart=/bin/bash -c "iptables -I DOCKER-USER -s 172.30.0.10 -d 172.16.0.0/12 -j ACCEPT; iptables -I DOCKER-USER -s 172.30.0.10 -d 10.0.0.0/8 -j ACCEPT; iptables -I DOCKER-USER -s 172.30.0.10 -d 127.0.0.0/8 -j ACCEPT; iptables -A DOCKER-USER -s 172.30.0.10 -m state --state NEW -j DROP"

[Install]
WantedBy=multi-user.target
EOF

systemctl daemon-reload
systemctl enable --now nexolab-firewall.service
```

#### 7. Configurar cron de reset

```bash
cat > /etc/cron.d/nexolab-reset << 'EOF'
SHELL=/bin/bash
PATH=/usr/local/sbin:/usr/local/bin:/sbin:/bin:/usr/sbin:/usr/bin

# Reset DB cada 4 horas
0 */4 * * * root . /opt/owasp2025/.env && docker exec nexolab-db mariadb -u"${NEXO_DB_USER}" -p"${NEXO_DB_PASS}" nexo_labs -e "SOURCE /docker-entrypoint-initdb.d/init.sql" >> /var/log/nexolab-reset.log 2>&1

# Limpiar uploads
0 */4 * * * root docker exec nexolab-web find /var/www/html/a02_admin/uploads -type f -not -name ".gitkeep" -delete 2>/dev/null

# Limpiar logs del lab A09
0 */4 * * * root docker exec nexolab-web sh -c "> /var/log/nexo/app.log" 2>/dev/null

# Renovar SSL (semanal)
0 3 * * 1 root certbot renew --quiet && systemctl reload nginx
EOF

chmod 644 /etc/cron.d/nexolab-reset
```

#### 8. Levantar y verificar

```bash
docker compose -f docker-compose.prod.yml up -d

# Verificar que la app funciona
curl -s https://tu-dominio.com/ | grep -o "Nexo"

# Verificar que el contenedor NO puede salir a internet
docker exec nexolab-web curl -s --connect-timeout 3 https://google.com || echo "BLOCKED - OK"
```

## Verificacion de Aislamiento

Despues del deploy, verifica que el contenedor web este correctamente aislado:

```bash
# Debe fallar (timeout/blocked)
docker exec nexolab-web curl -s --connect-timeout 5 https://google.com

# Debe funcionar (DB accesible)
docker exec nexolab-web php -r "new PDO('mysql:host=db;dbname=nexo_labs', 'user', 'pass');"
```

## Estructura del Proyecto

```
owasp2025/
├── src/                    # Codigo fuente PHP
│   ├── a01_facturas/       # Lab A01 - Broken Access Control
│   ├── a02_admin/          # Lab A02 - Security Misconfiguration
│   ├── a03_plugins/        # Lab A03 - Supply Chain
│   ├── ...
│   ├── config/             # Configuracion de DB
│   └── shared/             # Header, footer, panel lateral
├── php/                    # Dockerfile de PHP/Apache
├── mysql/                  # Init SQL y config de MariaDB
├── nginx/                  # Config de nginx (dev)
├── cron/                   # Scripts de reset
├── docker-compose.yml      # Desarrollo local
└── PRD.md                  # Documento de diseño completo
```

## Contribuir

Las contribuciones son bienvenidas! Por favor:

1. Fork del repositorio
2. Crea una rama (`git checkout -b feature/nueva-vuln`)
3. Commit con mensaje descriptivo
4. Push y abre un Pull Request

### Ideas para contribuir
- Traducciones (ingles, portugues)
- Nuevos escenarios para vulnerabilidades existentes
- Mejoras en la documentacion
- Fix de bugs en la infraestructura (NO en las vulnerabilidades intencionales)

## Licencia

MIT License - Ver [LICENSE](LICENSE) para mas detalles.

## Disclaimer Legal

Este software se proporciona "tal cual", sin garantias de ningun tipo. El autor no es responsable de:

- Uso indebido del conocimiento adquirido con este laboratorio
- Danos causados por ejecutar este software sin las protecciones adecuadas
- Consecuencias legales de atacar sistemas sin autorizacion

**Hackear sistemas sin autorizacion explicita es ilegal.** Este laboratorio existe para que practiques en un entorno controlado y legal.

---

Creado por [@guajilodev](https://github.com/guajilodev) | OWASP Top 10 2025
