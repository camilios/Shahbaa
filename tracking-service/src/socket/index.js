import { Server } from 'socket.io';
import { socketAuth } from '../middleware/socket-auth.js';
import { registerTrackingHandlers } from './tracking.handlers.js';

export function createSocketServer(httpServer, dependencies) {
  const { config } = dependencies;
  const io = new Server(httpServer, {
    maxHttpBufferSize: 10 * 1024,
    cors: {
      origin: config.allowedOrigins,
      methods: ['GET', 'POST'],
    },
  });

  io.use(socketAuth);
  io.on('connection', (socket) => registerTrackingHandlers(socket, dependencies));
  return io;
}
