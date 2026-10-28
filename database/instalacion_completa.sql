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



USE sistema_practicas;
DELIMITER $$
DROP TRIGGER IF EXISTS trg_roles_ai$$
CREATE TRIGGER trg_roles_ai AFTER INSERT ON roles FOR EACH ROW
BEGIN
  INSERT INTO bitacora(tabla_afectada,id_registro,accion,valores_nuevos,usuario_id,ip_cliente)
  VALUES('roles',CAST(NEW.id_rol AS CHAR),'INSERT',JSON_OBJECT('codigo',NEW.codigo,'nombre',NEW.nombre,'descripcion',NEW.descripcion,'eliminado',NEW.eliminado),@usuario_id,@ip_cliente);
END$$
DROP TRIGGER IF EXISTS trg_roles_au$$
CREATE TRIGGER trg_roles_au AFTER UPDATE ON roles FOR EACH ROW
BEGIN
  INSERT INTO bitacora(tabla_afectada,id_registro,accion,valores_anteriores,valores_nuevos,usuario_id,ip_cliente)
  VALUES('roles',CAST(NEW.id_rol AS CHAR),CASE WHEN OLD.eliminado=0 AND NEW.eliminado=1 THEN 'ELIMINACION_LOGICA' WHEN OLD.eliminado=1 AND NEW.eliminado=0 THEN 'RESTAURACION' ELSE 'UPDATE' END,JSON_OBJECT('codigo',OLD.codigo,'nombre',OLD.nombre,'descripcion',OLD.descripcion,'eliminado',OLD.eliminado),JSON_OBJECT('codigo',NEW.codigo,'nombre',NEW.nombre,'descripcion',NEW.descripcion,'eliminado',NEW.eliminado),@usuario_id,@ip_cliente);
END$$
DROP TRIGGER IF EXISTS trg_roles_bd$$
CREATE TRIGGER trg_roles_bd BEFORE DELETE ON roles FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='El borrado físico está prohibido; marca el registro como eliminado';
END$$
DROP TRIGGER IF EXISTS trg_permisos_ai$$
CREATE TRIGGER trg_permisos_ai AFTER INSERT ON permisos FOR EACH ROW
BEGIN
  INSERT INTO bitacora(tabla_afectada,id_registro,accion,valores_nuevos,usuario_id,ip_cliente)
  VALUES('permisos',CAST(NEW.id_permiso AS CHAR),'INSERT',JSON_OBJECT('codigo',NEW.codigo,'nombre',NEW.nombre,'descripcion',NEW.descripcion,'eliminado',NEW.eliminado),@usuario_id,@ip_cliente);
END$$
DROP TRIGGER IF EXISTS trg_permisos_au$$
CREATE TRIGGER trg_permisos_au AFTER UPDATE ON permisos FOR EACH ROW
BEGIN
  INSERT INTO bitacora(tabla_afectada,id_registro,accion,valores_anteriores,valores_nuevos,usuario_id,ip_cliente)
  VALUES('permisos',CAST(NEW.id_permiso AS CHAR),CASE WHEN OLD.eliminado=0 AND NEW.eliminado=1 THEN 'ELIMINACION_LOGICA' WHEN OLD.eliminado=1 AND NEW.eliminado=0 THEN 'RESTAURACION' ELSE 'UPDATE' END,JSON_OBJECT('codigo',OLD.codigo,'nombre',OLD.nombre,'descripcion',OLD.descripcion,'eliminado',OLD.eliminado),JSON_OBJECT('codigo',NEW.codigo,'nombre',NEW.nombre,'descripcion',NEW.descripcion,'eliminado',NEW.eliminado),@usuario_id,@ip_cliente);
END$$
DROP TRIGGER IF EXISTS trg_permisos_bd$$
CREATE TRIGGER trg_permisos_bd BEFORE DELETE ON permisos FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='El borrado físico está prohibido; marca el registro como eliminado';
END$$
DROP TRIGGER IF EXISTS trg_tipos_entidad_ai$$
CREATE TRIGGER trg_tipos_entidad_ai AFTER INSERT ON tipos_entidad FOR EACH ROW
BEGIN
  INSERT INTO bitacora(tabla_afectada,id_registro,accion,valores_nuevos,usuario_id,ip_cliente)
  VALUES('tipos_entidad',CAST(NEW.id_tipo_entidad AS CHAR),'INSERT',JSON_OBJECT('codigo',NEW.codigo,'nombre',NEW.nombre,'eliminado',NEW.eliminado),@usuario_id,@ip_cliente);
END$$
DROP TRIGGER IF EXISTS trg_tipos_entidad_au$$
CREATE TRIGGER trg_tipos_entidad_au AFTER UPDATE ON tipos_entidad FOR EACH ROW
BEGIN
  INSERT INTO bitacora(tabla_afectada,id_registro,accion,valores_anteriores,valores_nuevos,usuario_id,ip_cliente)
  VALUES('tipos_entidad',CAST(NEW.id_tipo_entidad AS CHAR),CASE WHEN OLD.eliminado=0 AND NEW.eliminado=1 THEN 'ELIMINACION_LOGICA' WHEN OLD.eliminado=1 AND NEW.eliminado=0 THEN 'RESTAURACION' ELSE 'UPDATE' END,JSON_OBJECT('codigo',OLD.codigo,'nombre',OLD.nombre,'eliminado',OLD.eliminado),JSON_OBJECT('codigo',NEW.codigo,'nombre',NEW.nombre,'eliminado',NEW.eliminado),@usuario_id,@ip_cliente);
END$$
DROP TRIGGER IF EXISTS trg_tipos_entidad_bd$$
CREATE TRIGGER trg_tipos_entidad_bd BEFORE DELETE ON tipos_entidad FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='El borrado físico está prohibido; marca el registro como eliminado';
END$$
DROP TRIGGER IF EXISTS trg_estados_ai$$
CREATE TRIGGER trg_estados_ai AFTER INSERT ON estados FOR EACH ROW
BEGIN
  INSERT INTO bitacora(tabla_afectada,id_registro,accion,valores_nuevos,usuario_id,ip_cliente)
  VALUES('estados',CAST(NEW.id_estado AS CHAR),'INSERT',JSON_OBJECT('tipo_entidad',NEW.tipo_entidad,'codigo',NEW.codigo,'nombre',NEW.nombre,'descripcion',NEW.descripcion,'color',NEW.color,'orden',NEW.orden,'eliminado',NEW.eliminado),@usuario_id,@ip_cliente);
END$$
DROP TRIGGER IF EXISTS trg_estados_au$$
CREATE TRIGGER trg_estados_au AFTER UPDATE ON estados FOR EACH ROW
BEGIN
  INSERT INTO bitacora(tabla_afectada,id_registro,accion,valores_anteriores,valores_nuevos,usuario_id,ip_cliente)
  VALUES('estados',CAST(NEW.id_estado AS CHAR),CASE WHEN OLD.eliminado=0 AND NEW.eliminado=1 THEN 'ELIMINACION_LOGICA' WHEN OLD.eliminado=1 AND NEW.eliminado=0 THEN 'RESTAURACION' ELSE 'UPDATE' END,JSON_OBJECT('tipo_entidad',OLD.tipo_entidad,'codigo',OLD.codigo,'nombre',OLD.nombre,'descripcion',OLD.descripcion,'color',OLD.color,'orden',OLD.orden,'eliminado',OLD.eliminado),JSON_OBJECT('tipo_entidad',NEW.tipo_entidad,'codigo',NEW.codigo,'nombre',NEW.nombre,'descripcion',NEW.descripcion,'color',NEW.color,'orden',NEW.orden,'eliminado',NEW.eliminado),@usuario_id,@ip_cliente);
