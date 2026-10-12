import os from 'os';
import SessionManager from '../core/SessionManager.js';
import MessageQueueService from './MessageQueueService.js';
import logger from '../utils/logger.js';

/**
 * Health Check Service
 *
 * Provides comprehensive health monitoring for the WhatsApp service
 * - System health (memory, CPU, uptime)
 * - Session health (connection status, last activity)
 * - Queue health (pending messages, processing rate)
 * - Detailed metrics for monitoring dashboards
 */
class HealthService {
  constructor() {
    this.startTime = Date.now();
    this.requestCount = 0;
    this.errorCount = 0;
    this.lastError = null;

    // Message metrics
    this.messageMetrics = {
      sent: 0,
      failed: 0,
      received: 0,
      lastSentAt: null,
      lastReceivedAt: null,
    };

    // Performance metrics
    this.responseTimeHistory = [];
    this.maxHistorySize = 100;
  }

  /**
   * Record a request
   */
  recordRequest(success = true, responseTime = 0) {
    this.requestCount++;
    if (!success) {
      this.errorCount++;
    }

    // Track response time
    this.responseTimeHistory.push(responseTime);
    if (this.responseTimeHistory.length > this.maxHistorySize) {
      this.responseTimeHistory.shift();
    }
  }

  /**
   * Record last error
   */
  recordError(error) {
    this.errorCount++;
    this.lastError = {
      message: error.message,
      timestamp: new Date().toISOString(),
      stack: error.stack?.split('\n')[0],
    };
  }

  /**
   * Record message sent
   */
  recordMessageSent(success = true) {
    if (success) {
      this.messageMetrics.sent++;
      this.messageMetrics.lastSentAt = new Date().toISOString();
    } else {
      this.messageMetrics.failed++;
    }
  }

  /**
   * Record message received
   */
  recordMessageReceived() {
    this.messageMetrics.received++;
    this.messageMetrics.lastReceivedAt = new Date().toISOString();
  }

  /**
   * Get system health metrics
   */
  getSystemHealth() {
    const totalMem = os.totalmem();
    const freeMem = os.freemem();
    const usedMem = totalMem - freeMem;
    const memUsagePercent = ((usedMem / totalMem) * 100).toFixed(2);

    // Get CPU load averages
    const loadAvg = os.loadavg();

    // Process memory usage
    const processMemory = process.memoryUsage();

    return {
      status: this.determineSystemStatus(memUsagePercent, loadAvg[0]),
      uptime: {
        system: os.uptime(),
        process: Math.floor((Date.now() - this.startTime) / 1000),
        startedAt: new Date(this.startTime).toISOString(),
      },
      memory: {
        total: this.formatBytes(totalMem),
        free: this.formatBytes(freeMem),
        used: this.formatBytes(usedMem),
        usagePercent: parseFloat(memUsagePercent),
        process: {
          heapUsed: this.formatBytes(processMemory.heapUsed),
          heapTotal: this.formatBytes(processMemory.heapTotal),
          rss: this.formatBytes(processMemory.rss),
          external: this.formatBytes(processMemory.external),
        },
      },
      cpu: {
        cores: os.cpus().length,
        loadAverage: {
          '1min': loadAvg[0].toFixed(2),
          '5min': loadAvg[1].toFixed(2),
          '15min': loadAvg[2].toFixed(2),
        },
      },
      platform: {
        os: os.platform(),
        arch: os.arch(),
        nodeVersion: process.version,
        hostname: os.hostname(),
      },
    };
  }

  /**
   * Determine system status based on metrics
   * Note: These thresholds are for the Node.js PROCESS, not the whole system
   * System-wide memory usage is informational only
   */
  determineSystemStatus(memUsage, cpuLoad) {
    const cpuCores = os.cpus().length;
    const normalizedLoad = cpuLoad / cpuCores;

    // Get process-specific memory (more relevant than system memory)
    const processMemory = process.memoryUsage();
    const heapUsedMB = processMemory.heapUsed / (1024 * 1024);
    const rssMB = processMemory.rss / (1024 * 1024);

    // Only mark critical if Node.js process itself is using too much memory
    // or CPU load is extremely high (> 95% per core)
    if (heapUsedMB > 500 || rssMB > 1000 || normalizedLoad > 0.95) {
      return 'critical';
    } else if (heapUsedMB > 300 || rssMB > 500 || normalizedLoad > 0.8) {
      return 'warning';
    }
    return 'healthy';
  }

