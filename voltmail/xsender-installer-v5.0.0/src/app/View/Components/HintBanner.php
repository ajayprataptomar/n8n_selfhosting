<?php

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

/**
 * Renders a contextual help banner sourced from config/hints.php.
 *
 * Usage in any Blade view:
 *   <x-hint-banner key="gateway.tracking_domain.cname_setup" />
 *
 * If the key is unknown the component renders nothing (graceful skip).
 *
 * Phase 1: reads from config/hints.php only. A future phase will check the
 * `hint_overrides` table for buyer-customised text in the active locale.
 */
class HintBanner extends Component
{
    public ?array $hint = null;
    public string $alertClass = 'alert-info';
    public string $icon = 'ri-information-line';

    public function __construct(public string $key)
    {
        $this->hint = config("hints.{$key}");

        if ($this->hint) {
            $severity = $this->hint['severity'] ?? 'info';
            $this->alertClass = match ($severity) {
                'warning' => 'alert-warning',
                'danger'  => 'alert-danger',
                'success' => 'alert-success',
                default   => 'alert-info',
            };
            $this->icon = match ($severity) {
                'warning' => 'ri-error-warning-line',
                'danger'  => 'ri-alert-line',
                'success' => 'ri-checkbox-circle-line',
                default   => 'ri-information-line',
            };
        }
    }

    public function render(): ?View
    {
        if (!$this->hint || ($this->hint['surface'] ?? null) !== 'banner') {
            return null;
        }
        return view('components.hint-banner');
    }
}
