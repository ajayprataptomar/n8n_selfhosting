import fs from "fs";
import path from "path";

export class EmailService {
  private static getApiKey(): string {
    try {
      const keyPath = process.env.RESEND_API_KEY_FILE || path.join(process.cwd(), "secrets", "resend_api_key");
      if (fs.existsSync(keyPath)) {
        return fs.readFileSync(keyPath, "utf-8").trim();
      }
    } catch {
      // Ignored
    }
    return process.env.RESEND_API_KEY || "re_dev_key_placeholder_value";
  }

  static async sendWelcomeEmail(email: string, name: string): Promise<boolean> {
    const apiKey = this.getApiKey();
    console.log(`[Email Service] Sending Welcome Email to ${email} (Name: ${name})`);
    console.log(`[Email Body] Hi ${name}, Welcome to Neuravolt Cloud! Your account registration is pending admin approval.`);

    if (apiKey === "re_dev_key_placeholder_value") {
      console.log("[Email Service] Resend key is placeholder. Skipping actual HTTP post.");
      return true;
    }

    try {
      const res = await fetch("https://api.resend.com/emails", {
        method: "POST",
        headers: {
          "Authorization": `Bearer ${apiKey}`,
          "Content-Type": "application/json",
        },
        body: JSON.stringify({
          from: "Neuravolt Cloud <welcome@neuravolt.cloud>",
          to: [email],
          subject: "Welcome to Neuravolt Cloud!",
          html: `<p>Hi <strong>${name}</strong>,</p><p>Welcome to Neuravolt Cloud! Your account is pending admin approval. You will receive another email once approved with your instances access details.</p>`,
        }),
      });
      return res.ok;
    } catch (err) {
      console.error("[Email Service] Failed to send welcome email:", err);
      return false;
    }
  }

  static async sendCredentialsEmail(email: string, name: string, domain: string): Promise<boolean> {
    const apiKey = this.getApiKey();
    console.log(`[Email Service] Sending Activation & Credentials Email to ${email}`);
    console.log(`[Email Body] Hi ${name}, your instance is live at https://${domain}.`);

    if (apiKey === "re_dev_key_placeholder_value") {
      console.log("[Email Service] Resend key is placeholder. Skipping actual HTTP post.");
      return true;
    }

    try {
      const res = await fetch("https://api.resend.com/emails", {
        method: "POST",
        headers: {
          "Authorization": `Bearer ${apiKey}`,
          "Content-Type": "application/json",
        },
        body: JSON.stringify({
          from: "Neuravolt Cloud <provision@neuravolt.cloud>",
          to: [email],
          subject: "Your Neuravolt Cloud Instance is Ready!",
          html: `<p>Hi ${name},</p><p>Great news! Your account has been approved and your cloud instance is now running.</p><p><strong>Instance URL:</strong> <a href="https://${domain}" target="_blank">https://${domain}</a></p><p>Log in using your account details in the User Portal to access your tools!</p>`,
        }),
      });
      return res.ok;
    } catch (err) {
      console.error("[Email Service] Failed to send credentials email:", err);
      return false;
    }
  }

  static async sendInvoiceEmail(email: string, invoiceId: string, amount: number, dueDate: string): Promise<boolean> {
    const apiKey = this.getApiKey();
    console.log(`[Email Service] Sending Invoice Email for ${invoiceId} to ${email}`);
    console.log(`[Email Body] Invoice ID: ${invoiceId}, Amount: INR ${amount}, Due Date: ${dueDate}`);

    if (apiKey === "re_dev_key_placeholder_value") {
      console.log("[Email Service] Resend key is placeholder. Skipping actual HTTP post.");
      return true;
    }

    try {
      const res = await fetch("https://api.resend.com/emails", {
        method: "POST",
        headers: {
          "Authorization": `Bearer ${apiKey}`,
          "Content-Type": "application/json",
        },
        body: JSON.stringify({
          from: "Neuravolt Cloud Billing <billing@neuravolt.cloud>",
          to: [email],
          subject: `Invoice #${invoiceId} generated for your Neuravolt Account`,
          html: `<p>Dear Customer,</p><p>A new invoice has been generated for your cloud resources.</p><p><strong>Invoice ID:</strong> #${invoiceId}<br/><strong>Amount Due:</strong> INR ${amount.toFixed(2)}<br/><strong>Due Date:</strong> ${dueDate}</p><p>Log in to your account dashboard to process the payment.</p>`,
        }),
      });
      return res.ok;
    } catch (err) {
      console.error("[Email Service] Failed to send invoice email:", err);
      return false;
    }
  }
}
export default EmailService;
