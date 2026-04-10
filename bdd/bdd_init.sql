mysqldump: [Warning] Using a password on the command line interface can be insecure.
-- MySQL dump 10.13  Distrib 8.4.8, for Linux (x86_64)
--
-- Host: 127.0.0.1    Database: comitemaore
-- ------------------------------------------------------
-- Server version	8.4.8

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `comitemaore_adherent`
--

DROP TABLE IF EXISTS `comitemaore_adherent`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `comitemaore_adherent` (
  `id_adht` int NOT NULL AUTO_INCREMENT,
  `soc_adht` varchar(3) DEFAULT '',
  `civilite_adht` varchar(13) DEFAULT NULL,
  `prenom_adht` varchar(50) DEFAULT '',
  `nom_adht` varchar(50) DEFAULT '',
  `adresse_adht` varchar(100) DEFAULT NULL,
  `cp_adht` varchar(8) DEFAULT '0',
  `ville_adht` varchar(50) DEFAULT '',
  `telephonef_adht` varchar(16) DEFAULT NULL,
  `telephonep_adht` varchar(16) DEFAULT NULL,
  `telecopie_adht` varchar(16) DEFAULT NULL,
  `email_adht` varchar(75) DEFAULT NULL,
  `promotion_adht` varchar(25) DEFAULT NULL,
  `datecreationfiche_adht` date DEFAULT NULL,
  `datenaisance_adht` date DEFAULT NULL,
  `visibl_adht` varchar(3) DEFAULT 'Non',
  `datemodiffiche_adht` date DEFAULT NULL,
  `siteweb_adht` varchar(50) DEFAULT NULL,
  `password_adht` varchar(33) DEFAULT '',
  `login_adht` varchar(33) DEFAULT '',
  `priorite_adht` varchar(1) DEFAULT '1',
  `date_echeance_cotis` date DEFAULT NULL,
  `dacces` datetime DEFAULT NULL,
  `date_sortie` date DEFAULT NULL,
  `tranche_age` varchar(20) DEFAULT '',
  `cotis_adht` varchar(3) DEFAULT 'Non',
  `disponib_adht` varchar(250) DEFAULT NULL,
  `profession_adht` varchar(50) DEFAULT '',
  `autres_info_adht` varchar(100) DEFAULT '',
  `NIN_adh` varchar(45) NOT NULL,
  `id_section` int DEFAULT NULL,
  PRIMARY KEY (`id_adht`),
  UNIQUE KEY `id_adht_UNIQUE` (`id_adht`),
  UNIQUE KEY `NIN_adh_UNIQUE` (`NIN_adh`),
  KEY `idx_id_section` (`id_section`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `comitemaore_adherent`
--

LOCK TABLES `comitemaore_adherent` WRITE;
/*!40000 ALTER TABLE `comitemaore_adherent` DISABLE KEYS */;
INSERT INTO `comitemaore_adherent` VALUES (1,'','M.','El-Macelie','SAID JAFFAR','Djivani','99397','Moroni',NULL,'4321244',NULL,'el_macelie@yahoo.fr',NULL,'2026-04-06','1944-12-18','Non',NULL,NULL,'azerty','jaffar','9',NULL,'2026-04-04 20:27:59',NULL,'','Non',NULL,'Retraité','Recherche des archives','EID038584',NULL);
/*!40000 ALTER TABLE `comitemaore_adherent` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `comitemaore_approbation`
--

DROP TABLE IF EXISTS `comitemaore_approbation`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `comitemaore_approbation` (
  `id_approbation` int NOT NULL AUTO_INCREMENT,
  `id_adht_cible` int NOT NULL COMMENT 'Adhérent concerné par l opération',
  `type_operation` enum('ajout','modification','suppression') NOT NULL,
  `data_operation` longtext COMMENT 'JSON des données à valider',
  `statut` enum('en_attente','approuve','rejete','expire') NOT NULL DEFAULT 'en_attente',
  `id_initiateur` int NOT NULL COMMENT 'Admin qui démarre la demande',
  `id_section_init` int DEFAULT NULL COMMENT 'Section de l initiateur',
  `date_creation` datetime DEFAULT CURRENT_TIMESTAMP,
  `date_expiration` datetime DEFAULT NULL COMMENT 'Validité 48h',
  `date_resolution` datetime DEFAULT NULL,
  PRIMARY KEY (`id_approbation`),
  KEY `idx_statut` (`statut`),
  KEY `idx_cible` (`id_adht_cible`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `comitemaore_approbation`
--

LOCK TABLES `comitemaore_approbation` WRITE;
/*!40000 ALTER TABLE `comitemaore_approbation` DISABLE KEYS */;
INSERT INTO `comitemaore_approbation` VALUES (1,0,'ajout','{\"civilite_adht\":\"M.\",\"prenom_adht\":\"Mahamoud\",\"nom_adht\":\"Jaffar\",\"adresse_adht\":\"5 RUE FRA ANGELICO\",\"cp_adht\":\"72100\",\"ville_adht\":\"Le Mans\",\"telephonef_adht\":\"\",\"telephonep_adht\":\"0603551547\",\"email_adht\":\"mahamoud.jaffar@hotmail.fr\",\"profession_adht\":\"Administrateur SI\",\"promotion_adht\":\"\",\"datenaisance_adht\":\"1977-03-03\",\"id_section\":28,\"visibl_adht\":\"Oui\",\"disponib_adht\":\"Immédiat\",\"autres_info_adht\":\"\",\"cotis_adht\":\"Oui\",\"date_echeance_cotis\":\"2027-03-10\"}','en_attente',1,NULL,'2026-04-10 14:21:15','2026-04-12 14:21:15',NULL);
/*!40000 ALTER TABLE `comitemaore_approbation` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `comitemaore_approbation_config`
--

DROP TABLE IF EXISTS `comitemaore_approbation_config`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `comitemaore_approbation_config` (
  `id_config` int NOT NULL AUTO_INCREMENT,
  `duree_heures` tinyint NOT NULL DEFAULT '8' COMMENT 'Durée en heures (max 12)',
  `duree_minutes` tinyint NOT NULL DEFAULT '0' COMMENT 'Minutes supplémentaires',
  `email_expediteur` varchar(150) NOT NULL DEFAULT 'noreply@comite-maore.org',
  `nom_expediteur` varchar(100) NOT NULL DEFAULT 'Comité Maoré',
  `date_modification` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `modifie_par` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id_config`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `comitemaore_approbation_config`
--

LOCK TABLES `comitemaore_approbation_config` WRITE;
/*!40000 ALTER TABLE `comitemaore_approbation_config` DISABLE KEYS */;
INSERT INTO `comitemaore_approbation_config` VALUES (1,8,0,'noreply@comite-maore.org','Comité Maoré','2026-04-10 16:05:23',NULL);
/*!40000 ALTER TABLE `comitemaore_approbation_config` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `comitemaore_approbation_token`
--

DROP TABLE IF EXISTS `comitemaore_approbation_token`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `comitemaore_approbation_token` (
  `id_token` int NOT NULL AUTO_INCREMENT,
  `id_approbation` int NOT NULL COMMENT 'FK → comitemaore_approbation',
  `token` varchar(64) NOT NULL COMMENT 'Token SHA256 unique',
  `role_destinataire` enum('initiateur','meme_section','autre_section','bureau_national') NOT NULL,
  `id_adht` int NOT NULL COMMENT 'id_adht de l admin destinataire',
  `email_envoye_a` varchar(150) NOT NULL COMMENT 'Email auquel le token a été envoyé',
  `expire_a` datetime NOT NULL COMMENT 'Expiration paramétrable max 12h et avant minuit',
  `utilise` tinyint(1) NOT NULL DEFAULT '0',
  `decision` enum('approuve','rejete') DEFAULT NULL,
  `date_envoi` datetime DEFAULT CURRENT_TIMESTAMP,
  `date_utilisation` datetime DEFAULT NULL,
  `ip_utilisation` varchar(45) DEFAULT NULL,
  PRIMARY KEY (`id_token`),
  UNIQUE KEY `uq_token` (`token`),
  KEY `idx_approbation` (`id_approbation`),
  KEY `idx_adht` (`id_adht`),
  KEY `idx_expire` (`expire_a`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `comitemaore_approbation_token`
--

LOCK TABLES `comitemaore_approbation_token` WRITE;
/*!40000 ALTER TABLE `comitemaore_approbation_token` DISABLE KEYS */;
/*!40000 ALTER TABLE `comitemaore_approbation_token` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `comitemaore_approbation_vote`
--

DROP TABLE IF EXISTS `comitemaore_approbation_vote`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `comitemaore_approbation_vote` (
  `id_vote` int NOT NULL AUTO_INCREMENT,
  `id_approbation` int NOT NULL,
  `id_votant` int NOT NULL COMMENT 'Admin qui vote',
  `id_section_votant` int DEFAULT NULL,
  `role_vote` enum('initiateur','meme_section','autre_section_1','autre_section_2') NOT NULL,
  `decision` enum('approuve','rejete') NOT NULL,
  `commentaire` varchar(500) DEFAULT NULL,
  `date_vote` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_vote`),
  UNIQUE KEY `uq_votant_approbation` (`id_approbation`,`id_votant`),
  KEY `idx_approbation` (`id_approbation`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `comitemaore_approbation_vote`
--

LOCK TABLES `comitemaore_approbation_vote` WRITE;
/*!40000 ALTER TABLE `comitemaore_approbation_vote` DISABLE KEYS */;
INSERT INTO `comitemaore_approbation_vote` VALUES (1,1,1,NULL,'initiateur','approuve',NULL,'2026-04-10 14:21:15');
/*!40000 ALTER TABLE `comitemaore_approbation_vote` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `comitemaore_burexecnat`
--

DROP TABLE IF EXISTS `comitemaore_burexecnat`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `comitemaore_burexecnat` (
  `id_burexec` int NOT NULL AUTO_INCREMENT,
  `nom_bureau` varchar(200) NOT NULL DEFAULT 'Bureau Exécutif National',
  `annee_mandat` year NOT NULL DEFAULT (year(curdate())),
  `president` int DEFAULT NULL COMMENT 'id_adht du Président',
  `vice_president` int DEFAULT NULL COMMENT 'id_adht du Vice-président',
  `secretaire` int DEFAULT NULL COMMENT 'id_adht du Secrétaire',
  `tresorier` int DEFAULT NULL COMMENT 'id_adht du Trésorier',
  `secretaire_adjoint` int DEFAULT NULL COMMENT 'id_adht du Secrétaire adjoint',
  `tresorier_adjoint` int DEFAULT NULL COMMENT 'id_adht du Trésorier adjoint',
  `administrateur1` int DEFAULT NULL COMMENT 'id_adht Administrateur 1',
  `administrateur2` int DEFAULT NULL COMMENT 'id_adht Administrateur 2',
  `administrateur3` int DEFAULT NULL COMMENT 'id_adht Administrateur 3',
  `actif` tinyint(1) NOT NULL DEFAULT '1',
  `date_creation` datetime DEFAULT CURRENT_TIMESTAMP,
  `date_modification` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `modifie_par` varchar(50) DEFAULT NULL COMMENT 'login de l admin ayant modifié',
  PRIMARY KEY (`id_burexec`),
  UNIQUE KEY `uq_annee` (`annee_mandat`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `comitemaore_burexecnat`
--

LOCK TABLES `comitemaore_burexecnat` WRITE;
/*!40000 ALTER TABLE `comitemaore_burexecnat` DISABLE KEYS */;
INSERT INTO `comitemaore_burexecnat` VALUES (1,'Bureau Exécutif National',2026,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1,'2026-04-10 15:57:28','2026-04-10 15:57:28',NULL);
/*!40000 ALTER TABLE `comitemaore_burexecnat` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `comitemaore_cotisation_due`
--

DROP TABLE IF EXISTS `comitemaore_cotisation_due`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `comitemaore_cotisation_due` (
  `id_cotis_due` int NOT NULL AUTO_INCREMENT,
  `id_adht` int NOT NULL,
  `annee` year NOT NULL,
  `montant_du` decimal(10,2) NOT NULL DEFAULT '0.00',
  `montant_paye` decimal(10,2) NOT NULL DEFAULT '0.00',
  `solde_restant` decimal(10,2) GENERATED ALWAYS AS ((`montant_du` - `montant_paye`)) STORED,
  `statut` enum('due','partiellement_payee','soldee') NOT NULL DEFAULT 'due',
  `date_creation` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_cotis_due`),
  KEY `idx_adht_cotis` (`id_adht`),
  KEY `idx_statut_cotis` (`statut`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `comitemaore_cotisation_due`
--

LOCK TABLES `comitemaore_cotisation_due` WRITE;
/*!40000 ALTER TABLE `comitemaore_cotisation_due` DISABLE KEYS */;
/*!40000 ALTER TABLE `comitemaore_cotisation_due` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `comitemaore_cv`
--

DROP TABLE IF EXISTS `comitemaore_cv`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `comitemaore_cv` (
  `id_cv` int NOT NULL AUTO_INCREMENT,
  `id_adht` int NOT NULL,
  `type_entree` enum('etude','diplome','experience','note') NOT NULL DEFAULT 'etude',
  `intitule` varchar(300) NOT NULL DEFAULT '' COMMENT 'Intitule etude diplome poste',
  `etablissement` varchar(200) DEFAULT NULL,
  `periode_debut` varchar(10) DEFAULT NULL COMMENT 'Annee ou date debut ex 2015',
  `periode_fin` varchar(10) DEFAULT NULL COMMENT 'Annee ou date fin ex 2018',
  `mention` varchar(100) DEFAULT NULL COMMENT 'Mention grade',
  `notes_particulieres` text,
  `ordre_affichage` int DEFAULT '0',
  `date_creation` datetime DEFAULT CURRENT_TIMESTAMP,
  `date_modification` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_cv`),
  KEY `idx_adht_cv` (`id_adht`),
  KEY `idx_type_cv` (`type_entree`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `comitemaore_cv`
--

LOCK TABLES `comitemaore_cv` WRITE;
/*!40000 ALTER TABLE `comitemaore_cv` DISABLE KEYS */;
/*!40000 ALTER TABLE `comitemaore_cv` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `comitemaore_document`
--

DROP TABLE IF EXISTS `comitemaore_document`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `comitemaore_document` (
  `id_doc` int NOT NULL AUTO_INCREMENT,
  `id_adht` int NOT NULL,
  `titre_doc` varchar(200) NOT NULL DEFAULT '',
  `type_doc` varchar(50) DEFAULT NULL COMMENT 'pièce identité, diplôme, contrat...',
  `nom_fichier` varchar(255) NOT NULL DEFAULT '' COMMENT 'Nom original',
  `chemin_fichier` varchar(500) NOT NULL DEFAULT '' COMMENT 'Chemin relatif sur le serveur',
  `mime_type` varchar(100) DEFAULT NULL,
  `taille_fichier` int DEFAULT NULL COMMENT 'Taille en octets',
  `description` text,
  `date_upload` datetime DEFAULT CURRENT_TIMESTAMP,
  `uploade_par` int DEFAULT NULL,
  PRIMARY KEY (`id_doc`),
  KEY `idx_adht_doc` (`id_adht`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `comitemaore_document`
--

LOCK TABLES `comitemaore_document` WRITE;
/*!40000 ALTER TABLE `comitemaore_document` DISABLE KEYS */;
/*!40000 ALTER TABLE `comitemaore_document` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `comitemaore_federations`
--

DROP TABLE IF EXISTS `comitemaore_federations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `comitemaore_federations` (
  `id_federation` int NOT NULL,
  `federation` varchar(8) DEFAULT NULL,
  `nom_complet` varchar(200) DEFAULT NULL COMMENT 'Nom complet de la fédération',
  `president` int DEFAULT NULL COMMENT 'id_adht du Président',
  `vice_president` int DEFAULT NULL COMMENT 'id_adht du Vice-président',
  `secretaire` int DEFAULT NULL COMMENT 'id_adht du Secrétaire',
  `tresorier` int DEFAULT NULL COMMENT 'id_adht du Trésorier',
  `secretaire_adjoint` int DEFAULT NULL COMMENT 'id_adht du Secrétaire adjoint',
  `tresorier_adjoint` int DEFAULT NULL COMMENT 'id_adht du Trésorier adjoint',
  `administrateur1` int DEFAULT NULL COMMENT 'id_adht Administrateur 1',
  `administrateur2` int DEFAULT NULL COMMENT 'id_adht Administrateur 2',
  `administrateur3` int DEFAULT NULL COMMENT 'id_adht Administrateur 3',
  `date_modification` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `modifie_par` varchar(50) DEFAULT NULL COMMENT 'login de l admin ayant modifié',
  PRIMARY KEY (`id_federation`),
  UNIQUE KEY `id_federation_UNIQUE` (`id_federation`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `comitemaore_federations`
--

LOCK TABLES `comitemaore_federations` WRITE;
/*!40000 ALTER TABLE `comitemaore_federations` DISABLE KEYS */;
INSERT INTO `comitemaore_federations` VALUES (70,'Maore',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-04-10 15:57:28',NULL),(71,'Ndzuwani',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-04-10 15:57:28',NULL),(72,'Mwali',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-04-10 15:57:28',NULL),(73,'Ngazidja',NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-04-10 15:57:28',NULL);
/*!40000 ALTER TABLE `comitemaore_federations` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `comitemaore_log`
--

DROP TABLE IF EXISTS `comitemaore_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `comitemaore_log` (
  `id_log` int NOT NULL AUTO_INCREMENT,
  `id_adht` int DEFAULT NULL COMMENT 'Utilisateur connecté',
  `login_adht` varchar(33) DEFAULT NULL,
  `ip_adresse` varchar(45) DEFAULT NULL,
  `action` varchar(100) NOT NULL DEFAULT '' COMMENT 'Type d action',
  `module` varchar(50) DEFAULT NULL COMMENT 'adherent, finance, document, cv...',
  `id_cible` int DEFAULT NULL COMMENT 'ID de l enregistrement concerné',
  `detail` text COMMENT 'Détail JSON de l opération',
  `resultat` enum('succes','echec','info') NOT NULL DEFAULT 'info',
  `date_action` datetime DEFAULT CURRENT_TIMESTAMP,
  `session_id` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id_log`),
  KEY `idx_adht_log` (`id_adht`),
  KEY `idx_date` (`date_action`),
  KEY `idx_action` (`action`)
) ENGINE=InnoDB AUTO_INCREMENT=132 DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `comitemaore_log`
--

LOCK TABLES `comitemaore_log` WRITE;
/*!40000 ALTER TABLE `comitemaore_log` DISABLE KEYS */;
INSERT INTO `comitemaore_log` VALUES (1,NULL,NULL,'192.168.1.44','list_adherents','adherent',NULL,'{\"search\":\"\"}','info','2026-04-08 22:07:25','f76550ae1b563352a88112eab7ccbefa'),(2,NULL,NULL,'192.168.1.44','list_adherents','adherent',NULL,'{\"search\":\"\"}','info','2026-04-08 22:13:54','f76550ae1b563352a88112eab7ccbefa'),(3,NULL,NULL,'::1','list_adherents','adherent',NULL,'{\"search\":\"\"}','info','2026-04-08 22:22:22','03e11935a26026e6c270aaf263939854'),(4,NULL,NULL,'::1','list_adherents','adherent',NULL,'{\"search\":\"\"}','info','2026-04-08 22:28:01','03e11935a26026e6c270aaf263939854'),(5,NULL,NULL,'::1','list_adherents','adherent',NULL,'{\"search\":\"\"}','info','2026-04-08 22:38:00','03e11935a26026e6c270aaf263939854'),(6,NULL,NULL,'::1','list_adherents','adherent',NULL,'{\"search\":\"\"}','info','2026-04-08 22:38:29','03e11935a26026e6c270aaf263939854'),(7,NULL,NULL,'::1','list_adherents','adherent',NULL,'{\"search\":\"\"}','info','2026-04-08 22:38:54','46b037bc5ea1adae089d4f4c9c9d1400'),(8,NULL,NULL,'::1','list_adherents','adherent',NULL,'{\"search\":\"\"}','info','2026-04-08 22:39:00','46b037bc5ea1adae089d4f4c9c9d1400'),(9,NULL,NULL,'::1','list_adherents','adherent',NULL,'{\"search\":\"\"}','info','2026-04-08 22:46:34','46b037bc5ea1adae089d4f4c9c9d1400'),(10,NULL,NULL,'::1','list_adherents','adherent',NULL,'{\"search\":\"\"}','info','2026-04-08 22:46:36','46b037bc5ea1adae089d4f4c9c9d1400'),(11,NULL,NULL,'::1','list_adherents','adherent',NULL,'{\"search\":\"\"}','info','2026-04-08 22:47:06','46b037bc5ea1adae089d4f4c9c9d1400'),(12,NULL,NULL,'::1','list_adherents','adherent',NULL,'{\"search\":\"\"}','info','2026-04-08 22:47:11','46b037bc5ea1adae089d4f4c9c9d1400'),(13,NULL,NULL,'::1','list_adherents','adherent',NULL,'{\"search\":\"\"}','info','2026-04-08 22:47:15','46b037bc5ea1adae089d4f4c9c9d1400'),(14,NULL,NULL,'::1','list_adherents','adherent',NULL,'{\"search\":\"\"}','info','2026-04-08 22:47:55','46b037bc5ea1adae089d4f4c9c9d1400'),(15,NULL,NULL,'::1','list_adherents','adherent',NULL,'{\"search\":\"\"}','info','2026-04-08 22:48:01','46b037bc5ea1adae089d4f4c9c9d1400'),(16,NULL,NULL,'::1','list_adherents','adherent',NULL,'{\"search\":\"\"}','info','2026-04-08 22:48:11','46b037bc5ea1adae089d4f4c9c9d1400'),(17,NULL,NULL,'::1','list_adherents','adherent',NULL,'{\"search\":\"\"}','info','2026-04-08 22:51:21','cbf6badac6d42820c5220920d0c5c444'),(18,NULL,NULL,'::1','list_adherents','adherent',NULL,'{\"search\":\"\"}','info','2026-04-08 22:52:07','cbf6badac6d42820c5220920d0c5c444'),(19,NULL,NULL,'::1','list_adherents','adherent',NULL,'{\"search\":\"\"}','info','2026-04-08 22:52:12','cbf6badac6d42820c5220920d0c5c444'),(20,NULL,NULL,'::1','list_adherents','adherent',NULL,'{\"search\":\"\"}','info','2026-04-08 23:15:38','b2baa72c64beacd76006035827af5657'),(21,NULL,NULL,'::1','list_adherents','adherent',NULL,'{\"search\":\"\"}','info','2026-04-08 23:23:33','2d7508418e57dde61cd3c8e524bc6493'),(22,NULL,NULL,'::1','list_adherents','adherent',NULL,'{\"search\":\"\"}','info','2026-04-08 23:23:54','8213531431f2ad2945c8286811b6d561'),(23,NULL,NULL,'192.168.1.44','list_adherents','adherent',NULL,'{\"search\":\"\"}','info','2026-04-09 06:50:53','c07591e8397ef0a210e70a9863abbb09'),(24,NULL,NULL,'192.168.1.44','list_adherents','adherent',NULL,'{\"search\":\"\"}','info','2026-04-09 07:15:26','382b8f1362037f20d102f5f9e6d2a30b'),(25,NULL,NULL,'192.168.1.44','list_adherents','adherent',NULL,'{\"search\":\"\"}','info','2026-04-09 07:15:29','382b8f1362037f20d102f5f9e6d2a30b'),(26,NULL,NULL,'192.168.1.44','list_adherents','adherent',NULL,'{\"search\":\"\"}','info','2026-04-09 07:25:22','91437582f697ae82898c7ba8bed87a18'),(27,NULL,NULL,'192.168.1.44','list_adherents','adherent',NULL,'{\"search\":\"\"}','info','2026-04-09 09:04:27','77ee85e70169a749170fc644fd351240'),(28,NULL,NULL,'192.168.1.44','list_adherents','adherent',NULL,'{\"search\":\"\"}','info','2026-04-09 09:04:50','77ee85e70169a749170fc644fd351240'),(29,NULL,NULL,'192.168.1.44','list_adherents','adherent',NULL,'{\"search\":\"\"}','info','2026-04-09 09:04:56','77ee85e70169a749170fc644fd351240'),(30,NULL,NULL,'192.168.1.44','list_adherents','adherent',NULL,'{\"search\":\"\"}','info','2026-04-09 09:05:13','27dc6a3d0e5910372bc88dae9fe191c8'),(31,NULL,NULL,'192.168.1.44','list_adherents','adherent',NULL,'{\"search\":\"\"}','info','2026-04-09 09:05:16','27dc6a3d0e5910372bc88dae9fe191c8'),(32,NULL,NULL,'192.168.1.44','list_adherents','adherent',NULL,'{\"search\":\"\"}','info','2026-04-09 09:05:40','27dc6a3d0e5910372bc88dae9fe191c8'),(33,NULL,NULL,'192.168.1.44','list_adherents','adherent',NULL,'{\"search\":\"\"}','info','2026-04-09 09:05:44','27dc6a3d0e5910372bc88dae9fe191c8'),(34,NULL,NULL,'192.168.1.44','list_adherents','adherent',NULL,'{\"search\":\"\"}','info','2026-04-09 09:06:44','9f7d98b773f1b50b727fcda016614ec5'),(35,NULL,NULL,'192.168.1.44','list_adherents','adherent',NULL,'{\"search\":\"\"}','info','2026-04-09 09:07:05','f266d88f6f88cb325c580c211cef62ff'),(36,NULL,NULL,'192.168.1.44','list_adherents','adherent',NULL,'{\"search\":\"\"}','info','2026-04-09 09:19:14','89975cf296081f3bcc1ae12d68ba528e'),(37,NULL,NULL,'192.168.1.44','list_adherents','adherent',NULL,'{\"search\":\"\"}','info','2026-04-09 09:34:44','89975cf296081f3bcc1ae12d68ba528e'),(38,NULL,NULL,'192.168.1.44','list_adherents','adherent',NULL,'{\"search\":\"\"}','info','2026-04-09 12:15:05','91b6b3a2e2a74533c41ed6b87c38772b'),(39,NULL,NULL,'192.168.1.44','list_adherents','adherent',NULL,'{\"search\":\"\"}','info','2026-04-09 12:15:08','91b6b3a2e2a74533c41ed6b87c38772b'),(40,NULL,NULL,'192.168.1.44','list_adherents','adherent',NULL,'{\"search\":\"\"}','info','2026-04-09 12:15:10','91b6b3a2e2a74533c41ed6b87c38772b'),(41,NULL,NULL,'192.168.1.44','list_adherents','adherent',NULL,'{\"search\":\"\"}','info','2026-04-09 12:16:49','807ab2c403fcaa00d1d68524e4ec94b1'),(42,NULL,NULL,'192.168.1.44','list_adherents','adherent',NULL,'{\"search\":\"\"}','info','2026-04-09 12:16:54','807ab2c403fcaa00d1d68524e4ec94b1'),(43,NULL,NULL,'192.168.1.44','list_adherents','adherent',NULL,'{\"search\":\"\"}','info','2026-04-09 12:17:13','38b76dc4322999538e96a1243efa7f7c'),(44,NULL,NULL,'192.168.1.44','list_adherents','adherent',NULL,'{\"search\":\"\"}','info','2026-04-09 12:17:55','3046f5882a754876c858bbc08788f6e5'),(45,NULL,NULL,'192.168.1.44','list_adherents','adherent',NULL,'{\"search\":\"\"}','info','2026-04-09 12:17:57','3046f5882a754876c858bbc08788f6e5'),(46,NULL,NULL,'192.168.1.44','list_adherents','adherent',NULL,'{\"search\":\"\"}','info','2026-04-09 12:19:25','3046f5882a754876c858bbc08788f6e5'),(47,NULL,NULL,'192.168.1.44','list_adherents','adherent',NULL,'{\"search\":\"\"}','info','2026-04-09 12:19:43','3046f5882a754876c858bbc08788f6e5'),(48,NULL,NULL,'37.174.114.226','list_adherents','adherent',NULL,'{\"search\":\"\"}','info','2026-04-09 12:34:42','07d390d5f94733ef13a9dc2d261e0be0'),(49,NULL,NULL,'37.174.114.226','list_adherents','adherent',NULL,'{\"search\":\"\"}','info','2026-04-09 12:36:51','07d390d5f94733ef13a9dc2d261e0be0'),(50,NULL,NULL,'164.160.136.22','list_adherents','adherent',NULL,'{\"search\":\"\"}','info','2026-04-09 13:09:58','5436716e7e974eba64a6b7e8abe02371'),(51,NULL,NULL,'164.160.136.22','list_adherents','adherent',NULL,'{\"search\":\"\"}','info','2026-04-09 13:13:04','5436716e7e974eba64a6b7e8abe02371'),(52,NULL,'admin','192.168.1.44','backup','dbadmin',NULL,'{\"file\":\"backup_comitemaore_20260409_141253.sql\"}','succes','2026-04-09 14:12:53','894f3f80c8523434df91fa10ef2849ba'),(53,NULL,'admin','192.168.1.44','download_backup','dbadmin',NULL,'{\"file\":\"backup_comitemaore_20260409_141253.sql\"}','succes','2026-04-09 14:13:08','894f3f80c8523434df91fa10ef2849ba'),(54,NULL,'admin','192.168.1.44','download_backup','dbadmin',NULL,'{\"file\":\"backup_comitemaore_20260409_141253.sql\"}','succes','2026-04-09 14:18:53','894f3f80c8523434df91fa10ef2849ba'),(55,NULL,NULL,'192.168.1.44','list_adherents','adherent',NULL,'{\"search\":\"\"}','info','2026-04-09 14:54:00','fcdcdfa93e321c9080d08e7f8cf8d543'),(56,1,'jaffar','192.168.1.44','list_adherents','adherent',NULL,'{\"search\":\"\"}','info','2026-04-09 15:31:52','fcdcdfa93e321c9080d08e7f8cf8d543'),(57,1,'jaffar','192.168.1.44','view_adherent','adherent',1,NULL,'info','2026-04-09 15:32:12','fcdcdfa93e321c9080d08e7f8cf8d543'),(58,1,'jaffar','192.168.1.44','view_adherent','adherent',1,NULL,'info','2026-04-09 15:41:59','fcdcdfa93e321c9080d08e7f8cf8d543'),(59,1,'jaffar','192.168.1.44','view_adherent','adherent',1,NULL,'info','2026-04-09 15:45:54','fcdcdfa93e321c9080d08e7f8cf8d543'),(60,1,'jaffar','192.168.1.44','view_adherent','adherent',1,NULL,'info','2026-04-09 15:49:12','fcdcdfa93e321c9080d08e7f8cf8d543'),(61,1,'jaffar','192.168.1.44','list_adherents','adherent',NULL,'{\"search\":\"\"}','info','2026-04-09 15:49:34','fcdcdfa93e321c9080d08e7f8cf8d543'),(62,1,'jaffar','192.168.1.44','list_adherents','adherent',NULL,'{\"search\":\"\"}','info','2026-04-09 15:57:29','fcdcdfa93e321c9080d08e7f8cf8d543'),(63,1,'jaffar','192.168.1.44','view_adherent','adherent',1,NULL,'info','2026-04-09 15:57:31','fcdcdfa93e321c9080d08e7f8cf8d543'),(64,1,'jaffar','192.168.1.44','list_adherents','adherent',NULL,'{\"search\":\"\"}','info','2026-04-09 15:57:55','fcdcdfa93e321c9080d08e7f8cf8d543'),(65,1,'jaffar','192.168.1.44','list_adherents','adherent',NULL,'{\"search\":\"\"}','info','2026-04-09 15:58:44','fcdcdfa93e321c9080d08e7f8cf8d543'),(66,1,'jaffar','192.168.1.44','backup','dbadmin',NULL,'{\"file\":\"backup_comitemaore_20260409_160002.sql\"}','succes','2026-04-09 16:00:02','fcdcdfa93e321c9080d08e7f8cf8d543'),(67,1,'jaffar','192.168.1.44','download_backup','dbadmin',NULL,'{\"file\":\"backup_comitemaore_20260409_160002.sql\"}','succes','2026-04-09 16:00:18','fcdcdfa93e321c9080d08e7f8cf8d543'),(68,1,'jaffar','192.168.1.44','list_adherents','adherent',NULL,'{\"search\":\"\"}','info','2026-04-09 16:12:46','fcdcdfa93e321c9080d08e7f8cf8d543'),(69,1,'jaffar','192.168.1.44','list_adherents','adherent',NULL,'{\"search\":\"\"}','info','2026-04-09 16:17:54','fcdcdfa93e321c9080d08e7f8cf8d543'),(70,1,'jaffar','192.168.1.44','view_finance','finance',1,NULL,'info','2026-04-09 16:18:02','fcdcdfa93e321c9080d08e7f8cf8d543'),(71,1,'jaffar','192.168.1.44','list_adherents','adherent',NULL,'{\"search\":\"\"}','info','2026-04-09 16:26:50','fcdcdfa93e321c9080d08e7f8cf8d543'),(72,1,'jaffar','192.168.1.44','view_finance','finance',1,NULL,'info','2026-04-09 16:26:52','fcdcdfa93e321c9080d08e7f8cf8d543'),(73,NULL,NULL,'164.160.136.22','list_adherents','adherent',NULL,'{\"search\":\"\"}','info','2026-04-09 16:35:31','9953ea585194bfc420aac774489a3d71'),(74,1,'jaffar','192.168.1.44','view_finance','finance',1,NULL,'info','2026-04-09 16:43:31','fcdcdfa93e321c9080d08e7f8cf8d543'),(75,1,'jaffar','192.168.1.44','view_finance','finance',1,NULL,'info','2026-04-09 16:43:33','fcdcdfa93e321c9080d08e7f8cf8d543'),(76,1,'jaffar','192.168.1.44','list_adherents','adherent',NULL,'{\"search\":\"\"}','info','2026-04-09 16:48:48','fcdcdfa93e321c9080d08e7f8cf8d543'),(77,1,'jaffar','192.168.1.44','view_finance','finance',1,NULL,'info','2026-04-09 16:48:50','fcdcdfa93e321c9080d08e7f8cf8d543'),(78,1,'jaffar','192.168.1.44','list_adherents','adherent',NULL,'{\"search\":\"\"}','info','2026-04-09 17:08:53','fcdcdfa93e321c9080d08e7f8cf8d543'),(79,1,'jaffar','192.168.1.44','list_adherents','adherent',NULL,'{\"search\":\"\"}','info','2026-04-09 17:09:02','fcdcdfa93e321c9080d08e7f8cf8d543'),(80,1,'jaffar','192.168.1.44','list_adherents','adherent',NULL,'{\"search\":\"\"}','info','2026-04-09 17:13:23','fcdcdfa93e321c9080d08e7f8cf8d543'),(81,1,'jaffar','192.168.1.44','list_adherents','adherent',NULL,'{\"search\":\"\"}','info','2026-04-09 17:35:13','fcdcdfa93e321c9080d08e7f8cf8d543'),(82,1,'jaffar','192.168.1.44','view_finance','finance',1,NULL,'info','2026-04-09 17:47:06','fcdcdfa93e321c9080d08e7f8cf8d543'),(83,1,'jaffar','192.168.1.44','list_adherents','adherent',NULL,'{\"search\":\"\"}','info','2026-04-09 17:47:13','fcdcdfa93e321c9080d08e7f8cf8d543'),(84,1,'jaffar','192.168.1.44','list_adherents','adherent',NULL,'{\"search\":\"\"}','info','2026-04-09 17:47:30','fcdcdfa93e321c9080d08e7f8cf8d543'),(85,1,'jaffar','192.168.1.44','list_adherents','adherent',NULL,'{\"search\":\"\"}','info','2026-04-09 17:48:41','fcdcdfa93e321c9080d08e7f8cf8d543'),(86,NULL,NULL,'192.168.1.44','list_adherents','adherent',NULL,'{\"search\":\"\"}','info','2026-04-09 22:26:53','2bf04133079a5db6df87d4287b910df5'),(87,NULL,NULL,'192.168.1.44','view_adherent','adherent',1,NULL,'info','2026-04-09 22:26:57','2bf04133079a5db6df87d4287b910df5'),(88,NULL,NULL,'192.168.1.44','list_adherents','adherent',NULL,'{\"search\":\"\"}','info','2026-04-09 22:27:02','2bf04133079a5db6df87d4287b910df5'),(89,NULL,NULL,'192.168.1.44','view_finance','finance',1,NULL,'info','2026-04-09 22:27:54','2bf04133079a5db6df87d4287b910df5'),(90,NULL,NULL,'192.168.1.44','view_finance','finance',1,NULL,'info','2026-04-09 22:27:56','2bf04133079a5db6df87d4287b910df5'),(91,NULL,NULL,'192.168.1.44','view_finance','finance',1,NULL,'info','2026-04-09 22:29:49','2bf04133079a5db6df87d4287b910df5'),(92,NULL,NULL,'192.168.1.44','view_finance','finance',1,NULL,'info','2026-04-09 22:29:51','2bf04133079a5db6df87d4287b910df5'),(93,NULL,NULL,'192.168.1.44','list_adherents','adherent',NULL,'{\"search\":\"\"}','info','2026-04-09 22:36:37','2bf04133079a5db6df87d4287b910df5'),(94,NULL,NULL,'192.168.1.44','list_documents','document',1,NULL,'info','2026-04-09 22:36:54','2bf04133079a5db6df87d4287b910df5'),(95,NULL,NULL,'192.168.1.44','list_documents','document',1,NULL,'info','2026-04-09 22:42:34','2bf04133079a5db6df87d4287b910df5'),(96,NULL,NULL,'192.168.1.44','list_adherents','adherent',NULL,'{\"search\":\"\"}','info','2026-04-09 22:43:00','2bf04133079a5db6df87d4287b910df5'),(97,NULL,NULL,'192.168.1.44','view_cv','cv',1,NULL,'info','2026-04-09 22:43:04','2bf04133079a5db6df87d4287b910df5'),(98,NULL,NULL,'192.168.1.44','view_cv','cv',1,NULL,'info','2026-04-09 22:47:17','2bf04133079a5db6df87d4287b910df5'),(99,NULL,NULL,'192.168.1.44','list_adherents','adherent',NULL,'{\"search\":\"\"}','info','2026-04-09 22:47:50','2bf04133079a5db6df87d4287b910df5'),(100,NULL,NULL,'192.168.1.44','view_cv','cv',1,NULL,'info','2026-04-09 22:47:56','2bf04133079a5db6df87d4287b910df5'),(101,NULL,NULL,'192.168.1.44','list_adherents','adherent',NULL,'{\"search\":\"\"}','info','2026-04-09 22:48:33','2bf04133079a5db6df87d4287b910df5'),(102,NULL,NULL,'192.168.1.44','list_adherents','adherent',NULL,'{\"search\":\"\"}','info','2026-04-09 22:49:10','2bf04133079a5db6df87d4287b910df5'),(103,NULL,NULL,'192.168.1.44','view_adherent','adherent',1,NULL,'info','2026-04-09 22:49:13','2bf04133079a5db6df87d4287b910df5'),(104,NULL,NULL,'192.168.1.44','list_adherents','adherent',NULL,'{\"search\":\"\"}','info','2026-04-09 22:49:15','2bf04133079a5db6df87d4287b910df5'),(105,NULL,NULL,'192.168.1.44','list_adherents','adherent',NULL,'{\"search\":\"\"}','info','2026-04-09 22:49:39','2bf04133079a5db6df87d4287b910df5'),(106,NULL,NULL,'192.168.1.44','list_adherents','adherent',NULL,'{\"search\":\"\"}','info','2026-04-09 22:54:21','2bf04133079a5db6df87d4287b910df5'),(107,NULL,NULL,'192.168.1.44','list_adherents','adherent',NULL,'{\"search\":\"\"}','info','2026-04-09 22:55:41','2bf04133079a5db6df87d4287b910df5'),(108,NULL,NULL,'192.168.1.44','list_adherents','adherent',NULL,'{\"search\":\"\"}','info','2026-04-09 22:56:58','2bf04133079a5db6df87d4287b910df5'),(109,NULL,NULL,'192.168.1.44','list_adherents','adherent',NULL,'{\"search\":\"\"}','info','2026-04-09 23:07:18','2bf04133079a5db6df87d4287b910df5'),(110,NULL,'admin','192.168.1.44','list_adherents','adherent',NULL,'{\"search\":\"\"}','info','2026-04-10 14:19:09','2ca147616e24675ac3b73af85cf8622f'),(111,NULL,'admin','192.168.1.44','list_adherents','adherent',NULL,'{\"search\":\"\"}','info','2026-04-10 14:19:14','2ca147616e24675ac3b73af85cf8622f'),(112,NULL,'admin','192.168.1.44','view_adherent','adherent',1,NULL,'info','2026-04-10 14:19:16','2ca147616e24675ac3b73af85cf8622f'),(113,NULL,'admin','192.168.1.44','list_adherents','adherent',NULL,'{\"search\":\"\"}','info','2026-04-10 14:19:18','2ca147616e24675ac3b73af85cf8622f'),(114,NULL,'admin','192.168.1.44','list_adherents','adherent',NULL,'{\"search\":\"\"}','info','2026-04-10 14:19:23','2ca147616e24675ac3b73af85cf8622f'),(115,NULL,NULL,'192.168.1.44','view_finance','finance',1,NULL,'info','2026-04-10 14:19:31','2ca147616e24675ac3b73af85cf8622f'),(116,NULL,'admin','192.168.1.44','list_adherents','adherent',NULL,'{\"search\":\"\"}','info','2026-04-10 14:19:34','2ca147616e24675ac3b73af85cf8622f'),(117,NULL,NULL,'192.168.1.44','list_documents','document',1,NULL,'info','2026-04-10 14:19:37','2ca147616e24675ac3b73af85cf8622f'),(118,NULL,'admin','192.168.1.44','list_adherents','adherent',NULL,'{\"search\":\"\"}','info','2026-04-10 14:19:39','2ca147616e24675ac3b73af85cf8622f'),(119,NULL,NULL,'192.168.1.44','view_cv','cv',1,NULL,'info','2026-04-10 14:19:43','2ca147616e24675ac3b73af85cf8622f'),(120,NULL,'admin','192.168.1.44','list_adherents','adherent',NULL,'{\"search\":\"\"}','info','2026-04-10 14:19:45','2ca147616e24675ac3b73af85cf8622f'),(121,NULL,'admin','192.168.1.44','list_adherents','adherent',NULL,'{\"search\":\"\"}','info','2026-04-10 14:19:58','2ca147616e24675ac3b73af85cf8622f'),(122,NULL,'admin','192.168.1.44','demande_ajout_adherent','adherent',NULL,'{\"id_approbation\":1}','info','2026-04-10 14:21:15','2ca147616e24675ac3b73af85cf8622f'),(123,NULL,'admin','192.168.1.44','list_adherents','adherent',NULL,'{\"search\":\"\"}','info','2026-04-10 14:22:53','2ca147616e24675ac3b73af85cf8622f'),(124,NULL,'root','176.173.60.208','list_adherents','adherent',NULL,'{\"search\":\"\"}','info','2026-04-10 15:56:10','f2dd296b4ba405625335196c867af106'),(125,NULL,'root','176.173.60.208','list_adherents','adherent',NULL,'{\"search\":\"\"}','info','2026-04-10 15:56:44','f2dd296b4ba405625335196c867af106'),(126,NULL,'root','176.173.60.208','list_adherents','adherent',NULL,'{\"search\":\"\"}','info','2026-04-10 15:57:12','f2dd296b4ba405625335196c867af106'),(127,NULL,'admin','192.168.1.44','list_adherents','adherent',NULL,'{\"search\":\"\"}','info','2026-04-10 16:34:12','fbac60f80d8ed0ffbe98604c084cc64e'),(128,NULL,'root','192.168.1.44','list_adherents','adherent',NULL,'{\"search\":\"\"}','info','2026-04-10 16:34:34','66b6d912ced1d8748a306e076b84c255'),(129,NULL,'root','192.168.1.44','list_adherents','adherent',NULL,'{\"search\":\"\"}','info','2026-04-10 16:35:14','66b6d912ced1d8748a306e076b84c255'),(130,NULL,'root','192.168.1.44','list_adherents','adherent',NULL,'{\"search\":\"\"}','info','2026-04-10 16:35:43','66b6d912ced1d8748a306e076b84c255'),(131,1,'jaffar','192.168.1.44','list_adherents','adherent',NULL,'{\"search\":\"\"}','info','2026-04-10 16:36:08','7e6bb76917ad7080aac7f2dcf4310f4b');
/*!40000 ALTER TABLE `comitemaore_log` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `comitemaore_section`
--

DROP TABLE IF EXISTS `comitemaore_section`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `comitemaore_section` (
  `id_section` int NOT NULL AUTO_INCREMENT,
  `code_section` varchar(10) NOT NULL DEFAULT '',
  `nom_section` varchar(100) NOT NULL DEFAULT '',
  `ville_section` varchar(50) DEFAULT NULL,
  `pays_section` varchar(50) DEFAULT 'France',
  `id_federation` int DEFAULT NULL,
  `actif` tinyint(1) NOT NULL DEFAULT '1',
  `date_creation` date DEFAULT NULL,
  PRIMARY KEY (`id_section`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `comitemaore_section`
--

LOCK TABLES `comitemaore_section` WRITE;
/*!40000 ALTER TABLE `comitemaore_section` DISABLE KEYS */;
/*!40000 ALTER TABLE `comitemaore_section` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `comitemaore_sections`
--

DROP TABLE IF EXISTS `comitemaore_sections`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `comitemaore_sections` (
  `id_section` int NOT NULL,
  `federation` varchar(255) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL,
  `section` varchar(44) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci DEFAULT NULL,
  `id_federation` int NOT NULL,
  `president` int DEFAULT NULL COMMENT 'id_adht du Président',
  `vice_president` int DEFAULT NULL COMMENT 'id_adht du Vice-président',
  `secretaire` int DEFAULT NULL COMMENT 'id_adht du Secrétaire',
  `tresorier` int DEFAULT NULL COMMENT 'id_adht du Trésorier',
  `secretaire_adjoint` int DEFAULT NULL COMMENT 'id_adht du Secrétaire adjoint',
  `tresorier_adjoint` int DEFAULT NULL COMMENT 'id_adht du Trésorier adjoint',
  `administrateur1` int DEFAULT NULL COMMENT 'id_adht Administrateur 1',
  `administrateur2` int DEFAULT NULL COMMENT 'id_adht Administrateur 2',
  `administrateur3` int DEFAULT NULL COMMENT 'id_adht Administrateur 3',
  `date_modification` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `modifie_par` varchar(50) DEFAULT NULL COMMENT 'login de l admin ayant modifié',
  PRIMARY KEY (`id_section`),
  UNIQUE KEY `id_section_UNIQUE` (`id_section`),
  KEY `id_federation` (`id_federation`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `comitemaore_sections`
--

LOCK TABLES `comitemaore_sections` WRITE;
/*!40000 ALTER TABLE `comitemaore_sections` DISABLE KEYS */;
INSERT INTO `comitemaore_sections` VALUES (1,'Mwali','FOMBONI',72,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-04-10 15:57:28',NULL),(2,'Mwali','MOILI_MDJINI',72,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-04-10 15:57:28',NULL),(3,'Mwali','MOIMBASSA',72,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-04-10 15:57:28',NULL),(4,'Mwali','MOIMBAO',72,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-04-10 15:57:28',NULL),(5,'Mwali','MLEDJELE',72,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-04-10 15:57:28',NULL),(6,'Mwali','DJANDO',72,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-04-10 15:57:28',NULL),(7,'Ndzuwani','MUTSAMUDU',71,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-04-10 15:57:28',NULL),(8,'Ndzuwani','BANDRANI_YA_CHIRONKAMBA',71,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-04-10 15:57:28',NULL),(9,'Ndzuwani','BANDRANI_YA_MTSAGANI',71,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-04-10 15:57:28',NULL),(10,'Ndzuwani','WANI',71,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-04-10 15:57:28',NULL),(11,'Ndzuwani','BAZIMINI',71,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-04-10 15:57:28',NULL),(12,'Ndzuwani','BAMBAO_MTROUNI',71,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-04-10 15:57:28',NULL),(13,'Ndzuwani','SIMA',71,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-04-10 15:57:28',NULL),(14,'Ndzuwani','VOUANI',71,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-04-10 15:57:28',NULL),(15,'Ndzuwani','MOYA',71,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-04-10 15:57:28',NULL),(16,'Ndzuwani','DJILIME',71,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-04-10 15:57:28',NULL),(17,'Ndzuwani','BAMBAO',71,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-04-10 15:57:28',NULL),(20,'Ndzuwani','KONI',71,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-04-10 15:57:28',NULL),(21,'Ndzuwani','NGANDZALE',71,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-04-10 15:57:28',NULL),(22,'Ndzuwani','DOMONI',71,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-04-10 15:57:28',NULL),(23,'Ndzuwani','MRAMANI',71,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-04-10 15:57:28',NULL),(24,'Ndzuwani','SHAWENI',71,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-04-10 15:57:28',NULL),(25,'Ndzuwani','ONGOJOU',71,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-04-10 15:57:28',NULL),(26,'Ndzuwani','MREMANI',71,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-04-10 15:57:28',NULL),(27,'Ndzuwani','ADDA',71,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-04-10 15:57:28',NULL),(28,'Ngazidja','MORONI',73,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-04-10 15:57:28',NULL),(29,'Ngazidja','BAMBAO_YA_MBOINI',73,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-04-10 15:57:28',NULL),(30,'Ngazidja','BAMBAO_YA_HARI',73,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-04-10 15:57:28',NULL),(31,'Ngazidja','BAMBAO_YA_DJOU',73,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-04-10 15:57:28',NULL),(32,'Ngazidja','TSINIMOIPANGA',73,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-04-10 15:57:28',NULL),(33,'Ngazidja','DJOUMOIPANGA',73,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-04-10 15:57:28',NULL),(34,'Ngazidja','NGOUENGOE',73,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-04-10 15:57:28',NULL),(35,'Ngazidja','NIOUMANGAMA',73,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-04-10 15:57:28',NULL),(37,'Ngazidja','ITSAHIDI',73,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-04-10 15:57:28',NULL),(38,'Ngazidja','DOMBA',73,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-04-10 15:57:28',NULL),(40,'Ngazidja','PIMBA',73,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-04-10 15:57:28',NULL),(43,'Ngazidja','NYUMA_MSIRU',73,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-04-10 15:57:28',NULL),(44,'Ngazidja','NYUMA_MRO',73,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-04-10 15:57:28',NULL),(45,'Ngazidja','MBOINKOU',73,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-04-10 15:57:28',NULL),(46,'Ngazidja','MITSAMIOULI',73,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-04-10 15:57:28',NULL),(47,'Ngazidja','CEMBENOI_SADA_DJ',73,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-04-10 15:57:28',NULL),(48,'Ngazidja','CEMBENOI_LAC_SALE',73,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-04-10 15:57:28',NULL),(50,'Ngazidja','NYUMA_KOMA',73,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-04-10 15:57:28',NULL),(51,'Ngazidja','NYUMAMRO_KIBLAN',73,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-04-10 15:57:28',NULL),(52,'Ngazidja','NYUMAMRO_SOUHE',73,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-04-10 15:57:28',NULL),(53,'Ngazidja','HAMANVOU',73,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-04-10 15:57:28',NULL),(54,'Ngazidja','ISAHARI',73,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-04-10 15:57:28',NULL),(55,'Ngazidja','DIMANI',73,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-04-10 15:57:28',NULL),(56,'Ngazidja','OICHILI_YA_DJOU',73,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-04-10 15:57:28',NULL),(57,'Ngazidja','OICHILI_YA_MBOINI',73,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,NULL,'2026-04-10 15:57:28',NULL);
/*!40000 ALTER TABLE `comitemaore_sections` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `finance_adh`
--

DROP TABLE IF EXISTS `finance_adh`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `finance_adh` (
  `id_fin_adh` int NOT NULL AUTO_INCREMENT,
  `id_adht` int NOT NULL COMMENT 'FK → comitemaore_adherent',
  `id_section` int DEFAULT NULL,
  `type_operation` enum('renouvellement_carte','ristourne','cotisation','cotisation_due','don','note') NOT NULL,
  `annee_cotisation` year DEFAULT NULL COMMENT 'Année concernée (cotisation)',
  `montant` decimal(10,2) DEFAULT '0.00',
  `montant_burnatexec` decimal(10,2) DEFAULT '0.00' COMMENT '50% → BNE',
  `montant_fedcorresp` decimal(10,2) DEFAULT '0.00' COMMENT '30% → Fédération',
  `montant_sectcorresp` decimal(10,2) DEFAULT '0.00' COMMENT '20% → Section',
  `date_operation` date NOT NULL DEFAULT (curdate()),
  `date_renouvellement` date DEFAULT NULL COMMENT 'Date renouvellement carte',
  `nature_don` varchar(100) DEFAULT NULL COMMENT 'Nature du don',
  `note` text COMMENT 'Note libre',
  `cotis_due_payee` tinyint(1) DEFAULT '0' COMMENT '1 si cotisation due réglée',
  `id_fin_due_regle` int DEFAULT NULL COMMENT 'FK → finance_adh (cotisation due réglée)',
  `enregistre_par` int DEFAULT NULL COMMENT 'FK → comitemaore_adherent (qui a saisi)',
  `date_enregistrement` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_fin_adh`),
  KEY `idx_adht` (`id_adht`),
  KEY `idx_section` (`id_section`),
  KEY `idx_type` (`type_operation`),
  KEY `idx_annee` (`annee_cotisation`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `finance_adh`
--

LOCK TABLES `finance_adh` WRITE;
/*!40000 ALTER TABLE `finance_adh` DISABLE KEYS */;
/*!40000 ALTER TABLE `finance_adh` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tab_finance`
--

DROP TABLE IF EXISTS `tab_finance`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tab_finance` (
  `id_finance` int NOT NULL AUTO_INCREMENT,
  `id_section` int DEFAULT NULL COMMENT 'Section concernée',
  `id_federation` int DEFAULT NULL COMMENT 'Fédération concernée',
  `annee_finance` year NOT NULL DEFAULT (year(curdate())),
  `fin_burnatexec` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '50% cotisation → Bureau National Exécutif',
  `fin_fedcorresp` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '30% cotisation → Fédération adherent',
  `fin_sectcorresp` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '20% cotisation → Section adherent',
  `fin_total_dons` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT 'Total des dons reçus',
  `fin_total_cotis` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT 'Total cotisations encaissées',
  `fin_cotis_dues` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT 'Cotisations dues restantes',
  `fin_ristourne` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT 'Total ristournes professionnelles',
  `date_maj` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_finance`),
  UNIQUE KEY `uq_section_annee` (`id_section`,`annee_finance`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tab_finance`
--

LOCK TABLES `tab_finance` WRITE;
/*!40000 ALTER TABLE `tab_finance` DISABLE KEYS */;
/*!40000 ALTER TABLE `tab_finance` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-04-10 18:48:51
