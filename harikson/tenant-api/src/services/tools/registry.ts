import fs from 'fs';
import path from 'path';
import { exec } from 'child_process';
import { IgnoreMatcher } from '../indexer/repository-indexer.js';

export interface ToolParameter {
  type: 'string' | 'number' | 'boolean' | 'array' | 'object';
  description: string;
  required?: boolean;
}

export interface ToolDefinition {
  name: string;
  description: string;
  parameters: Record<string, ToolParameter>;
  handler: (workspacePath: string, params: any) => Promise<any>;
}

export class ToolRegistry {
  private static tools = new Map<string, ToolDefinition>();

  static register(tool: ToolDefinition) {
    this.tools.set(tool.name, tool);
  }

  static get(name: string): ToolDefinition | undefined {
    return this.tools.get(name);
  }

  static list(): ToolDefinition[] {
    return Array.from(this.tools.values());
  }

  // Helper: Securely resolve path inside workspace
  static resolvePath(
    workspacePath: string,
    targetPath: string,
    allowOutside = false
  ): string {
    const resolvedRoot = path.resolve(workspacePath);
    const resolvedTarget = path.resolve(resolvedRoot, targetPath);

    if (!allowOutside && !resolvedTarget.startsWith(resolvedRoot)) {
      throw new Error(
        `Access Denied: Path "${targetPath}" resolves outside of workspace.`
      );
    }
    return resolvedTarget;
  }
}

// 1. read_file
ToolRegistry.register({
  name: 'read_file',
  description:
    'Reads the content of a file in the workspace, with optional line range selection.',
  parameters: {
    path: {
      type: 'string',
      description: 'The path of the file to read',
      required: true,
    },
    startLine: {
      type: 'number',
      description: 'First line to read (1-indexed)',
    },
    endLine: { type: 'number', description: 'Last line to read (inclusive)' },
  },
  handler: async (workspace, params) => {
    const filePath = ToolRegistry.resolvePath(workspace, params.path);
    if (!fs.existsSync(filePath)) {
      throw new Error(`File not found: ${params.path}`);
    }

    const content = fs.readFileSync(filePath, 'utf-8');
    const { startLine, endLine } = params;

    if (startLine !== undefined || endLine !== undefined) {
      const lines = content.split('\n');
      const start = startLine !== undefined ? Math.max(0, startLine - 1) : 0;
      const end =
        endLine !== undefined ? Math.min(lines.length, endLine) : lines.length;
      return lines.slice(start, end).join('\n');
    }

    return content;
  },
});

// 2. write_file
ToolRegistry.register({
  name: 'write_file',
  description: 'Writes or overwrites content into a file in the workspace.',
  parameters: {
    path: {
      type: 'string',
      description: 'The path of the file to write',
      required: true,
    },
    content: {
      type: 'string',
      description: 'The text content to write',
      required: true,
    },
  },
  handler: async (workspace, params) => {
    const filePath = ToolRegistry.resolvePath(workspace, params.path);
    const parentDir = path.dirname(filePath);

    if (!fs.existsSync(parentDir)) {
      fs.mkdirSync(parentDir, { recursive: true });
    }

    fs.writeFileSync(filePath, params.content, 'utf-8');
    return `File written successfully: ${params.path}`;
  },
});

// 3. search_files
ToolRegistry.register({
  name: 'search_files',
  description:
    'Performs a grep-like recursive text search for query terms across files in the workspace.',
  parameters: {
    query: {
      type: 'string',
      description: 'The query string to look for',
      required: true,
    },
  },
  handler: async (workspace, params) => {
    const root = path.resolve(workspace);
    const matcher = new IgnoreMatcher(path.join(root, '.hariksonignore'));
    const queryLower = params.query.toLowerCase();
    const matches: { file: string; line: number; text: string }[] = [];

    const searchDir = (dir: string) => {
      const items = fs.readdirSync(dir);
      for (const item of items) {
        const fullPath = path.join(dir, item);
        const relPath = path.relative(root, fullPath);

        if (matcher.isIgnored(relPath)) continue;

        const stat = fs.statSync(fullPath);
        if (stat.isDirectory()) {
          searchDir(fullPath);
        } else if (stat.isFile()) {
          const content = fs.readFileSync(fullPath, 'utf-8');
          if (content.toLowerCase().includes(queryLower)) {
            const lines = content.split('\n');
            lines.forEach((line, index) => {
              if (line.toLowerCase().includes(queryLower)) {
                matches.push({
                  file: relPath,
                  line: index + 1,
                  text: line.trim().substring(0, 150), // limit preview size
                });
              }
            });
          }
        }
      }
    };

    searchDir(root);
    return matches.slice(0, 100); // cap results at 100 hits
  },
});

// 4. run_terminal
ToolRegistry.register({
  name: 'run_terminal',
  description:
    'Executes a shell command in the workspace directory under strict timeout and safety configurations.',
  parameters: {
    command: {
      type: 'string',
      description: 'The shell command to run',
      required: true,
    },
  },
  handler: async (workspace, params) => {
    const command = params.command as string;

    // Safety check 1: Block dangerous keywords
    const blocklist = ['rm -rf /', 'rm -rf  /', 'format', 'fdisk', 'mkfs'];
    if (blocklist.some((term) => command.includes(term))) {
      throw new Error(
        `Security Exception: Command blocked. Dangerous operations detected.`
      );
    }

    // Safety check 2: Allowlist prefix constraints
    const allowlist = [
      'git',
      'npm',
      'node',
      'python',
      'pytest',
      'pip',
      'composer',
      'go',
      'cargo',
    ];
    const baseCommand = command.trim().split(/\s+/)[0];
    if (!allowlist.includes(baseCommand)) {
      throw new Error(
        `Security Exception: Command "${baseCommand}" is not in the safe allowlist.`
      );
    }

    // Spawn execution with 30s timeout
    return new Promise((resolve, reject) => {
      exec(
        command,
        { cwd: workspace, timeout: 30000 },
        (error, stdout, stderr) => {
          if (error) {
            if (error.killed) {
              reject(
                new Error(`Command timed out (exceeded 30-second limit).`)
              );
            } else {
              resolve(
                `Error (Exit Code ${error.code}):\n${stderr || error.message}`
              );
            }
            return;
          }
          resolve(stdout || stderr || 'Command completed with no output.');
        }
      );
    });
  },
});

