USE sistema_practicas;

-- Yankhel5 se guarda como hash bcrypt; nunca como texto plano.
-- Solo actualiza las tres cuentas indicadas y conserva sus perfiles y postulaciones.
UPDATE usuarios u JOIN roles r ON r.id_rol=u.id_rol
SET u.password_hash='$2y$10$/h190vWLnZtipdVoQtGbb.W6heoVK7s4O6W3Lj.DLxIQcqaDutnSW'
WHERE (u.usuario='Yankhel' AND r.nombre='superadministrador')
   OR (u.usuario='YankhelE' AND r.nombre='empresa')
   OR (u.usuario='YankhelS' AND r.nombre='estudiante');

-- Muestra qué cuentas están presentes, sin revelar los hashes.
SELECT u.usuario,r.nombre AS rol FROM usuarios u JOIN roles r ON r.id_rol=u.id_rol
WHERE u.usuario IN ('Yankhel','YankhelE','YankhelS')
ORDER BY FIELD(u.usuario,'Yankhel','YankhelE','YankhelS');
