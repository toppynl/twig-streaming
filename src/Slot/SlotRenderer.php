<?php
// packages/twig-streaming/src/Slot/SlotRenderer.php

declare(strict_types=1);

namespace Toppy\TwigStreaming\Slot;

/**
 * Renders slot placeholders and reconciliation fragments.
 */
final class SlotRenderer
{
    /**
     * Render the initial slot placeholder with skeleton content.
     */
    public function renderPlaceholder(DeferredSlot $slot, string $skeletonHtml): string
    {
        $escapedId = htmlspecialchars($slot->id, ENT_QUOTES, 'UTF-8');
        return sprintf('<div id="%s">%s</div>', $escapedId, $skeletonHtml);
    }

    /**
     * Render the fragment with template + reconciliation script.
     */
    public function renderFragment(DeferredSlot $slot, string $contentHtml): string
    {
        $suffix = $this->extractIdSuffix($slot->id);
        $escapedSlotId = htmlspecialchars($slot->id, ENT_QUOTES, 'UTF-8');

        $templateId = 'tmpl_' . $suffix;
        $scriptId = 'script_' . $suffix;

        return <<<HTML
<template id="{$templateId}">
{$contentHtml}
</template>
<script id="{$scriptId}">
(function(){
    var t=document.getElementById('{$templateId}'),
        s=document.getElementById('{$escapedSlotId}');
    if(t&&s)s.replaceChildren(...t.content.cloneNode(true).childNodes);
    t?.remove();
    document.getElementById('{$scriptId}')?.remove();
})();
</script>
HTML;
    }

    private function extractIdSuffix(string $slotId): string
    {
        // For generated IDs (slot_abc123), extract suffix
        if (str_starts_with($slotId, 'slot_')) {
            return substr($slotId, 5);
        }
        // For custom IDs, generate a unique suffix via hash
        return substr(md5($slotId), 0, 8);
    }
}
