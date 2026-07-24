import { z } from 'zod';

const schema = z.object({
  latitude: z.number().finite().min(-90).max(90),
  longitude: z.number().finite().min(-180).max(180),
  speed: z.number().finite().nonnegative().optional(),
  heading: z.number().finite().min(0).max(360).optional(),
  accuracy: z.number().finite().positive().optional(),
  recordedAt: z.iso.datetime({ offset: true }),
}).strict();

export function validateLocation(payload, { now, maxAgeMs, maxFutureMs, previous }) {
  const result = schema.safeParse(payload);
  if (!result.success) return { success: false };

  const recordedTime = Date.parse(result.data.recordedAt);
  if (recordedTime < now - maxAgeMs || recordedTime > now + maxFutureMs) {
    return { success: false };
  }
  if (previous && recordedTime <= Date.parse(previous.recordedAt)) {
    return { success: false };
  }

  return { success: true, data: result.data };
}
