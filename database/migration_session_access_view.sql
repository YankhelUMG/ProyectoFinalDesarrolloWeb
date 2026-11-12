-- Run once as the MySQL schema administrator after the session table exists.
-- PHP reads identity and role through this narrow view. Session creation/revocation
-- remains a prepared INSERT/UPDATE issued only by the PHP server.
CREATE OR REPLACE SQL SECURITY DEFINER VIEW vw_sesion_acceso AS
SELECT s.id_usuario,s.token_hash,s.revocada_en,s.expira_en,
       u.usuario,u.nombres,u.apellidos,u.correo,u.estado,r.nombre AS role
FROM sesiones_usuario s
JOIN usuarios u ON u.id_usuario=s.id_usuario
JOIN roles r ON r.id_rol=u.id_rol;

GRANT SELECT ON sistema_practicas.vw_sesion_acceso TO 'usr_proyecto'@'localhost';
