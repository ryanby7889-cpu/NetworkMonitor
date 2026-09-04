# Router modularization

Router now declares:

```php
$pageTitle = 'Router';
$activeMenu = 'router';
$pageCss = ['../assets/css/router.css'];
```

The Router business logic is preserved. Shared CSS is loaded before Router-specific CSS.
