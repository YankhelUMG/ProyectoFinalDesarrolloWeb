import { apiFetch } from './api.js';
import { guardarSesion, requerirSesion, redirigirAlPanel, urlPanel } from './sesion.js';
import { durantePeticion, mostrarAlerta, pintarErroresCampos, limpiarErroresCampos } from './ui.js';

const appBaseMeta = document.querySelector('meta[name="app-base"]');
const appBase = appBaseMeta ? appBaseMeta.content : new URL('../../../', import.meta.url).pathname.replace(/\/$/, '');
const pagina = document.body.dataset.paginaAcceso;
const destinoLogin = `${appBase}/public/login.html`;
let csrfInicializado = false;
let configuracion = {};
let estadosEmpresa = [];

function irA(ruta) {
  location.assign(`${appBase}/${String(ruta).replace(/^\/+/, '')}`);
}

function mostrarEnFormulario(formulario, mensaje, tipo = 'error') {
  let aviso = formulario.querySelector('[data-mensaje-formulario]');
  if (!aviso) {
    aviso = document.createElement('p');
    aviso.dataset.mensajeFormulario = '';
    aviso.setAttribute('role', tipo === 'error' ? 'alert' : 'status');
    formulario.prepend(aviso);
  }
  aviso.className = tipo === 'error' ? 'error-formulario' : 'exito-formulario';
  aviso.textContent = mensaje;
}

function mostrarResultadoError(formulario, respuesta) {
  const errores = respuesta.errores || {};
  if (Object.keys(errores).length) pintarErroresCampos(formulario, errores);
  mostrarEnFormulario(formulario, respuesta.mensaje || 'No se pudo completar la solicitud.');
}

async function inicializarCsrf() {
  const respuesta = await apiFetch('GET', 'csrf');
  if (!respuesta.ok) return false;
  csrfInicializado = Boolean(respuesta.datos?.csrf_token);
  return csrfInicializado;
}

async function cargarConfiguracion() {
  const respuesta = await apiFetch('GET', 'sistema');
  if (!respuesta.ok) return;
  configuracion = respuesta.datos || {};
  const minimo = Math.max(8, Number(configuracion.password_longitud_minima || 10));
  document.querySelectorAll('input[name="contrasena"], input[name="contrasena_nueva"], input[name="confirmacion"]')
    .forEach((campo) => { campo.minLength = minimo; });
  document.querySelectorAll('[data-longitud-clave]').forEach((aviso) => {
    aviso.textContent = `La contraseña debe tener al menos ${minimo} caracteres.`;
  });
  document.querySelectorAll('input[name="cv"]').forEach((campo) => {
    const maximo = Number(configuracion.cv_tamano_maximo_bytes || 0);
    if (maximo > 0) {
      campo.dataset.tamanoMaximo = String(maximo);
      const aviso = campo.closest('label')?.querySelector('[data-cv-limite]');
      if (aviso) aviso.textContent = `Tamaño máximo: ${Math.ceil(maximo / 1048576)} MB. El servidor volverá a validar el archivo.`;
    }
  });
  if (configuracion.nombre_sistema) {
    document.querySelectorAll('[data-nombre-sistema]').forEach((elemento) => { elemento.textContent = configuracion.nombre_sistema; });
    document.title = `${configuracion.nombre_sistema} · Acceso`;
  }
}

async function cargarEstadosEmpresa() {
  const respuesta = await apiFetch('GET', 'catalogos?tipo=empresa');
  if (respuesta.ok && Array.isArray(respuesta.datos)) estadosEmpresa = respuesta.datos;
}

async function cargarCarreras() {
  const select = document.querySelector('[data-catalogo="carrera"]');
  if (!select) return;
  const respuesta = await apiFetch('GET', 'catalogos?tipo=carrera');
  if (!respuesta.ok || !Array.isArray(respuesta.datos)) return;
  const marcador = select.querySelector('option[value=""]');
  if (marcador) marcador.textContent = 'Selecciona una carrera';
  respuesta.datos.forEach((carrera) => {
    const opcion = document.createElement('option');
    opcion.value = carrera.id;
    opcion.textContent = carrera.nombre;
    select.append(opcion);
  });
}

async function validarSesionExistente() {
  const sesion = await requerirSesion([], { silenciar401: true });
  if (!sesion.ok) return false;
  if (sesion.usuario.debe_cambiar_password) irA('public/cambiar-clave.html');
  else if (sesion.usuario.rol_codigo === 'empresa' && sesion.usuario.empresa_estado_codigo !== 'aprobada') {
    irA('pages/perfil/index.php');
  } else redirigirAlPanel(sesion.usuario);
  return true;
}

