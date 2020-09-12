<?php
defined('JPATH_BASE') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Filesystem\File;
use Joomla\CMS\Filesystem\Folder;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Registry\Registry;
use Joomla\CMS\Utility\Utility;
use Laminas\Dom\Query;

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

	public function onContentPrepare($context, &$article, &$params, $page = 0)
	{
###################return;
		if (
			$context !== 'com_content.article'
			|| !$this->app->isClient('site')
			|| $this->app->input->get('view') !== 'article'
			|| (!$this->params->get('robots', 0) && $this->app->client->robot)
			|| !isset($article->text)
			|| !trim($article->text)
			|| $this->app->getDocument()->getType() !== 'html'
			|| $this->app->input->getBool('print')
		){
			$this->goOn = false;
			return;
		}

		$hasCODE = strpos($article->text, '<code') !== false;
		$hasPRE = strpos($article->text, '<pre') !== false;

		if ($hasCODE === false && $hasPRE === false)
		{
			return;
		}
		
		// Why? Maybe plugin later also active for Modules.
		if (!isset(self::$loaded['autoload']))
		{
			JLoader::register('PrismHighlighterGhsvsHelper', __DIR__ . '/Helper/PrismHighlighterGhsvsHelper.php');
			require __DIR__ . '/vendor/autoload.php';
			self::$loaded['autoload'] = 1;
		}

		// Check basic needs:
		if (
			PrismHighlighterGhsvsHelper::renewal($this->params) !== true
			|| !($aliasLanguageMap = PrismHighlighterGhsvsHelper::getAliasLanguageMap())
			|| !($pluginCssMap = PrismHighlighterGhsvsHelper::getPluginCssMap())
		){
			return;
		}

		// Support also lang-xxx or only language-xxx classes?
		// O: No, only language-xxx | 1: lang-xxx and language-xxx | 2: only lang-xxx
		$supportLang = $this->params->get('supportLang', 0);

		// Search for lang(uage)-xxx classes (and others) 0:only in <code> or 1:also in <pre>?
		$tagsParam = $this->params->get('tags', 0);
		
		$collectAttribs = [];

		// autoloader | combined | singleFile
		$howToLoad = $this->params->get('howToLoad', 'autoloader');

		/* Field 'userMustSelect'. Some Prism plugins cannot be detected 
		automatically by this Joomla plugin.
		They do not use specific identifiers in the HTML. */

		// We need this array earlier than expected.
		$forced = $this->params->get('userMustSelect', []);
		$inlineColorToLoad = false;
		
		if (\in_array('inline-color', $forced))
		{
			$inlineColorToLoad = true;
		}

		$plgConfigurations = [];

		/* Joomla plugin can detect these Prismjs plugins and will load them automatically
		if found.
		The 'Active' switch decides whether the configuration you entered will be
		loaded *additionally* IF this Joomla plugin has discovered the respective
		PrismJs.
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
				){
					if ($must)
					{
						#$plgConfigurations['must'][$plugin] = $must;
					}

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
				){
					$this->filesToLoad['plugin'][] = $plugin;
					
					if ($must)
					{
						#$plgConfigurations['must'][$plugin] = $must;
					}

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

			$results = $dom->execute($tags);

			if (count($results))
			{
				// $result is always a CODE element.
				foreach ($results as $key => $result)
				{
					// Let's store class and other attributes of CODE.
					foreach ($result->attributes as $attribute)
					{
						// Easier later to exclude already here empty classes.
						// ToDo: Find a solution instead of checking again in $hasParent below.
						$attribute->value = trim($attribute->value);

						if ($attribute->name === 'class' && !$attribute->value)
						{
							continue;
						}

						$collectAttribs[$key][$attribute->name][] = $attribute->value;
					}

########## Plugin inline-color. Vorprüfung ##########
					// Decision comes later if unload.
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
							$result->firstChild->nodeType === 8
							&& !$result->firstChild->nextSibling
							// To Do: Let user decide via param?
							&& $result->firstChild->length > 3
						){
							$this->filesToLoad['plugin'][] = 'unescaped-markup';
						}
########## /Plugin unescaped-markup ##########
					}
					else
					{
						$collectAttribs[$key]['isInlineCode'] = 1;
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
				$collectAttribs = \array_values($collectAttribs);
			}
		}

		if($hasPRE)
		{
########## Plugin file-highlight ##########
			// Special PRE case that never should have a CODE inside.
			// Reset.
			$collectPreAttribs = [];

			/* ToDo: "/" is a compromise because laminas-dom doesn't understand
			all CSS selectors and creates a fatal error if "wrong". */
			$tags = 'pre[data-src*="/"]';

			$results = $dom->execute($tags);

			if (count($results))
			{
				$key = count($collectAttribs);

				// $result is always a PRE[data-src] element.
				foreach ($results as $result)
				{
					$key++;
					$collectPreAttribs[$key]['isInlineCode'] = 0;

					// Must collect for later further attributes checks.
					foreach ($result->attributes as $attribute)
					{
						$collectPreAttribs[$key][$attribute->name][] = trim($attribute->value);
					}
					
					$this->filesToLoad['plugin'][] = 'file-highlight';

					// Do we have to load a language or toolbar.
					foreach ($collectPreAttribs as $key => $attribs)
					{
						// Has lang(uage)- class? Nothing to do then.
						$hasLang = false;

						if (!empty($attribs['class']))
						{
							$hasLang = 
							PrismHighlighterGhsvsHelper::strposCheckForLanguageClass($attribs['class'], $supportLang);
						}

						// Has no lang(uage)- class. Try to load by file extension (e.g. *.js).
						if (
							!$hasLang
							&& ($ext = strtolower(File::getExt($attribs['data-src'][0])))
							&& isset($aliasLanguageMap[$ext])
						){
							$this->filesToLoad['mustLanguages'][] = $aliasLanguageMap[$ext]['alias'];
						}
		
						// Add toolbar?
						if (isset($attribs['data-download-link']))
						{
							$this->filesToLoad['requirePlugins'][] = 'toolbar';
							$this->filesToLoad['plugin'][] = 'download-button';
						}
					}

					$collectAttribs = array_merge($collectAttribs, $collectPreAttribs);
				}
			}
########## /Plugin file-highlight ##########

########## Plugin jsonp-highlight ##########
			// Special PRE case that never should have a CODE inside.
			// Reset.
			$collectPreAttribs = [];

			/* ToDo: "/" is a compromise because laminas-dom doesn't understand
			all CSS selectors and creates a fatal error if "wrong". */
			$tags = 'pre[data-jsonp*="/"]';
			$results = $dom->execute($tags);

			if (count($results))
			{
				$key = count($collectAttribs);

				// $result is always a PRE[data-jsonp] element.
				foreach ($results as $result)
				{
					if ($result->firstChild)
					{
						continue;
					}

					$key++;
					$collectPreAttribs[$key]['isInlineCode'] = 0;

					// Must collect for later further attributes checks.
					foreach ($result->attributes as $attribute)
					{
						$collectPreAttribs[$key][$attribute->name][] = trim($attribute->value);
					}

					$this->filesToLoad['plugin'][] = 'jsonp-highlight';

					// Shall we load a language. Check Atrributes.
					foreach ($collectPreAttribs as $key => $attribs)
					{
						// Has lang(uage)- class? Nothing to do then.
						$hasLang = false;

						if (!empty($attribs['class']))
						{
							$hasLang = 
							PrismHighlighterGhsvsHelper::strposCheckForLanguageClass($attribs['class'], $supportLang);
						}

						// Has no lang(uage)- class. Try to load by file extension (e.g. *.js).
						if (
							!$hasLang
							&& ($ext = strtolower(File::getExt($attribs['data-jsonp'][0])))
							&& isset($aliasLanguageMap[$ext])
						){
							$this->filesToLoad['mustLanguages'][] = $aliasLanguageMap[$ext]['alias'];
						}
					}
				}
				
				$collectAttribs = \array_merge($collectAttribs, $collectPreAttribs);
			}
			else
			{
				unset($plgConfigurations['jsonp-highlight']);
			}
		}
