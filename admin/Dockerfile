# Stage 1: Build step
FROM node:20-alpine AS builder

WORKDIR /usr/src/app

ARG NEXT_PUBLIC_API_URL
ENV NEXT_PUBLIC_API_URL=$NEXT_PUBLIC_API_URL

COPY package*.json ./
# package-lock.json in this repo may be generated on macOS and can pin
# platform-specific SWC binaries. For Linux builds, install from package.json.
RUN rm -f package-lock.json && npm install --no-audit --no-fund

COPY . .

# Build NextJS app
RUN npm run build

# Stage 2: Runner
FROM node:20-alpine AS runner

WORKDIR /usr/src/app

COPY --from=builder /usr/src/app/package*.json ./
RUN rm -f package-lock.json && npm install --omit=dev --no-audit --no-fund

COPY --from=builder /usr/src/app/.next ./.next

EXPOSE 3002

CMD ["npx", "next", "start", "-p", "3002"]
