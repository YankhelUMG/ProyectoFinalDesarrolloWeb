import { validarArchivoCv, confirmarAccion, durantePeticion, pintarErroresCampos, validarFormularioEspanol } from './ui.js?v=validacion-es-20261111-3';
import {
  crearElemento,
  mostrar,
  peticion,
  formulario,
  limpiarErroresFormulario,
  ponerSesion,
  estado,
  $,
  actualizarPantalla,
  boton,
  encabezado,
  etiquetaEstado,
  configurarCampoNombre,
  configurarCampoCarnet,
} from './comun.js?v=cambio-estado-modal-20261111-1';

// Funciones de la pantalla: perfil.

export function formularioPerfil(root, p, rol) {
  encabezado(root, 'Actualizar perfil');
  if (rol === 'empresa') {
    const revision = document.createElement('section');
    revision.className = 'panel-revision-empresa';
    const tituloRevision = document.createElement('div');
    tituloRevision.className = 'panel-revision-empresa__encabezado';
    tituloRevision.append(crearElemento('h2', 'Estado de revisión'));
    const estadoRevision = document.createElement('div');
    etiquetaEstado(estadoRevision, p.estado, 'empresa', p.estado_codigo);
    tituloRevision.append(estadoRevision);
    revision.append(tituloRevision);
    const comentario = String(p.observaciones_coordinacion || '').trim();
    if (comentario) {
      const detalle = document.createElement('div');
      detalle.className = 'aviso panel-comentario-revision';
      detalle.append(
        crearElemento('h3', 'Comentario de revisión'),
        crearElemento('p', comentario),
      );
      revision.append(detalle);
    }
    root.append(revision);
  }
  const f = document.createElement('form');
  f.noValidate = true;
  f.className = 'formulario columnas panel';
  const comunes = [
    ['nombres', 'Nombres'],
    ['apellidos', 'Apellidos'],
    ['correo', 'Correo'],
    ['telefono', 'Teléfono'],
  ];
  for (const [name, label] of comunes) {
    const l = crearElemento('label', label);
    const i = document.createElement('input');
    i.name = name;
    const ejemplos = {
      nombres: 'Ana',
      apellidos: 'Pérez',
      correo: 'ana@correo.com',
      telefono: '55551234',
    };
    if (ejemplos[name]) i.placeholder = ejemplos[name];
    i.type = name === 'correo' ? 'email' : 'text';
    if (name === 'telefono') {
      i.inputMode = 'numeric';
      i.pattern = '[0-9]{8}';
      i.maxLength = 8;
    }
    i.value = (name === 'correo' && rol === 'empresa' ? p.correo_usuario : p[name]) || '';
    i.required = name !== 'telefono' || rol === 'estudiante';
    l.append(i);
    if (name === 'nombres' || name === 'apellidos') configurarCampoNombre(i);
    f.append(l);
  }
  if (rol === 'estudiante') {
    for (const [name, label] of [['carnet', 'Carné'], ['universidad', 'Universidad'], ['semestre', 'Semestre']]) {
      const l = crearElemento('label', label);
      let i;
      if (name === 'universidad') {
        i = document.createElement('select');
        i.name = name;
        const opciones = [
          ['Universidad Mariano Gálvez de Guatemala', 'Universidad Mariano Gálvez de Guatemala'],
          ['Universidad Rafael Landívar', 'Universidad Rafael Landívar'],
        ];
        const vacio = document.createElement('option');
        vacio.value = '';
        vacio.textContent = 'Selecciona una universidad';
        i.append(vacio);
        for (const [valor, texto] of opciones) {
          const opcion = document.createElement('option');
          opcion.value = valor;
          opcion.textContent = texto;
          opcion.selected = p[name] === valor;
          i.append(opcion);
        }
        i.required = true;
      } else {
        i = document.createElement('input');
        i.name = name;
      }
      const ejemplos = { carnet: '20260001', universidad: 'Universidad Mariano Gálvez' };
      if (ejemplos[name]) i.placeholder = ejemplos[name];
      if (name === 'carnet') {
        i.pattern = '[0-9-]*[0-9][0-9-]*';
        i.title = 'Solo números y guiones';
        i.maxLength = 40;
        configurarCampoCarnet(i);
      }
      if (name === 'semestre') {
        i.type = 'number';
        i.min = '1';
        i.step = '1';
        i.inputMode = 'numeric';
        i.placeholder = '1';
        i.className = 'campo-semestre';
      }
      if (name !== 'universidad') i.value = p[name] || '';
      l.append(i);
      f.append(l);
    }
    const l = crearElemento('label', 'Carrera');
    const s = document.createElement('select');
    s.name = 'id_carrera';
    for (const c of estado.catalogos.filter((x) => x.tipo === 'carrera')) {
      const o = document.createElement('option');
      o.value = c.id;
      o.textContent = c.nombre;
      o.selected = Number(c.id) === Number(p.id_carrera);
      s.append(o);
    }
    l.append(s);
    f.append(l);
  } else if (rol === 'empresa') {
    for (const [name, label] of [
      ['nombre_legal', 'Nombre legal'],
      ['nombre_comercial', 'Nombre comercial'],
      ['identificacion_fiscal', 'Identificación fiscal'],
      ['sector', 'Sector'],
      ['correo_contacto', 'Correo de contacto'],
      ['sitio_web', 'Sitio web'],
      ['direccion', 'Dirección'],
    ]) {
      const l = crearElemento('label', label);
      const i = document.createElement('input');
      i.name = name;
      const ejemplos = {
        nombre_legal: 'Nombre legal de la empresa',
        nombre_comercial: 'Nombre de la marca',
        identificacion_fiscal: 'NIT',
        sector: 'Tecnología',
        correo_contacto: 'contacto@empresa.com',
        sitio_web: 'https://empresa.com',
        direccion: 'Ciudad de Guatemala',
      };
      if (ejemplos[name]) i.placeholder = ejemplos[name];
      i.value = p[name] || '';
      if (name === 'nombres' || name === 'apellidos') configurarCampoNombre(i);
      l.append(i);
      f.append(l);
    }
    const l = crearElemento('label', 'Descripción');
    const t = document.createElement('textarea');
    t.name = 'descripcion';
    t.placeholder = 'Describe brevemente la empresa';
    t.value = p.descripcion || '';
    l.append(t);
    f.append(l);
  }
  const camposEditables = Array.from(f.querySelectorAll('input, select, textarea'));
  const valoresIniciales = new Map(camposEditables.map((campo) => [campo, campo.value]));
  const marcarCambios = () =>
    camposEditables.forEach((campo) => {
      const modificado =
        valoresIniciales.get(campo) !== campo.value || campo.dataset.perfilGuardado === 'true';
      campo.closest('label')?.classList.toggle('campo-modificado', modificado);
    });
  f.addEventListener('input', marcarCambios);
  f.addEventListener('change', marcarCambios);
  const b = crearElemento('button', 'Actualizar perfil', 'boton primario');
  f.append(b);
  f.onsubmit = async (e) => {
    e.preventDefault();
    if (!validarFormularioEspanol(f)) return;
    try {
      await durantePeticion(
        b,
        async () => {
          const modificados = camposEditables.filter(
            (campo) => valoresIniciales.get(campo) !== campo.value,
          );
          await peticion('perfil', { method: 'PUT', body: JSON.stringify(formulario(f)) });
          mostrar('Perfil actualizado.');
          modificados.forEach((campo) => {
            campo.dataset.perfilGuardado = 'true';
          });
          camposEditables.forEach((campo) => valoresIniciales.set(campo, campo.value));
          marcarCambios();
          const sesionActualizada = await peticion('auth/me');
          estado.usuario = sesionActualizada.datos;
          ponerSesion();
        },
        'Guardando…',
      );
    } catch (err) {
      if (Object.keys(err.errores || {}).length) pintarErroresCampos(f, err.errores);
      mostrar(err.message, true);
    }
  };
  root.append(f);
  if (rol === 'estudiante') {
    const tieneCv = Boolean(p.cv_nombre && !Number(p.cv_eliminado));
    const panelCv = document.createElement('section');
    panelCv.className = 'panel-cv';
    const encabezadoCv = document.createElement('div');
    encabezadoCv.className = 'panel-cv__encabezado';
    const estadoCv = crearElemento(
      'span',
      tieneCv ? 'CV activo' : p.cv_nombre ? 'CV eliminado' : 'Falta subir CV',
      `panel-cv__estado${tieneCv ? ' activo' : ''}`,
    );
    const tituloCv = document.createElement('div');
    tituloCv.className = 'panel-cv__titulo';
    tituloCv.append(
      crearElemento('span', 'Documento requerido', 'panel-cv__subtitulo'),
      crearElemento('h3', 'Currículum vitae'),
    );
    encabezadoCv.append(tituloCv, estadoCv);

    const archivoActual = document.createElement('div');
    archivoActual.className = 'panel-cv__archivo';
    archivoActual.append(crearElemento('span', 'PDF', 'panel-cv__tipo'));
    const archivoTexto = document.createElement('div');
    archivoTexto.className = 'panel-cv__archivo-texto';
    archivoTexto.append(
      crearElemento('strong', tieneCv ? p.cv_nombre : 'No hay un CV activo'),
      crearElemento(
        'span',
        tieneCv
          ? 'Este archivo se usará al postularte a una práctica.'
          : 'Sube tu currículum en formato PDF para poder postularte.',
      ),
    );
    archivoActual.append(archivoTexto);

    const cv = document.createElement('form');
    cv.noValidate = true;
    cv.className = 'formulario panel-cv__formulario';
    cv.enctype = 'multipart/form-data';
    const l = crearElemento('label', 'Selecciona un archivo PDF');
    const file = document.createElement('input');
    file.type = 'file';
    file.name = 'cv';
    file.accept = 'application/pdf,.pdf';
    file.required = true;
    const seleccionado = crearElemento('span', 'Ningún archivo seleccionado', 'panel-cv__seleccionado');
    file.addEventListener('change', () => {
      const archivo = file.files?.[0];
      if (!archivo) return;
      seleccionado.textContent = `Seleccionado: ${archivo.name}`;
      if (validarArchivoCv(cv, file, estado.cvMaximo)) limpiarErroresFormulario(cv);
    });
    l.append(file);
    const up = crearElemento('button', tieneCv ? 'Reemplazar CV' : 'Subir CV', 'boton primario');
    const acciones = document.createElement('div');
    acciones.className = 'panel-cv__acciones';
    cv.append(l, seleccionado, up);
    cv.onsubmit = async (e) => {
      e.preventDefault();
      if (!validarFormularioEspanol(cv)) return;
      if (!validarArchivoCv(cv, file, estado.cvMaximo)) return;
      try {
        await durantePeticion(
          up,
          async () => {
            await peticion('perfil/cv', { method: 'POST', body: new FormData(cv) });
            mostrar('CV actualizado.');
            await actualizarPantalla();
          },
          'Subiendo CV…',
        );
      } catch (err) {
        if (Object.keys(err.errores || {}).length) pintarErroresCampos(cv, err.errores);
        mostrar(err.message, true);
      }
    };
    if (tieneCv)
      boton(
        'Descargar mi CV',
        async () => {
          try {
            const blob = await peticion('perfil/cv');
            const url = URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.href = url;
            link.download = p.cv_nombre || 'CV.pdf';
            link.click();
            setTimeout(() => URL.revokeObjectURL(url), 1000);
          } catch (error) {
            mostrar(error.message, true);
          }
        },
        acciones,
      );
    if (tieneCv) {
      boton(
        'Eliminar CV',
        async () => {
          try {
            const confirmacion = await confirmarAccion(
              'No podrás usar este CV para nuevas postulaciones hasta que subas otro archivo.',
              { titulo: 'Eliminar CV', textoConfirmar: 'Eliminar CV' },
            );
            if (!confirmacion.confirmado) return;
            await peticion('perfil/cv', {
              method: 'DELETE',
            });
            await actualizarPantalla();
          } catch (err) {
            mostrar(err.message, true);
          }
        },
        acciones,
      );
    }
    panelCv.append(encabezadoCv, archivoActual, cv);
    if (acciones.childElementCount) panelCv.append(acciones);
    root.append(panelCv);
  }
}
