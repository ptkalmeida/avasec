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

export interface Course {
  id: string;
  title: string;
  description: string;
  category: string;
  thumbnail: string;
  instructorName: string;
  lessons: Lesson[];
  liveSessions: LiveSession[];
  coverImage?: string;
  courseType?: 'fixo' | 'ao_vivo';
  hasChat?: boolean;
  minAttendance?: number;
}

export interface StudentEnrollment {
  enrolledCourseId: string | null;
  enrolledAt: string | null;
  completedCourseIds: string[];
  dropOutPenaltyUntil: string | null;
}

export interface StudentProgress {
  courseId: string;
  completedLessons: string[]; // lessonIds
  attendedLiveSessions: string[]; // liveSessionIds
}

export interface Certificate {
  id: string;
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
  senderRole: 'student' | 'instructor';
  text: string;
  timestamp: string;
}

export interface DirectMessage {
  id: string;
  studentName: string;
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
}

export interface Quiz {
  id: string;
  courseId: string;
  title: string;
  questions: QuizQuestion[];
}

export interface QuizSubmission {
  id: string;
  studentName: string;
  courseId: string;
  quizId: string;
  scorePercent: number;
  passed: boolean;
  submittedAt: string;
}

export interface AcademicRequest {
  id: string;
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
  studentName: string;
  courseId: string;
  status: 'pending' | 'approved' | 'rejected';
  submittedAt: string;
}

export interface LMSState {
  courses: Course[];
  activeUser: {
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
  likes: number;
  likedBy: string[]; // List of user names who liked
}


