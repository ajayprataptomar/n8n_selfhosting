import logger from '../utils/logger.js';
import { Resend } from 'resend';
import { Redis } from 'ioredis';

const resend = new Resend(process.env.RESEND_API_KEY || 're_dev_key');
const redis = new Redis(process.env.REDIS_URL || 'redis://redis:6379');

// Rate limit: max 3 emails per address per hour
async function checkEmailRateLimit(email: string): Promise<boolean> {
  try {
    const key = `ratelimit:emails:${email.toLowerCase()}`;
    const attempts = await redis.incr(key);
    if (attempts === 1) {
      await redis.expire(key, 3600); // 1 hour expiration
    }
    if (attempts > 3) {
      logger.warn(
        `[EMAIL RATE LIMIT EXCEEDED] Email "${email}" has requested too many emails in the last hour.`
      );
      return false;
    }
    return true;
  } catch (err: any) {
    logger.error('[EMAIL RATE LIMIT ERROR]:', err.message);
    return true;
  }
}

export const sendPasswordReset = async (to: string, resetUrl: string) => {
  if (!(await checkEmailRateLimit(to))) {
    return {
      success: false,
      error: 'Rate limit exceeded. Max 3 emails per hour.',
    };
  }

  try {
    const { data, error } = await resend.emails.send({
      from: 'Harikson AI <noreply@neuravolt.cloud>',
      to,
      subject: 'Reset your password',
      html: `
        <div style="font-family: sans-serif; padding: 20px; color: #1e293b; max-width: 600px; margin: 0 auto; border: 1px solid #e2e8f0; border-radius: 8px;">
          <h2 style="color: #3b82f6; border-bottom: 2px solid #3b82f6; padding-bottom: 10px;">Password Reset Request</h2>
          <p>We received a request to reset your password for your Harikson AI account.</p>
          <p>Please click the button below to reset your password (link is valid for 1 hour):</p>
          <div style="text-align: center; margin: 30px 0;">
            <a href="${resetUrl}" style="display: inline-block; padding: 12px 24px; background-color: #3b82f6; color: white; text-decoration: none; border-radius: 6px; font-weight: bold;">Reset Password</a>
          </div>
          <p style="font-size: 13px; color: #64748b;">If the button doesn't work, you can copy and paste this link into your browser:</p>
          <p style="font-size: 13px; color: #3b82f6; word-break: break-all;">${resetUrl}</p>
          <hr style="border: 0; border-top: 1px solid #e2e8f0; margin: 20px 0;" />
          <p style="font-size: 12px; color: #94a3b8;">If you did not request a password reset, you can safely ignore this email.</p>
        </div>
      `,
    });
    if (error) {
      logger.error(
        '[EMAIL SEND ERROR - PASSWORD RESET]:',
        error.message || error
      );
      return {
        success: false,
        error: error.message || 'Failed to send password reset email',
      };
    }
    return { success: true, data };
  } catch (err: any) {
    logger.error('[EMAIL SEND ERROR - PASSWORD RESET]:', err.message);
    return { success: false, error: 'Failed to send password reset email' };
  }
};

export const sendWelcomeEmail = async (to: string, name: string) => {
  if (!(await checkEmailRateLimit(to))) {
    return {
      success: false,
      error: 'Rate limit exceeded. Max 3 emails per hour.',
    };
  }

  try {
    const { data, error } = await resend.emails.send({
      from: 'Harikson AI <noreply@neuravolt.cloud>',
      to,
      subject: 'Welcome to Harikson AI!',
      html: `
        <div style="font-family: sans-serif; padding: 20px; color: #1e293b; max-width: 600px; margin: 0 auto; border: 1px solid #e2e8f0; border-radius: 8px;">
          <h2 style="color: #10b981; border-bottom: 2px solid #10b981; padding-bottom: 10px;">Welcome to Harikson AI!</h2>
          <p>Hello ${name || 'there'},</p>
          <p>Thank you for signing up to Harikson AI Platform. Your workspace is now active and ready to build state-of-the-art AI systems.</p>
          <p>Visit your dashboard to create your first agent or knowledge base documents library.</p>
          <hr style="border: 0; border-top: 1px solid #e2e8f0; margin: 20px 0;" />
          <p style="font-size: 12px; color: #94a3b8;">Secured by Harikson · Enterprise AI Platform</p>
        </div>
      `,
    });
    if (error) {
      logger.error('[EMAIL SEND ERROR - WELCOME]:', error.message || error);
      return {
        success: false,
        error: error.message || 'Failed to send welcome email',
      };
    }
    return { success: true, data };
  } catch (err: any) {
    logger.error('[EMAIL SEND ERROR - WELCOME]:', err.message);
    return { success: false, error: 'Failed to send welcome email' };
  }
};

