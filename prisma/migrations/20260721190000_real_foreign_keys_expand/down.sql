-- ROLLBACK da migration real_foreign_keys_expand.
-- Reversível sem perda: as colunas novas são aditivas; os campos *Name originais nunca foram
-- tocados. Executar este script devolve o banco exatamente ao estado anterior.
-- (O Prisma Migrate não executa down automaticamente — aplicar manualmente se necessário:
--  docker exec -i avasec-mysql mysql -uavasec -pavasec avasec < down.sql
--  e depois remover a linha correspondente de _prisma_migrations.)

ALTER TABLE `Course` DROP FOREIGN KEY `Course_instructorId_fkey`;
ALTER TABLE `StudentEnrollment` DROP FOREIGN KEY `StudentEnrollment_userId_fkey`;
ALTER TABLE `StudentProgress` DROP FOREIGN KEY `StudentProgress_userId_fkey`;
ALTER TABLE `StudentProgress` DROP FOREIGN KEY `StudentProgress_enrollmentId_fkey`;
ALTER TABLE `Certificate` DROP FOREIGN KEY `Certificate_userId_fkey`;
ALTER TABLE `Certificate` DROP FOREIGN KEY `Certificate_enrollmentId_fkey`;
ALTER TABLE `QuizSubmission` DROP FOREIGN KEY `QuizSubmission_userId_fkey`;
ALTER TABLE `ChatMessage` DROP FOREIGN KEY `ChatMessage_senderUserId_fkey`;
ALTER TABLE `DirectMessage` DROP FOREIGN KEY `DirectMessage_studentUserId_fkey`;
ALTER TABLE `DirectMessage` DROP FOREIGN KEY `DirectMessage_senderUserId_fkey`;
ALTER TABLE `ForumMessage` DROP FOREIGN KEY `ForumMessage_senderUserId_fkey`;
ALTER TABLE `AcademicRequest` DROP FOREIGN KEY `AcademicRequest_userId_fkey`;
ALTER TABLE `AdmissionRequest` DROP FOREIGN KEY `AdmissionRequest_userId_fkey`;
ALTER TABLE `ExerciseSubmission` DROP FOREIGN KEY `ExerciseSubmission_userId_fkey`;

ALTER TABLE `AcademicRequest` DROP COLUMN `userId`;
ALTER TABLE `AdmissionRequest` DROP COLUMN `userId`;
ALTER TABLE `Certificate` DROP COLUMN `enrollmentId`, DROP COLUMN `userId`;
ALTER TABLE `ChatMessage` DROP COLUMN `senderUserId`;
ALTER TABLE `Course` DROP COLUMN `instructorId`;
ALTER TABLE `DirectMessage` DROP COLUMN `senderUserId`, DROP COLUMN `studentUserId`;
ALTER TABLE `ExerciseSubmission` DROP COLUMN `userId`;
ALTER TABLE `ForumMessage` DROP COLUMN `senderUserId`;
ALTER TABLE `QuizSubmission` DROP COLUMN `userId`;
ALTER TABLE `StudentEnrollment` DROP COLUMN `id`, DROP COLUMN `userId`;
ALTER TABLE `StudentProgress` DROP COLUMN `enrollmentId`, DROP COLUMN `userId`;
