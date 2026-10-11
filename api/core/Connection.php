<?php 
require_once(__DIR__  .  "/Configuration.php");
require_once(__DIR__  .  "/Response.php");

date_default_timezone_set("America/Guatemala");

class Connection extends PDO
{
    public function __construct()
    {
        try {
            $dsn = "mysql:host=" . HOST_DB . ";dbname=" . DATABASE . ";charset=" . CHARSET;
            parent::__construct($dsn, USER_DB, PASSWORD_DB);
            $this->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
          //  Response::error("Ocurrio un error", -1001, 400);
            Response::debug(null, $e);
        }
    }
}
