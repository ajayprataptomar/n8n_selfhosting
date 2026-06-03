import { Request, Response, NextFunction } from "express";

export function errorHandler(err: any, req: Request, res: Response, next: NextFunction) {
  console.error("❌ Express unhandled exception caught:", err);
  
  const status = err.status || err.statusCode || 500;
  const message = err.message || "An unexpected system error occurred.";

  res.status(status).json({
    error: message,
    stack: process.env.NODE_ENV === "development" ? err.stack : undefined,
  });
}
export default errorHandler;
