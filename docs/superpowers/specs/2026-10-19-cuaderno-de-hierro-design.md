# Cuaderno de hierro — Diseño UI/UX

**Fecha:** 2026-10-19  
**Estado:** Pendiente de aprobación  
**Producto:** Gym Tracker

## Objetivo

Transformar Gym Tracker de una interfaz administrativa genérica en una herramienta de entrenamiento reconocible, rápida y centrada en registrar marcas entre series. La nueva experiencia debe transmitir precisión, progreso y esfuerzo sin recurrir al cliché visual de gimnasio negro con neón.

El rediseño conservará la funcionalidad, el modelo de datos y la arquitectura sin bundler actuales. La prioridad es mejorar jerarquía, navegación, velocidad percibida y claridad en móvil, con una adaptación específica para escritorio.

## Usuario y contexto de uso

El usuario principal consulta la aplicación durante una sesión de gimnasio. Puede tener una sola mano disponible, poco tiempo entre series y atención limitada. Las acciones frecuentes deben requerir pocas decisiones visibles y tener objetivos táctiles amplios.

La tarea principal es:

1. Confirmar entrenamiento y gimnasio.
2. Encontrar un ejercicio.
3. Consultar la referencia anterior.
4. Introducir una marca.
5. Guardarla con confianza.

Histórico y Gestión son tareas importantes, pero secundarias respecto a registrar una marca.

## Dirección visual

La identidad se denomina **Cuaderno de hierro**. Parte de tres referencias del entrenamiento real:

- Discos calibrados y números grandes.
- Tiza, cinta y superficies técnicas.
- La libreta donde se anotan series y progresos.

La interfaz será clara, fría y precisa. La personalidad vendrá de la tipografía numérica, la composición y el módulo de registro, no de llenar la pantalla de adornos deportivos.

### Paleta

- `--color-graphite: #111827` — texto principal y navegación.
- `--color-slate: #4B5563` — texto secundario.
- `--color-chalk: #F4F6F8` — fondo de aplicación.
- `--color-paper: #FFFFFF` — superficies de trabajo.
- `--color-plate-blue: #2453E6` — interacción, selección y marca.
- `--color-plate-blue-dark: #183AA3` — estados activos con mayor contraste.
- `--color-tape-orange: #F26A2E` — nueva RM y atención deportiva puntual.
- `--color-success: #187553` — confirmaciones.
- `--color-danger: #B42318` — acciones destructivas y errores.
- `--color-line: #D9DEE7` — divisores y límites funcionales.

El azul será el color principal. El naranja no se usará como decoración general: queda reservado para hitos, especialmente una nueva mejor marca.

### Tipografía

- **IBM Plex Sans**, alojada localmente, para navegación, formularios y texto.
- **Barlow Condensed**, alojada localmente, para marcas, RM, unidades y titulares deportivos.
- Fallbacks: `Arial`, `sans-serif` y `Arial Narrow`, `sans-serif`.

Los números deberán usar cifras tabulares cuando la fuente lo permita. No se utilizarán etiquetas decorativas en mayúsculas sobre cada título. Las mayúsculas quedan limitadas a unidades breves o indicadores deportivos donde aporten lectura.

### Forma y profundidad

- Radios pequeños y diferenciados: 4 px en controles técnicos, 8 px en superficies y 14 px solo en el módulo protagonista de registro.
- Las secciones se distinguirán principalmente mediante espacio, alineación y divisores.
- Las sombras se reservan para elementos flotantes o fijados; una tarjeta normal no necesita sombra.
- No se encerrará cada fragmento de información en una tarjeta.

### Elemento memorable

El módulo de registro de marca será la única pieza visualmente dominante: número grande, unidad clara, referencia inmediata y botón de guardado inequívoco. El resto de la interfaz será disciplinado y silencioso.

## Arquitectura de información

La navegación principal mantiene tres destinos:

- **Entrenar** — destino inicial en cualquier tamaño.
- **Histórico** — análisis de progreso y corrección de registros.
- **Gestión** — ejercicios, entrenamientos, gimnasios y datos.

En móvil se mantiene navegación inferior. En escritorio se mantiene la barra lateral ya integrada. Ambos navegadores compartirán vocabulario, orden y estado activo.

El email y la salida no competirán con la tarea principal. En móvil pasarán a un área secundaria de cuenta; en escritorio permanecerán al pie de la barra lateral.

## Pantalla Entrenar

### Contexto de sesión

Entrenamiento y gimnasio se presentarán como contexto persistente, no como dos campos indistinguibles del resto. Cuando falte alguno, la interfaz explicará qué debe seleccionarse antes de continuar.

