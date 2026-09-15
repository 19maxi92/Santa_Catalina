// Arma la comanda a partir de un pedido, reproduciendo lo más fielmente
// posible el diseño de admin/modules/impresion/comanda_simple.php (la comanda
// "manual" que se imprime desde el navegador), pero en ESC/POS directo:
//
//   ┌──────────────────────────────────────┐
//   │              Villa Elisa             │   ← ubicación (recuadro)
//   └──────────────────────────────────────┘
//   15-sep                               S    ← fecha + turno (S grande)
//   ────────────────────────────────────────
//               LOCAL LAURA                   ← nombre (doble alto)
//   ┌──────────────────────────────────────┐
//   │ 2pl Jamón y Queso (16)               │   ← sabores (recuadro)
//   └──────────────────────────────────────┘
//   ════════════════════════════════════════
//            TOTAL: 16 sándwiches              ← total (entre líneas dobles)
//   ════════════════════════════════════════
//   ┌──────────────────────────────────────┐
//   │              $10.800                 │   ← precio (recuadro, tamaño x2)
//   └──────────────────────────────────────┘
//    Modalidad: Retiro | Pago: Transferencia   ← pie chico
//
// Los recuadros se dibujan con los caracteres de caja de la página de
// códigos CP850 (que la impresora tiene que tener activa: ver printQueue.js).
// Los tamaños se manejan con GS ! n (no con ESC ! n, que además pisa la
// negrita), así se puede mezclar texto chico y grande en la misma línea.

const MESES = {
  Jan: 'ene', Feb: 'feb', Mar: 'mar', Apr: 'abr', May: 'may', Jun: 'jun',
  Jul: 'jul', Aug: 'ago', Sep: 'sep', Oct: 'oct', Nov: 'nov', Dec: 'dic',
};

// Caracteres de caja (existen en CP437, CP850 y CP858, las páginas de códigos
// que usan prácticamente todas las térmicas compatibles con Epson).
const CAJA_SIMPLE = { tl: '┌', tr: '┐', bl: '└', br: '┘', h: '─', v: '│' };
const CAJA_DOBLE = { tl: '╔', tr: '╗', bl: '╚', br: '╝', h: '═', v: '║' };

// ── Comandos ESC/POS de bajo nivel ────────────────────────────────────────

function tamanio(printer, ancho, alto) {
  // GS ! n → bits 4-7: multiplicador de ancho, bits 0-3: de alto (1x = 0)
  printer.add(Buffer.from([0x1d, 0x21, ((ancho - 1) << 4) | (alto - 1)]));
}

function fuenteChica(printer, activar) {
  printer.add(Buffer.from([0x1b, 0x4d, activar ? 0x01 : 0x00])); // ESC M n
}

function texto(printer, str) {
  // append() codifica a la página de códigos activa sin partir líneas
  // (print() las partiría al ancho configurado, y acá el ancho lo manejamos nosotros).
  printer.append(String(str));
}

function saltoLinea(printer) {
  printer.add(Buffer.from([0x0a]));
}

// ── Texto / formato ──────────────────────────────────────────────────────

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

// Fecha del servidor ("2026-09-16" o "2026-09-16 14:30:00") → partes numéricas.
// Se parsea a mano a propósito: new Date("2026-09-16") lo toma como UTC y en
// Argentina (UTC-3) eso cae en el día anterior.
function partesFecha(valor) {
  const m = String(valor || '').match(/^(\d{4})-(\d{2})-(\d{2})(?:[T ](\d{2}):(\d{2}))?/);
  if (!m) return null;
  return { anio: m[1], mes: m[2], dia: m[3], hora: m[4] || '00', min: m[5] || '00' };
}

function hoyLocal() {
  const d = new Date();
  const p2 = (n) => String(n).padStart(2, '0');
  return `${d.getFullYear()}-${p2(d.getMonth() + 1)}-${p2(d.getDate())}`;
}

// Igual criterio que comanda_simple.php: fecha_entrega si existe, si no created_at → "15-sep"
function formatearFechaCorta(pedido) {
  const p = partesFecha(pedido.fecha_entrega || pedido.created_at);
  if (!p) return '';
  const mesIngles = Object.keys(MESES)[parseInt(p.mes, 10) - 1];
  return `${p.dia}-${MESES[mesIngles] || p.mes}`;
}

function esEntregaFutura(pedido) {
  if (!pedido.fecha_entrega) return false;
  return String(pedido.fecha_entrega).slice(0, 10) !== hoyLocal();
}

