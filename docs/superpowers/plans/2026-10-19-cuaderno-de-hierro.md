# Cuaderno de hierro Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [x]`) syntax for tracking.

**Goal:** Rediseñar Gym Tracker como una herramienta de entrenamiento reconocible, rápida y accesible, conservando todas las funciones y el despliegue sin bundler actuales.

**Architecture:** Mantener la SPA de `index.html`, `app.css` y `app.js`, mejorando progresivamente la estructura existente en vez de incorporar un framework. La lógica nueva que transforma datos se expresará en funciones pequeñas y testeables; los flujos seguirán consumiendo los endpoints actuales. Los cambios se entregarán por pantalla sobre una base compartida de tokens, componentes y feedback.

**Tech Stack:** PHP 8+, MySQL/MariaDB, HTML5, CSS, JavaScript sin bundler, Chart.js 4.4.3, Node.js `assert`/`vm` para contratos frontend y PHPUnit 10.5.

**Spec:** `docs/superpowers/specs/2026-10-19-cuaderno-de-hierro-design.md`

## Global Constraints

- Mantener PHP 8+, MySQL/MariaDB, HTML, CSS y JavaScript sin bundler.
- No introducir React, Vue, Tailwind ni una biblioteca de componentes.
- Mantener Chart.js como dependencia CDN existente y ofrecer fallback sin conexión.
- Alojar fuentes e iconos necesarios dentro de `public/assets/`.
- Mantener los endpoints y el modelo de datos actuales salvo bloqueo demostrado.
- Conservar protección CSRF, ownership, sesiones y reglas de gimnasios.
- Mantener compatibilidad con hosting compartido IONOS.
- Entrenar debe ser el destino inicial en móvil y escritorio.
- Conservar el comportamiento responsive en caliente probado en `scripts/desktop-ui-behavior.test.cjs`.
- Todo texto nuevo se escribirá en español, en sentence case y con vocabulario de acción consistente.

## Review Focus

- Nombres de ejercicio, gimnasio, entrenamiento o notas muy largos: deben envolver sin provocar scroll horizontal ni tapar acciones; Task 3 y Task 5 lo comprobarán.
- Gimnasios activados sin gimnasio seleccionado: Entrenar e Histórico deben bloquear el siguiente paso con una instrucción específica; Task 3 y Task 4 lo comprobarán.
- Chart.js ausente o historial vacío: debe mostrarse orientación útil sin dejar un canvas roto; Task 4 lo comprobará.
- Cruce del breakpoint de 1024 px con formulario de gestión o diálogo abierto: debe conservar contexto y foco; Task 2 y Task 5 lo comprobarán.
- Movimiento reducido y navegación por teclado: no deben perder feedback, foco visible ni acceso a acciones; Task 2 y Task 7 lo comprobarán.

---

### Task 1: Fundamentos visuales y activos locales

**Files:**
- Create: `public/assets/fonts/IBMPlexSans-Regular.woff2`
- Create: `public/assets/fonts/IBMPlexSans-SemiBold.woff2`
- Create: `public/assets/fonts/BarlowCondensed-SemiBold.woff2`
- Create: `public/assets/fonts/OFL.txt`
- Create: `public/assets/icons/sprite.svg`
- Create: `scripts/ui-design-requirements.test.cjs`
- Modify: `public/index.html:3-10`
- Modify: `public/assets/app.css:1-185`

**Interfaces:**
- Consumes: la estructura HTML actual y las variables CSS existentes.
- Produces: tokens `--color-*`, `--font-ui`, `--font-display`, escalas de espacio/radio, clases de botón y foco compartidas, y símbolos SVG `train`, `history`, `manage`, `logout`, `search`, `edit`, `delete`.

- [x] **Step 1: Escribir el contrato visual que debe fallar**

En `scripts/ui-design-requirements.test.cjs`, cargar HTML/CSS/sprite y afirmar literalmente:

```js
assert.match(css, /--color-graphite:\s*#111827/);
assert.match(css, /--color-plate-blue:\s*#2453E6/);
assert.match(css, /--color-tape-orange:\s*#F26A2E/);
assert.match(css, /font-family:\s*"IBM Plex Sans"/);
assert.match(css, /font-family:\s*"Barlow Condensed"/);
assert.match(html, /assets\/fonts\/IBMPlexSans-Regular\.woff2/);
assert.match(sprite, /id="icon-train"/);
```

