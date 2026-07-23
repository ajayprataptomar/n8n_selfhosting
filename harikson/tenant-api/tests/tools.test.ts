import fs from 'fs';
import path from 'path';
import { ToolExecutor } from '../src/services/tools/executor.js';
import { ToolRegistry } from '../src/services/tools/registry.js';

async function runToolTests() {
  console.log('🧪 [Harikson Tool Framework Test Suite] Initializing tests...');

  const tenantId = '00000000-0000-0000-0000-000000000000';
  const conversationId = '22222222-2222-2222-2222-222222222222';
  const toolsWorkspace = path.resolve('./tests/tools-workspace');

  // Create temporary workspace files
  if (fs.existsSync(toolsWorkspace)) {
    fs.rmSync(toolsWorkspace, { recursive: true, force: true });
  }
  fs.mkdirSync(toolsWorkspace, { recursive: true });

  // Mock DB persistence log
  const originalExecuteQuery = (ToolExecutor as any).executeQuery;
  const mockDbLogs: any[] = [];
  (ToolExecutor as any).executeQuery = async (
    tenantId: string,
    callback: any
  ) => {
    const mockClient = {
      query: async (sql: string, params?: any[]) => {
        if (sql.includes('INSERT INTO tool_executions')) {
          mockDbLogs.push({
            tool_name: params![2],
            params: params![3],
            result: params![4],
            status: params![5],
            execution_time_ms: params![6],
          });
        }
        return { rows: [] };
      },
    };
    return callback(mockClient);
  };

  // 🔹 Test 1: XML Parsing protocol check
  try {
    console.log('\n🔹 Test 1: Parsing XML tool call tags from response...');
    const sampleResponse = `
      To list the folder details, run the read_file tool like this:
      <tool_call name="read_file">
        <param name="path">src/app.js</param>
        <param name="startLine">10</param>
      </tool_call>
      Let me know if that is correct.
    `;

    const parsedCalls = ToolExecutor.parseToolCalls(sampleResponse);
    console.log(`👉 Extracted calls count: ${parsedCalls.length}`);

    if (
      parsedCalls.length === 1 &&
      parsedCalls[0].name === 'read_file' &&
      parsedCalls[0].params.path === 'src/app.js' &&
      parsedCalls[0].params.startLine === 10
    ) {
      console.log(
        '✅ Pass: XML tool tags parsed and converted to typed params successfully.'
      );
    } else {
      throw new Error(
        `Fail: XML parsing failed. Result: ${JSON.stringify(parsedCalls)}`
      );
    }
  } catch (err: any) {
    console.error('❌ Test 1 FAILED:', err.message);
  }

  // 🔹 Test 2: Safe file operations (write_file creation)
  try {
    console.log(
      '\n🔹 Test 2: Testing write_file execution inside workspace...'
    );

    const result = await ToolExecutor.executeSingle(
      tenantId,
      conversationId,
      toolsWorkspace,
      'write_file',
      { path: 'migration.sql', content: 'CREATE TABLE test (id INT);' }
    );

    const createdFilePath = path.join(toolsWorkspace, 'migration.sql');
    const exists = fs.existsSync(createdFilePath);

    console.log(`👉 File created physically: ${exists}`);

    if (exists && fs.readFileSync(createdFilePath, 'utf-8').includes('test')) {
      console.log(
        '✅ Pass: write_file executed successfully and created the file.'
      );
    } else {
      throw new Error('Fail: write_file did not create file or write content.');
    }
  } catch (err: any) {
    console.error('❌ Test 2 FAILED:', err.message);
  }

  // 🔹 Test 3: Safety Block constraints (rm -rf /)
  try {
    console.log(
      '\n🔹 Test 3: Running safety block on dangerous command (rm -rf /)...'
    );

    const outcome = await ToolExecutor.executeSingle(
      tenantId,
      conversationId,
      toolsWorkspace,
      'run_terminal',
      { command: 'rm -rf /' }
    );

    console.log(
      `👉 Outcome status: ${outcome.error ? 'Blocked (Error)' : 'Passed (Output)'}`
    );

    if (outcome.error && outcome.error.includes('Security Exception')) {
      console.log(
        "✅ Pass: Dangerous command 'rm -rf /' was successfully blocked."
      );
    } else {
      throw new Error('Fail: Dangerous command was not blocked.');
    }
  } catch (err: any) {
    console.error('❌ Test 3 FAILED:', err.message);
  }

  // 🔹 Test 4: Persisted log checks
  try {
    console.log('\n🔹 Test 4: Checking tool execution log persistence...');
    console.log(`👉 Logs count: ${mockDbLogs.length}`);

    if (
      mockDbLogs.some(
        (log) => log.tool_name === 'write_file' && log.status === 'success'
      ) &&
      mockDbLogs.some(
        (log) => log.tool_name === 'run_terminal' && log.status === 'error'
      )
    ) {
      console.log(
        '✅ Pass: Tool execution history successfully logged and tracked.'
      );
    } else {
      throw new Error(
        'Fail: Logging did not capture correct execution status entries.'
      );
    }
  } catch (err: any) {
    console.error('❌ Test 4 FAILED:', err.message);
  }

  // Cleanup temp files
  try {
    fs.rmSync(toolsWorkspace, { recursive: true, force: true });
  } catch (err: any) {
    console.warn('Warning cleaning up temp directory:', err.message);
  }

  // Restore mocks
  (ToolExecutor as any).executeQuery = originalExecuteQuery;

  console.log(
    '\n🏁 [Harikson Tool Framework Test Suite] Verification completed.'
  );
}

runToolTests().catch((err) => console.error('Fatal tool tests error:', err));
