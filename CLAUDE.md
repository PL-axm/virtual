# Proyecto Moodle - virtual.enelmapa.co

## Objetivo
Desplegar una instancia de Moodle LMS en producción, alojada en un servidor cPanel
accesible por SSH, bajo el subdominio `virtual.enelmapa.co`.

## Servidor (verificado 2026-09-16)

| Dato | Valor |
|---|---|
| IP compartida | 181.79.0.25 |
| Usuario cPanel | moralesbilma |
| Hostname | srv01 |
| Dominio principal | credirapido.info |
| Home | /home/moralesbilma |
| Tipo | Hosting compartido (CloudLinux + cPanel, AlmaLinux) |
| SSH | Puerto 22, operativo |
| Disco | 713GB total, 631GB disponibles (12% uso) |
| SSL | **Expirado** — renovar antes de producción |

### PHP (verificado)
| Dato | Valor | Requerido Moodle | Estado |
|---|---|---|---|
| Versión por defecto | 8.0.30 (cgi-fcgi) | 8.1+ | **INSUFICIENTE** |
| PHP 8.1 disponible | /opt/alt/php81/usr/bin/php | 8.1+ | OK |
| PHP 8.3 disponible | /opt/alt/php83/usr/bin/php | 8.1+ | OK (recomendada) |
| PHP 8.4 disponible | /opt/alt/php84/usr/bin/php | 8.1+ | OK |
| memory_limit | 512M | 256M+ | OK |
| max_input_vars | 1000 | 5000+ | **CAMBIAR** |
| upload_max_filesize | 4M | 50M+ recomendado | **CAMBIAR** |
| post_max_size | 1024M | — | OK |

### Extensiones PHP presentes (verificado)
intl, soap, zip, gd, curl, mbstring, openssl, mysqli, pdo_mysql, json, xml,
xmlreader, xmlwriter, iconv, session, ctype, dom, simplexml, sockets, fileinfo,
bcmath, bz2, exif, imagick, imap, pcntl, phar, posix, readline, xsl, zlib.

**Falta**: xmlrpc (removida en PHP 8.0+, Moodle la recomienda pero no es obligatoria).

### Base de datos
- MariaDB 11.4.13 — **OK** (Moodle requiere MariaDB 10.6+)

### Git
- git 2.48.2 — disponible en el servidor

### Contexto del servidor
- `enelmapa.co` corre como app Node.js (Passenger) en `/home/moralesbilma/public_html/enelmapa.co` — **NO TOCAR**
- `virtual.enelmapa.co` → directorio ya existe: `/home/moralesbilma/public_html/virtual.enelmapa.co`
- Dominio principal `credirapido.info` tiene WordPress instalado en `public_html/`
- Hay otros subdominios activos (caficultor, circasia, salento, etc.) — no tocar

## Acciones previas al despliegue

### 1. Cambiar versión PHP del subdominio a 8.3
En cPanel → MultiPHP Manager → seleccionar `virtual.enelmapa.co` → PHP 8.3.
O por SSH usar la ruta directa: `/opt/alt/php83/usr/bin/php`.

### 2. Ajustar php.ini del subdominio
En cPanel → MultiPHP INI Editor → seleccionar `virtual.enelmapa.co`:
- `max_input_vars` = 5000
- `upload_max_filesize` = 256M

O crear/editar `/home/moralesbilma/public_html/virtual.enelmapa.co/php.ini`:
```ini
max_input_vars = 5000
upload_max_filesize = 256M
```

### 3. Renovar SSL
Desde cPanel → SSL/TLS o AutoSSL → renovar certificado.

## Pasos del despliegue

### 1. Crear base de datos en cPanel
- cPanel → MySQL Databases
- Nombre BD: `moralesb_moodle` (cPanel prefija con el usuario)
- Usuario BD: `moralesb_moodleuser`
- Contraseña: generar una segura
- Asignar TODOS los privilegios al usuario sobre la BD

### 2. Descargar Moodle en el servidor (vía SSH)
```bash
cd /home/moralesbilma/public_html/virtual.enelmapa.co
# Limpiar el directorio si tiene algo
ls -la

# Descargar Moodle 4.5 (última LTS)
wget https://download.moodle.org/download.php/direct/stable405/moodle-latest-405.tgz
tar -xzf moodle-latest-405.tgz --strip-components=1
rm moodle-latest-405.tgz
```

### 3. Crear directorio de datos (fuera del docroot)
```bash
mkdir /home/moralesbilma/moodledata
chmod 770 /home/moralesbilma/moodledata
```

### 4. Instalar Moodle por CLI
```bash
cd /home/moralesbilma/public_html/virtual.enelmapa.co
/opt/alt/php83/usr/bin/php admin/cli/install.php \
  --wwwroot=https://virtual.enelmapa.co \
  --dataroot=/home/moralesbilma/moodledata \
  --dbtype=mariadb \
  --dbhost=localhost \
  --dbname=moralesb_moodle \
  --dbuser=moralesb_moodleuser \
  --dbpass=TU_PASSWORD_AQUI \
  --fullname="Plataforma Virtual" \
  --shortname="Virtual" \
  --adminuser=admin \
  --adminpass=TU_ADMIN_PASS \
  --adminemail=maechavarriaor@gmail.com \
  --non-interactive \
  --agree-license
```

### 5. Configurar Cron (en cPanel → Cron Jobs)
```bash
*/5 * * * * /opt/alt/php83/usr/bin/php /home/moralesbilma/public_html/virtual.enelmapa.co/admin/cli/cron.php > /dev/null 2>&1
```

## Arquitectura

```
GitHub (este repo)          →  Servidor cPanel (producción)
  - Scripts de despliegue       - Subdominio: virtual.enelmapa.co
  - Configuración               - Ruta: /home/moralesbilma/public_html/virtual.enelmapa.co
  - Temas personalizados        - Datos: /home/moralesbilma/moodledata
  - Plugins                     - BD: MariaDB 11.4 (moralesb_moodle)
                                 - PHP 8.3 (CloudLinux selector)
                                 - Cron cada 5 min
```

## Reglas del proyecto
- NUNCA commitear credenciales, contraseñas o datos sensibles
- El archivo `config.php` de Moodle va en `.gitignore`
- Solo se versiona un `config.php.example` como template
- Los backups de base de datos NO van al repositorio
- El directorio `moodledata/` NO va al repositorio
- NO TOCAR nada en `enelmapa.co/`, `credirapido.info`, ni los otros subdominios
