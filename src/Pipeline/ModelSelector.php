<?php

declare(strict_types=1);

namespace Emissary\Pipeline;

use Emissary\IntentResult;

class ModelSelector
{
    public function select(IntentResult $intent, bool $hasMedia = false): string
    {
        if ($hasMedia) {
            return config('emissary.vision_model', 'google/gemma-4-31b-it');
        }

        $complexIntents = config('emissary.complex_intents', []);

        if (in_array($intent->slug, $complexIntents, true)) {
            return config('emissary.complex_model', 'google/gemma-4-31b-it');
        }

        $escalationThreshold = config('emissary.confidence_escalation_threshold', 0.5);

        if ($intent->confidence < $escalationThreshold) {
            return config('emissary.complex_model', 'google/gemma-4-31b-it');
        }

        return config('emissary.default_model', 'google/gemma-4-31b-it');
    }
}
