# Despliegue en IONOS con GitHub Actions y SFTP

Esta guia describe como desplegar Gym Tracker en un hosting Linux de IONOS usando GitHub Actions y SFTP. Asume que el usuario SFTP tiene como carpeta raiz la carpeta del proyecto.

Fuentes utiles de IONOS:

- Hosting: https://www.ionos.es/ayuda/hosting/
- Datos FTP/SFTP: https://www.ionos.es/ayuda/hosting/configurar-y-gestionar-accesos-ftp/datos-de-acceso-ftp/sftp-en-hosting-de-ionos/
- Crear base MySQL/MariaDB: https://www.ionos.es/ayuda/hosting/configurar-una-base-de-datos-mysql/configurar-una-base-de-datos-mysql-en-11-ionos/
- Abrir phpMyAdmin: https://www.ionos.es/ayuda/hosting/usar-una-base-de-datos-mysql-para-proyectos-web/abrir-phpmyadmin-para-administrar-una-base-de-datos-mysqlmariadb/
- Datos de acceso MySQL/MariaDB: https://www.ionos.es/ayuda/hosting/usar-una-base-de-datos-mysql-para-proyectos-web/consultar-los-datos-de-acceso-de-mysql-en-11-ionos/
- Version PHP: https://www.ionos.es/ayuda/hosting/gestionar-la-version-de-php/ver-o-cambiar-la-version-de-php/

## 1. Preparar IONOS

1. Entra en tu cuenta IONOS.
2. Ve a `Menu > Hosting` y selecciona el contrato correcto.
3. En PHP, selecciona una version PHP 8.x para el dominio. El proyecto requiere PHP 8+.
4. En SFTP & SSH, crea o revisa el usuario SFTP que usara GitHub Actions.
5. Anota estos datos del usuario SFTP:
   - Host, por ejemplo `access123456789.webspace-data.io`.
   - Usuario.
   - Password.
   - Puerto `22`.
6. Confirma que la raiz SFTP del usuario es la carpeta del proyecto. En esta guia el despliegue subira a `/`.

Si IONOS permite apuntar el document root del dominio directamente a `public/`, esa es la configuracion ideal: el navegador solo vera `index.html`, `api.php` y `assets/`, y el codigo PHP de `app/`, `vendor/`, `storage/` y `.env` quedara fuera de la raiz publica.

Si no puedes apuntar el dominio a `public/`, usa la raiz del proyecto, pero confirma por SFTP que existe el archivo `.htaccess` en esa raiz. Ese archivo reenvia `index.html`, `api.php` y `assets/` hacia `public/`. Sin ese `.htaccess`, el dominio puede quedarse en blanco o no encontrar ningun `index` valido en la raiz.

## 2. Crear la base de datos

1. En IONOS, ve a `Menu > Hosting > Bases de datos`.
2. Pulsa `Crear base de datos`.
3. Elige MySQL o MariaDB. MariaDB es compatible para este proyecto.
4. Define una password fuerte y guardala inmediatamente en un gestor de contrasenas. IONOS indica que la password no se puede consultar despues.
5. Cuando la base este creada, abre sus detalles y anota:
   - Host.
   - Nombre de base de datos.
   - Usuario.
   - Puerto, si IONOS lo muestra.
6. Abre phpMyAdmin desde IONOS.
7. Importa `database/schema.sql`.

El schema ya incluye la tabla `rate_limits`, necesaria para el rate limiting de login, registro, reset, verificacion e importaciones.

Importante: IONOS indica que el servidor MySQL solo es accesible desde la red de IONOS. La app local debe usar una base local propia; la `.env` de produccion debe usar los datos de IONOS.

## 3. Crear `.env` de produccion

El archivo `.env` no debe estar en Git. Crea el archivo en la raiz remota del proyecto por SFTP o desde el gestor de archivos de IONOS.

Ejemplo:

```env
APP_ENV=production
APP_URL=https://tu-dominio.com
SESSION_SECURE_COOKIE=1

DB_HOST=host-de-ionos
DB_NAME=nombre-base-ionos
DB_USER=usuario-base-ionos
DB_PASS=password-base-ionos
DB_CHARSET=utf8mb4

SMTP_HOST=smtp.example.com
SMTP_PORT=587
SMTP_USER=usuario-smtp
SMTP_PASS=password-smtp
SMTP_FROM=no-reply@tu-dominio.com
SMTP_FROM_NAME=Gym Tracker
SMTP_SECURE=tls
```

Notas:

- `APP_URL` debe ser HTTPS y coincidir con el dominio real.
- `SESSION_SECURE_COOKIE=1` fuerza cookies seguras en produccion.
- Usa SMTP real de IONOS o de otro proveedor. Sin SMTP funcional, registro, verificacion y reset no completaran el flujo de email.

## 4. Configurar secrets en GitHub

