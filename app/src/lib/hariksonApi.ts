const API_BASE_URL = process.env.NEXT_PUBLIC_HARIKSON_API_URL || "http://localhost:9000";

export class HariksonApiClient {
  private static getHeaders(): HeadersInit {
    const headers: HeadersInit = {
      "Content-Type": "application/json",
    };
    
    if (typeof window !== "undefined") {
      const token = localStorage.getItem("nv_user_token");
      if (token) {
        headers["Authorization"] = `Bearer ${token}`;
        headers["x-n8n-token"] = token;
      }
    }
    return headers;
  }

  static async get<T>(path: string): Promise<T> {
    const res = await fetch(`${API_BASE_URL}${path}`, {
      method: "GET",
      headers: this.getHeaders(),
    });
    if (!res.ok) {
      const err = await res.json().catch(() => ({}));
      throw new Error(err.error || `HTTP error ${res.status}`);
    }
    return res.json();
  }

  static async post<T>(path: string, body?: any): Promise<T> {
    const res = await fetch(`${API_BASE_URL}${path}`, {
      method: "POST",
      headers: this.getHeaders(),
      body: body ? JSON.stringify(body) : undefined,
    });
    if (!res.ok) {
      const err = await res.json().catch(() => ({}));
      throw new Error(err.error || `HTTP error ${res.status}`);
    }
    return res.json();
  }
}

export default HariksonApiClient;
