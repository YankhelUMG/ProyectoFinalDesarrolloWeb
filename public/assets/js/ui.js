// Componentes pequeños de interfaz; todo el contenido externo se pinta como texto.
const colorHex = /^#[0-9a-f]{6}$/i;

function limpiarId(valor) {
  return String(valor).replace(/[^a-zA-Z0-9_-]/g, '-');
}

export function mostrarAlerta(mensaje, tipo = 'info', duracion = 5000, raiz = document) {
  let region = raiz.querySelector('[data-alertas]');
  if (!region) {
    region = document.createElement('div');
    region.dataset.alertas = '';
    region.className = 'alertas';
    region.setAttribute('aria-live', tipo === 'error' ? 'assertive' : 'polite');
    region.setAttribute('aria-atomic', 'true');
    (raiz.body || raiz).prepend(region);
  }
  const alerta = document.createElement('p');
  alerta.className = `alerta alerta--${tipo}`;
  alerta.setAttribute('role', tipo === 'error' ? 'alert' : 'status');
  alerta.textContent = String(mensaje || 'Solicitud completada.');
  region.replaceChildren(alerta);
  if (duracion > 0) setTimeout(() => alerta.remove(), duracion);
  return alerta;
}

export function crearSpinner(mensaje = 'Cargando…') {
  const bloque = document.createElement('span');
  bloque.className = 'estado-carga';
  bloque.setAttribute('role', 'status');
  const icono = document.createElement('span');
  icono.className = 'spinner';
  icono.setAttribute('aria-hidden', 'true');
  const texto = document.createElement('span');
  texto.textContent = mensaje;
  bloque.append(icono, texto);
  return bloque;
}

export function mostrarCarga(contenedor, mensaje = 'Cargando…') {
  if (!contenedor) return () => {};
  const anterior = document.createDocumentFragment();
  while (contenedor.firstChild) anterior.append(contenedor.firstChild);
  contenedor.setAttribute('aria-busy', 'true');
  contenedor.append(crearSpinner(mensaje));
  return () => {
    contenedor.removeAttribute('aria-busy');
    contenedor.replaceChildren(anterior);
  };
}

export async function durantePeticion(boton, accion, textoCarga = 'Procesando…') {
  if (!boton || typeof accion !== 'function') return accion?.();
  const textoOriginal = boton.textContent;
  const estabaDeshabilitado = boton.disabled;
  boton.disabled = true;
  boton.setAttribute('aria-busy', 'true');
  boton.textContent = textoCarga;
  try {
    return await accion();
  } finally {
    boton.disabled = estabaDeshabilitado;
    boton.removeAttribute('aria-busy');
    if (boton.textContent === textoCarga) boton.textContent = textoOriginal;
  }
}

export function limpiarErroresCampos(formulario) {
  if (!formulario) return;
  formulario.querySelectorAll('[data-error-campo]').forEach((nodo) => nodo.remove());
  formulario.querySelectorAll('[aria-invalid="true"]').forEach((campo) => {
    campo.removeAttribute('aria-invalid');
    const ids = (campo.getAttribute('aria-describedby') || '').split(/\s+/).filter((id) => !id.startsWith('error-campo-'));
    if (ids.length) campo.setAttribute('aria-describedby', ids.join(' '));
    else campo.removeAttribute('aria-describedby');
  });
}