- [x] **Step 2: Ejecutar el contrato y confirmar el fallo**

Run: `node scripts/ui-design-requirements.test.cjs`  
Expected: FAIL porque los tokens, fuentes y sprite aún no existen.

- [x] **Step 3: Incorporar activos y sustituir el sistema base de CSS**

Añadir fuentes WOFF2 renombradas de sus distribuciones OFL, conservar `OFL.txt`, crear el sprite local y definir `@font-face`. Sustituir la paleta verde, la tipografía de sistema y el radio universal por los valores exactos del spec. Mantener 44 px como mínimo táctil y el foco de 3 px con contraste AA.

- [x] **Step 4: Verificar fundamentos**

Run: `node scripts/ui-design-requirements.test.cjs && node scripts/desktop-ui-requirements.test.cjs`  
Expected: PASS sin salida de error.

- [x] **Step 5: Commit**

```bash
git add public/index.html public/assets/app.css public/assets/fonts public/assets/icons scripts/ui-design-requirements.test.cjs
git commit -m "feat: establish Cuaderno de hierro design system"
```

### Task 2: Shell, navegación, feedback y diálogo accesible

**Files:**
- Create: `scripts/ui-shell-behavior.test.cjs`
- Modify: `public/index.html:61-87,348-352`
- Modify: `public/assets/app.css:185-345,591-925`
- Modify: `public/assets/app.js:77-85,258-388`
- Modify: `scripts/desktop-ui-behavior.test.cjs`

**Interfaces:**
- Consumes: tokens e iconos de Task 1; `switchTab(tabId)` y el listener responsive existentes.
- Produces: `setActiveNavigation(tabId: string): void`, `announce(message: string, tone?: 'success'|'error'|'info'): void`, `openConfirmDialog(options: { title: string, message: string, confirmLabel: string }): Promise<boolean>` y `closeConfirmDialog(result: boolean): void`.

- [x] **Step 1: Escribir pruebas de comportamiento del shell**

Usar el harness `vm` existente para afirmar que `setActiveNavigation('historyTab')` sincroniza ambas navegaciones y establece `aria-current="page"` solo en Histórico. Afirmar que `announce('Marca guardada', 'success')` actualiza una región `aria-live="polite"`. Afirmar que Escape resuelve el diálogo como `false` y devuelve el foco al control que lo abrió.

- [x] **Step 2: Ejecutar y confirmar los fallos**

Run: `node scripts/ui-shell-behavior.test.cjs && node scripts/desktop-ui-behavior.test.cjs`  
Expected: FAIL porque las tres interfaces nuevas no existen.

- [x] **Step 3: Implementar shell y navegación**

Actualizar sidebar, topbar y bottom nav con icono más texto, área de cuenta secundaria y `aria-current`. Mantener el orden Entrenar, Histórico, Gestión. `switchTab()` delegará la sincronización visual en `setActiveNavigation()`.

- [x] **Step 4: Implementar feedback y confirmación**

Convertir `globalMessage` en región viva persistente y añadir un único `<dialog id="confirmDialog">` reutilizable. `openConfirmDialog()` gestionará título, mensaje, etiqueta, foco inicial, Escape y restauración de foco. No sustituir todavía los `confirm()` de dominio; se hará en sus tareas propietarias.

- [x] **Step 5: Verificar shell y regresión responsive**

Run: `node scripts/ui-shell-behavior.test.cjs && node scripts/desktop-ui-behavior.test.cjs && node --check public/assets/app.js`  
Expected: PASS.

- [x] **Step 6: Commit**

```bash
git add public/index.html public/assets/app.css public/assets/app.js scripts/ui-shell-behavior.test.cjs scripts/desktop-ui-behavior.test.cjs
git commit -m "feat: redesign navigation and shared feedback"
```

### Task 3: Cockpit de Entrenar

**Files:**
- Create: `scripts/training-ui-behavior.test.cjs`
- Modify: `public/index.html:88-155`
- Modify: `public/assets/app.css:345-450,696-925`
- Modify: `public/assets/app.js:1-16,87-137,389-633`

**Interfaces:**
- Consumes: `announce()` de Task 2 y los endpoints `workout`, `exercises`, `exercise-summary`, `exercise`, `records` existentes.
- Produces: `renderTrainingContext(): void`, `renderTrainingGroups(groups: MuscleGroup[]): void`, `filterTrainingExercises(query: string): Exercise[]`, `renderTrainingExerciseList(exercises: Exercise[]): void`, `selectTrainingExercise(id: number): Promise<void>` y `renderRecordConfirmation(result: { value: string, metricType: string, previousRm: number|null }): void`.

