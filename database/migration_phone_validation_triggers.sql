-- Protect new/changed phone values without truncating legacy values.
-- Existing invalid values can be corrected later with their owners; unrelated
-- updates remain possible until the strict column constraints are installed.
DELIMITER $$
CREATE TRIGGER trg_usuario_phone_validate_bi BEFORE INSERT ON usuarios FOR EACH ROW
BEGIN
  IF NEW.telefono IS NOT NULL AND NEW.telefono NOT REGEXP '^[0-9]{8}$' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='El teléfono debe contener exactamente ocho dígitos';
  END IF;
END$$

CREATE TRIGGER trg_usuario_phone_validate_bu BEFORE UPDATE ON usuarios FOR EACH ROW
BEGIN
  IF NEW.telefono IS NOT NULL AND NEW.telefono <> OLD.telefono AND NEW.telefono NOT REGEXP '^[0-9]{8}$' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='El teléfono debe contener exactamente ocho dígitos';
  END IF;
END$$

CREATE TRIGGER trg_empresa_phone_validate_bi BEFORE INSERT ON empresas FOR EACH ROW
BEGIN
  IF NEW.telefono IS NOT NULL AND NEW.telefono NOT REGEXP '^[0-9]{8}$' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='El teléfono debe contener exactamente ocho dígitos';
  END IF;
END$$

CREATE TRIGGER trg_empresa_phone_validate_bu BEFORE UPDATE ON empresas FOR EACH ROW
BEGIN
  IF NEW.telefono IS NOT NULL AND NEW.telefono <> OLD.telefono AND NEW.telefono NOT REGEXP '^[0-9]{8}$' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='El teléfono debe contener exactamente ocho dígitos';
  END IF;
END$$
DELIMITER ;