export function pintarErroresCampos(formulario, errores = {}) {
  if (!formulario || !errores || typeof errores !== 'object') return null;
  limpiarErroresCampos(formulario);
  let primerCampo = null;
  for (const [nombre, mensaje] of Object.entries(errores)) {
    const campo = formulario.elements.namedItem(nombre);
    const control = campo instanceof RadioNodeList ? campo[0] : campo;
    if (!control || typeof control.setAttribute !== 'function') continue;
    const id = `error-campo-${limpiarId(formulario.id || 'formulario')}-${limpiarId(nombre)}`;
    const nota = document.createElement('small');
    nota.id = id;
    nota.dataset.errorCampo = '';
    nota.className = 'error-campo';
    nota.textContent = String(mensaje);
    control.setAttribute('aria-invalid', 'true');
    const actuales = (control.getAttribute('aria-describedby') || '').split(/\s+/).filter(Boolean);
    control.setAttribute('aria-describedby', [...actuales, id].join(' '));
    const etiqueta = control.closest('label');
    (etiqueta || control.parentElement || formulario).append(nota);
    primerCampo ||= control;
  }
  primerCampo?.focus();
  return primerCampo;
}

function crearDialogo(titulo, mensaje, opciones = {}) {
  const dialogo = document.createElement('dialog');
  dialogo.className = 'dialogo-base';
  const contenido = document.createElement('form');
  contenido.method = 'dialog';
  contenido.className = 'dialogo-base__contenido';
  const encabezado = document.createElement('h2');
  encabezado.textContent = titulo;
  const texto = document.createElement('p');
  texto.textContent = mensaje;
  contenido.append(encabezado, texto);
  let comentario;
  if (opciones.comentario) {
    const etiqueta = document.createElement('label');
    etiqueta.textContent = opciones.etiquetaComentario || 'Comentario';
    comentario = document.createElement('textarea');
    comentario.name = 'comentario';
    comentario.required = true;
    comentario.rows = 4;
    comentario.maxLength = opciones.maxLength || 2000;
    etiqueta.append(comentario);
    contenido.append(etiqueta);
  }
  const acciones = document.createElement('div');
  acciones.className = 'dialogo-base__acciones';
  const cancelar = document.createElement('button');
  cancelar.type = 'button';
  cancelar.className = 'boton secundario';
  cancelar.textContent = opciones.textoCancelar || 'Cancelar';
  cancelar.value = 'cancelar';
  const confirmar = document.createElement('button');
  confirmar.type = 'submit';
  confirmar.className = 'boton primario';
  confirmar.textContent = opciones.textoConfirmar || 'Confirmar';
  confirmar.value = 'confirmar';
  acciones.append(cancelar, confirmar);
  contenido.append(acciones);
  dialogo.append(contenido);
  document.body.append(dialogo);
  return { dialogo, contenido, cancelar, confirmar, comentario };
}

function mantenerFoco(dialogo, evento) {
  if (evento.key !== 'Tab') return;
  const controles = [...dialogo.querySelectorAll('button:not(:disabled), textarea:not(:disabled), input:not(:disabled)')];
  if (!controles.length) return;
  const primero = controles[0];
  const ultimo = controles.at(-1);
  if (evento.shiftKey && document.activeElement === primero) {
    evento.preventDefault();
    ultimo.focus();
  } else if (!evento.shiftKey && document.activeElement === ultimo) {
    evento.preventDefault();
    primero.focus();
  }
}

export function confirmarAccion(mensaje, opciones = {}) {
  return new Promise((resolver) => {
    const partes = crearDialogo(opciones.titulo || 'Confirmar acción', mensaje, opciones);
    const cerrar = (resultado) => {
      partes.dialogo.removeEventListener('keydown', atraparTab);
      partes.dialogo.close();
      partes.dialogo.remove();
      resolver(resultado);
    };
    const atraparTab = (evento) => mantenerFoco(partes.dialogo, evento);
    partes.cancelar.addEventListener('click', () => cerrar({ confirmado: false }));
    partes.contenido.addEventListener('submit', (evento) => {
      evento.preventDefault();
      cerrar({ confirmado: true, comentario: partes.comentario?.value.trim() || '' });
    });
    partes.dialogo.addEventListener('cancel', (evento) => {
      evento.preventDefault();
      cerrar({ confirmado: false });
    });
    partes.dialogo.addEventListener('keydown', atraparTab);
    partes.dialogo.addEventListener('click', (evento) => {
      if (evento.target === partes.dialogo) cerrar({ confirmado: false });
    });
    partes.dialogo.addEventListener('close', () => partes.dialogo.remove(), { once: true });
    partes.dialogo.showModal();
    (partes.comentario || partes.cancelar).focus();
  });
}

