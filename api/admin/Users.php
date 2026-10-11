<?php
require_once("../core/Connection.php");
require_once("../models/Users.php");
require_once("../core/Response.php");

$conection = new Connection();
$response = new Response();
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        try {
            $user = new Users($conection, $response);
            $user->getAll();
        } catch (\Throwable $th) {
            $response->error("Error al obtener los resultados", 2001, 400);
            #$response->debug(null, $th);
        }
        break;
    case 'POST':
        $username = $_POST['usuario'] ?? null;
        $name = $_POST['nombre'] ?? null;
        $email = $_POST['correo'] ?? null;
        $password = $_POST['contraseña'] ?? null;
        $status = $_POST['estado'] ?? null;

        if(is_null($status) || !isset($username) ||  !isset($name) ||  !isset($email) ||  !isset($password)){
            $response->error("Faltan datos obligatorios", 2005, 400);
            return;
        }

        try {
            $user = new Users($conection, $response);
            $user->add($_POST);
        } catch (\Throwable $th) {
            $response->error("Error al agregar el usuario", 2002, 400);
            #$response->debug(null, $th);
        }
        break;
    case 'PUT':
        try {
        } catch (\Throwable $th) {
            $response->error("Error al actualizar el usuario", 2003, 400);
        }
        break;
    case 'DELETE':
        try {
        } catch (\Throwable $th) {
            $response->error("Error al eliminar el usuario", 2004, 400);
        }
        break;
}
