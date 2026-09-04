# NetworkMonitor shared layout

The project now has reusable PHP layout partials:

- `layout/header.php` — document head and shared CSS loading
- `layout/sidebar.php` — navigation and active menu
- `layout/footer.php` — shared closing markup and JS
- `layout/page.php` — reusable page shell

Migration pattern:

```php
<?php
$pageTitle = 'Dashboard';
$activeMenu = 'dashboard';
$pageCss = ['../assets/css/dashboard.css'];
require __DIR__ . '/../layout/header.php';
?>
<div class="app-shell">
    <?php require __DIR__ . '/../layout/sidebar.php'; ?>
    <div class="app-content">
        <main class="main-content">
            <!-- page content -->
        </main>
    </div>
</div>
<?php require __DIR__ . '/../layout/footer.php'; ?>
```

Existing pages are intentionally left intact during this structural step so functionality is not changed accidentally.
