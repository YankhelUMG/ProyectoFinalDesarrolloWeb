-- Migration to keep Guatemalan contact numbers as exactly eight digits.
-- Review the SELECT result first. Resolve any legacy values with their owners
-- before running the ALTER statements; this migration intentionally never
-- truncates or silently discards existing contact data.
SELECT 'usuarios' AS tabla, id_usuario AS id, usuario AS referencia, telefono
FROM usuarios
WHERE telefono IS NOT NULL AND telefono NOT REGEXP '^[0-9]{8}$'
UNION ALL
SELECT 'empresas', id_empresa, nombre_legal, telefono
FROM empresas
WHERE telefono IS NOT NULL AND telefono NOT REGEXP '^[0-9]{8}$';

-- Run these statements only after the SELECT above returns no rows.
ALTER TABLE usuarios
  MODIFY telefono VARCHAR(8) NULL,
  ADD CONSTRAINT chk_usuario_telefono
    CHECK (telefono IS NULL OR telefono REGEXP '^[0-9]{8}$');

ALTER TABLE empresas
  MODIFY telefono VARCHAR(8) NULL,
  ADD CONSTRAINT chk_empresa_telefono
    CHECK (telefono IS NULL OR telefono REGEXP '^[0-9]{8}$');
