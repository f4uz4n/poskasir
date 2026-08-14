<?php

namespace App\View\Components;

use App\Models\AppSetting;
use App\Services\RecaptchaService;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class Recaptcha extends Component
{
    public bool $enabled;

    public ?string $siteKey;

    public function __construct(RecaptchaService $recaptcha)
    {
        $this->enabled = $recaptcha->shouldChallenge(request());
        $this->siteKey = $recaptcha->siteKey();
    }

    public function render(): View|Closure|string
    {
        return view('components.recaptcha');
    }
}
