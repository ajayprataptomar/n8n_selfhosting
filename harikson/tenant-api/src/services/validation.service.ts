export class ValidationService {
  // Check chat responses for toxicity and PII leaks
  static validateChat(text: string): { isValid: boolean; reason?: string } {
    // 1. PII Leak Check (Phone numbers, Email address patterns, SSN)
    const emailRegex = /[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/g;
    const phoneRegex = /(\+?\d{1,4}[-.\s]??)?[0-9]{10}/g;
    const ssnRegex = /\d{3}-\d{2}-\d{4}/g;

    if (emailRegex.test(text)) {
      return {
        isValid: false,
        reason: 'PII Leak: Found email address in generation.',
      };
    }
    if (phoneRegex.test(text)) {
      return {
        isValid: false,
        reason: 'PII Leak: Found potential phone number.',
      };
    }
    if (ssnRegex.test(text)) {
      return { isValid: false, reason: 'PII Leak: Found SSN format.' };
    }

    // 2. Simple Toxicity Keywords Filter
    const toxicityKeywords = ['abusiveword1', 'abusiveword2', 'toxicpattern'];
    const lowercaseText = text.toLowerCase();
    for (const word of toxicityKeywords) {
      if (lowercaseText.includes(word)) {
        return {
          isValid: false,
          reason: `Toxicity Check failed: Found blocked terms.`,
        };
      }
    }

    return { isValid: true };
  }

  // Check generated code for basic syntax and security vulnerabilities
  static validateCode(
    code: string,
    language: string
  ): { isValid: boolean; reason?: string } {
    // 1. Security scan: SQL Injection check
    const sqliPatterns = [
      /union\s+select/i,
      /or\s+['"]?\d+['"]?\s*=\s*['"]?\d+/i,
      /drop\s+table/i,
    ];
    for (const pattern of sqliPatterns) {
      if (pattern.test(code)) {
        return {
          isValid: false,
          reason: 'Security Scan: Found potential SQL Injection exploit query.',
        };
      }
    }

    // 2. Security scan: XSS vulnerabilities check
    const xssPatterns = [
      /<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/i,
      /javascript:/i,
    ];
    for (const pattern of xssPatterns) {
      if (pattern.test(code)) {
        return {
          isValid: false,
          reason:
            'Security Scan: Found potential Cross-Site Scripting (XSS) payload.',
        };
      }
    }

    // 3. Syntax checks
    if (language === 'javascript' || language === 'json') {
      try {
        if (language === 'json') {
          JSON.parse(code);
        }
      } catch (err: any) {
        return {
          isValid: false,
          reason: `Syntax Parse failed: ${err.message}`,
        };
      }
    }

    return { isValid: true };
  }
}
