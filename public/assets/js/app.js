// Punto de entrada del portal: elige el módulo según la pantalla y lo inicia.
import { activarIconos } from './ui/iconos.js';
import {
  crearElemento,
  mostrar,
  peticion,
  ponerSesion,
  cargarCatalogos,
  appBase,
  estado,
  vistaPublica,
  $,
  configurarActualizacion,
  encabezado,
  etiquetaEstado,
} from './nucleo/comun.js';
import {
  cargarPracticas,
  crearCatalogoModulo,
  formularioPractica,
  renderPracticasPropias,
} from './modulos/practicas.js';
import { formularioPerfil } from './modulos/perfil.js';
import { renderSolicitudes, cargarPostulacionesAdmin } from './modulos/postulaciones.js';
import {
  cargarPracticasAdmin,
  renderEmpresasPendientes,
  resumenReportes,
  herramientasAdmin,
} from './modulos/administracion.js';
import { formularioUsuario, gestionarUsuarios, gestionarRestauracion } from './modulos/usuarios.js';
import { requerirSesion, redirigirAlPanel } from './nucleo/sesion.js';
import { crearSpinner, mostrarAlerta } from './ui/ui.js';

// Entrada de la interfaz: consulta la sesión y abre el módulo solicitado.

// Carga la pantalla según data-module.
export async function cargarModuloActual() {
  const root = $('#contenido-modulo');
  if (!root) return;
  root.replaceChildren();
  const spinner = crearSpinner('Cargando información…');
  root.append(spinner);
  root.setAttribute('aria-busy', 'true');
  const modulo = document.body.dataset.module;
  const rol = estado.usuario?.rol_codigo;
  if (!estado.usuario) {
    const aviso = crearElemento('p', 'Inicia sesión para acceder a esta sección.');
    root.append(aviso);
    const link = document.createElement('a');
    link.href = `${appBase}/public/login.html`;
    link.textContent = 'Ir al inicio de sesión';
    root.append(link);
    return;
  }
  if (estado.usuario.debe_cambiar_password) {
    location.replace(`${appBase}/public/cambiar-clave.html`);
    return;
  }
  try {
    if (modulo === 'practicas') {
      if (rol === 'empresa') {
        crearCatalogoModulo(root);
      } else if (rol === 'admin' || rol === 'superadmin') {
        await cargarPracticasAdmin(root, rol);
      } else crearCatalogoModulo(root);
    } else if (modulo === 'empresa') {
      if (rol !== 'empresa') throw new Error('Esta sección es solo para cuentas de empresa.');
      if (estado.usuario.empresa_estado_codigo !== 'aprobada') {
        const perfil = await peticion('perfil');
        encabezado(root, `Estado de la empresa: ${perfil.datos.estado || 'sin estado'}`);
        const aviso = crearElemento(
          'p',
          'Las herramientas para publicar prácticas se habilitarán cuando la cuenta esté aprobada.',
          'aviso',
        );
        root.append(aviso);
        etiquetaEstado(root, perfil.datos.estado, 'empresa', perfil.datos.estado_codigo);
        const comentarioRevision = String(perfil.datos.observaciones_coordinacion || '').trim();
        if (comentarioRevision) {
          const comentario = document.createElement('section');
          comentario.className = 'aviso panel-comentario-revision';
          comentario.append(
            crearElemento('h3', 'Comentario de revisión'),
            crearElemento('p', comentarioRevision),
          );
          root.append(comentario);
        }
        const enlace = document.createElement('a');
        enlace.href = `${appBase}/pages/perfil.php`;
        enlace.textContent = 'Consultar mi perfil';
        root.append(enlace);
        return;
      }
      const p = await peticion('practicas/mias');
      formularioPractica(root);
      await renderPracticasPropias(root, p.datos);
    } else if (modulo === 'postulaciones') {
      if (rol === 'admin' || rol === 'superadmin') await cargarPostulacionesAdmin(root);
      else {
        const filas = await peticion('postulaciones');
        await renderSolicitudes(root, filas.datos, rol === 'estudiante');
      }
    } else if (modulo === 'perfil') {
      if (!['estudiante', 'empresa', 'admin', 'superadmin'].includes(rol))
        throw new Error('No se encontró el perfil de esta cuenta.');
      const perfil = await peticion('perfil');
      formularioPerfil(root, perfil.datos, rol);
    } else if (modulo === 'empleados') {
      if (rol !== 'admin' && rol !== 'superadmin')
        throw new Error('Esta sección requiere permisos de administración.');
      formularioUsuario(root, rol);
      await gestionarUsuarios(root, [], rol);
      await gestionarRestauracion(root);
    } else if (modulo === 'administracion') {
      if (rol !== 'admin' && rol !== 'superadmin')
        throw new Error('Esta sección requiere permisos de administración.');
      const d = await peticion('admin/reportes');
      resumenReportes(root, d.datos);
      try {
        const e = await peticion('admin/empresas');
        await renderEmpresasPendientes(root, e.datos);
      } catch (error) {
        const seccion = document.createElement('section');
        encabezado(seccion, 'Empresas por revisar');
        const aviso = crearElemento(
          'p',
          `No se pudo cargar la lista de empresas: ${error.message}`,
        );
        seccion.append(aviso);
        root.append(seccion);
      }
      herramientasAdmin(root);
    } else if (modulo === 'consultas') {
      const d = await peticion(
        rol === 'admin' || rol === 'superadmin' ? 'admin/reportes' : 'dashboard',
      );
      resumenReportes(root, d.datos);
    } else throw new Error('La sección solicitada no está disponible.');
  } catch (e) {
    root.replaceChildren();
    const aviso = document.createElement('p');
    aviso.className = 'aviso';
    aviso.setAttribute('role', 'alert');
    aviso.textContent = e.message || 'No se pudo cargar esta sección.';
    root.append(aviso);
    mostrar(aviso.textContent, true);
  } finally {
    spinner.remove();
    root.removeAttribute('aria-busy');
  }
}

