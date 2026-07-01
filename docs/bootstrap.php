<?php

use TightenCo\Jigsaw\Jigsaw;

$events->afterBuild(function (Jigsaw $jigsaw) {
    // Phase 2: PageFind integration and version builds will be wired here
});