END$$
DROP TRIGGER IF EXISTS trg_estados_bd$$
CREATE TRIGGER trg_estados_bd BEFORE DELETE ON estados FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='El borrado físico está prohibido; marca el registro como eliminado';
END$$
DROP TRIGGER IF EXISTS trg_usuarios_ai$$
CREATE TRIGGER trg_usuarios_ai AFTER INSERT ON usuarios FOR EACH ROW
BEGIN
  INSERT INTO bitacora(tabla_afectada,id_registro,accion,valores_nuevos,usuario_id,ip_cliente)
  VALUES('usuarios',CAST(NEW.id_usuario AS CHAR),'INSERT',JSON_OBJECT('id_rol',NEW.id_rol,'id_estado',NEW.id_estado,'usuario',NEW.usuario,'correo',NEW.correo,'nombres',NEW.nombres,'apellidos',NEW.apellidos,'telefono',NEW.telefono,'debe_cambiar_password',NEW.debe_cambiar_password,'ultimo_acceso',NEW.ultimo_acceso,'eliminado',NEW.eliminado),@usuario_id,@ip_cliente);
END$$
DROP TRIGGER IF EXISTS trg_usuarios_au$$
CREATE TRIGGER trg_usuarios_au AFTER UPDATE ON usuarios FOR EACH ROW
BEGIN
  INSERT INTO bitacora(tabla_afectada,id_registro,accion,valores_anteriores,valores_nuevos,usuario_id,ip_cliente)
  VALUES('usuarios',CAST(NEW.id_usuario AS CHAR),CASE WHEN OLD.eliminado=0 AND NEW.eliminado=1 THEN 'ELIMINACION_LOGICA' WHEN OLD.eliminado=1 AND NEW.eliminado=0 THEN 'RESTAURACION' ELSE 'UPDATE' END,JSON_OBJECT('id_rol',OLD.id_rol,'id_estado',OLD.id_estado,'usuario',OLD.usuario,'correo',OLD.correo,'nombres',OLD.nombres,'apellidos',OLD.apellidos,'telefono',OLD.telefono,'debe_cambiar_password',OLD.debe_cambiar_password,'ultimo_acceso',OLD.ultimo_acceso,'eliminado',OLD.eliminado),JSON_OBJECT('id_rol',NEW.id_rol,'id_estado',NEW.id_estado,'usuario',NEW.usuario,'correo',NEW.correo,'nombres',NEW.nombres,'apellidos',NEW.apellidos,'telefono',NEW.telefono,'debe_cambiar_password',NEW.debe_cambiar_password,'ultimo_acceso',NEW.ultimo_acceso,'eliminado',NEW.eliminado),@usuario_id,@ip_cliente);
END$$
DROP TRIGGER IF EXISTS trg_usuarios_bd$$
CREATE TRIGGER trg_usuarios_bd BEFORE DELETE ON usuarios FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='El borrado físico está prohibido; marca el registro como eliminado';
END$$
DROP TRIGGER IF EXISTS trg_sesiones_usuario_ai$$
CREATE TRIGGER trg_sesiones_usuario_ai AFTER INSERT ON sesiones_usuario FOR EACH ROW
BEGIN
  INSERT INTO bitacora(tabla_afectada,id_registro,accion,valores_nuevos,usuario_id,ip_cliente)
  VALUES('sesiones_usuario',CAST(NEW.id_sesion AS CHAR),'INSERT',JSON_OBJECT('id_usuario',NEW.id_usuario,'ip_origen',NEW.ip_origen,'creada_en',NEW.creada_en,'expira_en',NEW.expira_en,'ultima_actividad',NEW.ultima_actividad,'revocada_en',NEW.revocada_en,'eliminado',NEW.eliminado),@usuario_id,@ip_cliente);
END$$
DROP TRIGGER IF EXISTS trg_sesiones_usuario_au$$
CREATE TRIGGER trg_sesiones_usuario_au AFTER UPDATE ON sesiones_usuario FOR EACH ROW
BEGIN
  INSERT INTO bitacora(tabla_afectada,id_registro,accion,valores_anteriores,valores_nuevos,usuario_id,ip_cliente)
  VALUES('sesiones_usuario',CAST(NEW.id_sesion AS CHAR),CASE WHEN OLD.eliminado=0 AND NEW.eliminado=1 THEN 'ELIMINACION_LOGICA' WHEN OLD.eliminado=1 AND NEW.eliminado=0 THEN 'RESTAURACION' ELSE 'UPDATE' END,JSON_OBJECT('id_usuario',OLD.id_usuario,'ip_origen',OLD.ip_origen,'creada_en',OLD.creada_en,'expira_en',OLD.expira_en,'ultima_actividad',OLD.ultima_actividad,'revocada_en',OLD.revocada_en,'eliminado',OLD.eliminado),JSON_OBJECT('id_usuario',NEW.id_usuario,'ip_origen',NEW.ip_origen,'creada_en',NEW.creada_en,'expira_en',NEW.expira_en,'ultima_actividad',NEW.ultima_actividad,'revocada_en',NEW.revocada_en,'eliminado',NEW.eliminado),@usuario_id,@ip_cliente);
END$$
DROP TRIGGER IF EXISTS trg_sesiones_usuario_bd$$
CREATE TRIGGER trg_sesiones_usuario_bd BEFORE DELETE ON sesiones_usuario FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='El borrado físico está prohibido; marca el registro como eliminado';
END$$
DROP TRIGGER IF EXISTS trg_rol_permiso_ai$$
CREATE TRIGGER trg_rol_permiso_ai AFTER INSERT ON rol_permiso FOR EACH ROW
BEGIN
  INSERT INTO bitacora(tabla_afectada,id_registro,accion,valores_nuevos,usuario_id,ip_cliente)
  VALUES('rol_permiso',CONCAT(NEW.id_rol,':',NEW.id_permiso),'INSERT',JSON_OBJECT('id_rol',NEW.id_rol,'id_permiso',NEW.id_permiso,'eliminado',NEW.eliminado),@usuario_id,@ip_cliente);
END$$
DROP TRIGGER IF EXISTS trg_rol_permiso_au$$
CREATE TRIGGER trg_rol_permiso_au AFTER UPDATE ON rol_permiso FOR EACH ROW
BEGIN
  INSERT INTO bitacora(tabla_afectada,id_registro,accion,valores_anteriores,valores_nuevos,usuario_id,ip_cliente)
  VALUES('rol_permiso',CONCAT(NEW.id_rol,':',NEW.id_permiso),CASE WHEN OLD.eliminado=0 AND NEW.eliminado=1 THEN 'ELIMINACION_LOGICA' WHEN OLD.eliminado=1 AND NEW.eliminado=0 THEN 'RESTAURACION' ELSE 'UPDATE' END,JSON_OBJECT('id_rol',OLD.id_rol,'id_permiso',OLD.id_permiso,'eliminado',OLD.eliminado),JSON_OBJECT('id_rol',NEW.id_rol,'id_permiso',NEW.id_permiso,'eliminado',NEW.eliminado),@usuario_id,@ip_cliente);
