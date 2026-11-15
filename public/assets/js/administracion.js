import {
  crearElemento,
  mostrar,
  peticion,
  formulario,
  estado,
  $,
  actualizarPantalla,
  boton,
  encabezado,
  tarjeta,
  tabla,
  transiciones,
  etiquetaEstado,
  elegirTransicion,
} from './comun.js?v=cambio-estado-modal-20261111-1';
import { confirmarAccion } from './ui.js?v=validacion-es-20261111-3';

// Funciones de la pantalla: administracion.

const camposCatalogo = {
  areas: ['codigo', 'nombre', 'descripcion'],
  carreras: ['codigo', 'nombre'],
  modalidades: ['codigo', 'nombre'],
  estados: ['tipo_entidad', 'codigo', 'nombre', 'descripcion', 'color', 'orden'],
  mensajes: ['codigo', 'texto', 'tipo'],
  transiciones: [
    'entidad',
    'id_estado_origen',
    'id_estado_destino',
    'id_rol_permitido',
    'requiere_comentario',
  ],
};
export function resumenReportes(root, d) {
  encabezado(root, 'Resumen');
  for (const [tipo, filas] of Object.entries(d)) {
    const grupo = document.createElement('div');
    grupo.className = 'resumen';
    for (const x of filas) {
      const c = document.createElement('div');
      c.className = 'contador';
      if (x.estado_codigo)
        c.classList.add(
          `estado-${tipo === 'practicas' ? 'practica' : 'postulacion'}-${x.estado_codigo}`,
        );
      const n = crearElemento('strong', x.cantidad);
      c.append(n, document.createTextNode(x.estado));
      grupo.append(c);
    }
    root.append(grupo);
  }
}

export function herramientasAdmin(root) {
  encabezado(root, 'Herramientas de coordinación');
  const acciones = document.createElement('div');
  acciones.className = 'acciones';
  boton(
    'Configuración',
    async () => {
      try {
        const r = await peticion('admin/configuracion');
        root.replaceChildren();
        encabezado(root, 'Configuración');
        for (const x of r.datos) {
          const a = tarjeta(root, x.clave, [x.descripcion, x.valor]);
          if (Number(x.editable))
            boton(
              'Editar',
              async () => {
                const valor = prompt(x.clave, x.valor);
                if (valor === null) return;
                try {
                  await peticion(`admin/configuracion/${encodeURIComponent(x.clave)}`, {
                    method: 'PUT',
                    body: JSON.stringify({
                      valor,
                    }),
                  });
                  mostrar('Configuración guardada.');
                  await actualizarPantalla();
                } catch (e) {
                  mostrar(e.message, true);
                }
              },
              a,
            );
        }
      } catch (e) {
        mostrar(e.message, true);
      }
    },
    acciones,
  );
  boton('Catálogos', () => gestionarCatalogos(root), acciones);
  root.append(acciones);
}

