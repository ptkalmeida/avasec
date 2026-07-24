-- AlterTable
ALTER TABLE `AcademicRequest` ADD COLUMN `userId` VARCHAR(191) NULL;

-- AlterTable
ALTER TABLE `AdmissionRequest` ADD COLUMN `userId` VARCHAR(191) NULL;

-- AlterTable
ALTER TABLE `Certificate` ADD COLUMN `enrollmentId` VARCHAR(191) NULL,
    ADD COLUMN `userId` VARCHAR(191) NULL;

-- AlterTable
ALTER TABLE `ChatMessage` ADD COLUMN `senderUserId` VARCHAR(191) NULL;

-- AlterTable
ALTER TABLE `Course` ADD COLUMN `instructorId` VARCHAR(191) NULL;

-- AlterTable
ALTER TABLE `DirectMessage` ADD COLUMN `senderUserId` VARCHAR(191) NULL,
    ADD COLUMN `studentUserId` VARCHAR(191) NULL;

-- AlterTable
ALTER TABLE `ExerciseSubmission` ADD COLUMN `userId` VARCHAR(191) NULL;

-- AlterTable
ALTER TABLE `ForumMessage` ADD COLUMN `senderUserId` VARCHAR(191) NULL;

-- AlterTable
ALTER TABLE `QuizSubmission` ADD COLUMN `userId` VARCHAR(191) NULL;

-- AlterTable
ALTER TABLE `StudentEnrollment` ADD COLUMN `id` VARCHAR(191) NULL,
    ADD COLUMN `userId` VARCHAR(191) NULL;

-- AlterTable
ALTER TABLE `StudentProgress` ADD COLUMN `enrollmentId` VARCHAR(191) NULL,
    ADD COLUMN `userId` VARCHAR(191) NULL;

-- CreateIndex
CREATE INDEX `AcademicRequest_userId_idx` ON `AcademicRequest`(`userId`);

-- CreateIndex
CREATE INDEX `AdmissionRequest_userId_idx` ON `AdmissionRequest`(`userId`);

-- CreateIndex
CREATE INDEX `Certificate_userId_idx` ON `Certificate`(`userId`);

-- CreateIndex
CREATE INDEX `Certificate_enrollmentId_idx` ON `Certificate`(`enrollmentId`);

-- CreateIndex
CREATE INDEX `ChatMessage_senderUserId_idx` ON `ChatMessage`(`senderUserId`);

-- CreateIndex
CREATE INDEX `Course_instructorId_idx` ON `Course`(`instructorId`);

-- CreateIndex
CREATE INDEX `DirectMessage_studentUserId_idx` ON `DirectMessage`(`studentUserId`);

-- CreateIndex
CREATE INDEX `DirectMessage_senderUserId_idx` ON `DirectMessage`(`senderUserId`);

-- CreateIndex
CREATE INDEX `ExerciseSubmission_userId_idx` ON `ExerciseSubmission`(`userId`);

-- CreateIndex
CREATE INDEX `ForumMessage_senderUserId_idx` ON `ForumMessage`(`senderUserId`);

-- CreateIndex
CREATE INDEX `QuizSubmission_userId_idx` ON `QuizSubmission`(`userId`);

-- CreateIndex
CREATE UNIQUE INDEX `StudentEnrollment_id_key` ON `StudentEnrollment`(`id`);

-- CreateIndex
CREATE UNIQUE INDEX `StudentEnrollment_userId_key` ON `StudentEnrollment`(`userId`);

-- CreateIndex
CREATE INDEX `StudentProgress_userId_idx` ON `StudentProgress`(`userId`);

-- CreateIndex
CREATE INDEX `StudentProgress_enrollmentId_idx` ON `StudentProgress`(`enrollmentId`);


-- ============ BACKFILL (antes de criar as FKs) ============
-- Preenche as novas colunas a partir dos campos *Name existentes. Registros cujo nome não
-- corresponde a nenhum usuário permanecem NULL (dados históricos preservados, sem perda).

-- Identificador estável de matrícula (enrollmentId)
UPDATE `StudentEnrollment` SET `id` = UUID() WHERE `id` IS NULL;

-- Curso -> instrutor
UPDATE `Course` c
  JOIN `User` u ON u.`name` = c.`instructorName` AND u.`role` IN ('instructor', 'admin')
  SET c.`instructorId` = u.`id`
  WHERE c.`instructorId` IS NULL;

-- Matrícula -> aluno
UPDATE `StudentEnrollment` e
  JOIN `User` u ON u.`name` = e.`studentName` AND u.`role` = 'student'
  SET e.`userId` = u.`id`
  WHERE e.`userId` IS NULL;

-- Progresso -> aluno e matrícula
UPDATE `StudentProgress` p
  JOIN `User` u ON u.`name` = p.`studentName` AND u.`role` = 'student'
  SET p.`userId` = u.`id`
  WHERE p.`userId` IS NULL;
UPDATE `StudentProgress` p
  JOIN `StudentEnrollment` e ON e.`studentName` = p.`studentName`
  SET p.`enrollmentId` = e.`id`
  WHERE p.`enrollmentId` IS NULL;

