"use client";

import React, { useState, useEffect } from "react";
import { useRouter } from "next/navigation";
import "./globals.css";
import ApiClient from "../lib/api";
import { 
  Zap, 
  LayoutDashboard, 
  IndianRupee, 
  Settings, 
  LogOut, 
  Lock, 
  Mail, 
  User, 
  Building,
  ShieldAlert,
  Info
} from "lucide-react";

interface ModelOption {
  id: string;
  displayName: string;
  baseModel: string;
  size: string;
  category: "coding" | "chat";
  disabled?: boolean;
}

const MODELS_BY_PLAN: Record<string, ModelOption[]> = {
  STARTER: [
    { id: "harikson-coder-7b", displayName: "Harikson Coder 7B", baseModel: "Qwen2.5-Coder 7B", size: "5–6 GB", category: "coding" },
    { id: "harikson-coder-v2-lite", displayName: "Harikson Coder V2 Lite", baseModel: "DeepSeek-Coder V2 Lite", size: "6–8 GB", category: "coding" },
    { id: "harikson-codegemma-7b", displayName: "Harikson CodeGemma 7B", baseModel: "CodeGemma 7B", size: "5–6 GB", category: "coding" },
    { id: "harikson-chat-8b", displayName: "Harikson Chat 8B", baseModel: "Qwen3 8B", size: "5–6 GB", category: "chat" },
    { id: "harikson-llama-3.1-8b", displayName: "Harikson Llama 3.1 8B", baseModel: "Llama 3.1 8B", size: "5–6 GB", category: "chat" },
    { id: "harikson-gemma-3-4b", displayName: "Harikson Gemma 3 4B", baseModel: "Gemma 3 4B", size: "3–4 GB", category: "chat" },
    { id: "harikson-mistral-7b", displayName: "Harikson Mistral 7B", baseModel: "Mistral 7B Instruct", size: "5–6 GB", category: "chat" }
  ],
  PRO: [
    { id: "harikson-coder-14b", displayName: "Harikson Coder 14B", baseModel: "Qwen2.5-Coder 14B", size: "10–12 GB", category: "coding" },
    { id: "harikson-coder-16b", displayName: "Harikson Coder 16B", baseModel: "DeepSeek-Coder 16B", size: "10–12 GB", category: "coding" },
    { id: "harikson-chat-14b", displayName: "Harikson Chat 14B", baseModel: "Qwen3 14B", size: "10–12 GB", category: "chat" },
    { id: "harikson-gemma-3-12b", displayName: "Harikson Gemma 3 12B", baseModel: "Gemma 3 12B", size: "9–11 GB", category: "chat" }
  ],
  BUSINESS: [
    { id: "harikson-coder-14b", displayName: "Harikson Coder 14B", baseModel: "Qwen2.5-Coder 14B", size: "10–12 GB", category: "coding" },
    { id: "harikson-coder-v2-lite", displayName: "Harikson Coder V2 Lite", baseModel: "DeepSeek-Coder V2 Lite", size: "10–14 GB", category: "coding" },
    { id: "harikson-chat-14b", displayName: "Harikson Chat 14B", baseModel: "Qwen3 14B", size: "10–12 GB", category: "chat" },
    { id: "harikson-chat-30b-a3b", displayName: "Harikson Chat 30B-A3B", baseModel: "Qwen3 30B-A3B", size: "10–14 GB", category: "chat" },
    { id: "harikson-llama-3.3-70b", displayName: "Harikson Llama 3.3 70B (Not Practical)", baseModel: "Llama 3.3 70B", size: "Not Practical", category: "chat", disabled: true }
  ],
  ENTERPRISE: [
    { id: "harikson-coder-32b", displayName: "Harikson Coder 32B", baseModel: "Qwen2.5-Coder 32B", size: "20–24 GB", category: "coding" },
    { id: "harikson-coder-v2", displayName: "Harikson Coder V2", baseModel: "DeepSeek-Coder V2", size: "20–24 GB", category: "coding" },
    { id: "harikson-chat-32b", displayName: "Harikson Chat 32B", baseModel: "Qwen3 32B", size: "20–24 GB", category: "chat" },
    { id: "harikson-chat-35b-a3b", displayName: "Harikson Chat 35B-A3B", baseModel: "Qwen3 35B-A3B", size: "6–8 GB", category: "chat" },
    { id: "harikson-chat-32b-instruct", displayName: "Harikson Chat 32B Instruct", baseModel: "Qwen2.5 32B Instruct", size: "20–24 GB", category: "chat" }
  ]
};

