# Demo y entrenamiento flexible

## Uso

Entra mediante «Probar demo sin registrarme» o comparte `/?demo=1` (relativo a la raíz pública de la instalación). La demo contiene seis ejercicios, dos rutinas y seis semanas de marcas. Cada visitante tiene su propia sesión; puede modificar ejemplos, importar/exportar y reiniciar. Recargar conserva los datos mientras la sesión siga vigente. Salir o registrarse descarta la prueba; la cuenta real empieza vacía.

Entrenar abre todo el catálogo. Las rutinas y los grupos son filtros opcionales. Crear un ejercicio necesita nombre y unidad; se puede añadir grupo o asociar a rutinas después. Las rutinas son listas explícitas editables desde Gestión, y un ejercicio puede aparecer en varias. Al seleccionar una rutina se muestran sus ejercicios; «Todos los ejercicios» permite registrar otro conservando el contexto. Si no pertenece a esa rutina, su marca queda sin entrenamiento y el ejercicio no se añade a la lista.

Quitar un ejercicio de una lista conserva las marcas históricas de esa rutina. Eliminar una rutina conserva sus ejercicios y marcas, dejando estas últimas sin entrenamiento. Eliminar un ejercicio sigue eliminando sus marcas tras la confirmación existente.

## Instalación y migración

1. Requiere PHP 8.1+ (por los tipos `never` ya utilizados), PDO MySQL y `pdo_sqlite` para la demo. Comprobar con `php -m`.
2. En instalaciones existentes, hacer backup y ejecutar **una sola vez** `database/migrations/2026-10-19-flexible-training.sql` antes de subir la API nueva. El DDL MySQL hace commits implícitos: restaurar el backup es la estrategia de recuperación.
3. En una instalación nueva, ejecutar únicamente `database/schema.sql`.
4. La migración conserva la selección actual por grupos como asociaciones explícitas del mismo propietario. La tabla anterior `workout_muscle_groups` queda como dato legado, sin uso por la API.
5. Verificar acceso real, creación sin grupo, marca sin rutina y `/?demo=1` tras desplegar. Habilitar la limpieza de sesiones caducadas del hosting.

La demo reconstruye SQLite en memoria desde un snapshot de la sesión y guarda el snapshot al terminar la petición. No crea cuentas ni tablas de demo en MySQL ni archivos SQLite persistentes. Reutiliza las consultas, validaciones, CSRF y límites de importación de la API real. Las operaciones de autenticación no se ejecutan contra la demo. La sesión PHP serializa peticiones concurrentes del mismo visitante y las transacciones fallidas se revierten antes de guardar el snapshot.

## Contratos

- Ejercicio: `muscle_group_id` ausente, vacío o `null` significa sin grupo.
- Marca: `workout_id` ausente, vacío o `null` significa sin entrenamiento. Un ID explícito exige propietario válido y pertenencia actual del ejercicio a la lista.
- Rutinas: crear/editar envía `exercise_ids`; consultar devuelve `exercise_ids`. La lista puede estar vacía.
- Ejercicios: `muscle_group_id=none` consulta sin grupo; los filtros por grupo y `workout_id` se combinan.
- Demo: `POST demo-start` y `POST demo-reset`, protegidos por CSRF. `me` identifica al visitante con `user.is_demo`; `logout` descarta su sesión.
- JSON v3: las rutinas contienen `exercises: [{name, muscle_group}]`. Referencias sin grupo y marcas libres usan `null`. Versiones 1/2 siguen aceptándose convirtiendo los grupos tras importar ejercicios. Las importaciones fusionan listas; no eliminan asociaciones existentes.
- CSV: se mantiene la cabecera de ejercicios y se acepta grupo vacío. El CSV de rutinas exporta nombres de ejercicios; el JSON es el formato de backup completo.

## Verificación reproducible

```powershell
php vendor/phpunit/phpunit/phpunit
php scripts/lint.php
node --check public/assets/app.js
Get-ChildItem scripts/*test.cjs | Where-Object Name -ne 'demo-api.test.cjs' | ForEach-Object { node $_.FullName; if ($LASTEXITCODE -ne 0) { throw $_.Name } }
```

En otra terminal, `php -S 127.0.0.1:4178 -t public`, y después `node scripts/demo-api.test.cjs`. La prueba usa dos sesiones ficticias y no necesita MySQL.

Para la migración, usar **un servidor aislado de pruebas**, configurar `MIGRATION_TEST_DSN`, `MIGRATION_TEST_USER` y `MIGRATION_TEST_PASSWORD`, y ejecutar `php scripts/migration.test.php`. Crea bases con nombres aleatorios, comprueba el esquema nuevo y la conversión de datos de dos usuarios, y elimina solo las bases que ha creado.

Comprobar en navegador a 390 × 844 y en escritorio: demo directa y por botón; crear ejercicio externo a «Piernas» y marca libre; historial «Sin grupo»/«Sin entrenamiento»; edición de listas y búsqueda; recarga; reinicio; salida y registro.
