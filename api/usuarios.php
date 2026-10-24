<?php
declare(strict_types=1);
require_once __DIR__ . '/core/App.php';

// Role changes are a privileged operation; enforce the superadministrator role server-side.
$actor=require_role('superadministrador');
if(method_override()!=='POST'){http_response_code(405);exit('Método no permitido.');}
verify_csrf();

// Superadministrators can create an account and its role-specific profile in one transaction.
if(($_POST['accion']??'')==='crear_usuario'){
    // Creation offers only the three account types requested by the coordinator.
    $allowedRoles=['estudiante','empresa','administrador'];
    $first=post_value('nombres',100,true);
    $last=post_value('apellidos',100,true);
    $username=post_value('usuario',40,true);
    $emailInput=$_POST['correo']??null;
    $email=is_string($emailInput)?filter_var(trim($emailInput),FILTER_VALIDATE_EMAIL):false;
    $phone=post_value('telefono',25);
    $password=$_POST['password']??null;
    $role=$_POST['rol']??'';
    $limits=['nombres'=>100,'apellidos'=>100,'usuario'=>40,'correo'=>254,'telefono'=>25,'rol'=>20];
    $errors=[];
    if(!$first)$errors['nombres']='Escribe los nombres (máximo 100 caracteres).';
    elseif(!preg_match('/^[\p{L}\p{M} .\x{27}\x{2019}-]+$/u',$first))$errors['nombres']='Usa solo letras, espacios, puntos o guiones.';
    if(!$last)$errors['apellidos']='Escribe los apellidos (máximo 100 caracteres).';
    elseif(!preg_match('/^[\p{L}\p{M} .\x{27}\x{2019}-]+$/u',$last))$errors['apellidos']='Usa solo letras, espacios, puntos o guiones.';
    if(!$username||!preg_match('/^[A-Za-z0-9._-]{3,40}$/',$username))$errors['usuario']='Usa de 3 a 40 letras, números, puntos, guiones o guiones bajos.';
    if(!$email)$errors['correo']='Ingresa un correo válido.';
    $phoneInput=$_POST['telefono']??'';
    if(!is_string($phoneInput)||mb_strlen(trim($phoneInput))>25)$errors['telefono']='El teléfono admite hasta 25 caracteres.';
    elseif(trim($phoneInput)!==''&&!preg_match('/^[+0-9() .-]{7,25}$/',trim($phoneInput)))$errors['telefono']='Usa un teléfono válido de 7 a 25 caracteres.';
    if(!is_string($password)||strlen($password)<10||strlen($password)>200)$errors['password']='La contraseña debe tener de 10 a 200 caracteres.';
    if(!is_string($role)||!in_array($role,$allowedRoles,true))$errors['rol']='Selecciona Estudiante, Empresa o Administrador.';
    if($errors){
        remember_form_state('user_create',$limits,$errors,'Corrige los campos marcados. Conservamos los demás datos para que no tengas que escribirlos otra vez.');
        redirect(app_url('roles.php'));
    }
    try{
        $pdo=db();
        $pdo->beginTransaction();
        $roleQuery=$pdo->prepare('SELECT id_rol FROM roles WHERE nombre=:role');
        $roleQuery->execute(['role'=>$role]);
        $roleId=$roleQuery->fetchColumn();
        if(!$roleId)throw new DomainException('El perfil solicitado no está configurado.');
        $state=$role==='empresa'?'pendiente':'activo';
        set_audit_context((int)$actor['id']);
        $insertUser=$pdo->prepare('INSERT INTO usuarios(usuario,id_rol,nombres,apellidos,telefono,correo,password_hash,estado) VALUES(:username,:role,:first,:last,:phone,:email,:hash,:state)');
        $insertUser->execute(['username'=>$username,'role'=>$roleId,'first'=>$first,'last'=>$last,'phone'=>$phone,'email'=>$email,'hash'=>password_hash($password,PASSWORD_DEFAULT),'state'=>$state]);
        $newUserId=(int)$pdo->lastInsertId();

        if($role==='estudiante'){
            // Create a usable student profile immediately; academic details can be edited later.
            $profile=$pdo->prepare('INSERT INTO estudiantes(id_usuario,carnet,carrera,semestre,universidad) VALUES(:id,:carnet,:career,NULL,:university)');
            $profile->execute(['id'=>$newUserId,'carnet'=>'PEND-'.$newUserId,'career'=>'Por completar','university'=>'Universidad Mariano Gálvez']);
        }elseif($role==='empresa'){
            // Use account contact details to provision the enterprise profile without extra form fields.
            $companyName='Empresa de '.$first.' '.$last;
            $profile=$pdo->prepare('INSERT INTO empresas(nombre_legal,nombre_comercial,correo_contacto,telefono,id_usuario) VALUES(:legal,:commercial,:contact,:phone,:id)');
            $profile->execute(['legal'=>$companyName,'commercial'=>$companyName,'contact'=>$email,'phone'=>$phone,'id'=>$newUserId]);
        }elseif(in_array($role,['administrador','superadministrador'],true)){
            $profile=$pdo->prepare('INSERT INTO administradores(id_usuario,cargo) VALUES(:id,:cargo)');
            $profile->execute(['id'=>$newUserId,'cargo'=>$role==='superadministrador'?'Superadministración':'Coordinación de prácticas']);
        }
        $pdo->commit();
        flash('La cuenta '.$username.' se creó con perfil '.['usuario'=>'Usuario','estudiante'=>'Estudiante','empresa'=>'Empresa','administrador'=>'Administrador','superadministrador'=>'Superadministrador'][$role].($role==='empresa'?' y quedó pendiente de aprobación.':'.'));
    }catch(Throwable $e){
        if(isset($pdo)&&$pdo->inTransaction())$pdo->rollBack();
        error_log($e->getMessage());
        $errors=[];
        if($e instanceof PDOException&&$e->getCode()==='23000'){
            $detail=(string)($e->errorInfo[2]??'');
            if(stripos($detail,'correo')!==false)$errors['correo']='Este correo ya está registrado.';
            elseif(stripos($detail,'usuario')!==false)$errors['usuario']='Este usuario ya existe; elige otro.';
            else $errors['usuario']='Hay datos únicos duplicados. Revisa el usuario y el correo.';
        }
        remember_form_state('user_create',$limits,$errors,$e instanceof DomainException?$e->getMessage():'No se pudo crear la cuenta. Revisa los campos marcados; los datos ingresados se conservaron.');
    }
    redirect(app_url('roles.php'));
}

