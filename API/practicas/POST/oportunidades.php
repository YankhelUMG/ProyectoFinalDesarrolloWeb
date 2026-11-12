<?php
declare(strict_types = 1);
// Gestiona oportunidades, cambios y revisiones de coordinación.
require_once dirname(__DIR__, 2) . '/App.php';
$method = method_override();
$pdo = db();
$user = current_user();
// La consulta pública muestra oportunidades aprobadas.
if ($method === 'GET') {
    header('Content-Type: application/json; charset=utf-8');
    if ($user) require_permission('oportunidades.consultar');
    if (isset($_GET['id']) && ctype_digit((string)$_GET['id'])) {
        $stmt = $pdo->prepare('SELECT * FROM vw_oportunidades_publicadas WHERE id_oportunidad=:id');
        $stmt->execute(['id' => (int)$_GET['id']]);
        $row = $stmt->fetch();
        http_response_code($row ? 200: 404);
        echo json_encode($row ?: ['error' => 'Oportunidad no encontrada'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $where = ['1=1'];
    $params = [];
    if (!empty($_GET['q']) && is_string($_GET['q'])) {
        $where[] = '(titulo LIKE :q1 OR descripcion LIKE :q2 OR empresa LIKE :q3 OR palabras_clave LIKE :q4)';
        $like = '%' . trim($_GET['q']) . '%';
        $params = ['q1' => $like, 'q2' => $like, 'q3' => $like, 'q4' => $like];
    }
    if (isset($_GET['modalidad']) && in_array($_GET['modalidad'], ['presencial', 'remota', 'hibrida'], true)) {
        $where[] = 'modalidad=:modalidad';
        $params['modalidad'] = $_GET['modalidad'];
    }
    $stmt = $pdo->prepare('SELECT id_oportunidad,titulo,descripcion,requisitos,ubicacion,modalidad,tipo_practica,remuneracion,moneda,vacantes,fecha_inicio,fecha_fin,fecha_limite_postulacion,empresa FROM vw_oportunidades_publicadas WHERE ' . implode(' AND ', $where) . ' ORDER BY publicada_en DESC');
    $stmt->execute($params);
    echo json_encode($stmt->fetchAll(), JSON_UNESCAPED_UNICODE);
    exit;
}
if (!in_array($method, ['POST', 'PUT', 'DELETE'], true)) {
    http_response_code(405);
    header('Allow: GET, POST, PUT, DELETE');
    exit('Método no permitido.');
}
verify_csrf();
try {
    // Cada empresa gestiona sus oportunidades; coordinación aprueba la publicación.
    if ($method === 'POST' || (in_array($method, ['PUT', 'DELETE'], true) && ($user['role'] ?? '') === 'empresa')) {
        $companyUser = require_role('empresa');
        require_permission('oportunidades.gestionar_propias');
        $companyQuery = $pdo->prepare("SELECT id_empresa FROM vw_empresa_perfil WHERE id_usuario=:uid AND estado_cuenta='activo'");
        $companyQuery->execute(['uid' => $companyUser['id']]);
        $companyId = (int)$companyQuery->fetchColumn();
        if (!$companyId) throw new DomainException('Tu empresa debe estar aprobada para publicar oportunidades.');
        $id = filter_var($_POST['id_oportunidad'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($method === 'PUT' && !$id) throw new DomainException('La oportunidad indicada no es válida.');
        if ($method === 'PUT' && ($_POST['decision'] ?? '') === 'cerrar') {
            // La empresa cierra sus oportunidades tras revisar las postulaciones.
            $pdo->beginTransaction();
            $find = $pdo->prepare('SELECT titulo,estado FROM oportunidades WHERE id_oportunidad=:id AND id_empresa=:company FOR UPDATE');
            $find->execute(['id' => $id, 'company' => $companyId]);
            $opportunity = $find->fetch();
            if (!$opportunity || $opportunity['estado'] !== 'publicada') throw new DomainException('La convocatoria no existe o ya está cerrada.');
            $pending = $pdo->prepare("SELECT COUNT(*) FROM vw_empresa_postulaciones WHERE id_oportunidad=:id AND estado_codigo NOT IN ('aceptada','rechazada','retirada','borrado')");
            $pending->execute(['id' => $id]);
            if ((int)$pending->fetchColumn() > 0) throw new DomainException('Revisa todas las postulaciones: acepta o rechaza cada perfil antes de cerrar la convocatoria.');
            set_audit_context((int)$companyUser['id']);
            $close = $pdo->prepare("UPDATE oportunidades SET estado='cerrada' WHERE id_oportunidad=:id AND id_empresa=:company AND estado='publicada'");
            $close->execute(['id' => $id, 'company' => $companyId]);
            $pdo->commit();
            flash('Convocatoria cerrada.');
            redirect(app_url('empresa.php?oportunidad=' . (int)$id . '#postulaciones'));
        }
        if ($method === 'DELETE') {
            if (!$id) throw new DomainException('La oportunidad indicada no es válida.');
            set_audit_context((int)$companyUser['id']);
            $remove = $pdo->prepare("UPDATE oportunidades SET estado='borrado' WHERE id_oportunidad=:id AND id_empresa=:company AND estado='borrador'");
            $remove->execute(['id' => $id, 'company' => $companyId]);
            if (!$remove->rowCount()) throw new DomainException('Solo puedes retirar un borrador. Una solicitud rechazada queda cerrada y no se puede editar ni retirar.');
            flash('Oportunidad retirada; el historial y sus postulaciones se conservaron.');
            redirect(app_url('empresa.php#oportunidades'));
        }
        $title = post_value('titulo', 200, true);
        $desc = post_value('descripcion', 20000, true);
        $req = post_value('requisitos', 10000);
        $location = post_value('ubicacion', 200);
        $mode = post_value('modalidad', 20, true);
        $type = post_value('tipo_practica', 100);
        $vacancies = filter_var($_POST['vacantes'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 65535]]);
        $salaryText = is_string($_POST['remuneracion'] ?? null) ? trim($_POST['remuneracion']): '';
        $salary = $salaryText === '' ? null: filter_var($salaryText, FILTER_VALIDATE_FLOAT);
        $currency = post_value('moneda', 3, true);
        $start = post_value('fecha_inicio', 10);
        $end = post_value('fecha_fin', 10);
        $deadline = post_value('fecha_limite_postulacion', 10, true);
        $keywordsText = post_value('palabras_clave', 1500) ?? '';
        $fieldLimits = ['titulo' => 200, 'descripcion' => 20000, 'requisitos' => 10000, 'ubicacion' => 200, 'modalidad' => 20, 'tipo_practica' => 100, 'vacantes' => 5, 'remuneracion' => 20, 'moneda' => 3, 'fecha_inicio' => 10, 'fecha_fin' => 10, 'fecha_limite_postulacion' => 10, 'palabras_clave' => 1500];
        $errors = [];
        // Valida también el límite de los campos opcionales.
        foreach ($fieldLimits as $field => $max) {
            $raw = $_POST[$field] ?? null;
            if (is_string($raw) && mb_strlen(trim($raw)) > $max) $errors[$field] = 'Este campo admite hasta ' . $max . ' caracteres.';
        }
        if (!$title) $errors['titulo'] = 'Escribe el título de la oportunidad.';
        if (!$desc) $errors['descripcion'] = 'Escribe una descripción de la práctica.';
        if ($vacancies === false) $errors['vacantes'] = 'Ingresa un número entero de 1 a 65,535.';
        if (!in_array($mode, ['presencial', 'remota', 'hibrida'], true)) $errors['modalidad'] = 'Selecciona una modalidad válida.';
        if (!$currency || !in_array($currency, ['GTQ', 'USD', 'EUR'], true)) $errors['moneda'] = 'Selecciona quetzales (GTQ), dólares estadounidenses (USD) o euros (EUR).';
        if ($salaryText !== '' && (!preg_match('/^\d{1,10}(?:\.\d{1,2})?$/', $salaryText) || $salary === false || $salary < 0 || $salary > 9999999999.99)) $errors['remuneracion'] = 'Ingresa una cantidad positiva válida con máximo dos decimales.';
        $validDate = static function (?string $date): bool {
            return $date !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1 && checkdate((int)substr($date, 5, 2), (int)substr($date, 8, 2), (int)substr($date, 0, 4));
        };
        foreach (['fecha_inicio' => $start, 'fecha_fin' => $end] as $field => $date) {
            $raw = $_POST[$field] ?? '';
            if (is_string($raw) && trim($raw) !== '' && !$validDate($date)) $errors[$field] = 'Ingresa una fecha válida.';
        }
        if (!$deadline) $errors['fecha_limite_postulacion'] = 'Selecciona la fecha límite de postulación.';
        elseif (!$validDate($deadline)) $errors['fecha_limite_postulacion'] = 'Ingresa una fecha límite válida.';
        elseif ($deadline < date('Y-m-d')) $errors['fecha_limite_postulacion'] = 'La fecha límite debe ser hoy o una fecha futura.';
        if ($start && $end && $validDate($start) && $validDate($end) && $end < $start) $errors['fecha_fin'] = 'La fecha de fin debe ser igual o posterior a la fecha de inicio.';
        // Normaliza las etiquetas para mantener búsquedas consistentes.
        $keywords = [];
        foreach (preg_split('/[,;\r\n]+/u', $keywordsText) ?: [] as $keyword) {
            $keyword = trim($keyword);
            if ($keyword === '') continue;
            if (mb_strlen($keyword) > 100 || !preg_match('/^[\p{L}\p{M}\p{N}][\p{L}\p{M}\p{N} .+#&\/-]*$/u', $keyword)) {
                $errors['palabras_clave'] = 'Cada palabra clave admite hasta 100 letras, números, espacios o los símbolos . + # & / -.';
                break;
            }
            $normalized = mb_strtolower($keyword);
            $keywords[$normalized] = $keyword;
        }
        if (count($keywords) > 15) $errors['palabras_clave'] = 'Agrega como máximo 15 palabras clave.';
        if ($errors) {
            remember_form_state('opportunity', $fieldLimits, $errors, 'Corrige los campos marcados antes de enviar la oportunidad.');
            redirect(app_url('empresa.php#oportunidades'));
        }
        $pdo->beginTransaction();
        set_audit_context((int)$companyUser['id']);
        $values = ['company' => $companyId, 'title' => $title, 'description' => $desc, 'requirements' => $req, 'location' => $location, 'mode' => $mode, 'type' => $type, 'salary' => $salary, 'currency' => $currency, 'vacancies' => $vacancies, 'start' => $start, 'end' => $end, 'deadline' => $deadline];
        if ($method === 'POST') {
            $stmt = $pdo->prepare("INSERT INTO oportunidades(id_empresa,id_administrador,titulo,descripcion,requisitos,ubicacion,modalidad,tipo_practica,remuneracion,moneda,vacantes,fecha_inicio,fecha_fin,fecha_limite_postulacion,estado) VALUES(:company,NULL,:title,:description,:requirements,:location,:mode,:type,:salary,:currency,:vacancies,:start,:end,:deadline,'pendiente_revision')");
            $stmt->execute($values);
            $id = (int)$pdo->lastInsertId();
            flash('La oportunidad se envió a revisión. Será pública cuando coordinación la apruebe.');
        } else {
            $values['id'] = $id;
            $stmt = $pdo->prepare("UPDATE oportunidades SET titulo=:title,descripcion=:description,requisitos=:requirements,ubicacion=:location,modalidad=:mode,tipo_practica=:type,remuneracion=:salary,moneda=:currency,vacantes=:vacancies,fecha_inicio=:start,fecha_fin=:end,fecha_limite_postulacion=:deadline,estado='pendiente_revision',observaciones_coordinacion=NULL,id_administrador=NULL WHERE id_oportunidad=:id AND id_empresa=:company AND estado='requiere_cambios'");
            $stmt->execute($values);
            if (!$stmt->rowCount()) throw new DomainException('Solo puedes editar una práctica cuando coordinación solicita cambios.');
            flash('Cambios enviados a revisión.');
        }
        $deactivate = $pdo->prepare("UPDATE palabras_clave SET estado='borrado' WHERE id_oportunidad=:id");
        $deactivate->execute(['id' => $id]);
        $upsertKeyword = $pdo->prepare("INSERT INTO palabras_clave(id_oportunidad,nombre,nombre_busqueda,categoria,estado) VALUES(:id,:name,:normalized,'otro','activo') ON DUPLICATE KEY UPDATE nombre=VALUES(nombre),estado='activo'");
        foreach ($keywords as $normalized => $keyword) $upsertKeyword->execute(['id' => $id, 'name' => $keyword, 'normalized' => $normalized]);
        $pdo->commit();
        redirect(app_url('empresa.php#oportunidades'));
    }
    // Solo coordinación puede revisar o cerrar oportunidades.
    $admin = require_role('administrador', 'superadministrador');
    require_permission('oportunidades.aprobar');
    $id = filter_var($_POST['id_oportunidad'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if (!$id) throw new DomainException('La oportunidad indicada no es válida.');
    $review = $pdo->prepare('SELECT estado,fecha_limite_postulacion FROM vw_admin_oportunidades WHERE id_oportunidad=:id');
    $review->execute(['id' => $id]);
    $reviewRow = $review->fetch();
    if (!$reviewRow) throw new DomainException('La oportunidad no existe.');
    // Compatibilidad con filas creadas antes de que estado tuviera valor predeterminado.
    $oldState = ($reviewRow['estado'] === null || $reviewRow['estado'] === '') ? 'pendiente_revision': $reviewRow['estado'];
    set_audit_context((int)$admin['id']);
    if ($method === 'PUT') {
        $decision = $_POST['decision'] ?? '';
        if ($decision === 'publicar' && $oldState === 'pendiente_revision') {
            if ($reviewRow['fecha_limite_postulacion'] < date('Y-m-d')) throw new DomainException('La fecha límite ya pasó; pide a la empresa que corrija la publicación.');
            $adminId = $pdo->prepare('SELECT id_administrador FROM administradores WHERE id_usuario=:uid');
            $adminId->execute(['uid' => $admin['id']]);
            $administratorId = $adminId->fetchColumn();
            if (!$administratorId) throw new DomainException('Tu usuario no tiene perfil de coordinación.');
            $reviewComment = post_value('comentario_revision', 2000);
            $stmt = $pdo->prepare("UPDATE oportunidades SET estado='publicada',id_administrador=:admin,publicada_en=NOW(),observaciones_coordinacion=:comment WHERE id_oportunidad=:id AND (estado='pendiente_revision' OR estado='' OR estado IS NULL)");
            $stmt->execute(['admin' => $administratorId, 'comment' => $reviewComment, 'id' => $id]);
            flash('Oportunidad aprobada y publicada.');
        } elseif (in_array($decision, ['rechazar', 'solicitar_cambios'], true) && $oldState === 'pendiente_revision') {
            $reviewComment = post_value('comentario_revision', 2000, true);
            if (!$reviewComment) throw new DomainException('Escribe un comentario para indicar a la empresa qué debe corregir.');
            $nextState = $decision === 'solicitar_cambios' ? 'requiere_cambios': 'rechazada';
            $stmt = $pdo->prepare("UPDATE oportunidades SET estado=:state,observaciones_coordinacion=:comment WHERE id_oportunidad=:id AND (estado='pendiente_revision' OR estado='' OR estado IS NULL)");
            $stmt->execute(['state' => $nextState, 'comment' => $reviewComment, 'id' => $id]);
            flash($decision === 'solicitar_cambios' ? 'Se solicitaron cambios y el comentario quedó visible para la empresa.': 'Oportunidad rechazada con el comentario enviado a la empresa.');
        } elseif ($decision === 'cerrar' && $oldState === 'publicada') {
            $pending = $pdo->prepare("SELECT COUNT(*) FROM vw_admin_postulaciones WHERE id_oportunidad=:id AND estado NOT IN ('aceptada','rechazada','retirada','borrado')");
            $pending->execute(['id' => $id]);
            if ((int)$pending->fetchColumn() > 0) throw new DomainException('Revisa todas las postulaciones antes de cerrar la convocatoria.');
            $stmt = $pdo->prepare("UPDATE oportunidades SET estado='cerrada' WHERE id_oportunidad=:id AND estado='publicada'");
            $stmt->execute(['id' => $id]);
            flash('Convocatoria cerrada.');
        } else throw new DomainException('La acción no aplica al estado actual.');
    } elseif ($method === 'DELETE') {
        $stmt = $pdo->prepare("UPDATE oportunidades SET estado='borrado' WHERE id_oportunidad=:id AND estado<>'borrado'");
        $stmt->execute(['id' => $id]);
        flash('Oportunidad retirada y su historial conservado.');
    }
    redirect(app_url('admin.php#oportunidades'));
} catch (DomainException $e) {
    $role = current_user()['role'] ?? '';
    if ($role === 'empresa' && ($_POST['decision'] ?? '') === 'cerrar') {
        if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
        flash($e->getMessage(), 'error');
        $closeId = filter_var($_POST['id_oportunidad'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        redirect(app_url('empresa.php' . ($closeId ? '?oportunidad=' . $closeId: '') . '#postulaciones'));
    }
    if ($role === 'empresa' && in_array($method, ['POST', 'PUT'], true)) {
        remember_form_state('opportunity', ['titulo' => 200, 'descripcion' => 20000, 'requisitos' => 10000, 'ubicacion' => 200, 'modalidad' => 20, 'tipo_practica' => 100, 'vacantes' => 5, 'remuneracion' => 20, 'moneda' => 3, 'fecha_inicio' => 10, 'fecha_fin' => 10, 'fecha_limite_postulacion' => 10, 'palabras_clave' => 1500], [], $e->getMessage());
        redirect(app_url('empresa.php#oportunidades'));
    }
    flash($e->getMessage(), 'error');
    redirect(app_url($role === 'empresa' ? 'empresa.php#oportunidades': 'admin.php#oportunidades'));
} catch (Throwable $e) {
    error_log($e->getMessage());
    $role = current_user()['role'] ?? '';
    if ($role === 'empresa' && ($_POST['decision'] ?? '') === 'cerrar') {
        if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
        flash('No se pudo cerrar la convocatoria. Inténtalo de nuevo.', 'error');
        $closeId = filter_var($_POST['id_oportunidad'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        redirect(app_url('empresa.php' . ($closeId ? '?oportunidad=' . $closeId: '') . '#postulaciones'));
    }
    if ($role === 'empresa' && in_array($method, ['POST', 'PUT'], true)) {
        remember_form_state('opportunity', ['titulo' => 200, 'descripcion' => 20000, 'requisitos' => 10000, 'ubicacion' => 200, 'modalidad' => 20, 'tipo_practica' => 100, 'vacantes' => 5, 'remuneracion' => 20, 'moneda' => 3, 'fecha_inicio' => 10, 'fecha_fin' => 10, 'fecha_limite_postulacion' => 10, 'palabras_clave' => 1500], [], 'No se guardó la oportunidad por un error del servidor. Conservamos tus datos; inténtalo de nuevo o contacta a coordinación.');
        redirect(app_url('empresa.php#oportunidades'));
    }
    flash('No se pudo procesar la oportunidad. Verifica los datos y la configuración.', 'error');
    redirect(app_url($role === 'empresa' ? 'empresa.php#oportunidades': 'admin.php#oportunidades'));
}