END$$
DROP TRIGGER IF EXISTS trg_rol_permiso_bd$$
CREATE TRIGGER trg_rol_permiso_bd BEFORE DELETE ON rol_permiso FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='El borrado físico está prohibido; marca el registro como eliminado';
END$$
DROP TRIGGER IF EXISTS trg_areas_ai$$
CREATE TRIGGER trg_areas_ai AFTER INSERT ON areas FOR EACH ROW
BEGIN
  INSERT INTO bitacora(tabla_afectada,id_registro,accion,valores_nuevos,usuario_id,ip_cliente)
  VALUES('areas',CAST(NEW.id_area AS CHAR),'INSERT',JSON_OBJECT('codigo',NEW.codigo,'nombre',NEW.nombre,'descripcion',NEW.descripcion,'eliminado',NEW.eliminado),@usuario_id,@ip_cliente);
END$$
DROP TRIGGER IF EXISTS trg_areas_au$$
CREATE TRIGGER trg_areas_au AFTER UPDATE ON areas FOR EACH ROW
BEGIN
  INSERT INTO bitacora(tabla_afectada,id_registro,accion,valores_anteriores,valores_nuevos,usuario_id,ip_cliente)
  VALUES('areas',CAST(NEW.id_area AS CHAR),CASE WHEN OLD.eliminado=0 AND NEW.eliminado=1 THEN 'ELIMINACION_LOGICA' WHEN OLD.eliminado=1 AND NEW.eliminado=0 THEN 'RESTAURACION' ELSE 'UPDATE' END,JSON_OBJECT('codigo',OLD.codigo,'nombre',OLD.nombre,'descripcion',OLD.descripcion,'eliminado',OLD.eliminado),JSON_OBJECT('codigo',NEW.codigo,'nombre',NEW.nombre,'descripcion',NEW.descripcion,'eliminado',NEW.eliminado),@usuario_id,@ip_cliente);
END$$
DROP TRIGGER IF EXISTS trg_areas_bd$$
CREATE TRIGGER trg_areas_bd BEFORE DELETE ON areas FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='El borrado físico está prohibido; marca el registro como eliminado';
END$$
DROP TRIGGER IF EXISTS trg_carreras_ai$$
CREATE TRIGGER trg_carreras_ai AFTER INSERT ON carreras FOR EACH ROW
BEGIN
  INSERT INTO bitacora(tabla_afectada,id_registro,accion,valores_nuevos,usuario_id,ip_cliente)
  VALUES('carreras',CAST(NEW.id_carrera AS CHAR),'INSERT',JSON_OBJECT('codigo',NEW.codigo,'nombre',NEW.nombre,'eliminado',NEW.eliminado),@usuario_id,@ip_cliente);
END$$
DROP TRIGGER IF EXISTS trg_carreras_au$$
CREATE TRIGGER trg_carreras_au AFTER UPDATE ON carreras FOR EACH ROW
BEGIN
  INSERT INTO bitacora(tabla_afectada,id_registro,accion,valores_anteriores,valores_nuevos,usuario_id,ip_cliente)
  VALUES('carreras',CAST(NEW.id_carrera AS CHAR),CASE WHEN OLD.eliminado=0 AND NEW.eliminado=1 THEN 'ELIMINACION_LOGICA' WHEN OLD.eliminado=1 AND NEW.eliminado=0 THEN 'RESTAURACION' ELSE 'UPDATE' END,JSON_OBJECT('codigo',OLD.codigo,'nombre',OLD.nombre,'eliminado',OLD.eliminado),JSON_OBJECT('codigo',NEW.codigo,'nombre',NEW.nombre,'eliminado',NEW.eliminado),@usuario_id,@ip_cliente);
END$$
DROP TRIGGER IF EXISTS trg_carreras_bd$$
CREATE TRIGGER trg_carreras_bd BEFORE DELETE ON carreras FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='El borrado físico está prohibido; marca el registro como eliminado';
END$$
DROP TRIGGER IF EXISTS trg_modalidades_ai$$
CREATE TRIGGER trg_modalidades_ai AFTER INSERT ON modalidades FOR EACH ROW
BEGIN
  INSERT INTO bitacora(tabla_afectada,id_registro,accion,valores_nuevos,usuario_id,ip_cliente)
  VALUES('modalidades',CAST(NEW.id_modalidad AS CHAR),'INSERT',JSON_OBJECT('codigo',NEW.codigo,'nombre',NEW.nombre,'eliminado',NEW.eliminado),@usuario_id,@ip_cliente);
END$$
DROP TRIGGER IF EXISTS trg_modalidades_au$$
CREATE TRIGGER trg_modalidades_au AFTER UPDATE ON modalidades FOR EACH ROW
BEGIN
  INSERT INTO bitacora(tabla_afectada,id_registro,accion,valores_anteriores,valores_nuevos,usuario_id,ip_cliente)
  VALUES('modalidades',CAST(NEW.id_modalidad AS CHAR),CASE WHEN OLD.eliminado=0 AND NEW.eliminado=1 THEN 'ELIMINACION_LOGICA' WHEN OLD.eliminado=1 AND NEW.eliminado=0 THEN 'RESTAURACION' ELSE 'UPDATE' END,JSON_OBJECT('codigo',OLD.codigo,'nombre',OLD.nombre,'eliminado',OLD.eliminado),JSON_OBJECT('codigo',NEW.codigo,'nombre',NEW.nombre,'eliminado',NEW.eliminado),@usuario_id,@ip_cliente);
END$$
DROP TRIGGER IF EXISTS trg_modalidades_bd$$
CREATE TRIGGER trg_modalidades_bd BEFORE DELETE ON modalidades FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='El borrado físico está prohibido; marca el registro como eliminado';
END$$
DROP TRIGGER IF EXISTS trg_transiciones_estado_ai$$
CREATE TRIGGER trg_transiciones_estado_ai AFTER INSERT ON transiciones_estado FOR EACH ROW
BEGIN
  INSERT INTO bitacora(tabla_afectada,id_registro,accion,valores_nuevos,usuario_id,ip_cliente)
  VALUES('transiciones_estado',CAST(NEW.id_transicion AS CHAR),'INSERT',JSON_OBJECT('entidad',NEW.entidad,'id_estado_origen',NEW.id_estado_origen,'id_estado_destino',NEW.id_estado_destino,'id_rol_permitido',NEW.id_rol_permitido,'requiere_comentario',NEW.requiere_comentario,'eliminado',NEW.eliminado),@usuario_id,@ip_cliente);
END$$
DROP TRIGGER IF EXISTS trg_transiciones_estado_au$$
CREATE TRIGGER trg_transiciones_estado_au AFTER UPDATE ON transiciones_estado FOR EACH ROW
BEGIN
  INSERT INTO bitacora(tabla_afectada,id_registro,accion,valores_anteriores,valores_nuevos,usuario_id,ip_cliente)
  VALUES('transiciones_estado',CAST(NEW.id_transicion AS CHAR),CASE WHEN OLD.eliminado=0 AND NEW.eliminado=1 THEN 'ELIMINACION_LOGICA' WHEN OLD.eliminado=1 AND NEW.eliminado=0 THEN 'RESTAURACION' ELSE 'UPDATE' END,JSON_OBJECT('entidad',OLD.entidad,'id_estado_origen',OLD.id_estado_origen,'id_estado_destino',OLD.id_estado_destino,'id_rol_permitido',OLD.id_rol_permitido,'requiere_comentario',OLD.requiere_comentario,'eliminado',OLD.eliminado),JSON_OBJECT('entidad',NEW.entidad,'id_estado_origen',NEW.id_estado_origen,'id_estado_destino',NEW.id_estado_destino,'id_rol_permitido',NEW.id_rol_permitido,'requiere_comentario',NEW.requiere_comentario,'eliminado',NEW.eliminado),@usuario_id,@ip_cliente);
