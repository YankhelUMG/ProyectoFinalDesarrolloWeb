<?php
declare(strict_types=1);
require_once __DIR__ . '/api/core/App.php';
$user = require_role('estudiante');
require_permission('usuarios.perfil_propio');
$pdo = db();
$stmt = $pdo->prepare('SELECT telefono,cv_nombre,carnet,carrera,semestre,universidad FROM vw_estudiante_perfil WHERE id_usuario=:id');
$stmt->execute(['id'=>$user['id']]); $profile=$stmt->fetch();
$formState=take_form_state('student_profile');
$values=is_array($formState['values']??null)?$formState['values']:[];
$errors=is_array($formState['errors']??null)?$formState['errors']:[];
$fieldValue=static fn(string $key,mixed $fallback=''):string=>h((string)($values[$key]??$fallback));
$fieldStatus=static fn(string $key):string=>isset($errors[$key])?' class="is-invalid" aria-invalid="true" aria-describedby="profile-error-'.h($key).'"':'';
$fieldError=static fn(string $key):string=>'<small class="field-error" id="profile-error-'.h($key).'" data-field-error="'.h($key).'">'.h((string)($errors[$key]??'')).'</small>';
page_start('Mi perfil');
echo '<section class="auth-panel"><div><span class="eyebrow">DATOS DE POSTULACIÓN</span><h1>Mi perfil</h1><p>Completa tus datos académicos, teléfono y CV para que las empresas puedan evaluar tu postulación.</p></div>'.(!empty($formState['message'])?'<div class="notice notice-error" role="alert">'.h((string)$formState['message']).'</div>':'').'<form method="post" enctype="multipart/form-data" action="'.h(app_url('api/perfil.php')).'" data-validation-form="student-profile">'.csrf_field().'<label>Carné universitario<input name="carnet" maxlength="40" pattern="[A-Za-z0-9._/-]+" value="'.$fieldValue('carnet',$profile['carnet']??'').'" required></label><label>Carrera<input name="carrera" maxlength="150" pattern="[A-Za-zÁÉÍÓÚÜÑáéíóúüñ0-9 .,()&/-]+" value="'.$fieldValue('carrera',$profile['carrera']??'').'" required></label><label>Semestre<input name="semestre" maxlength="40" pattern="[A-Za-zÁÉÍÓÚÜÑáéíóúüñ0-9 .º°-]*" value="'.$fieldValue('semestre',$profile['semestre']??'').'" ></label><label>Universidad<input name="universidad" maxlength="180" pattern="[A-Za-zÁÉÍÓÚÜÑáéíóúüñ0-9 .,()&/-]+" value="'.$fieldValue('universidad',$profile['universidad']??'Universidad Mariano Gálvez').'" required></label><label>Teléfono (8 dígitos)<input type="tel" name="telefono" maxlength="8" inputmode="numeric" pattern="[0-9]{8}" title="Escribe exactamente 8 dígitos, sin espacios ni prefijo." value="'.$fieldValue('telefono',$profile['telefono']??'').'" autocomplete="tel-national" required>'.$fieldError('telefono').'</label><label>CV (Word .doc/.docx o PDF, máximo 5 MB)<input type="file" name="cv" accept=".pdf,.doc,.docx"'.(empty($profile['cv_nombre'])?' required':'').'></label>'.(!empty($profile['cv_nombre'])?'<p>CV guardado: '.h($profile['cv_nombre']).'</p>':'').'<button class="button button-primary" type="submit">Guardar perfil</button></form></section>';
page_end();
