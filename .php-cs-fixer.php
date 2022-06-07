<?php
# plg_content_prismhighlighterghsvs
$mainFinder = PhpCsFixer\Finder::create()
	->exclude('node_modules')
	->exclude('build')
	->exclude('dist')
	->exclude('_composer')
	->exclude('media')
	->in(
		[
			__DIR__,
		]

	);

$config = new PhpCsFixer\Config();

$phpCsFixerRules = require_once '../php-cs-fixer-ghsvs/.php-cs-fixer.rules.php';

$config
	->setRiskyAllowed(true)
	->setIndent("\t")
	->setRules($phpCsFixerRules)
	->setFinder($mainFinder);

return $config;
