import { prisma } from "./lib/prisma.js";
import { DockerService } from "./services/docker.service.js";

async function reprovision() {
  console.log("🔄 Reprovisioning existing tenant AI stacks...");
  
  const instances = await prisma.instance.findMany();
  for (const inst of instances) {
    const apps = inst.apps as string[];
    if (apps.includes("openwebui")) {
      console.log(`\nRe-deploying stack for: ${inst.name}...`);
      
      // Determine the plan enum from resources
      let planEnum = "STARTER";
      if (inst.memoryLimit === "2048m") {
        planEnum = "BUSINESS";
      } else if (inst.memoryLimit === "1024m") {
        planEnum = "PRO";
      }

      const containerInfo = await DockerService.createInstance(inst.name, planEnum, apps);
      
      await prisma.instance.update({
        where: { id: inst.id },
        data: {
          domain: containerInfo.domain,
          containerId: containerInfo.containerId
        }
      });
      console.log(`✅ Reprovisioned ${inst.name}. Domain updated to: ${containerInfo.domain}`);
    }
  }
}

reprovision()
  .then(() => {
    console.log("\n🎉 Reprovisioning complete!");
    process.exit(0);
  })
  .catch(err => {
    console.error("❌ Reprovisioning failed:", err);
    process.exit(1);
  });
