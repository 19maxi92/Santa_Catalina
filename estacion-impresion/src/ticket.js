// Arma el texto de la comanda a partir de un pedido (mismo criterio y mismo
// orden de campos que admin/modules/impresion/comanda_simple.php, pero
// "dibujado" con líneas en vez de bordes CSS, para imprimir directo por
// ESC/POS en vez de un HTML para el navegador.

const MESES = {
  Jan: 'ene', Feb: 'feb', Mar: 'mar', Apr: 'abr', May: 'may', Jun: 'jun',
  Jul: 'jul', Aug: 'ago', Sep: 'sep', Oct: 'oct', Nov: 'nov', Dec: 'dic',
};

function limpiarObservaciones(obs) {
  if (!obs) return '';
  let t = obs;
  t = t.replace(/===\s*SABORES PERSONALIZADOS\s*===[\s\S]*?(?=\n---|$)/, '');
  t = t.replace(/---\s*Info del Sistema\s*---[\s\S]*$/, '');
  t = t.replace(/Pedido Express - Empleado ID:.*$/m, '');
  t = t.replace(/Fecha\/Hora:.*$/m, '');
  t = t.replace(/🔗\s*PEDIDO COMBINADO.*$/m, '');
  t = t.replace(/^Turno:.*$/m, '');
  t = t.replace(/^🌐\s*PEDIDO ONLINE\s*$/m, '');
  t = t.replace(/^🎨\s*Pedido Personalizado\s*$/m, '');
  t = t.replace(/^Sabores:.*$/m, '');
  t = t.replace(/\[Datos sabores:[\s\S]*?\]/, '');
  t = t.replace(/^Notas del cliente:\s*$/m, '');
  t = t.replace(/\n\s*\n+/g, '\n');
  return t.trim();
}

function extraerTurno(observaciones) {
  const m = (observaciones || '').match(/Turno:\s*([MST]|Mañana|Siesta|Tarde)/i);
  if (!m) return 'M';
  const v = m[1];
  if (/^(Mañana|M)$/i.test(v)) return 'M';
  if (/^(Siesta|S)$/i.test(v)) return 'S';
  if (/^(Tarde|T)$/i.test(v)) return 'T';
  return 'M';
}

function formatearPrecio(precio) {
  const n = Math.round(Number(precio) || 0);
  return '$' + n.toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
}

// Igual criterio que comanda_simple.php: fecha_entrega si existe, si no created_at.
function formatearFechaCorta(pedido) {
  const fechaBase = pedido.fecha_entrega || pedido.created_at;
  if (!fechaBase) return '';
  const d = new Date(fechaBase);
  if (Number.isNaN(d.getTime())) return '';
  const dia = String(d.getDate()).padStart(2, '0');
  const mesIngles = d.toLocaleString('en-US', { month: 'short' });
  const mes = MESES[mesIngles] || mesIngles.toLowerCase();
  return `${dia}-${mes}`;
}

function esEntregaFutura(pedido) {
  if (!pedido.fecha_entrega) return false;
  const hoy = new Date().toISOString().slice(0, 10);
  return String(pedido.fecha_entrega).slice(0, 10) !== hoy;
}

// Mismo cálculo que comanda_simple.php: "x48" en el nombre del producto,
// o el número inicial ("24 Surtidos Elegidos").
function extraerTotalSandwiches(producto) {
  const p = producto || '';
  let m = p.match(/x(\d+)/i);
  if (m) return m[1];
  m = p.match(/^(\d+)/);
  if (m) return m[1];
  return '?';
}

