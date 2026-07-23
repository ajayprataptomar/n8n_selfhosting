import { prisma } from "./lib/prisma.js";
import { DockerService } from "./services/docker.service.js";
import docker from "./lib/docker.js";

async function verifyDeployment() {
  console.log("🚀 Running AI Stack Deployment Verification Test...");

  const testEmail = `ai_user_${Date.now()}@harikson.com`;
  const name = "aiuser";

  try {
    // 1. Create a mock user in database with aiEnabled=true
    console.log("1. Creating test user in database...");
    const user = await prisma.user.create({
      data: {
        email: testEmail,
        name: "AI Test User",
        company: "Harikson AI Labs",
        plan: "PRO",
        aiPlan: "PRO",
        role: "USER",
        status: "PENDING",
        aiEnabled: true,
        n8nEnabled: false,
        agentType: "CHAT",
        model: "harikson-chat-8b",
      }
    });

    console.log(`✅ User created! ID: ${user.id}, Email: ${user.email}`);

    // 2. Simulate User Approval Routing (just like users.ts router.patch("/:id/approve"))
    console.log("\n2. Simulating admin approval...");
    const safeName = user.email.split("@")[0].replace(/[^a-zA-Z0-9]/g, "").toLowerCase();
    const apps = ["openwebui"]; // n8n is disabled for this test

    console.log(`Deploying containers for safeName: ${safeName}, apps: ${apps}...`);
    const containerInfo = await DockerService.createInstance(safeName, user.plan, apps);

    console.log("✅ DockerService.createInstance resolved successfully!");
    console.log("Domain returned:", containerInfo.domain);
    console.log("Container ID returned:", containerInfo.containerId);

    // Save instance to DB
    const instance = await prisma.instance.create({
      data: {
        userId: user.id,
        name: safeName,
        domain: containerInfo.domain,
        containerId: containerInfo.containerId,
        status: "RUNNING",
        cpuLimit: 1.0,
        memoryLimit: "1024m",
        storageLimit: "25GB",
        apps: apps,
        agentType: user.agentType,
        model: user.model,
      }
    });
    console.log(`✅ Saved instance in DB. ID: ${instance.id}`);

    // 3. Inspect docker containers to see if they are active
    console.log("\n3. Verifying Docker containers status...");
    const list = await docker.listContainers({ all: true });
    
    const apiName = `harikson-tenant-${safeName}-api`;
    const aiName = `harikson-tenant-${safeName}-ai`;

    const apiContainer = list.find(c => c.Names?.includes(`/${apiName}`));
    const aiContainer = list.find(c => c.Names?.includes(`/${aiName}`));

    if (apiContainer) {
      console.log(`✅ Tenant API Container: ${apiName} is present! State: ${apiContainer.State}, Status: ${apiContainer.Status}`);
    } else {
      console.error(`❌ Tenant API Container: ${apiName} was NOT found!`);
    }

    if (aiContainer) {
      console.log(`✅ Ollama Container: ${aiName} is present! State: ${aiContainer.State}, Status: ${aiContainer.Status}`);
    } else {
      console.error(`❌ Ollama Container: ${aiName} was NOT found!`);
    }

    // Test stopping the instance
    console.log("\n4. Testing lifecycle stopInstance...");
    await DockerService.stopInstance(instance.containerId!);
    console.log("✅ stopInstance resolved!");

    // Cleanup
    console.log("\n5. Cleaning up resources...");
    await DockerService.deleteInstance(instance.containerId!);
    console.log("✅ deleted containers and cleanup complete.");

    // Clean up DB records
    await prisma.instance.delete({ where: { id: instance.id } });
    await prisma.user.delete({ where: { id: user.id } });
    console.log("✅ Deleted database records.");
    
    console.log("\n🎉 AI Stack deployment verification test completed successfully!");
  } catch (err) {
    console.error("❌ Verification failed with error:", err);
  } finally {
    await prisma.$disconnect();
  }
}

verifyDeployment();
