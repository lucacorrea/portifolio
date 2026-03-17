<?php
$styleCss = file_get_contents('c:/Users/User/Documents/portifolio/temp3_utf8.css');

// Change root colors
$styleCss = preg_replace('/--primary-color:\s*#[0-9a-fA-F]+;/', '--primary-color: #000000;', $styleCss);
$styleCss = preg_replace('/--primary-hover:\s*#[0-9a-fA-F]+;/', '--primary-hover: #333333;', $styleCss);
$styleCss = preg_replace('/--secondary-color:\s*#[0-9a-fA-F]+;/', '--secondary-color: #141414;', $styleCss);

// Force sidebar to keep yellow active states as it was
$styleCss = preg_replace(
    '/\.sidebar-menu li\.active a i \{[\s\n]*color:[^;]+;/',
    ".sidebar-menu li.active a i {\n    color: #FFC107;",
    $styleCss
);
$styleCss = preg_replace(
    '/\.sidebar-menu li:hover a,[\s\n]*\.sidebar-menu li\.active a \{[\s\n]*background:[^;]+;[\s\n]*color:[^;]+;[\s\n]*border-left-color:[^;]+;/',
    ".sidebar-menu li:hover a,\n.sidebar-menu li.active a {\n    background: rgba(255, 255, 255, 0.05);\n    color: white;\n    border-left-color: #FFC107;",
    $styleCss
);

// Also fix sidebar header logo if it had color
$styleCss = preg_replace('/\.sidebar-header i \{[\s\n]*font-size:[^;]+;[\s\n]*color:[^;]+;/', ".sidebar-header i {\n    font-size: 1.8rem;\n    color: #FFC107;", $styleCss);

file_put_contents('c:/Users/User/Documents/portifolio/erp_eletrica/style.css', $styleCss);


$corporateCss = file_get_contents('c:/Users/User/Documents/portifolio/temp4_utf8.css');

// Change root colors
$corporateCss = preg_replace('/--erp-primary:\s*#[0-9a-fA-F]+;/', '--erp-primary: #000000;', $corporateCss);
$corporateCss = preg_replace('/--erp-primary-hover:\s*#[0-9a-fA-F]+;/', '--erp-primary-hover: #333333;', $corporateCss);
$corporateCss = preg_replace('/--erp-secondary:\s*#[0-9a-fA-F]+;/', '--erp-secondary: #141414;', $corporateCss);

// Change bootstrap overrides
$corporateCss = preg_replace('/--bs-primary:\s*#[0-9a-fA-F]+;/', '--bs-primary: #000000;', $corporateCss);
$corporateCss = preg_replace('/--bs-primary-rgb:\s*[0-9,\s]+;/', '--bs-primary-rgb: 0, 0, 0;', $corporateCss);
$corporateCss = preg_replace('/--bs-link-color:\s*#[0-9a-fA-F]+;/', '--bs-link-color: #000000;', $corporateCss);
$corporateCss = preg_replace('/--bs-link-hover-color:\s*#[0-9a-fA-F]+;/', '--bs-link-hover-color: #333333;', $corporateCss);

// Change sidebar overlay
$corporateCss = preg_replace(
    '/\.sidebar \{[\s\n]*width: 260px;[\s\n]*background: #[0-9a-fA-F]+;/',
    ".sidebar {\n    width: 260px;\n    background: var(--erp-secondary);",
    $corporateCss
);

file_put_contents('c:/Users/User/Documents/portifolio/erp_eletrica/public/css/corporate.css', $corporateCss);

echo "Done\n";
