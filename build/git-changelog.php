<?php
/*
####EXTRAKT-START docBlock

####EXTRAKT-END docBlock
*/

use DigiLive\GitChangelog\Renderers\MarkDown;

require __DIR__ . '/../_composer/vendor/autoload.php';

// Instantiate the library's renderer.
$changelog = new MarkDown('../');
$changelog->commitUrl = 'https://github.com/GHSVS-de/plg_content_prismhighlighterghsvs/commit/{hash}';

$changelog->setOptions([
	#'logHeader'           => 'Changelog',
	#'headTagName'         => 'Upcoming changes',
	#'headTagDate'         => 'Undetermined',
	#'noChangesMessage'    => 'No changes.',
	#'addHashes'           => true,
	#'includeMergeCommits' => false,
	#'tagOrderBy'          => 'creatordate',
	#'tagOrderDesc'        => true,
	// GHSVS. No title ordering! DESC | ASC.
	'titleOrder'          => '',
]);

// Build and save the changelog with all defaults.
$changelog->build();
$changelog->save(__DIR__ . '/../dist/CHANGELOG.md');