export function confirmarConComentario(mensaje, opciones = {}) {
  return confirmarAccion(mensaje, { ...opciones, comentario: true });
}

/** Renderiza tablas con texto y paginación, sin interpretar datos como HTML. */
export function pintarTablaPaginada(contenedor, opciones = {}) {
  const columnas = opciones.columnas || [];
  const filas = opciones.filas || [];
  const pagina = Math.max(1, Number(opciones.pagina || 1));
  const porPagina = Math.max(1, Number(opciones.porPagina || filas.length || 1));
  const tieneTotal = opciones.total !== undefined && Number.isFinite(Number(opciones.total));
  const total = tieneTotal ? Number(opciones.total) : filas.length;
  const tabla = document.createElement('table');
  tabla.className = 'tabla-paginada';
  const encabezado = document.createElement('thead');
  const filaTitulo = document.createElement('tr');
  columnas.forEach((columna) => {
    const th = document.createElement('th');
    th.scope = 'col';
    th.textContent = String(columna.titulo || '');
    filaTitulo.append(th);
  });
  encabezado.append(filaTitulo);
  const cuerpo = document.createElement('tbody');
  filas.forEach((dato) => {
    const fila = document.createElement('tr');
    columnas.forEach((columna) => {
      const celda = document.createElement('td');
      const valor = typeof columna.valor === 'function' ? columna.valor(dato) : dato?.[columna.clave];
      if (valor instanceof Node) celda.append(valor);
      else celda.textContent = valor === null || valor === undefined ? '—' : String(valor);
      fila.append(celda);
    });
    cuerpo.append(fila);
  });
  if (!filas.length) {
    const fila = document.createElement('tr');
    const celda = document.createElement('td');
    celda.colSpan = Math.max(1, columnas.length);
    celda.textContent = opciones.vacio || 'No hay registros.';
    fila.append(celda);
    cuerpo.append(fila);
  }
  tabla.append(encabezado, cuerpo);
  const navegacion = document.createElement('nav');
  navegacion.className = 'paginacion';
  navegacion.setAttribute('aria-label', 'Paginación de resultados');
  const totalPaginas = Math.max(1, Math.ceil(total / porPagina));
  const anterior = document.createElement('button');
  anterior.type = 'button';
  anterior.className = 'boton secundario';
  anterior.textContent = 'Anterior';
  anterior.disabled = pagina <= 1;
  anterior.addEventListener('click', () => opciones.alCambiarPagina?.(pagina - 1));
  const estado = document.createElement('span');
  estado.textContent = tieneTotal ? `Página ${pagina} de ${totalPaginas}` : `Página ${pagina}`;
  const siguiente = document.createElement('button');
  siguiente.type = 'button';
  siguiente.className = 'boton secundario';
  siguiente.textContent = 'Siguiente';
  siguiente.disabled = typeof opciones.haySiguiente === 'boolean' ? !opciones.haySiguiente : pagina >= totalPaginas;
  siguiente.addEventListener('click', () => opciones.alCambiarPagina?.(pagina + 1));
  navegacion.append(anterior, estado, siguiente);
  contenedor.replaceChildren(tabla, navegacion);
}

export function crearInsigniaEstado(nombre, color) {
  const insignia = document.createElement('span');
  insignia.className = 'insignia-estado';
  insignia.textContent = String(nombre || 'Sin estado');
  if (colorHex.test(String(color || ''))) insignia.style.setProperty('--color-estado', color);
  return insignia;
}

document.addEventListener('api:alerta', (evento) => {
  const { mensaje, tipo } = evento.detail || {};
  mostrarAlerta(mensaje, tipo || 'error');
});