END$$
DROP TRIGGER IF EXISTS trg_transiciones_estado_bd$$
CREATE TRIGGER trg_transiciones_estado_bd BEFORE DELETE ON transiciones_estado FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='El borrado físico está prohibido; marca el registro como eliminado';
END$$
DROP TRIGGER IF EXISTS trg_estudiantes_ai$$
CREATE TRIGGER trg_estudiantes_ai AFTER INSERT ON estudiantes FOR EACH ROW
BEGIN
  INSERT INTO bitacora(tabla_afectada,id_registro,accion,valores_nuevos,usuario_id,ip_cliente)
  VALUES('estudiantes',CAST(NEW.id_estudiante AS CHAR),'INSERT',JSON_OBJECT('id_usuario',NEW.id_usuario,'id_carrera',NEW.id_carrera,'carnet',NEW.carnet,'semestre',NEW.semestre,'universidad',NEW.universidad,'cv_nombre',NEW.cv_nombre,'cv_mime',NEW.cv_mime,'cv_eliminado',NEW.cv_eliminado,'eliminado',NEW.eliminado),@usuario_id,@ip_cliente);
END$$
DROP TRIGGER IF EXISTS trg_estudiantes_au$$
CREATE TRIGGER trg_estudiantes_au AFTER UPDATE ON estudiantes FOR EACH ROW
BEGIN
  INSERT INTO bitacora(tabla_afectada,id_registro,accion,valores_anteriores,valores_nuevos,usuario_id,ip_cliente)
  VALUES('estudiantes',CAST(NEW.id_estudiante AS CHAR),CASE WHEN OLD.eliminado=0 AND NEW.eliminado=1 THEN 'ELIMINACION_LOGICA' WHEN OLD.eliminado=1 AND NEW.eliminado=0 THEN 'RESTAURACION' ELSE 'UPDATE' END,JSON_OBJECT('id_usuario',OLD.id_usuario,'id_carrera',OLD.id_carrera,'carnet',OLD.carnet,'semestre',OLD.semestre,'universidad',OLD.universidad,'cv_nombre',OLD.cv_nombre,'cv_mime',OLD.cv_mime,'cv_eliminado',OLD.cv_eliminado,'eliminado',OLD.eliminado),JSON_OBJECT('id_usuario',NEW.id_usuario,'id_carrera',NEW.id_carrera,'carnet',NEW.carnet,'semestre',NEW.semestre,'universidad',NEW.universidad,'cv_nombre',NEW.cv_nombre,'cv_mime',NEW.cv_mime,'cv_eliminado',NEW.cv_eliminado,'eliminado',NEW.eliminado),@usuario_id,@ip_cliente);
END$$
DROP TRIGGER IF EXISTS trg_estudiantes_bd$$
CREATE TRIGGER trg_estudiantes_bd BEFORE DELETE ON estudiantes FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='El borrado físico está prohibido; marca el registro como eliminado';
END$$
DROP TRIGGER IF EXISTS trg_empresas_ai$$
CREATE TRIGGER trg_empresas_ai AFTER INSERT ON empresas FOR EACH ROW
BEGIN
  INSERT INTO bitacora(tabla_afectada,id_registro,accion,valores_nuevos,usuario_id,ip_cliente)
  VALUES('empresas',CAST(NEW.id_empresa AS CHAR),'INSERT',JSON_OBJECT('id_usuario',NEW.id_usuario,'id_estado_revision',NEW.id_estado_revision,'nombre_legal',NEW.nombre_legal,'nombre_comercial',NEW.nombre_comercial,'identificacion_fiscal',NEW.identificacion_fiscal,'sector',NEW.sector,'correo_contacto',NEW.correo_contacto,'telefono',NEW.telefono,'sitio_web',NEW.sitio_web,'direccion',NEW.direccion,'observaciones_coordinacion',NEW.observaciones_coordinacion,'revisada_en',NEW.revisada_en,'id_administrador_revision',NEW.id_administrador_revision,'eliminado',NEW.eliminado),@usuario_id,@ip_cliente);
END$$
DROP TRIGGER IF EXISTS trg_empresas_au$$
CREATE TRIGGER trg_empresas_au AFTER UPDATE ON empresas FOR EACH ROW
BEGIN
  INSERT INTO bitacora(tabla_afectada,id_registro,accion,valores_anteriores,valores_nuevos,usuario_id,ip_cliente)
  VALUES('empresas',CAST(NEW.id_empresa AS CHAR),CASE WHEN OLD.eliminado=0 AND NEW.eliminado=1 THEN 'ELIMINACION_LOGICA' WHEN OLD.eliminado=1 AND NEW.eliminado=0 THEN 'RESTAURACION' ELSE 'UPDATE' END,JSON_OBJECT('id_usuario',OLD.id_usuario,'id_estado_revision',OLD.id_estado_revision,'nombre_legal',OLD.nombre_legal,'nombre_comercial',OLD.nombre_comercial,'identificacion_fiscal',OLD.identificacion_fiscal,'sector',OLD.sector,'correo_contacto',OLD.correo_contacto,'telefono',OLD.telefono,'sitio_web',OLD.sitio_web,'direccion',OLD.direccion,'observaciones_coordinacion',OLD.observaciones_coordinacion,'revisada_en',OLD.revisada_en,'id_administrador_revision',OLD.id_administrador_revision,'eliminado',OLD.eliminado),JSON_OBJECT('id_usuario',NEW.id_usuario,'id_estado_revision',NEW.id_estado_revision,'nombre_legal',NEW.nombre_legal,'nombre_comercial',NEW.nombre_comercial,'identificacion_fiscal',NEW.identificacion_fiscal,'sector',NEW.sector,'correo_contacto',NEW.correo_contacto,'telefono',NEW.telefono,'sitio_web',NEW.sitio_web,'direccion',NEW.direccion,'observaciones_coordinacion',NEW.observaciones_coordinacion,'revisada_en',NEW.revisada_en,'id_administrador_revision',NEW.id_administrador_revision,'eliminado',NEW.eliminado),@usuario_id,@ip_cliente);
END$$
DROP TRIGGER IF EXISTS trg_empresas_bd$$
CREATE TRIGGER trg_empresas_bd BEFORE DELETE ON empresas FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='El borrado físico está prohibido; marca el registro como eliminado';
END$$
DROP TRIGGER IF EXISTS trg_practicas_ai$$
CREATE TRIGGER trg_practicas_ai AFTER INSERT ON practicas FOR EACH ROW
BEGIN
  INSERT INTO bitacora(tabla_afectada,id_registro,accion,valores_nuevos,usuario_id,ip_cliente)
  VALUES('practicas',CAST(NEW.id_practica AS CHAR),'INSERT',JSON_OBJECT('id_empresa',NEW.id_empresa,'id_estado',NEW.id_estado,'id_area',NEW.id_area,'id_modalidad',NEW.id_modalidad,'titulo',NEW.titulo,'descripcion',NEW.descripcion,'requisitos',NEW.requisitos,'ubicacion',NEW.ubicacion,'cupo_maximo',NEW.cupo_maximo,'fecha_limite_postulacion',NEW.fecha_limite_postulacion,'publicada_en',NEW.publicada_en,'observaciones_coordinacion',NEW.observaciones_coordinacion,'eliminado',NEW.eliminado),@usuario_id,@ip_cliente);
