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

## Estado actual del frontend

La aplicación usa la identidad visual **Cuaderno de hierro**. El rediseño se implementa sin framework ni proceso de build y conserva los contratos de la API y de la base de datos.

Implementado actualmente:

- Sistema visual propio con grafito, azul de discos y naranja cinta; tipografías locales IBM Plex Sans y Barlow Condensed.
- Iconos SVG locales para navegación y acciones comunes.
- Shell responsive con sidebar en escritorio, navegación inferior en móvil, estado activo sincronizado mediante `aria-current` y controles táctiles de al menos 44 px.
- Región de feedback accesible, foco visible, respeto de `prefers-reduced-motion` y diálogo de confirmación reutilizable con restauración de foco.
- Cockpit de Entrenar con contexto de sesión, filtros por grupo, búsqueda tolerante a mayúsculas y diacríticos, listado enriquecido de ejercicios y panel destacado de registro.
- Estado explícito "Elige gimnasio primero" cuando la preferencia está activa y falta la selección.
- Feedback contextual al guardar una marca, incluido el mensaje de nueva RM.
- Histórico con resumen de mejor/última marca, cambio y cantidad; tarjetas móviles, tabla desktop, gráfico con escala legible, estado vacío con acción y fallback cuando Chart.js no está disponible.
- Gestión con instrucciones contextuales en el editor de escritorio, metadatos que envuelven nombres largos y confirmaciones específicas para borrar ejercicios, entrenamientos y gimnasios.
- Acceso con identidad propia y ejemplo de marca; login, registro, recuperación y reset son estados excluyentes.
- Onboarding progresivo para entrenamiento, ejercicio y primera marca, calculado con el endpoint existente `exercises` y sus `record_count`.
- Contratos Node para sistema visual/accesibilidad, shell, responsive, Entrenar, Histórico, Gestión y onboarding.

El plan de rediseño documentado está implementado; no se han cambiado endpoints ni esquema de base de datos.

La especificación y el plan de implementación viven en `docs/superpowers/specs/2026-10-19-cuaderno-de-hierro-design.md` y `docs/superpowers/plans/2026-10-19-cuaderno-de-hierro.md`.

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
  - `assets/fonts/`: IBM Plex Sans y Barlow Condensed autoalojadas, con licencia OFL.
  - `assets/icons/sprite.svg`: símbolos SVG compartidos de navegación y acciones.
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
- `scripts/*-ui-*.test.cjs`
  - Contratos Node sin bundler para identidad/accesibilidad, shell, responsive, Entrenar, Histórico, Gestión y onboarding.
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
  - Filtrar mediante controles visibles por grupo muscular permitido, o dejar "Todos".
  - Buscar ejercicios por nombre o grupo sin distinguir mayúsculas ni diacríticos.
  - Los selects nativos siguen sincronizados como controles accesibles; la lista enriquecida es el recorrido visual principal.
  - Si no hay grupo seleccionado, la lista muestra todos los ejercicios de los grupos incluidos en el entrenamiento activo.
  - Con gimnasios activos, el selector de ejercicio se filtra ademas por la allow-list del gimnasio seleccionado.
  - Elegir o crear ejercicio.
  - Si se crea un ejercicio rapido con gimnasios activos, queda limitado por defecto al gimnasio actual.
  - Con gimnasios activos, RM y ultimo registro pertenecen al gimnasio seleccionado.
  - Ver RM, último registro y notas en el cockpit destacado.
  - Guardar marca y, opcionalmente, desplegar "Añadir nota" para la nota puntual.
  - Con gimnasios activos, la marca se guarda con `gym_id`.
  - Tras guardar una marca, se mantienen entrenamiento, gimnasio, grupo y ejercicio; se refrescan RM y último registro y se anuncia si es una nueva RM.