// Inicia un módulo y maneja sus errores.
export async function iniciarModulo() {
  if (document.body.dataset.page === 'inicio') return iniciarPortal();
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
    // Selectores y fechas aplican el filtro inmediatamente; las búsquedas de texto
    // conservan el botón y esperan un breve momento para no consultar en cada tecla.
    filtros?.querySelectorAll('select, input[type="date"]').forEach((campo) => {
      campo.addEventListener('change', () => {
        estado.pagina = 1;
        void cargarPracticas(vistaPublica);
      });
    });
    let temporizadorBusqueda;
    filtros?.querySelectorAll('input[type="search"]').forEach((campo) => {
      campo.addEventListener('input', () => {
        clearTimeout(temporizadorBusqueda);
        temporizadorBusqueda = setTimeout(() => {
          estado.pagina = 1;
          void cargarPracticas(vistaPublica);
        }, 350);
      });
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
  const spinner = crearSpinner('Consultando tu sesión…');
  root?.replaceChildren(spinner);
  try {
    const sesion = await requerirSesion(roles);
    if (!sesion.ok) {
      spinner.remove();
      if (sesion.respuesta?.estado === 0) mostrar(sesion.respuesta.mensaje, true);
      return;
    }
    estado.usuario = sesion.usuario;
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

// Prepara sesión, catálogos y pantalla.
async function iniciarPortal() {
  const enlace = document.querySelector('[data-enlace-login]');
  if (enlace) enlace.href = `${appBase}/public/login.html`;
  const sesion = await requerirSesion();
  if (!sesion.ok) {
    if (sesion.respuesta?.estado === 0) {
      const mensaje = document.querySelector('[data-estado-arranque]');
      if (mensaje) mensaje.textContent = sesion.respuesta.mensaje;
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

configurarActualizacion(cargarModuloActual);
iniciarModulo().catch((error) => {
  console.error('No se pudo cargar la pantalla.', error);
  mostrar(error.message || 'No se pudo cargar la pantalla.', true);
});

activarIconos();
