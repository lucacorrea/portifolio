<?php
require_once __DIR__ . '/../../includes/coupons.php';

require_admin();

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Location: ' . site_url('admin/cupons.php?error=invalid'));
    exit;
}

if (!admin_csrf_is_valid($_POST['csrf_token'] ?? null)) {
    header('Location: ' . site_url('admin/cupons.php?error=csrf'));
    exit;
}

$couponId = filter_var($_POST['id'] ?? 0, FILTER_VALIDATE_INT) ?: 0;
$status = coupon_clean_text($_POST['status'] ?? '', 40);

try {
    coupon_update_status($couponId, $status);
    header('Location: ' . site_url('admin/cupons.php?success=status'));
} catch (InvalidArgumentException $exception) {
    error_log('[ArteFlor][coupon-status-validation] ' . $exception->getMessage());
    header('Location: ' . site_url('admin/cupons.php?error=invalid'));
} catch (Throwable $exception) {
    error_log('[ArteFlor][coupon-status] ' . $exception->getMessage());
    header('Location: ' . site_url('admin/cupons.php?error=status'));
}
exit;
