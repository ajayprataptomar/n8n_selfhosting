"use client";

import React, { useState, useEffect } from "react";
import { 
  Server, 
  Cpu, 
  Layers, 
  Activity, 
  Plus, 
  Play, 
  Square, 
  RotateCw, 
  Trash2, 
  TrendingUp,
  Brain,
  ShieldCheck,
  Settings
} from "lucide-react";
import HariksonApiClient from "../../lib/hariksonApi";

const HARIKSON_MODELS_BRANDING: Record<string, { displayName: string }> = {
  'harikson/qwen3-coder:1.5b': { displayName: 'Harikson Starter' },
  'harikson/qwen3-coder:4b': { displayName: 'Harikson Pro' },
  'harikson/qwen3-coder:8b': { displayName: 'Harikson Business' },
  'harikson/qwen3-coder:14b': { displayName: 'Harikson Enterprise' },
  'qwen3-coder-4b': { displayName: 'Harikson Pro' },
  'qwen3-coder-8b': { displayName: 'Harikson Business' },
  'qwen3-coder-14b': { displayName: 'Harikson Enterprise' }
};

function getBrandedModelName(internalName: string): string {
  return HARIKSON_MODELS_BRANDING[internalName]?.displayName || 'Harikson AI';
}

interface Tenant {
  id: string;
  name: string;
  domain: string;
  status: "PENDING" | "CREATING" | "RUNNING" | "STOPPED" | "ERROR";
  plan: "STARTER" | "PRO" | "BUSINESS" | "ENTERPRISE";
  agentType: "CHAT" | "CODING" | "HYBRID";
  model: string;
  cpuLimit: number;
  memoryLimit: string;
  cpuUsage?: number;
  memoryUsage?: number;
  diskUsage?: string;
  createdAt: string;
}

interface Stats {
  status: string;
  tenants: {
    total: number;
    active: number;
    stopped: number;
  };
  allocations: {
    cpuCores: number;
    memoryMB: number;
  };
}

interface VPSNode {
  id: string;
  name: string;
  ip: string;
  region: string;
  status: string;
  cpu: { total: number; used: number };
  memory: { totalGB: number; usedGB: number };
  storage: { totalGB: number; usedGB: number };
  engine: string;
}

interface FineTuneJob {
  id: string;
  tenant: { name: string };
  status: string;
  baseModel: string;
  adapterName: string;
  metrics: { epoch?: number; loss?: number; finalAccuracy?: number };
  createdAt: string;
}

interface PendingTenant {
  id: string;
  name: string;
  email: string | null;
  phone: string | null;
  requestedPlan: "STARTER" | "PRO" | "BUSINESS" | "ENTERPRISE";
  requestedAt: string;
}

interface BillingMode {
  mode: "manual" | "auto";
  gateway?: "razorpay" | "cashfree";
}

