<?php
#namespace Joomla\CMS\Form\Field;

defined('_JEXEC') or die;

use Joomla\CMS\Form\FormHelper;

FormHelper::loadFieldClass('list');

/**
 * Form Field to load a list of stylesheets from coreStylesheets.json
 */
class plgContentPrismHighlighterGhsvsFormFieldUserMustSelect extends JFormFieldList
{
	/**
	 * The form field type.
	 *
	 * @var    string
	 * @since  3.2
	 */
	public $type = 'userMustSelect';

	/**
	 * Cached array of the category items.
	 *
	 * @var    array
	 * @since  3.2
	 */
	protected static $options = [];

	/**
	 * Method to get the options to populate list
	 *
	 * @return  array  The field option objects.
	 *
	 * @since   3.2
	 */
	protected function getOptions()
	{
		$hash = md5($this->element);

		if (!isset(static::$options[$hash]))
		{
			static::$options[$hash] = parent::getOptions();
			$options = explode(',', $this->element['userMustSelect']);

			foreach ($options as $key => $value)
			{
				$options[$value] = $value;
				unset($options[$key]);
			}

			static::$options[$hash] = array_merge(static::$options[$hash], $options);
		}

		return static::$options[$hash];
	}
}
