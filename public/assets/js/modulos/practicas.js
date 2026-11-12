// Catálogo de prácticas, detalle, formulario y listado de la empresa.
import {
  crearElemento,
  mostrar,
  peticion,
  formulario,
  llenarCatalogos,
  appBase,
  estado,
  $,
  actualizarPantalla,
  boton,
  etiquetaEstado,
  fechaLocal,
  encabezado,
  transiciones,
} from '../nucleo/comun.js';
import {
  confirmarAccion,
  durantePeticion,
  limpiarErroresCampos,
  pintarErroresCampos,
  validarFormularioEspanol,
} from '../ui/ui.js';

// Funciones de la pantalla: practicas.

// Fecha en formato legible.
function fechaLegible(fecha) {
  if (!fecha) return 'Por definir';
  const partes = String(fecha).slice(0, 10).split('-').map(Number);
  if (partes.length !== 3 || partes.some(Number.isNaN)) return String(fecha);
  const [anio, mes, dia] = partes;
  const valor = new Date(Date.UTC(anio, mes - 1, dia));
  return new Intl.DateTimeFormat('es-GT', {
    day: 'numeric',
    month: 'long',
    year: 'numeric',
    timeZone: 'UTC',
  }).format(valor);
}

// Dato con etiqueta y valor.
function datoPractica(etiqueta, valor) {
  const dato = document.createElement('div');
  dato.className = 'dato-practica';
  dato.append(crearElemento('dt', etiqueta), crearElemento('dd', valor || 'Por definir'));
  return dato;
}

// Dibuja las tarjetas de prácticas.
export function renderPracticas(items, postulaciones = new Map(), root = $('#lista-practicas')) {
  root.replaceChildren();
  if (!items.length) {
    const p = crearElemento('p', 'No hay oportunidades que coincidan con los filtros.');
    root.append(p);
    return;
  }
  for (const x of items) {
    const card = document.createElement('article');
    card.className = 'tarjeta tarjeta-practica';
    card.dataset.inicial = String(x.empresa || x.titulo || '?').trim().charAt(0).toUpperCase();
    const h = crearElemento('h3', x.titulo);
    const area = crearElemento('span', x.area, 'etiqueta');
    const identidad = document.createElement('div');
    identidad.className = 'tarjeta-practica__identidad';
    identidad.append(h, area);
    const estadoPractica = document.createElement('div');
    estadoPractica.className = 'tarjeta-practica__estado';
    etiquetaEstado(estadoPractica, x.estado, 'practica', x.estado_codigo);
    const encabezadoPractica = document.createElement('div');
    encabezadoPractica.className = 'tarjeta-practica__encabezado';
    encabezadoPractica.append(identidad, estadoPractica);
    const descripcion = crearElemento('p', x.descripcion, 'tarjeta-practica__descripcion');
    const totalCupos = Number(x.cupo_maximo) || 0;
    const cuposOcupados = Number(x.cupos_ocupados) || 0;
    const cuposDisponibles = Math.max(0, totalCupos - cuposOcupados);
    const datos = document.createElement('dl');
    datos.className = 'tarjeta-practica__datos';
    datos.append(
      datoPractica('Empresa', x.empresa),
      datoPractica('Modalidad', x.modalidad),
      datoPractica('Ubicación', x.ubicacion || 'Por definir'),
      datoPractica('Inicio de la práctica', fechaLegible(x.fecha_inicio)),
      datoPractica('Último día para postularse', fechaLegible(x.fecha_limite_postulacion)),
      datoPractica('Cupos disponibles', `${cuposDisponibles} de ${totalCupos}`),
    );
    const acciones = document.createElement('div');
    acciones.className = 'tarjeta-practica__acciones';
    const b = document.createElement('button');
    b.className = 'boton primario';
    const postulacion = postulaciones.get(Number(x.id_practica));
    b.textContent =
      estado.usuario?.rol_codigo === 'estudiante'
        ? postulacion
          ? 'Ver mi postulación'
          : 'Ver detalles y postularme'
        : 'Ver detalles';
    b.onclick = () => verDetalle(x.id_practica, postulacion);
    acciones.append(b);
    card.append(encabezadoPractica, descripcion, datos, acciones);
    root.append(card);
  }
}

