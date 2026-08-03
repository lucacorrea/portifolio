<?php
declare(strict_types=1);

/**
 * Entry point for installations that expose the project root.
 * Authentication and authorization remain handled by the public application.
 */
header('Location: public/login', true, 302);
exit;
