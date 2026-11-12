import { appBase, apiFetch } from './api.js';
import { cerrarSesion as finalizarSesion, construirMenu } from './sesion.js?v=nav-practicas-inicio-20261111-7';
import { durantePeticion, crearInsigniaEstado, validarFormularioEspanol } from './ui.js?v=validacion-es-20261111-3';

// Estado y operaciones compartidas de las pantallas.

export const estado = { usuario: null, pagina: 1, catalogos: [], passwordMinima: 10, cvMaximo: 0 };
export const vistaPublica = {};
export const $ = (selector, raiz = document) => raiz.querySelector(selector);
export const esc = (valor) => String(valor ?? '');
export const valorVerdadero = (valor) =>
  valor === true || valor === 1 || valor === '1' || valor === 'true';
export const fechaLocal = (fecha) =>
  [
    fecha.getFullYear(),
    String(fecha.getMonth() + 1).padStart(2, '0'),
    String(fecha.getDate()).padStart(2, '0'),
  ].join('-');
export function configurarCampoNombre(input) {
  input.addEventListener('input', () => {
    const inicio = input.selectionStart;
    const limpio = input.value.replace(/[^\p{L}\p{M} ]/gu, '');
    if (limpio !== input.value) {
      const diferencia = input.value.length - limpio.length;
      input.value = limpio;
      input.setSelectionRange(Math.max(0, inicio - diferencia), Math.max(0, inicio - diferencia));
    }
  });
  input.addEventListener('blur', () => {
    input.value = input.value
      .trim()
      .replace(/\s+/g, ' ')
      .toLocaleLowerCase('es-GT')
      .replace(/(^| )([\p{L}\p{M}])/gu, (_coincidencia, espacio, letra) =>
        espacio + letra.toLocaleUpperCase('es-GT'),
      );
  });
}
export function configurarCampoCarnet(input) {
  input.addEventListener('input', () => {
    const inicio = input.selectionStart;
    const limpio = input.value.replace(/[^0-9-]/g, '');
    if (limpio !== input.value) {
      const diferencia = input.value.length - limpio.length;
      input.value = limpio;
      input.setSelectionRange(Math.max(0, inicio - diferencia), Math.max(0, inicio - diferencia));
    }
  });
}
let temporizadorMensaje;
let recargar = async () => {};
export function configurarActualizacion(accion) {
  recargar = accion;
}
export async function actualizarPantalla() {
  if (document.body.dataset.module) await recargar();
}
export function mostrar(mensaje, error = false) {
  const el = $('#mensaje');
  if (!el) return;
  clearTimeout(temporizadorMensaje);
  el.textContent = mensaje;
  el.hidden = false;
  el.classList.toggle('error', error);
  el.classList.toggle('exito', !error);
  temporizadorMensaje = setTimeout(() => {
    el.hidden = true;
  }, 5000);
}

export async function peticion(ruta, opciones = {}) {
  const resultado = await apiFetch(opciones.method || 'GET', ruta, opciones.body ?? null, {
    headers: opciones.headers,
    timeoutMs: opciones.timeoutMs,
    responseType: opciones.responseType,
    silenciarAlertas: true,
  });
  if (!resultado.ok) {
    const error = new Error(resultado.mensaje || 'No se pudo completar la solicitud.');
    error.errores = resultado.errores || {};
    error.estado = resultado.estado;
    throw error;
  }
  return resultado.datos instanceof Blob ? resultado.datos : resultado;
}

export function formulario(form) {
  return Object.fromEntries(new FormData(form).entries());
}

export function limpiarErroresFormulario(form) {
  form.querySelectorAll('.error-campo, .error-formulario').forEach((n) => n.remove());
  form.querySelectorAll('[aria-invalid="true"]').forEach((n) => {
    n.removeAttribute('aria-invalid');
    const descripcion = n.getAttribute('aria-describedby');
    if (descripcion?.startsWith('error-')) n.removeAttribute('aria-describedby');
  });
  form.querySelectorAll('.campo-invalido').forEach((n) => n.classList.remove('campo-invalido'));
}

export function llenarCatalogos() {
  document.querySelectorAll('[data-catalogo]').forEach((sel) => {
    const tipo = sel.dataset.catalogo;
    for (const x of estado.catalogos.filter((x) => x.tipo === tipo)) {
      const op = document.createElement('option');
      op.value = x.id;
      op.textContent = x.nombre;
      sel.append(op);
    }
  });
}

