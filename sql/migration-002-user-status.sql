-- À exécuter une seule fois si ta table users existe déjà sans ces
-- colonnes (créée avant cette mise à jour). Sans effet si elles
-- existent déjà (IF NOT EXISTS).
ALTER TABLE users
    ADD COLUMN IF NOT EXISTS active TINYINT(1) NOT NULL DEFAULT 1 AFTER role,
    ADD COLUMN IF NOT EXISTS last_login DATETIME(3) NULL AFTER active,
    ADD COLUMN IF NOT EXISTS login_count INT UNSIGNED NOT NULL DEFAULT 0 AFTER last_login;