function validarNavegador(formulario) {
  if (formulario.reportValidity()) return true;
  const campo = formulario.querySelector(':invalid');
  campo?.focus();
  return false;
}

function prepararBotonesClave() {
  document.querySelectorAll('[data-mostrar-clave]').forEach((boton) => {
    const campo = document.getElementById(boton.getAttribute('aria-controls'));
    if (!campo) return;
    boton.addEventListener('click', () => {
      const mostrar = campo.type === 'password';
      campo.type = mostrar ? 'text' : 'password';
      boton.textContent = mostrar ? 'Ocultar' : 'Mostrar';
      boton.setAttribute('aria-pressed', String(mostrar));
    });
  });
}

function validarCv(formulario) {
  const campo = formulario.elements.namedItem('cv');
  const archivo = campo?.files?.[0];
  if (!archivo) return true; // La regla required del formulario se encarga de indicarlo.
  limpiarErroresCampos(formulario);
  const esPdf = archivo.name.toLowerCase().endsWith('.pdf') && (!archivo.type || archivo.type === 'application/pdf');
  if (!esPdf) {
    pintarErroresCampos(formulario, { cv: 'Selecciona un archivo PDF.' });
    return false;
  }
  const maximo = Number(campo.dataset.tamanoMaximo || 0);
  if (maximo > 0 && archivo.size > maximo) {
    pintarErroresCampos(formulario, { cv: `El archivo supera el máximo permitido (${Math.ceil(maximo / 1048576)} MB).` });
    return false;
  }
  return true;
}

function estadoEmpresa(usuario) {
  const codigo = String(usuario.empresa_estado_codigo || '');
  return estadosEmpresa.find((estado) => estado.codigo === codigo)?.nombre || 'estado no disponible';
}

async function enviarLogin(formulario) {
  formulario.addEventListener('submit', async (evento) => {
    evento.preventDefault();
    if (!validarNavegador(formulario)) return;
    if (!csrfInicializado && !(await inicializarCsrf())) {
      mostrarEnFormulario(formulario, 'No se pudo iniciar una sesión segura. Actualiza la página e inténtalo de nuevo.');
      return;
    }
    const tipo = formulario.elements.namedItem('tipo_cuenta')?.value || '';
    const datos = {
      identificador: formulario.elements.namedItem('identificador').value.trim(),
      contrasena: formulario.elements.namedItem('contrasena').value,
    };
    if (tipo === 'estudiante' || tipo === 'empresa') datos.tipo_cuenta = tipo;
    limpiarErroresCampos(formulario);
    await durantePeticion(formulario.querySelector('[type="submit"]'), async () => {
      const respuesta = await apiFetch('POST', 'auth/login', datos);
      if (!respuesta.ok) {
        mostrarResultadoError(formulario, respuesta);
        return;
      }
      const actual = await apiFetch('GET', 'auth/me');
      if (!actual.ok) {
        mostrarResultadoError(formulario, actual);
        return;
      }
      const usuario = guardarSesion(actual.datos);
      if (!usuario) {
        mostrarEnFormulario(formulario, 'La API no devolvió los datos de la cuenta. Inténtalo de nuevo.');
        return;
      }
      if (usuario.debe_cambiar_password) {
        irA('public/cambiar-clave.html');
        return;
      }
      if (usuario.rol_codigo === 'empresa' && usuario.empresa_estado_codigo !== 'aprobada') {
        const estado = estadoEmpresa(usuario);
        mostrarEnFormulario(formulario, `Tu cuenta de empresa está en estado «${estado}». Te llevaremos al perfil para consultar la revisión.`, 'exito');
        setTimeout(() => irA('pages/perfil/index.php'), 1400);
        return;
      }
      location.assign(urlPanel(usuario));
    }, 'Ingresando…');
  });
}

async function enviarRegistroEstudiante(formulario) {
  formulario.addEventListener('submit', async (evento) => {
    evento.preventDefault();
    if (!validarNavegador(formulario) || !validarCv(formulario)) return;
    if (!csrfInicializado && !(await inicializarCsrf())) {
      mostrarEnFormulario(formulario, 'No se pudo iniciar una sesión segura. Actualiza la página e inténtalo de nuevo.');
      return;
    }
    limpiarErroresCampos(formulario);
    await durantePeticion(formulario.querySelector('[type="submit"]'), async () => {
      const respuesta = await apiFetch('POST', 'auth/registro-estudiante', new FormData(formulario));
      if (!respuesta.ok) {
        mostrarResultadoError(formulario, respuesta);
        return;
      }
      const usuario = guardarSesion(respuesta.datos);
      mostrarEnFormulario(formulario, respuesta.mensaje || 'Cuenta creada correctamente.', 'exito');
      setTimeout(() => {
        if (usuario) location.assign(urlPanel(usuario));
        else location.assign(destinoLogin);
      }, 900);
    }, 'Creando cuenta…');
  });
}