interface InvoiceDetails {
  amount: number;
  currency: string;
  status: string;
  invoiceUrl?: string;
  pdfUrl?: string;
}

export const sendInvoiceReceipt = async (to: string, invoiceDetails: InvoiceDetails) => {
  if (!(await checkEmailRateLimit(to))) {
    return {
      success: false,
      error: 'Rate limit exceeded. Max 3 emails per hour.',
    };
  }

  try {
    const { amount, currency, status, invoiceUrl, pdfUrl } = invoiceDetails;
    const { data, error } = await resend.emails.send({
      from: 'Harikson AI <noreply@neuravolt.cloud>',
      to,
      subject: 'Payment Receipt - Harikson AI',
      html: `
        <div style="font-family: sans-serif; padding: 20px; color: #1e293b; max-width: 600px; margin: 0 auto; border: 1px solid #e2e8f0; border-radius: 8px;">
          <h2 style="color: #059669; border-bottom: 2px solid #059669; padding-bottom: 10px;">Payment Receipt</h2>
          <p>Thank you for your payment!</p>
          <p><strong>Amount Paid:</strong> ${(amount / 100).toFixed(2)} ${currency.toUpperCase()}</p>
          <p><strong>Status:</strong> ${status.toUpperCase()}</p>
          <p>You can view your invoice online here:</p>
          <div style="text-align: center; margin: 30px 0;">
            <a href="${invoiceUrl || '#'}" style="display: inline-block; padding: 12px 24px; background-color: #059669; color: white; text-decoration: none; border-radius: 6px; font-weight: bold;">View Invoice</a>
          </div>
          ${pdfUrl ? `<p>Or download the PDF copy <a href="${pdfUrl}">here</a>.</p>` : ''}
          <hr style="border: 0; border-top: 1px solid #e2e8f0; margin: 20px 0;" />
          <p style="font-size: 12px; color: #94a3b8;">Thank you for choosing Harikson AI!</p>
        </div>
      `,
    });

    if (error) {
      logger.error('[EMAIL SEND ERROR - INVOICE]:', error.message || error);
      return {
        success: false,
        error: error.message || 'Failed to send invoice receipt',
      };
    }
    return { success: true, data };
  } catch (err: any) {
    logger.error('[EMAIL SEND ERROR - INVOICE]:', err.message);
    return { success: false, error: 'Failed to send invoice receipt' };
  }
};

export const sendSubscriptionCancellation = async (to: string, planName: string, endDate: string) => {
  if (!(await checkEmailRateLimit(to))) {
    return {
      success: false,
      error: 'Rate limit exceeded. Max 3 emails per hour.',
    };
  }

  try {
    const { data, error } = await resend.emails.send({
      from: 'Harikson AI <noreply@neuravolt.cloud>',
      to,
      subject: 'Subscription Cancelled — Harikson AI',
      html: `
        <div style="font-family: sans-serif; padding: 20px; color: #1e293b; max-width: 600px; margin: 0 auto; border: 1px solid #e2e8f0; border-radius: 8px;">
          <h2 style="color: #ef4444; border-bottom: 2px solid #ef4444; padding-bottom: 10px;">Subscription Cancelled</h2>
          <p>Hello,</p>
          <p>Your subscription to the <strong>${planName}</strong> plan has been cancelled as requested.</p>
          <p>Your access will remain active until the end of your billing cycle on <strong>${endDate}</strong>.</p>
          <p>If you change your mind, you can resubscribe at any time from your settings panel.</p>
          <hr style="border: 0; border-top: 1px solid #e2e8f0; margin: 20px 0;" />
          <p style="font-size: 12px; color: #94a3b8;">Secured by Harikson · Enterprise AI Platform</p>
        </div>
      `,
    });
    if (error) {
      logger.error('[EMAIL SEND ERROR - CANCEL]:', error.message || error);
      return {
        success: false,
        error: error.message || 'Failed to send cancellation email',
      };
    }
    return { success: true, data };
  } catch (err: any) {
    logger.error('[EMAIL SEND ERROR - CANCEL]:', err.message);
    return { success: false, error: 'Failed to send cancellation email' };
  }
};

