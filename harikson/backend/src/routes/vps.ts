import { Router, Response } from 'express';
import {
  n8nAuthBridge,
  AuthenticatedAdminRequest,
} from '../middleware/n8n-auth-bridge.js';

const router = Router();

router.use(n8nAuthBridge);

// GET /vps - List active VPS server nodes running container engines
router.get('/', async (req: AuthenticatedAdminRequest, res: Response) => {
  try {
    const mockNodes = [
      {
        id: 'node-primary-mumbai',
        name: 'Neuravolt VPS Node 01',
        ip: '45.194.2.244',
        region: 'ap-south-1 (Mumbai)',
        status: 'ONLINE',
        cpu: {
          total: 16,
          used: 4.5,
        },
        memory: {
          totalGB: 32,
          usedGB: 8.2,
        },
        storage: {
          totalGB: 500,
          usedGB: 120,
        },
        engine: 'Docker 24.0.7',
      },
    ];

    return res.status(200).json(mockNodes);
  } catch (error: any) {
    return res.status(500).json({ error: error.message });
  }
});

export default router;
