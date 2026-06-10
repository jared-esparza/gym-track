# Gym Tracker - Project Context

## Qué es

Gym Tracker es una app web mobile-first para registrar marcas de gimnasio durante una sesión. La V1 está pensada para desplegarse en hosting clásico tipo IONOS con PHP/MySQL, sin framework pesado ni proceso de build.

Funcionalidad principal:

- Registro abierto de usuarios con verificación de email.
- Login con sesiones PHP.
- Recuperación de contraseña por token.
- Gestión de entrenamientos, entendidos como rutinas/listas de grupos musculares.
- Grupos musculares fijos precargados.
- Ejercicios creados por usuario, asociados a un grupo muscular.
- Gestión de ejercicios desde la pestaña Gestión: crear, editar y eliminar.
- Gimnasios opcionales para separar marcas cuando cambia el material disponible.
- Registro de marcas por ejercicio, entrenamiento activo y, si la preferencia está activada, gimnasio.
- Histórico en tarjetas móviles, tabla desktop y gráfico de mejor marca diaria.
- Corrección y eliminación de registros.

## Stack

- PHP 8+.
- MySQL/MariaDB.
- HTML/CSS/JavaScript sin bundler.
- Chart.js desde CDN en `public/index.html`.
- PHPMailer vía Composer para SMTP.

Composer ya está configurado en `composer.json`. En local, `APP_ENV=local` hace que los emails se escriban en `storage/logs/mail.log` en vez de enviarse por SMTP.

## Estructura

- `app/`
  - `bootstrap.php`: carga autoload de Composer o autoload simple propio, e inicia sesión.
  - `Config.php`: lee `.env` y variables de entorno.
  - `Database.php`: crea conexión PDO MySQL.
  - `Auth.php`: sesiones, usuario actual, login/logout y protección de endpoints.
  - `Security.php`: token CSRF y cabeceras de seguridad.
  - `RateLimiter.php`: limitador de intentos por accion e identificador.
  - `ImportService.php`: normalizacion y previsualizacion testeable de importaciones.
  - `Request.php`: parseo de JSON o `POST`.
  - `Response.php`: respuestas JSON y errores.
  - `Mailer.php`: PHPMailer en producción; log local si `APP_ENV=local`.
- `public/`
  - `index.html`: SPA simple con vistas de auth, entrenar, histórico y gestión.
  - `api.php`: router API JSON de toda la app.
  - `assets/app.css`: estilos mobile-first.
  - `assets/app.js`: estado de frontend, llamadas API e interacción de pantalla.
  - `.htaccess`: cabeceras básicas si se sirve con Apache.
- `database/schema.sql`
  - Crea todas las tablas e inserta los grupos musculares fijos.
- `docs/PROJECT_STRUCTURE.md`
  - Guía humana para orientarse por carpetas y archivos.
- `docs/IONOS_DEPLOYMENT.md`
  - Guía de despliegue en IONOS con GitHub Actions y SFTP.
- `tests/`
  - Tests PHPUnit para seguridad e import/export.
- `scripts/lint.php`
  - Lint PHP portable usado por `composer lint`.
- `phpunit.xml`
  - Configuración de PHPUnit.
- `.env.example`
  - Plantilla de configuración.
- `.htaccess`
  - Reglas básicas para servir `public/` desde hosting donde no se pueda apuntar el document root.
- `README.md`
  - Instrucciones de instalación, despliegue y enlaces de documentación.

## Modelo de datos

Tablas principales:

- `users`: email, hash de password, verificación de email, preferencia `gyms_enabled`, tokens de reset.
- `muscle_groups`: catálogo fijo del sistema.
- `workouts`: entrenamientos/rutinas del usuario.
- `workout_muscle_groups`: relación N:M entre entrenamientos y grupos musculares.
- `gyms`: gimnasios del usuario, usados solo si `users.gyms_enabled` está activo.
- `exercises`: ejercicios del usuario con grupo, tipo de marca y notas permanentes.
- `exercise_gyms`: allow-list opcional N:M entre ejercicios y gimnasios; si un ejercicio no tiene filas aquí, se considera disponible en todos los gimnasios del usuario.
- `records`: marcas guardadas con usuario, ejercicio, entrenamiento usado, gimnasio opcional, valor, tipo, fecha/hora y nota puntual.
- `rate_limits`: ventanas de intentos para login, registro, reset, verificacion e importaciones.

