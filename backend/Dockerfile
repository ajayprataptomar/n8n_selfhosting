# Build step
FROM node:20-bookworm-slim AS builder

WORKDIR /usr/src/app

RUN apt-get update -y \
  && apt-get install -y --no-install-recommends openssl \
  && rm -rf /var/lib/apt/lists/*

COPY package*.json ./
RUN rm -f package-lock.json && npm install --legacy-peer-deps --no-audit --no-fund

COPY . .

# Generate Prisma Client
RUN npx prisma generate

# Compile TypeScript
RUN npm run build

# Production runtime step
FROM node:20-bookworm-slim AS runner

WORKDIR /usr/src/app

RUN apt-get update -y \
  && apt-get install -y --no-install-recommends openssl \
  && rm -rf /var/lib/apt/lists/*

COPY package*.json ./
RUN rm -f package-lock.json && npm install --legacy-peer-deps --omit=dev --no-audit --no-fund

COPY --from=builder /usr/src/app/dist ./dist
COPY --from=builder /usr/src/app/prisma ./prisma

# Copy the generated Prisma client + engines from the build stage (avoid needing
# the `prisma` CLI in the production image).
COPY --from=builder /usr/src/app/node_modules/.prisma ./node_modules/.prisma
COPY --from=builder /usr/src/app/node_modules/@prisma ./node_modules/@prisma

EXPOSE 3000

CMD ["node", "dist/index.js"]