export async function gestionarCatalogos(root, catalogosPermitidos = null, controlesCrud = false, alCerrar = null) {
  root.replaceChildren();
  const panel = document.createElement('section');
  panel.className = 'panel panel-crud-catalogo';
  root.append(panel);
  if (controlesCrud) {
    const cabecera = document.createElement('div');
    cabecera.className = 'crud-catalogo__cabecera';
    encabezado(cabecera, `Administrar ${catalogosPermitidos?.[0] || 'catálogo'}`);
    const cerrar = document.createElement('button');
    cerrar.type = 'button';
    cerrar.className = 'boton secundario';
    cerrar.textContent = 'Cerrar';
    cerrar.onclick = () => alCerrar?.();
    cabecera.append(cerrar);
    panel.append(cabecera);
  } else {
    encabezado(panel, 'Mantenimiento de catálogos');
  }
  const f = document.createElement('form');
  f.className = 'formulario';
  const sel = document.createElement('select');
  sel.name = 'catalogo';
  const nombresCatalogo = {
    areas: 'Áreas', carreras: 'Carreras', modalidades: 'Modalidades',
    mensajes: 'Mensajes', estados: 'Estados', transiciones: 'Transiciones de estado',
  };
  const opciones = catalogosPermitidos
    ? catalogosPermitidos.map((codigo) => [codigo, nombresCatalogo[codigo]])
    : estado.usuario?.rol_codigo === 'superadmin'
      ? [['areas', 'Áreas'], ['carreras', 'Carreras'], ['modalidades', 'Modalidades'], ['mensajes', 'Mensajes'], ['estados', 'Estados'], ['transiciones', 'Transiciones de estado']]
      : [['areas', 'Áreas'], ['mensajes', 'Mensajes']];
  for (const [codigo, nombre] of opciones) {
    const o = document.createElement('option');
    o.value = codigo;
    o.textContent = nombre;
    sel.append(o);
  }
  const borrados = document.createElement('input');
  borrados.type = 'checkbox';
  borrados.name = 'eliminados';
  const etiqueta = document.createElement('label');
  etiqueta.append(borrados, document.createTextNode(' Mostrar eliminados'));
  sel.hidden = controlesCrud;
  const nuevo = document.createElement('button');
  nuevo.type = 'button';
  nuevo.className = 'boton primario';
  nuevo.textContent = 'Crear registro';
  f.append(sel, etiqueta, nuevo);
  let verRegistros = null;
  if (controlesCrud) {
    verRegistros = document.createElement('button');
    verRegistros.type = 'button';
    verRegistros.className = 'boton secundario';
    verRegistros.textContent = 'Ver registros';
    f.append(verRegistros);
  }
  panel.append(f);
  const lista = document.createElement('div');
  lista.className = 'tabla-contenedor crud-catalogo__tabla';
  panel.append(lista);
  const refrescar = async () => {
    try {
      const q = borrados.checked ? '?eliminados=1' : '';
      const r = await peticion(`admin/catalogos/${sel.value}${q}`);
      lista.replaceChildren();
      const campos = camposCatalogo[sel.value];
      const tablaCrud = document.createElement('table');
      const thead = document.createElement('thead');
      const filaEncabezado = document.createElement('tr');
      for (const campo of [...campos, 'acciones']) {
        const celda = crearElemento('th', campo === 'acciones' ? 'Acciones' : campo.replaceAll('_', ' '));
        filaEncabezado.append(celda);
      }
      thead.append(filaEncabezado);
      tablaCrud.append(thead);
      const tbody = document.createElement('tbody');
      if (!r.datos.length) {
        const filaVacia = document.createElement('tr');
        const celdaVacia = crearElemento('td', 'No hay registros para mostrar.');
        celdaVacia.colSpan = campos.length + 1;
        filaVacia.append(celdaVacia);
        tbody.append(filaVacia);
      }
      for (const item of r.datos) {
        const pk = {
          areas: 'id_area',
          carreras: 'id_carrera',
          modalidades: 'id_modalidad',
          mensajes: 'id_mensaje',
          estados: 'id_estado',
          transiciones: 'id_transicion',
        }[sel.value];
        const id = item[pk];
        const fila = document.createElement('tr');
        for (const campo of campos) {
          const celda = crearElemento('td', item[campo] ?? '');
          fila.append(celda);
        }
        const acciones = document.createElement('td');
        acciones.className = 'crud-catalogo__acciones';
        if (borrados.checked) {
          boton(
            'Restaurar',
            async () => {
              try {
                await peticion(`admin/catalogos/${sel.value}/${id}/restaurar`, {
                  method: 'POST',
                  body: '{}',
                });
                await refrescar();
              } catch (e) {
                mostrar(e.message, true);
              }
            },
            acciones,
          );
        } else {
          boton(
            'Editar',
            async () => {
              const datos = {};
              for (const campo of campos) {
                const valor = prompt(`Valor de ${campo}:`, item[campo] ?? '');
                if (valor === null) return;
                datos[campo] =
                  campo === 'requiere_comentario'
                    ? /^(1|true|si)$/i.test(valor)
                    : [
                          'orden',
                          'id_estado_origen',
                          'id_estado_destino',
                          'id_rol_permitido',
                        ].includes(campo)
                      ? Number(valor)
                      : valor;
              }
              try {
                await peticion(`admin/catalogos/${sel.value}/${id}`, {
                  method: 'PUT',
                  body: JSON.stringify(datos),
                });
                await refrescar();
              } catch (e) {
                mostrar(e.message, true);
              }
            },
            acciones,
          );
          boton(
            'Eliminar',
            async () => {
              const confirmacion = await confirmarAccion(
                'El registro se ocultará del catálogo activo.',
                { titulo: 'Eliminar registro', textoConfirmar: 'Eliminar' },
              );
              if (!confirmacion.confirmado) return;
              try {
                await peticion(`admin/catalogos/${sel.value}/${id}/eliminar`, {
                  method: 'DELETE',
                });
                await refrescar();
              } catch (e) {
                mostrar(e.message, true);
              }
            },
            acciones,
          );
        }
        fila.append(acciones);
        tbody.append(fila);
      }
      tablaCrud.append(tbody);
      lista.append(tablaCrud);
    } catch (e) {
      mostrar(e.message, true);
    }
  };
  sel.onchange = controlesCrud ? null : refrescar;
  borrados.onchange = refrescar;
  if (verRegistros) verRegistros.onclick = refrescar;
  nuevo.onclick = async () => {
    const datos = {};
    for (const campo of camposCatalogo[sel.value]) {
      const valor = prompt(`Valor de ${campo}:`);
      if (valor === null) return;
      datos[campo] =
        campo === 'requiere_comentario'
          ? /^(1|true|si)$/i.test(valor)
          : ['orden', 'id_estado_origen', 'id_estado_destino', 'id_rol_permitido'].includes(campo)
            ? Number(valor)
            : valor;
    }
    try {
      await peticion(`admin/catalogos/${sel.value}`, {
        method: 'POST',
        body: JSON.stringify(datos),
      });
      await refrescar();
    } catch (e) {
      mostrar(e.message, true);
    }
  };
  await refrescar();
}

