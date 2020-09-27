<?php
#namespace Joomla\CMS\Form\Field;

defined('JPATH_PLATFORM') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Form\FormHelper;

FormHelper::loadFieldClass('list');

/**
 * Form Field to load a list of stylesheets from coreStylesheets.json
 */
class plgContentPrismHighlighterGhsvsFormFieldStylesheets extends JFormFieldList
{
	/**
	 * The form field type.
	 *
	 * @var    string
	 * @since  3.2
	 */
	public $type = 'stylesheets';

	/**
	 * Cached array of the category items.
	 *
	 * @var    array
	 * @since  3.2
	 */
	protected static $options = array();

	/**
	 * Method to get the options to populate list
	 *
	 * @return  array  The field option objects.
	 *
	 * @since   3.2
	 */
	protected function getOptions()
	{
		\JLoader::register('PrismHighlighterGhsvs', dirname(__DIR__) . '/helper.php');
		
		$coreStyleSheets = \PrismHighlighterGhsvs::getCoreStylesheets();
		$hash = md5($this->element);

		if (!isset(static::$options[$hash]))
		{
			static::$options[$hash] = parent::getOptions();
			
			$options = array();
			
			foreach ($coreStyleSheets as $value => $name)
			{
				$do = new \stdClass;
				$do->value = $value;
				$do->text = $name;
				$options[] = $do;
			}
			static::$options[$hash] = array_merge(static::$options[$hash], $options);
		}
		return static::$options[$hash];
	}
}
