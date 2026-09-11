// Configuración persistida de esta estación (sobrevive a reinicios de la PC).
const Store = require('electron-store');

const store = new Store({
  name: 'estacion-config',
  defaults: {
    servidorUrl: 'https://santacatalina.online',
    token: '',
    nombre: '',
    ubicacion: '',
    // 'auto' = impresora predeterminada de Windows. También puede ser
    // una IP de red, ej: "tcp://192.168.1.41" para una impresora en red.
    impresoraInterfaz: 'auto',
  },
});

module.exports = {
  get(key) {
    return store.get(key);
  },
  set(key, value) {
    store.set(key, value);
  },
  getAll() {
    return store.store;
  },
  estaConfigurada() {
    const c = store.store;
    return !!(c.token && c.ubicacion);
  },
  limpiar() {
    store.set('token', '');
    store.set('nombre', '');
    store.set('ubicacion', '');
  },
};
