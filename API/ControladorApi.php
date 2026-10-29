<?php
declare(strict_types = 1);
/** Centraliza las rutas de la API y coordina validaciones, permisos y repositorios. */
final class ControladorApi {
    private array $ruta;
    private string $metodo;
    private array $datos;
    private CatalogoRepo $catalogos;
    private UsuarioRepo $usuarios;
    private PracticaRepo $practicas;
    private PostulacionRepo $postulaciones;
    private EmpresaRepo $empresas;
    /** Lee la solicitud HTTP y prepara los repositorios usados por los endpoints. */
    public function __construct() {
        $this->ruta = Peticion::ruta();
        $this->metodo = Peticion::metodo();
        $this->datos = Peticion::datos();
        $this->catalogos = new CatalogoRepo();
        $this->usuarios = new UsuarioRepo();
        $this->practicas = new PracticaRepo();
        $this->postulaciones = new PostulacionRepo();
        $this->empresas = new EmpresaRepo();
    }
    /** Dirige cada solicitud a la sección de API correspondiente. */
    public function ejecutar(): never {
        if ($this->metodo === 'OPTIONS') {
            header('Allow: GET, POST, PUT, DELETE, OPTIONS');
            http_response_code(204);
            exit;
        }
        $metodos = ['GET', 'POST', 'PUT', 'DELETE'];
        if (!in_array($this->metodo, $metodos, true)) Respuesta::error('Método HTTP no permitido.', 405);
        if (in_array($this->metodo, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) Acceso::csrfEnMutacion();
        $grupo = match ($this->ruta[0] ?? '') {
            'csrf', 'sistema', 'auth', 'catalogos' => 'acceso-catalogos',
            'practicas' => 'practicas',
            'postulaciones' => 'postulaciones',
            'admin' => 'administracion',
            'perfil' => 'perfil',
            'cv', 'reportes', 'dashboard' => 'consultas',
            default => null,
        };
        if ($grupo === null) Respuesta::error((new MensajeRepo())->obtener('error_no_encontrado', 'No se encontró la ruta solicitada.'), 404);
        $archivoMetodo = __DIR__ . '/' . $grupo . '/' . $this->metodo . '/index.php';
        if (is_file($archivoMetodo)) require $archivoMetodo;
        Respuesta::error('Método no permitido para este grupo de rutas.', 405);
    }
    /** Atiende inicio/cierre de sesión, registro y cambio de contraseña. */
    private function autenticacion(): never {
        $accion = $this->ruta[1] ?? '';
        if ($accion === 'login' && $this->metodo === 'POST') {
            $id = $this->texto('identificador', 254, true);
            $clave = $this->texto('contrasena', 200, true);
            $tipo = isset($this->datos['tipo_cuenta']) ? trim((string)$this->datos['tipo_cuenta']) : null;
            if ($tipo !== null && !in_array($tipo, ['estudiante', 'empresa'], true)) throw new DomainException('El tipo de cuenta seleccionado no es válido.');
            $u = Sesion::iniciar($id, $clave, $tipo);
            Respuesta::exito($u, (new MensajeRepo())->obtener('exito_login', 'Sesión iniciada.'), 200);
        }
        if ($accion === 'logout' && $this->metodo === 'DELETE') Sesion::cerrar();
        if ($accion === 'registro-estudiante' && $this->metodo === 'POST') $this->registrarEstudiante();
        if ($accion === 'registro-empresa' && $this->metodo === 'POST') $this->registrarEmpresa();
        if ($accion === 'cambiar-clave' && $this->metodo === 'POST') $this->cambiarClave();
        Respuesta::error((new MensajeRepo())->obtener('error_no_encontrado', 'No se encontró la ruta solicitada.'), 404);
    }
    /** Crea la cuenta, el perfil académico y el CV en una transacción. */
    private function registrarEstudiante(): never {
        $correo = $this->correo('correo');
        $telefono = $this->telefono('telefono', true);
        $clave = $this->claveValida();
        $nombres = $this->texto('nombres', 100, true);
        $apellidos = $this->texto('apellidos', 100, true);
        $usuario = $this->texto('usuario', 40, true);
        $carnet = $this->texto('carnet', 40, true);
        $universidad = $this->texto('universidad', 180, true);
        $semestre = $this->texto('semestre', 40, false);
        $carrera = $this->entero('id_carrera');
        $erroresUnicos = [];
        if ($this->usuarios->usuarioExiste($usuario)) $erroresUnicos['usuario'] = 'Este nombre de usuario ya está registrado.';
        if ($this->usuarios->correoExiste($correo)) $erroresUnicos['correo'] = 'Este correo ya está registrado.';
        if ($this->empresas->carnetExiste($carnet)) $erroresUnicos['carnet'] = 'Este carné ya está registrado.';
        if ($erroresUnicos) Respuesta::error('Corrige los campos marcados.', 409, $erroresUnicos);
        if (!preg_match('/^[A-Za-z0-9._-]{3,40}$/', $usuario) || !preg_match('/^[A-Za-z0-9._\/-]{1,40}$/', $carnet)) throw new DomainException('Revisa el formato del usuario o del carné.');
        if (!$this->catalogos->idActivo('carreras', $carrera)) throw new DomainException('Selecciona una carrera activa.');
        $archivo = (new ArchivosCv())->guardar($_FILES['cv'] ?? []);
        $rol = $this->catalogos->rolId('estudiante');
        $estado = $this->catalogos->estadoId('usuario', 'activo');
        $id = $this->usuarios->transaccion(function()use($correo, $telefono, $clave, $nombres, $apellidos, $usuario, $carnet, $universidad, $semestre, $carrera, $archivo, $rol, $estado) {
                $uid = $this->usuarios->registrar(['rol' => $rol, 'estado' => $estado, 'usuario' => $usuario, 'correo' => $correo, 'nombres' => $nombres, 'apellidos' => $apellidos, 'telefono' => $telefono, 'hash' => password_hash($clave, PASSWORD_DEFAULT), 'cambio' => 0, 'actor' => null]);
                $this->usuarios->registrarHistorial($uid, null, $estado, $uid, 'Registro de estudiante.');
                $this->empresas->crearEstudiante(['usuario' => $uid, 'carrera' => $carrera, 'carnet' => $carnet, 'semestre' => $semestre, 'universidad' => $universidad, 'cv_nombre' => $archivo['nombre'], 'cv_ruta' => $archivo['ruta'], 'cv_mime' => $archivo['mime'], 'actor' => $uid]);
                return $uid;
        });
        (new BitacoraRepo())->registrar('REGISTRO_ESTUDIANTE', 'usuarios', (string)$id, null, ['correo' => $correo], $id);
        $sesion = Sesion::iniciar($usuario, $clave);
        Respuesta::exito($sesion, (new MensajeRepo())->obtener('exito_registro_estudiante', 'Cuenta de estudiante creada.'), 201);
    }
    /** Registra la cuenta y empresa para que coordinación la revise. */
    private function registrarEmpresa(): never {
        $correo = $this->correo('correo');
        $contacto = $this->correo('correo_contacto');
        $telefono = $this->telefono('telefono', false);
        $clave = $this->claveValida();
        $usuario = $this->texto('usuario', 40, true);
        $nombres = $this->texto('nombres', 100, true);
        $apellidos = $this->texto('apellidos', 100, true);
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
                $uid = $this->usuarios->registrar(['rol' => $rol, 'estado' => $pendienteUsuario, 'usuario' => $usuario, 'correo' => $correo, 'nombres' => $nombres, 'apellidos' => $apellidos, 'telefono' => $telefono, 'hash' => password_hash($clave, PASSWORD_DEFAULT), 'cambio' => 0, 'actor' => null]);
                $this->usuarios->registrarHistorial($uid, null, $pendienteUsuario, $uid, 'Solicitud de registro de empresa.');
                $empresaId = $this->empresas->crearEmpresa(['usuario' => $uid, 'estado' => $pendienteEmpresa, 'legal' => $legal, 'comercial' => $comercial, 'fiscal' => $fiscal, 'sector' => $sector, 'descripcion' => $descripcion, 'correo' => $contacto, 'telefono' => $telefono, 'web' => $web, 'direccion' => $direccion, 'actor' => $uid]);$this->empresas->registrarHistorial($empresaId, null, $pendienteEmpresa, $uid, 'Solicitud de registro.');return $uid;
        });
        (new BitacoraRepo())->registrar('REGISTRO_EMPRESA', 'usuarios', (string)$id, null, ['estado' => 'pendiente'], null);
        Respuesta::exito(['id_usuario' => $id, 'estado_revision' => 'pendiente'], (new MensajeRepo())->obtener('exito_registro_empresa', 'Solicitud registrada. Puedes iniciar sesión para consultar el estado de revisión; las funciones de empresa se habilitarán cuando la aprueben.'), 201);
    }
    /** Actualiza la contraseña y registra la operación en bitácora. */
    private function cambiarClave(): never {
        $u = Sesion::actual(true);
        $min = max(8, (int)(new ConfiguracionRepo())->obtener('password_longitud_minima', '10'));
        $nueva = $this->texto('contrasena_nueva', 200, true);
        $confirmar = $this->texto('confirmacion', 200, true);
        if (strlen($nueva) < $min || $nueva !== $confirmar) throw new DomainException('La contraseña debe cumplir la longitud mínima y coincidir con su confirmación.');
        $this->usuarios->cambiarClave((int)$u['id_usuario'], password_hash($nueva, PASSWORD_DEFAULT));
        (new BitacoraRepo())->registrar('CAMBIO_CLAVE', 'usuarios', (string)$u['id_usuario'], null, ['debe_cambiar_password' => false], (int)$u['id_usuario']);
        Respuesta::exito(['debe_cambiar_password' => false], (new MensajeRepo())->obtener('exito_clave', 'Contraseña actualizada.'));
    }
    /** Devuelve catálogos y transiciones de estado disponibles para el rol. */
    private function catalogos(): never {
        if ($this->metodo !== 'GET') Respuesta::error('Método no permitido.', 405);
        if (($this->ruta[1] ?? '') === 'transiciones') {
            $u = Acceso::requerirRol(['estudiante', 'empresa', 'admin', 'superadmin'], true);
            $entidad = trim((string)($_GET['entidad'] ?? ''));
            $estado = trim((string)($_GET['estado_codigo'] ?? ''));
            if ($entidad === '' || $estado === '') throw new DomainException('Indica entidad y estado de origen.');
            Respuesta::exito($this->catalogos->transiciones($entidad, $estado, $this->catalogos->rolId((string)$u['rol_codigo'])));
        }
        $tipo = isset($_GET['tipo']) ? trim((string)$_GET['tipo']): null;
        Respuesta::exito($this->catalogos->lista($tipo));
    }
    /** Maneja consulta, edición, revisión, cierre y postulación a prácticas. */
    private function rutasPracticas(): never {
        $sufijos = array_slice($this->ruta, 1);
        $cierre = new ServicioCierre();
        if ($this->metodo === 'GET' && $sufijos === []) {
            $cierre->procesarAutomaticos();
            $p = $this->paginacionPracticas();
            $rows = $this->practicas->catalogo($_GET, $p['limite'] + 1, $p['offset']);
            $haySiguiente = count($rows) > $p['limite'];
            Respuesta::exito(['elementos' => array_slice($rows, 0, $p['limite']), 'pagina' => $p['pagina'], 'por_pagina' => $p['limite'], 'hay_siguiente' => $haySiguiente]);
        }
        if ($this->metodo === 'GET' && ($sufijos[0] ?? '') === 'mias') {
            $cierre->procesarAutomaticos();
            $u = Acceso::requerirRol(['empresa'], true);
            Acceso::requerirPermiso('practicas.gestionar_propias');
            Respuesta::exito($this->practicas->listarEmpresa((int)$u['id_usuario']));
        }
        if ($this->metodo === 'GET' && ($sufijos[0] ?? '') === 'admin') {
            $u = Acceso::requerirRol(['admin', 'superadmin']);
            Acceso::requerirPermiso('practicas.consultar');
            $cierre->procesarAutomaticos();
            $p = $this->paginacion();
            Respuesta::exito(['elementos' => $this->practicas->listarAdmin($p, $p['limite'], $p['offset'], isset($_GET['estado']) ? (string)$_GET['estado']: null), 'pagina' => $p['pagina'], 'por_pagina' => $p['limite']]);
        }
        if ($this->metodo === 'POST' && $sufijos === []) $this->crearPractica();
        $id = (int)($sufijos[0] ?? 0);
        if ($id < 1) Respuesta::error((new MensajeRepo())->obtener('error_no_encontrado', 'Práctica no encontrada.'), 404);
        if ($this->metodo === 'GET' && count($sufijos) === 1) {
            $cierre->procesarAutomaticos();
            $haySesion = isset($_SESSION['usuario_id']) || isset($_SESSION['user_id']);
            if (!$haySesion) {
                $row = $this->practicas->una($id);
            } else {
                $lector = Sesion::actual(true);
                $row = match ($lector['rol_codigo']) {
                    'empresa' => $this->practicas->unaEmpresa($id, (int)$lector['id_usuario']),
                    'admin', 'superadmin' => $this->practicas->unaAdmin($id),
                    default => $this->practicas->una($id),
                };
                if (in_array($lector['rol_codigo'], ['admin', 'superadmin'], true)) Acceso::requerirPermiso('practicas.consultar');
            }
            if (!$row) Respuesta::error((new MensajeRepo())->obtener('error_no_encontrado', 'Práctica no encontrada.'), 404);
            Respuesta::exito($row);
        }
        if (($sufijos[1] ?? '') === 'postulaciones' && $this->metodo === 'GET') {
            $cierre->procesarAutomaticos();
            $u = Acceso::requerirRol(['empresa']);
            Acceso::requerirPermiso('postulaciones.consultar_empresa');
            Acceso::requiereEmpresaAprobada((int)$u['id_usuario']);
            Respuesta::exito($this->postulaciones->empresa((int)$u['id_usuario'], $id));
        }
        if (($sufijos[1] ?? '') === 'postulaciones' && $this->metodo === 'POST') $this->crearPostulacion($id);
        if (($sufijos[1] ?? '') === 'revision' && $this->metodo === 'PUT') $this->revisarPractica($id);
        if (($sufijos[1] ?? '') === 'cerrar' && $this->metodo === 'POST') $this->cerrarPractica($id, $cierre);
        if ($this->metodo === 'PUT' && count($sufijos) === 1) $this->editarPractica($id);
        Respuesta::error((new MensajeRepo())->obtener('error_no_encontrado', 'No se encontró la ruta solicitada.'), 404);
    }
    /** Crea una práctica propuesta por una empresa aprobada. */
    private function crearPractica(): never {
        $u = Acceso::requerirRol(['empresa']);
        Acceso::requerirPermiso('practicas.gestionar_propias');
        Acceso::requiereEmpresaAprobada((int)$u['id_usuario']);
        (new ServicioCierre())->procesarAutomaticos();
        $d = $this->datosPractica();
        $empresa = $this->empresas->idEmpresa((int)$u['id_usuario']);
        $estado = $this->catalogos->estadoId('practica', 'pendiente_revision');
        $id = $this->usuarios->transaccion(function()use($d, $empresa, $estado, $u) {
                Conexion::contextoAuditoria((int)$u['id_usuario'], substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45));$id = $this->practicas->crear($d + ['empresa' => $empresa, 'estado' => $estado, 'actor' => (int)$u['id_usuario']]);$this->practicas->registrarHistorial($id, null, $estado, (int)$u['id_usuario'], null);return $id;
        });
        Respuesta::exito(['id_practica' => $id], (new MensajeRepo())->obtener('exito_practica', 'Práctica enviada a revisión.'), 201);
    }
    /** Guarda cambios de una práctica propia y la devuelve a revisión. */
    private function editarPractica(int $id): never {
        $u = Acceso::requerirRol(['empresa']);
        Acceso::requerirPermiso('practicas.gestionar_propias');
        Acceso::requiereEmpresaAprobada((int)$u['id_usuario']);
        (new ServicioCierre())->procesarAutomaticos();
        $vigente = $this->practicas->unaEmpresa($id, (int)$u['id_usuario']);
        if (!$vigente) throw new DomainException('La práctica no existe o no pertenece a tu empresa.');
        $d = $this->datosPractica($vigente);
        $this->usuarios->transaccion(function()use($id, $u, $d) {
            Conexion::contextoAuditoria((int)$u['id_usuario'], substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45));
            $row = $this->practicas->porIdParaActualizar($id, (int)$u['id_usuario']);
            if (!$row) throw new DomainException('La práctica no existe o no pertenece a tu empresa.');
            $destino = $this->catalogos->estadoId('practica', 'pendiente_revision');
            $requiere = $this->catalogos->puedeTransicionar('practica', (int)$row['id_estado'], $destino, $this->catalogos->rolId('empresa'));
            if ($requiere === null) throw new DomainException('La práctica no admite cambios en su estado actual.');
            if ($this->postulaciones->aceptadas($id) > (int)$d['cupo']) throw new DomainException('El cupo no puede reducirse por debajo de las postulaciones aceptadas.');
            $this->practicas->guardar($d + ['estado' => $destino, 'comentario' => null, 'actor' => (int)$u['id_usuario'], 'id' => $id]);
            $this->practicas->registrarHistorial($id, (int)$row['id_estado'], $destino, (int)$u['id_usuario'], null);
        });
        Respuesta::exito(['id_practica' => $id], (new MensajeRepo())->obtener('exito_practica', 'Cambios enviados a revisión.'));
    }
    /** Permite a superadministración aceptar o rechazar una práctica. */
    private function revisarPractica(int $id): never {
        $u = Acceso::requerirRol(['superadmin']);
        Acceso::requerirPermiso('practicas.revisar');
        $estado = $this->texto('estado_codigo', 40, true);
        $comentario = $this->texto('comentario', 2000, false);
        $destino = $this->catalogos->estadoId('practica', $estado);
        $this->usuarios->transaccion(function()use($id, $u, $destino, $comentario) {
            Conexion::contextoAuditoria((int)$u['id_usuario'], substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45));
            $row = $this->practicas->porIdParaActualizar($id);
            if (!$row) throw new DomainException('La práctica no existe.');
            $requiere = $this->catalogos->puedeTransicionar('practica', (int)$row['id_estado'], $destino, $this->catalogos->rolId('superadmin'));
            if ($requiere === null) throw new DomainException('La transición no está permitida.');
            if ($destino === $this->catalogos->estadoId('practica', 'publicada') && $row['fecha_limite_postulacion'] < date('Y-m-d')) {
                throw new DomainException('La fecha límite ya venció y la práctica no puede publicarse.');
            }
            $this->validarComentario($comentario, $requiere);
            $this->practicas->cambiarEstado($id, $destino, (int)$u['id_usuario'], $comentario);
            $this->practicas->registrarHistorial($id, (int)$row['id_estado'], $destino, (int)$u['id_usuario'], $comentario);
        });
        Respuesta::exito(['id_practica' => $id], (new MensajeRepo())->obtener('exito_guardado', 'Revisión guardada.'));
    }
    /** Ejecuta el cierre manual respetando permisos y transiciones. */
    private function cerrarPractica(int $id, ServicioCierre $cierre): never {
        $u = Acceso::requerirRol(['empresa', 'admin', 'superadmin']);
        if ($u['rol_codigo'] === 'empresa') {
            Acceso::requerirPermiso('practicas.cerrar');
            Acceso::requiereEmpresaAprobada((int)$u['id_usuario']);
        } else Acceso::requerirPermiso('practicas.cerrar');
        $cierre->cerrar($id, $u, (string)$u['rol_codigo']);
        Respuesta::exito(['id_practica' => $id], (new MensajeRepo())->obtener('exito_cierre', 'Práctica cerrada.'));
    }
    /** Controla lectura, creación, historial, retiro y revisión de postulaciones. */
    private function rutasPostulaciones(): never {
        $s = array_slice($this->ruta, 1);
        $cierre = new ServicioCierre();
        if ($this->metodo === 'GET' && $s === []) {
            $cierre->procesarAutomaticos();
            $u = Sesion::actual();
            $p = $this->paginacion();
            switch ($u['rol_codigo']) {
                case 'estudiante': Acceso::requerirPermiso('postulaciones.consultar_propias');
                $rows = $this->postulaciones->propias((int)$u['id_usuario']);
                break;
                case 'empresa': Acceso::requerirPermiso('postulaciones.consultar_empresa');
                Acceso::requiereEmpresaAprobada((int)$u['id_usuario']);
                $rows = $this->postulaciones->empresa((int)$u['id_usuario']);
                break;
                case 'admin': case 'superadmin': Acceso::requerirPermiso('postulaciones.consultar');
                $rows = $this->postulaciones->admin($p['limite'], $p['offset'], isset($_GET['estado']) ? (string)$_GET['estado']: null);
                break;
                default: Respuesta::error('No tienes permiso para consultar postulaciones.', 403);
            }
            Respuesta::exito($rows);
        }
        if ($this->metodo === 'POST' && $s === []) $this->crearPostulacion($this->entero('id_practica'));
        $id = (int)($s[0] ?? 0);
        if ($id < 1) Respuesta::error((new MensajeRepo())->obtener('error_no_encontrado', 'Postulación no encontrada.'), 404);
        if ($this->metodo === 'GET' && ($s[1] ?? '') === 'historial') {
            $u = Sesion::actual();
            $cierre->procesarAutomaticos();
            $p = $this->postulaciones->porId($id);
            $this->autorizarLecturaPostulacion($u, $p);
            Respuesta::exito($this->postulaciones->historial($id));
        }
        if ($this->metodo === 'GET' && count($s) === 1) {
            $u = Sesion::actual();
            $cierre->procesarAutomaticos();
            $p = $this->postulaciones->porId($id);
            $this->autorizarLecturaPostulacion($u, $p);
            Respuesta::exito($p);
        }
        if ($this->metodo === 'PUT' && count($s) === 1) $this->cambiarEstadoPostulacion($id, $cierre);
        if ($this->metodo === 'DELETE' && count($s) === 1) $this->retirarPostulacion($id);
        Respuesta::error((new MensajeRepo())->obtener('error_no_encontrado', 'No se encontró la ruta solicitada.'), 404);
    }
    /** Valida elegibilidad y registra la postulación con copia del CV. */
    private function crearPostulacion(int $practicaId): never {
        $u = Acceso::requerirRol(['estudiante']);
        Acceso::requerirPermiso('postulaciones.crear');
        (new ServicioCierre())->procesarAutomaticos();
        $perfil = $this->empresas->estudiantePorUsuario((int)$u['id_usuario']);
        if (!$perfil || empty($perfil['cv_ruta']) || empty($perfil['cv_nombre']) || ($perfil['cv_mime'] ?? '') !== 'application/pdf' || !empty($perfil['cv_eliminado']) || !ArchivosCv::resolverRuta((string)$perfil['cv_ruta']) || !$this->correoValido((string)($perfil['correo'] ?? '')) || !preg_match('/^[0-9]{8}$/', (string)($perfil['telefono'] ?? ''))) throw new DomainException((new MensajeRepo())->obtener('error_postulacion_requisitos', 'Completa y valida correo, teléfono y CV antes de postularte.'));
        if ($practicaId < 1) throw new DomainException('Selecciona una práctica válida.');
        $comentario = $this->texto('carta_presentacion', 3000, false);
        $estudiante = (int)$perfil['id_estudiante'];
        $id = $this->usuarios->transaccion(function()use($practicaId, $u, $estudiante, $comentario, $perfil) {
                Conexion::contextoAuditoria((int)$u['id_usuario'], substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45));$pr = $this->practicas->porIdParaActualizar($practicaId);
                if (!$pr || $pr['estado_codigo'] !== 'publicada' || $pr['fecha_limite_postulacion'] < date('Y-m-d')) throw new DomainException((new MensajeRepo())->obtener('error_practica_no_vigente', 'La práctica ya no recibe postulaciones.'));
                if ($this->postulaciones->aceptadas($practicaId) >= (int)$pr['cupo_maximo']) throw new DomainException((new MensajeRepo())->obtener('error_cupo_lleno', 'La práctica ya alcanzó su cupo.'));
                $existente = $this->postulaciones->existente($estudiante, $practicaId);$pendiente = $this->catalogos->estadoId('postulacion', 'pendiente');$estudianteRol = $this->catalogos->rolId('estudiante');
                if ($existente) {
                    if ($existente['estado_codigo'] !== 'retirada') throw new DomainException((new MensajeRepo())->obtener('error_postulacion_duplicada', 'Ya existe una postulación activa para esta práctica.'));$anterior = $this->catalogos->estadoId('postulacion', 'retirada');$req = $this->catalogos->puedeTransicionar('postulacion', $anterior, $pendiente, $estudianteRol);if ($req === null) throw new DomainException('La reactivación no está permitida.');$this->postulaciones->reactivar((int)$existente['id_postulacion'], $pendiente, (int)$u['id_usuario'], ['cv_ruta' => $perfil['cv_ruta'], 'cv_nombre' => $perfil['cv_nombre'], 'cv_mime' => $perfil['cv_mime']]);$this->postulaciones->registrarHistorial('postulacion', (int)$existente['id_postulacion'], $anterior, $pendiente, (int)$u['id_usuario'], null);return (int)$existente['id_postulacion'];
            }
                $id = $this->postulaciones->crear(['estudiante' => $estudiante, 'practica' => $practicaId, 'estado' => $pendiente, 'carta' => $comentario, 'cv_ruta' => $perfil['cv_ruta'], 'cv_nombre' => $perfil['cv_nombre'], 'cv_mime' => $perfil['cv_mime'], 'actor' => (int)$u['id_usuario']]);$this->postulaciones->registrarHistorial('postulacion', $id, null, $pendiente, (int)$u['id_usuario'], null);return $id;
        });
        Respuesta::exito(['id_postulacion' => $id], (new MensajeRepo())->obtener('exito_postulacion', 'Postulación registrada.'), 201);
    }
    /** Aplica una transición permitida a una postulación. */
    private function cambiarEstadoPostulacion(int $id, ServicioCierre $cierre): never {
        $u = Acceso::requerirRol(['empresa', 'admin', 'superadmin']);
        Acceso::requerirPermiso('postulaciones.cambiar_estado');
        $esEmpresa = $u['rol_codigo'] === 'empresa';
        if ($esEmpresa) Acceso::requiereEmpresaAprobada((int)$u['id_usuario']);
        $cierre->procesarAutomaticos();
        $target = $this->texto('estado_codigo', 40, true);
        $comentario = $this->texto('comentario', 3000, false);
        $destino = $this->catalogos->estadoId('postulacion', $target);
        $roleId = $this->catalogos->rolId((string)$u['rol_codigo']);
        $this->usuarios->transaccion(function()use($id, $u, $destino, $comentario, $roleId, $cierre, $esEmpresa, $target) {
            Conexion::contextoAuditoria((int)$u['id_usuario'], substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45));
            $initial = $this->postulaciones->porId($id);
            if (!$initial) throw new DomainException('La postulación no existe.');
            $practice = $this->practicas->porIdParaActualizar((int)$initial['id_practica'], $esEmpresa ? (int)$u['id_usuario'] : null);
            if (!$practice || ($esEmpresa && $practice['estado_codigo'] !== 'publicada')) {
                throw new DomainException($esEmpresa ? 'La postulación no pertenece a una práctica abierta de tu empresa.' : 'La práctica de esta postulación no está disponible.');
            }
            $row = $this->postulaciones->porIdBloqueo($id);
            if (!$row) throw new DomainException('La postulación no existe.');
            $requires = $this->catalogos->puedeTransicionar('postulacion', (int)$row['id_estado'], $destino, $roleId);
            if ($requires === null) throw new DomainException('La transición de postulación no está permitida.');
            $this->validarComentario($comentario, $requires);
            if ($target === 'aceptada' && $this->postulaciones->aceptadas((int)$row['id_practica']) >= (int)$practice['cupo_maximo']) {
                throw new DomainException((new MensajeRepo())->obtener('error_cupo_lleno', 'La práctica ya alcanzó su cupo.'));
            }
            $this->postulaciones->cambiarEstado($id, $destino, (int)$u['id_usuario'], $comentario);
            $this->postulaciones->registrarHistorial('postulacion', $id, (int)$row['id_estado'], $destino, (int)$u['id_usuario'], $comentario);
            if ($target === 'aceptada' && $this->postulaciones->aceptadas((int)$row['id_practica']) >= (int)$practice['cupo_maximo']) {
                $cierre->cerrarEnTransaccion((int)$row['id_practica'], $u, 'sistema', true);
            }
        });
        Respuesta::exito(['id_postulacion' => $id], (new MensajeRepo())->obtener('exito_guardado', 'Estado de postulación actualizado.'));
    }
    /** Retira lógicamente una postulación del estudiante. */
    private function retirarPostulacion(int $id): never {
        $u = Acceso::requerirRol(['estudiante']);
        Acceso::requerirPermiso('postulaciones.retirar_propia');
        (new ServicioCierre())->procesarAutomaticos();
        $this->usuarios->transaccion(function()use($id, $u) {
                Conexion::contextoAuditoria((int)$u['id_usuario'], substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45));$pre = $this->postulaciones->porId($id);if (!$pre || (int)$pre['id_usuario_estudiante'] !== (int)$u['id_usuario']) throw new DomainException('La postulación no existe o no te pertenece.');
                $practice = $this->practicas->porIdParaActualizar((int)$pre['id_practica']);$row = $this->postulaciones->porIdBloqueo($id);$destino = $this->catalogos->estadoId('postulacion', 'retirada');$role = $this->catalogos->rolId('estudiante');$requires = $this->catalogos->puedeTransicionar('postulacion', (int)$row['id_estado'], $destino, $role);if ($requires === null) throw new DomainException('La postulación no se puede retirar en su estado actual.');
                $this->postulaciones->cambiarEstado($id, $destino, (int)$u['id_usuario'], null);$this->postulaciones->registrarHistorial('postulacion', $id, (int)$row['id_estado'], $destino, (int)$u['id_usuario'], null);
                $cierrePorCupo = (new ConfiguracionRepo())->obtener('practica_cierre_cupo_motivo', 'Cierre automático por cupo completo.');
                if ($practice && $practice['estado_codigo'] === 'cerrada' && $practice['observaciones_coordinacion'] === $cierrePorCupo && $practice['fecha_limite_postulacion'] >= date('Y-m-d')) {
                    $published = $this->catalogos->estadoId('practica', 'publicada');$system = $this->catalogos->rolId('sistema');$can = $this->catalogos->puedeTransicionar('practica', (int)$practice['id_estado'], $published, $system);if ($can !== null && (new PostulacionRepo())->aceptadas((int)$practice['id_practica']) < (int)$practice['cupo_maximo']) {
                        $this->practicas->cambiarEstado((int)$practice['id_practica'], $published, null, null);$this->practicas->registrarHistorial((int)$practice['id_practica'], (int)$practice['id_estado'], $published, (int)$u['id_usuario'], 'Cupo liberado por retiro de una postulación aceptada.');
                }
            }
        });
        Respuesta::exito(['id_postulacion' => $id], (new MensajeRepo())->obtener('exito_retiro', 'Postulación retirada.'));
    }
    /** Limita la consulta al dueño de la postulación o a administración. */
    private function autorizarLecturaPostulacion(array $u, ?array $p): void {
        if (!$p) Respuesta::error((new MensajeRepo())->obtener('error_no_encontrado', 'Postulación no encontrada.'), 404);
        match ($u['rol_codigo']) {
            'estudiante' => Acceso::requerirPermiso('postulaciones.consultar_propias'),
            'empresa' => Acceso::requerirPermiso('postulaciones.consultar_empresa'),
            'admin', 'superadmin' => Acceso::requerirPermiso('postulaciones.consultar'),
            default => Respuesta::error((new MensajeRepo())->obtener('error_sin_permiso', 'No tienes permiso para consultar esta postulación.'), 403),
        };
        if ($u['rol_codigo'] === 'empresa') Acceso::requiereEmpresaAprobada((int)$u['id_usuario']);
        $ok = match($u['rol_codigo']) {
            'estudiante' => (int)$p['id_usuario_estudiante'] === (int)$u['id_usuario'], 'empresa' => (int)$p['id_usuario_empresa'] === (int)$u['id_usuario'], 'admin', 'superadmin' => true, default => false
        };
        if (!$ok) Respuesta::error((new MensajeRepo())->obtener('error_sin_permiso', 'No tienes permiso para consultar esta postulación.'), 403);
    }
    /** Dirige las operaciones de administración a su módulo especializado. */
    private function rutasAdmin(): never {
        $s = array_slice($this->ruta, 1);
        $u = Acceso::requerirRol(['admin', 'superadmin']);
        if (($s[0] ?? '') === 'usuarios') {
            $this->adminUsuarios($u, array_slice($s, 1));
        }
        if (($s[0] ?? '') === 'empresas') {
            $this->adminEmpresas($u, array_slice($s, 1));
        }
        if (($s[0] ?? '') === 'catalogos') {
            $this->adminCatalogos($u, array_slice($s, 1));
        }
        if (($s[0] ?? '') === 'configuracion') {
            $this->adminConfiguracion($u, array_slice($s, 1));
        }
        if (($s[0] ?? '') === 'bitacora' && $this->metodo === 'GET') {
            $this->requerirGestion($u, 'bitacora.consultar');
            $p = $this->paginacion();
            $f = [];
            foreach (['tabla', 'accion', 'usuario_id', 'desde', 'hasta'] as $k) if (isset($_GET[$k])) $f[$k] = (string)$_GET[$k];
            Respuesta::exito(['elementos' => (new BitacoraRepo())->listar($f, $p['limite'], $p['offset']), 'pagina' => $p['pagina'], 'por_pagina' => $p['limite']]);
        }
        if (($s[0] ?? '') === 'reportes' && $this->metodo === 'GET') {
            $this->requerirGestion($u, 'reportes.consultar');
            Respuesta::exito($this->datosReporte());
        }
        Respuesta::error((new MensajeRepo())->obtener('error_no_encontrado', 'No se encontró la ruta solicitada.'), 404);
    }
    /** Lista, crea, actualiza, elimina, restaura y gestiona cuentas. */
    private function adminUsuarios(array $u, array $s): never {
        $this->requerirGestion($u, 'usuarios.gestionar');
        $id = (int)($s[0] ?? 0);
        $sub = $s[1] ?? '';
        if ($s === [] && $this->metodo === 'GET') {
            $p = $this->paginacion();
            $excluirSuperadministradores = $u['rol_codigo'] !== 'superadmin';
            $rows = ($_GET['eliminados'] ?? '') === '1' ? $this->usuarios->listarEliminados($p['limite'], $p['offset'], $excluirSuperadministradores): $this->usuarios->listarPagina($p['limite'], $p['offset'], isset($_GET['q']) ? (string)$_GET['q']: null, $excluirSuperadministradores);
            Respuesta::exito(['elementos' => $rows, 'pagina' => $p['pagina'], 'por_pagina' => $p['limite']]);
        }
        if ($s === [] && $this->metodo === 'POST') $this->crearCuentaGestionada($u);
        if ($sub === '' && $this->metodo === 'GET') {
            $target = $this->usuarios->detalleVisible($id);
            if (!$target) Respuesta::error((new MensajeRepo())->obtener('error_no_encontrado', 'No se encontró la cuenta.'), 404);
            $this->protegerSuperadmin($u, $target);
            Respuesta::exito($target);
        }
        if ($id < 1) Respuesta::error((new MensajeRepo())->obtener('error_no_encontrado', 'Identificador de usuario inválido.'), 404);
        if ($sub === 'restablecer-clave' && $this->metodo === 'POST') {
            $actor = Acceso::requerirRol(['superadmin']);
            Acceso::requerirPermiso('usuarios.password_reset');
            $target = $this->usuarios->detalleAdmin($id);
            if (!$target || $target['eliminado']) Respuesta::error((new MensajeRepo())->obtener('error_no_encontrado', 'No se encontró una cuenta activa.'), 404);
            $longitudMinima = min(200, max(8, (int)(new ConfiguracionRepo())->obtener('password_longitud_minima', '10')));
            $aleatorio = rtrim(strtr(base64_encode(random_bytes($longitudMinima)), '+/', '-_'), '=');
            $temporal = substr($aleatorio, 0, $longitudMinima - 3) . 'A9!';
            $this->usuarios->transaccion(function() use ($id, $temporal, $actor) {
                $this->usuarios->restablecerClave($id, password_hash($temporal, PASSWORD_DEFAULT), (int)$actor['id_usuario']);
                $this->usuarios->revocarSesionesUsuario($id, (int)$actor['id_usuario']);
            });
            (new BitacoraRepo())->registrar('CLAVE_RESTABLECIDA', 'usuarios', (string)$id, null, ['debe_cambiar_password' => true], (int)$actor['id_usuario']);
            Respuesta::exito(['contrasena_temporal' => $temporal, 'debe_cambiar_password' => true], 'Contraseña temporal generada. Se muestra una sola vez.');
        }
        if ($sub === 'restaurar' && $this->metodo === 'POST') {
            $target = $this->usuarios->detalleAdmin($id);
            $this->protegerSuperadmin($u, $target);
            if (!$target || (int)$target['eliminado'] !== 1) throw new DomainException('La cuenta no está eliminada.');
            $this->usuarios->transaccion(function()use($u, $target, $id) {
                    $role = $this->rolCodigoPorId((int)$target['id_rol']);if ($role === 'empresa') $this->empresas->restaurarEmpresa($id, (int)$u['id_usuario']);if ($role === 'estudiante') $this->empresas->restaurarEstudiante($id, (int)$u['id_usuario']);$this->usuarios->restaurar($id, (int)$u['id_usuario']);$activo = $this->catalogos->estadoId('usuario', 'activo');$this->empresas->actualizarEstadoCuenta($id, $activo, (int)$u['id_usuario']);$this->usuarios->registrarHistorial($id, (int)$target['id_estado'], $activo, (int)$u['id_usuario'], 'Cuenta restaurada.');
            });
            (new BitacoraRepo())->registrar('RESTAURACION', 'usuarios', (string)$id, null, ['eliminado' => false], (int)$u['id_usuario']);
            Respuesta::exito(['id_usuario' => $id], 'Cuenta restaurada.');
        }
        if ($sub === 'estado' && $this->metodo === 'PUT') {
            $target = $this->usuarios->detalleAdmin($id);
            $this->protegerSuperadmin($u, $target);
            if (!$target || !empty($target['eliminado'])) throw new DomainException('No se encontró una cuenta activa.');
            $estadoCodigo = $this->texto('estado_codigo', 40, true);
            if ($estadoCodigo === 'inactivo') {
                $this->eliminarCuentaGestionada($u, $target, false);
                Respuesta::exito(['id_usuario' => $id, 'estado_codigo' => $estadoCodigo], 'Cuenta desactivada y relaciones activas canceladas.');
            }
            $destino = $this->catalogos->estadoId('usuario', $estadoCodigo);
            $actual = (int)$target['id_estado'];
            $req = $this->catalogos->puedeTransicionar('usuario', $actual, $destino, $this->catalogos->rolId((string)$u['rol_codigo']));
            if ($req === null) throw new DomainException('La transición de cuenta no está permitida.');
            $comentario = $this->texto('comentario', 500, false);
            $this->validarComentario($comentario, $req);
            $this->usuarios->transaccion(function()use($id, $destino, $actual, $u, $comentario) {
                    $this->usuarios->cambiarEstado($id, $destino, (int)$u['id_usuario']);$this->usuarios->registrarHistorial($id, $actual, $destino, (int)$u['id_usuario'], $comentario);
            });
            Respuesta::exito(['id_usuario' => $id, 'estado_codigo' => $estadoCodigo], 'Estado de la cuenta actualizado.');
        }
        if ($this->metodo === 'PUT' && $sub === 'rol') {
            $this->asignarRolGestionado($u, $id);
        }
        if ($this->metodo === 'PUT' && $sub === '') {
            $target = $this->usuarios->detalleAdmin($id);
            $this->protegerSuperadmin($u, $target);
            if (!$target || !empty($target['eliminado'])) throw new DomainException('No se encontró una cuenta activa.');
            $correo = $this->correo('correo');
            $nombres = $this->texto('nombres', 100, true);
            $apellidos = $this->texto('apellidos', 100, true);
            $telefono = $this->telefono('telefono', $this->rolCodigoPorId((int)$target['id_rol']) === 'estudiante');
            $this->usuarios->actualizarDatos($id, ['correo' => $correo, 'nombres' => $nombres, 'apellidos' => $apellidos, 'telefono' => $telefono, 'actor' => (int)$u['id_usuario']]);
            Respuesta::exito(['id_usuario' => $id], (new MensajeRepo())->obtener('exito_guardado', 'Cuenta actualizada.'));
        }
        if ($this->metodo === 'DELETE' && $sub === '') {
            $target = $this->usuarios->detalleAdmin($id);
            $this->protegerSuperadmin($u, $target);
            if (!$target || $target['eliminado']) throw new DomainException('La cuenta no existe o ya está eliminada.');
            $this->eliminarCuentaGestionada($u, $target);
            Respuesta::exito(['id_usuario' => $id], 'Cuenta desactivada y conservada en el historial.');
        }
        Respuesta::error((new MensajeRepo())->obtener('error_no_encontrado', 'No se encontró la ruta solicitada.'), 404);
    }
    /** Crea cuentas de estudiante, empresa o administración. */
    private function crearCuentaGestionada(array $u): never {
        $this->requerirGestion($u, 'usuarios.gestionar');
        $rolCodigo = $this->texto('rol_codigo', 40, true);
        $roles = $u['rol_codigo'] === 'superadmin' ? ['superadmin', 'admin', 'empresa', 'estudiante']: ['admin', 'empresa', 'estudiante'];
        if (!in_array($rolCodigo, $roles, true)) Respuesta::error((new MensajeRepo())->obtener('error_sin_permiso', 'No puedes crear ese tipo de cuenta.'), 403);
        $correo = $this->correo('correo');
        $telefono = $this->telefono('telefono', in_array($rolCodigo, ['estudiante', 'empresa'], true));
        $usuario = $this->texto('usuario', 40, true);
        $nombres = $this->texto('nombres', 100, true);
        $apellidos = $this->texto('apellidos', 100, true);
        $clave = $this->claveValida();
        $rol = $this->catalogos->rolId($rolCodigo);
        $estadoUsuario = $this->catalogos->estadoId('usuario', 'activo');
        $cv = null;
        if ($rolCodigo === 'estudiante') {
            $carrera = $this->entero('id_carrera');
            if (!$this->catalogos->idActivo('carreras', $carrera)) throw new DomainException('Selecciona una carrera activa.');
            $carnet = $this->texto('carnet', 40, true);
            $universidad = $this->texto('universidad', 180, true);
            $semestre = $this->texto('semestre', 40, false);
            $cv = (new ArchivosCv())->guardar($_FILES['cv'] ?? []);
        }
        if ($rolCodigo === 'empresa') {
            $legal = $this->texto('nombre_legal', 180, true);
            $contacto = $this->correo('correo_contacto');
            $comercial = $this->texto('nombre_comercial', 180, false);
            $fiscal = $this->texto('identificacion_fiscal', 50, false);
            $sector = $this->texto('sector', 100, false);
            $descripcion = $this->texto('descripcion', 5000, false);
            $web = $this->texto('sitio_web', 255, false);
            if ($web !== null && (!filter_var($web, FILTER_VALIDATE_URL) || !in_array(strtolower((string)parse_url($web, PHP_URL_SCHEME)), ['http', 'https'], true))) throw new DomainException('Ingresa una dirección web segura válida o deja el campo vacío.');
            $direccion = $this->texto('direccion', 255, false);
        }
        $id = $this->usuarios->transaccion(function()use($u, $rolCodigo, $rol, $estadoUsuario, $correo, $telefono, $usuario, $nombres, $apellidos, $clave, $cv) {
                $id = $this->usuarios->registrar(['rol' => $rol, 'estado' => $estadoUsuario, 'usuario' => $usuario, 'correo' => $correo, 'nombres' => $nombres, 'apellidos' => $apellidos, 'telefono' => $telefono, 'hash' => password_hash($clave, PASSWORD_DEFAULT), 'cambio' => 1, 'actor' => (int)$u['id_usuario']]);$this->usuarios->registrarHistorial($id, null, $estadoUsuario, (int)$u['id_usuario'], 'Cuenta creada por coordinación.');
                if ($rolCodigo === 'estudiante') $this->empresas->crearEstudiante(['usuario' => $id, 'carrera' => $this->entero('id_carrera'), 'carnet' => $this->texto('carnet', 40, true), 'semestre' => $this->texto('semestre', 40, false), 'universidad' => $this->texto('universidad', 180, true), 'cv_nombre' => $cv['nombre'], 'cv_ruta' => $cv['ruta'], 'cv_mime' => $cv['mime'], 'actor' => (int)$u['id_usuario']]);
                if ($rolCodigo === 'empresa') {
                    $estadoEmpresa = $this->catalogos->estadoId('empresa', 'pendiente');$empresaId = $this->empresas->crearEmpresa(['usuario' => $id, 'estado' => $estadoEmpresa, 'legal' => $this->texto('nombre_legal', 180, true), 'comercial' => $this->texto('nombre_comercial', 180, false), 'fiscal' => $this->texto('identificacion_fiscal', 50, false), 'sector' => $this->texto('sector', 100, false), 'descripcion' => $this->texto('descripcion', 5000, false), 'correo' => $this->correo('correo_contacto'), 'telefono' => $telefono, 'web' => $this->texto('sitio_web', 255, false), 'direccion' => $this->texto('direccion', 255, false), 'actor' => (int)$u['id_usuario']]);$this->empresas->registrarHistorial($empresaId, null, $estadoEmpresa, (int)$u['id_usuario'], 'Cuenta creada por coordinación.');
            }
                return $id;
        });
        (new BitacoraRepo())->registrar('CREACION_CUENTA', 'usuarios', (string)$id, null, ['rol' => $rolCodigo], (int)$u['id_usuario']);
        Respuesta::exito(['id_usuario' => $id, 'debe_cambiar_password' => true], 'Cuenta creada. La persona deberá cambiar su contraseña temporal.', 201);
    }
    /** Cambia un rol y crea el perfil asociado cuando hace falta. */
    private function asignarRolGestionado(array $u, int $id): never {
        $target = $this->usuarios->detalleAdmin($id);
        $this->protegerSuperadmin($u, $target);
        $rolCodigo = $this->texto('rol_codigo', 40, true);
        $permitidos = $u['rol_codigo'] === 'superadmin' ? ['superadmin', 'admin', 'empresa', 'estudiante']: ['admin', 'empresa', 'estudiante'];
        if (!in_array($rolCodigo, $permitidos, true)) Respuesta::error((new MensajeRepo())->obtener('error_sin_permiso', 'No puedes asignar ese perfil.'), 403);
        $actual = (int)$target['id_rol'];
        $nuevo = $this->catalogos->rolId($rolCodigo);
        if (!$nuevo) throw new DomainException('El perfil no está configurado.');
        if ($actual !== $nuevo) throw new DomainException('Para preservar los datos del perfil, no se permite cambiar de rol a una cuenta existente.');
        Respuesta::exito(['id_usuario' => $id, 'rol_codigo' => $rolCodigo], 'El perfil ya coincide con la cuenta.');
    }
    /** Evita cambios que puedan quitar el último acceso de superadministración. */
    private function protegerSuperadmin(array $actor, ?array $target): void {
        if (!$target) throw new DomainException('No se encontró la cuenta.');
        $role = (int)$target['id_rol'];
        $super = $this->catalogos->rolId('superadmin');
        if ((int)$role === (int)$super && $actor['rol_codigo'] !== 'superadmin') Respuesta::error((new MensajeRepo())->obtener('error_sin_permiso', 'Solo superadministración puede administrar otra cuenta superadministradora.'), 403);
    }
    /** Desactiva relaciones y conserva historial al eliminar una cuenta. */
    private function eliminarCuentaGestionada(array $actor, array $target, bool $eliminarLogico = true): void {
        $id = (int)$target['id_usuario'];
        $roleCode = $this->rolCodigoPorId((int)$target['id_rol']);
        $actorRole = $actor['rol_codigo'];
        $this->usuarios->transaccion(function()use($actor, $target, $id, $roleCode, $actorRole, $eliminarLogico) {
                Conexion::contextoAuditoria((int)$actor['id_usuario'], substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45));
                if ($roleCode === 'empresa') {
                    foreach ($this->postulaciones->activasEmpresa($id) as $p) $this->cancelarPostulacionGestionada($p, $actor, $actorRole);
                    foreach ($this->practicas->idsEmpresa($id) as $p) {
                        if (($p['estado_codigo'] ?? '') === 'cancelada') continue;
                        $cancel = $this->catalogos->estadoId('practica', 'cancelada');
                        $can = $this->catalogos->puedeTransicionar('practica', (int)$p['id_estado'], $cancel, $this->catalogos->rolId($actorRole));
                        if ($can === null) throw new DomainException('Falta configurar la cancelación de una práctica de la empresa. No se desactivó la cuenta.');
                        $comentario = 'Empresa desactivada.';
                        $this->practicas->cambiarEstado((int)$p['id_practica'], $cancel, (int)$actor['id_usuario'], $comentario);
                        $this->practicas->registrarHistorial((int)$p['id_practica'], (int)$p['id_estado'], $cancel, (int)$actor['id_usuario'], $comentario);
                    }
                    if ($eliminarLogico) $this->empresas->eliminarPerfil($id, (int)$actor['id_usuario']);
                }
                if ($roleCode === 'estudiante') {
                    foreach ($this->postulaciones->activasEstudiante($id) as $p) $this->cancelarPostulacionGestionada($p, $actor, $actorRole);
                    if ($eliminarLogico) $this->empresas->eliminarEstudiante($id, (int)$actor['id_usuario']);
                }
                $inactivo = $this->catalogos->estadoId('usuario', 'inactivo');
                if ((int)$target['id_estado'] !== $inactivo) {
                    $req = $this->catalogos->puedeTransicionar('usuario', (int)$target['id_estado'], $inactivo, $this->catalogos->rolId($actorRole));
                    if ($req === null) throw new DomainException('La transición de desactivación de cuenta no está configurada.');
                    $this->usuarios->cambiarEstado($id, $inactivo, (int)$actor['id_usuario']);
                    $this->usuarios->registrarHistorial($id, (int)$target['id_estado'], $inactivo, (int)$actor['id_usuario'], 'Cuenta desactivada.');
                }
                $this->usuarios->revocarSesionesUsuario($id, (int)$actor['id_usuario']);
                if ($eliminarLogico) $this->usuarios->eliminar($id, (int)$actor['id_usuario']);
        });
    }
    /** Cancela postulaciones relacionadas con una cuenta desactivada. */
    private function cancelarPostulacionGestionada(array $p, array $actor, string $role): void {
        $destino = $this->catalogos->estadoId('postulacion', 'cancelada');
        $req = $this->catalogos->puedeTransicionar('postulacion', (int)$p['id_estado'], $destino, $this->catalogos->rolId($role));
        if ($req === null) throw new DomainException('Falta configurar la cancelación de una postulación activa. No se desactivó la cuenta.');
        $comentario = 'Cuenta relacionada desactivada.';
        $this->postulaciones->cambiarEstado((int)$p['id_postulacion'], $destino, (int)$actor['id_usuario'], $comentario);
        $this->postulaciones->registrarHistorial('postulacion', (int)$p['id_postulacion'], (int)$p['id_estado'], $destino, (int)$actor['id_usuario'], $comentario);
    }
    private function rolCodigoPorId(int $id): string {
        foreach ($this->catalogos->lista('rol') as $r) if ((int)$r['id'] === $id) return (string)$r['codigo'];
        return '';
    }
    /** Permite revisar y cambiar el estado de registro de una empresa. */
    private function adminEmpresas(array $u, array $s): never {
        Acceso::requerirRol(['admin', 'superadmin']);
        $this->requerirGestion($u, 'empresas.revisar');
        $id = (int)($s[0] ?? 0);
        if ($s === [] && $this->metodo === 'GET') Respuesta::exito($this->practicas->empresasPendientes());
        if ($id > 0 && ($s[1] ?? '') === 'revision' && $this->metodo === 'PUT') {
            $empresa = $this->empresas->porId($id);
            if (!$empresa) Respuesta::error((new MensajeRepo())->obtener('error_no_encontrado', 'No se encontró la empresa.'), 404);
            $estado = $this->texto('estado_codigo', 40, true);
            $destino = $this->catalogos->estadoId('empresa', $estado);
            $comentario = $this->texto('comentario', 2000, false);
            $this->usuarios->transaccion(function()use($u, $empresa, $destino, $comentario, $estado) {
                    $actual = (int)$empresa['id_estado_revision'];$requiere = $this->catalogos->puedeTransicionar('empresa', $actual, $destino, $this->catalogos->rolId((string)$u['rol_codigo']));if ($requiere === null) throw new DomainException('La transición de revisión no está permitida.');$this->validarComentario($comentario, $requiere);$this->empresas->revisar((int)$empresa['id_empresa'], $destino, (int)$u['id_usuario'], $comentario);$this->empresas->registrarHistorial((int)$empresa['id_empresa'], $actual, $destino, (int)$u['id_usuario'], $comentario);
                    if ($estado === 'aprobada') {
                        $cuenta = $this->usuarios->detalleAdmin((int)$empresa['id_usuario']);
                        if (!$cuenta) throw new DomainException('No se encontró la cuenta asociada a la empresa.');
                        $pendienteUsuario = $this->catalogos->estadoId('usuario', 'pendiente');
                        if ((int)$cuenta['id_estado'] === $pendienteUsuario) {
                            $activoUsuario = $this->catalogos->estadoId('usuario', 'activo');
                            $this->usuarios->cambiarEstado((int)$empresa['id_usuario'], $activoUsuario, (int)$u['id_usuario']);
                            $this->usuarios->registrarHistorial((int)$empresa['id_usuario'], $pendienteUsuario, $activoUsuario, (int)$u['id_usuario'], 'Empresa aprobada; cuenta activada.');
                        }
                    }
            });
            Respuesta::exito(['id_empresa' => $id], (new MensajeRepo())->obtener('exito_guardado', 'Revisión empresarial guardada.'));
        }
        Respuesta::error((new MensajeRepo())->obtener('error_no_encontrado', 'No se encontró la ruta solicitada.'), 404);
    }
    /** Gestiona catálogos editables y protege los catálogos estructurales. */
    private function adminCatalogos(array $u, array $s): never {
        $this->requerirGestion($u, 'catalogos.gestionar');
        $catalogo = (string)($s[0] ?? '');
        $id = (int)($s[1] ?? 0);
        $accion = $s[2] ?? '';
        if (in_array($catalogo, ['roles', 'permisos', 'tipos_entidad'], true)) throw new DomainException('Este catálogo es estructural y no se puede modificar desde esta operación.');
        if (in_array($catalogo, ['estados', 'transiciones'], true) && $u['rol_codigo'] !== 'superadmin') Respuesta::error('Solo superadministración puede modificar estados y transiciones.', 403);
        if ($this->metodo === 'GET' && $catalogo !== '') {
            $accion = ($_GET['eliminados'] ?? '') === '1' ? 'listar_eliminados': 'listar';
            Respuesta::exito($this->catalogos->administrar($catalogo, $accion, [], null, (int)$u['id_usuario']));
        }
        if ($this->metodo === 'POST' && $catalogo !== '') {
            $idNuevo = $this->catalogos->administrar($catalogo, 'crear', $this->datos, (int)$u['id_usuario'], (int)$u['id_usuario']);
            Respuesta::exito(['id' => $idNuevo], 'Catálogo creado.', 201);
        }
        if ($this->metodo === 'PUT' && $id > 0 && $catalogo !== '') {
            $this->catalogos->administrar($catalogo, 'actualizar', $this->datos, $id, (int)$u['id_usuario']);
            Respuesta::exito(['id' => $id], 'Catálogo actualizado.');
        }
        if (in_array($this->metodo, ['DELETE', 'POST'], true) && $id > 0 && $catalogo !== '' && in_array($accion, ['eliminar', 'restaurar'], true)) {
            $n = $this->catalogos->administrar($catalogo, $accion, [], $id, (int)$u['id_usuario']);
            Respuesta::exito(['id' => $id, 'actualizados' => $n], 'Operación completada.');
        }
        Respuesta::error((new MensajeRepo())->obtener('error_no_encontrado', 'No se encontró la ruta solicitada.'), 404);
    }
    /** Consulta o modifica los parámetros configurables del sistema. */
    private function adminConfiguracion(array $u, array $s): never {
        $this->requerirGestion($u, 'configuracion.gestionar');
        $repo = new ConfiguracionRepo();
        if ($this->metodo === 'GET' && $s === []) Respuesta::exito($repo->listar());
        if ($this->metodo === 'PUT' && isset($s[0])) {
            $clave = $s[0];
            $valor = $this->texto('valor', 5000, true);
            $repo->guardar($clave, $valor, (int)$u['id_usuario']);
            Respuesta::exito(['clave' => $clave, 'valor' => $valor], (new MensajeRepo())->obtener('exito_guardado', 'Configuración actualizada.'));
        }
        Respuesta::error((new MensajeRepo())->obtener('error_no_encontrado', 'No se encontró la ruta solicitada.'), 404);
    }
    /** Devuelve y actualiza el perfil según el tipo de cuenta. */
    private function rutasPerfil(): never {
        $u = Acceso::requerirRol(['estudiante', 'empresa', 'admin', 'superadmin'], true);
        if (in_array($u['rol_codigo'], ['admin', 'superadmin'], true)) {
            if ($this->metodo === 'GET' && count($this->ruta) === 1) {
                Respuesta::exito($this->usuarios->porId((int)$u['id_usuario']));
            }
            if ($this->metodo === 'PUT' && count($this->ruta) === 1) {
                $datos = ['correo' => $this->correo('correo'), 'nombres' => $this->texto('nombres', 100, true), 'apellidos' => $this->texto('apellidos', 100, true), 'telefono' => $this->telefono('telefono', false), 'actor' => (int)$u['id_usuario']];
                $this->usuarios->transaccion(fn() => $this->usuarios->actualizarDatos((int)$u['id_usuario'], $datos));
                Respuesta::exito(null, 'Perfil actualizado.');
            }
            Respuesta::error('Solo puedes consultar o actualizar los datos de tu propio perfil.', 405);
        }
        if ($u['rol_codigo'] === 'estudiante') {
            $perfil = $this->empresas->estudiantePorUsuario((int)$u['id_usuario']);
            if ($this->metodo === 'GET' && count($this->ruta) === 1) {
                if ($perfil) {
                    unset($perfil['cv_ruta'], $perfil['cv_mime']);
                }
                Respuesta::exito($perfil);
            }
            if ($this->metodo === 'PUT' && count($this->ruta) === 1) {
                $d = ['carrera' => $this->entero('id_carrera'), 'carnet' => $this->texto('carnet', 40, true), 'semestre' => $this->texto('semestre', 40, false), 'universidad' => $this->texto('universidad', 180, true), 'actor' => (int)$u['id_usuario']];
                $datos = ['correo' => $this->correo('correo'), 'nombres' => $this->texto('nombres', 100, true), 'apellidos' => $this->texto('apellidos', 100, true), 'telefono' => $this->telefono('telefono', true), 'actor' => (int)$u['id_usuario']];
                if (!$this->catalogos->idActivo('carreras', $d['carrera'])) throw new DomainException('Selecciona una carrera activa.');
                $this->usuarios->transaccion(function()use($u, $d, $datos) {
                        $this->empresas->guardarPerfilEstudiante((int)$u['id_usuario'], $d);$this->usuarios->actualizarDatos((int)$u['id_usuario'], $datos);
                });
                Respuesta::exito(null, 'Perfil actualizado.');
            }
            if (($this->ruta[1] ?? '') === 'cv' && $this->metodo === 'GET') {
                $cv = $this->empresas->cvPerfilEstudiante((int)$u['id_usuario']);
                if (!$cv || !empty($cv['cv_eliminado'])) Respuesta::error((new MensajeRepo())->obtener('error_cv_no_disponible', 'El CV no está disponible.'), 404);
                $this->transmitirCv($cv, 'estudiantes', (string)$cv['id_estudiante'], (int)$u['id_usuario']);
            }
            if (($this->ruta[1] ?? '') === 'cv' && $this->metodo === 'POST') {
                $cv = (new ArchivosCv())->guardar($_FILES['cv'] ?? []);
                $this->empresas->guardarCv((int)$u['id_usuario'], $cv['nombre'], $cv['ruta'], $cv['mime'], (int)$u['id_usuario']);
                Respuesta::exito(['cv_nombre' => $cv['nombre']], (new MensajeRepo())->obtener('exito_cv_actualizado', 'CV actualizado correctamente.'));
            }
            if (($this->ruta[1] ?? '') === 'cv' && $this->metodo === 'DELETE') {
                $this->empresas->marcarCvEliminado((int)$u['id_usuario'], (int)$u['id_usuario']);
                Respuesta::exito(null, (new MensajeRepo())->obtener('exito_cv_eliminado', 'El CV se marcó como eliminado.'));
            }
        } else {
            $perfil = $this->empresas->perfil((int)$u['id_usuario']);
            if ($this->metodo === 'GET' && count($this->ruta) === 1) Respuesta::exito($perfil);
            if ($this->metodo === 'PUT' && count($this->ruta) === 1) {
                $web = $this->texto('sitio_web', 255, false);
                if ($web !== null && (!filter_var($web, FILTER_VALIDATE_URL) || !in_array(strtolower((string)parse_url($web, PHP_URL_SCHEME)), ['http', 'https'], true))) throw new DomainException('Ingresa un sitio web válido.');
                $datosUsuario = ['correo' => $this->correo('correo'), 'nombres' => $this->texto('nombres', 100, true), 'apellidos' => $this->texto('apellidos', 100, true), 'telefono' => $this->telefono('telefono', false), 'actor' => (int)$u['id_usuario']];
                $datosEmpresa = ['legal' => $this->texto('nombre_legal', 180, true), 'comercial' => $this->texto('nombre_comercial', 180, false), 'fiscal' => $this->texto('identificacion_fiscal', 50, false), 'sector' => $this->texto('sector', 100, false), 'descripcion' => $this->texto('descripcion', 5000, false), 'correo' => $this->correo('correo_contacto'), 'telefono' => $this->telefono('telefono', false), 'web' => $web, 'direccion' => $this->texto('direccion', 255, false), 'actor' => (int)$u['id_usuario']];
                $this->usuarios->transaccion(function()use($u, $datosUsuario, $datosEmpresa) {
                        $this->usuarios->actualizarDatos((int)$u['id_usuario'], $datosUsuario);$this->empresas->guardarPerfil((int)$u['id_usuario'], $datosEmpresa);
                });
                Respuesta::exito(null, (new MensajeRepo())->obtener('exito_guardado', 'Perfil actualizado.'));
            }
        }
        Respuesta::error((new MensajeRepo())->obtener('error_no_encontrado', 'No se encontró el registro solicitado.'), 404);
    }
    /** Autoriza, registra y transmite el CV privado de una postulación. */
    private function descargarCv(): never {
        $u = Acceso::requerirRol(['estudiante', 'empresa', 'admin', 'superadmin'], true);
        if ($u['rol_codigo'] === 'estudiante') Acceso::requerirPermiso('postulaciones.consultar_propias');
        elseif ($u['rol_codigo'] === 'empresa') {
            Acceso::requerirPermiso('postulaciones.consultar_empresa');
            Acceso::requiereEmpresaAprobada((int)$u['id_usuario']);
        } else Acceso::requerirPermiso('postulaciones.consultar');
        $id = (int)($this->ruta[1] ?? 0);
        if ($id < 1) Respuesta::error('CV no encontrado.', 404);
        $row = $this->postulaciones->cvDescarga($id);
        if (!$row) Respuesta::error('CV no disponible.', 404);
        $permitido = match($u['rol_codigo']) {
            'estudiante' => (int)$row['id_usuario_estudiante'] === (int)$u['id_usuario'], 'empresa' => (int)$row['id_usuario_empresa'] === (int)$u['id_usuario'], 'admin', 'superadmin' => true, default => false
        };
        if (!$permitido) Respuesta::error((new MensajeRepo())->obtener('error_sin_permiso', 'No tienes permiso para descargar este CV.'), 403);
        $this->transmitirCv($row, 'postulaciones', (string)$id, (int)$u['id_usuario']);
    }
    /** Registra y transmite un PDF del almacenamiento privado. */
    private function transmitirCv(array $cv, string $entidad, string $registro, int $usuarioId): never {
        if (!empty($cv['cv_eliminado'])) Respuesta::error((new MensajeRepo())->obtener('error_cv_no_disponible', 'El CV no está disponible.'), 404);
        $ruta = ArchivosCv::resolverRuta((string)($cv['cv_ruta'] ?? ''));
        if (!$ruta) Respuesta::error((new MensajeRepo())->obtener('error_cv_no_disponible', 'El CV no está disponible.'), 404);
        $nombre = basename((string)($cv['cv_nombre'] ?? 'CV.pdf'));
        (new BitacoraRepo())->registrar('DESCARGA_CV', $entidad, $registro, null, ['descargado' => true], $usuarioId);
        header('Content-Type: application/pdf');
        header('Content-Length: ' . filesize($ruta));
        header('Content-Disposition: attachment; filename="CV.pdf"; filename*=UTF-8\'\'' . rawurlencode($nombre));
        header('Cache-Control: private, no-store');
        readfile($ruta);
        exit;
    }
    private function reportes(): never {
        $u = Acceso::requerirRol(['admin', 'superadmin']);
        $this->requerirGestion($u, 'reportes.consultar');
        (new ServicioCierre())->procesarAutomaticos();
        Respuesta::exito($this->datosReporte());
    }
    private function dashboard(): never {
        $u = Acceso::requerirRol(['estudiante', 'empresa', 'admin', 'superadmin'], true);
        if ($u['rol_codigo'] === 'estudiante') Acceso::requerirPermiso('postulaciones.consultar_propias');
        elseif ($u['rol_codigo'] === 'empresa') {
            Acceso::requerirPermiso('postulaciones.consultar_empresa');
            Acceso::requiereEmpresaAprobada((int)$u['id_usuario']);
        } else Acceso::requerirPermiso('reportes.consultar');
        (new ServicioCierre())->procesarAutomaticos();
        $datos = match($u['rol_codigo']) {
            'estudiante' => ['postulaciones' => $this->postulaciones->resumenEstudiante((int)$u['id_usuario'])], 'empresa' => ['practicas' => $this->practicas->resumenEmpresa((int)$u['id_usuario']), 'postulaciones' => $this->postulaciones->resumenEmpresa((int)$u['id_usuario'])], 'admin', 'superadmin' => $this->datosReporte(), default => []
        };
        Respuesta::exito($datos);
    }
    private function datosReporte(): array {
        $p = (new PracticaRepo())->reporteEstados();
        $s = (new PostulacionRepo())->reporteEstados();
        return ['practicas' => $p, 'postulaciones' => $s];
    }
    private function requerirGestion(array $u, string $permiso): void {
        Acceso::requerirPermiso($permiso);
    }
    private function texto(string $campo, int $maximo, bool $obligatorio): ?string {
        $v = $this->datos[$campo] ?? null;
        $etiquetas = [
            'id_area' => 'área', 'id_modalidad' => 'modalidad', 'id_carrera' => 'carrera',
            'fecha_inicio' => 'fecha de inicio', 'fecha_limite_postulacion' => 'fecha límite de postulación',
            'cupo_maximo' => 'cupo máximo', 'correo_contacto' => 'correo de contacto',
            'identificacion_fiscal' => 'identificación fiscal', 'nombre_legal' => 'nombre legal',
            'nombre_comercial' => 'nombre comercial', 'carta_presentacion' => 'carta de presentación',
            'contrasena_nueva' => 'contraseña nueva', 'contrasena' => 'contraseña',
        ];
        $nombre = $etiquetas[$campo] ?? str_replace('_', ' ', $campo);
        if ($v === null) {
            if ($obligatorio) throw new DomainException("El campo $nombre es obligatorio.");
            return null;
        }
        if (!is_scalar($v)) throw new DomainException("El campo $nombre no es válido.");
        $v = trim((string)$v);
        if ($v === '' && !$obligatorio) return null;
        if ($v === '' || mb_strlen($v) > $maximo) throw new DomainException("Revisa el campo $nombre.");
        return $v;
    }
    private function correo(string $campo): string {
        $v = $this->texto($campo, 254, true);
        if (filter_var($v, FILTER_VALIDATE_EMAIL) === false) throw new DomainException('Ingresa un correo electrónico válido.');
        return mb_strtolower($v);
    }
    private function correoValido(string $v): bool {
        return filter_var($v, FILTER_VALIDATE_EMAIL) !== false;
    }
    private function telefono(string $campo, bool $obligatorio): ?string {
        $v = $this->texto($campo, 8, $obligatorio);
        if ($v !== null && !preg_match('/^[0-9]{8}$/', $v)) throw new DomainException('El teléfono debe contener exactamente ocho dígitos.');
        return $v;
    }
    private function claveValida(): string {
        $v = $this->texto('contrasena', 200, true);
        $min = max(8, (int)(new ConfiguracionRepo())->obtener('password_longitud_minima', '10'));
        if (strlen($v) < $min) throw new DomainException('La contraseña no cumple la longitud mínima configurada.');
        return $v;
    }
    private function entero(string $campo): int {
        $v = $this->datos[$campo] ?? null;
        if (filter_var($v, FILTER_VALIDATE_INT) === false || (int)$v < 1) throw new DomainException("El campo $campo debe ser un entero positivo.");
        return (int)$v;
    }
    private function paginacion(): array {
        $conf = new ConfiguracionRepo();
        $max = max(1, (int)$conf->obtener('paginacion_tamano', '20'));
        $tope = max($max, (int)$conf->obtener('paginacion_maxima', '100'));
        $limite = min($tope, max(1, (int)($_GET['por_pagina'] ?? $max)));
        $pagina = max(1, (int)($_GET['pagina'] ?? 1));
        return ['limite' => $limite, 'pagina' => $pagina, 'offset' => ($pagina - 1)*$limite];
    }
    private function paginacionPracticas(): array {
        $limite = 10;
        $pagina = max(1, (int)($_GET['pagina'] ?? 1));
        return ['limite' => $limite, 'pagina' => $pagina, 'offset' => ($pagina - 1)*$limite];
    }
    private function validarComentario(?string $comentario, bool $requerido): void {
        $min = max(1, (int)(new ConfiguracionRepo())->obtener('comentario_rechazo_minimo', '10'));
        if ($requerido && ($comentario === null || mb_strlen(trim($comentario)) < $min)) throw new DomainException('El comentario es obligatorio y no cumple el mínimo configurado.');
    }
    /** Valida y normaliza los campos de una práctica antes de guardarlos. */
    private function datosPractica(?array $vigente = null): array {
        $errores = [];
        $texto = function(string $campo, int $maximo, bool $obligatorio = false) use (&$errores): ?string {
            $valor = $this->datos[$campo] ?? null;
            if ($valor === null || !is_scalar($valor)) {
                if ($obligatorio) $errores[$campo] = 'Este campo es obligatorio.';
                return null;
            }
            $valor = trim((string)$valor);
            if ($valor === '' && $obligatorio) $errores[$campo] = 'Este campo es obligatorio.';
            elseif (mb_strlen($valor) > $maximo) $errores[$campo] = "No puede superar $maximo caracteres.";
            return $valor === '' ? null : $valor;
        };
        $entero = function(string $campo) use (&$errores): ?int {
            $valor = $this->datos[$campo] ?? null;
            if (filter_var($valor, FILTER_VALIDATE_INT) === false || (int)$valor < 1) {
                $errores[$campo] = 'Selecciona un valor válido.';
                return null;
            }
            return (int)$valor;
        };
        $area = $entero('id_area');
        $modalidad = $entero('id_modalidad');
        if ($area !== null && !$this->catalogos->idActivo('areas', $area)) $errores['id_area'] = 'Selecciona un área activa.';
        if ($modalidad !== null && !$this->catalogos->idActivo('modalidades', $modalidad)) $errores['id_modalidad'] = 'Selecciona una modalidad activa.';
        $titulo = $texto('titulo', 180, true);
        $descripcion = $texto('descripcion', 10000, true);
        $requisitos = $texto('requisitos', 10000);
        $ubicacion = $texto('ubicacion', 255);
        $inicio = $texto('fecha_inicio', 10, true);
        $fecha = $texto('fecha_limite_postulacion', 10, true);
        foreach (['fecha_inicio' => $inicio, 'fecha_limite_postulacion' => $fecha] as $campo => $valor) {
            if ($valor === null) continue;
            $dt = DateTimeImmutable::createFromFormat('!Y-m-d', $valor);
            $esFechaValida = $dt && $dt->format('Y-m-d') === $valor;
            if (!$esFechaValida) $errores[$campo] = 'Ingresa una fecha válida.';
            elseif ($valor < date('Y-m-d') && !($campo === 'fecha_inicio' && $vigente && $valor === $vigente['fecha_inicio'])) $errores[$campo] = 'La fecha no puede estar en el pasado.';
        }
        if ($fecha !== null && $fecha <= date('Y-m-d')) $errores['fecha_limite_postulacion'] = 'La fecha límite debe ser posterior a hoy.';
        $cupo = $entero('cupo_maximo');
        $maxCupo = (int)(new ConfiguracionRepo())->obtener('cupo_maximo_permitido', '5000');
        if ($cupo !== null && $cupo > $maxCupo) $errores['cupo_maximo'] = 'El cupo supera el máximo permitido.';
        if ($errores) Respuesta::error('Corrige los campos marcados.', 422, $errores);
        return ['area' => $area, 'modalidad' => $modalidad, 'titulo' => $titulo, 'descripcion' => $descripcion, 'requisitos' => $requisitos, 'ubicacion' => $ubicacion, 'inicio' => $inicio, 'cupo' => $cupo, 'fecha' => $fecha];
    }
}
