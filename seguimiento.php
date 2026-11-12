<?php
declare(strict_types=1);
require_once __DIR__ . '/api/core/App.php';
// The student identity comes from the server session, never from a URL parameter.
$student=require_role('estudiante');require_permission('postulaciones.consultar_propias');$pdo=db();
$stmt=$pdo->prepare('SELECT id_postulacion,fecha_postulacion,carta_presentacion,observaciones,estado_codigo,estado,id_oportunidad,oportunidad,modalidad,ubicacion,empresa FROM vw_seguimiento_postulaciones WHERE id_usuario=:uid ORDER BY fecha_postulacion DESC');$stmt->execute(['uid'=>$student['id']]);$applications=$stmt->fetchAll();
$history=$pdo->prepare('SELECT accion,fecha_evento,old_id,new_id,comentario FROM vw_seguimiento_historial WHERE id_postulacion=:id ORDER BY fecha_evento ASC,id_bitacora ASC');
$stateName=$pdo->prepare('SELECT nombre FROM vw_estados_postulacion WHERE id_estado=:id');
// The page renders each application with its audit events and current coordination note.
page_start('Mis postulaciones');echo '<section class="page-heading"><div><span class="eyebrow">TU PROCESO, A TU ALCANCE</span><h1>Mis postulaciones</h1><p>Consulta el estado de cada solicitud y revisa los cambios registrados por coordinación.</p></div><a class="button button-primary" href="'.h(app_url('index.php')).'">Explorar oportunidades <span aria-hidden="true">→</span></a></section>';
if(!$applications)echo '<section class="empty-state"><div class="empty-icon">↗</div><h2>Aún no tienes postulaciones</h2><p>Cuando encuentres una oportunidad que te interese, podrás enviar tu solicitud desde su detalle.</p><a class="button button-primary" href="'.h(app_url('index.php')).'">Ver oportunidades</a></section>';
else{echo '<div class="tracking-list">';foreach($applications as $a){echo '<article class="tracking-card"><div class="tracking-head"><div><span class="eyebrow">'.h($a['empresa']).'</span><h2><a href="'.h(app_url('index.php?id='.$a['id_oportunidad'])).'">'.h($a['oportunidad']).'</a></h2><span class="muted">Enviada el '.h(date('d/m/Y \a \l\a\s H:i',strtotime($a['fecha_postulacion']))).'</span></div><span class="state-badge state-'.h($a['estado_codigo']).'">'.h($a['estado']).'</span></div>';
    echo '<div class="timeline"><h3>Historial de seguimiento</h3><ol>';$history->execute(['id'=>$a['id_postulacion']]);$entries=$history->fetchAll();if(!$entries){echo '<li><strong>Postulación recibida</strong><span>El '.h(date('d/m/Y H:i',strtotime($a['fecha_postulacion']))).'</span></li>';}else foreach($entries as $event){$label=$event['new_id'];$stateLabel='Postulación recibida';if($label){$stateName->execute(['id'=>(int)$label]);$stateLabel=$stateName->fetchColumn()?:$stateLabel;}echo '<li><strong>'.h($stateLabel).'</strong><span>'.h(date('d/m/Y H:i',strtotime($event['fecha_evento']))).'</span>'.(!empty($event['comentario'])?'<p>'.h($event['comentario']).'</p>':'').'</li>';}echo '</ol></div>';
    if($a['observaciones'])echo '<div class="student-note"><strong>'.($a['estado_codigo']==='rechazada'?'Motivo del rechazo':'Nota de seguimiento').'</strong><p>'.nl2br(h($a['observaciones'])).'</p></div>';
    if(in_array($a['estado_codigo'],['recibida','en_revision'],true))echo '<form class="withdraw-form" method="post" action="'.h(app_url('api/postulaciones.php')).'">'.csrf_field().'<input type="hidden" name="_method" value="DELETE"><input type="hidden" name="id_postulacion" value="'.h($a['id_postulacion']).'"><button class="text-danger" type="submit">Retirar postulación</button></form>';
    echo '</article>'; }echo '</div>';}
page_end();

