const serialize = (level, message, context = {}) => JSON.stringify({
  level,
  message,
  ...context,
  timestamp: new Date().toISOString(),
});

export const logger = {
  info(message, context) {
    console.log(serialize('info', message, context));
  },
  warn(message, context) {
    console.warn(serialize('warn', message, context));
  },
  error(message, context) {
    console.error(serialize('error', message, context));
  },
};
