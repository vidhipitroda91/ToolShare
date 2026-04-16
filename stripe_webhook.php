<?php
require 'config/db.php';
require 'vendor/autoload.php';
require 'config/stripe.php';
require 'includes/payment_helpers.php';

\Stripe\Stripe::setApiKey($stripeSecretKey);

$payload = @file_get_contents('php://input') ?: '';
$sigHeader = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

try {
    if ($stripeWebhookSecret !== '') {
        $event = \Stripe\Webhook::constructEvent($payload, $sigHeader, $stripeWebhookSecret);
    } else {
        $decoded = json_decode($payload, false, 512, JSON_THROW_ON_ERROR);
        if (!isset($decoded->type, $decoded->data->object)) {
            throw new RuntimeException('Invalid webhook payload.');
        }
        $event = $decoded;
    }
} catch (Throwable $e) {
    http_response_code(400);
    echo 'Webhook signature or payload error.';
    exit;
}

if (($event->type ?? '') === 'checkout.session.completed') {
    try {
        /** @var \Stripe\Checkout\Session $session */
        $session = $event->data->object;
        toolshare_finalize_stripe_checkout_session($pdo, $session);
    } catch (Throwable $e) {
        error_log('[stripe_webhook] ' . $e->getMessage());
        http_response_code(500);
        echo 'Webhook handling failed.';
        exit;
    }
}

http_response_code(200);
echo 'ok';
