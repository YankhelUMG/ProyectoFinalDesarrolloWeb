import { validarArchivoCv, confirmarAccion, durantePeticion, pintarErroresCampos } from './ui.js';
import {
  crearElemento,
  mostrar,
  peticion,
  formulario,
  limpiarErroresFormulario,
  estado,
  $,
  actualizarPantalla,
  boton,
  encabezado,
  tarjeta,
  etiquetaEstado,
  elegirTransicion,
} from './comun.js';

// Funciones de la pantalla: usuarios.

export function formularioUsuario(root, rolActor) {
  encabezado(root, 'Crear cuenta gestionada');
  const f = document.createElement('form');
  f.className = 'formulario columnas panel';
  f.enctype = 'multipart/form-data';
  const roles = estado.catalogos.filter(
    (x) =>
      x.tipo === 'rol' &&
      x.codigo !== 'sistema' &&
      (rolActor === 'superadmin' || x.codigo !== 'superadmin'),
  );
  const l = crearElemento('label', 'Perfil');
  const select = document.createElement('select');
  select.name = 'rol_codigo';
  select.required = true;
  for (const x of roles) {
    const o = document.createElement('option');
    o.value = x.codigo;
    o.textContent = x.nombre;
    select.append(o);
  }
  l.append(select);
  f.append(l);
  const defs = [
    ['usuario', 'Usuario', 'text'],
    ['correo', 'Correo de acceso', 'email'],
    ['contrasena', 'Contraseña temporal', 'password'],
    ['nombres', 'Nombres', 'text'],
    ['apellidos', 'Apellidos', 'text'],
    ['telefono', 'Teléfono', 'tel'],
    ['carnet', 'Carné', 'text'],
    ['id_carrera', 'Carrera', 'catalogo'],
    ['universidad', 'Universidad', 'text'],
    ['semestre', 'Semestre', 'text'],
    ['nombre_legal', 'Nombre legal', 'text'],
    ['nombre_comercial', 'Nombre comercial', 'text'],
    ['correo_contacto', 'Correo de contacto', 'email'],
    ['identificacion_fiscal', 'Identificación fiscal', 'text'],
    ['sector', 'Sector', 'text'],
    ['sitio_web', 'Sitio web', 'url'],
    ['direccion', 'Dirección', 'text'],
    ['descripcion', 'Descripción', 'textarea'],
  ];
  const maximos = {
    usuario: 40,
    correo: 254,
    contrasena: 200,
    nombres: 100,
    apellidos: 100,
    telefono: 8,
    carnet: 40,
    universidad: 180,
    semestre: 40,
    nombre_legal: 180,
    nombre_comercial: 180,
    correo_contacto: 254,
    identificacion_fiscal: 50,
    sector: 100,
    sitio_web: 255,
    direccion: 255,
    descripcion: 5000,
  };
  for (const [name, label, type] of defs) {
    const field = document.createElement('label');
    field.dataset.campo = name;
    field.textContent = label;
    let input;
    if (type === 'textarea') input = document.createElement('textarea');
    else if (type === 'catalogo') {
      input = document.createElement('select');
      const placeholder = document.createElement('option');
      placeholder.value = '';
      placeholder.textContent = 'Selecciona una carrera';
      input.append(placeholder);
      for (const c of estado.catalogos.filter((c) => c.tipo === 'carrera')) {
        const o = document.createElement('option');
        o.value = c.id;
        o.textContent = c.nombre;
        input.append(o);
      }
    } else {
      input = document.createElement('input');
      input.type = type;
    }
    input.name = name;
    if (name === 'contrasena') input.minLength = estado.passwordMinima;
    if (maximos[name]) input.maxLength = maximos[name];
    if (['usuario', 'correo', 'contrasena', 'nombres', 'apellidos'].includes(name))
      input.required = true;
    if (name === 'telefono') {
      input.maxLength = 8;
      input.inputMode = 'numeric';
      input.pattern = '[0-9]{8}';
    }
    if (name === 'id_carrera') input.required = true;
    field.append(input);
    f.append(field);
  }
  const cv = document.createElement('label');
  cv.dataset.campo = 'cv';
  cv.textContent = 'CV PDF';
  const file = document.createElement('input');
  file.type = 'file';
  file.name = 'cv';
  file.accept = 'application/pdf,.pdf';
  if (estado.cvMaximo > 0) file.dataset.tamanoMaximo = String(estado.cvMaximo);
  cv.append(file);
  f.append(cv);
  const btn = crearElemento('button', 'Crear cuenta', 'boton primario');
  f.append(btn);
  const ajustar = () => {
    const codigo = select.value;
    for (const campo of ['carnet', 'id_carrera', 'universidad', 'semestre']) {
      const etiqueta = f.querySelector(`[data-campo="${campo}"]`);
      etiqueta.hidden = codigo !== 'estudiante';
      etiqueta.querySelector('input,select').required =
        codigo === 'estudiante' && campo !== 'semestre';
    }
    for (const campo of [
      'nombre_legal',
      'nombre_comercial',
      'correo_contacto',
      'identificacion_fiscal',
      'sector',
      'sitio_web',
      'direccion',
      'descripcion',
    ]) {
      const etiqueta = f.querySelector(`[data-campo="${campo}"]`);
      etiqueta.hidden = codigo !== 'empresa';
      etiqueta.querySelector('input,textarea').required =
        codigo === 'empresa' && ['nombre_legal', 'correo_contacto'].includes(campo);
    }
    f.elements.namedItem('telefono').required = ['estudiante', 'empresa'].includes(codigo);
    cv.hidden = codigo !== 'estudiante';
    file.required = codigo === 'estudiante';
  };
  select.onchange = ajustar;
  ajustar();
  f.onsubmit = async (e) => {
    e.preventDefault();
    if (!f.reportValidity()) return;
    if (select.value === 'estudiante') {
      if (!validarArchivoCv(f, file, estado.cvMaximo)) return;
    }
    limpiarErroresFormulario(f);
    try {
      await durantePeticion(
        btn,
        async () => {
          const r = await peticion('admin/usuarios', { method: 'POST', body: new FormData(f) });
          mostrar(r.mensaje);
          f.reset();
          ajustar();
          await actualizarPantalla();
        },
        'Creando cuenta…',
      );
    } catch (err) {
      if (Object.keys(err.errores || {}).length) pintarErroresCampos(f, err.errores);
      mostrar(err.message, true);
    }
  };
  root.append(f);
}

