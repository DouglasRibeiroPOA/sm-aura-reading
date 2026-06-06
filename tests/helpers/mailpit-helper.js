/**
 * MailPit API Helper for E2E Tests
 *
 * Integrates with MailPit API to automatically retrieve emails, extract OTPs, and magic links.
 *
 * Requirements:
 *   - MailPit running locally (default: http://localhost:8025)
 *
 * Usage:
 *   const mailpit = new MailPitHelper();
 *   const otp = await mailpit.extractOTP('test@example.com');
 *   const magicLink = await mailpit.extractMagicLink('test@example.com');
 */

class MailPitHelper {
  /**
   * Initialize MailPit helper
   *
   * @param {string} baseUrl - MailPit API base URL (default: http://localhost:8025)
   * @param {number} timeout - Request timeout in ms (default: 5000)
   */
  constructor(baseUrl = null, timeout = 5000) {
    this.baseUrl = baseUrl || process.env.MAILPIT_URL || 'http://localhost:8025';
    this.apiUrl = `${this.baseUrl}/api/v1`;
    this.timeout = timeout;

    console.log(`[MailPit] Initialized with base URL: ${this.baseUrl}`);
  }

  /**
   * Execute HTTP request with timeout using native fetch.
   *
   * @param {string} url - Absolute URL
   * @param {Object} options - Fetch options
   * @returns {Promise<any>} Parsed JSON body
   */
  async request(url, options = {}) {
    const controller = new AbortController();
    const timer = setTimeout(() => controller.abort(), this.timeout);
    try {
      const response = await fetch(url, { ...options, signal: controller.signal });
      if (!response.ok) {
        throw new Error(`HTTP ${response.status}`);
      }
      return response.json();
    } finally {
      clearTimeout(timer);
    }
  }

  /**
   * Get all messages from MailPit
   *
   * @param {number} limit - Maximum number of messages to retrieve (default: 50)
   * @returns {Promise<Array>} Array of email messages
   */
  async getAllMessages(limit = 50) {
    try {
      const query = new URLSearchParams({ limit: String(limit) });
      const response = await this.request(`${this.apiUrl}/messages?${query.toString()}`);
      return response.messages || [];
    } catch (error) {
      console.error('[MailPit] Error fetching messages:', error.message);
      throw new Error(`Failed to fetch messages from MailPit: ${error.message}`);
    }
  }

  /**
   * Get latest email for specific recipient
   *
   * @param {string} email - Recipient email address
   * @param {number} maxWaitMs - Maximum wait time in ms (default: 10000)
   * @param {number} pollIntervalMs - Polling interval in ms (default: 500)
   * @returns {Promise<Object|null>} Email message object or null if not found
   */
  async getLatestEmail(email, maxWaitMs = 10000, pollIntervalMs = 500) {
    const startTime = Date.now();

    console.log(`[MailPit] Waiting for email to: ${email}`);

    while (Date.now() - startTime < maxWaitMs) {
      try {
        const messages = await this.getAllMessages();

        // Find email matching recipient
        const matchingEmail = messages.find(msg => {
          const recipients = msg.To || [];
          return recipients.some(to => to.Address === email);
        });

        if (matchingEmail) {
          console.log(`[MailPit] ✅ Found email: ${matchingEmail.Subject}`);

          // Fetch full message details
          const fullMessage = await this.getMessageById(matchingEmail.ID);
          return fullMessage;
        }

        // Wait before next poll
        await this.sleep(pollIntervalMs);
      } catch (error) {
        console.error(`[MailPit] Error polling for email:`, error.message);
      }
    }

    console.error(`[MailPit] ❌ Email not found after ${maxWaitMs}ms`);
    return null;
  }

  /**
   * Get full message details by ID
   *
   * @param {string} messageId - MailPit message ID
   * @returns {Promise<Object>} Full email message object
   */
  async getMessageById(messageId) {
    try {
      return this.request(`${this.apiUrl}/message/${messageId}`);
    } catch (error) {
      console.error(`[MailPit] Error fetching message ${messageId}:`, error.message);
      throw new Error(`Failed to fetch message: ${error.message}`);
    }
  }

  /**
   * Extract OTP code from email
   *
   * Looks for 4-6 digit numeric codes in email subject and body.
   *
   * @param {string} email - Recipient email address
   * @param {number} maxWaitMs - Maximum wait time in ms (default: 10000)
   * @returns {Promise<string|null>} 4-6 digit OTP code or null if not found
   */
  async extractOTP(email, maxWaitMs = 10000) {
    console.log(`[MailPit] Extracting OTP for: ${email}`);

    const message = await this.getLatestEmail(email, maxWaitMs);

    if (!message) {
      console.error('[MailPit] ❌ No email found to extract OTP from');
      return null;
    }

    // Check subject line first
    const subjectMatch = message.Subject.match(/\b(\d{4,6})\b/);
    if (subjectMatch) {
      const otp = subjectMatch[1];
      console.log(`[MailPit] ✅ OTP found in subject: ${otp}`);
      return otp;
    }

    // Check email body (both HTML and Text)
    const bodyText = message.Text || '';
    const bodyHTML = message.HTML || '';
    const combinedBody = `${bodyText} ${bodyHTML}`;

    const bodyMatch = combinedBody.match(/\b(\d{4,6})\b/);
    if (bodyMatch) {
      const otp = bodyMatch[1];
      console.log(`[MailPit] ✅ OTP found in body: ${otp}`);
      return otp;
    }

    console.error('[MailPit] ❌ No OTP code found in email');
    return null;
  }

