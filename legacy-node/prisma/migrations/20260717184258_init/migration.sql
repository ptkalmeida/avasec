-- CreateTable
CREATE TABLE `User` (
    `id` VARCHAR(191) NOT NULL,
    `name` VARCHAR(191) NOT NULL,
    `email` VARCHAR(191) NOT NULL,
    `passwordHash` VARCHAR(191) NOT NULL,
    `role` ENUM('student', 'instructor', 'admin') NOT NULL DEFAULT 'student',
    `cpf` VARCHAR(191) NULL,
    `createdAt` DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    `updatedAt` DATETIME(3) NOT NULL,

    UNIQUE INDEX `User_email_key`(`email`),
    PRIMARY KEY (`id`)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- CreateTable
CREATE TABLE `Course` (
    `id` VARCHAR(191) NOT NULL,
    `title` VARCHAR(191) NOT NULL,
    `description` TEXT NOT NULL,
    `category` VARCHAR(191) NOT NULL,
    `thumbnail` TEXT NOT NULL,
    `instructorName` VARCHAR(191) NOT NULL,
    `coverImage` TEXT NULL,
    `courseType` ENUM('fixo', 'ao_vivo') NULL,
    `hasChat` BOOLEAN NULL,
    `minAttendance` INTEGER NULL,
    `contractExpirationDate` VARCHAR(191) NULL,
    `areaTematica` VARCHAR(191) NULL,
    `cargaHoraria` INTEGER NULL,
    `modalidade` VARCHAR(191) NULL,
    `nivel` VARCHAR(191) NULL,
    `emiteCertificado` BOOLEAN NULL,
    `statusCurso` VARCHAR(191) NULL,

    PRIMARY KEY (`id`)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- CreateTable
CREATE TABLE `Lesson` (
    `id` VARCHAR(191) NOT NULL,
    `courseId` VARCHAR(191) NOT NULL,
    `title` VARCHAR(191) NOT NULL,
    `duration` VARCHAR(191) NOT NULL,
    `videoUrl` TEXT NULL,
    `content` TEXT NULL,
    `lesson_order` INTEGER NOT NULL,

    INDEX `Lesson_courseId_idx`(`courseId`),
    PRIMARY KEY (`id`)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- CreateTable
CREATE TABLE `LessonDocument` (
    `id` VARCHAR(191) NOT NULL,
    `lessonId` VARCHAR(191) NOT NULL,
    `title` VARCHAR(191) NOT NULL,
    `type` ENUM('pdf', 'doc', 'url', 'drive', 'outro') NOT NULL,
    `url` TEXT NOT NULL,
    `size` VARCHAR(191) NULL,

    INDEX `LessonDocument_lessonId_idx`(`lessonId`),
    PRIMARY KEY (`id`)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- CreateTable
CREATE TABLE `LiveSession` (
    `id` VARCHAR(191) NOT NULL,
    `courseId` VARCHAR(191) NOT NULL,
    `title` VARCHAR(191) NOT NULL,
    `scheduledAt` VARCHAR(191) NOT NULL,
    `durationMinutes` INTEGER NOT NULL,
    `meetingLink` TEXT NOT NULL,
    `isLive` BOOLEAN NOT NULL DEFAULT false,

    INDEX `LiveSession_courseId_idx`(`courseId`),
    PRIMARY KEY (`id`)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- CreateTable
CREATE TABLE `StudentEnrollment` (
    `studentName` VARCHAR(191) NOT NULL,
    `enrolledCourseId` VARCHAR(191) NULL,
    `enrolledAt` VARCHAR(191) NULL,
    `completedCourseIds` JSON NOT NULL,
    `dropOutPenaltyUntil` VARCHAR(191) NULL,

    PRIMARY KEY (`studentName`)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- CreateTable
CREATE TABLE `StudentProgress` (
    `id` VARCHAR(191) NOT NULL,
    `studentName` VARCHAR(191) NOT NULL,
    `courseId` VARCHAR(191) NOT NULL,
    `completedLessons` JSON NOT NULL,
    `attendedLiveSessions` JSON NOT NULL,

    UNIQUE INDEX `StudentProgress_studentName_courseId_key`(`studentName`, `courseId`),
    PRIMARY KEY (`id`)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- CreateTable
CREATE TABLE `Certificate` (
    `id` VARCHAR(191) NOT NULL,
    `studentName` VARCHAR(191) NOT NULL,
    `courseId` VARCHAR(191) NOT NULL,
    `courseTitle` VARCHAR(191) NOT NULL,
    `issueDate` VARCHAR(191) NOT NULL,
    `attendancePercent` DOUBLE NOT NULL,
    `verificationHash` VARCHAR(191) NOT NULL,

    INDEX `Certificate_studentName_idx`(`studentName`),
    PRIMARY KEY (`id`)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- CreateTable
CREATE TABLE `Quiz` (
    `id` VARCHAR(191) NOT NULL,
    `courseId` VARCHAR(191) NOT NULL,
    `title` VARCHAR(191) NOT NULL,

    INDEX `Quiz_courseId_idx`(`courseId`),
    PRIMARY KEY (`id`)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- CreateTable
CREATE TABLE `QuizQuestion` (
    `id` VARCHAR(191) NOT NULL,
    `quizId` VARCHAR(191) NOT NULL,
    `questionText` TEXT NOT NULL,
    `options` JSON NOT NULL,
    `correctOptionIndex` INTEGER NOT NULL,
    `explanation` TEXT NULL,
    `reviewMessage` TEXT NULL,
    `recommendedModule` VARCHAR(191) NULL,
    `allowRetry` BOOLEAN NULL,

    INDEX `QuizQuestion_quizId_idx`(`quizId`),
    PRIMARY KEY (`id`)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- CreateTable
CREATE TABLE `QuizSubmission` (
    `id` VARCHAR(191) NOT NULL,
    `studentName` VARCHAR(191) NOT NULL,
    `courseId` VARCHAR(191) NOT NULL,
    `quizId` VARCHAR(191) NOT NULL,
    `scorePercent` DOUBLE NOT NULL,
    `passed` BOOLEAN NOT NULL,
    `submittedAt` VARCHAR(191) NOT NULL,

    PRIMARY KEY (`id`)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- CreateTable
CREATE TABLE `ChatMessage` (
    `id` VARCHAR(191) NOT NULL,
    `sessionId` VARCHAR(191) NOT NULL,
    `senderName` VARCHAR(191) NOT NULL,
    `senderRole` ENUM('student', 'instructor', 'admin') NOT NULL,
    `text` TEXT NOT NULL,
    `timestamp` VARCHAR(191) NOT NULL,

    INDEX `ChatMessage_sessionId_idx`(`sessionId`),
    PRIMARY KEY (`id`)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- CreateTable
CREATE TABLE `DirectMessage` (
    `id` VARCHAR(191) NOT NULL,
    `studentName` VARCHAR(191) NOT NULL,
    `senderName` VARCHAR(191) NOT NULL,
    `senderRole` ENUM('student', 'instructor', 'admin') NOT NULL,
    `text` TEXT NOT NULL,
    `timestamp` VARCHAR(191) NOT NULL,

    INDEX `DirectMessage_studentName_idx`(`studentName`),
    PRIMARY KEY (`id`)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- CreateTable
CREATE TABLE `ForumMessage` (
    `id` VARCHAR(191) NOT NULL,
    `courseId` VARCHAR(191) NOT NULL,
    `senderName` VARCHAR(191) NOT NULL,
    `senderRole` ENUM('student', 'instructor', 'admin') NOT NULL,
    `text` TEXT NOT NULL,
    `timestamp` VARCHAR(191) NOT NULL,
    `likes` INTEGER NOT NULL DEFAULT 0,
    `likedBy` JSON NOT NULL,

    INDEX `ForumMessage_courseId_idx`(`courseId`),
    PRIMARY KEY (`id`)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- CreateTable
CREATE TABLE `AcademicRequest` (
    `id` VARCHAR(191) NOT NULL,
    `studentName` VARCHAR(191) NOT NULL,
    `type` ENUM('certificado', 'historico', 'matricula', 'outro') NOT NULL,
    `description` TEXT NOT NULL,
    `status` ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
    `submittedAt` VARCHAR(191) NOT NULL,
    `courseTitle` VARCHAR(191) NULL,

    PRIMARY KEY (`id`)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- CreateTable
CREATE TABLE `AdmissionRequest` (
    `id` VARCHAR(191) NOT NULL,
    `studentName` VARCHAR(191) NOT NULL,
    `courseId` VARCHAR(191) NOT NULL,
    `status` ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
    `submittedAt` VARCHAR(191) NOT NULL,

    PRIMARY KEY (`id`)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- CreateTable
CREATE TABLE `PracticalExercise` (
    `id` VARCHAR(191) NOT NULL,
    `courseId` VARCHAR(191) NOT NULL,
    `title` VARCHAR(191) NOT NULL,
    `description` TEXT NOT NULL,
    `instructions` TEXT NOT NULL,
    `maxPoints` INTEGER NOT NULL,
    `dueDate` VARCHAR(191) NULL,

    INDEX `PracticalExercise_courseId_idx`(`courseId`),
    PRIMARY KEY (`id`)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- CreateTable
CREATE TABLE `ExerciseSubmission` (
    `id` VARCHAR(191) NOT NULL,
    `exerciseId` VARCHAR(191) NOT NULL,
    `studentName` VARCHAR(191) NOT NULL,
    `submissionText` TEXT NOT NULL,
    `fileUrl` TEXT NULL,
    `fileName` VARCHAR(191) NULL,
    `submittedAt` VARCHAR(191) NOT NULL,
    `status` ENUM('pending', 'approved', 'rejected', 'revision') NOT NULL DEFAULT 'pending',
    `score` INTEGER NULL,
    `feedback` TEXT NULL,
    `gradedAt` VARCHAR(191) NULL,
    `gradedBy` VARCHAR(191) NULL,

    INDEX `ExerciseSubmission_exerciseId_idx`(`exerciseId`),
    PRIMARY KEY (`id`)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- CreateTable
CREATE TABLE `LibraryItem` (
    `id` VARCHAR(191) NOT NULL,
    `title` VARCHAR(191) NOT NULL,
    `type` ENUM('pdf', 'video', 'link') NOT NULL,
    `category` VARCHAR(191) NOT NULL,
    `description` TEXT NULL,
    `url` TEXT NOT NULL,

    PRIMARY KEY (`id`)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- CreateTable
CREATE TABLE `WebinarEvent` (
    `id` VARCHAR(191) NOT NULL,
    `title` VARCHAR(191) NOT NULL,
    `date` VARCHAR(191) NOT NULL,
    `time` VARCHAR(191) NOT NULL,
    `description` TEXT NOT NULL,
    `link` TEXT NOT NULL,
    `image` TEXT NULL,

    PRIMARY KEY (`id`)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- CreateTable
CREATE TABLE `SecurityLog` (
    `id` VARCHAR(191) NOT NULL,
    `timestamp` VARCHAR(191) NOT NULL,
    `user` VARCHAR(191) NOT NULL,
    `role` VARCHAR(191) NOT NULL,
    `ipAddress` VARCHAR(191) NOT NULL,
    `device` VARCHAR(191) NOT NULL,
    `action` VARCHAR(191) NOT NULL,
    `details` TEXT NOT NULL,
    `status` ENUM('SUCCESS', 'WARNING', 'FAILED') NOT NULL,

    INDEX `SecurityLog_timestamp_idx`(`timestamp`),
    PRIMARY KEY (`id`)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- CreateTable
CREATE TABLE `SystemSettings` (
    `id` VARCHAR(191) NOT NULL DEFAULT 'singleton',
    `siteName` VARCHAR(191) NOT NULL,
    `maintenanceMode` BOOLEAN NOT NULL DEFAULT false,
    `allowNewRegistrations` BOOLEAN NOT NULL DEFAULT true,
    `defaultLanguage` VARCHAR(191) NOT NULL DEFAULT 'pt',
    `sessionTimeout` INTEGER NOT NULL DEFAULT 30,
    `requireMfa` BOOLEAN NOT NULL DEFAULT true,
    `mfaType` ENUM('email', 'sms', 'app', 'pin') NOT NULL DEFAULT 'pin',
    `maxLoginAttempts` INTEGER NOT NULL DEFAULT 5,

    PRIMARY KEY (`id`)
) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- AddForeignKey
ALTER TABLE `Lesson` ADD CONSTRAINT `Lesson_courseId_fkey` FOREIGN KEY (`courseId`) REFERENCES `Course`(`id`) ON DELETE CASCADE ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE `LessonDocument` ADD CONSTRAINT `LessonDocument_lessonId_fkey` FOREIGN KEY (`lessonId`) REFERENCES `Lesson`(`id`) ON DELETE CASCADE ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE `LiveSession` ADD CONSTRAINT `LiveSession_courseId_fkey` FOREIGN KEY (`courseId`) REFERENCES `Course`(`id`) ON DELETE CASCADE ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE `QuizQuestion` ADD CONSTRAINT `QuizQuestion_quizId_fkey` FOREIGN KEY (`quizId`) REFERENCES `Quiz`(`id`) ON DELETE CASCADE ON UPDATE CASCADE;

-- AddForeignKey
ALTER TABLE `ExerciseSubmission` ADD CONSTRAINT `ExerciseSubmission_exerciseId_fkey` FOREIGN KEY (`exerciseId`) REFERENCES `PracticalExercise`(`id`) ON DELETE CASCADE ON UPDATE CASCADE;
