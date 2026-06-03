import jwt from "jsonwebtoken";
import bcrypt from "bcryptjs";
import fs from "fs";
import path from "path";

const getJwtSecret = (): string => {
  try {
    const secretPath = process.env.JWT_SECRET_FILE || path.join(process.cwd(), "secrets", "jwt_secret");
    if (fs.existsSync(secretPath)) {
      return fs.readFileSync(secretPath, "utf-8").trim();
    }
  } catch {
    // Ignored
  }
  return process.env.JWT_SECRET || "neuravolt_dev_jwt_secret_key_extremely_long_and_secure_value_12345!";
};

export class AuthUtils {
  static hashPassword(password: string): string {
    const salt = bcrypt.genSaltSync(10);
    return bcrypt.hashSync(password, salt);
  }

  static comparePassword(password: string, hash: string): boolean {
    return bcrypt.compareSync(password, hash);
  }

  static generateToken(payload: { userId: string; role: string }): string {
    return jwt.sign(payload, getJwtSecret(), { expiresIn: "7d" });
  }

  static verifyToken(token: string): { userId: string; role: string } | null {
    try {
      return jwt.verify(token, getJwtSecret()) as { userId: string; role: string };
    } catch {
      return null;
    }
  }
}
export default AuthUtils;
