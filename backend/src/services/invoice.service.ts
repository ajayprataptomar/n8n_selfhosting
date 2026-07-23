import { prisma } from "../lib/prisma.js";
import { EmailService } from "./email.service.js";

export class InvoiceService {
  // Configured costs per plan tier
  private static planPricing: Record<string, number> = {
    STARTER: 999.00,
    PRO: 1999.00,
    BUSINESS: 3999.00,
    ENTERPRISE: 9999.00,
  };

  static async generateInvoice(userId: string): Promise<any> {
    const user = await prisma.user.findUnique({
      where: { id: userId },
      include: { instances: true },
    });

    if (!user) {
      throw new Error("User not found");
    }

    const planCost = this.planPricing[user.plan] || 999.00;
    
    // Add additional costs for running instances if any (e.g. 200 per active container)
    const instancesCost = user.instances.filter(i => i.status === "RUNNING").length * 200.00;
    const totalAmount = planCost + instancesCost;

    const dueDate = new Date();
    dueDate.setDate(dueDate.getDate() + 7); // Due in 7 days

    const invoice = await prisma.invoice.create({
      data: {
        userId: user.id,
        amount: totalAmount,
        currency: "INR",
        status: "PENDING",
        dueDate,
        items: [
          { name: `${user.plan} Subscription Fee`, amount: planCost, period: "Monthly" },
          { name: "Active Micro-Containers Add-on", amount: instancesCost, count: user.instances.length },
        ],
      },
    });

    // Fire credentials email or invoice email
    try {
      await EmailService.sendInvoiceEmail(user.email, invoice.id, totalAmount, dueDate.toDateString());
    } catch (err) {
      console.warn("Could not dispatch invoice email:", err);
    }

    return invoice;
  }
}
export default InvoiceService;
