<?php
declare(strict_types=1);
require_once __DIR__ . '/api/core/App.php';

// Only an authenticated company account can see applications for its own profile.
$companyUser = require_role('empresa');
$pdo = db();
$companyQuery = $pdo->prepare('SELECT id_empresa,nombre_legal,nombre_comercial FROM empresas WHERE id_usuario=:uid');
$companyQuery->execute(['uid' => $companyUser['id']]);
$company = $companyQuery->fetch();
if (!$company) { http_response_code(403); exit('El perfil de empresa no está completo.'); }

$ownQuery = $pdo->prepare("SELECT o.*,COALESCE(NULLIF(o.estado,''),'pendiente_revision') AS estado_panel,(SELECT COUNT(*) FROM postulaciones p JOIN estados_postulacion ep ON ep.id_estado=p.id_estado WHERE p.id_oportunidad=o.id_oportunidad AND ep.codigo<>'borrado') AS numero_postulaciones FROM oportunidades o WHERE o.id_empresa=:empresa AND COALESCE(o.estado,'')<>'borrado' ORDER BY CASE COALESCE(NULLIF(o.estado,''),'pendiente_revision') WHEN 'pendiente_revision' THEN 1 WHEN 'rechazada' THEN 2 ELSE 3 END,o.id_oportunidad DESC");
$ownQuery->execute(['empresa' => $company['id_empresa']]);
$ownOpportunities = $ownQuery->fetchAll();
$selectedOpportunityId=filter_var($_GET['oportunidad']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
$selectedOpportunity=null;
if ($selectedOpportunityId) foreach($ownOpportunities as $candidate) if ((int)$candidate['id_oportunidad']===$selectedOpportunityId) {$selectedOpportunity=$candidate;break;}
if (!$selectedOpportunity) $selectedOpportunityId=null;
// Keep the company boundary in SQL and optionally filter to its selected practice.
$applicationSql="SELECT p.id_postulacion,p.fecha_postulacion,p.carta_presentacion,p.observaciones,ep.codigo AS estado_codigo,ep.nombre AS estado,o.titulo AS oportunidad,u.nombres,u.apellidos,u.correo,u.telefono,est.carnet,est.carrera,est.semestre,est.universidad,est.cv_nombre FROM postulaciones p JOIN estados_postulacion ep ON ep.id_estado=p.id_estado JOIN oportunidades o ON o.id_oportunidad=p.id_oportunidad JOIN estudiantes est ON est.id_estudiante=p.id_estudiante JOIN usuarios u ON u.id_usuario=est.id_usuario WHERE o.id_empresa=:empresa AND ep.codigo<>'borrado'";
if ($selectedOpportunityId) $applicationSql.=' AND o.id_oportunidad=:opportunity';
$applicationSql.=' ORDER BY p.fecha_postulacion DESC';
$applicationsQuery=$pdo->prepare($applicationSql);
$applicationParams=['empresa'=>$company['id_empresa']];
if ($selectedOpportunityId) $applicationParams['opportunity']=$selectedOpportunityId;
$applicationsQuery->execute($applicationParams);
$applications=$applicationsQuery->fetchAll();
$editing = null;
if (isset($_GET['editar']) && ctype_digit((string)$_GET['editar'])) {
    foreach ($ownOpportunities as $candidate) if ((int)$candidate['id_oportunidad']===(int)$_GET['editar'] && in_array($candidate['estado_panel'],['pendiente_revision','requiere_cambios','rechazada','borrador'],true)) $editing=$candidate;
}
$formState = take_form_state('opportunity');
$oldValues = is_array($formState['values'] ?? null) ? $formState['values'] : [];
$fieldErrors = is_array($formState['errors'] ?? null) ? $formState['errors'] : [];
$fieldValue = static fn(string $name, string $default = ''): string => h((string)($oldValues[$name] ?? ($editing[$name] ?? $default)));
$fieldStatus = static fn(string $name): string => isset($fieldErrors[$name]) ? ' class="is-invalid" aria-invalid="true" aria-describedby="error-'.h($name).'"' : '';
$fieldError = static fn(string $name): string => '<small class="field-error" id="error-'.h($name).'" data-field-error="'.h($name).'">'.h((string)($fieldErrors[$name] ?? '')).'</small>';

page_start('Panel de empresa');
echo '<section class="page-heading"><div><span class="eyebrow">PANEL DE EMPRESA</span><h1>'.h($company['nombre_comercial'] ?: $company['nombre_legal']).'</h1><p>Revisa las postulaciones que llegaron a tus oportunidades y actualiza cada etapa.</p></div><div class="admin-stats"><div><strong>'.count($applications).'</strong><span>'.($selectedOpportunity?'postulaciones para esta práctica':'postulaciones recibidas').'</span></div></div></section>';

// First show the opportunity review queue, prioritizing review and rejected entries.
echo '<section class="panel" id="oportunidades"><div class="panel-heading"><div><span class="eyebrow">SEGUIMIENTO DE CONVOCATORIAS</span><h2>Tus prácticas</h2><p>En revisión primero, rechazadas después y luego las demás prácticas.</p></div><a class="button button-primary" href="'.h(app_url('empresa.php#crear-oportunidad')).'">Crear oportunidad</a></div><div class="table-wrap"><table><thead><tr><th>Práctica</th><th>Estado</th><th>Fecha límite</th><th>Seguimiento</th></tr></thead><tbody>';
$opportunityLabels=['pendiente_revision'=>'En espera de revisión','requiere_cambios'=>'Cambios solicitados','rechazada'=>'Rechazada','borrador'=>'Borrador','publicada'=>'Publicada','pausada'=>'Pausada','cerrada'=>'Cerrada'];
foreach($ownOpportunities as $o){$state=$o['estado_panel'];$label=$opportunityLabels[$state]??'En espera de revisión';echo '<tr><td><strong>'.h($o['titulo']).'</strong>'.(!empty($o['observaciones_coordinacion'])?'<small>Coordinación: '.h($o['observaciones_coordinacion']).'</small>':'').'</td><td><strong>'.h($label).'</strong>'.($state==='pendiente_revision'?'<small>Enviada a coordinación; pendiente de aprobación.</small>':'').'</td><td>'.h($o['fecha_limite_postulacion']).'</td><td><a class="button button-small button-quiet" href="'.h(app_url('empresa.php?oportunidad='.$o['id_oportunidad'].'#postulaciones')).'">Ver postulaciones ('.h($o['numero_postulaciones']).')</a>'.(in_array($state,['pendiente_revision','requiere_cambios','rechazada','borrador'],true)?' <a class="text-link" href="'.h(app_url('empresa.php?editar='.$o['id_oportunidad'].'#crear-oportunidad')).'">Editar</a>':'').'</td></tr>';}
if(!$ownOpportunities)echo '<tr><td colspan="4">Aún no has creado prácticas.</td></tr>';
echo '</tbody></table></div></section>';

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
        echo '<article class="application-row"><div class="applicant"><div class="avatar">'.h(mb_strtoupper(mb_substr($application['nombres'],0,1).mb_substr($application['apellidos'],0,1))).'</div><div><strong>'.h($application['nombres'].' '.$application['apellidos']).'</strong><span>'.h($application['carrera']).' · '.h($application['carnet']).'</span><span>'.h($application['universidad']).' · '.h($application['semestre']).'</span><span>'.h($application['correo']).' · '.h($application['telefono']).'</span><a class="text-link" href="'.h(app_url('api/cv.php?id='.$application['id_postulacion'])).'">Descargar CV: '.h($application['cv_nombre']?:'CV').'</a></div></div><div class="application-for"><span>Oportunidad</span><strong>'.h($application['oportunidad']).'</strong><small>Recibida '.h(date('d/m/Y H:i',strtotime($application['fecha_postulacion']))).'</small></div><div class="application-message"><span>Carta de presentación</span><p>'.h($application['carta_presentacion'] ?: 'El estudiante no agregó una carta de presentación.').'</p></div><div class="application-update"><span class="state-badge state-'.h($application['estado_codigo']).'">'.h($application['estado']).'</span><form method="post" action="'.h(app_url('api/postulaciones.php')).'">'.csrf_field().'<input type="hidden" name="_method" value="PUT"><input type="hidden" name="id_postulacion" value="'.h($application['id_postulacion']).'"><label class="sr-only" for="company-state-'.h($application['id_postulacion']).'">Nuevo estado</label><select id="company-state-'.h($application['id_postulacion']).'" name="estado" required><option value="" selected disabled>Selecciona estado</option><option value="en_revision">En revisión</option><option value="entrevista">Entrevista</option><option value="aceptada">Aceptada</option><option value="rechazada">Rechazada</option></select><label class="sr-only" for="company-note-'.h($application['id_postulacion']).'">Observaciones para el estudiante</label><textarea id="company-note-'.h($application['id_postulacion']).'" name="observaciones" maxlength="1000" rows="2" placeholder="Nota visible para el estudiante">'.h($application['observaciones']).'</textarea><button class="button button-small button-primary" type="submit">Guardar avance</button></form></div></article>';
    }
    echo '</div></section>';
}
}
page_end();


