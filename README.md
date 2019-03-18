# DataObject as Page

Display a DataObject as page with own url, breadcrumbs and so on.
You could either use the id of the object or an human readable url segment as link.

## Usage, example & requirements

To help you get started, we want to display our ``BlogCategory`` object on to our blog holder with the ``BlogHolderController`` controller.
The outcome should be this page: ``domain.tld/my-blog/kategorie/tolle-kategorie-1``

Your object needs either an ``MenuTitle`` or ``Title`` db field. If both are available, the ``MenuTitle`` will be used for the url segment generation.
Also you need to define a ``canView()`` function onto your object.

#### 1. Extension

Add the ``DAPExtension`` to your controller where you want the object to be displayed and to the data object that should be displayed.

```yaml
Grasenhiller\Intranet\Blog\Pages\BlogHolderController:
  extensions:
    - Grasenhiller\DAP\Extensions\Controllers\DAPExtension
Grasenhiller\Intranet\Blog\Models\BlogCategory:
  extensions:
    - Grasenhiller\DAP\Extensions\Models\DAPExtension
```

#### 2. Configuration

```yaml
Grasenhiller\Intranet\Blog\Pages\BlogHolderController:
  dap_actions:
    kategorie: 'Grasenhiller\Intranet\Blog\Models\BlogCategory'
Grasenhiller\Intranet\Blog\Models\BlogCategory:
  dap_options:
    field_for_urlsegment: 'CustomTitle'
    id_or_urlsegment: 'urlsegment'
    controller_action: 'kategorie'
    template: 'MyCustomTemplate'
    breadcrumbs_max_depth: 20
    breadcrumbs_unlinked: false
    breadcrumbs_stop_at_pagetype: false # classname or false
    breadcrumbs_show_hidden: false
```

Inside your **controller** you need to define the name of the action you want to use and its value must be the class of the data objects you want to display.
On the **data object** you need to define at least if you want to use the id or an urlsegment and the name of the controller action. **Optionally** you can define the following:

- template
- breadcrumbs_max_depth
- breadcrumbs_unlinked
- breadcrumbs_stop_at_pagetype
- breadcrumbs_show_hidden
- field_for_urlsegment

#### 3. DataObject

Onto your data object you need to define which page it should use as "holder". This is required for the URLSegmentField and for the correct generation of the link.
In our example the category has a relation called "Blog" to our ``BlogHolder``, so we will return this one.

```php
	public function getDAPHolder() {
		return $this->Blog();
	}
```

Also you can or have to (if your object has no 'Title' db field) define a method to overwrite/set the page title

```php
	public function getDAPPageTitle() {
		return $this->CustomFieldOrFunctionForPageTitleBlabla();
	}
```

#### 4. dev/build

Don't forget to dev/build after adding the extension to an data object

#### 5. Templating

You can define a custom template via yml. Otherwise the module will look for a template in the layout folder under your namespace. In our example in the folder ``Grasenhiller/Intranet/Blog/Models/Layout/BlogCategory.ss``.

DB Fields are accessible like regular. To get relations and other methods use ``$DAPItem.MyMethod``.

## Methods and fields added

Your data object has now an ``URLSegment`` field and a ``Link()``, ``AbsoluteLink()`` and ``DAPLinkingMode()`` method.

## Extending

#### Controller

To update if an item is viewable or not

``public function updateDAPShowAccess($item, &$access) {}``

To modify the data object or add additional data to the page

``public function updateDAPShowBeforeRender(&$data, $item, &$templates) {}``
    
#### DataObject

To hook into the url segment creation
    
``public function updateDAPGenerateURLSegment(&$urlSegment, &$baseString) {}``

