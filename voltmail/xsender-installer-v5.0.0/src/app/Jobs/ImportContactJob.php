<?php

namespace App\Jobs;

use App\Enums\Common\Status;
use App\Enums\StatusEnum;
use App\Enums\System\ContactImportStatusEnum;
use App\Managers\ContactManager;
use App\Models\ContactImport;
use App\Models\File;
use App\Services\System\Contact\ContactService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\LazyCollection;
use Illuminate\Support\Arr;
use SplFileObject;

class ImportContactJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $contactImportId;
    protected $contactService;
    protected $contactManager;

    public function __construct(int $contactImportId)
    {
        $this->contactImportId = $contactImportId;
        $this->onQueue('import-contacts');
        $this->contactService = new ContactService();
        $this->contactManager = new ContactManager();
    }

    public function handle(): void
    {
        try {
            $contactImport = $this->contactManager->getPendingImportLog($this->contactImportId);
            if (!$contactImport) return;

            $contactImport->status = ContactImportStatusEnum::PROCESSING;
            $contactImport->save();

            $file = $contactImport->file;
            $filePath = storage_path("../../".$file->path . '/' . $file->name);
            
            if (!file_exists($filePath)) {
                throw new \Exception("File not found at: $filePath");
            }

            $mappingData = $contactImport->meta_data;
            if (is_string($mappingData)) $mappingData = json_decode($mappingData, true);

            $locations = explode(',', Arr::get($mappingData, 'location'));
            $values = explode(',', Arr::get($mappingData, 'value'));
            $newRow = Arr::get($mappingData, 'add_header_row', false);
            $newRow = $newRow && $newRow == "true";

            $columnMapping = collect($locations)->mapWithKeys(function ($csvColumn, $index) use ($values) {
                $csvColumn = trim(strtolower($csvColumn));
                $mappedValue = trim(Arr::get($values, $index, ''));
                if ($mappedValue) {
                    [$field, $type] = explode('::', $mappedValue);
                    return [$csvColumn => ['field' => $field, 'type' => (int) $type]];
                }
                return [];
            })->filter()->toArray();

            $chunkSize = config("queue.batch_sizes.regular.contacts.max");

            $csvRows = LazyCollection::make(function () use ($filePath) {
                $fileObj = new SplFileObject($filePath, 'r');
                $fileObj->setFlags(SplFileObject::READ_CSV | SplFileObject::READ_AHEAD | SplFileObject::SKIP_EMPTY);
                while (!$fileObj->eof()) {
                    $row = $fileObj->fgetcsv();
                    if ($row === false || $row === [null]) continue;
                    yield $row;
                }
            });

            $header = $csvRows->first();
            if ($header) {
                $header = array_map('trim', array_map('strtolower', $header));
            } else {
                throw new \Exception("CSV file is empty or invalid.");
            }

            $dataRows = $newRow ? $csvRows : $csvRows->slice(1);

            $dataRows->chunk($chunkSize)->each(function ($chunk) use ($header, $columnMapping, $contactImport, $newRow) {
                $chunk = $chunk->values()->toArray();
                $this->contactService->processContactChunk(
                    $chunk,
                    $header,
                    $columnMapping,
                    $contactImport,
                    $newRow
                );
            });

            $contactImport->status = ContactImportStatusEnum::COMPLETED;
            $contactImport->save();

            if (site_settings('email_contact_verification') == StatusEnum::TRUE->status() 
                || site_settings('email_contact_verification') == Status::ACTIVE->value) {

                $totalEmails = $this->contactManager->countEmailsForGroup($contactImport->group_id);
                $contactImport->total_emails = $totalEmails;
                $contactImport->save();

                if ($totalEmails > 0) { 
                    VerifyEmailJob::dispatch($this->contactImportId)->onQueue('verify-email');
                }
            }

            @unlink($filePath);
        } catch (\Throwable $e) {
            
            $contactImport->status = ContactImportStatusEnum::FAILED;
            $contactImport->save();
            throw $e;
        }
    }
}