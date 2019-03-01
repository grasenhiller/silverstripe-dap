# DataObject as Page

Display a DataObject as page with own url, breadcrumbs and so on.

## Configuration

#### 1. Extension
Add the ``DataObjectLinkExtension`` to your DataObject and the ``DataObjectLinkExtension_Controller`` to your page controller where you want the item to be displayed.

```yaml
Grasenhiller\Intranet\Blog\Pages\BlogHolderController:
  extensions:
    - Grasenhiller\DAP\Extensions\Controllers\DAPExtension
Grasenhiller\Intranet\Blog\Models\BlogCategory:
  extensions:
    - Grasenhiller\DAP\Extensions\Models\DAPExtension
```

#### 2. config.yml

```yaml
Grasenhiller\Intranet\Blog\Pages\BlogHolderController:
  dap_actions:
    kategorie: 'Grasenhiller\Intranet\Blog\Models\BlogCategory'
Grasenhiller\Intranet\Blog\Models\BlogCategory:
  dap_options:
    id_or_urlsegment: 'urlsegment'
    template: 'MyCustomTemplate'
    breadcrumbs_max_depth: 20
    breadcrumbs_unlinked: false
    breadcrumbs_stop_at_pagetype: false # classname or false
    breadcrumbs_show_hidden: false
```

You need to create a mapping for each URL Action. This action needs to be unique. So in this example, you can't create another one called "produkt"
For each action you must define a class (DataObject classname), if you want to use the ID as url segment instead of the slug and you are able to submit a specific template.
By default a template called "ClassNamePage" would be used. In this case "ItemPage"

**The URL Action must be unique!**

#### 3. DataObject

```php
  public function getHolderPage() {
    return $this->Page();
  }
```

If your DataObject is linked to a specific page, you could use this function to provide that page. It will be used in the Link() function. Otherwise the link would be the current page.

You could also use those methods to modify the breadcrumbs per class
- getBreadcrumbsMaxDepth()
- getBreadcrumbsUnlinked()
- getBreadcrumbsStopAtPageType()
- getBreadcrumbsShowHidden()

#### 4. dev/build
Don't forget to dev/build after adding the extension to an DataObject

## Extending
To use the extension points, you need to extend the class and apply your new class instead of the old one. Also your new class needs the same ``$allowed_actions`` and ``$url_handlers``

# Todo: Own controller

    [0] => CollItemPage
    [1] => Grasenhiller\Intranet\Blog\Models\Layout\BlogCategory
    [2] => Page
    
    $this->owner->extend('updateDAPShowAccess', $item, $access);
    
    $this->owner->extend('updateDAPShowBeforeRender', $data, $item, $templates);