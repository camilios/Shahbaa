import assert from 'node:assert/strict';
import test from 'node:test';
import { MemoryLocationStore } from '../src/services/location-store.js';
import { TrackingService } from '../src/services/tracking.service.js';

test('tracking transitions through stale and offline using one monitor', () => {
  let now = Date.parse('2026-07-21T10:00:00Z');
  const events = [];
  const io = { to: () => ({ emit: (event, payload) => events.push([event, payload]) }) };
  const store = new MemoryLocationStore({ ttlMs: 120_000, now: () => now });
  const service = new TrackingService({
    io,
    locationStore: store,
    staleAfterMs: 30_000,
    offlineAfterMs: 90_000,
    now: () => now,
  });

  service.startPublishing(1, 'driver-socket');
  now += 30_000;
  service.checkStatuses();
  assert.equal(service.currentStatus(1), 'stale');
  now += 60_000;
  service.checkStatuses();
  assert.equal(service.currentStatus(1), 'offline');
  assert.deepEqual(events.map(([, payload]) => payload.status), ['online', 'stale', 'offline']);
});

test('disconnect keeps the latest location and announces offline', () => {
  const io = { to: () => ({ emit: () => {} }) };
  const store = new MemoryLocationStore({ ttlMs: 120_000 });
  const service = new TrackingService({ io, locationStore: store, staleAfterMs: 30_000, offlineAfterMs: 90_000 });
  service.startPublishing(1, 'driver-socket');
  service.publish(1, 'driver-socket', {
    latitude: 32,
    longitude: 36,
    recordedAt: new Date().toISOString(),
  });
  service.disconnectPublisher('driver-socket');
  assert.equal(service.currentStatus(1), 'offline');
  assert.ok(store.getLatest(1));
});