  /**
   * Extract magic link from email
   *
   * Looks for URLs containing 'sm_magic=1' or 'sm_report=1'.
   *
   * @param {string} email - Recipient email address
   * @param {number} maxWaitMs - Maximum wait time in ms (default: 10000)
   * @returns {Promise<string|null>} Magic link URL or null if not found
   */
  async extractMagicLink(email, maxWaitMs = 10000) {
    console.log(`[MailPit] Extracting magic link for: ${email}`);

    const message = await this.getLatestEmail(email, maxWaitMs);

    if (!message) {
      console.error('[MailPit] ❌ No email found to extract magic link from');
      return null;
    }

    // Check both HTML and Text parts
    const bodyText = message.Text || '';
    const bodyHTML = message.HTML || '';
    const combinedBody = `${bodyText} ${bodyHTML}`;

    // Pattern 1: Magic link with sm_magic=1
    const magicLinkMatch = combinedBody.match(/https?:\/\/[^\s<>"]+sm_magic=1[^\s<>"]*/i);
    if (magicLinkMatch) {
      const link = magicLinkMatch[0].replace(/&amp;/g, '&'); // Decode HTML entities
      console.log(`[MailPit] ✅ Magic link found: ${link}`);
      return link;
    }

    // Pattern 2: Resubmit link for invalid image flow
    const resubmitLinkMatch = combinedBody.match(/https?:\/\/[^\s<>"]+sm_resubmit=1[^\s<>"]*/i);
    if (resubmitLinkMatch) {
      const link = resubmitLinkMatch[0].replace(/&amp;/g, '&');
      console.log(`[MailPit] ✅ Resubmit link found: ${link}`);
      return link;
    }

    // Pattern 3: Report link with sm_report=1 and lead_id
    const reportLinkMatch = combinedBody.match(/https?:\/\/[^\s<>"]+sm_report=1[^\s<>"]*/i);
    if (reportLinkMatch) {
      const link = reportLinkMatch[0].replace(/&amp;/g, '&');
      console.log(`[MailPit] ✅ Report link found: ${link}`);
      return link;
    }

    // Pattern 4: Generic link with lead_id (uuid) and token
    const genericLinkMatch = combinedBody.match(/https?:\/\/[^\s<>"]+lead_id=[0-9a-f-]{36}[^\s<>"]*/i);
    if (genericLinkMatch) {
      const link = genericLinkMatch[0].replace(/&amp;/g, '&');
      console.log(`[MailPit] ✅ Generic report link found: ${link}`);
      return link;
    }

    console.error('[MailPit] ❌ No magic link found in email');
    return null;
  }

  /**
   * Clear all messages in MailPit
   *
   * Useful for cleaning up between test runs.
   *
   * @returns {Promise<boolean>} True if successful, false otherwise
   */
  async clearAllMessages() {
    try {
      await this.request(`${this.apiUrl}/messages`, { method: 'DELETE' });

      console.log('[MailPit] ✅ All messages cleared');
      return true;
    } catch (error) {
      console.error('[MailPit] Error clearing messages:', error.message);
      return false;
    }
  }

  /**
   * Delete specific message by ID
   *
   * @param {string} messageId - MailPit message ID
   * @returns {Promise<boolean>} True if successful, false otherwise
   */
  async deleteMessage(messageId) {
    try {
      await this.request(`${this.apiUrl}/message/${messageId}`, { method: 'DELETE' });

      console.log(`[MailPit] ✅ Message ${messageId} deleted`);
      return true;
    } catch (error) {
      console.error(`[MailPit] Error deleting message ${messageId}:`, error.message);
      return false;
    }
  }

  /**
   * Wait for specified duration
   *
   * @param {number} ms - Duration in milliseconds
   * @returns {Promise<void>}
   */
  async sleep(ms) {
    return new Promise(resolve => setTimeout(resolve, ms));
  }

  /**
   * Check if MailPit is running and accessible
   *
   * @returns {Promise<boolean>} True if MailPit is accessible, false otherwise
   */
  async isAvailable() {
    try {
      const query = new URLSearchParams({ limit: '1' });
      await this.request(`${this.apiUrl}/messages?${query.toString()}`);

      console.log('[MailPit] ✅ MailPit is available');
      return true;
    } catch (error) {
      console.error('[MailPit] ❌ MailPit is not accessible:', error.message);
      return false;
    }
  }
}

module.exports = MailPitHelper;

/**
 * Example usage:
 *
 * const MailPitHelper = require('./mailpit-helper');
 *
 * const mailpit = new MailPitHelper();
 *
 * // Wait for OTP email
 * const otp = await mailpit.extractOTP('test@example.com');
 * console.log('OTP:', otp);
 *
 * // Wait for magic link email
 * const magicLink = await mailpit.extractMagicLink('test@example.com');
 * console.log('Magic Link:', magicLink);
 *
 * // Clean up
 * await mailpit.clearAllMessages();
 */
