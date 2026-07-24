export function socketAuth(socket, next) {
  const token = socket.handshake.auth?.token;
  if (typeof token !== 'string' || token.trim() === '' || token.length > 4096) {
    const error = new Error('Authentication token is required');
    error.data = { code: 'UNAUTHENTICATED' };
    return next(error);
  }

  socket.data.laravelToken = token;
  next();
}
