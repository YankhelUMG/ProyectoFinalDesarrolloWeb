-- Sistema de solicitudes y seguimiento de prácticas profesionales
-- Esquema y catálogos iniciales. Requiere MySQL/MariaDB con InnoDB y soporte JSON.
-- Ejecutar este archivo primero; luego sql/triggers.sql y sql/vistas.sql.

CREATE DATABASE IF NOT EXISTS sistema_practicas
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE sistema_practicas;
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE roles (
  id_rol SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  codigo VARCHAR(40) NOT NULL,
  nombre VARCHAR(80) NOT NULL,
  descripcion VARCHAR(255) NULL,
  eliminado TINYINT(1) NOT NULL DEFAULT 0, eliminado_en DATETIME NULL, eliminado_por BIGINT UNSIGNED NULL,
  codigo_activo VARCHAR(40) GENERATED ALWAYS AS (IF(eliminado=0,codigo,NULL)) STORED,
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  creado_por BIGINT UNSIGNED NULL, actualizado_por BIGINT UNSIGNED NULL,
  UNIQUE KEY uq_roles_codigo_activo (codigo_activo), KEY ix_roles_eliminado (eliminado)
) ENGINE=InnoDB;

CREATE TABLE permisos (
  id_permiso SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  codigo VARCHAR(80) NOT NULL,
  nombre VARCHAR(100) NOT NULL,
  descripcion VARCHAR(255) NULL,
  eliminado TINYINT(1) NOT NULL DEFAULT 0, eliminado_en DATETIME NULL, eliminado_por BIGINT UNSIGNED NULL,
  codigo_activo VARCHAR(80) GENERATED ALWAYS AS (IF(eliminado=0,codigo,NULL)) STORED,
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  creado_por BIGINT UNSIGNED NULL, actualizado_por BIGINT UNSIGNED NULL,
  UNIQUE KEY uq_permisos_codigo_activo (codigo_activo), KEY ix_permisos_eliminado (eliminado)
) ENGINE=InnoDB;

CREATE TABLE estados (
  id_estado SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tipo_entidad VARCHAR(32) NOT NULL,
  codigo VARCHAR(40) NOT NULL,
  nombre VARCHAR(80) NOT NULL,
  descripcion VARCHAR(255) NULL,
  color VARCHAR(24) NOT NULL DEFAULT '#64748b',
  orden SMALLINT NOT NULL DEFAULT 0,
  eliminado TINYINT(1) NOT NULL DEFAULT 0, eliminado_en DATETIME NULL, eliminado_por BIGINT UNSIGNED NULL,
  codigo_activo VARCHAR(40) GENERATED ALWAYS AS (IF(eliminado=0,codigo,NULL)) STORED,
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  creado_por BIGINT UNSIGNED NULL, actualizado_por BIGINT UNSIGNED NULL,
  UNIQUE KEY uq_estados_tipo_codigo_activo (tipo_entidad,codigo_activo),
  KEY ix_estados_tipo_orden (tipo_entidad,eliminado,orden)
) ENGINE=InnoDB;

CREATE TABLE tipos_entidad (
  id_tipo_entidad SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  codigo VARCHAR(32) NOT NULL, nombre VARCHAR(80) NOT NULL,
  eliminado TINYINT(1) NOT NULL DEFAULT 0, eliminado_en DATETIME NULL, eliminado_por BIGINT UNSIGNED NULL,
  codigo_activo VARCHAR(32) GENERATED ALWAYS AS (IF(eliminado=0,codigo,NULL)) STORED,
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  creado_por BIGINT UNSIGNED NULL, actualizado_por BIGINT UNSIGNED NULL,
  UNIQUE KEY uq_tipos_entidad_codigo (codigo), UNIQUE KEY uq_tipos_entidad_codigo_activo (codigo_activo), KEY ix_tipos_entidad_eliminado (eliminado)
) ENGINE=InnoDB;
ALTER TABLE estados
  ADD CONSTRAINT fk_estados_tipo FOREIGN KEY (tipo_entidad) REFERENCES tipos_entidad(codigo);

CREATE TABLE usuarios (
  id_usuario BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  id_rol SMALLINT UNSIGNED NOT NULL,
  id_estado SMALLINT UNSIGNED NOT NULL,
  usuario VARCHAR(40) NOT NULL,
  correo VARCHAR(254) NOT NULL,
  nombres VARCHAR(100) NOT NULL,
  apellidos VARCHAR(100) NOT NULL,
  telefono VARCHAR(8) NULL,
  password_hash VARCHAR(255) NOT NULL,
  debe_cambiar_password TINYINT(1) NOT NULL DEFAULT 0,
  ultimo_acceso DATETIME NULL,
  eliminado TINYINT(1) NOT NULL DEFAULT 0, eliminado_en DATETIME NULL, eliminado_por BIGINT UNSIGNED NULL,
  correo_activo VARCHAR(254) GENERATED ALWAYS AS (IF(eliminado=0,LOWER(correo),NULL)) STORED,
  usuario_activo VARCHAR(40) GENERATED ALWAYS AS (IF(eliminado=0,LOWER(usuario),NULL)) STORED,
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  creado_por BIGINT UNSIGNED NULL, actualizado_por BIGINT UNSIGNED NULL,
  CONSTRAINT fk_usuarios_rol FOREIGN KEY (id_rol) REFERENCES roles(id_rol),
  CONSTRAINT fk_usuarios_estado FOREIGN KEY (id_estado) REFERENCES estados(id_estado),
  CONSTRAINT chk_usuarios_telefono CHECK (telefono IS NULL OR telefono REGEXP '^[0-9]{8}$'),
  UNIQUE KEY uq_usuarios_correo_activo (correo_activo), UNIQUE KEY uq_usuarios_usuario_activo (usuario_activo),
  KEY ix_usuarios_rol_estado (id_rol,id_estado,eliminado), KEY ix_usuarios_eliminado (eliminado)
) ENGINE=InnoDB;
ALTER TABLE usuarios
  ADD CONSTRAINT fk_usuarios_creado_por FOREIGN KEY (creado_por) REFERENCES usuarios(id_usuario) ON DELETE RESTRICT,
  ADD CONSTRAINT fk_usuarios_actualizado_por FOREIGN KEY (actualizado_por) REFERENCES usuarios(id_usuario) ON DELETE RESTRICT,
  ADD CONSTRAINT fk_usuarios_eliminado_por FOREIGN KEY (eliminado_por) REFERENCES usuarios(id_usuario) ON DELETE RESTRICT;

