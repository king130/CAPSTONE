/** Single source of truth for school student CSV import columns (template + validation). */

export type StudentCsvColumnKey =
  | 'email'
  | 'studentName'
  | 'studentNumber'
  | 'course'
  | 'yearLevel'

export interface StudentCsvColumn {
  key: StudentCsvColumnKey
  header: string
  required: boolean
}

/** Canonical column order and headers for template download and parsing. */
export const STUDENT_CSV_COLUMNS: readonly StudentCsvColumn[] = [
  { key: 'email', header: 'email', required: true },
  { key: 'studentName', header: 'name', required: false },
  { key: 'studentNumber', header: 'student number', required: false },
  { key: 'course', header: 'course', required: false },
  { key: 'yearLevel', header: 'year level', required: false },
] as const

export type StudentCsvImportMode = 'skip_duplicates' | 'add_new_only'

export interface StudentCsvRow {
  line: number
  email: string
  studentName: string
  studentNumber: string
  course: string
  yearLevel: string
}

export interface StudentCsvParseIssue {
  line: number
  message: string
}

export function studentCsvHeaderLine(): string {
  return STUDENT_CSV_COLUMNS.map((column) => column.header).join(',')
}

export function buildStudentCsvTemplate(exampleRows: string[][] = []): string {
  const lines = [studentCsvHeaderLine()]
  if (exampleRows.length === 0) {
    lines.push('student1@example.com,Juan Dela Cruz,2024-0001,BS Computer Science,4th Year')
    lines.push('student2@example.com,Maria Santos,2024-0002,BS Information Technology,3rd Year')
  } else {
    for (const row of exampleRows) {
      lines.push(row.map(csvEscape).join(','))
    }
  }
  return `${lines.join('\n')}\n`
}

function csvEscape(value: string): string {
  if (/[",\n\r]/.test(value)) {
    return `"${value.replace(/"/g, '""')}"`
  }
  return value
}

function isHeaderRow(raw: string): boolean {
  const normalized = raw.trim().toLowerCase()
  const expected = studentCsvHeaderLine().toLowerCase()
  if (normalized === expected) return true
  return normalized.startsWith('email,')
}

/** Split a CSV line on commas outside quotes. */
function splitCsvLine(raw: string): string[] {
  const parts: string[] = []
  let current = ''
  let inQuotes = false
  for (let i = 0; i < raw.length; i += 1) {
    const ch = raw[i]
    if (ch === '"') {
      if (inQuotes && raw[i + 1] === '"') {
        current += '"'
        i += 1
      } else {
        inQuotes = !inQuotes
      }
      continue
    }
    if (ch === ',' && !inQuotes) {
      parts.push(current.trim())
      current = ''
      continue
    }
    current += ch
  }
  parts.push(current.trim())
  return parts
}

export function parseStudentCsv(input: string): { rows: StudentCsvRow[]; issues: StudentCsvParseIssue[] } {
  const issues: StudentCsvParseIssue[] = []
  const rows: StudentCsvRow[] = []

  const lines = input.split(/\r?\n/)
  for (let index = 0; index < lines.length; index += 1) {
    const raw = String(lines[index] ?? '').trim()
    const line = index + 1
    if (!raw) continue
    if (isHeaderRow(raw)) continue

    const parts = splitCsvLine(raw)
    const values: Record<StudentCsvColumnKey, string> = {
      email: '',
      studentName: '',
      studentNumber: '',
      course: '',
      yearLevel: '',
    }
    STUDENT_CSV_COLUMNS.forEach((column, columnIndex) => {
      values[column.key] = String(parts[columnIndex] ?? '').trim()
    })

    if (!values.email) {
      issues.push({ line, message: 'Missing email address.' })
      continue
    }

    rows.push({
      line,
      email: values.email,
      studentName: values.studentName,
      studentNumber: values.studentNumber,
      course: values.course,
      yearLevel: values.yearLevel,
    })
  }

  return { rows, issues }
}

export function applyStudentCsvDefaults(
  row: StudentCsvRow,
  defaults: { course?: string; yearLevel?: string },
): StudentCsvRow {
  return {
    ...row,
    course: row.course || String(defaults.course || '').trim(),
    yearLevel: row.yearLevel || String(defaults.yearLevel || '').trim(),
  }
}

export function isDuplicateStudentCsvRow(
  row: StudentCsvRow,
  existing: Array<{ email?: string | null; studentNumber?: string | null }>,
): boolean {
  const email = row.email.trim().toLowerCase()
  const number = row.studentNumber.trim().toLowerCase()
  return existing.some((student) => {
    const existingEmail = String(student.email || '')
      .trim()
      .toLowerCase()
    if (email && existingEmail && existingEmail === email) return true
    const existingNumber = String(student.studentNumber || '')
      .trim()
      .toLowerCase()
    if (number && existingNumber && existingNumber === number) return true
    return false
  })
}

export function downloadStudentCsvTemplate(filename = 'school-students-import-template.csv'): void {
  const blob = new Blob([buildStudentCsvTemplate()], { type: 'text/csv;charset=utf-8' })
  const objectUrl = URL.createObjectURL(blob)
  const link = document.createElement('a')
  link.href = objectUrl
  link.download = filename
  document.body.appendChild(link)
  link.click()
  document.body.removeChild(link)
  URL.revokeObjectURL(objectUrl)
}

export function formatFileSize(bytes: number): string {
  if (!Number.isFinite(bytes) || bytes < 0) return '0 B'
  if (bytes < 1024) return `${bytes} B`
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`
  return `${(bytes / (1024 * 1024)).toFixed(1)} MB`
}
