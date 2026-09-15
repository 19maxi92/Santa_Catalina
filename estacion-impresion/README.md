# Estación de Impresión — Santa Catalina

App de escritorio (Windows) que imprime las comandas automáticamente apenas
entra un pedido, sin depender de que alguien esté mirando la pantalla.

## Cómo funciona (resumen)

1. Cada pedido nuevo genera una "copia" en la tabla `cola_impresion` (ver
   `api-impresion/` en la raíz del repo).
2. Esta app pregunta cada 5 segundos si hay algo pendiente **para su
   sucursal**, lo reclama (de forma atómica, para que dos PCs nunca impriman
   lo mismo), lo imprime, y avisa al servidor que ya salió.
3. Si algo falla (impresora apagada, sin papel, etc.) el trabajo queda
   disponible para reintentarse, y nunca se pierde en silencio.
4. **El botón manual "Imprimir" del sistema web sigue funcionando exactamente
   igual que hoy** — esto es un agregado, no un reemplazo. Si esta app está
   apagada o mal configurada, todo sigue imprimiéndose a mano como siempre.

## Antes de instalarla en una PC real

Hace falta:

1. Correr la migración `migrations/add_cola_impresion.php` una vez (crea las
   tablas nuevas, no toca ninguna existente).
2. Entrar a `api-impresion/estaciones_v2.php` (logueado como admin) y crear una
   estación — te da un **token** que hay que pegar en la app.
3. Saber cómo se conecta la impresora térmica desde esa PC:
   - Impresora en red (por IP): `tcp://192.168.1.41`
   - Impresora instalada en Windows (USB compartida): `printer:NombreExacto`
     (el nombre tal cual aparece en "Impresoras y escáneres" de Windows)

## Desarrollo local

```bash
cd estacion-impresion
npm install
npm start
```

## Generar el instalador de Windows

```bash
npm run dist
```

El instalador queda en `estacion-impresion/dist/` (no se sube al repo).

## ⚠️ Qué se probó y qué falta probar

Esto se armó y se probó unitariamente (sin impresora ni Windows reales
disponibles en este entorno):

- ✅ El armado del texto de la comanda (`src/ticket.js`) — probado con
  `node test_ticket.js` contra ejemplos reales de pedidos personalizados y
  normales, comparando contra la lógica de `comanda_simple.php`.
- ✅ Los endpoints PHP (`api-impresion/*.php`) — sintaxis validada, lógica de
  claim atómico revisada a mano.
- ✅ Que la librería `node-thermal-printer` expone los métodos que se usan
  (`println`, `cut`, `execute`, `isPrinterConnected`, etc.) — verificado
  contra el paquete instalado.
- ❌ **No se probó imprimir de verdad en una POS80-CX ni en ninguna
  impresora física** — esto no se puede hacer desde este entorno (no hay
  Windows ni hardware acá). Antes de confiar en esto en el local, hay que
  probarlo con la impresora real: usar el botón "Imprimir prueba" de la app
  con la impresora conectada, y confirmar que sale bien.
- ❌ No se probó el instalador `.exe` generado en una PC Windows real (solo
  se armó, no se ejecutó su instalación).
- ❌ Todavía **no está conectada** la creación de pedidos (`crear_pedido.php`,
  etc.) con la cola — a propósito, como se acordó: primero se prueba todo
  esto por separado, y ese último cable se conecta cuando ya esté validado.
