-- AlterTable
ALTER TABLE "knowledge_documents" ADD COLUMN "user_id" UUID;
ALTER TABLE "knowledge_documents" ADD COLUMN "content" TEXT;
ALTER TABLE "knowledge_documents" ADD COLUMN "is_active" BOOLEAN DEFAULT true;

-- AddForeignKey
ALTER TABLE "knowledge_documents" ADD CONSTRAINT "knowledge_documents_user_id_fkey" FOREIGN KEY ("user_id") REFERENCES "users"("id") ON DELETE CASCADE ON UPDATE CASCADE;
