# Twig Streaming

> **Read-Only Repository**
> This is a read-only subtree split from the main repository.
> Please submit issues and pull requests to [toppynl/symfony-astro](https://github.com/toppynl/symfony-astro).

Yield-based streaming template renderer with deferred slots and HTTP 103 Early Hints for Twig. This package enables immediate response streaming while parallel async operations complete in the background, delivering sub-100ms Time to First Byte (TTFB) by flushing an HTML shell immediately and streaming resolved content fragments as they become available.

## Installation

```bash
composer require toppy/twig-streaming
```

## Requirements

- PHP 8.4+
- Twig 3.0+
- AmPHP 3.0+ (`amphp/amp`)
- Symfony HttpFoundation 6.4+ / 7.0+ / 8.0+
- Symfony Service Contracts 1.0+ / 2.0+ / 3.0+
- `toppy/async-view-model` (core async infrastructure)

## Quick Start

```php
use Toppy\TwigStreaming\Twig\StreamingTemplateRenderer;
use Toppy\TwigStreaming\Slot\SlotRegistry;
use Toppy\TwigStreaming\Slot\SlotRenderer;

// In your controller
$renderer = new StreamingTemplateRenderer(
    twig: $twig,
    viewModelManager: $viewModelManager,
    slotRegistry: new SlotRegistry(),
    slotRenderer: new SlotRenderer(),
);

// Simple streaming (no gates)
return $renderer->renderDirect('product/show.html.twig', [
    'product' => $product,
]);

// With gate conditions (await critical data before streaming)
return $renderer
    ->render('product/show.html.twig', ['productId' => $id])
    ->awaitBefore($productFuture, function ($product) {
        if ($product === null) {
            throw new NotFoundHttpException('Product not found');
        }
    })
    ->getResponse();
```

## Architecture

### Key Classes

| Class | Namespace | Purpose |
|-------|-----------|---------|
| `StreamingTemplateRenderer` | `Twig\` | Main renderer using Twig's `use_yield` mode to stream HTML chunks |
| `PendingResponse` | `Twig\` | Fluent builder for responses with gate conditions |
| `DeferredSlot` | `Slot\` | Value object representing a deferred content placeholder |
| `SlotRegistry` | `Slot\` | Tracks registered slots and their associated Futures during rendering |
| `SlotRenderer` | `Slot\` | Renders slot placeholders (with skeletons) and reconciliation fragments |
| `EarlyHintsExtension` | `Twig\` | Twig extension for compile-time Early Hints discovery |
| `EarlyHintsDiscoveryVisitor` | `Twig\NodeVisitor\` | AST visitor that extracts `preload()`, `preconnect()` calls |
| `EarlyHintsProviderInterface` | `EarlyHints\` | Interface for custom Early Hints providers |

### Streaming Flow

The streaming pipeline operates in distinct phases:

```
1. Controller calls render()
       │
       ▼
2. Template loaded, doPreload() discovered
   ViewModels started in parallel (non-blocking)
       │
       ▼
3. PendingResponse returned
   Controller can add gate conditions via awaitBefore()
       │
       ▼
4. getResponse() called
   ├── 103 Early Hints sent (Link headers)
   └── StreamedResponse created
       │
       ▼
5. StreamedResponse callback executes
   ├── Gates awaited (can throw to change HTTP status)
   ├── Template yields chunks → flushed immediately
   └── Deferred slot fragments streamed as Futures resolve
```

### Yield-Based Streaming

Twig's `use_yield` mode (required) transforms template rendering from string concatenation to a Generator that yields HTML chunks. Each chunk is immediately flushed to the client:

```php
// Twig configured with use_yield: true
foreach ($template->stream($context) as $chunk) {
    echo $chunk;
    ob_flush();
    flush();
}
```

This enables the browser to start parsing HTML, loading CSS/JS, and rendering the initial shell while the server continues processing.

## Usage

### Deferred Slots

Deferred slots enable out-of-order content delivery. The initial response contains a placeholder with skeleton content; the actual content streams later when its Future resolves.

**DeferredSlot value object:**

```php
use Toppy\TwigStreaming\Slot\DeferredSlot;
use Toppy\AsyncViewModel\Context\RequestContext;

// Create a slot with deterministic ID
$slot = new DeferredSlot(
    id: DeferredSlot::generateId('product/stock.html.twig', $requestContext),
    template: 'product/stock.html.twig',
    skeleton: 'skeletons/stock.html.twig',
    fallback: 'partials/stock-error.html.twig', // Optional error template
    isInlineFallback: false,
);
```

**SlotRegistry tracks slots during rendering:**

```php
use Toppy\TwigStreaming\Slot\SlotRegistry;
use Amp\Future;

$registry = new SlotRegistry();

// Register slot with its content Future
$registry->register($slot, $contentFuture);

// Check if slot exists
if ($registry->has($slotId)) {
    $slot = $registry->getSlot($slotId);
    $future = $registry->getFuture($slotId);
}

// Get all pending slots for streaming
$pending = $registry->getPending();
// Returns: ['slot_abc123' => ['slot' => DeferredSlot, 'future' => Future]]

// Reset for next request (worker mode)
$registry->reset();
```

**SlotRenderer outputs HTML:**

```php
use Toppy\TwigStreaming\Slot\SlotRenderer;

$renderer = new SlotRenderer();

// Initial placeholder with skeleton
$placeholder = $renderer->renderPlaceholder($slot, '<div class="skeleton">Loading...</div>');
// Output: <div id="slot_abc123"><div class="skeleton">Loading...</div></div>

// Reconciliation fragment (streamed when Future resolves)
$fragment = $renderer->renderFragment($slot, '<span>42 in stock</span>');
// Output: <template id="tmpl_abc123">...</template><script>/* replaces placeholder */</script>
```

The reconciliation fragment uses a `<template>` element and inline script that replaces the placeholder's children with the resolved content, then self-removes.

### Early Hints

HTTP 103 Early Hints allow the server to send Link headers before the main response, enabling browsers to preload critical resources while the server prepares content.

**Compile-time discovery:**

The `EarlyHintsDiscoveryVisitor` scans templates during compilation for WebLink function calls:

```twig
{# These are discovered at compile-time and sent as 103 Early Hints #}
{{ preload(asset('styles/app.css'), {as: 'style'}) }}
{{ preload(asset('scripts/app.js'), {as: 'script'}) }}
{{ preconnect('https://fonts.googleapis.com') }}
{{ dns_prefetch('https://analytics.example.com') }}
```

The visitor generates a `doEarlyHints()` method on the compiled template class that returns all discovered hints.

**Custom providers:**

Implement `EarlyHintsProviderInterface` to add hints from other sources (e.g., ImportMap):

```php
use Toppy\TwigStreaming\EarlyHints\EarlyHintsProviderInterface;

class ImportMapEarlyHintsProvider implements EarlyHintsProviderInterface
{
    public function getHints(): array
    {
        return [
            [
                'rel' => 'modulepreload',
                'href' => '/assets/app-abc123.js',
                'attributes' => [],
            ],
        ];
    }
}
```

**How Early Hints are sent:**

1. `render()` extracts hints from the compiled template
2. Hints from all registered providers are collected
3. Asset paths are resolved to URLs via Symfony's `Packages` service
4. Hints are deduplicated by href
5. 103 response sent via `headers_send(103)` before the main response
6. Link header cleared to prevent duplication in the 200 response

### Gate Conditions (awaitBefore)

Gates allow awaiting critical Futures before streaming starts, enabling proper HTTP status codes on failure:

```php
$renderer
    ->render('product/show.html.twig', ['id' => $id])
    ->awaitBefore(
        $viewModelManager->getFuture(ProductViewModel::class),
        function (?Product $product) {
            if ($product === null) {
                throw new NotFoundHttpException();
            }
        }
    )
    ->awaitBefore(
        $viewModelManager->getFuture(InventoryViewModel::class),
        function ($inventory) {
            if (!$inventory->isAvailable()) {
                throw new GoneHttpException('Product discontinued');
            }
        }
    )
    ->getResponse();
```

**Key timing:** Gates are deferred until AFTER `preloadAll()` starts all ViewModels, ensuring parallel execution. The gate's Future is already in-flight when awaited.

### Worker Mode Compatibility

`SlotRegistry` implements Symfony's `ResetInterface` to clear state between requests in FrankenPHP worker mode:

```php
// Automatically called by Symfony's kernel.reset event
$slotRegistry->reset();
```

### Profiler Integration

The package includes profiler infrastructure for debugging streaming performance:

```php
use Toppy\TwigStreaming\Profiler\TemplateStreamProfilerInterface;
use Toppy\TwigStreaming\Profiler\StreamingTimelineEvent;

// Profiler collects timing events
$profiler->enterTemplate('product/show.html.twig');
$profiler->enterBlock('product/show.html.twig', 'content');
// ... rendering ...
$profiler->leaveBlock('product/show.html.twig', 'content');
$profiler->leaveTemplate('product/show.html.twig');

// Retrieve events for visualization
$events = $profiler->getEvents();
```

The `StreamingProfilerExtension` registers a node visitor that injects profiling hooks into compiled templates.

## Integration

This package is **Layer 1** in the Toppy stack, sitting above `async-view-model` (Layer 0):

```
┌─────────────────────────────────────────┐
│  twig-streaming (this package)          │
│  - Streaming responses                  │
│  - Deferred slots                       │
│  - Early Hints                          │
└────────────────┬────────────────────────┘
                 │ depends on
                 ▼
┌─────────────────────────────────────────┐
│  async-view-model                       │
│  - ViewContext, RequestContext          │
│  - ViewModelManager                     │
│  - AsyncViewModel interface             │
└─────────────────────────────────────────┘
```

**Used by:**

- `toppy/twig-prerender` - Adds `{% include %}` modifiers for deferred/prerendered includes
- `toppy/symfony-async-twig-bundle` - Symfony integration with DI, profiler panels

## Testing

```bash
cd src/Toppy/Component/TwigStreaming
composer install
./vendor/bin/phpunit
```

Or from the monorepo root:

```bash
make demo-shell
cd src/Toppy/Component/TwigStreaming && ./vendor/bin/phpunit
```

## License

Proprietary - see LICENSE file for details.
