import { createServer } from 'node:http';
import 'dotenv/config';
import { createApp } from './app.js';
import { loadConfig } from './config/env.js';
import { LaravelAuthorizationService } from './services/laravel-authorization.service.js';
import { MemoryLocationStore } from './services/location-store.js';
import { TrackingService } from './services/tracking.service.js';
import { createSocketServer } from './socket/index.js';
import { logger } from './utils/logger.js';

const config = loadConfig();
const app = createApp(config);
const httpServer = createServer(app);
const locationStore = new MemoryLocationStore({ ttlMs: config.locationTtlMs });
const authorizationService = new LaravelAuthorizationService({
  apiUrl: config.laravelApiUrl,
  serviceSecret: config.serviceSecret,
  timeoutMs: config.laravelTimeoutMs,
});
const dependencies = { config, locationStore, authorizationService };
const io = createSocketServer(httpServer, dependencies);
const trackingService = new TrackingService({
  io,
  locationStore,
  staleAfterMs: config.staleAfterMs,
  offlineAfterMs: config.offlineAfterMs,
});
dependencies.trackingService = trackingService;

const monitorInterval = setInterval(
  () => trackingService.checkStatuses(),
  Math.min(5000, config.staleAfterMs),
);
monitorInterval.unref();

httpServer.listen(config.port, () => {
  logger.info('Tracking service started', { port: config.port, environment: config.nodeEnv });
});

let shuttingDown = false;
const shutdown = (signal) => {
  if (shuttingDown) return;
  shuttingDown = true;
  logger.info('Tracking service shutting down', { signal });
  clearInterval(monitorInterval);
  io.close(() => httpServer.close(() => process.exit(0)));
  setTimeout(() => process.exit(1), 10_000).unref();
};

process.on('SIGINT', () => shutdown('SIGINT'));
process.on('SIGTERM', () => shutdown('SIGTERM'));
