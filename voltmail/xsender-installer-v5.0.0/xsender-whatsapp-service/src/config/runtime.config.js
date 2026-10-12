import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

/**
 * Runtime Configuration
 * Values set dynamically from Laravel via API
 * Now persists to file to survive restarts
 */

class RuntimeConfig {
  constructor() {
    this.configPath = path.resolve(__dirname, '../../storage/.runtime-config.json');

    // Resolve Laravel .env path (configurable for different hosting setups)
    // Default: ../src/.env relative to the Node project root
    this.laravelEnvPath = process.env.LARAVEL_ENV_PATH
      || path.resolve(__dirname, '../../../src/.env');

    // Default values (can be overridden by Laravel)
    this.config = {
      apiKey: process.env.API_KEY || 'default-change-me',
      allowedOrigins: process.env.ALLOWED_ORIGINS?.split(',') || ['*'],
      purchaseKey: process.env.PURCHASE_KEY || '',
      envatoUsername: process.env.ENVATO_USERNAME || '',
      softwareId: process.env.SOFTWARE_ID || 'BX32DOTW4Q797ZF3',
      version: process.env.VERSION || '3.3',
      domain: process.env.DOMAIN || '',
      laravelConfigured: false,
    };

    // Load persisted config on startup
    this._loadPersistedConfig();

    // If API key is still default/empty after all loading, try Laravel's .env
    if (!this.config.apiKey || this.config.apiKey === 'default-change-me') {
      this._loadFromLaravelEnv();
    }
  }

  /**
   * Load configuration from persisted file
   * This ensures config survives PM2 restarts
   */
  _loadPersistedConfig() {
    try {
      if (fs.existsSync(this.configPath)) {
        const saved = JSON.parse(fs.readFileSync(this.configPath, 'utf8'));

        // Only restore if saved config is recent (within 7 days)
        if (saved.savedAt && Date.now() - saved.savedAt < 7 * 24 * 60 * 60 * 1000) {
          // Restore saved values, but keep env values for apiKey as fallback
          if (saved.apiKey) this.config.apiKey = saved.apiKey;
          if (saved.allowedOrigins) this.config.allowedOrigins = saved.allowedOrigins;
          if (saved.purchaseKey) this.config.purchaseKey = saved.purchaseKey;
          if (saved.envatoUsername) this.config.envatoUsername = saved.envatoUsername;
          if (saved.softwareId) this.config.softwareId = saved.softwareId;
          if (saved.version) this.config.version = saved.version;
          if (saved.domain) this.config.domain = saved.domain;

          this.config.laravelConfigured = true;
          console.log('[RuntimeConfig] Restored configuration from persistent storage');
        }
      }
    } catch (error) {
      console.error('[RuntimeConfig] Failed to load persisted config:', error.message);
    }
  }

