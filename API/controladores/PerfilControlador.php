<?php
declare(strict_types=1);
/** Perfil propio y CV. Cada tipo de cuenta tiene una función fácil de localizar. */
final class PerfilControlador extends ControladorBase {
    public function rutasPerfil(): never {
        $cuentaActual = Acceso::requerirRol(['estudiante', 'empresa', 'admin', 'superadmin'], true);
        if ($cuentaActual['rol_codigo'] === 'estudiante') $this->perfilEstudiante($cuentaActual);
        if ($cuentaActual['rol_codigo'] === 'empresa') $this->perfilEmpresa($cuentaActual);
        $this->perfilAdministrativo($cuentaActual);
    }
    private function perfilAdministrativo(array $cuentaActual): never {
        if ($this->metodo === 'GET' && count($this->ruta) === 1) {
            Respuesta::exito($this->usuarios->porId((int)$cuentaActual['id_usuario']));
        }
        if ($this->metodo === 'PUT' && count($this->ruta) === 1) {
            $datos = [
                'correo' => $this->correo('correo'),
                'nombres' => $this->texto('nombres', 100, true),
                'apellidos' => $this->texto('apellidos', 100, true),
                'telefono' => $this->telefono('telefono', false),
                'actor' => (int)$cuentaActual['id_usuario']
            ];
            $this->usuarios->transaccion(fn() => $this->usuarios->actualizarDatos((int)$cuentaActual['id_usuario'], $datos));
            Respuesta::exito(null, 'Perfil actualizado.');
        }
        Respuesta::error('Solo puedes consultar o actualizar los datos de tu propio perfil.', 405);
    }
    private function perfilEstudiante(array $cuentaActual): never {
        $perfil = $this->estudiantes->estudiantePorUsuario((int)$cuentaActual['id_usuario']);
        if ($this->metodo === 'GET' && count($this->ruta) === 1) {
            if ($perfil) {
                unset($perfil['cv_ruta'], $perfil['cv_mime']);
            }
            Respuesta::exito($perfil);
        }
        if ($this->metodo === 'PUT' && count($this->ruta) === 1) {
            $datosEntrada = [
                'carrera' => $this->entero('id_carrera'),
                'carnet' => $this->texto('carnet', 40, true),
                'semestre' => $this->texto('semestre', 40, false),
                'universidad' => $this->texto('universidad', 180, true),
                'actor' => (int)$cuentaActual['id_usuario']
            ];
            $datos = [
                'correo' => $this->correo('correo'),
                'nombres' => $this->texto('nombres', 100, true),
                'apellidos' => $this->texto('apellidos', 100, true),
                'telefono' => $this->telefono('telefono', true),
                'actor' => (int)$cuentaActual['id_usuario']
            ];
            if (!$this->catalogos->idActivo('carreras', $datosEntrada['carrera'])) throw new DomainException('Selecciona una carrera activa.');
            $this->usuarios->transaccion(function()use($cuentaActual, $datosEntrada, $datos) {
                $this->estudiantes->guardarPerfilEstudiante((int)$cuentaActual['id_usuario'], $datosEntrada);
                $this->usuarios->actualizarDatos((int)$cuentaActual['id_usuario'], $datos);
            });
            Respuesta::exito(null, 'Perfil actualizado.');
        }
        if (($this->ruta[1] ?? '') === 'cv' && $this->metodo === 'GET') {
            $cv = $this->estudiantes->cvPerfilEstudiante((int)$cuentaActual['id_usuario']);
            if (!$cv || !empty($cv['cv_eliminado'])) Respuesta::error((new ConfiguracionRepo())->mensaje('error_cv_no_disponible', 'El CV no está disponible.'), 404);
            $this->transmitirCv($cv, 'estudiantes', (string)$cv['id_estudiante'], (int)$cuentaActual['id_usuario']);
        }
        if (($this->ruta[1] ?? '') === 'cv' && $this->metodo === 'POST') {
            $cv = (new ArchivosCv())->guardar($_FILES['cv'] ?? []);
            $this->estudiantes->guardarCv((int)$cuentaActual['id_usuario'], $cv['nombre'], $cv['ruta'], $cv['mime'], (int)$cuentaActual['id_usuario']);
            Respuesta::exito(['cv_nombre' => $cv['nombre']], (new ConfiguracionRepo())->mensaje('exito_cv_actualizado', 'CV actualizado correctamente.'));
        }
        if (($this->ruta[1] ?? '') === 'cv' && $this->metodo === 'DELETE') {
            $this->estudiantes->marcarCvEliminado((int)$cuentaActual['id_usuario'], (int)$cuentaActual['id_usuario']);
            Respuesta::exito(null, (new ConfiguracionRepo())->mensaje('exito_cv_eliminado', 'El CV se marcó como eliminado.'));
        }
        Respuesta::error((new ConfiguracionRepo())->mensaje('error_no_encontrado', 'No se encontró el registro solicitado.'), 404);
    }
    private function perfilEmpresa(array $cuentaActual): never {
        $perfil = $this->empresas->perfil((int)$cuentaActual['id_usuario']);
        if ($this->metodo === 'GET' && count($this->ruta) === 1) Respuesta::exito($perfil);
        if ($this->metodo === 'PUT' && count($this->ruta) === 1) {
            $web = $this->texto('sitio_web', 255, false);
            if ($web !== null && (!filter_var($web, FILTER_VALIDATE_URL) || !in_array(strtolower((string)parse_url($web, PHP_URL_SCHEME)), ['http', 'https'], true))) throw new DomainException('Ingresa un sitio web válido.');
            $datosUsuario = [
                'correo' => $this->correo('correo'),
                'nombres' => $this->texto('nombres', 100, true),
                'apellidos' => $this->texto('apellidos', 100, true),
                'telefono' => $this->telefono('telefono', false),
                'actor' => (int)$cuentaActual['id_usuario']
            ];
            $datosEmpresa = [
                'legal' => $this->texto('nombre_legal', 180, true),
                'comercial' => $this->texto('nombre_comercial', 180, false),
                'fiscal' => $this->texto('identificacion_fiscal', 50, false),
                'sector' => $this->texto('sector', 100, false),
                'descripcion' => $this->texto('descripcion', 5000, false),
                'correo' => $this->correo('correo_contacto'),
                'telefono' => $this->telefono('telefono', false),
                'web' => $web,
                'direccion' => $this->texto('direccion', 255, false),
                'actor' => (int)$cuentaActual['id_usuario']
            ];
            $this->usuarios->transaccion(function()use($cuentaActual, $datosUsuario, $datosEmpresa) {
                $this->usuarios->actualizarDatos((int)$cuentaActual['id_usuario'], $datosUsuario);
                $this->empresas->guardarPerfil((int)$cuentaActual['id_usuario'], $datosEmpresa);
            });
            Respuesta::exito(null, (new ConfiguracionRepo())->mensaje('exito_guardado', 'Perfil actualizado.'));
        }
        Respuesta::error((new ConfiguracionRepo())->mensaje('error_no_encontrado', 'No se encontró el registro solicitado.'), 404);
    }

