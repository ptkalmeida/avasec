-- ROLLBACK: tabela aditiva, remoção não afeta nenhum dado pré-existente.
ALTER TABLE `StoredFile` DROP FOREIGN KEY `StoredFile_ownerUserId_fkey`;
DROP TABLE `StoredFile`;
