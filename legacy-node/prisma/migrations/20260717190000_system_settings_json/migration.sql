-- Converte SystemSettings de colunas fixas para armazenamento JSON flexível.
-- A linha existente é apenas dado de seed de desenvolvimento; o seed recria o singleton.
DELETE FROM `SystemSettings`;

ALTER TABLE `SystemSettings`
    DROP COLUMN `siteName`,
    DROP COLUMN `maintenanceMode`,
    DROP COLUMN `allowNewRegistrations`,
    DROP COLUMN `defaultLanguage`,
    DROP COLUMN `sessionTimeout`,
    DROP COLUMN `requireMfa`,
    DROP COLUMN `mfaType`,
    DROP COLUMN `maxLoginAttempts`,
    ADD COLUMN `data` JSON NOT NULL;