// Consulta el catálogo con filtros y paginación.
export async function cargarPracticas(vista = {}) {
  const lista = vista.lista || $('#lista-practicas');
  const filtros = vista.filtros || $('#filtros');
  const anterior = vista.anterior || $('#pagina-anterior');
  const paginaTexto = vista.pagina || $('#pagina-actual');
  const siguiente = vista.siguiente || $('#pagina-siguiente');
  const estadoCarga = vista.estado || $('#estado-carga');
  if (!lista || !filtros) return;
  const f = formulario(filtros);
  const campoDesde = filtros.elements.namedItem('fecha_inicio_desde');
  const campoHasta = filtros.elements.namedItem('fecha_inicio_hasta');
  campoDesde?.setCustomValidity('');
  campoHasta?.setCustomValidity('');
  const desde = campoDesde?.valueAsNumber;
  const hasta = campoHasta?.valueAsNumber;
  if (Number.isFinite(desde) && Number.isFinite(hasta) && desde > hasta) {
    campoDesde?.setCustomValidity('La fecha inicial no puede ser posterior a la fecha final.');
    campoHasta?.setCustomValidity('La fecha final no puede ser anterior a la fecha inicial.');
    campoDesde?.reportValidity();
    const limpiarErrorRango = () => {
      campoDesde?.setCustomValidity('');
      campoHasta?.setCustomValidity('');
    };
    if (campoDesde) campoDesde.oninput = limpiarErrorRango;
    if (campoHasta) campoHasta.oninput = limpiarErrorRango;
    if (estadoCarga) estadoCarga.textContent = 'Corrige el rango de fechas para buscar.';
    return;
  }
  const solicitud = (vista.solicitud = (vista.solicitud || 0) + 1);
  const q = new URLSearchParams({
    pagina: String(estado.pagina),
  });
  for (const k of [
    'q',
    'area',
    'modalidad',
    'empresa',
    'fecha_inicio_desde',
    'fecha_inicio_hasta',
  ]) {
    const valor = String(f[k] || '').trim();
    if (valor) q.set(k, valor);
  }
  lista.replaceChildren();
  const cargando = crearElemento('p', 'Cargando prácticas…');
  lista.append(cargando);
  for (const boton of [
    filtros.querySelector('button[type="submit"]'),
    filtros.querySelector('button[type="reset"]'),
    anterior,
    siguiente,
  ])
    if (boton) boton.disabled = true;
  if (estadoCarga) estadoCarga.textContent = 'Cargando prácticas…';
  let avisoPostulaciones = false;
  try {
    const r = await peticion(`practicas?${q}`);
    if (solicitud !== vista.solicitud) return;
    let postulaciones = new Map();
    if (estado.usuario?.rol_codigo === 'estudiante' && !estado.usuario.debe_cambiar_password) {
      try {
        const own = await peticion('postulaciones');
        if (solicitud !== vista.solicitud) return;
        postulaciones = new Map(own.datos.map((x) => [Number(x.id_practica), x.estado_codigo]));
      } catch {
        // El fallo al consultar postulaciones no debe ocultar los resultados del catálogo.
        avisoPostulaciones = true;
      }
    }
    if (solicitud !== vista.solicitud) return;
    renderPracticas(r.datos.elementos, postulaciones, lista);
    if (paginaTexto) paginaTexto.textContent = `Página ${estado.pagina}`;
    if (anterior) anterior.disabled = estado.pagina <= 1;
    if (siguiente) siguiente.disabled = !r.datos.hay_siguiente;
    if (estadoCarga) {
      const cantidad = r.datos.elementos.length;
      estadoCarga.textContent = avisoPostulaciones
        ? 'Prácticas cargadas; no se pudo consultar el estado de tus postulaciones.'
        : `${cantidad} ${cantidad === 1 ? 'práctica' : 'prácticas'} en esta página.`;
    }
  } catch (e) {
    if (solicitud !== vista.solicitud) return;
    lista.replaceChildren();
    if (estadoCarga) estadoCarga.textContent = e.message || 'No se pudieron cargar las prácticas.';
    mostrar(e.message, true);
  } finally {
    if (solicitud === vista.solicitud) {
      for (const boton of [
        filtros.querySelector('button[type="submit"]'),
        filtros.querySelector('button[type="reset"]'),
      ])
        if (boton) boton.disabled = false;
      if (anterior) anterior.disabled = estado.pagina <= 1;
      if (siguiente && !siguiente.disabled) siguiente.disabled = false;
    }
  }
}

