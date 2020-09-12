<?php
/*
 * Displays available PrismJs languages; and useable aliases to load them.
 * <field name="Languagesandaliases"
    type="Languagesandaliases"
    label="PLG_CONTENT_PRISMHIGHLIGHTERGHSVS_LANGUAGESANDALIASES"
    hiddenLabel="true"/>
 *
 * @since  3.9.8
*/

defined('JPATH_PLATFORM') or die;

use Joomla\CMS\Form\FormField;
use Joomla\CMS\Language\Text;

class JFormFieldLanguagesandaliases extends FormField
{
	protected $type = 'Languagesandaliases';

	protected function getInput()
	{
		$title = $this->element['label'] ? (string) $this->element['label'] : ($this->element['title'] ? (string) $this->element['title'] : '');
		$heading = $this->element['heading'] ? (string) $this->element['heading'] : 'h4';

		\JLoader::register('PrismHighlighterGhsvsHelper', dirname(__DIR__) . '/helper.php');

		if (($description = \PrismHighlighterGhsvsHelper::getBrushfileAliasesMap()) === false)
		{
			$description = 'Sorry! Fatal error! Reload the page. If error persits it would be nice if you inform the developer of this plugin.';
		}
		else
		{
			$table = array('<table class="table table-striped table-bordered">');

			foreach ($description as $brush => $aliases)
			{
				$brush = str_replace('shBrush', '', $brush);
				$aliases = implode('<br>', $aliases);
				$table[] = '<tr><td>' . $brush . '</td><td>' . $aliases . '</td></tr>';
			}

			$table[] = '</table>';
			$description = implode('', $table);
		}

		$class = !empty($this->class) ? ' class="' . $this->class . '"' : '';
		$close = (string) $this->element['close'];

		$html = array();

		if ($close)
		{
			$close = $close == 'true' ? 'alert' : $close;
			$html[] = '<button type="button" class="close" data-dismiss="' . $close . '">&times;</button>';
		}

		$html[] = !empty($title) ? '<' . $heading . '>' . Text::_($title) . '</' . $heading . '>' : '';
		$html[] = !empty($description) ? $description : '';
		return '</div><div ' . $class . '>' . implode('', $html);
	}

	protected function getLabel()
	{
		return '';
	}
}
