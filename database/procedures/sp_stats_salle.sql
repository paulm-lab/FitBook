-- =============================================================================
-- sp_stats_salle — statistiques d'une salle pour le tableau de bord gestionnaire (US-G05)
-- =============================================================================
-- Paramètre :
--   p_gym_id  IN  identifiant de la salle
--
-- Renvoie une ligne :
--   gym_id               identifiant de la salle
--   nb_seances           séances actives (passées et à venir)
--   capacite_totale      somme des capacités de ces séances
--   nb_reservations      réservations confirmées ou terminées sur ces séances
--   taux_remplissage     nb_reservations / capacite_totale, en % (NULL sans séance)
--   note_moyenne         moyenne des avis visibles sur la salle (NULL sans avis)
--   nb_avis              nombre d'avis visibles sur la salle
--
-- Lecture seule : aucune transaction nécessaire.
--
-- Exemple :  CALL sp_stats_salle(3);
-- =============================================================================

DROP PROCEDURE IF EXISTS sp_stats_salle;

DELIMITER $$

CREATE PROCEDURE sp_stats_salle(
    IN p_gym_id INT
)
READS SQL DATA
BEGIN
    DECLARE v_sessions     INT           DEFAULT 0;
    DECLARE v_capacity     INT           DEFAULT 0;
    DECLARE v_reservations INT           DEFAULT 0;
    DECLARE v_rating       DECIMAL(3, 2) DEFAULT NULL;
    DECLARE v_reviews      INT           DEFAULT 0;

    SELECT COUNT(*), COALESCE(SUM(capacity), 0)
      INTO v_sessions, v_capacity
      FROM `session`
     WHERE gym_id = p_gym_id
       AND status = 'active';

    SELECT COUNT(*)
      INTO v_reservations
      FROM reservation r
      JOIN `session` s ON s.id = r.session_id
     WHERE s.gym_id = p_gym_id
       AND s.status = 'active'
       AND r.status IN ('confirmee', 'terminee');

    SELECT ROUND(AVG(rating), 2), COUNT(*)
      INTO v_rating, v_reviews
      FROM review
     WHERE gym_id = p_gym_id
       AND is_visible = 1;

    SELECT p_gym_id                                              AS gym_id,
           v_sessions                                            AS nb_seances,
           v_capacity                                            AS capacite_totale,
           v_reservations                                        AS nb_reservations,
           ROUND(100 * v_reservations / NULLIF(v_capacity, 0), 1) AS taux_remplissage,
           v_rating                                              AS note_moyenne,
           v_reviews                                             AS nb_avis;
END$$

DELIMITER ;
