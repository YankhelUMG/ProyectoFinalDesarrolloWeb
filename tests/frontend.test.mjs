import assert from 'node:assert/strict';
import { test } from 'node:test';

// DOM mínimo para probar renderizado seguro y enlazado de módulos sin dependencias.
class Elemento {
  constructor(etiqueta) {
    this.tagName = etiqueta;
    this.children = [];
    this.dataset = {};
    this.style = {};
    this.classList = { add() {}, remove() {}, toggle() {} };
  }
  append(...children) { this.children.push(...children); }
  replaceChildren(...children) { this.children = children; }
  setAttribute(nombre, valor) { this[nombre] = valor; }
  addEventListener() {}
}
const storage = new Map();
const listeners = [];
globalThis.document = {
  body: { dataset: {} }, querySelector: () => null, querySelectorAll: () => [],
  createElement: (etiqueta) => new Elemento(etiqueta),
  addEventListener() {}, dispatchEvent: (evento) => listeners.push(evento),
};
globalThis.location = { origin: 'http://localhost', href: 'http://localhost/', assign() {} };
globalThis.sessionStorage = {
  getItem: (clave) => storage.get(clave) ?? null,
  setItem: (clave, valor) => storage.set(clave, valor),
  removeItem: (clave) => storage.delete(clave),
};
const api = await import('../public/assets/js/api.js');
const base = await import('../public/assets/js/comun.js');
const { crearElemento } = await import('../public/assets/js/comun.js');
const { renderPracticas } = await import('../public/assets/js/practicas.js');
const { errorCv } = await import('../public/assets/js/ui.js');

test('Todos los módulos se enlazan y la entrada admite una página sin módulo', async () => {
  await import('../public/assets/js/app.js');
});

test('Los datos de la API se presentan como texto, sin interpretar HTML', () => {
  const texto = '<img src=x onerror=alert(1)>';
  const elemento = crearElemento('p', texto, 'aviso');
  assert.equal(elemento.textContent, texto);
  assert.equal(elemento.innerHTML, undefined);
  assert.equal(elemento.className, 'aviso');
});

test('El catálogo conserva título, cupos, estado y acción de postulación', () => {
  base.estado.usuario = { rol_codigo: 'estudiante' };
  const raiz = new Elemento('div');
  renderPracticas([{ id_practica: 8, titulo: 'PHP junior', area: 'Tecnología', empresa: 'Empresa',
    modalidad: 'Remota', descripcion: 'Aprender PHP', fecha_limite_postulacion: '2099-12-01',
    cupos_ocupados: 0, cupo_maximo: 2, estado: 'Publicada', estado_codigo: 'publicada' }], new Map(), raiz);
  const tarjeta = raiz.children[0];
  assert.equal(tarjeta.children[0].textContent, 'PHP junior');
  assert.match(tarjeta.children[4].textContent, /Cupos ocupados: 0\/2/);
  assert.equal(tarjeta.children[5].textContent, 'Ver y postularme');
  renderPracticas([], new Map(), raiz);
  assert.match(raiz.children[0].textContent, /No hay oportunidades/);
});

test('El cliente manda el CSRF de la sesión y conserva el cuerpo JSON', async () => {
  let enviada;
  api.establecerCsrf('token-prueba');
  globalThis.fetch = async (url, opciones) => {
    enviada = opciones;
    return Response.json({ ok: true, mensaje: '', datos: { id: 1 }, errores: {} });
  };
  const respuesta = await api.apiFetch('POST', 'postulaciones', { id_practica: 8 });
  assert.equal(enviada.headers.get('X-CSRF-Token'), 'token-prueba');
  assert.equal(enviada.credentials, 'same-origin');
  assert.deepEqual(JSON.parse(enviada.body), { id_practica: 8 });
  assert.equal(respuesta.ok, true);
});

test('Un CSRF vencido se renueva una sola vez antes de repetir la operación', async () => {
  const llamadas = [];
  globalThis.fetch = async (url, opciones) => {
    llamadas.push({ url: String(url), token: opciones.headers.get('X-CSRF-Token') });
    if (llamadas.length === 1) return Response.json({ ok: false, mensaje: 'El token de seguridad venció.', datos: null, errores: {} }, { status: 403 });
    if (llamadas.length === 2) return Response.json({ ok: true, mensaje: '', datos: { csrf_token: 'nuevo' }, errores: {} });
    return Response.json({ ok: true, mensaje: '', datos: { id: 2 }, errores: {} });
  };
  const respuesta = await api.apiFetch('POST', 'postulaciones', { id_practica: 9 });
  assert.equal(respuesta.ok, true);
  assert.equal(llamadas.length, 3);
  assert.match(llamadas[1].url, /csrf$/);
  assert.equal(llamadas[2].token, 'nuevo');
});

test('La recarga compartida espera la actualización del módulo actual', async () => {
  let actualizaciones = 0;
  document.body.dataset.module = 'perfil';
  base.configurarActualizacion(async () => { actualizaciones++; });
  await base.actualizarPantalla();
  assert.equal(actualizaciones, 1);
  delete document.body.dataset.module;
});

test('Registro, perfil y administración comparten la validación del CV', () => {
  assert.equal(errorCv(null, 1000), null);
  assert.equal(errorCv({ name: 'CV.PDF', type: 'application/pdf', size: 100 }, 1000), null);
  assert.match(errorCv({ name: 'cv.pdf', type: 'image/png', size: 100 }, 1000), /PDF/);
  assert.match(errorCv({ name: 'cv.txt', type: '', size: 100 }, 1000), /PDF/);
  assert.match(errorCv({ name: 'cv.pdf', type: 'application/pdf', size: 1001 }, 1000), /máximo/);
});