END$$
DROP TRIGGER IF EXISTS trg_practicas_au$$
CREATE TRIGGER trg_practicas_au AFTER UPDATE ON practicas FOR EACH ROW
BEGIN
  INSERT INTO bitacora(tabla_afectada,id_registro,accion,valores_anteriores,valores_nuevos,usuario_id,ip_cliente)
  VALUES('practicas',CAST(NEW.id_practica AS CHAR),CASE WHEN OLD.eliminado=0 AND NEW.eliminado=1 THEN 'ELIMINACION_LOGICA' WHEN OLD.eliminado=1 AND NEW.eliminado=0 THEN 'RESTAURACION' ELSE 'UPDATE' END,JSON_OBJECT('id_empresa',OLD.id_empresa,'id_estado',OLD.id_estado,'id_area',OLD.id_area,'id_modalidad',OLD.id_modalidad,'titulo',OLD.titulo,'descripcion',OLD.descripcion,'requisitos',OLD.requisitos,'ubicacion',OLD.ubicacion,'cupo_maximo',OLD.cupo_maximo,'fecha_limite_postulacion',OLD.fecha_limite_postulacion,'publicada_en',OLD.publicada_en,'observaciones_coordinacion',OLD.observaciones_coordinacion,'eliminado',OLD.eliminado),JSON_OBJECT('id_empresa',NEW.id_empresa,'id_estado',NEW.id_estado,'id_area',NEW.id_area,'id_modalidad',NEW.id_modalidad,'titulo',NEW.titulo,'descripcion',NEW.descripcion,'requisitos',NEW.requisitos,'ubicacion',NEW.ubicacion,'cupo_maximo',NEW.cupo_maximo,'fecha_limite_postulacion',NEW.fecha_limite_postulacion,'publicada_en',NEW.publicada_en,'observaciones_coordinacion',NEW.observaciones_coordinacion,'eliminado',NEW.eliminado),@usuario_id,@ip_cliente);
END$$
DROP TRIGGER IF EXISTS trg_practicas_bd$$
CREATE TRIGGER trg_practicas_bd BEFORE DELETE ON practicas FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='El borrado físico está prohibido; marca el registro como eliminado';
END$$
DROP TRIGGER IF EXISTS trg_postulaciones_ai$$
CREATE TRIGGER trg_postulaciones_ai AFTER INSERT ON postulaciones FOR EACH ROW
BEGIN
  INSERT INTO bitacora(tabla_afectada,id_registro,accion,valores_nuevos,usuario_id,ip_cliente)
  VALUES('postulaciones',CAST(NEW.id_postulacion AS CHAR),'INSERT',JSON_OBJECT('id_estudiante',NEW.id_estudiante,'id_practica',NEW.id_practica,'id_estado',NEW.id_estado,'carta_presentacion',NEW.carta_presentacion,'comentario',NEW.comentario,'fecha_postulacion',NEW.fecha_postulacion,'eliminado',NEW.eliminado),@usuario_id,@ip_cliente);
END$$
DROP TRIGGER IF EXISTS trg_postulaciones_au$$
CREATE TRIGGER trg_postulaciones_au AFTER UPDATE ON postulaciones FOR EACH ROW
BEGIN
  INSERT INTO bitacora(tabla_afectada,id_registro,accion,valores_anteriores,valores_nuevos,usuario_id,ip_cliente)
  VALUES('postulaciones',CAST(NEW.id_postulacion AS CHAR),CASE WHEN OLD.eliminado=0 AND NEW.eliminado=1 THEN 'ELIMINACION_LOGICA' WHEN OLD.eliminado=1 AND NEW.eliminado=0 THEN 'RESTAURACION' ELSE 'UPDATE' END,JSON_OBJECT('id_estudiante',OLD.id_estudiante,'id_practica',OLD.id_practica,'id_estado',OLD.id_estado,'carta_presentacion',OLD.carta_presentacion,'comentario',OLD.comentario,'fecha_postulacion',OLD.fecha_postulacion,'eliminado',OLD.eliminado),JSON_OBJECT('id_estudiante',NEW.id_estudiante,'id_practica',NEW.id_practica,'id_estado',NEW.id_estado,'carta_presentacion',NEW.carta_presentacion,'comentario',NEW.comentario,'fecha_postulacion',NEW.fecha_postulacion,'eliminado',NEW.eliminado),@usuario_id,@ip_cliente);
END$$
DROP TRIGGER IF EXISTS trg_postulaciones_bd$$
CREATE TRIGGER trg_postulaciones_bd BEFORE DELETE ON postulaciones FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='El borrado físico está prohibido; marca el registro como eliminado';
END$$
DROP TRIGGER IF EXISTS trg_historial_estados_ai$$
CREATE TRIGGER trg_historial_estados_ai AFTER INSERT ON historial_estados FOR EACH ROW
BEGIN
  INSERT INTO bitacora(tabla_afectada,id_registro,accion,valores_nuevos,usuario_id,ip_cliente)
  VALUES('historial_estados',CAST(NEW.id_historial AS CHAR),'INSERT',JSON_OBJECT('entidad',NEW.entidad,'id_registro',NEW.id_registro,'id_estado_anterior',NEW.id_estado_anterior,'id_estado_nuevo',NEW.id_estado_nuevo,'id_usuario',NEW.id_usuario,'comentario',NEW.comentario,'fecha_hora',NEW.fecha_hora,'eliminado',NEW.eliminado),@usuario_id,@ip_cliente);
END$$
DROP TRIGGER IF EXISTS trg_historial_estados_au$$
CREATE TRIGGER trg_historial_estados_au AFTER UPDATE ON historial_estados FOR EACH ROW
BEGIN
  INSERT INTO bitacora(tabla_afectada,id_registro,accion,valores_anteriores,valores_nuevos,usuario_id,ip_cliente)
  VALUES('historial_estados',CAST(NEW.id_historial AS CHAR),CASE WHEN OLD.eliminado=0 AND NEW.eliminado=1 THEN 'ELIMINACION_LOGICA' WHEN OLD.eliminado=1 AND NEW.eliminado=0 THEN 'RESTAURACION' ELSE 'UPDATE' END,JSON_OBJECT('entidad',OLD.entidad,'id_registro',OLD.id_registro,'id_estado_anterior',OLD.id_estado_anterior,'id_estado_nuevo',OLD.id_estado_nuevo,'id_usuario',OLD.id_usuario,'comentario',OLD.comentario,'fecha_hora',OLD.fecha_hora,'eliminado',OLD.eliminado),JSON_OBJECT('entidad',NEW.entidad,'id_registro',NEW.id_registro,'id_estado_anterior',NEW.id_estado_anterior,'id_estado_nuevo',NEW.id_estado_nuevo,'id_usuario',NEW.id_usuario,'comentario',NEW.comentario,'fecha_hora',NEW.fecha_hora,'eliminado',NEW.eliminado),@usuario_id,@ip_cliente);
END$$
DROP TRIGGER IF EXISTS trg_historial_estados_bd$$
CREATE TRIGGER trg_historial_estados_bd BEFORE DELETE ON historial_estados FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='El borrado físico está prohibido; marca el registro como eliminado';
END$$
DROP TRIGGER IF EXISTS trg_configuracion_ai$$
CREATE TRIGGER trg_configuracion_ai AFTER INSERT ON configuracion FOR EACH ROW
BEGIN
  INSERT INTO bitacora(tabla_afectada,id_registro,accion,valores_nuevos,usuario_id,ip_cliente)
  VALUES('configuracion',CAST(NEW.id_configuracion AS CHAR),'INSERT',JSON_OBJECT('clave',NEW.clave,'valor',NEW.valor,'tipo',NEW.tipo,'descripcion',NEW.descripcion,'editable',NEW.editable,'eliminado',NEW.eliminado),@usuario_id,@ip_cliente);