// 5. git_status
ToolRegistry.register({
  name: 'git_status',
  description: 'Returns the current git status of the repository.',
  parameters: {},
  handler: async (workspace) => {
    return new Promise((resolve, reject) => {
      exec(
        'git status',
        { cwd: workspace, timeout: 10000 },
        (error, stdout, stderr) => {
          if (error) resolve(`Git Error:\n${stderr || error.message}`);
          else resolve(stdout);
        }
      );
    });
  },
});

// 6. git_diff
ToolRegistry.register({
  name: 'git_diff',
  description:
    'Returns the git diff for a specific file or the entire repository.',
  parameters: {
    path: { type: 'string', description: 'Optional path of a file to diff' },
  },
  handler: async (workspace, params) => {
    const subPath = params.path
      ? ToolRegistry.resolvePath(workspace, params.path)
      : '';
    const command = subPath ? `git diff "${subPath}"` : 'git diff';

    return new Promise((resolve, reject) => {
      exec(
        command,
        { cwd: workspace, timeout: 15000 },
        (error, stdout, stderr) => {
          if (error) resolve(`Git Error:\n${stderr || error.message}`);
          else resolve(stdout || 'No diff changes.');
        }
      );
    });
  },
});

// 7. run_tests
ToolRegistry.register({
  name: 'run_tests',
  description: 'Executes the workspace unit tests.',
  parameters: {},
  handler: async (workspace) => {
    // Check if python, npm, etc. exist, default npm test
    const pkgPath = path.join(workspace, 'package.json');
    let testCommand = 'npm test';

    if (
      !fs.existsSync(pkgPath) &&
      fs.existsSync(path.join(workspace, 'requirements.txt'))
    ) {
      testCommand = 'python -m unittest';
    }

    return new Promise((resolve) => {
      exec(
        testCommand,
        { cwd: workspace, timeout: 30000 },
        (error, stdout, stderr) => {
          if (error) {
            resolve(`Tests Failed:\n${stdout}\n${stderr || error.message}`);
          } else {
            resolve(`Tests Passed:\n${stdout}`);
          }
        }
      );
    });
  },
});

// 8. list_directory
ToolRegistry.register({
  name: 'list_directory',
  description:
    'Lists all files and subdirectories inside a workspace directory.',
  parameters: {
    path: {
      type: 'string',
      description: 'Optional relative subdirectory path',
    },
  },
  handler: async (workspace, params) => {
    const dirPath = ToolRegistry.resolvePath(workspace, params.path || '.');
    if (!fs.existsSync(dirPath)) {
      throw new Error(`Directory not found: ${params.path}`);
    }

    const stat = fs.statSync(dirPath);
    if (!stat.isDirectory()) {
      throw new Error(`Path is not a directory: ${params.path}`);
    }

    const items = fs.readdirSync(dirPath);
    const details = items.map((item) => {
      const full = path.join(dirPath, item);
      const isDir = fs.statSync(full).isDirectory();
      return `${isDir ? '📁' : '📄'} ${item}`;
    });

    return details.join('\n') || '(empty directory)';
  },
});

// 9. http_request
ToolRegistry.register({
  name: 'http_request',
  description: 'Sends an HTTP GET or POST request to a remote server.',
  parameters: {
    url: { type: 'string', description: 'The endpoint URL', required: true },
    method: { type: 'string', description: 'GET or POST', required: true },
    data: { type: 'object', description: 'JSON body object for POST requests' },
  },
  handler: async (workspace, params) => {
    const { url, method, data } = params;
    const options: RequestInit = {
      method: method.toUpperCase(),
      headers: { 'Content-Type': 'application/json' },
    };

    if (options.method === 'POST' && data) {
      options.body = JSON.stringify(data);
    }

    const res = await fetch(url, options);
    if (!res.ok) {
      throw new Error(`HTTP Request failed: Status ${res.status}`);
    }

    const contentType = res.headers.get('content-type') || '';
    if (contentType.includes('application/json')) {
      return await res.json();
    }
    return await res.text();
  },
});

// 10. install_package
ToolRegistry.register({
  name: 'install_package',
  description: 'Installs a package using npm, pip, or composer.',
  parameters: {
    manager: {
      type: 'string',
      description: 'npm | pip | composer',
      required: true,
    },
    name: { type: 'string', description: 'Package name', required: true },
  },
  handler: async (workspace, params) => {
    const { manager, name } = params;
    let command = '';

    if (manager === 'npm') {
      command = `npm install ${name}`;
    } else if (manager === 'pip') {
      command = `pip install ${name}`;
    } else if (manager === 'composer') {
      command = `composer require ${name}`;
    } else {
      throw new Error(`Unsupported package manager: ${manager}`);
    }

    return new Promise((resolve, reject) => {
      exec(
        command,
        { cwd: workspace, timeout: 60000 },
        (error, stdout, stderr) => {
          if (error) {
            reject(
              new Error(`Failed to install package: ${stderr || error.message}`)
            );
          } else {
            resolve(
              `Package ${name} installed successfully using ${manager}.\n${stdout}`
            );
          }
        }
      );
    });
  },
});