export function gestionarCatalogosSuperadmin(root) {
  const opciones = [
    ['carreras', 'Carreras'],
    ['estados', 'Estados'],
    ['modalidades', 'Modalidades'],
  ];
  const porPagina = 4;
  const mostrarOpciones = () => {
    root.replaceChildren();
    const panel = document.createElement('section');
    panel.className = 'panel panel-opciones-catalogo';
    encabezado(panel, 'Catálogos del sistema');
    panel.append(crearElemento('p', 'Selecciona una tabla para abrir su CRUD.'));
    const botones = document.createElement('div');
    botones.className = 'opciones-catalogo';
    for (const [codigo, nombre] of opciones) {
      const botonCatalogo = document.createElement('button');
      botonCatalogo.type = 'button';
      botonCatalogo.className = 'boton catalogo-opcion';
      botonCatalogo.textContent = nombre;
      botonCatalogo.onclick = () => abrirCatalogo(codigo, nombre);
      botones.append(botonCatalogo);
    }
    panel.append(botones);
    root.append(panel);
  };

  const abrirCatalogo = async (codigo, nombre) => {
    root.replaceChildren();
    let registros = [];
    let pagina = 1;
    let editandoId = null;
    const campos = camposCatalogo[codigo];
    const clavePrimaria = { carreras: 'id_carrera', estados: 'id_estado', modalidades: 'id_modalidad' }[codigo];

    const panel = document.createElement('section');
    panel.className = 'panel panel-crud-catalogo';
    const cabecera = document.createElement('div');
    cabecera.className = 'crud-catalogo__cabecera';
    encabezado(cabecera, `Administrar ${nombre.toLowerCase()}`);
    const cerrar = document.createElement('button');
    cerrar.type = 'button';
    cerrar.className = 'boton secundario';
    cerrar.textContent = 'Cerrar';
    cerrar.onclick = mostrarOpciones;
    cabecera.append(cerrar);
    panel.append(cabecera);

    const form = document.createElement('form');
    form.noValidate = true;
    form.className = 'formulario crud-catalogo__formulario';
    const camposForm = document.createElement('div');
    camposForm.className = 'crud-catalogo__campos';
    for (const campo of campos) {
      const etiqueta = document.createElement('label');
      etiqueta.append(document.createTextNode(campo.replaceAll('_', ' ')));
      const input = document.createElement('input');
      input.name = campo;
      input.type = campo === 'orden' ? 'number' : 'text';
      input.required = true;
      if (campo === 'orden') input.min = '1';
      if (campo === 'codigo') input.maxLength = 100;
      if (campo === 'nombre') input.maxLength = 180;
      if (campo === 'tipo_entidad') input.maxLength = 40;
      if (campo === 'color') input.maxLength = 16;
      if (campo === 'descripcion') input.maxLength = 5000;
      etiqueta.append(input);
      camposForm.append(etiqueta);
    }
    const accionesForm = document.createElement('div');
    accionesForm.className = 'crud-catalogo__form-acciones';
    const errorForm = crearElemento('p', '', 'crud-catalogo__mensaje');
    errorForm.setAttribute('role', 'status');
    const enviar = document.createElement('button');
    enviar.type = 'submit';
    enviar.className = 'boton primario';
    enviar.textContent = 'Enviar';
    accionesForm.append(enviar);
    const cancelar = document.createElement('button');
    cancelar.type = 'button';
    cancelar.className = 'boton secundario';
    cancelar.textContent = 'Cancelar edición';
    cancelar.hidden = true;
    cancelar.onclick = () => limpiarFormulario();
    accionesForm.append(cancelar);
    form.append(camposForm, errorForm, accionesForm);
    panel.append(form);

    const tablaWrap = document.createElement('div');
    tablaWrap.className = 'tabla-contenedor crud-catalogo__tabla';
    panel.append(tablaWrap);
    const paginador = document.createElement('nav');
    paginador.className = 'paginador crud-catalogo__paginador';
    paginador.setAttribute('aria-label', `Paginación de ${nombre.toLowerCase()}`);
    const anterior = document.createElement('button');
    anterior.type = 'button';
    anterior.className = 'boton secundario';
    anterior.textContent = 'Anterior';
    const paginaTexto = document.createElement('span');
    const siguiente = document.createElement('button');
    siguiente.type = 'button';
    siguiente.className = 'boton secundario';
    siguiente.textContent = 'Siguiente';
    paginador.append(anterior, paginaTexto, siguiente);
    panel.append(paginador);
    root.append(panel);

    function limpiarFormulario() {
      form.reset();
      editandoId = null;
      enviar.textContent = 'Enviar';
      cancelar.hidden = true;
      errorForm.textContent = '';
      errorForm.classList.remove('is-error', 'is-success');
    }

    const renderizarTabla = () => {
      tablaWrap.replaceChildren();
      const tablaCrud = document.createElement('table');
      const thead = document.createElement('thead');
      const filaEncabezado = document.createElement('tr');
      for (const campo of [...campos, 'acciones'])
        filaEncabezado.append(crearElemento('th', campo === 'acciones' ? 'Acciones' : campo.replaceAll('_', ' ')));
      thead.append(filaEncabezado);
      tablaCrud.append(thead);

      const tbody = document.createElement('tbody');
      const totalPaginas = Math.max(1, Math.ceil(registros.length / porPagina));
      pagina = Math.min(pagina, totalPaginas);
      const inicio = (pagina - 1) * porPagina;
      for (const registro of registros.slice(inicio, inicio + porPagina)) {
        const fila = document.createElement('tr');
        for (const campo of campos) fila.append(crearElemento('td', registro[campo] ?? ''));
        const celdaAcciones = document.createElement('td');
        celdaAcciones.className = 'crud-catalogo__acciones';
        boton('Editar', () => {
          editandoId = Number(registro[clavePrimaria]);
          for (const campo of campos) form.elements.namedItem(campo).value = registro[campo] ?? '';
          enviar.textContent = 'Guardar cambios';
          cancelar.hidden = false;
          form.scrollIntoView({ behavior: 'smooth', block: 'center' });
          form.elements.namedItem(campos[0]).focus();
        }, celdaAcciones);
        const eliminar = document.createElement('button');
        eliminar.type = 'button';
        eliminar.className = 'boton secundario';
        eliminar.textContent = 'Eliminar';
        eliminar.onclick = async () => {
          eliminar.disabled = true;
          try {
            await peticion(`admin/catalogos/${codigo}/${registro[clavePrimaria]}/eliminar`, { method: 'DELETE' });
            if (editandoId === Number(registro[clavePrimaria])) limpiarFormulario();
            await cargarRegistros();
            mostrar(`${nombre}: registro eliminado.`);
          } catch (error) {
            eliminar.disabled = false;
            mostrar(error.message, true);
          }
        };
        celdaAcciones.append(eliminar);
        fila.append(celdaAcciones);
        tbody.append(fila);
      }
      if (!registros.length) {
        const filaVacia = document.createElement('tr');
        const celdaVacia = crearElemento('td', `No hay registros de ${nombre.toLowerCase()}.`);
        celdaVacia.colSpan = campos.length + 1;
        filaVacia.append(celdaVacia);
        tbody.append(filaVacia);
      }
      tablaCrud.append(tbody);
      tablaWrap.append(tablaCrud);
      paginaTexto.textContent = `Página ${pagina} de ${totalPaginas} · ${registros.length} registros`;
      anterior.disabled = pagina <= 1;
      siguiente.disabled = pagina >= totalPaginas;
    };

    const cargarRegistros = async () => {
      try {
        const respuesta = await peticion(`admin/catalogos/${codigo}`);
        registros = respuesta.datos;
        renderizarTabla();
        return true;
      } catch (error) {
        errorForm.textContent = error.message;
        errorForm.classList.add('is-error');
        return false;
      }
    };

    anterior.onclick = () => { pagina = Math.max(1, pagina - 1); renderizarTabla(); };
    siguiente.onclick = () => { pagina += 1; renderizarTabla(); };
    form.onsubmit = async (evento) => {
      evento.preventDefault();
      if (!form.checkValidity()) {
        errorForm.textContent = 'Completa los campos obligatorios antes de enviar.';
        errorForm.classList.add('is-error');
        return;
      }
      errorForm.textContent = '';
      errorForm.classList.remove('is-error', 'is-success');
      const estabaEditando = Boolean(editandoId);
      const datos = Object.fromEntries(new FormData(form).entries());
      if (Object.hasOwn(datos, 'orden')) datos.orden = Number(datos.orden);
      enviar.disabled = true;
      try {
        await peticion(
          editandoId ? `admin/catalogos/${codigo}/${editandoId}` : `admin/catalogos/${codigo}`,
          { method: editandoId ? 'PUT' : 'POST', body: JSON.stringify(datos) },
        );
        limpiarFormulario();
        pagina = estabaEditando ? pagina : 1;
        const cargados = await cargarRegistros();
        if (!cargados) return;
        errorForm.textContent = estabaEditando
          ? 'Cambios guardados correctamente.'
          : 'Registro creado y visible en la tabla.';
        errorForm.classList.add('is-success');
      } catch (error) {
        errorForm.textContent = error.message;
        errorForm.classList.add('is-error');
      } finally {
        enviar.disabled = false;
      }
    };
    await cargarRegistros();
  };

  mostrarOpciones();
}