En GitHub:

1. Abre el repositorio.
2. Ve a `Settings > Secrets and variables > Actions`.
3. Crea estos secrets:

```text
IONOS_SFTP_HOST=access123456789.webspace-data.io
IONOS_SFTP_USER=tu_usuario_sftp
IONOS_SFTP_PASSWORD=tu_password_sftp
IONOS_SFTP_PORT=22
```

No guardes credenciales SFTP, credenciales MySQL ni SMTP en el repositorio.

## 5. Workflow GitHub Actions

Crea `.github/workflows/deploy-ionos.yml` con este contenido:

```yaml
name: Deploy to IONOS

on:
  push:
    branches:
      - main

jobs:
  test-and-deploy:
    runs-on: ubuntu-latest

    steps:
      - name: Checkout
        uses: actions/checkout@v4

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'
          extensions: mbstring, pdo_mysql
          coverage: none

      - name: Setup Node
        uses: actions/setup-node@v4
        with:
          node-version: '22'

      - name: Validate Composer
        run: composer validate --strict

      - name: Install dev dependencies
        run: composer install --prefer-dist --no-progress --no-interaction

      - name: Run PHPUnit
        run: composer test

      - name: Lint PHP
        run: composer lint

      - name: Check JavaScript syntax
        run: node --check public/assets/app.js

      - name: Install production dependencies
        run: composer install --no-dev --prefer-dist --no-progress --no-interaction --optimize-autoloader

      - name: Deploy over SFTP
        uses: wlixcc/SFTP-Deploy-Action@v1.2.6
        with:
          server: ${{ secrets.IONOS_SFTP_HOST }}
          username: ${{ secrets.IONOS_SFTP_USER }}
          password: ${{ secrets.IONOS_SFTP_PASSWORD }}
          port: ${{ secrets.IONOS_SFTP_PORT }}
          local_path: './'
          remote_path: '/'
          sftp_only: true
          delete_remote_files: false
          exclude: |
            **/.git*
            **/.github/**
            **/.env
            **/.superpowers/**
            **/.composer-cache/**
            **/.phpunit*
            **/tests/**
            **/node_modules/**
```

Este workflow sube `vendor/` ya construido porque un hosting compartido puede no permitir ejecutar Composer en servidor.

`delete_remote_files: false` evita borrar accidentalmente `.env`, `storage/` u otros archivos gestionados en hosting. Si algun dia se cambia a borrado remoto, hay que excluir explicitamente `.env` y `storage/`.

## 6. Primer despliegue

1. Haz una copia manual de seguridad del espacio web si ya hay archivos.
2. Haz una copia SQL de la base si ya existe.
3. Confirma que `database/schema.sql` ya esta importado.
4. Confirma que `.env` existe en la raiz remota.
5. Haz push a `main`.
6. Revisa el job `Deploy to IONOS` en GitHub Actions.
7. Si el deploy termina bien, abre:

```text
https://tu-dominio.com/index.html
```

Si el dominio muestra una pagina en blanco, revisa primero estas dos cosas:

1. El document root del dominio en IONOS debe apuntar a `public/` si tu plan lo permite.
2. Si el dominio apunta a la raiz del proyecto, el archivo `.htaccess` de la raiz debe existir en el servidor remoto. Algunos patrones de subida como `./*` pueden dejar fuera archivos ocultos.

## 7. Checklist postdeploy

1. Abrir `/index.html`.
2. Registrar un usuario.
3. Verificar el email.
4. Hacer login.
5. Crear un entrenamiento.
6. Crear un ejercicio.
7. Guardar un registro.
8. Exportar backup JSON desde Gestion > Datos.
9. Probar import preview con un CSV pequeno de ejercicios.
10. Opcional: verificar con una peticion manual que una mutacion sin `X-CSRF-Token` devuelve `403`.

## 8. Rollback

Antes del primer despliegue:

- Descarga una copia del espacio web por SFTP.
- Exporta un dump SQL desde phpMyAdmin.

Si un despliegue falla:

1. Revertir el commit en Git y hacer push a `main`, o volver a ejecutar el workflow desde un commit anterior.
2. Si hace falta restaurar archivos, sube el backup manual por SFTP.
3. Si hace falta restaurar datos, usa phpMyAdmin para restaurar el dump SQL.

El backup JSON de la app sirve como apoyo funcional para recuperar datos de usuario, pero no sustituye un dump SQL completo.

## 9. Recordatorios de seguridad

- No commitear `.env`.
- No subir `tests/` ni `.github/` al hosting si no hace falta.
- Mantener HTTPS activo.
- Mantener `APP_ENV=production`.
- Mantener `SESSION_SECURE_COOKIE=1`.
- Usar contrasenas distintas para SFTP, MySQL y SMTP.
- Rotar secrets de GitHub si alguien deja de tener acceso al proyecto.
