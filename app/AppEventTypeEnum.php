<?php

namespace App;

enum AppEventTypeEnum: string
{
    case ReviewPromptTriggered = 'review_prompt_triggered';
    case ReviewPromptShown = 'review_prompt_shown';
    case ReviewCompleted = 'review_completed';
    case ReviewDismissed = 'review_dismissed';
    // Utilizador trocou de operador no ecrã de linhas (metadata: from, to, source)
    case ProviderSelected = 'provider_selected';
}
