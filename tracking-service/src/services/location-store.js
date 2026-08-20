export class MemoryLocationStore {
  constructor({ ttlMs, now = () => Date.now() }) {
    this.ttlMs = ttlMs;
    this.now = now;
    this.locations = new Map();
  }

  setLatest(tripId, location) {
    this.locations.set(Number(tripId), {
      location,
      expiresAt: this.now() + this.ttlMs,
    });
  }

  getLatest(tripId) {
    const key = Number(tripId);
    const entry = this.locations.get(key);
    if (!entry) return null;
    if (entry.expiresAt <= this.now()) {
      this.locations.delete(key);
      return null;
    }
    return entry.location;
  }

  deleteLatest(tripId) {
    this.locations.delete(Number(tripId));
  }

  cleanup() {
    const expiredTripIds = [];
    for (const [tripId, entry] of this.locations) {
      if (entry.expiresAt <= this.now()) {
        this.locations.delete(tripId);
        expiredTripIds.push(tripId);
      }
    }
    return expiredTripIds;
  }
}
