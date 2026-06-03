"use client";

import React, { useState, useEffect } from "react";
import { Play, Square, RotateCw, BarChart2, FileText, ChevronRight, Settings } from "lucide-react";
import ApiClient from "../../lib/api";

interface InstanceRecord {
  id: string;
  name: string;
  domain: string;
  containerId: string | null;
  status: "PENDING" | "CREATING" | "RUNNING" | "STOPPED" | "ERROR";
  cpuLimit: number;
  memoryLimit: string;
  storageLimit: string;
  apps: string[];
  cpuUsage: number | null;
  memoryUsage: number | null;
  diskUsage: string | null;
  lastBackup: string | null;
  user?: {
    email: string;
    name: string | null;
  };
}

export default function InstancesAdmin() {
  const [instances, setInstances] = useState<InstanceRecord[]>([]);
  const [loading, setLoading] = useState(true);
  const [selectedInstanceLogs, setSelectedInstanceLogs] = useState<{ id: string; name: string; logs: string } | null>(null);
  const [scalingInstance, setScalingInstance] = useState<InstanceRecord | null>(null);
  const [newCpu, setNewCpu] = useState<number>(0.5);
  const [newMemory, setNewMemory] = useState<string>("512m");
  const [actionLoading, setActionLoading] = useState<string | null>(null);

  const loadInstances = async () => {
    try {
      const data = await ApiClient.get<InstanceRecord[]>("/instances");
      setInstances(data);
    } catch (err) {
      console.warn("API `/instances` unreachable, loading high-fidelity mock workloads.");
      setInstances([
        {
          id: "inst_1",
          name: "priya",
          domain: "priya.neuravolt.cloud",
          containerId: "nv_container_ae98",
          status: "RUNNING",
          cpuLimit: 1.0,
          memoryLimit: "1024m",
          storageLimit: "25GB",
          apps: ["n8n", "openwebui"],
          cpuUsage: 14.5,
          memoryUsage: 256.0,
          diskUsage: "1.2 GB",
          lastBackup: new Date().toISOString(),
          user: { email: "priya@neuravolt.in", name: "Priya Patel" }
        },
        {
          id: "inst_2",
          name: "amit",
          domain: "amit.neuravolt.cloud",
          containerId: "nv_container_cd52",
          status: "STOPPED",
          cpuLimit: 2.0,
          memoryLimit: "2048m",
          storageLimit: "50GB",
          apps: ["n8n"],
          cpuUsage: 0,
          memoryUsage: 0,
          diskUsage: "4.8 GB",
          lastBackup: new Date(Date.now() - 24 * 60 * 60 * 1000).toISOString(),
          user: { email: "amit@enterprise.com", name: "Amit Kumar" }
        }
      ]);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    loadInstances();
    const timer = setInterval(loadInstances, 6000);
    return () => clearInterval(timer);
  }, []);

  const handleStart = async (id: string) => {
    setActionLoading(`start_${id}`);
    try {
      await ApiClient.post(`/instances/${id}/start`);
      await loadInstances();
    } catch (err: any) {
      alert(err.message || "Failed to start container");
    } finally {
      setActionLoading(null);
    }
  };

  const handleStop = async (id: string) => {
    setActionLoading(`stop_${id}`);
    try {
      await ApiClient.post(`/instances/${id}/stop`);
      await loadInstances();
    } catch (err: any) {
      alert(err.message || "Failed to halt container");
    } finally {
      setActionLoading(null);
    }
  };

  const handleRestart = async (id: string) => {
    setActionLoading(`restart_${id}`);
    try {
      await ApiClient.post(`/instances/${id}/restart`);
      await loadInstances();
    } catch (err: any) {
      alert(err.message || "Failed to reboot container");
    } finally {
      setActionLoading(null);
    }
  };

  const showLogs = async (inst: InstanceRecord) => {
    if (!inst.containerId) return;
    try {
      const res = await ApiClient.get<{ logs: string }>(`/monitoring/logs/${inst.containerId}`);
      setSelectedInstanceLogs({ id: inst.id, name: inst.name, logs: res.logs });
    } catch {
      // Mock Logs
      setSelectedInstanceLogs({ 
        id: inst.id, 
        name: inst.name, 
        logs: `[Mock Logs ${new Date().toISOString()}] System engine up.\n[Mock Logs] Local routing registered for subdomain: ${inst.domain}\n[Mock Logs] Traefik loaded configurations: TLS Challenge Enabled\n[Mock Logs] Heartbeat pulse check: 200 OK` 
      });
    }
  };

  const openScaleModal = (inst: InstanceRecord) => {
    setScalingInstance(inst);
    setNewCpu(inst.cpuLimit);
    setNewMemory(inst.memoryLimit);
  };

  const handleScaleSave = async () => {
    if (!scalingInstance) return;
    setActionLoading("scale");
    try {
      await ApiClient.patch(`/instances/${scalingInstance.id}/scale`, {
        cpuLimit: newCpu,
        memoryLimit: newMemory,
      });
      setScalingInstance(null);
      await loadInstances();
    } catch (err: any) {
      alert(err.message || "Scaling request failed");
    } finally {
      setActionLoading(null);
    }
  };

  return (
    <div>
      {/* Header */}
      <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", marginBottom: "30px" }}>
        <div>
          <h1 style={{ fontSize: "1.75rem", fontWeight: "700" }}>Docker Compute Containers</h1>
          <p style={{ color: "rgba(255,255,255,0.5)", fontSize: "0.9rem" }}>Control telemetry logs and allocate raw CPU/RAM limits</p>
        </div>
      </div>

      {/* Instance Table */}
      <div className="glass-card" style={{ padding: "10px", overflow: "hidden" }}>
        {loading ? (
          <div style={{ display: "flex", justifyContent: "center", alignItems: "center", padding: "60px", color: "rgba(255,255,255,0.4)" }}>
            Loading container assets...
          </div>
        ) : (
          <div className="table-container">
            <table className="custom-table">
              <thead>
                <tr>
                  <th>Instance Domain</th>
                  <th>Ownership</th>
                  <th>Allocated Limits</th>
                  <th>Usage telemetry</th>
                  <th>Workload Status</th>
                  <th>Control Action</th>
                </tr>
              </thead>
              <tbody>
                {instances.map((inst) => (
                  <tr key={inst.id}>
                    <td>
                      <div>
                        <div style={{ fontWeight: "600", fontSize: "0.95rem", display: "flex", alignItems: "center", gap: "6px" }}>
                          <span>{inst.name}</span>
                          <span style={{ fontSize: "0.75rem", color: "rgba(255,255,255,0.4)" }}>({inst.apps.join(", ")})</span>
                        </div>
                        <a href={`https://${inst.domain}`} target="_blank" style={{ fontSize: "0.8rem", color: "#a78bfa", textDecoration: "none" }}>
                          https://{inst.domain}
                        </a>
                      </div>
                    </td>
                    <td>
                      <div style={{ fontSize: "0.85rem" }}>
                        <div>{inst.user?.name || "Manual Provision"}</div>
                        <div style={{ fontSize: "0.75rem", color: "rgba(255,255,255,0.4)" }}>{inst.user?.email || ""}</div>
                      </div>
                    </td>
                    <td>
                      <div style={{ fontSize: "0.8rem", color: "rgba(255,255,255,0.7)" }}>
                        <div>CPU: <strong>{inst.cpuLimit} Cores</strong></div>
                        <div>RAM: <strong>{inst.memoryLimit}</strong></div>
                      </div>
                    </td>
                    <td>
                      <div style={{ fontSize: "0.8rem" }}>
                        <div>CPU: <strong style={{ color: "#3b82f6" }}>{inst.cpuUsage ?? 0}%</strong></div>
                        <div>RAM: <strong style={{ color: "#10b981" }}>{inst.memoryUsage ?? 0} MB</strong></div>
                      </div>
                    </td>
                    <td>
                      <span className={`badge badge-${inst.status.toLowerCase()}`}>{inst.status}</span>
                    </td>
                    <td>
                      <div style={{ display: "flex", gap: "6px" }}>
                        {inst.status !== "RUNNING" ? (
                          <button 
                            onClick={() => handleStart(inst.id)}
                            disabled={actionLoading !== null}
                            className="btn btn-secondary" 
                            style={{ padding: "6px 8px", color: "#10b981" }}
                            title="Boot container"
                          >
                            <Play size={14} />
                          </button>
                        ) : (
                          <button 
                            onClick={() => handleStop(inst.id)}
                            disabled={actionLoading !== null}
                            className="btn btn-secondary" 
                            style={{ padding: "6px 8px", color: "#ef4444" }}
                            title="Stop container"
                          >
                            <Square size={14} />
                          </button>
                        )}
                        <button 
                          onClick={() => handleRestart(inst.id)}
                          disabled={actionLoading !== null}
                          className="btn btn-secondary" 
                          style={{ padding: "6px 8px" }}
                          title="Reboot"
                        >
                          <RotateCw size={14} />
                        </button>
                        <button 
                          onClick={() => showLogs(inst)}
                          className="btn btn-secondary" 
                          style={{ padding: "6px 8px" }}
                          title="Get Live Logs"
                        >
                          <FileText size={14} />
                        </button>
                        <button 
                          onClick={() => openScaleModal(inst)}
                          className="btn btn-secondary" 
                          style={{ padding: "6px 8px", color: "#a78bfa" }}
                          title="Adjust limits"
                        >
                          <Settings size={14} />
                        </button>
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>

      {/* Logs pop up pane */}
      {selectedInstanceLogs && (
        <div style={{ position: "fixed", inset: 0, background: "rgba(0,0,0,0.8)", display: "flex", alignItems: "center", justifyContent: "center", zIndex: 200, padding: "20px" }}>
          <div className="glass-card" style={{ width: "100%", maxWidth: "800px", display: "flex", flexDirection: "column", maxHeight: "80vh" }}>
            <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", padding: "20px 24px", borderBottom: "1px solid rgba(255,255,255,0.06)" }}>
              <h3 style={{ fontSize: "1.1rem", fontWeight: "600" }}>Live Logs: {selectedInstanceLogs.name}</h3>
              <button onClick={() => setSelectedInstanceLogs(null)} className="btn btn-secondary" style={{ padding: "6px 12px" }}>Close</button>
            </div>
            <pre style={{ flex: 1, overflowY: "auto", background: "#060608", color: "#10b981", padding: "20px", fontFamily: "monospace", fontSize: "0.85rem", lineHeight: "1.5", whiteSpace: "pre-wrap" }}>
              {selectedInstanceLogs.logs}
            </pre>
          </div>
        </div>
      )}

      {/* Scale edit modal */}
      {scalingInstance && (
        <div style={{ position: "fixed", inset: 0, background: "rgba(0,0,0,0.7)", display: "flex", alignItems: "center", justifyContent: "center", zIndex: 200, padding: "20px" }}>
          <div className="glass-card" style={{ width: "100%", maxWidth: "420px", padding: "30px" }}>
            <h3 style={{ fontSize: "1.15rem", fontWeight: "600", marginBottom: "8px" }}>Resource Scaling</h3>
            <p style={{ fontSize: "0.85rem", color: "rgba(255,255,255,0.5)", marginBottom: "25px" }}>Scale limits dynamically for {scalingInstance.name}</p>
            
            <div style={{ display: "flex", flexDirection: "column", gap: "20px" }}>
              <div>
                <label style={{ display: "block", fontSize: "0.8rem", color: "rgba(255,255,255,0.6)", marginBottom: "8px", fontWeight: "500" }}>CPU cores limits (Scale step: 0.1 cores)</label>
                <input 
                  type="number" 
                  step="0.1"
                  min="0.1"
                  max="4.0"
                  className="input-field" 
                  value={newCpu}
                  onChange={(e) => setNewCpu(parseFloat(e.target.value))}
                />
              </div>

              <div>
                <label style={{ display: "block", fontSize: "0.8rem", color: "rgba(255,255,255,0.6)", marginBottom: "8px", fontWeight: "500" }}>Memory Limit (e.g. 512m, 1g)</label>
                <input 
                  type="text" 
                  className="input-field" 
                  value={newMemory}
                  onChange={(e) => setNewMemory(e.target.value)}
                />
              </div>

              <div style={{ display: "flex", justifyContent: "flex-end", gap: "10px", marginTop: "15px" }}>
                <button onClick={() => setScalingInstance(null)} className="btn btn-secondary">Cancel</button>
                <button onClick={handleScaleSave} className="btn btn-primary" disabled={actionLoading === "scale"}>
                  {actionLoading === "scale" ? "Re-scaling..." : "Apply Scale limits"}
                </button>
              </div>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