// Ventana con el detalle y la opción de postularse.
export async function verDetalle(id, postulacion = null) {
  try {
    const r = await peticion(`practicas/${id}`);
    const x = r.datos;
    const dialog = document.createElement('dialog');
    dialog.className = 'dialogo';
    const article = document.createElement('article');
    article.className = 'detalle-practica';

    const heading = document.createElement('div');
    heading.className = 'detalle-practica__encabezado';
    const title = crearElemento('h2', x.titulo);
    heading.append(title);
    if (x.estado) {
      const status = document.createElement('div');
      status.className = 'detalle-practica__estado';
      etiquetaEstado(status, x.estado, 'practica', x.estado_codigo);
      heading.append(status);
    }
    article.append(heading);

    const content = document.createElement('div');
    content.className = 'detalle-practica__contenido';
    const cuposTotal = Number(x.cupo_maximo) || 0;
    const cuposDisponibles = Math.max(0, cuposTotal - (Number(x.cupos_ocupados) || 0));
    const details = document.createElement('dl');
    details.className = 'detalle-practica__datos';
    details.append(
      datoPractica('Empresa', x.empresa),
      datoPractica('Área', x.area),
      datoPractica('Modalidad', x.modalidad),
      datoPractica('Ubicación', x.ubicacion || 'Por definir'),
      datoPractica('Inicio de la práctica', fechaLegible(x.fecha_inicio)),
      datoPractica('Último día para postularse', fechaLegible(x.fecha_limite_postulacion)),
      datoPractica('Cupos disponibles', `${cuposDisponibles} de ${cuposTotal}`),
    );

    const description = document.createElement('section');
    description.className = 'detalle-practica__seccion';
    description.append(
      crearElemento('h3', 'Descripción'),
      crearElemento('p', x.descripcion || 'Sin descripción.'),
    );
    const requirements = document.createElement('section');
    requirements.className = 'detalle-practica__seccion';
    requirements.append(
      crearElemento('h3', 'Requisitos'),
      crearElemento('p', x.requisitos || 'No se especificaron requisitos.'),
    );
    content.append(details, description, requirements);

    const actions = document.createElement('div');
    actions.className = 'detalle-practica__acciones';
    const close = document.createElement('button');
    close.type = 'button';
    close.className = 'boton secundario';
    close.textContent = 'Cerrar';
    close.onclick = () => dialog.close();
    if (
      estado.usuario?.rol_codigo === 'estudiante' &&
      !estado.usuario.debe_cambiar_password &&
      postulacion
    ) {
      const applicationState = document.createElement('section');
      applicationState.className = 'detalle-practica__postulacion-existente';
      applicationState.append(
        crearElemento('h3', postulacion === 'retirada' ? 'Postulación retirada' : 'Ya te postulaste'),
        crearElemento(
          'p',
          postulacion === 'retirada'
            ? 'Puedes revisar si todavía está disponible la opción para reactivarla.'
            : 'Tu solicitud ya fue enviada. Consulta aquí su estado y seguimiento.',
        ),
      );
      const tracking = document.createElement('a');
      tracking.href = `${appBase}/pages/postulaciones.php`;
      tracking.className = 'boton secundario';
      tracking.textContent = 'Abrir mis postulaciones';
      applicationState.append(tracking);
      content.append(applicationState);
    } else if (
      estado.usuario?.rol_codigo === 'estudiante' &&
      !estado.usuario.debe_cambiar_password
    ) {
      const applySection = document.createElement('section');
      applySection.className = 'detalle-practica__postulacion';
      applySection.append(
        crearElemento('h3', 'Presenta tu postulación'),
        crearElemento('p', 'Agrega una carta breve para acompañar tu solicitud. Es opcional.'),
      );
      const form = document.createElement('form');
      form.className = 'formulario';
      const label = crearElemento('label', 'Carta de presentación (opcional)');
      const letter = document.createElement('textarea');
      letter.name = 'carta_presentacion';
      letter.placeholder = 'Escribe tu carta de presentación';
      letter.maxLength = 3000;
      letter.rows = 4;
      label.append(letter);
      form.append(label);
      const submit = crearElemento('button', 'Enviar postulación', 'boton primario');
      form.append(submit);
      form.onsubmit = async (event) => {
        event.preventDefault();
        submit.disabled = true;
        try {
          await peticion('postulaciones', {
            method: 'POST',
            body: JSON.stringify({ id_practica: id, carta_presentacion: letter.value }),
          });
          dialog.close();
          mostrar('Postulación registrada.');
          if (document.body.dataset.module) await actualizarPantalla();
          else await cargarPracticas();
        } catch (error) {
          mostrar(error.message, true);
          if (
            /cv|correo|teléfono|perfil/i.test(error.message) &&
            !article.querySelector('[data-enlace-perfil]')
          ) {
            const aviso = crearElemento(
              'p',
              'Revisa tus datos y tu CV antes de volver a postularte.',
              'aviso',
            );
            const perfil = document.createElement('a');
            perfil.href = `${appBase}/pages/perfil.php`;
            perfil.dataset.enlacePerfil = '';
            perfil.textContent = 'Ir a mi perfil';
            aviso.append(' ', perfil);
            applySection.append(aviso);
          }
          submit.disabled = false;
        }
      };
      applySection.append(form);
      content.append(applySection);
    } else if (!estado.usuario) {
      const login = document.createElement('a');
      login.className = 'boton primario';
      login.href = `${appBase}/public/login.html`;
      login.textContent = 'Iniciar sesión para postularte';
      actions.append(login);
    } else if (estado.usuario.debe_cambiar_password) {
      content.append(
        crearElemento('p', 'Cambia tu contraseña temporal antes de postularte.', 'aviso'),
      );
    }
    actions.append(close);
    article.append(content, actions);
    dialog.append(article);
    document.body.append(dialog);
    dialog.addEventListener('close', () => dialog.remove(), { once: true });
    dialog.showModal();
  } catch (e) {
    mostrar(e.message, true);
  }
}

