<?php
declare(strict_types=1);
require_once __DIR__ . '/core/App.php';

$method = method_override();
$pdo = db();
$user = current_user();

// Public GET exposes only opportunities approved by coordination.
if ($method === 'GET') {
    header('Content-Type: application/json; charset=utf-8');
    if (isset($_GET['id']) && ctype_digit((string)$_GET['id'])) {
        $stmt = $pdo->prepare('SELECT * FROM vw_oportunidades_publicadas WHERE id_oportunidad=:id');
        $stmt->execute(['id' => (int)$_GET['id']]);
        $row = $stmt->fetch();
        http_response_code($row ? 200 : 404);
        echo json_encode($row ?: ['error' => 'Oportunidad no encontrada'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $where = ["o.estado='publicada'", 'o.fecha_limite_postulacion>=CURRENT_DATE'];
    $params = [];
    if (!empty($_GET['q']) && is_string($_GET['q'])) {
        $where[] = '(o.titulo LIKE :q1 OR o.descripcion LIKE :q2 OR e.nombre_legal LIKE :q3 OR e.nombre_comercial LIKE :q4)';
        $like = '%' . trim($_GET['q']) . '%';
        $params = ['q1'=>$like,'q2'=>$like,'q3'=>$like,'q4'=>$like];
    }
    if (isset($_GET['modalidad']) && in_array($_GET['modalidad'], ['presencial','remota','hibrida'], true)) {
        $where[] = 'o.modalidad=:modalidad'; $params['modalidad'] = $_GET['modalidad'];
    }
    $stmt = $pdo->prepare('SELECT o.id_oportunidad,o.titulo,o.descripcion,o.requisitos,o.ubicacion,o.modalidad,o.tipo_practica,o.remuneracion,o.moneda,o.vacantes,o.fecha_inicio,o.fecha_fin,o.fecha_limite_postulacion,COALESCE(e.nombre_comercial,e.nombre_legal) AS empresa FROM oportunidades o JOIN empresas e ON e.id_empresa=o.id_empresa WHERE ' . implode(' AND ', $where) . ' ORDER BY o.publicada_en DESC');
    $stmt->execute($params); echo json_encode($stmt->fetchAll(), JSON_UNESCAPED_UNICODE); exit;
}
if (!in_array($method, ['POST','PUT','DELETE'], true)) { http_response_code(405); header('Allow: GET, POST, PUT, DELETE'); exit('Método no permitido.'); }
verify_csrf();

try {
    // Companies can submit/edit their own listings; publication always requires administrator review.
    if ($method === 'POST' || (in_array($method,['PUT','DELETE'],true) && ($user['role'] ?? '') === 'empresa')) {
        $companyUser = require_role('empresa');
        $companyQuery = $pdo->prepare('SELECT e.id_empresa FROM empresas e JOIN usuarios u ON u.id_usuario=e.id_usuario WHERE e.id_usuario=:uid AND u.estado=\'activo\'');
        $companyQuery->execute(['uid' => $companyUser['id']]); $companyId = (int)$companyQuery->fetchColumn();
        if (!$companyId) throw new DomainException('Tu empresa debe estar aprobada para publicar oportunidades.');
        $id = filter_var($_POST['id_oportunidad'] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
        if ($method === 'PUT' && !$id) throw new DomainException('La oportunidad indicada no es válida.');
        if ($method === 'DELETE') {
            if (!$id) throw new DomainException('La oportunidad indicada no es válida.');
            set_audit_context((int)$companyUser['id']);
            $remove=$pdo->prepare("UPDATE oportunidades SET estado='borrado' WHERE id_oportunidad=:id AND id_empresa=:company AND estado IN ('pendiente_revision','rechazada','borrador')");
            $remove->execute(['id'=>$id,'company'=>$companyId]);
            if (!$remove->rowCount()) throw new DomainException('Solo puedes retirar una oportunidad tuya que aún no está publicada.');
            flash('Oportunidad retirada; el historial y sus postulaciones se conservaron.'); redirect(app_url('empresa.php#oportunidades'));
        }
        $title=post_value('titulo',200,true); $desc=post_value('descripcion',20000,true); $req=post_value('requisitos',10000);
        $location=post_value('ubicacion',200); $mode=post_value('modalidad',20,true); $type=post_value('tipo_practica',100);
        $vacancies=filter_var($_POST['vacantes']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>65535]]);
        $salaryText=is_string($_POST['remuneracion']??null)?trim($_POST['remuneracion']):''; $salary=$salaryText===''?null:filter_var($salaryText,FILTER_VALIDATE_FLOAT);
        $currency=post_value('moneda',3,true); $start=post_value('fecha_inicio',10); $end=post_value('fecha_fin',10); $deadline=post_value('fecha_limite_postulacion',10,true);
        $fieldLimits=['titulo'=>200,'descripcion'=>20000,'requisitos'=>10000,'ubicacion'=>200,'modalidad'=>20,'tipo_practica'=>100,'vacantes'=>5,'remuneracion'=>20,'moneda'=>3,'fecha_inicio'=>10,'fecha_fin'=>10,'fecha_limite_postulacion'=>10];
        $errors=[];
        if (!$title) $errors['titulo']='Escribe el título de la oportunidad.';
        if (!$desc) $errors['descripcion']='Escribe una descripción de la práctica.';
        if ($vacancies===false) $errors['vacantes']='Ingresa un número entero de 1 a 65,535.';
        if (!in_array($mode,['presencial','remota','hibrida'],true)) $errors['modalidad']='Selecciona una modalidad válida.';
        if (!$currency || !in_array($currency,['GTQ','USD','EUR'],true)) $errors['moneda']='Selecciona quetzales (GTQ), dólares estadounidenses (USD) o euros (EUR).';
        if ($salaryText!=='' && (!preg_match('/^\d{1,10}(?:\.\d{1,2})?$/',$salaryText) || $salary===false || $salary<0 || $salary>9999999999.99)) $errors['remuneracion']='Ingresa una cantidad positiva válida con máximo dos decimales.';
        $validDate=static function (?string $date): bool { return $date!==null && preg_match('/^\d{4}-\d{2}-\d{2}$/',$date)===1 && checkdate((int)substr($date,5,2),(int)substr($date,8,2),(int)substr($date,0,4)); };
        foreach (['fecha_inicio'=>$start,'fecha_fin'=>$end] as $field=>$date) {
            $raw=$_POST[$field]??'';
            if (is_string($raw) && trim($raw)!=='' && !$validDate($date)) $errors[$field]='Ingresa una fecha válida.';
        }
        if (!$deadline) $errors['fecha_limite_postulacion']='Selecciona la fecha límite de postulación.';
        elseif (!$validDate($deadline)) $errors['fecha_limite_postulacion']='Ingresa una fecha límite válida.';
        elseif ($deadline<date('Y-m-d')) $errors['fecha_limite_postulacion']='La fecha límite debe ser hoy o una fecha futura.';
        if ($start && $end && $validDate($start) && $validDate($end) && $end<$start) $errors['fecha_fin']='La fecha de fin debe ser igual o posterior a la fecha de inicio.';
        if ($errors) {
            remember_form_state('opportunity',$fieldLimits,$errors,'Corrige los campos marcados antes de enviar la oportunidad.');
            redirect(app_url('empresa.php#oportunidades'));
        }
        set_audit_context((int)$companyUser['id']);
        $values=['company'=>$companyId,'title'=>$title,'description'=>$desc,'requirements'=>$req,'location'=>$location,'mode'=>$mode,'type'=>$type,'salary'=>$salary,'currency'=>$currency,'vacancies'=>$vacancies,'start'=>$start,'end'=>$end,'deadline'=>$deadline];
        if ($method==='POST') {
            $stmt=$pdo->prepare("INSERT INTO oportunidades(id_empresa,id_administrador,titulo,descripcion,requisitos,ubicacion,modalidad,tipo_practica,remuneracion,moneda,vacantes,fecha_inicio,fecha_fin,fecha_limite_postulacion,estado) VALUES(:company,NULL,:title,:description,:requirements,:location,:mode,:type,:salary,:currency,:vacancies,:start,:end,:deadline,'pendiente_revision')");
            $stmt->execute($values); flash('La oportunidad se envió a revisión. Será pública cuando coordinación la apruebe.');
        } else {
            $values['id']=$id;
            $stmt=$pdo->prepare("UPDATE oportunidades SET titulo=:title,descripcion=:description,requisitos=:requirements,ubicacion=:location,modalidad=:mode,tipo_practica=:type,remuneracion=:salary,moneda=:currency,vacantes=:vacancies,fecha_inicio=:start,fecha_fin=:end,fecha_limite_postulacion=:deadline,estado='pendiente_revision',observaciones_coordinacion=NULL,id_administrador=NULL WHERE id_oportunidad=:id AND id_empresa=:company AND estado IN ('pendiente_revision','requiere_cambios','rechazada','borrador')");
            $stmt->execute($values); if (!$stmt->rowCount()) throw new DomainException('No existe una oportunidad editable de tu empresa.');
            flash('Cambios enviados a revisión.');
        }
        redirect(app_url('empresa.php#oportunidades'));
    }

    // Only coordination can approve/reject company submissions or close active listings.
    $admin = require_role('administrador','superadministrador');
    $id=filter_var($_POST['id_oportunidad']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
    if (!$id) throw new DomainException('La oportunidad indicada no es válida.');
    $review=$pdo->prepare('SELECT estado,fecha_limite_postulacion FROM oportunidades WHERE id_oportunidad=:id'); $review->execute(['id'=>$id]); $reviewRow=$review->fetch();
    if (!$reviewRow) throw new DomainException('La oportunidad no existe.');
    // Compatibilidad con filas creadas antes de que estado tuviera valor predeterminado.
    $oldState=($reviewRow['estado']===null || $reviewRow['estado']==='')?'pendiente_revision':$reviewRow['estado'];
    set_audit_context((int)$admin['id']);
    if ($method==='PUT') {
        $decision=$_POST['decision']??'';
        if ($decision==='publicar' && $oldState==='pendiente_revision') {
            if ($reviewRow['fecha_limite_postulacion']<date('Y-m-d')) throw new DomainException('La fecha límite ya pasó; pide a la empresa que corrija la publicación.');
            $adminId=$pdo->prepare('SELECT id_administrador FROM administradores WHERE id_usuario=:uid'); $adminId->execute(['uid'=>$admin['id']]); $administratorId=$adminId->fetchColumn();
            if (!$administratorId) throw new DomainException('Tu usuario no tiene perfil de coordinación.');
            $reviewComment=post_value('comentario_revision',2000);
            $stmt=$pdo->prepare("UPDATE oportunidades SET estado='publicada',id_administrador=:admin,publicada_en=NOW(),observaciones_coordinacion=:comment WHERE id_oportunidad=:id AND (estado='pendiente_revision' OR estado='' OR estado IS NULL)"); $stmt->execute(['admin'=>$administratorId,'comment'=>$reviewComment,'id'=>$id]);
            flash('Oportunidad aprobada y publicada.');
        } elseif (in_array($decision,['rechazar','solicitar_cambios'],true) && $oldState==='pendiente_revision') {
            $reviewComment=post_value('comentario_revision',2000,true);
            if (!$reviewComment) throw new DomainException('Escribe un comentario para indicar a la empresa qué debe corregir.');
            $nextState=$decision==='solicitar_cambios'?'requiere_cambios':'rechazada';
            $stmt=$pdo->prepare("UPDATE oportunidades SET estado=:state,observaciones_coordinacion=:comment WHERE id_oportunidad=:id AND (estado='pendiente_revision' OR estado='' OR estado IS NULL)");
            $stmt->execute(['state'=>$nextState,'comment'=>$reviewComment,'id'=>$id]);
            flash($decision==='solicitar_cambios'?'Se solicitaron cambios y el comentario quedó visible para la empresa.':'Oportunidad rechazada con el comentario enviado a la empresa.');
        } elseif ($decision==='cerrar' && $oldState==='publicada') {
            $stmt=$pdo->prepare("UPDATE oportunidades SET estado='cerrada' WHERE id_oportunidad=:id AND estado='publicada'"); $stmt->execute(['id'=>$id]); flash('Convocatoria cerrada.');
            // Closing sends one company digest containing applicant contacts and CV attachments through PHP mail().
            $digest=$pdo->prepare('SELECT e.correo_contacto,o.titulo,u.nombres,u.apellidos,u.correo,u.telefono,est.cv_ruta,est.cv_nombre FROM oportunidades o JOIN empresas e ON e.id_empresa=o.id_empresa LEFT JOIN postulaciones p ON p.id_oportunidad=o.id_oportunidad LEFT JOIN estudiantes est ON est.id_estudiante=p.id_estudiante LEFT JOIN usuarios u ON u.id_usuario=est.id_usuario WHERE o.id_oportunidad=:id AND p.id_postulacion IS NOT NULL ORDER BY p.fecha_postulacion');
            $digest->execute(['id'=>$id]); $rows=$digest->fetchAll();
            if ($rows) {
                $to=$rows[0]['correo_contacto']; $subject='Postulantes de convocatoria cerrada: '.$rows[0]['titulo'];
                $boundary='mix_'.bin2hex(random_bytes(12)); $body='Se cerró la convocatoria '.$rows[0]['titulo'].".\r\n\r\n";
                $attachments=[];
                foreach ($rows as $applicant) {
                    $body.='Nombre: '.$applicant['nombres'].' '.$applicant['apellidos']."\r\nCorreo: ".$applicant['correo']."\r\nTeléfono: ".$applicant['telefono']."\r\nCV adjunto: ".$applicant['cv_nombre']."\r\n\r\n";
                    if ($applicant['cv_ruta'] && is_file($applicant['cv_ruta'])) $attachments[]=$applicant;
                }
                $message="--{$boundary}\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit\r\n\r\n{$body}\r\n";
                foreach ($attachments as $file) {
                    $data=chunk_split(base64_encode((string)file_get_contents($file['cv_ruta'])));
                    $name=preg_replace('/[^a-zA-Z0-9._-]/','_',basename((string)$file['cv_nombre']));
                    $message.="--{$boundary}\r\nContent-Type: application/octet-stream; name=\"{$name}\"\r\nContent-Transfer-Encoding: base64\r\nContent-Disposition: attachment; filename=\"{$name}\"\r\n\r\n{$data}\r\n";
                }
                $message.="--{$boundary}--\r\n";
                $from=getenv('MAIL_FROM') ?: 'no-reply@localhost';
                $headers="From: {$from}\r\nMIME-Version: 1.0\r\nContent-Type: multipart/mixed; boundary=\"{$boundary}\"\r\n";
                $sent=mail($to, '=?UTF-8?B?'.base64_encode($subject).'?=', $message, $headers);
                $queue=$pdo->prepare("INSERT INTO cola_notificaciones(tipo,destinatario,asunto,cuerpo,estado,intentos,error_ultimo,procesado_en) VALUES('resumen_cierre',:to,:subject,:body,:state,1,:error,NOW())");
                $queue->execute(['to'=>$to,'subject'=>$subject,'body'=>$body,'state'=>$sent?'enviada':'fallida','error'=>$sent?null:'PHP mail() no pudo entregar el mensaje; configura un transporte SMTP en el servidor.']);
                if (!$sent) flash('Convocatoria cerrada, pero PHP no pudo entregar el correo. Configura SMTP y revisa la cola de notificaciones.','error');
            }
        } else throw new DomainException('La acción no aplica al estado actual.');
    } elseif ($method==='DELETE') {
        $stmt=$pdo->prepare("UPDATE oportunidades SET estado='borrado' WHERE id_oportunidad=:id AND estado<>'borrado'"); $stmt->execute(['id'=>$id]); flash('Oportunidad retirada y su historial conservado.');
    }
    redirect(app_url('admin.php#oportunidades'));
} catch (DomainException $e) {
    $role=current_user()['role']??'';
    if ($role==='empresa' && in_array($method,['POST','PUT'],true)) {
        remember_form_state('opportunity',['titulo'=>200,'descripcion'=>20000,'requisitos'=>10000,'ubicacion'=>200,'modalidad'=>20,'tipo_practica'=>100,'vacantes'=>5,'remuneracion'=>20,'moneda'=>3,'fecha_inicio'=>10,'fecha_fin'=>10,'fecha_limite_postulacion'=>10],[],$e->getMessage());
        redirect(app_url('empresa.php#oportunidades'));
    }
    flash($e->getMessage(),'error'); redirect(app_url($role==='empresa'?'empresa.php#oportunidades':'admin.php#oportunidades'));
} catch (Throwable $e) {
    error_log($e->getMessage()); $role=current_user()['role']??'';
    if ($role==='empresa' && in_array($method,['POST','PUT'],true)) {
        remember_form_state('opportunity',['titulo'=>200,'descripcion'=>20000,'requisitos'=>10000,'ubicacion'=>200,'modalidad'=>20,'tipo_practica'=>100,'vacantes'=>5,'remuneracion'=>20,'moneda'=>3,'fecha_inicio'=>10,'fecha_fin'=>10,'fecha_limite_postulacion'=>10],[],'No se guardó la oportunidad por un error del servidor. Conservamos tus datos; inténtalo de nuevo o contacta a coordinación.');
        redirect(app_url('empresa.php#oportunidades'));
    }
    flash('No se pudo procesar la oportunidad. Verifica los datos y la configuración.','error'); redirect(app_url($role==='empresa'?'empresa.php#oportunidades':'admin.php#oportunidades'));
}

