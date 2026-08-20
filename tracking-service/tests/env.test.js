import assert from 'node:assert/strict';
import test from 'node:test';
import { loadConfig } from '../src/config/env.js';

test('tracking timing configuration must be ordered consistently', () => {
  assert.throws(
    () => loadConfig({ NODE_ENV: 'test', TRACKING_STALE_AFTER_SECONDS: '90', TRACKING_OFFLINE_AFTER_SECONDS: '90' }),
    /STALE.*less than.*OFFLINE/,
  );
  assert.throws(
    () => loadConfig({ NODE_ENV: 'test', LOCATION_TTL_SECONDS: '89', TRACKING_OFFLINE_AFTER_SECONDS: '90' }),
    /LOCATION_TTL.*greater than or equal to.*OFFLINE/,
  );
});

test('authorization refresh interval is configurable', () => {
  const config = loadConfig({ NODE_ENV: 'test', AUTHORIZATION_REFRESH_SECONDS: '15' });
  assert.equal(config.authorizationRefreshMs, 15_000);
});
