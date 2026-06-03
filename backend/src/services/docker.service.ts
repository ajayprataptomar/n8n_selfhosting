import docker from "../lib/docker.js";

export class DockerService {
  private static isMockMode = false;
  private static readonly baseImage = "n8nio/n8n:latest";
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
        domain,
      };
    }

    try {
      // Determine base CPU and Memory limits
      let cpuCount = 0.5;
      let memoryLimit = "512m";
      if (plan === "PRO") {
        cpuCount = 1.0;
        memoryLimit = "1024m";
      } else if (plan === "HEAVY") {
        cpuCount = 2.0;
        memoryLimit = "2048m";
      }

      // Convert memory limit string (e.g. 512m) to bytes for Docker API
      const memoryBytes = parseInt(memoryLimit) * 1024 * 1024;

      // Ensure the lightweight n8n image exists before creating the web app.
      await this.ensureImage(this.baseImage);
      await this.ensureNetwork(this.networkName);

      const containerName = `nv-instance-${name}`;
      const existing = await docker.listContainers({
        all: true,
        filters: { name: [containerName] },
      });
      const existingInfo = existing.find((containerInfo) => 
        containerInfo.Names?.includes(`/${containerName}`)
      );
      if (existingInfo) {
        const existingContainer = docker.getContainer(existingInfo.Id);
        if (existingInfo.State !== "running") {
          await existingContainer.start();
        }
        console.log(`🐳 Container ${containerName} already exists. Reusing it.`);
        return {
          containerId: existingInfo.Id,
          domain,
        };
      }

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
      return {
        containerId: container.id,
        domain,
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
      await container.stop();
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
      await container.start();
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
      await container.restart();
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
      await container.remove({ force: true });
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
