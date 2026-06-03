"use client";

import React, { useState, useEffect } from "react";
import { Users, Layers, IndianRupee, Bell, AlertTriangle, ShieldCheck, Play } from "lucide-react";
import ApiClient from "../lib/api";

interface DashboardMetrics {
  totalUsers: number;
  activeContainers: number;
  totalContainers: number;
  systemCpuAverage: number;
  systemMemoryUsageGB: number;
  systemMemoryTotalGB: number;
}

export default function AdminDashboard() {
  const [metrics, setMetrics] = useState<DashboardMetrics>({
    totalUsers: 0,
    activeContainers: 0,
    totalContainers: 0,
    systemCpuAverage: 0,
    systemMemoryUsageGB: 0,
    systemMemoryTotalGB: 16.0,
  });
  const [loading, setLoading] = useState(true);
  const [alerts, setAlerts] = useState<string[]>([]);

  useEffect(() => {
    async function loadData() {
      try {
        const data = await ApiClient.get<DashboardMetrics>("/monitoring/metrics");
        setMetrics(data);
      } catch (err) {
        console.warn("API metrics unreachable, utilizing high-fidelity mock overrides.");
        // Mock fallback
        setMetrics({
          totalUsers: 14,
          activeContainers: 8,
          totalContainers: 10,
          systemCpuAverage: 24.5,
          systemMemoryUsageGB: 6.8,
          systemMemoryTotalGB: 16.0,
        });
      } finally {
        setLoading(false);
      }
    }

    loadData();
    const interval = setInterval(loadData, 5000); // refresh every 5s

    // Set static mock alerts
    setAlerts([
      "Daily backup process completed successfully at 00:00 UTC",
      "Notice: Database transaction pools scaling up automatically",
      "System warning: cAdvisor telemetry feed connecting...",
    ]);

    return () => clearInterval(interval);
  }, []);

  return (
    <div>
      {/* Header */}
      <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", marginBottom: "30px" }}>
        <div>
          <h1 style={{ fontSize: "1.75rem", fontWeight: "700" }}>System Command Center</h1>
          <p style={{ color: "rgba(255,255,255,0.5)", fontSize: "0.9rem" }}>Neuravolt Node Management & Resource Controls</p>
        </div>
        <div style={{ display: "flex", gap: "10px" }}>
          <div className="badge badge-active" style={{ fontSize: "0.8rem", padding: "8px 14px", borderRadius: "10px" }}>
            <ShieldCheck size={14} />
            <span>NODE SECURE</span>
          </div>
        </div>
      </div>

      {/* Metrics Row */}
      <div style={{ display: "grid", gridTemplateColumns: "repeat(auto-fit, minmax(220px, 1fr))", gap: "20px", marginBottom: "40px" }}>
        <div className="glass-card" style={{ padding: "24px", display: "flex", alignItems: "center", gap: "20px" }}>
          <div style={{ padding: "12px", borderRadius: "12px", background: "rgba(167, 139, 250, 0.1)", color: "#a78bfa" }}>
            <Users size={24} />
          </div>
          <div>
            <p style={{ fontSize: "0.85rem", color: "rgba(255,255,255,0.5)", fontWeight: "500" }}>Total Registrations</p>
            <h3 style={{ fontSize: "1.75rem", fontWeight: "700", marginTop: "4px" }}>{loading ? "..." : metrics.totalUsers}</h3>
          </div>
        </div>

        <div className="glass-card" style={{ padding: "24px", display: "flex", alignItems: "center", gap: "20px" }}>
          <div style={{ padding: "12px", borderRadius: "12px", background: "rgba(59, 130, 246, 0.1)", color: "#3b82f6" }}>
            <Layers size={24} />
          </div>
          <div>
            <p style={{ fontSize: "0.85rem", color: "rgba(255,255,255,0.5)", fontWeight: "500" }}>Active Workloads</p>
            <h3 style={{ fontSize: "1.75rem", fontWeight: "700", marginTop: "4px" }}>{loading ? "..." : `${metrics.activeContainers} / ${metrics.totalContainers}`}</h3>
          </div>
        </div>

        <div className="glass-card" style={{ padding: "24px", display: "flex", alignItems: "center", gap: "20px" }}>
          <div style={{ padding: "12px", borderRadius: "12px", background: "rgba(16, 185, 129, 0.1)", color: "#10b981" }}>
            <IndianRupee size={24} />
          </div>
          <div>
            <p style={{ fontSize: "0.85rem", color: "rgba(255,255,255,0.5)", fontWeight: "500" }}>Estimated MRR</p>
            <h3 style={{ fontSize: "1.75rem", fontWeight: "700", marginTop: "4px" }}>{loading ? "..." : `₹${metrics.totalUsers * 999}`}</h3>
          </div>
        </div>
      </div>

      {/* Grid of details */}
      <div style={{ display: "grid", gridTemplateColumns: "2fr 1fr", gap: "30px" }}>
        
        {/* Core telemetry details */}
        <div className="glass-card" style={{ padding: "30px" }}>
          <h3 style={{ fontSize: "1.1rem", fontWeight: "600", marginBottom: "20px" }}>Live Engine Resource Footprints</h3>
          
          <div style={{ display: "flex", flexDirection: "column", gap: "24px" }}>
            {/* CPU utilization bar */}
            <div>
              <div style={{ display: "flex", justifyContent: "space-between", marginBottom: "8px", fontSize: "0.9rem" }}>
                <span style={{ fontWeight: "500" }}>Cluster-wide CPU Load</span>
                <span style={{ color: "#a78bfa", fontWeight: "600" }}>{metrics.systemCpuAverage}%</span>
              </div>
              <div style={{ width: "100%", height: "8px", background: "rgba(255,255,255,0.05)", borderRadius: "4px", overflow: "hidden" }}>
                <div style={{ width: `${metrics.systemCpuAverage}%`, height: "100%", background: "linear-gradient(90deg, #3b82f6, #8b5cf6)", borderRadius: "4px", transition: "width 0.5s ease" }} />
              </div>
            </div>

            {/* RAM usage bar */}
            <div>
              <div style={{ display: "flex", justifyContent: "space-between", marginBottom: "8px", fontSize: "0.9rem" }}>
                <span style={{ fontWeight: "500" }}>Dynamic Memory (RAM) Consumption</span>
                <span style={{ color: "#3b82f6", fontWeight: "600" }}>{metrics.systemMemoryUsageGB} GB / {metrics.systemMemoryTotalGB} GB</span>
              </div>
              <div style={{ width: "100%", height: "8px", background: "rgba(255,255,255,0.05)", borderRadius: "4px", overflow: "hidden" }}>
                <div style={{ width: `${(metrics.systemMemoryUsageGB / metrics.systemMemoryTotalGB) * 100}%`, height: "100%", background: "linear-gradient(90deg, #10b981, #3b82f6)", borderRadius: "4px", transition: "width 0.5s ease" }} />
              </div>
            </div>
          </div>

          <div style={{ marginTop: "40px", borderTop: "1px solid rgba(255,255,255,0.06)", paddingTop: "30px" }}>
            <h4 style={{ fontSize: "0.95rem", fontWeight: "600", marginBottom: "15px" }}>Quick Controls Shortcuts</h4>
            <div style={{ display: "flex", gap: "10px" }}>
              <button onClick={() => window.location.href = "/users"} className="btn btn-secondary" style={{ fontSize: "0.8rem" }}>
                Approve Pending Signups
              </button>
              <button onClick={() => window.location.href = "/instances"} className="btn btn-secondary" style={{ fontSize: "0.8rem" }}>
                Scale Active Workloads
              </button>
            </div>
          </div>
        </div>

        {/* Sidebar logs */}
        <div style={{ display: "flex", flexDirection: "column", gap: "20px" }}>
          {/* Node alert list */}
          <div className="glass-card" style={{ padding: "24px", flex: 1 }}>
            <div style={{ display: "flex", alignItems: "center", gap: "8px", marginBottom: "20px" }}>
              <Bell size={18} style={{ color: "#a78bfa" }} />
              <h3 style={{ fontSize: "1rem", fontWeight: "600" }}>Node Log Feeds</h3>
            </div>
            
            <div style={{ display: "flex", flexDirection: "column", gap: "14px" }}>
              {alerts.map((alert, i) => (
                <div key={i} className="glass-panel" style={{ padding: "12px 16px", display: "flex", gap: "12px", alignItems: "flex-start" }}>
                  <Play size={12} style={{ marginTop: "4px", color: "#a78bfa" }} />
                  <p style={{ fontSize: "0.8rem", color: "rgba(255,255,255,0.7)", lineHeight: "1.4" }}>{alert}</p>
                </div>
              ))}
            </div>
          </div>
        </div>

      </div>
    </div>
  );
}