  /**
   * Load API key and other config from Laravel's .env file
   * This is the "single source of truth" fallback - eliminates the need
   * to manually keep both .env files in sync
   */
  _loadFromLaravelEnv() {
    try {
      if (!fs.existsSync(this.laravelEnvPath)) {
        console.log('[RuntimeConfig] Laravel .env not found at:', this.laravelEnvPath);
        return false;
      }

      const envContent = fs.readFileSync(this.laravelEnvPath, 'utf8');
      let loaded = false;

      // Read WP_API_KEY
      const apiKeyMatch = envContent.match(/^WP_API_KEY=(.+)$/m);
      if (apiKeyMatch && apiKeyMatch[1].trim()) {
        this.config.apiKey = apiKeyMatch[1].trim();
        loaded = true;
      }

      // Read APP_URL for allowed origins (if not already set)
      if (this.config.allowedOrigins.includes('*') || this.config.allowedOrigins.length === 0) {
        const appUrlMatch = envContent.match(/^APP_URL=(.+)$/m);
        if (appUrlMatch && appUrlMatch[1].trim()) {
          this.config.allowedOrigins = [appUrlMatch[1].trim()];
        }
      }

      // Read PURCHASE_KEY if not set
      if (!this.config.purchaseKey) {
        const pkMatch = envContent.match(/^PURCHASE_KEY=(.+)$/m);
        if (pkMatch && pkMatch[1].trim()) {
          this.config.purchaseKey = pkMatch[1].trim();
        }
      }

      // Read ENVATO_USERNAME if not set
      if (!this.config.envatoUsername) {
        const euMatch = envContent.match(/^ENVATO_USERNAME="?([^"]+)"?$/m);
        if (euMatch && euMatch[1].trim()) {
          this.config.envatoUsername = euMatch[1].trim();
        }
      }

      // Read SOFTWARE_ID if not set
      if (!this.config.softwareId || this.config.softwareId === 'BX32DOTW4Q797ZF3') {
        const siMatch = envContent.match(/^SOFTWARE_ID=(.+)$/m);
        if (siMatch && siMatch[1].trim()) {
          this.config.softwareId = siMatch[1].trim();
        }
      }

      // Read VERSION if not set
      if (!this.config.version || this.config.version === '3.3') {
        const vMatch = envContent.match(/^VERSION=(.+)$/m);
        if (vMatch && vMatch[1].trim()) {
          this.config.version = vMatch[1].trim();
        }
      }

      if (loaded) {
        console.log('[RuntimeConfig] Configuration loaded from Laravel .env:', this.laravelEnvPath);
        this.config.laravelConfigured = true;
      }

      return loaded;
    } catch (error) {
      console.error('[RuntimeConfig] Failed to read Laravel .env:', error.message);
      return false;
    }
  }

  /**
   * Save configuration to persistent storage
   */
  _persistConfig() {
    try {
      const dir = path.dirname(this.configPath);
      if (!fs.existsSync(dir)) {
        fs.mkdirSync(dir, { recursive: true });
      }

      const dataToSave = {
        apiKey: this.config.apiKey,
        allowedOrigins: this.config.allowedOrigins,
        purchaseKey: this.config.purchaseKey,
        envatoUsername: this.config.envatoUsername,
        softwareId: this.config.softwareId,
        version: this.config.version,
        domain: this.config.domain,
        savedAt: Date.now()
      };

      fs.writeFileSync(this.configPath, JSON.stringify(dataToSave, null, 2));
      console.log('[RuntimeConfig] Configuration persisted to storage');
    } catch (error) {
      console.error('[RuntimeConfig] Failed to persist config:', error.message);
    }
  }

  /**
   * Update configuration from Laravel
   */
  updateFromLaravel(laravelConfig) {
    if (laravelConfig.apiKey) {
      this.config.apiKey = laravelConfig.apiKey;
    }

    if (laravelConfig.allowedOrigins) {
      this.config.allowedOrigins = Array.isArray(laravelConfig.allowedOrigins)
        ? laravelConfig.allowedOrigins
        : [laravelConfig.allowedOrigins];
    }

    if (laravelConfig.purchaseKey) {
      this.config.purchaseKey = laravelConfig.purchaseKey;
    }

    if (laravelConfig.envatoUsername) {
      this.config.envatoUsername = laravelConfig.envatoUsername;
    }

    if (laravelConfig.softwareId) {
      this.config.softwareId = laravelConfig.softwareId;
    }

    if (laravelConfig.version) {
      this.config.version = laravelConfig.version;
    }

    if (laravelConfig.domain) {
      this.config.domain = laravelConfig.domain;
    }

    this.config.laravelConfigured = true;

    // Persist config to file so it survives restarts
    this._persistConfig();
  }

  /**
   * Reload API key from Laravel .env
   * Called by auth middleware when submitted key doesn't match stored key.
   * Throttled to prevent excessive file reads from repeated invalid attempts.
   * Returns true if the key was updated, false otherwise.
   */
  reloadApiKey() {
    // Throttle: only reload once every 10 seconds
    const now = Date.now();
    if (this._lastApiKeyReload && now - this._lastApiKeyReload < 10000) {
      return false;
    }
    this._lastApiKeyReload = now;

    try {
      if (!fs.existsSync(this.laravelEnvPath)) {
        return false;
      }

      const envContent = fs.readFileSync(this.laravelEnvPath, 'utf8');
      const apiKeyMatch = envContent.match(/^WP_API_KEY=(.+)$/m);

      if (apiKeyMatch && apiKeyMatch[1].trim()) {
        const freshKey = apiKeyMatch[1].trim();

        if (freshKey !== this.config.apiKey) {
          console.log('[RuntimeConfig] API key updated from Laravel .env');
          this.config.apiKey = freshKey;
          this._persistConfig();
          return true;
        }
      }

      return false;
    } catch (error) {
      console.error('[RuntimeConfig] Failed to reload API key:', error.message);
      return false;
    }
  }

  /**
   * Get configuration value
   */
  get(key) {
    return this.config[key];
  }

  /**
   * Check if configured by Laravel
   */
  isConfigured() {
    return this.config.laravelConfigured;
  }

  /**
   * Get all config
   */
  getAll() {
    return { ...this.config };
  }

  /**
   * Clear persisted config (useful for troubleshooting)
   */
  clearPersisted() {
    try {
      if (fs.existsSync(this.configPath)) {
        fs.unlinkSync(this.configPath);
        console.log('[RuntimeConfig] Persisted config cleared');
      }
    } catch (error) {
      console.error('[RuntimeConfig] Failed to clear persisted config:', error.message);
    }
  }
}

// Export singleton instance
export default new RuntimeConfig();
