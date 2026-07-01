<?php

use TightenCo\Jigsaw\Jigsaw;
use Michelf\MarkdownExtra;

$events->afterBuild(function (Jigsaw $jigsaw) {
    // Phase 2: PageFind integration and version builds will be wired here
});

// Register a markdown() helper available in all Blade templates.
// Content pages use Markdown syntax; this renders it to HTML.
$events->beforeBuild(function (Jigsaw $jigsaw) {
    $container = $jigsaw->app;

    // Share a markdown converter with all views
    $container->singleton('markdown_converter', function () {
        $parser = new MarkdownExtra;
        $parser->code_class_prefix = 'language-';
        return $parser;
    });
});