export async function gestionarBitacora(root) {
  root.replaceChildren();
  encabezado(root, 'Consulta de bitácora');
  let pagina = 1;
  const filtros = document.createElement('form');
  filtros.className = 'filtros';
  const defs = [
    ['usuario', 'Usuario'],
  ];
  for (const [name, label] of defs) {
    const l = crearElemento('label', label);
    const i = document.createElement('input');
    i.name = name;
    i.placeholder = 'Nombre del usuario';
    i.type = 'search';
    l.append(i);
    filtros.append(l);
  }
  const buscar = crearElemento('button', 'Filtrar', 'boton secundario');
  filtros.append(buscar);
  root.append(filtros);
  const resultado = document.createElement('div');
  root.append(resultado);
  const controles = document.createElement('div');
  controles.className = 'paginador';
  const anterior = document.createElement('button');
  anterior.type = 'button';
  anterior.className = 'boton secundario';
  anterior.textContent = 'Anterior';
  const estadoPagina = document.createElement('span');
  const siguiente = document.createElement('button');
  siguiente.type = 'button';
  siguiente.className = 'boton secundario';
  siguiente.textContent = 'Siguiente';
  controles.append(anterior, estadoPagina, siguiente);
  root.append(controles);
  const cargar = async () => {
    const q = new URLSearchParams({
      pagina: String(pagina),
    });
    for (const [k, v] of Object.entries(formulario(filtros))) if (v) q.set(k, v);
    try {
      const r = await peticion(`admin/bitacora?${q}`);
      resultado.replaceChildren();
      tabla(resultado, 'Eventos', r.datos.elementos, [
        'fecha_hora',
        'tabla_afectada',
        'id_registro',
        'accion',
        'usuario',
        'ip_cliente',
      ]);
      estadoPagina.textContent = `Página ${pagina}`;
      anterior.disabled = pagina <= 1;
      siguiente.disabled = r.datos.elementos.length < Number(r.datos.por_pagina);
    } catch (e) {
      mostrar(e.message, true);
    }
  };
  filtros.onsubmit = async (e) => {
    e.preventDefault();
    pagina = 1;
    await cargar();
  };
  anterior.onclick = async () => {
    pagina = Math.max(1, pagina - 1);
    await cargar();
  };
  siguiente.onclick = async () => {
    pagina++;
    await cargar();
  };
  await cargar();
}

