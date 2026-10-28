<?php
if ($this->ruta === ['csrf']) Respuesta::exito(['csrf_token' => Sesion::csrfNuevo()]);
if ($this->ruta === ['sistema']) Respuesta::exito((new ConfiguracionRepo())->publicos());
if ($this->ruta === ['auth', 'me']) {
    $usuario = Sesion::actual(true);
    $usuario['csrf_token'] = Sesion::csrfNuevo();
    Respuesta::exito($usuario);
}
switch ($this->ruta[0] ?? '') {
    case 'auth': $this->autenticacion();
    case 'catalogos': $this->catalogos();
}
