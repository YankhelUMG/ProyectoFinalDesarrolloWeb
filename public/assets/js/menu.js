// Navegación visible según el rol y el estado devueltos por la API.
const appBaseMeta = document.querySelector('meta[name="app-base"]');
const appBase = appBaseMeta ? appBaseMeta.content : new URL('../../../', import.meta.url).pathname.replace(/\/$/, '');

const enlacesPorRol = {
  estudiante: [
    ['pages/practicas/index.php', 'Prácticas'],
    ['pages/postulaciones/index.php', 'Mis postulaciones'],
  ],
  empresa: [
    ['pages/practicas/index.php', 'Prácticas'],
    ['pages/empresa/index.php', 'Publicar práctica'],
    ['pages/postulaciones/index.php', 'Postulaciones recibidas por práctica'],
  ],
  admin: [
    ['pages/practicas/index.php', 'Prácticas'],
    ['pages/postulaciones/index.php', 'Postulaciones'],
    ['pages/empleados/index.php', 'Usuarios'],
    ['pages/administracion/index.php', 'Administración'],
    ['pages/consultas/index.php', 'Consultas y reportes'],
  ],
  superadmin: [
    ['pages/practicas/index.php', 'Prácticas'],
    ['pages/postulaciones/index.php', 'Postulaciones'],
    ['pages/empleados/index.php', 'Usuarios'],
    ['pages/administracion/index.php', 'Administración'],
    ['pages/consultas/index.php', 'Consultas y reportes'],
  ],
};

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

export function construirMenuPublico(contenedor, opciones = {}) {
  if (!contenedor) return;
  const base = opciones.baseUrl ?? appBase;
  contenedor.replaceChildren();
  [['/', 'Inicio'], ['/practicas-disponibles/practicas-disponibles.php', 'Ver prácticas'], ['/public/login.html', 'Iniciar sesión']].forEach(([ruta, texto]) => {
    const enlace = document.createElement('a');
    enlace.href = ruta === '/' ? `${base}/` : `${base}${ruta}`;
    enlace.textContent = texto;
    contenedor.append(enlace);
  });
}