CREATE TABLE sesiones_usuario (
  id_sesion BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  id_usuario BIGINT UNSIGNED NOT NULL,
  token_hash CHAR(64) NOT NULL,
  ip_origen VARCHAR(45) NULL, user_agent VARCHAR(500) NULL,
  creada_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  expira_en DATETIME NOT NULL, ultima_actividad DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  revocada_en DATETIME NULL,
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  creado_por BIGINT UNSIGNED NULL, actualizado_por BIGINT UNSIGNED NULL,
  eliminado TINYINT(1) NOT NULL DEFAULT 0, eliminado_en DATETIME NULL, eliminado_por BIGINT UNSIGNED NULL,
  CONSTRAINT fk_sesion_usuario FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario),
  UNIQUE KEY uq_sesion_token (token_hash), KEY ix_sesion_usuario (id_usuario,revocada_en,expira_en),
  KEY ix_sesion_eliminado (eliminado)
) ENGINE=InnoDB;

CREATE TABLE rol_permiso (
  id_rol SMALLINT UNSIGNED NOT NULL,
  id_permiso SMALLINT UNSIGNED NOT NULL,
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  creado_por BIGINT UNSIGNED NULL, actualizado_por BIGINT UNSIGNED NULL,
  eliminado TINYINT(1) NOT NULL DEFAULT 0, eliminado_en DATETIME NULL, eliminado_por BIGINT UNSIGNED NULL,
  PRIMARY KEY (id_rol,id_permiso),
  CONSTRAINT fk_rol_permiso_rol FOREIGN KEY (id_rol) REFERENCES roles(id_rol),
  CONSTRAINT fk_rol_permiso_permiso FOREIGN KEY (id_permiso) REFERENCES permisos(id_permiso),
  KEY ix_rol_permiso_eliminado (eliminado)
) ENGINE=InnoDB;

CREATE TABLE areas (
  id_area SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  codigo VARCHAR(40) NOT NULL, nombre VARCHAR(100) NOT NULL, descripcion VARCHAR(255) NULL,
  eliminado TINYINT(1) NOT NULL DEFAULT 0, eliminado_en DATETIME NULL, eliminado_por BIGINT UNSIGNED NULL,
  codigo_activo VARCHAR(40) GENERATED ALWAYS AS (IF(eliminado=0,codigo,NULL)) STORED,
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  creado_por BIGINT UNSIGNED NULL, actualizado_por BIGINT UNSIGNED NULL,
  UNIQUE KEY uq_areas_codigo_activo (codigo_activo), KEY ix_areas_eliminado (eliminado)
) ENGINE=InnoDB;

CREATE TABLE carreras (
  id_carrera SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  codigo VARCHAR(40) NOT NULL, nombre VARCHAR(120) NOT NULL,
  eliminado TINYINT(1) NOT NULL DEFAULT 0, eliminado_en DATETIME NULL, eliminado_por BIGINT UNSIGNED NULL,
  codigo_activo VARCHAR(40) GENERATED ALWAYS AS (IF(eliminado=0,codigo,NULL)) STORED,
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  creado_por BIGINT UNSIGNED NULL, actualizado_por BIGINT UNSIGNED NULL,
  UNIQUE KEY uq_carreras_codigo_activo (codigo_activo), KEY ix_carreras_eliminado (eliminado)
) ENGINE=InnoDB;

CREATE TABLE modalidades (
  id_modalidad SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  codigo VARCHAR(32) NOT NULL, nombre VARCHAR(80) NOT NULL,
  eliminado TINYINT(1) NOT NULL DEFAULT 0, eliminado_en DATETIME NULL, eliminado_por BIGINT UNSIGNED NULL,
  codigo_activo VARCHAR(32) GENERATED ALWAYS AS (IF(eliminado=0,codigo,NULL)) STORED,
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  creado_por BIGINT UNSIGNED NULL, actualizado_por BIGINT UNSIGNED NULL,
  UNIQUE KEY uq_modalidades_codigo_activo (codigo_activo), KEY ix_modalidades_eliminado (eliminado)
) ENGINE=InnoDB;

CREATE TABLE transiciones_estado (
  id_transicion BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  entidad VARCHAR(32) NOT NULL,
  id_estado_origen SMALLINT UNSIGNED NOT NULL,
  id_estado_destino SMALLINT UNSIGNED NOT NULL,
  id_rol_permitido SMALLINT UNSIGNED NOT NULL,
  requiere_comentario TINYINT(1) NOT NULL DEFAULT 0,
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  creado_por BIGINT UNSIGNED NULL, actualizado_por BIGINT UNSIGNED NULL,
  eliminado TINYINT(1) NOT NULL DEFAULT 0, eliminado_en DATETIME NULL, eliminado_por BIGINT UNSIGNED NULL,
  transicion_activa VARCHAR(160) GENERATED ALWAYS AS
    (IF(eliminado=0,CONCAT(entidad,':',id_estado_origen,':',id_estado_destino,':',id_rol_permitido),NULL)) STORED,
  CONSTRAINT fk_transicion_origen FOREIGN KEY (id_estado_origen) REFERENCES estados(id_estado),
  CONSTRAINT fk_transicion_destino FOREIGN KEY (id_estado_destino) REFERENCES estados(id_estado),
  CONSTRAINT fk_transicion_rol FOREIGN KEY (id_rol_permitido) REFERENCES roles(id_rol),
  UNIQUE KEY uq_transicion_activa (transicion_activa),
  KEY ix_transicion_busqueda (entidad,id_estado_origen,id_rol_permitido,eliminado)
) ENGINE=InnoDB;

