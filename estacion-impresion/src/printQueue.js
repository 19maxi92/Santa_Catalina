// Loop principal: pregunta al servidor si hay algo para imprimir, lo reclama,
// lo imprime, y confirma o avisa error. Diseñado para nunca "colgarse":
// cualquier falla en un pedido puntual no frena el resto de la cola.

const fs = require('fs');
const os = require('os');
const path = require('path');
const { exec } = require('child_process');
const { promisify } = require('util');
const { ThermalPrinter, PrinterTypes, CharacterSet } = require('node-thermal-printer');
const config = require('./config');
const { armarComanda, armarPrueba } = require('./ticket');

const execAsync = promisify(exec);

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

/**
 * Impresora instalada en Windows y COMPARTIDA (ej: "printer:POS80") — se
 * manda el ticket con `copy /b` al recurso compartido, sin necesitar
 * ningún driver nativo compilado. Alcanza con compartir la impresora
 * desde Windows (Propiedades de impresora → Compartir).
 */
function nombreCompartida(interfaz) {
  return interfaz && interfaz.startsWith('printer:') ? interfaz.slice('printer:'.length) : null;
}

function crearPrinter() {
  const interfaz = config.get('impresoraInterfaz');
  if (!interfaz) {
    throw new Error('No hay una impresora configurada en esta estación');
  }

  const compartida = nombreCompartida(interfaz);

  // Para una impresora compartida no usamos la interfaz "printer:" de la
  // librería (esa sí pide un driver nativo) — usamos "file:" solo para que
  // arme el ticket en un buffer; el envío real lo hacemos nosotros con copy.
  const interfazReal = compartida
    ? 'file:' + path.join(os.tmpdir(), 'santa-catalina-buffer.tmp')
    : interfaz;

  const printer = new ThermalPrinter({
    type: PrinterTypes.EPSON,
    interface: interfazReal,
    width: 42,
    // CP850 (multilingüe): tiene los acentos del español (á é í ó ú ñ Ó...) y
    // los caracteres de caja (┌ ─ │ ═ ║) con los que ticket.js dibuja los
    // recuadros de la comanda. Es la página de códigos 2 en cualquier
    // impresora compatible con Epson (ESC t 2).
    characterSet: CharacterSet.PC850_MULTILINGUAL,
    removeSpecialCharacters: false,
    options: { timeout: 8000 },
  });

  printer._compartida = compartida;
  return printer;
}

async function verificarConectada(printer) {
  // Una impresora compartida no la "chequeamos" antes: probamos mandarle
  // el ticket directamente y el error de copy, si lo hay, ya es bastante claro.
  if (printer._compartida) return true;
  return printer.isPrinterConnected().catch(() => false);
}

async function enviarAlaImpresora(printer) {
  if (!printer._compartida) {
    await printer.execute();
    return;
  }

  const tmp = path.join(os.tmpdir(), `santa-catalina-${Date.now()}-${Math.random().toString(36).slice(2)}.prn`);
  fs.writeFileSync(tmp, printer.getBuffer());
  try {
    await execAsync(`copy /b "${tmp}" "\\\\localhost\\${printer._compartida}"`, { shell: 'cmd.exe' });
  } finally {
    fs.unlink(tmp, () => {});
  }
}

async function imprimirPedido(pedido) {
  const printer = crearPrinter();
  const conectada = await verificarConectada(printer);
  if (!conectada) {
    throw new Error(`No se pudo conectar a la impresora (${config.get('impresoraInterfaz')})`);
  }

  armarComanda(printer, pedido);
  await enviarAlaImpresora(printer);
}

async function abrirCajon() {
  const printer = crearPrinter();
  const conectada = await verificarConectada(printer);
  if (!conectada) {
    throw new Error(`No se pudo conectar a la impresora (${config.get('impresoraInterfaz')})`);
  }

  // Pulso al cajón: ESC p m t1 t2 (m = pin del conector RJ11, t1/t2 = tiempos
  // de encendido/apagado en unidades de 2 ms). Se manda a los dos pines (2 y 5)
  // porque según el cable/cajón puede estar conectado a cualquiera de los dos,
  // con un pulso de 100 ms que es lo que piden cajones como el Gadnic RUHF65.
  // No se usa printer.openCashDrawer() porque la librería manda ESC p sin
  // los tiempos, y la impresora los toma de los bytes que siguen (queda un
  // pulso demasiado corto y el segundo pin nunca recibe nada).
  printer.add(Buffer.from([0x1b, 0x70, 0x00, 0x32, 0xfa]));
  printer.add(Buffer.from([0x1b, 0x70, 0x01, 0x32, 0xfa]));
  await enviarAlaImpresora(printer);
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

  log('imprimiendo',
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
  const printer = crearPrinter();
  const conectada = await verificarConectada(printer);
  if (!conectada) throw new Error('No se pudo conectar a la impresora');
  armarPrueba(printer);
  await enviarAlaImpresora(printer);
}

module.exports = { iniciar, detener, imprimirDePrueba };
