import {
  crearElemento,
  mostrar,
  peticion,
  estado,
  $,
  actualizarPantalla,
  boton,
  encabezado,
  etiquetaEstado,
  transiciones,
  pedirComentario,
} from './comun.js?v=nav-20261111-2';
import { confirmarAccion } from './ui.js?v=validacion-es-20261111-3';

// Funciones de la pantalla: postulaciones.

function fechaPostulacionLegible(fecha) {
  if (!fecha) return 'No indicada';
  const soloFecha = String(fecha).match(/^(\d{4})-(\d{2})-(\d{2})$/);
  if (soloFecha) {
    const [, anio, mes, dia] = soloFecha;
    return new Intl.DateTimeFormat('es-GT', {
      dateStyle: 'long',
      timeZone: 'UTC',
    }).format(new Date(Date.UTC(Number(anio), Number(mes) - 1, Number(dia))));
  }
  const valor = new Date(String(fecha).replace(' ', 'T'));
  if (Number.isNaN(valor.getTime())) return String(fecha);
  return new Intl.DateTimeFormat('es-GT', {
    dateStyle: 'long',
    timeStyle: 'short',
  }).format(valor);
}

function datoPostulacion(etiqueta, valor) {
  const dato = document.createElement('div');
  dato.className = 'dato-postulacion';
  dato.append(crearElemento('dt', etiqueta), crearElemento('dd', valor || 'No indicado'));
  return dato;
}

function codigoEstadoHistorial(nombre) {
  const texto = String(nombre || '')
    .toLocaleLowerCase('es')
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '');
  if (texto.includes('pendient')) return 'pendiente';
  if (texto.includes('aceptad')) return 'aceptada';
  if (texto.includes('rechazad')) return 'rechazada';
  if (texto.includes('retirad')) return 'retirada';
  if (texto.includes('cancelad')) return 'cancelada';
  return '';
}

function nombreEtapa(codigo, fallback = '') {
  return {
    pendiente: 'En revisión',
    aceptada: 'Aceptada',
    rechazada: 'No seleccionada',
    retirada: 'Retirada por ti',
    cancelada: 'Cancelada',
  }[codigo] || fallback || 'Estado actualizado';
}

function agregarPasoSeguimiento(
  lista,
  { titulo, fecha, comentario, actor, codigo, clase = 'completado' },
) {
  const paso = document.createElement('li');
  paso.className = `paso-seguimiento ${clase}`;
  if (codigo) paso.classList.add(`estado-${codigo}`);
  paso.append(crearElemento('strong', titulo));
  if (fecha) {
    const tiempo = document.createElement('time');
    tiempo.dateTime = fecha;
    tiempo.textContent = fechaPostulacionLegible(fecha);
    paso.append(tiempo);
  }
  if (actor) paso.append(crearElemento('span', `Responsable: ${actor}`));
  if (comentario) paso.append(crearElemento('p', comentario));
  lista.append(paso);
  return paso;
}

