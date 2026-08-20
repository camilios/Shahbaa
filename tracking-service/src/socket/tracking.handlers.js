import { validateLocation } from '../validation/location.schema.js';

const errorMessages = {
  UNAUTHENTICATED: 'Authentication is required.',
  UNAUTHORIZED_TRIP: 'You are not authorized for this trip.',
  INVALID_LOCATION: 'The location payload is invalid.',
  TRACKING_NOT_STARTED: 'Location publishing has not been started.',
  RATE_LIMITED: 'Location updates are being sent too frequently.',
  LARAVEL_UNAVAILABLE: 'Authorization is temporarily unavailable.',
};

const validTripId = (value) => Number.isSafeInteger(value) && value > 0;
const callback = (ack) => typeof ack === 'function' ? ack : () => {};

const fail = (socket, ack, code) => {
  const error = { code, message: errorMessages[code] };
  socket.emit('tracking:error', error);
  callback(ack)({ ok: false, error });
};

export function registerTrackingHandlers(socket, dependencies) {
  const { authorizationService, trackingService, locationStore, config, now = () => Date.now() } = dependencies;
  socket.data.locationUpdateTimes = [];
  socket.data.subscribedTripIds = new Set();

  const refreshAuthorizations = async () => {
    const publishingTripId = socket.data.publishingTripId;
    if (publishingTripId) {
      try {
        await authorizationService.authorize({
          token: socket.data.laravelToken,
          tripId: publishingTripId,
          action: 'publish',
        });
      } catch (error) {
        trackingService.revokePublisher(publishingTripId, socket.id);
        socket.data.publishingTripId = null;
        fail(socket, null, error.code ?? 'LARAVEL_UNAVAILABLE');
      }
    }

    for (const tripId of [...socket.data.subscribedTripIds]) {
      try {
        await authorizationService.authorize({
          token: socket.data.laravelToken,
          tripId,
          action: 'subscribe',
        });
      } catch (error) {
        await socket.leave(trackingService.roomFor(tripId));
        socket.data.subscribedTripIds.delete(tripId);
        fail(socket, null, error.code ?? 'LARAVEL_UNAVAILABLE');
      }
    }
  };

  const authorizationInterval = setInterval(refreshAuthorizations, config.authorizationRefreshMs ?? 30_000);
  authorizationInterval.unref();

  socket.on('trip:subscribe', async (payload, ack) => {
    const tripId = payload?.tripId;
    if (!validTripId(tripId)) return fail(socket, ack, 'UNAUTHORIZED_TRIP');
    try {
      await authorizationService.authorize({
        token: socket.data.laravelToken,
        tripId,
        action: 'subscribe',
      });
      await socket.join(trackingService.roomFor(tripId));
      socket.data.subscribedTripIds.add(tripId);
      const latest = locationStore.getLatest(tripId);
      if (latest) socket.emit('trip:location', latest);
      trackingService.emitStatus(tripId, socket);
      callback(ack)({ ok: true, tripId });
    } catch (error) {
      fail(socket, ack, error.code ?? 'LARAVEL_UNAVAILABLE');
    }
  });

  socket.on('trip:unsubscribe', async (payload, ack) => {
    const tripId = payload?.tripId;
    if (!validTripId(tripId)) return fail(socket, ack, 'UNAUTHORIZED_TRIP');
    await socket.leave(trackingService.roomFor(tripId));
    socket.data.subscribedTripIds.delete(tripId);
    callback(ack)({ ok: true, tripId });
  });

  socket.on('location:publish:start', async (payload, ack) => {
    const tripId = payload?.tripId;
    if (!validTripId(tripId)) return fail(socket, ack, 'UNAUTHORIZED_TRIP');
    try {
      const authorization = await authorizationService.authorize({
        token: socket.data.laravelToken,
        tripId,
        action: 'publish',
      });
      const previousTripId = socket.data.publishingTripId;
      if (previousTripId && previousTripId !== tripId) {
        trackingService.stopPublishing(previousTripId, socket.id);
      }
      socket.data.publishingTripId = tripId;
      socket.data.authorizedUserId = authorization.user_id;
      trackingService.startPublishing(tripId, socket.id);
      callback(ack)({ ok: true, tripId });
    } catch (error) {
      fail(socket, ack, error.code ?? 'LARAVEL_UNAVAILABLE');
    }
  });

  socket.on('location:update', (payload, ack) => {
    const tripId = socket.data.publishingTripId;
    if (!tripId) return fail(socket, ack, 'TRACKING_NOT_STARTED');

    const currentTime = now();
    socket.data.locationUpdateTimes = socket.data.locationUpdateTimes.filter(
      (timestamp) => timestamp > currentTime - 60_000,
    );
    if (socket.data.locationUpdateTimes.length >= config.maxUpdatesPerMinute) {
      return fail(socket, ack, 'RATE_LIMITED');
    }
    socket.data.locationUpdateTimes.push(currentTime);

    const previous = locationStore.getLatest(tripId);
    const result = validateLocation(payload, {
      now: currentTime,
      maxAgeMs: config.maxLocationAgeMs,
      maxFutureMs: config.maxLocationFutureMs,
      previous,
    });
    if (!result.success) return fail(socket, ack, 'INVALID_LOCATION');

    if (!trackingService.publish(tripId, socket.id, result.data)) {
      return fail(socket, ack, 'TRACKING_NOT_STARTED');
    }
    callback(ack)({ ok: true, tripId, receivedAt: locationStore.getLatest(tripId).receivedAt });
  });

  socket.on('location:publish:stop', (payload, ack) => {
    const tripId = socket.data.publishingTripId;
    if (!tripId) return fail(socket, ack, 'TRACKING_NOT_STARTED');
    trackingService.stopPublishing(tripId, socket.id);
    socket.data.publishingTripId = null;
    callback(ack)({ ok: true, tripId });
  });

  socket.on('disconnect', () => {
    clearInterval(authorizationInterval);
    trackingService.disconnectPublisher(socket.id);
  });
}
