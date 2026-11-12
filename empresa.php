<?php
declare(strict_types=1);
require_once __DIR__ . '/api/core/App.php';

// Only an authenticated company account can see applications for its own profile.
$companyUser = require_role('empresa');
require_permission('empresas.perfil_propio');
$pdo = db();
$companyQuery = $pdo->prepare('SELECT * FROM vw_empresa_perfil WHERE id_usuario=:uid');
$companyQuery->execute(['uid' => $companyUser['id']]);
$company = $companyQuery->fetch();
if (!$company) { http_response_code(403); exit('El perfil de empresa no está completo.'); }

// Unapproved companies can only review the decision and correct their profile.
if ($company['estado_cuenta'] !== 'activo') {
    $reviewQuery=$pdo->prepare("SELECT JSON_UNQUOTE(JSON_EXTRACT(datos_nuevos,'$.decision')) AS decision,JSON_UNQUOTE(JSON_EXTRACT(datos_nuevos,'$.comentario')) AS comentario FROM vw_admin_bitacora WHERE tabla_afectada='empresas' AND id_registro=:id AND accion IN ('REVISION_EMPRESA','REENVIO_EMPRESA') ORDER BY id_bitacora DESC LIMIT 1");
    $reviewQuery->execute(['id'=>$company['id_empresa']]); $review=$reviewQuery->fetch()?:[];
    $reviewLabels=['solicitar_cambios'=>'Cambios solicitados','rechazar'=>'Solicitud rechazada','reenviada'=>'En espera de revisión'];
    $profileState=take_form_state('company_profile');
    $profileValues=is_array($profileState['values']??null)?$profileState['values']:[];
    $profileErrors=is_array($profileState['errors']??null)?$profileState['errors']:[];
    $val=static fn(string $key): string=>h((string)($profileValues[$key]??($company[$key]??'')));
    page_start('Revisión del perfil de empresa');
    echo '<section class="page-heading"><div><span class="eyebrow">ACCESO EMPRESARIAL</span><h1>Revisión de empresa</h1><p>Completa o corrige el perfil para que coordinación pueda aprobarlo.</p></div><span class="status-pill">'.h($reviewLabels[$review['decision']??'']??'En espera de revisión').'</span></section>';
    if (!empty($profileState['message'])) echo '<div class="notice notice-error" role="alert">'.h($profileState['message']).'</div>';
    if (!empty($review['comentario']) && $review['comentario']!=='null') echo '<section class="panel"><span class="eyebrow">COMENTARIO DE COORDINACIÓN</span><h2>Qué debes corregir</h2><p>'.nl2br(h($review['comentario'])).'</p></section>';
    echo '<section class="panel" id="perfil-empresa"><div class="panel-heading"><div><span class="eyebrow">DATOS EMPRESARIALES</span><h2>Perfil de la empresa</h2></div></div><form method="post" action="'.h(app_url('api/perfil_empresa.php')).'" data-validation-form="company-profile">'.csrf_field().'<div class="form-grid">';
    $fields=[['nombre_legal','Nombre legal *','text',true],['nombre_comercial','Nombre comercial','text',false],['identificacion_fiscal','Identificación fiscal','text',false],['sector','Sector','text',false],['correo_contacto','Correo de contacto *','email',true],['telefono','Teléfono (8 dígitos)','tel',false],['sitio_web','Sitio web','url',false],['direccion','Dirección','text',false]];
    foreach($fields as [$key,$label,$type,$required]) { $error=$profileErrors[$key]??''; echo '<label>'.h($label).'<input type="'.h($type).'" name="'.h($key).'" maxlength="'.($key==='correo_contacto'?254:($key==='nombre_legal'||$key==='nombre_comercial'?180:255)).'"'.($key==='telefono'?' pattern="[0-9]{8}" inputmode="numeric" title="Ingresa exactamente 8 dígitos."':'').($required?' required':'').($error?' class="is-invalid" aria-invalid="true"':'').' value="'.$val($key).'"><small class="field-error">'.h($error).'</small></label>'; }
    $error=$profileErrors['descripcion']??'';
    echo '<label class="span-2">Descripción<textarea name="descripcion" maxlength="5000" rows="5">'.$val('descripcion').'</textarea><small class="field-error">'.h($error).'</small></label></div><button class="button button-primary" type="submit">Guardar y enviar a revisión</button></form></section>';
    page_end(); exit;
}
require_permission('oportunidades.gestionar_propias');
require_permission('postulaciones.consultar_todas');
$notificationQuery=$pdo->prepare('SELECT asunto,estado,intentos,error_ultimo,procesado_en FROM vw_empresa_notificaciones WHERE id_usuario_empresa=:uid ORDER BY id_notificacion DESC LIMIT 10');
$notificationQuery->execute(['uid'=>$companyUser['id']]);
$notifications=$notificationQuery->fetchAll();