CREATE TABLE estudiantes (
  id_estudiante BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  id_usuario BIGINT UNSIGNED NOT NULL,
  id_carrera SMALLINT UNSIGNED NOT NULL,
  carnet VARCHAR(40) NOT NULL,
  eliminado TINYINT(1) NOT NULL DEFAULT 0, eliminado_en DATETIME NULL, eliminado_por BIGINT UNSIGNED NULL,
  carnet_activo VARCHAR(40) GENERATED ALWAYS AS (IF(eliminado=0,LOWER(carnet),NULL)) STORED,
  semestre VARCHAR(40) NULL, universidad VARCHAR(180) NOT NULL,
  cv_nombre VARCHAR(255) NULL, cv_ruta VARCHAR(500) NULL, cv_mime VARCHAR(100) NULL,
  cv_eliminado TINYINT(1) NOT NULL DEFAULT 0,
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  creado_por BIGINT UNSIGNED NULL, actualizado_por BIGINT UNSIGNED NULL,
  id_usuario_activo BIGINT UNSIGNED GENERATED ALWAYS AS (IF(eliminado=0,id_usuario,NULL)) STORED,
  CONSTRAINT fk_estudiante_usuario FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario),
  CONSTRAINT fk_estudiante_carrera FOREIGN KEY (id_carrera) REFERENCES carreras(id_carrera),
  UNIQUE KEY uq_estudiante_usuario_activo (id_usuario_activo),
  UNIQUE KEY uq_estudiante_carnet_activo (carnet_activo), KEY ix_estudiantes_eliminado (eliminado)
) ENGINE=InnoDB;

CREATE TABLE empresas (
  id_empresa BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  id_usuario BIGINT UNSIGNED NOT NULL,
  id_estado_revision SMALLINT UNSIGNED NOT NULL,
  nombre_legal VARCHAR(180) NOT NULL, nombre_comercial VARCHAR(180) NULL,
  identificacion_fiscal VARCHAR(50) NULL, sector VARCHAR(100) NULL,
  descripcion TEXT NULL, correo_contacto VARCHAR(254) NOT NULL,
  telefono VARCHAR(8) NULL, sitio_web VARCHAR(255) NULL, direccion VARCHAR(255) NULL,
  observaciones_coordinacion TEXT NULL, revisada_en DATETIME NULL,
  id_administrador_revision BIGINT UNSIGNED NULL,
  eliminado TINYINT(1) NOT NULL DEFAULT 0, eliminado_en DATETIME NULL, eliminado_por BIGINT UNSIGNED NULL,
  identificacion_fiscal_activa VARCHAR(50) GENERATED ALWAYS AS (IF(eliminado=0,UPPER(identificacion_fiscal),NULL)) STORED,
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  creado_por BIGINT UNSIGNED NULL, actualizado_por BIGINT UNSIGNED NULL,
  id_usuario_activo BIGINT UNSIGNED GENERATED ALWAYS AS (IF(eliminado=0,id_usuario,NULL)) STORED,
  CONSTRAINT fk_empresa_usuario FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario),
  CONSTRAINT fk_empresa_estado_revision FOREIGN KEY (id_estado_revision) REFERENCES estados(id_estado),
  CONSTRAINT fk_empresa_revisor FOREIGN KEY (id_administrador_revision) REFERENCES usuarios(id_usuario),
  CONSTRAINT chk_empresa_telefono CHECK (telefono IS NULL OR telefono REGEXP '^[0-9]{8}$'),
  UNIQUE KEY uq_empresa_usuario_activo (id_usuario_activo),
  UNIQUE KEY uq_empresa_fiscal_activo (identificacion_fiscal_activa), KEY ix_empresas_eliminado (eliminado)
) ENGINE=InnoDB;

CREATE TABLE practicas (
  id_practica BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  id_empresa BIGINT UNSIGNED NOT NULL,
  id_estado SMALLINT UNSIGNED NOT NULL,
  id_area SMALLINT UNSIGNED NOT NULL,
  id_modalidad SMALLINT UNSIGNED NOT NULL,
  titulo VARCHAR(200) NOT NULL, descripcion TEXT NOT NULL, requisitos TEXT NULL,
  ubicacion VARCHAR(200) NULL, fecha_inicio DATE NULL, cupo_maximo SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  fecha_limite_postulacion DATE NOT NULL, publicada_en DATETIME NULL,
  observaciones_coordinacion TEXT NULL,
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  creado_por BIGINT UNSIGNED NULL, actualizado_por BIGINT UNSIGNED NULL,
  eliminado TINYINT(1) NOT NULL DEFAULT 0, eliminado_en DATETIME NULL, eliminado_por BIGINT UNSIGNED NULL,
  CONSTRAINT fk_practica_empresa FOREIGN KEY (id_empresa) REFERENCES empresas(id_empresa),
  CONSTRAINT fk_practica_estado FOREIGN KEY (id_estado) REFERENCES estados(id_estado),
  CONSTRAINT fk_practica_area FOREIGN KEY (id_area) REFERENCES areas(id_area),
  CONSTRAINT fk_practica_modalidad FOREIGN KEY (id_modalidad) REFERENCES modalidades(id_modalidad),
  CONSTRAINT chk_practica_cupo CHECK (cupo_maximo > 0),
  KEY ix_practicas_busqueda (id_estado,fecha_limite_postulacion,eliminado), KEY ix_practicas_inicio (fecha_inicio,id_estado,eliminado),
  KEY ix_practicas_empresa (id_empresa,id_estado,eliminado), KEY ix_practicas_eliminado (eliminado)
) ENGINE=InnoDB;