// Funciones de la pantalla: revision.

export async function cargarPracticasAdmin(root, rol) {
  encabezado(root, 'Prácticas por revisar');
  const controls = document.createElement('form');
  controls.className = 'filtros';
  const label = crearElemento('label', 'Estado');
  const select = document.createElement('select');
  select.name = 'estado';
  const all = document.createElement('option');
  all.value = '';
  all.textContent = 'Todos los estados';
  select.append(all);
  for (const item of estado.catalogos.filter((item) => item.tipo === 'practica')) {
    const option = document.createElement('option');
    option.value = item.codigo;
    option.textContent = item.nombre;
    option.selected = item.codigo === 'pendiente_revision';
    select.append(option);
  }
  label.append(select);
  controls.append(label);
  const submit = document.createElement('button');
  submit.type = 'submit';
  submit.textContent = 'Filtrar';
  controls.append(submit);
  root.append(controls);
  const list = document.createElement('div');
  list.className = 'lista-practicas';
  root.append(list);
  const pager = document.createElement('nav');
  pager.className = 'paginador';
  pager.setAttribute('aria-label', 'Paginación de prácticas');
  const previous = document.createElement('button');
  previous.type = 'button';
  previous.textContent = 'Anterior';
  previous.disabled = true;
  const pageLabel = document.createElement('span');
  const next = document.createElement('button');
  next.type = 'button';
  next.textContent = 'Siguiente';
  next.disabled = true;
  pager.append(previous, pageLabel, next);
  root.append(pager);
  let page = 1;
  const load = async () => {
    const query = new URLSearchParams({ pagina: String(page), por_pagina: '20' });
    if (select.value) query.set('estado', select.value);
    try {
      const response = await peticion(`practicas/admin?${query}`);
      list.replaceChildren();
      await renderPracticasGestion(list, response.datos.elementos, rol);
      pageLabel.textContent = `Página ${page}`;
      previous.disabled = page <= 1;
      next.disabled = response.datos.elementos.length < Number(response.datos.por_pagina || 20);
    } catch (error) {
      mostrar(error.message, true);
    }
  };
  controls.onsubmit = async (event) => {
    event.preventDefault();
    page = 1;
    await load();
  };
  previous.onclick = async () => {
    page = Math.max(1, page - 1);
    await load();
  };
  next.onclick = async () => {
    page++;
    await load();
  };
  await load();
}