- [x] **Step 1: Escribir pruebas del flujo principal**

Probar con fixtures literales que:

```js
assert.deepEqual(filterTrainingExercises('press'), [pressBanca]);
assert.deepEqual(filterTrainingExercises('PECTORAL'), [pressBanca]);
assert.match(css, /\.exercise-option__name[\s\S]*overflow-wrap:\s*anywhere/);
```

Afirmar además que, con gimnasios activados y selección vacía, la lista muestra “Elige gimnasio primero”; que seleccionar ejercicio actualiza nombre, unidad, RM y último registro; y que una marca `101` sobre RM `100` anuncia “Nueva RM: 101 kg”.

- [x] **Step 2: Ejecutar y confirmar los fallos**

Run: `node scripts/training-ui-behavior.test.cjs`  
Expected: FAIL por ausencia de búsqueda, lista y confirmación de RM.

- [x] **Step 3: Reestructurar Entrenar**

Crear `session-context`, filtros musculares, campo de búsqueda, lista de ejercicios y `record-cockpit`. Mantener los selects existentes como controles accesibles sincronizados mientras la lista enriquecida ofrece el recorrido principal. La nota puntual se mostrará bajo un control “Añadir nota”.

- [x] **Step 4: Implementar estado y renderizado**

Añadir `state.trainExercises`, `state.trainingQuery` y `state.previousRm`. `loadExercises('train')` guardará el resultado antes de renderizar; `selectTrainingExercise()` mantendrá el select sincronizado y reutilizará `loadExerciseSummary()`. La búsqueda normalizará texto con `toLocaleLowerCase('es')` y eliminará diacríticos.

- [x] **Step 5: Implementar guardado con contexto y nueva RM**

Antes de guardar, capturar la RM visible. Tras éxito, llamar `renderRecordConfirmation()` y mantener entrenamiento/gimnasio. El feedback será comprensible con movimiento reducido y no dependerá solo del naranja.

- [x] **Step 6: Verificar y realizar checkpoint visual**

Run: `node scripts/training-ui-behavior.test.cjs && node scripts/ui-design-requirements.test.cjs && node --check public/assets/app.js`  
Expected: PASS.

Revisar en navegador a 390 × 844 y 1440 × 900 con: cuenta vacía, un entrenamiento, gimnasios desactivados y gimnasios activados sin selección. Mostrar el resultado al usuario y continuar tras su visto bueno, según el método acordado.

- [x] **Step 7: Commit**

```bash
git add public/index.html public/assets/app.css public/assets/app.js scripts/training-ui-behavior.test.cjs
git commit -m "feat: build focused training cockpit"
```

### Task 4: Histórico orientado a progreso

**Files:**
- Create: `scripts/history-ui-behavior.test.cjs`
- Modify: `public/index.html:157-195`
- Modify: `public/assets/app.css:450-590,696-925`
- Modify: `public/assets/app.js:1053-1182`

**Interfaces:**
- Consumes: `openConfirmDialog()` y `announce()` de Task 2; respuesta actual de `history` con `records` y `chart`.
- Produces: `summarizeHistory(records: Record[]): { best: number|null, latest: number|null, delta: number|null, count: number, metricType: string|null }`, `renderHistorySummary(summary): void` y `historyChartOptions(values: number[]): object`.

- [x] **Step 1: Escribir pruebas de resumen y estados límite**

Con fixtures literales, afirmar resumen vacío, un solo registro, progresión positiva y registros no ordenados. Afirmar que Chart.js ausente muestra “El gráfico no está disponible”, que cero registros muestra una acción para volver a Entrenar y que gimnasios activados sin selección mantiene ocultos resumen y gráfico con “Elige gimnasio primero”.

- [x] **Step 2: Ejecutar y confirmar el fallo**

Run: `node scripts/history-ui-behavior.test.cjs`  
Expected: FAIL porque no existen resumen ni opciones de escala aisladas.

- [x] **Step 3: Implementar encabezado de progreso y layout**

Añadir nombre del ejercicio, contexto de gimnasio, mejor, última, variación y cantidad. Móvil utilizará línea temporal compacta; escritorio conservará tabla. Valores nulos mostrarán “—”, nunca `NaN`.

