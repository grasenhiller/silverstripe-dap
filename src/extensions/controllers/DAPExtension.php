<?php

namespace Grasenhiller\DAP\Extensions\Controllers;

use InvalidArgumentException;
use SilverStripe\Control\Director;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Extension;
use SilverStripe\ORM\ArrayList;
use SilverStripe\Security\Security;
use SilverStripe\View\ArrayData;
use SilverStripe\View\SSViewer;

class DAPExtension extends Extension {

	protected $dap_config;
	protected $skip_dap_check = false;

	private static $allowed_actions = [
		'dapShow'
	];

	private static $url_handlers = [
		'//$Action!/$ID!' => 'dapShow',
	];

	public function onBeforeInit() {
		$owner = $this->owner;
		$r = $owner->request;

		$dapActions = $owner->config()->get('dap_actions');
		$urlSegment = $r->param('URLSegment');

		if (!$r->param('ID')) {
			if (isset($dapActions['index'])) {
				$owner->skip_dap_check = true;
				$owner->config()->update('url_handlers', ['//$Action!' => 'dapShow']);
			}
		} else if ($urlSegment == $r->param('Action') && isset($dapActions[$urlSegment])) {
			if (count($r->getVars())) {
				$queryString = '?' . http_build_query($r->getVars());
			} else {
				$queryString = null;
			}

			return $owner->redirect(str_replace($urlSegment . '/' . $urlSegment . '/', $urlSegment . '/', $r->getUrl()) . $queryString, 301);
		}
	}

	/**
	 * get the requested item
	 *
	 * @return mixed
	 */
	public function getDAPItem() {
		$owner = $this->owner;
		$r = $owner->request;
		$action = $r->param('Action');
		$url = $r->param('ID');

		if (
			($action && $url)
			|| ($action && $owner->skip_dap_check)
		) {
			$dapActions = $owner->config()->get('dap_actions');

			if ($owner->skip_dap_check) {
				$url = $action;
				$action = 'index';
			}

			if (is_array($dapActions) && isset($dapActions[$action])) {
				$itemClass = $dapActions[$action];
				$itemDAPConfig = Config::inst()->get($itemClass, 'dap_options');

				if (!$itemDAPConfig) {
					throw new InvalidArgumentException('Please add DAP configuration for your data object "' . $itemClass . '"', E_USER_ERROR);
				} else if (!isset($itemDAPConfig['id_or_urlsegment'])) {
					throw new InvalidArgumentException('Please add at least the "id_or_urlsegment" option for your data object "' . $itemClass . '"', E_USER_ERROR);
				} else if ($itemDAPConfig['id_or_urlsegment'] != 'urlsegment' && $itemDAPConfig['id_or_urlsegment'] != 'id') {
					throw new InvalidArgumentException('Please define "urlsegment" or "id" for the option "id_or_urlsegment" of your data object "' . $itemClass . '"', E_USER_ERROR);
				}

				$this->dap_config = $itemDAPConfig;

				if ($itemDAPConfig['id_or_urlsegment'] == 'urlsegment') {
					$field ='URLSegment';
				} else {
					$field ='ID';
				}

				$item = $itemClass::get()->find($field, $url);

				if ($item) {
					return $item;
				}
			}
		}
	}

