import { RepositoryIndexer } from './repository-indexer.js';

async function main() {
  const args = process.argv.slice(2);
  const workspacePath = args[0];

  if (!workspacePath) {
    console.error('❌ Usage: npm run harikson:index <workspace-path>');
    process.exit(1);
  }

  const tenantId =
    process.env.TENANT_ID || '00000000-0000-0000-0000-000000000000';

  console.log(
    `🧠 [Harikson Indexer] Starting indexer for workspace: "${workspacePath}"`
  );
  console.log(`🔑 Tenant ID: ${tenantId}`);

  try {
    const start = Date.now();
    const result = await RepositoryIndexer.indexWorkspace(
      tenantId,
      workspacePath,
      (progress) => {
        if (progress.phase === 'scanning') {
          process.stdout.write('🔍 Scanning directory structure...\r');
        } else if (progress.phase === 'indexing') {
          const percent =
            progress.totalFiles > 0
              ? Math.round(
                  (progress.processedFiles / progress.totalFiles) * 100
                )
              : 0;

          process.stdout.write(
            `⚡ Indexing ${progress.totalFiles} files... ${percent}% complete (Chunks: ${progress.chunksCreated}, Skipped: ${progress.skippedFiles})\r`
          );
        } else if (progress.phase === 'cleanup') {
          process.stdout.write('🧹 Running database state cleanup...\r');
        }
      }
    );

    const elapsed = ((Date.now() - start) / 1000).toFixed(2);
    console.log('\n\n==================================================');
    console.log('🎉 HARIKSON INDEXING COMPLETED SUCCESSFULLY!');
    console.log(`⏱️  Time Elapsed: ${elapsed} seconds`);
    console.log(`📁 Total Files Evaluated: ${result.totalFiles}`);
    console.log(`⏭️  Files Skipped (Unchanged): ${result.skippedFiles}`);
    console.log(`🧱 Total Vector Chunks Created: ${result.chunksCreated}`);
    console.log('==================================================\n');

    process.exit(0);
  } catch (error: any) {
    console.error(`\n❌ Indexer failed: ${error.message}`);
    process.exit(1);
  }
}

main().catch((err) => {
  console.error('Fatal runner error:', err);
  process.exit(1);
});