En móvil:

```text
Push · Gimnasio Centro                 Cambiar

Pecho   Hombro   Tríceps
Buscar o elegir ejercicio…

PRESS BANCA
RM 100 kg                       Último 95 kg

┌──────────────────────────────────────┐
│               97,5            kg     │
│ Nota opcional                        │
│          Guardar marca               │
└──────────────────────────────────────┘
```

En escritorio:

```text
┌──────────────┬────────────────────────────┬───────────────────────┐
│ Navegación   │ Contexto y ejercicios      │ Registro fijo         │
│              │                            │ Marca / nota / guardar │
└──────────────┴────────────────────────────┴───────────────────────┘
```

### Selección de ejercicio

- Los grupos musculares actuarán como filtro compacto.
- El selector mostrará grupo y nombre cuando se visualicen ejercicios de varios grupos.
- Se habilitará búsqueda local por nombre sobre los ejercicios ya cargados.
- La creación rápida seguirá disponible sin abandonar la sesión.
- Con gimnasios activados, se conservarán todas las reglas actuales de disponibilidad.

### Referencias y registro

- El nombre del ejercicio será el encabezado principal.
- RM y último registro estarán visibles antes de introducir el nuevo valor.
- La entrada numérica tendrá teclado decimal en móvil, unidad fija y área táctil amplia.
- La nota puntual será secundaria y plegable cuando esté vacía.
- Guardar mantendrá entrenamiento y gimnasio, limpiará el ejercicio según el comportamiento actual y devolverá foco a la selección.

### Confirmación

Tras guardar:

- Mostrar el valor guardado con texto explícito.
- Si supera la RM anterior, mostrar un tratamiento naranja breve de “Nueva RM”.
- No usar confeti ni animación continua.
- Respetar `prefers-reduced-motion`.

## Pantalla Histórico

### Resumen

Antes del gráfico se mostrará:

- Mejor marca.
- Última marca.
- Cambio entre ambas cuando haya datos suficientes.
- Número de registros del filtro actual.

Los valores se derivarán de los datos que ya devuelve la API. No se añadirá una métrica si no puede explicarse con claridad.

### Filtros y gráfico

- Gimnasio, grupo y ejercicio formarán un bloque de filtros compacto.
- El filtro activo será visible junto al título del resultado.
- El gráfico mostrará unidad, puntos identificables y estados de carga, vacío y sin Chart.js.
- El eje Y no empezará obligatoriamente en cero cuando hacerlo oculte la progresión; deberá mantener una escala comprensible.

### Registros

- En móvil se agruparán como una línea temporal compacta.
- En escritorio se conservará tabla, con jerarquía más clara para fecha, marca y contexto.
- Editar será una acción secundaria.
- Eliminar estará separado y requerirá confirmación propia de la aplicación, no `window.confirm()`.

## Pantalla Gestión

- Se conserva la navegación secundaria de escritorio y el acordeón exclusivo en móvil.
- Listado y editor deben leerse como zonas diferentes.
- Las filas serán densas y escaneables; Editar y Eliminar no dominarán cada elemento.
- Guardar y Cancelar tendrán posiciones consistentes en todos los formularios.
- Las acciones destructivas se separarán físicamente de la acción principal.
- El panel editor derecho no mostrará una tarjeta vacía sin función; deberá ofrecer instrucción contextual o desaparecer hasta que haya edición.
- Importación y exportación se presentarán como herramientas avanzadas dentro de Datos.

## Acceso y onboarding

### Acceso

- Mantener login, registro, recuperación y reset como estados mutuamente excluyentes.
- Presentar la identidad mediante tipografía, composición y un ejemplo de registro; no mediante una ilustración decorativa genérica.
- Mantener formularios cortos y vocabulario directo.

### Primera configuración

El onboarding guiará tres resultados:

1. Crear un entrenamiento.
2. Crear o elegir ejercicios.
3. Registrar la primera marca.

Cada estado vacío indicará la siguiente acción disponible. No se añadirá un asistente modal multipaso.

## Componentes y estados compartidos

Se definirán tratamientos consistentes para:

- Botón principal, secundario, textual y peligroso.
- Campos, selectores, búsqueda y entrada numérica.
- Filtros segmentados.
- Filas de entidad y filas de registro.
- Avisos de éxito, información y error.
- Estado vacío con acción.
- Estado cargando sin bloqueo total de pantalla.
- Diálogo de confirmación destructiva.
- Navegación principal y secundaria.

El texto de una acción se mantendrá durante todo el flujo: “Guardar marca” producirá “Marca guardada”; “Eliminar registro” producirá “Registro eliminado”.

