# WooCommerce Nested Layered Nav Widget

This overrides the [Layered Nav widget](http://docs.woothemes.com/document/woocommerce-widgets/) you get with [WooCommerce](http://www.woothemes.com/woocommerce/), and nests your child attributes beneath the parent instead of having them all on the same level.

e.g.

```
<ul>
	<li><a href="/products/?filter_types=1">Parent 1</a> <span class="count">(1)</span>
		<ul>
			<li><a href="/products/?filter_types=2">Child 1.1</a> <span class="count">(1)</span></li>
			<li><a href="/products/?filter_types=3">Child 1.2</a> <span class="count">(1)</span></li>
		</ul>
	</li>
	<li><a href="/products/?filter_types=4">Parent 2</a> <span class="count">(1)</span>
		<ul>
			<li><a href="/products/?filter_types=5">Child 2.1</a> <span class="count">(1)</span></li>
		</ul>
	</li>
</ul>
```

## Installation

#### Install with Composer

Add the package to your project’s `composer.json` file. Visit [getcomposer.org](http://getcomposer.org/) for more information.

```json
{
    "repositories": [
      {
        "type": "vcs",
        "url": "https://github.com/deadlyhifi/woocomm-nested-layered-nav"
      }
    ],
    "require": {
        "deadlyhifi/woocomm-nested-layered-nav": "1.*"
    }
}
```

#### Install Manually

Download and include the class file into your themes `functions.php` like so:

```php
include_once('WidgetNestedLayeredNav.php');
```

### Register the Widget

Place the following in your `functions.php` to replace the default WooCommerce Layered Nav widget with this one.

```php
function widget_nested_layered_nav() {
    if ( class_exists( 'WC_Widget_Layered_Nav' ) ) {
        unregister_widget( 'WC_Widget_Layered_Nav' );
        register_widget( 'WidgetNestedLayeredNav' );
    }
}
add_action( 'widgets_init', 'widget_nested_layered_nav', 15 );
```

### CSS Magic

WooCommerce adds an X in-front of a selected filter, and also adds one to the child of a selected filter, whether that child is selected or not, since it is an `a` with a parent `li.chosen`.

Use this CSS to stop that from happening.

```css
.woocommerce .widget_layered_nav ul li.chosen ul li:not(.chosen) a:before {
    content: "";
    margin-right: 0;
}
```

### Changelog

* 1.1 – Hide empty attributes, improve documentation.
* 1.0 – First version.