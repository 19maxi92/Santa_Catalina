const { app, BrowserWindow, Tray, Menu, ipcMain, nativeImage } = require('electron');
const path = require('path');
const config = require('./src/config');
const printQueue = require('./src/printQueue');

let ventana = null;
let bandeja = null;
let ultimosEventos = [];
const MAX_EVENTOS = 30;

function crearVentana() {
  ventana = new BrowserWindow({
    width: 480,
    height: 640,
    resizable: false,
    icon: path.join(__dirname, 'assets', 'icon.png'),
    webPreferences: {
      preload: path.join(__dirname, 'preload.js'),
      contextIsolation: true,
      nodeIntegration: false,
    },
  });

  ventana.loadFile(path.join(__dirname, 'renderer', 'index.html'));
  ventana.setMenu(null);

  // No se cierra de verdad al tocar la X: se minimiza a la bandeja para que
  // nadie la cierre "sin querer" pensando que es un programa cualquiera.
  ventana.on('close', (e) => {
    if (!app.isQuitting) {
      e.preventDefault();
      ventana.hide();
    }
  });

  ventana.webContents.on('did-finish-load', () => {
    enviarEstadoActual();
  });
}

function crearBandeja() {
  const icono = nativeImage.createFromPath(path.join(__dirname, 'assets', 'icon.png'));
  bandeja = new Tray(icono.isEmpty() ? nativeImage.createEmpty() : icono);
  bandeja.setToolTip('Santa Catalina - Estación de Impresión');

  const menu = Menu.buildFromTemplate([
    { label: 'Mostrar', click: () => ventana.show() },
    { type: 'separator' },
    {
      label: 'Salir',
      click: () => {
        app.isQuitting = true;
        app.quit();
      },
    },
  ]);
  bandeja.setContextMenu(menu);
  bandeja.on('click', () => ventana.show());
}

function registrarEvento(evento) {
  ultimosEventos.unshift(evento);
  if (ultimosEventos.length > MAX_EVENTOS) ultimosEventos.pop();
  if (ventana && !ventana.isDestroyed()) {
    ventana.webContents.send('evento-cola', evento);
  }
}

function enviarEstadoActual() {
  if (!ventana || ventana.isDestroyed()) return;
  ventana.webContents.send('config-actual', config.getAll());
  ultimosEventos.slice().reverse().forEach((ev) => ventana.webContents.send('evento-cola', ev));
}

// ── IPC: lo que la ventana le puede pedir al proceso principal ──────────────

ipcMain.handle('config:get', () => config.getAll());

ipcMain.handle('config:set-estacion', (_e, { servidorUrl, token, nombre, ubicacion }) => {
  config.set('servidorUrl', servidorUrl);
  config.set('token', token);
  config.set('nombre', nombre);
  config.set('ubicacion', ubicacion);
  printQueue.detener();
  printQueue.iniciar(registrarEvento);
  return config.getAll();
});

ipcMain.handle('config:set-impresora', (_e, { impresoraInterfaz }) => {
  config.set('impresoraInterfaz', impresoraInterfaz);
  return config.getAll();
});

ipcMain.handle('config:desconfigurar', () => {
  printQueue.detener();
  config.limpiar();
  return config.getAll();
});

ipcMain.handle('impresora:prueba', async () => {
  try {
    await printQueue.imprimirDePrueba();
    return { success: true };
  } catch (e) {
    return { success: false, error: e.message };
  }
});

// ── Arranque ────────────────────────────────────────────────────────────────

app.whenReady().then(() => {
  crearVentana();
  crearBandeja();

  // Autoarranque con Windows (minimizada, no molesta al abrir la PC)
  app.setLoginItemSettings({ openAtLogin: true, openAsHidden: true });

  if (config.estaConfigurada()) {
    printQueue.iniciar(registrarEvento);
  }
});

app.on('window-all-closed', () => {
  // No cerrar la app al cerrar la ventana: sigue viva en la bandeja.
});
