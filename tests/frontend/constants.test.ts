import { describe, it, expect } from 'vitest';
import { courseMinAttendance, DEFAULT_MIN_ATTENDANCE } from '../../src/config/constants';

describe('courseMinAttendance — fonte única da regra de frequência mínima', () => {
  it('usa o percentual do curso quando definido', () => {
    expect(courseMinAttendance({ minAttendance: 85 })).toBe(85);
  });

  it('cai no padrão institucional quando o curso não define minAttendance', () => {
    expect(courseMinAttendance({ minAttendance: undefined })).toBe(DEFAULT_MIN_ATTENDANCE);
    expect(courseMinAttendance({ minAttendance: null })).toBe(DEFAULT_MIN_ATTENDANCE);
  });

  it('cai no padrão institucional quando o curso é null/undefined', () => {
    expect(courseMinAttendance(null)).toBe(DEFAULT_MIN_ATTENDANCE);
    expect(courseMinAttendance(undefined)).toBe(DEFAULT_MIN_ATTENDANCE);
  });

  it('respeita minAttendance = 0 (não confunde com "não definido")', () => {
    expect(courseMinAttendance({ minAttendance: 0 })).toBe(0);
  });
});
