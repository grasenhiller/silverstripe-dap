<?php

namespace Grasenhiller\DAP\Extensions\Controllers;

use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Extension;
use SilverStripe\ORM\ArrayList;
use SilverStripe\Security\Security;
use SilverStripe\View\ArrayData;
use SilverStripe\View\SSViewer;

class DAPExtension extends Extension {

	private $config;

	private static $allowed_actions = [
		'show'
	];

	private static $url_handlers = [
		'//$Action!/$ID!' => 'show',
	];

	public function getItem() {
		$r = $this->owner->request;
		$action = $r->allParams()['Action'];
		$url = $r->allParams()['ID'];

		if ($action && $url) {
			$config = Config::inst()->get('DataObjectLinkMapping', 'mappings');

			if ($config && isset($config[$action])) {
				$config = $config[$action];

				if ($config) {
					$this->config = $config;

					if (!isset($config['id_instead_of_slug']) || (isset($config['id_instead_of_slug']) && !$config['id_instead_of_slug'])) {
						$searchField ='URLSegment';
					} else {
						$searchField ='ID';
					}

					$item = $config['class']::get()->find($searchField, $url);

					if ($item) {
						return $item;
					}
				}
			}
		}
	}

	public function show() {
		$item = $this->getItem();

		if ($item) {
			$access = $item->canView();
			$this->owner->extend('updateShowAccess', $item, $access);

			if ($access) {
				$parent = Director::get_current_page();

				if ($item->hasMethod('getBreadcrumbsMaxDepth')) {
					$maxDepth = $item->getBreadcrumbsMaxDepth();
				} else {
					$maxDepth = 20;
				}

				if ($item->hasMethod('getBreadcrumbsUnlinked')) {
					$unlinked = $item->getBreadcrumbsUnlinked();
				} else {
					$unlinked = false;
				}

				if ($item->hasMethod('getBreadcrumbsStopAtPageType')) {
					$stopAtPageType = $item->getBreadcrumbsStopAtPageType();
				} else {
					$stopAtPageType = false;
				}

				if ($item->hasMethod('getBreadcrumbsShowHidden')) {
					$showHidden = $item->getBreadcrumbsShowHidden();
				} else {
					$showHidden = false;
				}

				$data = [
					'Title' => $item->Title,
					'Parent' => $parent,
					'ClassName' => $item->ClassName,
					'Item' => $item,
					'Breadcrumbs' => $this->DataObjectBreadcrumbs($maxDepth, $unlinked, $stopAtPageType, $showHidden)
				];

				$pageTemplate = false;

				if (isset($this->config['template']) && $this->config['template']) {
					$pageTemplate = $this->config['template'];
				}

				$this->owner->extend('updateShowData', $data, $item, $pageTemplate);

				return $this->owner
					->customise($data)
					->renderWith([$pageTemplate, $item->ClassName . 'Page', 'Page']);
			} else {
				return Security::permissionFailure();
			}
		}

		return $this->owner->httpError(404);
	}

	public function DataObjectBreadcrumbs($maxDepth = 20, $unlinked = false, $stopAtPageType = false, $showHidden = false, $page = false) {
		$pages = $this->getDataObjectBreadcrumbItems($maxDepth, $stopAtPageType, $showHidden, $page);
		$template = new SSViewer('BreadcrumbsTemplate');

		return $template->process($this->owner->customise(ArrayData::create([
			"Pages" => $pages,
			"Unlinked" => $unlinked
		])));
	}

	public function getDataObjectBreadcrumbItems($maxDepth = 20, $stopAtPageType = false, $showHidden = false, $page = false) {
		if (!$page) {
			$page = $this->getItem();
		}

		$page->ShowInMenus = true;
		$page->MenuTitle = $page->Title;
		$page->Parent = $this->owner;
		$pages = [];

		while(
			$page
			&& (!$maxDepth || count($pages) < $maxDepth)
			&& (!$stopAtPageType || $page->ClassName != $stopAtPageType)
		) {
			if ($showHidden || $page->ShowInMenus) {
				$pages[] = $page;
			}
			$page = $page->Parent;
		}

		return ArrayList::create(array_reverse($pages));
	}
}