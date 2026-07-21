-- Executado automaticamente pelo MySQL na PRIMEIRA inicialização do volume.
-- Dá ao usuário `avasec` permissão global, necessária para o Prisma Migrate
-- criar/derrubar o "shadow database" durante `prisma migrate dev`.
GRANT ALL PRIVILEGES ON *.* TO 'avasec'@'%' WITH GRANT OPTION;
FLUSH PRIVILEGES;
