<?php
declare(strict_types = 1);
// Gestiona la revisión de solicitudes de empresa.
require_once dirname(__DIR__, 2) . '/App.php';
$method = method_override();
$pdo = db();
// Permite consultar solicitudes de empresa.
if ($method === 'GET') {
    require_role('superadmin');
    require_permission('empresas.aprobar');
    $stmt = $pdo->query("SELECT e.id_empresa,e.nombre_legal,e.nombre_comercial,e.identificacion_fiscal,e.sector,e.correo_contacto,e.telefono,e.sitio_web,e.direccion,e.descripcion,e.id_usuario,e.nombres,e.apellidos,e.correo,e.estado,(SELECT JSON_UNQUOTE(JSON_EXTRACT(b.datos_nuevos,'$.decision')) FROM vw_admin_bitacora b WHERE b.tabla_afectada='empresas' AND b.id_registro=e.id_empresa AND b.accion IN ('REVISION_EMPRESA','REENVIO_EMPRESA') ORDER BY b.id_bitacora DESC LIMIT 1) AS estado_revision,(SELECT JSON_UNQUOTE(JSON_EXTRACT(b.datos_nuevos,'$.comentario')) FROM vw_admin_bitacora b WHERE b.tabla_afectada='empresas' AND b.id_registro=e.id_empresa AND b.accion='REVISION_EMPRESA' ORDER BY b.id_bitacora DESC LIMIT 1) AS comentario_revision FROM vw_admin_empresas_revision e WHERE e.estado='pendiente' ORDER BY e.id_empresa DESC");
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($stmt->fetchAll(), JSON_UNESCAPED_UNICODE);
    exit;
}
// Solo administración puede aprobar o rechazar empresas.
if ($method !== 'PUT') {
    http_response_code(405);
    header('Allow: GET, PUT');
    exit('Método no permitido.');
}
verify_csrf();
$admin = require_role('superadmin');
require_permission('empresas.aprobar');
$companyId = filter_var($_POST['id_empresa'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$decision = $_POST['decision'] ?? null;
$comment = post_value('comentario_revision', 2000);
if (!$companyId || !in_array($decision, ['aprobar', 'solicitar_cambios', 'rechazar'], true)) {
    flash('La empresa o decisión indicada no es válida.', 'error');
    redirect(app_url('admin.php#empresas'));
}
if (in_array($decision, ['solicitar_cambios', 'rechazar'], true) && !$comment) {
    flash('Escribe un comentario para indicar los cambios solicitados o el motivo del rechazo.', 'error');
    redirect(app_url('admin.php#empresas'));
}
try {
    $pdo->beginTransaction();
    // Bloquea los registros durante la actualización.
    $find = $pdo->prepare("SELECT e.id_usuario,u.estado,e.nombre_legal,e.nombre_comercial,e.identificacion_fiscal,e.sector,e.descripcion,e.correo_contacto,e.telefono,e.sitio_web,e.direccion FROM empresas e JOIN usuarios u ON u.id_usuario=e.id_usuario WHERE e.id_empresa=:id AND EXISTS (SELECT 1 FROM vw_admin_empresas_revision v WHERE v.id_empresa=e.id_empresa) FOR UPDATE");
    $find->execute(['id' => $companyId]);
    $company = $find->fetch();
    if (!$company || $company['estado'] !== 'pendiente') throw new DomainException('La solicitud ya fue revisada o no existe.');
    // Conserva pendientes las empresas no aprobadas y registra la decisión.
    $newState = $decision === 'aprobar' ? 'activo': 'pendiente';
    set_audit_context($admin['id']);
    $update = $pdo->prepare('UPDATE usuarios SET estado=:estado WHERE id_usuario=:id');
    $update->execute(['estado' => $newState, 'id' => $company['id_usuario']]);
    // Registra la decisión y los datos revisados en la bitácora.
    $audit = $pdo->prepare("INSERT INTO bitacora (id_usuario,accion,tabla_afectada,id_registro,datos_anteriores,datos_nuevos,ip_origen) VALUES (:actor,'REVISION_EMPRESA','empresas',:record,:before_json,:after_json,:ip)");
    $before = json_encode(['estado_cuenta' => $company['estado'], 'nombre_legal' => $company['nombre_legal'], 'nombre_comercial' => $company['nombre_comercial'], 'identificacion_fiscal' => $company['identificacion_fiscal'], 'sector' => $company['sector'], 'descripcion' => $company['descripcion'], 'correo_contacto' => $company['correo_contacto'], 'telefono' => $company['telefono'], 'sitio_web' => $company['sitio_web'], 'direccion' => $company['direccion']], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    $after = json_encode(['decision' => $decision, 'estado_cuenta' => $newState, 'comentario' => $decision === 'aprobar' ? null: $comment, 'revisor' => $admin['name']], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    $audit->execute(['actor' => $admin['id'], 'record' => $companyId, 'before_json' => $before, 'after_json' => $after, 'ip' => substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45)]);
    $pdo->commit();
    flash(match($decision) {
            'aprobar' => 'Empresa aprobada. Ya puede publicar oportunidades.', 'solicitar_cambios' => 'Se solicitaron cambios. La empresa podrá corregir su perfil y reenviarlo.', 'rechazar' => 'Empresa rechazada con comentario; la cuenta podrá corregir la información y reenviar la solicitud.'
    });
} catch (DomainException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    flash($e->getMessage(), 'error');
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log($e->__toString());
    flash('No se pudo actualizar la cuenta de empresa.', 'error');
}
redirect(app_url('admin.php#empresas'));
