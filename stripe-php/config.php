<?php

use WHMCS\Database\Capsule;

require_once __DIR__ . '/../../../init.php';
require_once __DIR__ . '/../../../includes/gatewayfunctions.php';

$config = [
	'publishableKey',
	'secretKey',
	'webhooksSigningSecret',
	'identifier',
];

$gatewayval = getGatewayVariables('stripe2alipay');
$stripeConfig['publishableKey'] = $gatewayval['PublishableKey'];
$stripeConfig['secretKey'] = $gatewayval['SecretKey'];
$stripeConfig['webhooksSigningSecret'] = $gatewayval['WebhooksSigningSecret'];
$stripeConfig['identifier'] = $gatewayval['Identifier'];
