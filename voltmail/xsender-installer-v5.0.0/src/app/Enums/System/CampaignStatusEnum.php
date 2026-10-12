<?php
  
namespace App\Enums\System;

use App\Enums\EnumTrait;
use Illuminate\Support\Arr;

enum CampaignStatusEnum :String {

     use EnumTrait;

     case CANCEL    = 'cancel';
     case PENDING   = 'pending';
     case ACTIVE    = 'active';
     case DEACTIVE  = 'deactive';
     case COMPLETED = 'completed';
     case ONGOING   = 'ongoing';

     /**
      * values
      *
      * @return string
      */
     public function values(): string
     {
          return match($this) {
               self::CANCEL        => 'Cancel',
               self::PENDING       => 'Pending',
               self::ACTIVE        => 'Active',
               self::DEACTIVE      => 'Deactive',
               self::COMPLETED     => 'Completed',
               self::ONGOING       => 'Ongoing',
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
               self::CANCEL        => 'dark-soft',
               self::PENDING       => 'warning-soft',
               self::ACTIVE        => 'primary-soft',
               self::DEACTIVE      => 'danger-soft',
               self::COMPLETED     => 'success-soft',
               self::ONGOING       => 'info-soft',
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