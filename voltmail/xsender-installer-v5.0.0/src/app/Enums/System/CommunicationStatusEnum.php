<?php
  
namespace App\Enums\System;

use App\Enums\EnumTrait;
use Illuminate\Support\Arr;

enum CommunicationStatusEnum :String {

     use EnumTrait;

     case CANCEL     = 'cancel';
     case PENDING    = 'pending';
     case SCHEDULE   = 'schedule';
     case FAIL       = 'fail';
     case DELIVERED  = 'delivered';
     case PROCESSING = 'processing';

     /**
      * values
      *
      * @return string
      */
     public function values(): string
     {
          return match($this) {
               self::CANCEL      => 'Cancel',
               self::PENDING     => 'Pending',
               self::SCHEDULE    => 'Scheduled',
               self::FAIL        => 'Failed',
               self::DELIVERED   => 'Delivered',
               self::PROCESSING  => 'Processing',
          };
     }

     /**
      * badge
      *
      * @return void
      */
     public function badge(): void
     {
          $color = match($this) {
               self::CANCEL      => 'dark-soft',
               self::PENDING     => 'primary-soft',
               self::SCHEDULE    => 'info-soft',
               self::FAIL        => 'danger-soft',
               self::DELIVERED   => 'success-soft',
               self::PROCESSING  => 'warning-soft',
          };

          echo "<span class='i-badge dot pill {$color}'>{$this->values()}</span>";
     }

     /**
      * getValues
      *
      * @return array
      */
     public static function getValues(): array
     {
          return array_map(fn($case) => $case->value, self::cases());
     }
}