export const sendVerificationEmail = async (to: string, verificationUrl: string) => {
  if (!(await checkEmailRateLimit(to))) {
    return {
      success: false,
      error: 'Rate limit exceeded. Max 3 emails per hour.',
    };
  }

  try {
    const { data, error } = await resend.emails.send({
      from: 'Neuravolt AI <noreply@neuravolt.cloud>',
      to,
      subject: 'Verify your email — Neuravolt',
      html: `
        <div style="font-family: sans-serif; padding: 20px; color: #1e293b; max-width: 600px; margin: 0 auto; border: 1px solid #e2e8f0; border-radius: 8px;">
          <h2 style="color: #2563eb; border-bottom: 2px solid #2563eb; padding-bottom: 10px;">Verify your Neuravolt Account</h2>
          <p>Welcome to Neuravolt!</p>
          <p>Please click the button below to verify your email address and activate your account (link expires in 24 hours):</p>
          <div style="text-align: center; margin: 30px 0;">
            <a href="${verificationUrl}" style="display: inline-block; padding: 12px 24px; background-color: #2563eb; color: white; text-decoration: none; border-radius: 6px; font-weight: bold;">Verify Email Address</a>
          </div>
          <p style="font-size: 13px; color: #64748b;">If the button doesn't work, copy and paste this URL into your browser:</p>
          <p style="font-size: 13px; color: #2563eb; word-break: break-all;">${verificationUrl}</p>
          <hr style="border: 0; border-top: 1px solid #e2e8f0; margin: 20px 0;" />
          <p style="font-size: 12px; color: #94a3b8;">If you did not register for a Neuravolt account, please ignore this message.</p>
        </div>
      `,
    });
    if (error) {
      logger.error('[EMAIL SEND ERROR - VERIFICATION]:', error.message || error);
      return {
        success: false,
        error: error.message || 'Failed to send verification email',
      };
    }
    return { success: true, data };
  } catch (err: any) {
    logger.error('[EMAIL SEND ERROR - VERIFICATION]:', err.message);
    return { success: false, error: 'Failed to send verification email' };
  }
};

export const sendAccountLockoutAlert = async (to: string, unlockUrl: string, durationText: string) => {
  if (!(await checkEmailRateLimit(to))) {
    return {
      success: false,
      error: 'Rate limit exceeded. Max 3 emails per hour.',
    };
  }

  try {
    const { data, error } = await resend.emails.send({
      from: 'Neuravolt Security <security@neuravolt.cloud>',
      to,
      subject: 'Security Alert: Your Neuravolt account has been locked',
      html: `
        <div style="font-family: sans-serif; padding: 20px; color: #1e293b; max-width: 600px; margin: 0 auto; border: 1px solid #fee2e2; border-radius: 8px;">
          <h2 style="color: #dc2626; border-bottom: 2px solid #dc2626; padding-bottom: 10px;">Security Alert: Account Temporarily Locked</h2>
          <p>We detected multiple failed login attempts on your Neuravolt AI account.</p>
          <p>To protect your workspace from brute-force access attempts, your account has been locked for <strong>${durationText}</strong>.</p>
          <p>If this was you or if you need to restore immediate access, please click the button below to safely unlock your account:</p>
          <div style="text-align: center; margin: 30px 0;">
            <a href="${unlockUrl}" style="display: inline-block; padding: 12px 24px; background-color: #dc2626; color: white; text-decoration: none; border-radius: 6px; font-weight: bold;">Unlock Account Now</a>
          </div>
          <p style="font-size: 13px; color: #64748b;">Or copy and paste this link into your browser:</p>
          <p style="font-size: 13px; color: #dc2626; word-break: break-all;">${unlockUrl}</p>
          <hr style="border: 0; border-top: 1px solid #fee2e2; margin: 20px 0;" />
          <p style="font-size: 12px; color: #94a3b8;">If you did not attempt to log in, we recommend reviewing your account credentials immediately.</p>
        </div>
      `,
    });
    if (error) {
      logger.error('[EMAIL SEND ERROR - LOCKOUT ALERT]:', error.message || error);
      return {
        success: false,
        error: error.message || 'Failed to send account lockout alert',
      };
    }
    return { success: true, data };
  } catch (err: any) {
    logger.error('[EMAIL SEND ERROR - LOCKOUT ALERT]:', err.message);
    return { success: false, error: 'Failed to send account lockout alert' };
  }
};