- [x] **Step 4: Implementar gráfico y confirmación de borrado**

Usar `historyChartOptions()` con escala ajustada cuando existan al menos dos valores y diferencia visible; conservar cero cuando sea necesario para interpretación. Sustituir `window.confirm()` de registros por `openConfirmDialog()` y anunciar éxito tras borrar.

- [x] **Step 5: Verificar Histórico**

Run: `node scripts/history-ui-behavior.test.cjs && node --check public/assets/app.js`  
Expected: PASS.

- [x] **Step 6: Commit**

```bash
git add public/index.html public/assets/app.css public/assets/app.js scripts/history-ui-behavior.test.cjs
git commit -m "feat: make history communicate progress"
```

### Task 5: Gestión densa y segura

**Files:**
- Create: `scripts/management-ui-behavior.test.cjs`
- Modify: `public/index.html:197-345`
- Modify: `public/assets/app.css:250-345,450-590,696-925`
- Modify: `public/assets/app.js:428-490,634-978`
- Modify: `scripts/desktop-ui-behavior.test.cjs`

**Interfaces:**
- Consumes: diálogo, feedback y navegación de Task 2; movimiento responsive existente.
- Produces: `setManagementEditorState(sectionId: string, mode: 'empty'|'create'|'edit'): void`, `entityMeta(parts: Array<string|number|null>): string` y uso uniforme de `openConfirmDialog()` en ejercicios, entrenamientos y gimnasios.

- [x] **Step 1: Escribir pruebas de Gestión**

Afirmar que metadatos largos se renderizan como partes semánticas sin separadores sobrantes y con `overflow-wrap: anywhere`, que el editor vacío muestra instrucción contextual sin tarjeta blanca vacía, y que cancelar/guardar devuelve el editor a `empty`. Probar que cada eliminación usa la etiqueta concreta: “Eliminar ejercicio”, “Eliminar entrenamiento” o “Eliminar gimnasio”. Extender la prueba responsive para confirmar que cruzar 1024 px mantiene abierto el diálogo y conserva el formulario en el contenedor correcto.

- [x] **Step 2: Ejecutar y confirmar los fallos**

Run: `node scripts/management-ui-behavior.test.cjs && node scripts/desktop-ui-behavior.test.cjs`  
Expected: FAIL por ausencia del estado de editor y del diálogo de dominio.

- [x] **Step 3: Rediseñar listados y editores**

Compactar filas, separar metadatos de acciones, mantener edición embebida móvil y editor lateral desktop. Ocultar el contenedor derecho decorativo cuando no aporte contexto; mostrar una instrucción breve por sección. Preservar todos los campos y reglas de gimnasios.

- [x] **Step 4: Sustituir confirmaciones y normalizar feedback**

Migrar `deleteExercise`, `deleteWorkout` y `deleteGym` al diálogo compartido. Mantener los avisos actuales sobre registros asociados y cascadas. Tras éxito, anunciar la entidad eliminada y devolver foco a un destino estable.

- [x] **Step 5: Verificar Gestión y responsive**

Run: `node scripts/management-ui-behavior.test.cjs && node scripts/desktop-ui-behavior.test.cjs && node --check public/assets/app.js`  
Expected: PASS.

- [x] **Step 6: Commit**

```bash
git add public/index.html public/assets/app.css public/assets/app.js scripts/management-ui-behavior.test.cjs scripts/desktop-ui-behavior.test.cjs
git commit -m "feat: refine management workflows"
```

### Task 6: Acceso y onboarding contextual

**Files:**
- Create: `scripts/auth-onboarding-ui.test.cjs`
- Modify: `public/index.html:11-59,88-99`
- Modify: `public/assets/app.css:145-235`
- Modify: `public/assets/app.js:138-257,389-427`

**Interfaces:**
- Consumes: fundamentos visuales y `announce()`.
- Produces: `loadOnboardingProgress(): Promise<{ workouts: number, exercises: number, records: number }>`, `renderOnboardingState({ workouts: number, exercises: number, records: number }): void` y estados de auth con un único formulario visible.

- [x] **Step 1: Escribir pruebas de acceso y onboarding**

Afirmar que `showAuthMode()` deja un único flujo visible y que `renderOnboardingState()` produce literalmente los siguientes pasos: “Crea tu primer entrenamiento”, “Añade tu primer ejercicio” y “Registra tu primera marca”. Probar nombres largos y navegación mediante teclado.