export async function renderPracticasGestion(root, filas, rol) {
  encabezado(root, 'Prácticas');
  if (!filas.length) {
    const vacio = crearElemento('p', 'No hay prácticas para este filtro.');
    root.append(vacio);
    return;
  }
  for (const x of filas) {
    const a = document.createElement('article');
    a.className = 'tarjeta tarjeta-practica';
    const identidad = document.createElement('div');
    identidad.className = 'tarjeta-practica__identidad';
    identidad.append(crearElemento('h3', x.titulo));
    const area = crearElemento('span', x.area, 'etiqueta');
    identidad.append(area);
    const status = document.createElement('div');
    status.className = 'tarjeta-practica__estado';
    etiquetaEstado(status, x.estado, 'practica', x.estado_codigo);
    const encabezadoPractica = document.createElement('div');
    encabezadoPractica.className = 'tarjeta-practica__encabezado';
    encabezadoPractica.append(identidad, status);

    const descripcion = crearElemento(
      'p',
      x.descripcion || 'Sin descripción.',
      'tarjeta-practica__descripcion',
    );
    const datos = document.createElement('dl');
    datos.className = 'tarjeta-practica__datos';
    const detalles = [
      ['Empresa', x.empresa],
      ['Modalidad', x.modalidad],
      ['Ubicación', x.ubicacion || 'Por definir'],
      ['Inicio de la práctica', x.fecha_inicio || 'Por definir'],
      ['Último día para postularse', x.fecha_limite_postulacion || 'Por definir'],
      ['Cupos aceptados', `${x.aceptadas}/${x.cupo_maximo}`],
      ['Solicitudes recibidas', x.total_postulaciones],
    ];
    detalles.forEach(([titulo, valor]) => {
      const dato = document.createElement('div');
      dato.className = 'dato-practica';
      dato.append(
        crearElemento('dt', titulo),
        crearElemento('dd', String(valor ?? 'Por definir')),
      );
      datos.append(dato);
    });
    a.append(encabezadoPractica, descripcion, datos);
    if (x.requisitos) {
      const requisitos = crearElemento(
        'p',
        `Requisitos: ${x.requisitos}`,
        'tarjeta-practica__descripcion',
      );
      a.append(requisitos);
    }
    const acciones = document.createElement('div');
    acciones.className = 'tarjeta-practica__acciones';
    a.append(acciones);
    root.append(a);
    try {
      const opciones = await transiciones('practica', x.estado_codigo);
      if (opciones.some((t) => t.estado_codigo === 'cerrada'))
        boton(
          'Cerrar práctica',
          async () => {
            try {
              await peticion(`practicas/${x.id_practica}/cerrar`, {
                method: 'POST',
                body: '{}',
              });
              await actualizarPantalla();
            } catch (e) {
              mostrar(e.message, true);
            }
          },
          acciones,
        );
      if (['admin', 'superadmin'].includes(rol) && opciones.length)
        boton(
          'Cambiar estado',
          async () => {
            const sel = await elegirTransicion('practica', x.estado_codigo);
            if (!sel) return;
            try {
              await peticion(`practicas/${x.id_practica}/revision`, {
                method: 'PUT',
                body: JSON.stringify({
                  estado_codigo: sel.destino.estado_codigo,
                  comentario: sel.comentario,
                }),
              });
              await actualizarPantalla();
            } catch (e) {
              mostrar(e.message, true);
            }
          },
          acciones,
        );
    } catch (error) {
      mostrar(error.message, true);
    }
  }
}

