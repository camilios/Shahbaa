import cors from 'cors';
import express from 'express';
import rateLimit from 'express-rate-limit';
import helmet from 'helmet';

export function createApp(config) {
  const app = express();
  app.disable('x-powered-by');
  app.use(helmet());
  app.use(cors({ origin: config.allowedOrigins }));
  app.use(express.json({ limit: '10kb' }));
  app.use(rateLimit({ windowMs: 60_000, limit: 120, standardHeaders: true, legacyHeaders: false }));

  app.get('/health', (_request, response) => response.json({
    status: 'ok',
    service: 'tracking-service',
    timestamp: new Date().toISOString(),
  }));

  return app;
}
