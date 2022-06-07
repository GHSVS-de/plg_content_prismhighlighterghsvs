<?php
defined('_JEXEC') or die;

/**
 * Article migration from
 * plg_content_syntaxhighlighterghsvs/plg_editors-xtd_syntaxhighlighterghsvs
 * to
 * plg_content_prismhighlighterghsvs/plg_editors-xtd_prismhighlighterghsvs
 *
 * Replace class="brush: xyz" and other attributes with Prismjs classes and pre/code
 * blocks.
*/

//public function insertObject($table, &$object, $key = null)
//public function updateObject($table, &$object, $key, $nulls = false)

$db = JFactory::getDbo();

$query = $db->getQuery(true);
$query->select('*')->from('#__content');
$db->setQuery($query);
$articles = $db->loadObjectList();

$tag_ = '<pre';
//$muster = '/<pre\s+class=("|\'|)brush:([a-zA-Z0-9#]+)/';
$json = JPATH_SITE . '/media/plg_content_prismhighlighterghsvs/json/aliasLanguageMap.json';

$json = json_decode(file_get_contents($json), true);

$muster = '/(<pre\s+class=("|\'|)brush:([a-zA-Z0-9#]+)[^>]+>)(.*?)<\/pre>/s';

$replace = [
	'text' => 'markup',
];

foreach ($articles as $key => $article)
{
	$checks = ['introtext', 'fulltext'];

	$show = false;

	foreach ($checks as $check)
	{
		$text = $article->$check;

		if (
			strpos($text, $tag_ . ' ') === false
			|| (
				strpos($text, $tag_ . ' class="brush:') === false
				&& strpos($text, $tag_ . ' class=\'brush:') === false
				&& strpos($text, $tag_ . ' class=brush:') === false
			)
		) {
			continue;
		}

		/*
		0 : uninteressant
		1 : <pre .... >
		2 : uninteressant
		3: language Alias
		4: Content innerhalb pre
		*/

		if (!preg_match_all($muster, $text, $foundBrushes, PREG_SET_ORDER))
		{
			continue;
		}

		foreach ($foundBrushes as $key => $found)
		{
			#echo ' 4654sd48sa7d98sD81s8d71dsa <pre>' . print_r($found, true) . '</pre>';exit;
			$alias = $found[3];

			if (!isset($json[$alias]))
			{
				$alias_ = $replace[$alias];
				$languageClass = 'language-' . $alias_;
			}
			else
			{
				$languageClass = 'language-' . $alias;
			}

			$foundBrushes[$key][1] = str_replace(
				[
					'brush:' . $alias . ';',
					'brush:' . $alias,
				],
				$languageClass . ' line-numbers"',
				$foundBrushes[$key][1]
			);

			if (strpos($foundBrushes[$key][1], 'highlight') !== false)
			{
				$foundBrushes[$key][1] = str_replace('highlight: [', 'highlight:[', $foundBrushes[$key][1]);

				$muster2 = '/highlight:\[(.*?)\]/';

				preg_match($muster2, $foundBrushes[$key][1], $highlights);
				#echo ' 4654sd48sa7d98sD81s8d71dsa <pre>' . print_r($highlights, true) . '</pre>' . PHP_EOL . PHP_EOL;#exit;
				$newhighlite = 'data-line="' . $highlights[1] . '"';
				#echo ' 4654sd48sa7d98sD81s8d71dsa <pre>' . print_r($newhighlite, true) . '</pre>' . PHP_EOL . PHP_EOL;#exit;
				$foundBrushes[$key][1] = str_replace(
					[
						$highlights[0] . ';',
						$highlights[0],
					],
					[
					$newhighlite,
					$newhighlite,
					],
					$foundBrushes[$key][1]
				);
			}

			if (strpos($foundBrushes[$key][1], 'first-line') !== false)
			{
				$foundBrushes[$key][1] = str_replace('first-line: ', 'first-line:', $foundBrushes[$key][1]);

				$muster3 = '/first-line:(\d*)/';

				preg_match($muster3, $foundBrushes[$key][1], $firstlines);

				$newfl = 'data-start="' . $firstlines[1] . '"';
				$foundBrushes[$key][1] = str_replace(
					[
						$firstlines[0] . ';',
						$firstlines[0],
					],
					[
					$newfl,
					$newfl,
					],
					$foundBrushes[$key][1]
				);
			}

			$foundBrushes[$key][1] = str_replace('title: ', 'title=', $foundBrushes[$key][1]);
			$foundBrushes[$key][1] = str_replace('""', '"', $foundBrushes[$key][1]);
			$foundBrushes[$key][1] = str_replace('\'"', "'", $foundBrushes[$key][1]);

			$new = $foundBrushes[$key][1] . '<code>' . $foundBrushes[$key][4] . '</code></pre>';

			// PROTOKOLL für abschließenden Check!!!
			file_put_contents(
				JPATH_SITE . '/migration.log',
				$article->id . ':' . $article->title . PHP_EOL . $foundBrushes[$key][1] . PHP_EOL . PHP_EOL,
				FILE_APPEND
			);

			$show = true;
			$article->$check = str_replace($foundBrushes[$key][0], $new, $article->$check);
		} //foreach foundBrushes

		if ($show)
		{
			#echo " 4654sd48s $article->title <pre>" . print_r($article->$check, true) . '</pre>' . PHP_EOL;#exit;
		}
	}//foreach ($checks as $check)

	if ($show)
	{
		//public function updateObject($table, &$object, $key, $nulls = false)
		$db->updateObject('#__content', $article, 'id');
	}

	#echo ' 4654sd48sa7d98sD81s8d71dsa <pre>' . print_r($article, true) . '</pre>';exit;
}//foreach ($articles as $key => $article)
echo 'DONE';
exit;
