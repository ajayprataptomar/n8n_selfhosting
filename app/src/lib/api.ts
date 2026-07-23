const getBaseUrl = (): string => {
  if (typeof window !== "undefined") {
    const hostname = window.location.hostname;
    if (hostname !== "localhost" && hostname !== "127.0.0.1" && !hostname.endsWith(".neuravolt.cloud")) {
      return `${window.location.protocol}//${hostname}:3000`;
    }
  }
  return process.env.NEXT_PUBLIC_API_URL || "http://localhost:3000";
};

export class ApiClient {
  private static getHeaders(): HeadersInit {
    const headers: HeadersInit = {
      "Content-Type": "application/json",
    };
    if (typeof window !== "undefined") {
      const token = localStorage.getItem("nv_user_token");
      if (token) {
        headers["Authorization"] = `Bearer ${token}`;
      }
    }
    return headers;
  }

  static async get<T>(path: string): Promise<T> {
    const res = await fetch(`${getBaseUrl()}${path}`, {
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
    const res = await fetch(`${getBaseUrl()}${path}`, {
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

  static async delete<T>(path: string): Promise<T> {
    const res = await fetch(`${getBaseUrl()}${path}`, {
      method: "DELETE",
      headers: this.getHeaders(),
    });
    if (!res.ok) {
      const err = await res.json().catch(() => ({}));
      throw new Error(err.error || `HTTP error ${res.status}`);
    }
    return res.json();
  }
}
export default ApiClient;
