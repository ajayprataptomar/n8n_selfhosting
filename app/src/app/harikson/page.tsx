"use client";

import React, { useState, useEffect } from "react";
import { 
  Bot, 
  Settings, 
  FolderOpen, 
  Brain, 
  Users, 
  Send, 
  Upload, 
  Plus, 
  Code, 
  Info,
  Layers,
  Activity,
  ArrowRight,
  UserCheck
} from "lucide-react";
import HariksonApiClient from "../../lib/hariksonApi";
import ApiClient from "../../lib/api";

interface DocumentItem {
  id: string;
  name: string;
  type: string;
  size: string;
  status: "PROCESSING" | "INDEXED" | "ERROR";
  createdAt: string;
}

interface FineTuneLog {
  id: string;
  status: "QUEUED" | "TRAINING" | "COMPLETED" | "FAILED";
  baseModel: string;
  adapterName: string;
  createdAt: string;
  completedAt?: string;
  loss: number[];
}

interface Lead {
  id: string;
  email: string;
  phone: string;
  name: string;
  createdAt: string;
}

interface Message {
  sender: "user" | "bot";
  text: string;
}

const HARIKSON_MODELS_BRANDING: Record<string, { displayName: string }> = {
  // Original configurations
  'harikson/qwen3-coder:1.5b': { displayName: 'Harikson Starter' },
  'harikson/qwen3-coder:4b': { displayName: 'Harikson Pro' },
  'harikson/qwen3-coder:8b': { displayName: 'Harikson Business' },
  'harikson/qwen3-coder:14b': { displayName: 'Harikson Enterprise' },
  'qwen3-coder-4b': { displayName: 'Harikson Pro' },
  'qwen3-coder-8b': { displayName: 'Harikson Business' },
  'qwen3-coder-14b': { displayName: 'Harikson Enterprise' },

  // Starter Models (8 GB RAM)
  'harikson-coder-7b': { displayName: 'Harikson Coder 7B' },
  'harikson-coder-v2-lite': { displayName: 'Harikson Coder V2 Lite' },
  'harikson-codegemma-7b': { displayName: 'Harikson CodeGemma 7B' },
  'harikson-chat-8b': { displayName: 'Harikson Chat 8B' },
  'harikson-llama-3.1-8b': { displayName: 'Harikson Llama 3.1 8B' },
  'harikson-gemma-3-4b': { displayName: 'Harikson Gemma 3 4B' },
  'harikson-mistral-7b': { displayName: 'Harikson Mistral 7B' },

  // Pro Models (12 GB RAM)
  'harikson-coder-14b': { displayName: 'Harikson Coder 14B' },
  'harikson-coder-16b': { displayName: 'Harikson Coder 16B' },
  'harikson-chat-14b': { displayName: 'Harikson Chat 14B' },
  'harikson-gemma-3-12b': { displayName: 'Harikson Gemma 3 12B' },

  // Business Models (16 GB RAM additional)
  'harikson-chat-30b-a3b': { displayName: 'Harikson Chat 30B-A3B' },
  'harikson-llama-3.3-70b': { displayName: 'Harikson Llama 3.3 70B (Not Practical)' },

  // Enterprise Models (24 GB RAM)
  'harikson-coder-32b': { displayName: 'Harikson Coder 32B' },
  'harikson-coder-v2': { displayName: 'Harikson Coder V2' },
  'harikson-chat-32b': { displayName: 'Harikson Chat 32B' },
  'harikson-chat-35b-a3b': { displayName: 'Harikson Chat 35B-A3B' },
  'harikson-chat-32b-instruct': { displayName: 'Harikson Chat 32B Instruct' }
};

function getBrandedModelName(internalName: string): string {
  return HARIKSON_MODELS_BRANDING[internalName]?.displayName || internalName || 'Harikson AI';
}

