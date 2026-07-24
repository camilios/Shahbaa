export class LaravelAuthorizationService {
  constructor({ apiUrl, serviceSecret, timeoutMs, fetchImpl = fetch }) {
    this.endpoint = `${apiUrl}/api/v1/realtime/authorize`;
    this.serviceSecret = serviceSecret;
    this.timeoutMs = timeoutMs;
    this.fetchImpl = fetchImpl;
  }

  async authorize({ token, tripId, action }) {
    try {
      const response = await this.fetchImpl(this.endpoint, {
        method: 'POST',
        headers: {
          Authorization: `Bearer ${token}`,
          'Content-Type': 'application/json',
          'X-Tracking-Service-Key': this.serviceSecret,
        },
        body: JSON.stringify({ trip_id: tripId, action }),
        signal: AbortSignal.timeout(this.timeoutMs),
      });

      if (response.ok) return await response.json();
      if ([401, 403, 404, 422].includes(response.status)) {
        const error = new Error(response.status === 401 ? 'Unauthenticated' : 'Unauthorized trip');
        error.code = response.status === 401 ? 'UNAUTHENTICATED' : 'UNAUTHORIZED_TRIP';
        throw error;
      }

      throw this.unavailable();
    } catch (error) {
      if (error.code === 'UNAUTHENTICATED' || error.code === 'UNAUTHORIZED_TRIP') throw error;
      throw this.unavailable();
    }
  }

  unavailable() {
    const error = new Error('Laravel authorization service is unavailable');
    error.code = 'LARAVEL_UNAVAILABLE';
    return error;
  }
}
