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
  tarjeta,
  etiquetaEstado,
  fechaLocal,
  encabezado,
  transiciones,
} from './comun.js';
import {
  confirmarAccion,
  durantePeticion,
  limpiarErroresCampos,
  pintarErroresCampos,
} from './ui.js';

// Funciones de la pantalla: practicas.

export function renderPracticas(items, postulaciones = new Map(), root = $('#lista-practicas')) {
  root.replaceChildren();
  if (!items.length) {
    const p = crearElemento('p', 'No hay oportunidades que coincidan con los filtros.');
    root.append(p);
    return;
  }
  for (const x of items) {
    const card = document.createElement('article');
    card.className = 'tarjeta';
    const h = crearElemento('h3', x.titulo);
    const badge = crearElemento('span', x.area, 'etiqueta');
    const p = crearElemento(
      'p',
      `${x.empresa} · ${x.modalidad} · ${x.ubicacion || 'Ubicación por definir'}`,
    );
    const d = crearElemento('p', x.descripcion);
    const f = crearElemento(
      'p',
      `Inicio: ${x.fecha_inicio || 'Por definir'} · Fecha límite: ${x.fecha_limite_postulacion} · Cupos ocupados: ${x.cupos_ocupados}/${x.cupo_maximo}`,
    );
    const b = document.createElement('button');
    b.className = 'boton primario';
    const postulacion = postulaciones.get(Number(x.id_practica));
    b.textContent =
      estado.usuario?.rol_codigo === 'estudiante' && !postulacion
        ? 'Ver y postularme'
        : 'Ver detalle';
    b.onclick = () => verDetalle(x.id_practica, postulacion);
    card.append(h, badge, p, d, f, b);
    etiquetaEstado(card, x.estado, 'practica', x.estado_codigo);
    root.append(card);
  }
}

export async function cargarPracticas(vista = {}) {
  const lista = vista.lista || $('#lista-practicas');
  const filtros = vista.filtros || $('#filtros');
  const anterior = vista.anterior || $('#pagina-anterior');
  const paginaTexto = vista.pagina || $('#pagina-actual');
  const siguiente = vista.siguiente || $('#pagina-siguiente');
  const estadoCarga = vista.estado || $('#estado-carga');
  if (!lista || !filtros) return;
  const f = formulario(filtros);
  const desde = f.fecha_inicio_desde || '';
  const hasta = f.fecha_inicio_hasta || '';
  if (desde && hasta && desde > hasta) {
    const campoDesde = filtros.elements.namedItem('fecha_inicio_desde');
    const campoHasta = filtros.elements.namedItem('fecha_inicio_hasta');
    campoDesde?.setCustomValidity('La fecha inicial no puede ser posterior a la fecha final.');
    campoHasta?.setCustomValidity('La fecha final no puede ser anterior a la fecha inicial.');
    campoDesde?.reportValidity();
    campoDesde?.addEventListener('input', () => campoDesde.setCustomValidity(''), { once: true });
    campoHasta?.addEventListener('input', () => campoHasta.setCustomValidity(''), { once: true });
    if (estadoCarga) estadoCarga.textContent = 'Corrige el rango de fechas para buscar.';
    return;
  }
  filtros.elements.namedItem('fecha_inicio_desde')?.setCustomValidity('');
  filtros.elements.namedItem('fecha_inicio_hasta')?.setCustomValidity('');
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

export async function verDetalle(id, postulacion = null) {
  try {
    const r = await peticion(`practicas/${id}`);
    const x = r.datos;
    const dialog = document.createElement('dialog');
    dialog.className = 'dialogo';
    const article = document.createElement('article');
    article.className = 'detalle-practica';
    const title = crearElemento('h2', x.titulo);
    const company = crearElemento(
      'p',
      `${x.empresa} · ${x.area} · ${x.modalidad}`,
      'texto-secundario',
    );
    const description = crearElemento('p', x.descripcion);
    const requirements = crearElemento('p', `Requisitos: ${x.requisitos || 'No especificados.'}`);
    const locationText = crearElemento('p', `Ubicación: ${x.ubicacion || 'Por definir'}`);
    const dates = crearElemento(
      'p',
      `Inicio: ${x.fecha_inicio || 'Por definir'} · Fecha límite: ${x.fecha_limite_postulacion}`,
    );
    const capacity = crearElemento('p', `Cupos ocupados: ${x.cupos_ocupados}/${x.cupo_maximo}`);
    article.append(title, company, description, requirements, locationText, dates, capacity);
    const actions = document.createElement('div');
    actions.className = 'acciones';
    const close = document.createElement('button');
    close.type = 'button';
    close.className = 'boton secundario';
    close.textContent = 'Cerrar';
    close.onclick = () => dialog.close();
    actions.append(close);
    if (
      estado.usuario?.rol_codigo === 'estudiante' &&
      !estado.usuario.debe_cambiar_password &&
      postulacion
    ) {
      const note = crearElemento(
        'p',
        postulacion === 'retirada'
          ? 'Retiraste esta postulación. Puedes revisar si aún se permite reactivarla en Mis postulaciones.'
          : 'Ya tienes una postulación para esta práctica.',
        'aviso',
      );
      const tracking = document.createElement('a');
      tracking.href = `${appBase}/pages/postulaciones.php`;
      tracking.textContent = 'Ver mis postulaciones';
      article.append(note, tracking);
    } else if (
      estado.usuario?.rol_codigo === 'estudiante' &&
      !estado.usuario.debe_cambiar_password
    ) {
      const form = document.createElement('form');
      form.className = 'formulario';
      const label = crearElemento('label', 'Carta de presentación (opcional)');
      const letter = document.createElement('textarea');
      letter.name = 'carta_presentacion';
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
            article.append(aviso);
          }
          submit.disabled = false;
        }
      };
      article.append(form);
    } else if (!estado.usuario) {
      const login = document.createElement('a');
      login.className = 'boton primario';
      login.href = `${appBase}/public/login.html`;
      login.textContent = 'Iniciar sesión para postularte';
      actions.append(login);
    } else if (estado.usuario.debe_cambiar_password) {
      const note = crearElemento('p', 'Cambia tu contraseña temporal antes de postularte.');
      article.append(note);
    }
    article.append(actions);
    dialog.append(article);
    document.body.append(dialog);
    dialog.addEventListener('close', () => dialog.remove(), { once: true });
    dialog.showModal();
  } catch (e) {
    mostrar(e.message, true);
  }
}

