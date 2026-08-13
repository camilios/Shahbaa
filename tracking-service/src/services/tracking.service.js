const roomFor = (tripId) => `trip:${tripId}:admins`;

export class TrackingService {
  constructor({ io, locationStore, staleAfterMs, offlineAfterMs, now = () => Date.now() }) {
    this.io = io;
    this.locationStore = locationStore;
    this.staleAfterMs = staleAfterMs;
    this.offlineAfterMs = offlineAfterMs;
    this.now = now;
    this.trips = new Map();
  }

  roomFor(tripId) {
    return roomFor(tripId);
  }

  startPublishing(tripId, socketId) {
    const nowIso = new Date(this.now()).toISOString();
    this.trips.set(tripId, {
      publisherSocketId: socketId,
      status: 'online',
      lastUpdateAt: nowIso,
    });
    this.emitStatus(tripId);
  }

  publish(tripId, socketId, location) {
    const state = this.trips.get(tripId);
    if (!state || state.publisherSocketId !== socketId || state.status === 'stopped') return false;

    const receivedAt = new Date(this.now()).toISOString();
    const outgoing = {
      tripId,
      ...location,
      receivedAt,
      trackingStatus: 'online',
    };
    this.locationStore.setLatest(tripId, outgoing);
    state.status = 'online';
    state.lastUpdateAt = receivedAt;
    this.io.to(roomFor(tripId)).emit('trip:location', outgoing);
    this.emitStatus(tripId);
    return true;
  }

  stopPublishing(tripId, socketId) {
    const state = this.trips.get(tripId);
    if (!state || state.publisherSocketId !== socketId) return;
    state.status = 'stopped';
    state.publisherSocketId = null;
    this.emitStatus(tripId);
  }

  disconnectPublisher(socketId) {
    for (const [tripId, state] of this.trips) {
      if (state.publisherSocketId === socketId) {
        state.publisherSocketId = null;
        state.status = 'offline';
        this.emitStatus(tripId);
      }
    }
  }

  checkStatuses() {
    const now = this.now();
    this.locationStore.cleanup();
    for (const [tripId, state] of this.trips) {
      if (state.status === 'stopped' || state.status === 'offline') continue;
      const age = now - Date.parse(state.lastUpdateAt);
      const next = age >= this.offlineAfterMs ? 'offline' : age >= this.staleAfterMs ? 'stale' : 'online';
      if (next !== state.status) {
        state.status = next;
        this.emitStatus(tripId);
      }
    }
  }

  currentStatus(tripId) {
    return this.trips.get(tripId)?.status ?? 'offline';
  }

  emitStatus(tripId, socket = null) {
    const state = this.trips.get(tripId);
    const payload = {
      tripId,
      status: state?.status ?? 'offline',
      lastUpdateAt: state?.lastUpdateAt ?? null,
    };
    if (socket) socket.emit('tracking:status', payload);
    else this.io.to(roomFor(tripId)).emit('tracking:status', payload);
  }
}