	/**
	 * show the found item if the request is for dap or redirect to intended action
	 *
	 * @return \SilverStripe\Control\HTTPResponse
	 */
	public function dapShow() {
		$item = $this->getDAPItem();

		if ($item) {
			$access = $item->canView();
			$this->owner->extend('updateDAPShowAccess', $item, $access);
			$dapConfig = $this->dap_config;

			if ($access) {
				$parent = Director::get_current_page();

				if (isset($dapConfig['breadcrumbs_max_depth'])) {
					$maxDepth = $dapConfig['breadcrumbs_max_depth'];
				} else {
					$maxDepth = 20;
				}

				if (isset($dapConfig['breadcrumbs_unlinked'])) {
					$unlinked = $dapConfig['breadcrumbs_unlinked'];
				} else {
					$unlinked = false;
				}

				if (isset($dapConfig['breadcrumbs_stop_at_pagetype'])) {
					$stopAtPageType = $dapConfig['breadcrumbs_stop_at_pagetype'];
				} else {
					$stopAtPageType = false;
				}

				if (isset($dapConfig['breadcrumbs_show_hidden'])) {
					$showHidden = $dapConfig['breadcrumbs_show_hidden'];
				} else {
					$showHidden = false;
				}

				$data = $item->getQueriedDatabaseFields();

				$additionalData = [
					'Parent' => $parent,
					'ClassNameForTemplate' => self::get_classname_for_template($item),
					'DAPItem' => $item,
					'Breadcrumbs' => $this->getDAPBreadcrumbs($maxDepth, $unlinked, $stopAtPageType, $showHidden)
				];

				if (!isset($data['Content'])) {
					$additionalData['Content'] = '';
				}

				$data = array_merge($data, $additionalData);

				if (
					(!isset($data['Title'])
					|| (isset($data['Title']) && !$data['Title']))
					&& $item->hasMethod('getTitle')
				) {
					$data['Title'] = $item->getTitle();
				}

				if ($item->hasMethod('getDAPPageTitle')) {
					$data['Title'] = $item->getDAPPageTitle();
				}

				$templates = [];

				if (isset($dapConfig['template']) && $dapConfig['template']) {
					$templates[] = $dapConfig['template'];
				}

				$templates[] = $item->ClassName;
				$templates[] = 'Page';

				$this->owner->extend('updateDAPShowBeforeRender', $data, $item, $templates);

				return $this->owner
					->customise($data)
					->renderWith($templates);
			} else {
				return Security::permissionFailure();
			}
		} else {
			$r = $this->owner->request;
			$action = $r->param('Action');
			$directMethod = $this->owner->hasMethod($action);

			if (!$directMethod) {
				$handlers = $this->owner->config()->get('url_handlers');
				unset($handlers['$Action'], $handlers['$Action//$ID/$OtherID'], $handlers['//$Action!/$ID!']);

				foreach ($handlers as $handler => $actionName) {
					if (strpos($handler, $action) === 0) {
						if ($this->owner->hasMethod($actionName)) {
							$action = $actionName;
							break;
						}
					}

					$action = false;
				}
			}

			if ($action) {
				return $this->owner->$action();
			} else {
				return $this->owner->httpError(404);
			}
		}
	}

	/**
	 * generate the breadcrumbs
	 *
	 * @param int  $maxDepth
	 * @param bool $unlinked
	 * @param bool $stopAtPageType
	 * @param bool $showHidden
	 * @param bool $page
	 *
	 * @return \SilverStripe\ORM\FieldType\DBHTMLText
	 */
	public function getDAPBreadcrumbs($maxDepth = 20, $unlinked = false, $stopAtPageType = false, $showHidden = false, $page = false) {
		$pages = $this->owner->getDAPBreadcrumbItems($maxDepth, $stopAtPageType, $showHidden, $page);
		$template = new SSViewer('BreadcrumbsTemplate');

		return $template->process($this->owner->customise(ArrayData::create([
			'Pages' => $pages,
			'Unlinked' => $unlinked
		])));
	}

	/**
	 * get the breadcrumb items
	 *
	 * @param int  $maxDepth
	 * @param bool $stopAtPageType
	 * @param bool $showHidden
	 * @param bool $page
	 *
	 * @return ArrayList
	 */
	public function getDAPBreadcrumbItems($maxDepth = 20, $stopAtPageType = false, $showHidden = false, $page = false) {
		if (!$page) {
			$page = $this->getDAPItem();
		}

		$page->ShowInMenus = true;
		$page->MenuTitle = $page->Title;
		$page->DAPParent = $this->owner;
		$pages = [];
		$i = 0;

		while(
			$page
			&& (!$maxDepth || count($pages) < $maxDepth)
			&& (!$stopAtPageType || $page->ClassName != $stopAtPageType)
		) {
			if ($showHidden || $page->ShowInMenus) {
				$pages[] = $page;
			}

			if (!$i) {
				$page = $page->DAPParent;
			} else {
				$page = $page->Parent;
			}

			$i++;
		}

		return ArrayList::create(array_reverse($pages));
	}

	/**
	 * create an class or id name suitable for css
	 *
	 * @param $item
	 *
	 * @return string
	 */
	public static function get_classname_for_template($item) {
		$expl = explode('\\', $item->ClassName);

		if (count($expl) >= 2) {
			return $expl[0] . '_' . $expl[count($expl) - 1];
		} else {
			return $expl[0];
		}
	}
}
