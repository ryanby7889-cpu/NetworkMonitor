# Dashboard modularization

The Dashboard now declares its shared layout configuration:

```php
$pageTitle = 'Dashboard';
$activeMenu = 'dashboard';
$pageCss = ['../assets/css/dashboard.css'];
```

The shared layout files are ready for a full shell migration. The page's existing body/query/JavaScript logic is preserved in this step to minimize functional risk.

Next migration should move only the outer HTML shell to:

- `layout/header.php`
- `layout/sidebar.php`
- `layout/footer.php`

while keeping Dashboard-specific queries and JavaScript in the Dashboard module.
