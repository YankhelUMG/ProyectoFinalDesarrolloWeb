// Cliente común para la API REST del sistema.
const appBaseMeta = document.querySelector('meta[name="app-base"]');
const appBaseDefault = new URL('../../../', import.meta.url).pathname.replace(/\/$/, '');
const appBase = appBaseMeta ? appBaseMeta.content : appBaseDefault;
const apiBase = document.querySelector('meta[name="api-base"]')?.content;
const normalizarBase = (valor) => `${String(valor || '').replace(/\/+$/, '')}/`;

export const BASE_URL = new URL(normalizarBase(apiBase || `${appBase}/public/api`), location.origin).href;
export const CLAVE_CSRF = 'practicas_csrf_token';
export const CLAVE_USUARIO = 'practicas_usuario';

const METODOS_MUTACION = new Set(['POST', 'PUT', 'PATCH', 'DELETE']);
const URL_LOGIN = document.querySelector('meta[name="login-url"]')?.content || new URL('../../login.html', import.meta.url).pathname;
let csrfEnMemoria = leerStorage(CLAVE_CSRF);

function leerStorage(clave) {
  try {
    return sessionStorage.getItem(clave);
  } catch (error) {
    console.error('No se pudo leer el estado de sesión del navegador.', error);
    return null;
  }
}

function guardarStorage(clave, valor) {
  try {
    if (valor === null || valor === undefined || valor === '') sessionStorage.removeItem(clave);
    else sessionStorage.setItem(clave, String(valor));
  } catch (error) {
    console.error('No se pudo guardar el estado de sesión del navegador.', error);
  }
}

export function establecerCsrf(token) {
  csrfEnMemoria = token ? String(token) : null;
  guardarStorage(CLAVE_CSRF, csrfEnMemoria);
}

export function obtenerCsrf() {
  csrfEnMemoria ||= leerStorage(CLAVE_CSRF);
  return csrfEnMemoria;
}

export function guardarUsuario(usuario) {
  guardarStorage(CLAVE_USUARIO, usuario ? JSON.stringify(usuario) : null);
}

export function limpiarSesionLocal() {
  establecerCsrf(null);
  guardarUsuario(null);
}

function emitir(nombre, detalle) {
  if (typeof document !== 'undefined') {
    document.dispatchEvent(new CustomEvent(nombre, { detail: detalle }));
  }
}

function rutaLogin() {
  return new URL(URL_LOGIN, location.origin).href;
}

function informarError(mensaje, estado, errores = {}) {
  emitir('api:alerta', { mensaje, estado, errores, tipo: 'error' });
  if (estado === 422 && Object.keys(errores).length) {
    emitir('api:errores-campo', { errores });
  }
}

function respuestaError(estado, mensaje, errores = {}) {
  informarError(mensaje, estado, errores);
  return { ok: false, estado, mensaje, datos: null, errores };
}

function esBlob(response, tipo, opciones) {
  return opciones.responseType === 'blob' || tipo.includes('application/pdf') || tipo.includes('application/octet-stream');
}

