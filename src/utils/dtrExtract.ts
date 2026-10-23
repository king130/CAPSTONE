import { getDocument, GlobalWorkerOptions } from 'pdfjs-dist'
import pdfWorker from 'pdfjs-dist/build/pdf.worker.min.mjs?url'
import Tesseract from 'tesseract.js'

import { parseDtrText, type DtrSuggestion } from '@/utils/dtrText'

GlobalWorkerOptions.workerSrc = pdfWorker

const emptySuggestion: DtrSuggestion = {
  hours: null,
  periodStart: null,
  periodEnd: null,
}

let ocrWorker: Promise<Tesseract.Worker> | null = null

function ocr() {
  if (!ocrWorker) {
    ocrWorker = Tesseract.createWorker('eng')
  }
  return ocrWorker
}

export async function extractDtrSuggestions(file: File): Promise<DtrSuggestion> {
  const name = file.name.toLowerCase()
  const type = file.type.toLowerCase()
  if (type.startsWith('image/') || /\.(png|jpe?g|webp|gif)$/.test(name)) {
    return parseDtrText(await recognize(file))
  }
  if (type === 'application/pdf' || name.endsWith('.pdf')) {
    return readPdf(file)
  }
  return emptySuggestion
}

async function readPdf(file: File): Promise<DtrSuggestion> {
  const data = new Uint8Array(await file.arrayBuffer())
  const pdf = await getDocument({ data }).promise
  const pageCount = Math.min(pdf.numPages, 2)
  let text = ''
  for (let pageNumber = 1; pageNumber <= pageCount; pageNumber += 1) {
    const page = await pdf.getPage(pageNumber)
    const content = await page.getTextContent()
    text += content.items.map((item) => ('str' in item ? item.str : '')).join(' ')
    text += '\n'
  }

  const fromText = parseDtrText(text)
  if (fromText.hours != null && fromText.periodStart && fromText.periodEnd) {
    return fromText
  }
  if (text.replace(/\s/g, '').length >= 80 && (fromText.hours != null || fromText.periodStart)) {
    return fromText
  }

  let ocrText = ''
  for (let pageNumber = 1; pageNumber <= pageCount; pageNumber += 1) {
    const page = await pdf.getPage(pageNumber)
    const viewport = page.getViewport({ scale: 2 })
    const canvas = document.createElement('canvas')
    canvas.width = Math.ceil(viewport.width)
    canvas.height = Math.ceil(viewport.height)
    const context = canvas.getContext('2d')
    if (!context) continue
    await page.render({ canvasContext: context, viewport }).promise
    ocrText += `${await recognize(canvas)}\n`
  }

  const fromOcr = parseDtrText(`${text}\n${ocrText}`)
  if (fromOcr.hours != null || fromOcr.periodStart || fromOcr.periodEnd) return fromOcr
  return fromText
}

async function recognize(image: File | HTMLCanvasElement): Promise<string> {
  const worker = await ocr()
  const result = await worker.recognize(image)
  return result.data.text ?? ''
}
