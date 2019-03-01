<?php

namespace Grasenhiller\DAP\Extensions\Models;

use SilverStripe\CMS\Forms\SiteTreeURLSegmentField;
use SilverStripe\Control\Director;
use SilverStripe\Core\Config\Config;
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

		if (
			(!$owner->URLSegment && $owner->Title)
			|| (isset($owner->getConfiguration()['id_instead_of_slug']) && $owner->getConfiguration()['id_instead_of_slug'] && $owner->isChanged('Title', 2))
		) {
			$owner->URLSegment = $owner->generateURLSegment($owner->Title);
		} else if ($owner->isChanged('URLSegment', 2)) {
			$owner->URLSegment = $owner->generateURLSegment($owner->URLSegment);
		}

		$count = 2;

		while(!$owner->validURLSegment()) {
			$owner->URLSegment = preg_replace('/-[0-9]+$/', null, $owner->URLSegment) . '-' . $count;
			$count++;
		}
	}

	public function validURLSegment() {
		$owner = $this->owner;
		$class = $owner->ClassName;

		$existingItem = $class::get()
			->filter('URLSegment', $owner->URLSegment)
			->exclude('ID', $owner->ID)
			->first();

		return !($existingItem);
	}

	public function generateURLSegment($title) {
		$owner = $this->owner;
		$filter = URLSegmentFilter::create();
		$t = $filter->filter($title);

		if (!$t || $t == '-' || $t == '-1') $t = $owner->getConfiguration()['action'] . '-' . $owner->ID;

		$owner->extend('updateURLSegment', $t, $title);

		return $t;
	}

	public function Link() {
		$owner = $this->owner;

		if ($owner->hasMethod('getHolderPage')) {
			$holder = $owner->getHolderPage();
		} else {
			$holder = false;
		}

		if ($holder) {
			$page = $holder;
		} else {
			$page = Director::get_current_page();
		}

		$config = $this->owner->getConfiguration();

		if (!isset($config['id_instead_of_slug']) || (isset($config['id_instead_of_slug']) && !$config['id_instead_of_slug'])) {
			$segment = $owner->URLSegment;
		} else {
			$segment = $owner->ID;
		}

		return $page->Link($owner->getConfiguration()['action'] . '/' . $segment);
	}

	public function AbsoluteLink() {
		return Director::absoluteURL($this->Link());
	}

	public function updateCMSFields(FieldList $fields) {
		if (!$this->owner->getConfiguration()['id_instead_of_slug']) {
			$fields->insertBefore(
				SiteTreeURLSegmentField::create('URLSegment', 'URL')
					->setURLPrefix($this->owner->getConfiguration()['action'] . '/')
				, '');
		}
	}

	public function getConfiguration() {
		$configs = Config::inst()->get('DataObjectLinkMapping', 'mappings');

		foreach ($configs as $action => $config) {
			if ($config['class'] == $this->owner->ClassName) {
				$match = $configs[$action];
				$match['action'] = $action;
				return $match;
			}
		}
	}
}