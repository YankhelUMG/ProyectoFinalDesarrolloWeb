// Iconos SVG que se agregan a botones y enlaces según su texto.
// Iconos de línea (estilo Lucide, licencia ISC) creados como SVG en línea.
// Se asignan por el texto del botón o enlace, sin tocar la lógica de cada pantalla.
const NS = 'http://www.w3.org/2000/svg';

const RUTAS = {
  buscar: '<circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/>',
  filtro: '<path d="M22 3H2l8 9.46V19l4 2v-8.54z"/>',
  limpiar: '<path d="M3 6h18"/><path d="M8 6V4h8v2"/><path d="m19 6-1 14H6L5 6"/>',
  entrar: '<path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><path d="m10 17 5-5-5-5"/><path d="M15 12H3"/>',
  salir: '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="m16 17 5-5-5-5"/><path d="M21 12H9"/>',
  ojo: '<path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/>',
  ojoNo: '<path d="M9.9 4.2A10 10 0 0 1 12 4c6.4 0 10 7 10 7a17 17 0 0 1-2.2 3.2"/><path d="M6.6 6.6A17 17 0 0 0 2 11s3.6 7 10 7a10 10 0 0 0 5.4-1.6"/><path d="m2 2 20 20"/>',
  maletin: '<rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/>',
  documento: '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="M8 13h8"/><path d="M8 17h5"/>',
  usuario: '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
  usuarios: '<circle cx="9" cy="8" r="4"/><path d="M2 21a7 7 0 0 1 14 0"/><path d="M16 4a4 4 0 0 1 0 8"/><path d="M22 21a7 7 0 0 0-5-6.7"/>',
  usuarioMas: '<circle cx="9" cy="8" r="4"/><path d="M2 21a7 7 0 0 1 14 0"/><path d="M19 8v6"/><path d="M16 11h6"/>',
  edificio: '<rect x="4" y="2" width="16" height="20" rx="2"/><path d="M9 22v-4h6v4"/><path d="M8 6h.01M12 6h.01M16 6h.01M8 10h.01M12 10h.01M16 10h.01M8 14h.01M12 14h.01M16 14h.01"/>',
  descargar: '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="m7 10 5 5 5-5"/><path d="M12 15V3"/>',
  subir: '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="m17 8-5-5-5 5"/><path d="M12 3v12"/>',
  check: '<path d="M20 6 9 17l-5-5"/>',
  x: '<path d="M18 6 6 18"/><path d="m6 6 12 12"/>',
  deshacer: '<circle cx="12" cy="12" r="10"/><path d="M8 12h8"/>',
  recargar: '<path d="M3 12a9 9 0 1 0 3-6.7L3 8"/><path d="M3 3v5h5"/>',
  flecha: '<path d="M5 12h14"/><path d="m12 5 7 7-7 7"/>',
  atras: '<path d="M19 12H5"/><path d="m12 19-7-7 7-7"/>',
  izquierda: '<path d="m15 18-6-6 6-6"/>',
  derecha: '<path d="m9 18 6-6-6-6"/>',
  enviar: '<path d="m22 2-7 20-4-9-9-4z"/><path d="M22 2 11 13"/>',
  mas: '<path d="M12 5v14"/><path d="M5 12h14"/>',
  guardar: '<path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><path d="M17 21v-8H7v8"/><path d="M7 3v5h8"/>',
  lapiz: '<path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/>',
  ajustes: '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.8-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1.1-1.5 1.7 1.7 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1.1 1.7 1.7 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.8.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.8V9a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1z"/>',
  grafica: '<path d="M3 3v18h18"/><path d="M18 17V9"/><path d="M13 17V5"/><path d="M8 17v-3"/>',
  escudo: '<path d="M20 13c0 5-3.5 7.5-8 9-4.5-1.5-8-4-8-9V5l8-3 8 3z"/><path d="m9 12 2 2 4-4"/>',
  candado: '<rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>',
  campana: '<path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.9 1.9 0 0 0 3.4 0"/>',
};

