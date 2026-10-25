<?php
declare(strict_types=1);

require_once __DIR__ . '/Connection.php';

if (in_array(strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')), ['PUT', 'DELETE'], true) && empty($_POST)) {
    $rawBody = file_get_contents('php://input');
    if (is_string($rawBody)) parse_str($rawBody, $_POST);
}

set_exception_handler(static function (Throwable $error): void {
    error_log($error->__toString());
    http_response_code(500);
    if (str_contains(str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? '')), '/api/')) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'No se pudo completar la solicitud. Verifica la configuración del sistema.'], JSON_UNESCAPED_UNICODE);
        return;
    }
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="es"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Error | Prácticas UMG</title><link rel="stylesheet" href="' . htmlspecialchars(app_url('css/styles.css'), ENT_QUOTES, 'UTF-8') . '"><main class="container"><section class="empty-state"><h1>No se pudo cargar el sistema</h1><p>Verifica que MySQL esté activo y que la conexión esté configurada correctamente.</p></section></main></html>';
});

$scriptPath = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? '/index.php'));
$basePath = preg_replace('#/(?:api/.*|[^/]+\.php)$#', '', $scriptPath) ?? '';
define('APP_BASE', rtrim($basePath, '/'));
function app_url(string $path): string { return APP_BASE . '/' . ltrim($path, '/'); }

