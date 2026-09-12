const pantallaConfig = document.getElementById('pantalla-config');
const pantallaEstado = document.getElementById('pantalla-estado');

let ubicacionElegida = '';

document.querySelectorAll('#botones-ubicacion button').forEach((btn) => {
  btn.addEventListener('click', () => {
    document.querySelectorAll('#botones-ubicacion button').forEach((b) => b.classList.remove('activo'));
    btn.classList.add('activo');
    ubicacionElegida = btn.dataset.ubicacion;
  });
});

document.getElementById('btn-guardar-config').addEventListener('click', async () => {
  const servidorUrl = document.getElementById('input-servidor').value.trim();
  const nombre = document.getElementById('input-nombre').value.trim();
  const token = document.getElementById('input-token').value.trim();
  const errorEl = document.getElementById('error-config');
  errorEl.textContent = '';

  if (!servidorUrl || !nombre || !token || !ubicacionElegida) {
    errorEl.textContent = 'Completá todos los campos y elegí una sucursal.';
    return;
  }

  await window.estacionAPI.setEstacion({ servidorUrl, token, nombre, ubicacion: ubicacionElegida });
  mostrarPantallaEstado({ ubicacion: ubicacionElegida });
});

document.getElementById('btn-guardar-impresora').addEventListener('click', async () => {
  const impresoraInterfaz = document.getElementById('input-impresora').value.trim();
  await window.estacionAPI.setImpresora({ impresoraInterfaz });
});

document.getElementById('btn-prueba').addEventListener('click', async () => {
  const resultado = document.getElementById('resultado-prueba');
  resultado.textContent = 'Imprimiendo...';
  resultado.style.color = '#888';
  const r = await window.estacionAPI.probarImpresora();
  if (r.success) {
    resultado.textContent = '✅ Se envió correctamente';
    resultado.style.color = '#1a9d5c';
  } else {
    resultado.textContent = '❌ ' + r.error;
    resultado.style.color = '#c0392b';
  }
});

document.getElementById('btn-reconfigurar').addEventListener('click', async () => {
  if (confirm('¿Reconfigurar esta PC? Vas a necesitar el token de nuevo.')) {
    await window.estacionAPI.desconfigurar();
    mostrarPantallaConfig();
  }
});

function mostrarPantallaConfig() {
  pantallaConfig.hidden = false;
  pantallaEstado.hidden = true;
}

function mostrarPantallaEstado(cfg) {
  pantallaConfig.hidden = true;
  pantallaEstado.hidden = false;
  document.getElementById('estado-ubicacion').textContent = cfg.ubicacion || '—';
  document.getElementById('input-impresora').value = cfg.impresoraInterfaz && cfg.impresoraInterfaz !== 'auto' ? cfg.impresoraInterfaz : '';
}

function agregarEvento(evento) {
  const badge = document.getElementById('badge-conexion');
  if (evento.tipo === 'error') {
    badge.textContent = '● con problemas';
    badge.className = 'badge mal';
  } else {
    badge.textContent = '● conectado';
    badge.className = 'badge ok';
  }

  const lista = document.getElementById('lista-eventos');
  const div = document.createElement('div');
  div.className = 'evento ' + evento.tipo;
  const hora = new Date(evento.ts).toLocaleTimeString('es-AR', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
  div.innerHTML = `<span class="hora">${hora}</span><span>${evento.mensaje}</span>`;
  lista.prepend(div);
  while (lista.children.length > 30) lista.removeChild(lista.lastChild);
}

window.estacionAPI.onEvento(agregarEvento);
window.estacionAPI.onConfigActual((cfg) => {
  if (cfg.token && cfg.ubicacion) {
    mostrarPantallaEstado(cfg);
  } else {
    mostrarPantallaConfig();
  }
});

// Estado inicial al abrir
window.estacionAPI.getConfig().then((cfg) => {
  if (cfg.token && cfg.ubicacion) {
    mostrarPantallaEstado(cfg);
  } else {
    mostrarPantallaConfig();
    document.getElementById('input-servidor').value = cfg.servidorUrl || 'https://santacatalina.online';
  }
});
