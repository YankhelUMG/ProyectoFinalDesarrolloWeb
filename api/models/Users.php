<?php

class Users
{
    private Connection $connection;
    private Response $response;

    public function __construct(Connection $connection, Response $response)
    {
        $this->connection = $connection;
        $this->response = $response;
    }

    function getAll()
    {
        $query = "SELECT id as codigo, username as usuario, name as nombre, status as estado FROM usuarios ORDER BY id DESC";
        $statement = $this->connection->prepare($query);
        $statement->execute();
        $result = $statement->fetchAll(PDO::FETCH_ASSOC);
        $this->response->success("Usuarios obtenidos correctamente", $result, 1);
    }

    function add($arrayData)
    {
        $username = $arrayData['usuario'];
        $name = $arrayData['nombre'];
        $password = $arrayData['contraseña'];
        $email = $arrayData['correo'];
        $status = $arrayData['estado'];

        $query = "INSERT INTO usuarios (name, username, email, password, status) VALUES (:name, :username, :email, :password, :status)";
        $statement = $this->connection->prepare($query);
        $statement->execute([
            'name' => $name,
            'username' => $username,
            'email' => $email,
            'password' => $password,
            'status' => $status
        ]);
        $this->response->success("Usuario agregado correctamente", [], 1);
    }
}