export default function HariksonAdmin() {
  const [tenants, setTenants] = useState<Tenant[]>([]);
  const [stats, setStats] = useState<(Stats & { pendingApprovals?: number; totalRevenue?: string }) | null>(null);
  const [vpsNodes, setVpsNodes] = useState<VPSNode[]>([]);
  const [trainingJobs, setTrainingJobs] = useState<FineTuneJob[]>([]);
  const [pendingTenants, setPendingTenants] = useState<PendingTenant[]>([]);
  const [billingMode, setBillingMode] = useState<BillingMode>({ mode: "manual" });
  const [loading, setLoading] = useState(true);
  const [actionLoading, setActionLoading] = useState<string | null>(null);

  // Form State
  const [name, setName] = useState("");
  const [plan, setPlan] = useState<"STARTER" | "PRO" | "BUSINESS" | "ENTERPRISE">("STARTER");
  const [agentType, setAgentType] = useState<"CHAT" | "CODING" | "HYBRID">("CHAT");
  const [model, setModel] = useState("qwen3-coder-8b");
  const [email, setEmail] = useState("");
  const [phone, setPhone] = useState("");
  const [showAddForm, setShowAddForm] = useState(false);
  const [error, setError] = useState("");

  // Edit Form State
  const [editingTenant, setEditingTenant] = useState<Tenant | null>(null);
  const [editPlan, setEditPlan] = useState<"STARTER" | "PRO" | "BUSINESS" | "ENTERPRISE">("STARTER");
  const [editModel, setEditModel] = useState("qwen3-coder-8b");
  const [editAgentType, setEditAgentType] = useState<"CHAT" | "CODING" | "HYBRID">("CHAT");
  const [showEditForm, setShowEditForm] = useState(false);


  const loadData = async () => {
    try {
      const [tenantsData, statsData, vpsData, trainingData, pendingData, billingData] = await Promise.all([
        HariksonApiClient.get<Tenant[]>("/tenants"),
        HariksonApiClient.get<Stats & { pendingApprovals?: number; totalRevenue?: string }>("/stats"),
        HariksonApiClient.get<VPSNode[]>("/vps"),
        HariksonApiClient.get<FineTuneJob[]>("/training"),
        HariksonApiClient.get<PendingTenant[]>("/tenants/pending"),
        HariksonApiClient.get<BillingMode>("/tenants/billing/mode"),
      ]);

      setTenants(tenantsData);
      setStats(statsData);
      setVpsNodes(vpsData);
      setTrainingJobs(trainingData);
      setPendingTenants(pendingData);
      setBillingMode(billingData);
    } catch (err) {
      console.warn("Harikson API offline, showing high-fidelity control panel simulator.");
      // High-fidelity fallback mocks
      setTenants([
        {
          id: "t_1",
          name: "alphatech",
          domain: "alphatech.neuravolt.cloud",
          status: "RUNNING",
          plan: "BUSINESS",
          agentType: "HYBRID",
          model: "qwen3-coder-14b",
          cpuLimit: 2.0,
          memoryLimit: "2048m",
          cpuUsage: 14.8,
          memoryUsage: 382.4,
          diskUsage: "1.4 GB",
          createdAt: new Date().toISOString(),
        },
        {
          id: "t_2",
          name: "leadguru",
          domain: "leadguru.neuravolt.cloud",
          status: "STOPPED",
          plan: "STARTER",
          agentType: "CHAT",
          model: "qwen3-coder-4b",
          cpuLimit: 0.5,
          memoryLimit: "512m",
          cpuUsage: 0,
          memoryUsage: 0,
          diskUsage: "120 MB",
          createdAt: new Date().toISOString(),
        }
      ]);
      setStats({
        status: "healthy",
        tenants: { total: 2, active: 1, stopped: 1 },
        allocations: { cpuCores: 2.5, memoryMB: 2560 },
        pendingApprovals: 1,
        totalRevenue: "INR 4,998.00"
      });
      setVpsNodes([
        {
          id: "vps_1",
          name: "Primary VPS Node 01",
          ip: "45.194.2.244",
          region: "ap-south-1 (Mumbai)",
          status: "ONLINE",
          cpu: { total: 16, used: 2.5 },
          memory: { totalGB: 32, usedGB: 2.56 },
          storage: { totalGB: 500, usedGB: 1.52 },
          engine: "Docker 24.0.7",
        }
      ]);
      setTrainingJobs([
        {
          id: "ft_1",
          tenant: { name: "alphatech" },
          status: "COMPLETED",
          baseModel: "qwen3-coder-14b",
          adapterName: "nv-adapter-alphatech-6273",
          metrics: { epoch: 3, loss: 0.74, finalAccuracy: 0.95 },
          createdAt: new Date().toISOString(),
        }
      ]);
      setPendingTenants([
        {
          id: "t_pending_1",
          name: "betasolutions",
          email: "ceo@betasolutions.io",
          phone: "9999912345",
          requestedPlan: "PRO",
          requestedAt: new Date(Date.now() - 3600000 * 4).toISOString(),
        }
      ]);
      setBillingMode({ mode: "manual" });
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    loadData();
    const timer = setInterval(loadData, 5000);
    return () => clearInterval(timer);
  }, []);

  const handleCreate = async (e: React.FormEvent) => {
    e.preventDefault();
    setError("");
    setLoading(true);

    try {
      await HariksonApiClient.post("/tenants", { name, plan, agentType, model, email, phone });
      setName("");
      setEmail("");
      setPhone("");
      setShowAddForm(false);
      await loadData();
    } catch (err: any) {
      setError(err.message || "Failed to provision stack");
    } finally {
      setLoading(false);
    }
  };

  const handlePowerAction = async (id: string, action: "start" | "stop" | "restart" | "delete") => {
    setActionLoading(id);
    try {
      await HariksonApiClient.post(`/tenants/${id}/${action}`);
      await loadData();
    } catch (err: any) {
      alert(err.message || `Action ${action} failed`);
    } finally {
      setActionLoading(null);
    }
  };

  const handleApprove = async (id: string) => {
    setActionLoading(id);
    try {
      await HariksonApiClient.post(`/tenants/${id}/approve`);
      await loadData();
    } catch (err: any) {
      alert(err.message || "Approval failed");
    } finally {
      setActionLoading(null);
    }
  };

  const handleReject = async (id: string, reason: string = "Not specified") => {
    setActionLoading(id);
    try {
      await HariksonApiClient.post(`/tenants/${id}/reject`, { reason });
      await loadData();
    } catch (err: any) {
      alert(err.message || "Rejection failed");
    } finally {
      setActionLoading(null);
    }
  };

  const handleUpdate = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!editingTenant) return;
    setActionLoading(editingTenant.id);
    try {
      await HariksonApiClient.post(`/tenants/${editingTenant.id}/update`, {
        plan: editPlan,
        model: editModel,
        agentType: editAgentType,
      });
      setShowEditForm(false);
      setEditingTenant(null);
      await loadData();
    } catch (err: any) {
      alert(err.message || "Failed to update tenant");
    } finally {
      setActionLoading(null);
    }
  };

  const handleSwitchBillingMode = async (mode: "manual" | "auto", gateway?: "razorpay" | "cashfree") => {
    try {
      await HariksonApiClient.post("/tenants/billing/mode", { mode, gateway });
      setBillingMode({ mode, gateway });
      await loadData();
    } catch (err: any) {
      alert(err.message || "Failed to update billing mode");
    }
  };

  return (
    <div>
      {/* Header */}
      <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", marginBottom: "35px" }}>
        <div>
          <h1 style={{ fontSize: "1.75rem", fontWeight: "700" }}>Neuravolt-Harikson AI Hub</h1>
          <p style={{ color: "rgba(255,255,255,0.5)", fontSize: "0.9rem" }}>White-Label Multi-Tenant Orchestrator & Node Pools</p>
        </div>
        <div style={{ display: "flex", gap: "10px" }}>
          <button onClick={() => setShowAddForm(!showAddForm)} className="btn btn-primary" style={{ display: "flex", alignItems: "center", gap: "6px" }}>
            <Plus size={16} />
            <span>Deploy AI Agent</span>
          </button>
        </div>
      </div>

      {/* Stats Dashboard */}
      {stats && (
        <div style={{ display: "grid", gridTemplateColumns: "repeat(auto-fit, minmax(200px, 1fr))", gap: "20px", marginBottom: "35px" }}>
          <div className="glass-card" style={{ padding: "20px 24px", display: "flex", alignItems: "center", gap: "20px" }}>
            <div style={{ padding: "12px", borderRadius: "12px", background: "rgba(167, 139, 250, 0.1)", color: "#a78bfa" }}>
              <Layers size={24} />
            </div>
            <div>
              <p style={{ fontSize: "0.8rem", color: "rgba(255,255,255,0.5)" }}>Total Clients Deployed</p>
              <h3 style={{ fontSize: "1.5rem", fontWeight: "700", marginTop: "4px" }}>
                {stats.tenants.total} <span style={{ fontSize: "0.85rem", color: "#10b981", fontWeight: "500" }}>({stats.tenants.active} Active)</span>
              </h3>
            </div>
          </div>

          <div className="glass-card" style={{ padding: "20px 24px", display: "flex", alignItems: "center", gap: "20px" }}>
            <div style={{ padding: "12px", borderRadius: "12px", background: "rgba(245, 158, 11, 0.1)", color: "#f59e0b" }}>
              <RotateCw size={24} />
            </div>
            <div>
              <p style={{ fontSize: "0.8rem", color: "rgba(255,255,255,0.5)" }}>Pending Approvals</p>
              <h3 style={{ fontSize: "1.5rem", fontWeight: "700", marginTop: "4px" }}>
                {stats.pendingApprovals ?? 0}
              </h3>
            </div>
          </div>

          <div className="glass-card" style={{ padding: "20px 24px", display: "flex", alignItems: "center", gap: "20px" }}>
            <div style={{ padding: "12px", borderRadius: "12px", background: "rgba(16, 185, 129, 0.1)", color: "#10b981" }}>
              <TrendingUp size={24} />
            </div>
            <div>
              <p style={{ fontSize: "0.8rem", color: "rgba(255,255,255,0.5)" }}>Total Revenue</p>
              <h3 style={{ fontSize: "1.5rem", fontWeight: "700", marginTop: "4px" }}>
                {stats.totalRevenue ?? "INR 0.00"}
              </h3>
            </div>
          </div>

          <div className="glass-card" style={{ padding: "20px 24px", display: "flex", alignItems: "center", gap: "20px" }}>
            <div style={{ padding: "12px", borderRadius: "12px", background: "rgba(59, 130, 246, 0.1)", color: "#3b82f6" }}>
              <Cpu size={24} />
            </div>
            <div>
              <p style={{ fontSize: "0.8rem", color: "rgba(255,255,255,0.5)" }}>Compute Cores</p>
              <h3 style={{ fontSize: "1.5rem", fontWeight: "700", marginTop: "4px" }}>{stats.allocations.cpuCores.toFixed(1)} Cores</h3>
            </div>
          </div>
        </div>
      )}

      {/* Deployment Form overlay modal */}
      {showAddForm && (
        <div style={{ background: "rgba(0,0,0,0.6)", backdropFilter: "blur(4px)", position: "fixed", inset: 0, zIndex: 1000, display: "flex", alignItems: "center", justifyContent: "center", padding: "20px" }}>
          <div className="glass-card" style={{ width: "100%", maxWidth: "500px", padding: "30px" }}>
            <h3 style={{ fontSize: "1.2rem", fontWeight: "700", marginBottom: "20px" }}>Deploy Isolated Tenant Agent</h3>
            {error && <div style={{ color: "#ef4444", fontSize: "0.85rem", marginBottom: "15px" }}>{error}</div>}
            
            <form onSubmit={handleCreate} style={{ display: "flex", flexDirection: "column", gap: "14px" }}>
              <div>
                <label style={{ display: "block", fontSize: "0.8rem", color: "rgba(255,255,255,0.6)", marginBottom: "6px" }}>Subdomain Prefix (lowercase, alphanumeric)</label>
                <input 
                  type="text" 
                  className="input-field" 
                  placeholder="e.g. sharma-agency" 
                  value={name} 
                  onChange={(e) => setName(e.target.value)}
                  required 
                />
              </div>

              <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: "15px" }}>
                <div>
                  <label style={{ display: "block", fontSize: "0.8rem", color: "rgba(255,255,255,0.6)", marginBottom: "6px" }}>Customer Email</label>
                  <input 
                    type="email" 
                    className="input-field" 
                    placeholder="customer@domain.com" 
                    value={email} 
                    onChange={(e) => setEmail(e.target.value)}
                    required 
                  />
                </div>
                <div>
                  <label style={{ display: "block", fontSize: "0.8rem", color: "rgba(255,255,255,0.6)", marginBottom: "6px" }}>Customer Phone</label>
                  <input 
                    type="text" 
                    className="input-field" 
                    placeholder="9999999999" 
                    value={phone} 
                    onChange={(e) => setPhone(e.target.value)}
                  />
                </div>
              </div>

              <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: "15px" }}>
                <div>
                  <label style={{ display: "block", fontSize: "0.8rem", color: "rgba(255,255,255,0.6)", marginBottom: "6px" }}>Select Tier Plan</label>
                  <select className="input-field" value={plan} onChange={(e) => setPlan(e.target.value as any)}>
                    <option value="STARTER">STARTER ($29/mo)</option>
                    <option value="PRO">PRO ($59/mo)</option>
                    <option value="BUSINESS">BUSINESS ($129/mo)</option>
                    <option value="ENTERPRISE">ENTERPRISE (Custom)</option>
                  </select>
                </div>
                <div>
                  <label style={{ display: "block", fontSize: "0.8rem", color: "rgba(255,255,255,0.6)", marginBottom: "6px" }}>Agent Mode</label>
                  <select className="input-field" value={agentType} onChange={(e) => setAgentType(e.target.value as any)}>
                    <option value="CHAT">CHAT AGENT</option>
                    <option value="CODING">CODING AGENT</option>
                    <option value="HYBRID">HYBRID MODE</option>
                  </select>
                </div>
              </div>

              <div>
                <label style={{ display: "block", fontSize: "0.8rem", color: "rgba(255,255,255,0.6)", marginBottom: "6px" }}>Core Model Variant</label>
                <select className="input-field" value={model} onChange={(e) => setModel(e.target.value)}>
                  <option value="qwen3-coder-4b">Harikson Pro (4B quantized)</option>
                  <option value="qwen3-coder-8b">Harikson Business (8B recommended)</option>
                  <option value="qwen3-coder-14b">Harikson Enterprise (14B)</option>
                  <option value="qwen3-coder-32b">Harikson Infinite (32B Enterprise only)</option>
                </select>
              </div>

              <div style={{ display: "flex", gap: "12px", justifyContent: "flex-end", marginTop: "10px" }}>
                <button type="button" onClick={() => setShowAddForm(false)} className="btn btn-secondary">Cancel</button>
                <button type="submit" className="btn btn-primary" disabled={loading}>
                  {loading ? "Deploying..." : "Provision Stack"}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* Edit Tenant Form overlay modal */}
      {showEditForm && editingTenant && (
        <div style={{ background: "rgba(0,0,0,0.6)", backdropFilter: "blur(4px)", position: "fixed", inset: 0, zIndex: 1000, display: "flex", alignItems: "center", justifyContent: "center", padding: "20px" }}>
          <div className="glass-card" style={{ width: "100%", maxWidth: "500px", padding: "30px" }}>
            <h3 style={{ fontSize: "1.2rem", fontWeight: "700", marginBottom: "20px" }}>Modify Tenant Stack Settings</h3>
            <p style={{ color: "rgba(255,255,255,0.6)", fontSize: "0.85rem", marginBottom: "20px" }}>
              Updating plan resources or model variants will hot-reload the containers for <strong>{editingTenant.name}</strong> automatically.
            </p>
            
            <form onSubmit={handleUpdate} style={{ display: "flex", flexDirection: "column", gap: "18px" }}>
              <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: "15px" }}>
                <div>
                  <label style={{ display: "block", fontSize: "0.8rem", color: "rgba(255,255,255,0.6)", marginBottom: "6px" }}>Tier Plan</label>
                  <select className="input-field" value={editPlan} onChange={(e) => setEditPlan(e.target.value as any)}>
                    <option value="STARTER">STARTER ($29/mo)</option>
                    <option value="PRO">PRO ($59/mo)</option>
                    <option value="BUSINESS">BUSINESS ($129/mo)</option>
                    <option value="ENTERPRISE">ENTERPRISE (Custom)</option>
                  </select>
                </div>
                <div>
                  <label style={{ display: "block", fontSize: "0.8rem", color: "rgba(255,255,255,0.6)", marginBottom: "6px" }}>Agent Mode</label>
                  <select className="input-field" value={editAgentType} onChange={(e) => setEditAgentType(e.target.value as any)}>
                    <option value="CHAT">CHAT AGENT</option>
                    <option value="CODING">CODING AGENT</option>
                    <option value="HYBRID">HYBRID MODE</option>
                  </select>
                </div>
              </div>

              <div>
                <label style={{ display: "block", fontSize: "0.8rem", color: "rgba(255,255,255,0.6)", marginBottom: "6px" }}>Core Model Variant</label>
                <select className="input-field" value={editModel} onChange={(e) => setEditModel(e.target.value)}>
                  <option value="qwen3-coder-4b">Harikson Pro (4B quantized)</option>
                  <option value="qwen3-coder-8b">Harikson Business (8B recommended)</option>
                  <option value="qwen3-coder-14b">Harikson Enterprise (14B)</option>
                  <option value="qwen3-coder-32b">Harikson Infinite (32B Enterprise only)</option>
                </select>
              </div>

              <div style={{ display: "flex", gap: "12px", justifyContent: "flex-end", marginTop: "10px" }}>
                <button type="button" onClick={() => { setShowEditForm(false); setEditingTenant(null); }} className="btn btn-secondary">Cancel</button>
                <button type="submit" className="btn btn-primary" disabled={!!actionLoading}>
                  {actionLoading === editingTenant.id ? "Updating Stack..." : "Save Settings"}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* Billing Settings and Pending Approvals Section */}
      <div style={{ display: "grid", gridTemplateColumns: "1.5fr 1fr", gap: "35px", marginBottom: "35px" }}>
        
        {/* Pending Approvals Table */}
        <div className="glass-card" style={{ padding: "30px", overflowX: "auto" }}>
          <div style={{ display: "flex", alignItems: "center", gap: "10px", marginBottom: "20px" }}>
            <RotateCw size={20} style={{ color: "#f59e0b" }} />
            <h3 style={{ fontSize: "1.1rem", fontWeight: "600" }}>
              Pending Manual Approvals
              {pendingTenants.length > 0 && (
                <span className="badge badge-pending" style={{ marginLeft: "10px", fontSize: "0.75rem", background: "rgba(245, 158, 11, 0.15)", color: "#f59e0b", padding: "2px 8px", borderRadius: "10px" }}>
                  {pendingTenants.length} waiting
                </span>
              )}
            </h3>
          </div>

          <table style={{ width: "100%", borderCollapse: "collapse", textAlign: "left" }}>
            <thead>
              <tr style={{ borderBottom: "1px solid rgba(255,255,255,0.06)", color: "rgba(255,255,255,0.4)", fontSize: "0.75rem", textTransform: "uppercase" }}>
                <th style={{ padding: "12px 10px" }}>Tenant Name</th>
                <th style={{ padding: "12px 10px" }}>Contact</th>
                <th style={{ padding: "12px 10px" }}>Requested Plan</th>
                <th style={{ padding: "12px 10px" }}>Date</th>
                <th style={{ padding: "12px 10px", textAlign: "right" }}>Actions</th>
              </tr>
            </thead>
            <tbody>
              {pendingTenants.length === 0 ? (
                <tr>
                  <td colSpan={5} style={{ textAlign: "center", padding: "40px 0", color: "rgba(255,255,255,0.4)", fontSize: "0.9rem" }}>
                    No pending approval requests.
                  </td>
                </tr>
              ) : (
                pendingTenants.map((t) => (
                  <tr key={t.id} style={{ borderBottom: "1px solid rgba(255,255,255,0.04)", fontSize: "0.85rem" }}>
                    <td style={{ padding: "14px 10px", fontWeight: "600" }}>
                      <div>{t.name}</div>
                      <div style={{ fontSize: "0.75rem", color: "rgba(255,255,255,0.4)" }}>ID: {t.id}</div>
                    </td>
                    <td style={{ padding: "14px 10px" }}>
                      <div>{t.email || "No email"}</div>
                      <div style={{ fontSize: "0.75rem", color: "rgba(255,255,255,0.4)" }}>{t.phone || "No phone"}</div>
                    </td>
                    <td style={{ padding: "14px 10px" }}>
                      <span className="badge badge-active" style={{ fontSize: "0.7rem" }}>{t.requestedPlan}</span>
                    </td>
                    <td style={{ padding: "14px 10px", color: "rgba(255,255,255,0.6)" }}>
                      {new Date(t.requestedAt).toLocaleDateString()}
                    </td>
                    <td style={{ padding: "14px 10px", textAlign: "right" }}>
                      <div style={{ display: "flex", gap: "8px", justifyContent: "flex-end" }}>
                        <button 
                          onClick={() => handleApprove(t.id)} 
                          className="btn btn-primary" 
                          style={{ padding: "6px 12px", borderRadius: "6px", fontSize: "0.75rem" }} 
                          disabled={!!actionLoading}
                        >
                          Approve
                        </button>
                        <button 
                          onClick={() => handleReject(t.id)} 
                          className="btn btn-secondary" 
                          style={{ padding: "6px 12px", borderRadius: "6px", fontSize: "0.75rem", color: "#ef4444" }} 
                          disabled={!!actionLoading}
                        >
                          Reject
                        </button>
                      </div>
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>

        {/* Billing Gateway Controls */}
        <div className="glass-card" style={{ padding: "30px" }}>
          <div style={{ display: "flex", alignItems: "center", gap: "10px", marginBottom: "20px" }}>
            <TrendingUp size={20} style={{ color: "#3b82f6" }} />
            <h3 style={{ fontSize: "1.1rem", fontWeight: "600" }}>System Billing Selector</h3>
          </div>
          <p style={{ fontSize: "0.85rem", color: "rgba(255,255,255,0.6)", marginBottom: "20px" }}>
            Choose whether to require manual administrator verification or integrate automated credit/payment gateways for instant activations.
          </p>

          <div style={{ display: "flex", flexDirection: "column", gap: "16px" }}>
            <label style={{ display: "flex", alignItems: "center", gap: "12px", padding: "12px 16px", borderRadius: "10px", border: billingMode.mode === "manual" ? "1px solid #3b82f6" : "1px solid rgba(255,255,255,0.06)", cursor: "pointer", background: billingMode.mode === "manual" ? "rgba(59,130,246,0.08)" : "transparent" }}>
              <input 
                type="radio" 
                name="billing_mode" 
                checked={billingMode.mode === "manual"} 
                onChange={() => handleSwitchBillingMode("manual")} 
              />
              <div>
                <strong style={{ display: "block", fontSize: "0.9rem" }}>Manual Ops Approvals</strong>
                <span style={{ fontSize: "0.75rem", color: "rgba(255,255,255,0.5)" }}>Admin manually verifies client credentials and clicks approve to launch containers</span>
              </div>
            </label>

            <label style={{ display: "flex", alignItems: "center", gap: "12px", padding: "12px 16px", borderRadius: "10px", border: (billingMode.mode === "auto" && billingMode.gateway === "razorpay") ? "1px solid #3b82f6" : "1px solid rgba(255,255,255,0.06)", cursor: "pointer", background: (billingMode.mode === "auto" && billingMode.gateway === "razorpay") ? "rgba(59,130,246,0.08)" : "transparent" }}>
              <input 
                type="radio" 
                name="billing_mode" 
                checked={billingMode.mode === "auto" && billingMode.gateway === "razorpay"} 
                onChange={() => handleSwitchBillingMode("auto", "razorpay")} 
              />
              <div>
                <strong style={{ display: "block", fontSize: "0.9rem" }}>Razorpay Automation</strong>
                <span style={{ fontSize: "0.75rem", color: "rgba(255,255,255,0.5)" }}>Enforces Razorpay checkout orders and updates deployment statuses instantly on payment completion</span>
              </div>
            </label>

            <label style={{ display: "flex", alignItems: "center", gap: "12px", padding: "12px 16px", borderRadius: "10px", border: (billingMode.mode === "auto" && billingMode.gateway === "cashfree") ? "1px solid #3b82f6" : "1px solid rgba(255,255,255,0.06)", cursor: "pointer", background: (billingMode.mode === "auto" && billingMode.gateway === "cashfree") ? "rgba(59,130,246,0.08)" : "transparent" }}>
              <input 
                type="radio" 
                name="billing_mode" 
                checked={billingMode.mode === "auto" && billingMode.gateway === "cashfree"} 
                onChange={() => handleSwitchBillingMode("auto", "cashfree")} 
              />
              <div>
                <strong style={{ display: "block", fontSize: "0.9rem" }}>Cashfree Payment Link</strong>
                <span style={{ fontSize: "0.75rem", color: "rgba(255,255,255,0.5)" }}>Generates Cashfree API requests and releases nodes automatically upon callback notifications</span>
              </div>
            </label>
          </div>
        </div>
      </div>

      {/* Main content split panel */}
      <div style={{ display: "grid", gridTemplateColumns: "1fr", gap: "35px" }}>
        
        {/* Tenants compute environments table */}
        <div className="glass-card" style={{ padding: "30px", overflowX: "auto" }}>
          <h3 style={{ fontSize: "1.1rem", fontWeight: "600", marginBottom: "20px" }}>Active Subsystem Deployments</h3>
          
          <table style={{ width: "100%", borderCollapse: "collapse", textAlign: "left" }}>
            <thead>
              <tr style={{ borderBottom: "1px solid rgba(255,255,255,0.06)", color: "rgba(255,255,255,0.4)", fontSize: "0.75rem", textTransform: "uppercase" }}>
                <th style={{ padding: "12px 10px" }}>Tenant Name</th>
                <th style={{ padding: "12px 10px" }}>Active Domain</th>
                <th style={{ padding: "12px 10px" }}>Subscription</th>
                <th style={{ padding: "12px 10px" }}>Type</th>
                <th style={{ padding: "12px 10px" }}>LLM Model</th>
                <th style={{ padding: "12px 10px" }}>Status</th>
                <th style={{ padding: "12px 10px" }}>vCPU</th>
                <th style={{ padding: "12px 10px" }}>RAM Usage</th>
                <th style={{ padding: "12px 10px" }}>Actions</th>
              </tr>
            </thead>
            <tbody>
              {tenants.length === 0 ? (
                <tr>
                  <td colSpan={9} style={{ textAlign: "center", padding: "40px 0", color: "rgba(255,255,255,0.4)", fontSize: "0.9rem" }}>
                    No client agent stacks deployed. Click "Deploy AI Agent" to start.
                  </td>
                </tr>
              ) : (
                tenants.map((t) => (
                  <tr key={t.id} style={{ borderBottom: "1px solid rgba(255,255,255,0.04)", fontSize: "0.85rem" }}>
                    <td style={{ padding: "14px 10px", fontWeight: "600" }}>{t.name}</td>
                    <td style={{ padding: "14px 10px", color: "#a78bfa" }}>
                      <a href={`https://${t.domain}`} target="_blank" style={{ color: "inherit", textDecoration: "none" }}>{t.domain}</a>
                    </td>
                    <td style={{ padding: "14px 10px" }}>
                      <span className="badge badge-active" style={{ fontSize: "0.7rem" }}>{t.plan}</span>
                    </td>
                    <td style={{ padding: "14px 10px", color: "rgba(255,255,255,0.6)" }}>{t.agentType}</td>
                    <td style={{ padding: "14px 10px" }}>{getBrandedModelName(t.model)}</td>
                    <td style={{ padding: "14px 10px" }}>
                      <span className={`badge badge-${t.status.toLowerCase()}`} style={{ fontSize: "0.7rem" }}>{t.status}</span>
                    </td>
                    <td style={{ padding: "14px 10px" }}>
                      {t.status === "RUNNING" ? `${t.cpuUsage ?? 0}% / ${t.cpuLimit}` : "-"}
                    </td>
                    <td style={{ padding: "14px 10px" }}>
                      {t.status === "RUNNING" ? `${t.memoryUsage ?? 0}MB / ${t.memoryLimit}` : "-"}
                    </td>
                    <td style={{ padding: "14px 10px" }}>
                      <div style={{ display: "flex", gap: "8px" }}>
                        {t.status !== "RUNNING" ? (
                          <button onClick={() => handlePowerAction(t.id, "start")} className="btn btn-secondary" style={{ padding: "6px", borderRadius: "6px" }} disabled={!!actionLoading}>
                            <Play size={12} style={{ color: "#10b981" }} />
                          </button>
                        ) : (
                          <button onClick={() => handlePowerAction(t.id, "stop")} className="btn btn-secondary" style={{ padding: "6px", borderRadius: "6px" }} disabled={!!actionLoading}>
                            <Square size={12} style={{ color: "#ef4444" }} />
                          </button>
                        )}
                        <button onClick={() => handlePowerAction(t.id, "restart")} className="btn btn-secondary" style={{ padding: "6px", borderRadius: "6px" }} disabled={!!actionLoading}>
                          <RotateCw size={12} style={{ color: "#3b82f6" }} />
                        </button>
                        <button 
                          onClick={() => {
                            setEditingTenant(t);
                            setEditPlan(t.plan);
                            setEditModel(t.model);
                            setEditAgentType(t.agentType);
                            setShowEditForm(true);
                          }} 
                          className="btn btn-secondary" 
                          style={{ padding: "6px", borderRadius: "6px" }} 
                          disabled={!!actionLoading}
                          title="Edit Plan/Model"
                        >
                          <Settings size={12} style={{ color: "#f59e0b" }} />
                        </button>
                        <button onClick={() => handlePowerAction(t.id, "delete")} className="btn btn-danger" style={{ padding: "6px", borderRadius: "6px" }} disabled={!!actionLoading}>
                          <Trash2 size={12} />
                        </button>
                      </div>
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>

        {/* Lower split column: Training Queue & Host Nodes */}
        <div style={{ display: "grid", gridTemplateColumns: "1.2fr 1fr", gap: "30px" }}>
          
          {/* Fine Tuning queues list */}
          <div className="glass-card" style={{ padding: "24px 30px" }}>
            <div style={{ display: "flex", alignItems: "center", gap: "10px", marginBottom: "20px" }}>
              <Brain size={20} style={{ color: "#a78bfa" }} />
              <h3 style={{ fontSize: "1.1rem", fontWeight: "600" }}>Global Model Fine-Tuning Monitor</h3>
            </div>

            <div style={{ display: "flex", flexDirection: "column", gap: "14px" }}>
              {trainingJobs.length === 0 ? (
                <div style={{ color: "rgba(255,255,255,0.4)", fontSize: "0.85rem", textAlign: "center", padding: "30px" }}>
                  No active model fine-tuning adapters in progress.
                </div>
              ) : (
                trainingJobs.map((j) => (
                  <div key={j.id} className="glass-panel" style={{ padding: "14px 18px", display: "flex", flexDirection: "column", gap: "10px" }}>
                    <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center" }}>
                      <span style={{ fontSize: "0.85rem", fontWeight: "600" }}>Tenant: {j.tenant.name}</span>
                      <span className={`badge badge-${j.status === "COMPLETED" ? "running" : j.status === "TRAINING" ? "pending" : "error"}`} style={{ fontSize: "0.7rem" }}>
                        {j.status}
                      </span>
                    </div>

                    <div style={{ display: "grid", gridTemplateColumns: "1fr 1.2fr", gap: "15px", fontSize: "0.75rem", color: "rgba(255,255,255,0.6)" }}>
                      <div>Base Model: <strong style={{ color: "white" }}>{getBrandedModelName(j.baseModel)}</strong></div>
                      <div>Adapter: <strong style={{ color: "white" }}>{j.adapterName}</strong></div>
                    </div>

                    {j.status === "TRAINING" && j.metrics.loss && (
                      <div style={{ fontSize: "0.75rem", color: "rgba(255,255,255,0.5)" }}>
                        Training Adapter progress: Epoch {j.metrics.epoch} | Loss: {j.metrics.loss}
                      </div>
                    )}
                    {j.status === "COMPLETED" && j.metrics.finalAccuracy && (
                      <div style={{ fontSize: "0.75rem", color: "#10b981" }}>
                        Adapters successfully compiled. Accuracy: {(j.metrics.finalAccuracy * 100).toFixed(0)}%
                      </div>
                    )}
                  </div>
                ))
              )}
            </div>
          </div>

          {/* VPS Hosting Nodes monitor */}
          <div className="glass-card" style={{ padding: "24px 30px" }}>
            <div style={{ display: "flex", alignItems: "center", gap: "10px", marginBottom: "20px" }}>
              <Server size={20} style={{ color: "#3b82f6" }} />
              <h3 style={{ fontSize: "1.1rem", fontWeight: "600" }}>Active VPS Cluster Nodes</h3>
            </div>

            <div style={{ display: "flex", flexDirection: "column", gap: "16px" }}>
              {vpsNodes.map((node) => (
                <div key={node.id} className="glass-panel" style={{ padding: "16px" }}>
                  <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", marginBottom: "12px" }}>
                    <div>
                      <h4 style={{ fontSize: "0.9rem", fontWeight: "600" }}>{node.name}</h4>
                      <p style={{ fontSize: "0.75rem", color: "rgba(255,255,255,0.4)" }}>IP: {node.ip} | Region: {node.region}</p>
                    </div>
                    <span className="badge badge-active" style={{ fontSize: "0.7rem", padding: "4px 8px" }}>
                      <ShieldCheck size={10} />
                      <span>{node.status}</span>
                    </span>
                  </div>

                  <div style={{ display: "flex", flexDirection: "column", gap: "10px", fontSize: "0.8rem" }}>
                    {/* CPU slider */}
                    <div>
                      <div style={{ display: "flex", justifyContent: "space-between", marginBottom: "4px", color: "rgba(255,255,255,0.6)" }}>
                        <span>CPU Cores</span>
                        <span>{node.cpu.used} / {node.cpu.total} Cores</span>
                      </div>
                      <div style={{ height: "4px", background: "rgba(255,255,255,0.05)", borderRadius: "2px", overflow: "hidden" }}>
                        <div style={{ width: `${(node.cpu.used / node.cpu.total) * 100}%`, height: "100%", background: "#a78bfa" }} />
                      </div>
                    </div>

                    {/* RAM slider */}
                    <div>
                      <div style={{ display: "flex", justifyContent: "space-between", marginBottom: "4px", color: "rgba(255,255,255,0.6)" }}>
                        <span>RAM Memory</span>
                        <span>{node.memory.usedGB.toFixed(1)}GB / {node.memory.totalGB}GB</span>
                      </div>
                      <div style={{ height: "4px", background: "rgba(255,255,255,0.05)", borderRadius: "2px", overflow: "hidden" }}>
                        <div style={{ width: `${(node.memory.usedGB / node.memory.totalGB) * 100}%`, height: "100%", background: "#3b82f6" }} />
                      </div>
                    </div>
                  </div>
                </div>
              ))}
            </div>
          </div>

        </div>

      </div>

    </div>
  );
}
