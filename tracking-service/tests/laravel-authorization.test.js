import assert from 'node:assert/strict';
import test from 'node:test';
import { LaravelAuthorizationService } from '../src/services/laravel-authorization.service.js';

test('Laravel failure is converted to LARAVEL_UNAVAILABLE', async () => {
  const service = new LaravelAuthorizationService({
    apiUrl: 'http://laravel.test',
    serviceSecret: 'secret',
    timeoutMs: 100,
    fetchImpl: async () => { throw new Error('network down'); },
  });
  await assert.rejects(
    service.authorize({ token: 'token', tripId: 1, action: 'publish' }),
    (error) => error.code === 'LARAVEL_UNAVAILABLE',
  );
});

test('Laravel authorization forwards only trusted authorization inputs', async () => {
  let request;
  const service = new LaravelAuthorizationService({
    apiUrl: 'http://laravel.test',
    serviceSecret: 'secret',
    timeoutMs: 100,
    fetchImpl: async (url, options) => {
      request = { url, options };
      return { ok: true, json: async () => ({ authorized: true, user_id: 2 }) };
    },
  });
  await service.authorize({ token: 'bearer-token', tripId: 8, action: 'publish' });
  assert.equal(request.options.headers.Authorization, 'Bearer bearer-token');
  assert.equal(request.options.headers['X-Tracking-Service-Key'], 'secret');
  assert.deepEqual(JSON.parse(request.options.body), { trip_id: 8, action: 'publish' });
});
