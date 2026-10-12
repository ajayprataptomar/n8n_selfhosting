import crypto from 'crypto';
import os from 'os';
import fs from 'fs';
import path from 'path';
import axios from 'axios';
import { fileURLToPath } from 'url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

/**
 * LicenseGuard - Secure License Verification System
 * Multi-layer protection against cracking and nulling
 */
class LicenseGuard {
  constructor() {
    // Encoded verification endpoints (base64 + reversed)
    this._e = [
      '=42bpRXYjlmZpJXZ21SZj5WZjlGbvkGch9SZulGbu9mLlNnblNWaslnZpJXZ29yL6MHc0RHa'
    ];

    this._k = 'X5$nD#r@9Lm!pQ2w';
    this._iv = 'Yh7*Kj3&Nf6^Bc1z';
    this._s = null;
    this._v = false;
    this._t = 0;
    this._c = null;
    this._h = [];

    // Integrity hashes for critical files
    this._criticalFiles = [
      'src/app.js',
      'src/security/LicenseGuard.js',
      'src/core/SessionManager.js',
      'src/services/SessionService.js'
    ];

    this._bind();
  }

  _bind() {
    // Bind methods to prevent tampering
    const methods = ['verify', 'check', '_validate', '_decrypt', '_hash'];
    methods.forEach(m => {
      if (this[m]) this[m] = this[m].bind(this);
    });
  }

  /**
   * Generate machine fingerprint
   */
  _getMachineId() {
    const data = [
      os.hostname(),
      os.platform(),
      os.arch(),
      os.cpus()[0]?.model || '',
      os.networkInterfaces()
    ];

    const str = JSON.stringify(data);
    return crypto.createHash('sha256').update(str).digest('hex').substring(0, 32);
  }

  /**
   * Decrypt encoded string
   */
  _decrypt(encoded) {
    try {
      const cleaned = encoded.replace(/\s/g, '');
      const reversed = cleaned.split('').reverse().join('');
      return Buffer.from(reversed, 'base64').toString('utf8');
    } catch {
      return null;
    }
  }

  /**
   * Encrypt data for storage
   */
  _encrypt(data) {
    const cipher = crypto.createCipheriv(
      'aes-128-cbc',
      Buffer.from(this._k),
      Buffer.from(this._iv)
    );
    let encrypted = cipher.update(JSON.stringify(data), 'utf8', 'hex');
    encrypted += cipher.final('hex');
    return encrypted;
  }

  /**
   * Decrypt stored data
   */
  _decryptData(encrypted) {
    try {
      const decipher = crypto.createDecipheriv(
        'aes-128-cbc',
        Buffer.from(this._k),
        Buffer.from(this._iv)
      );
      let decrypted = decipher.update(encrypted, 'hex', 'utf8');
      decrypted += decipher.final('utf8');
      return JSON.parse(decrypted);
    } catch {
      return null;
    }
  }

  /**
   * Calculate file hash for integrity check
   */
  _hash(filePath) {
    try {
      const fullPath = path.resolve(__dirname, '../../', filePath);
      if (!fs.existsSync(fullPath)) return null;
      const content = fs.readFileSync(fullPath);
      return crypto.createHash('sha256').update(content).digest('hex');
    } catch {
      return null;
    }
  }

  /**
   * Verify file integrity
   */
  _checkIntegrity() {
    if (!this._h.length) return true;

    for (const file of this._criticalFiles) {
      const currentHash = this._hash(file);
      const storedHash = this._h.find(h => h.f === file);

      if (storedHash && currentHash !== storedHash.h) {
        return false;
      }
    }
    return true;
  }

  /**
   * Store integrity hashes
   */
  _storeIntegrity() {
    this._h = this._criticalFiles.map(f => ({
      f,
      h: this._hash(f)
    })).filter(x => x.h);
  }

  /**
   * Get license cache path
   */
  _getCachePath() {
    return path.resolve(__dirname, '../../storage/.license');
  }

