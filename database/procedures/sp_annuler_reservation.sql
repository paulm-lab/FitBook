-- =============================================================================
-- sp_annuler_reservation — annule une réservation au plus tard 2 h avant la séance (RG-05)
-- =============================================================================
-- Paramètres :
--   p_reservation_id  IN   identifiant de la réservation
--   p_user_id         IN   identifiant du sportif qui demande l'annulation
--   p_code            OUT  0 = annulée
--                          1 = réservation introuvable
--                          2 = la réservation appartient à un autre utilisateur
--                          3 = trop tard : la séance commence dans 2 h ou moins
--                          4 = déjà annulée ou terminée
--
-- Transaction propre avec verrou sur la réservation. Dates en UTC (UTC_TIMESTAMP()).
--
-- Exemple :  CALL sp_annuler_reservation(56, 12, @code);  SELECT @code;
-- =============================================================================

DROP PROCEDURE IF EXISTS sp_annuler_reservation;

DELIMITER $$

CREATE PROCEDURE sp_annuler_reservation(
    IN  p_reservation_id INT,
    IN  p_user_id        INT,
    OUT p_code           TINYINT
)
MODIFIES SQL DATA
BEGIN
    DECLARE v_owner_id INT         DEFAULT NULL;
    DECLARE v_status   VARCHAR(20) DEFAULT NULL;
    DECLARE v_start_at DATETIME    DEFAULT NULL;

    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;

    SET p_code = NULL;

    START TRANSACTION;

    SELECT r.user_id, r.status, s.start_at
      INTO v_owner_id, v_status, v_start_at
      FROM reservation r
      JOIN `session` s ON s.id = r.session_id
     WHERE r.id = p_reservation_id
       FOR UPDATE;

    IF v_owner_id IS NULL THEN
        SET p_code = 1;
        ROLLBACK;

    ELSEIF v_owner_id <> p_user_id THEN
        SET p_code = 2;
        ROLLBACK;

    ELSEIF v_status <> 'confirmee' THEN
        SET p_code = 4;
        ROLLBACK;

    ELSEIF v_start_at <= UTC_TIMESTAMP() + INTERVAL 2 HOUR THEN
        SET p_code = 3;
        ROLLBACK;

    ELSE
        UPDATE reservation
           SET status = 'annulee',
               cancelled_at = UTC_TIMESTAMP()
         WHERE id = p_reservation_id;

        SET p_code = 0;
        COMMIT;
    END IF;
END$$

DELIMITER ;
