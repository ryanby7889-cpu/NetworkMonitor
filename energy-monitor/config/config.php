<?php

declare(strict_types=1);

// Tarif energi dalam Rupiah per kWh. Bisa diubah melalui environment variable.
define('ENERGY_TARIFF', (float)(getenv('ENERGY_TARIFF') ?: 1500));
