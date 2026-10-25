#!/usr/bin/env node
/**
 * The MDX Lens worker (ux-0233 d2), run by {@see NodeMdxLens}.
 *
 * Reads JSON on stdin and writes JSON on stdout, exiting non-zero with a human diagnostic on stderr on any failure:
 *   { op: 'read',  body, root, context? }  →  { doc, meta }   (an id-less JsonDoc, and any `export const meta`)
 *   { op: 'print', doc,  root }            →  { mdx }         (canonical MDX)
 *
 * Like beam-ux's TSX Lens worker (`laravel-beam-ux/resources/lens/tsx-to-blocks.mjs`) it is a plain script: PHP owns
 * the orchestration (which entry, the Working Head, the id rematch); this runs the one thing PHP cannot, the MDX
 * Lens in `@splicewire/beam-mdx/lens`. That package resolves from the HOST's node_modules, never from anything this
 * package vendors, through the package's own `exports` map (a Composer path repo symlinks this package away from
 * the host, so a bare import would not find the host's copy).
 */
import { readFileSync } from 'node:fs'
import { join } from 'node:path'
import { pathToFileURL } from 'node:url'

const read = (stream) =>
  new Promise((resolve, reject) => {
    let buffer = ''
    stream.setEncoding('utf8')
    stream.on('data', (chunk) => (buffer += chunk))
    stream.on('end', () => resolve(buffer))
    stream.on('error', reject)
  })

const fail = (message) => {
  process.stderr.write(`${message}\n`)
  process.exit(1)
}

const load = async (specifier, root) => {
  try {
    const segments = specifier.split('/')
    const pkg = specifier.startsWith('@') ? segments.slice(0, 2).join('/') : segments[0]
    const tail = segments.slice(specifier.startsWith('@') ? 2 : 1).join('/')
    const subpath = tail === '' ? '.' : `./${tail}`

    const pkgDir = join(root, 'node_modules', pkg)
    const manifest = JSON.parse(readFileSync(join(pkgDir, 'package.json'), 'utf8'))
    const entry = manifest.exports?.[subpath]
    const relative = typeof entry === 'string' ? entry : (entry?.import ?? entry?.default)
    if (typeof relative !== 'string') {
      throw new Error(`[${pkg}] does not export [${subpath}]`)
    }

    return await import(pathToFileURL(join(pkgDir, relative)).href)
  } catch (error) {
    fail(
      `beam-mdx lens: cannot resolve [${specifier}] from the host at ${root} (${error.message}). ` +
        `Install it in the host (npm i ${specifier}) — the lens runs with the host's toolchain.`,
    )
  }
}

const main = async () => {
  let input
  try {
    input = JSON.parse(await read(process.stdin))
  } catch (error) {
    fail(`beam-mdx lens: could not parse stdin as JSON (${error.message}).`)
  }

  const { op, root } = input ?? {}
  if (typeof root !== 'string' || (op !== 'read' && op !== 'print')) {
    fail('beam-mdx lens: stdin must be {"op": "read"|"print", "root": string, …}.')
  }
  if (op === 'read' && typeof input.body !== 'string') fail('beam-mdx lens: a read needs {"body": string}.')
  if (op === 'print' && !Array.isArray(input.doc)) fail('beam-mdx lens: a print needs {"doc": array}.')

  const { readMdx, printMdx } = await load('@splicewire/beam-mdx/lens', root)

  try {
    if (op === 'read') {
      const { doc, meta } = readMdx(input.body, input.context ?? {})
      process.stdout.write(JSON.stringify({ doc, meta }))
    } else {
      process.stdout.write(JSON.stringify({ mdx: printMdx(input.doc) }))
    }
  } catch (error) {
    // A read failure is the author's MDX, not an internal fault: report it for a 422.
    fail(`beam-mdx lens: could not ${op === 'read' ? 'read the MDX into blocks' : 'print the blocks as MDX'} (${error.message}).`)
  }
}

main().catch((error) => fail(`beam-mdx lens: ${error?.message ?? error}`))
