// Arma el texto de la comanda a partir de un pedido (mismo criterio que
// admin/modules/impresion/comanda_simple.php, pero para imprimir directo
// por ESC/POS en vez de un HTML para el navegador).

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

  printer.alignCenter();
  printer.setTextDoubleHeight();
  printer.bold(true);
  printer.println(pedido.ubicacion || '');
  printer.bold(false);
  printer.setTextNormal();
  printer.drawLine();

  printer.alignLeft();
  printer.println(`Turno: ${turno}`);

  printer.alignCenter();
  printer.bold(true);
  printer.println(nombreCompleto.toUpperCase());
  printer.bold(false);

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

  printer.drawLine();

  if (esPersonalizado) {
    // Sabores: mismo parseo que hace comanda_simple.php, versión simplificada
    printer.alignLeft();
    const obs = pedido.observaciones || '';
    const bloque = obs.match(/===\s*SABORES PERSONALIZADOS\s*===[\s\n]*([\s\S]*?)(?:---|$)/);
    let huboSabores = false;
    if (bloque) {
      const matches = [...bloque[1].matchAll(/•\s*([^:]+):\s*(\d+)\s*plancha/gi)];
      matches.forEach((m) => {
        const sabor = m[1].trim();
        const planchas = parseInt(m[2], 10);
        printer.println(`${planchas}pl ${sabor} (${planchas * 8})`);
        huboSabores = true;
      });
    }
    if (!huboSabores) {
      const bloqueOnline = obs.match(/Sabores:\s*(.+?)(?:\n\[|\n\n|$)/s);
      if (bloqueOnline) {
        const matches = [...bloqueOnline[1].matchAll(/(\d+)\s*x\s*([^,]+)/gi)];
        matches.forEach((m) => {
          const cant = parseInt(m[1], 10);
          const sabor = m[2].trim();
          printer.println(`${Math.ceil(cant / 8)}pl ${sabor} (${cant})`);
        });
      }
    }
    printer.alignCenter();
  } else {
    printer.bold(true);
    printer.println(pedido.producto || '');
    printer.bold(false);
  }

  printer.drawLine();
  printer.setTextDoubleHeight();
  printer.bold(true);
  printer.println(formatearPrecio(pedido.precio));
  printer.bold(false);
  printer.setTextNormal();

  printer.alignLeft();
  printer.newLine();
  printer.println(`Modalidad: ${pedido.modalidad || ''} | Pago: ${pedido.forma_pago || ''}`);
  printer.println(`Pedido #${pedido.id}`);

  printer.cut();
}

module.exports = { armarComanda, limpiarObservaciones, extraerTurno, formatearPrecio };
