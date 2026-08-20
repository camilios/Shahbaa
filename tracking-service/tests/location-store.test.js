import assert from 'node:assert/strict';
import test from 'node:test';
import { MemoryLocationStore } from '../src/services/location-store.js';

test('memory store returns the latest location until its TTL expires', () => {
  let now = 1_000;
  const store = new MemoryLocationStore({ ttlMs: 100, now: () => now });
  store.setLatest(12, { latitude: 1 });
  assert.deepEqual(store.getLatest(12), { latitude: 1 });
  now = 1_101;
  assert.equal(store.getLatest(12), null);
});

test('memory store deletes a location explicitly', () => {
  const store = new MemoryLocationStore({ ttlMs: 100 });
  store.setLatest(12, { latitude: 1 });
  store.deleteLatest(12);
  assert.equal(store.getLatest(12), null);
});

test('cleanup reports the trip ids whose locations expired', () => {
  let now = 1_000;
  const store = new MemoryLocationStore({ ttlMs: 100, now: () => now });
  store.setLatest(12, { latitude: 1 });
  now = 1_101;
  assert.deepEqual(store.cleanup(), [12]);
  assert.deepEqual(store.cleanup(), []);
});
