<?php
use Stripe\Stripe;
use Stripe\Source;
use Stripe\Refund;

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

require_once(__DIR__ . '/stripe-php/vendor/autoload.php');

function stripe2wechat_config() {
    return [
        'FriendlyName' => [
            'Type' => 'System',
            'Value' => 'Stripe WePay'
        ],
        'PublishableKey' => [
            'FriendlyName' => 'Publishable key',
            'Type' => 'text',
            'Size' => 30,
        ],
        'SecretKey' => [
            'FriendlyName' => 'Secret key',
            'Type' => 'text',
            'Size' => 30,
        ],
        'WebhooksSigningSecret' => [
            'FriendlyName' => 'Webhooks signing secret key',
            'Type' => 'text',
            'Size' => 30,
        ],
        'Identifier' => [
            'FriendlyName' => 'Site identifier',
            'Type' => 'text',
            'Size' => 30,
            'Default' => '',
        ],
        'transactionFeePer' => [
            'FriendlyName' => '交易手续费百分比',
            'Type' => 'text',
            'Size' => 5,
            'Default' => 2.9,
            'Description' => '每次成功收取的交易费用的百分比（例如：2.9％）'
        ],
        'transactionFeeFixed' => [
            'FriendlyName' => '固定手续费',
            'Type' => 'text',
            'Size' => 5,
            'Default' => 30,
            'Description' => '每次成功收取的固定交易费用（例如：30美分）'
        ]
    ];
}

function stripe2wechat_link($params) {
	
    $publishableKey = $params['PublishableKey'];
    $secretKey = $params['SecretKey'];
    $webhooksSigningSecret = $params['WebhooksSigningSecret'];
    $identifier = $params['Identifier'];

    if(isset($_GET['pay'])) return '<div class="alert alert-success" role="alert">支付完成，请刷新页面或返回用户中心</div>';
	
    if(!strpos($_SERVER['PHP_SELF'], 'viewinvoice')) {
        $html = '<form action="' . $params['systemurl'] . 'viewinvoice.php' . '" method="get">';
        $html .= '<input type="hidden" name="id" value="' . $params['invoiceid'] . '" />';
        $html .= '<input type="submit" class="btn btn-primary" value="' . $params['langpaynow'] . '" /></form>';
        return $html;
    }

    Stripe::setApiKey($publishableKey);

    try {
        $source = Source::create([
            'type' => 'wechat',
            'amount' => abs($params['amount'] * 100),
            'currency' => strtolower($params['currency']),
            'metadata' => [
                'invoice_id' => $params['invoiceid'],
                'identifier' => $identifier
            ],
        ]);
    } catch (Exception $e) {
        return '<div class="alert alert-danger" role="alert">支付网关错误，请联系客服进行处理 '.$e.'</div>';
    }


    $invoiceStatus = $params['systemurl']
        .'/modules/gateways/stripe-php/wechat_status.php?invoice_id='
        . $params['invoiceid'];

    if ($source->status == 'pending') {
        $redirect = $source->wechat->qr_code_url;
        $html = <<<html
<style>
#wechat-qrcode {
    border-radius: 4px;
    padding: 5px;
    background-color: #FFF;
    display: flex;
    align-items: center;
    justify-content: center;
}
</style>
<script src="//cdn.jsdelivr.net/npm/davidshimjs-qrcodejs@0.0.2/qrcode.min.js"></script>
<div id="wechat-qrcode"></div>
<br>
<div class="alert alert-primary text-center">
请使用微信扫描二维码支付
</div>
<script>
    let wechatQrcode = document.getElementById("wechat-qrcode");
    new QRCode(wechatQrcode, {
        text: "$redirect",
        width: 220,
        height: 220
    });
    setInterval(() => {
        fetch("{$invoiceStatus}", {
            credentials: 'same-origin'
        })
            .then(e => e.json())
            .then(r => {
                if (r.invoice_status == 'Paid') {
                    window.location.reload(true)
                }
            })
            .catch()
    }, 2000)
</script>
html;
        return $html;
    }
    return '<div class="alert alert-danger" role="alert">发生错误，请创建工单联系客服处理</div>';
}

function stripe2wechat_refund($params) {
	
    $publishableKey = $params['PublishableKey'];
    $secretKey = $params['SecretKey'];
    $webhooksSigningSecret = $params['WebhooksSigningSecret'];
    $identifier = $params['Identifier'];

    Stripe::setApiKey($secretKey);

    try {
        $refund = Refund::create([
            'charge' => $params['transid'],
            'amount' => abs($params['amount'] * 100)
        ]);
        if ($refund['status'] == 'succeeded' || $refund['status'] == 'pending') {
            return [
                'status' => 'success',
                'rawdata' => $refund,
                'transid' => $refund['id'],
            ];
        } else {
            return [
                'status' => 'declined',
                'rawdata' => $refund,
                'transid' => $refund['id'],
            ];
        }

    } catch (Exception $e){
        return [
            'status' => 'error',
            'rawdata' => $e->getMessage()
        ];
    }
}