  /**
   * Get session health metrics
   */
  getSessionHealth() {
    const sessions = SessionManager.getAll();
    const sessionDetails = [];

    let connected = 0;
    let disconnected = 0;
    let authenticating = 0;

    for (const sessionId of sessions) {
      const status = SessionManager.getStatus(sessionId);

      if (status.status === 'authenticated' || status.isSession) {
        connected++;
      } else if (status.status === 'connecting') {
        authenticating++;
      } else {
        disconnected++;
      }

      const client = SessionManager.get(sessionId);

      sessionDetails.push({
        id: sessionId,
        status: status.status,
        isAuthenticated: status.isSession,
        user: status.user ? {
          id: status.user.id,
          name: status.user.name,
        } : null,
        lastActivity: client?.lastActivity
          ? new Date(client.lastActivity).toISOString()
          : null,
        retryCount: client?.retryCount || 0,
      });
    }

    return {
      status: disconnected === sessions.length && sessions.length > 0 ? 'critical' :
        disconnected > 0 ? 'warning' : 'healthy',
      total: sessions.length,
      connected,
      disconnected,
      authenticating,
      sessions: sessionDetails,
    };
  }

  /**
   * Get queue health metrics
   */
  getQueueHealth() {
    const queueStatus = MessageQueueService.getAllStatus();

    let totalQueued = 0;
    let totalPending = 0;

    for (const sessionId in queueStatus.sessions) {
      const session = queueStatus.sessions[sessionId];
      totalQueued += session.queued.total;
      totalPending += session.pending;
    }

    return {
      status: totalQueued > 1000 ? 'warning' : 'healthy',
      totalQueued,
      totalPending,
      stats: queueStatus.stats,
      config: {
        maxQueueSize: queueStatus.config.maxQueueSize,
        rateLimitPerMinute: queueStatus.config.rateLimitPerMinute,
      },
      sessions: queueStatus.sessions,
    };
  }

  /**
   * Get API metrics
   */
  getApiMetrics() {
    const avgResponseTime = this.responseTimeHistory.length > 0
      ? (this.responseTimeHistory.reduce((a, b) => a + b, 0) / this.responseTimeHistory.length).toFixed(2)
      : 0;

    const errorRate = this.requestCount > 0
      ? ((this.errorCount / this.requestCount) * 100).toFixed(2)
      : 0;

    return {
      status: errorRate > 10 ? 'warning' : 'healthy',
      requests: {
        total: this.requestCount,
        errors: this.errorCount,
        errorRate: parseFloat(errorRate),
      },
      responseTime: {
        average: parseFloat(avgResponseTime),
        samples: this.responseTimeHistory.length,
      },
      messages: this.messageMetrics,
      lastError: this.lastError,
    };
  }

  /**
   * Get comprehensive health report
   */
  getHealthReport() {
    const system = this.getSystemHealth();
    const sessions = this.getSessionHealth();
    const queue = this.getQueueHealth();
    const api = this.getApiMetrics();

    // Determine overall status
    const statuses = [system.status, sessions.status, queue.status, api.status];
    let overallStatus = 'healthy';

    if (statuses.includes('critical')) {
      overallStatus = 'critical';
    } else if (statuses.includes('warning')) {
      overallStatus = 'warning';
    }

    return {
      status: overallStatus,
      timestamp: new Date().toISOString(),
      version: process.env.npm_package_version || '1.0.0',
      system,
      sessions,
      queue,
      api,
    };
  }

  /**
   * Simple liveness check (for container orchestration)
   */
  getLivenessCheck() {
    return {
      status: 'alive',
      timestamp: new Date().toISOString(),
      uptime: Math.floor((Date.now() - this.startTime) / 1000),
    };
  }

  /**
   * Readiness check (for load balancer)
   */
  getReadinessCheck() {
    const sessions = this.getSessionHealth();
    const system = this.getSystemHealth();

    // Ready if system is not critical and at least one session is connected
    const isReady =
      system.status !== 'critical' &&
      (sessions.total === 0 || sessions.connected > 0);

    return {
      status: isReady ? 'ready' : 'not_ready',
      timestamp: new Date().toISOString(),
      checks: {
        system: system.status !== 'critical',
        sessions: sessions.total === 0 || sessions.connected > 0,
      },
    };
  }

  /**
   * Format bytes to human readable
   */
  formatBytes(bytes) {
    if (bytes === 0) return '0 B';
    const k = 1024;
    const sizes = ['B', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
  }
}

// Export singleton instance
export default new HealthService();
