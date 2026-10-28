<?php
declare(strict_types = 1);
/** Controla autenticación, renovación, expiración y cierre de sesiones. */
final class Sesion {
    private static ?array $usuario = null;
    public static function csrfNuevo(): string {
        if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        return (string)$_SESSION['csrf_token'];
    }
    public static function verificarCsrf(): void {
        $token = Peticion::encabezado('X-CSRF-Token');
        if (!$token || empty($_SESSION['csrf_token']) || !hash_equals((string)$_SESSION['csrf_token'], $token)) Respuesta::error((new MensajeRepo())->obtener('error_csrf', 'El token de seguridad venció. Actualiza la página.'), 403);
    }
    public static function iniciar(string $identificador, string $clave, ?string $rolEsperado = null): array {
        $repo = new UsuarioRepo();
        $ip = substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
        $conf = new ConfiguracionRepo();
        $max = max(1, (int)$conf->obtener('login_intentos_maximos', '5'));
        $bloqueo = max(1, (int)$conf->obtener('login_bloqueo_minutos', '15'));
        $vigente = $repo->bloqueoVigente($identificador, $ip);
        if ($vigente && strtotime($vigente) > time()) {
            $repo->guardarIntento($identificador, $ip, false, $vigente);
            (new BitacoraRepo())->registrar('LOGIN_BLOQUEADO', 'usuarios', null, null, ['identificador' => mb_substr($identificador, 0, 254)], null, $ip);
            Respuesta::error((new MensajeRepo())->obtener('error_bloqueo', 'La cuenta está temporalmente bloqueada.'), 429);
        }
        $usuario = $repo->porAcceso($identificador);
        $estadoActivo = (new CatalogoRepo())->estadoId('usuario', 'activo');
        $estadoPendiente = (new CatalogoRepo())->estadoId('usuario', 'pendiente');
        $empresaPendiente = $usuario
            && $usuario['rol_codigo'] === 'empresa'
            && (int)$usuario['id_estado'] === $estadoPendiente
            && in_array($usuario['empresa_estado_codigo'] ?? '', ['pendiente', 'requiere_cambios', 'rechazada'], true);
        $permitido = $usuario && ((int)$usuario['id_estado'] === $estadoActivo || $empresaPendiente) && password_verify($clave, (string)$usuario['password_hash']);
        if (!$permitido) {
            $fallos = $repo->fallosRecientes($identificador, $ip, $bloqueo);
            $bloqueadoHasta = $fallos + 1 >= $max ? (new DateTimeImmutable())->modify('+' . $bloqueo . ' minutes')->format('Y-m-d H:i:s'): null;
            $repo->guardarIntento($identificador, $ip, false, $bloqueadoHasta);
            (new BitacoraRepo())->registrar('LOGIN_FALLIDO', 'usuarios', null, null, ['identificador' => mb_substr($identificador, 0, 254)], null, $ip);
            $esBloqueo = $bloqueadoHasta !== null;
            Respuesta::error((new MensajeRepo())->obtener($esBloqueo ? 'error_bloqueo': 'error_credenciales', $esBloqueo ? 'Se alcanzó el límite de intentos. Inténtalo más tarde.': 'Usuario o contraseña incorrectos.'), $esBloqueo ? 429: 401);
        }
        if ($rolEsperado !== null && !in_array($usuario['rol_codigo'], [$rolEsperado, 'admin', 'superadmin'], true)) {
            Respuesta::error('La cuenta no corresponde al tipo seleccionado.', 403, ['identificador' => 'Usa la pestaña del tipo de cuenta correcto.']);
        }
        session_regenerate_id(true);
        $_SESSION = [];
        $_SESSION['usuario_id'] = (int)$usuario['id_usuario'];
        $_SESSION['ultima_actividad'] = time();
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $minutos = max(5, (int)$conf->obtener('sesion_inactividad_minutos', '30'));
        $expira = (new DateTimeImmutable())->modify('+' . $minutos . ' minutes')->format('Y-m-d H:i:s');
        Conexion::contextoAuditoria((int)$usuario['id_usuario'], $ip);
        $repo->registrarSesion((int)$usuario['id_usuario'], hash('sha256', session_id()), $ip, substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500), $expira);
        $repo->guardarIntento($identificador, $ip, true);
        $repo->actualizarAcceso((int)$usuario['id_usuario']);
        (new BitacoraRepo())->registrar('LOGIN_EXITOSO', 'usuarios', (string)$usuario['id_usuario'], null, ['usuario' => $usuario['usuario']], (int)$usuario['id_usuario'], $ip);
        $actual = $repo->porId((int)$usuario['id_usuario']) ?? [];
        $actual['csrf_token'] = $_SESSION['csrf_token'];
        return $actual;
    }
    public static function actual(bool $permitirEmpresaPendiente = false): array {
        if (self::$usuario !== null) {
            self::validarAprobacionEmpresa(self::$usuario, $permitirEmpresaPendiente);
            return self::$usuario;
        }
        $id = (int)($_SESSION['usuario_id'] ?? 0);
        $hash = hash('sha256', session_id());
        $ahora = time();
        if ($id < 1) Respuesta::error((new MensajeRepo())->obtener('error_sesion', 'Inicia sesión para continuar.'), 401);
        $limite = max(1, (int)(new ConfiguracionRepo())->obtener('sesion_inactividad_minutos', '30'))*60;
        if ($ahora - (int)($_SESSION['ultima_actividad'] ?? 0) > $limite) {
            self::cerrarInterno($id, $hash);
            Respuesta::error((new MensajeRepo())->obtener('error_sesion', 'Tu sesión expiró por inactividad.'), 401);
        }
        $u = (new UsuarioRepo())->sesiones($id, $hash);
        $empresaPendiente = $u
            && $permitirEmpresaPendiente
            && $u['rol_codigo'] === 'empresa'
            && $u['estado_codigo'] === 'pendiente'
            && in_array($u['empresa_estado_codigo'] ?? '', ['pendiente', 'requiere_cambios', 'rechazada'], true);
        if (!$u || ($u['estado_codigo'] !== 'activo' && !$empresaPendiente)) {
            self::cerrarInterno($id, $hash);
            Respuesta::error((new MensajeRepo())->obtener('error_sesion', 'La cuenta no está activa o la sesión venció.'), 401);
        }
        self::validarAprobacionEmpresa($u, $permitirEmpresaPendiente);
        $ruta = implode('/', Peticion::ruta());
        if (!empty($u['debe_cambiar_password']) && !in_array($ruta, ['auth/me', 'auth/cambiar-clave'], true)) Respuesta::error((new MensajeRepo())->obtener('error_cambio_password', 'Debes cambiar la contraseña temporal antes de continuar.'), 403);
        $_SESSION['ultima_actividad'] = $ahora;
        $minutos = max(1, (int)(new ConfiguracionRepo())->obtener('sesion_inactividad_minutos', '30'));
        if ($ahora - (int)($_SESSION['db_touch'] ?? 0) >= 60) {
            (new UsuarioRepo())->tocarSesion($id, $hash, (new DateTimeImmutable())->modify('+' . $minutos . ' minutes')->format('Y-m-d H:i:s'));
            $_SESSION['db_touch'] = $ahora;
        }
        Conexion::contextoAuditoria($id, substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45));
        self::$usuario = $u;
        return $u;
    }
    /** Impide que una empresa sin aprobación use rutas protegidas. */
    private static function validarAprobacionEmpresa(array $usuario, bool $permitirPendiente): void {
        if (($usuario['rol_codigo'] ?? '') === 'empresa' && ($usuario['empresa_estado_codigo'] ?? null) !== 'aprobada' && !$permitirPendiente) {
            Respuesta::error((new MensajeRepo())->obtener('error_empresa_pendiente', 'La empresa debe estar aprobada para realizar esta acción.'), 403);
        }
    }
    public static function cerrar(): void {
        $id = (int)($_SESSION['usuario_id'] ?? 0);
        $hash = hash('sha256', session_id());
        if ($id > 0) {
            (new UsuarioRepo())->revocarSesion($id, $hash);
            (new BitacoraRepo())->registrar('LOGOUT', 'usuarios', (string)$id, null, null, $id);
        }
        self::cerrarInterno($id, $hash);
        Respuesta::exito(null, (new MensajeRepo())->obtener('exito_logout', 'Sesión cerrada.'));
    }
    private static function cerrarInterno(int $id, string $hash): void {
        if ($id > 0) {
            try {
                (new UsuarioRepo())->revocarSesion($id, $hash);
            } catch (Throwable) {
            }
        }
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', ['expires' => time() - 3600, 'path' => $p['path'], 'domain' => $p['domain'], 'secure' => $p['secure'], 'httponly' => $p['httponly'], 'samesite' => 'Strict']);
        }
        session_destroy();
    }
}
