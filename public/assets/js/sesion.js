// Estado local de la cuenta y navegación según el rol devuelto por la API.
import { apiFetch, establecerCsrf, guardarUsuario, limpiarSesionLocal, CLAVE_USUARIO } from './api.js';

const appBaseMeta = document.querySelector('meta[name="app-base"]');
const appBase = appBaseMeta ? appBaseMeta.content : new URL('../../../', import.meta.url).pathname.replace(/\/$/, '');
const destinoPorRol = {
  estudiante: 'pages/practicas/index.php',
  empresa: 'pages/empresa/index.php',
  admin: 'pages/administracion/index.php',
  superadmin: 'pages/administracion/index.php',
};

function urlAplicacion(ruta) {
  return `${appBase}/${String(ruta).replace(/^\/+/, '')}`;
}

export function obtenerUsuarioLocal() {
  try {
    const guardado = sessionStorage.getItem(CLAVE_USUARIO);
    return guardado ? JSON.parse(guardado) : null;
  } catch (error) {
    console.error('El usuario guardado en el navegador no se pudo leer.', error);
    guardarUsuario(null);
    return null;
  }
}

export function guardarSesion(usuario) {
  if (!usuario || typeof usuario !== 'object') {
    limpiarSesionLocal();
    return null;
  }
  guardarUsuario(usuario);
  if (usuario.csrf_token) establecerCsrf(usuario.csrf_token);
  return usuario;
}

export function urlPanel(usuario) {
  const destino = destinoPorRol[usuario?.rol_codigo];
  return destino ? urlAplicacion(destino) : urlAplicacion('public/403.html');
}

export function redirigirAlPanel(usuario) {
  location.assign(urlPanel(usuario));
}

/** Consulta la sesión vigente y, opcionalmente, permite una lista de roles. */
export async function requerirSesion(rolesPermitidos = [], opciones = {}) {
  const respuesta = await apiFetch('GET', 'auth/me', null, opciones);
  if (!respuesta.ok) {
    if (opciones.silenciar401) guardarUsuario(null);
    else limpiarSesionLocal();
    return { ok: false, usuario: null, respuesta };
  }

  const usuario = guardarSesion(respuesta.datos);
  if (rolesPermitidos.length && !rolesPermitidos.includes(usuario?.rol_codigo)) {
    redirigirAlPanel(usuario);
    return { ok: false, usuario, respuesta, redirigido: true };
  }
  return { ok: true, usuario, respuesta };
}

export async function cerrarSesion() {
  const respuesta = await apiFetch('DELETE', 'auth/logout');
  limpiarSesionLocal();
  location.assign(`${appBase}/public/login.html`);
  return respuesta;
}

document.addEventListener('api:sesion-expirada', () => limpiarSesionLocal());