export const sendDeviceMismatchAlert = async (to: string, deviceName: string, ip: string) => {
  if (!(await checkEmailRateLimit(to))) {
    return {
      success: false,
      error: 'Rate limit exceeded. Max 3 emails per hour.',
    };
  }

  try {
    const { data, error } = await resend.emails.send({
      from: 'Neuravolt Security <security@neuravolt.cloud>',
      to,
      subject: 'Security Alert: Device fingerprint mismatch detected',
      html: `
        <div style="font-family: sans-serif; padding: 20px; color: #1e293b; max-width: 600px; margin: 0 auto; border: 1px solid #fee2e2; border-radius: 8px;">
          <h2 style="color: #dc2626; border-bottom: 2px solid #dc2626; padding-bottom: 10px;">Security Alert: Unrecognized Device Blocked</h2>
          <p>We blocked a refresh token request on your Neuravolt AI account from an unrecognized device.</p>
          <p><strong>Device:</strong> ${deviceName || 'Unknown Device'}</p>
          <p><strong>IP Address:</strong> ${ip || 'Unknown IP'}</p>
          <p><strong>Time:</strong> ${new Date().toUTCString()}</p>
          <p>For your security, access was blocked on that device and you must log in again to authenticate.</p>
          <hr style="border: 0; border-top: 1px solid #fee2e2; margin: 20px 0;" />
          <p style="font-size: 12px; color: #94a3b8;">If you did not initiate this request, we recommend changing your account password immediately.</p>
        </div>
      `,
    });
    if (error) {
      logger.error('[EMAIL SEND ERROR - DEVICE MISMATCH ALERT]:', error.message || error);
      return {
        success: false,
        error: error.message || 'Failed to send device mismatch alert',
      };
    }
    return { success: true, data };
  } catch (err: any) {
    logger.error('[EMAIL SEND ERROR - DEVICE MISMATCH ALERT]:', err.message);
    return { success: false, error: 'Failed to send device mismatch alert' };
  }
};

export const sendDunningNotice = async (to: string, dayStage: number, billingUrl: string = 'https://app.neuravolt.cloud/settings/billing') => {
  if (!(await checkEmailRateLimit(to))) {
    return { success: false, error: 'Rate limit exceeded.' };
  }

  let subject = 'Payment Failed - Action Required';
  let heading = 'Payment Failed for Your Neuravolt Subscription';
  let message = 'We were unable to process the latest payment for your Neuravolt AI subscription. Please update your payment details to ensure uninterrupted service.';
  let buttonText = 'Update Billing Details';
  let color = '#dc2626';

  if (dayStage === 3) {
    subject = 'Reminder: Update Payment Method (27 Days Remaining)';
    heading = 'Your Subscription Will Be Suspended in 27 Days';
    message = 'Your subscription payment is past due. To prevent service interruption, please update your payment method.';
  } else if (dayStage === 7) {
    subject = 'Notice: Update Payment Method (23 Days Remaining)';
    heading = 'Your Subscription Will Be Suspended in 23 Days';
    message = 'We still have not received your subscription payment. Please update your card to maintain active access.';
  } else if (dayStage === 14) {
    subject = 'Final Notice: Update Payment Method or Lose Access';
    heading = 'Final Notice: Payment Overdue (Chat Access Restricted)';
    message = 'Your payment is now 14 days overdue. Chat generation is temporarily restricted until payment details are updated.';
    buttonText = 'Pay & Restore Access Now';
  } else if (dayStage === 30) {
    subject = 'Subscription Cancelled due to Non-Payment';
    heading = 'Your Neuravolt Subscription Has Been Cancelled';
    message = 'Your subscription was cancelled after 30 days of uncollected payment. Reactivate your plan anytime to restore access.';
    buttonText = 'Reactivate Subscription';
    color = '#4b5563';
  }

  try {
    const { data, error } = await resend.emails.send({
      from: 'Neuravolt Billing <billing@neuravolt.cloud>',
      to,
      subject,
      html: `
        <div style="font-family: sans-serif; padding: 20px; color: #1e293b; max-width: 600px; margin: 0 auto; border: 1px solid #fee2e2; border-radius: 8px;">
          <h2 style="color: ${color}; border-bottom: 2px solid ${color}; padding-bottom: 10px;">${heading}</h2>
          <p>${message}</p>
          <div style="text-align: center; margin: 30px 0;">
            <a href="${billingUrl}" style="display: inline-block; padding: 12px 24px; background-color: ${color}; color: white; text-decoration: none; border-radius: 6px; font-weight: bold;">${buttonText}</a>
          </div>
          <hr style="border: 0; border-top: 1px solid #fee2e2; margin: 20px 0;" />
          <p style="font-size: 12px; color: #94a3b8;">If you believe this is an error or need billing support, contact support@neuravolt.cloud.</p>
        </div>
      `,
    });

    if (error) {
      logger.error('[EMAIL SEND ERROR - DUNNING NOTICE]:', error.message || error);
      return { success: false, error: error.message };
    }
    return { success: true, data };
  } catch (err: any) {
    logger.error('[EMAIL SEND ERROR - DUNNING NOTICE]:', err.message);
    return { success: false, error: 'Failed to send dunning notice email' };
  }
};