export function ponerSesion() {
  const nav = $('#nav-principal');
  if (nav) {
    construirMenu(nav, estado.usuario);
  }
  const s = $('#estado-sesion');
  if (!s) return;
  s.hidden = !estado.usuario;
  s.replaceChildren();
  if (estado.usuario) {
    const perfil = document.createElement('a');
    perfil.href = `${appBase}/pages/perfil.php`;
    perfil.textContent = `${estado.usuario.nombres} ${estado.usuario.apellidos} · ${estado.usuario.rol_nombre}`;
    s.append(perfil);
    const b = crearElemento('button', 'Cerrar sesión', 'boton secundario');
    b.onclick = () => finalizarSesion();
    s.append(' ', b);
  } else s.textContent = 'Sesión no iniciada';
}

export async function cargarCatalogos() {
  const [r, sistema] = await Promise.all([peticion('catalogos'), peticion('sistema')]);
  estado.catalogos = r.datos;
  estado.passwordMinima = Math.max(8, Number(sistema.datos.password_longitud_minima || 10));
  estado.cvMaximo = Number(sistema.datos.cv_tamano_maximo_bytes || 0);
  llenarCatalogos();
  document
    .querySelectorAll('input[name="contrasena"]')
    .forEach((i) => (i.minLength = estado.passwordMinima));
  if (sistema.datos.nombre_sistema) {
    $('#nombre-sistema').textContent = sistema.datos.nombre_sistema;
    document.title = sistema.datos.nombre_sistema;
  }
}

export { appBase };

// Crea nodos de texto sin interpretar HTML del usuario.
export function crearElemento(etiqueta, texto = '', clase = '') {
  const elemento = document.createElement(etiqueta);
  elemento.textContent = texto;
  if (clase) elemento.className = clase;
  return elemento;
}

// Funciones de la pantalla: componentes.

export function boton(etiqueta, accion, contenedor) {
  const b = document.createElement('button');
  b.type = 'button';
  b.className = 'boton secundario';
  b.textContent = etiqueta;
  b.onclick = (evento) =>
    durantePeticion(b, () => accion(evento), 'Procesando…').catch((error) =>
      mostrar(error.message || 'No se pudo completar la acción.', true),
    );
  contenedor.append(b);
  return b;
}

export function encabezado(root, titulo) {
  const h = crearElemento('h3', titulo);
  root.append(h);
}

export function tarjeta(root, titulo, detalle) {
  const a = document.createElement('article');
  a.className = 'tarjeta';
  const h = crearElemento('h4', titulo);
  a.append(h);
  for (const texto of detalle) {
    const p = crearElemento('p', esc(texto));
    a.append(p);
  }
  root.append(a);
  return a;
}

export function etiquetaEstado(contenedor, texto, entidad, codigo) {
  const color = estado.catalogos.find((x) => x.tipo === entidad && x.codigo === codigo)?.color;
  const badge = crearInsigniaEstado(texto, color);
  if (
    /^[a-zA-Z0-9_-]+$/.test(String(entidad || '')) &&
    /^[a-zA-Z0-9_-]+$/.test(String(codigo || ''))
  )
    badge.classList.add(`estado-${entidad}-${codigo}`);
  contenedor.append(badge);
}

export function tabla(root, titulo, filas, campos) {
  const h = crearElemento('h3', titulo);
  root.append(h);
  if (!Array.isArray(filas)) filas = [];
  const wrap = document.createElement('div');
  wrap.className = 'tabla-contenedor';
  const table = document.createElement('table');
  const thead = document.createElement('thead');
  const tr = document.createElement('tr');
  for (const f of campos) {
    const th = crearElemento('th', f.replaceAll('_', ' '));
    tr.append(th);
  }
  thead.append(tr);
  table.append(thead);
  const tbody = document.createElement('tbody');
  if (!filas.length) {
    const row = document.createElement('tr');
    const cell = document.createElement('td');
    cell.colSpan = Math.max(1, campos.length);
    cell.textContent = 'No hay registros.';
    row.append(cell);
    tbody.append(row);
  }
  for (const fila of filas) {
    const row = document.createElement('tr');
    for (const f of campos) {
      const td = crearElemento('td', esc(fila[f]));
      row.append(td);
    }
    tbody.append(row);
  }
  table.append(tbody);
  wrap.append(table);
  root.append(wrap);
}

// Funciones de la pantalla: transiciones.

export async function transiciones(entidad, codigo) {
  const q = new URLSearchParams({
    entidad,
    estado_codigo: codigo,
  });
  return (await peticion(`catalogos/transiciones?${q}`)).datos;
}

