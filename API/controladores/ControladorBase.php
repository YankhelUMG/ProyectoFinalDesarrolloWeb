<?php
declare(strict_types=1);
/** Datos de la petición y validaciones compartidas. */
abstract class ControladorBase {
    protected CatalogoRepo $catalogos;
    protected UsuarioRepo $usuarios;
    protected PracticaRepo $practicas;
    protected PostulacionRepo $postulaciones;
    protected EmpresaRepo $empresas;
    protected EstudianteRepo $estudiantes;
    public function __construct(protected array $ruta, protected string $metodo, protected array $datos) {
        $this->catalogos = new CatalogoRepo();
        $this->usuarios = new UsuarioRepo();
        $this->practicas = new PracticaRepo();
        $this->postulaciones = new PostulacionRepo();
        $this->empresas = new EmpresaRepo();
        $this->estudiantes = new EstudianteRepo();
    }
    protected function datosReporte(): array {
        $resumenPracticas = $this->practicas->reporteEstados();
        $resumenPostulaciones = $this->postulaciones->reporteEstados();
        return ['practicas' => $resumenPracticas, 'postulaciones' => $resumenPostulaciones];
    }
    protected function texto(string $campo, int $maximo, bool $obligatorio): ?string {
        $valorCampo = $this->datos[$campo] ?? null;
        $etiquetas = [
            'id_area' => 'área',
            'id_modalidad' => 'modalidad',
            'id_carrera' => 'carrera',
            'fecha_inicio' => 'fecha de inicio',
            'fecha_limite_postulacion' => 'fecha límite de postulación',
            'cupo_maximo' => 'cupo máximo',
            'correo_contacto' => 'correo de contacto',
            'identificacion_fiscal' => 'identificación fiscal',
            'nombre_legal' => 'nombre legal',
            'nombre_comercial' => 'nombre comercial',
            'carta_presentacion' => 'carta de presentación',
            'contrasena_nueva' => 'contraseña nueva',
            'contrasena' => 'contraseña',
        ];
        $nombre = $etiquetas[$campo] ?? str_replace('_', ' ', $campo);
        if ($valorCampo === null) {
            if ($obligatorio) throw new DomainException("El campo $nombre es obligatorio.");
            return null;
        }
        if (!is_scalar($valorCampo)) throw new DomainException("El campo $nombre no es válido.");
        $valorCampo = trim((string)$valorCampo);
        if ($valorCampo === '' && !$obligatorio) return null;
        if ($valorCampo === '' || mb_strlen($valorCampo) > $maximo) throw new DomainException("Revisa el campo $nombre.");
        return $valorCampo;
    }
    protected function nombrePersona(string $campo): string {
        $valorCampo = $this->texto($campo, 100, true);
        if (!preg_match('/^[\p{L}\p{M}]+(?: +[\p{L}\p{M}]+)*$/u', $valorCampo)) {
            $etiqueta = $campo === 'nombres' ? 'Los nombres' : 'Los apellidos';
            throw new DomainException($etiqueta . ' solo pueden contener letras y espacios.');
        }
        return mb_convert_case($valorCampo, MB_CASE_TITLE, 'UTF-8');
    }
    protected function universidad(): string {
        $valorCampo = $this->texto('universidad', 180, true);
        $opciones = ['Universidad Mariano Gálvez de Guatemala', 'Universidad Rafael Landívar'];
        if (!in_array($valorCampo, $opciones, true)) {
            throw new DomainException('Selecciona Universidad Mariano Gálvez de Guatemala o Universidad Rafael Landívar.');
        }
        return $valorCampo;
    }
    protected function carnet(): string {
        $valorCampo = $this->texto('carnet', 40, true);
        if (!preg_match('/^(?=.*[0-9])[0-9-]+$/D', $valorCampo)) {
            throw new DomainException('El carné solo puede contener números y guiones, e incluir al menos un número.');
        }
        return $valorCampo;
    }
    protected function correo(string $campo): string {
        $valorCampo = $this->texto($campo, 254, true);
        if (filter_var($valorCampo, FILTER_VALIDATE_EMAIL) === false) throw new DomainException('Ingresa un correo electrónico válido.');
        return mb_strtolower($valorCampo);
    }
    protected function correoValido(string $valorCampo): bool {
        return filter_var($valorCampo, FILTER_VALIDATE_EMAIL) !== false;
    }
    protected function semestre(): ?string {
        $valorCampo = $this->texto('semestre', 40, false);
        if ($valorCampo !== null && (!preg_match('/^[0-9]+$/D', $valorCampo) || (int)$valorCampo < 1)) {
            throw new DomainException('El semestre debe ser un número entero positivo, por ejemplo 1.');
        }
        return $valorCampo;
    }
    protected function telefono(string $campo, bool $obligatorio): ?string {
        $valorCampo = $this->texto($campo, 8, $obligatorio);
        if ($valorCampo !== null && !preg_match('/^[0-9]{8}$/', $valorCampo)) throw new DomainException('El teléfono debe contener exactamente ocho dígitos.');
        return $valorCampo;
    }
    protected function claveValida(): string {
        $valorCampo = $this->texto('contrasena', 200, true);
        $min = max(8, (int)(new ConfiguracionRepo())->obtener('password_longitud_minima', '10'));
        if (strlen($valorCampo) < $min) throw new DomainException('La contraseña no cumple la longitud mínima configurada.');
        return $valorCampo;
    }
    protected function entero(string $campo): int {
        $valorCampo = $this->datos[$campo] ?? null;
        if (filter_var($valorCampo, FILTER_VALIDATE_INT) === false || (int)$valorCampo < 1) throw new DomainException("El campo $campo debe ser un entero positivo.");
        return (int)$valorCampo;
    }
    protected function paginacion(): array {
        $conf = new ConfiguracionRepo();
        $max = max(1, (int)$conf->obtener('paginacion_tamano', '20'));
        $tope = max($max, (int)$conf->obtener('paginacion_maxima', '100'));
        $limite = min($tope, max(1, (int)($_GET['por_pagina'] ?? $max)));
        $pagina = max(1, (int)($_GET['pagina'] ?? 1));
        return ['limite' => $limite, 'pagina' => $pagina, 'offset' => ($pagina - 1)*$limite];
    }
    protected function validarComentario(?string $comentario, bool $requerido): void {
        $min = max(1, (int)(new ConfiguracionRepo())->obtener('comentario_rechazo_minimo', '10'));
        if ($requerido && ($comentario === null || mb_strlen(trim($comentario)) < $min)) throw new DomainException('El comentario es obligatorio y no cumple el mínimo configurado.');
    }
    protected function transmitirCv(array $cv, string $entidad, string $registro, int $usuarioId): never {
        if (!empty($cv['cv_eliminado'])) Respuesta::error((new ConfiguracionRepo())->mensaje('error_cv_no_disponible', 'El CV no está disponible.'), 404);
        $ruta = ArchivosCv::resolverRuta((string)($cv['cv_ruta'] ?? ''));
        if (!$ruta) Respuesta::error((new ConfiguracionRepo())->mensaje('error_cv_no_disponible', 'El CV no está disponible.'), 404);
        $nombre = basename((string)($cv['cv_nombre'] ?? 'CV.pdf'));
        (new BitacoraRepo())->registrar('DESCARGA_CV', $entidad, $registro, null, ['descargado' => true], $usuarioId);
        header('Content-Type: application/pdf');
        header('Content-Length: ' . filesize($ruta));
        header('Content-Disposition: attachment; filename="CV.pdf"; filename*=UTF-8\'\'' . rawurlencode($nombre));
        header('Cache-Control: private, no-store');
        readfile($ruta);
        exit;
    }
}
