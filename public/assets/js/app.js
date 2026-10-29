// Pantallas autenticadas: comparten cliente API, sesión, menú y utilidades visuales.
import { apiFetch } from './api.js';
import { requerirSesion, cerrarSesion as finalizarSesion } from './sesion.js';
import { construirMenu } from './menu.js?v=20261028-4';
import { confirmarAccion, durantePeticion, crearInsigniaEstado, crearSpinner, limpiarErroresCampos, pintarErroresCampos } from './ui.js';

const appBaseMeta = document.querySelector('meta[name="app-base"]');
const appBase = appBaseMeta ? appBaseMeta.content : new URL('../../../', import.meta.url).pathname.replace(/\/$/, '');

const estado = {
 usuario: null, csrf: null, pagina: 1, catalogos: [], passwordMinima: 10, cvMaximo: 0
};
const vistaPublica = {};
let temporizadorMensaje;

const $ = (s, root = document) => root.querySelector(s);

const esc = value => String(value ?? '');
const valorVerdadero = value => value === true || value === 1 || value === '1' || value === 'true';
const fechaLocal = (fecha) => `${fecha.getFullYear()}-${String(fecha.getMonth() + 1).padStart(2, '0')}-${String(fecha.getDate()).padStart(2, '0')}`;

function mostrar(mensaje, error = false) {
 const el = $('#mensaje');
 if (!el) return;
 clearTimeout(temporizadorMensaje);
 el.textContent = mensaje;
 el.hidden = false;
 el.classList.toggle('error', error);
 el.classList.toggle('exito', !error);
 temporizadorMensaje = setTimeout(() => {
 el.hidden = true
  }, 5000)
}

