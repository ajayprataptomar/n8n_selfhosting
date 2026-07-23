import { prisma } from '../config/database.js';
import { DockerService } from './docker.service.js';
import Razorpay from 'razorpay';

const PRICING: Record<string, { price: number; ram: string; cpu: number }> = {
  STARTER: { price: 2499, ram: '512m', cpu: 0.5 },
  PRO: { price: 4999, ram: '1024m', cpu: 1.0 },
  BUSINESS: { price: 10999, ram: '2048m', cpu: 2.0 },
  ENTERPRISE: { price: 0, ram: '4096m', cpu: 4.0 },
};

export class BillingService {
  private mode: string = 'manual';
  private gateway: string | null = null;

  constructor() {
    this.loadConfig();
  }

  async loadConfig() {
    try {
      const config = await prisma.systemSetting.findUnique({
        where: { key: 'billing_mode' },
      });
      if (config && config.value && typeof config.value === 'object') {
        const val = config.value as any;
        this.mode = val.mode || 'manual';
        this.gateway = val.gateway || null;
      }
    } catch (e) {
      console.warn('⚠️ Failed to load billing config from DB:', e);
    }
  }

  isAutoBilling(): boolean {
    return this.mode !== 'manual';
  }

  async handleSignup(tenantData: any) {
    await this.loadConfig();
    if (this.mode === 'manual') {
      return this.createPendingTenant(tenantData);
    }
    return this.createTenantWithPayment(tenantData);
  }

  private async createPendingTenant(data: any) {
    const tenant = await prisma.tenant.create({
      data: {
        name: data.name,
        domain: `${data.name}.neuravolt.cloud`,
        plan: data.plan || 'STARTER',
        agentType: data.agentType || 'CHAT',
        model: data.model || 'qwen3-coder-8b',
        billingMode: 'manual',
        approvalStatus: 'pending',
        requestedPlan: data.plan || 'STARTER',
        requestedAt: new Date(),
        status: 'PENDING',
        email: data.email,
        phone: data.phone,
        whiteLabelSettings: data.whiteLabelSettings || {},
      },
    });

    // Notify admin
    await this.notifyAdmin({
      type: 'TENANT_APPROVAL_REQUEST',
      tenantId: tenant.id,
      message: `New tenant "${tenant.name}" requests ${tenant.requestedPlan} plan`,
    });

    return {
      tenant,
      message: 'Request submitted. Admin will review shortly.',
      nextStep: 'WAIT_FOR_APPROVAL',
    };
  }

  private async createTenantWithPayment(data: any) {
    const tenant = await prisma.tenant.create({
      data: {
        name: data.name,
        domain: `${data.name}.neuravolt.cloud`,
        plan: data.plan || 'STARTER',
        agentType: data.agentType || 'CHAT',
        model: data.model || 'qwen3-coder-8b',
        billingMode: `auto_${this.gateway}`,
        approvalStatus: 'pending', // will get approved on payment
        requestedPlan: data.plan || 'STARTER',
        requestedAt: new Date(),
        status: 'PENDING',
        email: data.email,
        phone: data.phone,
        whiteLabelSettings: data.whiteLabelSettings || {},
      },
    });

    const order = await this.createPaymentOrder(tenant);

    return {
      tenant,
      paymentOrder: order,
      message: 'Please complete payment to activate your agent',
      nextStep: 'COMPLETE_PAYMENT',
    };
  }

  private async createPaymentOrder(tenant: any) {
    const planKey = tenant.requestedPlan as keyof typeof PRICING;
    const plan = PRICING[planKey] || PRICING.STARTER;

    if (this.gateway === 'razorpay') {
      return this.createRazorpayOrder(tenant, plan);
    } else if (this.gateway === 'cashfree') {
      return this.createCashfreeOrder(tenant, plan);
    }

    throw new Error('Invalid gateway');
  }

