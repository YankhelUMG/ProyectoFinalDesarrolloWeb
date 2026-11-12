-- Permite administrar cuentas básicas, estudiantes, empresas y perfiles administrativos.
INSERT IGNORE INTO roles(nombre,descripcion)
VALUES('usuario','Cuenta básica para consultar oportunidades públicas');

-- El rol usuario puede consultar el catálogo público, pero no postularse.
INSERT IGNORE INTO roles_permisos(id_rol,id_permiso)
SELECT r.id_rol,p.id_permiso
FROM roles r
JOIN permisos p ON p.codigo='oportunidades.consultar'
WHERE r.nombre='usuario';
