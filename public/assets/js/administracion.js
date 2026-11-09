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
} from './comun.js';
import { confirmarAccion } from './ui.js';

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
  boton('Bitácora', () => gestionarBitacora(root), acciones);
  root.append(acciones);
}

export async function gestionarCatalogos(root) {
  root.replaceChildren();
  encabezado(root, 'Mantenimiento de catálogos');
  const f = document.createElement('form');
  f.className = 'formulario';
  const sel = document.createElement('select');
  sel.name = 'catalogo';
  const opciones = [
    ['areas', 'Áreas'],
    ['carreras', 'Carreras'],
    ['modalidades', 'Modalidades'],
    ['mensajes', 'Mensajes'],
  ];
  if (estado.usuario?.rol_codigo === 'superadmin')
    opciones.push(['estados', 'Estados'], ['transiciones', 'Transiciones de estado']);
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
  const nuevo = document.createElement('button');
  nuevo.type = 'button';
  nuevo.className = 'boton primario';
  nuevo.textContent = 'Crear registro';
  f.append(sel, etiqueta, nuevo);
  root.append(f);
  const lista = document.createElement('div');
  lista.className = 'tarjetas';
  root.append(lista);
  const refrescar = async () => {
    try {
      const q = borrados.checked ? '?eliminados=1' : '';
      const r = await peticion(`admin/catalogos/${sel.value}${q}`);
      lista.replaceChildren();
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
        const campos = camposCatalogo[sel.value];
        const resumen = campos.map((c) => `${c}: ${item[c] ?? ''}`);
        const a = tarjeta(lista, `${sel.value} #${id}`, resumen);
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
            a,
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
            a,
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
            a,
          );
        }
      }
    } catch (e) {
      mostrar(e.message, true);
    }
  };
  sel.onchange = refrescar;
  borrados.onchange = refrescar;
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

export async function gestionarBitacora(root) {
  root.replaceChildren();
  encabezado(root, 'Consulta de bitácora');
  let pagina = 1;
  const filtros = document.createElement('form');
  filtros.className = 'filtros';
  const defs = [
    ['tabla', 'Tabla'],
    ['accion', 'Acción'],
    ['usuario_id', 'ID de usuario'],
    ['desde', 'Desde'],
    ['hasta', 'Hasta'],
  ];
  for (const [name, label] of defs) {
    const l = crearElemento('label', label);
    const i = document.createElement('input');
    i.name = name;
    if (name === 'desde' || name === 'hasta') i.type = 'date';
    if (name === 'usuario_id') i.type = 'number';
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
  list.className = 'tarjetas';
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
    const a = tarjeta(root, x.titulo, [
      x.empresa,
      `${x.area} · ${x.modalidad} · ${x.ubicacion || 'Ubicación por definir'}`,
      `Fecha de inicio: ${x.fecha_inicio || 'Por definir'} · Fecha límite: ${x.fecha_limite_postulacion}`,
      `Cupo: ${x.aceptadas}/${x.cupo_maximo} · Solicitudes: ${x.total_postulaciones}`,
      `Descripción: ${x.descripcion}`,
      `Requisitos: ${x.requisitos || 'No especificados.'}`,
    ]);
    etiquetaEstado(a, x.estado, 'practica', x.estado_codigo);
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
          a,
        );
      if (rol === 'superadmin' && opciones.length)
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
          a,
        );
    } catch (error) {
      mostrar(error.message, true);
    }
  }
}

export async function renderEmpresasPendientes(root, filas) {
  encabezado(root, 'Empresas por revisar');
  if (!filas.length) {
    const vacio = crearElemento('p', 'No hay empresas pendientes de revisión.');
    root.append(vacio);
    return;
  }
  for (const x of filas) {
    const a = tarjeta(root, x.nombre_comercial || x.nombre_legal, [
      x.correo_contacto,
      `${x.nombres || ''} ${x.apellidos || ''} · ${x.telefono || 'Sin teléfono'}`,
      `Sector: ${x.sector || 'No indicado'}`,
      `Identificación fiscal: ${x.identificacion_fiscal || 'No indicada'}`,
      `Descripción: ${x.descripcion || 'No proporcionada'}`,
      `Sitio web: ${x.sitio_web || 'No indicado'}`,
    ]);
    etiquetaEstado(a, x.estado, 'empresa', x.estado_codigo);
    boton(
      'Revisar',
      async () => {
        const sel = await elegirTransicion('empresa', x.estado_codigo);
        if (!sel) return;
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
