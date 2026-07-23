import docker from "../lib/docker.js";
import path from "path";
import net from "net";

// Helper to find an open port starting from a given port
async function findOpenPort(startPort: number): Promise<number> {
  return new Promise((resolve) => {
    const checkPort = (port: number) => {
      const server = net.createServer();
      server.listen(port, () => {
        server.once('close', () => resolve(port));
        server.close();
      });
      server.on('error', () => {
        checkPort(port + 1);
      });
    };
    checkPort(startPort);
  });
}

export class DockerService {
  private static isMockMode = false;
  private static readonly baseImage = process.env.NODE_ENV === "development" ? "nginx:alpine" : "n8nio/n8n:latest";
  private static readonly networkName = process.env.DOCKER_NETWORK || "internal";

  static {
    // Check if Docker is available
    try {
      docker.ping((err) => {
        if (err) {
          console.warn("⚠️ Docker daemon not reachable. Falling back to MOCK MODE for instance management.");
          DockerService.isMockMode = true;
        } else {
          console.log("🐳 Docker daemon connected successfully.");
        }
      });
    } catch {
      DockerService.isMockMode = true;
    }
  }

  static async createInstance(name: string, plan: string, apps: string[]): Promise<{ containerId: string; domain: string }> {
    const domain = `${name}.neuravolt.cloud`;
    
    if (this.isMockMode) {
      console.log(`[Mock Docker] Spawning container for ${name} under plan ${plan} with apps: ${apps.join(", ")}`);
      return {
        containerId: `mock_container_${Math.random().toString(36).substring(7)}`,
        domain: apps.includes("openwebui") && process.env.NODE_ENV === "development" ? `localhost:5005` : domain,
      };
    }

    try {
      // Determine base CPU and Memory limits
      let cpuCount = 0.5;
      let memoryLimit = "512m";
      if (plan === "PRO") {
        cpuCount = 1.0;
        memoryLimit = "1024m";
      } else if (plan === "BUSINESS" || plan === "HEAVY") {
        cpuCount = 2.0;
        memoryLimit = "2048m";
      } else if (plan === "ENTERPRISE") {
        cpuCount = 4.0;
        memoryLimit = "4096m";
      }

      // Convert memory limit string (e.g. 512m) to bytes for Docker API
      const memoryBytes = parseInt(memoryLimit) * 1024 * 1024;

      await this.ensureNetwork(this.networkName);

      let mainContainerId = "";
      let activeDomain = domain;

      // 1. Deploy n8n Container
      if (apps.includes("n8n")) {
        await this.ensureImage(this.baseImage);
        const containerName = `nv-instance-${name}`;
        
        const existing = await docker.listContainers({
          all: true,
          filters: { name: [containerName] },
        });
        const existingInfo = existing.find((containerInfo) => 
          containerInfo.Names?.includes(`/${containerName}`)
        );

        let containerId = "";
        if (existingInfo) {
          const existingContainer = docker.getContainer(existingInfo.Id);
          if (existingInfo.State !== "running") {
            await existingContainer.start();
          }
          console.log(`🐳 Container ${containerName} already exists. Reusing it.`);
          containerId = existingInfo.Id;
        } else {
          const container = await docker.createContainer({
            Image: this.baseImage,
            name: containerName,
            Env: [
              `N8N_PORT=5678`,
              `WEBHOOK_URL=https://${domain}/`,
              `N8N_ENFORCE_SETTINGS_FILE_PERMISSIONS=false`
            ],
            HostConfig: {
              NanoCpus: cpuCount * 1e9, // nanoCPUs
              Memory: memoryBytes,
              RestartPolicy: { Name: "unless-stopped" },
              NetworkMode: this.networkName, // Join the internal network
              Binds: [
                `nv-instance-${name}-data:/home/node/.n8n`
              ]
            },
            Labels: {
              "traefik.enable": "true",
              [`traefik.http.routers.${name}.rule`]: `Host(\`${domain}\`)`,
              [`traefik.http.routers.${name}.entrypoints`]: "websecure",
              [`traefik.http.routers.${name}.tls.certresolver`]: "letsencrypt",
              [`traefik.http.services.${name}.loadbalancer.server.port`]: "5678",
            },
          });

          await container.start();
          console.log(`🐳 Container nv-instance-${name} started successfully.`);
          containerId = container.id;
        }
        mainContainerId = containerId;
      }

      // 2. Deploy AI Agent Tenant Container Stack
      if (apps.includes("openwebui")) {
        console.log(`🐳 Spawning AI Stack for tenant ${name}...`);
        const apiContainerName = `harikson-tenant-${name}-api`;
        const aiContainerName = `harikson-tenant-${name}-ai`;

        // 2.1 Spin up AI Core container (running Ollama)
        const aiImage = "ollama/ollama:latest";
        await this.ensureImage(aiImage);
        
        try {
          const aiContainer = await docker.createContainer({
            Image: aiImage,
            name: aiContainerName,
            HostConfig: {
              NanoCpus: cpuCount * 1e9,
              Memory: memoryBytes,
              RestartPolicy: { Name: "unless-stopped" },
              NetworkMode: this.networkName,
              Binds: [`harikson-tenant-${name}-ai-data:/root/.ollama`],
            },
          });
          await aiContainer.start();
          console.log(`🐳 AI Core container ${aiContainerName} started.`);
        } catch (err: any) {
          if (err.statusCode === 409) {
            console.log(`🐳 AI Core container ${aiContainerName} already exists. Reusing it.`);
            try {
              await docker.getContainer(aiContainerName).start();
            } catch {}
          } else {
            throw err;
          }
        }

        // 2.2 Spin up Tenant API container
        const apiImage = "node:18-alpine";
        await this.ensureImage(apiImage);

        // Resolve absolute path to tenant-api folder
        const tenantApiPath = path.resolve(process.cwd(), "../harikson/tenant-api");

        // Find a free port on the host
        let hostPort = 5005;
        if (process.env.NODE_ENV === "development") {
          hostPort = await findOpenPort(5005);
          activeDomain = `localhost:${hostPort}`;
        }

        try {
          const apiContainer = await docker.createContainer({
            Image: apiImage,
            name: apiContainerName,
            Env: [
              `PORT=5000`,
              `TENANT_NAME=${name}`,
              `AGENT_TYPE=CHAT`,
              `OLLAMA_HOST=http://${aiContainerName}:11434`,
              `NODE_ENV=${process.env.NODE_ENV || "development"}`,
            ],
            HostConfig: {
              NanoCpus: cpuCount * 1e9,
              Memory: memoryBytes,
              RestartPolicy: { Name: "unless-stopped" },
              NetworkMode: this.networkName,
              Binds: [`${tenantApiPath}:/usr/src/app`],
              PortBindings: {
                "5000/tcp": [{ HostPort: String(hostPort) }]
              }
            },
            WorkingDir: "/usr/src/app",
            Cmd: ["npm", "run", "start"]
          });
          await apiContainer.start();
          console.log(`🐳 Tenant API container ${apiContainerName} started on host port ${hostPort}.`);
          if (!mainContainerId) {
            mainContainerId = apiContainer.id;
          }
        } catch (err: any) {
          if (err.statusCode === 409) {
            console.log(`🐳 Tenant API container ${apiContainerName} already exists. Reusing it.`);
            try {
              const apiContainer = docker.getContainer(apiContainerName);
              await apiContainer.start();
              
              // Inspect to see host port binding if already running
              const inspectInfo = await apiContainer.inspect();
              const portBinding = inspectInfo.HostConfig.PortBindings?.["5000/tcp"]?.[0]?.HostPort;
              if (portBinding) {
                hostPort = parseInt(portBinding);
                if (process.env.NODE_ENV === "development") {
                  activeDomain = `localhost:${hostPort}`;
                }
              }
            } catch (inspectErr) {
              console.warn("⚠️ Failed to start or inspect existing tenant API container:", inspectErr);
            }
            if (!mainContainerId) {
              const existingApi = await docker.listContainers({
                all: true,
                filters: { name: [apiContainerName] },
              });
              if (existingApi.length > 0) {
                mainContainerId = existingApi[0].Id;
              }
            }
          } else {
            throw err;
          }
        }
      }

      return {
        containerId: mainContainerId || `mock_container_${Math.random().toString(36).substring(7)}`,
        domain: activeDomain,
      };
    } catch (error) {
      console.error("❌ Failed to create Docker container:", error);
      throw error;
    }
  }

