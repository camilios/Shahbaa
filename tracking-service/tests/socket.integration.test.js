import assert from 'node:assert/strict';
import { createServer } from 'node:http';
import test from 'node:test';
import { io as connect } from 'socket.io-client';
import { createApp } from '../src/app.js';
import { MemoryLocationStore } from '../src/services/location-store.js';
import { TrackingService } from '../src/services/tracking.service.js';
import { createSocketServer } from '../src/socket/index.js';

const emitAck = (socket, event, payload) => new Promise((resolve) => socket.emit(event, payload, resolve));
const nextEvent = (socket, event) => new Promise((resolve) => socket.once(event, resolve));

async function fixture(t, overrides = {}) {
  const config = {
    allowedOrigins: ['http://localhost:5173'],
    locationTtlMs: 120_000,
    staleAfterMs: 30_000,
    offlineAfterMs: 90_000,
    maxUpdatesPerMinute: 12,
    maxLocationAgeMs: 120_000,
    maxLocationFutureMs: 15_000,
    authorizationRefreshMs: 30_000,
    ...overrides,
  };
  const app = createApp(config);
  const httpServer = createServer(app);
  const locationStore = new MemoryLocationStore({ ttlMs: config.locationTtlMs });
  const revokedTokens = new Set();
  const authorizationService = {
    async authorize({ token, tripId, action }) {
      if (revokedTokens.has(token)) {
        const error = new Error('denied');
        error.code = 'UNAUTHENTICATED';
        throw error;
      }
      if (token === 'driver-1' && [1, 2].includes(tripId) && action === 'publish') return { user_id: 10 };
      if (token.startsWith('admin') && action === 'subscribe') return { user_id: 1 };
      const error = new Error('denied');
      error.code = 'UNAUTHORIZED_TRIP';
      throw error;
    },
  };
  const dependencies = { config, locationStore, authorizationService };
  const io = createSocketServer(httpServer, dependencies);
  dependencies.trackingService = new TrackingService({
    io,
    locationStore,
    staleAfterMs: config.staleAfterMs,
    offlineAfterMs: config.offlineAfterMs,
  });
  await new Promise((resolve) => httpServer.listen(0, '127.0.0.1', resolve));
  const url = `http://127.0.0.1:${httpServer.address().port}`;
  const clients = [];
  const client = (token) => {
    const socket = connect(url, { auth: token ? { token } : {}, transports: ['websocket'], forceNew: true });
    clients.push(socket);
    return socket;
  };
  t.after(async () => {
    clients.forEach((socket) => socket.close());
    await new Promise((resolve) => io.close(resolve));
    if (httpServer.listening) await new Promise((resolve) => httpServer.close(resolve));
  });
  return { client, url, revokedTokens, trackingService: dependencies.trackingService };
}

test('health endpoint reports the service without internal details', async (t) => {
  const { url } = await fixture(t);
  const response = await fetch(`${url}/health`);
  assert.equal(response.status, 200);
  const payload = await response.json();
  assert.equal(payload.status, 'ok');
  assert.equal(payload.service, 'tracking-service');
  assert.ok(payload.timestamp);
});

test('connection without token is rejected', async (t) => {
  const { client } = await fixture(t);
  const socket = client();
  const error = await nextEvent(socket, 'connect_error');
  assert.equal(error.data.code, 'UNAUTHENTICATED');
});

test('unauthorized publish start is rejected', async (t) => {
  const { client } = await fixture(t);
  const socket = client('another-driver');
  await nextEvent(socket, 'connect');
  const result = await emitAck(socket, 'location:publish:start', { tripId: 1 });
  assert.equal(result.error.code, 'UNAUTHORIZED_TRIP');
});

test('valid location reaches only the matching trip and late admin gets latest location', async (t) => {
  const { client } = await fixture(t);
  const driver = client('driver-1');
  const adminOne = client('admin-1');
  const adminTwo = client('admin-2');
  await Promise.all([nextEvent(driver, 'connect'), nextEvent(adminOne, 'connect'), nextEvent(adminTwo, 'connect')]);
  await emitAck(adminOne, 'trip:subscribe', { tripId: 1 });
  await emitAck(adminTwo, 'trip:subscribe', { tripId: 2 });
  await emitAck(driver, 'location:publish:start', { tripId: 1 });

  let wrongRoomReceived = false;
  adminTwo.on('trip:location', () => { wrongRoomReceived = true; });
  const received = nextEvent(adminOne, 'trip:location');
  const recordedAt = new Date().toISOString();
  const result = await emitAck(driver, 'location:update', {
    latitude: 32.7102,
    longitude: 36.5651,
    speed: 62.5,
    heading: 175,
    accuracy: 10,
    recordedAt,
  });
  assert.equal(result.ok, true);
  assert.equal((await received).tripId, 1);
  await new Promise((resolve) => setTimeout(resolve, 30));
  assert.equal(wrongRoomReceived, false);

  const lateAdmin = client('admin-late');
  await nextEvent(lateAdmin, 'connect');
  const latest = nextEvent(lateAdmin, 'trip:location');
  await emitAck(lateAdmin, 'trip:subscribe', { tripId: 1 });
  assert.equal((await latest).recordedAt, recordedAt);
});

test('invalid and older locations are rejected', async (t) => {
  const { client } = await fixture(t);
  const driver = client('driver-1');
  await nextEvent(driver, 'connect');
  await emitAck(driver, 'location:publish:start', { tripId: 1 });
  let result = await emitAck(driver, 'location:update', {
    latitude: 100,
    longitude: 36,
    recordedAt: new Date().toISOString(),
  });
  assert.equal(result.error.code, 'INVALID_LOCATION');

  const newer = new Date().toISOString();
  await emitAck(driver, 'location:update', { latitude: 32, longitude: 36, recordedAt: newer });
  result = await emitAck(driver, 'location:update', {
    latitude: 32,
    longitude: 36,
    recordedAt: new Date(Date.parse(newer) - 1000).toISOString(),
  });
  assert.equal(result.error.code, 'INVALID_LOCATION');
});

