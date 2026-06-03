import Docker from "dockerode";

// Connects to local Docker engine using Unix socket
export const docker = new Docker({ socketPath: "/var/run/docker.sock" });
export default docker;