// Pie: "ENTREGA: 16/09/2026" si es para otro día, si no la fecha del pedido "15/09 01:02"
function textoFechaPie(pedido) {
  if (esEntregaFutura(pedido)) {
    const p = partesFecha(pedido.fecha_entrega);
    return p ? `ENTREGA: ${p.dia}/${p.mes}/${p.anio}` : '';
  }
  if (pedido.fecha_display) return String(pedido.fecha_display);
  const p = partesFecha(pedido.created_at);
  return p ? `${p.dia}/${p.mes}/${p.anio} ${p.hora}:${p.min}` : '';
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

    // Formato directo "• Sabor: 16" (sin "plancha"), igual que el PHP
    if (lineas.length === 0) {
      const directos = [...bloque[1].matchAll(/•\s*([^:•]+):\s*(\d+)(?!\s*plancha)/gi)];
      directos.forEach((m) => lineas.push(`${parseInt(m[2], 10)} ${m[1].trim()}`));
    }
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

// Parte un texto en líneas de como máximo `ancho` caracteres, cortando por palabra.
function partirLineas(str, ancho) {
  const resultado = [];
  String(str || '').split('\n').forEach((parrafo) => {
    let linea = '';
    parrafo.split(/\s+/).filter(Boolean).forEach((palabra) => {
      while (palabra.length > ancho) {
        if (linea) { resultado.push(linea); linea = ''; }
        resultado.push(palabra.slice(0, ancho));
        palabra = palabra.slice(ancho);
      }
      if (!linea) linea = palabra;
      else if (linea.length + 1 + palabra.length <= ancho) linea += ' ' + palabra;
      else { resultado.push(linea); linea = palabra; }
    });
    resultado.push(linea);
  });
  return resultado;
}

function centrar(str, ancho) {
  const s = String(str);
  if (s.length >= ancho) return s.slice(0, ancho);
  const izq = Math.floor((ancho - s.length) / 2);
  return ' '.repeat(izq) + s + ' '.repeat(ancho - s.length - izq);
}

// ── Bloques de dibujo ────────────────────────────────────────────────────

function lineaHorizontal(printer, ancho, caracter, { negrita = false } = {}) {
  printer.alignLeft();
  printer.bold(negrita);
  texto(printer, caracter.repeat(ancho));
  saltoLinea(printer);
  printer.bold(false);
}

/**
 * Recuadro con texto adentro.
 *   lineas:   array de strings (ya partidas al ancho interno si hace falta)
 *   escala:   1 = normal, 2 = doble ancho y doble alto (para el precio)
 *   alto:     multiplicador de alto solo (2 = doble alto, mismo ancho)
 *   alinear:  'centro' | 'izquierda'
 *   estilo:   CAJA_SIMPLE | CAJA_DOBLE
 */
function recuadro(printer, ancho, lineas, { escala = 1, alto = 1, alinear = 'centro', estilo = CAJA_SIMPLE } = {}) {
  const interno = ancho - 2;
  const altoLinea = Math.max(alto, escala);

  printer.alignLeft();
  printer.bold(true);

  texto(printer, estilo.tl + estilo.h.repeat(interno) + estilo.tr);
  saltoLinea(printer);

  lineas.forEach((l) => {
    const maxChars = Math.floor(interno / escala);
    const contenido = String(l).slice(0, maxChars);
    const colsTexto = contenido.length * escala;
    const padIzq = alinear === 'centro' ? Math.floor((interno - colsTexto) / 2) : 1;
    const padDer = interno - colsTexto - padIzq;

    // Bordes laterales del mismo alto que el texto, así la línea vertical no queda cortada
    tamanio(printer, 1, altoLinea);
    texto(printer, estilo.v + ' '.repeat(padIzq));
    tamanio(printer, escala, altoLinea);
    texto(printer, contenido);
    tamanio(printer, 1, altoLinea);
    texto(printer, ' '.repeat(padDer) + estilo.v);
    tamanio(printer, 1, 1);
    saltoLinea(printer);
  });

  texto(printer, estilo.bl + estilo.h.repeat(interno) + estilo.br);
  saltoLinea(printer);
  printer.bold(false);
}

/**
 * Escribe la comanda en el objeto `printer` de node-thermal-printer.
 * No imprime todavía (eso lo hace quien llama, con printer.execute()).
 */
function armarComanda(printer, pedido) {
  const W = printer.getWidth();
  const nombreCompleto = pedido.cliente_fijo_nombre
    ? `${pedido.cliente_fijo_nombre} ${pedido.cliente_fijo_apellido}`
    : `${pedido.nombre} ${pedido.apellido}`;

  const turno = extraerTurno(pedido.observaciones);
  const obsLimpia = limpiarObservaciones(pedido.observaciones);
  const esPersonalizado =
    (pedido.producto || '').includes('Personalizado') ||
    (pedido.producto || '').includes('Surtidos Elegidos') ||
    (pedido.observaciones || '').includes('Sabores:');

  // Estado limpio por las dudas (cada trabajo arranca con un buffer nuevo, pero
  // la impresora puede haber quedado en negrita/doble tamaño de un trabajo cortado)
  tamanio(printer, 1, 1);
  printer.bold(false);
  printer.invert(false);
  fuenteChica(printer, false);

  // 1) UBICACIÓN — recuadro
  recuadro(printer, W, [pedido.ubicacion || '']);

  // 2) FECHA (izquierda) + TURNO grande (derecha), y línea finita abajo
  const fechaCorta = formatearFechaCorta(pedido);
  const entregaFutura = esEntregaFutura(pedido);
  const fechaTexto = entregaFutura ? ` ENTREGA: ${fechaCorta} ` : fechaCorta;
  const pad = Math.max(1, W - fechaTexto.length - 2 * turno.length);
  printer.alignLeft();
  printer.bold(true);
  if (entregaFutura) printer.invert(true); // "badge" resaltado, como el recuadro del HTML
  texto(printer, fechaTexto);
  if (entregaFutura) printer.invert(false);
  texto(printer, ' '.repeat(pad));
  tamanio(printer, 2, 2);
  texto(printer, turno);
  tamanio(printer, 1, 1);
  saltoLinea(printer);
  printer.bold(false);
  lineaHorizontal(printer, W, CAJA_SIMPLE.h);

  // 3) NOMBRE CLIENTE — doble alto, centrado
  printer.alignCenter();
  printer.bold(true);
  tamanio(printer, 1, 2);
  partirLineas(nombreCompleto.toUpperCase(), W).forEach((l) => { texto(printer, l); saltoLinea(printer); });
  tamanio(printer, 1, 1);
  printer.bold(false);

  // 4) OBSERVACIONES (si existen) — título y recuadro doble con texto grande
  if (obsLimpia) {
    lineaHorizontal(printer, W, CAJA_DOBLE.h, { negrita: true });
    printer.alignCenter();
    printer.bold(true);
    tamanio(printer, 1, 2);
    texto(printer, 'OBSERVACIONES');
    saltoLinea(printer);
    tamanio(printer, 1, 1);
    printer.bold(false);
    const lineasObs = partirLineas(obsLimpia.toUpperCase(), Math.floor((W - 2) / 2));
    recuadro(printer, W, lineasObs, { escala: 2, alinear: 'izquierda', estilo: CAJA_DOBLE });
  }

  // 5) SABORES + TOTAL, o PRODUCTO
  if (esPersonalizado) {
    const lineasSabores = [];
    extraerSabores(pedido).forEach((s) => {
      partirLineas(s, W - 4).forEach((l, i) => lineasSabores.push(i === 0 ? l : '   ' + l));
    });
    if (lineasSabores.length === 0) lineasSabores.push('(sin detalle de sabores)');
    recuadro(printer, W, lineasSabores, { alto: 2, alinear: 'izquierda' });

    lineaHorizontal(printer, W, CAJA_DOBLE.h, { negrita: true });
    printer.alignCenter();
    printer.bold(true);
    tamanio(printer, 1, 2);
    texto(printer, `TOTAL: ${extraerTotalSandwiches(pedido.producto)} sándwiches`);
    saltoLinea(printer);
    tamanio(printer, 1, 1);
    printer.bold(false);
    lineaHorizontal(printer, W, CAJA_DOBLE.h, { negrita: true });
  } else {
    recuadro(printer, W, partirLineas(pedido.producto || '', W - 4), { alto: 2 });
  }

  // 6) PRECIO — recuadro con texto al doble de tamaño
  recuadro(printer, W, [formatearPrecio(pedido.precio)], { escala: 2 });

  // 7) PIE ADMINISTRATIVO — chico y centrado
  printer.alignCenter();
  fuenteChica(printer, true);
  texto(printer, '- '.repeat(Math.floor(W / 2)));
  saltoLinea(printer);
  texto(printer, `Modalidad: ${pedido.modalidad || ''} | Pago: ${pedido.forma_pago || ''}`);
  saltoLinea(printer);
  if (entregaFutura) printer.bold(true);
  texto(printer, textoFechaPie(pedido));
  saltoLinea(printer);
  printer.bold(false);
  texto(printer, `Pedido #${pedido.id}`);
  saltoLinea(printer);
  fuenteChica(printer, false);
  printer.alignLeft();

  printer.cut();
}

/**
 * Ticket de prueba: sirve para verificar de un vistazo que la impresora
 * dibuja bien los recuadros y los acentos (si salen símbolos raros en vez de
 * líneas, la impresora no tiene la página de códigos CP850).
 */
function armarPrueba(printer) {
  const W = printer.getWidth();
  tamanio(printer, 1, 1);
  printer.bold(false);
  recuadro(printer, W, ['PRUEBA DE IMPRESIÓN']);
  printer.alignCenter();
  texto(printer, new Date().toLocaleString('es-AR'));
  saltoLinea(printer);
  texto(printer, 'Acentos: á é í ó ú ñ Ñ Ó ¿ ¡');
  saltoLinea(printer);
  recuadro(printer, W, ['$12.345'], { escala: 2 });
  printer.alignCenter();
  texto(printer, 'Si ves los recuadros bien, está todo OK');
  saltoLinea(printer);
  printer.cut();
}

module.exports = {
  armarComanda,
  armarPrueba,
  limpiarObservaciones,
  extraerTurno,
  formatearPrecio,
  formatearFechaCorta,
  extraerTotalSandwiches,
  extraerSabores,
  partirLineas,
};