test('location update rate limit is enforced', async (t) => {
  const { client } = await fixture(t, { maxUpdatesPerMinute: 1 });
  const driver = client('driver-1');
  await nextEvent(driver, 'connect');
  await emitAck(driver, 'location:publish:start', { tripId: 1 });
  const firstTime = new Date().toISOString();
  await emitAck(driver, 'location:update', { latitude: 32, longitude: 36, recordedAt: firstTime });
  const result = await emitAck(driver, 'location:update', {
    latitude: 32.1,
    longitude: 36.1,
    recordedAt: new Date(Date.parse(firstTime) + 1000).toISOString(),
  });
  assert.equal(result.error.code, 'RATE_LIMITED');
});

test('intentional stop broadcasts stopped and keeps the last location', async (t) => {
  const { client } = await fixture(t);
  const driver = client('driver-1');
  const admin = client('admin-1');
  await Promise.all([nextEvent(driver, 'connect'), nextEvent(admin, 'connect')]);
  await emitAck(admin, 'trip:subscribe', { tripId: 1 });
  await emitAck(driver, 'location:publish:start', { tripId: 1 });
  await emitAck(driver, 'location:update', {
    latitude: 32,
    longitude: 36,
    recordedAt: new Date().toISOString(),
  });
  const stopped = new Promise((resolve) => {
    const listener = (payload) => {
      if (payload.status === 'stopped') {
        admin.off('tracking:status', listener);
        resolve(payload);
      }
    };
    admin.on('tracking:status', listener);
  });
  await emitAck(driver, 'location:publish:stop', {});
  assert.equal((await stopped).status, 'stopped');

  const lateAdmin = client('admin-late');
  await nextEvent(lateAdmin, 'connect');
  const latest = nextEvent(lateAdmin, 'trip:location');
  await emitAck(lateAdmin, 'trip:subscribe', { tripId: 1 });
  assert.equal((await latest).tripId, 1);
});

test('starting another trip stops the previous trip instead of leaving a ghost publisher', async (t) => {
  const { client } = await fixture(t);
  const driver = client('driver-1');
  const admin = client('admin-1');
  await Promise.all([nextEvent(driver, 'connect'), nextEvent(admin, 'connect')]);
  await Promise.all([
    emitAck(admin, 'trip:subscribe', { tripId: 1 }),
    emitAck(admin, 'trip:subscribe', { tripId: 2 }),
  ]);
  await emitAck(driver, 'location:publish:start', { tripId: 1 });

  const stopped = new Promise((resolve) => {
    const listener = (payload) => {
      if (payload.tripId === 1 && payload.status === 'stopped') {
        admin.off('tracking:status', listener);
        resolve(payload);
      }
    };
    admin.on('tracking:status', listener);
  });
  await emitAck(driver, 'location:publish:start', { tripId: 2 });
  assert.equal((await stopped).status, 'stopped');
});

test('revoked credentials remove publishers and subscribers during periodic reauthorization', async (t) => {
  const { client, revokedTokens } = await fixture(t, { authorizationRefreshMs: 20 });
  const driver = client('driver-1');
  const admin = client('admin-1');
  await Promise.all([nextEvent(driver, 'connect'), nextEvent(admin, 'connect')]);
  await emitAck(admin, 'trip:subscribe', { tripId: 1 });
  await emitAck(driver, 'location:publish:start', { tripId: 1 });

  const driverError = nextEvent(driver, 'tracking:error');
  const adminError = nextEvent(admin, 'tracking:error');
  revokedTokens.add('driver-1');
  revokedTokens.add('admin-1');
  assert.equal((await driverError).code, 'UNAUTHENTICATED');
  assert.equal((await adminError).code, 'UNAUTHENTICATED');

  const result = await emitAck(driver, 'location:update', {
    latitude: 32,
    longitude: 36,
    recordedAt: new Date().toISOString(),
  });
  assert.equal(result.error.code, 'TRACKING_NOT_STARTED');
});

test('expired locations are removed from connected admin dashboards', async (t) => {
  const { client, trackingService } = await fixture(t, {
    locationTtlMs: 25,
    staleAfterMs: 10,
    offlineAfterMs: 20,
  });
  const driver = client('driver-1');
  const admin = client('admin-1');
  await Promise.all([nextEvent(driver, 'connect'), nextEvent(admin, 'connect')]);
  await emitAck(admin, 'trip:subscribe', { tripId: 1 });
  await emitAck(driver, 'location:publish:start', { tripId: 1 });
  await emitAck(driver, 'location:update', {
    latitude: 32,
    longitude: 36,
    recordedAt: new Date().toISOString(),
  });

  const expired = nextEvent(admin, 'trip:location:expired');
  await new Promise((resolve) => setTimeout(resolve, 30));
  trackingService.checkStatuses();
  assert.equal((await expired).tripId, 1);
  // A late subscription proves the store no longer returns the expired point.
  const lateAdmin = client('admin-late');
  await nextEvent(lateAdmin, 'connect');
  let receivedLocation = false;
  lateAdmin.on('trip:location', () => { receivedLocation = true; });
  await emitAck(lateAdmin, 'trip:subscribe', { tripId: 1 });
  await new Promise((resolve) => setTimeout(resolve, 10));
  assert.equal(receivedLocation, false);
});
