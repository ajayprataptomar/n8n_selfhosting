import { Server } from 'socket.io';
import { createServer } from 'http';

const httpServer = createServer();
const io = new Server(httpServer, {
  cors: {
    origin: '*',
    methods: ['GET', 'POST'],
  },
});

const port = process.env.PORT || 6000;

io.on('connection', (socket) => {
  console.log(`🔌 [IDE Bridge] Developer IDE connected: ${socket.id}`);

  // Handle ghost text autocomplete requests
  socket.on(
    'autocomplete',
    async (
      data: { prefix: string; suffix: string; language: string },
      callback
    ) => {
      console.log(
        `💻 [IDE Bridge] Auto-completion request for language: ${data.language}`
      );

      // Simulate low latency code completion response (<300ms)
      setTimeout(() => {
        let completion = '';
        if (data.prefix.trim().endsWith('function')) {
          completion =
            ' calculateMetrics(data) {\n  const cores = data.cores || 1;\n  return cores * 100;\n}';
        } else if (data.prefix.trim().endsWith('const')) {
          completion = " express = require('express');";
        } else {
          completion = ' // Autocompleted by Neuravolt AI';
        }

        callback({ completion });
      }, 150);
    }
  );

  // Handle workspace folder structure sync
  socket.on('sync-workspace', (data: { filesList: string[] }) => {
    console.log(
      `💻 [IDE Bridge] Indexed local workspace containing ${data.filesList.length} files.`
    );
  });

  // Handle chat panels inside IDE
  socket.on('chat-query', (data: { prompt: string }, callback) => {
    console.log(`💻 [IDE Bridge] IDE Chat prompt: ${data.prompt}`);

    setTimeout(() => {
      callback({
        response: `[IDE Code Assistant] I've analyzed your project layout. To implement your feature, you should update the route handler in backend/src/routes/instances.ts.`,
      });
    }, 800);
  });

  socket.on('disconnect', () => {
    console.log(`🔌 [IDE Bridge] Developer IDE disconnected: ${socket.id}`);
  });
});

httpServer.listen(port, () => {
  console.log(`⚡ [IDE Bridge Daemon] Connected and listening on port ${port}`);
});
