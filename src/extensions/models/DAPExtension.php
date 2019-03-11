<?php

namespace Grasenhiller\DAP\Extensions\Models;

use InvalidArgumentException;
use SilverStripe\CMS\Forms\SiteTreeURLSegmentField;
use SilverStripe\Control\Controller;
use SilverStripe\Control\Director;
use SilverStripe\Forms\FieldList;
use SilverStripe\ORM\DataExtension;
use SilverStripe\View\Parsers\URLSegmentFilter;

class DAPExtension extends DataExtension {

	private static $db = [
		'URLSegment' => 'Varchar(255)',
	];

	public function onBeforeWrite() {
		parent::onBeforeWrite();

		$owner = $this->owner;
		$itemDAPConfig = $owner->config()->get('dap_options');

		if ($itemDAPConfig['id_or_urlsegment'] == 'urlsegment') {
			if (
				!$owner->URLSegment
				|| $owner->isChanged('Title', 2)
			) {
				if (isset($owner->config()->get('db')['MenuTitle'])) {
					$stringForURLSegment = $owner->MenuTitle;
				} else {
					$stringForURLSegment = $owner->Title;
				}

				$owner->URLSegment = $owner->dapGenerateURLSegment($stringForURLSegment);
			} else if ($owner->isChanged('URLSegment', 2)) {
				$owner->URLSegment = $owner->dapGenerateURLSegment($owner->URLSegment);
			}
		}
	}

	/**
	 * generate the urlsegment
	 *
	 * @param $baseString
	 *
	 * @return mixed
	 */
	public function dapGenerateURLSegment($baseString) {
		$owner = $this->owner;
		$filter = URLSegmentFilter::create();
		$urlSegment = $filter->filter($baseString);

		$owner->extend('updateDAPGenerateURLSegment', $urlSegment, $baseString);

		return $owner->dapValidateURLSegment($urlSegment);
	}

	/**
	 * validate the given urlsegment and if invalid return a new and valid one
	 *
	 * @param     $urlSegment
	 * @param int $count
	 *
	 * @return string
	 */
	public function dapValidateURLSegment($urlSegment, $count = 2) {
		$owner = $this->owner;
		$class = $owner->ClassName;

		$existingItem = $class::get()
			->exclude('ID', $owner->ID)
			->find('URLSegment', $urlSegment);

		if ($existingItem) {
			$urlSegment = preg_replace('/-[0-9]+$/', null, $urlSegment) . '-' . $count;
			$count++;
			return $owner->dapValidateURLSegment($urlSegment, $count);
		}

		return $urlSegment;
	}

	public function Link($action = null) {
		$owner = $this->owner;

		if ($owner->hasMethod('getDAPHolder')) {
			$holder = $owner->getDAPHolder();

			$itemDAPConfig = $owner->config()->get('dap_options');

			if($itemDAPConfig['id_or_urlsegment'] == 'urlsegment') {
				$segment = $owner->URLSegment;
			} else {
				$segment = $owner->ID;
			}

			return $holder->Link($itemDAPConfig['controller_action'] . '/' . $segment . '/' . $action);
		}
	}

	/**
	 * @param null $action
	 *
	 * @return string
	 */
	public function AbsoluteLink($action = null) {
		return Director::absoluteURL($this->Link($action));
	}

	/**
	 * @param FieldList $fields
	 */
	public function updateCMSFields(FieldList $fields) {
		$owner = $this->owner;
		$owner->validateDAPSettings();
		$itemDAPConfig = $owner->config()->get('dap_options');

		if ($itemDAPConfig['id_or_urlsegment'] == 'urlsegment') {
			$after = '';

			if ($fields->dataFieldByName('MenuTitle')) {
				$after = 'MenuTitle';
			} else if ($fields->dataFieldByName('Title')) {
				$after = 'Title';
			}

			$prefix = '';
			
			if ($owner->getDAPHolder() && $owner->getDAPHolder()->exists()) {
				$prefix = $owner->getDAPHolder()->Link() . $itemDAPConfig['controller_action'] . '/'
			}
			
			$fields->insertAfter(
				$after,
				SiteTreeURLSegmentField::create('URLSegment', 'URL-Segment')
					->setURLPrefix($prefix)
			);
		}
	}

	/**
	 * a little bit of validation to help the developer
	 */
	public function validateDAPSettings() {
		$owner = $this->owner;
		$itemDAPConfig = $owner->config()->get('dap_options');
		$itemClass = $owner->ClassName;

		if (!$itemDAPConfig) {
			throw new InvalidArgumentException('Please add DAP configuration for your data object "' . $itemClass . '"', E_USER_ERROR);
		} else if (!isset($itemDAPConfig['id_or_urlsegment']) || !isset($itemDAPConfig['controller_action'])) {
			throw new InvalidArgumentException('Please add at least the "id_or_urlsegment" and "controller_action" option for your data object "' . $itemClass . '"', E_USER_ERROR);
		} else if ($itemDAPConfig['id_or_urlsegment'] != 'urlsegment' && $itemDAPConfig['id_or_urlsegment'] != 'id') {
			throw new InvalidArgumentException('Please define "urlsegment" or "id" for the option "id_or_urlsegment" of your data object "' . $itemClass . '"', E_USER_ERROR);
		}  else if ($itemDAPConfig['id_or_urlsegment'] == 'urlsegment' && !isset($owner->config()->get('db')['Title']) && !isset($owner->config()->get('db')['MenuTitle'])) {
			throw new InvalidArgumentException('You need an db field called "MenuTitle" or "Title" in order to create an urlsegment on your ' . $itemClass . '" class', E_USER_ERROR);
		}
	}

	public function DAPLinkingMode() {
		$owner = $this->owner;
		$dapConfig = $owner->config()->get('dap_options');

		if ($dapConfig && isset($dapConfig['id_or_urlsegment']) && isset($dapConfig['controller_action'])) {
			$ctrl = Controller::curr();
			$r = $ctrl->getRequest();
			$action = $ctrl->getAction();

			if ($action == 'dapShow' && $dapConfig['controller_action'] == $r->param('Action')) {
				if ($dapConfig['id_or_urlsegment'] == 'urlsegment') {
					$field = 'URLSegment';
				} else if ($dapConfig['id_or_urlsegment'] == 'id') {
					$field = 'ID';
				}

				if ($owner->$field == $r->param('ID')) {
					return 'current';
				}
			}
		}

		return 'link';
	}
}