END$$
DROP TRIGGER IF EXISTS trg_configuracion_au$$
CREATE TRIGGER trg_configuracion_au AFTER UPDATE ON configuracion FOR EACH ROW
BEGIN
  INSERT INTO bitacora(tabla_afectada,id_registro,accion,valores_anteriores,valores_nuevos,usuario_id,ip_cliente)
  VALUES('configuracion',CAST(NEW.id_configuracion AS CHAR),CASE WHEN OLD.eliminado=0 AND NEW.eliminado=1 THEN 'ELIMINACION_LOGICA' WHEN OLD.eliminado=1 AND NEW.eliminado=0 THEN 'RESTAURACION' ELSE 'UPDATE' END,JSON_OBJECT('clave',OLD.clave,'valor',OLD.valor,'tipo',OLD.tipo,'descripcion',OLD.descripcion,'editable',OLD.editable,'eliminado',OLD.eliminado),JSON_OBJECT('clave',NEW.clave,'valor',NEW.valor,'tipo',NEW.tipo,'descripcion',NEW.descripcion,'editable',NEW.editable,'eliminado',NEW.eliminado),@usuario_id,@ip_cliente);
END$$
DROP TRIGGER IF EXISTS trg_configuracion_bd$$
CREATE TRIGGER trg_configuracion_bd BEFORE DELETE ON configuracion FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='El borrado físico está prohibido; marca el registro como eliminado';
END$$
DROP TRIGGER IF EXISTS trg_mensajes_ai$$
CREATE TRIGGER trg_mensajes_ai AFTER INSERT ON mensajes FOR EACH ROW
BEGIN
  INSERT INTO bitacora(tabla_afectada,id_registro,accion,valores_nuevos,usuario_id,ip_cliente)
  VALUES('mensajes',CAST(NEW.id_mensaje AS CHAR),'INSERT',JSON_OBJECT('codigo',NEW.codigo,'texto',NEW.texto,'tipo',NEW.tipo,'eliminado',NEW.eliminado),@usuario_id,@ip_cliente);
END$$
DROP TRIGGER IF EXISTS trg_mensajes_au$$
CREATE TRIGGER trg_mensajes_au AFTER UPDATE ON mensajes FOR EACH ROW
BEGIN
  INSERT INTO bitacora(tabla_afectada,id_registro,accion,valores_anteriores,valores_nuevos,usuario_id,ip_cliente)
  VALUES('mensajes',CAST(NEW.id_mensaje AS CHAR),CASE WHEN OLD.eliminado=0 AND NEW.eliminado=1 THEN 'ELIMINACION_LOGICA' WHEN OLD.eliminado=1 AND NEW.eliminado=0 THEN 'RESTAURACION' ELSE 'UPDATE' END,JSON_OBJECT('codigo',OLD.codigo,'texto',OLD.texto,'tipo',OLD.tipo,'eliminado',OLD.eliminado),JSON_OBJECT('codigo',NEW.codigo,'texto',NEW.texto,'tipo',NEW.tipo,'eliminado',NEW.eliminado),@usuario_id,@ip_cliente);
END$$
DROP TRIGGER IF EXISTS trg_mensajes_bd$$
CREATE TRIGGER trg_mensajes_bd BEFORE DELETE ON mensajes FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='El borrado físico está prohibido; marca el registro como eliminado';
END$$
DROP TRIGGER IF EXISTS trg_intentos_login_ai$$
CREATE TRIGGER trg_intentos_login_ai AFTER INSERT ON intentos_login FOR EACH ROW
BEGIN
  INSERT INTO bitacora(tabla_afectada,id_registro,accion,valores_nuevos,usuario_id,ip_cliente)
  VALUES('intentos_login',CAST(NEW.id_intento AS CHAR),'INSERT',JSON_OBJECT('identificador',NEW.identificador,'ip_cliente',NEW.ip_cliente,'exitoso',NEW.exitoso,'ocurrido_en',NEW.ocurrido_en,'bloqueado_hasta',NEW.bloqueado_hasta,'eliminado',NEW.eliminado),@usuario_id,@ip_cliente);
END$$
DROP TRIGGER IF EXISTS trg_intentos_login_au$$
CREATE TRIGGER trg_intentos_login_au AFTER UPDATE ON intentos_login FOR EACH ROW
BEGIN
  INSERT INTO bitacora(tabla_afectada,id_registro,accion,valores_anteriores,valores_nuevos,usuario_id,ip_cliente)
  VALUES('intentos_login',CAST(NEW.id_intento AS CHAR),CASE WHEN OLD.eliminado=0 AND NEW.eliminado=1 THEN 'ELIMINACION_LOGICA' WHEN OLD.eliminado=1 AND NEW.eliminado=0 THEN 'RESTAURACION' ELSE 'UPDATE' END,JSON_OBJECT('identificador',OLD.identificador,'ip_cliente',OLD.ip_cliente,'exitoso',OLD.exitoso,'ocurrido_en',OLD.ocurrido_en,'bloqueado_hasta',OLD.bloqueado_hasta,'eliminado',OLD.eliminado),JSON_OBJECT('identificador',NEW.identificador,'ip_cliente',NEW.ip_cliente,'exitoso',NEW.exitoso,'ocurrido_en',NEW.ocurrido_en,'bloqueado_hasta',NEW.bloqueado_hasta,'eliminado',NEW.eliminado),@usuario_id,@ip_cliente);
END$$
DROP TRIGGER IF EXISTS trg_intentos_login_bd$$
CREATE TRIGGER trg_intentos_login_bd BEFORE DELETE ON intentos_login FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='El borrado físico está prohibido; marca el registro como eliminado';
END$$
DROP TRIGGER IF EXISTS trg_bitacora_no_update$$
CREATE TRIGGER trg_bitacora_no_update BEFORE UPDATE ON bitacora FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='La bitácora es de solo inserción';
END$$
DROP TRIGGER IF EXISTS trg_bitacora_no_delete$$
CREATE TRIGGER trg_bitacora_no_delete BEFORE DELETE ON bitacora FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='La bitácora no permite borrado físico';
END$$
DELIMITER ;



USE sistema_practicas;

CREATE OR REPLACE SQL SECURITY DEFINER VIEW vw_catalogos AS
SELECT 'rol' AS tipo,r.id_rol AS id,r.codigo,r.nombre,r.descripcion,NULL AS color,NULL AS orden
FROM roles r WHERE r.eliminado=0
UNION ALL SELECT 'permiso',p.id_permiso,p.codigo,p.nombre,p.descripcion,NULL,NULL FROM permisos p WHERE p.eliminado=0
UNION ALL SELECT e.tipo_entidad,e.id_estado,e.codigo,e.nombre,e.descripcion,e.color,e.orden
FROM estados e JOIN tipos_entidad t ON t.codigo=e.tipo_entidad AND t.eliminado=0 WHERE e.eliminado=0
UNION ALL SELECT 'area',a.id_area,a.codigo,a.nombre,a.descripcion,NULL,NULL FROM areas a WHERE a.eliminado=0
UNION ALL SELECT 'carrera',c.id_carrera,c.codigo,c.nombre,NULL,NULL,NULL FROM carreras c WHERE c.eliminado=0
UNION ALL SELECT 'modalidad',m.id_modalidad,m.codigo,m.nombre,NULL,NULL,NULL FROM modalidades m WHERE m.eliminado=0
UNION ALL SELECT 'tipo_entidad',t.id_tipo_entidad,t.codigo,t.nombre,NULL,NULL,NULL FROM tipos_entidad t WHERE t.eliminado=0;

