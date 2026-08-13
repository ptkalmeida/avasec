-- Executado automaticamente pelo MySQL na PRIMEIRA inicialização do volume.
-- O usuário `avasec` recebe privilégios APENAS no schema da aplicação (avasec.*).
-- O GRANT global (*.* WITH GRANT OPTION) existia para o "shadow database" do
-- `prisma migrate dev`; o Prisma foi extinto (ADR 06), então a permissão ampla
-- (FILE/SUPER/GRANT em todo o servidor) não é mais necessária e foi removida —
-- reduz o raio de dano de um vazamento da credencial de dev.
GRANT ALL PRIVILEGES ON `avasec`.* TO 'avasec'@'%';
FLUSH PRIVILEGES;