function extraerSabores(pedido) {
  const obs = pedido.observaciones || '';
  const lineas = [];

  const bloque = obs.match(/===\s*SABORES PERSONALIZADOS\s*===[\s\n]*([\s\S]*?)(?:---|$)/);
  if (bloque) {
    const matches = [...bloque[1].matchAll(/•\s*([^:]+):\s*(\d+)\s*plancha/gi)];
    matches.forEach((m) => {
      const sabor = m[1].trim();
      const planchas = parseInt(m[2], 10);
      lineas.push(`${planchas}pl ${sabor} (${planchas * 8})`);
    });
  }

  if (lineas.length === 0) {
    const bloqueOnline = obs.match(/Sabores:\s*(.+?)(?:\n\[|\n\n|$)/s);
    if (bloqueOnline) {
      const matches = [...bloqueOnline[1].matchAll(/(\d+)\s*x\s*([^,]+)/gi)];
      matches.forEach((m) => {
        const cant = parseInt(m[1], 10);
        const sabor = m[2].trim();
        lineas.push(`${Math.ceil(cant / 8)}pl ${sabor} (${cant})`);
      });
    }
  }

  return lineas;
}

// "Caja": línea, contenido centrado en negrita, línea — la aproximación de
// un recuadro que puede dibujar una impresora térmica con texto plano.
function caja(printer, lineas, { doble = false } = {}) {
  printer.drawLine();
  printer.alignCenter();
  printer.bold(true);
  if (doble) printer.setTextDoubleHeight();
  lineas.forEach((l) => printer.println(l));
  if (doble) printer.setTextNormal();
  printer.bold(false);
  printer.drawLine();
}

/**
 * Escribe la comanda en el objeto `printer` de node-thermal-printer.
 * No imprime todavía (eso lo hace quien llama, con printer.execute()).
 */
function armarComanda(printer, pedido) {
  const nombreCompleto = pedido.cliente_fijo_nombre
    ? `${pedido.cliente_fijo_nombre} ${pedido.cliente_fijo_apellido}`
    : `${pedido.nombre} ${pedido.apellido}`;

  const turno = extraerTurno(pedido.observaciones);
  const obsLimpia = limpiarObservaciones(pedido.observaciones);
  const esPersonalizado =
    (pedido.producto || '').includes('Personalizado') ||
    (pedido.producto || '').includes('Surtidos Elegidos') ||
    (pedido.observaciones || '').includes('Sabores:');

  // 1) UBICACIÓN
  caja(printer, [pedido.ubicacion || ''], { doble: true });

  // 2) FECHA + TURNO (misma línea, como en comanda_simple.php)
  const fechaTexto = esEntregaFutura(pedido)
    ? `ENTREGA: ${formatearFechaCorta(pedido)}`
    : formatearFechaCorta(pedido);
  printer.alignLeft();
  printer.bold(true);
  printer.leftRight(fechaTexto, turno);
  printer.bold(false);
  printer.drawLine();

  // 3) NOMBRE CLIENTE
  printer.alignCenter();
  printer.bold(true);
  printer.println(nombreCompleto.toUpperCase());
  printer.bold(false);

  // 4) OBSERVACIONES (si existen)
  if (obsLimpia) {
    printer.drawLine();
    printer.alignCenter();
    printer.bold(true);
    printer.println('OBSERVACIONES');
    printer.setTextDoubleHeight();
    obsLimpia.split('\n').forEach((linea) => printer.println(linea.toUpperCase()));
    printer.setTextNormal();
    printer.bold(false);
  }

  // 5) SABORES + TOTAL, o PRODUCTO
  if (esPersonalizado) {
    printer.drawLine();
    printer.alignLeft();
    extraerSabores(pedido).forEach((linea) => printer.println(linea));
    printer.alignCenter();
    printer.bold(true);
    printer.println(`TOTAL: ${extraerTotalSandwiches(pedido.producto)} sándwiches`);
    printer.bold(false);
  } else {
    caja(printer, [pedido.producto || '']);
  }

  // 6) PRECIO
  caja(printer, [formatearPrecio(pedido.precio)], { doble: true });

  // 7) INFO ADMINISTRATIVA (pie)
  printer.alignLeft();
  printer.println(`Modalidad: ${pedido.modalidad || ''} | Pago: ${pedido.forma_pago || ''}`);
  printer.println(`Pedido #${pedido.id}`);

  printer.cut();
}

module.exports = {
  armarComanda,
  limpiarObservaciones,
  extraerTurno,
  formatearPrecio,
  formatearFechaCorta,
  extraerTotalSandwiches,
};
