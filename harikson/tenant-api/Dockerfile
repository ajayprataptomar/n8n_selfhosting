FROM node:20-alpine AS builder

WORKDIR /usr/src/app

COPY package*.json ./
RUN rm -f package-lock.json && npm install

COPY . .
RUN npm run build

FROM node:20-alpine

WORKDIR /usr/src/app

COPY package*.json ./
RUN rm -f package-lock.json && npm install --omit=dev --no-audit --no-fund
RUN apk add --no-cache curl

COPY --from=builder /usr/src/app/dist ./dist
COPY --from=builder /usr/src/app/src/migrations ./dist/migrations
COPY --from=builder /usr/src/app/src/openapi.json ./dist/openapi.json

EXPOSE 3008

CMD ["node", "dist/index.js"]