CREATE TABLE postulaciones (
  id_postulacion BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  id_estudiante BIGINT UNSIGNED NOT NULL, id_practica BIGINT UNSIGNED NOT NULL,
  id_estado SMALLINT UNSIGNED NOT NULL,
  carta_presentacion TEXT NULL, comentario TEXT NULL,
  cv_ruta_snapshot VARCHAR(500) NOT NULL, cv_nombre_snapshot VARCHAR(255) NOT NULL, cv_mime_snapshot VARCHAR(100) NOT NULL,
  fecha_postulacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  eliminado TINYINT(1) NOT NULL DEFAULT 0, eliminado_en DATETIME NULL, eliminado_por BIGINT UNSIGNED NULL,
  activa_estudiante BIGINT UNSIGNED GENERATED ALWAYS AS (IF(eliminado=0,id_estudiante,NULL)) STORED,
  activa_practica BIGINT UNSIGNED GENERATED ALWAYS AS (IF(eliminado=0,id_practica,NULL)) STORED,
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  creado_por BIGINT UNSIGNED NULL, actualizado_por BIGINT UNSIGNED NULL,
  CONSTRAINT fk_postulacion_estudiante FOREIGN KEY (id_estudiante) REFERENCES estudiantes(id_estudiante),
  CONSTRAINT fk_postulacion_practica FOREIGN KEY (id_practica) REFERENCES practicas(id_practica),
  CONSTRAINT fk_postulacion_estado FOREIGN KEY (id_estado) REFERENCES estados(id_estado),
  UNIQUE KEY uq_postulacion_unica_activa (activa_estudiante,activa_practica),
  KEY ix_postulaciones_practica_estado (id_practica,id_estado,eliminado),
  KEY ix_postulaciones_estudiante (id_estudiante,id_estado,eliminado)
) ENGINE=InnoDB;

CREATE TABLE historial_estados (
  id_historial BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  entidad VARCHAR(32) NOT NULL, id_registro BIGINT UNSIGNED NOT NULL,
  id_estado_anterior SMALLINT UNSIGNED NULL, id_estado_nuevo SMALLINT UNSIGNED NOT NULL,
  id_usuario BIGINT UNSIGNED NULL, comentario TEXT NULL,
  fecha_hora DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  creado_por BIGINT UNSIGNED NULL, actualizado_por BIGINT UNSIGNED NULL,
  eliminado TINYINT(1) NOT NULL DEFAULT 0, eliminado_en DATETIME NULL, eliminado_por BIGINT UNSIGNED NULL,
  CONSTRAINT fk_hist_estado_anterior FOREIGN KEY (id_estado_anterior) REFERENCES estados(id_estado),
  CONSTRAINT fk_hist_estado_nuevo FOREIGN KEY (id_estado_nuevo) REFERENCES estados(id_estado),
  CONSTRAINT fk_hist_usuario FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario),
  KEY ix_historial_entidad (entidad,id_registro,fecha_hora), KEY ix_historial_eliminado (eliminado)
) ENGINE=InnoDB;

CREATE TABLE configuracion (
  id_configuracion INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  clave VARCHAR(100) NOT NULL, valor TEXT NOT NULL,
  tipo ENUM('texto','entero','decimal','booleano','json') NOT NULL,
  descripcion VARCHAR(255) NOT NULL, editable TINYINT(1) NOT NULL DEFAULT 1,
  eliminado TINYINT(1) NOT NULL DEFAULT 0, eliminado_en DATETIME NULL, eliminado_por BIGINT UNSIGNED NULL,
  clave_activa VARCHAR(100) GENERATED ALWAYS AS (IF(eliminado=0,clave,NULL)) STORED,
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  creado_por BIGINT UNSIGNED NULL, actualizado_por BIGINT UNSIGNED NULL,
  UNIQUE KEY uq_configuracion_clave_activa (clave_activa), KEY ix_configuracion_eliminado (eliminado)
) ENGINE=InnoDB;

CREATE TABLE mensajes (
  id_mensaje INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  codigo VARCHAR(100) NOT NULL, texto TEXT NOT NULL,
  tipo VARCHAR(24) NOT NULL DEFAULT 'informativo',
  eliminado TINYINT(1) NOT NULL DEFAULT 0, eliminado_en DATETIME NULL, eliminado_por BIGINT UNSIGNED NULL,
  codigo_activo VARCHAR(100) GENERATED ALWAYS AS (IF(eliminado=0,codigo,NULL)) STORED,
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  creado_por BIGINT UNSIGNED NULL, actualizado_por BIGINT UNSIGNED NULL,
  UNIQUE KEY uq_mensajes_codigo_activo (codigo_activo), KEY ix_mensajes_eliminado (eliminado)
) ENGINE=InnoDB;

CREATE TABLE intentos_login (
  id_intento BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  identificador VARCHAR(254) NOT NULL, ip_cliente VARCHAR(45) NOT NULL,
  exitoso TINYINT(1) NOT NULL DEFAULT 0, ocurrido_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  bloqueado_hasta DATETIME NULL,
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  creado_por BIGINT UNSIGNED NULL, actualizado_por BIGINT UNSIGNED NULL,
  eliminado TINYINT(1) NOT NULL DEFAULT 0, eliminado_en DATETIME NULL, eliminado_por BIGINT UNSIGNED NULL,
  KEY ix_intentos_login_usuario_fecha (identificador,ocurrido_en),
  KEY ix_intentos_login_ip_fecha (ip_cliente,ocurrido_en)
) ENGINE=InnoDB;

CREATE TABLE bitacora (
  id_bitacora BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tabla_afectada VARCHAR(64) NOT NULL, id_registro VARCHAR(80) NULL,
  accion VARCHAR(40) NOT NULL, valores_anteriores JSON NULL, valores_nuevos JSON NULL,
  usuario_id BIGINT UNSIGNED NULL, ip_cliente VARCHAR(45) NULL,
  fecha_hora DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_bitacora_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id_usuario),
  KEY ix_bitacora_fecha (fecha_hora), KEY ix_bitacora_tabla_accion (tabla_afectada,accion),
  KEY ix_bitacora_usuario_fecha (usuario_id,fecha_hora)
) ENGINE=InnoDB;

