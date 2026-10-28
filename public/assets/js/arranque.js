import { apiFetch } from './api.js';
import { requerirSesion, redirigirAlPanel } from './sesion.js';
import { mostrarAlerta } from './ui.js';

const appBaseMeta = document.querySelector('meta[name="app-base"]');
const appBase = appBaseMeta ? appBaseMeta.content : new URL('../../../', import.meta.url).pathname.replace(/\/$/, '');
const estado = document.querySelector('[data-estado-arranque]');

async function iniciar() {
  const sesion = await requerirSesion();
  if (!sesion.ok) {
    if (sesion.respuesta?.estado === 0) {
      estado.textContent = sesion.respuesta.mensaje;
      mostrarAlerta(sesion.respuesta.mensaje, 'error');
    }
    return;
  }

  if (sesion.usuario.debe_cambiar_password) {
    location.replace(`${appBase}/public/cambiar-clave.html`);
    return;
  }

  redirigirAlPanel(sesion.usuario);
}

const enlace = document.querySelector('[data-enlace-login]');
if (enlace) enlace.href = `${appBase}/public/login.html`;
iniciar().catch((error) => {
  console.error('No se pudo consultar la sesión al iniciar.', error);
  if (estado) estado.textContent = 'No se pudo consultar tu sesión. Revisa la conexión e inténtalo de nuevo.';
  mostrarAlerta('No se pudo conectar con el servidor.', 'error');
});
