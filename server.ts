import http from 'http';
import { spawn, execSync } from 'child_process';

const PORT = 3000;
const PHP_PORT = 8088;

// 1. Ensure MariaDB service is running
try {
  execSync('service mariadb status >/dev/null 2>&1 || service mariadb start >/dev/null 2>&1 || true');
} catch {
  // Non-blocking
}

// 2. Ensure database is initialized
try {
  execSync('php database/setup.php >/dev/null 2>&1 || true');
} catch {
  // Non-blocking
}

// 3. Spawn PHP built-in server on internal port 8088
const phpServer = spawn('php', ['-S', `127.0.0.1:${PHP_PORT}`, '-t', 'public', 'router.php'], {
  stdio: 'inherit',
});

process.on('exit', () => phpServer.kill());
process.on('SIGINT', () => {
  phpServer.kill();
  process.exit();
});
process.on('SIGTERM', () => {
  phpServer.kill();
  process.exit();
});

// 4. HTTP Reverse Proxy to forward all traffic to PHP server
const server = http.createServer((req, res) => {
  const options: http.RequestOptions = {
    hostname: '127.0.0.1',
    port: PHP_PORT,
    path: req.url,
    method: req.method,
    headers: {
      ...req.headers,
      host: `127.0.0.1:${PHP_PORT}`,
      'x-forwarded-host': req.headers.host || `127.0.0.1:${PORT}`,
      'x-forwarded-proto': 'http',
      'x-forwarded-for': req.socket.remoteAddress || '127.0.0.1',
    },
  };

  const proxyReq = http.request(options, (proxyRes) => {
    res.writeHead(proxyRes.statusCode || 200, proxyRes.headers);
    proxyRes.pipe(res);
  });

  proxyReq.on('error', () => {
    res.writeHead(502, { 'Content-Type': 'text/html' });
    res.end('<!DOCTYPE html><html><head><meta http-equiv="refresh" content="2"></head><body style="font-family:sans-serif;padding:2rem;text-align:center;"><h2>Starting N.A Fresh Fruits &amp; Coconuts...</h2><p>Please wait a moment while the PHP services initialize.</p></body></html>');
  });

  req.pipe(proxyReq);
});

server.listen(PORT, '0.0.0.0', () => {
  console.log(`[N.A Fresh Fruits & Coconuts] Gateway active on http://0.0.0.0:${PORT} -> PHP :${PHP_PORT}`);
});
