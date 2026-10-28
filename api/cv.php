<?php
declare(strict_types=1);
require_once __DIR__ . '/core/App.php';
$user=require_role('estudiante','empresa','administrador','superadministrador');
$id=filter_input(INPUT_GET,'id',FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
if (!$id) { http_response_code(404); exit('Archivo no encontrado.'); }
$pdo=db();
$sql='SELECT cv_ruta,cv_nombre,id_usuario_estudiante AS id_usuario,id_usuario_empresa AS empresa_usuario FROM vw_empresa_postulaciones WHERE id_postulacion=:id';
$stmt=$pdo->prepare($sql);$stmt->execute(['id'=>$id]);$row=$stmt->fetch();
$allowed=$row && (in_array($user['role'],['administrador','superadministrador'],true) || (int)$row['id_usuario']===(int)$user['id'] || ($user['role']==='empresa' && (int)$row['empresa_usuario']===(int)$user['id']));
if (!$allowed || !$row['cv_ruta'] || !is_file($row['cv_ruta'])) { http_response_code(404); exit('Archivo no encontrado.'); }
header('Content-Type: application/octet-stream');header('X-Content-Type-Options: nosniff');header('Content-Disposition: attachment; filename="'.rawurlencode(basename($row['cv_nombre'])).'"');header('Content-Length: '.filesize($row['cv_ruta']));readfile($row['cv_ruta']);