ALTER TABLE roles ADD CONSTRAINT fk_roles_creado_por FOREIGN KEY (creado_por) REFERENCES usuarios(id_usuario), ADD CONSTRAINT fk_roles_actualizado_por FOREIGN KEY (actualizado_por) REFERENCES usuarios(id_usuario), ADD CONSTRAINT fk_roles_eliminado_por FOREIGN KEY (eliminado_por) REFERENCES usuarios(id_usuario);
ALTER TABLE permisos ADD CONSTRAINT fk_permisos_creado_por FOREIGN KEY (creado_por) REFERENCES usuarios(id_usuario), ADD CONSTRAINT fk_permisos_actualizado_por FOREIGN KEY (actualizado_por) REFERENCES usuarios(id_usuario), ADD CONSTRAINT fk_permisos_eliminado_por FOREIGN KEY (eliminado_por) REFERENCES usuarios(id_usuario);
ALTER TABLE tipos_entidad ADD CONSTRAINT fk_tipos_entidad_creado_por FOREIGN KEY (creado_por) REFERENCES usuarios(id_usuario), ADD CONSTRAINT fk_tipos_entidad_actualizado_por FOREIGN KEY (actualizado_por) REFERENCES usuarios(id_usuario), ADD CONSTRAINT fk_tipos_entidad_eliminado_por FOREIGN KEY (eliminado_por) REFERENCES usuarios(id_usuario);
ALTER TABLE estados ADD CONSTRAINT fk_estados_creado_por FOREIGN KEY (creado_por) REFERENCES usuarios(id_usuario), ADD CONSTRAINT fk_estados_actualizado_por FOREIGN KEY (actualizado_por) REFERENCES usuarios(id_usuario), ADD CONSTRAINT fk_estados_eliminado_por FOREIGN KEY (eliminado_por) REFERENCES usuarios(id_usuario);
ALTER TABLE rol_permiso ADD CONSTRAINT fk_rol_permiso_creado_por FOREIGN KEY (creado_por) REFERENCES usuarios(id_usuario), ADD CONSTRAINT fk_rol_permiso_actualizado_por FOREIGN KEY (actualizado_por) REFERENCES usuarios(id_usuario), ADD CONSTRAINT fk_rol_permiso_eliminado_por FOREIGN KEY (eliminado_por) REFERENCES usuarios(id_usuario);
ALTER TABLE sesiones_usuario ADD CONSTRAINT fk_sesiones_creado_por FOREIGN KEY (creado_por) REFERENCES usuarios(id_usuario), ADD CONSTRAINT fk_sesiones_actualizado_por FOREIGN KEY (actualizado_por) REFERENCES usuarios(id_usuario), ADD CONSTRAINT fk_sesiones_eliminado_por FOREIGN KEY (eliminado_por) REFERENCES usuarios(id_usuario);
ALTER TABLE areas ADD CONSTRAINT fk_areas_creado_por FOREIGN KEY (creado_por) REFERENCES usuarios(id_usuario), ADD CONSTRAINT fk_areas_actualizado_por FOREIGN KEY (actualizado_por) REFERENCES usuarios(id_usuario), ADD CONSTRAINT fk_areas_eliminado_por FOREIGN KEY (eliminado_por) REFERENCES usuarios(id_usuario);
ALTER TABLE carreras ADD CONSTRAINT fk_carreras_creado_por FOREIGN KEY (creado_por) REFERENCES usuarios(id_usuario), ADD CONSTRAINT fk_carreras_actualizado_por FOREIGN KEY (actualizado_por) REFERENCES usuarios(id_usuario), ADD CONSTRAINT fk_carreras_eliminado_por FOREIGN KEY (eliminado_por) REFERENCES usuarios(id_usuario);
ALTER TABLE modalidades ADD CONSTRAINT fk_modalidades_creado_por FOREIGN KEY (creado_por) REFERENCES usuarios(id_usuario), ADD CONSTRAINT fk_modalidades_actualizado_por FOREIGN KEY (actualizado_por) REFERENCES usuarios(id_usuario), ADD CONSTRAINT fk_modalidades_eliminado_por FOREIGN KEY (eliminado_por) REFERENCES usuarios(id_usuario);
ALTER TABLE transiciones_estado ADD CONSTRAINT fk_transiciones_creado_por FOREIGN KEY (creado_por) REFERENCES usuarios(id_usuario), ADD CONSTRAINT fk_transiciones_actualizado_por FOREIGN KEY (actualizado_por) REFERENCES usuarios(id_usuario), ADD CONSTRAINT fk_transiciones_eliminado_por FOREIGN KEY (eliminado_por) REFERENCES usuarios(id_usuario);
ALTER TABLE estudiantes ADD CONSTRAINT fk_estudiantes_creado_por FOREIGN KEY (creado_por) REFERENCES usuarios(id_usuario), ADD CONSTRAINT fk_estudiantes_actualizado_por FOREIGN KEY (actualizado_por) REFERENCES usuarios(id_usuario), ADD CONSTRAINT fk_estudiantes_eliminado_por FOREIGN KEY (eliminado_por) REFERENCES usuarios(id_usuario);
ALTER TABLE empresas ADD CONSTRAINT fk_empresas_creado_por FOREIGN KEY (creado_por) REFERENCES usuarios(id_usuario), ADD CONSTRAINT fk_empresas_actualizado_por FOREIGN KEY (actualizado_por) REFERENCES usuarios(id_usuario), ADD CONSTRAINT fk_empresas_eliminado_por FOREIGN KEY (eliminado_por) REFERENCES usuarios(id_usuario);
ALTER TABLE practicas ADD CONSTRAINT fk_practicas_creado_por FOREIGN KEY (creado_por) REFERENCES usuarios(id_usuario), ADD CONSTRAINT fk_practicas_actualizado_por FOREIGN KEY (actualizado_por) REFERENCES usuarios(id_usuario), ADD CONSTRAINT fk_practicas_eliminado_por FOREIGN KEY (eliminado_por) REFERENCES usuarios(id_usuario);
ALTER TABLE postulaciones ADD CONSTRAINT fk_postulaciones_creado_por FOREIGN KEY (creado_por) REFERENCES usuarios(id_usuario), ADD CONSTRAINT fk_postulaciones_actualizado_por FOREIGN KEY (actualizado_por) REFERENCES usuarios(id_usuario), ADD CONSTRAINT fk_postulaciones_eliminado_por FOREIGN KEY (eliminado_por) REFERENCES usuarios(id_usuario);
ALTER TABLE historial_estados ADD CONSTRAINT fk_historial_creado_por FOREIGN KEY (creado_por) REFERENCES usuarios(id_usuario), ADD CONSTRAINT fk_historial_actualizado_por FOREIGN KEY (actualizado_por) REFERENCES usuarios(id_usuario), ADD CONSTRAINT fk_historial_eliminado_por FOREIGN KEY (eliminado_por) REFERENCES usuarios(id_usuario);
ALTER TABLE configuracion ADD CONSTRAINT fk_configuracion_creado_por FOREIGN KEY (creado_por) REFERENCES usuarios(id_usuario), ADD CONSTRAINT fk_configuracion_actualizado_por FOREIGN KEY (actualizado_por) REFERENCES usuarios(id_usuario), ADD CONSTRAINT fk_configuracion_eliminado_por FOREIGN KEY (eliminado_por) REFERENCES usuarios(id_usuario);
ALTER TABLE mensajes ADD CONSTRAINT fk_mensajes_creado_por FOREIGN KEY (creado_por) REFERENCES usuarios(id_usuario), ADD CONSTRAINT fk_mensajes_actualizado_por FOREIGN KEY (actualizado_por) REFERENCES usuarios(id_usuario), ADD CONSTRAINT fk_mensajes_eliminado_por FOREIGN KEY (eliminado_por) REFERENCES usuarios(id_usuario);
ALTER TABLE intentos_login ADD CONSTRAINT fk_intentos_creado_por FOREIGN KEY (creado_por) REFERENCES usuarios(id_usuario), ADD CONSTRAINT fk_intentos_actualizado_por FOREIGN KEY (actualizado_por) REFERENCES usuarios(id_usuario), ADD CONSTRAINT fk_intentos_eliminado_por FOREIGN KEY (eliminado_por) REFERENCES usuarios(id_usuario);
ALTER TABLE transiciones_estado ADD CONSTRAINT fk_transiciones_entidad FOREIGN KEY (entidad) REFERENCES tipos_entidad(codigo);
INSERT INTO roles(codigo,nombre,descripcion) VALUES
 ('superadmin','Superadministrador','Administración total del sistema'),
 ('admin','Administrador','Gestión académica y operativa'),
 ('empresa','Empresa','Publica prácticas y gestiona postulaciones'),
 ('estudiante','Estudiante','Consulta prácticas y gestiona postulaciones'),
 ('sistema','Proceso automático','Transiciones ejecutadas por tareas programadas');
