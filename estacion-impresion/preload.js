const { contextBridge, ipcRenderer } = require('electron');

contextBridge.exposeInMainWorld('estacionAPI', {
  getConfig: () => ipcRenderer.invoke('config:get'),
  setEstacion: (datos) => ipcRenderer.invoke('config:set-estacion', datos),
  setImpresora: (datos) => ipcRenderer.invoke('config:set-impresora', datos),
  desconfigurar: () => ipcRenderer.invoke('config:desconfigurar'),
  probarImpresora: () => ipcRenderer.invoke('impresora:prueba'),
  onEvento: (callback) => ipcRenderer.on('evento-cola', (_e, evento) => callback(evento)),
  onConfigActual: (callback) => ipcRenderer.on('config-actual', (_e, cfg) => callback(cfg)),
});