Tipos de marca válidos:

- `kg`
- `reps`
- `min`
- `km`

La RM en V1 es simplemente la mejor marca histórica numérica de un ejercicio (`MAX(value)`), no una 1RM estimada. Si los gimnasios están activados, la RM y el último registro se calculan dentro del gimnasio seleccionado; si están desactivados, se mezclan todos los registros como antes.

Notas de integridad:

- Si se elimina un ejercicio, MySQL elimina también sus registros por `records.exercise_id ON DELETE CASCADE`.
- Si se elimina un usuario, se eliminan sus entrenamientos, ejercicios y registros.
- Si se intenta eliminar un entrenamiento con registros asociados, la API lo bloquea para no dejar histórico sin rutina.
- Si se intenta eliminar un gimnasio con registros asociados, la API lo bloquea; si solo aparece en `exercise_gyms`, se puede eliminar y se limpian esas relaciones por cascada.
- Los registros anteriores a la feature de gimnasios quedan con `records.gym_id = NULL`, que la UI muestra como "Sin gimnasio".

## Endpoints API

Todos están en `public/api.php` y se seleccionan con `?action=...`.

Auth:

- `GET api.php?action=me`
- `POST api.php?action=register`
- `POST api.php?action=login`
- `POST api.php?action=logout`
- `GET api.php?action=verify-email&token=...`
- `POST api.php?action=forgot-password`
- `POST api.php?action=reset-password`

Datos:

- `GET api.php?action=bootstrap`
- `POST api.php?action=preferences`
- `GET/POST api.php?action=gyms`
- `POST/DELETE api.php?action=gym&id=...`
- `GET/POST api.php?action=workouts`
- `GET/POST/DELETE api.php?action=workout&id=...`
- `GET api.php?action=exercises[&muscle_group_id=...][&workout_id=...][&gym_id=...]`
- `POST/DELETE api.php?action=exercise&id=...`
- `GET api.php?action=exercise-summary&exercise_id=...[&gym_id=...]`
- `POST/DELETE api.php?action=records`
- `GET api.php?action=history&exercise_id=...[&gym_id=ID|none]`
- `GET api.php?action=export&format=json`
- `GET api.php?action=export&format=csv&type=exercises|workouts|records`
- `POST api.php?action=import-preview`
- `POST api.php?action=import-confirm`
- `POST api.php?action=import-cancel`

Los endpoints protegidos usan `Auth::requireUser()` y validan ownership antes de leer/modificar gyms, workouts, exercises y records. Todas las mutaciones `POST`/`DELETE` requieren header `X-CSRF-Token`, que el frontend obtiene desde `GET me`.

Detalles relevantes:

