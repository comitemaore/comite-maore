-- ============================================================
-- COMITÉ MAORÉ — Organigramme des sections, fédérations
--                et bureau exécutif national
-- À exécuter dans : comitemaore
-- ============================================================

-- ------------------------------------------------------------
-- 1. ALTER TABLE comitemaore_sections
--    Ajout des fonctions + 3 administrateurs (id_adht)
-- ------------------------------------------------------------
ALTER TABLE `comitemaore_sections`
    ADD COLUMN `president`          int(4)       DEFAULT NULL COMMENT 'id_adht du Président',
    ADD COLUMN `vice_president`     int(4)       DEFAULT NULL COMMENT 'id_adht du Vice-président',
    ADD COLUMN `secretaire`         int(4)       DEFAULT NULL COMMENT 'id_adht du Secrétaire',
    ADD COLUMN `tresorier`          int(4)       DEFAULT NULL COMMENT 'id_adht du Trésorier',
    ADD COLUMN `secretaire_adjoint` int(4)       DEFAULT NULL COMMENT 'id_adht du Secrétaire adjoint',
    ADD COLUMN `tresorier_adjoint`  int(4)       DEFAULT NULL COMMENT 'id_adht du Trésorier adjoint',
    ADD COLUMN `administrateur1`    int(4)       DEFAULT NULL COMMENT 'id_adht Administrateur 1',
    ADD COLUMN `administrateur2`    int(4)       DEFAULT NULL COMMENT 'id_adht Administrateur 2',
    ADD COLUMN `administrateur3`    int(4)       DEFAULT NULL COMMENT 'id_adht Administrateur 3',
    ADD COLUMN `date_modification`  datetime     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    ADD COLUMN `modifie_par`        varchar(50)  DEFAULT NULL COMMENT 'login de l admin ayant modifié';

-- ------------------------------------------------------------
-- 2. ALTER TABLE comitemaore_federations
--    Ajout nom complet + fonctions + 3 administrateurs
-- ------------------------------------------------------------
ALTER TABLE `comitemaore_federations`
    ADD COLUMN `nom_complet`        varchar(200) DEFAULT NULL COMMENT 'Nom complet de la fédération',
    ADD COLUMN `president`          int(4)       DEFAULT NULL COMMENT 'id_adht du Président',
    ADD COLUMN `vice_president`     int(4)       DEFAULT NULL COMMENT 'id_adht du Vice-président',
    ADD COLUMN `secretaire`         int(4)       DEFAULT NULL COMMENT 'id_adht du Secrétaire',
    ADD COLUMN `tresorier`          int(4)       DEFAULT NULL COMMENT 'id_adht du Trésorier',
    ADD COLUMN `secretaire_adjoint` int(4)       DEFAULT NULL COMMENT 'id_adht du Secrétaire adjoint',
    ADD COLUMN `tresorier_adjoint`  int(4)       DEFAULT NULL COMMENT 'id_adht du Trésorier adjoint',
    ADD COLUMN `administrateur1`    int(4)       DEFAULT NULL COMMENT 'id_adht Administrateur 1',
    ADD COLUMN `administrateur2`    int(4)       DEFAULT NULL COMMENT 'id_adht Administrateur 2',
    ADD COLUMN `administrateur3`    int(4)       DEFAULT NULL COMMENT 'id_adht Administrateur 3',
    ADD COLUMN `date_modification`  datetime     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    ADD COLUMN `modifie_par`        varchar(50)  DEFAULT NULL COMMENT 'login de l admin ayant modifié';

-- ------------------------------------------------------------
-- 3. CREATE TABLE comitemaore_burexecnat
--    Bureau Exécutif National
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `comitemaore_burexecnat` (
    `id_burexec`         int(4)       NOT NULL AUTO_INCREMENT,
    `nom_bureau`         varchar(200) NOT NULL DEFAULT 'Bureau Exécutif National',
    `annee_mandat`       year(4)      NOT NULL DEFAULT (YEAR(CURDATE())),
    -- Fonctions de l'organigramme
    `president`          int(4)       DEFAULT NULL COMMENT 'id_adht du Président',
    `vice_president`     int(4)       DEFAULT NULL COMMENT 'id_adht du Vice-président',
    `secretaire`         int(4)       DEFAULT NULL COMMENT 'id_adht du Secrétaire',
    `tresorier`          int(4)       DEFAULT NULL COMMENT 'id_adht du Trésorier',
    `secretaire_adjoint` int(4)       DEFAULT NULL COMMENT 'id_adht du Secrétaire adjoint',
    `tresorier_adjoint`  int(4)       DEFAULT NULL COMMENT 'id_adht du Trésorier adjoint',
    -- Administrateurs système
    `administrateur1`    int(4)       DEFAULT NULL COMMENT 'id_adht Administrateur 1',
    `administrateur2`    int(4)       DEFAULT NULL COMMENT 'id_adht Administrateur 2',
    `administrateur3`    int(4)       DEFAULT NULL COMMENT 'id_adht Administrateur 3',
    -- Métadonnées
    `actif`              tinyint(1)   NOT NULL DEFAULT 1,
    `date_creation`      datetime     DEFAULT CURRENT_TIMESTAMP,
    `date_modification`  datetime     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `modifie_par`        varchar(50)  DEFAULT NULL COMMENT 'login de l admin ayant modifié',
    PRIMARY KEY (`id_burexec`),
    UNIQUE KEY `uq_annee` (`annee_mandat`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- Insérer le bureau de l'année courante
INSERT IGNORE INTO `comitemaore_burexecnat` (`nom_bureau`, `annee_mandat`)
VALUES ('Bureau Exécutif National', YEAR(CURDATE()));
