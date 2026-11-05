import {
  crearElemento,
  mostrar,
  peticion,
  estado,
  $,
  actualizarPantalla,
  boton,
  encabezado,
  tarjeta,
  etiquetaEstado,
  transiciones,
  pedirComentario,
} from './comun.js';
import { confirmarAccion } from './ui.js';

// Funciones de la pantalla: postulaciones.

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
  estado.catalogos
    .filter((item) => item.tipo === 'postulacion')
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
  lista.className = 'tarjetas';
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
    for (const x of seleccionadas) {
      if (!estudiante && Number(x.id_practica) !== practicaActual) {
        practicaActual = Number(x.id_practica);
        const grupo = crearElemento('h3', x.practica || 'Práctica');
        lista.append(grupo);
      }
      const detalle = estudiante
        ? [
            `Empresa: ${x.empresa || 'No indicada'}`,
            `Fecha: ${x.fecha_postulacion}`,
            x.carta_presentacion ? `Carta de presentación: ${x.carta_presentacion}` : '',
            x.comentario ? `Observación: ${x.comentario}` : '',
          ]
        : [
            `${x.empresa || ''}`,
            `${x.nombres || ''} ${x.apellidos || ''} · ${x.carrera || ''}`,
            `Correo: ${x.correo || ''} · Teléfono: ${x.telefono || ''}`,
            `Fecha: ${x.fecha_postulacion}`,
            x.comentario || '',
          ];
      const item = tarjeta(lista, x.practica || 'Postulación', detalle.filter(Boolean));
      etiquetaEstado(item, x.estado, 'postulacion', x.estado_codigo);
      if (!estudiante) boton('Ver perfil del postulante', () => verPerfilPostulante(x), item);
      if (estudiante)
        boton(
          'Ver historial',
          async (evento) => {
            const trigger = evento.currentTarget;
            const anterior = item.querySelector('.linea-tiempo');
            if (anterior) {
              anterior.remove();
              trigger.textContent = 'Ver historial';
              return;
            }
            try {
              const respuesta = await peticion(`postulaciones/${x.id_postulacion}/historial`);
              const timeline = document.createElement('section');
              timeline.className = 'linea-tiempo';
              const title = crearElemento('h5', 'Historial de seguimiento');
              timeline.append(title);
              if (!respuesta.datos.length) {
                const vacio = crearElemento('p', 'Aún no hay cambios registrados.');
                timeline.append(vacio);
              } else {
                const historial = document.createElement('ol');
                respuesta.datos.forEach((cambio) => {
                  const entrada = document.createElement('li');
                  const transicion = crearElemento(
                    'strong',
                    `${cambio.estado_anterior || 'Registro inicial'} → ${cambio.estado_nuevo}`,
                  );
                  const fecha = document.createElement('time');
                  fecha.dateTime = cambio.fecha_hora;
                  fecha.textContent = cambio.fecha_hora;
                  entrada.append(transicion, fecha);
                  if (cambio.actor) {
                    const actor = crearElemento('span', `Responsable: ${cambio.actor}`);
                    entrada.append(actor);
                  }
                  if (cambio.comentario) {
                    const comentario = crearElemento('p', cambio.comentario);
                    entrada.append(comentario);
                  }
                  historial.append(entrada);
                });
                timeline.append(historial);
              }
              item.append(timeline);
              trigger.textContent = 'Ocultar historial';
            } catch (error) {
              mostrar(error.message, true);
            }
          },
          item,
        );

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
            item,
          );
        else {
          const aviso = crearElemento('p', 'Esta práctica ya no recibe postulaciones.', 'aviso');
          item.append(aviso);
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
              item,
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
                item,
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
  dialog.className = 'dialogo';
  const article = document.createElement('article');
  article.className = 'detalle-practica';
  const title = crearElemento(
    'h2',
    `${postulacion.nombres || ''} ${postulacion.apellidos || ''}`.trim() || 'Perfil del postulante',
  );
  article.append(title);
  const details = [
    ['Práctica', postulacion.practica],
    ['Carrera', postulacion.carrera],
    ['Carné', postulacion.carnet],
    ['Universidad', postulacion.universidad],
    ['Semestre', postulacion.semestre],
    ['Correo', postulacion.correo],
    ['Teléfono', postulacion.telefono],
    ['Fecha de postulación', postulacion.fecha_postulacion],
    ['Estado', postulacion.estado],
    ['Comentario', postulacion.comentario || 'Sin comentario'],
  ];
  for (const [label, value] of details) {
    const row = document.createElement('p');
    const strong = crearElemento('strong', `${label}: `);
    row.append(strong, document.createTextNode(value || 'No indicado'));
    article.append(row);
  }
  const actions = document.createElement('div');
  actions.className = 'acciones';
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