- Histórico:
  - Elegir grupo y ejercicio, o dejar el grupo en "Todos los grupos" para escoger cualquier ejercicio.
  - Si los gimnasios estan activos, elegir gimnasio o "Sin gimnasio" antes de elegir ejercicio; no hay vista mezclada de todos los gimnasios en ese modo.
  - `loadBootstrap()` carga los ejercicios disponibles para que el selector no dependa de un cambio manual de grupo.
  - El resumen presenta mejor marca, última marca, cambio total y cantidad de registros; los valores ausentes se muestran como "—".
  - Con gimnasios activos, grafico, tarjetas y tabla se filtran por el gimnasio seleccionado o por registros "Sin gimnasio".
  - El gráfico de mejor marca diaria ajusta la escala cuando hay progresión visible y conserva una orientación útil si Chart.js no carga.
  - Ver registros como tarjetas en móvil y tabla en pantallas amplias.
  - Editar registros con formulario embebido.
  - La edicion permite corregir el gimnasio del registro, incluido dejarlo como "Sin gimnasio".
  - Un histórico vacío invita a ir a Entrenar; borrar un registro requiere confirmación accesible y anuncia el resultado.
- Gestión:
  - Los apartados funcionan como acordeón exclusivo en móvil y subnavegación en escritorio.
  - Apartado de entrenamientos: listar, crear, editar y eliminar.
  - Apartado de gimnasios: activar/desactivar la feature, listar, crear, editar y eliminar gimnasios.
  - No se puede eliminar un gimnasio con registros asociados; si solo aparece en allow-lists de ejercicios, se elimina y se limpian esas relaciones.
  - Apartados de gimnasios, entrenamientos y ejercicios: listar, crear, editar y eliminar. En móvil los formularios permanecen embebidos; en escritorio se trasladan a un editor lateral con instrucciones contextuales cuando está vacío.
  - Las eliminaciones usan el diálogo compartido con una etiqueta de acción específica y feedback accesible.
  - Con gimnasios activos, el formulario de ejercicio permite elegir gimnasios permitidos. Sin seleccion significa disponible en todos los gimnasios del usuario.
  - Al eliminar un ejercicio se avisa de que también se eliminarán todos sus registros de marcas asociados.
  - Apartado de datos: exportar backup JSON v2, exportar CSVs e importar con previsualizacion y confirmacion.

Onboarding:

- El panel presenta tres pasos: "Crea tu primer entrenamiento", "Añade tu primer ejercicio" y "Registra tu primera marca".
- La acción principal conduce al formulario o pantalla del siguiente paso incompleto; el panel se oculta al completar los tres.
- `loadOnboardingProgress()` reutiliza `exercises` y suma `record_count`, sin cambios de backend.

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
node scripts\ui-design-requirements.test.cjs
node scripts\ui-shell-behavior.test.cjs
node scripts\training-ui-behavior.test.cjs
node scripts\history-ui-behavior.test.cjs
node scripts\management-ui-behavior.test.cjs
node scripts\auth-onboarding-ui.test.cjs
node scripts\desktop-ui-requirements.test.cjs
node scripts\desktop-ui-behavior.test.cjs
composer test
```

La suite PHPUnit contiene 13 tests y 41 aserciones. El frontend se revisó en navegador a 390 × 844, 768 × 1024 y escritorio; contraste de navegación y estados vacíos se comprobaron visualmente.

También se comprobó que el servidor PHP responde con HTTP 200 para:

- `/index.html`
- `/assets/app.css`
- `/assets/app.js`

## Puntos importantes / deuda conocida

- Las pantallas principales comparten la identidad Cuaderno de hierro. El siguiente trabajo de diseño debería responder a necesidades observadas con usuarios, no sustituir el sistema visual completo.
- Hay suite PHPUnit para helpers de seguridad e importacion, mas una prueba local ignorada en `.superpowers/implementation-tests/management-plan.test.cjs`.
- `database/schema.sql` es la fuente canonica para instalaciones nuevas.
- `database/schema.sql` ya refleja el modelo actual con gimnasios opcionales; cualquier instalación antigua debe compararse y migrarse antes de desplegar cambios de esquema.
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
