<?php
declare(strict_types=1);
require_once __DIR__ . '/core/App.php';

// Only company accounts awaiting or correcting approval may edit this profile.
$user = require_role('empresa');
require_permission('empresas.perfil_propio');
if (method_override() !== 'POST') { http_response_code(405); header('Allow: POST'); exit('Método no permitido.'); }
verify_csrf();
$pdo = db();
$companyQuery=$pdo->prepare('SELECT id_empresa,estado_cuenta AS estado,nombre_legal,nombre_comercial,identificacion_fiscal,sector,descripcion,correo_contacto,telefono,sitio_web,direccion FROM vw_empresa_perfil WHERE id_usuario=:uid');
$companyQuery->execute(['uid'=>$user['id']]); $company=$companyQuery->fetch();
if (!$company || !in_array($company['estado'],['pendiente','requiere_cambios','rechazada'],true)) { http_response_code(403); exit('El perfil aprobado no requiere esta acción.'); }

// Validate each field on the server and preserve safe input for correction.
$limits=['nombre_legal'=>180,'nombre_comercial'=>180,'identificacion_fiscal'=>50,'sector'=>100,'descripcion'=>5000,'correo_contacto'=>254,'telefono'=>8,'sitio_web'=>255,'direccion'=>255];
$values=[]; $errors=[];
foreach($limits as $field=>$max) {
    $value=post_value($field,$max);
    if (isset($_POST[$field]) && is_string($_POST[$field]) && mb_strlen(trim($_POST[$field]))>$max) $errors[$field]="Admite hasta {$max} caracteres.";
    $values[$field]=$value;
}
if (!$values['nombre_legal']) $errors['nombre_legal']='Indica el nombre legal de la empresa.';
if (!$values['correo_contacto'] || !filter_var($values['correo_contacto'],FILTER_VALIDATE_EMAIL)) $errors['correo_contacto']='Ingresa un correo de contacto válido.';
if ($values['telefono']!==null && !valid_phone($values['telefono'])) $errors['telefono']='El teléfono debe contener exactamente 8 dígitos.';
if ($values['sitio_web']!==null && (!filter_var($values['sitio_web'],FILTER_VALIDATE_URL) || !in_array(strtolower((string)parse_url($values['sitio_web'],PHP_URL_SCHEME)),['http','https'],true))) $errors['sitio_web']='Ingresa una dirección web válida que comience con http:// o https://.';
if ($values['nombre_comercial']!==null && !preg_match('/^[\p{L}\p{M}0-9 .,&()\'-]+$/u',$values['nombre_comercial'])) $errors['nombre_comercial']='Usa texto válido para el nombre comercial.';
if ($errors) {
    remember_form_state('company_profile',$limits,$errors,'Corrige los campos marcados. Conservamos la información ingresada.');
    redirect(app_url('empresa.php#perfil-empresa'));
}

try {
    $pdo->beginTransaction(); set_audit_context($user['id']);
    $update=$pdo->prepare('UPDATE empresas SET nombre_legal=:legal,nombre_comercial=:commercial,identificacion_fiscal=:tax,sector=:sector,descripcion=:description,correo_contacto=:email,telefono=:phone,sitio_web=:website,direccion=:address WHERE id_empresa=:id');
    $update->execute(['legal'=>$values['nombre_legal'],'commercial'=>$values['nombre_comercial'],'tax'=>$values['identificacion_fiscal'],'sector'=>$values['sector'],'description'=>$values['descripcion'],'email'=>$values['correo_contacto'],'phone'=>$values['telefono'],'website'=>$values['sitio_web'],'address'=>$values['direccion'],'id'=>$company['id_empresa']]);
    $state=$pdo->prepare("UPDATE usuarios SET estado='pendiente' WHERE id_usuario=:uid"); $state->execute(['uid'=>$user['id']]);
    $after=json_encode(['decision'=>'reenviada','estado_cuenta'=>'pendiente','comentario'=>null,'nombre_legal'=>$values['nombre_legal'],'nombre_comercial'=>$values['nombre_comercial'],'identificacion_fiscal'=>$values['identificacion_fiscal'],'sector'=>$values['sector'],'descripcion'=>$values['descripcion'],'correo_contacto'=>$values['correo_contacto'],'telefono'=>$values['telefono'],'sitio_web'=>$values['sitio_web'],'direccion'=>$values['direccion']],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
    $audit=$pdo->prepare("INSERT INTO bitacora(id_usuario,accion,tabla_afectada,id_registro,datos_anteriores,datos_nuevos,ip_origen) VALUES(:uid,'REENVIO_EMPRESA','empresas',:id,:before_json,:after_json,:ip)");
    $audit->execute(['uid'=>$user['id'],'id'=>$company['id_empresa'],'before_json'=>json_encode($company,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),'after_json'=>$after,'ip'=>substr((string)($_SERVER['REMOTE_ADDR']??''),0,45)]);
    $pdo->commit(); flash('Perfil reenviado a coordinación para revisión.');
} catch(Throwable $error) {
    if($pdo->inTransaction())$pdo->rollBack(); error_log($error->__toString());
    remember_form_state('company_profile',$limits,[],'No se pudo reenviar el perfil; conservamos los datos para que intentes otra vez.');
}
redirect(app_url('empresa.php#perfil-empresa'));
