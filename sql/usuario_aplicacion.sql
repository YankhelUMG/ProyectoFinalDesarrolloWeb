-- Permisos mínimos del usuario usado por la aplicación.
-- Ejecutar después de esquema, triggers, vistas y datos de demostración.
USE sistema_practicas;

CREATE USER IF NOT EXISTS 'practicas_app'@'localhost' IDENTIFIED BY 'CAMBIAR_POR_UNA_CLAVE_LARGA';

CREATE USER IF NOT EXISTS 'practicas_app'@'127.0.0.1' IDENTIFIED BY 'CAMBIAR_POR_UNA_CLAVE_LARGA';

ALTER USER 'practicas_app'@'localhost' IDENTIFIED BY 'CAMBIAR_POR_UNA_CLAVE_LARGA';

ALTER USER 'practicas_app'@'127.0.0.1' IDENTIFIED BY 'CAMBIAR_POR_UNA_CLAVE_LARGA';

REVOKE ALL PRIVILEGES, GRANT OPTION FROM 'practicas_app'@'localhost';

REVOKE ALL PRIVILEGES, GRANT OPTION FROM 'practicas_app'@'127.0.0.1';

GRANT SELECT, INSERT ON sistema_practicas.bitacora TO 'practicas_app'@'localhost';
GRANT SELECT ON sistema_practicas.roles TO 'practicas_app'@'localhost';
GRANT SELECT ON sistema_practicas.permisos TO 'practicas_app'@'localhost';
GRANT SELECT, INSERT, UPDATE ON sistema_practicas.estados TO 'practicas_app'@'localhost';
GRANT SELECT ON sistema_practicas.tipos_entidad TO 'practicas_app'@'localhost';
GRANT SELECT, INSERT, UPDATE ON sistema_practicas.usuarios TO 'practicas_app'@'localhost';
GRANT SELECT, INSERT, UPDATE ON sistema_practicas.sesiones_usuario TO 'practicas_app'@'localhost';
GRANT SELECT ON sistema_practicas.rol_permiso TO 'practicas_app'@'localhost';
GRANT SELECT, INSERT, UPDATE ON sistema_practicas.areas TO 'practicas_app'@'localhost';
GRANT SELECT, INSERT, UPDATE ON sistema_practicas.carreras TO 'practicas_app'@'localhost';
GRANT SELECT, INSERT, UPDATE ON sistema_practicas.modalidades TO 'practicas_app'@'localhost';
GRANT SELECT, INSERT, UPDATE ON sistema_practicas.transiciones_estado TO 'practicas_app'@'localhost';
GRANT SELECT, INSERT, UPDATE ON sistema_practicas.estudiantes TO 'practicas_app'@'localhost';
GRANT SELECT, INSERT, UPDATE ON sistema_practicas.empresas TO 'practicas_app'@'localhost';
GRANT SELECT, INSERT, UPDATE ON sistema_practicas.practicas TO 'practicas_app'@'localhost';
GRANT SELECT, INSERT, UPDATE ON sistema_practicas.postulaciones TO 'practicas_app'@'localhost';
GRANT SELECT, INSERT ON sistema_practicas.historial_estados TO 'practicas_app'@'localhost';
GRANT SELECT, UPDATE ON sistema_practicas.configuracion TO 'practicas_app'@'localhost';
GRANT SELECT, INSERT, UPDATE ON sistema_practicas.mensajes TO 'practicas_app'@'localhost';
GRANT SELECT, INSERT ON sistema_practicas.intentos_login TO 'practicas_app'@'localhost';
GRANT SELECT ON sistema_practicas.vw_catalogos TO 'practicas_app'@'localhost';
GRANT SELECT ON sistema_practicas.vw_usuario_acceso TO 'practicas_app'@'localhost';
GRANT SELECT ON sistema_practicas.vw_usuario_permisos TO 'practicas_app'@'localhost';
GRANT SELECT ON sistema_practicas.vw_estudiantes TO 'practicas_app'@'localhost';
GRANT SELECT ON sistema_practicas.vw_empresas TO 'practicas_app'@'localhost';
GRANT SELECT ON sistema_practicas.vw_usuarios_admin TO 'practicas_app'@'localhost';
GRANT SELECT ON sistema_practicas.vw_empresas_revision TO 'practicas_app'@'localhost';
GRANT SELECT ON sistema_practicas.vw_practicas_publicadas TO 'practicas_app'@'localhost';
GRANT SELECT ON sistema_practicas.vw_practicas_empresa TO 'practicas_app'@'localhost';
GRANT SELECT ON sistema_practicas.vw_practicas_admin TO 'practicas_app'@'localhost';
GRANT SELECT ON sistema_practicas.vw_postulaciones_estudiante TO 'practicas_app'@'localhost';
GRANT SELECT ON sistema_practicas.vw_postulaciones_empresa TO 'practicas_app'@'localhost';
GRANT SELECT ON sistema_practicas.vw_postulaciones_admin TO 'practicas_app'@'localhost';
GRANT SELECT ON sistema_practicas.vw_historial_postulaciones TO 'practicas_app'@'localhost';
GRANT SELECT ON sistema_practicas.vw_bitacora TO 'practicas_app'@'localhost';
GRANT SELECT ON sistema_practicas.vw_dashboard_practicas TO 'practicas_app'@'localhost';
GRANT SELECT ON sistema_practicas.vw_dashboard_postulaciones TO 'practicas_app'@'localhost';

