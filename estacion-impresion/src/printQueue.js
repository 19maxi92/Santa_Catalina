// Loop principal: pregunta al servidor si hay algo para imprimir, lo reclama,
// lo imprime, y confirma o avisa error. Diseñado para nunca "colgarse":
// cualquier falla en un pedido puntual no frena el resto de la cola.

const { ThermalPrinter, PrinterTypes } = require('node-thermal-printer');
const config = require('./config');
const { armarComanda } = require('./ticket');

const INTERVALO_POLLING_MS = 5000;

let timer = null;
let corriendo = false;
let onEvento = () => {};

function log(tipo, mensaje, extra = {}) {
  onEvento({ tipo, mensaje, extra, ts: Date.now() });
}

async function llamarApi(ruta, opciones = {}) {
  const url = config.get('servidorUrl').replace(/\/$/, '') + '/api-impresion/' + ruta;
  const resp = await fetch(url, {
    ...opciones,
    headers: {
      'Content-Type': 'application/json',
      'X-Estacion-Token': config.get('token'),
      ...(opciones.headers || {}),
    },
  });
  const data = await resp.json().catch(() => ({}));
  if (!resp.ok) {
    const err = new Error(data.error || `Error HTTP ${resp.status}`);
    err.status = resp.status;
    throw err;
  }
  return data;
}

function crearPrinter() {
  const interfaz = config.get('impresoraInterfaz');
  if (!interfaz) {
    throw new Error('No hay una impresora configurada en esta estación');
  }
  return new ThermalPrinter({
    type: PrinterTypes.EPSON,
    interface: interfaz,
    width: 42,
    removeSpecialCharacters: false,
    options: { timeout: 8000 },
  });
}

async function imprimirPedido(pedido) {
  const printer = crearPrinter();
  const conectada = await printer.isPrinterConnected().catch(() => false);
  if (!conectada) {
    throw new Error(`No se pudo conectar a la impresora (${config.get('impresoraInterfaz')})`);
  }

  armarComanda(printer, pedido);
  await printer.execute();
}

async function abrirCajon() {
  const printer = crearPrinter();
  const conectada = await printer.isPrinterConnected().catch(() => false);
  if (!conectada) {
    throw new Error(`No se pudo conectar a la impresora (${config.get('impresoraInterfaz')})`);
  }

  printer.openCashDrawer();
  await printer.execute();
}

async function procesarUnPendiente(item) {
  let reclamo;
  try {
    reclamo = await llamarApi('reclamar.php', {
      method: 'POST',
      body: JSON.stringify({ codigo: item.codigo }),
    });
  } catch (e) {
    // Otra estación lo tomó primero, o ya no existe: no es un error real, se ignora.
    log('info', `Pedido #${item.pedido_id} ya no disponible (${e.message})`);
    return;
  }

  const pedido = reclamo.pedido;
  const esCajon = pedido.accion === 'abrir_cajon';

  log(esCajon ? 'imprimiendo' : 'imprimiendo',
      esCajon ? `Abriendo cajón (pedido #${pedido.id})...` : `Imprimiendo pedido #${pedido.id}...`,
      { pedidoId: pedido.id });

  try {
    if (esCajon) {
      await abrirCajon();
    } else {
      await imprimirPedido(pedido);
    }
    await llamarApi('confirmar.php', {
      method: 'POST',
      body: JSON.stringify({ codigo: item.codigo }),
    });
    log('impreso', esCajon ? `Cajón abierto (pedido #${pedido.id})` : `Pedido #${pedido.id} impreso correctamente`, { pedidoId: pedido.id });
  } catch (e) {
    log('error', `Falló la impresión del pedido #${pedido.id}: ${e.message}`, { pedidoId: pedido.id });
    try {
      await llamarApi('error.php', {
        method: 'POST',
        body: JSON.stringify({ codigo: item.codigo, mensaje: e.message }),
      });
    } catch (e2) {
      // Si ni siquiera se pudo avisar el error, no hay mucho más para hacer;
      // el trabajo queda "reservado" y algún día habría que revisarlo a mano.
      log('error', `Además, no se pudo avisar el error al servidor: ${e2.message}`);
    }
  }
}

async function ciclo() {
  if (!config.estaConfigurada()) return;

  try {
    const data = await llamarApi('pendientes.php?token=' + encodeURIComponent(config.get('token')));
    const pendientes = Array.isArray(data.pendientes) ? data.pendientes : [];
    for (const item of pendientes) {
      // Uno por uno, no en paralelo: evita mandarle dos trabajos a la vez a
      // una impresora que puede no soportarlo bien.
      await procesarUnPendiente(item);
    }
    if (pendientes.length === 0) {
      log('ok', 'Sin pedidos nuevos');
    }
  } catch (e) {
    log('error', `No se pudo conectar con el servidor: ${e.message}`);
  }
}

function iniciar(callbackEventos) {
  onEvento = callbackEventos || (() => {});
  if (corriendo) return;
  corriendo = true;
  ciclo(); // primer chequeo inmediato
  timer = setInterval(ciclo, INTERVALO_POLLING_MS);
}

function detener() {
  corriendo = false;
  if (timer) clearInterval(timer);
  timer = null;
}

async function imprimirDePrueba() {
  const printer = new ThermalPrinter({
    type: PrinterTypes.EPSON,
    interface: config.get('impresoraInterfaz'),
    width: 42,
    options: { timeout: 8000 },
  });
  const conectada = await printer.isPrinterConnected().catch(() => false);
  if (!conectada) throw new Error('No se pudo conectar a la impresora');
  printer.alignCenter();
  printer.bold(true);
  printer.println('PRUEBA DE IMPRESIÓN');
  printer.bold(false);
  printer.println(new Date().toLocaleString('es-AR'));
  printer.cut();
  await printer.execute();
}

module.exports = { iniciar, detener, imprimirDePrueba };
