-- ================================================================
-- ORACLE — Schéma MySQL (remplace GitHub-comme-base-de-données)
-- Charset utf8mb4 partout : noms béninois avec accents (SEDJRO,
-- HOUNGBLOHOUN...) doivent être stockés sans perte.
-- ================================================================

CREATE DATABASE IF NOT EXISTS oracle_db
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE oracle_db;

-- ---- Utilisateurs (agents + admins) ----
CREATE TABLE IF NOT EXISTS users (
    id            VARCHAR(40)  PRIMARY KEY,
    matricule     VARCHAR(40)  NOT NULL UNIQUE,
    email         VARCHAR(190) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    nom           VARCHAR(120) NOT NULL,
    prenoms       VARCHAR(160) NOT NULL,
    role          ENUM('agent','admin') NOT NULL DEFAULT 'agent',
    active        TINYINT(1)   NOT NULL DEFAULT 1,
    last_login    DATETIME(3)  NULL,
    login_count   INT UNSIGNED NOT NULL DEFAULT 0,
    created_at    DATETIME(3)  NOT NULL DEFAULT CURRENT_TIMESTAMP(3)
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- ---- Registre des immatriculations — LE cœur du système.
-- Index sur chassis et immatriculation : une recherche est une simple
-- requête indexée, instantanée quel que soit le volume (fini le
-- découpage en 256 fragments, MySQL gère ça nativement).
CREATE TABLE IF NOT EXISTS immatriculations (
    id             BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nom_prenom     VARCHAR(191) NOT NULL DEFAULT '',
    immatriculation VARCHAR(191) NOT NULL DEFAULT '',
    departement    VARCHAR(191) NOT NULL DEFAULT '',
    chassis        VARCHAR(191) NOT NULL DEFAULT '',
    statut         VARCHAR(40) NOT NULL DEFAULT 'OK',
    source         VARCHAR(40) NOT NULL DEFAULT 'import',
    created_at     DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    updated_at     DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3) ON UPDATE CURRENT_TIMESTAMP(3),
    INDEX idx_chassis (chassis),
    INDEX idx_immat (immatriculation),
    INDEX idx_nom_prenom (nom_prenom),
    INDEX idx_updated (updated_at, id)
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- ---- Historique des recherches (par agent) ----
CREATE TABLE IF NOT EXISTS search_history (
    id           VARCHAR(40)  PRIMARY KEY,
    user_id      VARCHAR(40)  NOT NULL,
    search_type  VARCHAR(20)  NOT NULL,
    search_key   VARCHAR(60)  NOT NULL DEFAULT '',
    vin          VARCHAR(60)  NOT NULL DEFAULT '',
    status       VARCHAR(20)  NOT NULL,
    owner        VARCHAR(255) NOT NULL DEFAULT '',
    plate        VARCHAR(40)  NOT NULL DEFAULT '',
    department   VARCHAR(120) NOT NULL DEFAULT '',
    message      TEXT,
    from_cache   TINYINT(1)   NOT NULL DEFAULT 0,
    latitude     DOUBLE       NULL,
    longitude    DOUBLE       NULL,
    accuracy     DOUBLE       NULL,
    samples      INT          NOT NULL DEFAULT 0,
    searched_at  DATETIME(3)  NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    INDEX idx_user (user_id),
    INDEX idx_searched_at (searched_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- ---- Logs techniques (observabilité) ----
CREATE TABLE IF NOT EXISTS tech_logs (
    id          VARCHAR(40)  PRIMARY KEY,
    user_id     VARCHAR(40)  NOT NULL,
    type        VARCHAR(120) NOT NULL,
    status      VARCHAR(20)  NOT NULL,
    message     TEXT,
    metadata    JSON         NULL,
    created_at  DATETIME(3)  NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    INDEX idx_user (user_id),
    INDEX idx_created_at (created_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- ---- Erreurs d'authentification (visibles par l'admin) ----
CREATE TABLE IF NOT EXISTS auth_errors (
    id         VARCHAR(40)  PRIMARY KEY,
    email      VARCHAR(190) NOT NULL,
    type       VARCHAR(60)  NOT NULL,
    message    TEXT,
    created_at DATETIME(3)  NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    INDEX idx_email (email),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