export async function verDetalleUsuario(id) {
  const respuesta = await peticion(`admin/usuarios/${id}`);
  const usuario = respuesta.datos;
  const dialogo = document.createElement('dialog');
  dialogo.className = 'dialogo';
  const articulo = document.createElement('article');
  articulo.className = 'detalle-practica';
  const titulo = crearElemento(
    'h2',
    `${usuario.nombres || ''} ${usuario.apellidos || ''}`.trim() || 'Cuenta',
  );
  articulo.append(titulo);
  const campos = [
    ['Usuario', usuario.usuario],
    ['Correo', usuario.correo],
    ['Teléfono', usuario.telefono],
    ['Rol', usuario.rol_nombre || usuario.rol],
    ['Estado', usuario.estado_nombre || usuario.estado],
    ['Carrera', usuario.carrera],
    ['Carné', usuario.carnet],
    ['Universidad', usuario.universidad],
    ['Empresa', usuario.nombre_legal || usuario.nombre_comercial],
    ['Estado de empresa', usuario.empresa_estado],
    ['Creación', usuario.creado_en || usuario.fecha_creacion],
  ];
  campos
    .filter(([, valor]) => valor !== null && valor !== undefined && valor !== '')
    .forEach(([nombre, valor]) => {
      const fila = document.createElement('p');
      const etiqueta = crearElemento('strong', `${nombre}: `);
      fila.append(etiqueta, document.createTextNode(String(valor)));
      articulo.append(fila);
    });
  const cerrar = document.createElement('button');
  cerrar.type = 'button';
  cerrar.className = 'boton secundario';
  cerrar.textContent = 'Cerrar';
  cerrar.onclick = () => dialogo.close();
  articulo.append(cerrar);
  dialogo.append(articulo);
  dialogo.addEventListener('close', () => dialogo.remove(), { once: true });
  document.body.append(dialogo);
  dialogo.showModal();
  cerrar.focus();
}

