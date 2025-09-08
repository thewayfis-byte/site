<?php

require '../db.php';
require '../admin/rcon/rcon/rcon.php';

$yukassa = R::findOne('yukassa', 'id = ?', ['1']);

$shop_id = $yukassa->shop_id;
$secret_key = $yukassa->secret_key;

// Получаем данные из POST запроса
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// Проверяем подпись
if (!isset($_SERVER['HTTP_AUTHORIZATION'])) {
    http_response_code(400);
    die('Missing authorization header');
}

$auth_header = $_SERVER['HTTP_AUTHORIZATION'];
if (strpos($auth_header, 'Basic ') !== 0) {
    http_response_code(400);
    die('Invalid authorization header');
}

$credentials = base64_decode(substr($auth_header, 6));
list($username, $password) = explode(':', $credentials, 2);

if ($username !== $shop_id || $password !== $secret_key) {
    http_response_code(401);
    die('Invalid credentials');
}

// Проверяем тип события
if (!isset($data['event']) || $data['event'] !== 'payment.succeeded') {
    http_response_code(200);
    die('OK');
}

$payment = $data['object'];
$payment_id = $payment['id'];
$amount = $payment['amount']['value'];
$currency = $payment['amount']['currency'];
$metadata = $payment['metadata'];

if (!isset($metadata['order_id'])) {
    http_response_code(400);
    die('Missing order_id in metadata');
}

$order_id = $metadata['order_id'];

// Находим платеж в базе данных
$post = R::findOne('payments', 'id = ?', [$order_id]);
if (!$post) {
    http_response_code(404);
    die('Payment not found');
}

// Проверяем, что платеж еще не обработан
if ($post->status === 'Оплачено') {
    http_response_code(200);
    die('OK');
}

// Обновляем статус платежа
$post->status = "Оплачено";
$post->payment_id = $payment_id;
R::store($post);

// Получаем информацию о товаре
$product = R::findOne('donate', 'id = ?', [$post->donate_id]);
if (!$product) {
    http_response_code(404);
    die('Product not found');
}

// Выполняем команду на сервере
$rcon = R::findOne('rcon', 'id = ?', ['1']);
if ($rcon) {
    $timeout = 3;
    $rcon1 = new Rcon($rcon->host, $rcon->port, $rcon->password, $timeout);
    
    $cmd = str_replace("%ИГРОК%", $post->nick, $product->cmd);
    if ($product->type == "curr") {
        $cmd = str_replace("%КОЛ%", $post->kol, $cmd);
    }
    
    if ($rcon1->connect()) {
        $rcon1->send_command($cmd);
    }
}

// Обрабатываем промокод
if ($post->promo) {
    $promo = R::findOne('promo', 'promo = ?', [$post->promo]);
    if ($promo && $promo->ogr == "on" && $promo->isp > 0) {
        $promo->isp = $promo->isp - 1;
        R::store($promo);
    }
}

http_response_code(200);
die('OK');

?>