  private async createRazorpayOrder(tenant: any, plan: any) {
    const keyId = process.env.RAZORPAY_KEY_ID || 'rzp_test_mock';
    const keySecret = process.env.RAZORPAY_KEY_SECRET || 'mock_secret';

    const razorpay = new Razorpay({
      key_id: keyId,
      key_secret: keySecret,
    });

    const order = await razorpay.orders.create({
      amount: plan.price * 100,
      currency: 'INR',
      receipt: `tenant_${tenant.id}`,
      notes: {
        tenantId: tenant.id,
        plan: tenant.requestedPlan,
      },
    });

    await prisma.invoice.create({
      data: {
        tenantId: tenant.id,
        amount: plan.price,
        currency: 'INR',
        gateway: 'razorpay',
        gatewayOrderId: order.id,
        plan: tenant.requestedPlan,
        period: 'monthly',
      },
    });

    return order;
  }

  private async createCashfreeOrder(tenant: any, plan: any) {
    const clientId = process.env.CASHFREE_CLIENT_ID || 'cf_mock_client';
    const clientSecret = process.env.CASHFREE_CLIENT_SECRET || 'cf_mock_secret';

    const response = await fetch('https://api.cashfree.com/pg/orders', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'x-client-id': clientId,
        'x-client-secret': clientSecret,
        'x-api-version': '2022-09-01',
      },
      body: JSON.stringify({
        order_id: `tenant_${tenant.id}_${Date.now()}`,
        order_amount: plan.price,
        order_currency: 'INR',
        customer_details: {
          customer_id: tenant.id,
          customer_email: tenant.email || 'support@neuravolt.cloud',
          customer_phone: tenant.phone || '9999999999',
        },
      }),
    });

    const order = (await response.json()) as any;

    await prisma.invoice.create({
      data: {
        tenantId: tenant.id,
        amount: plan.price,
        currency: 'INR',
        gateway: 'cashfree',
        gatewayOrderId: order.order_id || order.id || `mock_${Date.now()}`,
        plan: tenant.requestedPlan,
        period: 'monthly',
      },
    });

    return order;
  }

  async approveAndDeploy(tenantId: string, adminId: string) {
    const tenant = await prisma.tenant.findUnique({
      where: { id: tenantId },
    });

    if (!tenant) throw new Error('Tenant not found');

    // Trigger deployment via DockerService
    const deployment = await DockerService.createTenantStack(
      tenant.name,
      tenant.requestedPlan,
      tenant.agentType
    );

    const updatedTenant = await prisma.tenant.update({
      where: { id: tenantId },
      data: {
        approvalStatus: 'approved',
        approvedBy: adminId,
        approvedAt: new Date(),
        status: 'RUNNING',
        domain: deployment.domain,
        containerId: deployment.containerId,
        plan: tenant.requestedPlan, // Set active plan to requested plan
      },
    });

    // Email user
    await this.emailUser(tenant.email || 'user@neuravolt.cloud', {
      subject: 'Your Harikson AI Agent is Ready!',
      body: `Your agent is deployed at: https://${deployment.domain}`,
    });

    return updatedTenant;
  }

  async switchBillingMode(mode: string, gateway?: string) {
    await prisma.systemSetting.upsert({
      where: { key: 'billing_mode' },
      update: {
        value: { mode, gateway },
        updatedAt: new Date(),
      },
      create: {
        key: 'billing_mode',
        value: { mode, gateway },
        description: 'Billing mode configuration',
      },
    });

    this.mode = mode;
    this.gateway = gateway || null;

    return { mode, gateway };
  }

  private async notifyAdmin(data: any) {
    const admins = await prisma.adminUser.findMany({
      where: { role: { in: ['super', 'ops'] } },
    });

    for (const admin of admins) {
      await this.emailUser(admin.email, {
        subject: 'Harikson: New Tenant Approval Request',
        body: `${data.message}. Review admin dashboard.`,
      });
    }
  }

  async emailUser(email: string, data: any) {
    console.log(
      `📧 [Harikson Email] Sent to ${email}: ${data.subject}\nBody: ${data.body}`
    );
  }
}
