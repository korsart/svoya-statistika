<?php
// Copy to site-stats-private/integrations.php, outside the public document root.
// extensions.php loads the interface and built-in adapter before this registry.
return [new JsonBudgetIntegration(private_dir() . '/budget.json')];