$ownQuery = $pdo->prepare("SELECT * FROM vw_empresa_oportunidades WHERE id_usuario_empresa=:uid ORDER BY CASE estado_panel WHEN 'pendiente_revision' THEN 1 WHEN 'requiere_cambios' THEN 2 WHEN 'rechazada' THEN 3 ELSE 4 END,id_oportunidad DESC");
$ownQuery->execute(['uid' => $companyUser['id']]);
$ownOpportunities = $ownQuery->fetchAll();
$approvedOpportunities=array_values(array_filter($ownOpportunities,static fn(array $row):bool=>in_array($row['estado_panel'],['publicada','cerrada','pausada'],true)));
$selectedOpportunityId=filter_var($_GET['oportunidad']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
$selectedOpportunity=null;
if ($selectedOpportunityId) foreach($approvedOpportunities as $candidate) if ((int)$candidate['id_oportunidad']===$selectedOpportunityId) {$selectedOpportunity=$candidate;break;}
if (!$selectedOpportunity) $selectedOpportunityId=null;
// Keep the company boundary in SQL and optionally filter to its selected practice.
$applicationSql='SELECT id_postulacion,fecha_postulacion,carta_presentacion,observaciones,estado_codigo,estado,oportunidad,nombres,apellidos,correo,telefono,carnet,carrera,semestre,universidad,cv_nombre FROM vw_empresa_postulaciones WHERE id_usuario_empresa=:empresa';
if ($selectedOpportunityId) $applicationSql.=' AND id_oportunidad=:opportunity';
$applicationSql.=' ORDER BY fecha_postulacion DESC';
$applicationsQuery=$pdo->prepare($applicationSql);
$applicationParams=['empresa'=>$companyUser['id']];
if ($selectedOpportunityId) $applicationParams['opportunity']=$selectedOpportunityId;
$applicationsQuery->execute($applicationParams);
$applications=$applicationsQuery->fetchAll();
$totalApplicationsQuery=$pdo->prepare('SELECT COUNT(*) FROM vw_empresa_postulaciones WHERE id_usuario_empresa=:company');
$totalApplicationsQuery->execute(['company'=>$companyUser['id']]);
$totalCompanyApplications=(int)$totalApplicationsQuery->fetchColumn();
$editing = null;
if (isset($_GET['editar']) && ctype_digit((string)$_GET['editar'])) {
    foreach ($ownOpportunities as $candidate) if ((int)$candidate['id_oportunidad']===(int)$_GET['editar'] && $candidate['estado_panel']==='requiere_cambios') $editing=$candidate;
    if (!$editing) { flash('Solo puedes editar una práctica cuando coordinación te solicita cambios. Las solicitudes rechazadas quedan cerradas.','error'); redirect(app_url('empresa.php#estado-solicitudes')); }
}
$formState = take_form_state('opportunity');
$oldValues = is_array($formState['values'] ?? null) ? $formState['values'] : [];
$fieldErrors = is_array($formState['errors'] ?? null) ? $formState['errors'] : [];
$fieldValue = static fn(string $name, string $default = ''): string => h((string)($oldValues[$name] ?? ($editing[$name] ?? $default)));
$fieldStatus = static fn(string $name): string => isset($fieldErrors[$name]) ? ' class="is-invalid" aria-invalid="true" aria-describedby="error-'.h($name).'"' : '';
$fieldError = static fn(string $name): string => '<small class="field-error" id="error-'.h($name).'" data-field-error="'.h($name).'">'.h((string)($fieldErrors[$name] ?? '')).'</small>';

page_start('Panel de empresa');
echo '<section class="page-heading"><div><span class="eyebrow">PANEL DE EMPRESA</span><h1>'.h($company['nombre_comercial'] ?: $company['nombre_legal']).'</h1><p>Revisa las postulaciones recibidas en todas las prácticas de tu empresa y consulta el estado de cada solicitud de publicación.</p></div><div class="admin-stats"><div><strong>'.$totalCompanyApplications.'</strong><span>postulaciones para esta empresa</span></div></div></section>';

// Show the moderation result and coordination comment for every submitted opportunity.
$publicationStatus=['pendiente_revision'=>'En trámite de revisión','requiere_cambios'=>'Se solicitaron cambios','rechazada'=>'Rechazada','publicada'=>'Aprobada y publicada','cerrada'=>'Aprobada · convocatoria cerrada','borrador'=>'Borrador','pausada'=>'Aprobada · pausada'];
echo '<section class="panel" id="estado-solicitudes"><div class="panel-heading"><div><span class="eyebrow">SEGUIMIENTO DE SOLICITUDES</span><h2>Estado de revisión de tus prácticas</h2><p>Aquí puedes ver si coordinación aprobó, está revisando, rechazó o solicitó cambios.</p></div></div>';
if (!$ownOpportunities) echo '<p class="empty-inline">Cuando envíes una práctica a revisión, su estado aparecerá aquí.</p>';
else { echo '<div class="table-wrap"><table><thead><tr><th>Oportunidad</th><th>Estado de solicitud</th><th>Comentario de coordinación</th><th>Fecha límite</th><th>Acción</th></tr></thead><tbody>';
foreach($ownOpportunities as $o){$state=$o['estado_panel'];$label=$publicationStatus[$state]??'En trámite de revisión';$comment=$o['observaciones_coordinacion']??'';echo '<tr><td><strong>'.h($o['titulo']).'</strong></td><td><span class="status-pill">'.h($label).'</span></td><td>'.($comment!==''?nl2br(h($comment)):($state==='requiere_cambios'||$state==='rechazada'?'<span class="text-danger">Coordinación no agregó un comentario.</span>':'<span class="muted">—</span>')).'</td><td>'.h($o['fecha_limite_postulacion']).'</td><td>'.($state==='requiere_cambios'?'<a class="button button-small button-primary" href="'.h(app_url('empresa.php?editar='.$o['id_oportunidad'].'#crear-oportunidad')).'">Corregir y reenviar</a>':($state==='rechazada'?'<span class="muted">Solicitud cerrada</span>':'<span class="muted">Sin acciones pendientes</span>')).'</td></tr>';}
echo '</tbody></table></div>'; }
echo '</section>';

// First show the opportunity review queue, prioritizing review and rejected entries.
echo '<section class="panel" id="oportunidades"><div class="panel-heading"><div><span class="eyebrow">CONVOCATORIAS APROBADAS</span><h2>Tus prácticas</h2><p>Solo aparecen aquí las oportunidades aprobadas por coordinación.</p></div><a class="button button-primary" href="'.h(app_url('empresa.php#crear-oportunidad')).'">Crear oportunidad</a></div><div class="table-wrap"><table><thead><tr><th>Práctica</th><th>Estado</th><th>Fecha límite</th><th>Seguimiento</th></tr></thead><tbody>';
$opportunityLabels=['pendiente_revision'=>'En espera de revisión','requiere_cambios'=>'Cambios solicitados','rechazada'=>'Rechazada','borrador'=>'Borrador','publicada'=>'Publicada','pausada'=>'Pausada','cerrada'=>'Cerrada'];
foreach($approvedOpportunities as $o){$state=$o['estado_panel'];$label=$opportunityLabels[$state]??'Aprobada';echo '<tr><td><strong>'.h($o['titulo']).'</strong></td><td><strong>'.h($label).'</strong></td><td>'.h($o['fecha_limite_postulacion']).'</td><td><a class="button button-small button-quiet" href="'.h(app_url('empresa.php?oportunidad='.$o['id_oportunidad'].'#postulaciones')).'">Ver postulaciones ('.h($o['numero_postulaciones']).')</a>'.($state==='publicada'?'<small>'.h($o['perfiles_aceptados']).' aceptados · '.h($o['perfiles_pendientes']).' por revisar</small><form method="post" action="'.h(app_url('api/oportunidades.php')).'">'.csrf_field().'<input type="hidden" name="_method" value="PUT"><input type="hidden" name="decision" value="cerrar"><input type="hidden" name="id_oportunidad" value="'.h($o['id_oportunidad']).'"><button class="button button-small button-primary" type="submit">Cerrar y enviar perfiles aceptados</button></form>':'').'</td></tr>';}
if(!$approvedOpportunities)echo '<tr><td colspan="4">Aún no tienes prácticas aprobadas. Revisa el estado de tus solicitudes arriba.</td></tr>';
echo '</tbody></table></div></section>';

// Companies can confirm automatic email delivery or see why SMTP needs attention.
if ($notifications) {
    echo '<section class="panel"><div class="panel-heading"><div><span class="eyebrow">NOTIFICACIONES</span><h2>Correos de perfiles aceptados</h2><p>Los intentos de envío quedan registrados para seguimiento.</p></div></div><div class="table-wrap"><table><thead><tr><th>Convocatoria</th><th>Estado del correo</th><th>Intentos</th><th>Último detalle</th></tr></thead><tbody>';
    foreach($notifications as $notice){$delivered=$notice['estado']==='enviada';echo '<tr><td>'.h($notice['asunto']).'</td><td><span class="status-pill">'.h($delivered?'Aceptado por el servidor de correo':($notice['estado']==='fallida'?'Envío pendiente de configurar':'En cola')).'</span></td><td>'.h($notice['intentos']).'</td><td>'.h($notice['error_ultimo']?:($notice['procesado_en']?:'Pendiente')).'</td></tr>';}
    echo '</tbody></table></div></section>';
}

// Companies own the opportunity form; each submission enters administrator review before becoming public.
echo '<section class="panel" id="crear-oportunidad"><div class="panel-heading"><div><span class="eyebrow">TUS PUBLICACIONES</span><h2>'.($editing?'Editar oportunidad':'Crear oportunidad').'</h2><p>Coordinación debe aprobarla antes de que aparezca en el catálogo público.</p></div></div>';
if ($editing && !empty($editing['observaciones_coordinacion'])) echo '<div class="notice notice-error" role="status"><strong>Comentario de coordinación:</strong> '.nl2br(h($editing['observaciones_coordinacion'])).'</div>';
if (!empty($formState['message'])) echo '<div class="notice notice-error" role="alert">'.h((string)$formState['message']).'</div>';
echo '<form method="post" action="'.h(app_url('api/oportunidades.php')).'" data-validation-form="opportunity">'.csrf_field().($editing?'<input type="hidden" name="_method" value="PUT"><input type="hidden" name="id_oportunidad" value="'.h($editing['id_oportunidad']).'">':'').'<div class="form-grid">';
echo '<label class="span-2">Título *<input name="titulo" maxlength="200" required'.$fieldStatus('titulo').' value="'.$fieldValue('titulo').'"><small class="field-error" id="error-titulo" data-field-error="titulo">'.h((string)($fieldErrors['titulo']??'')).'</small></label>';
echo '<label>Modalidad *<select name="modalidad" required'.$fieldStatus('modalidad').'>';
foreach(['presencial'=>'Presencial','hibrida'=>'Híbrida','remota'=>'Remota'] as $value=>$label) echo '<option value="'.h($value).'"'.($fieldValue('modalidad','presencial')===$value?' selected':'').'>'.h($label).'</option>';
echo '</select>'.$fieldError('modalidad').'</label>';
echo '<label>Tipo de práctica<input name="tipo_practica" maxlength="100"'.$fieldStatus('tipo_practica').' value="'.$fieldValue('tipo_practica').'"></label>';
echo '<label class="span-2">Descripción *<textarea name="descripcion" maxlength="20000" rows="4" required'.$fieldStatus('descripcion').'>'.$fieldValue('descripcion').'</textarea>'.$fieldError('descripcion').'</label>';
echo '<label class="span-2">Requisitos<textarea name="requisitos" maxlength="10000" rows="3"'.$fieldStatus('requisitos').'>'.$fieldValue('requisitos').'</textarea>'.$fieldError('requisitos').'</label>';
echo '<label class="span-2">Palabras clave <span class="optional">(separa con comas; máximo 15)</span><input name="palabras_clave" maxlength="1500" placeholder="Ejemplo: programación, análisis de datos, mercadeo"'.$fieldStatus('palabras_clave').' value="'.$fieldValue('palabras_clave').'">'.$fieldError('palabras_clave').'</label>';
echo '<label>Ubicación<input name="ubicacion" maxlength="200"'.$fieldStatus('ubicacion').' value="'.$fieldValue('ubicacion').'"></label>';
echo '<label>Vacantes *<input type="number" name="vacantes" min="1" max="65535" required'.$fieldStatus('vacantes').' value="'.$fieldValue('vacantes','1').'">'.$fieldError('vacantes').'</label>';
echo '<label>Remuneración<input type="number" name="remuneracion" min="0" max="9999999999.99" step="0.01"'.$fieldStatus('remuneracion').' value="'.$fieldValue('remuneracion').'">'.$fieldError('remuneracion').'</label>';
echo '<label>Moneda *<select name="moneda" required'.$fieldStatus('moneda').'>';foreach(['GTQ'=>'Quetzales (GTQ)','USD'=>'Dólares estadounidenses (USD)','EUR'=>'Euros (EUR)'] as $code=>$label)echo '<option value="'.h($code).'"'.($fieldValue('moneda','GTQ')===$code?' selected':'').'>'.h($label).'</option>';echo '</select>'.$fieldError('moneda').'</label>';
echo '<label>Fecha de inicio<input type="date" name="fecha_inicio"'.$fieldStatus('fecha_inicio').' value="'.$fieldValue('fecha_inicio').'">'.$fieldError('fecha_inicio').'</label>';
echo '<label>Fecha de fin<input type="date" name="fecha_fin"'.$fieldStatus('fecha_fin').' value="'.$fieldValue('fecha_fin').'">'.$fieldError('fecha_fin').'</label>';
echo '<label>Fecha límite de postulación *<input type="date" name="fecha_limite_postulacion" required'.$fieldStatus('fecha_limite_postulacion').' value="'.$fieldValue('fecha_limite_postulacion').'">'.$fieldError('fecha_limite_postulacion').'</label>';
echo '</div><button class="button button-primary" type="submit">'.($editing?'Reenviar a revisión':'Enviar a revisión').'</button></form></section>';

// Opening a practice displays all active applications linked to that practice.
if ($selectedOpportunity) {
if (!$applications) {
    echo '<section class="panel" id="postulaciones"><div class="panel-heading"><div><span class="eyebrow">ESTUDIANTES INTERESADOS</span><h2>'.h($selectedOpportunity['titulo']).'</h2></div><a class="text-link" href="'.h(app_url('empresa.php#oportunidades')).'">Volver a prácticas</a></div><p class="empty-inline">Aún no hay postulaciones para esta práctica.</p></section>';
} else {
    echo '<section class="panel" id="postulaciones"><div class="panel-heading"><div><span class="eyebrow">ESTUDIANTES INTERESADOS</span><h2>Postulaciones para '.h($selectedOpportunity['titulo']).'</h2><p>'.count($applications).' solicitudes recibidas.</p></div><a class="text-link" href="'.h(app_url('empresa.php#oportunidades')).'">Volver a prácticas</a></div><div class="application-list">';
    foreach ($applications as $application) {
        echo '<article class="application-row"><div class="applicant"><div class="avatar">'.h(mb_strtoupper(mb_substr($application['nombres'],0,1).mb_substr($application['apellidos'],0,1))).'</div><div><strong>'.h($application['nombres'].' '.$application['apellidos']).'</strong><span>'.h($application['carrera']).' · '.h($application['carnet']).'</span><span>'.h($application['universidad']).' · '.h($application['semestre']).'</span><span>'.h($application['correo']).' · '.h($application['telefono']).'</span><a class="text-link" href="'.h(app_url('api/cv.php?id='.$application['id_postulacion'])).'">Descargar CV: '.h($application['cv_nombre']?:'CV').'</a></div></div><div class="application-for"><span>Oportunidad</span><strong>'.h($application['oportunidad']).'</strong><small>Recibida '.h(date('d/m/Y H:i',strtotime($application['fecha_postulacion']))).'</small></div><div class="application-message"><span>Carta de presentación</span><p>'.h($application['carta_presentacion'] ?: 'El estudiante no agregó una carta de presentación.').'</p></div><div class="application-update"><span class="state-badge state-'.h($application['estado_codigo']).'">'.h($application['estado']).'</span>';
        if (!empty($application['observaciones'])) echo '<small>'.($application['estado_codigo']==='rechazada'?'Motivo del rechazo: ':'Nota para el estudiante: ').h($application['observaciones']).'</small>';
        if ($selectedOpportunity['estado_panel']==='publicada' && in_array($application['estado_codigo'],['recibida','en_revision','entrevista'],true)) {
            echo '<form method="post" action="'.h(app_url('api/postulaciones.php')).'" data-application-review>'.csrf_field().'<input type="hidden" name="_method" value="PUT"><input type="hidden" name="id_postulacion" value="'.h($application['id_postulacion']).'"><label class="sr-only" for="company-state-'.h($application['id_postulacion']).'">Nuevo estado</label><select id="company-state-'.h($application['id_postulacion']).'" name="estado" required><option value="" selected disabled>Selecciona estado</option><option value="en_revision">En revisión</option><option value="entrevista">Entrevista</option><option value="aceptada">Aceptada</option><option value="rechazada">Rechazada</option></select><label for="company-note-'.h($application['id_postulacion']).'">Comentario para el estudiante <small>(obligatorio al rechazar)</small></label><textarea id="company-note-'.h($application['id_postulacion']).'" name="observaciones" maxlength="500" rows="2" placeholder="Indica el motivo si rechazas la postulación" data-application-comment></textarea><button class="button button-small button-primary" type="submit">Guardar decisión</button></form>';
        } else echo '<small>'.($selectedOpportunity['estado_panel']==='publicada'?'Decisión final registrada.':'Convocatoria cerrada; perfil en estado final.').'</small>';
        echo '</div></article>';
    }
    echo '</div></section>';
}
}
page_end();


