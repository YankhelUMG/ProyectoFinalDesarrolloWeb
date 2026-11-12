<?php
declare(strict_types = 1);
// Gestiona el perfil y el CV del estudiante.
require_once dirname(__DIR__, 2) . '/App.php';
$user = require_role('estudiante');
require_permission('usuarios.perfil_propio');
if (method_override() !== 'POST') {
    http_response_code(405);
    exit('Método no permitido.');
}
verify_csrf();
try {
    $phone = post_value('telefono', 8, true);
    if (!valid_phone($phone)) throw new DomainException('El teléfono debe tener exactamente 8 dígitos, sin espacios ni prefijo.');
    $carnet = post_value('carnet', 40, true);
    $career = post_value('carrera', 150, true);
    $semester = post_value('semestre', 40);
    $university = post_value('universidad', 180, true);
    if (!$carnet || !$career || !$university) throw new DomainException('Completa carné, carrera y universidad.');
    if (!preg_match('/^[A-Za-z0-9._\/-]{1,40}$/', $carnet) || !preg_match('/^[\p{L}\p{M}0-9 .,&()\/-]+$/u', $career) || !preg_match('/^[\p{L}\p{M}0-9 .,&()\/-]+$/u', $university) || ($semester && !preg_match('/^[\p{L}\p{M}0-9 .º°-]{1,40}$/u', $semester)) || (isset($_POST['semestre']) && is_string($_POST['semestre']) && mb_strlen(trim($_POST['semestre'])) > 40)) throw new DomainException('Revisa el formato o la longitud de carné, carrera, semestre o universidad.');
    $pdo = db();
    $pdo->beginTransaction();
    set_audit_context((int)$user['id']);
    $up = $pdo->prepare('UPDATE usuarios SET telefono=:phone WHERE id_usuario=:id');
    $up->execute(['phone' => $phone, 'id' => $user['id']]);
    $academic = $pdo->prepare('UPDATE estudiantes SET carnet=:carnet,carrera=:career,semestre=:semester,universidad=:university WHERE id_usuario=:id');
    $academic->execute(['carnet' => $carnet, 'career' => $career, 'semester' => $semester, 'university' => $university, 'id' => $user['id']]);
    if (isset($_FILES['cv']) && (int)($_FILES['cv']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        $file = $_FILES['cv'];
        if ((int)$file['error'] !== UPLOAD_ERR_OK || (int)$file['size'] > 5*1024*1024 || !is_uploaded_file($file['tmp_name'])) throw new DomainException('El CV supera 5 MB o no se pudo recibir.');
        $ext = strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION));
        $signature = file_get_contents($file['tmp_name'], false, null, 0, 8);
        $validPdf = $ext === 'pdf' && is_string($signature) && str_starts_with($signature, '%PDF-');
        $validDocx = $ext === 'docx' && is_string($signature) && str_starts_with($signature, "PK\x03\x04");
        $validDoc = $ext === 'doc' && is_string($signature) && $signature === "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1";
        if (!$validPdf && !$validDocx && !$validDoc) throw new DomainException('Adjunta un archivo Word (.doc/.docx) o PDF válido.');
        $folder = getenv('PRIVATE_UPLOAD_DIR') ?: (sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'practicas_private');
        if (!is_dir($folder) && !mkdir($folder, 0700, true)) throw new RuntimeException('No se pudo preparar almacenamiento privado.');
        $filename = bin2hex(random_bytes(24)) . '.' . $ext;
        $destination = rtrim($folder, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $filename;
        if (!move_uploaded_file($file['tmp_name'], $destination)) throw new RuntimeException('No se pudo guardar el CV.');
        $st = $pdo->prepare('UPDATE estudiantes SET cv_nombre=:name,cv_ruta=:path WHERE id_usuario=:id');
        $st->execute(['name' => substr(basename((string)$file['name']), 0, 255), 'path' => $destination, 'id' => $user['id']]);
    }
    $pdo->commit();
    flash('Perfil actualizado.');
} catch (DomainException $e) {
    if (db()->inTransaction()) db()->rollBack();
    $errors = str_contains(mb_strtolower($e->getMessage()), 'teléfono') ? ['telefono' => $e->getMessage()]: [];
    remember_form_state('student_profile', ['carnet' => 40, 'carrera' => 150, 'semestre' => 40, 'universidad' => 180, 'telefono' => 8], $errors, $e->getMessage());
    flash($e->getMessage(), 'error');
} catch (Throwable $e) {
    if (db()->inTransaction()) db()->rollBack();
    error_log($e->getMessage());
    remember_form_state('student_profile', ['carnet' => 40, 'carrera' => 150, 'semestre' => 40, 'universidad' => 180, 'telefono' => 8], [], 'No se pudo guardar el perfil. Conservamos los datos; inténtalo de nuevo.');
    flash('No se pudo guardar el perfil. Conservamos los datos; inténtalo de nuevo.', 'error');
}
redirect(app_url('perfil.php'));
