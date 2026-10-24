<?php
declare(strict_types=1);
require_once __DIR__ . '/core/App.php';

$method = method_override();
$pdo = db();

// Administrators can review submitted company profiles.
if ($method === 'GET') {
    require_role('administrador','superadministrador');
    $stmt = $pdo->query("SELECT e.id_empresa,e.nombre_legal,e.nombre_comercial,e.identificacion_fiscal,e.sector,e.correo_contacto,e.telefono,e.sitio_web,e.direccion,e.descripcion,u.id_usuario,u.nombres,u.apellidos,u.correo,u.estado FROM empresas e JOIN usuarios u ON u.id_usuario=e.id_usuario JOIN roles r ON r.id_rol=u.id_rol WHERE r.nombre='empresa' AND u.estado IN ('pendiente','activo') ORDER BY u.estado='pendiente' DESC,e.id_empresa DESC");
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($stmt->fetchAll(), JSON_UNESCAPED_UNICODE);
    exit;
}

// Only an administrator can approve or reject a pending company login.
if ($method !== 'PUT') { http_response_code(405); header('Allow: GET, PUT'); exit('Método no permitido.'); }
verify_csrf();
$admin = require_role('administrador','superadministrador');
$companyId = filter_var($_POST['id_empresa'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$decision = $_POST['decision'] ?? null;
if (!$companyId || !in_array($decision, ['aprobar','rechazar'], true)) {
    flash('La empresa o decisión indicada no es válida.', 'error');
    redirect(app_url('admin.php#empresas'));
}

try {
    $pdo->beginTransaction();
    $find = $pdo->prepare("SELECT e.id_usuario,u.estado FROM empresas e JOIN usuarios u ON u.id_usuario=e.id_usuario JOIN roles r ON r.id_rol=u.id_rol WHERE e.id_empresa=:id AND r.nombre='empresa' FOR UPDATE");
    $find->execute(['id' => $companyId]);
    $company = $find->fetch();
    if (!$company || $company['estado'] !== 'pendiente') throw new DomainException('La solicitud ya fue revisada o no existe.');

    $newState = $decision === 'aprobar' ? 'activo' : 'baneado';
    set_audit_context($admin['id']);
    $update = $pdo->prepare('UPDATE usuarios SET estado=:estado WHERE id_usuario=:id');
    $update->execute(['estado' => $newState, 'id' => $company['id_usuario']]);

    // Store the account decision as a before/after JSON audit record.
    $audit = $pdo->prepare("INSERT INTO bitacora (id_usuario,accion,tabla_afectada,id_registro,datos_anteriores,datos_nuevos,ip_origen) VALUES (:actor,'CAMBIO_CUENTA','empresas',:record,JSON_OBJECT('estado',:old_state),JSON_OBJECT('estado',:new_state),:ip)");
    $audit->execute(['actor'=>$admin['id'],'record'=>$companyId,'old_state'=>$company['estado'],'new_state'=>$newState,'ip'=>substr((string)($_SERVER['REMOTE_ADDR']??''),0,45)]);
    $pdo->commit();
    flash($decision === 'aprobar' ? 'Empresa aprobada. Ya puede ingresar y consultar sus postulaciones.' : 'La solicitud de empresa fue rechazada.');
} catch (DomainException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    flash($e->getMessage(), 'error');
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log($e->__toString());
    flash('No se pudo actualizar la cuenta de empresa.', 'error');
}
redirect(app_url('admin.php#empresas'));