CREATE OR REPLACE SQL SECURITY DEFINER VIEW vw_usuario_acceso AS
SELECT u.id_usuario,u.usuario,u.correo,u.nombres,u.apellidos,u.telefono,
       u.id_rol,u.id_estado,u.debe_cambiar_password,u.ultimo_acceso,
       r.codigo AS rol_codigo,r.nombre AS rol_nombre,
       e.codigo AS estado_codigo,e.nombre AS estado_nombre
FROM usuarios u JOIN roles r ON r.id_rol=u.id_rol AND r.eliminado=0
JOIN estados e ON e.id_estado=u.id_estado AND e.tipo_entidad='usuario' AND e.eliminado=0
WHERE u.eliminado=0;

CREATE OR REPLACE SQL SECURITY DEFINER VIEW vw_usuario_permisos AS
SELECT u.id_usuario,r.codigo AS rol_codigo,p.codigo AS permiso_codigo
FROM usuarios u JOIN roles r ON r.id_rol=u.id_rol AND r.eliminado=0
JOIN rol_permiso rp ON rp.id_rol=r.id_rol AND rp.eliminado=0
JOIN permisos p ON p.id_permiso=rp.id_permiso AND p.eliminado=0
JOIN estados eu ON eu.id_estado=u.id_estado AND eu.tipo_entidad='usuario' AND eu.codigo='activo' AND eu.eliminado=0
WHERE u.eliminado=0;

CREATE OR REPLACE SQL SECURITY DEFINER VIEW vw_estudiantes AS
SELECT e.id_estudiante,e.id_usuario,e.id_carrera,c.codigo AS carrera_codigo,c.nombre AS carrera,
       e.carnet,e.semestre,e.universidad,e.cv_nombre,e.cv_eliminado,
       u.nombres,u.apellidos,u.correo,u.telefono
FROM estudiantes e JOIN usuarios u ON u.id_usuario=e.id_usuario AND u.eliminado=0
JOIN estados eu ON eu.id_estado=u.id_estado AND eu.tipo_entidad='usuario' AND eu.codigo='activo' AND eu.eliminado=0
JOIN carreras c ON c.id_carrera=e.id_carrera AND c.eliminado=0 WHERE e.eliminado=0;

CREATE OR REPLACE SQL SECURITY DEFINER VIEW vw_empresas AS
SELECT e.id_empresa,e.id_usuario,e.id_estado_revision,ee.codigo AS estado_codigo,ee.nombre AS estado,ee.color AS estado_color,
       e.nombre_legal,e.nombre_comercial,e.identificacion_fiscal,e.sector,e.descripcion,
       e.correo_contacto,e.telefono,e.sitio_web,e.direccion,e.observaciones_coordinacion,
       e.revisada_en,e.id_administrador_revision,u.nombres,u.apellidos,u.correo AS correo_usuario
FROM empresas e JOIN usuarios u ON u.id_usuario=e.id_usuario AND u.eliminado=0
JOIN estados ee ON ee.id_estado=e.id_estado_revision AND ee.tipo_entidad='empresa' AND ee.eliminado=0
WHERE e.eliminado=0;

CREATE OR REPLACE SQL SECURITY DEFINER VIEW vw_usuarios_admin AS
SELECT u.id_usuario,u.usuario,u.correo,u.nombres,u.apellidos,u.telefono,
       u.debe_cambiar_password,u.ultimo_acceso,u.id_rol,r.codigo AS rol_codigo,r.nombre AS rol,
       u.id_estado,es.codigo AS estado_codigo,es.nombre AS estado,es.color AS estado_color,
       u.creado_en,u.actualizado_en,u.eliminado
FROM usuarios u JOIN roles r ON r.id_rol=u.id_rol AND r.eliminado=0
JOIN estados es ON es.id_estado=u.id_estado AND es.tipo_entidad='usuario' AND es.eliminado=0
WHERE u.eliminado=0;

CREATE OR REPLACE SQL SECURITY DEFINER VIEW vw_empresas_revision AS
SELECT e.*,u.usuario
FROM vw_empresas e JOIN usuarios u ON u.id_usuario=e.id_usuario
WHERE e.estado_codigo IN ('pendiente','rechazada');

CREATE OR REPLACE SQL SECURITY DEFINER VIEW vw_practicas_publicadas AS
SELECT p.id_practica,p.titulo,p.descripcion,p.requisitos,p.ubicacion,p.fecha_inicio,p.cupo_maximo,
       p.fecha_limite_postulacion,p.publicada_en,p.id_empresa,
       COALESCE(e.nombre_comercial,e.nombre_legal) AS empresa,
       a.id_area,a.codigo AS area_codigo,a.nombre AS area,
       m.id_modalidad,m.codigo AS modalidad_codigo,m.nombre AS modalidad,
       es.codigo AS estado_codigo,es.nombre AS estado,es.color AS estado_color,
       (SELECT COUNT(*) FROM postulaciones po JOIN estados ep ON ep.id_estado=po.id_estado AND ep.tipo_entidad='postulacion' AND ep.eliminado=0
        WHERE po.id_practica=p.id_practica AND po.eliminado=0 AND ep.codigo='aceptada') AS cupos_ocupados
FROM practicas p JOIN estados es ON es.id_estado=p.id_estado AND es.tipo_entidad='practica' AND es.eliminado=0
JOIN empresas e ON e.id_empresa=p.id_empresa AND e.eliminado=0
JOIN estados ee ON ee.id_estado=e.id_estado_revision AND ee.tipo_entidad='empresa' AND ee.codigo='aprobada' AND ee.eliminado=0
JOIN areas a ON a.id_area=p.id_area AND a.eliminado=0
JOIN modalidades m ON m.id_modalidad=p.id_modalidad AND m.eliminado=0
WHERE p.eliminado=0 AND es.codigo='publicada' AND p.fecha_limite_postulacion>=CURRENT_DATE;

CREATE OR REPLACE SQL SECURITY DEFINER VIEW vw_practicas_empresa AS
SELECT p.*,es.codigo AS estado_codigo,es.nombre AS estado,es.color AS estado_color,
       a.codigo AS area_codigo,a.nombre AS area,m.codigo AS modalidad_codigo,m.nombre AS modalidad,
       e.id_usuario AS id_usuario_empresa,
       (SELECT COUNT(*) FROM postulaciones po WHERE po.id_practica=p.id_practica AND po.eliminado=0) AS total_postulaciones,
       (SELECT COUNT(*) FROM postulaciones po JOIN estados ep ON ep.id_estado=po.id_estado AND ep.tipo_entidad='postulacion' AND ep.eliminado=0
        WHERE po.id_practica=p.id_practica AND po.eliminado=0 AND ep.codigo='aceptada') AS cupos_ocupados,
       (SELECT COUNT(*) FROM postulaciones po JOIN estados ep ON ep.id_estado=po.id_estado AND ep.tipo_entidad='postulacion' AND ep.eliminado=0
        WHERE po.id_practica=p.id_practica AND po.eliminado=0 AND ep.codigo='pendiente') AS pendientes
FROM practicas p JOIN empresas e ON e.id_empresa=p.id_empresa AND e.eliminado=0
JOIN estados es ON es.id_estado=p.id_estado AND es.tipo_entidad='practica' AND es.eliminado=0
JOIN areas a ON a.id_area=p.id_area AND a.eliminado=0 JOIN modalidades m ON m.id_modalidad=p.id_modalidad AND m.eliminado=0
WHERE p.eliminado=0;

