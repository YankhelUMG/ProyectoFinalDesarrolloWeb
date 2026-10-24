USE sistema_practicas;
SET NAMES utf8mb4;

-- Cuentas para la demostración de clase. Cambia estas contraseñas después de importar.
-- Tres cuentas de demostración: superadministración, estudiante y empresa.
INSERT IGNORE INTO usuarios (usuario,id_rol,nombres,apellidos,correo,password_hash,estado)
SELECT 'superadmin',r.id_rol,'Super','Administrador','superadmin@practicas.edu.gt',
       '$2y$10$pcDWuExGAYS9QTNBuQuT1.qSeXyYpy57n58gcvCRQjPormVinpnca','activo'
FROM roles r WHERE r.nombre='superadministrador';
INSERT IGNORE INTO administradores (id_usuario,cargo)
SELECT u.id_usuario,'Administración del sistema' FROM usuarios u
WHERE u.correo='superadmin@practicas.edu.gt';

INSERT IGNORE INTO usuarios (usuario,id_rol,nombres,apellidos,correo,password_hash,estado)
SELECT 'andrea',r.id_rol,'Andrea','Méndez','andrea@estudiante.edu.gt',
       '$2y$10$oP7DmVToaq0xtz0IdRsXH.Q4/tfvcUn/NDoVUcag8xm34lkXCRawe','activo'
FROM roles r WHERE r.nombre='estudiante';
INSERT IGNORE INTO estudiantes (id_usuario,carnet,carrera,semestre,universidad)
SELECT u.id_usuario,'0900-22-12345','Ingeniería en Sistemas','8.º semestre','Universidad Mariano Gálvez'
FROM usuarios u WHERE u.correo='andrea@estudiante.edu.gt';

INSERT IGNORE INTO usuarios (usuario,id_rol,nombres,apellidos,correo,password_hash,estado)
SELECT 'innovatech',r.id_rol,'Contacto','Innovatech','contacto@innovatech.edu.gt',
       '$2y$10$/RpE0w2UOLe9wSfA3KHSJO7IyConb9db5./6ONmjYe1mQpUBRnF4K','activo'
FROM roles r WHERE r.nombre='empresa';
INSERT IGNORE INTO empresas (nombre_legal,nombre_comercial,sector,descripcion,correo_contacto,telefono,sitio_web,direccion,id_usuario)
SELECT 'Innovatech Guatemala, S.A.','Innovatech','Tecnología','Empresa de soluciones digitales y desarrollo de software.',
       'talento@innovatech.edu.gt','+502 5555-0123','https://example.com','Ciudad de Guatemala',u.id_usuario
FROM usuarios u WHERE u.correo='contacto@innovatech.edu.gt';

INSERT INTO oportunidades (id_empresa,id_administrador,titulo,descripcion,requisitos,ubicacion,modalidad,tipo_practica,remuneracion,moneda,vacantes,fecha_inicio,fecha_fin,fecha_limite_postulacion,estado,publicada_en)
SELECT e.id_empresa,a.id_administrador,'Practicante de desarrollo web',
       'Participa en el desarrollo de plataformas web con un equipo multidisciplinario. Colaborarás en el diseño, implementación y revisión de funcionalidades para clientes locales.',
       'Estudiante de Ingeniería en Sistemas o carrera afín. Conocimientos básicos de PHP, HTML, CSS y bases de datos relacionales. Interés por aprender y trabajar en equipo.',
       'Ciudad de Guatemala','hibrida','Desarrollo de software',1800.00,'GTQ',2,CURRENT_DATE,DATE_ADD(CURRENT_DATE,INTERVAL 4 MONTH),DATE_ADD(CURRENT_DATE,INTERVAL 30 DAY),'publicada',NOW()
