<?php
declare(strict_types=1);
require_once __DIR__ . '/core/App.php';

// GET exposes only the current session identity; credentials are never returned.
$method = method_override();
if ($method === 'GET') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['authenticated' => current_user() !== null, 'user' => current_user()], JSON_UNESCAPED_UNICODE);
    exit;
}
if ($method === 'DELETE') {
    verify_csrf();
    revoke_authenticated_session();
    $_SESSION = [];
    session_regenerate_id(true);
    setcookie(session_name(), '', ['expires'=>time()-3600,'path'=>'/','httponly'=>true,'secure'=>!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off','samesite'=>'Strict']);
    flash('Cerraste sesión correctamente.');
    redirect(app_url('index.php'));
}
if ($method !== 'POST') { http_response_code(405); header('Allow: GET, POST, DELETE'); exit('Método no permitido.'); }
verify_csrf();
$action = $_POST['action'] ?? 'login';
if (!is_string($action)) $action = '';
try {
    // Login selects the role from the database instead of trusting browser-supplied role IDs.
    if ($action === 'login') {
        $usernameInput = $_POST['usuario'] ?? null;
        $username = is_string($usernameInput) ? trim($usernameInput) : '';
        $password = $_POST['password'] ?? '';
        if (!preg_match('/^[A-Za-z0-9._-]{3,40}$/', $username) || !is_string($password) || $password === '' || strlen($password) > 200) throw new DomainException('Ingresa un usuario y contraseña válidos.');
        $stmt = db()->prepare('SELECT id_usuario,nombres,apellidos,correo,usuario,password_hash,estado,role FROM vw_usuario_autenticacion WHERE usuario=:usuario LIMIT 1');
        $stmt->execute(['usuario' => $username]);
        $user = $stmt->fetch();
        $companyMaintenance = $user && $user['role'] === 'empresa' && in_array($user['estado'], ['pendiente','requiere_cambios','rechazada'], true);
        if (!$user || ($user['estado'] !== 'activo' && !$companyMaintenance) || !password_verify($password, $user['password_hash'])) throw new DomainException('Usuario o contraseña incorrectos, o cuenta no activa.');
        revoke_authenticated_session();
        session_regenerate_id(true);
        $_SESSION = ['user_id'=>(int)$user['id_usuario']];
        register_authenticated_session((int)$user['id_usuario']);
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
        flash('Bienvenido/a, ' . $user['nombres'] . ($companyMaintenance ? '. Tu empresa debe completar o corregir su perfil antes de publicar.' : '.'));
        // Send each authenticated profile to the first page it is allowed to use.
        $destination = match ($user['role']) { 'administrador','superadministrador' => 'admin.php', 'empresa' => 'empresa.php', default => 'index.php' };
        redirect(app_url($destination));
    }
    if ($action === 'register') {
        // Student registration creates the user and academic profile atomically.
        $first = post_value('nombres', 100, true); $last = post_value('apellidos', 100, true); $username=post_value('usuario',40,true);
        $emailInput = $_POST['correo'] ?? null;
        $email = is_string($emailInput) ? filter_var(trim($emailInput), FILTER_VALIDATE_EMAIL) : false;
        $carnet = post_value('carnet', 40, true); $career = post_value('carrera', 150, true); $university = post_value('universidad', 180, true);
        $semester = post_value('semestre', 40); $password = $_POST['password'] ?? '';
        if (!$first || !$last || !preg_match('/^[\p{L}\p{M} .\x{27}\x{2019}-]+$/u',$first) || !preg_match('/^[\p{L}\p{M} .\x{27}\x{2019}-]+$/u',$last) || !$username || !preg_match('/^[A-Za-z0-9._-]{3,40}$/',$username) || !$email || !$carnet || !preg_match('/^[A-Za-z0-9._\/-]{1,40}$/',$carnet) || !$career || !$university || !is_string($password) || strlen($password) < 10 || strlen($password) > 200) throw new DomainException('Completa todos los campos con datos válidos. La contraseña requiere al menos 10 caracteres y el usuario solo admite letras, números, punto, guion y guion bajo.');
        if (!preg_match('/^[\p{L}\p{M}0-9 .,&()\/-]+$/u',$career) || !preg_match('/^[\p{L}\p{M}0-9 .,&()\/-]+$/u',$university) || ($semester && !preg_match('/^[\p{L}\p{M}0-9 .º°-]{1,40}$/u',$semester)) || (isset($_POST['semestre']) && is_string($_POST['semestre']) && mb_strlen(trim($_POST['semestre']))>40)) throw new DomainException('Revisa el formato o la longitud de carrera, universidad o semestre.');
        $pdo = db(); $pdo->beginTransaction();
        set_audit_context(0);
        $role = $pdo->prepare("SELECT id_rol FROM vw_roles_catalogo WHERE nombre='estudiante'"); $role->execute(); $roleId = $role->fetchColumn();
        if (!$roleId) throw new RuntimeException('No está configurado el rol de estudiante.');
        $insert = $pdo->prepare("INSERT INTO usuarios (usuario,id_rol,nombres,apellidos,correo,password_hash,estado) VALUES (:username,:role,:first,:last,:email,:hash,'activo')");
        $insert->execute(['username'=>$username,'role' => $roleId, 'first' => $first, 'last' => $last, 'email' => $email, 'hash' => password_hash($password, PASSWORD_DEFAULT)]);
        $uid = (int)$pdo->lastInsertId();
        set_audit_context($uid);
        $student = $pdo->prepare('INSERT INTO estudiantes (id_usuario,carnet,carrera,semestre,universidad) VALUES (:uid,:carnet,:career,:semester,:university)');
        $student->execute(['uid' => $uid, 'carnet' => $carnet, 'career' => $career, 'semester' => $semester, 'university' => $university]);
        $pdo->commit();
        session_regenerate_id(true);
        $_SESSION = ['user_id'=>$uid];
        register_authenticated_session($uid);
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
        flash('Tu cuenta de estudiante está lista. Ya puedes consultar y postularte.');
        redirect(app_url('index.php'));
    }
    if ($action === 'register_company') {
        // Company accounts wait for administrator approval before they can sign in.
        $first=post_value('nombres',100,true); $last=post_value('apellidos',100,true);
        $username=post_value('usuario',40,true); $emailInput=$_POST['correo']??null; $email=is_string($emailInput)?filter_var(trim($emailInput),FILTER_VALIDATE_EMAIL):false;
        $password=$_POST['password']??''; $legal=post_value('nombre_legal',180,true); $commercial=post_value('nombre_comercial',180);
        $tax=post_value('identificacion_fiscal',50); $sector=post_value('sector',100); $description=post_value('descripcion',5000);
        $contactInput=$_POST['correo_contacto']??null; $contact=is_string($contactInput)?filter_var(trim($contactInput),FILTER_VALIDATE_EMAIL):false;
        $phoneInput=$_POST['telefono']??''; $phone=is_string($phoneInput)&&trim($phoneInput)!==''?trim($phoneInput):null; $website=post_value('sitio_web',255); $address=post_value('direccion',255);
        if (!$first||!$last||!preg_match('/^[\p{L}\p{M} .\x{27}\x{2019}-]+$/u',$first)||!preg_match('/^[\p{L}\p{M} .\x{27}\x{2019}-]+$/u',$last)||!$username||!preg_match('/^[A-Za-z0-9._-]{3,40}$/',$username)||!$email||!$legal||!$contact||!is_string($password)||strlen($password)<10||strlen($password)>200) throw new DomainException('Completa los datos requeridos con un usuario válido. La contraseña debe tener al menos 10 caracteres.');
        if (!is_string($phoneInput) || ($phone !== null && (!valid_phone($phone)||mb_strlen($phone)>8))) throw new DomainException('El teléfono debe tener exactamente 8 dígitos, sin espacios ni prefijo.');
        foreach (['nombre_comercial'=>180,'identificacion_fiscal'=>50,'sector'=>100,'descripcion'=>5000,'sitio_web'=>255,'direccion'=>255] as $field=>$max) {
            if (isset($_POST[$field]) && is_string($_POST[$field]) && mb_strlen(trim($_POST[$field]))>$max) throw new DomainException('El campo '.$field.' excede su límite de '.$max.' caracteres.');
        }
        foreach (['nombre comercial'=>$commercial,'identificación fiscal'=>$tax,'sector'=>$sector,'dirección'=>$address] as $label=>$value) {
            if ($value !== null && preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', $value)) throw new DomainException('El campo '.$label.' contiene caracteres de control no permitidos.');
        }
        if ($website && (!filter_var($website,FILTER_VALIDATE_URL)||!in_array(strtolower((string)parse_url($website,PHP_URL_SCHEME)),['http','https'],true))) throw new DomainException('Ingresa una dirección web http o https válida, o deja el campo vacío.');
        $pdo=db(); $pdo->beginTransaction();
        set_audit_context(0);
        $role=$pdo->prepare("SELECT id_rol FROM vw_roles_catalogo WHERE nombre='empresa'"); $role->execute(); $roleId=$role->fetchColumn();
        if (!$roleId) throw new RuntimeException('No está configurado el perfil de empresa.');
        $insertUser=$pdo->prepare("INSERT INTO usuarios (usuario,id_rol,nombres,apellidos,correo,password_hash,estado) VALUES (:username,:role,:first,:last,:email,:hash,'pendiente')");
        $insertUser->execute(['username'=>$username,'role'=>$roleId,'first'=>$first,'last'=>$last,'email'=>$email,'hash'=>password_hash($password,PASSWORD_DEFAULT)]);
        $userId=(int)$pdo->lastInsertId();
        set_audit_context($userId);
        $insertCompany=$pdo->prepare('INSERT INTO empresas (nombre_legal,nombre_comercial,identificacion_fiscal,sector,descripcion,correo_contacto,telefono,sitio_web,direccion,id_usuario) VALUES (:legal,:commercial,:tax,:sector,:description,:contact,:phone,:website,:address,:user)');
        $insertCompany->execute(['legal'=>$legal,'commercial'=>$commercial,'tax'=>$tax,'sector'=>$sector,'description'=>$description,'contact'=>$contact,'phone'=>$phone,'website'=>$website,'address'=>$address,'user'=>$userId]);
        $pdo->commit(); flash('Solicitud de empresa enviada. Coordinación debe aprobar la cuenta antes del primer ingreso.');
        redirect(app_url('index.php?login=1'));
    }
    throw new DomainException('Acción no reconocida.');
} catch (DomainException $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    flash($e->getMessage(), 'error');
    $destination = $action === 'register' ? 'index.php?registro=1' : ($action === 'register_company' ? 'index.php?registro=empresa' : 'index.php?login=1');
    redirect(app_url($destination));
} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    $duplicate = $e->getCode() === '23000';
    $connectionDenied = str_contains($e->getMessage(), 'Access denied');
    $message = $duplicate ? 'El usuario, correo o carnet ya está registrado.' : ($connectionDenied ? 'No hay conexión con la base de datos. Revisa el usuario, contraseña y permisos en api/core/.env.' : 'No se pudo completar la operación. Inténtalo de nuevo.');
    flash($message, 'error');
    $destination = $action === 'register_company' ? 'registro.php?tipo=empresa' : ($action === 'register' ? 'registro.php?tipo=estudiante' : 'index.php');
    redirect(app_url($destination));
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    error_log($e->getMessage()); flash('Ocurrió un error controlado. Revisa la configuración e inténtalo de nuevo.', 'error');
    redirect(app_url('index.php'));
}
