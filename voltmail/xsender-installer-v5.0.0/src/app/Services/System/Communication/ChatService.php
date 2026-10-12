<?php

namespace App\Services\System\Communication;

use Carbon\Carbon;
use App\Models\User;
use App\Models\Message;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\MessageStatus;
use App\Models\MessageParticipant;
use App\Enums\System\ConversationParticipantEnum;
use App\Enums\System\ConversationMessageStatusEnum;
use App\Models\Gateway;
use App\Service\Admin\Core\FileService;
use Illuminate\Support\Arr;

class ChatService
{ 
     /**
      * Summary of createMessage
      * @param \App\Models\Conversation $conversation
      * @param string|null $messageContent
      * @param string $type
      * @param \App\Models\User|null $user
      * @param array|null $fileInfo
      * @return Message|\Illuminate\Database\Eloquent\Model
      */
     public function createMessage(
          Conversation $conversation,
          string|null $messageContent,
          string $type = 'whatsapp',
          User|null $user = null,
          ?array $fileInfo = null): Message {

          return Message::create([
               'conversation_id'   => $conversation->id,
               'type'              => $type,
               'message'           => $messageContent,
               'user_id'           => @$user?->id,
               'file_info'         => $fileInfo,
          ]);
     }

     /**
      * Summary of createMessageParticipants
      * @param \App\Models\Message $message
      * @param int $senderId
      * @param string $senderType
      * @param int $receiverId
      * @param string $receiverType
      * @return void
      */
     public function createMessageParticipants(
          Message $message, 
          int $senderId, 
          string $senderType, 
          int $receiverId, 
          string $receiverType): void {

          MessageParticipant::create([
               'message_id'             => $message->id,
               'participantable_id'     => $senderId,
               'participantable_type'   => $senderType,
               'role'                   => ConversationParticipantEnum::SENDER->value,
          ]);

          MessageParticipant::create([
               'message_id'             => $message->id,
               'participantable_id'     => $receiverId,
               'participantable_type'   => $receiverType,
               'role'                   => ConversationParticipantEnum::RECEIVER->value,
          ]);
     }

     /**
      * Summary of createMessageStatus
      * @param \App\Models\Message $message
      * @param \App\Enums\System\ConversationMessageStatusEnum $status
      * @param mixed $whatsappMessageId
      * @param array $additionalData
      * @return MessageStatus|\Illuminate\Database\Eloquent\Model
      */
     public function createMessageStatus(
          Message $message, 
          ConversationMessageStatusEnum $status, 
          ?string $whatsappMessageId = null, 
          ?array $additionalData = []): MessageStatus
     {
          return MessageStatus::create([
               'message_id'             => $message->id,
               'status'                 => $status->value,
               'whatsapp_message_id'    => $whatsappMessageId,
               'status_timestamp'       => Carbon::now(),
               'additional_data'        => $additionalData,
          ]);
     }

     /**
      * Summary of createOrUpdateConversation
      * @param \App\Models\Contact $contact
      * @param string $channel
      * @param mixed $metaData
      * @param \App\Models\User|null $user
      * @return Conversation|\Illuminate\Database\Eloquent\Model
      */
     public function createOrUpdateConversation(
          Contact $contact, 
          string $channel = 'whatsapp', 
          int|string|null $gateway_id = null, 
          ?array $metaData = null, 
          User|null $user = null): Conversation {

          return Conversation::firstOrCreate(
               [
                    'contact_id'  => $contact->id,
                    'channel'     => $channel,
                    'user_id'          => @$user?->id,
               ],
               [
                    'gateway_id'       => @$gateway_id,
                    'last_message_at'  => Carbon::now(),
                    'unread_count'     => 0,
                    'meta_data'        => $metaData,
               ]
          );
     }

     /**
      * Summary of updateConversation
      * @param \App\Models\Conversation $conversation
      * @return void
      */
     public function updateConversation(Conversation $conversation): void
     {
          $conversation->increment('unread_count');
          $conversation->update(['last_message_at' => Carbon::now()]);
     }

     /**
      * Summary of processWhatsAppMedia
      * @param array $messageData
      * @param Message|null $message
      * @param Conversation $conversation
      * @param int|null $gatewayId
      * @param User|null $user
      */
     public function processWhatsAppMedia(
          array $messageData,
          ?Message $message,
          Conversation $conversation,
          ?int $gatewayId = null,
          ?User $user = null
     )
     {
          $type = Arr::get($messageData, 'type');
          $fileData = Arr::get($messageData, $type);
          if (!$fileData || !isset($fileData['id'], $fileData['mime_type'], $fileData['sha256']))  return;
          

          $fileService = new FileService();
          $filePath = config('setting.file_path.whatsapp_meta_chats.path', 'assets/file/chats/whatsapp/meta/incoming');

          // Create a message only if none exists and there is no text content
          if (!$message && !Arr::get($messageData, 'text.body')) {
               $message = $this->createMessage(
                    conversation: $conversation,
                    messageContent: null,
                    type: 'whatsapp',
                    user: $user
               );
          }

          // Store the media file
          $file = $fileService->storeWhatsAppMessageFile(
               fileData: $fileData,
               filePath: $filePath,
               message: $message,
               gatewayId: $gatewayId,
               user: $user
          );

          if ($file) {
               // Update message file_info with file URL
               $fileUrl = $fileService->getFileUrl($filePath, $file->name);
               $message->update([
                    'file_info' => array_merge($message->file_info ?? [], [$file->id => $fileUrl])
               ]);
          }
          return $message;
     }
}