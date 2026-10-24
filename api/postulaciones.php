<?php
declare(strict_types=1);
require_once __DIR__ . '/core/App.php';
$method = method_override(); $pdo = db();

// Read routes return only the records allowed for the active profile.
if ($method === 'GET') {
    $user = require_role('estudiante','administrador','superadministrador','empresa');
    $params = [];
    $sql = 'SELECT p.id_postulacion,p.fecha_postulacion,p.carta_presentacion,p.observaciones,ep.codigo AS estado_codigo,ep.nombre AS estado,o.id_oportunidad,o.titulo AS oportunidad,o.modalidad,o.ubicacion,COALESCE(e.nombre_comercial,e.nombre_legal) AS empresa,u.nombres,u.apellidos,u.correo,est.carnet,est.carrera FROM postulaciones p JOIN estados_postulacion ep ON ep.id_estado=p.id_estado JOIN oportunidades o ON o.id_oportunidad=p.id_oportunidad JOIN empresas e ON e.id_empresa=o.id_empresa JOIN estudiantes est ON est.id_estudiante=p.id_estudiante JOIN usuarios u ON u.id_usuario=est.id_usuario WHERE ep.codigo<>\'borrado\'';
    if ($user['role'] === 'estudiante') { $sql .= ' AND est.id_usuario=:uid'; $params['uid'] = $user['id']; }
    if ($user['role'] === 'empresa') { $sql .= ' AND e.id_usuario=:uid'; $params['uid'] = $user['id']; }
    $sql .= ' ORDER BY p.fecha_postulacion DESC';
    $stmt = $pdo->prepare($sql); $stmt->execute($params); $rows = $stmt->fetchAll();
    header('Content-Type: application/json; charset=utf-8'); echo json_encode($rows, JSON_UNESCAPED_UNICODE); exit;
}
if (!in_array($method,['POST','PUT','DELETE'],true)) { http_response_code(405); header('Allow: GET, POST, PUT, DELETE'); exit('Método no permitido.'); }
verify_csrf();
try {
    // Students submit applications through the atomic duplicate-safe database procedure.
    if ($method === 'POST') {
        $student = require_role('estudiante');
        $opportunity = filter_var($_POST['id_oportunidad'] ?? null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
        $letter = post_value('carta_presentacion',3000);
        if (!$opportunity) throw new DomainException('La oportunidad indicada no es válida.');
        $stmt=$pdo->prepare('SELECT e.id_estudiante,e.cv_ruta,u.telefono FROM estudiantes e JOIN usuarios u ON u.id_usuario=e.id_usuario WHERE e.id_usuario=:uid'); $stmt->execute(['uid'=>$student['id']]); $profile=$stmt->fetch();
        if (!$profile) throw new DomainException('Completa primero tu perfil de estudiante.');
        if (!$profile['cv_ruta'] || !$profile['telefono']) throw new DomainException('Antes de postularte agrega tu teléfono y CV en Mi perfil.');
        set_audit_context($student['id']);
        $call=$pdo->prepare('CALL sp_postular(:student,:opportunity,:cv,:letter)');
        $call->execute(['student'=>$profile['id_estudiante'],'opportunity'=>$opportunity,'cv'=>$profile['cv_ruta'],'letter'=>$letter]);
        while ($call->nextRowset()) {} $call->closeCursor();
        flash('Tu postulación se registró y ya aparece en tu seguimiento.'); redirect(app_url('seguimiento.php'));
    }
    // Students can withdraw their own applications while they remain in early review stages.
    if ($method === 'DELETE') {
        $student=require_role('estudiante'); $id=filter_var($_POST['id_postulacion'] ?? null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
        if (!$id) throw new DomainException('La postulación indicada no es válida.');
        $state=$pdo->prepare("SELECT id_estado FROM estados_postulacion WHERE codigo='retirada'"); $state->execute(); $stateId=$state->fetchColumn();
        set_audit_context($student['id']);
        $stmt=$pdo->prepare("UPDATE postulaciones p JOIN estudiantes e ON e.id_estudiante=p.id_estudiante JOIN estados_postulacion ep ON ep.id_estado=p.id_estado SET p.id_estado=:state WHERE p.id_postulacion=:id AND e.id_usuario=:uid AND ep.codigo IN ('recibida','en_revision')");
        $stmt->execute(['state'=>$stateId,'id'=>$id,'uid'=>$student['id']]);
        if (!$stmt->rowCount()) throw new DomainException('Solo puedes retirar una postulación tuya que esté recibida o en revisión.');
        flash('Retiraste tu postulación. El registro se conservó para mantener el historial.'); redirect(app_url('seguimiento.php'));
    }
    // Coordinators see all applications; a company can update only its own opportunities.
    $actor=require_role('administrador','superadministrador','empresa'); $id=filter_var($_POST['id_postulacion'] ?? null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
    $state=post_value('estado',30,true); $comment=post_value('observaciones',1000);
    if (!$id || !in_array($state,['en_revision','entrevista','aceptada','rechazada'],true)) throw new DomainException('Selecciona una postulación y un estado válidos.');
    if ($actor['role'] === 'empresa') {
        $ownership=$pdo->prepare('SELECT p.id_postulacion FROM postulaciones p JOIN oportunidades o ON o.id_oportunidad=p.id_oportunidad JOIN empresas e ON e.id_empresa=o.id_empresa WHERE p.id_postulacion=:id AND e.id_usuario=:uid');
        $ownership->execute(['id'=>$id,'uid'=>$actor['id']]);
        if (!$ownership->fetchColumn()) throw new DomainException('Esa postulación no pertenece a tu empresa.');
    }
    $set=$pdo->prepare('SET @app_user_id=:uid,@app_ip=:ip,@app_comentario=:comment');
    $set->execute(['uid'=>$actor['id'],'ip'=>substr((string)($_SERVER['REMOTE_ADDR']??''),0,45),'comment'=>$comment]);
    $call=$pdo->prepare('CALL sp_cambiar_estado_postulacion(:id,:state,:comment)');
    $call->execute(['id'=>$id,'state'=>$state,'comment'=>$comment]); while ($call->nextRowset()) {} $call->closeCursor();
    flash('El estado de la postulación y su trazabilidad se actualizaron.'); redirect(app_url(in_array($actor['role'],['administrador','superadministrador'],true)?'admin.php#postulaciones':'empresa.php'));
} catch (DomainException $e) { flash($e->getMessage(),'error'); $role=current_user()['role']??''; redirect(app_url(in_array($role,['administrador','superadministrador'],true)?'admin.php#postulaciones':($role==='empresa'?'empresa.php':'seguimiento.php'))); }
catch (PDOException $e) {
    error_log($e->getMessage());
    $msg = str_contains($e->getMessage(),'Duplicate entry') ? 'Ya te postulaste a esta oportunidad.' : 'No se pudo procesar la solicitud. Verifica que siga vigente y que no exista una postulación previa.';
    flash($msg,'error'); $role=current_user()['role']??''; redirect(app_url($role==='empresa'?'empresa.php':($role==='administrador'?'admin.php#postulaciones':'seguimiento.php')));
} catch (Throwable $e) {
    error_log($e->getMessage());
    flash('No se pudo completar la operación.','error');
    $role=current_user()['role']??'';
    redirect(app_url($role==='empresa'?'empresa.php':($role==='administrador'?'admin.php#postulaciones':($role==='estudiante'?'seguimiento.php':'index.php'))));
}

