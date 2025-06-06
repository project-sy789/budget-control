<?php
// Dynamic PWA Manifest
header('Content-Type: application/json; charset=utf-8');

// Include SettingsService
require_once __DIR__ . '/../src/Services/SettingsService.php';
$settingsService = new SettingsService();
$siteConfig = $settingsService->getSiteConfig();

$manifest = [
    'name' => $siteConfig['site_title'],
    'short_name' => $siteConfig['site_name'],
    'description' => 'ระบบควบคุมงบประมาณสำหรับ' . $siteConfig['organization_name'],
    'start_url' => '/',
    'display' => 'standalone',
    'background_color' => '#ffffff',
    'theme_color' => '#667eea',
    'orientation' => 'portrait-primary',
    'scope' => '/',
    'lang' => 'th',
    'dir' => 'ltr',
    'categories' => ['business', 'finance', 'productivity'],
    'icons' => []
];

// Add icons if site icon is available
if ($siteConfig['site_icon']) {
    $iconPath = $siteConfig['site_icon'];
    
    // Generate different sizes for PWA
    $manifest['icons'] = [
        [
            'src' => $iconPath,
            'sizes' => '192x192',
            'type' => 'image/png',
            'purpose' => 'any maskable'
        ],
        [
            'src' => $iconPath,
            'sizes' => '512x512',
            'type' => 'image/png',
            'purpose' => 'any maskable'
        ],
        [
            'src' => $iconPath,
            'sizes' => '144x144',
            'type' => 'image/png',
            'purpose' => 'any'
        ],
        [
            'src' => $iconPath,
            'sizes' => '96x96',
            'type' => 'image/png',
            'purpose' => 'any'
        ],
        [
            'src' => $iconPath,
            'sizes' => '72x72',
            'type' => 'image/png',
            'purpose' => 'any'
        ],
        [
            'src' => $iconPath,
            'sizes' => '128x128',
            'type' => 'image/png',
            'purpose' => 'any'
        ],
        [
            'src' => $iconPath,
            'sizes' => '256x256',
            'type' => 'image/png',
            'purpose' => 'any'
        ]
    ];
} else {
    // Default icons if no custom icon is set
    $manifest['icons'] = [
        [
            'src' => '/assets/images/icon-72x72.png',
            'sizes' => '72x72',
            'type' => 'image/png',
            'purpose' => 'any'
        ],
        [
            'src' => '/assets/images/icon-96x96.png',
            'sizes' => '96x96',
            'type' => 'image/png',
            'purpose' => 'any'
        ],
        [
            'src' => '/assets/images/icon-128x128.png',
            'sizes' => '128x128',
            'type' => 'image/png',
            'purpose' => 'any'
        ],
        [
            'src' => '/assets/images/icon-144x144.png',
            'sizes' => '144x144',
            'type' => 'image/png',
            'purpose' => 'any'
        ],
        [
            'src' => '/assets/images/icon-192x192.png',
            'sizes' => '192x192',
            'type' => 'image/png',
            'purpose' => 'any maskable'
        ],
        [
            'src' => '/assets/images/icon-256x256.png',
            'sizes' => '256x256',
            'type' => 'image/png',
            'purpose' => 'any'
        ],
        [
            'src' => '/assets/images/icon-512x512.png',
            'sizes' => '512x512',
            'type' => 'image/png',
            'purpose' => 'any maskable'
        ]
    ];
}

echo json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
?>