export default function RootLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  const router = useRouter();
  const [isAuthenticated, setIsAuthenticated] = useState<boolean>(false);
  const [isLoginView, setIsLoginView] = useState<boolean>(true);
  const [activeTab, setActiveTab] = useState<string>("dashboard");
  
  // Auth Form State
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [name, setName] = useState("");
  const [company, setCompany] = useState("");
  const [plan, setPlan] = useState("STARTER");
  const [aiPlan, setAiPlan] = useState("STARTER");
  const [selectedModel, setSelectedModel] = useState("harikson-chat-8b");

  const [buyN8n, setBuyN8n] = useState<boolean>(true);
  const [buyAi, setBuyAi] = useState<boolean>(false);
  const [n8nEnabled, setN8nEnabled] = useState<boolean>(true);
  const [aiEnabled, setAiEnabled] = useState<boolean>(false);
  
  const [error, setError] = useState("");
  const [info, setInfo] = useState("");
  const [loading, setLoading] = useState(false);

  useEffect(() => {
    if (typeof window !== "undefined") {
      const token = localStorage.getItem("nv_user_token");
      if (token) {
        setIsAuthenticated(true);
      }

      // Parse product query parameters
      const params = new URLSearchParams(window.location.search);
      const productParam = params.get("product");
      if (productParam === "n8n") {
        setBuyN8n(true);
        setBuyAi(false);
      } else if (productParam === "ai") {
        setBuyN8n(false);
        setBuyAi(true);
      } else if (productParam === "both") {
        setBuyN8n(true);
        setBuyAi(true);
      }
      
      const path = window.location.pathname;
      if (path.includes("/billing")) setActiveTab("billing");
      else if (path.includes("/settings")) setActiveTab("settings");
      else if (path.includes("/harikson")) setActiveTab("harikson");
      else setActiveTab("dashboard");
    }
  }, []);

  // Fetch user profile to read product entitlements
  useEffect(() => {
    const fetchProfile = async () => {
      const token = localStorage.getItem("nv_user_token");
      if (token) {
        try {
          const profile = await ApiClient.get<{ n8nEnabled: boolean; aiEnabled: boolean }>("/auth/me");
          setN8nEnabled(profile.n8nEnabled);
          setAiEnabled(profile.aiEnabled);
          localStorage.setItem("nv_user_n8n_enabled", String(profile.n8nEnabled));
          localStorage.setItem("nv_user_ai_enabled", String(profile.aiEnabled));
          
          // Redirect if user only has AI Agents enabled and is on root page
          if (profile.aiEnabled && !profile.n8nEnabled && window.location.pathname === "/") {
            router.push("/harikson");
            setActiveTab("harikson");
          }
        } catch (err) {
          console.error("Failed to load user profile:", err);
          setN8nEnabled(localStorage.getItem("nv_user_n8n_enabled") !== "false");
          setAiEnabled(localStorage.getItem("nv_user_ai_enabled") === "true");
        }
      }
    };
    if (isAuthenticated) {
      fetchProfile();
    }
  }, [isAuthenticated]);

  const handleLogin = async (e: React.FormEvent) => {
    e.preventDefault();
    setError("");
    setInfo("");
    setLoading(true);

    try {
      const data = await ApiClient.post<{ token: string; user: { name: string; email: string; plan: string; status: string } }>("/auth/login", { email, password });

      localStorage.setItem("nv_user_token", data.token);
      localStorage.setItem("nv_user_name", data.user.name);
      localStorage.setItem("nv_user_email", data.user.email);
      localStorage.setItem("nv_user_plan", data.user.plan);
      localStorage.setItem("nv_user_status", data.user.status);

      setIsAuthenticated(true);
      window.location.href = "/";
    } catch (err: any) {
      setError(err.message || "Invalid credentials");
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    const available = MODELS_BY_PLAN[aiPlan] || [];
    const firstEnabled = available.find(m => !m.disabled);
    if (firstEnabled) {
      setSelectedModel(firstEnabled.id);
    }
  }, [aiPlan]);

  const handleSignup = async (e: React.FormEvent) => {
    e.preventDefault();
    setError("");
    setInfo("");

    if (!buyN8n && !buyAi) {
      setError("Please select at least one product offering to signup.");
      return;
    }

    setLoading(true);

    try {
      const available = MODELS_BY_PLAN[aiPlan] || [];
      const modelConfig = available.find(m => m.id === selectedModel);
      const agentType = modelConfig?.category === "coding" ? "CODING" : "CHAT";

      await ApiClient.post("/auth/signup", { 
        email, 
        password, 
        name, 
        company, 
        plan,
        aiPlan,
        agentType,
        model: selectedModel,
        n8nEnabled: buyN8n,
        aiEnabled: buyAi
      });

      setInfo("Account registered! Your access is pending administrator approval.");
      setIsLoginView(true);
      setEmail("");
      setPassword("");
    } catch (err: any) {
      setError(err.message || "Registration failed");
    } finally {
      setLoading(false);
    }
  };

  const handleLogout = () => {
    localStorage.removeItem("nv_user_token");
    localStorage.removeItem("nv_user_name");
    localStorage.removeItem("nv_user_email");
    localStorage.removeItem("nv_user_plan");
    localStorage.removeItem("nv_user_status");
    setIsAuthenticated(false);
    window.location.href = "/";
  };

  const navigateTo = (tab: string, path: string) => {
    setActiveTab(tab);
    router.push(path);
  };

  if (!isAuthenticated) {
    return (
      <html lang="en">
        <body>
          <div style={{ display: "flex", alignItems: "center", justifyContent: "center", minHeight: "100vh", padding: "20px" }}>
            <div className="glass-card" style={{ width: "100%", maxWidth: "440px", padding: "40px 30px" }}>
              <div style={{ display: "flex", alignItems: "center", gap: "10px", justifyContent: "center", marginBottom: "30px" }}>
                <div style={{ padding: "10px", borderRadius: "12px", background: "linear-gradient(135deg, hsl(var(--primary)), #8b5cf6)", color: "white" }}>
                  <Zap size={24} />
                </div>
                <div>
                  <h1 style={{ fontSize: "1.5rem", fontWeight: "700", letterSpacing: "-0.02em" }}>Neuravolt</h1>
                  <p style={{ fontSize: "0.75rem", color: "rgba(255,255,255,0.4)", fontWeight: "600", textTransform: "uppercase" }}>Customer Cloud Portal</p>
                </div>
              </div>

              {error && (
                <div style={{ display: "flex", alignItems: "center", gap: "8px", background: "rgba(239,68,68,0.1)", border: "1px solid rgba(239,68,68,0.15)", color: "#ef4444", padding: "12px", borderRadius: "8px", fontSize: "0.85rem", marginBottom: "20px" }}>
                  <ShieldAlert size={16} />
                  <span>{error}</span>
                </div>
              )}

              {info && (
                <div style={{ display: "flex", alignItems: "center", gap: "8px", background: "rgba(59,130,246,0.1)", border: "1px solid rgba(59,130,246,0.15)", color: "#3b82f6", padding: "12px", borderRadius: "8px", fontSize: "0.85rem", marginBottom: "20px" }}>
                  <Info size={16} />
                  <span>{info}</span>
                </div>
              )}

              {isLoginView ? (
                /* LOGIN FORM */
                <form onSubmit={handleLogin} style={{ display: "flex", flexDirection: "column", gap: "18px" }}>
                  <h2 style={{ fontSize: "1.2rem", fontWeight: "600", textAlign: "center" }}>Log In to Your Cloud Console</h2>
                  
                  <div>
                    <label style={{ display: "block", fontSize: "0.8rem", color: "rgba(255,255,255,0.6)", marginBottom: "6px", fontWeight: "500" }}>Account Email</label>
                    <div style={{ position: "relative" }}>
                      <Mail size={16} style={{ position: "absolute", left: "14px", top: "14px", color: "rgba(255,255,255,0.3)" }} />
                      <input 
                        type="email" 
                        className="input-field" 
                        placeholder="you@company.com" 
                        style={{ paddingLeft: "42px" }}
                        value={email}
                        onChange={(e) => setEmail(e.target.value)}
                        required
                      />
                    </div>
                  </div>

                  <div>
                    <label style={{ display: "block", fontSize: "0.8rem", color: "rgba(255,255,255,0.6)", marginBottom: "6px", fontWeight: "500" }}>Password</label>
                    <div style={{ position: "relative" }}>
                      <Lock size={16} style={{ position: "absolute", left: "14px", top: "14px", color: "rgba(255,255,255,0.3)" }} />
                      <input 
                        type="password" 
                        className="input-field" 
                        placeholder="••••••••" 
                        style={{ paddingLeft: "42px" }}
                        value={password}
                        onChange={(e) => setPassword(e.target.value)}
                        required
                      />
                    </div>
                  </div>

                  <button type="submit" className="btn btn-primary" style={{ width: "100%", padding: "12px", marginTop: "8px" }} disabled={loading}>
                    {loading ? "Authenticating Session..." : "Sign In to Console"}
                  </button>

                  <p style={{ fontSize: "0.8rem", color: "rgba(255,255,255,0.5)", textAlign: "center", marginTop: "10px" }}>
                    New to Neuravolt?{" "}
                    <button type="button" onClick={() => { setIsLoginView(false); setError(""); setInfo(""); }} style={{ background: "none", border: "none", color: "#a78bfa", fontWeight: "600", cursor: "pointer" }}>
                      Create an account
                    </button>
                  </p>
                </form>
              ) : (
                /* SIGNUP FORM */
                <form onSubmit={handleSignup} style={{ display: "flex", flexDirection: "column", gap: "15px" }}>
                  <h2 style={{ fontSize: "1.2rem", fontWeight: "600", textAlign: "center" }}>Create Your Cloud Account</h2>

                  <div>
                    <label style={{ display: "block", fontSize: "0.75rem", color: "rgba(255,255,255,0.6)", marginBottom: "4px" }}>Full Name</label>
                    <div style={{ position: "relative" }}>
                      <User size={14} style={{ position: "absolute", left: "14px", top: "12px", color: "rgba(255,255,255,0.3)" }} />
                      <input 
                        type="text" 
                        className="input-field" 
                        placeholder="Rahul Sharma" 
                        style={{ paddingLeft: "40px", paddingBlock: "8px", fontSize: "0.85rem" }}
                        value={name}
                        onChange={(e) => setName(e.target.value)}
                        required
                      />
                    </div>
                  </div>

                  <div>
                    <label style={{ display: "block", fontSize: "0.75rem", color: "rgba(255,255,255,0.6)", marginBottom: "4px" }}>Email address</label>
                    <div style={{ position: "relative" }}>
                      <Mail size={14} style={{ position: "absolute", left: "14px", top: "12px", color: "rgba(255,255,255,0.3)" }} />
                      <input 
                        type="email" 
                        className="input-field" 
                        placeholder="rahul@agency.in" 
                        style={{ paddingLeft: "40px", paddingBlock: "8px", fontSize: "0.85rem" }}
                        value={email}
                        onChange={(e) => setEmail(e.target.value)}
                        required
                      />
                    </div>
                  </div>

                  <div>
                    <label style={{ display: "block", fontSize: "0.75rem", color: "rgba(255,255,255,0.6)", marginBottom: "4px" }}>Company / Agency</label>
                    <div style={{ position: "relative" }}>
                      <Building size={14} style={{ position: "absolute", left: "14px", top: "12px", color: "rgba(255,255,255,0.3)" }} />
                      <input 
                        type="text" 
                        className="input-field" 
                        placeholder="Sharma Marketing Ltd." 
                        style={{ paddingLeft: "40px", paddingBlock: "8px", fontSize: "0.85rem" }}
                        value={company}
                        onChange={(e) => setCompany(e.target.value)}
                      />
                    </div>
                  </div>

                  <div>
                    <label style={{ display: "block", fontSize: "0.75rem", color: "rgba(255,255,255,0.6)", marginBottom: "4px" }}>Console Password</label>
                    <div style={{ position: "relative" }}>
                      <Lock size={14} style={{ position: "absolute", left: "14px", top: "12px", color: "rgba(255,255,255,0.3)" }} />
                      <input 
                        type="password" 
                        className="input-field" 
                        placeholder="••••••••" 
                        style={{ paddingLeft: "40px", paddingBlock: "8px", fontSize: "0.85rem" }}
                        value={password}
                        onChange={(e) => setPassword(e.target.value)}
                        required
                      />
                    </div>
                  </div>

                  <div>
                    <label style={{ display: "block", fontSize: "0.8rem", color: "rgba(255,255,255,0.6)", marginBottom: "8px", fontWeight: "600" }}>Products to Purchase</label>
                    <div style={{ display: "flex", flexDirection: "column", gap: "10px" }}>
                      <label style={{ display: "flex", alignItems: "center", gap: "10px", cursor: "pointer", background: "rgba(255,255,255,0.02)", padding: "10px 14px", borderRadius: "8px", border: "1px solid rgba(255,255,255,0.06)" }}>
                        <input 
                          type="checkbox" 
                          checked={buyN8n} 
                          onChange={(e) => setBuyN8n(e.target.checked)}
                          style={{ accentColor: "#8b5cf6", width: "16px", height: "16px" }}
                        />
                        <div>
                          <div style={{ fontSize: "0.85rem", fontWeight: "600" }}>n8n Workflows Automation</div>
                          <div style={{ fontSize: "0.75rem", color: "rgba(255,255,255,0.4)" }}>Deploy isolated workflow containers</div>
                        </div>
                      </label>

                      <label style={{ display: "flex", alignItems: "center", gap: "10px", cursor: "pointer", background: "rgba(255,255,255,0.02)", padding: "10px 14px", borderRadius: "8px", border: "1px solid rgba(255,255,255,0.06)" }}>
                        <input 
                          type="checkbox" 
                          checked={buyAi} 
                          onChange={(e) => setBuyAi(e.target.checked)}
                          style={{ accentColor: "#8b5cf6", width: "16px", height: "16px" }}
                        />
                        <div>
                          <div style={{ fontSize: "0.85rem", fontWeight: "600" }}>Harikson AI Agents Core</div>
                          <div style={{ fontSize: "0.75rem", color: "rgba(255,255,255,0.4)" }}>Deploy Ollama, custom templates & vector pools</div>
                        </div>
                      </label>
                    </div>
                  </div>

                  {buyN8n && (
                    <div>
                      <label style={{ display: "block", fontSize: "0.75rem", color: "rgba(255,255,255,0.6)", marginBottom: "4px" }}>Select n8n Subscription Plan</label>
                      <select 
                        className="input-field" 
                        style={{ fontSize: "0.85rem", padding: "8px 12px" }}
                        value={plan}
                        onChange={(e) => setPlan(e.target.value)}
                      >
                        <option value="STARTER">STARTER - ₹2,499/mo (0.5 CPU, 512MB RAM)</option>
                        <option value="PRO">PRO - ₹4,999/mo (1.0 CPU, 1024MB RAM)</option>
                        <option value="BUSINESS">BUSINESS - ₹10,999/mo (2.0 CPU, 2048MB RAM)</option>
                        <option value="ENTERPRISE">ENTERPRISE - Custom Pricing (4.0 CPU, 4096MB RAM)</option>
                      </select>
                    </div>
                  )}

                  {buyAi && (
                    <div>
                      <label style={{ display: "block", fontSize: "0.75rem", color: "rgba(255,255,255,0.6)", marginBottom: "4px" }}>Select AI Agents Plan</label>
                      <select 
                        className="input-field" 
                        style={{ fontSize: "0.85rem", padding: "8px 12px" }}
                        value={aiPlan}
                        onChange={(e) => setAiPlan(e.target.value)}
                      >
                        <option value="STARTER">STARTER AI - ₹2,499/mo (8 GB RAM)</option>
                        <option value="PRO">PRO AI - ₹4,999/mo (12 GB RAM)</option>
                        <option value="BUSINESS">BUSINESS AI - ₹10,999/mo (16 GB RAM)</option>
                        <option value="ENTERPRISE">ENTERPRISE AI - Custom Pricing (24 GB RAM)</option>
                      </select>
                    </div>
                  )}

                  {buyAi && (
                    <div>
                      <label style={{ display: "block", fontSize: "0.75rem", color: "rgba(255,255,255,0.6)", marginBottom: "4px" }}>Select AI Agent Model</label>
                      <select 
                        className="input-field" 
                        style={{ fontSize: "0.85rem", padding: "8px 12px" }}
                        value={selectedModel}
                        onChange={(e) => setSelectedModel(e.target.value)}
                      >
                        <optgroup label="Best Coding" style={{ background: "#1f1f2e", color: "#a78bfa" }}>
                          {(MODELS_BY_PLAN[aiPlan] || []).filter(m => m.category === "coding").map(m => (
                            <option key={m.id} value={m.id} disabled={m.disabled} style={{ background: "#0a0a0f", color: "white" }}>
                              {m.displayName} ({m.baseModel}) &rarr; {m.size}
                            </option>
                          ))}
                        </optgroup>
                        <optgroup label="Best General Chat" style={{ background: "#1f1f2e", color: "#a78bfa" }}>
                          {(MODELS_BY_PLAN[aiPlan] || []).filter(m => m.category === "chat").map(m => (
                            <option key={m.id} value={m.id} disabled={m.disabled} style={{ background: "#0a0a0f", color: "white" }}>
                              {m.displayName} ({m.baseModel}) &rarr; {m.size}
                            </option>
                          ))}
                        </optgroup>
                      </select>
                    </div>
                  )}

                  <button type="submit" className="btn btn-primary" style={{ width: "100%", padding: "10px", marginTop: "5px" }} disabled={loading}>
                    {loading ? "Registering Account..." : "Create Account"}
                  </button>

                  <p style={{ fontSize: "0.8rem", color: "rgba(255,255,255,0.5)", textAlign: "center", marginTop: "8px" }}>
                    Already have an account?{" "}
                    <button type="button" onClick={() => { setIsLoginView(true); setError(""); setInfo(""); }} style={{ background: "none", border: "none", color: "#a78bfa", fontWeight: "600", cursor: "pointer" }}>
                      Sign in
                    </button>
                  </p>
                </form>
              )}
            </div>
          </div>
        </body>
      </html>
    );
  }

  return (
    <html lang="en">
      <body>
        <div style={{ display: "flex", minHeight: "100vh" }}>
          {/* User Sidebar */}
          <aside className="glass-card" style={{ width: "260px", borderRadius: "0 24px 24px 0", borderLeft: "none", borderTop: "none", borderBottom: "none", display: "flex", flexDirection: "column", padding: "30px 20px", position: "fixed", height: "100vh", zIndex: 100 }}>
            <div style={{ display: "flex", alignItems: "center", gap: "10px", marginBottom: "40px" }}>
              <div style={{ padding: "8px", borderRadius: "10px", background: "linear-gradient(135deg, hsl(var(--primary)), #8b5cf6)", color: "white" }}>
                <Zap size={20} />
              </div>
              <div>
                <h1 style={{ fontSize: "1.2rem", fontWeight: "700", letterSpacing: "-0.02em" }}>Neuravolt</h1>
                <p style={{ fontSize: "0.65rem", color: "rgba(255,255,255,0.4)", fontWeight: "600", textTransform: "uppercase" }}>User Console</p>
              </div>
            </div>

            <nav style={{ display: "flex", flexDirection: "column", gap: "8px", flex: 1 }}>
              {n8nEnabled && (
                <button 
                  onClick={() => navigateTo("dashboard", "/")}
                  className="btn" 
                  style={{ justifyContent: "flex-start", width: "100%", background: activeTab === "dashboard" ? "rgba(139, 92, 246, 0.15)" : "transparent", color: activeTab === "dashboard" ? "#a78bfa" : "rgba(255,255,255,0.7)" }}
                >
                  <LayoutDashboard size={18} />
                  <span>My Dashboard</span>
                </button>
              )}

              <button 
                onClick={() => navigateTo("billing", "/billing")}
                className="btn" 
                style={{ justifyContent: "flex-start", width: "100%", background: activeTab === "billing" ? "rgba(139, 92, 246, 0.15)" : "transparent", color: activeTab === "billing" ? "#a78bfa" : "rgba(255,255,255,0.7)" }}
              >
                <IndianRupee size={18} />
                <span>My Invoices</span>
              </button>

              <button 
                onClick={() => navigateTo("settings", "/settings")}
                className="btn" 
                style={{ justifyContent: "flex-start", width: "100%", background: activeTab === "settings" ? "rgba(139, 92, 246, 0.15)" : "transparent", color: activeTab === "settings" ? "#a78bfa" : "rgba(255,255,255,0.7)" }}
              >
                <Settings size={18} />
                <span>Settings</span>
              </button>

              {aiEnabled && (
                <button 
                  onClick={() => navigateTo("harikson", "/harikson")}
                  className="btn" 
                  style={{ justifyContent: "flex-start", width: "100%", background: activeTab === "harikson" ? "rgba(139, 92, 246, 0.15)" : "transparent", color: activeTab === "harikson" ? "#a78bfa" : "rgba(255,255,255,0.7)" }}
                >
                  <Zap size={18} />
                  <span>AI Agents</span>
                </button>
              )}
            </nav>

            <div style={{ borderTop: "1px solid rgba(255,255,255,0.06)", paddingTop: "20px" }}>
              <div style={{ display: "flex", alignItems: "center", gap: "10px", marginBottom: "15px", padding: "0 8px" }}>
                <div style={{ width: "32px", height: "32px", borderRadius: "50%", background: "rgba(255,255,255,0.1)", display: "flex", alignItems: "center", justifyContent: "center", fontSize: "0.85rem", fontWeight: "600" }}>
                  U
                </div>
                <div>
                  <p style={{ fontSize: "0.85rem", fontWeight: "500", whiteSpace: "nowrap", overflow: "hidden", textOverflow: "ellipsis", maxWidth: "150px" }}>
                    {typeof window !== "undefined" ? localStorage.getItem("nv_user_name") : "Client"}
                  </p>
                  <p style={{ fontSize: "0.7rem", color: "rgba(255,255,255,0.4)" }}>Client Operator</p>
                </div>
              </div>
              <button onClick={handleLogout} className="btn btn-danger" style={{ width: "100%" }}>
                <LogOut size={16} />
                <span>Sign Out</span>
              </button>
            </div>
          </aside>

          <main style={{ marginLeft: "260px", flex: 1, padding: "40px", minHeight: "100vh" }}>
            {children}
          </main>
        </div>
      </body>
    </html>
  );
}