// Shared escaping and database access helpers used by every rendered page.
function h(mixed $value): string { return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function db(): PDO { return Connection::get(); }
function current_user(): ?array {
    static $checked = false;
    static $identity = null;
    if ($checked) return $identity;
    $checked = true;
    $userId = $_SESSION['user_id'] ?? ($_SESSION['user']['id'] ?? null);
    if (!is_numeric($userId) || (int)$userId < 1) return null;

    // A PHP session is valid only while its hashed session ID has a live DB registry row.
    $query = db()->prepare("SELECT id_usuario,usuario,nombres,apellidos,correo,estado,role FROM vw_sesion_acceso WHERE id_usuario=:uid AND token_hash=:token AND revocada_en IS NULL AND expira_en>NOW() AND (estado='activo' OR (role='empresa' AND estado IN ('pendiente','requiere_cambios','rechazada'))) LIMIT 1");
    $query->execute(['uid'=>(int)$userId,'token'=>hash('sha256',session_id())]);
    $row = $query->fetch();
    if (!$row) {
        unset($_SESSION['user'], $_SESSION['user_id']);
        return null;
    }
    // The current role always comes from MySQL, never from values left in the session.
    $identity = ['id'=>(int)$row['id_usuario'],'username'=>$row['usuario'],'name'=>$row['nombres'].' '.$row['apellidos'],'email'=>$row['correo'],'role'=>$row['role'],'state'=>$row['estado']];
    $_SESSION['user_id'] = (int)$row['id_usuario'];
    unset($_SESSION['user']);
    return $identity;
}

// Register and revoke session tokens in MySQL while PHP stores the session payload server-side.
function register_authenticated_session(int $userId): void {
    $pdo=db();
    set_audit_context($userId);
    $expires=date('Y-m-d H:i:s',time()+28800);
    $stmt=$pdo->prepare('INSERT INTO sesiones_usuario(id_usuario,token_hash,ip_origen,user_agent,expira_en) VALUES(:uid,:token,:ip,:agent,:expires)');
    $stmt->execute(['uid'=>$userId,'token'=>hash('sha256',session_id()),'ip'=>substr((string)($_SERVER['REMOTE_ADDR']??''),0,45),'agent'=>substr((string)($_SERVER['HTTP_USER_AGENT']??''),0,500),'expires'=>$expires]);
}
function revoke_authenticated_session(): void {
    $userId=$_SESSION['user_id']??($_SESSION['user']['id']??null);
    if (!is_numeric($userId) || (int)$userId<1) return;
    set_audit_context((int)$userId);
    $stmt=db()->prepare('UPDATE sesiones_usuario SET revocada_en=NOW() WHERE id_usuario=:uid AND token_hash=:token AND revocada_en IS NULL');
    $stmt->execute(['uid'=>(int)$userId,'token'=>hash('sha256',session_id())]);
}

// Enforce server-side profile access before returning protected data or forms.
function require_role(string ...$roles): array {
    $user = current_user();
    if (!$user) { header('Location: ' . app_url('index.php?login=1')); exit; }
    if (!in_array($user['role'], $roles, true)) { http_response_code(403); exit('No tienes permiso para acceder a esta sección.'); }
    return $user;
}

// Check a fine-grained permission by looking up the current user's database grants.
function require_permission(string $permission): array {
    $user=current_user();
    if (!$user) { header('Location: ' . app_url('index.php?login=1')); exit; }
    $stmt=db()->prepare('SELECT 1 FROM vw_usuario_permisos WHERE id_usuario=:uid AND permiso=:permission LIMIT 1');
    $stmt->execute(['uid'=>$user['id'],'permission'=>$permission]);
    if (!$stmt->fetchColumn()) { http_response_code(403); exit('Tu perfil no tiene permiso para realizar esta acción.'); }
    return $user;
}

// Attach CSRF tokens to all state-changing HTML forms and verify them on receipt.
function csrf_token(): string { if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32)); return $_SESSION['csrf']; }
function csrf_field(): string { return '<input type="hidden" name="csrf" value="' . h(csrf_token()) . '">'; }
function verify_csrf(): void {
    $token = $_POST['csrf'] ?? '';
    if (!is_string($token) || !hash_equals(csrf_token(), $token)) { http_response_code(419); exit('La sesión del formulario venció. Recarga la página e inténtalo de nuevo.'); }
}
function flash(string $message, string $type = 'success'): void { $_SESSION['flash'] = ['message' => $message, 'type' => $type]; }
function redirect(string $url): never { header('Location: ' . $url); exit; }
// Render the shared navigation, role-specific links, and one-time status messages.
function page_start(string $title): void {
    $user = current_user();
    echo '<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="description" content="Sistema de oportunidades y seguimiento de prácticas profesionales"><title>' . h($title) . ' | Prácticas UMG</title><link rel="stylesheet" href="' . h(app_url('css/styles.css')) . '"></head><body><header class="topbar"><a class="brand" href="' . h(app_url('index.php')) . '"><span class="brand-mark">P</span><span>Prácticas<span class="brand-muted">UMG</span></span></a><nav aria-label="Navegación principal"><a href="' . h(app_url('index.php?oportunidades=1')) . '">Oportunidades</a>';
    if ($user && $user['role'] === 'estudiante') echo '<a href="' . h(app_url('seguimiento.php')) . '">Mis postulaciones</a><a href="' . h(app_url('perfil.php')) . '">Mi perfil</a>';
    if ($user && in_array($user['role'], ['administrador','superadministrador'], true)) echo '<a href="' . h(app_url('admin.php')) . '">Administración</a>';
    if ($user && $user['role'] === 'superadministrador') echo '<a href="' . h(app_url('roles.php')) . '">Roles</a>';
    if ($user && $user['role'] === 'empresa') echo '<a href="' . h(app_url('empresa.php')) . '">Panel de empresa</a>';
    if ($user) echo '<span class="nav-user">' . h($user['name']) . '</span><form class="inline-form" method="post" action="' . h(app_url('api/auth.php')) . '"><input type="hidden" name="_method" value="DELETE">' . csrf_field() . '<button class="button button-quiet" type="submit">Cerrar sesión</button></form>';
    else echo '<a class="button button-quiet" href="' . h(app_url('index.php?login=1')) . '">Iniciar sesión</a>';
    echo '</nav></header><main class="container">';
    if (!empty($_SESSION['flash'])) { $f = $_SESSION['flash']; unset($_SESSION['flash']); echo '<div class="notice notice-' . h($f['type']) . '" role="status">' . h($f['message']) . '</div>'; }
}
function page_end(): void { echo '</main><footer class="footer"><div><strong>Prácticas UMG</strong><span>Conecta tu formación con experiencia profesional.</span></div><span>© ' . date('Y') . ' Coordinación Académica</span></footer><script src="' . h(app_url('js/app.js')) . '" defer></script></body></html>'; }
// Normalize form strings and reject oversize or missing values before SQL writes.
function post_value(string $key, int $max, bool $required = false): ?string {
    $value = $_POST[$key] ?? null;
    if (!is_string($value)) return null;
    $value = trim($value);
    if (($required && $value === '') || mb_strlen($value) > $max) return null;
    return $value === '' ? null : $value;
}
// Guatemalan contact phones are stored and accepted as exactly eight digits.
function valid_phone(?string $value): bool {
    return $value !== null && preg_match('/^[0-9]{8}$/D', $value) === 1;
}
// Preserve only named, scalar form fields when the server sends validation feedback.
function remember_form_state(string $name, array $limits, array $errors, string $message): void {
    $values = [];
    foreach ($limits as $field => $max) {
        $value = $_POST[$field] ?? null;
        if (is_string($value)) $values[$field] = mb_substr($value, 0, $max);
    }
    $_SESSION['_form_state'][$name] = ['values' => $values, 'errors' => $errors, 'message' => $message];
}
function take_form_state(string $name): array {
    $state = $_SESSION['_form_state'][$name] ?? [];
    unset($_SESSION['_form_state'][$name]);
    return is_array($state) ? $state : [];
}
// Render one accessible password field; JavaScript only toggles its visibility.
function password_field(string $name, string $label, string $autocomplete, int $minimum = 0): string {
    $id = 'field-' . preg_replace('/[^a-z0-9_-]/i', '-', $name);
    return '<label class="password-field" for="' . h($id) . '">' . h($label) . '<span class="password-control"><input id="' . h($id) . '" type="password" name="' . h($name) . '" maxlength="200" autocomplete="' . h($autocomplete) . '"' . ($minimum ? ' minlength="' . $minimum . '"' : '') . ' required><button class="password-toggle" type="button" data-password-toggle aria-label="Mostrar contraseña" aria-pressed="false"><span aria-hidden="true">◉</span></button></span></label>';
}
function set_audit_context(int $userId): void {
    $stmt = db()->prepare('SET @app_user_id = :uid, @app_ip = :ip');
    $stmt->execute(['uid' => $userId, 'ip' => substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45)]);
}
function method_override(): string {
    $method = $_POST['_method'] ?? $_SERVER['REQUEST_METHOD'] ?? 'GET';
    return is_string($method) ? strtoupper($method) : strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
}
