"use client";

import React, { useState, useEffect } from "react";
import { UserCheck, ShieldAlert, Sparkles, Filter, RefreshCw, Trash2 } from "lucide-react";
import ApiClient from "../../lib/api";

interface UserInstance {
  id: string;
  name: string;
  domain: string;
  status: string;
}

interface UserInvoice {
  id: string;
  amount: string;
  status: string;
}

interface UserRecord {
  id: string;
  email: string;
  name: string | null;
  company: string | null;
  status: "PENDING" | "ACTIVE" | "SUSPENDED" | "DELETED";
  role: "USER" | "ADMIN";
  plan: "LITE" | "BASIC" | "PRO" | "HEAVY";
  createdAt: string;
  instances: UserInstance[];
  invoices: UserInvoice[];
}

export default function UsersAdmin() {
  const [users, setUsers] = useState<UserRecord[]>([]);
  const [filterStatus, setFilterStatus] = useState<string>("ALL");
  const [loading, setLoading] = useState<boolean>(true);
  const [actionLoading, setActionLoading] = useState<string | null>(null);

  const loadUsers = async () => {
    setLoading(true);
    try {
      const data = await ApiClient.get<UserRecord[]>("/users");
      setUsers(data);
    } catch (err) {
      console.warn("API `/users` not online, loading mock clients roster.");
      // Fallback mocks
      setUsers([
        {
          id: "usr_1",
          email: "rahul@agency.in",
          name: "Rahul Sharma",
          company: "Sharma Marketing Agency",
          status: "PENDING",
          role: "USER",
          plan: "BASIC",
          createdAt: new Date().toISOString(),
          instances: [],
          invoices: [],
        },
        {
          id: "usr_2",
          email: "priya@neuravolt.in",
          name: "Priya Patel",
          company: "Priya Tech Labs",
          status: "ACTIVE",
          role: "USER",
          plan: "PRO",
          createdAt: new Date(Date.now() - 3 * 24 * 60 * 60 * 1000).toISOString(),
          instances: [{ id: "inst_1", name: "priya", domain: "priya.neuravolt.cloud", status: "RUNNING" }],
          invoices: [{ id: "inv_1", amount: "1999.00", status: "PAID" }],
        },
        {
          id: "usr_3",
          email: "amit@enterprise.com",
          name: "Amit Kumar",
          company: "Kumar & Sons",
          status: "SUSPENDED",
          role: "USER",
          plan: "HEAVY",
          createdAt: new Date(Date.now() - 10 * 24 * 60 * 60 * 1000).toISOString(),
          instances: [{ id: "inst_2", name: "amit", domain: "amit.neuravolt.cloud", status: "STOPPED" }],
          invoices: [{ id: "inv_2", amount: "3999.00", status: "PENDING" }],
        }
      ]);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    loadUsers();
  }, []);

  const handleApprove = async (id: string) => {
    setActionLoading(id);
    try {
      await ApiClient.patch(`/users/${id}/approve`);
      await loadUsers();
    } catch (err: any) {
      alert(err.message || "Failed to approve user");
    } finally {
      setActionLoading(null);
    }
  };

  const handleSuspend = async (id: string) => {
    setActionLoading(id);
    try {
      await ApiClient.patch(`/users/${id}/suspend`);
      await loadUsers();
    } catch (err: any) {
      alert(err.message || "Failed to suspend user");
    } finally {
      setActionLoading(null);
    }
  };

  const handleUnsuspend = async (id: string) => {
    setActionLoading(id);
    try {
      await ApiClient.patch(`/users/${id}/unsuspend`);
      await loadUsers();
    } catch (err: any) {
      alert(err.message || "Failed to resume user");
    } finally {
      setActionLoading(null);
    }
  };

  const handleDelete = async (id: string) => {
    if (!confirm("Are you sure you want to delete this user? This will permanently delete their database records and destroy all their Docker containers/volumes on the VPS.")) {
      return;
    }
    setActionLoading(id);
    try {
      await ApiClient.delete(`/users/${id}`);
      await loadUsers();
    } catch (err: any) {
      alert(err.message || "Failed to delete user");
    } finally {
      setActionLoading(null);
    }
  };

  const filteredUsers = users.filter((u) => {
    if (filterStatus === "ALL") return true;
    return u.status === filterStatus;
  });

  return (
    <div>
      {/* Header */}
      <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", marginBottom: "30px" }}>
        <div>
          <h1 style={{ fontSize: "1.75rem", fontWeight: "700" }}>Registrations Administration</h1>
          <p style={{ color: "rgba(255,255,255,0.5)", fontSize: "0.9rem" }}>Approve client containers and scale subscriptions</p>
        </div>
        <button onClick={loadUsers} className="btn btn-secondary" style={{ padding: "10px" }}>
          <RefreshCw size={16} />
        </button>
      </div>

      {/* Filter panel */}
      <div className="glass-panel" style={{ padding: "16px 20px", display: "flex", gap: "12px", alignItems: "center", marginBottom: "25px" }}>
        <Filter size={16} style={{ color: "rgba(255,255,255,0.4)" }} />
        <span style={{ fontSize: "0.85rem", color: "rgba(255,255,255,0.6)", fontWeight: "500" }}>Filter By Status:</span>
        <div style={{ display: "flex", gap: "6px" }}>
          {["ALL", "PENDING", "ACTIVE", "SUSPENDED"].map((st) => (
            <button 
              key={st}
              onClick={() => setFilterStatus(st)}
              className="btn btn-secondary" 
              style={{ fontSize: "0.75rem", padding: "6px 12px", background: filterStatus === st ? "rgba(139, 92, 246, 0.2)" : "transparent", borderColor: filterStatus === st ? "#8b5cf6" : "rgba(255,255,255,0.05)", color: filterStatus === st ? "#a78bfa" : "rgba(255,255,255,0.7)" }}
            >
              {st}
            </button>
          ))}
        </div>
      </div>

      {/* Main Table view */}
      <div className="glass-card" style={{ padding: "10px", overflow: "hidden" }}>
        {loading ? (
          <div style={{ display: "flex", justifyContent: "center", alignItems: "center", padding: "60px", color: "rgba(255,255,255,0.4)", gap: "10px" }}>
            <RefreshCw size={20} className="animate-spin" />
            <span>Fetching user rosters...</span>
          </div>
        ) : filteredUsers.length === 0 ? (
          <div style={{ padding: "50px 30px", textAlign: "center", color: "rgba(255,255,255,0.4)" }}>
            No registered users match this status criteria.
          </div>
        ) : (
          <div className="table-container">
            <table className="custom-table">
              <thead>
                <tr>
                  <th>Client Information</th>
                  <th>Core Plan</th>
                  <th>Provisioned Apps</th>
                  <th>Current State</th>
                  <th>Control Integrations</th>
                </tr>
              </thead>
              <tbody>
                {filteredUsers.map((user) => (
                  <tr key={user.id}>
                    <td>
                      <div>
                        <div style={{ fontWeight: "600", fontSize: "0.95rem" }}>{user.name || "N/A"}</div>
                        <div style={{ fontSize: "0.8rem", color: "rgba(255,255,255,0.4)" }}>{user.email}</div>
                        {user.company && <div style={{ fontSize: "0.75rem", color: "#a78bfa", marginTop: "2px" }}>{user.company}</div>}
                      </div>
                    </td>
                    <td>
                      <div style={{ fontWeight: "600", fontSize: "0.85rem", color: "#8b5cf6" }}>{user.plan}</div>
                    </td>
                    <td>
                      <div style={{ fontSize: "0.8rem" }}>
                        {user.instances.length > 0 ? (
                          <span style={{ color: "rgba(255,255,255,0.85)" }}>
                            {user.instances[0].domain} (n8n, OpenWebUI)
                          </span>
                        ) : (
                          <span style={{ color: "rgba(255,255,255,0.3)" }}>No containers yet</span>
                        )}
                      </div>
                    </td>
                    <td>
                      <span className={`badge badge-${user.status.toLowerCase()}`}>
                        {user.status}
                      </span>
                    </td>
                    <td>
                      <div style={{ display: "flex", gap: "8px" }}>
                        {user.status === "PENDING" && (
                          <button 
                            onClick={() => handleApprove(user.id)}
                            disabled={actionLoading === user.id}
                            className="btn btn-primary" 
                            style={{ fontSize: "0.75rem", padding: "6px 12px" }}
                          >
                            <UserCheck size={14} />
                            <span>Approve & Launch</span>
                          </button>
                        )}
                        {user.status === "ACTIVE" && (
                          <button 
                            onClick={() => handleSuspend(user.id)}
                            disabled={actionLoading === user.id}
                            className="btn btn-danger" 
                            style={{ fontSize: "0.75rem", padding: "6px 12px" }}
                          >
                            <ShieldAlert size={14} />
                            <span>Suspend Workload</span>
                          </button>
                        )}
                        {user.status === "SUSPENDED" && (
                          <button 
                            onClick={() => handleUnsuspend(user.id)}
                            disabled={actionLoading === user.id}
                            className="btn btn-primary" 
                            style={{ fontSize: "0.75rem", padding: "6px 12px", background: "rgba(16, 185, 129, 0.15)", color: "#10b981", border: "1px solid rgba(16, 185, 129, 0.2)" }}
                          >
                            <Sparkles size={14} />
                            <span>Resume Services</span>
                          </button>
                        )}
                        <button 
                          onClick={() => handleDelete(user.id)}
                          disabled={actionLoading === user.id}
                          className="btn btn-danger" 
                          style={{ fontSize: "0.75rem", padding: "6px 12px", background: "rgba(239, 68, 68, 0.15)", color: "#ef4444", border: "1px solid rgba(239, 68, 68, 0.2)" }}
                        >
                          <Trash2 size={14} />
                          <span>Delete User</span>
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
    </div>
  );
}
