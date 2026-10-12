const path = require('path');
const fs = require('fs');

// Load .env file if exists
function loadEnvFile() {
  const envPath = path.resolve(__dirname, '.env');
  const env = {};

  try {
    if (fs.existsSync(envPath)) {
      const content = fs.readFileSync(envPath, 'utf8');
      content.split('\n').forEach(line => {
        const trimmed = line.trim();
        if (trimmed && !trimmed.startsWith('#')) {
          const [key, ...valueParts] = trimmed.split('=');
          if (key && valueParts.length > 0) {
            let value = valueParts.join('=').trim();
            // Remove surrounding quotes if present
            if ((value.startsWith('"') && value.endsWith('"')) ||
                (value.startsWith("'") && value.endsWith("'"))) {
              value = value.slice(1, -1);
            }
            env[key.trim()] = value;
          }
        }
      });
    }
  } catch (e) {
    console.error('Failed to load .env:', e.message);
  }

  return env;
}

module.exports = {
  apps: [
    {
      name: 'xsender-whatsapp',
      script: 'src/app.js',
      cwd: __dirname,
      instances: 1,
      exec_mode: 'fork',
      watch: false,

      // Node.js arguments for memory optimization
      node_args: '--max-old-space-size=256 --expose-gc',

      // Interpreter settings for ES modules
      interpreter: 'node',

      // Environment
      env: {
        ...loadEnvFile(),
        NODE_ENV: 'production',
      },

      // Logging
      error_file: './logs/error.log',
      out_file: './logs/output.log',
      log_date_format: 'YYYY-MM-DD HH:mm:ss Z',
      merge_logs: true,

      // Restart Configuration
      autorestart: true,
      max_restarts: 10,
      min_uptime: '30s',
      restart_delay: 5000,
      max_memory_restart: '300M',

      // Timeouts
      kill_timeout: 10000,

      // Wait for ready signal (if using process.send('ready'))
      wait_ready: false,

      // Exponential backoff restart delay
      exp_backoff_restart_delay: 1000,

      // Cron restart to clear memory leaks (restart at 4 AM daily)
      cron_restart: '0 4 * * *',
    },
  ],
};
