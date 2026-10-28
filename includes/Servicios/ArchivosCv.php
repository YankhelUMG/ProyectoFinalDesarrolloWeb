<?php
declare(strict_types = 1);
/** Valida los CV y los guarda fuera de la carpeta pública. */
final class ArchivosCv {
    public function guardar(array $archivo): array {
        $max = max(1, (int)(new ConfiguracionRepo())->obtener('cv_tamano_maximo_bytes', '5242880'));
        if (($archivo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file((string)($archivo['tmp_name'] ?? ''))) throw new DomainException((new MensajeRepo())->obtener('error_cv_obligatorio', 'Adjunta un CV PDF válido.'));
        $rutaTemporal = (string)$archivo['tmp_name'];
        $tamanoReal = filesize($rutaTemporal);
        if ($tamanoReal === false || $tamanoReal < 1) throw new DomainException((new MensajeRepo())->obtener('error_cv_vacio', 'El CV está vacío o no se puede leer.'));
        if ((int)($archivo['size'] ?? 0) !== $tamanoReal || $tamanoReal > $max) throw new DomainException((new MensajeRepo())->obtener('error_cv_tamano', 'El CV supera el tamaño máximo permitido.'));
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($rutaTemporal);
        $ext = strtolower(pathinfo((string)($archivo['name'] ?? ''), PATHINFO_EXTENSION));
        $contenido = file_get_contents($rutaTemporal, false, null, 0, 5);
        $tipos = json_decode((new ConfiguracionRepo())->obtener('cv_tipos_mime', '["application/pdf"]') ?? '[]', true) ?: [];
        if ($ext !== 'pdf' || $mime !== 'application/pdf' || !in_array('application/pdf', $tipos, true) || !is_string($contenido) || !str_starts_with($contenido, '%PDF-')) throw new DomainException((new MensajeRepo())->obtener('error_cv_formato', 'Adjunta un archivo PDF válido.'));
        $dir = self::directorioPrivado();
        if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) throw new RuntimeException('No se pudo preparar el almacenamiento privado de CV.');
        $dirReal = realpath($dir);
        if ($dirReal === false || self::estaDentroDeLaRaizPublicada($dirReal)) throw new RuntimeException('El almacenamiento de CV debe estar fuera de la carpeta publicada.');
        $dir = $dirReal;
        $name = bin2hex(random_bytes(24)) . '.pdf';
        $path = rtrim($dir, '\\/') . DIRECTORY_SEPARATOR . $name;
        if (!move_uploaded_file($rutaTemporal, $path)) throw new RuntimeException('No se pudo guardar el CV.');
        chmod($path, 0600);
        return ['nombre' => substr(basename((string)$archivo['name']), 0, 255) ?: 'CV.pdf', 'ruta' => $path, 'mime' => $mime];
    }
    public static function directorioPrivado(): string {
        return getenv('PRIVATE_UPLOAD_DIR') ?: dirname(__DIR__, 4) . DIRECTORY_SEPARATOR . 'private' . DIRECTORY_SEPARATOR . 'cv';
    }
    /** Resuelve rutas registradas y rechaza enlaces o recorridos fuera del directorio privado. */
    public static function resolverRuta(string $rutaGuardada): ?string {
        $base = realpath(self::directorioPrivado());
        if ($base === false || self::estaDentroDeLaRaizPublicada($base) || $rutaGuardada === '') return null;
        $esAbsoluta = preg_match('#^(?:[A-Za-z]:[\\\\/]|\\\\\\\\|/)#', $rutaGuardada) === 1;
        $candidata = $esAbsoluta ? $rutaGuardada : $base . DIRECTORY_SEPARATOR . ltrim($rutaGuardada, '\\\\/');
        $ruta = realpath($candidata);
        if ($ruta === false || !is_file($ruta) || !str_starts_with($ruta, rtrim($base, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR)) return null;
        return $ruta;
    }
    private static function estaDentroDeLaRaizPublicada(string $ruta): bool {
        $raiz = realpath(dirname(__DIR__, 2));
        if ($raiz === false) return false;
        $normalizar = static fn(string $valor): string => strtolower(rtrim(str_replace('\\', '/', $valor), '/')) . '/';
        return str_starts_with($normalizar($ruta), $normalizar($raiz));
    }
}
