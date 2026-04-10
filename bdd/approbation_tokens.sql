-- ============================================================
-- TABLE comitemaore_approbation_token
-- Tokens d'approbation envoyés par mail
-- ============================================================

CREATE TABLE IF NOT EXISTS `comitemaore_approbation_token` (
    `id_token`          int(10)      NOT NULL AUTO_INCREMENT,
    `id_approbation`    int(8)       NOT NULL COMMENT 'FK → comitemaore_approbation',
    `token`             varchar(64)  NOT NULL COMMENT 'Token SHA256 unique',
    `role_destinataire` enum(
                            'initiateur',
                            'meme_section',
                            'autre_section',
                            'bureau_national'
                        ) NOT NULL,
    `id_adht`           int(4)       NOT NULL COMMENT 'id_adht de l admin destinataire',
    `email_envoye_a`    varchar(150) NOT NULL COMMENT 'Email auquel le token a été envoyé',
    `expire_a`          datetime     NOT NULL COMMENT 'Expiration paramétrable max 12h et avant minuit',
    `utilise`           tinyint(1)   NOT NULL DEFAULT 0,
    `decision`          enum('approuve','rejete') DEFAULT NULL,
    `date_envoi`        datetime     DEFAULT CURRENT_TIMESTAMP,
    `date_utilisation`  datetime     DEFAULT NULL,
    `ip_utilisation`    varchar(45)  DEFAULT NULL,
    PRIMARY KEY (`id_token`),
    UNIQUE KEY `uq_token` (`token`),
    KEY `idx_approbation` (`id_approbation`),
    KEY `idx_adht` (`id_adht`),
    KEY `idx_expire` (`expire_a`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- ============================================================
-- TABLE comitemaore_approbation_config
-- Paramètres de durée de validité des tokens
-- ============================================================

CREATE TABLE IF NOT EXISTS `comitemaore_approbation_config` (
    `id_config`         int(4)       NOT NULL AUTO_INCREMENT,
    `duree_heures`      tinyint(2)   NOT NULL DEFAULT 8  COMMENT 'Durée en heures (max 12)',
    `duree_minutes`     tinyint(2)   NOT NULL DEFAULT 0  COMMENT 'Minutes supplémentaires',
    `email_expediteur`  varchar(150) NOT NULL DEFAULT 'noreply@comite-maore.org',
    `nom_expediteur`    varchar(100) NOT NULL DEFAULT 'Comité Maoré',
    `date_modification` datetime     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `modifie_par`       varchar(50)  DEFAULT NULL,
    PRIMARY KEY (`id_config`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- Insérer la configuration par défaut (8h, max avant minuit)
INSERT IGNORE INTO `comitemaore_approbation_config`
    (`id_config`, `duree_heures`, `duree_minutes`, `email_expediteur`, `nom_expediteur`)
VALUES
    (1, 8, 0, 'noreply@comite-maore.org', 'Comité Maoré');
