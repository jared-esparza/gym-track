# Estructura del proyecto

Esta guía explica qué hay en cada carpeta y archivo importante de Gym Tracker, y dónde conviene tocar cuando se quiera hacer un cambio manual.

## Vista rápida

```text
gym-tracker/
|-- app/                  Lógica PHP compartida: config, DB, auth, request/response, mail
|-- database/             SQL inicial de base de datos
|-- docs/                 Documentación humana del proyecto
|-- public/               Raíz web: HTML, API, CSS y JS
|-- storage/              Archivos generados en local, como logs de email
|-- vendor/               Dependencias Composer
|-- .env.example          Plantilla de variables de entorno
|-- .htaccess             Rewrite para hosting compartido
|-- composer.json         Dependencias PHP
|-- PROJECT_CONTEXT.md    Contexto técnico y funcional para futuras sesiones
`-- README.md             Instalación, despliegue y uso básico
```

## Carpetas principales

### `app/`

Contiene clases PHP pequeñas y reutilizables. No define pantallas ni rutas por sí misma; `public/api.php` llama a estas clases.

- `bootstrap.php`
  - Punto de arranque común para la API.
  - Carga `vendor/autoload.php` si existe.
  - Si Composer no está disponible, registra un autoload simple para clases de `app/`.
  - Inicia la sesión PHP.

- `Config.php`
  - Lee `.env` y variables de entorno.
  - Es el sitio correcto para revisar cómo se resuelven valores como `DB_HOST`, `APP_ENV` o SMTP.

- `Database.php`
  - Crea la conexión PDO a MySQL/MariaDB.
  - Si hay problemas de conexión o charset, se revisa aquí.

- `Auth.php`
  - Gestiona login/logout de sesión.
  - Expone usuario actual y `requireUser()`.
  - Cualquier endpoint protegido debe pasar por aquí.

- `Request.php`
  - Normaliza entrada JSON o `POST`.
  - Útil si se cambia cómo el frontend envía formularios.

- `Response.php`
  - Centraliza respuestas JSON y errores.
  - Si se quiere cambiar el formato de errores API, se toca aquí.

- `Mailer.php`
  - Envía emails con PHPMailer en producción.
  - En local (`APP_ENV=local`) escribe en `storage/logs/mail.log`.

### `public/`

Es la raíz web. En hosting ideal, el document root debe apuntar a esta carpeta.

- `index.html`
  - Contiene toda la estructura HTML de la SPA.
  - Aquí están las pantallas de auth, Entrenar, Histórico y Gestión.
  - Si falta un formulario, botón, selector o sección visual, normalmente empieza aquí.

- `api.php`
  - Router JSON único de toda la aplicación.
  - Selecciona acciones con `?action=...`.
  - Contiene validaciones de ownership y operaciones SQL.
  - Si se añade una operación nueva de datos, se añade aquí.

- `assets/app.js`
  - Estado de frontend, llamadas API y listeners de interacción.
  - Gestiona cambios de pestaña, carga de selects, guardado de registros, edición y borrado.
  - Si algo “no responde” al pulsar un botón o hay que cambiar un flujo de pantalla, se revisa aquí.

- `assets/app.css`
  - Estilos mobile-first.
  - Controla paneles, formularios, navegación inferior, tarjetas móviles, tablas, avisos y botones.
  - Si un cambio es visual sin alterar datos, probablemente va aquí.

- `.htaccess`
  - Cabeceras básicas si se sirve desde Apache.

### `database/`

- `schema.sql`
  - SQL inicial completo.
  - Crea tablas, relaciones y grupos musculares fijos.
  - No hay migraciones incrementales; si se cambia el esquema, hay que documentar cómo aplicar el cambio en bases existentes.

### `storage/`

Archivos generados. No debería contener código.

- `logs/mail.log`
  - En local recibe emails de verificación y reset.
  - Se usa para probar registro y recuperación de contraseña sin SMTP real.

### `vendor/`

Dependencias instaladas con Composer.

- No editar manualmente.
- Si falta, ejecutar `composer install --no-dev`.

### `docs/`

Documentación humana del proyecto.

- `PROJECT_STRUCTURE.md`
  - Esta guía.

## Archivos de raíz

- `.env.example`
  - Plantilla de configuración.
  - Copiar a `.env` en local o configurar equivalentes en hosting.

- `.env`
  - Configuración local real.
  - No debe subirse si hay repositorio Git.

- `.htaccess`
  - Rewrite básico para hostings compartidos donde no se pueda apuntar directamente a `public/`.

- `.gitignore`
  - Ignora `.env`, `storage/`, `vendor/` y `.superpowers/`.

- `composer.json`
  - Declara PHPMailer.
  - Si se añade una dependencia PHP, se modifica aquí y luego se ejecuta Composer.

- `composer.lock`
  - Versiones exactas instaladas por Composer.

- `README.md`
  - Guía corta de instalación, despliegue y uso.

- `PROJECT_CONTEXT.md`
  - Contexto más amplio para futuras sesiones de desarrollo.
  - Debe mantenerse actualizado cuando cambien endpoints, flujo UX, estructura o deuda conocida.

## Dónde tocar según el cambio

- Cambiar textos, botones o formularios:
  - `public/index.html`
  - `public/assets/app.js` si el elemento necesita interacción.

- Cambiar comportamiento de una pantalla:
  - `public/assets/app.js`
  - `public/api.php` si necesita leer/escribir datos.

- Añadir o cambiar endpoints:
  - `public/api.php`
  - Revisar `Auth::requireUser()` y ownership.

- Cambiar tablas o relaciones:
  - `database/schema.sql`
  - Documentar cómo migrar bases ya existentes.

- Cambiar estilos:
  - `public/assets/app.css`

- Cambiar emails:
  - `app/Mailer.php`
  - Textos concretos en `public/api.php`, donde se llama a `Mailer::send()`.

- Cambiar login, sesión o permisos:
  - `app/Auth.php`
  - `public/api.php` para endpoints concretos.

- Cambiar configuración:
  - `.env.example`
  - `app/Config.php` si cambia cómo se leen valores.

## Endpoints y pantallas relacionadas

- Auth:
  - HTML: formularios en `public/index.html`.
  - JS: `bindAuth()` en `public/assets/app.js`.
  - API: `register`, `login`, `logout`, `verify-email`, `forgot-password`, `reset-password`.

- Entrenar:
  - HTML: `trainTab` en `public/index.html`.
  - JS: `loadActiveWorkout()`, `loadExercises()`, `loadExerciseSummary()`, `saveRecord()`.
  - API: `bootstrap`, `workout`, `exercises`, `exercise-summary`, `records`.
  - Nota: `loadExercises()` puede cargar por grupo o, si no hay grupo seleccionado, por `workout_id` para mostrar todos los ejercicios permitidos por el entrenamiento activo.

- Histórico:
  - HTML: `historyTab`.
  - JS: `loadHistory()`, `renderHistory()`, `renderHistoryCards()`, `saveRecordEdit()`, `deleteRecord()`.
  - API: `exercises`, `history`, `records`.
  - Nota: si no hay grupo seleccionado, `exercises` devuelve todos los ejercicios del usuario.

- Gestión:
  - HTML: `manageTab`.
  - JS: `renderWorkouts()`, `saveWorkout()`, `renderManageExercises()`, `saveManagedExercise()`, `deleteExercise()`, `previewImport()`, `confirmImport()`.
  - API: `workouts`, `workout`, `exercises`, `exercise`, `export`, `import-preview`, `import-confirm`, `import-cancel`.
  - Nota: el filtro de ejercicios arranca en "Todos los grupos"; solo se envia `muscle_group_id` cuando el usuario elige un grupo concreto.
  - Nota: el apartado Datos exporta backup JSON restaurable, CSVs de consulta e importa JSON o CSV de ejercicios con previsualizacion.

## Validaciones recomendadas tras cambios

```powershell
composer validate --strict
Get-ChildItem -Recurse -Filter *.php | Where-Object { $_.FullName -notlike '*\vendor\*' } | ForEach-Object { php -l $_.FullName }
node --check public\assets\app.js
```

Si se mantienen las pruebas locales ignoradas en `.superpowers/`, también se puede ejecutar:

```powershell
node .superpowers\implementation-tests\management-plan.test.cjs
```

Para comprobar que la app sirve archivos básicos:

```powershell
php -S 127.0.0.1:4177 -t public
```

Y abrir `http://127.0.0.1:4177/`.
