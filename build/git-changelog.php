<?php
/*
####EXTRAKT-START docBlock

####EXTRAKT-END docBlock
*/

use DigiLive\GitChangelog\Renderers\MarkDown;

require __DIR__ . '/../_composer/vendor/autoload.php';

// Instantiate the library's renderer.
$changelog = new MarkDown('../');

// Build and save the changelog with all defaults.
$changelog->build();
$changelog->save('CHANGELOG.md');