function pintarSeguimiento(contenedor, postulacion, eventos, fallo = false) {
  const titulo = crearElemento('h4', 'Seguimiento de tu postulación');
  const codigoActual = postulacion.estado_codigo;
  const pasos = document.createElement('ol');
  pasos.className = 'seguimiento-postulacion__pasos';

  const eventosOrdenados = [...eventos].sort(
    (a, b) => new Date(a.fecha_hora).getTime() - new Date(b.fecha_hora).getTime(),
  );
  const inicio = eventosOrdenados.find((evento) => !evento.estado_anterior);
  agregarPasoSeguimiento(pasos, {
    titulo: 'Solicitud enviada',
    fecha: inicio?.fecha_hora || postulacion.fecha_postulacion,
    actor: inicio?.actor,
    codigo: 'pendiente',
  });

  const fechaCierre = String(postulacion.fecha_limite_postulacion || '').slice(0, 10);
  if (/^\d{4}-\d{2}-\d{2}$/.test(fechaCierre)) {
    const [anio, mes, dia] = fechaCierre.split('-').map(Number);
    const cierre = new Date(anio, mes - 1, dia);
    const hoy = new Date();
    hoy.setHours(0, 0, 0, 0);
    const cierreCompletado = codigoActual !== 'pendiente' || cierre <= hoy;
    agregarPasoSeguimiento(pasos, {
      titulo: 'Cierre de solicitudes',
      fecha: fechaCierre,
      comentario: cierreCompletado
        ? 'Esta es la fecha límite para enviar solicitudes.'
        : 'Puedes enviar solicitudes hasta este día.',
      clase: cierreCompletado ? 'completado' : 'actual',
    });
  }

  const cambios = eventosOrdenados.filter((evento) => evento.estado_anterior);
  cambios.forEach((evento) => {
    const codigo = codigoEstadoHistorial(evento.estado_nuevo);
    agregarPasoSeguimiento(pasos, {
      titulo: nombreEtapa(codigo, evento.estado_nuevo),
      fecha: evento.fecha_hora,
      actor: evento.actor,
      comentario: evento.comentario,
      codigo,
    });
  });

  const estadoFinalHistorial = cambios.length
    ? codigoEstadoHistorial(cambios[cambios.length - 1].estado_nuevo)
    : codigoEstadoHistorial(inicio?.estado_nuevo);
  if (codigoActual === 'pendiente') {
    agregarPasoSeguimiento(pasos, {
      titulo: 'Esperando respuesta de la empresa',
      comentario: 'La empresa todavía no ha actualizado el estado de tu solicitud.',
      codigo: codigoActual,
      clase: 'actual',
    });
  } else if (estadoFinalHistorial === codigoActual && cambios.length) {
    pasos.lastElementChild.classList.add('actual', 'final');
    pasos.lastElementChild.classList.remove('completado');
  } else {
    agregarPasoSeguimiento(pasos, {
      titulo: nombreEtapa(codigoActual, postulacion.estado),
      codigo: codigoActual,
      comentario: fallo
        ? 'Este es el estado actual registrado en la base de datos.'
        : '',
      clase: 'actual final',
    });
  }

  const ayuda = crearElemento(
    'p',
    fallo
      ? 'No se pudo cargar el detalle de los cambios. El estado actual sí proviene de la base de datos.'
      : 'El seguimiento se actualiza cuando la empresa cambia el estado de tu solicitud.',
    'seguimiento-postulacion__ayuda',
  );
  contenedor.replaceChildren(titulo, ayuda, pasos);
}

async function cargarSeguimiento(contenedor, postulacion) {
  try {
    const respuesta = await peticion(`postulaciones/${postulacion.id_postulacion}/historial`);
    pintarSeguimiento(contenedor, postulacion, respuesta.datos || []);
  } catch {
    pintarSeguimiento(contenedor, postulacion, [], true);
  }
}