CREATE OR REPLACE SQL SECURITY DEFINER VIEW vw_practicas_admin AS
SELECT p.id_practica,p.id_empresa,COALESCE(e.nombre_comercial,e.nombre_legal) AS empresa,
       p.id_estado,es.codigo AS estado_codigo,es.nombre AS estado,
       p.id_area,a.nombre AS area,p.id_modalidad,m.nombre AS modalidad,
       p.titulo,p.descripcion,p.requisitos,p.ubicacion,p.cupo_maximo,
       p.fecha_inicio,p.fecha_limite_postulacion,p.publicada_en,p.observaciones_coordinacion,
       p.creado_en,p.actualizado_en,
       (SELECT COUNT(*) FROM postulaciones po WHERE po.id_practica=p.id_practica AND po.eliminado=0) AS total_postulaciones,
       (SELECT COUNT(*) FROM postulaciones po JOIN estados ep ON ep.id_estado=po.id_estado AND ep.tipo_entidad='postulacion' AND ep.codigo='aceptada' AND ep.eliminado=0
        WHERE po.id_practica=p.id_practica AND po.eliminado=0) AS aceptadas
FROM practicas p JOIN empresas e ON e.id_empresa=p.id_empresa AND e.eliminado=0
JOIN estados es ON es.id_estado=p.id_estado AND es.tipo_entidad='practica' AND es.eliminado=0
JOIN areas a ON a.id_area=p.id_area AND a.eliminado=0
JOIN modalidades m ON m.id_modalidad=p.id_modalidad AND m.eliminado=0
WHERE p.eliminado=0;

CREATE OR REPLACE SQL SECURITY DEFINER VIEW vw_postulaciones_estudiante AS
SELECT po.id_postulacion,po.id_estudiante,st.id_usuario,po.id_practica,po.id_estado,
       po.carta_presentacion,po.comentario,po.fecha_postulacion,
       es.codigo AS estado_codigo,es.nombre AS estado,es.color AS estado_color,
       p.titulo AS practica,COALESCE(em.nombre_comercial,em.nombre_legal) AS empresa
FROM postulaciones po JOIN estudiantes st ON st.id_estudiante=po.id_estudiante AND st.eliminado=0
JOIN estados es ON es.id_estado=po.id_estado AND es.tipo_entidad='postulacion' AND es.eliminado=0
JOIN practicas p ON p.id_practica=po.id_practica AND p.eliminado=0
JOIN empresas em ON em.id_empresa=p.id_empresa AND em.eliminado=0 WHERE po.eliminado=0;

CREATE OR REPLACE SQL SECURITY DEFINER VIEW vw_postulaciones_empresa AS
SELECT po.id_postulacion,po.id_estudiante,po.id_practica,po.id_estado,
       po.carta_presentacion,po.comentario,po.fecha_postulacion,
       es.codigo AS estado_codigo,es.nombre AS estado,es.color AS estado_color,
       em.id_usuario AS id_usuario_empresa,p.titulo AS practica,
       st.carnet,st.semestre,st.universidad,po.cv_nombre_snapshot AS cv_nombre,
       st.cv_eliminado,c.nombre AS carrera,u.id_usuario AS id_usuario_estudiante,u.nombres,u.apellidos,u.correo,u.telefono
FROM postulaciones po JOIN estados es ON es.id_estado=po.id_estado AND es.tipo_entidad='postulacion' AND es.eliminado=0
JOIN estudiantes st ON st.id_estudiante=po.id_estudiante AND st.eliminado=0
JOIN usuarios u ON u.id_usuario=st.id_usuario AND u.eliminado=0
JOIN estados eu ON eu.id_estado=u.id_estado AND eu.tipo_entidad='usuario' AND eu.codigo='activo' AND eu.eliminado=0
JOIN carreras c ON c.id_carrera=st.id_carrera AND c.eliminado=0
JOIN practicas p ON p.id_practica=po.id_practica AND p.eliminado=0
JOIN empresas em ON em.id_empresa=p.id_empresa AND em.eliminado=0 WHERE po.eliminado=0;

CREATE OR REPLACE SQL SECURITY DEFINER VIEW vw_postulaciones_admin AS
SELECT po.id_postulacion,po.id_estudiante,po.id_practica,po.id_estado,
       po.carta_presentacion,po.comentario,po.fecha_postulacion,
       es.codigo AS estado_codigo,es.nombre AS estado,es.color AS estado_color,
       p.titulo AS practica,COALESCE(em.nombre_comercial,em.nombre_legal) AS empresa,
       em.id_empresa,em.id_usuario AS id_usuario_empresa,
       st.carnet,st.semestre,st.universidad,po.cv_nombre_snapshot AS cv_nombre,st.cv_eliminado,
       c.nombre AS carrera,u.id_usuario AS id_usuario_estudiante,u.nombres,u.apellidos,u.correo,u.telefono
FROM postulaciones po JOIN estados es ON es.id_estado=po.id_estado AND es.tipo_entidad='postulacion' AND es.eliminado=0
JOIN estudiantes st ON st.id_estudiante=po.id_estudiante
JOIN usuarios u ON u.id_usuario=st.id_usuario
JOIN carreras c ON c.id_carrera=st.id_carrera
JOIN practicas p ON p.id_practica=po.id_practica
JOIN empresas em ON em.id_empresa=p.id_empresa WHERE po.eliminado=0;

CREATE OR REPLACE SQL SECURITY DEFINER VIEW vw_historial_postulaciones AS
SELECT h.id_historial,h.entidad,h.id_registro AS id_postulacion,
       h.id_estado_anterior,ea.nombre AS estado_anterior,h.id_estado_nuevo,en.nombre AS estado_nuevo,
       h.id_usuario,CONCAT(u.nombres,' ',u.apellidos) AS actor,h.comentario,h.fecha_hora,
       po.id_estudiante,st.id_usuario AS propietario
FROM historial_estados h JOIN estados en ON en.id_estado=h.id_estado_nuevo AND en.eliminado=0
LEFT JOIN estados ea ON ea.id_estado=h.id_estado_anterior AND ea.eliminado=0
LEFT JOIN usuarios u ON u.id_usuario=h.id_usuario
JOIN postulaciones po ON h.entidad='postulacion' AND po.id_postulacion=h.id_registro
JOIN estudiantes st ON st.id_estudiante=po.id_estudiante WHERE h.eliminado=0;

CREATE OR REPLACE SQL SECURITY DEFINER VIEW vw_bitacora AS
SELECT b.id_bitacora,b.tabla_afectada,b.id_registro,b.accion,b.valores_anteriores,b.valores_nuevos,
       b.usuario_id,CONCAT(u.nombres,' ',u.apellidos) AS usuario,b.ip_cliente,b.fecha_hora
FROM bitacora b LEFT JOIN usuarios u ON u.id_usuario=b.usuario_id;

CREATE OR REPLACE SQL SECURITY DEFINER VIEW vw_dashboard_practicas AS
SELECT es.codigo AS estado_codigo,es.nombre AS estado,es.color,COUNT(*) AS cantidad
FROM practicas p JOIN estados es ON es.id_estado=p.id_estado AND es.tipo_entidad='practica' AND es.eliminado=0
WHERE p.eliminado=0 GROUP BY es.id_estado,es.codigo,es.nombre,es.color;

CREATE OR REPLACE SQL SECURITY DEFINER VIEW vw_dashboard_postulaciones AS
SELECT es.codigo AS estado_codigo,es.nombre AS estado,es.color,COUNT(*) AS cantidad
FROM postulaciones p JOIN estados es ON es.id_estado=p.id_estado AND es.tipo_entidad='postulacion' AND es.eliminado=0
WHERE p.eliminado=0 GROUP BY es.id_estado,es.codigo,es.nombre,es.color;



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
