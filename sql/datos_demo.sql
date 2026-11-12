-- Datos de demostración para una base recién creada.
-- Ejecutar después de sql/database.sql, sql/triggers.sql y sql/vistas.sql.
-- Incluye cuentas, empresas, estudiantes, prácticas y postulaciones en todos los estados.
USE sistema_practicas;
SET @usuario_id=NULL;
SET @ip_cliente='127.0.0.1';
SET @r_super=(SELECT id_rol FROM roles WHERE codigo='superadmin');
SET @r_admin=(SELECT id_rol FROM roles WHERE codigo='admin');
SET @r_empresa=(SELECT id_rol FROM roles WHERE codigo='empresa');
SET @r_estudiante=(SELECT id_rol FROM roles WHERE codigo='estudiante');
SET @e_activo=(SELECT id_estado FROM estados WHERE tipo_entidad='usuario' AND codigo='activo');
SET @e_pendiente=(SELECT id_estado FROM estados WHERE tipo_entidad='empresa' AND codigo='pendiente');
SET @e_aprobada=(SELECT id_estado FROM estados WHERE tipo_entidad='empresa' AND codigo='aprobada');
SET @e_rechazada=(SELECT id_estado FROM estados WHERE tipo_entidad='empresa' AND codigo='rechazada');
INSERT INTO usuarios(id_usuario,id_rol,id_estado,usuario,correo,nombres,apellidos,telefono,password_hash) VALUES
(1,@r_super,@e_activo,'superadmin','superadmin@practicas.local','Super','Administración','55550001','$2y$10$jrriRFWbwVMh4x8jVu28A.tGsL5zEz/Yt8rO3NQRc5yfBzGWKdAdC'),
(2,@r_admin,@e_activo,'admin1','admin1@practicas.local','Ana','Coordinación','55550002','$2y$10$jrriRFWbwVMh4x8jVu28A.tGsL5zEz/Yt8rO3NQRc5yfBzGWKdAdC'),
(3,@r_admin,@e_activo,'admin2','admin2@practicas.local','Luis','Coordinación','55550003','$2y$10$jrriRFWbwVMh4x8jVu28A.tGsL5zEz/Yt8rO3NQRc5yfBzGWKdAdC'),
(4,@r_empresa,@e_activo,'innovatech','innovatech@practicas.local','Marta','López','55550004','$2y$10$jrriRFWbwVMh4x8jVu28A.tGsL5zEz/Yt8rO3NQRc5yfBzGWKdAdC'),
(5,@r_empresa,@e_activo,'empresa_pendiente','pendiente@practicas.local','Carlos','Ruiz','55550005','$2y$10$jrriRFWbwVMh4x8jVu28A.tGsL5zEz/Yt8rO3NQRc5yfBzGWKdAdC'),
(6,@r_empresa,@e_activo,'empresa_rechazada','rechazada@practicas.local','Rosa','Díaz','55550006','$2y$10$jrriRFWbwVMh4x8jVu28A.tGsL5zEz/Yt8rO3NQRc5yfBzGWKdAdC'),
(7,@r_estudiante,@e_activo,'andrea','andrea@practicas.local','Andrea','García','55550007','$2y$10$jrriRFWbwVMh4x8jVu28A.tGsL5zEz/Yt8rO3NQRc5yfBzGWKdAdC'),
(8,@r_estudiante,@e_activo,'bruno','bruno@practicas.local','Bruno','Pérez','55550008','$2y$10$jrriRFWbwVMh4x8jVu28A.tGsL5zEz/Yt8rO3NQRc5yfBzGWKdAdC'),
(9,@r_estudiante,@e_activo,'camila','camila@practicas.local','Camila','Soto','55550009','$2y$10$jrriRFWbwVMh4x8jVu28A.tGsL5zEz/Yt8rO3NQRc5yfBzGWKdAdC'),
(10,@r_estudiante,@e_activo,'diego','diego@practicas.local','Diego','Morales','55550010','$2y$10$jrriRFWbwVMh4x8jVu28A.tGsL5zEz/Yt8rO3NQRc5yfBzGWKdAdC'),
(11,@r_estudiante,@e_activo,'elena','elena@practicas.local','Elena','Cruz','55550011','$2y$10$jrriRFWbwVMh4x8jVu28A.tGsL5zEz/Yt8rO3NQRc5yfBzGWKdAdC'),
(12,@r_estudiante,@e_activo,'fabian','fabian@practicas.local','Fabián','Luna','55550012','$2y$10$jrriRFWbwVMh4x8jVu28A.tGsL5zEz/Yt8rO3NQRc5yfBzGWKdAdC');
INSERT INTO historial_estados(entidad,id_registro,id_estado_anterior,id_estado_nuevo,id_usuario,comentario,creado_por)
SELECT 'usuario',id_usuario,NULL,id_estado,id_usuario,'Cuenta de demostración.',id_usuario FROM usuarios;
INSERT INTO empresas(id_empresa,id_usuario,id_estado_revision,nombre_legal,nombre_comercial,identificacion_fiscal,sector,descripcion,correo_contacto,telefono) VALUES
(1,4,@e_aprobada,'Innovación Tecnológica, S.A.','Innovatech','CF-10001','Tecnología','Empresa de demostración aprobada.','contacto@practicas.local','55550004'),
(2,5,@e_pendiente,'Servicios Profesionales, S.A.','Servicios Pro','CF-10002','Administración','Cuenta pendiente de revisión.','pendiente-contacto@practicas.local','55550005'),
(3,6,@e_rechazada,'Comercial del Norte, S.A.','Comercial Norte','CF-10003','Mercadeo','Cuenta empresarial rechazada de ejemplo.','rechazada-contacto@practicas.local','55550006');
INSERT INTO historial_estados(entidad,id_registro,id_estado_anterior,id_estado_nuevo,id_usuario,comentario,creado_por)
SELECT 'empresa',id_empresa,NULL,id_estado_revision,id_usuario,'Estado inicial de demostración.',id_usuario FROM empresas;
INSERT INTO estudiantes(id_estudiante,id_usuario,id_carrera,carnet,semestre,universidad,cv_nombre,cv_ruta,cv_mime) VALUES
(1,7,(SELECT id_carrera FROM carreras WHERE codigo='sistemas'),'20260001','Sexto','Universidad de Guatemala','cv_andrea.pdf','demo/cv_andrea.pdf','application/pdf'),
(2,8,(SELECT id_carrera FROM carreras WHERE codigo='sistemas'),'20260002','Quinto','Universidad de Guatemala','cv_bruno.pdf','demo/cv_bruno.pdf','application/pdf'),
(3,9,(SELECT id_carrera FROM carreras WHERE codigo='administracion'),'20260003','Séptimo','Universidad de Guatemala','cv_camila.pdf','demo/cv_camila.pdf','application/pdf'),
(4,10,(SELECT id_carrera FROM carreras WHERE codigo='mercadotecnia'),'20260004','Cuarto','Universidad de Guatemala','cv_diego.pdf','demo/cv_diego.pdf','application/pdf'),
(5,11,(SELECT id_carrera FROM carreras WHERE codigo='contaduria'),'20260005','Octavo','Universidad de Guatemala','cv_elena.pdf','demo/cv_elena.pdf','application/pdf'),
(6,12,(SELECT id_carrera FROM carreras WHERE codigo='diseno_grafico'),'20260006','Sexto','Universidad de Guatemala','cv_fabian.pdf','demo/cv_fabian.pdf','application/pdf');
SET @area=(SELECT id_area FROM areas WHERE codigo='tecnologia'); SET @modalidad=(SELECT id_modalidad FROM modalidades WHERE codigo='hibrida');
INSERT INTO practicas(id_practica,id_empresa,id_estado,id_area,id_modalidad,titulo,descripcion,requisitos,ubicacion,cupo_maximo,fecha_limite_postulacion,publicada_en,creado_por) VALUES
(1,1,(SELECT id_estado FROM estados WHERE tipo_entidad='practica' AND codigo='publicada'),@area,@modalidad,'Desarrollo web con cupo completo','Práctica de demostración abierta con una plaza ocupada.','Conocimientos básicos de PHP.','Ciudad de Guatemala',1,DATE_ADD(CURRENT_DATE,INTERVAL 60 DAY),NOW(),4),
(2,1,(SELECT id_estado FROM estados WHERE tipo_entidad='practica' AND codigo='borrador'),@area,@modalidad,'Borrador de ejemplo','Registro en estado borrador.','', 'Ciudad de Guatemala',2,DATE_ADD(CURRENT_DATE,INTERVAL 90 DAY),NULL,4),
(3,1,(SELECT id_estado FROM estados WHERE tipo_entidad='practica' AND codigo='pendiente_revision'),@area,@modalidad,'Práctica pendiente de revisión','Pendiente de aprobación.','Manejo de bases de datos.','Remoto',2,DATE_ADD(CURRENT_DATE,INTERVAL 90 DAY),NULL,4),
(4,1,(SELECT id_estado FROM estados WHERE tipo_entidad='practica' AND codigo='cambios_solicitados'),@area,@modalidad,'Práctica con cambios solicitados','Requiere completar detalles.','', 'Ciudad de Guatemala',2,DATE_ADD(CURRENT_DATE,INTERVAL 90 DAY),NULL,4),
(5,1,(SELECT id_estado FROM estados WHERE tipo_entidad='practica' AND codigo='rechazada'),@area,@modalidad,'Práctica rechazada de ejemplo','No se aprobó en la revisión inicial.','', 'Remoto',2,DATE_ADD(CURRENT_DATE,INTERVAL 90 DAY),NULL,4),
(6,1,(SELECT id_estado FROM estados WHERE tipo_entidad='practica' AND codigo='cerrada'),@area,@modalidad,'Práctica cerrada de ejemplo','Convocatoria cerrada.','', 'Híbrido',1,DATE_SUB(CURRENT_DATE,INTERVAL 1 DAY),NOW(),4),
(7,1,(SELECT id_estado FROM estados WHERE tipo_entidad='practica' AND codigo='cancelada'),@area,@modalidad,'Práctica cancelada de ejemplo','Convocatoria cancelada.','', 'Ciudad de Guatemala',1,DATE_ADD(CURRENT_DATE,INTERVAL 90 DAY),NULL,4),
(8,1,(SELECT id_estado FROM estados WHERE tipo_entidad='practica' AND codigo='publicada'),@area,@modalidad,'Analista de datos en formación','Oportunidad activa para probar una nueva postulación.','SQL básico y hojas de cálculo.','Remoto',3,DATE_ADD(CURRENT_DATE,INTERVAL 45 DAY),NOW(),4);
INSERT INTO practicas(id_empresa,id_estado,id_area,id_modalidad,titulo,descripcion,requisitos,ubicacion,fecha_inicio,cupo_maximo,fecha_limite_postulacion,publicada_en,creado_por) VALUES
(1,(SELECT id_estado FROM estados WHERE tipo_entidad='practica' AND codigo='publicada'),(SELECT id_area FROM areas WHERE codigo='finanzas'),(SELECT id_modalidad FROM modalidades WHERE codigo='hibrida'),'Asistente de finanzas y presupuesto','Apoyo en el control de gastos, presupuestos y reportes financieros.','Conocimientos básicos de contabilidad y hojas de cálculo.','Ciudad de Guatemala',DATE_ADD(CURRENT_DATE,INTERVAL 90 DAY),2,DATE_ADD(CURRENT_DATE,INTERVAL 60 DAY),NOW(),4),
(1,(SELECT id_estado FROM estados WHERE tipo_entidad='practica' AND codigo='publicada'),(SELECT id_area FROM areas WHERE codigo='administracion'),(SELECT id_modalidad FROM modalidades WHERE codigo='presencial'),'Apoyo en administración de proyectos','Seguimiento de tareas, cronogramas y documentación de proyectos.','Organización, comunicación y manejo básico de hojas de cálculo.','Ciudad de Guatemala',DATE_ADD(CURRENT_DATE,INTERVAL 100 DAY),2,DATE_ADD(CURRENT_DATE,INTERVAL 70 DAY),NOW(),4),
(1,(SELECT id_estado FROM estados WHERE tipo_entidad='practica' AND codigo='publicada'),(SELECT id_area FROM areas WHERE codigo='diseno'),(SELECT id_modalidad FROM modalidades WHERE codigo='remota'),'Diseño gráfico para campañas digitales','Creación de piezas visuales para campañas y comunicación digital.','Manejo básico de herramientas de diseño y portafolio de trabajos.','Remoto',DATE_ADD(CURRENT_DATE,INTERVAL 105 DAY),2,DATE_ADD(CURRENT_DATE,INTERVAL 75 DAY),NOW(),4),
(1,(SELECT id_estado FROM estados WHERE tipo_entidad='practica' AND codigo='publicada'),(SELECT id_area FROM areas WHERE codigo='mercadeo'),(SELECT id_modalidad FROM modalidades WHERE codigo='remota'),'Análisis de mercado y contenidos','Investigación de audiencias y preparación de contenidos para redes.','Interés en mercadeo digital y redacción clara.','Remoto',DATE_ADD(CURRENT_DATE,INTERVAL 95 DAY),2,DATE_ADD(CURRENT_DATE,INTERVAL 65 DAY),NOW(),4);
INSERT INTO historial_estados(entidad,id_registro,id_estado_anterior,id_estado_nuevo,id_usuario,comentario,creado_por)
SELECT 'practica',id_practica,NULL,id_estado,creado_por,'Estado inicial de demostración.',creado_por FROM practicas;
INSERT INTO postulaciones(id_postulacion,id_estudiante,id_practica,id_estado,carta_presentacion,comentario,cv_ruta_snapshot,cv_nombre_snapshot,cv_mime_snapshot,creado_por) VALUES
(1,2,1,(SELECT id_estado FROM estados WHERE tipo_entidad='postulacion' AND codigo='aceptada'),'Me interesa esta práctica.',NULL,'demo/cv_bruno.pdf','cv_bruno.pdf','application/pdf',8),
(2,1,8,(SELECT id_estado FROM estados WHERE tipo_entidad='postulacion' AND codigo='pendiente'),'Me interesa esta práctica.',NULL,'demo/cv_andrea.pdf','cv_andrea.pdf','application/pdf',7),
(3,3,6,(SELECT id_estado FROM estados WHERE tipo_entidad='postulacion' AND codigo='rechazada'),'Gracias por considerar mi perfil.','La convocatoria concluyó.','demo/cv_camila.pdf','cv_camila.pdf','application/pdf',9),
(4,4,6,(SELECT id_estado FROM estados WHERE tipo_entidad='postulacion' AND codigo='retirada'),'Retiro mi solicitud.',NULL,'demo/cv_diego.pdf','cv_diego.pdf','application/pdf',10),
(5,5,7,(SELECT id_estado FROM estados WHERE tipo_entidad='postulacion' AND codigo='cancelada'),NULL,'La práctica fue cancelada.','demo/cv_elena.pdf','cv_elena.pdf','application/pdf',11);
INSERT INTO historial_estados(entidad,id_registro,id_estado_anterior,id_estado_nuevo,id_usuario,comentario,creado_por)
SELECT 'postulacion',po.id_postulacion,NULL,po.id_estado,po.creado_por,po.comentario,po.creado_por FROM postulaciones po;
INSERT INTO mensajes(codigo,texto,tipo) VALUES
('error_registro_estudiante','No se pudo crear el perfil de estudiante.','error'),('error_registro_empresa','No se pudo registrar la empresa.','error'),
('error_postulacion','No se pudo registrar la postulación.','error'),('error_fecha_limite','La fecha límite indicada no es válida.','error'),
('error_cupo','El cupo debe ser un entero positivo.','error'),('error_archivo_cv','Adjunta un CV PDF válido.','error'),
('error_catalogo','El valor de catálogo no está disponible.','error'),('error_transicion','El cambio de estado no está permitido.','error'),
('exito_login','Sesión iniciada.','exito'),('exito_logout','Sesión cerrada.','exito'),
('exito_registro_estudiante','Cuenta de estudiante creada.','exito'),('exito_registro_empresa','Solicitud de empresa registrada y pendiente de revisión.','exito'),
('exito_practica','Práctica guardada y enviada a revisión.','exito'),('exito_retiro','Postulación retirada.','exito'),
('exito_clave','Contraseña actualizada.','exito')
ON DUPLICATE KEY UPDATE texto=VALUES(texto),tipo=VALUES(tipo);