FROM empresas e JOIN administradores a JOIN usuarios u ON u.id_usuario=a.id_usuario
WHERE e.nombre_comercial='Innovatech' AND u.correo='superadmin@practicas.edu.gt'
  AND NOT EXISTS (SELECT 1 FROM oportunidades o WHERE o.titulo='Practicante de desarrollo web');

INSERT INTO oportunidades (id_empresa,id_administrador,titulo,descripcion,requisitos,ubicacion,modalidad,tipo_practica,remuneracion,moneda,vacantes,fecha_inicio,fecha_fin,fecha_limite_postulacion,estado,publicada_en)
SELECT e.id_empresa,a.id_administrador,'Analista de datos en formación',
       'Apoya al equipo de datos en la limpieza de información, elaboración de reportes y automatización de análisis para proyectos internos.',
       'Estudiante de Sistemas, Informática o carrera relacionada. Excel intermedio y nociones de SQL. Deseable Python básico.',
       'Zona 10, Ciudad de Guatemala','presencial','Análisis de datos',1500.00,'GTQ',1,DATE_ADD(CURRENT_DATE,INTERVAL 1 MONTH),DATE_ADD(CURRENT_DATE,INTERVAL 5 MONTH),DATE_ADD(CURRENT_DATE,INTERVAL 45 DAY),'publicada',NOW()
FROM empresas e JOIN administradores a JOIN usuarios u ON u.id_usuario=a.id_usuario
WHERE e.nombre_comercial='Innovatech' AND u.correo='superadmin@practicas.edu.gt'
  AND NOT EXISTS (SELECT 1 FROM oportunidades o WHERE o.titulo='Analista de datos en formación');

INSERT IGNORE INTO palabras_clave (id_oportunidad,nombre,nombre_busqueda,categoria)
SELECT o.id_oportunidad,'PHP','php','habilidad' FROM oportunidades o WHERE o.titulo='Practicante de desarrollo web';
INSERT IGNORE INTO palabras_clave (id_oportunidad,nombre,nombre_busqueda,categoria)
SELECT o.id_oportunidad,'Desarrollo web','desarrollo web','area' FROM oportunidades o WHERE o.titulo='Practicante de desarrollo web';
INSERT IGNORE INTO palabras_clave (id_oportunidad,nombre,nombre_busqueda,categoria)
SELECT o.id_oportunidad,'SQL','sql','habilidad' FROM oportunidades o WHERE o.titulo='Analista de datos en formación';

-- Solicitud inicial para que el panel y el seguimiento tengan datos desde la primera prueba.
INSERT IGNORE INTO postulaciones (id_estudiante,id_oportunidad,id_estado,carta_presentacion)
SELECT est.id_estudiante,o.id_oportunidad,ep.id_estado,
       'Me interesa aplicar mis conocimientos de ingeniería y aprender con un equipo profesional.'
FROM estudiantes est JOIN usuarios u ON u.id_usuario=est.id_usuario
JOIN oportunidades o ON o.titulo='Practicante de desarrollo web'
JOIN estados_postulacion ep ON ep.codigo='recibida'
WHERE u.correo='andrea@estudiante.edu.gt';


-- Mantiene credenciales de demostración consistentes si el archivo se vuelve a importar.
UPDATE usuarios SET usuario='superadmin', password_hash='$2y$10$pcDWuExGAYS9QTNBuQuT1.qSeXyYpy57n58gcvCRQjPormVinpnca' WHERE correo='superadmin@practicas.edu.gt';
UPDATE usuarios SET usuario='andrea', password_hash='$2y$10$oP7DmVToaq0xtz0IdRsXH.Q4/tfvcUn/NDoVUcag8xm34lkXCRawe' WHERE correo='andrea@estudiante.edu.gt';
UPDATE usuarios SET usuario='innovatech', password_hash='$2y$10$/RpE0w2UOLe9wSfA3KHSJO7IyConb9db5./6ONmjYe1mQpUBRnF4K' WHERE correo='contacto@innovatech.edu.gt';
