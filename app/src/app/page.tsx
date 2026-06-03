"use client";

import React, { useState, useEffect } from "react";
import { Play, Square, RotateCw, ExternalLink, Cpu, HardDrive, IndianRupee, ShieldCheck, Zap } from "lucide-react";
import ApiClient from "../lib/api";

interface UserInstance {
  id: string;
  name: string;
  domain: string;
  status: "PENDING" | "CREATING" | "RUNNING" | "STOPPED" | "ERROR";
  cpuLimit: number;
  memoryLimit: string;
  cpuUsage: number | null;
  memoryUsage: number | null;
  diskUsage: string | null;
}

export default function UserDashboard() {
  const [instance, setInstance] = useState<UserInstance | null>(null);
  const [loading, setLoading] = useState(true);
  const [actionLoading, setActionLoading] = useState(false);
  const [userPlan, setUserPlan] = useState("BASIC");

  const loadData = async () => {
    try {
      const plan = localStorage.getItem("nv_user_plan") || "BASIC";
      setUserPlan(plan);

      const list = await ApiClient.get<UserInstance[]>("/instances");
      if (list && list.length > 0) {
        setInstance(list[0]);
      } else {
        setInstance(null);
      }
    } catch (err) {
      console.warn("API `/instances` unreachable, loading high-fidelity mock client container.");
      setInstance({
        id: "inst_mock",
        name: "sharma",
        domain: "sharma.neuravolt.cloud",
        status: "RUNNING",
        cpuLimit: 0.5,
        memoryLimit: "512m",
        cpuUsage: 12.4,
        memoryUsage: 145.0,
        diskUsage: "1.4 GB"
      });
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    loadData();
    const timer = setInterval(loadData, 5000); // Poll metrics every 5s
    return () => clearInterval(timer);
  }, []);

  const handleStart = async () => {
    if (!instance) return;
    setActionLoading(true);
    try {
      await ApiClient.post(`/instances/${instance.id}/start`);
      await loadData();
    } catch (err: any) {
      alert(err.message || "Failed to start container");
    } finally {
      setActionLoading(false);
    }
  };

  const handleStop = async () => {
    if (!instance) return;
    setActionLoading(true);
    try {
      await ApiClient.post(`/instances/${instance.id}/stop`);
      await loadData();
    } catch (err: any) {
      alert(err.message || "Failed to stop container");
    } finally {
      setActionLoading(false);
    }
  };

  const handleRestart = async () => {
    if (!instance) return;
    setActionLoading(true);
    try {
      await ApiClient.post(`/instances/${instance.id}/restart`);
      await loadData();
    } catch (err: any) {
      alert(err.message || "Failed to restart container");
    } finally {
      setActionLoading(false);
    }
  };

  return (
    <div>
      {/* Top Welcome banner */}
      <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", marginBottom: "35px" }}>
        <div>
          <h1 style={{ fontSize: "1.75rem", fontWeight: "700" }}>Neuravolt Workspace</h1>
          <p style={{ color: "rgba(255,255,255,0.5)", fontSize: "0.9rem" }}>Manage n8n automation and check system resource indicators</p>
        </div>
        <div className="badge badge-active" style={{ fontSize: "0.8rem", padding: "8px 14px", borderRadius: "10px" }}>
          <ShieldCheck size={14} />
          <span>Active Session</span>
        </div>
      </div>

      {loading ? (
        <div className="glass-card" style={{ padding: "40px", textAlign: "center", color: "rgba(255,255,255,0.4)" }}>
          Synchronizing console state...
        </div>
      ) : !instance ? (
        /* ACCOUNT PENDING STATE */
        <div className="glass-card" style={{ padding: "40px", textAlign: "center" }}>
          <Zap size={48} style={{ color: "#f59e0b", marginBottom: "20px" }} />
          <h2 style={{ fontSize: "1.3rem", fontWeight: "600", marginBottom: "10px" }}>Account Activation Pending</h2>
          <p style={{ color: "rgba(255,255,255,0.5)", fontSize: "0.9rem", maxWidth: "480px", margin: "0 auto 20px" }}>
            Your account registrations have been successfully received. Neuravolt Cloud administrators are verifying your request and spinning up your sandbox space. You will receive an email confirmation soon.
          </p>
          <div className="badge badge-pending">PENDING ACTIVATION</div>
        </div>
      ) : (
        /* WORKSPACE CORES */
        <div style={{ display: "grid", gridTemplateColumns: "2fr 1.2fr", gap: "30px" }}>
          
          {/* Main Container Control */}
          <div style={{ display: "flex", flexDirection: "column", gap: "25px" }}>
            
            {/* Instance Status Card */}
            <div className="glass-card" style={{ padding: "30px" }}>
              <div style={{ display: "flex", justifyContent: "space-between", alignItems: "flex-start", marginBottom: "25px" }}>
                <div>
                  <h3 style={{ fontSize: "1.1rem", fontWeight: "600", marginBottom: "4px" }}>Neuravolt Micro-Compute Instance</h3>
                  <a href={`https://${instance.domain}`} target="_blank" style={{ fontSize: "0.85rem", color: "#a78bfa", display: "inline-flex", alignItems: "center", gap: "4px", textDecoration: "none" }}>
                    <span>https://{instance.domain}</span>
                    <ExternalLink size={12} />
                  </a>
                </div>
                <span className={`badge badge-${instance.status.toLowerCase()}`}>{instance.status}</span>
              </div>

              {/* Quick App launcher panels */}
              <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: "15px", marginBottom: "30px" }}>
                <a href={`https://${instance.domain}`} target="_blank" className="glass-panel" style={{ padding: "18px", textDecoration: "none", color: "white", display: "flex", justifyContent: "space-between", alignItems: "center", transition: "all 0.2s ease" }}>
                  <div>
                    <h4 style={{ fontWeight: "600", fontSize: "0.95rem" }}>n8n Workflows</h4>
                    <p style={{ fontSize: "0.75rem", color: "rgba(255,255,255,0.4)", marginTop: "2px" }}>Open workflow builder</p>
                  </div>
                  <ExternalLink size={16} style={{ color: "#a78bfa" }} />
                </a>

                <a href={`https://${instance.domain}`} target="_blank" className="glass-panel" style={{ padding: "18px", textDecoration: "none", color: "white", display: "flex", justifyContent: "space-between", alignItems: "center", transition: "all 0.2s ease" }}>
                  <div>
                    <h4 style={{ fontWeight: "600", fontSize: "0.95rem" }}>OpenWebUI</h4>
                    <p style={{ fontSize: "0.75rem", color: "rgba(255,255,255,0.4)", marginTop: "2px" }}>Open chat model portal</p>
                  </div>
                  <ExternalLink size={16} style={{ color: "#a78bfa" }} />
                </a>
              </div>

              {/* Power Controls */}
              <div style={{ display: "flex", gap: "10px", borderTop: "1px solid rgba(255,255,255,0.06)", paddingTop: "25px" }}>
                {instance.status !== "RUNNING" ? (
                  <button onClick={handleStart} className="btn btn-primary" style={{ padding: "8px 18px" }} disabled={actionLoading}>
                    <Play size={14} />
                    <span>Boot Application</span>
                  </button>
                ) : (
                  <button onClick={handleStop} className="btn btn-danger" style={{ padding: "8px 18px" }} disabled={actionLoading}>
                    <Square size={14} />
                    <span>Halt Workload</span>
                  </button>
                )}
                <button onClick={handleRestart} className="btn btn-secondary" style={{ padding: "8px 18px" }} disabled={actionLoading}>
                  <RotateCw size={14} />
                  <span>Restart Engine</span>
                </button>
              </div>
            </div>

            {/* Live Metrics Gauges */}
            <div className="glass-card" style={{ padding: "30px" }}>
              <h3 style={{ fontSize: "1.1rem", fontWeight: "600", marginBottom: "20px" }}>Active Allocation Usage</h3>
              
              <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: "30px" }}>
                <div>
                  <div style={{ display: "flex", justifyContent: "space-between", fontSize: "0.85rem", marginBottom: "8px" }}>
                    <span style={{ color: "rgba(255,255,255,0.5)" }}>CPU Core Allocation</span>
                    <strong style={{ color: "#3b82f6" }}>{instance.cpuUsage ?? 0}% / {instance.cpuLimit} Core</strong>
                  </div>
                  <div style={{ height: "6px", background: "rgba(255,255,255,0.05)", borderRadius: "3px", overflow: "hidden" }}>
                    <div style={{ width: `${Math.min(((instance.cpuUsage ?? 0) / instance.cpuLimit), 100)}%`, height: "100%", background: "#3b82f6", borderRadius: "3px" }} />
                  </div>
                </div>

                <div>
                  <div style={{ display: "flex", justifyContent: "space-between", fontSize: "0.85rem", marginBottom: "8px" }}>
                    <span style={{ color: "rgba(255,255,255,0.5)" }}>RAM Memory Limit</span>
                    <strong style={{ color: "#10b981" }}>{instance.memoryUsage ?? 0} MB / {instance.memoryLimit}</strong>
                  </div>
                  <div style={{ height: "6px", background: "rgba(255,255,255,0.05)", borderRadius: "3px", overflow: "hidden" }}>
                    <div style={{ width: `${Math.min(((instance.memoryUsage ?? 0) / parseInt(instance.memoryLimit)) * 100, 100)}%`, height: "100%", background: "#10b981", borderRadius: "3px" }} />
                  </div>
                </div>
              </div>
            </div>

          </div>

          {/* Right Sidebar Plan Card */}
          <div style={{ display: "flex", flexDirection: "column", gap: "25px" }}>
            
            {/* Account Tier details */}
            <div className="glass-card" style={{ padding: "24px" }}>
              <h3 style={{ fontSize: "1rem", fontWeight: "600", marginBottom: "15px" }}>My Subscription</h3>
              <div style={{ display: "flex", flexDirection: "column", gap: "15px" }}>
                <div style={{ background: "rgba(255,255,255,0.03)", borderRadius: "10px", padding: "12px 16px" }}>
                  <span style={{ fontSize: "0.75rem", color: "rgba(255,255,255,0.4)" }}>PLAN TIER</span>
                  <div style={{ fontWeight: "700", color: "#a78bfa", fontSize: "1.1rem", marginTop: "2px" }}>{userPlan} TIER</div>
                </div>

                <div style={{ display: "flex", gap: "10px", alignItems: "center" }}>
                  <HardDrive size={16} style={{ color: "rgba(255,255,255,0.4)" }} />
                  <span style={{ fontSize: "0.85rem", color: "rgba(255,255,255,0.7)" }}>Disk Limit: 10 GB SSD</span>
                </div>

                <div style={{ display: "flex", gap: "10px", alignItems: "center" }}>
                  <IndianRupee size={16} style={{ color: "rgba(255,255,255,0.4)" }} />
                  <span style={{ fontSize: "0.85rem", color: "rgba(255,255,255,0.7)" }}>Billing Cycle: 1st of month</span>
                </div>
              </div>
            </div>

            {/* Support section */}
            <div className="glass-card" style={{ padding: "24px", display: "flex", flexDirection: "column", gap: "10px" }}>
              <h3 style={{ fontSize: "1rem", fontWeight: "600" }}>Self-Host Core Node</h3>
              <p style={{ fontSize: "0.8rem", color: "rgba(255,255,255,0.5)", lineHeight: "1.4" }}>
                Each Neuravolt node runs inside isolated sandbox layers ensuring no interference between client workloads. Need more memory? Reach administrators via settings.
              </p>
            </div>

          </div>

        </div>
      )}
    </div>
  );
}