// Arma la pantalla del catálogo.
export function crearCatalogoModulo(root) {
  const form = document.createElement('form');
  form.id = 'filtros-modulo';
  form.className = 'filtros-catalogo';
  const fieldset = document.createElement('fieldset');
  const legend = crearElemento('legend', 'Filtrar prácticas');
  fieldset.append(legend);
  for (const [name, label, type] of [
    ['q', 'Palabra clave', 'search'],
    ['empresa', 'Empresa', 'text'],
  ]) {
    const l = crearElemento('label', label);
    const i = document.createElement('input');
    i.name = name;
    i.type = type;
    i.placeholder = name === 'empresa' ? 'Nombre de la empresa' : 'Buscar prácticas';
    l.append(i);
    fieldset.append(l);
  }
  for (const [name, label, catalogo] of [
    ['area', 'Área', 'area'],
    ['modalidad', 'Tipo o modalidad', 'modalidad'],
  ]) {
    const l = crearElemento('label', label);
    const s = document.createElement('select');
    s.name = name;
    s.dataset.catalogo = catalogo;
    const all = document.createElement('option');
    all.value = '';
    all.textContent = catalogo === 'area' ? 'Todas' : 'Todos';
    s.append(all);
    l.append(s);
    fieldset.append(l);
  }
  for (const [name, label] of [
    ['fecha_inicio_desde', 'Inicio desde'],
    ['fecha_inicio_hasta', 'Inicio hasta'],
  ]) {
    const l = crearElemento('label', label);
    const i = document.createElement('input');
    i.type = 'date';
    i.name = name;
    l.append(i);
    fieldset.append(l);
  }
  const actions = document.createElement('div');
  actions.className = 'acciones-filtros';
  const submit = document.createElement('button');
  submit.type = 'submit';
  submit.textContent = 'Buscar';
  actions.append(submit);
  const reset = document.createElement('button');
  reset.type = 'reset';
  reset.textContent = 'Limpiar';
  actions.append(reset);
  fieldset.append(actions);
  form.append(fieldset);
  const status = document.createElement('p');
  status.setAttribute('role', 'status');
  const list = document.createElement('div');
  list.className = 'lista-practicas';
  list.setAttribute('aria-live', 'polite');
  const nav = document.createElement('nav');
  nav.setAttribute('aria-label', 'Paginación');
  const prev = document.createElement('button');
  prev.type = 'button';
  prev.textContent = 'Anterior';
  prev.disabled = true;
  const page = crearElemento('span', 'Página 1');
  const next = document.createElement('button');
  next.type = 'button';
  next.textContent = 'Siguiente';
  next.disabled = true;
  nav.append(prev, page, next);
  root.append(form, status, list, nav);
  llenarCatalogos();
  const vista = {
    filtros: form,
    lista: list,
    anterior: prev,
    pagina: page,
    siguiente: next,
    estado: status,
  };
  form.addEventListener('submit', (e) => {
    e.preventDefault();
    estado.pagina = 1;
    cargarPracticas(vista);
  });
  form.addEventListener('reset', () =>
    setTimeout(() => {
      estado.pagina = 1;
      cargarPracticas(vista);
    }, 0),
  );
  prev.onclick = () => {
    estado.pagina = Math.max(1, estado.pagina - 1);
    cargarPracticas(vista);
  };
  next.onclick = () => {
    estado.pagina++;
    cargarPracticas(vista);
  };
  cargarPracticas(vista);
}