export async function elegirTransicion(entidad, codigo) {
  const opciones = await transiciones(entidad, codigo);
  if (!opciones.length) {
    mostrar('No hay transiciones permitidas para esta cuenta y estado.', true);
    return null;
  }
  return new Promise((resolve) => {
    const dialog = document.createElement('dialog');
    dialog.className = 'dialogo dialogo-usuario dialogo-cambio-estado';
    const form = document.createElement('form');
    form.noValidate = true;
    form.className = 'detalle-usuario detalle-usuario--edicion';
    const cabecera = document.createElement('div');
    cabecera.className = 'detalle-usuario__encabezado';
    const identidad = document.createElement('div');
    identidad.className = 'detalle-usuario__identidad';
    identidad.append(
      crearElemento('h2', 'Cambiar estado'),
      crearElemento('p', 'Selecciona el estado que deseas asignar.'),
    );
    cabecera.append(identidad);
    form.append(cabecera);
    const campos = document.createElement('div');
    campos.className = 'detalle-usuario__formulario';
    form.append(campos);
    const stateLabel = crearElemento('label', 'Nuevo estado');
    const select = document.createElement('select');
    select.required = true;
    for (const option of opciones) {
      const item = document.createElement('option');
      item.value = option.estado_codigo;
      item.textContent = option.estado_nombre;
      select.append(item);
    }
    stateLabel.append(select);
    campos.append(stateLabel);
    const commentLabel = crearElemento('label', 'Comentario');
    const comment = document.createElement('textarea');
    comment.name = 'comentario';
    comment.placeholder = 'Escribe un comentario';
    comment.rows = 4;
    comment.maxLength = 2000;
    commentLabel.append(comment);
    campos.append(commentLabel);
    const actions = document.createElement('div');
    actions.className = 'detalle-usuario__acciones';
    const cancel = document.createElement('button');
    cancel.type = 'button';
    cancel.className = 'boton secundario';
    cancel.textContent = 'Cancelar';
    cancel.onclick = () => dialog.close();
    const save = document.createElement('button');
    save.type = 'submit';
    save.className = 'boton primario';
    save.textContent = 'Guardar cambio';
    actions.append(cancel, save);
    form.append(actions);
    dialog.append(form);
    const actualizarComentario = () => {
      const destino = opciones.find((item) => item.estado_codigo === select.value);
      const requerido = valorVerdadero(destino?.requiere_comentario);
      commentLabel.hidden = !requerido;
      comment.required = requerido;
    };
    select.addEventListener('change', actualizarComentario);
    actualizarComentario();
    let seleccion = null;
    dialog.addEventListener(
      'close',
      () => {
        dialog.remove();
        resolve(seleccion);
      },
      { once: true },
    );
    form.addEventListener('submit', (event) => {
      event.preventDefault();
      const destino = opciones.find((item) => item.estado_codigo === select.value);
      if (!destino) return;
      seleccion = { destino, comentario: comment.value.trim() };
      dialog.close();
    });
    document.body.append(dialog);
    dialog.showModal();
  });
}

export function pedirComentario(transicion) {
  return new Promise((resolve) => {
    const dialog = document.createElement('dialog');
    dialog.className = 'dialogo';
    const form = document.createElement('form');
    form.noValidate = true;
    form.className = 'formulario detalle-practica';
    const title = crearElemento('h2', transicion.estado_nombre);
    form.append(title);
    const requerido = valorVerdadero(transicion.requiere_comentario);
    const label = crearElemento(
      'label',
      requerido ? 'Comentario obligatorio' : 'Comentario (opcional)',
    );
    const comment = document.createElement('textarea');
    comment.name = 'comentario';
    comment.placeholder = 'Escribe un comentario';
    comment.rows = 5;
    comment.maxLength = 2000;
    comment.required = requerido;
    label.append(comment);
    form.append(label);
    const actions = document.createElement('div');
    actions.className = 'acciones';
    const cancel = document.createElement('button');
    cancel.type = 'button';
    cancel.className = 'boton secundario';
    cancel.textContent = 'Cancelar';
    cancel.onclick = () => dialog.close();
    const submit = document.createElement('button');
    submit.type = 'submit';
    submit.className = 'boton primario';
    submit.textContent = 'Confirmar respuesta';
    actions.append(cancel, submit);
    form.append(actions);
    dialog.append(form);
    let value = null;
    form.addEventListener('submit', (event) => {
      event.preventDefault();
      if (!validarFormularioEspanol(form)) return;
      value = comment.value.trim();
      dialog.close();
    });
    dialog.addEventListener(
      'close',
      () => {
        dialog.remove();
        resolve(value);
      },
      { once: true },
    );
    document.body.append(dialog);
    dialog.showModal();
    comment.focus();
  });
}