async function enviarRegistroEmpresa(formulario) {
  formulario.addEventListener('submit', async (evento) => {
    evento.preventDefault();
    if (!validarNavegador(formulario)) return;
    if (!csrfInicializado && !(await inicializarCsrf())) {
      mostrarEnFormulario(formulario, 'No se pudo iniciar una sesión segura. Actualiza la página e inténtalo de nuevo.');
      return;
    }
    limpiarErroresCampos(formulario);
    const datos = Object.fromEntries(new FormData(formulario).entries());
    await durantePeticion(formulario.querySelector('[type="submit"]'), async () => {
      const respuesta = await apiFetch('POST', 'auth/registro-empresa', datos);
      if (!respuesta.ok) {
        mostrarResultadoError(formulario, respuesta);
        return;
      }
      mostrarEnFormulario(formulario, respuesta.mensaje || 'Solicitud enviada. La cuenta quedará pendiente de revisión.', 'exito');
      formulario.reset();
      const enlace = document.querySelector('[data-ir-login]');
      if (enlace) enlace.hidden = false;
    }, 'Enviando solicitud…');
  });
}

async function enviarCambioClave(formulario) {
  const sesion = await requerirSesion();
  if (!sesion.ok) return;
  const usuario = sesion.usuario;
  if (usuario.debe_cambiar_password) {
    const aviso = document.querySelector('[data-aviso-clave]');
    if (aviso) aviso.textContent = 'Esta cuenta tiene una contraseña temporal. Crea una nueva para continuar.';
  }
  formulario.addEventListener('submit', async (evento) => {
    evento.preventDefault();
    if (!validarNavegador(formulario)) return;
    const nueva = formulario.elements.namedItem('contrasena_nueva').value;
    const confirmacion = formulario.elements.namedItem('confirmacion').value;
    if (nueva !== confirmacion) {
      pintarErroresCampos(formulario, { confirmacion: 'Las contraseñas no coinciden.' });
      return;
    }
    limpiarErroresCampos(formulario);
    await durantePeticion(formulario.querySelector('[type="submit"]'), async () => {
      const respuesta = await apiFetch('POST', 'auth/cambiar-clave', { contrasena_nueva: nueva, confirmacion });
      if (!respuesta.ok) {
        mostrarResultadoError(formulario, respuesta);
        return;
      }
      mostrarEnFormulario(formulario, respuesta.mensaje || 'Contraseña actualizada.', 'exito');
      const actualizada = await apiFetch('GET', 'auth/me');
      if (actualizada.ok) {
        const cuenta = guardarSesion(actualizada.datos);
        setTimeout(() => {
          if (cuenta?.rol_codigo === 'empresa' && cuenta.empresa_estado_codigo !== 'aprobada') irA('pages/perfil/index.php');
          else if (cuenta) redirigirAlPanel(cuenta);
        }, 800);
      }
    }, 'Guardando…');
  });
}

async function iniciarPagina() {
  prepararBotonesClave();
  const csrfOk = await inicializarCsrf();
  await cargarConfiguracion();
  if (pagina === 'login') {
    await cargarEstadosEmpresa();
    if (await validarSesionExistente()) return;
    const formulario = document.querySelector('[data-form-login]');
    if (formulario) await enviarLogin(formulario);
  } else if (pagina === 'registro-estudiante') {
    await cargarCarreras();
    const formulario = document.querySelector('[data-form-registro-estudiante]');
    if (formulario) await enviarRegistroEstudiante(formulario);
  } else if (pagina === 'registro-empresa') {
    const formulario = document.querySelector('[data-form-registro-empresa]');
    if (formulario) await enviarRegistroEmpresa(formulario);
  } else if (pagina === 'cambiar-clave') {
    const formulario = document.querySelector('[data-form-cambiar-clave]');
    if (formulario) await enviarCambioClave(formulario);
  }
  document.querySelector('form.formulario-acceso')
    ?.querySelector('button[type="submit"]')?.removeAttribute('disabled');
  if (!csrfOk) mostrarAlerta('No se pudo preparar la sesión segura. Actualiza la página antes de enviar formularios.', 'error');
}

iniciarPagina().catch((error) => {
  console.error('No se pudo preparar la pantalla de acceso.', error);
  mostrarAlerta('No se pudo preparar la pantalla. Revisa tu conexión e inténtalo de nuevo.', 'error');
});