GRANT SELECT, INSERT ON sistema_practicas.bitacora TO 'practicas_app'@'127.0.0.1';
GRANT SELECT ON sistema_practicas.roles TO 'practicas_app'@'127.0.0.1';
GRANT SELECT ON sistema_practicas.permisos TO 'practicas_app'@'127.0.0.1';
GRANT SELECT, INSERT, UPDATE ON sistema_practicas.estados TO 'practicas_app'@'127.0.0.1';
GRANT SELECT ON sistema_practicas.tipos_entidad TO 'practicas_app'@'127.0.0.1';
GRANT SELECT, INSERT, UPDATE ON sistema_practicas.usuarios TO 'practicas_app'@'127.0.0.1';
GRANT SELECT, INSERT, UPDATE ON sistema_practicas.sesiones_usuario TO 'practicas_app'@'127.0.0.1';
GRANT SELECT ON sistema_practicas.rol_permiso TO 'practicas_app'@'127.0.0.1';
GRANT SELECT, INSERT, UPDATE ON sistema_practicas.areas TO 'practicas_app'@'127.0.0.1';
GRANT SELECT, INSERT, UPDATE ON sistema_practicas.carreras TO 'practicas_app'@'127.0.0.1';
GRANT SELECT, INSERT, UPDATE ON sistema_practicas.modalidades TO 'practicas_app'@'127.0.0.1';
GRANT SELECT, INSERT, UPDATE ON sistema_practicas.transiciones_estado TO 'practicas_app'@'127.0.0.1';
GRANT SELECT, INSERT, UPDATE ON sistema_practicas.estudiantes TO 'practicas_app'@'127.0.0.1';
GRANT SELECT, INSERT, UPDATE ON sistema_practicas.empresas TO 'practicas_app'@'127.0.0.1';
GRANT SELECT, INSERT, UPDATE ON sistema_practicas.practicas TO 'practicas_app'@'127.0.0.1';
GRANT SELECT, INSERT, UPDATE ON sistema_practicas.postulaciones TO 'practicas_app'@'127.0.0.1';
GRANT SELECT, INSERT ON sistema_practicas.historial_estados TO 'practicas_app'@'127.0.0.1';
GRANT SELECT, UPDATE ON sistema_practicas.configuracion TO 'practicas_app'@'127.0.0.1';
GRANT SELECT, INSERT, UPDATE ON sistema_practicas.mensajes TO 'practicas_app'@'127.0.0.1';
GRANT SELECT, INSERT ON sistema_practicas.intentos_login TO 'practicas_app'@'127.0.0.1';
GRANT SELECT ON sistema_practicas.vw_catalogos TO 'practicas_app'@'127.0.0.1';
GRANT SELECT ON sistema_practicas.vw_usuario_acceso TO 'practicas_app'@'127.0.0.1';
GRANT SELECT ON sistema_practicas.vw_usuario_permisos TO 'practicas_app'@'127.0.0.1';
GRANT SELECT ON sistema_practicas.vw_estudiantes TO 'practicas_app'@'127.0.0.1';
GRANT SELECT ON sistema_practicas.vw_empresas TO 'practicas_app'@'127.0.0.1';
GRANT SELECT ON sistema_practicas.vw_usuarios_admin TO 'practicas_app'@'127.0.0.1';
GRANT SELECT ON sistema_practicas.vw_empresas_revision TO 'practicas_app'@'127.0.0.1';
GRANT SELECT ON sistema_practicas.vw_practicas_publicadas TO 'practicas_app'@'127.0.0.1';
GRANT SELECT ON sistema_practicas.vw_practicas_empresa TO 'practicas_app'@'127.0.0.1';
GRANT SELECT ON sistema_practicas.vw_practicas_admin TO 'practicas_app'@'127.0.0.1';
GRANT SELECT ON sistema_practicas.vw_postulaciones_estudiante TO 'practicas_app'@'127.0.0.1';
GRANT SELECT ON sistema_practicas.vw_postulaciones_empresa TO 'practicas_app'@'127.0.0.1';
GRANT SELECT ON sistema_practicas.vw_postulaciones_admin TO 'practicas_app'@'127.0.0.1';
GRANT SELECT ON sistema_practicas.vw_historial_postulaciones TO 'practicas_app'@'127.0.0.1';
GRANT SELECT ON sistema_practicas.vw_bitacora TO 'practicas_app'@'127.0.0.1';
GRANT SELECT ON sistema_practicas.vw_dashboard_practicas TO 'practicas_app'@'127.0.0.1';
GRANT SELECT ON sistema_practicas.vw_dashboard_postulaciones TO 'practicas_app'@'127.0.0.1';
