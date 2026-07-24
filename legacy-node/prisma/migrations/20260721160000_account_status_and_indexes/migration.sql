-- AlterTable
ALTER TABLE `User` ADD COLUMN `failedLoginAttempts` INTEGER NOT NULL DEFAULT 0,
    ADD COLUMN `lockedUntil` DATETIME(3) NULL,
    ADD COLUMN `status` ENUM('active', 'blocked', 'pending_confirmation') NOT NULL DEFAULT 'active';

-- CreateIndex
CREATE INDEX `AcademicRequest_studentName_idx` ON `AcademicRequest`(`studentName`);

-- CreateIndex
CREATE INDEX `AcademicRequest_status_idx` ON `AcademicRequest`(`status`);

-- CreateIndex
CREATE INDEX `AdmissionRequest_studentName_idx` ON `AdmissionRequest`(`studentName`);

-- CreateIndex
CREATE INDEX `AdmissionRequest_courseId_idx` ON `AdmissionRequest`(`courseId`);

-- CreateIndex
CREATE INDEX `AdmissionRequest_studentName_courseId_status_idx` ON `AdmissionRequest`(`studentName`, `courseId`, `status`);

-- CreateIndex
CREATE INDEX `Certificate_courseId_idx` ON `Certificate`(`courseId`);

-- CreateIndex
CREATE UNIQUE INDEX `Certificate_studentName_courseId_key` ON `Certificate`(`studentName`, `courseId`);

-- CreateIndex
CREATE INDEX `Course_instructorName_idx` ON `Course`(`instructorName`);

-- CreateIndex
CREATE INDEX `ExerciseSubmission_studentName_idx` ON `ExerciseSubmission`(`studentName`);

-- CreateIndex
CREATE INDEX `QuizSubmission_studentName_idx` ON `QuizSubmission`(`studentName`);

-- CreateIndex
CREATE INDEX `QuizSubmission_quizId_idx` ON `QuizSubmission`(`quizId`);

-- CreateIndex
CREATE INDEX `QuizSubmission_courseId_idx` ON `QuizSubmission`(`courseId`);

-- CreateIndex
CREATE INDEX `SecurityLog_user_idx` ON `SecurityLog`(`user`);

-- CreateIndex
CREATE INDEX `SecurityLog_action_idx` ON `SecurityLog`(`action`);

-- CreateIndex
CREATE INDEX `StudentProgress_courseId_idx` ON `StudentProgress`(`courseId`);

-- CreateIndex
CREATE INDEX `User_role_idx` ON `User`(`role`);

-- CreateIndex
CREATE INDEX `User_status_idx` ON `User`(`status`);

-- CreateIndex
CREATE INDEX `User_role_status_idx` ON `User`(`role`, `status`);

