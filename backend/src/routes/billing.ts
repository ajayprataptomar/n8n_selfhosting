import { Router, Response } from "express";
import { prisma } from "../lib/prisma.js";
import { authMiddleware, adminMiddleware, AuthenticatedRequest } from "../middleware/auth.js";
import { InvoiceService } from "../services/invoice.service.js";

const router = Router();

// Apply auth checking
router.use(authMiddleware);

// GET /billing/invoices - retrieve invoices (Admin: all, User: only owned)
router.get("/invoices", async (req: AuthenticatedRequest, res: Response, next) => {
  try {
    if (req.user?.role === "ADMIN") {
      const invoices = await prisma.invoice.findMany({
        include: { user: { select: { email: true, name: true } } },
        orderBy: { createdAt: "desc" },
      });
      return res.status(200).json(invoices);
    }

    const myInvoices = await prisma.invoice.findMany({
      where: { userId: req.user?.userId },
      orderBy: { createdAt: "desc" },
    });
    res.status(200).json(myInvoices);
  } catch (error) {
    next(error);
  }
});

// POST /billing/generate - Admin manual generation trigger
router.post("/generate", adminMiddleware, async (req: AuthenticatedRequest, res: Response, next) => {
  try {
    const { userId } = req.body;
    if (!userId) {
      return res.status(400).json({ error: "userId parameter is required." });
    }

    const invoice = await InvoiceService.generateInvoice(userId);
    res.status(201).json(invoice);
  } catch (error) {
    next(error);
  }
});

// POST /billing/invoices/:id/pay - User manual mock payment trigger (or bank validation)
router.post("/invoices/:id/pay", async (req: AuthenticatedRequest, res: Response, next) => {
  try {
    const { id } = req.params;

    const invoice = await prisma.invoice.findFirst({
      where: req.user?.role === "ADMIN" ? { id } : { id, userId: req.user?.userId },
    });

    if (!invoice) {
      return res.status(404).json({ error: "Invoice not found or unauthorized access." });
    }

    if (invoice.status === "PAID") {
      return res.status(400).json({ error: "Invoice has already been paid." });
    }

    const updated = await prisma.invoice.update({
      where: { id },
      data: {
        status: "PAID",
        paidAt: new Date(),
      },
    });

    res.status(200).json({ message: "Invoice processed successfully.", invoice: updated });
  } catch (error) {
    next(error);
  }
});

export default router;
