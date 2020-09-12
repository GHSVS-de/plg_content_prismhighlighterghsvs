<?php
defined('_JEXEC') or die;

use Joomla\CMS\Filesystem\Folder;
use Joomla\CMS\Filesystem\File;

class PrismHighlighterGhsvsHelper
{
	protected static $basepath = 'media/plg_content_prismhighlighterghsvs';
	
	/**
	 * Path of JSON file with aliases to language shortcut map. 
	 * 
	 * @var string
	*/
	protected static $aliasLanguageMapJson;
	
	protected static $pluginCssMapJson;
	protected static $renewalFile;

	protected static $loaded;
	
	/**
	 * Remember the starting point of dependency detection of a plugin. 
	 * 
	 * @var string
	 */
	protected static $debuggerFirst;
	
  public static function init()
	{
		if (!isset(self::$loaded[__METHOD__]))
		{
			self::$aliasLanguageMapJson = JPATH_SITE . '/' . self::$basepath . '/json/aliasLanguageMap.json';
			self::$pluginCssMapJson = JPATH_SITE . '/' . self::$basepath . '/json/pluginCssMapJson.json';
			self::$renewalFile = JPATH_SITE . '/' . self::$basepath . '/renewal.log';
			self::$loaded[__METHOD__] = 1;
		}
  }
	
	/**
   * Detect all languages plus possible aliases.
	 * Collect in array [markup => markup, html => markup, ....]
	 * Write collection to JSON file aliasLanguageMap.json.
	 * 
	 * @return boolean True if JSON file exists afterwards.
	 */
	public static function mapsToFiles()
	{
		self::init();

		$aliasLanguageMap = [];
		$pluginCssMap = [];

		if (!is_file(self::$aliasLanguageMapJson) || !is_file(self::$pluginCssMapJson))
		{
			if (!($components = @file_get_contents(JPATH_SITE . '/' . self::$basepath . '/prismjs/components.json')))
			{
				return false;
			}

			self::renewal(null, true);

			$components = json_decode(
				$components,
				// asArray = 
				true
			);

			$exclude = ['meta' => 1];
			
			// aliasLanguageMap START
			foreach ($components['languages'] as $language => $infos)
			{
				if (isset($exclude[$language]))
				{
					continue;
				}
				
				$aliasLanguageMap[$language]['alias'] = $language;
				
				if (isset($infos['title']))
				{
					$aliasLanguageMap[$language]['aliasTitle'] = $infos['title'];
				}
				else
				{
					$aliasLanguageMap[$language]['aliasTitle'] = $language;
				}
				
				if (isset($infos['alias']))
				{
					$infos['alias'] = (array) $infos['alias'];
					
					foreach ($infos['alias'] as $alias)
					{
						$aliasLanguageMap[$alias]['alias'] = $language;
						
						if (isset($infos['aliasTitles'][$alias]))
						{
							$aliasLanguageMap[$alias]['aliasTitle'] = $infos['aliasTitles'][$alias];
						}
						else
						{
							$aliasLanguageMap[$alias]['aliasTitle'] = $alias;
						}
					}
				}
				
				// Reset array.
				$dependencies = array();
				
				// Reset starting point.
				self::$debuggerFirst = $language;

				self::getDependenciesOfLanguage($components, $language, $dependencies);

				if ($dependencies)
				{
					$aliasLanguageMap[$language] = \array_merge($aliasLanguageMap[$language], $dependencies);
				}
			}

			if ($aliasLanguageMap)
			{
				File::write(self::$aliasLanguageMapJson, json_encode($aliasLanguageMap, JSON_PRETTY_PRINT));
			}
			else
			{
				return false;
			}
			// aliasLanguageMap END
			
			// pluginCssMap START
			foreach ($components['plugins'] as $plugin => $infos)
			{
				if (isset($exclude[$plugin]))
				{
					continue;
				}
				
				unset($infos['owner']);
				if (!isset($infos['noCSS']))
				{
					$infos['noCSS'] = 0;
				}
				else
				{
					$infos['noCSS'] = 1;
				}
				
				// Special dependencies.
				if ($plugin === 'copy-to-clipboard')
				{
					$infos['requireVendorJs'] = 'clipboard/clipboard';
				}
				
				// Reset array.
				$dependencies = array();
				
				// Reset starting point.
				self::$debuggerFirst = $plugin;

				self::getDependenciesOfPlugin($components, $plugin, $dependencies);

				if ($dependencies)
				{
					$infos = \array_merge($infos, $dependencies);
				}

				$pluginCssMap[$plugin] = $infos;
			}

			if ($pluginCssMap)
			{
				File::write(self::$pluginCssMapJson, json_encode($pluginCssMap, JSON_PRETTY_PRINT));
			}
			else
			{
				return false;
			}
			// pluginCssMap END
		}

		if (is_file(self::$aliasLanguageMapJson) && is_file(self::$pluginCssMapJson))
		{
			return true;
		}
		return false;
	}

	public static function getAliasLanguageMap()
	{
		if (self::mapsToFiles() === true)
		{
			$content = file_get_contents(self::$aliasLanguageMapJson);
			return json_decode($content, true);
		}
		return false;
	}

