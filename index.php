<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inicio | MI PROYECTO UMG</title>
</head>
<body>

<Table>
    <form method="POST" action="api/admin/Users.php">
        <label for="usuario">Usuario</label>
        <input type="text" name="usuario" placeholder="Usuario" required>
        <label for="nombre">Nombre</label>
        <input type="text" name="nombre" placeholder="Nombre" required>
        <label for ="contraseña">Contraseña</label>
        <input type="password" name="contraseña" placeholder="Contraseña" required>
        <label for="correo">Correo</label>
        <input type="email" name="correo" placeholder="Correo" required>
        <label for="estado">Estado</label>
        <input type="text" name="estado" placeholder="Estado" required>
        <button type="submit">Registrar</button>
    </form>

</Table>
<!-- <script src="index.js"></script> -->
    
</body>
</html>

