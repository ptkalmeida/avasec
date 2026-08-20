/**
 * @license
 * SPDX-License-Identifier: Apache-2.0
 */

export interface LessonDocument {
  id: string;
  title: string;
  type: 'pdf' | 'doc' | 'url' | 'drive' | 'outro';
  url: string;
  size?: string;
}

export interface Lesson {
  id: string;
  courseId: string;
  title: string;
  duration: string;
  videoUrl?: string;
  content?: string;
  isOptional?: boolean;
  order: number;
  documents?: LessonDocument[];
}

export interface LiveSession {
  id: string;
  courseId: string;
  title: string;
  scheduledAt: string; // ISO string or human dates
  durationMinutes: number;
  meetingLink: string; // e.g. Google Meet, Zoom or internal simulator
  isLive: boolean;
}

// Referência mínima a uma pessoa: id é identidade (ADR 10), name é display.
export interface PersonRef {
  id: string;
  name: string;
}

export interface Course {
  id: string;
  title: string;
  description: string;
  category: string;
  thumbnail: string;
  /** Display — a identidade do instrutor é instructorId (ADR 10). */
  instructorName: string;
  instructorId?: string | null;
  lessons: Lesson[];
  liveSessions: LiveSession[];
  coverImage?: string;
  courseType?: 'fixo' | 'ao_vivo';
  hasChat?: boolean;
  minAttendance?: number;
  contractExpirationDate?: string; // YYYY-MM-DD or DD/MM/YYYY
  areaTematica?: string;
  cargaHoraria?: number;
  modalidade?: string;
  nivel?: string;
  emiteCertificado?: boolean;
  statusCurso?: string;
}

export function isCourseExpired(contractExpirationDate?: string): boolean {
  if (!contractExpirationDate) return false;
  try {
    let expDate: Date;
    if (contractExpirationDate.includes('-')) {
      // YYYY-MM-DD
      const parts = contractExpirationDate.split('-');
      if (parts.length === 3) {
        const year = parseInt(parts[0], 10);
        const month = parseInt(parts[1], 10) - 1;
        const day = parseInt(parts[2], 10);
        expDate = new Date(year, month, day, 23, 59, 59);
      } else {
        expDate = new Date(contractExpirationDate + 'T23:59:59');
      }
    } else if (contractExpirationDate.includes('/')) {
      // DD/MM/YYYY
      const parts = contractExpirationDate.split('/');
      if (parts.length === 3) {
        const day = parseInt(parts[0], 10);
        const month = parseInt(parts[1], 10) - 1;
        const year = parseInt(parts[2], 10);
        expDate = new Date(year, month, day, 23, 59, 59);
      } else {
        return false;
      }
    } else {
      expDate = new Date(contractExpirationDate);
    }
    
    if (isNaN(expDate.getTime())) return false;
    
    // Set current date to 2026-07-01 (based on additional metadata local time 2026-07-01) or standard system Date.
    // Let's use current Date, but ensure it works.
    const today = new Date();
    return today > expDate;
  } catch (e) {
    console.error("Error parsing expiration date", e);
    return false;
  }
}

export interface StudentEnrollment {
  /** Identidade do aluno (ADR 10) — chave do mapa de matrículas. */
  userId: string;
  /** Display (snapshot). */
  studentName: string;
  enrolledCourseId: string | null;
  enrolledAt: string | null;
  completedCourseIds: string[];
  dropOutPenaltyUntil: string | null;
  /** Concedida só pelo Admin Superior (feature matriculasMultiplas). */
  canMultiEnroll: boolean;
  /** Cursos ativos ALÉM do principal (enrolledCourseId) — só quando canMultiEnroll. */
  extraCourseIds: string[];
}

/** Área de gerenciamento de templates de documentos (certificado, histórico) — Admin Superior. */
export interface DocumentTemplate {
  type: 'certificado' | 'historico';
  institutionName: string;
  institutionLogoPath: string | null;
  signatories: { name: string; role: string }[];
  footerText: string;
  /** Presente e não-vazio = modo "layout livre" (substitui o template estruturado). */
  customHtml: string | null;
  updatedAt: string | null;
  updatedByUserId: string | null;
}

export interface StudentProgress {
  userId: string;
  /** Display (snapshot) — a identidade é userId (ADR 10). */
  studentName: string;
  courseId: string;
  completedLessons: string[]; // lessonIds
  attendedLiveSessions: string[]; // liveSessionIds
}