export async function renderSolicitudes(root, filas, estudiante, opciones = {}) {
  encabezado(
    root,
    estudiante
      ? 'Mis postulaciones'
      : estado.usuario?.rol_codigo === 'empresa'
        ? 'Postulaciones recibidas por práctica'
        : 'Postulaciones',
  );
  const filtros = document.createElement('form');
  filtros.className = 'filtros';
  const terminoLabel = crearElemento(
    'label',
    opciones.buscarEnPagina
      ? 'Buscar en esta página'
      : estudiante
        ? 'Buscar práctica'
        : 'Buscar práctica o postulante',
  );
  const termino = document.createElement('input');
  termino.type = 'search';
  termino.name = 'q';
  termino.placeholder = 'Buscar';
  terminoLabel.append(termino);
  const estadoLabel = crearElemento(
    'label',
    opciones.buscarEnPagina ? 'Estado en esta página' : 'Estado',
  );
  const estadoSelect = document.createElement('select');
  estadoSelect.name = 'estado';
  const todos = document.createElement('option');
  todos.value = '';
  todos.textContent = 'Todos los estados';
  estadoSelect.append(todos);
  let estadosPostulacion = estado.catalogos.filter((item) => item.tipo === 'postulacion');
  try {
    const catalogo = await peticion('catalogos?tipo=postulacion');
    if (Array.isArray(catalogo.datos)) estadosPostulacion = catalogo.datos;
  } catch {
    // Conserva el catálogo ya cargado y completa con los estados presentes en las filas.
  }
  const porCodigo = new Map(estadosPostulacion.map((item) => [item.codigo, item]));
  filas.forEach((fila) => {
    if (fila.estado_codigo && !porCodigo.has(fila.estado_codigo)) {
      porCodigo.set(fila.estado_codigo, {
        codigo: fila.estado_codigo,
        nombre: fila.estado || fila.estado_codigo,
      });
    }
  });
  [...porCodigo.values()]
    .sort((a, b) => String(a.nombre).localeCompare(String(b.nombre), 'es'))
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
  filtros.append(terminoLabel, estadoLabel, buscar);
  root.append(filtros);
  const lista = document.createElement('div');
  lista.className = estudiante
    ? 'tarjetas tarjetas-postulaciones'
    : 'lista-practicas lista-postulaciones-grupos';
  root.append(lista);
  const pintar = async () => {
    lista.replaceChildren();
    const q = termino.value.trim().toLocaleLowerCase('es');
    let seleccionadas = filas.filter(
      (fila) =>
        (!estadoSelect.value || fila.estado_codigo === estadoSelect.value) &&
        (!q ||
          `${fila.practica || ''} ${fila.nombres || ''} ${fila.apellidos || ''}`
            .toLocaleLowerCase('es')
            .includes(q)),
    );
    if (!seleccionadas.length) {
      const vacio = crearElemento('p', 'No hay postulaciones que coincidan con los filtros.');
      lista.append(vacio);
      return;
    }
    if (!estudiante)
      seleccionadas = [...seleccionadas].sort((a, b) =>
        String(a.practica).localeCompare(String(b.practica), 'es'),
      );
    let practicaActual = null;
    let listaPracticaActual = null;
    for (const x of seleccionadas) {
      if (!estudiante && Number(x.id_practica) !== practicaActual) {
        practicaActual = Number(x.id_practica);
        const grupo = document.createElement('div');
        grupo.className = 'grupo-postulaciones';
        const tituloGrupo = crearElemento('h3', x.practica || 'Práctica');
        tituloGrupo.className = 'grupo-postulaciones__titulo';
        listaPracticaActual = document.createElement('div');
        listaPracticaActual.className = 'lista-practicas';
        grupo.append(tituloGrupo, listaPracticaActual);
        lista.append(grupo);
      }
      let item;
      let contenedorAcciones;
      let notas;
      let seguimiento;
      if (estudiante) {
        item = document.createElement('article');
        item.className = 'tarjeta tarjeta-postulacion';

        const titulo = crearElemento('h3', x.practica || 'Postulación');
        const estadoPostulacion = document.createElement('div');
        estadoPostulacion.className = 'tarjeta-postulacion__estado';
        etiquetaEstado(estadoPostulacion, x.estado, 'postulacion', x.estado_codigo);
        const encabezadoPostulacion = document.createElement('div');
        encabezadoPostulacion.className = 'tarjeta-postulacion__encabezado';
        encabezadoPostulacion.append(titulo, estadoPostulacion);

        const datos = document.createElement('dl');
        datos.className = 'tarjeta-postulacion__datos';
        datos.append(
          datoPostulacion('Empresa', x.empresa),
          datoPostulacion('Enviada el', fechaPostulacionLegible(x.fecha_postulacion)),
        );

        const contenido = document.createElement('div');
        contenido.className = 'tarjeta-postulacion__contenido';
        contenido.append(datos);
        item.append(encabezadoPostulacion, contenido);
        const carta = document.createElement('section');
        carta.className = 'tarjeta-postulacion__carta';
        carta.append(
          crearElemento('h4', 'Carta de presentación'),
          crearElemento('p', x.carta_presentacion || 'No agregaste una carta de presentación.'),
        );
        contenido.append(carta);
        notas = document.createElement('div');
        notas.className = 'tarjeta-postulacion__notas';
        if (x.comentario) {
          const observacion = document.createElement('section');
          observacion.className = 'tarjeta-postulacion__observacion';
          observacion.append(
            crearElemento('h4', 'Observación'),
            crearElemento('p', x.comentario),
          );
          notas.append(observacion);
        }
        contenido.append(notas);
        seguimiento = document.createElement('section');
        seguimiento.className = 'seguimiento-postulacion';
        seguimiento.append(
          crearElemento('h4', 'Seguimiento de tu postulación'),
          crearElemento('p', 'Cargando las actualizaciones…', 'seguimiento-postulacion__ayuda'),
        );
        contenedorAcciones = document.createElement('div');
        contenedorAcciones.className = 'tarjeta-postulacion__acciones';
        item.append(seguimiento, contenedorAcciones);
        lista.append(item);
        void cargarSeguimiento(seguimiento, x);
      } else {
        item = document.createElement('article');
        item.className = 'tarjeta tarjeta-practica tarjeta-postulacion-recibida';
        const identidad = document.createElement('div');
        identidad.className = 'tarjeta-practica__identidad';
        identidad.append(
          crearElemento(
            'h3',
            `${x.nombres || ''} ${x.apellidos || ''}`.trim() || 'Postulante',
          ),
        );
        const estadoPostulacion = document.createElement('div');
        estadoPostulacion.className = 'tarjeta-practica__estado';
        etiquetaEstado(estadoPostulacion, x.estado, 'postulacion', x.estado_codigo);
        const encabezadoPostulacion = document.createElement('div');
        encabezadoPostulacion.className = 'tarjeta-practica__encabezado';
        encabezadoPostulacion.append(identidad, estadoPostulacion);

        const datos = document.createElement('dl');
        datos.className = 'tarjeta-practica__datos';
        datos.append(
          datoPostulacion('Carrera', x.carrera),
          datoPostulacion('Correo', x.correo),
          datoPostulacion('Teléfono', x.telefono),
          datoPostulacion('Enviada el', fechaPostulacionLegible(x.fecha_postulacion)),
        );
        item.append(encabezadoPostulacion, datos);
        if (x.carta_presentacion) {
          const carta = document.createElement('section');
          carta.className = 'tarjeta-postulacion__carta';
          carta.append(
            crearElemento('h4', 'Carta de presentación'),
            crearElemento('p', x.carta_presentacion),
          );
          item.append(carta);
        }
        if (x.comentario) {
          const observacion = document.createElement('section');
          observacion.className = 'tarjeta-postulacion__observacion';
          observacion.append(
            crearElemento('h4', 'Observación'),
            crearElemento('p', x.comentario),
          );
          item.append(observacion);
        }
        contenedorAcciones = document.createElement('div');
        contenedorAcciones.className = 'tarjeta-practica__acciones tarjeta-postulacion__acciones';
        item.append(contenedorAcciones);
        listaPracticaActual.append(item);
      }
      if (!estudiante)
        boton('Ver perfil del postulante', () => verPerfilPostulante(x), contenedorAcciones);

      if (estudiante && x.estado_codigo === 'retirada') {
        let practicaAbierta = false;
        try {
          await peticion(`practicas/${x.id_practica}`);
          practicaAbierta = true;
        } catch {
          /* La práctica ya no está publicada. */
        }
        if (practicaAbierta)
          boton(
            'Reactivar postulación',
            async () => {
              await peticion('postulaciones', {
                method: 'POST',
                body: JSON.stringify({ id_practica: Number(x.id_practica) }),
              });
              mostrar('Postulación reactivada.');
              await actualizarPantalla();
            },
            contenedorAcciones,
          );
        else {
          const aviso = crearElemento('p', 'Esta práctica ya no recibe postulaciones.', 'aviso');
          (notas || item).append(aviso);
        }
      } else {
        try {
          const opciones = await transiciones('postulacion', x.estado_codigo);
          if (estudiante && opciones.some((opcion) => opcion.estado_codigo === 'retirada'))
            boton(
              'Retirar',
              async () => {
                const confirmacion = await confirmarAccion(
                  'La postulación dejará de estar activa. Puedes reactivarla mientras la práctica siga recibiendo solicitudes.',
                  { titulo: 'Retirar postulación', textoConfirmar: 'Retirar' },
                );
                if (!confirmacion.confirmado) return;
                await peticion(`postulaciones/${x.id_postulacion}`, { method: 'DELETE' });
                mostrar('Postulación retirada.');
                await actualizarPantalla();
              },
              contenedorAcciones,
            );
          if (!estudiante)
            for (const transicion of opciones)
              boton(
                transicion.estado_nombre,
                async () => {
                  const comentario = await pedirComentario(transicion);
                  if (comentario === null) return;
                  await peticion(`postulaciones/${x.id_postulacion}`, {
                    method: 'PUT',
                    body: JSON.stringify({
                      estado_codigo: transicion.estado_codigo,
                      comentario: comentario || '',
                    }),
                  });
                  mostrar('Estado de postulación actualizado.');
                  await actualizarPantalla();
                },
                contenedorAcciones,
              );
        } catch (error) {
          mostrar(error.message, true);
        }
      }
    }
  };
  filtros.addEventListener('submit', (evento) => {
    evento.preventDefault();
    void pintar();
  });
  estadoSelect.addEventListener('change', () => void pintar());
  await pintar();
}

