import { describe, expect, it } from 'vitest'

import {
  STUDENT_CSV_COLUMNS,
  applyStudentCsvDefaults,
  buildStudentCsvTemplate,
  isDuplicateStudentCsvRow,
  parseStudentCsv,
  studentCsvHeaderLine,
} from './studentCsvImport'

describe('studentCsvImport', () => {
  it('exposes a single column definition used by template and parser', () => {
    expect(STUDENT_CSV_COLUMNS.map((column) => column.header).join(',')).toBe(studentCsvHeaderLine())
    expect(buildStudentCsvTemplate().startsWith(`${studentCsvHeaderLine()}\n`)).toBe(true)
  })

  it('parses rows and skips the header', () => {
    const csv = `${studentCsvHeaderLine()}
student1@example.com,Juan Dela Cruz,2024-0001,BS Computer Science,4th Year
`
    const { rows, issues } = parseStudentCsv(csv)
    expect(issues).toEqual([])
    expect(rows).toHaveLength(1)
    expect(rows[0]).toMatchObject({
      email: 'student1@example.com',
      studentName: 'Juan Dela Cruz',
      studentNumber: '2024-0001',
      course: 'BS Computer Science',
      yearLevel: '4th Year',
    })
  })

  it('reports missing email as a parse issue', () => {
    const { rows, issues } = parseStudentCsv(',No Email,2024-0001,,')
    expect(rows).toHaveLength(0)
    expect(issues[0]?.message).toMatch(/email/i)
  })

  it('applies default course and year when omitted', () => {
    const filled = applyStudentCsvDefaults(
      {
        line: 2,
        email: 'a@example.com',
        studentName: 'A',
        studentNumber: '1',
        course: '',
        yearLevel: '',
      },
      { course: 'BSIT', yearLevel: '3rd Year' },
    )
    expect(filled.course).toBe('BSIT')
    expect(filled.yearLevel).toBe('3rd Year')
  })

  it('detects duplicates by email or student number', () => {
    const row = {
      line: 1,
      email: 'dup@example.com',
      studentName: '',
      studentNumber: '2024-9',
      course: '',
      yearLevel: '',
    }
    expect(isDuplicateStudentCsvRow(row, [{ email: 'dup@example.com' }])).toBe(true)
    expect(isDuplicateStudentCsvRow(row, [{ studentNumber: '2024-9' }])).toBe(true)
    expect(isDuplicateStudentCsvRow(row, [{ email: 'other@example.com', studentNumber: 'x' }])).toBe(false)
  })
})
