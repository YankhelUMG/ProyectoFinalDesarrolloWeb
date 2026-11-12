<?php
declare(strict_types=1);
require_once __DIR__ . '/core/App.php';
$method = method_override(); $pdo = db();

// Read routes return only the records allowed for the active profile.
if ($method === 'GET') {
    $user = require_role('estudiante','administrador','superadministrador','empresa');
    $params = [];
    if ($user['role'] === 'estudiante') { require_permission('postulaciones.consultar_propias'); $sql='SELECT id_postulacion,fecha_postulacion,carta_presentacion,observaciones,estado_codigo,estado,id_oportunidad,oportunidad,modalidad,ubicacion,empresa FROM vw_seguimiento_postulaciones WHERE id_usuario=:uid'; $params['uid']=$user['id']; }
    elseif ($user['role'] === 'empresa') { require_permission('postulaciones.consultar_todas'); $sql='SELECT id_postulacion,fecha_postulacion,carta_presentacion,observaciones,estado_codigo,estado,id_oportunidad,oportunidad,empresa,nombres,apellidos,correo,telefono,carnet,carrera FROM vw_empresa_postulaciones WHERE id_usuario_empresa=:uid'; $params['uid']=$user['id']; }
    else { require_permission('postulaciones.consultar_todas'); $sql='SELECT id_postulacion,fecha_postulacion,carta_presentacion,observaciones,estado_codigo AS estado,oportunidad,empresa,nombres,apellidos,correo,telefono,carnet,carrera FROM vw_admin_postulaciones WHERE 1=1'; }
    $sql .= ' ORDER BY fecha_postulacion DESC';
    $stmt = $pdo->prepare($sql); $stmt->execute($params); $rows = $stmt->fetchAll();
    header('Content-Type: application/json; charset=utf-8'); echo json_encode($rows, JSON_UNESCAPED_UNICODE); exit;
}
if (!in_array($method,['POST','PUT','DELETE'],true)) { http_response_code(405); header('Allow: GET, POST, PUT, DELETE'); exit('Método no permitido.'); }
verify_csrf();
try {
    // Students submit applications through the atomic duplicate-safe database procedure.
    if ($method === 'POST') {
        $student = require_role('estudiante');
        require_permission('postulaciones.crear');
        $opportunity = filter_var($_POST['id_oportunidad'] ?? null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
        $letter = post_value('carta_presentacion',3000);
        if(isset($_POST['carta_presentacion'])&&is_string($_POST['carta_presentacion'])&&mb_strlen(trim($_POST['carta_presentacion']))>3000)throw new DomainException('La carta de presentación admite hasta 3,000 caracteres.');
        if (!$opportunity) throw new DomainException('La oportunidad indicada no es válida.');
        $stmt=$pdo->prepare('SELECT id_estudiante,cv_ruta,telefono FROM vw_estudiante_perfil WHERE id_usuario=:uid'); $stmt->execute(['uid'=>$student['id']]); $profile=$stmt->fetch();
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
        $student=require_role('estudiante'); require_permission('postulaciones.consultar_propias'); $id=filter_var($_POST['id_postulacion'] ?? null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
        if (!$id) throw new DomainException('La postulación indicada no es válida.');
        $state=$pdo->prepare("SELECT id_estado FROM vw_estados_postulacion WHERE codigo='retirada'"); $state->execute(); $stateId=$state->fetchColumn();
        set_audit_context($student['id']);
        $stmt=$pdo->prepare("UPDATE postulaciones p JOIN estudiantes e ON e.id_estudiante=p.id_estudiante JOIN estados_postulacion ep ON ep.id_estado=p.id_estado SET p.id_estado=:state WHERE p.id_postulacion=:id AND e.id_usuario=:uid AND ep.codigo IN ('recibida','en_revision')");
        $stmt->execute(['state'=>$stateId,'id'=>$id,'uid'=>$student['id']]);
        if (!$stmt->rowCount()) throw new DomainException('Solo puedes retirar una postulación tuya que esté recibida o en revisión.');
        flash('Retiraste tu postulación. El registro se conservó para mantener el historial.'); redirect(app_url('seguimiento.php'));
    }
    // Only the company that published the opportunity decides on its applications.
    $actor=require_role('empresa'); require_permission('postulaciones.cambiar_estado'); $id=filter_var($_POST['id_postulacion'] ?? null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
    $state=post_value('estado',30,true); $comment=post_value('observaciones',500);
    if(isset($_POST['observaciones'])&&is_string($_POST['observaciones'])&&mb_strlen(trim($_POST['observaciones']))>500)throw new DomainException('Las observaciones admiten hasta 500 caracteres.');
    if (!$id || !in_array($state,['en_revision','entrevista','aceptada','rechazada'],true)) throw new DomainException('Selecciona una postulación y un estado válidos.');
    if ($state==='rechazada' && !$comment) throw new DomainException('Escribe el motivo del rechazo para que el estudiante pueda corregir o entender la decisión.');
    $companyOpportunityId=null;
    $ownership=$pdo->prepare('SELECT o.id_oportunidad,o.estado_panel AS estado FROM vw_empresa_postulaciones p JOIN vw_empresa_oportunidades o ON o.id_oportunidad=p.id_oportunidad WHERE p.id_postulacion=:id AND p.id_usuario_empresa=:uid');
    $ownership->execute(['id'=>$id,'uid'=>$actor['id']]);
    $companyOpportunity=$ownership->fetch();
    $companyOpportunityId=$companyOpportunity['id_oportunidad']??null;
    if (!$companyOpportunityId) throw new DomainException('Esa postulación no pertenece a tu empresa.');
    if ($companyOpportunity['estado']!=='publicada') throw new DomainException('La convocatoria ya se cerró; sus postulaciones ya no se pueden modificar.');
    $current=$pdo->prepare('SELECT estado_codigo FROM vw_empresa_postulaciones WHERE id_postulacion=:id AND id_usuario_empresa=:uid');
    $current->execute(['id'=>$id,'uid'=>$actor['id']]);
    if (!in_array($current->fetchColumn(),['recibida','en_revision','entrevista'],true)) throw new DomainException('Esta postulación ya tiene una decisión final o fue retirada.');
    $set=$pdo->prepare('SET @app_user_id=:uid,@app_ip=:ip,@app_comentario=:comment');
    $set->execute(['uid'=>$actor['id'],'ip'=>substr((string)($_SERVER['REMOTE_ADDR']??''),0,45),'comment'=>$comment]);
    $call=$pdo->prepare('CALL sp_cambiar_estado_postulacion(:id,:state,:comment)');
    $call->execute(['id'=>$id,'state'=>$state,'comment'=>$comment]); while ($call->nextRowset()) {} $call->closeCursor();
    flash('El estado de la postulación y su trazabilidad se actualizaron.'); redirect(app_url('empresa.php?oportunidad='.(int)$companyOpportunityId.'#postulaciones'));
} catch (DomainException $e) { flash($e->getMessage(),'error'); $role=current_user()['role']??''; redirect(app_url(in_array($role,['administrador','superadministrador'],true)?'admin.php#postulaciones':($role==='empresa'?'empresa.php'.(isset($companyOpportunityId)?'?oportunidad='.(int)$companyOpportunityId:'').'#postulaciones':'seguimiento.php'))); }
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

