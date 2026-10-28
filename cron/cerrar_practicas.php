<?php
declare(strict_types = 1);
// Esta tarea solo se ejecuta desde la consola.
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Acceso no permitido.\n");
}
require_once dirname(__DIR__) . '/config/bootstrap.php';
// Cierra las prácticas vencidas o sin cupos disponibles.
try {
    $cantidad = (new ServicioCierre())->procesarAutomaticos();
    (new BitacoraRepo())->registrar('EJECUCION_CRON', 'sistema', null, null, ['practicas_cerradas' => $cantidad], null);
    echo date('c') . ' - Cierres procesados: ' . $cantidad . PHP_EOL;
} catch (Throwable $e) {
    error_log('Cron de cierre de prácticas: ' . $e->__toString());
    try {
        (new BitacoraRepo())->registrar('ERROR_CRON', 'sistema', null, null, ['mensaje' => 'No se completó el cierre automático.'], null);
    } catch (Throwable $errorBitacora) {
        error_log('No fue posible escribir el error del cron en bitácora.');
    }
    fwrite(STDERR, "No se pudo completar el cierre automático. Revisa el log de PHP.\n");
    exit(1);
}