- [x] **Step 2: Ejecutar y confirmar el fallo**

Run: `node scripts/auth-onboarding-ui.test.cjs`  
Expected: FAIL porque el onboarding actual solo distingue si existen entrenamientos.

- [x] **Step 3: Implementar composición de acceso**

Reorganizar el panel para mostrar la identidad mediante una muestra de marca y tipografía, manteniendo login, registro, recuperación y reset mutuamente excluyentes. No añadir claims comerciales ni ilustración remota.

- [x] **Step 4: Implementar onboarding progresivo**

Implementar `loadOnboardingProgress()` con el endpoint `exercises` existente: `workouts` sale de `state.workouts`, `exercises` del total devuelto y `records` de la suma literal de `record_count`. Cada estado tendrá una única acción que navega a la sección o control correcto; no se añadirá ningún endpoint.

- [x] **Step 5: Verificar y commit**

Run: `node scripts/auth-onboarding-ui.test.cjs && node --check public/assets/app.js`  
Expected: PASS.

```bash
git add public/index.html public/assets/app.css public/assets/app.js scripts/auth-onboarding-ui.test.cjs
git commit -m "feat: redesign access and onboarding"
```

### Task 7: Integración, accesibilidad, documentación y validación visual

**Files:**
- Modify: `scripts/ui-design-requirements.test.cjs`
- Modify: `scripts/desktop-ui-requirements.test.cjs`
- Modify: `PROJECT_CONTEXT.md`
- Modify: `docs/PROJECT_STRUCTURE.md`
- Modify: `README.md`

**Interfaces:**
- Consumes: todas las pantallas y componentes de Tasks 1–6.
- Produces: contrato final de accesibilidad/estructura, documentación actualizada y evidencia visual en móvil, tablet y escritorio.

- [x] **Step 1: Completar el contrato final antes del pulido**

Añadir assertions para `aria-current`, región `aria-live`, diálogo etiquetado, `prefers-reduced-motion`, controles de 44 px, fuentes locales y ausencia de `window.confirm`. Ejecutarlo y confirmar que cualquier requisito aún pendiente falla de forma específica.

- [x] **Step 2: Corregir únicamente los gaps detectados**

Aplicar los ajustes mínimos de semántica, foco, wrapping, scroll horizontal y movimiento necesarios para hacer pasar el contrato. No añadir componentes nuevos en esta fase.

- [x] **Step 3: Ejecutar la suite completa**

Run:

```powershell
composer validate --strict
composer lint
composer test
node --check public\assets\app.js
node scripts\desktop-ui-requirements.test.cjs
node scripts\desktop-ui-behavior.test.cjs
node scripts\ui-design-requirements.test.cjs
node scripts\ui-shell-behavior.test.cjs
node scripts\training-ui-behavior.test.cjs
node scripts\history-ui-behavior.test.cjs
node scripts\management-ui-behavior.test.cjs
node scripts\auth-onboarding-ui.test.cjs
node scripts\ui-workflow-regressions.test.cjs
git diff --check
```

Expected: todos los comandos terminan con código 0; PHPUnit informa 13 tests y 41 assertions o más si se amplía la suite PHP.

- [x] **Step 4: Validar visualmente los estados del spec**

Inspeccionar a 390 × 844, 768 × 1024 y 1440 × 900: auth, cuenta vacía, Entrenar listo, gimnasios sin seleccionar, Histórico con/sin datos, Gestión crear/editar y diálogo destructivo. Repetir con texto al 200 % y movimiento reducido. Corregir defectos observables y volver a ejecutar Step 3.

- [x] **Step 5: Actualizar documentación**

Documentar identidad, nuevos componentes, activos locales, pruebas frontend y flujo responsive en `PROJECT_CONTEXT.md`, `docs/PROJECT_STRUCTURE.md` y `README.md`.

- [x] **Step 6: Commit final**

```bash
git add public scripts PROJECT_CONTEXT.md docs/PROJECT_STRUCTURE.md README.md
git commit -m "docs: document Cuaderno de hierro interface"
```

- [x] **Step 7: Revisión final de rama**

Comparar `main...HEAD`, comprobar que no hay cambios de API/esquema no documentados y revisar el conjunto completo contra el spec antes de solicitar integración.

