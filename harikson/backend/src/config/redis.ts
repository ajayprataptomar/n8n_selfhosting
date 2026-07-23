import { createClient } from 'redis';

const redisUrl = process.env.HARIKSON_REDIS_URL || 'redis://localhost:6380/1';

export const redisClient = createClient({
  url: redisUrl,
});

redisClient.on('error', (err) =>
  console.error('🐳 [Harikson Redis] Client Error', err)
);

try {
  await redisClient.connect();
  console.log('🐳 [Harikson Redis] Connected successfully to DB Index 1.');
} catch (err) {
  console.error(
    '❌ [Harikson Redis] Failed to establish Redis connection:',
    err
  );
}

// BullMQ connection options
const parsedUrl = new URL(redisUrl);
export const bullMQConnection = {
  host: parsedUrl.hostname,
  port: parseInt(parsedUrl.port),
  username: parsedUrl.username || undefined,
  password: parsedUrl.password || undefined,
  db: parseInt(parsedUrl.pathname.replace('/', '')) || 1,
};