-- Certificado -> aluno e matrícula
UPDATE `Certificate` c
  JOIN `User` u ON u.`name` = c.`studentName` AND u.`role` = 'student'
  SET c.`userId` = u.`id`
  WHERE c.`userId` IS NULL;
UPDATE `Certificate` c
  JOIN `StudentEnrollment` e ON e.`studentName` = c.`studentName`
  SET c.`enrollmentId` = e.`id`
  WHERE c.`enrollmentId` IS NULL;

-- Submissões de quiz/exercício, solicitações acadêmicas e admissões -> aluno
UPDATE `QuizSubmission` q JOIN `User` u ON u.`name` = q.`studentName` AND u.`role` = 'student'
  SET q.`userId` = u.`id` WHERE q.`userId` IS NULL;
UPDATE `ExerciseSubmission` s JOIN `User` u ON u.`name` = s.`studentName` AND u.`role` = 'student'
  SET s.`userId` = u.`id` WHERE s.`userId` IS NULL;
UPDATE `AcademicRequest` r JOIN `User` u ON u.`name` = r.`studentName` AND u.`role` = 'student'
  SET r.`userId` = u.`id` WHERE r.`userId` IS NULL;
UPDATE `AdmissionRequest` r JOIN `User` u ON u.`name` = r.`studentName` AND u.`role` = 'student'
  SET r.`userId` = u.`id` WHERE r.`userId` IS NULL;

-- Mensagens -> remetente (name + role) e aluno do canal
UPDATE `ChatMessage` m JOIN `User` u ON u.`name` = m.`senderName` AND u.`role` = m.`senderRole`
  SET m.`senderUserId` = u.`id` WHERE m.`senderUserId` IS NULL;
UPDATE `DirectMessage` m JOIN `User` u ON u.`name` = m.`studentName` AND u.`role` = 'student'
  SET m.`studentUserId` = u.`id` WHERE m.`studentUserId` IS NULL;
UPDATE `DirectMessage` m JOIN `User` u ON u.`name` = m.`senderName` AND u.`role` = m.`senderRole`
  SET m.`senderUserId` = u.`id` WHERE m.`senderUserId` IS NULL;
UPDATE `ForumMessage` m JOIN `User` u ON u.`name` = m.`senderName` AND u.`role` = m.`senderRole`
  SET m.`senderUserId` = u.`id` WHERE m.`senderUserId` IS NULL;

-- ============ FOREIGN KEYS (após o backfill) ============
-- AddForeignKey
ALTER TABLE `Course` ADD CONSTRAINT `Course_instructorId_fkey` FOREIGN KEY (`instructorId`) REFERENCES `User`(`id`) ON DELETE SET NULL ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE `StudentEnrollment` ADD CONSTRAINT `StudentEnrollment_userId_fkey` FOREIGN KEY (`userId`) REFERENCES `User`(`id`) ON DELETE SET NULL ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE `StudentProgress` ADD CONSTRAINT `StudentProgress_userId_fkey` FOREIGN KEY (`userId`) REFERENCES `User`(`id`) ON DELETE SET NULL ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE `StudentProgress` ADD CONSTRAINT `StudentProgress_enrollmentId_fkey` FOREIGN KEY (`enrollmentId`) REFERENCES `StudentEnrollment`(`id`) ON DELETE SET NULL ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE `Certificate` ADD CONSTRAINT `Certificate_userId_fkey` FOREIGN KEY (`userId`) REFERENCES `User`(`id`) ON DELETE SET NULL ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE `Certificate` ADD CONSTRAINT `Certificate_enrollmentId_fkey` FOREIGN KEY (`enrollmentId`) REFERENCES `StudentEnrollment`(`id`) ON DELETE SET NULL ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE `QuizSubmission` ADD CONSTRAINT `QuizSubmission_userId_fkey` FOREIGN KEY (`userId`) REFERENCES `User`(`id`) ON DELETE SET NULL ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE `ChatMessage` ADD CONSTRAINT `ChatMessage_senderUserId_fkey` FOREIGN KEY (`senderUserId`) REFERENCES `User`(`id`) ON DELETE SET NULL ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE `DirectMessage` ADD CONSTRAINT `DirectMessage_studentUserId_fkey` FOREIGN KEY (`studentUserId`) REFERENCES `User`(`id`) ON DELETE SET NULL ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE `DirectMessage` ADD CONSTRAINT `DirectMessage_senderUserId_fkey` FOREIGN KEY (`senderUserId`) REFERENCES `User`(`id`) ON DELETE SET NULL ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE `ForumMessage` ADD CONSTRAINT `ForumMessage_senderUserId_fkey` FOREIGN KEY (`senderUserId`) REFERENCES `User`(`id`) ON DELETE SET NULL ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE `AcademicRequest` ADD CONSTRAINT `AcademicRequest_userId_fkey` FOREIGN KEY (`userId`) REFERENCES `User`(`id`) ON DELETE SET NULL ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE `AdmissionRequest` ADD CONSTRAINT `AdmissionRequest_userId_fkey` FOREIGN KEY (`userId`) REFERENCES `User`(`id`) ON DELETE SET NULL ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE `ExerciseSubmission` ADD CONSTRAINT `ExerciseSubmission_userId_fkey` FOREIGN KEY (`userId`) REFERENCES `User`(`id`) ON DELETE SET NULL ON UPDATE CASCADE;