## Responsive

- Base móvil desde 320 px.
- Ajuste intermedio desde 680 px.
- Experiencia desktop desde 1024 px.
- El cambio de breakpoint en caliente conservará pantalla, sección y formularios visibles.
- Los controles táctiles tendrán al menos 44 × 44 px.
- El contenido no producirá scroll horizontal a 320 px.
- Las líneas de texto normal no superarán aproximadamente 80 caracteres.

## Accesibilidad

- Contraste WCAG AA para texto, controles y foco.
- Foco visible que no dependa solo del color.
- Navegación completa mediante teclado.
- `aria-current` en navegación activa.
- `aria-live` para confirmaciones y errores relevantes.
- Etiquetas accesibles para iconos y controles sin texto visible.
- Orden de foco coherente al abrir, guardar o cancelar formularios.
- Movimiento reducido cuando el sistema lo solicite.
- Zoom de texto al 200 % sin pérdida de contenido o acciones.

## Movimiento

- Una transición breve al cambiar de ejercicio puede actualizar nombre, referencias y formulario como un solo momento.
- El guardado puede usar una confirmación de 160–220 ms.
- No se animarán todas las tarjetas al cargar ni se añadirán movimientos ambientales.
- Las transiciones deben mostrar qué cambió, no decorar.

## Restricciones técnicas

- PHP 8+, MySQL/MariaDB, HTML, CSS y JavaScript sin bundler.
- No introducir React, Vue, Tailwind ni una biblioteca de componentes.
- Mantener Chart.js como dependencia CDN existente y ofrecer fallback sin conexión.
- Alojar fuentes e iconos necesarios dentro de `public/assets/`.
- Mantener los endpoints y el modelo de datos actuales salvo bloqueo demostrado.
- Conservar protección CSRF, ownership, sesiones y reglas de gimnasios.
- Mantener compatibilidad con hosting compartido IONOS.

## Archivos previstos

- `public/index.html` — estructura y semántica de las pantallas.
- `public/assets/app.css` — tokens, componentes, layouts y responsive.
- `public/assets/app.js` — estado visual, búsqueda, feedback y diálogos.
- `public/assets/fonts/` — fuentes WOFF2 locales.
- `public/assets/icons/` — sprite o SVG locales si son necesarios.
- `scripts/` — pruebas de contratos y comportamiento frontend.
- `PROJECT_CONTEXT.md` y `docs/PROJECT_STRUCTURE.md` — documentación final del cambio.

No se dividirá JavaScript o CSS en múltiples módulos durante este rediseño salvo que una necesidad de mantenibilidad aparezca dentro de los archivos que ya se modifican.

## Validación

La implementación deberá comprobarse en:

- 390 × 844 px.
- 768 × 1024 px.
- 1440 × 900 px.
- Teclado completo.
- Texto al 200 %.
- Movimiento reducido.
- Sin entrenamientos.
- Sin ejercicios.
- Con y sin gimnasios.
- Con registros y sin registros.
- Sin Chart.js.
- Nombres y notas largos.
- Cambio de breakpoint con un formulario abierto.

Las comprobaciones automatizadas incluirán sintaxis JavaScript, contratos estructurales, comportamiento responsive y la suite PHP existente. Se añadirán pruebas de comportamiento para cualquier nueva lógica frontend que pueda aislarse sin introducir un framework de test pesado.

## Criterios de aceptación

- Entrenar es el destino inicial en móvil y escritorio.
- Una persona puede identificar ejercicio, referencia anterior, entrada y guardado sin recorrer varias tarjetas visualmente equivalentes.
- La marca introducida es el elemento dominante de Entrenar.
- Histórico comunica progreso antes de mostrar el detalle completo.
- Gestión conserva todas las operaciones actuales y mejora su jerarquía.
- No se pierde ninguna regla de gimnasios, importación, edición o eliminación.
- Todos los estados relevantes tienen instrucciones y feedback específicos.
- El diseño se reconoce por su tratamiento tipográfico y numérico, no solo por un cambio de color.
- La aplicación funciona sin proceso de build y mantiene el despliegue actual.
- Las pruebas existentes y nuevas terminan correctamente.

## Fuera de alcance

- Cambiar el nombre Gym Tracker.
- Añadir planes de entrenamiento, temporizador, series múltiples o programación semanal.
- Añadir roles de administrador.
- Crear una app nativa o PWA completa.
- Cambiar autenticación, API o esquema sin una necesidad demostrada.
- Añadir gamificación, comunidad o comparación entre usuarios.

