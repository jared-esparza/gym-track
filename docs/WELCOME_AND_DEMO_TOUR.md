# Bienvenida y guía de la demo

## Navegación

La raíz pública muestra la presentación del producto. `?login=1` abre el acceso, `?register=1` el registro y `?demo=1` inicia una demo. Los enlaces existentes `?reset=TOKEN` y `?verified=1` siguen abriendo su estado de acceso. Si ya existe una sesión válida, se entra directamente en la aplicación. Salir de la demo vuelve a la bienvenida; crear cuenta desde la demo abre un registro limpio.

Fotografías, fuentes y capturas se sirven localmente. Ver `public/assets/images/CREDITS.md` para fuentes, autores y capturas. No cambia la base de datos, la API ni las extensiones PHP requeridas.

## Tour

`public/assets/demo-tour.js` expone `window.DemoTour`, un controlador con navegación de aplicación inyectada. Usa solo selecciones de interfaz y las consultas existentes, nunca escrituras. Sus seis pasos cubren demo temporal, catálogo, registro, organización opcional, histórico y gestión. Al completar o saltar vuelve a Entrenar con el catálogo completo.

La primera demo en cada navegador abre la guía. Completar o saltar escribe `cuaderno.demo-tour.v1` en `localStorage`; reiniciar ejemplos no elimina esa elección. «Ver guía» repite el recorrido manualmente. Si localStorage está bloqueado, intenta sessionStorage y, si tampoco está disponible, recuerda la elección en memoria mientras la página permanezca abierta. La guía nunca aparece en cuentas reales.

El diálogo nativo mantiene el foco dentro, admite Escape y devuelve el foco al cerrar. Los cambios de paso anuncian su descripción. En móvil el panel inferior deja espacio para el elemento explicado y durante el tour se añade espacio de desplazamiento temporal. Se limpia al cerrar, fallar una carga, salir o caducar la sesión. La guía admite catálogos vacíos y rutinas eliminadas sin recrearlos. Los desplazamientos son inmediatos y no requieren animaciones.

## Verificación

```powershell
node scripts/welcome-routing.test.cjs
node scripts/welcome-tour.test.cjs
node --check public/assets/demo-tour.js
node --check public/assets/app.js
```

Ejecutar también las suites UI existentes, PHPUnit, lint PHP e integración `demo-api.test.cjs` con `TEST_BASE_URL` apuntando al servidor local.

Comprobar en navegador a 390 × 844 y en escritorio: bienvenida y enlaces de cuenta, todos los accesos a demo, los seis pasos, Atrás, Escape, salto, finalización y repetición manual; comprobar que recargar no repite automáticamente. Repetir con ejemplos borrados y almacenamiento bloqueado. Comparar exports antes/después confirma que el recorrido no modifica datos. Las imágenes fallidas deben dejar visibles el texto y los accesos.