  /**
   * Load cached license
   */
  _loadCache() {
    try {
      const cachePath = this._getCachePath();
      if (!fs.existsSync(cachePath)) return null;

      const encrypted = fs.readFileSync(cachePath, 'utf8');
      const data = this._decryptData(encrypted);

      if (!data) return null;

      // Verify machine ID matches
      if (data.m !== this._getMachineId()) {
        fs.unlinkSync(cachePath);
        return null;
      }

      // Check if cache is expired (7 days instead of 24 hours)
      // This prevents issues when license server is temporarily unavailable
      const CACHE_DURATION = 7 * 24 * 60 * 60 * 1000; // 7 days
      if (Date.now() - data.t > CACHE_DURATION) {
        return null;
      }

      return data;
    } catch {
      return null;
    }
  }

  /**
   * Save license cache
   */
  _saveCache(licenseData) {
    try {
      const cachePath = this._getCachePath();
      const cacheDir = path.dirname(cachePath);

      if (!fs.existsSync(cacheDir)) {
        fs.mkdirSync(cacheDir, { recursive: true });
      }

      const data = {
        ...licenseData,
        m: this._getMachineId(),
        t: Date.now()
      };

      const encrypted = this._encrypt(data);
      fs.writeFileSync(cachePath, encrypted);
    } catch {
      // Silent fail
    }
  }

  /**
   * Remote license verification
   */
  async _remoteVerify(config) {
    const endpoints = this._e.map(e => this._decrypt(e)).filter(Boolean);

    for (const baseUrl of endpoints) {
      try {
        // Step 1: Register domain
        const regResponse = await axios.post(
          `${baseUrl}/register-domain`,
          {
            domain: config.domain,
            software_id: config.softwareId,
            version: config.version
          },
          { timeout: 15000 }
        );

        if (!regResponse.data) continue;

        // Step 2: Verify purchase
        const verifyResponse = await axios.post(
          `${baseUrl}/verify-purchase`,
          {
            domain: config.domain,
            software_id: config.softwareId,
            version: config.version,
            purchase_key: config.purchaseKey,
            envato_username: config.envatoUsername
          },
          { timeout: 15000 }
        );

        if (verifyResponse.data?.success) {
          return {
            valid: true,
            message: verifyResponse.data.message || 'License verified',
            data: verifyResponse.data
          };
        }
      } catch (error) {
        continue;
      }
    }

    return { valid: false, message: 'License verification failed' };
  }

  /**
   * Main verification method
   */
  async verify(config) {
    // Quick check: if already verified in memory, return immediately (performance optimization)
    // This avoids file I/O and network calls on every request
    if (this._v) {
      return { valid: true, cached: true };
    }

    // Required fields check
    const required = ['domain', 'purchaseKey', 'softwareId'];
    for (const field of required) {
      if (!config[field] || config[field].trim() === '') {
        // Allow development mode without license
        if (config.domain?.includes('localhost') || config.domain?.includes('.test')) {
          this._v = true;
          this._t = Date.now();
          return { valid: true, development: true };
        }
        return { valid: false, message: `Missing required field: ${field}` };
      }
    }

    // Try cached license first
    const cached = this._loadCache();
    if (cached && cached.valid) {
      this._v = true;
      this._t = Date.now();
      this._c = cached;
      return { valid: true, cached: true };
    }

    // Remote verification
    const result = await this._remoteVerify(config);

    if (result.valid) {
      this._v = true;
      this._t = Date.now();
      this._c = result;
      this._storeIntegrity();
      this._saveCache({ valid: true, ...result });
    }

    return result;
  }

  /**
   * Quick check if license is valid
   */
  check() {
    // Check memory state - if verified once, stays valid until PM2 restart
    // File cache (7 days) handles persistence across restarts
    if (!this._v) return false;

    // Check file integrity (prevents tampering)
    if (!this._checkIntegrity()) {
      this._v = false;
      return false;
    }

    return true;
  }

  /**
   * Get license status
   */
  getStatus() {
    return {
      valid: this._v,
      lastCheck: this._t,
      integrity: this._checkIntegrity()
    };
  }

  /**
   * Force re-verification
   */
  invalidate() {
    this._v = false;
    this._t = 0;
    this._c = null;

    try {
      const cachePath = this._getCachePath();
      if (fs.existsSync(cachePath)) {
        fs.unlinkSync(cachePath);
      }
    } catch {
      // Silent fail
    }
  }
}

// Singleton instance
// Note: Object.freeze removed because it prevents updating internal state (_v, _t, etc.)
const _instance = new LicenseGuard();

export default _instance;