// Funciones de la pantalla: empresa.

// Muestra el formulario de práctica.
export function formularioPractica(root) {
  encabezado(root, 'Publicar una práctica');
  root.append(crearFormularioPractica());
}

// Construye el formulario de crear o editar.
export function crearFormularioPractica(practica = null) {
  const f = document.createElement('form');
  f.noValidate = true;
  f.className = practica
    ? 'formulario columnas formulario-practica-edicion'
    : 'formulario columnas panel';
  f.id = practica
    ? `formulario-editar-practica-${practica.id_practica}`
    : 'formulario-nueva-practica';
  const campos = [
    ['titulo', 'Título', 'text'],
    ['descripcion', 'Descripción', 'textarea'],
    ['requisitos', 'Requisitos', 'textarea'],
    ['ubicacion', 'Ubicación', 'text'],
    ['fecha_inicio', 'Fecha de inicio', 'date'],
    ['cupo_maximo', 'Cupo máximo', 'text'],
    ['fecha_limite_postulacion', 'Fecha límite de postulación', 'date'],
  ];
  for (const [name, label, type] of campos) {
    const l = crearElemento('label', label);
    if (type === 'textarea') l.className = 'formulario-practica__campo-amplio';
    const input =
      type === 'textarea' ? document.createElement('textarea') : document.createElement('input');
    if (type !== 'textarea') input.type = type;
    input.name = name;
    input.value = practica?.[name] ?? (name === 'cupo_maximo' ? '1' : '');
    const ejemplos = {
      titulo: 'Asistente de desarrollo',
      descripcion: 'Describe las actividades de la práctica',
      requisitos: 'Conocimientos o habilidades requeridas',
      ubicacion: 'Ciudad de Guatemala',
    };
    if (ejemplos[name]) input.placeholder = ejemplos[name];
    input.required = [
      'titulo',
      'descripcion',
      'fecha_inicio',
      'cupo_maximo',
      'fecha_limite_postulacion',
    ].includes(name);
    if (name === 'titulo') input.maxLength = 180;
    if (name === 'descripcion' || name === 'requisitos') input.maxLength = 10000;
    if (name === 'ubicacion') input.maxLength = 255;
    if (name === 'cupo_maximo') {
      input.inputMode = 'numeric';
      input.pattern = '[0-9]+';
      input.title = 'Ingresa solo números enteros.';
    }
    if (
      name === 'fecha_inicio' &&
      (!practica || String(practica.fecha_inicio || '') >= fechaLocal(new Date()))
    )
      input.min = fechaLocal(new Date());
    if (name === 'fecha_limite_postulacion') {
      const manana = new Date();
      manana.setDate(manana.getDate() + 1);
      input.min = fechaLocal(manana);
    }
    l.append(input);
    f.append(l);
  }
  for (const [name, label, tipo, clave] of [
    ['id_area', 'Área', 'area', 'id_area'],
    ['id_modalidad', 'Modalidad', 'modalidad', 'id_modalidad'],
  ]) {
    const l = crearElemento('label', label);
    const select = document.createElement('select');
    select.name = name;
    select.required = true;
    const placeholder = document.createElement('option');
    placeholder.value = '';
    placeholder.textContent = `Selecciona ${label.toLowerCase()}`;
    select.append(placeholder);
    for (const item of estado.catalogos.filter((x) => x.tipo === tipo)) {
      const option = document.createElement('option');
      option.value = item.id;
      option.textContent = item.nombre;
      option.selected = Number(item.id) === Number(practica?.[clave]);
      select.append(option);
    }
    l.append(select);
    f.append(l);
  }
  const actions = document.createElement('div');
  actions.className = 'formulario-practica__acciones';
  const submit = crearElemento(
    'button',
    practica ? 'Guardar y reenviar a revisión' : 'Enviar a revisión',
    'boton primario',
  );
  actions.append(submit);
  f.append(actions);
  const fechaInicio = f.elements.namedItem('fecha_inicio');
  const fechaLimite = f.elements.namedItem('fecha_limite_postulacion');
  const cupoMaximo = f.elements.namedItem('cupo_maximo');
  const fechaInicioMinimaBase = fechaInicio.min;
  const validarCupo = () => {
    cupoMaximo.setCustomValidity('');
    if (cupoMaximo.value && /^\d+$/.test(cupoMaximo.value) && Number(cupoMaximo.value) < 1) {
      cupoMaximo.setCustomValidity('Ingresa un número entero mayor que cero.');
    }
  };
  const validarFechas = () => {
    const minimos = [fechaInicioMinimaBase, fechaLimite.value].filter(Boolean).sort();
    fechaInicio.min = minimos.at(-1) || '';
    fechaInicio.setCustomValidity('');
    if (fechaInicio.value && fechaLimite.value && fechaInicio.value < fechaLimite.value) {
      fechaInicio.setCustomValidity(
        'La fecha de inicio debe ser igual o posterior al último día para postularse.',
      );
    }
  };
  fechaInicio.addEventListener('input', validarFechas);
  fechaInicio.addEventListener('change', validarFechas);
  fechaLimite.addEventListener('input', validarFechas);
  fechaLimite.addEventListener('change', validarFechas);
  cupoMaximo.addEventListener('input', validarCupo);
  cupoMaximo.addEventListener('change', validarCupo);
  validarCupo();
  validarFechas();
  f.onsubmit = async (event) => {
    event.preventDefault();
    validarFechas();
    validarCupo();
    if (!validarFormularioEspanol(f)) return;
    limpiarErroresCampos(f);
    f.querySelector('[data-error-formulario]')?.remove();
    try {
      await durantePeticion(
        submit,
        async () => {
          await peticion(practica ? `practicas/${practica.id_practica}` : 'practicas', {
            method: practica ? 'PUT' : 'POST',
            body: JSON.stringify(formulario(f)),
          });
          f.closest('dialog')?.close();
          mostrar(
            practica
              ? 'Los cambios se enviaron a revisión.'
              : 'La propuesta fue enviada a revisión.',
          );
          await actualizarPantalla();
        },
        'Enviando…',
      );
    } catch (error) {
      if (Object.keys(error.errores || {}).length) pintarErroresCampos(f, error.errores);
      else {
        const aviso = document.createElement('p');
        aviso.className = 'error-formulario alerta alerta--error';
        aviso.dataset.errorFormulario = '';
        aviso.setAttribute('role', 'alert');
        aviso.textContent = error.message;
        f.prepend(aviso);
      }
      mostrar(error.message, true);
    }
  };
  return f;
}