export async function editarUsuario(usuario, alGuardar) {
  const dialogo = document.createElement('dialog');
  dialogo.className = 'dialogo';
  const form = document.createElement('form');
  form.className = 'formulario detalle-practica';
  const titulo = crearElemento('h2', 'Editar datos de cuenta');
  form.append(titulo);
  const definiciones = [
    ['nombres', 'Nombres', 'text'],
    ['apellidos', 'Apellidos', 'text'],
    ['correo', 'Correo', 'email'],
    ['telefono', 'Teléfono', 'tel'],
  ];
  definiciones.forEach(([nombre, texto, tipo]) => {
    const label = crearElemento('label', texto);
    const input = document.createElement('input');
    input.name = nombre;
    input.type = tipo;
    input.value = usuario[nombre] || '';
    input.maxLength = nombre === 'correo' ? 254 : nombre === 'telefono' ? 8 : 100;
    if (nombre !== 'telefono' || ['estudiante', 'empresa'].includes(usuario.rol_codigo))
      input.required = true;
    if (nombre === 'telefono') {
      input.inputMode = 'numeric';
      input.pattern = '[0-9]{8}';
    }
    label.append(input);
    form.append(label);
  });
  const acciones = document.createElement('div');
  acciones.className = 'acciones';
  const cancelar = document.createElement('button');
  cancelar.type = 'button';
  cancelar.className = 'boton secundario';
  cancelar.textContent = 'Cancelar';
  cancelar.onclick = () => dialogo.close();
  const guardar = document.createElement('button');
  guardar.type = 'submit';
  guardar.className = 'boton primario';
  guardar.textContent = 'Guardar';
  acciones.append(cancelar, guardar);
  form.append(acciones);
  form.onsubmit = async (evento) => {
    evento.preventDefault();
    if (!form.reportValidity()) return;
    try {
      await durantePeticion(
        guardar,
        async () => {
          await peticion(`admin/usuarios/${usuario.id_usuario}`, {
            method: 'PUT',
            body: JSON.stringify(formulario(form)),
          });
          dialogo.close();
          mostrar('Datos de la cuenta actualizados.');
          await alGuardar();
        },
        'Guardando…',
      );
    } catch (error) {
      if (Object.keys(error.errores || {}).length) pintarErroresCampos(form, error.errores);
      mostrar(error.message, true);
    }
  };
  dialogo.append(form);
  dialogo.addEventListener('close', () => dialogo.remove(), { once: true });
  document.body.append(dialogo);
  dialogo.showModal();
  form.elements.namedItem('nombres').focus();
}

export function mostrarClaveTemporal(clave) {
  const dialogo = document.createElement('dialog');
  dialogo.className = 'dialogo';
  const articulo = document.createElement('article');
  articulo.className = 'detalle-practica';
  const titulo = crearElemento('h2', 'Contraseña temporal');
  const aviso = crearElemento(
    'p',
    'Cópiala ahora. Se muestra una sola vez y la cuenta tendrá que cambiarla al iniciar sesión.',
  );
  const valor = crearElemento('p', clave, 'valor-temporal');
  const cerrar = document.createElement('button');
  cerrar.type = 'button';
  cerrar.className = 'boton primario';
  cerrar.textContent = 'Cerrar';
  cerrar.onclick = () => dialogo.close();
  articulo.append(titulo, aviso, valor, cerrar);
  dialogo.append(articulo);
  dialogo.addEventListener('close', () => dialogo.remove(), { once: true });
  document.body.append(dialogo);
  dialogo.showModal();
  cerrar.focus();
}