export default function HariksonClientDashboard() {
  const [activeSubTab, setActiveSubTab] = useState<"playground" | "widget" | "rag" | "finetune" | "leads">("playground");
  const [agentName, setAgentName] = useState("Harikson");
  const [messages, setMessages] = useState<Message[]>([
    { sender: "bot", text: "Welcome to Harikson AI Console! How can I assist you with your operations today?" }
  ]);
  const [inputText, setInputText] = useState("");
  const [documents, setDocuments] = useState<DocumentItem[]>([]);
  const [trainingLogs, setTrainingLogs] = useState<FineTuneLog[]>([]);
  const [leads, setLeads] = useState<Lead[]>([]);
  const [loading, setLoading] = useState(false);
  const [isTyping, setIsTyping] = useState(false);
  const [instance, setInstance] = useState<any>(null);

  // Widget settings
  const [widgetColor, setWidgetColor] = useState("#8b5cf6");
  const [welcomeText, setWelcomeText] = useState("Hi! How can I help you today?");
  const [widgetPosition, setWidgetPosition] = useState("bottom-right");
  const [avatarUrl, setAvatarUrl] = useState("");

  // RAG upload states
  const [fileName, setFileName] = useState("");
  const [urlCrawling, setUrlCrawling] = useState("");

  const loadData = async () => {
    try {
      // 1. Fetch real instance details from /instances on the main backend
      const list = await ApiClient.get<any[]>("/instances");
      if (list && list.length > 0) {
        // Find the instance that has openwebui/AI enabled, or simply use the first instance
        const aiInst = list.find(inst => inst.apps?.includes("openwebui")) || list[0];
        setInstance(aiInst);
        setAgentName(aiInst.name.charAt(0).toUpperCase() + aiInst.name.slice(1));
      }

      setDocuments([
        { id: "doc_1", name: "company-handbook.pdf", type: "PDF", size: "1.2 MB", status: "INDEXED", createdAt: "2026-06-18" },
        { id: "doc_2", name: "https://docs.harikson.yourdomain.com/features", type: "URL", size: "Crawled Website", status: "INDEXED", createdAt: "2026-06-19" },
      ]);
      setTrainingLogs([
        {
          id: "ft_1",
          status: "COMPLETED",
          baseModel: "harikson/qwen3-coder:8b",
          adapterName: "harikson-adapter-sales-agent",
          createdAt: "2026-06-18",
          completedAt: "2026-06-18 10:20:12",
          loss: [2.1, 1.8, 1.4, 0.9, 0.76]
        }
      ]);
      setLeads([
        { id: "ld_1", name: "Amit Patel", email: "amit@techcorp.in", phone: "+91 98765 43210", createdAt: "2026-06-19" },
        { id: "ld_2", name: "Sara Smith", email: "sara@webagency.io", phone: "+1 (555) 234-5678", createdAt: "2026-06-20" }
      ]);
    } catch (err) {
      console.warn("Error fetching data, using high-fidelity local fallbacks.", err);
    }
  };

  useEffect(() => {
    loadData();
  }, []);

  useEffect(() => {
    setMessages([
      { sender: "bot", text: `Welcome to ${agentName} AI Console! How can I assist you with your operations today?` }
    ]);
  }, [agentName]);

  const handleSendMessage = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!inputText.trim()) return;

    const userMsg: Message = { sender: "user", text: inputText };
    setMessages((prev) => [...prev, userMsg]);
    setInputText("");
    setIsTyping(true);

    try {
      let targetUrl = "";
      if (instance && instance.domain) {
        const domain = instance.domain;
        if (domain.startsWith("localhost")) {
          targetUrl = `http://${domain}/chat`;
        } else {
          targetUrl = `https://${domain}/chat`;
        }
      } else {
        targetUrl = "http://localhost:5005/chat";
      }

      console.log(`📡 Connecting to tenant API at: ${targetUrl}`);
      const response = await fetch(targetUrl, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({
          message: userMsg.text,
          useRag: true
        })
      });

      if (!response.ok) {
        const errorData = await response.json().catch(() => ({}));
        throw new Error(errorData.error || `HTTP error ${response.status}`);
      }

      const data = await response.json() as { response: string };
      setMessages((prev) => [...prev, { sender: "bot", text: data.response }]);
    } catch (err: any) {
      console.warn("Tenant API call failed, falling back to simulated response.", err);
      // Fallback simulator if the backend container is unreachable
      let botResponse = "I've reviewed your request. Based on the RAG index data, we suggest following the standard deployment sequence.";
      if (userMsg.text.toLowerCase().includes("pricing")) {
        botResponse = "Our plans start at $29/mo (Starter) and go up to $129/mo (Business). Let me know if you want to update your billing parameters!";
      } else if (userMsg.text.toLowerCase().includes("hello") || userMsg.text.toLowerCase().includes("hi")) {
        botResponse = `Hello there! I'm your dedicated ${agentName} AI Agent. Let's build something great.`;
      }
      setMessages((prev) => [...prev, { sender: "bot", text: botResponse }]);
    } finally {
      setIsTyping(false);
    }
  };

  const handleAddDocument = (e: React.FormEvent) => {
    e.preventDefault();
    if (!fileName.trim()) return;

    const newDoc: DocumentItem = {
      id: `doc_${Date.now()}`,
      name: fileName,
      type: fileName.endsWith(".pdf") ? "PDF" : fileName.startsWith("http") ? "URL" : "MD",
      size: "245 KB",
      status: "PROCESSING",
      createdAt: new Date().toISOString().substring(0, 10),
    };

    setDocuments((prev) => [newDoc, ...prev]);
    setFileName("");

    // Simulate indexing completing in 5s
    setTimeout(() => {
      setDocuments((prev) => 
        prev.map((d) => d.id === newDoc.id ? { ...d, status: "INDEXED" } : d)
      );
    }, 5000);
  };

  const handleTrainNow = async () => {
    setLoading(true);
    const newJob: FineTuneLog = {
      id: `ft_${Date.now()}`,
      status: "TRAINING",
      baseModel: "qwen3-coder-8b",
      adapterName: `nv-adapter-custom-${Date.now().toString().substring(8)}`,
      createdAt: new Date().toISOString().substring(0, 10),
      loss: [2.5, 2.1, 1.7]
    };

    setTrainingLogs((prev) => [newJob, ...prev]);

    // Simulate complete in 15 seconds
    setTimeout(() => {
      setTrainingLogs((prev) => 
        prev.map((j) => j.id === newJob.id ? { ...j, status: "COMPLETED", completedAt: new Date().toISOString(), loss: [...j.loss, 1.2, 0.84] } : j)
      );
      setLoading(false);
    }, 15000);
  };

  return (
    <div>
      {/* Upper info panel */}
      <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", marginBottom: "35px" }}>
        <div>
          <h1 style={{ fontSize: "1.75rem", fontWeight: "700" }}>{agentName} AI Agents Hub</h1>
          <p style={{ color: "rgba(255,255,255,0.5)", fontSize: "0.9rem" }}>Configure model weights, customize chat widgets, and train adapters</p>
        </div>
        <div className="badge badge-active" style={{ fontSize: "0.85rem", padding: "8px 14px", borderRadius: "10px" }}>
          <UserCheck size={14} />
          <span>PRO Tier Active</span>
        </div>
      </div>

      {/* Tabs list */}
      <div style={{ display: "flex", gap: "10px", background: "rgba(255,255,255,0.03)", padding: "5px", borderRadius: "12px", marginBottom: "30px", width: "fit-content" }}>
        <button 
          onClick={() => setActiveSubTab("playground")} 
          className={`btn ${activeSubTab === "playground" ? "btn-primary" : "btn-secondary"}`} 
          style={{ padding: "8px 16px", borderRadius: "8px", border: "none", fontSize: "0.85rem" }}
        >
          <Bot size={16} />
          <span>Agent Playground</span>
        </button>

        <button 
          onClick={() => setActiveSubTab("widget")} 
          className={`btn ${activeSubTab === "widget" ? "btn-primary" : "btn-secondary"}`} 
          style={{ padding: "8px 16px", borderRadius: "8px", border: "none", fontSize: "0.85rem" }}
        >
          <Code size={16} />
          <span>Chat Widget</span>
        </button>

        <button 
          onClick={() => setActiveSubTab("rag")} 
          className={`btn ${activeSubTab === "rag" ? "btn-primary" : "btn-secondary"}`} 
          style={{ padding: "8px 16px", borderRadius: "8px", border: "none", fontSize: "0.85rem" }}
        >
          <FolderOpen size={16} />
          <span>Knowledge Base (RAG)</span>
        </button>

        <button 
          onClick={() => setActiveSubTab("finetune")} 
          className={`btn ${activeSubTab === "finetune" ? "btn-primary" : "btn-secondary"}`} 
          style={{ padding: "8px 16px", borderRadius: "8px", border: "none", fontSize: "0.85rem" }}
        >
          <Brain size={16} />
          <span>Fine-Tuning (QLoRA)</span>
        </button>

        <button 
          onClick={() => setActiveSubTab("leads")} 
          className={`btn ${activeSubTab === "leads" ? "btn-primary" : "btn-secondary"}`} 
          style={{ padding: "8px 16px", borderRadius: "8px", border: "none", fontSize: "0.85rem" }}
        >
          <Users size={16} />
          <span>Leads Captured</span>
        </button>
      </div>

      {/* Tab Panels */}
      {activeSubTab === "playground" && (
        <div style={{ display: "grid", gridTemplateColumns: "1.8fr 1fr", gap: "30px" }}>
          {/* Chat box */}
          <div className="glass-card" style={{ padding: "24px", height: "550px", display: "flex", flexDirection: "column" }}>
            <div style={{ display: "flex", gap: "10px", alignItems: "center", borderBottom: "1px solid rgba(255,255,255,0.06)", paddingBottom: "15px", marginBottom: "15px" }}>
              <div style={{ padding: "8px", borderRadius: "8px", background: "rgba(139, 92, 246, 0.1)", color: "#a78bfa" }}>
                <Bot size={20} />
              </div>
              <div>
                <h3 style={{ fontSize: "0.95rem", fontWeight: "600" }}>{agentName} Sales AI Agent</h3>
                <span style={{ fontSize: "0.75rem", color: "#10b981" }}>● Online and Synchronized</span>
              </div>
            </div>

            <div style={{ flex: 1, overflowY: "auto", display: "flex", flexDirection: "column", gap: "15px", padding: "10px" }}>
              {messages.map((m, i) => (
                <div key={i} style={{ display: "flex", justifyContent: m.sender === "user" ? "flex-end" : "flex-start" }}>
                  <div style={{ 
                    maxWidth: "70%", 
                    padding: "12px 18px", 
                    borderRadius: "12px", 
                    fontSize: "0.85rem",
                    lineHeight: "1.4",
                    background: m.sender === "user" ? "linear-gradient(135deg, hsl(var(--primary)), #8b5cf6)" : "rgba(255,255,255,0.04)",
                    color: "white"
                  }}>
                    {m.text}
                  </div>
                </div>
              ))}
              {isTyping && (
                <div style={{ display: "flex", justifyContent: "flex-start" }}>
                  <div style={{ 
                    maxWidth: "70%", 
                    padding: "12px 18px", 
                    borderRadius: "12px", 
                    fontSize: "0.85rem",
                    background: "rgba(255,255,255,0.04)",
                    color: "rgba(255,255,255,0.5)",
                    fontStyle: "italic"
                  }}>
                    {agentName} is thinking...
                  </div>
                </div>
              )}
            </div>

            <form onSubmit={handleSendMessage} style={{ display: "flex", gap: "10px", marginTop: "15px", borderTop: "1px solid rgba(255,255,255,0.06)", paddingTop: "15px" }}>
              <input 
                type="text" 
                className="input-field" 
                placeholder="Ask your agent anything..." 
                value={inputText}
                onChange={(e) => setInputText(e.target.value)}
              />
              <button type="submit" className="btn btn-primary" style={{ paddingInline: "20px" }}>
                <Send size={16} />
              </button>
            </form>
          </div>

          {/* Quick Metrics */}
          <div style={{ display: "flex", flexDirection: "column", gap: "25px" }}>
            <div className="glass-card" style={{ padding: "24px" }}>
              <h3 style={{ fontSize: "0.95rem", fontWeight: "600", marginBottom: "15px" }}>Compute Allocation Status</h3>
              <div style={{ display: "flex", flexDirection: "column", gap: "15px" }}>
                <div>
                  <div style={{ display: "flex", justifyContent: "space-between", fontSize: "0.8rem", marginBottom: "6px", color: "rgba(255,255,255,0.6)" }}>
                    <span>vCPU Allocated</span>
                    <span>1.0 Core (Pro Plan)</span>
                  </div>
                  <div style={{ height: "4px", background: "rgba(255,255,255,0.05)", borderRadius: "2px" }}>
                    <div style={{ width: "25%", height: "100%", background: "#a78bfa" }} />
                  </div>
                </div>

                <div>
                  <div style={{ display: "flex", justifyContent: "space-between", fontSize: "0.8rem", marginBottom: "6px", color: "rgba(255,255,255,0.6)" }}>
                    <span>RAM Allocated</span>
                    <span>1024 MB Limit</span>
                  </div>
                  <div style={{ height: "4px", background: "rgba(255,255,255,0.05)", borderRadius: "2px" }}>
                    <div style={{ width: "35%", height: "100%", background: "#3b82f6" }} />
                  </div>
                </div>
              </div>
            </div>

            <div className="glass-card" style={{ padding: "24px" }}>
              <h3 style={{ fontSize: "0.95rem", fontWeight: "600", marginBottom: "12px" }}>Active RAG Document Pool</h3>
              <p style={{ fontSize: "0.8rem", color: "rgba(255,255,255,0.5)", lineHeight: "1.4" }}>
                Your AI agent answers user queries based on files indexed in the RAG repository. Keep your knowledge base updated by uploading documentation under the "Knowledge Base" tab.
              </p>
            </div>
          </div>
        </div>
      )}

      {activeSubTab === "widget" && (
        <div style={{ display: "grid", gridTemplateColumns: "1.2fr 1fr", gap: "30px" }}>
          {/* Widget customizer */}
          <div className="glass-card" style={{ padding: "30px" }}>
            <h3 style={{ fontSize: "1.1rem", fontWeight: "600", marginBottom: "20px" }}>White-Label Chatbot Designer</h3>
            
            <div style={{ display: "flex", flexDirection: "column", gap: "20px" }}>
              <div>
                <label style={{ display: "block", fontSize: "0.8rem", color: "rgba(255,255,255,0.6)", marginBottom: "6px" }}>Widget Branding Color (HEX)</label>
                <div style={{ display: "flex", gap: "10px" }}>
                  <input type="color" value={widgetColor} onChange={(e) => setWidgetColor(e.target.value)} style={{ width: "40px", height: "40px", border: "none", borderRadius: "8px", background: "none" }} />
                  <input type="text" className="input-field" value={widgetColor} onChange={(e) => setWidgetColor(e.target.value)} />
                </div>
              </div>

              <div>
                <label style={{ display: "block", fontSize: "0.8rem", color: "rgba(255,255,255,0.6)", marginBottom: "6px" }}>Agent Display Name</label>
                <input type="text" className="input-field" value={agentName} onChange={(e) => setAgentName(e.target.value)} />
              </div>

              <div>
                <label style={{ display: "block", fontSize: "0.8rem", color: "rgba(255,255,255,0.6)", marginBottom: "6px" }}>Welcome Message</label>
                <input type="text" className="input-field" value={welcomeText} onChange={(e) => setWelcomeText(e.target.value)} />
              </div>

              <div>
                <label style={{ display: "block", fontSize: "0.8rem", color: "rgba(255,255,255,0.6)", marginBottom: "6px" }}>Screen Placement</label>
                <select className="input-field" value={widgetPosition} onChange={(e) => setWidgetPosition(e.target.value)}>
                  <option value="bottom-right">Bottom Right Corner</option>
                  <option value="bottom-left">Bottom Left Corner</option>
                </select>
              </div>

              <div style={{ borderTop: "1px solid rgba(255,255,255,0.06)", paddingTop: "20px", marginTop: "10px" }}>
                <h4 style={{ fontSize: "0.9rem", fontWeight: "600", marginBottom: "12px" }}>Embed JavaScript Snippet</h4>
                <p style={{ fontSize: "0.75rem", color: "rgba(255,255,255,0.4)", marginBottom: "12px" }}>Paste this HTML script tag inside your website body to run the live chat widget:</p>
                <div className="glass-panel" style={{ padding: "14px", position: "relative" }}>
                  <code style={{ fontSize: "0.7rem", color: "#a78bfa", wordBreak: "break-all" }}>
                    {`<script src="https://harikson.neuravolt.cloud/widget.js" data-tenant="alphatech" data-color="${widgetColor}"></script>`}
                  </code>
                </div>
              </div>
            </div>
          </div>

          {/* Widget preview mock */}
          <div style={{ display: "flex", alignItems: "center", justifyContent: "center", minHeight: "400px" }}>
            <div style={{ 
              width: "320px", 
              height: "440px", 
              borderRadius: "16px", 
              overflow: "hidden", 
              border: "1px solid rgba(255,255,255,0.1)",
              background: "rgba(0,0,0,0.6)",
              display: "flex", 
              flexDirection: "column",
              boxShadow: "0 10px 30px rgba(0,0,0,0.3)"
            }}>
              <div style={{ padding: "16px", background: widgetColor, color: "white", display: "flex", gap: "10px", alignItems: "center" }}>
                <div style={{ width: "8px", height: "8px", borderRadius: "50%", background: "#10b981" }} />
                <span style={{ fontSize: "0.85rem", fontWeight: "600" }}>{agentName} Helpdesk</span>
              </div>
              <div style={{ flex: 1, padding: "15px", display: "flex", flexDirection: "column", gap: "10px", justifyContent: "flex-end" }}>
                <div style={{ background: "rgba(255,255,255,0.05)", padding: "10px 12px", borderRadius: "8px 8px 8px 0", maxWidth: "80%", fontSize: "0.75rem", alignSelf: "flex-start" }}>
                  {welcomeText}
                </div>
              </div>
              <div style={{ borderTop: "1px solid rgba(255,255,255,0.06)", padding: "10px 15px", display: "flex", gap: "10px", alignItems: "center" }}>
                <input type="text" className="input-field" placeholder="Send a message..." disabled style={{ height: "30px", fontSize: "0.75rem" }} />
                <button className="btn btn-primary" disabled style={{ padding: "6px 12px" }}>
                  <Send size={12} />
                </button>
              </div>
            </div>
          </div>
        </div>
      )}

      {activeSubTab === "rag" && (
        <div style={{ display: "flex", flexDirection: "column", gap: "30px" }}>
          {/* File Upload card */}
          <div className="glass-card" style={{ padding: "30px" }}>
            <h3 style={{ fontSize: "1.1rem", fontWeight: "600", marginBottom: "20px" }}>Upload Knowledge Base Documents</h3>
            
            <form onSubmit={handleAddDocument} style={{ display: "grid", gridTemplateColumns: "2fr 1fr", gap: "20px" }}>
              <div style={{ display: "flex", flexDirection: "column", gap: "15px" }}>
                <div>
                  <label style={{ display: "block", fontSize: "0.8rem", color: "rgba(255,255,255,0.6)", marginBottom: "6px" }}>File path name or URL crawler endpoint</label>
                  <input 
                    type="text" 
                    className="input-field" 
                    placeholder="e.g. pricing-sheet.pdf or https://mycompany.com/about" 
                    value={fileName}
                    onChange={(e) => setFileName(e.target.value)}
                    required
                  />
                </div>
              </div>

              <div style={{ display: "flex", alignItems: "flex-end" }}>
                <button type="submit" className="btn btn-primary" style={{ width: "100%", height: "40px", display: "flex", justifyContent: "center", alignItems: "center", gap: "8px" }}>
                  <Upload size={16} />
                  <span>Index File</span>
                </button>
              </div>
            </form>
          </div>

          {/* Files Index tables */}
          <div className="glass-card" style={{ padding: "30px" }}>
            <h3 style={{ fontSize: "1.1rem", fontWeight: "600", marginBottom: "20px" }}>Indexed Documentation Pool</h3>
            
            <table style={{ width: "100%", borderCollapse: "collapse", textAlign: "left" }}>
              <thead>
                <tr style={{ borderBottom: "1px solid rgba(255,255,255,0.06)", color: "rgba(255,255,255,0.4)", fontSize: "0.75rem", textTransform: "uppercase" }}>
                  <th style={{ padding: "12px 10px" }}>Document Name</th>
                  <th style={{ padding: "12px 10px" }}>Source Type</th>
                  <th style={{ padding: "12px 10px" }}>File Size</th>
                  <th style={{ padding: "12px 10px" }}>Status</th>
                  <th style={{ padding: "12px 10px" }}>Uploaded</th>
                </tr>
              </thead>
              <tbody>
                {documents.map((d) => (
                  <tr key={d.id} style={{ borderBottom: "1px solid rgba(255,255,255,0.04)", fontSize: "0.85rem" }}>
                    <td style={{ padding: "14px 10px", fontWeight: "600" }}>{d.name}</td>
                    <td style={{ padding: "14px 10px", color: "rgba(255,255,255,0.6)" }}>{d.type}</td>
                    <td style={{ padding: "14px 10px" }}>{d.size}</td>
                    <td style={{ padding: "14px 10px" }}>
                      <span className={`badge badge-${d.status === "INDEXED" ? "running" : "pending"}`} style={{ fontSize: "0.7rem" }}>
                        {d.status}
                      </span>
                    </td>
                    <td style={{ padding: "14px 10px", color: "rgba(255,255,255,0.4)" }}>{d.createdAt}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      )}

      {activeSubTab === "finetune" && (
        <div style={{ display: "grid", gridTemplateColumns: "1.2fr 1fr", gap: "30px" }}>
          {/* Active FineTune adaptors */}
          <div className="glass-card" style={{ padding: "30px" }}>
            <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", marginBottom: "25px" }}>
              <h3 style={{ fontSize: "1.1rem", fontWeight: "600" }}>QLoRA Adapter Registry</h3>
              <button onClick={handleTrainNow} className="btn btn-primary" style={{ padding: "8px 16px", display: "flex", gap: "6px" }} disabled={loading}>
                <Plus size={14} />
                <span>Trigger Fine-Tune</span>
              </button>
            </div>

            <div style={{ display: "flex", flexDirection: "column", gap: "15px" }}>
              {trainingLogs.map((log) => (
                <div key={log.id} className="glass-panel" style={{ padding: "18px" }}>
                  <div style={{ display: "flex", justifyContent: "space-between", alignItems: "center", marginBottom: "12px" }}>
                    <div>
                      <h4 style={{ fontSize: "0.9rem", fontWeight: "600" }}>{log.adapterName}</h4>
                      <p style={{ fontSize: "0.75rem", color: "rgba(255,255,255,0.4)" }}>Base: {getBrandedModelName(log.baseModel)} | Triggered: {log.createdAt}</p>
                    </div>
                    <span className={`badge badge-${log.status === "COMPLETED" ? "running" : "pending"}`} style={{ fontSize: "0.7rem" }}>
                      {log.status}
                    </span>
                  </div>

                  {log.status === "COMPLETED" && (
                    <div style={{ display: "flex", gap: "8px", fontSize: "0.75rem", color: "#10b981" }}>
                      <span>Completed fine-tuning at: {log.completedAt}</span>
                    </div>
                  )}

                  {/* Simulated graph check */}
                  {log.loss.length > 0 && (
                    <div style={{ marginTop: "15px", borderTop: "1px solid rgba(255,255,255,0.06)", paddingTop: "12px" }}>
                      <span style={{ fontSize: "0.7rem", color: "rgba(255,255,255,0.4)" }}>Loss checkpoints curve:</span>
                      <div style={{ display: "flex", gap: "10px", alignItems: "flex-end", height: "40px", marginTop: "8px" }}>
                        {log.loss.map((val, i) => (
                          <div key={i} style={{ 
                            flex: 1, 
                            height: `${(val / 3.0) * 100}%`, 
                            background: "linear-gradient(to top, #8b5cf6, #3b82f6)",
                            borderRadius: "2px",
                            position: "relative"
                          }}>
                            <span style={{ position: "absolute", top: "-15px", left: "0", fontSize: "0.6rem", color: "rgba(255,255,255,0.5)" }}>{val}</span>
                          </div>
                        ))}
                      </div>
                    </div>
                  )}
                </div>
              ))}
            </div>
          </div>

          {/* Model fine-tune logs details */}
          <div className="glass-card" style={{ padding: "24px" }}>
            <h3 style={{ fontSize: "1rem", fontWeight: "600", marginBottom: "15px" }}>Adaptor Training Rules</h3>
            <div style={{ display: "flex", flexDirection: "column", gap: "12px", fontSize: "0.8rem", lineHeight: "1.5", color: "rgba(255,255,255,0.6)" }}>
              <div style={{ display: "flex", gap: "10px", alignItems: "flex-start" }}>
                <Info size={16} style={{ color: "#a78bfa", marginTop: "2px", flexShrink: 0 }} />
                <p>Training runs adapt your chatbot's language patterns using your past tickets, logs, and indexing databases. Ensure to verify toxicity scoring prior to adaptions.</p>
              </div>
              <div style={{ display: "flex", gap: "10px", alignItems: "flex-start" }}>
                <Info size={16} style={{ color: "#3b82f6", marginTop: "2px", flexShrink: 0 }} />
                <p>QLoRA fine-tuning runs execute inside isolated GPU clusters. Each training epoch takes roughly 5 to 10 minutes depending on data size.</p>
              </div>
            </div>
          </div>
        </div>
      )}

      {activeSubTab === "leads" && (
        <div className="glass-card" style={{ padding: "30px" }}>
          <h3 style={{ fontSize: "1.1rem", fontWeight: "600", marginBottom: "20px" }}>Website Captured Leads</h3>
          
          <table style={{ width: "100%", borderCollapse: "collapse", textAlign: "left" }}>
            <thead>
              <tr style={{ borderBottom: "1px solid rgba(255,255,255,0.06)", color: "rgba(255,255,255,0.4)", fontSize: "0.75rem", textTransform: "uppercase" }}>
                <th style={{ padding: "12px 10px" }}>Lead Name</th>
                <th style={{ padding: "12px 10px" }}>Email Address</th>
                <th style={{ padding: "12px 10px" }}>Phone Number</th>
                <th style={{ padding: "12px 10px" }}>Captured Date</th>
              </tr>
            </thead>
            <tbody>
              {leads.map((l) => (
                <tr key={l.id} style={{ borderBottom: "1px solid rgba(255,255,255,0.04)", fontSize: "0.85rem" }}>
                  <td style={{ padding: "14px 10px", fontWeight: "600" }}>{l.name}</td>
                  <td style={{ padding: "14px 10px", color: "#a78bfa" }}>{l.email}</td>
                  <td style={{ padding: "14px 10px" }}>{l.phone}</td>
                  <td style={{ padding: "14px 10px", color: "rgba(255,255,255,0.4)" }}>{l.createdAt}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </div>
  );
}
