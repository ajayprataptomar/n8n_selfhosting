import Docker from 'dockerode';

// Connect to host Docker engine via unix socket
export const docker = new Docker({ socketPath: '/var/run/docker.sock' });
export default docker;
