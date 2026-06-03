"use client";

import React, { useState, useEffect } from "react";
import { CreditCard, AlertTriangle, Plus, CheckCircle, RefreshCw } from "lucide-react";
import ApiClient from "../../lib/api";

interface InvoiceRecord {
  id: string;
  userId: string;
  amount: string;
  currency: string;
  status: "PENDING" | "PAID" | "OVERDUE" | "CANCELLED";
  dueDate: string;
  paidAt: string | null;
  items: any;
  user?: {
    email: string;
    name: string | null;
  };
}

export default function BillingAdmin() {
  const [invoices, setInvoices] = useState<InvoiceRecord[]>([]);
  const [loading, setLoading] = useState(true);
  const [showGenModal, setShowGenModal] = useState(false);
  const [genUserId, setGenUserId] = useState("");
  const [actionLoading, setActionLoading] = useState(false);

  const loadInvoices = async () => {
    try {
      const data = await ApiClient.get<InvoiceRecord[]>("/billing/invoices");
      setInvoices(data);
    } catch (err) {
      console.warn("API `/billing/invoices` unreachable, using mock invoice databases.");
      setInvoices([
        {
          id: "inv_ae89",
          userId: "usr_2",
          amount: "1999.00",
          currency: "INR",
          status: "PAID",
          dueDate: new Date(Date.now() + 2 * 24 * 60 * 60 * 1000).toDateString(),
          paidAt: new Date().toDateString(),
          items: [{ name: "PRO Subscription Fee", amount: 1999 }],
          user: { email: "priya@neuravolt.in", name: "Priya Patel" }
        },
        {
          id: "inv_cd42",
          userId: "usr_3",
          amount: "3999.00",
          currency: "INR",
          status: "PENDING",
          dueDate: new Date(Date.now() - 4 * 24 * 60 * 60 * 1000).toDateString(),
          paidAt: null,
          items: [{ name: "HEAVY Subscription Fee", amount: 3999 }],
          user: { email: "amit@enterprise.com", name: "Amit Kumar" }
        }
      ]);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    loadInvoices();
  }, []);

  const handleGenerateInvoice = async (e: React.FormEvent) => {
    e.preventDefault();
    setActionLoading(true);
    try {
      await ApiClient.post("/billing/generate", { userId: genUserId });
      setShowGenModal(false);
      setGenUserId("");
      await loadInvoices();
    } catch (err: any) {
      alert(err.message || "Failed to generate invoice");
    } finally {
      setActionLoading(false);
    }
  };

  const handleMarkPaid = async (id: string) => {
    try {
      await ApiClient.post(`/billing/invoices/${id}/pay`);
      await loadInvoices();
    } catch (err: any) {
      alert(err.message || "Payment update failed");
    }
  };

  const calculateRevenue = () => {
    const paid = invoices.filter(i => i.status === "PAID").reduce((acc, curr) => acc + parseFloat(curr.amount), 0);
    const pending = invoices.filter(i => i.status === "PENDING").reduce((acc, curr) => acc + parseFloat(curr.amount), 0);
    return { paid, pending };
  };

  const { paid, pending } = calculateRevenue();

  return (
    <div>
      {/* Header */}
      <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", marginBottom: "30px" }}>
        <div>
          <h1 style={{ fontSize: "1.75rem", fontWeight: "700" }}>Accounts & Invoices Ledger</h1>
          <p style={{ color: "rgba(255,255,255,0.5)", fontSize: "0.9rem" }}>Track receivables, generate bills, and audit invoice states</p>
        </div>
        <button onClick={() => setShowGenModal(true)} className="btn btn-primary">
          <Plus size={16} />
          <span>New Invoice</span>
        </button>
      </div>

      {/* Analytics Summary */}
      <div style={{ display: "grid", gridTemplateColumns: "repeat(auto-fit, minmax(260px, 1fr))", gap: "20px", marginBottom: "40px" }}>
        <div className="glass-card" style={{ padding: "24px", display: "flex", alignItems: "center", gap: "20px" }}>
          <div style={{ padding: "12px", borderRadius: "12px", background: "rgba(16, 185, 129, 0.1)", color: "#10b981" }}>
            <CheckCircle size={24} />
          </div>
          <div>
            <p style={{ fontSize: "0.85rem", color: "rgba(255,255,255,0.5)", fontWeight: "500" }}>Total Paid Revenue</p>
            <h3 style={{ fontSize: "1.75rem", fontWeight: "700", marginTop: "4px" }}>₹{paid.toFixed(2)}</h3>
          </div>
        </div>

        <div className="glass-card" style={{ padding: "24px", display: "flex", alignItems: "center", gap: "20px" }}>
          <div style={{ padding: "12px", borderRadius: "12px", background: "rgba(245, 158, 11, 0.1)", color: "#f59e0b" }}>
            <AlertTriangle size={24} />
          </div>
          <div>
            <p style={{ fontSize: "0.85rem", color: "rgba(255,255,255,0.5)", fontWeight: "500" }}>Outstanding Receivables</p>
            <h3 style={{ fontSize: "1.75rem", fontWeight: "700", marginTop: "4px" }}>₹{pending.toFixed(2)}</h3>
          </div>
        </div>
      </div>

      {/* Invoices list */}
      <div className="glass-card" style={{ padding: "10px", overflow: "hidden" }}>
        {loading ? (
          <div style={{ display: "flex", justifyContent: "center", alignItems: "center", padding: "60px", color: "rgba(255,255,255,0.4)" }}>
            Fetching Invoices...
          </div>
        ) : invoices.length === 0 ? (
          <div style={{ padding: "50px", textAlign: "center", color: "rgba(255,255,255,0.4)" }}>
            No invoice records exist inside the system databases yet.
          </div>
        ) : (
          <div className="table-container">
            <table className="custom-table">
              <thead>
                <tr>
                  <th>Invoice ID</th>
                  <th>Client</th>
                  <th>Amount</th>
                  <th>Payment Status</th>
                  <th>Due Date</th>
                  <th>Billing Control</th>
                </tr>
              </thead>
              <tbody>
                {invoices.map((inv) => (
                  <tr key={inv.id}>
                    <td style={{ fontFamily: "monospace", fontSize: "0.85rem", fontWeight: "600" }}>
                      #{inv.id}
                    </td>
                    <td>
                      <div>
                        <div style={{ fontWeight: "600", fontSize: "0.9rem" }}>{inv.user?.name || "Manual Record"}</div>
                        <div style={{ fontSize: "0.75rem", color: "rgba(255,255,255,0.4)" }}>{inv.user?.email || "N/A"}</div>
                      </div>
                    </td>
                    <td>
                      <div style={{ fontWeight: "600" }}>
                        ₹{parseFloat(inv.amount).toFixed(2)}
                      </div>
                    </td>
                    <td>
                      <span className={`badge badge-${inv.status.toLowerCase()}`}>{inv.status}</span>
                    </td>
                    <td>
                      <span style={{ fontSize: "0.85rem", color: inv.status === "PENDING" ? "#ef4444" : "rgba(255,255,255,0.6)" }}>
                        {new Date(inv.dueDate).toDateString()}
                      </span>
                    </td>
                    <td>
                      {inv.status === "PENDING" && (
                        <button 
                          onClick={() => handleMarkPaid(inv.id)}
                          className="btn btn-secondary" 
                          style={{ fontSize: "0.75rem", padding: "6px 12px", background: "rgba(16,185,129,0.1)", color: "#10b981", border: "1px solid rgba(16,185,129,0.2)" }}
                        >
                          <CreditCard size={12} />
                          <span>Approve Payment</span>
                        </button>
                      )}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>

      {/* Generator modal overlay */}
      {showGenModal && (
        <div style={{ position: "fixed", inset: 0, background: "rgba(0,0,0,0.7)", display: "flex", alignItems: "center", justifyContent: "center", zIndex: 200, padding: "20px" }}>
          <form onSubmit={handleGenerateInvoice} className="glass-card" style={{ width: "100%", maxWidth: "420px", padding: "30px" }}>
            <h3 style={{ fontSize: "1.15rem", fontWeight: "600", marginBottom: "8px" }}>Dispatch Custom Invoice</h3>
            <p style={{ fontSize: "0.85rem", color: "rgba(255,255,255,0.5)", marginBottom: "25px" }}>Trigger billing calculations manually for user accounts</p>

            <div style={{ display: "flex", flexDirection: "column", gap: "20px" }}>
              <div>
                <label style={{ display: "block", fontSize: "0.8rem", color: "rgba(255,255,255,0.6)", marginBottom: "8px", fontWeight: "500" }}>Account Identifier User ID</label>
                <input 
                  type="text" 
                  className="input-field" 
                  placeholder="e.g. clwkd89ea0000..." 
                  value={genUserId}
                  onChange={(e) => setGenUserId(e.target.value)}
                  required
                />
              </div>

              <div style={{ display: "flex", justifyContent: "flex-end", gap: "10px", marginTop: "15px" }}>
                <button type="button" onClick={() => setShowGenModal(false)} className="btn btn-secondary">Cancel</button>
                <button type="submit" className="btn btn-primary" disabled={actionLoading}>
                  {actionLoading ? "Processing invoice..." : "Calculate & Generate"}
                </button>
              </div>
            </div>
          </form>
        </div>
      )}
    </div>
  );
}