  private static async ensureImage(image: string): Promise<void> {
    try {
      await docker.getImage(image).inspect();
      return;
    } catch {
      console.log(`🐳 Docker image ${image} not found locally. Pulling...`);
    }

    const stream = await docker.pull(image);
    await new Promise<void>((resolve, reject) => {
      docker.modem.followProgress(stream, (err) => {
        if (err) {
          reject(err);
          return;
        }
        resolve();
      });
    });
    console.log(`🐳 Docker image ${image} is ready.`);
  }

  private static async ensureNetwork(networkName: string): Promise<void> {
    try {
      await docker.getNetwork(networkName).inspect();
      return;
    } catch {
      console.log(`🐳 Docker network ${networkName} not found. Creating...`);
    }

    await docker.createNetwork({
      Name: networkName,
      Driver: "bridge",
    });
    console.log(`🐳 Docker network ${networkName} is ready.`);
  }

  static async stopInstance(containerId: string): Promise<void> {
    if (this.isMockMode || containerId.startsWith("mock_")) {
      console.log(`[Mock Docker] Stopped container ${containerId}`);
      return;
    }
    try {
      const container = docker.getContainer(containerId);
      const info = await container.inspect().catch(() => null);
      await container.stop();

      if (info && info.Name) {
        const containerName = info.Name.replace(/^\//, "");
        if (containerName.startsWith("nv-instance-")) {
          const name = containerName.replace("nv-instance-", "");
          try {
            await docker.getContainer(`harikson-tenant-${name}-api`).stop();
          } catch {}
          try {
            await docker.getContainer(`harikson-tenant-${name}-ai`).stop();
          } catch {}
        }
      }
    } catch (error) {
      console.error(`❌ Failed to stop container ${containerId}:`, error);
      throw error;
    }
  }

  static async startInstance(containerId: string): Promise<void> {
    if (this.isMockMode || containerId.startsWith("mock_")) {
      console.log(`[Mock Docker] Started container ${containerId}`);
      return;
    }
    try {
      const container = docker.getContainer(containerId);
      const info = await container.inspect().catch(() => null);
      await container.start();

      if (info && info.Name) {
        const containerName = info.Name.replace(/^\//, "");
        if (containerName.startsWith("nv-instance-")) {
          const name = containerName.replace("nv-instance-", "");
          try {
            await docker.getContainer(`harikson-tenant-${name}-api`).start();
          } catch {}
          try {
            await docker.getContainer(`harikson-tenant-${name}-ai`).start();
          } catch {}
        }
      }
    } catch (error) {
      console.error(`❌ Failed to start container ${containerId}:`, error);
      throw error;
    }
  }

  static async restartInstance(containerId: string): Promise<void> {
    if (this.isMockMode || containerId.startsWith("mock_")) {
      console.log(`[Mock Docker] Restarted container ${containerId}`);
      return;
    }
    try {
      const container = docker.getContainer(containerId);
      const info = await container.inspect().catch(() => null);
      await container.restart();

      if (info && info.Name) {
        const containerName = info.Name.replace(/^\//, "");
        if (containerName.startsWith("nv-instance-")) {
          const name = containerName.replace("nv-instance-", "");
          try {
            await docker.getContainer(`harikson-tenant-${name}-api`).restart();
          } catch {}
          try {
            await docker.getContainer(`harikson-tenant-${name}-ai`).restart();
          } catch {}
        }
      }
    } catch (error) {
      console.error(`❌ Failed to restart container ${containerId}:`, error);
      throw error;
    }
  }

  static async deleteInstance(containerId: string): Promise<void> {
    if (this.isMockMode || containerId.startsWith("mock_")) {
      console.log(`[Mock Docker] Deleted container ${containerId}`);
      return;
    }
    try {
      const container = docker.getContainer(containerId);
      const info = await container.inspect().catch(() => null);
      await container.remove({ force: true });

      if (info && info.Name) {
        const containerName = info.Name.replace(/^\//, "");
        if (containerName.startsWith("nv-instance-")) {
          const name = containerName.replace("nv-instance-", "");
          try {
            await docker.getContainer(`harikson-tenant-${name}-api`).remove({ force: true });
          } catch {}
          try {
            await docker.getContainer(`harikson-tenant-${name}-ai`).remove({ force: true });
          } catch {}
          try {
            await docker.getVolume(`harikson-tenant-${name}-ai-data`).remove();
          } catch {}
        }
      }
    } catch (error) {
      console.error(`❌ Failed to delete container ${containerId}:`, error);
      throw error;
    }
  }

  static async scaleInstance(containerId: string, cpuLimit: number, memoryLimit: string): Promise<void> {
    if (this.isMockMode || containerId.startsWith("mock_")) {
      console.log(`[Mock Docker] Scaled container ${containerId} to CPU: ${cpuLimit}, RAM: ${memoryLimit}`);
      return;
    }
    try {
      const container = docker.getContainer(containerId);
      const memoryBytes = parseInt(memoryLimit) * 1024 * 1024;
      await container.update({
        NanoCpus: cpuLimit * 1e9,
        Memory: memoryBytes,
      });
    } catch (error) {
      console.error(`❌ Failed to scale container ${containerId}:`, error);
      throw error;
    }
  }

  static async getMetrics(containerId: string): Promise<{ cpuUsage: number; memoryUsage: number; diskUsage: string }> {
    if (this.isMockMode || !containerId || containerId.startsWith("mock_")) {
      return {
        cpuUsage: +(Math.random() * 45 + 5).toFixed(2),
        memoryUsage: +(Math.random() * 300 + 150).toFixed(2),
        diskUsage: "2.4 GB",
      };
    }
    try {
      const container = docker.getContainer(containerId);
      const stats = await container.stats({ stream: false });
      
      // Calculate cpu percent usage
      let cpuPercent = 0.0;
      if (stats.cpu_stats && stats.precpu_stats) {
        const cpuDelta = stats.cpu_stats.cpu_usage.total_usage - stats.precpu_stats.cpu_usage.total_usage;
        const systemDelta = stats.cpu_stats.system_cpu_usage - stats.precpu_stats.system_cpu_usage;
        const numberCpus = stats.cpu_stats.online_cpus || 1;
        if (systemDelta > 0.0 && cpuDelta > 0.0) {
          cpuPercent = (cpuDelta / systemDelta) * numberCpus * 100.0;
        }
      }

      // Memory usage in MB
      let memoryMB = 0;
      if (stats.memory_stats) {
        memoryMB = stats.memory_stats.usage / (1024 * 1024);
      }

      return {
        cpuUsage: +cpuPercent.toFixed(2),
        memoryUsage: +memoryMB.toFixed(2),
        diskUsage: "1.2 GB",
      };
    } catch {
      return {
        cpuUsage: 0,
        memoryUsage: 0,
        diskUsage: "0 GB",
      };
    }
  }

  static async getLogs(containerId: string): Promise<string> {
    if (this.isMockMode || !containerId || containerId.startsWith("mock_")) {
      return `[Mock Logs ${new Date().toISOString()}] Container initialized.\n[Mock Logs] Nginx listening on port 80.\n[Mock Logs] Traefik health check request received: 200 OK.`;
    }
    try {
      const container = docker.getContainer(containerId);
      const logBuffer = await container.logs({ stdout: true, stderr: true, tail: 100 });
      return logBuffer.toString("utf-8");
    } catch {
      return "Logs unavailable.";
    }
  }

  static async deleteVolume(name: string): Promise<void> {
    if (this.isMockMode) {
      console.log(`[Mock Docker] Removed volume nv-instance-${name}-data`);
      return;
    }
    try {
      await docker.getVolume(`nv-instance-${name}-data`).remove();
      console.log(`🐳 Volume nv-instance-${name}-data removed.`);
    } catch (error) {
      console.warn(`⚠️ Failed to remove volume nv-instance-${name}-data:`, error);
    }
  }
}
