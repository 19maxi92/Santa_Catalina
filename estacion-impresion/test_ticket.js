// Vista previa en consola de la comanda que arma src/ticket.js, usando la
// librería real (node-thermal-printer) y "decodificando" el ESC/POS que sale,
// así se ve el layout tal como lo va a imprimir la térmica, sin impresora.
//
//   cd estacion-impresion && npm install && node test_ticket.js
//
// Convenciones de la vista previa:
//   - Texto en doble ancho: cada carácter se muestra seguido de un espacio
//     (ocupa 2 columnas, igual que en el papel).
//   - Texto en negrita, doble alto, invertido o fuente chica: se muestra
//     igual que el normal (no cambia el ancho de columna).

const path = require('path');
const { ThermalPrinter, PrinterTypes, CharacterSet } = require('node-thermal-printer');
const iconv = require('iconv-lite');
const { armarComanda, armarPrueba } = require('./src/ticket');

function crearPrinterDePrueba() {
  return new ThermalPrinter({
    type: PrinterTypes.EPSON,
    interface: 'file:' + path.join(require('os').tmpdir(), 'santa-catalina-test.prn'),
    width: 42,
    characterSet: CharacterSet.PC850_MULTILINGUAL,
    removeSpecialCharacters: false,
  });
}

// Decodificador mínimo de ESC/POS → texto (solo lo que usa ticket.js)
function escposATexto(buf) {
  let out = '';
  let dobleAncho = false;
  let i = 0;
  let pendiente = [];

  const flush = () => {
    if (pendiente.length) {
      const txt = iconv.decode(Buffer.from(pendiente), 'cp850');
      out += dobleAncho ? [...txt].map((c) => c + ' ').join('') : txt;
      pendiente = [];
    }
  };

  while (i < buf.length) {
    const b = buf[i];
    if (b === 0x1b) { // ESC
      flush();
      const cmd = buf[i + 1];
      if (cmd === 0x70) { i += 5; continue; }           // ESC p m t1 t2 (cajón)
      if (cmd === 0x40) { i += 2; continue; }           // ESC @
      if (cmd === 0x64) { out += '\n'.repeat(buf[i + 2]); i += 3; continue; } // ESC d n
      i += 3;                                            // ESC E/a/t/M/!  n
      continue;
    }
    if (b === 0x1d) { // GS
      flush();
      const cmd = buf[i + 1];
      if (cmd === 0x21) { dobleAncho = (buf[i + 2] >> 4) > 0; i += 3; continue; } // GS ! n
      if (cmd === 0x56) { out += '======== CORTE ========\n'; i += 3; continue; }  // GS V n
      i += 3;                                            // GS B n
      continue;
    }
    if (b === 0x0a) { flush(); out += '\n'; i += 1; continue; }
    pendiente.push(b);
    i += 1;
  }
  flush();
  return out;
}

function mostrar(titulo, armar) {
  const printer = crearPrinterDePrueba();
  armar(printer);
  const buffer = printer.getBuffer();
  console.log(`\n=== ${titulo} (${buffer.length} bytes) ===`);
  const txt = escposATexto(buffer);
  // Marco visual del ancho real del papel (42 columnas)
  txt.split('\n').forEach((l) => console.log('|' + l.padEnd(42) + '|' + (l.length > 42 ? '  <-- SE PASA' : '')));
}

const pedidoPersonalizado = {
  id: 22004,
  ubicacion: 'Fábrica',
  nombre: 'Prueba',
  apellido: 'Prueba',
  cliente_fijo_nombre: null,
  producto: 'Personalizado x24 (3 planchas)',
  precio: 16000,
  modalidad: 'Retiro',
  forma_pago: 'Transferencia',
  created_at: '2026-09-15 01:02:21',
  fecha_display: '15/09 01:02',
  observaciones: `Turno: Siesta
=== SABORES PERSONALIZADOS ===
• Aceitunas: 1 plancha(s) (8 sándwiches)
• Zanahoria y Queso: 1 plancha(s) (8 sándwiches)
• Tomate: 1 plancha(s) (8 sándwiches)

--- Info del Sistema ---
Pedido Express - Empleado ID: 4
Fecha/Hora: 2026-09-15 01:02`,
};

const pedidoNormal = {
  id: 502,
  ubicacion: 'Villa Elisa',
  nombre: 'Ana',
  apellido: 'Martínez',
  producto: '48 Jamón y Queso',
  precio: 28000,
  modalidad: 'Delivery',
  forma_pago: 'Efectivo',
  created_at: '2026-09-15 10:30:00',
  fecha_entrega: '2099-12-24',
  observaciones: 'Turno: M\nSin cebolla por favor, y cortar en triángulos chicos',
};

const pedidoOnline = {
  id: 9126,
  ubicacion: 'Local 1',
  nombre: 'Laura',
  apellido: 'Gómez',
  producto: '16 Surtidos Elegidos',
  precio: 10800,
  modalidad: 'Retiro',
  forma_pago: 'Transferencia',
  created_at: '2026-09-15 12:00:00',
  observaciones: '🌐 PEDIDO ONLINE\n🎨 Pedido Personalizado\nSabores: 8x Jamón y Queso, 8x Tomate\n[Datos sabores: {"x":1}]\nTurno: Tarde',
};

mostrar('COMANDA PERSONALIZADA (como la foto de referencia)', (p) => armarComanda(p, pedidoPersonalizado));
mostrar('COMANDA NORMAL con entrega futura y observaciones', (p) => armarComanda(p, pedidoNormal));
mostrar('COMANDA ONLINE (Surtidos Elegidos)', (p) => armarComanda(p, pedidoOnline));
mostrar('TICKET DE PRUEBA', (p) => armarPrueba(p));

// Chequeo de que los caracteres de caja realmente existen en CP850
const cajas = '┌─┐│└┘╔═╗║╚╝';
const codificado = iconv.encode(cajas, 'cp850');
const vuelta = iconv.decode(codificado, 'cp850');
console.log('\nCaracteres de caja en CP850:', vuelta === cajas ? 'OK' : 'FALLA (' + vuelta + ')');
