import { Request, Response, NextFunction } from 'express';
import { ZodSchema } from 'zod';
import logger from '../utils/logger.js';

export const validate = (schema: ZodSchema) => (req: Request, res: Response, next: NextFunction) => {
  try {
    schema.parse(req.body);
    next();
  } catch (error: any) {
    logger.info(
      'Zod validation failed details:',
      JSON.stringify(error.errors || error.issues || error)
    );
    return res.status(400).json({
      error: 'Validation failed',
      details: error.errors || error.issues || [],
    });
  }
};