export async function gestionarUsuarios(root, filasIniciales, rol) {
  const caja = document.createElement('section');
  caja.className = 'panel';
  encabezado(caja, 'Usuarios');
  const buscar = document.createElement('form');
  buscar.className = 'filtros';
  const entrada = document.createElement('input');
  entrada.name = 'q';
  entrada.placeholder = 'Buscar por usuario, correo o nombre';
  entrada.setAttribute('aria-label', 'Buscar usuarios');
  const enviar = crearElemento('button', 'Buscar', 'boton secundario');
  buscar.append(entrada, enviar);
  caja.append(buscar);
  const lista = document.createElement('div');
  lista.className = 'tarjetas';
  caja.append(lista);
  const controles = document.createElement('nav');
  controles.className = 'paginador';
  controles.setAttribute('aria-label', 'Paginación de usuarios');
  const anterior = document.createElement('button');
  anterior.type = 'button';
  anterior.className = 'boton secundario';
  anterior.textContent = 'Anterior';
  const paginaTexto = document.createElement('span');
  const siguiente = document.createElement('button');
  siguiente.type = 'button';
  siguiente.className = 'boton secundario';
  siguiente.textContent = 'Siguiente';
  controles.append(anterior, paginaTexto, siguiente);
  caja.append(controles);
  let pagina = 1;
  const porPagina = 20;
  const pintar = (filas) => {
    lista.replaceChildren();
    if (!filas.length) {
      const vacio = crearElemento('p', 'No hay cuentas que coincidan con la búsqueda.');
      lista.append(vacio);
      return;
    }
    filas.forEach((usuario) => {
      const tarjetaUsuario = tarjeta(
        lista,
        `${usuario.nombres || ''} ${usuario.apellidos || ''}`.trim() || usuario.usuario,
        [
          `${usuario.usuario} · ${usuario.rol_nombre || usuario.rol || usuario.rol_codigo}`,
          usuario.correo,
        ],
      );
      etiquetaEstado(
        tarjetaUsuario,
        usuario.estado_nombre || usuario.estado,
        'usuario',
        usuario.estado_codigo,
      );
      boton('Ver detalles', () => verDetalleUsuario(usuario.id_usuario), tarjetaUsuario);
      boton('Editar datos', () => editarUsuario(usuario, cargar), tarjetaUsuario);
      boton(
        'Cambiar estado',
        async () => {
          const transicion = await elegirTransicion('usuario', usuario.estado_codigo);
          if (!transicion) return;
          await peticion(`admin/usuarios/${usuario.id_usuario}/estado`, {
            method: 'PUT',
            body: JSON.stringify({
              estado_codigo: transicion.destino.estado_codigo,
              comentario: transicion.comentario,
            }),
          });
          mostrar('Estado de la cuenta actualizado.');
          await cargar();
        },
        tarjetaUsuario,
      );
      if (rol === 'superadmin')
        boton(
          'Restablecer contraseña',
          async () => {
            const resultado = await peticion(
              `admin/usuarios/${usuario.id_usuario}/restablecer-clave`,
              { method: 'POST', body: '{}' },
            );
            mostrarClaveTemporal(resultado.datos.contrasena_temporal);
          },
          tarjetaUsuario,
        );
      boton(
        'Eliminar cuenta',
        async () => {
          const confirmacion = await confirmarAccion(
            'Se dará de baja la cuenta y se cancelarán las relaciones activas asociadas.',
            { titulo: 'Eliminar cuenta', textoConfirmar: 'Eliminar cuenta' },
          );
          if (!confirmacion.confirmado) return;
          await peticion(`admin/usuarios/${usuario.id_usuario}`, { method: 'DELETE' });
          mostrar('Cuenta eliminada lógicamente.');
          await cargar();
        },
        tarjetaUsuario,
      );
    });
  };
  const cargar = async () => {
    const consulta = new URLSearchParams({ pagina: String(pagina), por_pagina: String(porPagina) });
    if (entrada.value.trim()) consulta.set('q', entrada.value.trim());
    try {
      const respuesta = await peticion(`admin/usuarios?${consulta}`);
      const filas = respuesta.datos.elementos || [];
      pintar(filas);
      const limite = Number(respuesta.datos.por_pagina || porPagina);
      paginaTexto.textContent = `Página ${pagina}`;
      anterior.disabled = pagina <= 1;
      siguiente.disabled = filas.length < limite;
    } catch (error) {
      mostrar(error.message, true);
    }
  };
  buscar.onsubmit = async (evento) => {
    evento.preventDefault();
    pagina = 1;
    await cargar();
  };
  anterior.onclick = async () => {
    pagina = Math.max(1, pagina - 1);
    await cargar();
  };
  siguiente.onclick = async () => {
    pagina += 1;
    await cargar();
  };
  root.append(caja);
  if (Array.isArray(filasIniciales)) pintar(filasIniciales);
  await cargar();
}

export async function gestionarRestauracion(root) {
  encabezado(root, 'Cuentas eliminadas');
  const lista = document.createElement('div');
  lista.className = 'tarjetas';
  root.append(lista);
  const controles = document.createElement('nav');
  controles.className = 'paginador';
  controles.setAttribute('aria-label', 'Paginación de cuentas eliminadas');
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
  let pagina = 1;
  const porPagina = 20;
  const cargar = async () => {
    try {
      const respuesta = await peticion(
        `admin/usuarios?eliminados=1&pagina=${pagina}&por_pagina=${porPagina}`,
      );
      const filas = respuesta.datos.elementos || [];
      lista.replaceChildren();
      if (!filas.length) {
        const vacio = crearElemento('p', 'No hay cuentas eliminadas.');
        lista.append(vacio);
      }
      filas.forEach((usuario) => {
        const item = tarjeta(
          lista,
          `${usuario.nombres || ''} ${usuario.apellidos || ''}`.trim() || usuario.usuario,
          [
            `${usuario.usuario} · ${usuario.rol_nombre || usuario.rol || usuario.rol_codigo}`,
            usuario.correo,
          ],
        );
        boton(
          'Restaurar cuenta',
          async () => {
            const confirmacion = await confirmarAccion('La cuenta volverá a estar activa.', {
              titulo: 'Restaurar cuenta',
              textoConfirmar: 'Restaurar',
            });
            if (!confirmacion.confirmado) return;
            await peticion(`admin/usuarios/${usuario.id_usuario}/restaurar`, {
              method: 'POST',
              body: '{}',
            });
            mostrar('Cuenta restaurada.');
            await cargar();
          },
          item,
        );
      });
      const limite = Number(respuesta.datos.por_pagina || porPagina);
      estadoPagina.textContent = `Página ${pagina}`;
      anterior.disabled = pagina <= 1;
      siguiente.disabled = filas.length < limite;
    } catch (error) {
      mostrar(error.message, true);
    }
  };
  anterior.onclick = async () => {
    pagina = Math.max(1, pagina - 1);
    await cargar();
  };
  siguiente.onclick = async () => {
    pagina += 1;
    await cargar();
  };
  await cargar();
}
