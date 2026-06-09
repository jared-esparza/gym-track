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
- Registro de marcas por ejercicio y entrenamiento activo.
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
- `.env.example`
  - Plantilla de configuración.
- `.htaccess`
  - Reglas básicas para servir `public/` desde hosting donde no se pueda apuntar el document root.
- `README.md`
  - Instrucciones de instalación, despliegue y enlaces de documentación.

## Modelo de datos

Tablas principales:

- `users`: email, hash de password, verificación de email, tokens de reset.
- `muscle_groups`: catálogo fijo del sistema.
- `workouts`: entrenamientos/rutinas del usuario.
- `workout_muscle_groups`: relación N:M entre entrenamientos y grupos musculares.
- `exercises`: ejercicios del usuario con grupo, tipo de marca y notas permanentes.
- `records`: marcas guardadas con usuario, ejercicio, entrenamiento usado, valor, tipo, fecha/hora y nota puntual.

Tipos de marca válidos:

- `kg`
- `reps`
- `min`
- `km`

La RM en V1 es simplemente la mejor marca histórica numérica de un ejercicio (`MAX(value)`), no una 1RM estimada.

Notas de integridad:

- Si se elimina un ejercicio, MySQL elimina también sus registros por `records.exercise_id ON DELETE CASCADE`.
- Si se elimina un usuario, se eliminan sus entrenamientos, ejercicios y registros.
- Si se intenta eliminar un entrenamiento con registros asociados, la API lo bloquea para no dejar histórico sin rutina.

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
- `GET/POST api.php?action=workouts`
- `GET/POST/DELETE api.php?action=workout&id=...`
- `GET api.php?action=exercises&muscle_group_id=...`
- `POST/DELETE api.php?action=exercise&id=...`
- `GET api.php?action=exercise-summary&exercise_id=...`
- `POST/DELETE api.php?action=records`
- `GET api.php?action=history&exercise_id=...`

Los endpoints protegidos usan `Auth::requireUser()` y validan ownership antes de leer/modificar workouts, exercises y records.

Detalles relevantes:

- `GET exercises` devuelve `record_count` para saber si el tipo de marca debe bloquearse en edición.
- `POST exercise` crea ejercicios si no recibe `id`.
- `POST exercise` edita nombre, grupo, tipo de marca y notas si recibe `id`.
- Si un ejercicio ya tiene registros, no se permite cambiar su `metric_type`.
- `DELETE exercise` elimina el ejercicio y sus registros asociados por cascada.

## Flujo UX

Pantallas principales:

- Auth:
  - Login, registro, forgot password y reset password.
- Entrenar:
  - Elegir entrenamiento activo.
  - Elegir grupo muscular permitido por ese entrenamiento.
  - Elegir o crear ejercicio.
  - Ver RM, último registro y notas.
  - Guardar marca y nota puntual.
  - Tras guardar una marca, se mantiene el entrenamiento activo pero se limpian grupo, ejercicio y panel para registrar otro ejercicio.
- Histórico:
  - Elegir grupo y ejercicio.
  - Ver gráfico de mejor marca diaria.
  - Ver registros como tarjetas en móvil y tabla en pantallas amplias.
  - Editar registros con formulario embebido.
  - Eliminar registros con confirmación.
- Gestión:
  - Apartado de entrenamientos: listar, crear, editar y eliminar.
  - Apartado de ejercicios: filtrar por grupo, listar, crear, editar y eliminar.
  - Al eliminar un ejercicio se avisa de que también se eliminarán todos sus registros de marcas asociados.

Onboarding actual:

- Si el usuario no tiene entrenamientos, se muestra un panel de "Primer entrenamiento" con CTA para ir a Gestión y abrir el formulario de nuevo entrenamiento.

## Cómo probar en local

Con XAMPP:

1. Arrancar MySQL desde `C:\xampp\xampp-control.exe`.
2. Crear base `gym_tracker` en phpMyAdmin.
3. Ejecutar `database/schema.sql`.
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
Get-ChildItem -Recurse -Filter *.php | Where-Object { $_.FullName -notlike '*\vendor\*' } | ForEach-Object { php -l $_.FullName }
node --check public\assets\app.js
node .superpowers\implementation-tests\management-plan.test.cjs
```

También se comprobó que el servidor PHP responde con HTTP 200 para:

- `/index.html`
- `/assets/app.css`
- `/assets/app.js`

## Puntos importantes / deuda conocida

- La app es una primera V1 funcional, pero todavía puede pulirse visualmente.
- No hay una suite formal de tests automatizados versionada. Existe una prueba local ignorada en `.superpowers/implementation-tests/management-plan.test.cjs` usada durante los últimos cambios.
- No hay migraciones incrementales; solo `database/schema.sql`.
- Chart.js se carga desde CDN, por lo que requiere conexión a internet para ver gráficos.
- El `.htaccess` raíz ayuda en hosting compartido, pero el despliegue ideal es apuntar document root a `public/`.
- La protección CSRF no está implementada. Las sesiones son HTTP-only/SameSite=Lax, pero para producción sería recomendable añadir tokens CSRF en mutaciones.
- La verificación y reset usan tokens hasheados y caducidad, pero no hay rate limiting.

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
