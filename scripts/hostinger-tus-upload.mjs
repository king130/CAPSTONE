import fs from 'node:fs'
import path from 'node:path'
import https from 'node:https'

const urlBase = process.env.HOSTINGER_TUS_URL
const auth = process.env.HOSTINGER_TUS_AUTH
const rest = process.env.HOSTINGER_TUS_REST
const root = process.env.HOSTINGER_UPLOAD_ROOT
const maxAttempts = Math.max(1, Number(process.env.HOSTINGER_TUS_RETRIES || 5))
if (!urlBase || !auth || !rest || !root) {
  console.error('Missing HOSTINGER_TUS_URL / HOSTINGER_TUS_AUTH / HOSTINGER_TUS_REST / HOSTINGER_UPLOAD_ROOT')
  process.exit(1)
}

const httpsAgent = new https.Agent({
  keepAlive: true,
  timeout: 120_000,
  maxSockets: 4,
})

function walk(dir, base = dir, out = []) {
  for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
    const full = path.join(dir, entry.name)
    if (entry.isDirectory()) walk(full, base, out)
    else out.push(path.relative(base, full).replaceAll('\\', '/'))
  }
  return out
}

function sleep(ms) {
  return new Promise((resolve) => setTimeout(resolve, ms))
}

function request(method, targetUrl, headers, body) {
  return new Promise((resolve, reject) => {
    const u = new URL(targetUrl)
    const req = https.request(
      {
        protocol: u.protocol,
        hostname: u.hostname,
        port: u.port || 443,
        path: `${u.pathname}${u.search}`,
        method,
        headers,
        agent: httpsAgent,
        timeout: 180_000,
      },
      (res) => {
        const chunks = []
        res.on('data', (c) => chunks.push(c))
        res.on('end', () => {
          resolve({
            status: res.statusCode || 0,
            headers: res.headers,
            body: Buffer.concat(chunks).toString('utf8'),
          })
        })
      },
    )
    req.on('timeout', () => {
      req.destroy(new Error(`timeout ${method} ${u.pathname}`))
    })
    req.on('error', reject)
    if (body) req.write(body)
    req.end()
  })
}

async function tusUploadOnce(relPath) {
  const abs = path.join(root, relPath)
  const size = fs.statSync(abs).size
  const target = `${urlBase.replace(/\/$/, '')}/${relPath}?override=true`
  const headers = {
    'X-Auth': auth,
    'X-Auth-Rest': rest,
    'Tus-Resumable': '1.0.0',
  }

  const create = await request('POST', target, {
    ...headers,
    'Upload-Length': String(size),
    'Upload-Offset': '0',
  })
  if (![200, 201, 204].includes(create.status)) {
    throw new Error(`POST ${relPath} -> ${create.status} ${create.body.slice(0, 200)}`)
  }

  const buf = fs.readFileSync(abs)
  const patch = await request(
    'PATCH',
    target,
    {
      ...headers,
      'Content-Type': 'application/offset+octet-stream',
      'Upload-Offset': '0',
      'Content-Length': String(buf.length),
    },
    buf,
  )
  if (![200, 204].includes(patch.status)) {
    throw new Error(`PATCH ${relPath} -> ${patch.status} ${patch.body.slice(0, 200)}`)
  }
  const offset = patch.headers['upload-offset']
  if (offset && Number(offset) !== size) {
    throw new Error(`Incomplete ${relPath}: offset ${offset}/${size}`)
  }
  return size
}

async function tusUpload(relPath) {
  let lastError
  for (let attempt = 1; attempt <= maxAttempts; attempt += 1) {
    try {
      return await tusUploadOnce(relPath)
    } catch (error) {
      lastError = error
      if (attempt >= maxAttempts) break
      const waitMs = attempt * 2500
      process.stdout.write(`retry ${attempt}/${maxAttempts - 1} after ${waitMs}ms ... `)
      await sleep(waitMs)
    }
  }
  throw lastError
}

const files = walk(root)
console.log(`Uploading ${files.length} files from ${root}`)
let ok = 0
for (const rel of files) {
  process.stdout.write(`  ${rel} ... `)
  const size = await tusUpload(rel)
  console.log(`${size} bytes`)
  ok += 1
}
console.log(`Done ${ok}/${files.length}`)