	public static function getPluginCssMap()
	{
		if (self::mapsToFiles() === true)
		{
			$content = file_get_contents(self::$pluginCssMapJson);
			return json_decode($content, true);
		}
		return false;
	}
	/**
	* Collects recursively dependencies/requirements (other plugins OR languages) of a single plugin.
	*
	* @param string $toCheck Plugin or language name
	* @param array $components Plugins and languages collection from Prism file components.json.
	* @param array $collect Collection reference of requirements of an initially passed plugin.
	*
	* @return void
	*/
	protected static function getDependenciesOfPlugin(
		$components,
		$toCheck,
		&$collect,
		$first = false
	){
		$doCollect = $toCheck !== self::$debuggerFirst;

		if (!empty($components['plugins'][$toCheck]))
		{
			$requireKey = 'plugins';
		}
		elseif (!empty($components['languages'][$toCheck]))
		{
			$requireKey = 'languages';
		}
		
		if ($doCollect && $requireKey)
		{
			$collect['require' . ucfirst($requireKey)][] = $toCheck;
		}
		
		if (isset($components[$requireKey][$toCheck]['require']))
		{
			foreach ((array) $components[$requireKey][$toCheck]['require'] as $require)
			{
				self::getDependenciesOfPlugin($components, $require, $collect, $first);
			}
		}
	}

	/**
	* Collects recursively dependencies/requirements (other plugins OR languages) of a single plugin.
	*
	* @param string $toCheck Plugin or language name
	* @param array $components Plugins and languages collection from Prism file components.json.
	* @param array $collect Collection reference of requirements of an initially passed plugin.
	*
	* @return void
	*/
	protected static function getDependenciesOfLanguage(
		$components,
		$toCheck,
		&$collect,
		$first = false
	){
		$doCollect = $toCheck !== self::$debuggerFirst;

		if (!empty($components['languages'][$toCheck]))
		{
			$requireKey = 'languages';
		}
		// Needed for languages?????
		elseif (!empty($components['plugins'][$toCheck]))
		{
			$requireKey = 'plugins';
		}
		
		if ($doCollect && $requireKey)
		{
			$collect['require' . ucfirst($requireKey)][] = $toCheck;
		}
		
		if (isset($components[$requireKey][$toCheck]['require']))
		{
			foreach ((array) $components[$requireKey][$toCheck]['require'] as $require)
			{
				self::getDependenciesOfPlugin($components, $require, $collect, $first);
			}
		}
	}
	/*
	 * Does a string contain language- and/or lang- depending on the plugin parameters.
	 *
	 * @param string|array $what Haystack(s) to search in.
	 * @param integer $supportLang Setting in plugin. O:Only language-xxx|1:lang-xxx and language-xxx|2:only lang-xxx
	 *
	 * @return boolean True if found.
	 */
	public static function strposCheckForLanguageClass($what, $supportLang)
	{
		if (is_array($what))
		{
			$what = implode(' ', $what);
		}

		switch ($supportLang)
		{
			case 0:
				if (strpos($what, 'language-') !== false)
				{
					return true;
				}
				return false;
				break;
			case 2:
				if (strpos($what, 'lang-') !== false)
				{
					return true;
				}
				return false;
				break;
			default:
				if (strpos($what, 'lang-') !== false || strpos($what, 'language-') !== false)
				{
					return true;
				}
				return false;
		}
	}

	public static function renewal($params, $force = false)
	{
		// Just once per page load.
		if (!isset(self::$loaded['renewalDone']))
		{
			if ($force || self::renewalCheck($params))
			{
				$root = JPATH_SITE . '/' . self::$basepath;
	
				$forceRenewals = [
					'/js/_combiByPlugin',
					'/css/_combiByPlugin',
				];
	
				foreach ($forceRenewals as $item)
				{
					if (is_dir($root . $item))
					{
						Folder::delete($root . $item);
					}
				}
	
				$forceRenewals = [
					'/json/aliasLanguageMap.json',
					'/json/pluginCssMapJson.json',
				];
	
				foreach ($forceRenewals as $item)
				{
					if (is_file($root . $item))
					{
						File::delete($root . $item);
					}
				}
				
				if ($force)
				{
					self::init();
					file_put_contents(self::$renewalFile, 0);
				}
			}
			self::$loaded['renewalDone'] = 1;
		}
		return true;
	}

	protected static function renewalCheck($params)
	{
		if (!isset(self::$loaded['forceRenewal']))
		{
			self::init();
			$firstDate = @file_get_contents(self::$renewalFile);
			$firstDate = (int) $firstDate;
			$renewalDays = $params->get('forceRenewalDays', 90) * 24 * 60 * 60;
			$lastDate = time() + $renewalDays;
			$writeIt = !($firstDate === -1);

			if ($params->get('forceRenewal', 0) === 1)
			{
				self::$loaded['forceRenewal'] = true;
				
				if ($writeIt)
				{
					$write = -1;
				}
			}
			// Is always reset to 0 after updates of extension.
			// or when file deleted.
			elseif ($firstDate === 0)
			{
				self::$loaded['forceRenewal'] = true;
				$write = -1;
				
				if ($renewalDays)
				{
					$write = time() + $renewalDays;
				}
				elseif ($writeIt)
				{
					$write = -1;
				}
			}
			// User wants no renewal.
			elseif ($renewalDays === 0)
			{
				self::$loaded['forceRenewal'] = false;

				if ($writeIt)
				{
					$write = -1;
				}
			}
			elseif (time() > ($firstDate + $renewalDays))
			{
				self::$loaded['forceRenewal'] = true;
				$write = time() + $renewalDays;
			}
			else
			{
				self::$loaded['forceRenewal'] = false;
			}
			
			if (isset($write))
			{
				file_put_contents(self::$renewalFile, $write);
			}
		}
		return self::$loaded['forceRenewal'];
	}

	public static function getBrushfileAliasesMap($forceRefresh = false)
	{
		if (self::brushesToFiles($forceRefresh))
		{
			$content = file_get_contents(JPATH_SITE . '/' . self::$basepath . '/js/_combiByPlugin/brushfileAliasesMap.json');
			return json_decode($content, true);
		}
		return false;
	}
}