export function verPerfilPostulante(postulacion) {
  const dialog = document.createElement('dialog');
  dialog.className = 'dialogo dialogo-usuario dialogo-perfil-postulante';
  const article = document.createElement('article');
  article.className = 'detalle-usuario detalle-postulante';
  const heading = document.createElement('div');
  heading.className = 'detalle-usuario__encabezado';
  const identidad = document.createElement('div');
  identidad.className = 'detalle-usuario__identidad';
  identidad.append(
    crearElemento(
      'h2',
      `${postulacion.nombres || ''} ${postulacion.apellidos || ''}`.trim() || 'Perfil del postulante',
    ),
    crearElemento('p', `Postulación para ${postulacion.practica || 'la práctica'}`),
  );
  const estadoPostulacion = document.createElement('div');
  estadoPostulacion.className = 'detalle-usuario__estado';
  etiquetaEstado(
    estadoPostulacion,
    postulacion.estado,
    'postulacion',
    postulacion.estado_codigo,
  );
  heading.append(identidad, estadoPostulacion);
  article.append(heading);

  const datos = document.createElement('dl');
  datos.className = 'detalle-usuario__datos';
  const details = [
    ['Carrera', postulacion.carrera],
    ['Carné', postulacion.carnet],
    ['Universidad', postulacion.universidad],
    ['Semestre', postulacion.semestre],
    ['Correo', postulacion.correo],
    ['Teléfono', postulacion.telefono],
    ['Fecha de postulación', fechaPostulacionLegible(postulacion.fecha_postulacion)],
  ];
  for (const [label, value] of details) {
    const row = document.createElement('div');
    row.className = 'detalle-usuario__dato';
    row.append(crearElemento('dt', label), crearElemento('dd', value || 'No indicado'));
    datos.append(row);
  }
  article.append(datos);
  if (postulacion.comentario) {
    const comentario = document.createElement('section');
    comentario.className = 'tarjeta-postulacion__observacion';
    comentario.append(
      crearElemento('h4', 'Comentario de la postulación'),
      crearElemento('p', postulacion.comentario),
    );
    article.append(comentario);
  }
  const actions = document.createElement('div');
  actions.className = 'detalle-usuario__acciones detalle-postulante__acciones';
  const download = document.createElement('button');
  download.type = 'button';
  download.className = 'boton primario';
  download.textContent = 'Descargar CV';
  download.onclick = async () => {
    try {
      const blob = await peticion(`cv/${postulacion.id_postulacion}`);
      const url = URL.createObjectURL(blob);
      const link = document.createElement('a');
      link.href = url;
      link.download = postulacion.cv_nombre || 'cv.pdf';
      link.click();
      setTimeout(() => URL.revokeObjectURL(url), 1000);
    } catch (error) {
      mostrar(error.message, true);
    }
  };
  const close = document.createElement('button');
  close.type = 'button';
  close.className = 'boton secundario';
  close.textContent = 'Cerrar';
  close.onclick = () => dialog.close();
  actions.append(download, close);
  article.append(actions);
  dialog.append(article);
  dialog.addEventListener('close', () => dialog.remove(), { once: true });
  document.body.append(dialog);
  dialog.showModal();
}