export function crearCatalogoModulo(root) {
  const form = document.createElement('form');
  form.id = 'filtros-modulo';
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
    all.textContent = `Todas: ${label.toLowerCase()}`;
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
  const submit = document.createElement('button');
  submit.type = 'submit';
  submit.textContent = 'Buscar';
  fieldset.append(submit);
  const reset = document.createElement('button');
  reset.type = 'reset';
  reset.textContent = 'Limpiar';
  fieldset.append(reset);
  form.append(fieldset);
  const status = document.createElement('p');
  status.setAttribute('role', 'status');
  const list = document.createElement('div');
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

export function formularioPractica(root) {
  encabezado(root, 'Publicar una práctica');
  root.append(crearFormularioPractica());
}

export function crearFormularioPractica(practica = null) {
  const f = document.createElement('form');
  f.className = 'formulario columnas panel';
  f.id = practica
    ? `formulario-editar-practica-${practica.id_practica}`
    : 'formulario-nueva-practica';
  const campos = [
    ['titulo', 'Título', 'text'],
    ['descripcion', 'Descripción', 'textarea'],
    ['requisitos', 'Requisitos', 'textarea'],
    ['ubicacion', 'Ubicación', 'text'],
    ['fecha_inicio', 'Fecha de inicio', 'date'],
    ['cupo_maximo', 'Cupo máximo', 'number'],
    ['fecha_limite_postulacion', 'Fecha límite de postulación', 'date'],
  ];
  for (const [name, label, type] of campos) {
    const l = crearElemento('label', label);
    const input =
      type === 'textarea' ? document.createElement('textarea') : document.createElement('input');
    if (type !== 'textarea') input.type = type;
    input.name = name;
    input.value = practica?.[name] ?? (name === 'cupo_maximo' ? '1' : '');
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
      input.min = '1';
      input.step = '1';
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
  const submit = crearElemento(
    'button',
    practica ? 'Guardar y reenviar a revisión' : 'Enviar a revisión',
    'boton primario',
  );
  f.append(submit);
  f.onsubmit = async (event) => {
    event.preventDefault();
    if (!f.reportValidity()) return;
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

export function editarPractica(practica) {
  const dialog = document.createElement('dialog');
  dialog.className = 'dialogo';
  const body = document.createElement('article');
  body.className = 'detalle-practica';
  const title = crearElemento('h2', 'Editar práctica');
  body.append(title, crearFormularioPractica(practica));
  const cancel = document.createElement('button');
  cancel.type = 'button';
  cancel.className = 'boton secundario';
  cancel.textContent = 'Cancelar';
  cancel.onclick = () => dialog.close();
  body.append(cancel);
  dialog.append(body);
  dialog.addEventListener('close', () => dialog.remove(), { once: true });
  document.body.append(dialog);
  dialog.showModal();
}

export async function renderPracticasPropias(root, filas) {
  encabezado(root, 'Mis prácticas');
  const filtros = document.createElement('form');
  filtros.className = 'filtros';
  const busquedaLabel = crearElemento('label', 'Buscar práctica');
  const busqueda = document.createElement('input');
  busqueda.type = 'search';
  busqueda.name = 'q';
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
  lista.className = 'tarjetas';
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
      const a = tarjeta(lista, x.titulo, [
        `${x.estado} · Inicio: ${x.fecha_inicio || 'Por definir'} · Cierre: ${x.fecha_limite_postulacion}`,
        `Postulaciones: ${x.total_postulaciones} · pendientes: ${x.pendientes}`,
      ]);
      try {
        const opciones = await transiciones('practica', x.estado_codigo);
        etiquetaEstado(a, x.estado, 'practica', x.estado_codigo);
        if (opciones.some((t) => t.estado_codigo === 'pendiente_revision'))
          boton('Editar y reenviar a revisión', () => editarPractica(x), a);
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
            a,
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