// Abre la edición de una práctica.
export function editarPractica(practica) {
  const dialog = document.createElement('dialog');
  dialog.className = 'dialogo dialogo-editar-practica';
  const body = document.createElement('article');
  body.className = 'detalle-practica';
  const heading = document.createElement('div');
  heading.className = 'detalle-practica__encabezado';
  heading.append(
    crearElemento('h2', 'Editar práctica'),
    crearElemento('p', 'Actualiza los datos y envía los cambios a revisión.'),
  );
  const form = crearFormularioPractica(practica);
  body.append(heading, form);
  const cancel = document.createElement('button');
  cancel.type = 'button';
  cancel.className = 'boton secundario';
  cancel.textContent = 'Cancelar';
  cancel.onclick = () => dialog.close();
  form.querySelector('.formulario-practica__acciones')?.prepend(cancel);
  dialog.append(body);
  dialog.addEventListener('close', () => dialog.remove(), { once: true });
  document.body.append(dialog);
  dialog.showModal();
}

// Lista de prácticas de la empresa.
export async function renderPracticasPropias(root, filas) {
  encabezado(root, 'Mis prácticas');
  const filtros = document.createElement('form');
  filtros.className = 'filtros';
  const busquedaLabel = crearElemento('label', 'Buscar práctica');
  const busqueda = document.createElement('input');
  busqueda.type = 'search';
  busqueda.name = 'q';
  busqueda.placeholder = 'Buscar';
  busquedaLabel.append(busqueda);
  const estadoLabel = crearElemento('label', 'Estado');
  const estadoSelect = document.createElement('select');
  estadoSelect.name = 'estado';
  const todos = document.createElement('option');
  todos.value = '';
  todos.textContent = 'Todos los estados';
  estadoSelect.append(todos);
  estado.catalogos
    .filter((item) => item.tipo === 'practica')
    .forEach((item) => {
      const opcion = document.createElement('option');
      opcion.value = item.codigo;
      opcion.textContent = item.nombre;
      estadoSelect.append(opcion);
    });
  estadoLabel.append(estadoSelect);
  const buscar = document.createElement('button');
  buscar.type = 'submit';
  buscar.className = 'boton secundario';
  buscar.textContent = 'Filtrar';
  filtros.append(busquedaLabel, estadoLabel, buscar);
  root.append(filtros);
  const lista = document.createElement('div');
  lista.className = 'lista-practicas';
  root.append(lista);
  const pintar = async () => {
    lista.replaceChildren();
    const q = busqueda.value.trim().toLocaleLowerCase('es');
    const resultados = filas.filter(
      (fila) =>
        (!estadoSelect.value || fila.estado_codigo === estadoSelect.value) &&
        (!q ||
          String(fila.titulo || '')
            .toLocaleLowerCase('es')
            .includes(q)),
    );
    if (!resultados.length) {
      const vacio = crearElemento('p', 'No hay prácticas que coincidan con los filtros.');
      lista.append(vacio);
      return;
    }
    for (const x of resultados) {
      const a = document.createElement('article');
      a.className = 'tarjeta tarjeta-practica';
      const identidad = document.createElement('div');
      identidad.className = 'tarjeta-practica__identidad';
      identidad.append(
        crearElemento('h3', x.titulo),
        crearElemento('span', x.area || 'Área sin especificar', 'etiqueta'),
      );
      const estadoPractica = document.createElement('div');
      estadoPractica.className = 'tarjeta-practica__estado';
      etiquetaEstado(estadoPractica, x.estado, 'practica', x.estado_codigo);
      const encabezadoPractica = document.createElement('div');
      encabezadoPractica.className = 'tarjeta-practica__encabezado';
      encabezadoPractica.append(identidad, estadoPractica);

      const descripcion = crearElemento(
        'p',
        x.descripcion || 'Sin descripción.',
        'tarjeta-practica__descripcion',
      );
      const cuposMaximos = Number(x.cupo_maximo) || 0;
      const cuposOcupados = Number(x.cupos_ocupados) || 0;
      const datos = document.createElement('dl');
      datos.className = 'tarjeta-practica__datos';
      datos.append(
        datoPractica('Modalidad', x.modalidad),
        datoPractica('Ubicación', x.ubicacion || 'Por definir'),
        datoPractica('Inicio de la práctica', fechaLegible(x.fecha_inicio)),
        datoPractica('Último día para postularse', fechaLegible(x.fecha_limite_postulacion)),
        datoPractica('Cupos ocupados', `${cuposOcupados} de ${cuposMaximos}`),
        datoPractica('Postulaciones recibidas', Number(x.total_postulaciones) || 0),
        datoPractica('Pendientes de revisión', Number(x.pendientes) || 0),
      );
      a.append(encabezadoPractica, descripcion, datos);
      if (x.requisitos) {
        a.append(
          crearElemento('p', `Requisitos: ${x.requisitos}`, 'tarjeta-practica__descripcion'),
        );
      }
      const acciones = document.createElement('div');
      acciones.className = 'tarjeta-practica__acciones';
      a.append(acciones);
      lista.append(a);
      try {
        const opciones = await transiciones('practica', x.estado_codigo);
        if (opciones.some((t) => t.estado_codigo === 'pendiente_revision'))
          boton('Editar y reenviar a revisión', () => editarPractica(x), acciones);
        if (opciones.some((t) => t.estado_codigo === 'cerrada'))
          boton(
            'Cerrar práctica',
            async () => {
              const confirmacion = await confirmarAccion(
                'Cerrar la práctica también responderá las postulaciones pendientes.',
                { titulo: 'Cerrar práctica', textoConfirmar: 'Cerrar práctica' },
              );
              if (!confirmacion.confirmado) return;
              await peticion(`practicas/${x.id_practica}/cerrar`, { method: 'POST', body: '{}' });
              mostrar('Práctica cerrada.');
              await actualizarPantalla();
            },
            acciones,
          );
      } catch (error) {
        mostrar(error.message, true);
      }
    }
  };
  filtros.addEventListener('submit', (evento) => {
    evento.preventDefault();
    void pintar();
  });
  filtros.addEventListener('change', () => void pintar());
  await pintar();
}
