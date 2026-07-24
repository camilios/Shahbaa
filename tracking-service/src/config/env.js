const integer = (value, fallback, name, minimum = 1) => {
  const parsed = Number.parseInt(value ?? String(fallback), 10);
  if (!Number.isInteger(parsed) || parsed < minimum) {
    throw new Error(`${name} must be an integer greater than or equal to ${minimum}`);
  }
  return parsed;
};

export function loadConfig(source = process.env) {
  const nodeEnv = source.NODE_ENV ?? 'development';
  const serviceSecret = source.TRACKING_SERVICE_SECRET ?? '';
  if (!serviceSecret && nodeEnv !== 'test') {
    throw new Error('TRACKING_SERVICE_SECRET is required');
  }

  const allowedOrigins = (source.ALLOWED_ORIGINS ?? 'http://localhost:5173')
    .split(',')
    .map((origin) => origin.trim())
    .filter(Boolean);

  return {
    nodeEnv,
    port: integer(source.PORT, 4000, 'PORT'),
    laravelApiUrl: (source.LARAVEL_API_URL ?? 'http://127.0.0.1:8000').replace(/\/$/, ''),
    serviceSecret,
    allowedOrigins,
    locationTtlMs: integer(source.LOCATION_TTL_SECONDS, 120, 'LOCATION_TTL_SECONDS') * 1000,
    staleAfterMs: integer(source.TRACKING_STALE_AFTER_SECONDS, 30, 'TRACKING_STALE_AFTER_SECONDS') * 1000,
    offlineAfterMs: integer(source.TRACKING_OFFLINE_AFTER_SECONDS, 90, 'TRACKING_OFFLINE_AFTER_SECONDS') * 1000,
    laravelTimeoutMs: integer(source.LARAVEL_REQUEST_TIMEOUT_MS, 5000, 'LARAVEL_REQUEST_TIMEOUT_MS'),
    maxUpdatesPerMinute: integer(source.MAX_LOCATION_UPDATES_PER_MINUTE, 12, 'MAX_LOCATION_UPDATES_PER_MINUTE'),
    maxLocationAgeMs: integer(source.MAX_LOCATION_AGE_SECONDS, 120, 'MAX_LOCATION_AGE_SECONDS') * 1000,
    maxLocationFutureMs: integer(source.MAX_LOCATION_FUTURE_SECONDS, 15, 'MAX_LOCATION_FUTURE_SECONDS') * 1000,
  };
}
