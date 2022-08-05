<?php
defined('JPATH_BASE') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Filesystem\File;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Uri\Uri;
use Laminas\Dom\Query;
use MatthiasMullie\Minify;

class plgContentPrismHighlighterGhsvs extends CMSPlugin
{
	protected $basepath = 'media/plg_content_prismhighlighterghsvs';

	protected static $loaded;

	protected $app;

	/**
	 * Switch for decision if it's worth to proceed.
	 *
	 * @var bool
	*/
	protected $goOn = false;

	protected $filesToLoad = [
		'plugin' => [],
		'scriptDeclaration' => [],
		'language' => [],
		'requireCss' => [],
		'requireJs' => [],
		'css' => [],
		'requirePlugins' => [],
		'requireLanguages' => [],
		// e.g. file-highlight needs sometimes a language explicitly that can't be detected by autoloader.
		'mustLanguages' => [],
		'requireVendorJs' => [],
	];

	// Specials: final replacements in article->text
	protected $replace = [
		'what' => [],
		'with' => [],
	];

	// Schlechte Krücke, um doppelten Lauf zu unterbinden. Bspw. Modul
	// Let's hope that it doesn't need other languages.
	// To be fixed!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
	protected $count = 0;

	public function onContentPrepare($context, &$article, &$params, $page = 0)
	{
		$contexts = ['com_content.article'];
		$views = ['article'];

		if ($this->params->get('categoryActive', 0) === 1)
		{
			$contexts[] = 'com_content.category';
			$views[] = 'category';
		}

		if (
			!$this->app->isClient('site')
			|| (!$this->params->get('robots', 0) && $this->app->client->robot)
			|| !in_array($context, $contexts)
			|| !in_array($this->app->input->get('view'), $views)
			|| !isset($article->text)
			|| !trim($article->text)
			|| $this->app->getDocument()->getType() !== 'html'
			|| $this->app->input->getBool('print')
		) {
			$this->goOn = false;

			return;
		}

		// Schlechte Krücke, um doppelten Lauf zu unterbinden. Bspw. Modul
		if ($this->count >= 1)
		{
			return;
		}

		$hasCODE = strpos($article->text, '<code') !== false;
		$hasPRE = strpos($article->text, '<pre') !== false;

		if ($hasCODE === false && $hasPRE === false)
		{
			return;
		}

		// Why that? Answer: Maybe plugin later also active for modules.
		if (!isset(self::$loaded['autoload']))
		{
			JLoader::register(
				'PrismHighlighterGhsvs',
				__DIR__ . '/Helper/PrismHighlighterGhsvsHelper.php'
			);
			require __DIR__ . '/vendor/autoload.php';
			self::$loaded['autoload'] = 1;
		}

		// Check basic needs:
		if (
			PrismHighlighterGhsvs::renewal($this->params) !== true
			|| !($aliasLanguageMap = PrismHighlighterGhsvs::getAliasLanguageMap())
			|| !($pluginCssMap = PrismHighlighterGhsvs::getPluginCssMap())
		) {
			return;
		}

		// Support also lang-xxx or only language-xxx classes?
		// 0:No, only language-xxx | 1:lang-xxx and language-xxx | 2:only lang-xxx
		// ToDo: Was a stupid idea.
		$supportLang = $this->params->get('supportLang', 0);

		// Search for lang(uage)-xxx classes (and others) 0:only in <code> or 1:also in <pre>?
		// ToDo: Was a stupid idea.
		$tagsParam = $this->params->get('tags', 0);

		$collectAttribs = [];

		// autoloader | combined | singleFile
		$howToLoad = $this->params->get('howToLoad', 'combined');

		/* Field 'userMustSelect'. Some Prism plugins cannot be detected
		automatically by this Joomla plugin.
		They do not use specific identifiers in the HTML.
########## Plugin autolinker,data-uri-highlight,highlight-keywords,inline-color,keep-markup,previewers,show-invisibles ##########
		Plugins like inline-color won't be loaded if there's no
		content with keyword 'color' in it.
		*/

		// We need this array earlier than expected.
		$forced = $this->params->get('userMustSelect', []);
		$inlineColorToLoad = false;

		########## Plugin inline-color. Part 1. Advance test 1 ##########
		if (in_array('inline-color', $forced))
		{
			$inlineColorToLoad = true;
		}
		########## /Plugin inline-color ##########

		$plgConfigurations = [];

		/* Joomla plugin can detect these Prismjs plugins and will load them automatically
		if found.
		The 'Active' switch decides whether the configuration you entered will be
		loaded *additionally* IF this Joomla plugin has discovered the respective
		PrismJs plugin.
		*/

		$pluginConfigurations_1 = $this->params->get('pluginConfiguration_1');

		if (is_object($pluginConfigurations_1) && count(get_object_vars($pluginConfigurations_1)))
		{
			foreach ($pluginConfigurations_1 as $pluginConfiguration)
			{
				if (
					$pluginConfiguration->active
					&& ($plugin = str_replace('(*)', '', $pluginConfiguration->plugin, $must))
					&& $config = trim($pluginConfiguration->configuration)
				) {
					/* We store the configuration always here and decide later if we
						will load it. */
					$plgConfigurations[$plugin][] = $config;
				}
			}
		}

		/* Some configurable(!) PrismJs plugins can NOT be found by this Joomla
		plugin automatically. The 'Active' switch decides whether the PrismJs plugin
		will be loaded. It will be also loaded if no "must-configuration" has been
		entered. Thus you can offload the configuration to an external JavaScript
		file. */

		$pluginConfigurations_2 = $this->params->get('pluginConfiguration_2');

		if (is_object($pluginConfigurations_2) && count(get_object_vars($pluginConfigurations_2)))
		{
			foreach ($pluginConfigurations_2 as $pluginConfiguration)
			{
				if (
					$pluginConfiguration->active
					&& ($plugin = str_replace('(*)', '', $pluginConfiguration->plugin, $must))
				) {
					$this->filesToLoad['plugin'][] = $plugin;

					if ($config = trim($pluginConfiguration->configuration))
					{
						$plgConfigurations[$plugin][] = $config;
					}
				}
			}
		}

		$dom = new Query('<div>' . $article->text . '</div>');

		if ($hasCODE)
		{
			/* First we check for CODE elements. They can be inline or can have a parent PRE
			(depending on setting 'tags' in plugin configuration).
			All attributes will be collected to identify languages and plugins later. */

			switch ($tagsParam)
			{
				case 1:
					// tag CODE itself.
					$tags = 'code';
					// And check parent PRE if exists.
					$hasParent = 1;
					break;
				default:
					$tags = 'code';
					// Ignore parents.
					$hasParent = 0;
			}

			// Find all <code> elements.
			$results = $dom->execute($tags);

			if (count($results))
			{
				$removeInlineWithoutLangClass = $this->params->get('removeInlineWithoutLangClass', 1) === 1;

				foreach ($results as $key => $result)
				{
					// Let's store class and other attributes of <code> element in array $collectAttribs.
					foreach ($result->attributes as $attribute)
					{
						// Easier later to exclude already here empty classes.
						// ToDo: Find a solution instead of checking again in $hasParent below?
						$attribute->value = trim($attribute->value);

						if ($attribute->name === 'class' && !$attribute->value)
						{
							continue;
						}

						$collectAttribs[$key][$attribute->name][] = $attribute->value;
					}

					########## Plugin inline-color. Part 2. Advance test 2 ##########
					if ($inlineColorToLoad && !isset($hasColor) && strpos($result->textContent, 'color') !== false)
					{
						$hasColor = true;
					}
					########## /Plugin inline-color ##########

					if ($hasParent && $result->parentNode->tagName === 'pre')
					{
						// Let's store class and other attributes of surounding PRE.
						foreach ($result->parentNode->attributes as $attribute)
						{
							// Easier later to exclude already here empty classes.
							// ToDo: Find a solution instead of checking again in !$hasParent above.
							$attribute->value = trim($attribute->value);

							if ($attribute->name === 'class' && !$attribute->value)
							{
								continue;
							}

							$collectAttribs[$key][$attribute->name][] = $attribute->value;
						}

						// Parent PRE => Not inline.
						$collectAttribs[$key]['isInlineCode'] = 0;

						/* We know here that we have <pre><code> combis.
						Let's detect plugins without characteristic
						attributes but characteristic nodes.
						*/

						########## Plugin unescaped-markup ##########
						/* Is a #comment (in HTML "<!--...-->") and no other
						DOM elements after? */
						if (
							/* In some weird cases (conflicts with already rendered shortcuts
							of other plugins like pagebreakghsvs AROUND(!) a code snippet)
							$result->firstChild is not populated correctly. */
							!empty($result->firstChild)

							&& $result->firstChild->nodeType === 8
							&& !$result->firstChild->nextSibling
							// To Do: Let user decide via param?
							&& $result->firstChild->length > 3
						) {
							$this->filesToLoad['plugin'][] = 'unescaped-markup';
						}
						########## /Plugin unescaped-markup ##########
					}
					else
					{
						$collectAttribs[$key]['isInlineCode'] = 1;

						/*
						Puuuuh! A harakiri action. The final removement follows later. See "Rough house cleaning.".
						Hintergrund: Ich verwende häufig Inline-<code> mit selbst definierten Klassen
						<code class="code-filename">. Die führen derzeit dazu, dass Basis-CSS geladen wird, obwohl gar
						nicht benötigt. Hier ist einfach die früheste Stelle, um einzugreifen.
						Es werden nur Inline-<code> durchgelassen, die eine lang[uage]-xy-Klasse haben.
						*/
						if (
							$removeInlineWithoutLangClass === true
							&& isset($collectAttribs[$key]['class'])
							&& PrismHighlighterGhsvs::strposCheckForLanguageClass(
								' ' . implode(' ', $collectAttribs[$key]['class']) . ' ',
								$supportLang) === false
						) {
							unset($collectAttribs[$key]['class']);
						}
					}
				}

				// Rough house cleaning.
				foreach ($collectAttribs as $key => $collected)
				{
					// ['isInlineCode'] always present.
					if (count($collectAttribs[$key]) <= 1)
					{
						unset($collectAttribs[$key]);
					}
				}

				// reset indices for following actions.
				$collectAttribs = array_values($collectAttribs);
			}
		}

		if ($hasPRE)
		{
			########## Plugin file-highlight ##########
			$fileHighlightFound = PrismHighlighterGhsvs::checkPREWithFile(
				'file-highlight',
				'data-src',
				$dom,
				$collectAttribs,
				$this->filesToLoad,
				$supportLang,
				$aliasLanguageMap,
				$plgConfigurations,
				$this->replace
			);
			########## /Plugin file-highlight ##########

			########## Plugin jsonp-highlight ##########
			PrismHighlighterGhsvs::checkPREWithFile(
				'jsonp-highlight',
				'data-jsonp',
				$dom,
				$collectAttribs,
				$this->filesToLoad,
				$supportLang,
				$aliasLanguageMap,
				$plgConfigurations,
				$this->replace
			);
			########## /Plugin jsonp-highlight ##########
		}

		// Nothing to do.
		if (!$collectAttribs)
		{
			return;
		}

		/*
		1) Detect language classes and load language if found.
		2) Combine all classes in $classesAll for easier handling later on.
		3) Add 'hasLang' values. */
		$matchesKey = 1;

		switch ($supportLang)
		{
			case 0:
				$muster = '/\s+language-([-a-z]+)\s+/';
				break;
			case 2:
				$muster = '/\s+lang-([-a-z]+)\s+/';
				break;
			default:
				$muster = '/\s+lang(uage)-([-a-z]+)\s+/';
				$matchesKey = 2;
		}

		foreach ($collectAttribs as $key => $attribs)
		{
			$classesAll = '';

			if (isset($attribs['class']))
			{
				foreach ($attribs['class'] as $class)
				{
					// Spaces for guaranteed matching regex that follows later on.
					$classes = array_map('trim', explode(' ', $class));
					$classesAll .= ' ' . implode(' ', $classes) . ' ';
				}

				if ($classesAll !== '')
				{
					// Short check with strpos.
					$hasLang =
					PrismHighlighterGhsvs::strposCheckForLanguageClass($classesAll, $supportLang);

					if ($hasLang)
					{
						if (preg_match_all($muster, $classesAll, $matches))
						{
							// Get component JS file specific alias of language and collect for loading.
							foreach ($matches[$matchesKey] as $language)
							{
								if (isset($aliasLanguageMap[$language]))
								{
									$this->filesToLoad['language'][]
										= $collectAttribs[$key]['hasLang']
										= $aliasLanguageMap[$language]['alias'];
								}
							}
						}
					}
				}
			}
			$collectAttribs[$key]['classesAll'] = $classesAll;
		}

		if ($theme = $this->params->get('theme', 'prism'))
		{
			$this->filesToLoad['css'] = (array) $theme;
		}

		########## Plugin inline-color. Part 3 ##########
		if ($inlineColorToLoad && !isset($hasColor))
		{
			unset($forced[ array_keys($forced, 'inline-color')[0] ]);
		}
		########## /Plugin inline-color ##########

		if ($forced)
		{
			$this->filesToLoad['plugin']
			=
			array_merge(
				$this->filesToLoad['plugin'],
				$forced
			);
		}

		// Toolbar dependent plugins selected?
		if ($forced = $this->params->get('toolbar', []))
		{
			$this->filesToLoad['plugin']
			=
			array_merge(
				$this->filesToLoad['plugin'],
				$forced
			);
		}

		/* ToDo: Revise this comment.
		Some plugins can be detected with CSS classes and/or attributes.
		Some plugins need <pre><code> (= ignore inline)
		Some/all(?) need a language

		ToDo: This should be a JSON file in the end instead of searching spaghetti like.
		ToDo or not: It's annoying. Some plugins need a language- class(!) that also can be
		"language-firlefanz". */
		foreach ($collectAttribs as $key => $attribs)
		{
			// Shortcut for the lazy ones.
			$classesAll = $attribs['classesAll'];

			########## Plugin match-braces ##########
			if (strpos($classesAll, ' match-braces '))
			{
				$this->filesToLoad['plugin'][] = 'match-braces';
			}
			########## /Plugin match-braces ##########

			// Only <pre><code> combinations.
			if ($attribs['isInlineCode'] === 0)
			{
				########## Plugin treeview ##########
				if (strpos($classesAll, ' language-treeview ') !== false)
				{
					$this->filesToLoad['plugin'][] = 'treeview';
				}
				########## /Plugin treeview ##########

				########## Plugin diff-highlight ##########
				// It has a class like language-diff-javascript?
				if (strpos($classesAll, ' language-diff-') !== false)
				{
					// ToDo: preg_match_all is just friendly for stupid users ;-)
					// 'diff-highlight' expects language-diff-, not lang-diff-!
					$muster = '/\s+language-diff-([-a-z]+)\s+/';

					if (preg_match_all($muster, $classesAll, $matches))
					{
						$this->filesToLoad['plugin'][] = 'diff-highlight';

						foreach ($matches[1] as $lang)
						{
							$this->filesToLoad['language'][] = $aliasLanguageMap[$lang]['alias'];
						}
					}
				}
				elseif (strpos($classesAll, ' diff-highlight '))
				{
					$this->filesToLoad['plugin'][] = 'diff-highlight';
				}
				########## /Plugin diff-highlight ##########

				########## Plugin line-numbers ##########
				if (strpos($classesAll, ' line-numbers '))
				{
					$this->filesToLoad['plugin'][] = 'line-numbers';
				}
				########## /Plugin line-numbers ##########

				/* Some plugins work also with nonsense language classes
				like language-fifipaffi but need a language class to work.
				Whyever. I ignore that shit. Only valid languages go through. */
				if (isset($attribs['hasLang']))
				{
					########## Plugin command-line ##########
					// Inconsistently needs explicitly language- not lang- class.
					if (strpos($classesAll, ' language-') !== false)
					{
						if (
							(isset($attribs['data-user']) && isset($attribs['data-host']))
							|| isset($attribs['data-prompt'])
						) {
							$this->filesToLoad['plugin'][] = 'command-line';
						}
					}
					########## /Plugin command-line ##########
				} //hasLang

				########## Plugin line-highlight ##########
				if (!empty($attribs['data-line']))
				{
					$this->filesToLoad['plugin'][] = 'line-highlight';
				}
				########## /Plugin line-highlight ##########
			} // if ($attribs['isInlineCode'] === 0) END
		} // foreach ($collectAttribs as $key => $attribs) END

		// autoloader makes no sense if there is no language class (= no language found).
		if ($howToLoad === 'autoloader' && $this->filesToLoad['language'])
		{
			$this->filesToLoad['plugin'][] = 'autoloader';
			$this->filesToLoad['scriptDeclaration'][]
				= "Prism.plugins.autoloader.languages_path = " . Uri::root(true) . $this->basepath . "/prismjs/components';";
			$this->filesToLoad['language'] = [];
		}

		########## Collect languagedependencies. ##########
		// ToDo: I don't know if autoloader loads dependencies, too. If yes ignore this routine if autoloader active!
		foreach (['mustLanguages', 'language'] as $index)
		{
			if ($this->filesToLoad[$index])
			{
				foreach ($this->filesToLoad[$index] as $key => $language)
				{
					// ToDo: Do we need 'requirePlugins' for languages?????
					foreach (['requireLanguages', 'requirePlugins'] as $requireKey)
					{
						if (!empty($aliasLanguageMap[$language][$requireKey]))
						{
							foreach ($aliasLanguageMap[$language][$requireKey] as $require)
							{
								$this->filesToLoad[$requireKey][] = $require;
							}
						}
					}
				}
			}
		}

		// Collect plugin dependencies.
		if ($this->filesToLoad['plugin'])
		{
			foreach ($this->filesToLoad['plugin'] as $key => $plugin)
			{
				if (!isset($pluginCssMap[$plugin]))
				{
					unset($this->filesToLoad['plugin'][$key]);
					continue;
				}

				if ($pluginCssMap[$plugin]['noCSS'] == 0)
				{
					$this->filesToLoad['requireCss'][] = $plugin;
				}

				if (!empty($pluginCssMap[$plugin]['requireVendorJs']))
				{
					foreach ((array) $pluginCssMap[$plugin]['requireVendorJs'] as $require)
					{
						$this->filesToLoad['requireVendorJs'][] = $require;
					}
				}

				// Yes, there can be language dependencies for plugins.
				foreach (['requirePlugins', 'requireLanguages'] as $requireKey)
				{
					if (!empty($pluginCssMap[$plugin][$requireKey]))
					{
						foreach ($pluginCssMap[$plugin][$requireKey] as $require)
						{
							$this->filesToLoad[$requireKey][] = $require;

							if (
								$requireKey === 'requirePlugins'
								&& (int) $pluginCssMap[$require]['noCSS'] === 0
							) {
								$this->filesToLoad['requireCss'][] = $require;
							}
						}
					}
				}
			}
		}

		// Hierarchy.
		$this->filesToLoad['requireLanguages'] = array_reverse($this->filesToLoad['requireLanguages']);
		$this->filesToLoad['requirePlugins'] = array_reverse($this->filesToLoad['requirePlugins']);

		//ToDo: Why just 'mustLanguages'????
		if ($this->filesToLoad['mustLanguages'])
		{
			$this->filesToLoad['language'] = array_merge(
				$this->filesToLoad['mustLanguages'],
				$this->filesToLoad['language']
			);

			// ToDo: For easier debugging. Remove?
			unset($this->filesToLoad['mustLanguages']);
		}

		// At this point we should have found something to do.
		if (!$this->filesToLoad['language'] && !$this->filesToLoad['plugin'])
		{
			return;
		}

		array_unshift($this->filesToLoad['requireJs'], 'core');

		foreach ($plgConfigurations as $plg => $config)
		{
			if (in_array($plg, $this->filesToLoad['plugin']))
			{
				$this->filesToLoad['scriptDeclaration'][] = implode(';', $config);
			}
		}

		$this->filesToLoad['plugin'] =
			array_merge(
				$this->filesToLoad['requirePlugins'],
				$this->filesToLoad['plugin']
			);
		// ToDo: For easier debugging. Remove?
		//unset($this->filesToLoad['requirePlugins']);

		$this->filesToLoad['language'] =
			array_merge(
				$this->filesToLoad['requireJs'],
				$this->filesToLoad['requireLanguages'],
				$this->filesToLoad['language']
			);
		// ToDo: For easier debugging. Remove?
		// Problems with PHP8 when second run, e.g. a module.
		//unset(
		//$this->filesToLoad['requireJs'],
		//$this->filesToLoad['requireLanguages']
		//);

		// House cleaning.
		foreach ($this->filesToLoad as $key => $values)
		{
			if ($values)
			{
				$this->filesToLoad[$key] = array_unique($this->filesToLoad[$key]);
			}
		}

		########## Plugin previewers. Unload? ##########
		// "This plugin is compatible with CSS, Less, Markup attributes, Sass, Scss and Stylus."
		PrismHighlighterGhsvs::checkPluginWithLanguageDependency(
			'previewers',
			['css', 'less', 'markup', 'sass', 'scss', 'stylus'],
			$this->filesToLoad
		);
		########## /Plugin previewers ##########

		########## Plugin wpd. Unload? ##########
		/* This plugin is compatible with CSS, SCSS, Markup.
		It adds links like https://webplatform.github.io/docs/css/atrules/import/
		to some keywords. */
		PrismHighlighterGhsvs::checkPluginWithLanguageDependency(
			'wpd',
			['css', 'scss', 'markup'],
			$this->filesToLoad
		);
		########## /Plugin wpd ##########

		########## Plugin download-button. Unload? ##########
		if (
			empty($fileHighlightFound)
			&& ($arrayKeys = array_keys($this->filesToLoad['plugin'], 'download-button'))
		) {
			unset($this->filesToLoad['plugin'][$arrayKeys[0]]);
		}
		########## /Plugin download-button ##########

		########## Plugin toolbar. Unload? ##########
		if (
			($arrayKeys = array_keys($this->filesToLoad['plugin'], 'toolbar'))
			&& !in_array('download-button', $this->filesToLoad['plugin'])
			&& !in_array('copy-to-clipboard', $this->filesToLoad['plugin'])
		) {
			unset(
				$this->filesToLoad['plugin'][$arrayKeys[0]],
				$this->filesToLoad['requireCss'][$arrayKeys[0]]
			);
		}
		########## /Plugin toolbar ##########

		$min = JDEBUG ? '' : '.min';
		$version = JDEBUG ? time() : 'auto';
		$jsFileName = $cssFileName = $doCss = $doJs = [];

		// Identify paths of CSS files.

		$paths = [
			'css' => 'themes/{id}.css',
			'requireCss' => 'plugins/{id}/prism-{id}.css',
		];

		foreach ($paths as $what => $path)
		{
			foreach ($this->filesToLoad[$what] as $id)
			{
				$doCss[] = $this->basepath . '/css/prismjs/' . str_replace('{id}', $id, $path);
				$cssFileName['first'][] = strtoupper($what[0]) . $id[0];
				$cssFileName['id'][] = $id;
			}
		}

		if ($customCssFile = trim($this->params->get('customCssFile', '')))
		{
			$doCss[] = str_replace('$template', $this->app->getTemplate(), $customCssFile);
			$cssFileName['first'][] = 'R' . $customCssFile[0];
			$cssFileName['id'][] = $customCssFile;
		}

		// Identify paths of JS files.

		// für ggf. später relative => true, hier ein if(). ToDo: Auch bei CSS.
		$folder = '/js';

		$paths = [
			'requireVendorJs' => $this->basepath . '{folder}/{id}' . $min . '.js',
			'language' => $this->basepath . '{folder}/prismjs/components/prism-{id}' . $min . '.js',
			'plugin' => $this->basepath . '{folder}/prismjs/plugins/{id}/prism-{id}' . $min . '.js',
		];

		foreach ($paths as $what => $path)
		{
			foreach ($this->filesToLoad[$what] as $id)
			{
				$doJs[] = str_replace(['{folder}', '{id}'], [$folder, $id], $path);
				$jsFileName['first'][] = strtoupper($what[0]) . $id[0];
				$jsFileName['id'][] = $id;
			}
		}

		$imploder = '';

		if (!$min)
		{
			$imploder = "\n";
		}

		$this->filesToLoad['scriptDeclaration'] =
			implode($imploder, $this->filesToLoad['scriptDeclaration']) . ';';

		if ($howToLoad === 'combined')
		{
			// Sort for file names only(!)? Doesn't match the ordering of combined files.
			sort($jsFileName['id'], SORT_NATURAL | SORT_FLAG_CASE);
			sort($cssFileName['id'], SORT_NATURAL | SORT_FLAG_CASE);
			sort($jsFileName['first'], SORT_NATURAL | SORT_FLAG_CASE);
			sort($cssFileName['first'], SORT_NATURAL | SORT_FLAG_CASE);

			$cssFileName = md5(implode('', $cssFileName['first'])) . '_'
				. md5(implode('_', $cssFileName['id'])) . $min . '.css';
			$jsFileName = md5(implode('', $jsFileName['first'])) . '_'
				. md5(implode('_', $jsFileName['id']) . $this->filesToLoad['scriptDeclaration'])
				. $min . '.js';

			$cssFileRel = $this->basepath . '/css/_combiByPlugin/' . $cssFileName;
			$jsFileRel = $this->basepath . '/js/_combiByPlugin/' . $jsFileName;
			$cssFileAbs = JPATH_SITE . '/' . $this->basepath . '/css/_combiByPlugin/' . $cssFileName;
			$jsFileAbs = JPATH_SITE . '/' . $this->basepath . '/js/_combiByPlugin/' . $jsFileName;

			if (!is_file($cssFileAbs))
			{
				if ($min)
				{
					$minifier = new Minify\CSS();

					// 0:Protection against image embedding and shit.
					$minifier->setMaxImportSize(0);

					foreach ($doCss as $file)
					{
						$minifier->add(JPATH_SITE . '/' . $file);
					}

					$minifier->minify($cssFileAbs);
				}
				else
				{
					$contents = [];

					foreach ($doCss as $file)
					{
						$currentFile = JPATH_SITE . '/' . $file;

						if (is_file($currentFile))
						{
							$contents[] = "/*\n" . $file . "\n*/";
							$contents[] = file_get_contents($currentFile);
						}
					}
					file_put_contents($cssFileAbs, implode("\n", $contents));
				}
			}

			$doCss = [$cssFileRel];

			if (!is_file($jsFileAbs))
			{
				$contents = [];

				foreach ($doJs as $file)
				{
					$currentFile = JPATH_SITE . '/' . $file;

					if (is_file($currentFile))
					{
						$contents[] = "/*\n" . $file . "\n*/";
						$contents[] = file_get_contents($currentFile);
					}
				}

				if ($this->filesToLoad['scriptDeclaration'])
				{
					if ($min)
					{
						$minifier = new Minify\JS($this->filesToLoad['scriptDeclaration']);
						$this->filesToLoad['scriptDeclaration'] = $minifier->minify();
					}

					$contents[] = "/*\n Plugins Configurations \n*/";
					$contents[] = $this->filesToLoad['scriptDeclaration'];
				}

				file_put_contents($jsFileAbs, implode(";\n", $contents));
			}

			$doJs = [$jsFileRel];
		}

		$attribs = ['version' => 'auto'];

		foreach ($doCss as $file)
		{
			HTMLHelper::_('stylesheet', $file, $attribs);

			// Schlechte Krücke, um doppelten Lauf zu unterbinden. Bspw. Modul
			$this->count++;
		}

		foreach ($doJs as $file)
		{
			HTMLHelper::_('script', $file, $attribs);

			// Schlechte Krücke, um doppelten Lauf zu unterbinden. Bspw. Modul
			$this->count++;
		}

		if ($howToLoad !== 'combined' && $this->filesToLoad['scriptDeclaration'])
		{
			Factory::getDocument()->addScriptDeclaration(
				$this->filesToLoad['scriptDeclaration'] . ';'
			);

			// Schlechte Krücke, um doppelten Lauf zu unterbinden. Bspw. Modul
			$this->count++;
		}

		if ($this->replace['what'])
		{
			$article->text = str_replace($this->replace['what'], $this->replace['with'], $article->text);

			// Schlechte Krücke, um doppelten Lauf zu unterbinden. Bspw. Modul
			$this->count++;
		}
	}
}