export async function cargarPostulacionesAdmin(root) {
  let pagina = 1;
  const porPagina = 20;
  const contenido = document.createElement('div');
  const paginador = document.createElement('nav');
  paginador.className = 'paginador';
  paginador.setAttribute('aria-label', 'Paginación de postulaciones');
  const anterior = document.createElement('button');
  anterior.type = 'button';
  anterior.textContent = 'Anterior';
  const actual = document.createElement('span');
  const siguiente = document.createElement('button');
  siguiente.type = 'button';
  siguiente.textContent = 'Siguiente';
  paginador.append(anterior, actual, siguiente);
  root.append(contenido, paginador);
  const cargar = async () => {
    anterior.disabled = true;
    siguiente.disabled = true;
    const respuesta = await peticion(`postulaciones?pagina=${pagina}&por_pagina=${porPagina}`);
    contenido.replaceChildren();
    await renderSolicitudes(contenido, respuesta.datos, false, { buscarEnPagina: true });
    actual.textContent = `Página ${pagina}`;
    anterior.disabled = pagina <= 1;
    siguiente.disabled = respuesta.datos.length < porPagina;
  };
  anterior.onclick = async () => {
    pagina--;
    try {
      await cargar();
    } catch (error) {
      pagina++;
      mostrar(error.message, true);
    }
  };
  siguiente.onclick = async () => {
    pagina++;
    try {
      await cargar();
    } catch (error) {
      pagina--;
      mostrar(error.message, true);
    }
  };
  await cargar();
}