$id=filter_var($_POST['id_usuario']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
$role=$_POST['rol']??'';
$allowedRoles=['usuario','estudiante','empresa','administrador','superadministrador'];
if(!$id||!is_string($role)||!in_array($role,$allowedRoles,true)||(int)$id===(int)$actor['id']){
    flash('Selecciona un usuario y un perfil válido. No puedes cambiar tu propio perfil.','error');
    redirect(app_url('roles.php'));
}

try{
    $pdo=db();
    $pdo->beginTransaction();
    $find=$pdo->prepare('SELECT u.id_usuario,u.nombres,u.apellidos,u.correo,u.telefono,r.nombre AS role FROM usuarios u JOIN roles r ON r.id_rol=u.id_rol WHERE u.id_usuario=:id FOR UPDATE');
    $find->execute(['id'=>$id]);
    $target=$find->fetch();
    if(!$target)throw new DomainException('Usuario no encontrado.');

    $select=$pdo->prepare('SELECT id_rol FROM roles WHERE nombre=:role');
    $select->execute(['role'=>$role]);
    $roleId=$select->fetchColumn();
    if(!$roleId)throw new DomainException('El perfil solicitado no está configurado en la base de datos.');

    // Create a required role profile only if this account does not already have one.
    if($role==='estudiante'){
        $profile=$pdo->prepare('SELECT id_estudiante FROM estudiantes WHERE id_usuario=:id');
        $profile->execute(['id'=>$id]);
        if(!$profile->fetchColumn()){
            $carnet=post_value('carnet',40,true);
            $career=post_value('carrera',150,true);
            $university=post_value('universidad',180,true);
            if(!$carnet||!$career||!$university)throw new DomainException('Para asignar Estudiante completa carné, carrera y universidad en los datos de perfil.');
            $insert=$pdo->prepare('INSERT INTO estudiantes(id_usuario,carnet,carrera,universidad) VALUES(:id,:carnet,:career,:university)');
            $insert->execute(['id'=>$id,'carnet'=>$carnet,'career'=>$career,'university'=>$university]);
        }
    }elseif($role==='empresa'){
        $profile=$pdo->prepare('SELECT id_empresa FROM empresas WHERE id_usuario=:id');
        $profile->execute(['id'=>$id]);
        if(!$profile->fetchColumn()){
            $legalName=post_value('nombre_legal',180,true);
            $contactEmail=post_value('correo_contacto',254,true);
            if(!$legalName||!$contactEmail||!filter_var($contactEmail,FILTER_VALIDATE_EMAIL))throw new DomainException('Para asignar Empresa completa el nombre legal y un correo de contacto válido en los datos de perfil.');
            $insert=$pdo->prepare('INSERT INTO empresas(id_usuario,nombre_legal,nombre_comercial,correo_contacto,telefono) VALUES(:id,:legal,:commercial,:email,:phone)');
            $insert->execute(['id'=>$id,'legal'=>$legalName,'commercial'=>$legalName,'email'=>$contactEmail,'phone'=>$target['telefono']]);
            // New companies must pass the existing coordination approval flow.
            $pending=$pdo->prepare("UPDATE usuarios SET estado='pendiente' WHERE id_usuario=:id AND estado='activo'");
            $pending->execute(['id'=>$id]);
        }
    }elseif(in_array($role,['administrador','superadministrador'],true)){
        $admin=$pdo->prepare('INSERT INTO administradores(id_usuario,cargo) VALUES(:id,:cargo) ON DUPLICATE KEY UPDATE cargo=VALUES(cargo)');
        $admin->execute(['id'=>$id,'cargo'=>$role==='superadministrador'?'Superadministración':'Coordinación de prácticas']);
    }

    // The audit trigger records the previous and resulting role; profiles remain for traceability.
    set_audit_context((int)$actor['id']);
    $update=$pdo->prepare('UPDATE usuarios SET id_rol=:role WHERE id_usuario=:id');
    $update->execute(['role'=>$roleId,'id'=>$id]);
    $pdo->commit();
    flash('Perfil actualizado a '.['usuario'=>'Usuario','estudiante'=>'Estudiante','empresa'=>'Empresa','administrador'=>'Administrador','superadministrador'=>'Superadministrador'][$role].'.');
}catch(Throwable $e){
    if(isset($pdo)&&$pdo->inTransaction())$pdo->rollBack();
    error_log($e->getMessage());
    flash($e instanceof DomainException?$e->getMessage():'No se pudo actualizar el perfil. Revisa que los datos de perfil no estén duplicados.','error');
}
redirect(app_url('roles.php'));