export type AccountStatus = 'active' | 'blocked' | 'pending_confirmation';

export interface AuthUser {
  id: string;
  name: string;
  email: string;
  role: 'student' | 'instructor' | 'admin';
  status: AccountStatus;
}

export interface Certificate {
  id: string;
  /** userId pode ser null em certificados históricos de contas removidas. */
  userId?: string | null;
  studentName: string;
  courseId: string;
  courseTitle: string;
  issueDate: string;
  attendancePercent: number;
  verificationHash: string;
}

export interface ChatMessage {
  id: string;
  sessionId: string;
  senderName: string;
  senderRole: 'student' | 'instructor' | 'admin';
  text: string;
  timestamp: string;
}

export interface DirectMessage {
  id: string;
  /** Dono da thread (ADR 10). */
  studentUserId: string;
  /** Display (snapshot). */
  studentName: string;
  senderUserId?: string | null;
  senderName: string;
  senderRole: 'student' | 'instructor';
  text: string;
  timestamp: string;
}

export interface QuizQuestion {
  id: string;
  questionText: string;
  options: string[];
  correctOptionIndex: number;
  explanation?: string;
  reviewMessage?: string;
  recommendedModule?: string;
  allowRetry?: boolean;
}

export interface Quiz {
  id: string;
  courseId: string;
  title: string;
  questions: QuizQuestion[];
}

export interface QuizSubmission {
  id: string;
  userId: string;
  /** Display (snapshot). */
  studentName: string;
  courseId: string;
  quizId: string;
  scorePercent: number;
  passed: boolean;
  submittedAt: string;
}

export interface AcademicRequest {
  id: string;
  userId: string;
  /** Display (snapshot). */
  studentName: string;
  type: 'certificado' | 'historico' | 'matricula' | 'outro';
  description: string;
  status: 'pending' | 'approved' | 'rejected';
  submittedAt: string;
  courseTitle?: string;
}

export interface LibraryItem {
  id: string;
  title: string;
  type: 'pdf' | 'video' | 'link';
  category: string;
  description?: string;
  url: string;
}

export interface WebinarEvent {
  id: string;
  title: string;
  date: string;
  time: string;
  description: string;
  link: string;
  image?: string;
}

export interface AccessibilitySettings {
  highContrast: boolean;
  fontSize: 'small' | 'medium' | 'large';
  dyslexicFont: boolean;
}

export interface AdmissionRequest {
  id: string;
  userId: string;
  /** Display (snapshot). */
  studentName: string;
  courseId: string;
  status: 'pending' | 'approved' | 'rejected';
  submittedAt: string;
}

export interface PracticalExercise {
  id: string;
  courseId: string;
  title: string;
  description: string;
  instructions: string;
  maxPoints: number;
  dueDate?: string;
}

export interface ExerciseSubmission {
  id: string;
  exerciseId: string;
  userId: string;
  /** Display (snapshot). */
  studentName: string;
  submissionText: string;
  fileUrl?: string;
  fileName?: string;
  submittedAt: string;
  status: 'pending' | 'approved' | 'rejected' | 'revision';
  score?: number;
  feedback?: string;
  gradedAt?: string;
  gradedBy?: string;
}

export interface LMSState {
  courses: Course[];
  activeUser: {
    /** '' quando visitante — identidade real vem de authUser (ADR 10). */
    id: string;
    name: string;
    role: 'student' | 'instructor' | 'admin';
  };
  progress: StudentProgress[]; // only relevant for students
  certificates: Certificate[];
  chatMessages: ChatMessage[];
  directMessages: DirectMessage[];
  quizzes: Quiz[];
  quizSubmissions: QuizSubmission[];
}

export interface SecurityLog {
  id: string;
  timestamp: string;
  user: string;
  role: string;
  ipAddress: string;
  device: string;
  action: string;
  details: string;
  status: 'SUCCESS' | 'WARNING' | 'FAILED';
}

export interface ForumMessage {
  id: string;
  courseId: string;
  senderName: string;
  senderRole: 'student' | 'instructor' | 'admin';
  text: string;
  timestamp: string;
  senderUserId?: string | null;
  likes: number;
  likedBy: string[]; // userIds de quem curtiu (ADR 10 — antes eram nomes)
}