INSERT INTO tipos_entidad(codigo,nombre) VALUES
 ('usuario','Cuenta de usuario'),('empresa','Perfil de empresa'),('practica','Práctica profesional'),('postulacion','Postulación');
INSERT INTO permisos(codigo,nombre,descripcion) VALUES
 ('usuarios.consultar','Consultar usuarios','Consultar cuentas autorizadas'),
 ('usuarios.gestionar','Gestionar usuarios','Crear, editar y eliminar lógicamente cuentas autorizadas'),
 ('usuarios.restaurar','Restaurar usuarios','Restaurar cuentas eliminadas lógicamente'),
 ('usuarios.password_reset','Restablecer contraseñas','Generar contraseñas temporales'),
 ('empresas.revisar','Revisar empresas','Aprobar o rechazar empresas'),
 ('practicas.consultar','Consultar prácticas','Consultar prácticas vigentes'),
 ('practicas.gestionar_propias','Gestionar prácticas propias','Crear y modificar prácticas propias'),
 ('practicas.revisar','Revisar prácticas','Aprobar, pedir cambios o rechazar prácticas'),
 ('practicas.cerrar','Cerrar prácticas','Cerrar prácticas autorizadas'),
 ('postulaciones.crear','Crear postulaciones','Postularse a prácticas vigentes'),
 ('postulaciones.consultar_propias','Consultar postulaciones propias','Ver postulaciones personales'),
 ('postulaciones.consultar','Consultar todas las postulaciones','Consulta de coordinación'),
 ('postulaciones.consultar_empresa','Consultar postulaciones de empresa','Ver postulantes de prácticas propias'),
 ('postulaciones.cambiar_estado','Cambiar estado de postulaciones','Aceptar o rechazar postulantes'),
 ('postulaciones.reactivar','Reactivar postulaciones','Reactivar una postulación retirada'),
 ('postulaciones.retirar_propia','Retirar postulación propia','Retirar una postulación propia'),
 ('perfiles.consultar_propio','Consultar perfil propio','Consultar los datos del perfil de la cuenta propia'),
 ('perfiles.gestionar_propio','Gestionar perfil propio','Actualizar los datos y archivos del perfil propio'),
 ('catalogos.gestionar','Gestionar catálogos','Mantener valores de referencia'),
 ('configuracion.gestionar','Gestionar configuración','Editar valores de configuración'),
 ('bitacora.consultar','Consultar bitácora','Consultar auditoría del sistema'),
 ('reportes.consultar','Consultar reportes','Consultar resúmenes del sistema');
