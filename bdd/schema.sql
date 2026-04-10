-- ============================================================
-- COMITÉ MAORÉ — Schéma SQL complet
-- À exécuter dans la base : comitemaore
-- ============================================================


-- ------------------------------------------------------------
-- 3. TABLE FINANCES GÉNÉRALES (tab_finance)
--    Une ligne par section/fédération — soldes courants
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `tab_finance` (
    `id_finance`        int(4)         NOT NULL AUTO_INCREMENT,
    `id_section`        int(4)         DEFAULT NULL COMMENT 'Section concernée',
    `id_federation`     int(4)         DEFAULT NULL COMMENT 'Fédération concernée',
    `annee_finance`     year(4)        NOT NULL DEFAULT (YEAR(CURDATE())),
    `fin_burnatexec`    decimal(10,2)  NOT NULL DEFAULT 0.00 COMMENT '50% cotisation → Bureau National Exécutif',
    `fin_fedcorresp`    decimal(10,2)  NOT NULL DEFAULT 0.00 COMMENT '30% cotisation → Fédération adherent',
    `fin_sectcorresp`   decimal(10,2)  NOT NULL DEFAULT 0.00 COMMENT '20% cotisation → Section adherent',
    `fin_total_dons`    decimal(10,2)  NOT NULL DEFAULT 0.00 COMMENT 'Total des dons reçus',
    `fin_total_cotis`   decimal(10,2)  NOT NULL DEFAULT 0.00 COMMENT 'Total cotisations encaissées',
    `fin_cotis_dues`    decimal(10,2)  NOT NULL DEFAULT 0.00 COMMENT 'Cotisations dues restantes',
    `fin_ristourne`     decimal(10,2)  NOT NULL DEFAULT 0.00 COMMENT 'Total ristournes professionnelles',
    `date_maj`          datetime       DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id_finance`),
    UNIQUE KEY `uq_section_annee` (`id_section`, `annee_finance`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- ------------------------------------------------------------
-- 4. TABLE FINANCES PAR ADHÉRENT (finance_adh)
--    Historique de toutes les opérations financières
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `finance_adh` (
    `id_fin_adh`            int(8)         NOT NULL AUTO_INCREMENT,
    `id_adht`               int(4)         NOT NULL COMMENT 'FK → comitemaore_adherent',
    `id_section`            int(4)         DEFAULT NULL,
    `type_operation`        enum(
                                'renouvellement_carte',
                                'ristourne',
                                'cotisation',
                                'cotisation_due',
                                'don',
                                'note'
                            ) NOT NULL,
    `annee_cotisation`      year(4)        DEFAULT NULL COMMENT 'Année concernée (cotisation)',
    `montant`               decimal(10,2)  DEFAULT 0.00,
    `montant_burnatexec`    decimal(10,2)  DEFAULT 0.00 COMMENT '50% → BNE',
    `montant_fedcorresp`    decimal(10,2)  DEFAULT 0.00 COMMENT '30% → Fédération',
    `montant_sectcorresp`   decimal(10,2)  DEFAULT 0.00 COMMENT '20% → Section',
    `date_operation`        date           NOT NULL DEFAULT (CURDATE()),
    `date_renouvellement`   date           DEFAULT NULL COMMENT 'Date renouvellement carte',
    `nature_don`            varchar(100)   DEFAULT NULL COMMENT 'Nature du don',
    `note`                  text           DEFAULT NULL COMMENT 'Note libre',
    `cotis_due_payee`       tinyint(1)     DEFAULT 0 COMMENT '1 si cotisation due réglée',
    `id_fin_due_regle`      int(8)         DEFAULT NULL COMMENT 'FK → finance_adh (cotisation due réglée)',
    `enregistre_par`        int(4)         DEFAULT NULL COMMENT 'FK → comitemaore_adherent (qui a saisi)',
    `date_enregistrement`   datetime       DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id_fin_adh`),
    KEY `idx_adht` (`id_adht`),
    KEY `idx_section` (`id_section`),
    KEY `idx_type` (`type_operation`),
    KEY `idx_annee` (`annee_cotisation`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- ------------------------------------------------------------
-- 5. TABLE DOCUMENTS ADHÉRENT
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `comitemaore_document` (
    `id_doc`            int(8)       NOT NULL AUTO_INCREMENT,
    `id_adht`           int(4)       NOT NULL,
    `titre_doc`         varchar(200) NOT NULL DEFAULT '',
    `type_doc`          varchar(50)  DEFAULT NULL COMMENT 'pièce identité, diplôme, contrat...',
    `nom_fichier`       varchar(255) NOT NULL DEFAULT '' COMMENT 'Nom original',
    `chemin_fichier`    varchar(500) NOT NULL DEFAULT '' COMMENT 'Chemin relatif sur le serveur',
    `mime_type`         varchar(100) DEFAULT NULL,
    `taille_fichier`    int(10)      DEFAULT NULL COMMENT 'Taille en octets',
    `description`       text         DEFAULT NULL,
    `date_upload`       datetime     DEFAULT CURRENT_TIMESTAMP,
    `uploade_par`       int(4)       DEFAULT NULL,
    PRIMARY KEY (`id_doc`),
    KEY `idx_adht_doc` (`id_adht`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- ------------------------------------------------------------
-- 6. TABLE CV ADHÉRENT
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `comitemaore_cv` (
    `id_cv`               int(8)       NOT NULL AUTO_INCREMENT,
    `id_adht`             int(4)       NOT NULL,
    `type_entree`         enum('etude','diplome','experience','note') NOT NULL DEFAULT 'etude',
    `intitule`            varchar(300) NOT NULL DEFAULT '' COMMENT 'Intitule etude diplome poste',
    `etablissement`       varchar(200) DEFAULT NULL,
    `periode_debut`       varchar(10)  DEFAULT NULL COMMENT 'Annee ou date debut ex 2015',
    `periode_fin`         varchar(10)  DEFAULT NULL COMMENT 'Annee ou date fin ex 2018',
    `mention`             varchar(100) DEFAULT NULL COMMENT 'Mention grade',
    `notes_particulieres` text         DEFAULT NULL,
    `ordre_affichage`     int(3)       DEFAULT 0,
    `date_creation`       datetime     DEFAULT CURRENT_TIMESTAMP,
    `date_modification`   datetime     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id_cv`),
    KEY `idx_adht_cv` (`id_adht`),
    KEY `idx_type_cv` (`type_entree`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- ------------------------------------------------------------
-- 7. TABLE WORKFLOW D'APPROBATION (tokens 3 admins)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `comitemaore_approbation` (
    `id_approbation`    int(8)       NOT NULL AUTO_INCREMENT,
    `id_adht_cible`     int(4)       NOT NULL COMMENT 'Adhérent concerné par l opération',
    `type_operation`    enum('ajout','modification','suppression') NOT NULL,
    `data_operation`    longtext     DEFAULT NULL COMMENT 'JSON des données à valider',
    `statut`            enum('en_attente','approuve','rejete','expire') NOT NULL DEFAULT 'en_attente',
    `id_initiateur`     int(4)       NOT NULL COMMENT 'Admin qui démarre la demande',
    `id_section_init`   int(4)       DEFAULT NULL COMMENT 'Section de l initiateur',
    `date_creation`     datetime     DEFAULT CURRENT_TIMESTAMP,
    `date_expiration`   datetime     DEFAULT NULL COMMENT 'Validité 48h',
    `date_resolution`   datetime     DEFAULT NULL,
    PRIMARY KEY (`id_approbation`),
    KEY `idx_statut` (`statut`),
    KEY `idx_cible` (`id_adht_cible`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

CREATE TABLE IF NOT EXISTS `comitemaore_approbation_vote` (
    `id_vote`           int(8)       NOT NULL AUTO_INCREMENT,
    `id_approbation`    int(8)       NOT NULL,
    `id_votant`         int(4)       NOT NULL COMMENT 'Admin qui vote',
    `id_section_votant` int(4)       DEFAULT NULL,
    `role_vote`         enum('initiateur','meme_section','autre_section_1','autre_section_2') NOT NULL,
    `decision`          enum('approuve','rejete') NOT NULL,
    `commentaire`       varchar(500) DEFAULT NULL,
    `date_vote`         datetime     DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id_vote`),
    UNIQUE KEY `uq_votant_approbation` (`id_approbation`, `id_votant`),
    KEY `idx_approbation` (`id_approbation`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- ------------------------------------------------------------
-- 8. TABLE LOGS
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `comitemaore_log` (
    `id_log`            int(10)      NOT NULL AUTO_INCREMENT,
    `id_adht`           int(4)       DEFAULT NULL COMMENT 'Utilisateur connecté',
    `login_adht`        varchar(33)  DEFAULT NULL,
    `ip_adresse`        varchar(45)  DEFAULT NULL,
    `action`            varchar(100) NOT NULL DEFAULT '' COMMENT 'Type d action',
    `module`            varchar(50)  DEFAULT NULL COMMENT 'adherent, finance, document, cv...',
    `id_cible`          int(8)       DEFAULT NULL COMMENT 'ID de l enregistrement concerné',
    `detail`            text         DEFAULT NULL COMMENT 'Détail JSON de l opération',
    `resultat`          enum('succes','echec','info') NOT NULL DEFAULT 'info',
    `date_action`       datetime     DEFAULT CURRENT_TIMESTAMP,
    `session_id`        varchar(100) DEFAULT NULL,
    PRIMARY KEY (`id_log`),
    KEY `idx_adht_log` (`id_adht`),
    KEY `idx_date` (`date_action`),
    KEY `idx_action` (`action`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- ------------------------------------------------------------
-- 9. TABLE COTISATIONS DUES (référentiel)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `comitemaore_cotisation_due` (
    `id_cotis_due`      int(8)       NOT NULL AUTO_INCREMENT,
    `id_adht`           int(4)       NOT NULL,
    `annee`             year(4)      NOT NULL,
    `montant_du`        decimal(10,2) NOT NULL DEFAULT 0.00,
    `montant_paye`      decimal(10,2) NOT NULL DEFAULT 0.00,
    `solde_restant`     decimal(10,2) GENERATED ALWAYS AS (`montant_du` - `montant_paye`) STORED,
    `statut`            enum('due','partiellement_payee','soldee') NOT NULL DEFAULT 'due',
    `date_creation`     datetime     DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id_cotis_due`),
    KEY `idx_adht_cotis` (`id_adht`),
    KEY `idx_statut_cotis` (`statut`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;
