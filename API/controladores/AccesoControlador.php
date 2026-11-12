<?php
declare(strict_types=1);
/** Acceso y catálogos públicos. */
final class AccesoControlador extends ControladorBase {
    /** Devuelve el token CSRF y los datos públicos del sistema. */
    public function catalogosPublicos(): never {
        if ($this->metodo !== 'GET') Respuesta::error('Método no permitido para este grupo de rutas.', 405);
        if ($this->ruta === ['csrf']) Respuesta::exito(['csrf_token' => Sesion::csrfNuevo()]);
        if ($this->ruta === ['sistema']) Respuesta::exito((new ConfiguracionRepo())->publicos());
        Respuesta::error('Método no permitido para este grupo de rutas.', 405);
    }
    /** Rutas de sesión: login, logout, me, registros y cambio de clave. */
    public function sesion(): never {
        if ($this->metodo === 'GET' && $this->ruta === ['auth', 'me']) {
            $usuario = Sesion::actual(true);
            $usuario['csrf_token'] = Sesion::csrfNuevo();
            Respuesta::exito($usuario);
        }
        $this->autenticacion();
    }
    /** Inicia sesión con usuario o correo y contraseña. */
    public function autenticacion(): never {
        $accion = $this->ruta[1] ?? '';
        if ($accion === 'login' && $this->metodo === 'POST') {
            $id = $this->texto('identificador', 254, true);
            $clave = $this->texto('contrasena', 200, true);
            $tipo = isset($this->datos['tipo_cuenta']) ? trim((string)$this->datos['tipo_cuenta']) : null;
            if ($tipo !== null && !in_array($tipo, ['estudiante', 'empresa'], true)) throw new DomainException('El tipo de cuenta seleccionado no es válido.');
            $cuentaActual = Sesion::iniciar($id, $clave, $tipo);
            Respuesta::exito($cuentaActual, (new ConfiguracionRepo())->mensaje('exito_login', 'Sesión iniciada.'), 200);
        }
        if ($accion === 'logout' && $this->metodo === 'DELETE') Sesion::cerrar();
        if ($accion === 'registro-estudiante' && $this->metodo === 'POST') $this->registrarEstudiante();
        if ($accion === 'registro-empresa' && $this->metodo === 'POST') $this->registrarEmpresa();
        if ($accion === 'cambiar-clave' && $this->metodo === 'POST') $this->cambiarClave();
        Respuesta::error((new ConfiguracionRepo())->mensaje('error_no_encontrado', 'No se encontró la ruta solicitada.'), 404);
    }
    /** Crea una cuenta de estudiante con su CV en PDF. */
    private function registrarEstudiante(): never {
        $correo = $this->correo('correo');
        $telefono = $this->telefono('telefono', true);
        $clave = $this->claveValida();
        $nombres = $this->nombrePersona('nombres');
        $apellidos = $this->nombrePersona('apellidos');
        $usuario = $this->texto('usuario', 40, true);
        $carnet = $this->carnet();
        $universidad = $this->universidad();
        $semestre = $this->semestre();
        $carrera = $this->entero('id_carrera');
        $erroresUnicos = [];
        if ($this->usuarios->usuarioExiste($usuario)) $erroresUnicos['usuario'] = 'Este nombre de usuario ya está registrado.';
        if ($this->usuarios->correoExiste($correo)) $erroresUnicos['correo'] = 'Este correo ya está registrado.';
        if ($this->estudiantes->carnetExiste($carnet)) $erroresUnicos['carnet'] = 'Este carné ya está registrado.';
        if ($erroresUnicos) Respuesta::error('Corrige los campos marcados.', 409, $erroresUnicos);
        if (!preg_match('/^[A-Za-z0-9._-]{3,40}$/', $usuario)) throw new DomainException('Revisa el formato del nombre de usuario.');
        if (!$this->catalogos->idActivo('carreras', $carrera)) throw new DomainException('Selecciona una carrera activa.');
        $archivo = (new ArchivosCv())->guardar($_FILES['cv'] ?? []);
        $rol = $this->catalogos->rolId('estudiante');
        $estado = $this->catalogos->estadoId('usuario', 'activo');
        $id = $this->usuarios->transaccion(function()use($correo, $telefono, $clave, $nombres, $apellidos, $usuario, $carnet, $universidad, $semestre, $carrera, $archivo, $rol, $estado) {
            $uid = $this->usuarios->registrar([
                'rol' => $rol,
                'estado' => $estado,
                'usuario' => $usuario,
                'correo' => $correo,
                'nombres' => $nombres,
                'apellidos' => $apellidos,
                'telefono' => $telefono,
                'hash' => password_hash($clave, PASSWORD_DEFAULT),
                'cambio' => 0,
                'actor' => null
            ]);
            $this->usuarios->registrarHistorial($uid, null, $estado, $uid, 'Registro de estudiante.');
            $this->estudiantes->crearEstudiante([
                'usuario' => $uid,
                'carrera' => $carrera,
                'carnet' => $carnet,
                'semestre' => $semestre,
                'universidad' => $universidad,
                'cv_nombre' => $archivo['nombre'],
                'cv_ruta' => $archivo['ruta'],
                'cv_mime' => $archivo['mime'],
                'actor' => $uid
            ]);
            return $uid;
        });
        (new BitacoraRepo())->registrar('REGISTRO_ESTUDIANTE', 'usuarios', (string)$id, null, ['correo' => $correo], $id);
        $sesion = Sesion::iniciar($usuario, $clave);
        Respuesta::exito($sesion, (new ConfiguracionRepo())->mensaje('exito_registro_estudiante', 'Cuenta de estudiante creada.'), 201);
    }
    /** Registra una empresa en estado pendiente de revisión. */
    private function registrarEmpresa(): never {
        $correo = $this->correo('correo');
        $contacto = $this->correo('correo_contacto');
        $telefono = $this->telefono('telefono', false);
        $clave = $this->claveValida();
        $usuario = $this->texto('usuario', 40, true);
        $nombres = $this->nombrePersona('nombres');
        $apellidos = $this->nombrePersona('apellidos');
        $legal = $this->texto('nombre_legal', 180, true);
        $comercial = $this->texto('nombre_comercial', 180, false);
        $fiscal = $this->texto('identificacion_fiscal', 50, false);
        $sector = $this->texto('sector', 100, false);
        $descripcion = $this->texto('descripcion', 5000, false);
        $web = $this->texto('sitio_web', 255, false);
        $direccion = $this->texto('direccion', 255, false);
        $erroresUnicos = [];
        if ($this->usuarios->usuarioExiste($usuario)) $erroresUnicos['usuario'] = 'Este nombre de usuario ya está registrado.';
        if ($this->usuarios->correoExiste($correo)) $erroresUnicos['correo'] = 'Este correo de acceso ya está registrado.';
        if ($fiscal !== null && $this->empresas->identificacionFiscalExiste($fiscal)) $erroresUnicos['identificacion_fiscal'] = 'Esta identificación fiscal ya está registrada.';
        if ($erroresUnicos) Respuesta::error('Corrige los campos marcados.', 409, $erroresUnicos);
        if (!preg_match('/^[A-Za-z0-9._-]{3,40}$/', $usuario)) throw new DomainException('El usuario debe tener de 3 a 40 letras, números, punto, guion o guion bajo.');
        if ($web !== null && (!filter_var($web, FILTER_VALIDATE_URL) || !in_array(strtolower((string)parse_url($web, PHP_URL_SCHEME)), ['http', 'https'], true))) throw new DomainException('Ingresa una dirección web segura válida o deja el campo vacío.');
        $rol = $this->catalogos->rolId('empresa');
        $pendienteUsuario = $this->catalogos->estadoId('usuario', 'pendiente');
        $pendienteEmpresa = $this->catalogos->estadoId('empresa', 'pendiente');
        $id = $this->usuarios->transaccion(function()use($correo, $telefono, $clave, $usuario, $nombres, $apellidos, $legal, $comercial, $fiscal, $sector, $descripcion, $contacto, $web, $direccion, $rol, $pendienteUsuario, $pendienteEmpresa) {
            $uid = $this->usuarios->registrar([
                'rol' => $rol,
                'estado' => $pendienteUsuario,
                'usuario' => $usuario,
                'correo' => $correo,
                'nombres' => $nombres,
                'apellidos' => $apellidos,
                'telefono' => $telefono,
                'hash' => password_hash($clave, PASSWORD_DEFAULT),
                'cambio' => 0,
                'actor' => null
            ]);
            $this->usuarios->registrarHistorial($uid, null, $pendienteUsuario, $uid, 'Solicitud de registro de empresa.');
            $empresaId = $this->empresas->crearEmpresa([
                'usuario' => $uid,
                'estado' => $pendienteEmpresa,
                'legal' => $legal,
                'comercial' => $comercial,
                'fiscal' => $fiscal,
                'sector' => $sector,
                'descripcion' => $descripcion,
                'correo' => $contacto,
                'telefono' => $telefono,
                'web' => $web,
                'direccion' => $direccion,
                'actor' => $uid
            ]);
            $this->empresas->registrarHistorial($empresaId, null, $pendienteEmpresa, $uid, 'Solicitud de registro.');
            return $uid;
        });
        (new BitacoraRepo())->registrar('REGISTRO_EMPRESA', 'usuarios', (string)$id, null, ['estado' => 'pendiente'], null);
        Respuesta::exito(['id_usuario' => $id, 'estado_revision' => 'pendiente'], (new ConfiguracionRepo())->mensaje('exito_registro_empresa', 'Solicitud registrada. Puedes iniciar sesión para consultar el estado de revisión; las funciones de empresa se habilitarán cuando la aprueben.'), 201);
    }
    /** Cambia la contraseña de la cuenta autenticada. */
    private function cambiarClave(): never {
        $cuentaActual = Sesion::actual(true);
        $min = max(8, (int)(new ConfiguracionRepo())->obtener('password_longitud_minima', '10'));
        $nueva = $this->texto('contrasena_nueva', 200, true);
        $confirmar = $this->texto('confirmacion', 200, true);
        if (strlen($nueva) < $min || $nueva !== $confirmar) throw new DomainException('La contraseña debe cumplir la longitud mínima y coincidir con su confirmación.');
        $this->usuarios->cambiarClave((int)$cuentaActual['id_usuario'], password_hash($nueva, PASSWORD_DEFAULT));
        (new BitacoraRepo())->registrar('CAMBIO_CLAVE', 'usuarios', (string)$cuentaActual['id_usuario'], null, ['debe_cambiar_password' => false], (int)$cuentaActual['id_usuario']);
        Respuesta::exito(['debe_cambiar_password' => false], (new ConfiguracionRepo())->mensaje('exito_clave', 'Contraseña actualizada.'));
    }
    /** Lista catálogos y transiciones de estado permitidas. */
    public function catalogos(): never {
        if ($this->metodo !== 'GET') Respuesta::error('Método no permitido.', 405);
        if (($this->ruta[1] ?? '') === 'transiciones') {
            $cuentaActual = Acceso::requerirRol(['estudiante', 'empresa', 'admin', 'superadmin'], true);
            $entidad = trim((string)($_GET['entidad'] ?? ''));
            $estado = trim((string)($_GET['estado_codigo'] ?? ''));
            if ($entidad === '' || $estado === '') throw new DomainException('Indica entidad y estado de origen.');
            Respuesta::exito($this->catalogos->transiciones($entidad, $estado, $this->catalogos->rolId((string)$cuentaActual['rol_codigo'])));
        }
        $tipo = isset($_GET['tipo']) ? trim((string)$_GET['tipo']): null;
        Respuesta::exito($this->catalogos->lista($tipo));
    }
}
