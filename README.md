# DataObject Link Extension
Give DataObjects an own url and a page to display them on.

## Configuration
#### 1. Extension
Add the ``DataObjectLinkExtension`` to your DataObject and the ``DataObjectLinkExtension_Controller`` to your page controller where you want the item to be displayed.

```yaml
Item:
  extensions:
    - DataObjectLinkExtension
ItemCategoryPage_Controller:
  extensions:
    - DataObjectLinkExtension_Controller
```

#### 2. config.yml

```yaml
DataObjectLinkMapping:
	mappings:
	  produkt: 
	    class: 'Item'
	    id_instead_of_slug: false
	    template: 'CoolItemPage'
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