/** Envía una solicitud y devuelve siempre el mismo formato, incluso si falla. */
export async function apiFetch(metodo, ruta, cuerpo = null, opciones = {}) {
  const verbo = String(metodo || 'GET').toUpperCase();
  const cabeceras = new Headers(opciones.headers || {});
  const controlador = new AbortController();
  const timeoutMs = Math.max(1000, Number(opciones.timeoutMs ?? 20000));
  const url = new URL(String(ruta || '').replace(/^\/+/, ''), BASE_URL);
  let temporizador;

  if (cuerpo instanceof FormData) {
    // El navegador añade el boundary requerido por multipart/form-data.
    cabeceras.delete('Content-Type');
  } else if (cuerpo !== null && cuerpo !== undefined && !cabeceras.has('Content-Type')) {
    cabeceras.set('Content-Type', 'application/json; charset=utf-8');
  }

  if (METODOS_MUTACION.has(verbo)) {
    const token = obtenerCsrf();
    if (token) cabeceras.set('X-CSRF-Token', token);
  }

  const cuerpoEnvio = cuerpo instanceof FormData || typeof cuerpo === 'string' || cuerpo === null || cuerpo === undefined
    ? cuerpo
    : JSON.stringify(cuerpo);

  if (opciones.signal) {
    if (opciones.signal.aborted) controlador.abort(opciones.signal.reason);
    else opciones.signal.addEventListener('abort', () => controlador.abort(opciones.signal.reason), { once: true });
  }

  try {
    temporizador = setTimeout(() => controlador.abort(new DOMException('Tiempo de espera agotado.', 'TimeoutError')), timeoutMs);
    const response = await fetch(url, {
      method: verbo,
      credentials: 'same-origin',
      headers: cabeceras,
      body: cuerpoEnvio,
      signal: controlador.signal,
    });
    const contentType = response.headers.get('content-type') || '';

    if (response.status === 401) {
      if (opciones.silenciar401) guardarUsuario(null);
      else limpiarSesionLocal();
      const esLogin = /\/auth\/login(?:$|\?)/.test(url.pathname + url.search);
      const mensaje = esLogin ? 'Usuario o contraseña incorrectos.' : 'Tu sesión expiró. Inicia sesión nuevamente.';
      if (!opciones.silenciar401) {
        emitir('api:sesion-expirada', { mensaje, estado: 401 });
        informarError(mensaje, 401);
        if (!esLogin && location.href !== rutaLogin()) location.assign(rutaLogin());
      }
      return { ok: false, estado: 401, mensaje, datos: null, errores: {} };
    }

    if (esBlob(response, contentType, opciones)) {
      if (!response.ok) return respuestaError(response.status, 'No se pudo descargar el archivo.');
      return { ok: true, estado: response.status, mensaje: '', datos: await response.blob(), errores: {} };
    }

    let payload;
    const texto = await response.text();
    try {
      payload = texto ? JSON.parse(texto) : null;
    } catch (error) {
      console.error('La API devolvió una respuesta que no es JSON válido.', { url: url.href, estado: response.status, contentType, texto, error });
      return respuestaError(response.status, 'El servidor devolvió una respuesta no válida. Inténtalo de nuevo.');
    }

    if (!payload || typeof payload !== 'object' || Array.isArray(payload)) {
      console.error('La API devolvió un cuerpo JSON con formato inesperado.', { url: url.href, estado: response.status, payload });
      return respuestaError(response.status, 'El servidor devolvió una respuesta no válida. Inténtalo de nuevo.');
    }

    const mensajeApi = String(payload.mensaje || '').toLocaleLowerCase('es');
    const csrfVencido = response.status === 403
      && (mensajeApi.includes('token de seguridad') || mensajeApi.includes('csrf'));
    if (csrfVencido && METODOS_MUTACION.has(verbo) && !opciones._reintentoCsrf) {
      // El controlador valida CSRF antes de ejecutar la ruta, así que este reintento no duplica la operación.
      const tokenActualizado = await apiFetch('GET', 'csrf', null, { timeoutMs, signal: opciones.signal, silenciar401: true });
      if (tokenActualizado.ok && tokenActualizado.datos?.csrf_token) {
        return await apiFetch(verbo, ruta, cuerpo, { ...opciones, _reintentoCsrf: true });
      }
    }

    const resultado = {
      ok: response.ok && payload.ok !== false,
      estado: response.status,
      mensaje: String(payload.mensaje || (response.ok ? 'Solicitud completada.' : 'No se pudo completar la solicitud.')),
      datos: payload.datos ?? null,
      errores: payload.errores && typeof payload.errores === 'object' ? payload.errores : {},
    };
    if (resultado.datos?.csrf_token) establecerCsrf(resultado.datos.csrf_token);

    if (!resultado.ok) {
      if (response.status === 403) resultado.mensaje ||= 'No tienes permiso para realizar esta acción.';
      else if (response.status === 429) resultado.mensaje ||= 'Has realizado demasiados intentos. Inténtalo más tarde.';
      else if (response.status >= 500 && !Object.keys(resultado.errores).length) resultado.mensaje = 'No se pudo completar la solicitud. Inténtalo de nuevo más tarde.';
      informarError(resultado.mensaje, response.status, resultado.errores);
    }
    return resultado;
  } catch (error) {
    if (error?.name === 'AbortError' || error?.name === 'TimeoutError') {
      const mensaje = 'La solicitud tardó demasiado. Inténtalo de nuevo.';
      informarError(mensaje, 0);
      return { ok: false, estado: 0, mensaje, datos: null, errores: {} };
    }
    console.error('No se pudo conectar con la API.', { url: url.href, error });
    const mensaje = 'No se pudo conectar con el servidor.';
    informarError(mensaje, 0);
    return { ok: false, estado: 0, mensaje, datos: null, errores: {} };
  } finally {
    clearTimeout(temporizador);
  }
}
