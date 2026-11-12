// Sesión del navegador: menú por rol, redirección al panel y cierre de sesión.
import {
  appBase,
  apiFetch,
  establecerCsrf,
  guardarUsuario,
  limpiarSesionLocal,
  CLAVE_USUARIO,
} from './api.js';

// Estado local de la cuenta y navegación según el rol devuelto por la API.

const destinoPorRol = {
  estudiante: 'pages/practicas.php',
  empresa: 'pages/empresa.php',
  admin: 'pages/practicas.php',
  superadmin: 'pages/practicas.php',
};

// Dirección base de la aplicación.
function urlAplicacion(ruta) {
  return `${appBase}/${String(ruta).replace(/^\/+/, '')}`;
}

// Usuario guardado en el navegador.
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

// Guarda la sesión tras iniciar.
export function guardarSesion(usuario) {
  if (!usuario || typeof usuario !== 'object') {
    limpiarSesionLocal();
    return null;
  }
  guardarUsuario(usuario);
  if (usuario.csrf_token) establecerCsrf(usuario.csrf_token);
  return usuario;
}

// Pantalla principal según el rol.
export function urlPanel(usuario) {
  const destino = destinoPorRol[usuario?.rol_codigo];
  return destino ? urlAplicacion(destino) : urlAplicacion('public/403.html');
}

// Lleva al usuario a su pantalla principal.
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

// Cierra la sesión en el servidor y el navegador.
export async function cerrarSesion() {
  const respuesta = await apiFetch('DELETE', 'auth/logout');
  if (respuesta.ok) {
    limpiarSesionLocal();
    location.assign(`${appBase}/public/login.html`);
  }
  return respuesta;
}

document.addEventListener('api:sesion-expirada', () => limpiarSesionLocal());

// Navegación visible según el rol y el estado devueltos por la API.

const enlacesPorRol = {
  estudiante: [
    ['pages/postulaciones.php', 'Mis Postulaciones'],
  ],
  empresa: [
    ['pages/empresa.php', 'Publicar práctica'],
    ['pages/postulaciones.php', 'Postulaciones recibidas por práctica'],
  ],
  admin: [
    ['pages/postulaciones.php', 'Postulaciones'],
    ['pages/usuarios.php', 'Usuarios'],
  ],
  superadmin: [
    ['pages/postulaciones.php', 'Postulaciones'],
    ['pages/usuarios.php', 'Usuarios'],
  ],
};

// Menú de navegación por rol.
export function construirMenu(contenedor, usuario, opciones = {}) {
  if (!contenedor) return;
  const base = opciones.baseUrl ?? appBase;
  const rol = usuario?.rol_codigo;
  let enlaces = enlacesPorRol[rol] || [];
  if (rol === 'empresa' && usuario.empresa_estado_codigo !== 'aprobada') {
    enlaces = [];
  }
  const nav = contenedor.matches('nav') ? contenedor : document.createElement('nav');
  if (!contenedor.matches('nav')) {
    nav.setAttribute('aria-label', 'Navegación principal');
    contenedor.replaceChildren(nav);
  }
  nav.replaceChildren();
  enlaces.forEach(([ruta, etiqueta]) => {
    const enlace = document.createElement('a');
    enlace.href = `${base}/${ruta}`;
    enlace.textContent = etiqueta;
    if (new URL(enlace.href, location.origin).pathname === location.pathname) {
      enlace.setAttribute('aria-current', 'page');
    }
    nav.append(enlace);
  });
}

// Menú para visitantes.
export function construirMenuPublico(contenedor, opciones = {}) {
  if (!contenedor) return;
  const base = opciones.baseUrl ?? appBase;
  contenedor.replaceChildren();
  [
    ['/', 'Inicio'],
    ['/public/practicas.php', 'Ver prácticas'],
    ['/public/login.html', 'Iniciar sesión'],
  ].forEach(([ruta, texto]) => {
    const enlace = document.createElement('a');
    enlace.href = ruta === '/' ? `${base}/` : `${base}${ruta}`;
    enlace.textContent = texto;
    contenedor.append(enlace);
  });
}