    public function descargarCv(): never {
        $cuentaActual = Acceso::requerirRol(['estudiante', 'empresa', 'admin', 'superadmin'], true);
        if ($cuentaActual['rol_codigo'] === 'estudiante') Acceso::requerirPermiso('postulaciones.consultar_propias');
        elseif ($cuentaActual['rol_codigo'] === 'empresa') {
            Acceso::requerirPermiso('postulaciones.consultar_empresa');
            Acceso::requiereEmpresaAprobada((int)$cuentaActual['id_usuario']);
        } else Acceso::requerirPermiso('postulaciones.consultar');
        $id = (int)($this->ruta[1] ?? 0);
        if ($id < 1) Respuesta::error('CV no encontrado.', 404);
        $row = $this->postulaciones->cvDescarga($id);
        if (!$row) Respuesta::error('CV no disponible.', 404);
        $permitido = match($cuentaActual['rol_codigo']) {
            'estudiante' => (int)$row['id_usuario_estudiante'] === (int)$cuentaActual['id_usuario'], 'empresa' => (int)$row['id_usuario_empresa'] === (int)$cuentaActual['id_usuario'], 'admin', 'superadmin' => true, default => false
        };
        if (!$permitido) Respuesta::error((new ConfiguracionRepo())->mensaje('error_sin_permiso', 'No tienes permiso para descargar este CV.'), 403);
        $this->transmitirCv($row, 'postulaciones', (string)$id, (int)$cuentaActual['id_usuario']);
    }
    public function reportes(): never {
        $cuentaActual = Acceso::requerirRol(['admin', 'superadmin']);
        Acceso::requerirPermiso('reportes.consultar');
        (new ServicioCierre())->procesarAutomaticos();
        Respuesta::exito($this->datosReporte());
    }
    public function dashboard(): never {
        $cuentaActual = Acceso::requerirRol(['estudiante', 'empresa', 'admin', 'superadmin'], true);
        if ($cuentaActual['rol_codigo'] === 'estudiante') Acceso::requerirPermiso('postulaciones.consultar_propias');
        elseif ($cuentaActual['rol_codigo'] === 'empresa') {
            Acceso::requerirPermiso('postulaciones.consultar_empresa');
            Acceso::requiereEmpresaAprobada((int)$cuentaActual['id_usuario']);
        } else Acceso::requerirPermiso('reportes.consultar');
        (new ServicioCierre())->procesarAutomaticos();
        $datos = match($cuentaActual['rol_codigo']) {
            'estudiante' => ['postulaciones' => $this->postulaciones->resumenEstudiante((int)$cuentaActual['id_usuario'])], 'empresa' => [
                'practicas' => $this->practicas->resumenEmpresa((int)$cuentaActual['id_usuario']),
                'postulaciones' => $this->postulaciones->resumenEmpresa((int)$cuentaActual['id_usuario'])
            ], 'admin', 'superadmin' => $this->datosReporte(), default => []
        };
        Respuesta::exito($datos);
    }
}