- `POST preferences` actualiza preferencias de usuario como `gyms_enabled`.
- `GET/POST gyms` lista o crea gimnasios; `POST/DELETE gym&id=...` edita o elimina un gimnasio del usuario.
- `GET exercises` acepta filtros opcionales: por `muscle_group_id`, por `workout_id` si se quiere limitar a los grupos de un entrenamiento, y por `gym_id` si los gimnasios están activos.
- `GET exercises` devuelve `record_count` y `gym_ids`; `record_count` sirve para bloquear el tipo de marca en edición y `gym_ids` representa la allow-list de gimnasios. Lista vacía significa "todos los gimnasios".
- `POST exercise` crea ejercicios si no recibe `id`.
- `POST exercise` edita nombre, grupo, tipo de marca, notas y `gym_ids` si recibe `id`.
- Si un ejercicio ya tiene registros, no se permite cambiar su `metric_type`.
- `DELETE exercise` elimina el ejercicio y sus registros asociados por cascada.
- `GET exercise-summary` acepta `gym_id` si los gimnasios están activos; filtra RM y último registro por ese gimnasio, o por `none` para registros sin gimnasio.
- `POST records` exige `gym_id` solo si `gyms_enabled` está activo. La edición de registros puede corregir `gym_id` y acepta `none` para dejarlo sin gimnasio.
- `GET history` acepta `gym_id` si los gimnasios están activos; no hay vista "todos los gimnasios" en ese modo.
- `GET export&format=json` descarga un backup restaurable con `schema: "gym-tracker-export"`, version 2, gimnasios, entrenamientos, ejercicios y registros.
- `GET export&format=csv&type=exercises` descarga un CSV importable con columnas `muscle_group,name,metric_type,notes,gyms`.
- `GET export&format=csv&type=workouts|records` descarga CSVs de consulta; records incluye columna `gym`.
- `POST import-preview` valida un JSON de backup o un CSV de ejercicios y guarda el plan normalizado en sesion con `import_token`.
- `POST import-confirm` aplica el plan validado en transaccion; `POST import-cancel` descarta la previsualizacion.
- La importacion acepta backups v1 y v2. V1 entra sin gimnasios; V2 incluye `gyms`, `exercises[].gyms` y `records[].gym`.
- La importacion fusiona sin duplicar: gimnasios por nombre, entrenamientos por nombre, ejercicios por grupo+nombre y registros por ejercicio+entrenamiento+gimnasio+fecha+valor+nota.
- Login, registro, forgot/reset password, verificacion e import preview tienen rate limiting y devuelven `429` si se supera el limite.

## Flujo UX

Pantallas principales:

- Auth:
  - Login, registro, forgot password y reset password.
  - Los formularios son mutuamente excluyentes: al abrir registro, recuperacion o reset se oculta el login para evitar estados mezclados.
- Entrenar:
  - Elegir entrenamiento activo.
  - Si los gimnasios estan activos, elegir gimnasio antes de elegir ejercicio; sin gimnasio seleccionado se ocultan selector de ejercicio y formulario de marca.
  - Elegir grupo muscular permitido por ese entrenamiento, o dejarlo en "Todos los grupos del entrenamiento".
  - Si no hay grupo seleccionado, el selector de ejercicio muestra todos los ejercicios de los grupos incluidos en el entrenamiento activo.
  - Con gimnasios activos, el selector de ejercicio se filtra ademas por la allow-list del gimnasio seleccionado.
  - Elegir o crear ejercicio.
  - Si se crea un ejercicio rapido con gimnasios activos, queda limitado por defecto al gimnasio actual.
  - Con gimnasios activos, RM y ultimo registro pertenecen al gimnasio seleccionado.
  - Ver RM, último registro y notas.
  - Guardar marca y nota puntual.
  - Con gimnasios activos, la marca se guarda con `gym_id`.
  - Tras guardar una marca, se mantiene el entrenamiento activo y el gimnasio, pero se limpian grupo, ejercicio y panel para registrar otro ejercicio.
- Histórico:
  - Elegir grupo y ejercicio, o dejar el grupo en "Todos los grupos" para escoger cualquier ejercicio.
  - Si los gimnasios estan activos, elegir gimnasio o "Sin gimnasio" antes de elegir ejercicio; no hay vista mezclada de todos los gimnasios en ese modo.
  - En la carga inicial, `loadBootstrap()` llama a `loadExercises('history')` para que el selector muestre todos los ejercicios sin esperar un cambio manual de grupo.
  - Con gimnasios activos, grafico, tarjetas y tabla se filtran por el gimnasio seleccionado o por registros "Sin gimnasio".
  - Ver gráfico de mejor marca diaria.
  - Ver registros como tarjetas en móvil y tabla en pantallas amplias.
  - Editar registros con formulario embebido.
  - La edicion permite corregir el gimnasio del registro, incluido dejarlo como "Sin gimnasio".
  - Eliminar registros con confirmación.
