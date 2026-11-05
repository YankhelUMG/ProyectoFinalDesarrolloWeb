import { validarArchivoCv, confirmarAccion, durantePeticion, pintarErroresCampos } from './ui.js';
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
} from './comun.js';

// Funciones de la pantalla: perfil.

export function formularioPerfil(root, p, rol) {
  encabezado(root, 'Actualizar perfil');
  const f = document.createElement('form');
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
    i.type = name === 'correo' ? 'email' : 'text';
    if (name === 'telefono') {
      i.inputMode = 'numeric';
      i.pattern = '[0-9]{8}';
      i.maxLength = 8;
    }
    i.value = (name === 'correo' && rol === 'empresa' ? p.correo_usuario : p[name]) || '';
    i.required = name !== 'telefono' || rol === 'estudiante';
    l.append(i);
    f.append(l);
  }
  if (rol === 'estudiante') {
    for (const [name, label] of [
      ['carnet', 'Carné'],
      ['universidad', 'Universidad'],
      ['semestre', 'Semestre'],
    ]) {
      const l = crearElemento('label', label);
      const i = document.createElement('input');
      i.name = name;
      i.value = p[name] || '';
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
      i.value = p[name] || '';
      l.append(i);
      f.append(l);
    }
    const l = crearElemento('label', 'Descripción');
    const t = document.createElement('textarea');
    t.name = 'descripcion';
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
    if (!f.reportValidity()) return;
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
    const cv = document.createElement('form');
    cv.className = 'formulario';
    cv.enctype = 'multipart/form-data';
    const l = crearElemento('label', `CV actual: ${p.cv_nombre || 'No disponible'}`);
    const file = document.createElement('input');
    file.type = 'file';
    file.name = 'cv';
    file.accept = 'application/pdf,.pdf';
    file.required = true;
    file.addEventListener('change', () => {
      const archivo = file.files?.[0];
      if (!archivo) return;
      if (validarArchivoCv(cv, file, estado.cvMaximo)) limpiarErroresFormulario(cv);
    });
    l.append(file);
    cv.append(l);
    const up = crearElemento('button', 'Reemplazar CV', 'boton secundario');
    cv.append(up);
    cv.onsubmit = async (e) => {
      e.preventDefault();
      if (!cv.reportValidity()) return;
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
    root.append(cv);
    if (p.cv_nombre && !Number(p.cv_eliminado))
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
        root,
      );
    if (p.cv_nombre && !Number(p.cv_eliminado)) {
      boton(
        'Marcar CV como eliminado',
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
        root,
      );
    }
  }
}
