<?php

namespace App;

enum AppEventTypeEnum: string
{
    case ReviewPromptTriggered = 'review_prompt_triggered';
    case ReviewPromptShown = 'review_prompt_shown';
    case ReviewCompleted = 'review_completed';
    case ReviewDismissed = 'review_dismissed';
}