INSERT INTO estados(tipo_entidad,codigo,nombre,descripcion,color,orden) VALUES
 ('usuario','activo','Activa','Cuenta habilitada','#16824b',1),('usuario','pendiente','Pendiente','Cuenta en espera de revisión','#a66a00',2),
 ('usuario','rechazado','Rechazada','Cuenta empresarial rechazada','#b42318',3),('usuario','inactivo','Inactiva','Cuenta desactivada','#64748b',4),
 ('practica','borrador','Borrador','Práctica en preparación','#64748b',1),('practica','pendiente_revision','Pendiente de revisión','Práctica enviada a coordinación','#a66a00',2),
 ('practica','cambios_solicitados','Cambios solicitados','Requiere correcciones','#b54708',3),('practica','rechazada','Rechazada','Práctica no aprobada','#b42318',4),
 ('practica','publicada','Publicada','Disponible para postulaciones','#16824b',5),('practica','cerrada','Cerrada','Ya no recibe postulaciones','#475467',6),('practica','cancelada','Cancelada','Práctica cancelada','#b42318',7),
 ('postulacion','pendiente','Pendiente','Postulación en evaluación','#a66a00',1),('postulacion','aceptada','Aceptada','Postulación aceptada','#16824b',2),
 ('postulacion','rechazada','Rechazada','Postulación no seleccionada','#b42318',3),('postulacion','retirada','Retirada','Postulación retirada por estudiante','#64748b',4),('postulacion','cancelada','Cancelada','Postulación cancelada','#475467',5),
 ('empresa','pendiente','Pendiente','Empresa en revisión','#a66a00',1),('empresa','aprobada','Aprobada','Empresa habilitada','#16824b',2),('empresa','rechazada','Rechazada','Empresa no aprobada','#b42318',3);
INSERT INTO areas(codigo,nombre,descripcion) VALUES
 ('tecnologia','Tecnología','Desarrollo, infraestructura y datos'),('administracion','Administración','Gestión y operaciones'),
 ('mercadeo','Mercadeo','Comunicación y mercadeo'),('finanzas','Finanzas','Contabilidad y finanzas'),('diseno','Diseño','Diseño gráfico y experiencia de usuario');
INSERT INTO carreras(codigo,nombre) VALUES
 ('sistemas','Ingeniería en Sistemas'),('administracion','Administración de Empresas'),('mercadotecnia','Mercadotecnia'),
 ('contaduria','Contaduría Pública y Auditoría'),('diseno_grafico','Diseño Gráfico');
INSERT INTO modalidades(codigo,nombre) VALUES
 ('presencial','Presencial'),('remota','Remota'),('hibrida','Híbrida');

INSERT INTO configuracion(clave,valor,tipo,descripcion) VALUES
 ('sesion_inactividad_minutos','30','entero','Minutos sin actividad antes de cerrar sesión'),
 ('login_intentos_maximos','5','entero','Intentos fallidos permitidos antes del bloqueo'),
 ('login_bloqueo_minutos','15','entero','Duración del bloqueo temporal'),
 ('cv_tamano_maximo_bytes','5242880','entero','Tamaño máximo permitido para el CV'),
 ('cv_tipos_mime','["application/pdf"]','json','Tipos MIME permitidos para CV'),
 ('password_longitud_minima','10','entero','Longitud mínima de contraseña'),
 ('comentario_rechazo_minimo','10','entero','Caracteres mínimos del motivo de rechazo'),
 ('paginacion_tamano','20','entero','Cantidad predeterminada de registros por página'),
 ('paginacion_maxima','100','entero','Cantidad máxima de registros por página'),
 ('cupo_maximo_permitido','5000','entero','Límite superior configurable de plazas por práctica'),
 ('nombre_sistema','Prácticas UMG','texto','Nombre mostrado en la interfaz'),
 ('practica_cierre_comentario','La práctica fue cerrada','texto','Comentario al rechazar pendientes durante el cierre'),
 ('practica_cierre_cupo_motivo','Cierre automático por cupo completo.','texto','Motivo guardado al cerrar una práctica por alcanzar el cupo'),
 ('practica_cierre_fecha_motivo','Cierre automático por fecha límite.','texto','Motivo guardado al cerrar una práctica por vencer el plazo');
INSERT INTO mensajes(codigo,texto,tipo) VALUES
 ('error_generico','No se pudo completar la solicitud. Inténtalo nuevamente.','error'),
 ('error_datos','Revisa los datos ingresados.','error'),
 ('error_sesion','Tu sesión venció. Inicia sesión nuevamente.','error'),
 ('error_csrf','El token de seguridad venció. Actualiza la página.','error'),
 ('error_bloqueo','Se alcanzó el límite de intentos. Inténtalo más tarde.','error'),
 ('error_credenciales','Usuario o contraseña incorrectos.','error'),
 ('error_cambio_password','Debes cambiar la contraseña temporal antes de continuar.','error'),
 ('error_sin_permiso','No tienes permiso para realizar esta acción.','error'),
 ('error_no_encontrado','No se encontró el registro solicitado.','error'),
 ('error_duplicado','Ya existe un registro con esos datos.','error'),
 ('error_practica_cerrada','La práctica ya está cerrada y no admite cambios.','error'),
 ('error_practica_no_vigente','La práctica ya no recibe postulaciones.','error'),
 ('error_cupo_lleno','La práctica ya alcanzó su cupo.','error'),
 ('error_postulacion_duplicada','Ya existe una postulación para esta práctica.','error'),
 ('error_cv_obligatorio','Adjunta un CV PDF válido.','error'),
 ('error_cv_tamano','El CV supera el tamaño máximo permitido.','error'),
 ('error_cv_vacio','El CV está vacío o no se puede leer.','error'),
 ('error_cv_formato','Adjunta un archivo PDF válido.','error'),
 ('error_cv_no_disponible','El CV no está disponible.','error'),
 ('error_postulacion_requisitos','Completa y valida correo, teléfono y CV antes de postularte.','error'),
 ('error_empresa_pendiente','La empresa debe estar aprobada para realizar esta acción.','error'),
 ('exito_guardado','Los cambios se guardaron correctamente.','exito'),
 ('exito_postulacion','Tu postulación se registró correctamente.','exito'),
 ('exito_cv_actualizado','CV actualizado correctamente.','exito'),
 ('exito_cv_eliminado','El CV se marcó como eliminado.','exito'),
 ('exito_cierre','La práctica se cerró correctamente.','exito');