########## /Plugin jsonp-highlight ##########

		// Nothing to do.
		if (!$collectAttribs)
		{
			return;
		}

		/* 1) Detect language classes and load language if found.
		2) Combine all classes in $classesAll. */
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
					PrismHighlighterGhsvsHelper::strposCheckForLanguageClass($classesAll, $supportLang);
					
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

		$this->filesToLoad['css'] = (array) $this->params->get('theme', ['prism']);

########## Plugin inline-color. Part 2 ##########
		if ($inlineColorToLoad && !isset($hasColor))
		{
			unset($forced[ \array_keys($forced, 'inline-color')[0] ]);
		}
########## /Plugin inline-color. Part 2 ##########	

		if ($forced)
		{
			$this->filesToLoad['plugin']
			=
			\array_merge(
				$this->filesToLoad['plugin'],
				$forced
			);
		}

		// Toolbar dependent plugins selected?
		if ($forced = $this->params->get('toolbar', []))
		{
			$this->filesToLoad['plugin']
			=
			\array_merge(
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

########## Plugin diff-highlight ##########
				// It has a class like language-diff-javascript?
				if (strpos($classesAll, ' language-diff-') !== false)
				{
					// ToDo: preg_match_all is just friendly for stupid users ;-)
					// 'diff-highlight' expects language-diff-, not lang-diff-!
					$muster = '/\s+language-diff-([-a-z]+)\s+/';
	
					if (preg_match_all($muster, $attribs['classesAll'], $matches))
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
						){
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
		if (
			$howToLoad === 'autoloader'
			&& $this->filesToLoad['language']
		){
			$this->filesToLoad['plugin'][] = 'autoloader';
			$this->filesToLoad['scriptDeclaration'][] 
				= "Prism.plugins.autoloader.languages_path = " . JUri::root(true) . $this->basepath . "/prismjs/components';";
			
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
							){
								$this->filesToLoad['requireCss'][] = $require;
							}
						}
					}
				}
			}
		}

		// Hierarchy.
		$this->filesToLoad['requireLanguages'] = \array_reverse($this->filesToLoad['requireLanguages']);
		$this->filesToLoad['requirePlugins'] = \array_reverse($this->filesToLoad['requirePlugins']);

		//ToDo: Why just 'mustLanguages'????
		if ($this->filesToLoad['mustLanguages'])
		{
			$this->filesToLoad['language'] = \array_merge(
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

		\array_unshift($this->filesToLoad['requireJs'], 'core');

		foreach ($plgConfigurations as $plg => $config)
		{
			if (in_array($plg, $this->filesToLoad['plugin']))
			{
				$this->filesToLoad['scriptDeclaration'][] = implode(';', $config);
			}
		}

		// House cleaning. ToDo: Better if later?
		foreach ($this->filesToLoad as $key => $values)
		{
			if ($values)
			{
				$this->filesToLoad[$key] = \array_unique($this->filesToLoad[$key]);
			}
		}
		
		$min = JDEBUG ? '' : '.min';
		$version = JDEBUG ? time() : 'auto';

		// Identify paths of CSS files.
		$doCss = [];
		$cssFileName = [];
		$paths = [
			'css' => 'themes/{id}.css',
			'requireCss' => 'plugins/{id}/prism-{id}.css',
		];
		
		foreach ($paths as $what => $path)
		{
			foreach ($this->filesToLoad[$what] as $id)
			{
				$doCss[] = $this->basepath . '/css/prismjs/' . str_replace('{id}', $id, $path);
				
				@$cssFileName['first'][] = strtoupper($what[0]) . $id[0];;
				@$cssFileName['id'][] = $id;
			}
		}

		//sort($combinedFilename, SORT_NATURAL | SORT_FLAG_CASE);
		
		$this->filesToLoad['plugin'] = \array_unique(
			\array_merge(
				$this->filesToLoad['requirePlugins'],
				$this->filesToLoad['plugin']
			)
		); 
		
		$this->filesToLoad['language'] = \array_unique(
			\array_merge(
				$this->filesToLoad['requireJs'],
				$this->filesToLoad['requireLanguages'],
				$this->filesToLoad['language']
			)
		);

########## Plugin previewers ##########
		// "This plugin is compatible with CSS, Less, Markup attributes, Sass, Scss and Stylus."
		if (
			\in_array('previewers', $this->filesToLoad['plugin'])
			&& !\array_intersect(
				['css', 'less', 'markup', 'sass', 'scss', 'stylus'],
				$this->filesToLoad['language']
			)
		){
			unset($this->filesToLoad['plugin']['previewers']);
		}
########## /Plugin previewers ##########

		// Identify paths of JS files.
		$doJs = [];
		$jsFileName = [];
		
		// für ggf. später relative => true, hier ein if(). ToDo: Auch bei CSS.
		$folder = '/js';

		$paths = [
			'requireVendorJs' => $this->basepath . '{folder}/{id}' . $min . '.js',
			// 'requireJs' => 'components/prism-{id}' . $min . '.js',
			// 'requireLanguages' => 'components/prism-{id}' . $min . '.js',
			'language' => $this->basepath . '{folder}/prismjs/components/prism-{id}' . $min . '.js',
			// 'requirePlugins' => 'plugins/{id}/prism-{id}' . $min . '.js',,
			'plugin' => $this->basepath . '{folder}/prismjs/plugins/{id}/prism-{id}' . $min . '.js',
		];

		foreach ($paths as $what => $path)
		{
			foreach ($this->filesToLoad[$what] as $id)
			{
				$doJs[] = str_replace(array('{folder}', '{id}'), array($folder, $id), $path);
				
				@$jsFileName['first'][] = strtoupper($what[0]) . $id[0];
				@$jsFileName['id'][] = $id;
			}
		}

		// ToDo: To sort or not to sort for file names only(!)? Doesn't match the ordering of combined files.
		sort($jsFileName['id'], SORT_NATURAL | SORT_FLAG_CASE);
		sort($cssFileName['id'], SORT_NATURAL | SORT_FLAG_CASE);
		sort($jsFileName['first'], SORT_NATURAL | SORT_FLAG_CASE);
		sort($cssFileName['first'], SORT_NATURAL | SORT_FLAG_CASE);
		
		$cssFileName
			= implode('', $cssFileName['first']) . '_' . md5(implode('_', $cssFileName['id'])) . $min . '.css';
		$jsFileName = implode('', $jsFileName['first']) . '_' . md5(implode('_', $jsFileName['id'])) . $min . '.js';

		foreach ($doCss as $file)
		{
			HTMLHelper::_('stylesheet', $file);
		}

		foreach ($doJs as $file)
		{
			HTMLHelper::_('script', $file);
		}
		
		if ($this->filesToLoad['scriptDeclaration'])
		{
			// ToDo: Remove "\n"
			Factory::getDocument()->addScriptDeclaration(
				';' . implode(";", $this->filesToLoad['scriptDeclaration']) . ';'
			);
		}
echo ' 4654sd48sa7d98sD81s8d71dsa filesToLoad 2 <pre>' . print_r($this->filesToLoad, true) . '</pre>';
echo ' 4654sd48sa7d98sD81s8d71dsa doJs <pre>' . print_r($doJs, true) . '</pre>';
echo ' 4654sd48sa7d98sD81s8d71ds doCss a <pre>' . print_r($doCss, true) . '</pre>';



return;

#echo ' 4654sd48sa7d98sD81s8d71dsa <pre>' . print_r(base64_encode(implode('_', $cssFileName)), true) . '</pre>';

echo ' 4654sd48sa7d98sD81s8d71dsa <pre>' . print_r($cssFileName, true) . '</pre>';


echo ' 4654sd48sa7d98sD81s8d71dsa <pre>' . print_r($jsFileName, true) . '</pre>';
exit;
echo ' 4654sd48sa7d98sD81s8d71dsa filesToLoad 2 <pre>' . print_r($this->filesToLoad, true) . '</pre>';exit;




		
################return;		

		
		
/**
autolinker: Done mit userMustSelect Feld.
'dependencies' => language-Klasse.
'Inline' => 1,
'PluginCanDetect' => 0,
'userMustSelect' => 1

command-line:
'Inline' => 0,
'PluginCanDetect' => 1,
'identifier' => '<pre class="command-line"',
'attributes' => data-user="", data-host="", data-prompt?"", data-output=""

copy-to-clipboard: Über toolbar-Feld erledigt

custom-class:
'Inline' => 1,
'PluginCanDetect' => 0,
'userMustSelect' => 1,
'userMustConfigure' => 1 (see https://prismjs.com/plugins/custom-class/),

data-uri-highlight:
'Inline' => 1,
'PluginCanDetect' => 0 (weiß nicht genau, ob nur auf bestimmte language-xyz lauscht,
'userMustSelect' => 1,
'requireOthers' => 'autolinker' Wohl kein muss, wird aber im Plugin-Code erwähnt.

diff-highlight:
'Inline' => 1,
'PluginCanDetect' => nicht immer,


copy-to-clipboard: Über toolbar-Feld erledigt

filter-highlight-all: noch großes ????
'Inline' => 1,
'PluginCanDetect' => 0,
'userMustSelect' => 1,
'userMustConfigure' => 1 (see https://prismjs.com/plugins/filter-highlight-all/),





keep-markup:  Done mit userMustSelect Feld.

match-braces:
'Inline' => 0,
'PluginCanDetect' => 1,
'dependencies' => language-Klasse sowie Klasse match-braces
'hint': Klasse im CODE haben bei mir nicht funktioniert, wenn kein <pre> drumrum ist.
<pre><code class="language-xxxx match-braces">...</code></pre>



treeview: Weiß noch nicht.

wpd: Keine Ahnung.

*/







		// Vergessen, warum. Ah, für Name der combined files. ToDo: Mach später!
		//sort($this->filesToLoad['language']);
		//sort($this->filesToLoad['plugin']);
		//sort($this->filesToLoad['css']);
		//sort($this->filesToLoad['requireCss']);
		


		if ($this->filesToLoad['language'] || $this->filesToLoad['plugin'])
		{
			


		}

		
		
//echo ' 4654sd48sa7d98sD81s8 $doCss <pre>' . print_r($doCss, true) . '</pre>';#exit;
echo ' 4654sd48sa7d98sD81s8 $doJs <pre>' . print_r($doJs, true) . '</pre>';#exit;
#
#echo ' 4654sd48sa7d98sD81s8 $this->filesToLoad <pre>' . print_r($this->filesToLoad, true) . '</pre>';exit;

return;




############return;
		$combine = $this->params->get('combine', 1);

		/*
		$muster   = array();
		$muster[] = '/';
		$muster[] = $tag_;
		$muster[] = '\s+class=("|\'|)brush:([a-zA-Z0-9#]+)';
		$muster[] = '/';
		$muster   = implode('', $muster);
		*/

		$muster = '/' . $tag_ . '\s+class=("|\'|)brush:([a-zA-Z0-9#]+)' . '/';

		if (!preg_match_all($muster, $article->text, $neededBrushes))
		{
			return;
		}

		$neededBrushes = array_flip($neededBrushes[2]);

		$config = array();
		$combineSuccess = 0;
		$min = JDEBUG ? '' : '.min';
		$version = JDEBUG ? time() : 'auto';

		JLoader::register('PrismHighlighterGhsvsHelper', __DIR__ . '/helper.php');

		$aliasesBrushfileMap = PrismHighlighterGhsvsHelper::getAliasesBrushfileMap();

		// Fatal error.
		if ($aliasesBrushfileMap === false)
		{
			return;
		}

		// Needed brushes in current article.
		/*
		Array
		(
    [php] => shBrushPhp
    [text] => shBrushPlain
    [xml] => shBrushXml
		)
		*/
		$neededBrushes = array_unique(array_intersect_key($aliasesBrushfileMap, $neededBrushes));

		if (empty($neededBrushes))
		{
			return;
		}
		
		// js files that shall be loaded or combined.
		$brushFiles = array();
		
		// parts of filename of combined files. 
		$combinedFilename = array();

		foreach ($neededBrushes as $alias => $brush)
		{
			$combinedFilename[] = trim(ucfirst($alias));
			$brushFiles[]      = $brush . $min . '.js';
		}

		// Nothing todo. Maybe error.
		if (!$brushFiles)
		{
			return;
		}
		elseif ($combine && $combinedFilename)
		{
			sort($combinedFilename, SORT_NATURAL | SORT_FLAG_CASE);

			// Filename for combined bruskes js. E.g. Core_PhpTextXmlBrushes.min.js.
			$combinedFilename = '_combiByPlugin/' . 'Core_' . implode('', $combinedFilename) . 'Brushes' . $min . '.js';

			// Combined brushes file or override exists?
			$fileExist = HTMLHelper::_('script',
				$this->basepath . '/' . $combinedFilename,
				array('relative' => true, 'pathOnly' => true)
			);
			
			// If it exists it already contains Core and xregexp.
			if ($fileExist)
			{
				// Now we need only this file and can forget other brushes.
				$brushFiles = array($combinedFilename);
				$combineSuccess = 1;
			}
			// We have to create a new combined file.
			else
			{
				$contents = array();

				// ToDo: Add Copyright if minified.
				$contents[] = file_get_contents(
					JPATH_SITE . '/media/' . $this->basepath . '/js/xregexp-2.0.0/xregexp' . $min . '.js'
				);

				// CORE-File or CORE-File-override exists?
				$fileExist = HTMLHelper::_('script',
					$this->basepath . '/' . 'shCore' . $min . '.js',
					array('relative' => true, 'pathOnly' => true)
				);
				
				// Fatal error.
				if (!$fileExist)
				{
					return;
				}
				
				// ToDo: Add Copyright.
				$contents[] = file_get_contents(JPATH_SITE . $fileExist);

				foreach ($brushFiles as $file)
				{
					// File or override exists?
					$fileExist = HTMLHelper::_('script',
						$this->basepath . '/' . $file,
						array('relative' => true, 'pathOnly' => true)
					);

					if ($fileExist)
					{
						$contents[] = file_get_contents(JPATH_SITE . $fileExist);
					}
					// Forget combining.
					else
					{
						$combineSuccess = 0;
						$contents = false;
						break;
					}
				}

				if ($contents)
				{
					$contents = implode(";\n;", $contents);

					if (
						File::write(JPATH_SITE . '/media/' . $this->basepath . '/js/' . $combinedFilename, $contents)
					){
						// We just need 1 file now. The combined one.
						$brushFiles = array($combinedFilename);
						$combineSuccess = 1;
					}
					else
					{
						$combineSuccess = 0;
					}
				}
			}
		} // END elseif ($combine && $combinedFilename)

		// Combination not wanted or failed. Load files separately from plugin core.
		$filesCore = array();

		if (!$combine || !$combineSuccess)
		{
			$filesCore = array(
				'xregexp-2.0.0/xregexp' . $min . '.js',
				'shCore' . $min . '.js',
			);
		}
		
		// $brushFiles contains 1 cmbined or several files.
		foreach (array_merge($filesCore, $brushFiles) as $file)
		{
			HTMLHelper::_('script',
				$this->basepath . '/' . $file,
				array('relative' => true, 'version' => $version)
			);
		}

		// Load configuration JS.
		if ($this->params->get('stripbrs', 0))
		{
			$config[] = 'SyntaxHighlighter.config.stripBrs = true;';
		}

		$config[] = 'SyntaxHighlighter.config.tagName="' . $tag . '";';

		if (! $this->params->get('auto-links', 0))
		{
		 $config[] = 'SyntaxHighlighter.defaults["auto-links"] = false;';
		}

		if (($cname = trim($this->params->get('class-name', ''))))
		{
			$config[] = 'SyntaxHighlighter.defaults["class-name"] = "' . $cname . '";';
		}

		// Nonsense.
		$config[] = 'SyntaxHighlighter.defaults["toolbar"] = false;';

		if (! $this->params->get('gutter', 1))
		{
			$config[] = 'SyntaxHighlighter.defaults["gutter"] = false;';
		}

		if (! $this->params->get('quick-code', 1))
		{
			$config[] = 'SyntaxHighlighter.defaults["quick-code"] = false;';
		}

 		$js = implode('', $config) . ';SyntaxHighlighter.all();';
 		Factory::getDocument()->addScriptDeclaration($js);

		if ($file = $this->params->get('stylesheets', 'shCoreDefault'))
		{
			HTMLHelper::_('stylesheet',
				$this->basepath . '/' . $file . $min . '.css',
				array('relative' => true, 'version' => $version)
			);
		}

		// Custom CSS.
		$customCssRules = $this->params->get('customCss', null);
		$css = '';

		if (is_object($customCssRules) && count(get_object_vars($customCssRules)))
		{
			foreach ($customCssRules as $customCssRule)
			{
				$customCssRule = new Registry($customCssRule);

				if (
					$customCssRule->get('active', 0)
					&& ($selector = trim($customCssRule->get('selector', '')))
					&& ($cssRules = trim($customCssRule->get('cssRules', '')))
				){
			  	$css .= $selector . '{' . $cssRules  . '}';
				}
			}
		}
		
		if ($css)
		{
			$css = str_replace(array("\n", "\r", "\t"), '', $css);
			Factory::getDocument()->addStyleDeclaration($css);
		}

		return true;
	}
}
