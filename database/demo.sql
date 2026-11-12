USE sistema_practicas;
SET NAMES utf8mb4;

-- Cuentas para la demostración de clase. Cambia estas contraseñas después de importar.
-- Tres cuentas de demostración: superadministración, estudiante y empresa.
INSERT IGNORE INTO usuarios (usuario,id_rol,nombres,apellidos,correo,password_hash,estado)
SELECT 'Yankhel',r.id_rol,'Yankhel','Superadmin','yankhel.superadmin@practicas.edu.gt',
       '$2y$10$/h190vWLnZtipdVoQtGbb.W6heoVK7s4O6W3Lj.DLxIQcqaDutnSW','activo'
FROM roles r WHERE r.nombre='superadministrador';
INSERT IGNORE INTO administradores (id_usuario,cargo)
SELECT u.id_usuario,'Administración del sistema' FROM usuarios u
WHERE u.correo='yankhel.superadmin@practicas.edu.gt';

INSERT IGNORE INTO usuarios (usuario,id_rol,nombres,apellidos,correo,password_hash,estado)
SELECT 'YankhelS',r.id_rol,'Yankhel','Estudiante','yankhel.estudiante@practicas.edu.gt',
       '$2y$10$/h190vWLnZtipdVoQtGbb.W6heoVK7s4O6W3Lj.DLxIQcqaDutnSW','activo'
FROM roles r WHERE r.nombre='estudiante';
INSERT IGNORE INTO estudiantes (id_usuario,carnet,carrera,semestre,universidad)
SELECT u.id_usuario,'0900-22-12345','Ingeniería en Sistemas','8.º semestre','Universidad Mariano Gálvez'
FROM usuarios u WHERE u.correo='yankhel.estudiante@practicas.edu.gt';

INSERT IGNORE INTO usuarios (usuario,id_rol,nombres,apellidos,correo,password_hash,estado)
SELECT 'YankhelE',r.id_rol,'Yankhel','Empresa','yankhel.empresa@practicas.edu.gt',
       '$2y$10$/h190vWLnZtipdVoQtGbb.W6heoVK7s4O6W3Lj.DLxIQcqaDutnSW','activo'
FROM roles r WHERE r.nombre='empresa';
INSERT IGNORE INTO empresas (nombre_legal,nombre_comercial,sector,descripcion,correo_contacto,telefono,sitio_web,direccion,id_usuario)
SELECT 'Yankhel Empresa','Yankhel Empresa','Tecnología','Empresa de soluciones digitales y desarrollo de software.',
       'yankhel.empresa@practicas.edu.gt','55550123','','Ciudad de Guatemala',u.id_usuario
FROM usuarios u WHERE u.correo='yankhel.empresa@practicas.edu.gt';

-- Mantiene credenciales de demostración consistentes si el archivo se vuelve a importar.
UPDATE usuarios SET usuario='Yankhel', password_hash='$2y$10$/h190vWLnZtipdVoQtGbb.W6heoVK7s4O6W3Lj.DLxIQcqaDutnSW' WHERE correo='yankhel.superadmin@practicas.edu.gt';
UPDATE usuarios SET usuario='YankhelS', password_hash='$2y$10$/h190vWLnZtipdVoQtGbb.W6heoVK7s4O6W3Lj.DLxIQcqaDutnSW' WHERE correo='yankhel.estudiante@practicas.edu.gt';
UPDATE usuarios SET usuario='YankhelE', password_hash='$2y$10$/h190vWLnZtipdVoQtGbb.W6heoVK7s4O6W3Lj.DLxIQcqaDutnSW' WHERE correo='yankhel.empresa@practicas.edu.gt';
