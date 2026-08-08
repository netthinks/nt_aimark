/**
 * Minimal static file server for the accessibility run.
 *
 * The fixture loads the detail-panel script as an ES module, and browsers
 * refuse to load modules over file:// — the page would silently come up
 * without its JavaScript and the keyboard tests would pass for the wrong
 * reason. Serving over HTTP is what makes them mean something.
 */
import { createServer } from 'node:http';
import { readFile } from 'node:fs/promises';
import path from 'node:path';

const ROOT = path.resolve(process.argv[2] ?? '.');
const PORT = Number(process.env.PORT ?? 8099);

const TYPES = {
  '.html': 'text/html; charset=utf-8',
  '.css': 'text/css; charset=utf-8',
  '.js': 'text/javascript; charset=utf-8',
  '.mjs': 'text/javascript; charset=utf-8',
  '.svg': 'image/svg+xml',
};

createServer(async (request, response) => {
  const requested = decodeURIComponent(new URL(request.url, 'http://localhost').pathname);
  const target = path.join(ROOT, requested);

  // Nothing outside the served root, whatever the path claims to be.
  if (!target.startsWith(ROOT)) {
    response.writeHead(403).end('Forbidden');
    return;
  }

  try {
    const body = await readFile(target);
    response.writeHead(200, { 'Content-Type': TYPES[path.extname(target)] ?? 'application/octet-stream' });
    response.end(body);
  } catch {
    response.writeHead(404).end('Not found');
  }
}).listen(PORT, () => {
  console.log(`Serving ${ROOT} on http://127.0.0.1:${PORT}`);
});
