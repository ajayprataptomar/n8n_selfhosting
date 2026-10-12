/**
 * Phone Number Formatting Utilities
 */

/**
 * Check if receiver is a group ID
 * Group IDs have format: 1234567890-1234567890 or already have @g.us suffix
 * @param {string} receiver - Receiver ID
 * @returns {boolean} True if group
 */
export const isGroup = (receiver) => {
  if (!receiver) return false;

  // Already has group suffix
  if (receiver.endsWith('@g.us')) {
    return true;
  }

  // Group ID format: numbers-numbers (e.g., 1234567890-1234567890)
  // This is the standard WhatsApp group ID format
  if (/^\d+-\d+$/.test(receiver) || /^\d+-\d+@/.test(receiver)) {
    return true;
  }

  return false;
};

/**
 * Format phone number for WhatsApp
 * @param {string} phone - Phone number
 * @returns {string} Formatted phone number with @s.whatsapp.net
 */
export const formatPhone = (phone) => {
  if (!phone) return null;

  // Already formatted
  if (phone.endsWith('@s.whatsapp.net')) {
    return phone;
  }

  // Remove all non-digit characters
  let formatted = phone.replace(/\D/g, '');

  // Add WhatsApp suffix
  return `${formatted}@s.whatsapp.net`;
};

/**
 * Format group ID for WhatsApp
 * @param {string} groupId - Group ID
 * @returns {string} Formatted group ID with @g.us
 */
export const formatGroup = (groupId) => {
  if (!groupId) return null;

  // Already formatted
  if (groupId.endsWith('@g.us')) {
    return groupId;
  }

  // Keep digits and hyphens only
  let formatted = groupId.replace(/[^\d-]/g, '');

  // Add group suffix
  return `${formatted}@g.us`;
};

/**
 * Format receiver for WhatsApp - automatically detects group or individual
 * @param {string} receiver - Receiver phone/group ID
 * @returns {string} Formatted receiver with appropriate suffix
 */
export const formatReceiver = (receiver) => {
  if (!receiver) return null;

  // Already formatted
  if (receiver.endsWith('@s.whatsapp.net') || receiver.endsWith('@g.us')) {
    return receiver;
  }

  // Check if it's a group
  if (isGroup(receiver)) {
    return formatGroup(receiver);
  }

  // Individual number
  return formatPhone(receiver);
};

/**
 * Extract phone number from JID
 * @param {string} jid - WhatsApp JID
 * @returns {string} Phone number without suffix
 */
export const extractPhone = (jid) => {
  if (!jid) return null;

  // Remove @s.whatsapp.net or @c.us
  return jid.replace(/@s\.whatsapp\.net|@c\.us/g, '').split(':')[0];
};

/**
 * Validate phone number format
 * @param {string} phone - Phone number
 * @returns {boolean} True if valid
 */
export const isValidPhone = (phone) => {
  if (!phone) return false;

  const cleaned = phone.replace(/\D/g, '');

  // Should be between 10-15 digits
  return cleaned.length >= 10 && cleaned.length <= 15;
};

export default {
  isGroup,
  formatPhone,
  formatGroup,
  formatReceiver,
  extractPhone,
  isValidPhone,
};
