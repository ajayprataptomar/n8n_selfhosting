import { PrismaClient } from '../../node_modules/.prisma/harikson-client/index.js';

export const prisma = new PrismaClient({
  datasources: {
    db: {
      url: process.env.HARIKSON_DATABASE_URL,
    },
  },
});