INSERT INTO rol_permiso(id_rol,id_permiso)
SELECT r.id_rol,p.id_permiso FROM roles r JOIN permisos p ON
 (r.codigo='superadmin') OR
 (r.codigo='admin' AND p.codigo NOT IN ('usuarios.password_reset','practicas.revisar')) OR
 (r.codigo='empresa' AND p.codigo IN ('practicas.consultar','practicas.gestionar_propias','practicas.cerrar','postulaciones.consultar_empresa','postulaciones.cambiar_estado','perfiles.consultar_propio','perfiles.gestionar_propio')) OR
 (r.codigo='estudiante' AND p.codigo IN ('practicas.consultar','postulaciones.crear','postulaciones.consultar_propias','postulaciones.reactivar','postulaciones.retirar_propia','perfiles.consultar_propio','perfiles.gestionar_propio'));

INSERT INTO transiciones_estado(entidad,id_estado_origen,id_estado_destino,id_rol_permitido,requiere_comentario)
SELECT 'practica',o.id_estado,d.id_estado,r.id_rol,req.requiere_comentario
FROM (SELECT 'borrador' origen,'pendiente_revision' destino,'empresa' rol,0 requiere_comentario UNION ALL
 SELECT 'pendiente_revision','publicada','superadmin',0 UNION ALL SELECT 'pendiente_revision','cambios_solicitados','superadmin',1 UNION ALL
 SELECT 'pendiente_revision','rechazada','superadmin',1 UNION ALL SELECT 'cambios_solicitados','pendiente_revision','empresa',0 UNION ALL
 SELECT 'rechazada','pendiente_revision','empresa',0 UNION ALL SELECT 'publicada','pendiente_revision','empresa',0 UNION ALL
 SELECT 'pendiente_revision','pendiente_revision','empresa',0 UNION ALL
 SELECT 'publicada','cerrada','empresa',0 UNION ALL SELECT 'publicada','cerrada','admin',0 UNION ALL
 SELECT 'publicada','cerrada','superadmin',0 UNION ALL SELECT 'publicada','cancelada','admin',1 UNION ALL
 SELECT 'publicada','cancelada','superadmin',1 UNION ALL SELECT 'publicada','cerrada','sistema',0 UNION ALL
 SELECT 'cerrada','publicada','sistema',0 UNION ALL
 SELECT 'pendiente_revision','cancelada','admin',1 UNION ALL SELECT 'pendiente_revision','cancelada','superadmin',1 UNION ALL
 SELECT 'cambios_solicitados','cancelada','admin',1 UNION ALL SELECT 'cambios_solicitados','cancelada','superadmin',1 UNION ALL
 SELECT 'rechazada','cancelada','admin',1 UNION ALL SELECT 'rechazada','cancelada','superadmin',1 UNION ALL
 SELECT 'cerrada','cancelada','admin',1 UNION ALL SELECT 'cerrada','cancelada','superadmin',1 UNION ALL
 SELECT 'borrador','cancelada','admin',1 UNION ALL SELECT 'borrador','cancelada','superadmin',1) req
JOIN estados o ON o.tipo_entidad='practica' AND o.codigo=req.origen
JOIN estados d ON d.tipo_entidad='practica' AND d.codigo=req.destino
JOIN roles r ON r.codigo=req.rol;
INSERT INTO transiciones_estado(entidad,id_estado_origen,id_estado_destino,id_rol_permitido,requiere_comentario)
SELECT 'postulacion',o.id_estado,d.id_estado,r.id_rol,req.requiere_comentario
FROM (SELECT 'pendiente' origen,'aceptada' destino,'empresa' rol,0 requiere_comentario UNION ALL
 SELECT 'pendiente','rechazada','empresa',1 UNION ALL SELECT 'pendiente','retirada','estudiante',0 UNION ALL
 SELECT 'aceptada','rechazada','empresa',1 UNION ALL SELECT 'aceptada','retirada','estudiante',0 UNION ALL
 SELECT 'retirada','pendiente','estudiante',0 UNION ALL SELECT 'pendiente','cancelada','admin',1 UNION ALL
 SELECT 'pendiente','cancelada','superadmin',1 UNION ALL SELECT 'aceptada','cancelada','admin',1 UNION ALL
 SELECT 'aceptada','cancelada','superadmin',1 UNION ALL SELECT 'pendiente','rechazada','sistema',1) req
JOIN estados o ON o.tipo_entidad='postulacion' AND o.codigo=req.origen
JOIN estados d ON d.tipo_entidad='postulacion' AND d.codigo=req.destino
JOIN roles r ON r.codigo=req.rol;
INSERT INTO transiciones_estado(entidad,id_estado_origen,id_estado_destino,id_rol_permitido,requiere_comentario)
SELECT 'empresa',o.id_estado,d.id_estado,r.id_rol,req.requiere_comentario
FROM (SELECT 'pendiente' origen,'aprobada' destino,'superadmin' rol,0 requiere_comentario UNION ALL
 SELECT 'pendiente','rechazada','superadmin',1 UNION ALL SELECT 'pendiente','aprobada','admin',0 UNION ALL
 SELECT 'pendiente','rechazada','admin',1 UNION ALL SELECT 'rechazada','pendiente','empresa',0) req
JOIN estados o ON o.tipo_entidad='empresa' AND o.codigo=req.origen
JOIN estados d ON d.tipo_entidad='empresa' AND d.codigo=req.destino
JOIN roles r ON r.codigo=req.rol;
INSERT INTO transiciones_estado(entidad,id_estado_origen,id_estado_destino,id_rol_permitido,requiere_comentario)
SELECT 'usuario',o.id_estado,d.id_estado,r.id_rol,req.requiere_comentario
FROM (SELECT 'pendiente' origen,'activo' destino,'superadmin' rol,0 requiere_comentario UNION ALL
 SELECT 'pendiente','rechazado','superadmin',1 UNION ALL
 SELECT 'activo','inactivo','admin',1 UNION ALL SELECT 'activo','inactivo','superadmin',1 UNION ALL
 SELECT 'inactivo','activo','admin',0 UNION ALL SELECT 'inactivo','activo','superadmin',0) req
JOIN estados o ON o.tipo_entidad='usuario' AND o.codigo=req.origen
JOIN estados d ON d.tipo_entidad='usuario' AND d.codigo=req.destino
JOIN roles r ON r.codigo=req.rol;



