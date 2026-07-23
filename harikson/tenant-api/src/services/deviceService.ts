import crypto from 'crypto';
import { Request } from 'express';

export interface DeviceFingerprint {
  deviceHash: string;
  deviceName: string;
  ip: string;
  ipSubnet: string;
  countryCode: string;
}

/**
 * Parse OS and browser details from User-Agent string.
 */
export function parseDeviceName(userAgent: string): string {
  if (!userAgent) return 'Unknown Device';

  let os = 'Unknown OS';
  if (userAgent.includes('Mac OS X')) os = 'macOS';
  else if (userAgent.includes('Windows')) os = 'Windows';
  else if (userAgent.includes('Android')) os = 'Android';
  else if (userAgent.includes('iPhone') || userAgent.includes('iPad')) os = 'iOS';
  else if (userAgent.includes('Linux')) os = 'Linux';

  let browser = 'Browser';
  if (userAgent.includes('Chrome') && !userAgent.includes('Edg')) browser = 'Chrome';
  else if (userAgent.includes('Safari') && !userAgent.includes('Chrome')) browser = 'Safari';
  else if (userAgent.includes('Firefox')) browser = 'Firefox';
  else if (userAgent.includes('Edg')) browser = 'Edge';

  return `${browser} on ${os}`;
}

/**
 * Compute cryptographic SHA-256 device fingerprint from request headers & IP subnet.
 */
export function computeDeviceFingerprint(req: Request): DeviceFingerprint {
  const userAgent = (req.headers['user-agent'] || 'Unknown-Agent').toString();
  const acceptLanguage = (req.headers['accept-language'] || 'en').toString().split(',')[0];

  const rawIp =
    (req.headers['x-forwarded-for'] as string)?.split(',')[0].trim() ||
    req.socket?.remoteAddress ||
    '127.0.0.1';

  const ipSubnet = rawIp.includes('.')
    ? rawIp.split('.').slice(0, 3).join('.')
    : rawIp.split(':').slice(0, 4).join(':');

  const rawString = `${userAgent}|${ipSubnet}|${acceptLanguage}`;
  const deviceHash = crypto.createHash('sha256').update(rawString).digest('hex');
  const deviceName = parseDeviceName(userAgent);
  const countryCode = ((req.headers['x-country-code'] as string) || 'IN').toUpperCase();

  return {
    deviceHash,
    deviceName,
    ip: rawIp,
    ipSubnet,
    countryCode,
  };
}