// Envía solicitudes y agrega el token CSRF cuando hace falta.
async function peticion(ruta, opciones = {}) {
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

function formulario(form) {
 return Object.fromEntries(new FormData(form).entries())
}

function limpiarErroresFormulario(form) {
 form.querySelectorAll('.error-campo, .error-formulario').forEach(n => n.remove());
 form.querySelectorAll('[aria-invalid="true"]').forEach(n => {
 n.removeAttribute('aria-invalid');
 const descripcion = n.getAttribute('aria-describedby');
 if(descripcion?.startsWith('error-'))n.removeAttribute('aria-describedby')
  });
 form.querySelectorAll('.campo-invalido').forEach(n => n.classList.remove('campo-invalido'))
}

// Llena las listas con los catálogos del servidor.
function llenarCatalogos() {
 document.querySelectorAll('[data-catalogo]').forEach(sel => {
 const tipo = sel.dataset.catalogo;for(const x of estado.catalogos.filter(x => x.tipo === tipo)) {
 const op = document.createElement('option');op.value = x.id;op.textContent = x.nombre;sel.append(op)
    }
  })
}

// Muestra la navegación y el panel del usuario.
function ponerSesion() {
 const nav = $('#nav-principal');
 if(nav) {
 construirMenu(nav, estado.usuario);
 }
 const s = $('#estado-sesion');
 if(!s)return;
 s.hidden = !estado.usuario;
  s.replaceChildren();
 if(estado.usuario) {
  const perfil = document.createElement('a');
  perfil.href = `${appBase}/pages/perfil/index.php`;
  perfil.textContent = `${estado.usuario.nombres} ${estado.usuario.apellidos} · ${estado.usuario.rol_nombre}`;
  s.append(perfil);
 const b = document.createElement('button');
 b.className = 'boton secundario';
 b.textContent = 'Cerrar sesión';
 b.onclick = () => finalizarSesion();
 s.append(' ', b);
  } else s.textContent = 'Sesión no iniciada';
}

// Carga el token CSRF, los catálogos y la configuración pública.
async function cargarCatalogos() {
 const [csrf, r, sistema] = await Promise.all([peticion('csrf'), peticion('catalogos'), peticion('sistema')]);
 estado.csrf = csrf.datos.csrf_token;
 estado.catalogos = r.datos;
 estado.passwordMinima = Math.max(8, Number(sistema.datos.password_longitud_minima || 10));
 estado.cvMaximo = Number(sistema.datos.cv_tamano_maximo_bytes || 0);
 llenarCatalogos();
 document.querySelectorAll('input[name="contrasena"]').forEach(i => i.minLength = estado.passwordMinima);
 if(sistema.datos.nombre_sistema) {
 $("#nombre-sistema").textContent = sistema.datos.nombre_sistema;
 document.title = sistema.datos.nombre_sistema
  }
}

// Muestra las oportunidades y el estado de postulación.
function renderPracticas(items, postulaciones = new Map(), root = $('#lista-practicas')) {
 root.replaceChildren();
 if(!items.length) {
 const p = document.createElement('p');
 p.textContent = 'No hay oportunidades que coincidan con los filtros.';
 root.append(p);
 return
  }
 for(const x of items) {
 const card = document.createElement('article');
 card.className = 'tarjeta';
 const h = document.createElement('h3');
 h.textContent = x.titulo;
 const badge = document.createElement('span');
 badge.className = 'etiqueta';
 badge.textContent = x.area;
 const p = document.createElement('p');
 p.textContent = `${x.empresa} · ${x.modalidad} · ${x.ubicacion||'Ubicación por definir'}`;
 const d = document.createElement('p');
 d.textContent = x.descripcion;
 const f = document.createElement('p');
 f.textContent = `Inicio: ${x.fecha_inicio || 'Por definir'} · Fecha límite: ${x.fecha_limite_postulacion} · Cupos ocupados: ${x.cupos_ocupados}/${x.cupo_maximo}`;
 const b = document.createElement('button');
 b.className = 'boton primario';
 const postulacion = postulaciones.get(Number(x.id_practica));
 b.textContent = estado.usuario?.rol_codigo === 'estudiante' && !postulacion ? 'Ver y postularme' : 'Ver detalle';
 b.onclick = () => verDetalle(x.id_practica, postulacion);
 card.append(h, badge, p, d, f, b);
 etiquetaEstado(card, x.estado, 'practica', x.estado_codigo);
 root.append(card)
  }
}

async function cargarPracticas(vista = {}) {
 const lista = vista.lista || $('#lista-practicas');
 const filtros = vista.filtros || $('#filtros');
 const anterior = vista.anterior || $('#pagina-anterior');
 const paginaTexto = vista.pagina || $('#pagina-actual');
 const siguiente = vista.siguiente || $('#pagina-siguiente');
 const estadoCarga = vista.estado || $('#estado-carga');
 if(!lista || !filtros)return;
 const f = formulario(filtros);
 const desde = f.fecha_inicio_desde || '';
 const hasta = f.fecha_inicio_hasta || '';
 if(desde && hasta && desde > hasta) {
 const campoDesde = filtros.elements.namedItem('fecha_inicio_desde');
 const campoHasta = filtros.elements.namedItem('fecha_inicio_hasta');
 campoDesde?.setCustomValidity('La fecha inicial no puede ser posterior a la fecha final.');
 campoHasta?.setCustomValidity('La fecha final no puede ser anterior a la fecha inicial.');
 campoDesde?.reportValidity();
 campoDesde?.addEventListener('input', () => campoDesde.setCustomValidity(''), {once: true});
 campoHasta?.addEventListener('input', () => campoHasta.setCustomValidity(''), {once: true});
 if(estadoCarga)estadoCarga.textContent = 'Corrige el rango de fechas para buscar.';
 return
  }
 filtros.elements.namedItem('fecha_inicio_desde')?.setCustomValidity('');
 filtros.elements.namedItem('fecha_inicio_hasta')?.setCustomValidity('');
 const solicitud = vista.solicitud = (vista.solicitud || 0) + 1;
 const q = new URLSearchParams( {
 pagina: String(estado.pagina)
  });
 for(const k of ['q', 'area', 'modalidad', 'empresa', 'fecha_inicio_desde', 'fecha_inicio_hasta']) {
 const valor = String(f[k] || '').trim();
 if(valor)q.set(k, valor)
  }
 lista.replaceChildren();
 const cargando = document.createElement('p');cargando.textContent = 'Cargando prácticas…';lista.append(cargando);
 for(const boton of [filtros.querySelector('button[type="submit"]'), filtros.querySelector('button[type="reset"]'), anterior, siguiente])if(boton)boton.disabled = true;
 if(estadoCarga)estadoCarga.textContent = 'Cargando prácticas…';
 let avisoPostulaciones = false;
 try {
 const r = await peticion(`practicas?${q}`);
 if(solicitud !== vista.solicitud)return;
 let postulaciones = new Map();
 if(estado.usuario?.rol_codigo === 'estudiante' && !estado.usuario.debe_cambiar_password) {
 try {
 const own = await peticion('postulaciones');
 if(solicitud !== vista.solicitud)return;
 postulaciones = new Map(own.datos.map(x => [Number(x.id_practica), x.estado_codigo]));
 } catch {
 // El fallo al consultar postulaciones no debe ocultar los resultados del catálogo.
 avisoPostulaciones = true
  }
    }
 if(solicitud !== vista.solicitud)return;
 renderPracticas(r.datos.elementos, postulaciones, lista);
 if(paginaTexto)paginaTexto.textContent = `Página ${estado.pagina}`;
 if(anterior)anterior.disabled = estado.pagina <= 1;
 if(siguiente)siguiente.disabled = !r.datos.hay_siguiente;
 if(estadoCarga) {
  const cantidad = r.datos.elementos.length;
  estadoCarga.textContent = avisoPostulaciones
   ? 'Prácticas cargadas; no se pudo consultar el estado de tus postulaciones.'
   : `${cantidad} ${cantidad === 1 ? 'práctica' : 'prácticas'} en esta página.`;
 }
  }
 catch(e) {
 if(solicitud !== vista.solicitud)return;
 lista.replaceChildren();
 if(estadoCarga)estadoCarga.textContent = e.message || 'No se pudieron cargar las prácticas.';
 mostrar(e.message, true)
 } finally {
 if(solicitud === vista.solicitud) {
 for(const boton of [filtros.querySelector('button[type="submit"]'), filtros.querySelector('button[type="reset"]')])if(boton)boton.disabled = false;
 if(anterior)anterior.disabled = estado.pagina <= 1;
 if(siguiente && !siguiente.disabled)siguiente.disabled = false
  }
  }
}

// Muestra una oportunidad y permite postularse.
async function verDetalle(id, postulacion = null) {
 try {
 const r = await peticion(`practicas/${id}`);
 const x = r.datos;
 const dialog = document.createElement('dialog');
 dialog.className = 'dialogo';
 const article = document.createElement('article');
 article.className = 'detalle-practica';
 const title = document.createElement('h2');title.textContent = x.titulo;
 const company = document.createElement('p');company.className = 'texto-secundario';company.textContent = `${x.empresa} · ${x.area} · ${x.modalidad}`;
 const description = document.createElement('p');description.textContent = x.descripcion;
 const requirements = document.createElement('p');requirements.textContent = `Requisitos: ${x.requisitos || 'No especificados.'}`;
 const locationText = document.createElement('p');locationText.textContent = `Ubicación: ${x.ubicacion || 'Por definir'}`;
 const dates = document.createElement('p');dates.textContent = `Inicio: ${x.fecha_inicio || 'Por definir'} · Fecha límite: ${x.fecha_limite_postulacion}`;
 const capacity = document.createElement('p');capacity.textContent = `Cupos ocupados: ${x.cupos_ocupados}/${x.cupo_maximo}`;
 article.append(title, company, description, requirements, locationText, dates, capacity);
 const actions = document.createElement('div');actions.className = 'acciones';
 const close = document.createElement('button');close.type = 'button';close.className = 'boton secundario';close.textContent = 'Cerrar';close.onclick = () => dialog.close();
 actions.append(close);
 if(estado.usuario?.rol_codigo === 'estudiante' && !estado.usuario.debe_cambiar_password && postulacion) {
 const note = document.createElement('p');
 note.className = 'aviso';
 note.textContent = postulacion === 'retirada' ? 'Retiraste esta postulación. Puedes revisar si aún se permite reactivarla en Mis postulaciones.' : 'Ya tienes una postulación para esta práctica.';
 const tracking = document.createElement('a');
 tracking.href = `${appBase}/pages/postulaciones/index.php`;
 tracking.textContent = 'Ver mis postulaciones';
 article.append(note, tracking);
 } else if(estado.usuario?.rol_codigo === 'estudiante' && !estado.usuario.debe_cambiar_password) {
 const form = document.createElement('form');form.className = 'formulario';
 const label = document.createElement('label');label.textContent = 'Carta de presentación (opcional)';
 const letter = document.createElement('textarea');letter.name = 'carta_presentacion';letter.maxLength = 3000;letter.rows = 4;label.append(letter);form.append(label);
 const submit = document.createElement('button');submit.className = 'boton primario';submit.textContent = 'Enviar postulación';form.append(submit);
 form.onsubmit = async event => {
 event.preventDefault();submit.disabled = true;
 try {
 await peticion('postulaciones', {method: 'POST', body: JSON.stringify({id_practica: id, carta_presentacion: letter.value})});
 dialog.close();mostrar('Postulación registrada.');
 if(document.body.dataset.module)await cargarModuloActual();else await cargarPracticas();
 } catch(error) {
  mostrar(error.message, true);
  if (/cv|correo|teléfono|perfil/i.test(error.message) && !article.querySelector('[data-enlace-perfil]')) {
   const aviso = document.createElement('p');aviso.className = 'aviso';
   aviso.textContent = 'Revisa tus datos y tu CV antes de volver a postularte.';
   const perfil = document.createElement('a');perfil.href = `${appBase}/pages/perfil/index.php`;
   perfil.dataset.enlacePerfil = '';perfil.textContent = 'Ir a mi perfil';
   aviso.append(' ', perfil);article.append(aviso);
  }
  submit.disabled = false;
 }
 };
 article.append(form);
 } else if(!estado.usuario) {
 const login = document.createElement('a');login.className = 'boton primario';login.href = `${appBase}/public/login.html`;login.textContent = 'Iniciar sesión para postularte';actions.append(login);
 } else if(estado.usuario.debe_cambiar_password) {
 const note = document.createElement('p');note.textContent = 'Cambia tu contraseña temporal antes de postularte.';article.append(note);
 }
 article.append(actions);dialog.append(article);document.body.append(dialog);dialog.addEventListener('close', () => dialog.remove(), {once: true});dialog.showModal();
  }
 catch(e) {
 mostrar(e.message, true)
  }
}

// Selecciona las herramientas según el rol.
async function cargarPanel() {
 if(document.body.dataset.module)return cargarModuloActual()
}

function crearCatalogoModulo(root) {
 const form = document.createElement('form');
 form.id = 'filtros-modulo';
 const fieldset = document.createElement('fieldset');
 const legend = document.createElement('legend');
 legend.textContent = 'Filtrar prácticas';
 fieldset.append(legend);
 for(const [name, label, type] of [['q', 'Palabra clave', 'search'], ['empresa', 'Empresa', 'text']]) {
 const l = document.createElement('label');l.textContent = label;
 const i = document.createElement('input');i.name = name;i.type = type;l.append(i);fieldset.append(l)
  }
 for(const [name, label, catalogo] of [['area', 'Área', 'area'], ['modalidad', 'Tipo o modalidad', 'modalidad']]) {
 const l = document.createElement('label');l.textContent = label;
 const s = document.createElement('select');s.name = name;s.dataset.catalogo = catalogo;
 const all = document.createElement('option');all.value = '';all.textContent = `Todas: ${label.toLowerCase()}`;s.append(all);
 l.append(s);fieldset.append(l)
  }
 for(const [name, label] of [['fecha_inicio_desde', 'Inicio desde'], ['fecha_inicio_hasta', 'Inicio hasta']]) {
 const l = document.createElement('label');l.textContent = label;
 const i = document.createElement('input');i.type = 'date';i.name = name;l.append(i);fieldset.append(l)
  }
 const submit = document.createElement('button');submit.type = 'submit';submit.textContent = 'Buscar';fieldset.append(submit);
 const reset = document.createElement('button');reset.type = 'reset';reset.textContent = 'Limpiar';fieldset.append(reset);
 form.append(fieldset);
 const status = document.createElement('p');status.setAttribute('role', 'status');
 const list = document.createElement('div');list.setAttribute('aria-live', 'polite');
 const nav = document.createElement('nav');nav.setAttribute('aria-label', 'Paginación');
 const prev = document.createElement('button');prev.type = 'button';prev.textContent = 'Anterior';prev.disabled = true;
 const page = document.createElement('span');page.textContent = 'Página 1';
 const next = document.createElement('button');next.type = 'button';next.textContent = 'Siguiente';next.disabled = true;
 nav.append(prev, page, next);
 root.append(form, status, list, nav);
 llenarCatalogos();
 const vista = { filtros: form, lista: list, anterior: prev, pagina: page, siguiente: next, estado: status };
 form.addEventListener('submit', e => { e.preventDefault();estado.pagina = 1;cargarPracticas(vista) });
 form.addEventListener('reset', () => setTimeout(() => { estado.pagina = 1;cargarPracticas(vista) }, 0));
 prev.onclick = () => { estado.pagina = Math.max(1, estado.pagina - 1);cargarPracticas(vista) };
 next.onclick = () => { estado.pagina++;cargarPracticas(vista) };
 cargarPracticas(vista)
}

async function cargarModuloActual() {
 const root = $('#contenido-modulo');
 if(!root)return;
 root.replaceChildren();
 const spinner = crearSpinner('Cargando información…');root.append(spinner);root.setAttribute('aria-busy', 'true');
 const modulo = document.body.dataset.module;
 const rol = estado.usuario?.rol_codigo;
 if(!estado.usuario) {
 const aviso = document.createElement('p');aviso.textContent = 'Inicia sesión para acceder a esta sección.';root.append(aviso);
 const link = document.createElement('a');link.href = `${appBase}/public/login.html`;link.textContent = 'Ir al inicio de sesión';root.append(link);return
  }
 if(estado.usuario.debe_cambiar_password) {
 location.replace(`${appBase}/public/cambiar-clave.html`);return
  }
 try {
 if(modulo === 'practicas') {
 if(rol === 'empresa') {
 crearCatalogoModulo(root)
    } else if(rol === 'admin' || rol === 'superadmin') {
 await cargarPracticasAdmin(root, rol)
    } else crearCatalogoModulo(root)
  } else if(modulo === 'empresa') {
 if(rol !== 'empresa')throw new Error('Esta sección es solo para cuentas de empresa.');
 if(estado.usuario.empresa_estado_codigo !== 'aprobada') {
 const perfil = await peticion('perfil');
 encabezado(root, `Estado de la empresa: ${perfil.datos.estado || 'sin estado'}`);
 const aviso = document.createElement('p');aviso.className = 'aviso';aviso.textContent = 'Las herramientas para publicar prácticas se habilitarán cuando la cuenta esté aprobada.';root.append(aviso);
 etiquetaEstado(root, perfil.datos.estado, 'empresa', perfil.datos.estado_codigo);
 const enlace = document.createElement('a');enlace.href = `${appBase}/pages/perfil/index.php`;enlace.textContent = 'Consultar mi perfil';root.append(enlace);return
 }
 const p = await peticion('practicas/mias');
 formularioPractica(root);await renderPracticasPropias(root, p.datos)
  } else if(modulo === 'postulaciones') {
 if(rol === 'admin' || rol === 'superadmin') await cargarPostulacionesAdmin(root);
 else { const filas = await peticion('postulaciones');await renderSolicitudes(root, filas.datos, rol === 'estudiante'); }
  } else if(modulo === 'perfil') {
 if(!['estudiante', 'empresa', 'admin', 'superadmin'].includes(rol))throw new Error('No se encontró el perfil de esta cuenta.');
 const perfil = await peticion('perfil');
 formularioPerfil(root, perfil.datos, rol)
  } else if(modulo === 'empleados') {
 if(rol !== 'admin' && rol !== 'superadmin')throw new Error('Esta sección requiere permisos de administración.');
 formularioUsuario(root, rol);await gestionarUsuarios(root, [], rol);await gestionarRestauracion(root)
  } else if(modulo === 'administracion') {
 if(rol !== 'admin' && rol !== 'superadmin')throw new Error('Esta sección requiere permisos de administración.');
 const d = await peticion('admin/reportes');resumenReportes(root, d.datos);
 try {
 const e = await peticion('admin/empresas');await renderEmpresasPendientes(root, e.datos)
 } catch(error) {
 const seccion = document.createElement('section');encabezado(seccion, 'Empresas por revisar');
 const aviso = document.createElement('p');aviso.textContent = `No se pudo cargar la lista de empresas: ${error.message}`;seccion.append(aviso);root.append(seccion)
  }
 herramientasAdmin(root)
  } else if(modulo === 'consultas') {
 const d = await peticion(rol === 'admin' || rol === 'superadmin' ? 'admin/reportes' : 'dashboard');resumenReportes(root, d.datos)
  } else throw new Error('La sección solicitada no está disponible.');
 }
 catch(e) {
  root.replaceChildren();
  const aviso = document.createElement('p');
  aviso.className = 'aviso';
  aviso.setAttribute('role', 'alert');
  aviso.textContent = e.message || 'No se pudo cargar esta sección.';
  root.append(aviso);
  mostrar(aviso.textContent, true);
 }
 finally { spinner.remove();root.removeAttribute('aria-busy'); }
}

async function cargarPostulacionesAdmin(root) {
 let pagina = 1;
 const porPagina = 20;
 const contenido = document.createElement('div');
 const paginador = document.createElement('nav');
 paginador.className = 'paginador';
 paginador.setAttribute('aria-label', 'Paginación de postulaciones');
 const anterior = document.createElement('button');anterior.type = 'button';anterior.textContent = 'Anterior';
 const actual = document.createElement('span');
 const siguiente = document.createElement('button');siguiente.type = 'button';siguiente.textContent = 'Siguiente';
 paginador.append(anterior, actual, siguiente);
 root.append(contenido, paginador);
 const cargar = async () => {
  anterior.disabled = true;siguiente.disabled = true;
  const respuesta = await peticion(`postulaciones?pagina=${pagina}&por_pagina=${porPagina}`);
  contenido.replaceChildren();
  await renderSolicitudes(contenido, respuesta.datos, false, {buscarEnPagina: true});
  actual.textContent = `Página ${pagina}`;
  anterior.disabled = pagina <= 1;
  siguiente.disabled = respuesta.datos.length < porPagina;
 };
 anterior.onclick = async () => {pagina--;try { await cargar(); } catch(error) {pagina++;mostrar(error.message, true);} };
 siguiente.onclick = async () => {pagina++;try { await cargar(); } catch(error) {pagina--;mostrar(error.message, true);} };
 await cargar();
}

// Bandeja de coordinación con filtro de estado y paginación del servidor.
async function cargarPracticasAdmin(root, rol) {
 encabezado(root, 'Prácticas por revisar');
 const controls = document.createElement('form');controls.className = 'filtros';
 const label = document.createElement('label');label.textContent = 'Estado';
 const select = document.createElement('select');select.name = 'estado';
 const all = document.createElement('option');all.value = '';all.textContent = 'Todos los estados';select.append(all);
 for(const item of estado.catalogos.filter(item => item.tipo === 'practica')) {
 const option = document.createElement('option');option.value = item.codigo;option.textContent = item.nombre;option.selected = item.codigo === 'pendiente_revision';select.append(option)
 }
 label.append(select);controls.append(label);
 const submit = document.createElement('button');submit.type = 'submit';submit.textContent = 'Filtrar';controls.append(submit);
 root.append(controls);
 const list = document.createElement('div');list.className = 'tarjetas';root.append(list);
 const pager = document.createElement('nav');pager.className = 'paginador';pager.setAttribute('aria-label', 'Paginación de prácticas');
 const previous = document.createElement('button');previous.type = 'button';previous.textContent = 'Anterior';previous.disabled = true;
 const pageLabel = document.createElement('span');
 const next = document.createElement('button');next.type = 'button';next.textContent = 'Siguiente';next.disabled = true;
 pager.append(previous, pageLabel, next);root.append(pager);
 let page = 1;
 const load = async() => {
 const query = new URLSearchParams({pagina: String(page), por_pagina: '20'});
 if(select.value)query.set('estado', select.value);
 try {
 const response = await peticion(`practicas/admin?${query}`);list.replaceChildren();
 await renderPracticasGestion(list, response.datos.elementos, rol);
 pageLabel.textContent = `Página ${page}`;previous.disabled = page <= 1;next.disabled = response.datos.elementos.length < Number(response.datos.por_pagina || 20)
 } catch(error) { mostrar(error.message, true) }
 };
 controls.onsubmit = async event => { event.preventDefault();page = 1;await load() };
 previous.onclick = async() => { page = Math.max(1, page - 1);await load() };
 next.onclick = async() => { page++;await load() };
 await load()
}

// Crea el editor del perfil propio según el rol de la cuenta.
function formularioPerfil(root, p, rol) {
 encabezado(root, 'Actualizar perfil');
 const f = document.createElement('form');
 f.className = 'formulario columnas panel';
 const comunes = [['nombres', 'Nombres'], ['apellidos', 'Apellidos'], ['correo', 'Correo'], ['telefono', 'Teléfono']];
 for(const [name, label] of comunes) {
 const l = document.createElement('label');
 l.textContent = label;
 const i = document.createElement('input');
 i.name = name;
 i.type = name === 'correo' ? 'email' : 'text';
 if (name === 'telefono') { i.inputMode = 'numeric'; i.pattern = '[0-9]{8}'; i.maxLength = 8; }
 i.value = (name === 'correo' && rol === 'empresa'?p.correo_usuario: p[name]) || '';
 i.required = name !== 'telefono' || rol === 'estudiante';
 l.append(i);
 f.append(l)
  }
  if(rol === 'estudiante') {
 for(const [name, label] of [['carnet', 'Carné'], ['universidad', 'Universidad'], ['semestre', 'Semestre']]) {
 const l = document.createElement('label');
 l.textContent = label;
 const i = document.createElement('input');
 i.name = name;
 i.value = p[name] || '';
 l.append(i);
 f.append(l)
    }
 const l = document.createElement('label');
 l.textContent = 'Carrera';
 const s = document.createElement('select');
 s.name = 'id_carrera';
 for(const c of estado.catalogos.filter(x => x.tipo === 'carrera')) {
 const o = document.createElement('option');
 o.value = c.id;
 o.textContent = c.nombre;
 o.selected = Number(c.id) === Number(p.id_carrera);
 s.append(o)
    }
 l.append(s);
 f.append(l)
  }
  else if(rol === 'empresa') {
 for(const [name, label] of [['nombre_legal', 'Nombre legal'], ['nombre_comercial', 'Nombre comercial'], ['identificacion_fiscal', 'Identificación fiscal'], ['sector', 'Sector'], ['correo_contacto', 'Correo de contacto'], ['sitio_web', 'Sitio web'], ['direccion', 'Dirección']]) {
 const l = document.createElement('label');
 l.textContent = label;
 const i = document.createElement('input');
 i.name = name;
 i.value = p[name] || '';
 l.append(i);
 f.append(l)
    }
 const l = document.createElement('label');
 l.textContent = 'Descripción';
 const t = document.createElement('textarea');
 t.name = 'descripcion';
 t.value = p.descripcion || '';
 l.append(t);
 f.append(l)
  }
  const camposEditables = Array.from(f.querySelectorAll('input, select, textarea'));
  const valoresIniciales = new Map(camposEditables.map(campo => [campo, campo.value]));
  const marcarCambios = () => camposEditables.forEach(campo => {
   const modificado = valoresIniciales.get(campo) !== campo.value || campo.dataset.perfilGuardado === 'true';
   campo.closest('label')?.classList.toggle('campo-modificado', modificado);
  });
  f.addEventListener('input', marcarCambios);
  f.addEventListener('change', marcarCambios);
  const b = document.createElement('button');
 b.className = 'boton primario';
 b.textContent = 'Actualizar perfil';
 f.append(b);
 f.onsubmit = async e => {
 e.preventDefault();
 if (!f.reportValidity()) return;
 try {
 await durantePeticion(b, async () => {
  const modificados = camposEditables.filter(campo => valoresIniciales.get(campo) !== campo.value);
  await peticion('perfil', { method: 'PUT', body: JSON.stringify(formulario(f)) });
  mostrar('Perfil actualizado.');
  modificados.forEach(campo => { campo.dataset.perfilGuardado = 'true'; });
  camposEditables.forEach(campo => valoresIniciales.set(campo, campo.value));
  marcarCambios();
  const sesionActualizada = await peticion('auth/me');
  estado.usuario = sesionActualizada.datos;
  ponerSesion();
 }, 'Guardando…');
    }
 catch(err) {
 if (Object.keys(err.errores || {}).length) pintarErroresCampos(f, err.errores);
 mostrar(err.message, true)
    }
  };
 root.append(f);
 if(rol === 'estudiante') {
 const cv = document.createElement('form');
 cv.className = 'formulario';
 cv.enctype = 'multipart/form-data';
 const l = document.createElement('label');
 l.textContent = `CV actual: ${p.cv_nombre||'No disponible'}`;
 const file = document.createElement('input');
 file.type = 'file';
 file.name = 'cv';
 file.accept = 'application/pdf,.pdf';
 file.required = true;
 file.addEventListener('change', () => {
  const archivo = file.files?.[0];
  if (!archivo) return;
  const pdf = archivo.name.toLowerCase().endsWith('.pdf') && (!archivo.type || archivo.type === 'application/pdf');
  if (!pdf) pintarErroresCampos(cv, { cv: 'Selecciona un archivo PDF.' });
  else if (estado.cvMaximo > 0 && archivo.size > estado.cvMaximo) pintarErroresCampos(cv, { cv: `El archivo supera el máximo permitido (${Math.ceil(estado.cvMaximo / 1048576)} MB).` });
  else limpiarErroresFormulario(cv);
 });
 l.append(file);
 cv.append(l);
 const up = document.createElement('button');
 up.className = 'boton secundario';
 up.textContent = 'Reemplazar CV';
 cv.append(up);
 cv.onsubmit = async e => {
 e.preventDefault();
 if (!cv.reportValidity()) return;
 const archivo = file.files?.[0];
 if (archivo) {
  const pdf = archivo.name.toLowerCase().endsWith('.pdf') && (!archivo.type || archivo.type === 'application/pdf');
  if (!pdf) { pintarErroresCampos(cv, { cv: 'Selecciona un archivo PDF.' }); return; }
  if (estado.cvMaximo > 0 && archivo.size > estado.cvMaximo) { pintarErroresCampos(cv, { cv: `El archivo supera el máximo permitido (${Math.ceil(estado.cvMaximo / 1048576)} MB).` }); return; }
 }
 try {
 await durantePeticion(up, async () => {
  await peticion('perfil/cv', { method: 'POST', body: new FormData(cv) });
  mostrar('CV actualizado.');
  await cargarPanel();
 }, 'Subiendo CV…');
      }
 catch(err) {
 if (Object.keys(err.errores || {}).length) pintarErroresCampos(cv, err.errores);
 mostrar(err.message, true)
      }
    };
 root.append(cv);
 if(p.cv_nombre && !Number(p.cv_eliminado))boton('Descargar mi CV', async() => {
 try {
 const blob = await peticion('perfil/cv');
 const url = URL.createObjectURL(blob);const link = document.createElement('a');link.href = url;link.download = p.cv_nombre || 'CV.pdf';link.click();setTimeout(() => URL.revokeObjectURL(url), 1000)
 } catch(error) { mostrar(error.message, true) }
 }, root);
 if(p.cv_nombre && !Number(p.cv_eliminado)) {
 boton('Marcar CV como eliminado', async() => {
 try {
 const confirmacion = await confirmarAccion('No podrás usar este CV para nuevas postulaciones hasta que subas otro archivo.', {titulo: 'Eliminar CV', textoConfirmar: 'Eliminar CV'});
 if (!confirmacion.confirmado) return;
 await peticion('perfil/cv', {
 method: 'DELETE'
          });await cargarPanel()
        }
 catch(err) {
 mostrar(err.message, true)
        }
      }, root)
    }
  }
}

// Funciones para crear elementos del panel.
function boton(etiqueta, accion, contenedor) {
 const b = document.createElement('button');
 b.type = 'button';
 b.className = 'boton secundario';
 b.textContent = etiqueta;
 b.onclick = (evento) => durantePeticion(b, () => accion(evento), 'Procesando…')
  .catch((error) => mostrar(error.message || 'No se pudo completar la acción.', true));
 contenedor.append(b);
 return b
}

function encabezado(root, titulo) {
 const h = document.createElement('h3');
 h.textContent = titulo;
 root.append(h)
}

function tarjeta(root, titulo, detalle) {
 const a = document.createElement('article');
 a.className = 'tarjeta';
 const h = document.createElement('h4');
 h.textContent = titulo;
 a.append(h);
 for(const texto of detalle) {
 const p = document.createElement('p');
 p.textContent = esc(texto);
 a.append(p)
  }
 root.append(a);
 return a
}

function etiquetaEstado(contenedor, texto, entidad, codigo) {
 const color = estado.catalogos.find(x => x.tipo === entidad && x.codigo === codigo)?.color;
 const badge = crearInsigniaEstado(texto, color);
 if(/^[a-zA-Z0-9_-]+$/.test(String(entidad || '')) && /^[a-zA-Z0-9_-]+$/.test(String(codigo || '')))badge.classList.add(`estado-${entidad}-${codigo}`);
 contenedor.append(badge)
}

// Crea el formulario de oportunidades para empresas.
function formularioPractica(root) {
 encabezado(root, 'Publicar una práctica');
 root.append(crearFormularioPractica())
}

function crearFormularioPractica(practica = null) {
 const f = document.createElement('form');
 f.className = 'formulario columnas panel';
 f.id = practica ? `formulario-editar-practica-${practica.id_practica}` : 'formulario-nueva-practica';
 const campos = [['titulo', 'Título', 'text'], ['descripcion', 'Descripción', 'textarea'], ['requisitos', 'Requisitos', 'textarea'], ['ubicacion', 'Ubicación', 'text'], ['fecha_inicio', 'Fecha de inicio', 'date'], ['cupo_maximo', 'Cupo máximo', 'number'], ['fecha_limite_postulacion', 'Fecha límite de postulación', 'date']];
 for(const [name, label, type] of campos) {
  const l = document.createElement('label');l.textContent = label;
  const input = type === 'textarea' ? document.createElement('textarea') : document.createElement('input');
  if(type !== 'textarea')input.type = type;
  input.name = name;input.value = practica?.[name] ?? (name === 'cupo_maximo' ? '1' : '');
  input.required = ['titulo', 'descripcion', 'fecha_inicio', 'cupo_maximo', 'fecha_limite_postulacion'].includes(name);
  if(name === 'titulo')input.maxLength = 180;
  if(name === 'descripcion' || name === 'requisitos')input.maxLength = 10000;
  if(name === 'ubicacion')input.maxLength = 255;
  if(name === 'cupo_maximo') { input.min = '1'; input.step = '1'; }
  if(name === 'fecha_inicio' && (!practica || String(practica.fecha_inicio || '') >= fechaLocal(new Date())))input.min = fechaLocal(new Date());
  if(name === 'fecha_limite_postulacion') {
   const manana = new Date();
   manana.setDate(manana.getDate() + 1);
   input.min = fechaLocal(manana);
  }
  l.append(input);f.append(l)
 }
 for(const [name, label, tipo, clave] of [['id_area', 'Área', 'area', 'id_area'], ['id_modalidad', 'Modalidad', 'modalidad', 'id_modalidad']]) {
  const l = document.createElement('label');l.textContent = label;
  const select = document.createElement('select');select.name = name;select.required = true;
  const placeholder = document.createElement('option');placeholder.value = '';placeholder.textContent = `Selecciona ${label.toLowerCase()}`;select.append(placeholder);
  for(const item of estado.catalogos.filter(x => x.tipo === tipo)) {
   const option = document.createElement('option');option.value = item.id;option.textContent = item.nombre;option.selected = Number(item.id) === Number(practica?.[clave]);select.append(option)
  }
  l.append(select);f.append(l)
 }
 const submit = document.createElement('button');submit.className = 'boton primario';submit.textContent = practica ? 'Guardar y reenviar a revisión' : 'Enviar a revisión';f.append(submit);
 f.onsubmit = async event => {
  event.preventDefault();if(!f.reportValidity())return;
  limpiarErroresCampos(f);f.querySelector('[data-error-formulario]')?.remove();
  try {
   await durantePeticion(submit, async () => {
    await peticion(practica ? `practicas/${practica.id_practica}` : 'practicas', {method: practica ? 'PUT' : 'POST', body: JSON.stringify(formulario(f))});
    f.closest('dialog')?.close();mostrar(practica ? 'Los cambios se enviaron a revisión.' : 'La propuesta fue enviada a revisión.');await cargarPanel();
   }, 'Enviando…');
  } catch(error) {
   if(Object.keys(error.errores || {}).length) pintarErroresCampos(f, error.errores);
   else { const aviso = document.createElement('p');aviso.className = 'error-formulario alerta alerta--error';aviso.dataset.errorFormulario = '';aviso.setAttribute('role', 'alert');aviso.textContent = error.message;f.prepend(aviso) }
   mostrar(error.message, true)
  }
 };
 return f
}

function editarPractica(practica) {
 const dialog = document.createElement('dialog');dialog.className = 'dialogo';
 const body = document.createElement('article');body.className = 'detalle-practica';
 const title = document.createElement('h2');title.textContent = 'Editar práctica';body.append(title, crearFormularioPractica(practica));
 const cancel = document.createElement('button');cancel.type = 'button';cancel.className = 'boton secundario';cancel.textContent = 'Cancelar';cancel.onclick = () => dialog.close();body.append(cancel);
 dialog.append(body);dialog.addEventListener('close', () => dialog.remove(), {once: true});document.body.append(dialog);dialog.showModal()
}

// Consulta los cambios de estado permitidos.
async function transiciones(entidad, codigo) {
 const q = new URLSearchParams( {
 entidad, estado_codigo: codigo
  });
 return (await peticion(`catalogos/transiciones?${q}`)).datos
}

async function elegirTransicion(entidad, codigo) {
 const opciones = await transiciones(entidad, codigo);
 if(!opciones.length) {
 mostrar('No hay transiciones permitidas para esta cuenta y estado.', true);
 return null
  }
 return new Promise(resolve => {
 const dialog = document.createElement('dialog');dialog.className = 'dialogo';
 const form = document.createElement('form');form.className = 'formulario';
 const title = document.createElement('h2');title.textContent = 'Cambiar estado';form.append(title);
 const stateLabel = document.createElement('label');stateLabel.textContent = 'Nuevo estado';
 const select = document.createElement('select');select.required = true;
 for(const option of opciones) { const item = document.createElement('option');item.value = option.estado_codigo;item.textContent = option.estado_nombre;select.append(item) }
 stateLabel.append(select);form.append(stateLabel);
 const commentLabel = document.createElement('label');commentLabel.textContent = 'Comentario';
 const comment = document.createElement('textarea');comment.name = 'comentario';comment.rows = 4;comment.maxLength = 2000;commentLabel.append(comment);form.append(commentLabel);
 const actions = document.createElement('div');actions.className = 'acciones';
 const cancel = document.createElement('button');cancel.type = 'button';cancel.className = 'boton secundario';cancel.textContent = 'Cancelar';cancel.onclick = () => dialog.close();
 const save = document.createElement('button');save.type = 'submit';save.className = 'boton primario';save.textContent = 'Guardar cambio';actions.append(cancel, save);form.append(actions);dialog.append(form);
 const actualizarComentario = () => { const destino = opciones.find(item => item.estado_codigo === select.value);const requerido = valorVerdadero(destino?.requiere_comentario);commentLabel.hidden = !requerido;comment.required = requerido };
 select.addEventListener('change', actualizarComentario);actualizarComentario();
 let seleccion = null;
 dialog.addEventListener('close', () => { dialog.remove();resolve(seleccion) }, {once: true});
 form.addEventListener('submit', event => { event.preventDefault();const destino = opciones.find(item => item.estado_codigo === select.value);if(!destino)return;seleccion = {destino, comentario: comment.value.trim()};dialog.close() });
 document.body.append(dialog);dialog.showModal()
 })
}

function pedirComentario(transicion) {
 return new Promise(resolve => {
  const dialog = document.createElement('dialog');dialog.className = 'dialogo';
  const form = document.createElement('form');form.className = 'formulario detalle-practica';
  const title = document.createElement('h2');title.textContent = transicion.estado_nombre;form.append(title);
  const requerido = valorVerdadero(transicion.requiere_comentario);
  const label = document.createElement('label');label.textContent = requerido ? 'Comentario obligatorio' : 'Comentario (opcional)';
  const comment = document.createElement('textarea');comment.name = 'comentario';comment.rows = 5;comment.maxLength = 2000;comment.required = requerido;label.append(comment);form.append(label);
  const actions = document.createElement('div');actions.className = 'acciones';
  const cancel = document.createElement('button');cancel.type = 'button';cancel.className = 'boton secundario';cancel.textContent = 'Cancelar';cancel.onclick = () => dialog.close();
  const submit = document.createElement('button');submit.type = 'submit';submit.className = 'boton primario';submit.textContent = 'Confirmar respuesta';actions.append(cancel, submit);form.append(actions);dialog.append(form);
  let value = null;
  form.addEventListener('submit', event => {event.preventDefault();if(!form.reportValidity())return;value = comment.value.trim();dialog.close()});
  dialog.addEventListener('close', () => {dialog.remove();resolve(value)}, {once: true});document.body.append(dialog);dialog.showModal();comment.focus()
 })
}

// Muestra las oportunidades y acciones de la empresa.
async function renderPracticasPropias(root, filas) {
 encabezado(root, 'Mis prácticas');
 const filtros = document.createElement('form');filtros.className = 'filtros';
 const busquedaLabel = document.createElement('label');busquedaLabel.textContent = 'Buscar práctica';
 const busqueda = document.createElement('input');busqueda.type = 'search';busqueda.name = 'q';busquedaLabel.append(busqueda);
 const estadoLabel = document.createElement('label');estadoLabel.textContent = 'Estado';
 const estadoSelect = document.createElement('select');estadoSelect.name = 'estado';
 const todos = document.createElement('option');todos.value = '';todos.textContent = 'Todos los estados';estadoSelect.append(todos);
 estado.catalogos.filter((item) => item.tipo === 'practica').forEach((item) => {
  const opcion = document.createElement('option');opcion.value = item.codigo;opcion.textContent = item.nombre;estadoSelect.append(opcion);
 });
 estadoLabel.append(estadoSelect);
 const buscar = document.createElement('button');buscar.type = 'submit';buscar.className = 'boton secundario';buscar.textContent = 'Filtrar';
 filtros.append(busquedaLabel, estadoLabel, buscar);root.append(filtros);
 const lista = document.createElement('div');lista.className = 'tarjetas';root.append(lista);
 const pintar = async () => {
  lista.replaceChildren();
  const q = busqueda.value.trim().toLocaleLowerCase('es');
  const resultados = filas.filter((fila) => (!estadoSelect.value || fila.estado_codigo === estadoSelect.value) && (!q || String(fila.titulo || '').toLocaleLowerCase('es').includes(q)));
  if (!resultados.length) { const vacio = document.createElement('p');vacio.textContent = 'No hay prácticas que coincidan con los filtros.';lista.append(vacio);return; }
  for (const x of resultados) {
   const a = tarjeta(lista, x.titulo, [`${x.estado} · Inicio: ${x.fecha_inicio || 'Por definir'} · Cierre: ${x.fecha_limite_postulacion}`, `Postulaciones: ${x.total_postulaciones} · pendientes: ${x.pendientes}`]);
   try {
    const opciones = await transiciones('practica', x.estado_codigo);
    etiquetaEstado(a, x.estado, 'practica', x.estado_codigo);
    if(opciones.some(t => t.estado_codigo === 'pendiente_revision'))boton('Editar y reenviar a revisión', () => editarPractica(x), a);
    if(opciones.some(t => t.estado_codigo === 'cerrada'))boton('Cerrar práctica', async() => {
     const confirmacion = await confirmarAccion('Cerrar la práctica también responderá las postulaciones pendientes.', {titulo: 'Cerrar práctica', textoConfirmar: 'Cerrar práctica'});
     if(!confirmacion.confirmado)return;
     await peticion(`practicas/${x.id_practica}/cerrar`, {method: 'POST', body: '{}'});mostrar('Práctica cerrada.');await cargarPanel();
    }, a);
   } catch(error) { mostrar(error.message, true); }
  }
 };
 filtros.addEventListener('submit', (evento) => {evento.preventDefault();void pintar();});
 filtros.addEventListener('change', () => void pintar());
 await pintar();
}
// Muestra las postulaciones según el perfil.
async function renderSolicitudes(root, filas, estudiante, opciones = {}) {
 encabezado(root, estudiante ? 'Mis postulaciones' : (estado.usuario?.rol_codigo === 'empresa' ? 'Postulaciones recibidas por práctica' : 'Postulaciones'));
 const filtros = document.createElement('form');filtros.className = 'filtros';
 const terminoLabel = document.createElement('label');terminoLabel.textContent = opciones.buscarEnPagina ? 'Buscar en esta página' : (estudiante ? 'Buscar práctica' : 'Buscar práctica o postulante');
 const termino = document.createElement('input');termino.type = 'search';termino.name = 'q';terminoLabel.append(termino);
 const estadoLabel = document.createElement('label');estadoLabel.textContent = opciones.buscarEnPagina ? 'Estado en esta página' : 'Estado';
 const estadoSelect = document.createElement('select');estadoSelect.name = 'estado';
 const todos = document.createElement('option');todos.value = '';todos.textContent = 'Todos los estados';estadoSelect.append(todos);
 estado.catalogos.filter((item) => item.tipo === 'postulacion').forEach((item) => {
  const opcion = document.createElement('option');opcion.value = item.codigo;opcion.textContent = item.nombre;estadoSelect.append(opcion);
 });
 estadoLabel.append(estadoSelect);const buscar = document.createElement('button');buscar.type = 'submit';buscar.className = 'boton secundario';buscar.textContent = 'Filtrar';
 filtros.append(terminoLabel, estadoLabel, buscar);root.append(filtros);
 const lista = document.createElement('div');lista.className = 'tarjetas';root.append(lista);
 const pintar = async () => {
  lista.replaceChildren();
  const q = termino.value.trim().toLocaleLowerCase('es');
  let seleccionadas = filas.filter((fila) => (!estadoSelect.value || fila.estado_codigo === estadoSelect.value) && (!q || `${fila.practica || ''} ${fila.nombres || ''} ${fila.apellidos || ''}`.toLocaleLowerCase('es').includes(q)));
  if (!seleccionadas.length) { const vacio = document.createElement('p');vacio.textContent = 'No hay postulaciones que coincidan con los filtros.';lista.append(vacio);return; }
  if (!estudiante) seleccionadas = [...seleccionadas].sort((a, b) => String(a.practica).localeCompare(String(b.practica), 'es'));
  let practicaActual = null;
  for (const x of seleccionadas) {
   if (!estudiante && Number(x.id_practica) !== practicaActual) {
    practicaActual = Number(x.id_practica);const grupo = document.createElement('h3');grupo.textContent = x.practica || 'Práctica';lista.append(grupo);
   }
   const detalle = estudiante
    ? [`Empresa: ${x.empresa || 'No indicada'}`, `Fecha: ${x.fecha_postulacion}`, x.carta_presentacion ? `Carta de presentación: ${x.carta_presentacion}` : '', x.comentario ? `Observación: ${x.comentario}` : '']
    : [`${x.empresa || ''}`, `${x.nombres || ''} ${x.apellidos || ''} · ${x.carrera || ''}`, `Correo: ${x.correo || ''} · Teléfono: ${x.telefono || ''}`, `Fecha: ${x.fecha_postulacion}`, x.comentario || ''];
   const item = tarjeta(lista, x.practica || 'Postulación', detalle.filter(Boolean));
   etiquetaEstado(item, x.estado, 'postulacion', x.estado_codigo);
   if (!estudiante) boton('Ver perfil del postulante', () => verPerfilPostulante(x), item);
   if (estudiante) boton('Ver historial', async (evento) => {
    const trigger = evento.currentTarget;
    const anterior = item.querySelector('.linea-tiempo');
    if (anterior) { anterior.remove();trigger.textContent = 'Ver historial';return; }
    try {
     const respuesta = await peticion(`postulaciones/${x.id_postulacion}/historial`);
     const timeline = document.createElement('section');timeline.className = 'linea-tiempo';
     const title = document.createElement('h5');title.textContent = 'Historial de seguimiento';timeline.append(title);
     if (!respuesta.datos.length) {const vacio = document.createElement('p');vacio.textContent = 'Aún no hay cambios registrados.';timeline.append(vacio);}
     else {
      const historial = document.createElement('ol');
      respuesta.datos.forEach((cambio) => {
       const entrada = document.createElement('li');
       const transicion = document.createElement('strong');transicion.textContent = `${cambio.estado_anterior || 'Registro inicial'} → ${cambio.estado_nuevo}`;
       const fecha = document.createElement('time');fecha.dateTime = cambio.fecha_hora;fecha.textContent = cambio.fecha_hora;entrada.append(transicion, fecha);
       if (cambio.actor) {const actor = document.createElement('span');actor.textContent = `Responsable: ${cambio.actor}`;entrada.append(actor);}
       if (cambio.comentario) {const comentario = document.createElement('p');comentario.textContent = cambio.comentario;entrada.append(comentario);}
       historial.append(entrada);
      });
      timeline.append(historial);
     }
     item.append(timeline);trigger.textContent = 'Ocultar historial';
    } catch (error) {mostrar(error.message, true);}
   }, item);

   if (estudiante && x.estado_codigo === 'retirada') {
    let practicaAbierta = false;
    try { await peticion(`practicas/${x.id_practica}`);practicaAbierta = true; } catch { /* La práctica ya no está publicada. */ }
    if (practicaAbierta) boton('Reactivar postulación', async () => {
     await peticion('postulaciones', {method: 'POST', body: JSON.stringify({id_practica: Number(x.id_practica)})});
     mostrar('Postulación reactivada.');await cargarPanel();
    }, item);
    else {const aviso = document.createElement('p');aviso.className = 'aviso';aviso.textContent = 'Esta práctica ya no recibe postulaciones.';item.append(aviso);}
   } else {
    try {
     const opciones = await transiciones('postulacion', x.estado_codigo);
     if (estudiante && opciones.some((opcion) => opcion.estado_codigo === 'retirada')) boton('Retirar', async () => {
      const confirmacion = await confirmarAccion('La postulación dejará de estar activa. Puedes reactivarla mientras la práctica siga recibiendo solicitudes.', {titulo: 'Retirar postulación', textoConfirmar: 'Retirar'});
      if (!confirmacion.confirmado) return;
      await peticion(`postulaciones/${x.id_postulacion}`, {method: 'DELETE'});mostrar('Postulación retirada.');await cargarPanel();
     }, item);
     if (!estudiante) for (const transicion of opciones) boton(transicion.estado_nombre, async () => {
      const comentario = await pedirComentario(transicion);if (comentario === null) return;
      await peticion(`postulaciones/${x.id_postulacion}`, {method: 'PUT', body: JSON.stringify({estado_codigo: transicion.estado_codigo, comentario: comentario || ''})});
      mostrar('Estado de postulación actualizado.');await cargarPanel();
     }, item);
    } catch (error) {mostrar(error.message, true);}
   }
  }
 };
 filtros.addEventListener('submit', (evento) => {evento.preventDefault();void pintar();});
 estadoSelect.addEventListener('change', () => void pintar());
 await pintar();
}
function verPerfilPostulante(postulacion) {
 const dialog = document.createElement('dialog');dialog.className = 'dialogo';
 const article = document.createElement('article');article.className = 'detalle-practica';
 const title = document.createElement('h2');title.textContent = `${postulacion.nombres || ''} ${postulacion.apellidos || ''}`.trim() || 'Perfil del postulante';article.append(title);
 const details = [
  ['Práctica', postulacion.practica], ['Carrera', postulacion.carrera], ['Carné', postulacion.carnet],
  ['Universidad', postulacion.universidad], ['Semestre', postulacion.semestre], ['Correo', postulacion.correo],
  ['Teléfono', postulacion.telefono], ['Fecha de postulación', postulacion.fecha_postulacion],
  ['Estado', postulacion.estado], ['Comentario', postulacion.comentario || 'Sin comentario']
 ];
 for(const [label, value] of details) {const row = document.createElement('p');const strong = document.createElement('strong');strong.textContent = `${label}: `;row.append(strong, document.createTextNode(value || 'No indicado'));article.append(row)}
 const actions = document.createElement('div');actions.className = 'acciones';
 const download = document.createElement('button');download.type = 'button';download.className = 'boton primario';download.textContent = 'Descargar CV';download.onclick = async() => {
  try {const blob = await peticion(`cv/${postulacion.id_postulacion}`);const url = URL.createObjectURL(blob);const link = document.createElement('a');link.href = url;link.download = postulacion.cv_nombre || 'cv.pdf';link.click();setTimeout(() => URL.revokeObjectURL(url), 1000)}
  catch(error) {mostrar(error.message, true)}
 };
 const close = document.createElement('button');close.type = 'button';close.className = 'boton secundario';close.textContent = 'Cerrar';close.onclick = () => dialog.close();actions.append(download, close);article.append(actions);dialog.append(article);
 dialog.addEventListener('close', () => dialog.remove(), {once: true});document.body.append(dialog);dialog.showModal()
}

// Herramientas para revisar oportunidades y estados.
async function renderPracticasGestion(root, filas, rol) {
 encabezado(root, 'Prácticas');
 if (!filas.length) { const vacio = document.createElement('p');vacio.textContent = 'No hay prácticas para este filtro.';root.append(vacio);return; }
 for(const x of filas) {
 const a = tarjeta(root, x.titulo, [x.empresa, `${x.area} · ${x.modalidad} · ${x.ubicacion || 'Ubicación por definir'}`, `Fecha de inicio: ${x.fecha_inicio || 'Por definir'} · Fecha límite: ${x.fecha_limite_postulacion}`, `Cupo: ${x.aceptadas}/${x.cupo_maximo} · Solicitudes: ${x.total_postulaciones}`, `Descripción: ${x.descripcion}`, `Requisitos: ${x.requisitos || 'No especificados.'}`]);
 etiquetaEstado(a, x.estado, 'practica', x.estado_codigo);
 try {
 const opciones = await transiciones('practica', x.estado_codigo);
 if(opciones.some(t => t.estado_codigo === 'cerrada'))boton('Cerrar práctica', async() => {
 try {
 await peticion(`practicas/${x.id_practica}/cerrar`, {
 method: 'POST', body: '{}'
          });await cargarPanel()
        }
 catch(e) {
 mostrar(e.message, true)
        }
      }, a);
 if(rol === 'superadmin' && opciones.length)boton('Cambiar estado', async() => {
 const sel = await elegirTransicion('practica', x.estado_codigo);if(!sel)return;try {
 await peticion(`practicas/${x.id_practica}/revision`, {
 method: 'PUT', body: JSON.stringify( {
 estado_codigo: sel.destino.estado_codigo, comentario: sel.comentario
            })
          });await cargarPanel()
        }
 catch(e) {
 mostrar(e.message, true)
        }
      }, a)
    }
 catch(error) { mostrar(error.message, true) }
  }
}

// Muestra empresas pendientes de revisión.
async function renderEmpresasPendientes(root, filas) {
 encabezado(root, 'Empresas por revisar');
 if (!filas.length) { const vacio = document.createElement('p');vacio.textContent = 'No hay empresas pendientes de revisión.';root.append(vacio);return; }
 for(const x of filas) {
 const a = tarjeta(root, x.nombre_comercial || x.nombre_legal, [x.correo_contacto, `${x.nombres || ''} ${x.apellidos || ''} · ${x.telefono || 'Sin teléfono'}`, `Sector: ${x.sector || 'No indicado'}`, `Identificación fiscal: ${x.identificacion_fiscal || 'No indicada'}`, `Descripción: ${x.descripcion || 'No proporcionada'}`, `Sitio web: ${x.sitio_web || 'No indicado'}`]);
 etiquetaEstado(a, x.estado, 'empresa', x.estado_codigo);
 boton('Revisar', async() => {
 const sel = await elegirTransicion('empresa', x.estado_codigo);if(!sel)return;try {
 await peticion(`admin/empresas/${x.id_empresa}/revision`, {
 method: 'PUT', body: JSON.stringify( {
 estado_codigo: sel.destino.estado_codigo, comentario: sel.comentario
          })
        });await cargarPanel()
      }
 catch(e) {
 mostrar(e.message, true)
      }
    }, a)
  }
}

function resumenReportes(root, d) {
 encabezado(root, 'Resumen');
 for(const [tipo, filas] of Object.entries(d)) {
 const grupo = document.createElement('div');
 grupo.className = 'resumen';
 for(const x of filas) {
 const c = document.createElement('div');
 c.className = 'contador';
 if(x.estado_codigo)c.classList.add(`estado-${tipo==='practicas'?'practica':'postulacion'}-${x.estado_codigo}`);
 const n = document.createElement('strong');
 n.textContent = x.cantidad;
 c.append(n, document.createTextNode(x.estado));
 grupo.append(c)
    }
 root.append(grupo)
  }
}

function herramientasAdmin(root) {
 encabezado(root, 'Herramientas de coordinación');
 const acciones = document.createElement('div');
 acciones.className = 'acciones';
 boton('Configuración', async() => {
 try {
 const r = await peticion('admin/configuracion');root.replaceChildren();encabezado(root, 'Configuración');for(const x of r.datos) {
 const a = tarjeta(root, x.clave, [x.descripcion, x.valor]);if(Number(x.editable))boton('Editar', async() => {
 const valor = prompt(x.clave, x.valor);if(valor === null)return;try {
 await peticion(`admin/configuracion/${encodeURIComponent(x.clave)}`, {
 method: 'PUT', body: JSON.stringify( {
 valor
              })
            });mostrar('Configuración guardada.');await cargarPanel()
          }
 catch(e) {
 mostrar(e.message, true)
          }
        }, a)
      }
    }
 catch(e) {
 mostrar(e.message, true)
    }
  }, acciones);
 boton('Catálogos', () => gestionarCatalogos(root), acciones);
 boton('Bitácora', () => gestionarBitacora(root), acciones);
 root.append(acciones)
}

// Herramientas administrativas de catálogos, reportes y cuentas.
const camposCatalogo = {
 areas: ['codigo', 'nombre', 'descripcion'], carreras: ['codigo', 'nombre'], modalidades: ['codigo', 'nombre'], estados: ['tipo_entidad', 'codigo', 'nombre', 'descripcion', 'color', 'orden'], mensajes: ['codigo', 'texto', 'tipo'], transiciones: ['entidad', 'id_estado_origen', 'id_estado_destino', 'id_rol_permitido', 'requiere_comentario']
};

async function gestionarCatalogos(root) {
 root.replaceChildren();
 encabezado(root, 'Mantenimiento de catálogos');
 const f = document.createElement('form');
 f.className = 'formulario';
 const sel = document.createElement('select');
 sel.name = 'catalogo';
 const opciones = [['areas', 'Áreas'], ['carreras', 'Carreras'], ['modalidades', 'Modalidades'], ['mensajes', 'Mensajes']];
 if(estado.usuario?.rol_codigo === 'superadmin')opciones.push(['estados', 'Estados'], ['transiciones', 'Transiciones de estado']);
 for(const [codigo, nombre] of opciones) {
 const o = document.createElement('option');
 o.value = codigo;
 o.textContent = nombre;
 sel.append(o)
  }
 const borrados = document.createElement('input');
 borrados.type = 'checkbox';
 borrados.name = 'eliminados';
 const etiqueta = document.createElement('label');
 etiqueta.append(borrados, document.createTextNode(' Mostrar eliminados'));
 const nuevo = document.createElement('button');
 nuevo.type = 'button';
 nuevo.className = 'boton primario';
 nuevo.textContent = 'Crear registro';
 f.append(sel, etiqueta, nuevo);
 root.append(f);
 const lista = document.createElement('div');
 lista.className = 'tarjetas';
 root.append(lista);
 const refrescar = async() => {
 try {
 const q = borrados.checked?'?eliminados=1': '';
 const r = await peticion(`admin/catalogos/${sel.value}${q}`);
 lista.replaceChildren();
 for(const item of r.datos) {
 const pk = {
 areas: 'id_area', carreras: 'id_carrera', modalidades: 'id_modalidad', mensajes: 'id_mensaje', estados: 'id_estado', transiciones: 'id_transicion'
        }
 [sel.value];
 const id = item[pk];
 const campos = camposCatalogo[sel.value];
 const resumen = campos.map(c => `${c}: ${item[c]??''}`);
 const a = tarjeta(lista, `${sel.value} #${id}`, resumen);
 if(borrados.checked) {
 boton('Restaurar', async() => {
 try {
 await peticion(`admin/catalogos/${sel.value}/${id}/restaurar`, {
 method: 'POST', body: '{}'
              });await refrescar()
            }
 catch(e) {
 mostrar(e.message, true)
            }
          }, a)
        }
 else {
 boton('Editar', async() => {
 const datos = {};for(const campo of campos) {
 const valor = prompt(`Valor de ${campo}:`, item[campo] ?? '');if(valor === null)return;datos[campo] = campo === 'requiere_comentario'?/^(1|true|si)$/i.test(valor): (['orden', 'id_estado_origen', 'id_estado_destino', 'id_rol_permitido'].includes(campo)?Number(valor): valor)
            }
 try {
 await peticion(`admin/catalogos/${sel.value}/${id}`, {
 method: 'PUT', body: JSON.stringify(datos)
              });await refrescar()
            }
 catch(e) {
 mostrar(e.message, true)
            }
          }, a);
 boton('Eliminar', async() => {
 const confirmacion = await confirmarAccion('El registro se ocultará del catálogo activo.', {titulo: 'Eliminar registro', textoConfirmar: 'Eliminar'});
 if(!confirmacion.confirmado)return;try {
 await peticion(`admin/catalogos/${sel.value}/${id}/eliminar`, {
 method: 'DELETE'
              });await refrescar()
            }
 catch(e) {
 mostrar(e.message, true)
            }
          }, a)
        }
      }
    }
 catch(e) {
 mostrar(e.message, true)
    }
  };
 sel.onchange = refrescar;
 borrados.onchange = refrescar;
 nuevo.onclick = async() => {
 const datos = {};
 for(const campo of camposCatalogo[sel.value]) {
 const valor = prompt(`Valor de ${campo}:`);
 if(valor === null)return;
 datos[campo] = campo === 'requiere_comentario'?/^(1|true|si)$/i.test(valor): (['orden', 'id_estado_origen', 'id_estado_destino', 'id_rol_permitido'].includes(campo)?Number(valor): valor)
    }
 try {
 await peticion(`admin/catalogos/${sel.value}`, {
 method: 'POST', body: JSON.stringify(datos)
      });
 await refrescar()
    }
 catch(e) {
 mostrar(e.message, true)
    }
  };
 await refrescar()
}

async function gestionarBitacora(root) {
 root.replaceChildren();
 encabezado(root, 'Consulta de bitácora');
 let pagina = 1;
 const filtros = document.createElement('form');
 filtros.className = 'filtros';
 const defs = [['tabla', 'Tabla'], ['accion', 'Acción'], ['usuario_id', 'ID de usuario'], ['desde', 'Desde'], ['hasta', 'Hasta']];
 for(const [name, label] of defs) {
 const l = document.createElement('label');
 l.textContent = label;
 const i = document.createElement('input');
 i.name = name;
 if(name === 'desde' || name === 'hasta')i.type = 'date';
 if(name === 'usuario_id')i.type = 'number';
 l.append(i);
 filtros.append(l)
  }
 const buscar = document.createElement('button');
 buscar.className = 'boton secundario';
 buscar.textContent = 'Filtrar';
 filtros.append(buscar);
 root.append(filtros);
 const resultado = document.createElement('div');
 root.append(resultado);
 const controles = document.createElement('div');
 controles.className = 'paginador';
 const anterior = document.createElement('button');
 anterior.type = 'button';
 anterior.className = 'boton secundario';
 anterior.textContent = 'Anterior';
 const estadoPagina = document.createElement('span');
 const siguiente = document.createElement('button');
 siguiente.type = 'button';
 siguiente.className = 'boton secundario';
 siguiente.textContent = 'Siguiente';
 controles.append(anterior, estadoPagina, siguiente);
 root.append(controles);
 const cargar = async() => {
 const q = new URLSearchParams( {
 pagina: String(pagina)
    });
 for(const [k, v] of Object.entries(formulario(filtros)))if(v)q.set(k, v);
 try {
 const r = await peticion(`admin/bitacora?${q}`);
 resultado.replaceChildren();
 tabla(resultado, 'Eventos', r.datos.elementos, ['fecha_hora', 'tabla_afectada', 'id_registro', 'accion', 'usuario', 'ip_cliente']);
 estadoPagina.textContent = `Página ${pagina}`;
 anterior.disabled = pagina <= 1;
 siguiente.disabled = r.datos.elementos.length < Number(r.datos.por_pagina)
    }
 catch(e) {
 mostrar(e.message, true)
    }
  };
 filtros.onsubmit = async e => {
 e.preventDefault();
 pagina = 1;
 await cargar()
  };
 anterior.onclick = async() => {
 pagina = Math.max(1, pagina-1);
 await cargar()
  };
 siguiente.onclick = async() => {
 pagina++;
 await cargar()
  };
 await cargar()
}

function tabla(root, titulo, filas, campos) {
 const h = document.createElement('h3');
 h.textContent = titulo;
 root.append(h);
 if(!Array.isArray(filas))filas = [];
 const wrap = document.createElement('div');
 wrap.className = 'tabla-contenedor';
 const table = document.createElement('table');
 const thead = document.createElement('thead');
 const tr = document.createElement('tr');
 for(const f of campos) {
 const th = document.createElement('th');
 th.textContent = f.replaceAll('_', ' ');
 tr.append(th)
  }
 thead.append(tr);
 table.append(thead);
 const tbody = document.createElement('tbody');
 if (!filas.length) {
  const row = document.createElement('tr');const cell = document.createElement('td');cell.colSpan = Math.max(1, campos.length);cell.textContent = 'No hay registros.';row.append(cell);tbody.append(row);
 }
 for(const fila of filas) {
 const row = document.createElement('tr');
 for(const f of campos) {
 const td = document.createElement('td');
 td.textContent = esc(fila[f]);
 row.append(td)
    }
 tbody.append(row)
  }
 table.append(tbody);
 wrap.append(table);
 root.append(wrap)
}

function formularioUsuario(root, rolActor) {
 encabezado(root, 'Crear cuenta gestionada');
 const f = document.createElement('form');
 f.className = 'formulario columnas panel';
 f.enctype = 'multipart/form-data';
 const roles = estado.catalogos.filter(x => x.tipo === 'rol' && x.codigo !== 'sistema' && (rolActor === 'superadmin' || x.codigo !== 'superadmin'));
 const l = document.createElement('label');
 l.textContent = 'Perfil';
 const select = document.createElement('select');
 select.name = 'rol_codigo';
 select.required = true;
 for(const x of roles) {
 const o = document.createElement('option');
 o.value = x.codigo;
 o.textContent = x.nombre;
 select.append(o)
  }
 l.append(select);
 f.append(l);
 const defs = [['usuario', 'Usuario', 'text'], ['correo', 'Correo de acceso', 'email'], ['contrasena', 'Contraseña temporal', 'password'], ['nombres', 'Nombres', 'text'], ['apellidos', 'Apellidos', 'text'], ['telefono', 'Teléfono', 'tel'], ['carnet', 'Carné', 'text'], ['id_carrera', 'Carrera', 'catalogo'], ['universidad', 'Universidad', 'text'], ['semestre', 'Semestre', 'text'], ['nombre_legal', 'Nombre legal', 'text'], ['nombre_comercial', 'Nombre comercial', 'text'], ['correo_contacto', 'Correo de contacto', 'email'], ['identificacion_fiscal', 'Identificación fiscal', 'text'], ['sector', 'Sector', 'text'], ['sitio_web', 'Sitio web', 'url'], ['direccion', 'Dirección', 'text'], ['descripcion', 'Descripción', 'textarea']];
 const maximos = {usuario: 40, correo: 254, contrasena: 200, nombres: 100, apellidos: 100, telefono: 8, carnet: 40, universidad: 180, semestre: 40, nombre_legal: 180, nombre_comercial: 180, correo_contacto: 254, identificacion_fiscal: 50, sector: 100, sitio_web: 255, direccion: 255, descripcion: 5000};
 for(const [name, label, type] of defs) {
 const field = document.createElement('label');
 field.dataset.campo = name;
 field.textContent = label;
 let input;
 if(type === 'textarea')input = document.createElement('textarea');
 else if(type === 'catalogo') {
 input = document.createElement('select');
 const placeholder = document.createElement('option');placeholder.value = '';placeholder.textContent = 'Selecciona una carrera';input.append(placeholder);
 for(const c of estado.catalogos.filter(c => c.tipo === 'carrera')) {
 const o = document.createElement('option');
 o.value = c.id;
 o.textContent = c.nombre;
 input.append(o)
      }
    }
 else {
 input = document.createElement('input');
 input.type = type
    }
 input.name = name;
 if(name === 'contrasena')input.minLength = estado.passwordMinima;
 if(maximos[name])input.maxLength = maximos[name];
 if(['usuario', 'correo', 'contrasena', 'nombres', 'apellidos'].includes(name))input.required = true;
 if(name === 'telefono') {
 input.maxLength = 8;
 input.inputMode = 'numeric';input.pattern = '[0-9]{8}'
    }
 if(name === 'id_carrera')input.required = true;
 field.append(input);
 f.append(field)
  }
 const cv = document.createElement('label');
 cv.dataset.campo = 'cv';
 cv.textContent = 'CV PDF';
 const file = document.createElement('input');
 file.type = 'file';
 file.name = 'cv';
 file.accept = 'application/pdf,.pdf';
 if(estado.cvMaximo > 0)file.dataset.tamanoMaximo = String(estado.cvMaximo);
 cv.append(file);
 f.append(cv);
 const btn = document.createElement('button');
 btn.className = 'boton primario';
 btn.textContent = 'Crear cuenta';
 f.append(btn);
 const ajustar = () => {
 const codigo = select.value;
 for(const campo of ['carnet', 'id_carrera', 'universidad', 'semestre']) {
  const etiqueta = f.querySelector(`[data-campo="${campo}"]`);etiqueta.hidden = codigo !== 'estudiante';
  etiqueta.querySelector('input,select').required = codigo === 'estudiante' && campo !== 'semestre';
 }
 for(const campo of ['nombre_legal', 'nombre_comercial', 'correo_contacto', 'identificacion_fiscal', 'sector', 'sitio_web', 'direccion', 'descripcion']) {
  const etiqueta = f.querySelector(`[data-campo="${campo}"]`);etiqueta.hidden = codigo !== 'empresa';
  etiqueta.querySelector('input,textarea').required = codigo === 'empresa' && ['nombre_legal', 'correo_contacto'].includes(campo);
 }
 f.elements.namedItem('telefono').required = ['estudiante', 'empresa'].includes(codigo);
 cv.hidden = codigo !== 'estudiante';
 file.required = codigo === 'estudiante';
  };
 select.onchange = ajustar;
 ajustar();
 f.onsubmit = async e => {
 e.preventDefault();
 if(!f.reportValidity())return;
 if(select.value === 'estudiante') {
  const archivo = file.files?.[0];
  if(archivo) {
   const pdf = archivo.name.toLowerCase().endsWith('.pdf') && (!archivo.type || archivo.type === 'application/pdf');
   if(!pdf) {pintarErroresCampos(f, {cv: 'Selecciona un archivo PDF.'});return;}
   if(estado.cvMaximo > 0 && archivo.size > estado.cvMaximo) {pintarErroresCampos(f, {cv: `El archivo supera el máximo permitido (${Math.ceil(estado.cvMaximo / 1048576)} MB).`});return;}
  }
 }
 limpiarErroresFormulario(f);
 try {
 await durantePeticion(btn, async () => {
  const r = await peticion('admin/usuarios', {method: 'POST', body: new FormData(f)});
  mostrar(r.mensaje);f.reset();ajustar();await cargarPanel();
 }, 'Creando cuenta…');
    }
 catch(err) {
 if(Object.keys(err.errores || {}).length)pintarErroresCampos(f, err.errores);
 mostrar(err.message, true)
    }
  };
 root.append(f)
}

async function verDetalleUsuario(id) {
 const respuesta = await peticion(`admin/usuarios/${id}`);
 const usuario = respuesta.datos;
 const dialogo = document.createElement('dialog');dialogo.className = 'dialogo';
 const articulo = document.createElement('article');articulo.className = 'detalle-practica';
 const titulo = document.createElement('h2');titulo.textContent = `${usuario.nombres || ''} ${usuario.apellidos || ''}`.trim() || 'Cuenta';articulo.append(titulo);
 const campos = [
  ['Usuario', usuario.usuario], ['Correo', usuario.correo], ['Teléfono', usuario.telefono],
  ['Rol', usuario.rol_nombre || usuario.rol], ['Estado', usuario.estado_nombre || usuario.estado],
  ['Carrera', usuario.carrera], ['Carné', usuario.carnet], ['Universidad', usuario.universidad],
  ['Empresa', usuario.nombre_legal || usuario.nombre_comercial], ['Estado de empresa', usuario.empresa_estado],
  ['Creación', usuario.creado_en || usuario.fecha_creacion]
 ];
 campos.filter(([, valor]) => valor !== null && valor !== undefined && valor !== '').forEach(([nombre, valor]) => {
  const fila = document.createElement('p');const etiqueta = document.createElement('strong');etiqueta.textContent = `${nombre}: `;
  fila.append(etiqueta, document.createTextNode(String(valor)));articulo.append(fila);
 });
 const cerrar = document.createElement('button');cerrar.type = 'button';cerrar.className = 'boton secundario';cerrar.textContent = 'Cerrar';cerrar.onclick = () => dialogo.close();articulo.append(cerrar);
 dialogo.append(articulo);dialogo.addEventListener('close', () => dialogo.remove(), {once: true});document.body.append(dialogo);dialogo.showModal();cerrar.focus();
}

async function editarUsuario(usuario, alGuardar) {
 const dialogo = document.createElement('dialog');dialogo.className = 'dialogo';
 const form = document.createElement('form');form.className = 'formulario detalle-practica';
 const titulo = document.createElement('h2');titulo.textContent = 'Editar datos de cuenta';form.append(titulo);
 const definiciones = [['nombres', 'Nombres', 'text'], ['apellidos', 'Apellidos', 'text'], ['correo', 'Correo', 'email'], ['telefono', 'Teléfono', 'tel']];
 definiciones.forEach(([nombre, texto, tipo]) => {
  const label = document.createElement('label');label.textContent = texto;
  const input = document.createElement('input');input.name = nombre;input.type = tipo;input.value = usuario[nombre] || '';
  input.maxLength = nombre === 'correo' ? 254 : nombre === 'telefono' ? 8 : 100;
  if (nombre !== 'telefono' || ['estudiante', 'empresa'].includes(usuario.rol_codigo)) input.required = true;
  if (nombre === 'telefono') { input.inputMode = 'numeric';input.pattern = '[0-9]{8}'; }
  label.append(input);form.append(label);
 });
 const acciones = document.createElement('div');acciones.className = 'acciones';
 const cancelar = document.createElement('button');cancelar.type = 'button';cancelar.className = 'boton secundario';cancelar.textContent = 'Cancelar';cancelar.onclick = () => dialogo.close();
 const guardar = document.createElement('button');guardar.type = 'submit';guardar.className = 'boton primario';guardar.textContent = 'Guardar';acciones.append(cancelar, guardar);form.append(acciones);
 form.onsubmit = async (evento) => {
  evento.preventDefault();if (!form.reportValidity()) return;
  try {
   await durantePeticion(guardar, async () => {
    await peticion(`admin/usuarios/${usuario.id_usuario}`, {method: 'PUT', body: JSON.stringify(formulario(form))});
    dialogo.close();mostrar('Datos de la cuenta actualizados.');await alGuardar();
   }, 'Guardando…');
  } catch (error) { if (Object.keys(error.errores || {}).length) pintarErroresCampos(form, error.errores);mostrar(error.message, true); }
 };
 dialogo.append(form);dialogo.addEventListener('close', () => dialogo.remove(), {once: true});document.body.append(dialogo);dialogo.showModal();form.elements.namedItem('nombres').focus();
}

function mostrarClaveTemporal(clave) {
 const dialogo = document.createElement('dialog');dialogo.className = 'dialogo';
 const articulo = document.createElement('article');articulo.className = 'detalle-practica';
 const titulo = document.createElement('h2');titulo.textContent = 'Contraseña temporal';
 const aviso = document.createElement('p');aviso.textContent = 'Cópiala ahora. Se muestra una sola vez y la cuenta tendrá que cambiarla al iniciar sesión.';
 const valor = document.createElement('p');valor.className = 'valor-temporal';valor.textContent = clave;
 const cerrar = document.createElement('button');cerrar.type = 'button';cerrar.className = 'boton primario';cerrar.textContent = 'Cerrar';cerrar.onclick = () => dialogo.close();
 articulo.append(titulo, aviso, valor, cerrar);dialogo.append(articulo);dialogo.addEventListener('close', () => dialogo.remove(), {once: true});document.body.append(dialogo);dialogo.showModal();cerrar.focus();
}

async function gestionarUsuarios(root, filasIniciales, rol) {
 const caja = document.createElement('section');caja.className = 'panel';encabezado(caja, 'Usuarios');
 const buscar = document.createElement('form');buscar.className = 'filtros';
 const entrada = document.createElement('input');entrada.name = 'q';entrada.placeholder = 'Buscar por usuario, correo o nombre';entrada.setAttribute('aria-label', 'Buscar usuarios');
 const enviar = document.createElement('button');enviar.className = 'boton secundario';enviar.textContent = 'Buscar';buscar.append(entrada, enviar);caja.append(buscar);
 const lista = document.createElement('div');lista.className = 'tarjetas';caja.append(lista);
 const controles = document.createElement('nav');controles.className = 'paginador';controles.setAttribute('aria-label', 'Paginación de usuarios');
 const anterior = document.createElement('button');anterior.type = 'button';anterior.className = 'boton secundario';anterior.textContent = 'Anterior';
 const paginaTexto = document.createElement('span');
 const siguiente = document.createElement('button');siguiente.type = 'button';siguiente.className = 'boton secundario';siguiente.textContent = 'Siguiente';
 controles.append(anterior, paginaTexto, siguiente);caja.append(controles);
 let pagina = 1;const porPagina = 20;
 const pintar = (filas) => {
  lista.replaceChildren();
  if (!filas.length) { const vacio = document.createElement('p');vacio.textContent = 'No hay cuentas que coincidan con la búsqueda.';lista.append(vacio);return; }
  filas.forEach((usuario) => {
   const tarjetaUsuario = tarjeta(lista, `${usuario.nombres || ''} ${usuario.apellidos || ''}`.trim() || usuario.usuario, [`${usuario.usuario} · ${usuario.rol_nombre || usuario.rol || usuario.rol_codigo}`, usuario.correo]);
   etiquetaEstado(tarjetaUsuario, usuario.estado_nombre || usuario.estado, 'usuario', usuario.estado_codigo);
   boton('Ver detalles', () => verDetalleUsuario(usuario.id_usuario), tarjetaUsuario);
   boton('Editar datos', () => editarUsuario(usuario, cargar), tarjetaUsuario);
   boton('Cambiar estado', async () => {
    const transicion = await elegirTransicion('usuario', usuario.estado_codigo);
    if (!transicion) return;
    await peticion(`admin/usuarios/${usuario.id_usuario}/estado`, {method: 'PUT', body: JSON.stringify({estado_codigo: transicion.destino.estado_codigo, comentario: transicion.comentario})});
    mostrar('Estado de la cuenta actualizado.');await cargar();
   }, tarjetaUsuario);
   if (rol === 'superadmin') boton('Restablecer contraseña', async () => {
    const resultado = await peticion(`admin/usuarios/${usuario.id_usuario}/restablecer-clave`, {method: 'POST', body: '{}'});
    mostrarClaveTemporal(resultado.datos.contrasena_temporal);
   }, tarjetaUsuario);
   boton('Eliminar cuenta', async () => {
    const confirmacion = await confirmarAccion('Se dará de baja la cuenta y se cancelarán las relaciones activas asociadas.', {titulo: 'Eliminar cuenta', textoConfirmar: 'Eliminar cuenta'});
    if (!confirmacion.confirmado) return;
    await peticion(`admin/usuarios/${usuario.id_usuario}`, {method: 'DELETE'});mostrar('Cuenta eliminada lógicamente.');await cargar();
   }, tarjetaUsuario);
  });
 };
 const cargar = async () => {
  const consulta = new URLSearchParams({pagina: String(pagina), por_pagina: String(porPagina)});
  if (entrada.value.trim()) consulta.set('q', entrada.value.trim());
  try {
   const respuesta = await peticion(`admin/usuarios?${consulta}`);
   const filas = respuesta.datos.elementos || [];pintar(filas);
   const limite = Number(respuesta.datos.por_pagina || porPagina);
   paginaTexto.textContent = `Página ${pagina}`;anterior.disabled = pagina <= 1;siguiente.disabled = filas.length < limite;
  } catch (error) { mostrar(error.message, true); }
 };
 buscar.onsubmit = async (evento) => {evento.preventDefault();pagina = 1;await cargar();};
 anterior.onclick = async () => {pagina = Math.max(1, pagina - 1);await cargar();};
 siguiente.onclick = async () => {pagina += 1;await cargar();};
 root.append(caja);
 if (Array.isArray(filasIniciales)) pintar(filasIniciales);
 await cargar();
}

async function gestionarRestauracion(root) {
 encabezado(root, 'Cuentas eliminadas');
 const lista = document.createElement('div');lista.className = 'tarjetas';root.append(lista);
 const controles = document.createElement('nav');controles.className = 'paginador';controles.setAttribute('aria-label', 'Paginación de cuentas eliminadas');
 const anterior = document.createElement('button');anterior.type = 'button';anterior.className = 'boton secundario';anterior.textContent = 'Anterior';
 const estadoPagina = document.createElement('span');
 const siguiente = document.createElement('button');siguiente.type = 'button';siguiente.className = 'boton secundario';siguiente.textContent = 'Siguiente';
 controles.append(anterior, estadoPagina, siguiente);root.append(controles);
 let pagina = 1;const porPagina = 20;
 const cargar = async () => {
  try {
   const respuesta = await peticion(`admin/usuarios?eliminados=1&pagina=${pagina}&por_pagina=${porPagina}`);
   const filas = respuesta.datos.elementos || [];lista.replaceChildren();
   if (!filas.length) { const vacio = document.createElement('p');vacio.textContent = 'No hay cuentas eliminadas.';lista.append(vacio); }
   filas.forEach((usuario) => {
    const item = tarjeta(lista, `${usuario.nombres || ''} ${usuario.apellidos || ''}`.trim() || usuario.usuario, [`${usuario.usuario} · ${usuario.rol_nombre || usuario.rol || usuario.rol_codigo}`, usuario.correo]);
    boton('Restaurar cuenta', async () => {
     const confirmacion = await confirmarAccion('La cuenta volverá a estar activa.', {titulo: 'Restaurar cuenta', textoConfirmar: 'Restaurar'});
     if (!confirmacion.confirmado) return;
     await peticion(`admin/usuarios/${usuario.id_usuario}/restaurar`, {method: 'POST', body: '{}'});mostrar('Cuenta restaurada.');await cargar();
    }, item);
   });
   const limite = Number(respuesta.datos.por_pagina || porPagina);
   estadoPagina.textContent = `Página ${pagina}`;anterior.disabled = pagina <= 1;siguiente.disabled = filas.length < limite;
  } catch (error) { mostrar(error.message, true); }
 };
 anterior.onclick = async () => {pagina = Math.max(1, pagina - 1);await cargar();};
 siguiente.onclick = async () => {pagina += 1;await cargar();};
 await cargar();
}
async function iniciarModulo() {
 const modulo = document.body.dataset.module;
 if (!modulo) {
  if (document.body.dataset.page !== 'practicas-disponibles') return;
  const lista = $('#lista-practicas');
  const filtros = $('#filtros');
  filtros?.addEventListener('submit', (evento) => {
   evento.preventDefault();
   estado.pagina = 1;
   void cargarPracticas(vistaPublica);
  });
  filtros?.addEventListener('reset', () => {
   estado.pagina = 1;
   setTimeout(() => void cargarPracticas(vistaPublica), 0);
  });
  $('#pagina-anterior')?.addEventListener('click', () => {
   estado.pagina = Math.max(1, estado.pagina - 1);
   void cargarPracticas(vistaPublica);
  });
  $('#pagina-siguiente')?.addEventListener('click', () => {
   estado.pagina++;
   void cargarPracticas(vistaPublica);
  });
  const spinner = crearSpinner('Cargando prácticas…');
  lista?.replaceChildren(spinner);
  try {
   await cargarCatalogos();
   const sesion = await requerirSesion([], { silenciar401: true });
   if (sesion.ok) {
    estado.usuario = sesion.usuario;
    estado.csrf = sesion.usuario.csrf_token || null;
    ponerSesion();
    const accesos = $('#botones-login');
    if (accesos) accesos.hidden = true;
   }
   spinner.remove();
   await cargarPracticas(vistaPublica);
  } catch (error) {
   spinner.remove();
   mostrar(error.message || 'No se pudieron cargar las prácticas.', true);
  }
  return;
 }
 const roles = (document.body.dataset.roles || '').split(',').filter(Boolean);
 const root = $('#contenido-modulo');
 const spinner = crearSpinner('Consultando tu sesión…');root?.replaceChildren(spinner);
 try {
  const sesion = await requerirSesion(roles);
  if (!sesion.ok) {
   spinner.remove();
   if (sesion.respuesta?.estado === 0) mostrar(sesion.respuesta.mensaje, true);
   return;
  }
  estado.usuario = sesion.usuario;
  estado.csrf = sesion.usuario.csrf_token || null;
  if (sesion.usuario.debe_cambiar_password) {
   location.replace(`${appBase}/public/cambiar-clave.html`);
   return;
  }
  await cargarCatalogos();
  ponerSesion();
  spinner.remove();
  await cargarModuloActual();
 } catch (error) {
  spinner.remove();
  throw error;
 }
}

iniciarModulo().catch((error) => {
 console.error('No se pudo cargar la pantalla.', error);
 mostrar(error.message || 'No se pudo cargar la pantalla.', true);
});
