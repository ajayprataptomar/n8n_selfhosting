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
  const [plan, setPlan] = useState("BASIC");
  
  const [error, setError] = useState("");
  const [info, setInfo] = useState("");
  const [loading, setLoading] = useState(false);

  useEffect(() => {
    if (typeof window !== "undefined") {
      const token = localStorage.getItem("nv_user_token");
      if (token) {
        setIsAuthenticated(true);
      }
      
      const path = window.location.pathname;
      if (path.includes("/billing")) setActiveTab("billing");
      else if (path.includes("/settings")) setActiveTab("settings");
      else setActiveTab("dashboard");
    }
  }, []);

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

  const handleSignup = async (e: React.FormEvent) => {
    e.preventDefault();
    setError("");
    setInfo("");
    setLoading(true);

    try {
      await ApiClient.post("/auth/signup", { email, password, name, company, plan });

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
                    <label style={{ display: "block", fontSize: "0.75rem", color: "rgba(255,255,255,0.6)", marginBottom: "4px" }}>Select Subscription Plan</label>
                    <select 
                      className="input-field" 
                      style={{ fontSize: "0.85rem", padding: "8px 12px" }}
                      value={plan}
                      onChange={(e) => setPlan(e.target.value)}
                    >
                      <option value="LITE">LITE - ₹499/mo (0.5 CPU, 512MB RAM)</option>
                      <option value="BASIC">BASIC - ₹999/mo (0.5 CPU, 512MB RAM + Addons)</option>
                      <option value="PRO">PRO - ₹1999/mo (1.0 CPU, 1024MB RAM)</option>
                      <option value="HEAVY">HEAVY - ₹3999/mo (2.0 CPU, 2048MB RAM)</option>
                    </select>
                  </div>

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
              <button 
                onClick={() => navigateTo("dashboard", "/")}
                className="btn" 
                style={{ justifyContent: "flex-start", width: "100%", background: activeTab === "dashboard" ? "rgba(139, 92, 246, 0.15)" : "transparent", color: activeTab === "dashboard" ? "#a78bfa" : "rgba(255,255,255,0.7)" }}
              >
                <LayoutDashboard size={18} />
                <span>My Dashboard</span>
              </button>

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
