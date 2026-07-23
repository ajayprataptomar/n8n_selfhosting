"use client";

import React, { useState, useEffect } from "react";
import { useRouter } from "next/navigation";
import "./globals.css";
import ApiClient from "../lib/api";
import { 
  LayoutDashboard, 
  Users, 
  Layers, 
  IndianRupee, 
  Activity, 
  LogOut, 
  ShieldAlert, 
  Lock, 
  Mail,
  Zap
} from "lucide-react";

export default function RootLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  const router = useRouter();
  const [isAuthenticated, setIsAuthenticated] = useState<boolean>(false);
  const [activeTab, setActiveTab] = useState<string>("dashboard");
  const [email, setEmail] = useState<string>("");
  const [password, setPassword] = useState<string>("");
  const [error, setError] = useState<string>("");
  const [loading, setLoading] = useState<boolean>(false);

  useEffect(() => {
    if (typeof window !== "undefined") {
      const token = localStorage.getItem("nv_token");
      const role = localStorage.getItem("nv_role");
      if (token && role === "ADMIN") {
        setIsAuthenticated(true);
      }
      
      // Determine active tab from URL path
      const path = window.location.pathname;
      if (path.includes("/users")) setActiveTab("users");
      else if (path.includes("/instances")) setActiveTab("instances");
      else if (path.includes("/billing")) setActiveTab("billing");
      else if (path.includes("/monitoring")) setActiveTab("monitoring");
      else if (path.includes("/harikson")) setActiveTab("harikson");
      else setActiveTab("dashboard");
    }
  }, []);

  const handleLogin = async (e: React.FormEvent) => {
    e.preventDefault();
    setError("");
    setLoading(true);

    try {
      const data = await ApiClient.post<{ token: string; user: { role: string; name?: string; email?: string } }>("/auth/login", { email, password });
      
      if (data.user.role !== "ADMIN") {
        throw new Error("Access restricted to administrators only.");
      }

      localStorage.setItem("nv_token", data.token);
      localStorage.setItem("nv_role", data.user.role);
      localStorage.setItem("nv_username", data.user.name || data.user.email || "admin");
      
      setIsAuthenticated(true);
      window.location.href = "/";
    } catch (err: any) {
      setError(err.message || "Something went wrong");
    } finally {
      setLoading(false);
    }
  };

  const handleLogout = () => {
    localStorage.removeItem("nv_token");
    localStorage.removeItem("nv_role");
    localStorage.removeItem("nv_username");
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
            <div className="glass-card" style={{ width: "100%", maxWidth: "420px", padding: "40px 30px" }}>
              <div style={{ display: "flex", alignItems: "center", gap: "10px", justifyContent: "center", marginBottom: "30px" }}>
                <div style={{ padding: "10px", borderRadius: "12px", background: "linear-gradient(135deg, hsl(var(--primary)), #8b5cf6)", color: "white" }}>
                  <Zap size={24} />
                </div>
                <div>
                  <h1 style={{ fontSize: "1.5rem", fontWeight: "700", letterSpacing: "-0.02em" }}>Neuravolt</h1>
                  <p style={{ fontSize: "0.75rem", color: "rgba(255,255,255,0.4)", fontWeight: "600", textTransform: "uppercase" }}>Admin Console</p>
                </div>
              </div>

              <h2 style={{ fontSize: "1.25rem", fontWeight: "600", marginBottom: "8px", textAlign: "center" }}>Sign In to Control Panel</h2>
              <p style={{ fontSize: "0.85rem", color: "rgba(255,255,255,0.5)", textAlign: "center", marginBottom: "30px" }}>Enter credentials to manage system resources</p>

              {error && (
                <div style={{ display: "flex", alignItems: "center", gap: "8px", background: "rgba(239,68,68,0.1)", border: "1px solid rgba(239,68,68,0.2)", color: "#ef4444", padding: "12px", borderRadius: "8px", fontSize: "0.85rem", marginBottom: "20px" }}>
                  <ShieldAlert size={16} />
                  <span>{error}</span>
                </div>
              )}

              <form onSubmit={handleLogin} style={{ display: "flex", flexDirection: "column", gap: "18px" }}>
                <div>
                  <label style={{ display: "block", fontSize: "0.8rem", color: "rgba(255,255,255,0.6)", marginBottom: "6px", fontWeight: "500" }}>Admin Email</label>
                  <div style={{ position: "relative" }}>
                    <Mail size={16} style={{ position: "absolute", left: "14px", top: "14px", color: "rgba(255,255,255,0.3)" }} />
                    <input 
                      type="email" 
                      className="input-field" 
                      placeholder="admin@neuravolt.cloud" 
                      style={{ paddingLeft: "42px" }}
                      value={email}
                      onChange={(e) => setEmail(e.target.value)}
                      required
                    />
                  </div>
                </div>

                <div>
                  <label style={{ display: "block", fontSize: "0.8rem", color: "rgba(255,255,255,0.6)", marginBottom: "6px", fontWeight: "500" }}>Secret Key</label>
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

                <button type="submit" className="btn btn-primary" style={{ width: "100%", padding: "12px", marginTop: "10px" }} disabled={loading}>
                  {loading ? "Authenticating..." : "Establish Secure Session"}
                </button>
              </form>
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
          {/* Glass Sidebar */}
          <aside className="glass-card" style={{ width: "260px", borderRadius: "0 24px 24px 0", borderLeft: "none", borderTop: "none", borderBottom: "none", display: "flex", flexDirection: "column", padding: "30px 20px", position: "fixed", height: "100vh", zIndex: 100 }}>
            {/* Header */}
            <div style={{ display: "flex", alignItems: "center", gap: "10px", marginBottom: "40px" }}>
              <div style={{ padding: "8px", borderRadius: "10px", background: "linear-gradient(135deg, hsl(var(--primary)), #8b5cf6)", color: "white" }}>
                <Zap size={20} />
              </div>
              <div>
                <h1 style={{ fontSize: "1.2rem", fontWeight: "700", letterSpacing: "-0.02em" }}>Neuravolt</h1>
                <p style={{ fontSize: "0.65rem", color: "rgba(255,255,255,0.4)", fontWeight: "600", textTransform: "uppercase" }}>Admin Panel</p>
              </div>
            </div>

            {/* Nav Menu */}
            <nav style={{ display: "flex", flexDirection: "column", gap: "8px", flex: 1 }}>
              <button 
                onClick={() => navigateTo("dashboard", "/")}
                className="btn" 
                style={{ justifyContent: "flex-start", width: "100%", background: activeTab === "dashboard" ? "rgba(139, 92, 246, 0.15)" : "transparent", color: activeTab === "dashboard" ? "#a78bfa" : "rgba(255,255,255,0.7)" }}
              >
                <LayoutDashboard size={18} />
                <span>Dashboard</span>
              </button>

              <button 
                onClick={() => navigateTo("users", "/users")}
                className="btn" 
                style={{ justifyContent: "flex-start", width: "100%", background: activeTab === "users" ? "rgba(139, 92, 246, 0.15)" : "transparent", color: activeTab === "users" ? "#a78bfa" : "rgba(255,255,255,0.7)" }}
              >
                <Users size={18} />
                <span>Users Control</span>
              </button>

              <button 
                onClick={() => navigateTo("instances", "/instances")}
                className="btn" 
                style={{ justifyContent: "flex-start", width: "100%", background: activeTab === "instances" ? "rgba(139, 92, 246, 0.15)" : "transparent", color: activeTab === "instances" ? "#a78bfa" : "rgba(255,255,255,0.7)" }}
              >
                <Layers size={18} />
                <span>Containers</span>
              </button>

              <button 
                onClick={() => navigateTo("billing", "/billing")}
                className="btn" 
                style={{ justifyContent: "flex-start", width: "100%", background: activeTab === "billing" ? "rgba(139, 92, 246, 0.15)" : "transparent", color: activeTab === "billing" ? "#a78bfa" : "rgba(255,255,255,0.7)" }}
              >
                <IndianRupee size={18} />
                <span>Billing System</span>
              </button>

              <button 
                onClick={() => navigateTo("monitoring", "/monitoring")}
                className="btn" 
                style={{ justifyContent: "flex-start", width: "100%", background: activeTab === "monitoring" ? "rgba(139, 92, 246, 0.15)" : "transparent", color: activeTab === "monitoring" ? "#a78bfa" : "rgba(255,255,255,0.7)" }}
              >
                <Activity size={18} />
                <span>Monitoring</span>
              </button>

              <button 
                onClick={() => navigateTo("harikson", "/harikson")}
                className="btn" 
                style={{ justifyContent: "flex-start", width: "100%", background: activeTab === "harikson" ? "rgba(139, 92, 246, 0.15)" : "transparent", color: activeTab === "harikson" ? "#a78bfa" : "rgba(255,255,255,0.7)" }}
              >
                <Zap size={18} />
                <span>AI Agents Control</span>
              </button>
            </nav>

            {/* Logout Footer */}
            <div style={{ borderTop: "1px solid rgba(255,255,255,0.06)", paddingTop: "20px" }}>
              <div style={{ display: "flex", alignItems: "center", gap: "10px", marginBottom: "15px", padding: "0 8px" }}>
                <div style={{ width: "32px", height: "32px", borderRadius: "50%", background: "rgba(255,255,255,0.1)", display: "flex", alignItems: "center", justifyContent: "center", fontSize: "0.85rem", fontWeight: "600" }}>
                  A
                </div>
                <div>
                  <p style={{ fontSize: "0.85rem", fontWeight: "500" }}>Administrator</p>
                  <p style={{ fontSize: "0.7rem", color: "rgba(255,255,255,0.4)" }}>Console Operator</p>
                </div>
              </div>
              <button 
                onClick={handleLogout}
                className="btn btn-danger" 
                style={{ width: "100%" }}
              >
                <LogOut size={16} />
                <span>Terminate Session</span>
              </button>
            </div>
          </aside>

          {/* Main Workspace */}
          <main style={{ marginLeft: "260px", flex: 1, padding: "40px", minHeight: "100vh" }}>
            {children}
          </main>
        </div>
      </body>
    </html>
  );
}
