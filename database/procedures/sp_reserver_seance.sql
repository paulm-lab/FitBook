-- =============================================================================
-- sp_reserver_seance — réserve une place sur une séance (RG-03, RG-04)
-- =============================================================================
-- Paramètres :
--   p_user_id         IN   identifiant du sportif
--   p_session_id      IN   identifiant de la séance
--   p_code            OUT  0 = réservée
--                          1 = séance introuvable
--                          2 = séance indisponible (annulée ou déjà commencée)
--                          3 = déjà réservée par ce sportif
--                          4 = séance complète
--   p_reservation_id  OUT  identifiant de la réservation (si p_code = 0)
--
-- La procédure gère sa propre transaction : la séance est verrouillée (FOR UPDATE)
-- pendant tout le contrôle, donc deux appels simultanés sur la dernière place ne
-- peuvent pas réussir tous les deux. Elle ne doit pas être appelée à l'intérieur
-- d'une transaction ouverte (START TRANSACTION la validerait implicitement).
--
-- Les dates sont stockées en UTC : comparaison avec UTC_TIMESTAMP(), jamais NOW().
--
-- Exemple :  CALL sp_reserver_seance(12, 34, @code, @id);  SELECT @code, @id;
-- =============================================================================

DROP PROCEDURE IF EXISTS sp_reserver_seance;

DELIMITER $$

CREATE PROCEDURE sp_reserver_seance(
    IN  p_user_id        INT,
    IN  p_session_id     INT,
    OUT p_code           TINYINT,
    OUT p_reservation_id INT
)
MODIFIES SQL DATA
BEGIN
    DECLARE v_status          VARCHAR(20) DEFAULT NULL;
    DECLARE v_start_at        DATETIME    DEFAULT NULL;
    DECLARE v_capacity        INT         DEFAULT 0;
    DECLARE v_taken           INT         DEFAULT 0;
    DECLARE v_existing_id     INT         DEFAULT NULL;
    DECLARE v_existing_status VARCHAR(20) DEFAULT NULL;

    -- Toute erreur SQL (utilisateur inexistant, etc.) annule la transaction puis remonte.
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;

    SET p_code = NULL;
    SET p_reservation_id = NULL;

    START TRANSACTION;

    -- 1. Verrouille la séance : les autres réservations attendent la fin de ce contrôle.
    SELECT status, start_at, capacity
      INTO v_status, v_start_at, v_capacity
      FROM `session`
     WHERE id = p_session_id
       FOR UPDATE;

    IF v_status IS NULL THEN
        SET p_code = 1;
        ROLLBACK;

    ELSEIF v_status <> 'active' OR v_start_at <= UTC_TIMESTAMP() THEN
        SET p_code = 2;
        ROLLBACK;

    ELSE
        -- 2. Une seule ligne par couple (sportif, séance) : on la réutilise si elle existe.
        SELECT id, status
          INTO v_existing_id, v_existing_status
          FROM reservation
         WHERE user_id = p_user_id
           AND session_id = p_session_id
           FOR UPDATE;

        IF v_existing_status = 'confirmee' THEN
            SET p_code = 3;
            ROLLBACK;

        ELSE
            -- 3. Contrôle de capacité sur les réservations confirmées.
            SELECT COUNT(*)
              INTO v_taken
              FROM reservation
             WHERE session_id = p_session_id
               AND status = 'confirmee';

            IF v_taken >= v_capacity THEN
                SET p_code = 4;
                ROLLBACK;

            ELSEIF v_existing_id IS NOT NULL THEN
                -- Réservation annulée auparavant : réactivée, avec un nouveau rappel à envoyer.
                UPDATE reservation
                   SET status = 'confirmee',
                       created_at = UTC_TIMESTAMP(),
                       cancelled_at = NULL,
                       reminder_sent_at = NULL
                 WHERE id = v_existing_id;

                SET p_reservation_id = v_existing_id;
                SET p_code = 0;
                COMMIT;

            ELSE
                INSERT INTO reservation (user_id, session_id, status, created_at)
                VALUES (p_user_id, p_session_id, 'confirmee', UTC_TIMESTAMP());

                SET p_reservation_id = LAST_INSERT_ID();
                SET p_code = 0;
                COMMIT;
            END IF;
        END IF;
    END IF;
END$$

DELIMITER ;
