<?php
declare(strict_types = 1);
// Gestiona cuentas y roles desde las páginas PHP.
require_once dirname(__DIR__, 2) . '/App.php';
// Restringe los cambios de rol a superadministración.
$actor = require_role('superadministrador');
require_permission('usuarios.gestionar');
require_permission('usuarios.roles.gestionar');
if (method_override() !== 'POST') {
    http_response_code(405);
    exit('Método no permitido.');
}
verify_csrf();
// Crea la cuenta y su perfil en una transacción.
if (($_POST['accion'] ?? '') === 'crear_usuario') {
    // Limita la creación a los tipos de cuenta disponibles.
    $allowedRoles = ['estudiante', 'empresa', 'administrador'];
    $first = post_value('nombres', 100, true);
    $last = post_value('apellidos', 100, true);
    $username = post_value('usuario', 40, true);
    $emailInput = $_POST['correo'] ?? null;
    $email = is_string($emailInput) ? filter_var(trim($emailInput), FILTER_VALIDATE_EMAIL): false;
    $phone = post_value('telefono', 8);
    $password = $_POST['password'] ?? null;
    $role = $_POST['rol'] ?? '';
    $limits = ['nombres' => 100, 'apellidos' => 100, 'usuario' => 40, 'correo' => 254, 'telefono' => 8, 'rol' => 20];
    $errors = [];
    if (!$first) $errors['nombres'] = 'Escribe los nombres (máximo 100 caracteres).';
    elseif (!preg_match('/^[\p{L}\p{M} .\x{27}\x{2019}-]+$/u', $first)) $errors['nombres'] = 'Usa solo letras, espacios, puntos o guiones.';
    if (!$last) $errors['apellidos'] = 'Escribe los apellidos (máximo 100 caracteres).';
    elseif (!preg_match('/^[\p{L}\p{M} .\x{27}\x{2019}-]+$/u', $last)) $errors['apellidos'] = 'Usa solo letras, espacios, puntos o guiones.';
    if (!$username || !preg_match('/^[A-Za-z0-9._-]{3,40}$/', $username)) $errors['usuario'] = 'Usa de 3 a 40 letras, números, puntos, guiones o guiones bajos.';
    if (!$email) $errors['correo'] = 'Ingresa un correo válido.';
    $phoneInput = $_POST['telefono'] ?? '';
    if (!is_string($phoneInput)) $errors['telefono'] = 'Ingresa un teléfono válido.';
    elseif (trim($phoneInput) !== '' && !valid_phone(trim($phoneInput))) $errors['telefono'] = 'Escribe exactamente 8 dígitos, sin espacios ni prefijo.';
    if (!is_string($password) || strlen($password) < 10 || strlen($password) > 200) $errors['password'] = 'La contraseña debe tener de 10 a 200 caracteres.';
    if (!is_string($role) || !in_array($role, $allowedRoles, true)) $errors['rol'] = 'Selecciona Estudiante, Empresa o Administrador.';
    if ($errors) {
        remember_form_state('user_create', $limits, $errors, 'Corrige los campos marcados. Conservamos los demás datos para que no tengas que escribirlos otra vez.');
        redirect(app_url('roles.php'));
    }
    try {
        $pdo = db();
        $pdo->beginTransaction();
        $roleQuery = $pdo->prepare('SELECT id_rol FROM vw_roles_catalogo WHERE nombre=:role');
        $roleQuery->execute(['role' => $role]);
        $roleId = $roleQuery->fetchColumn();
        if (!$roleId) throw new DomainException('El perfil solicitado no está configurado.');
        $state = $role === 'empresa' ? 'pendiente': 'activo';
        set_audit_context((int)$actor['id']);
        $insertUser = $pdo->prepare('INSERT INTO usuarios(usuario,id_rol,nombres,apellidos,telefono,correo,password_hash,estado) VALUES(:username,:role,:first,:last,:phone,:email,:hash,:state)');
        $insertUser->execute(['username' => $username, 'role' => $roleId, 'first' => $first, 'last' => $last, 'phone' => $phone, 'email' => $email, 'hash' => password_hash($password, PASSWORD_DEFAULT), 'state' => $state]);
        $newUserId = (int)$pdo->lastInsertId();
        if ($role === 'estudiante') {
            // Crea un perfil estudiantil que se completa después.
            $profile = $pdo->prepare('INSERT INTO estudiantes(id_usuario,carnet,carrera,semestre,universidad) VALUES(:id,:carnet,:career,NULL,:university)');
            $profile->execute(['id' => $newUserId, 'carnet' => 'PEND-' . $newUserId, 'career' => 'Por completar', 'university' => 'Universidad Mariano Gálvez']);
        } elseif ($role === 'empresa') {
            // Usa los datos de contacto para crear el perfil empresarial.
            $companyName = 'Empresa de ' . $first . ' ' . $last;
            $profile = $pdo->prepare('INSERT INTO empresas(nombre_legal,nombre_comercial,correo_contacto,telefono,id_usuario) VALUES(:legal,:commercial,:contact,:phone,:id)');
            $profile->execute(['legal' => $companyName, 'commercial' => $companyName, 'contact' => $email, 'phone' => $phone, 'id' => $newUserId]);
        } elseif (in_array($role, ['administrador', 'superadministrador'], true)) {
            $profile = $pdo->prepare('INSERT INTO administradores(id_usuario,cargo) VALUES(:id,:cargo)');
            $profile->execute(['id' => $newUserId, 'cargo' => $role === 'superadministrador' ? 'Superadministración': 'Coordinación de prácticas']);
        }
        $pdo->commit();
        flash('La cuenta ' . $username . ' se creó con perfil ' . ['usuario' => 'Usuario', 'estudiante' => 'Estudiante', 'empresa' => 'Empresa', 'administrador' => 'Administrador', 'superadministrador' => 'Superadministrador'][$role] . ($role === 'empresa' ? ' y quedó pendiente de aprobación.': '.'));
    } catch (Throwable $e) {
        if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
        error_log($e->getMessage());
        $errors = [];
        if ($e instanceof PDOException && $e->getCode() === '23000') {
            $detail = (string)($e->errorInfo[2] ?? '');
            if (stripos($detail, 'correo') !== false) $errors['correo'] = 'Este correo ya está registrado.';
            elseif (stripos($detail, 'usuario') !== false) $errors['usuario'] = 'Este usuario ya existe; elige otro.';
            else $errors['usuario'] = 'Hay datos únicos duplicados. Revisa el usuario y el correo.';
        }
        remember_form_state('user_create', $limits, $errors, $e instanceof DomainException ? $e->getMessage(): 'No se pudo crear la cuenta. Revisa los campos marcados; los datos ingresados se conservaron.');
    }
    redirect(app_url('roles.php'));
}
$id = filter_var($_POST['id_usuario'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$role = $_POST['rol'] ?? '';
$allowedRoles = ['usuario', 'estudiante', 'empresa', 'administrador', 'superadministrador'];
if (!$id || !is_string($role) || !in_array($role, $allowedRoles, true) || (int)$id === (int)$actor['id']) {
    flash('Selecciona un usuario y un perfil válido. No puedes cambiar tu propio perfil.', 'error');
    redirect(app_url('roles.php'));
}
try {
    $pdo = db();
    $pdo->beginTransaction();
    // Bloquea la cuenta durante la transacción.
    $find = $pdo->prepare('SELECT id_usuario,nombres,apellidos,correo,telefono FROM usuarios WHERE id_usuario=:id FOR UPDATE');
    $find->execute(['id' => $id]);
    $target = $find->fetch();
    if (!$target) throw new DomainException('Usuario no encontrado.');
    $select = $pdo->prepare('SELECT id_rol FROM vw_roles_catalogo WHERE nombre=:role');
    $select->execute(['role' => $role]);
    $roleId = $select->fetchColumn();
    if (!$roleId) throw new DomainException('El perfil solicitado no está configurado en la base de datos.');
    // Crea el perfil del rol si aún no existe.
    if ($role === 'estudiante') {
        $profile = $pdo->prepare('SELECT id_estudiante FROM estudiantes WHERE id_usuario=:id');
        $profile->execute(['id' => $id]);
        if (!$profile->fetchColumn()) {
            $carnet = post_value('carnet', 40, true);
            $career = post_value('carrera', 150, true);
            $university = post_value('universidad', 180, true);
            if (!$carnet || !$career || !$university) throw new DomainException('Para asignar Estudiante completa carné, carrera y universidad en los datos de perfil.');
            if (!preg_match('/^[A-Za-z0-9._\/-]{1,40}$/', $carnet) || !preg_match('/^[\p{L}\p{M}0-9 .,&()\/-]+$/u', $career) || !preg_match('/^[\p{L}\p{M}0-9 .,&()\/-]+$/u', $university)) throw new DomainException('Revisa el formato de carné, carrera o universidad.');
            $insert = $pdo->prepare('INSERT INTO estudiantes(id_usuario,carnet,carrera,universidad) VALUES(:id,:carnet,:career,:university)');
            $insert->execute(['id' => $id, 'carnet' => $carnet, 'career' => $career, 'university' => $university]);
        }
    } elseif ($role === 'empresa') {
        $profile = $pdo->prepare('SELECT id_empresa FROM empresas WHERE id_usuario=:id');
        $profile->execute(['id' => $id]);
        if (!$profile->fetchColumn()) {
            $legalName = post_value('nombre_legal', 180, true);
            $contactEmail = post_value('correo_contacto', 254, true);
            if (!$legalName || !$contactEmail || !filter_var($contactEmail, FILTER_VALIDATE_EMAIL)) throw new DomainException('Para asignar Empresa completa el nombre legal y un correo de contacto válido en los datos de perfil.');
            $companyPhone = $target['telefono'] === null ? null: (string)$target['telefono'];
            if ($companyPhone !== null && !valid_phone($companyPhone)) throw new DomainException('Antes de convertir esta cuenta en empresa, actualiza su teléfono a exactamente 8 dígitos.');
            $insert = $pdo->prepare('INSERT INTO empresas(id_usuario,nombre_legal,nombre_comercial,correo_contacto,telefono) VALUES(:id,:legal,:commercial,:email,:phone)');
            $insert->execute(['id' => $id, 'legal' => $legalName, 'commercial' => $legalName, 'email' => $contactEmail, 'phone' => $companyPhone]);
            // Las empresas nuevas requieren aprobación.
            $pending = $pdo->prepare("UPDATE usuarios SET estado='pendiente' WHERE id_usuario=:id AND estado='activo'");
            $pending->execute(['id' => $id]);
        }
    } elseif (in_array($role, ['administrador', 'superadministrador'], true)) {
        $admin = $pdo->prepare('INSERT INTO administradores(id_usuario,cargo) VALUES(:id,:cargo) ON DUPLICATE KEY UPDATE cargo=VALUES(cargo)');
        $admin->execute(['id' => $id, 'cargo' => $role === 'superadministrador' ? 'Superadministración': 'Coordinación de prácticas']);
    }
    // Conserva los perfiles y registra el cambio de rol.
    set_audit_context((int)$actor['id']);
    $update = $pdo->prepare('UPDATE usuarios SET id_rol=:role WHERE id_usuario=:id');
    $update->execute(['role' => $roleId, 'id' => $id]);
    $pdo->commit();
    flash('Perfil actualizado a ' . ['usuario' => 'Usuario', 'estudiante' => 'Estudiante', 'empresa' => 'Empresa', 'administrador' => 'Administrador', 'superadministrador' => 'Superadministrador'][$role] . '.');
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    error_log($e->getMessage());
    flash($e instanceof DomainException ? $e->getMessage(): 'No se pudo actualizar el perfil. Revisa que los datos de perfil no estén duplicados.', 'error');
}
redirect(app_url('roles.php'));