export async function renderEmpresasPendientes(root, filas) {
  const panel = document.createElement('section');
  panel.className = 'panel panel-empresas-revision';
  encabezado(panel, 'Aceptar empresas');
  panel.append(
    crearElemento(
      'p',
      'Revisa las solicitudes pendientes. Al aceptar una empresa, también se activa su cuenta de acceso.',
      'ayuda',
    ),
  );
  root.append(panel);
  if (!filas.length) {
    const vacio = crearElemento('p', 'No hay empresas pendientes de revisión.');
    panel.append(vacio);
    return;
  }
  for (const x of filas) {
    const a = tarjeta(panel, x.nombre_comercial || x.nombre_legal, [
      x.correo_contacto,
      `${x.nombres || ''} ${x.apellidos || ''} · ${x.telefono || 'Sin teléfono'}`,
      `Sector: ${x.sector || 'No indicado'}`,
      `Identificación fiscal: ${x.identificacion_fiscal || 'No indicada'}`,
      `Descripción: ${x.descripcion || 'No proporcionada'}`,
      `Sitio web: ${x.sitio_web || 'No indicado'}`,
    ]);
    etiquetaEstado(a, x.estado, 'empresa', x.estado_codigo);
    boton(
      'Aceptar empresa',
      async () => {
        try {
          await peticion(`admin/empresas/${x.id_empresa}/revision`, {
            method: 'PUT',
            body: JSON.stringify({ estado_codigo: 'aprobada', comentario: '' }),
          });
          mostrar('Empresa aprobada y cuenta activada.');
          await actualizarPantalla();
        } catch (e) {
          mostrar(e.message, true);
        }
      },
      a,
    );
    boton(
      'Rechazar solicitud',
      async () => {
        const sel = await elegirTransicion('empresa', x.estado_codigo);
        if (!sel || sel.destino.estado_codigo !== 'rechazada') return;
        try {
          await peticion(`admin/empresas/${x.id_empresa}/revision`, {
            method: 'PUT',
            body: JSON.stringify({
              estado_codigo: sel.destino.estado_codigo,
              comentario: sel.comentario,
            }),
          });
          await actualizarPantalla();
        } catch (e) {
          mostrar(e.message, true);
        }
      },
      a,
    );
  }
}
