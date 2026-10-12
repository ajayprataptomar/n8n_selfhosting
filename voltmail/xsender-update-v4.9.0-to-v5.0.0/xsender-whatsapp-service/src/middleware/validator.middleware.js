import { validationResult } from 'express-validator';
import { errorResponse } from '../utils/response.js';

/**
 * Validation Result Middleware
 */
export const validate = (req, res, next) => {
  const errors = validationResult(req);

  if (!errors.isEmpty()) {
    const errorMessages = errors.array().map((err) => ({
      field: err.path,
      message: err.msg,
    }));

    return errorResponse(res, 422, 'Validation failed', errorMessages);
  }

  next();
};

export default validate;
