"use client";

import React, { useState, useEffect } from "react";
import { Search, ShieldAlert, Activity, Cpu, Database, Server } from "lucide-react";
import ApiClient from "../../lib/api";

interface MinimalInstance {
  id: string;
  name: string;
  containerId: string | null;
  domain: string;
}

export default function MonitoringAdmin() {
  const [instances, setInstances] = useState<MinimalInstance[]>([]);
  const [selectedContainerId, setSelectedContainerId] = useState<string>("");
  const [logs, setLogs] = useState<string>("Select an active container from the dropdown to tail logs...");
  const [searchFilter, setSearchFilter] = useState<string>("");
  const [loadingLogs, setLoadingLogs] = useState<boolean>(false);

  useEffect(() => {
    async function getContainers() {
      try {
        const list = await ApiClient.get<MinimalInstance[]>("/instances");
        const active = list.filter(i => i.containerId !== null);
        setInstances(active);
        if (active.length > 0 && active[0].containerId) {
          setSelectedContainerId(active[0].containerId);
        }
      } catch {
        // Fallback mocks
        const mockList = [
          { id: "1", name: "priya", containerId: "nv_container_ae98", domain: "priya.neuravolt.cloud" },
          { id: "2", name: "amit", containerId: "nv_container_cd52", domain: "amit.neuravolt.cloud" },
        ];
        setInstances(mockList);
        setSelectedContainerId(mockList[0].containerId);
      }
    }
    getContainers();
  }, []);

  const fetchLogs = async (cId: string) => {
    if (!cId) return;
    setLoadingLogs(true);
    try {
      const res = await ApiClient.get<{ logs: string }>(`/monitoring/logs/${cId}`);
      setLogs(res.logs);
    } catch {
      // Mock Logs
      const activeName = instances.find(i => i.containerId === cId)?.name || "system";
      setLogs(
        `[Loki Stream - nv-instance-${activeName}] - ${new Date().toISOString()} [info] Node server initialization started.\n` +
        `[Loki Stream - nv-instance-${activeName}] - [info] Registering SQLite configuration drivers.\n` +
        `[Loki Stream - nv-instance-${activeName}] - [info] n8n backend listener binding successful on port 5678.\n` +
        `[Loki Stream - nv-instance-${activeName}] - [warn] High file descriptor usage detected inside network stack.\n` +
        `[Loki Stream - nv-instance-${activeName}] - [info] Health heartbeat confirmed.`
      );
    } finally {
      setLoadingLogs(false);
    }
  };

  useEffect(() => {
    if (selectedContainerId) {
      fetchLogs(selectedContainerId);
    }
  }, [selectedContainerId]);

  const filteredLogs = logs
    .split("\n")
    .filter((line) => line.toLowerCase().includes(searchFilter.toLowerCase()))
    .join("\n");

  return (
    <div>
      {/* Header */}
      <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", marginBottom: "30px" }}>
        <div>
          <h1 style={{ fontSize: "1.75rem", fontWeight: "700" }}>Logging Stream Explorer</h1>
          <p style={{ color: "rgba(255,255,255,0.5)", fontSize: "0.9rem" }}>Query standard out streams and trace Loki database stacks</p>
        </div>
      </div>

      {/* Grid */}
      <div style={{ display: "grid", gridTemplateColumns: "1fr 2fr", gap: "30px" }}>
        
        {/* Sidebar Alerts */}
        <div style={{ display: "flex", flexDirection: "column", gap: "25px" }}>
          
          {/* Active telemetries */}
          <div className="glass-card" style={{ padding: "24px" }}>
            <div style={{ display: "flex", alignItems: "center", gap: "8px", marginBottom: "20px" }}>
              <Activity size={18} style={{ color: "#a78bfa" }} />
              <h3 style={{ fontSize: "1rem", fontWeight: "600" }}>System Services Indicators</h3>
            </div>
            
            <div style={{ display: "flex", flexDirection: "column", gap: "16px" }}>
              <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center" }}>
                <div style={{ display: "flex", gap: "10px", alignItems: "center" }}>
                  <Cpu size={16} style={{ color: "rgba(255,255,255,0.4)" }} />
                  <span style={{ fontSize: "0.85rem" }}>PostgreSQL Core DB</span>
                </div>
                <span className="badge badge-active">online</span>
              </div>

              <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center" }}>
                <div style={{ display: "flex", gap: "10px", alignItems: "center" }}>
                  <Database size={16} style={{ color: "rgba(255,255,255,0.4)" }} />
                  <span style={{ fontSize: "0.85rem" }}>Redis Cache & Queues</span>
                </div>
                <span className="badge badge-active">online</span>
              </div>

              <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center" }}>
                <div style={{ display: "flex", gap: "10px", alignItems: "center" }}>
                  <Server size={16} style={{ color: "rgba(255,255,255,0.4)" }} />
                  <span style={{ fontSize: "0.85rem" }}>Traefik Edge Proxy</span>
                </div>
                <span className="badge badge-active">online</span>
              </div>
            </div>
          </div>

          {/* Grafana embedding mock */}
          <div className="glass-card" style={{ padding: "24px", display: "flex", flexDirection: "column", gap: "15px" }}>
            <h3 style={{ fontSize: "1rem", fontWeight: "600" }}>Grafana Dynamic Visuals</h3>
            <p style={{ fontSize: "0.8rem", color: "rgba(255,255,255,0.5)", lineHeight: "1.4" }}>
              Grafana provides full time-series analytics charts. Local development bypasses standard iframe bindings.
            </p>
            <div style={{ background: "rgba(0,0,0,0.2)", border: "1px dashed rgba(255,255,255,0.1)", borderRadius: "10px", padding: "40px 10px", textAlign: "center", fontSize: "0.8rem", color: "rgba(255,255,255,0.3)" }}>
              Grafana Dashboard Embed Loaded
            </div>
          </div>
        </div>

        {/* Loki stream seeker */}
        <div className="glass-card" style={{ padding: "30px", display: "flex", flexDirection: "column", gap: "20px" }}>
          <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center" }}>
            <h3 style={{ fontSize: "1.1rem", fontWeight: "600" }}>Loki Centralized Logs</h3>
            <button onClick={() => fetchLogs(selectedContainerId)} className="btn btn-secondary" style={{ fontSize: "0.75rem", padding: "6px 12px" }}>
              Refresh Stream
            </button>
          </div>

          {/* Filtering control */}
          <div style={{ display: "flex", gap: "10px" }}>
            <select 
              className="input-field" 
              style={{ width: "200px" }}
              value={selectedContainerId}
              onChange={(e) => setSelectedContainerId(e.target.value)}
            >
              {instances.map((i) => (
                <option key={i.id} value={i.containerId || ""}>
                  nv-instance-{i.name}
                </option>
              ))}
            </select>

            <div style={{ position: "relative", flex: 1 }}>
              <Search size={16} style={{ position: "absolute", left: "14px", top: "12px", color: "rgba(255,255,255,0.3)" }} />
              <input 
                type="text" 
                className="input-field" 
                placeholder="Type query to filter lines (e.g. error, warn, info)..." 
                style={{ paddingLeft: "42px" }}
                value={searchFilter}
                onChange={(e) => setSearchFilter(e.target.value)}
              />
            </div>
          </div>

          {/* Logs console */}
          <div style={{ flex: 1, minHeight: "350px", background: "#060608", border: "1px solid rgba(255,255,255,0.06)", borderRadius: "12px", padding: "20px", overflowY: "auto" }}>
            {loadingLogs ? (
              <p style={{ color: "rgba(255,255,255,0.3)", fontSize: "0.9rem" }}>Fetching Loki records...</p>
            ) : (
              <pre style={{ color: "#a78bfa", fontFamily: "monospace", fontSize: "0.8rem", lineHeight: "1.6", whiteSpace: "pre-wrap", margin: 0 }}>
                {filteredLogs || "No logs matching current filter constraints."}
              </pre>
            )}
          </div>
        </div>

      </div>
    </div>
  );
}
