<?php

declare(strict_types=1);

if (!defined('LTECO_PROJECT_ROOT')) {
    define('LTECO_PROJECT_ROOT', dirname(__DIR__, 3));
}

if (!defined('LTECO_PANEL_PUBLIC_DIR')) {
    define('LTECO_PANEL_PUBLIC_DIR', LTECO_PROJECT_ROOT . '/lteco-panel');
}

if (!defined('LTECO_SHARED_DIR')) {
    define('LTECO_SHARED_DIR', LTECO_PROJECT_ROOT . '/shared');
}

if (!defined('LTECO_PANEL_SUPPORT_DIR')) {
    define('LTECO_PANEL_SUPPORT_DIR', __DIR__ . '/Support');
}

if (!defined('LTECO_PANEL_VIEW_INCLUDES_DIR')) {
    define('LTECO_PANEL_VIEW_INCLUDES_DIR', __DIR__ . '/View/Includes');
}