- Gestión:
  - Los apartados de Gestion funcionan como acordeon exclusivo y pueden estar todos cerrados. Al entrar en Gestion todos aparecen cerrados por defecto.
  - Apartado de entrenamientos: listar, crear, editar y eliminar.
  - Apartado de gimnasios: activar/desactivar la feature, listar, crear, editar y eliminar gimnasios.
  - No se puede eliminar un gimnasio con registros asociados; si solo aparece en allow-lists de ejercicios, se elimina y se limpian esas relaciones.
  - Apartados de gimnasios, entrenamientos y ejercicios: listar, crear, editar y eliminar. La edicion se abre dentro de la tarjeta del elemento; la creacion se abre al inicio del apartado correspondiente.
  - Con gimnasios activos, el formulario de ejercicio permite elegir gimnasios permitidos. Sin seleccion significa disponible en todos los gimnasios del usuario.
  - Al eliminar un ejercicio se avisa de que también se eliminarán todos sus registros de marcas asociados.
  - Apartado de datos: exportar backup JSON v2, exportar CSVs e importar con previsualizacion y confirmacion.

Onboarding actual:

- Si el usuario no tiene entrenamientos, se muestra un panel de "Primer entrenamiento" con CTA para ir a Gestión y abrir el formulario de nuevo entrenamiento.

## Cómo probar en local

Con XAMPP:

1. Arrancar MySQL desde `C:\xampp\xampp-control.exe`.
2. Crear base `gym_tracker` en phpMyAdmin.
3. Ejecutar `database/schema.sql`. En una base existente anterior a gimnasios opcionales, aplicar tambien `database/2026-06-10_optional_gyms_migration.sql`.
4. Crear `.env` en la raíz:

```env
APP_ENV=local
APP_URL=http://127.0.0.1:4177

DB_HOST=localhost
DB_NAME=gym_tracker
DB_USER=root
DB_PASS=
DB_CHARSET=utf8mb4
```

5. Levantar servidor:

```powershell
cd C:\Users\jespa\Desktop\proyectos\gym-tracker
php -S 127.0.0.1:4177 -t public
```

6. Abrir `http://127.0.0.1:4177/`.
7. Registrar usuario.
8. Leer enlace de verificación en `storage/logs/mail.log`.
9. Verificar email y entrar.

## Validaciones ya realizadas

Comandos ejecutados correctamente durante el desarrollo:

```powershell
composer validate --strict
composer lint
node --check public\assets\app.js
node .superpowers\implementation-tests\management-plan.test.cjs
composer test
```

También se comprobó que el servidor PHP responde con HTTP 200 para:

- `/index.html`
- `/assets/app.css`
- `/assets/app.js`

## Puntos importantes / deuda conocida

- La app es una primera V1 funcional, pero todavía puede pulirse visualmente.
- Hay suite PHPUnit para helpers de seguridad e importacion, mas una prueba local ignorada en `.superpowers/implementation-tests/management-plan.test.cjs`.
- `database/schema.sql` es la fuente canonica para instalaciones nuevas.
- En bases existentes hay que aplicar SQL incremental para cambios ya desplegados. Actualmente `database/2026-06-10_optional_gyms_migration.sql` anade gimnasios opcionales y se mantiene como archivo local no comprometido hasta decidir si entra al repositorio.
- Si una base antigua no tiene `rate_limits`, crear esa tabla desde `database/schema.sql` antes de activar el rate limiting.
- Chart.js se carga desde CDN, por lo que requiere conexión a internet para ver gráficos.
- El `.htaccess` raíz ayuda en hosting compartido, pero el despliegue ideal es apuntar document root a `public/`.
- Para produccion: `APP_ENV=production`, HTTPS real, `APP_URL` HTTPS, SMTP configurado, `composer install --no-dev`, document root a `public/` y permisos de escritura solo en `storage/`.
- Para desplegar en IONOS, seguir `docs/IONOS_DEPLOYMENT.md`; asume Hosting Linux, SFTP puerto 22, raíz remota como carpeta del proyecto y CI/CD desde GitHub Actions.

## Nota sobre Browser de Codex

En esta máquina el navegador integrado de Codex puede fallar si `node_repl` no arranca con el sandbox de Windows. Se corrigió previamente cambiando en `C:\Users\jespa\.codex\config.toml`:

```toml
[mcp_servers.node_repl]
args = ["--disable-sandbox"]
```

Hay backup en:

```text
C:\Users\jespa\.codex\config.toml.bak-node-repl-sandbox
```

Si vuelve a fallar, se puede verificar la app con el servidor PHP local y `Invoke-WebRequest`.
