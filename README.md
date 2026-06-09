# Gym Tracker

Aplicación web mobile-first para registrar marcas de gimnasio con PHP, MySQL/MariaDB y JavaScript.

## Requisitos

- PHP 8.0 o superior con PDO MySQL.
- MySQL/MariaDB.
- Composer para instalar PHPMailer.
- Hosting apuntando a la carpeta `public/`.

## Instalación local o IONOS

1. Copia `.env.example` a `.env` y ajusta `APP_URL`, credenciales de base de datos y SMTP.
2. Ejecuta `composer install --no-dev`.
3. Crea la base de datos y ejecuta `database/schema.sql`.
4. Configura el document root del hosting en `public/`.
5. Abre `public/index.html` desde el dominio configurado.

Si Composer no está disponible en el hosting, ejecuta `composer install --no-dev` en local y sube también la carpeta `vendor/`.

## SMTP

La app usa PHPMailer si `vendor/autoload.php` existe. En desarrollo, con `APP_ENV=local`, los correos se escriben en `storage/logs/mail.log` para poder probar registro, verificación y reset sin servidor SMTP.

## Uso

- Un usuario se registra con email y contraseña.
- Debe verificar su email antes de iniciar sesión.
- En el primer acceso, crea un entrenamiento seleccionando grupos musculares.
- En `Entrenar`, elige un entrenamiento activo y registra marcas por grupo y ejercicio.
- Después de guardar una marca, se mantiene el entrenamiento activo y se vuelve a elegir grupo/ejercicio para el siguiente registro.
- En `Histórico`, consulta tabla completa, gráfico de mejor marca diaria, edita registros y elimina marcas.
- En `Gestión`, crea/edita/elimina entrenamientos y crea/edita/elimina ejercicios.

## Estructura

- `public/`: raíz web con frontend y API.
- `app/`: configuración, conexión DB, sesión, request/response y mailer.
- `database/schema.sql`: tablas y grupos musculares iniciales.
- `docs/PROJECT_STRUCTURE.md`: guía humana de carpetas, archivos y dónde tocar para cambios manuales.
- `PROJECT_CONTEXT.md`: contexto funcional/técnico actualizado para futuras sesiones de desarrollo.
- `.env.example`: plantilla de configuración.

## Documentación

- [Guía de estructura del proyecto](docs/PROJECT_STRUCTURE.md)
- [Contexto técnico del proyecto](PROJECT_CONTEXT.md)

## Validaciones útiles

```powershell
composer validate --strict
Get-ChildItem -Recurse -Filter *.php | Where-Object { $_.FullName -notlike '*\vendor\*' } | ForEach-Object { php -l $_.FullName }
node --check public\assets\app.js
```