// [expresión sobre el texto sin tildes, icono]. El orden importa: la primera que coincide gana.
const REGLAS_BOTONES = [
  [/^iniciar sesion/, 'entrar'],
  [/^cerrar sesion/, 'salir'],
  [/^(ver )?practicas?$|^ver practicas/, 'maletin'],
  [/^buscar/, 'buscar'],
  [/^filtrar/, 'filtro'],
  [/^limpiar/, 'limpiar'],
  [/^mostrar/, 'ojo'],
  [/^ocultar/, 'ojoNo'],
  [/^crear cuenta/, 'usuarioMas'],
  [/^solicitar cuenta/, 'edificio'],
  [/^registr/, 'usuarioMas'],
  [/descargar/, 'descargar'],
  [/^ver perfil|^mi perfil|^perfil/, 'usuario'],
  [/postularme|^postular/, 'enviar'],
  [/^ver (mi )?postulacion|^ver detalle|^ver mas|^detalle/, 'flecha'],
  [/^acept|^aprobar|^aprobada/, 'check'],
  [/^confirmar|^si, /, 'check'],
  [/^rechaz|^no seleccionar/, 'x'],
  [/^cancelar|^cerrar$|^descartar/, 'x'],
  [/^retirar/, 'deshacer'],
  [/^reactivar|^restaurar|^recargar|^actualizar/, 'recargar'],
  [/^guardar|^actualizar perfil/, 'guardar'],
  [/^editar|^modificar|^cambiar/, 'lapiz'],
  [/^eliminar|^borrar/, 'limpiar'],
  [/^publicar|^subir/, 'subir'],
  [/^nueva|^nuevo|^agregar|^crear/, 'mas'],
  [/^volver|^regresar/, 'atras'],
  [/^anterior/, 'izquierda'],
  [/^siguiente/, 'derecha'],
];

const REGLAS_NAV = [
  [/postulacion/, 'documento'],
  [/practica|oportunidad/, 'maletin'],
  [/perfil/, 'usuario'],
  [/administracion|admin|configuracion/, 'ajustes'],
  [/usuario|cuenta|empleado/, 'usuarios'],
  [/empresa/, 'edificio'],
  [/consulta|reporte|panel|dashboard/, 'grafica'],
  [/seguimiento/, 'documento'],
];

// Texto en minúsculas y sin tildes.
function normalizar(texto) {
  return String(texto || '')
    .toLowerCase()
    .normalize('NFD')
    .replace(/[̀-ͯ]/g, '')
    .replace(/\s+/g, ' ')
    .trim();
}

// Crea el SVG de un icono por nombre.
export function crearIcono(nombre) {
  const plantilla = RUTAS[nombre];
  if (!plantilla) return null;
  const svg = document.createElementNS(NS, 'svg');
  svg.setAttribute('viewBox', '0 0 24 24');
  svg.setAttribute('class', 'icono');
  svg.setAttribute('aria-hidden', 'true');
  svg.setAttribute('focusable', 'false');
  svg.innerHTML = plantilla;
  return svg;
}

// Decide qué icono corresponde al elemento.
function nombreParaElemento(el) {
  if (el.dataset?.icono) return el.dataset.icono;
  const texto = normalizar(el.textContent);
  if (!texto) return null;
  const reglas = el.closest('#nav-principal') ? REGLAS_NAV : REGLAS_BOTONES;
  const regla = reglas.find(([patron]) => patron.test(texto));
  return regla ? regla[1] : null;
}

// Agrega el icono si todavía no lo tiene.
function agregarIcono(el) {
  if (el.querySelector(':scope > svg.icono')) return;
  if (el.getAttribute('role') === 'tab' || el.hasAttribute('data-sin-icono')) return;
  const nombre = nombreParaElemento(el);
  const icono = nombre ? crearIcono(nombre) : null;
  if (icono) el.prepend(icono);
}

const SELECTOR =
  'button, a.boton, #nav-principal a, .enlaces-acceso a, header .barra-izquierda a.boton, [data-icono]';

// Recorre botones y enlaces para agregar iconos.
export function decorarIconos(raiz = document) {
  if (!raiz.querySelectorAll) return;
  raiz.querySelectorAll(SELECTOR).forEach(agregarIcono);
}

// Activa los iconos y vigila los cambios de la página.
export function activarIconos() {
  decorarIconos();
  if (typeof MutationObserver === 'undefined') return;
  let pendiente = false;
  const observador = new MutationObserver(() => {
    if (pendiente) return;
    pendiente = true;
    queueMicrotask(() => {
      pendiente = false;
      decorarIconos();
    });
  });
  observador.observe(document.body, { childList: true, subtree: true, characterData: true });
}
