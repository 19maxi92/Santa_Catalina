const { armarComanda } = require('./src/ticket');

// Mock de printer que solo loguea lo que se llamaría en la impresora real
const mock = {
  alignCenter: () => console.log('[centrar]'),
  alignLeft: () => console.log('[izquierda]'),
  bold: (v) => console.log('[negrita:' + v + ']'),
  setTextDoubleHeight: () => console.log('[doble alto]'),
  setTextNormal: () => console.log('[normal]'),
  println: (t) => console.log('  ' + t),
  drawLine: () => console.log('----------------------------------------'),
  newLine: () => console.log(''),
  cut: () => console.log('[CORTE]'),
};

const pedidoPersonalizado = {
  id: 501,
  ubicacion: 'Local 1',
  nombre: 'Maxi',
  apellido: 'Burgos',
  cliente_fijo_nombre: null,
  producto: 'Personalizado x48 (6 planchas)',
  precio: 25000,
  modalidad: 'Retiro',
  forma_pago: 'Efectivo',
  observaciones: `Turno: Tarde
=== SABORES PERSONALIZADOS ===
• Jamón y Queso: 3 plancha(s) (24 sándwiches)
• Ananá: 2 plancha(s) (16 sándwiches)
• Atún: 1 plancha(s) (8 sándwiches)

--- Info del Sistema ---
Pedido Express - Empleado ID: 4
Fecha/Hora: 2026-09-11 14:00`,
};

const pedidoNormal = {
  id: 502,
  ubicacion: 'Fábrica',
  nombre: 'Ana',
  apellido: 'Martínez',
  producto: '48 Jamón y Queso',
  precio: 28000,
  modalidad: 'Delivery',
  forma_pago: 'Transferencia',
  observaciones: 'Turno: M\nSin cebolla por favor',
};

console.log('=== COMANDA PERSONALIZADA ===');
armarComanda(mock, pedidoPersonalizado);

console.log('\n=== COMANDA NORMAL ===');
armarComanda(mock, pedidoNormal);
