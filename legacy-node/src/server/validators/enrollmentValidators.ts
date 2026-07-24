import { z } from 'zod';
import { idSchema, looseDateSchema, nameSchema, requestStatusSchema } from './common';

export const progressUpdateSchema = z.object({
  studentName: nameSchema,
  courseId: idSchema,
  completedLessons: z.array(idSchema).max(2000).default([]),
  attendedLiveSessions: z.array(idSchema).max(2000).default([]),
});

export const enrollmentUpdateSchema = z.object({
  enrolledCourseId: idSchema.nullable().optional(),
  enrolledAt: z.string().nullable().optional(),
  completedCourseIds: z.array(idSchema).max(500).optional(),
  dropOutPenaltyUntil: z.string().nullable().optional(),
});

export const enrollStudentSchema = z.object({
  studentName: nameSchema,
  courseId: idSchema,
  customEnrolledAt: looseDateSchema.optional(),
});

export const admissionRequestSchema = z.object({
  id: idSchema.optional(),
  studentName: nameSchema,
  courseId: idSchema,
  status: requestStatusSchema.optional(),
});

export const admissionStatusUpdateSchema = z.object({
  status: z.enum(['approved', 'rejected']),
});
